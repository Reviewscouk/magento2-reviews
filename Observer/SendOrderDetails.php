<?php

namespace Reviewscouk\Reviews\Observer;

use Magento\Framework as Framework;
use Reviewscouk\Reviews as Reviews;
use Magento\Catalog as Catalog;
use Magento\ConfigurableProduct as ConfigurableProduct;
use Magento\GroupedProduct as GroupedProduct;
use Magento\Store as Store;

class SendOrderDetails implements Framework\Event\ObserverInterface
{

    private $configHelper;
    private $apiModel;
    private $productModel;
    private $imageHelper;
    private $configProductModel;
    private $groupedProductModel;

    /**
     * Order IDs already sent by the "completed" trigger in this PHP process.
     * Magento does not refresh getOrigData() after a save, so an order object
     * that is saved twice in one request would otherwise look "newly complete"
     * both times.
     *
     * @var array<int|string, bool>
     */
    private $completedOrdersSent = [];

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Model\Api $api,
        Catalog\Model\ProductFactory $product,
        Catalog\Helper\Image $image,
        ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable,
        GroupedProduct\Model\Product\Type\Grouped $grouped
    ) {
        $this->configHelper = $config;
        $this->apiModel = $api;
        $this->productModel = $product;
        $this->imageHelper = $image;
        $this->configProductModel = $configurable;
        $this->groupedProductModel = $grouped;
    }

    public function execute(Framework\Event\Observer $observer)
    {
        $event = $observer->getEvent();

        if ($event->getShipment()) {
            $shipment = $event->getShipment();
            $order = $shipment->getOrder();
            $magento_store_id = $order->getStoreId();
            $trigger = $this->configHelper->getInvitationTrigger($magento_store_id);

            if ($trigger === 'shipped') {
                $this->dispatchNotification($order);
            }
            return;
        }

        if ($event->getOrder()) {
            $order = $event->getOrder();
            $magento_store_id = $order->getStoreId();
            $trigger = $this->configHelper->getInvitationTrigger($magento_store_id);

            if ($trigger === 'completed') {
                try {
                    $state = $order->getState();
                } catch (\Exception $e) {
                    $state = null;
                }

                if ($state === \Magento\Sales\Model\Order::STATE_COMPLETE && $this->hasJustCompleted($order)) {
                    $this->dispatchNotification($order);
                }
            }
        }
    }

    /**
     * True only for the save that moves the order into the "complete" state.
     *
     * sales_order_save_after fires on every save of an order, including saves
     * that do not touch the state (an order comment, or a customer changing
     * their account email, which re-saves all their past orders). Those orders
     * were loaded as "complete", so their original state is already "complete"
     * and they must not queue another invitation.
     */
    private function hasJustCompleted($order)
    {
        if ($order->getOrigData('state') === \Magento\Sales\Model\Order::STATE_COMPLETE) {
            return false;
        }

        $orderId = $order->getId();
        if ($orderId === null) {
            return true;
        }

        if (isset($this->completedOrdersSent[$orderId])) {
            return false;
        }
        $this->completedOrdersSent[$orderId] = true;

        return true;
    }

    public function dispatchNotification($order)
    {
        try {
            $magento_store_id = $order->getStoreId();

            if ($this->configHelper->getStoreId($magento_store_id) && $this->configHelper->getApiKey($magento_store_id) && $this->configHelper->isProductReviewsEnabled($magento_store_id)) {
                $items = $order->getAllVisibleItems();
                $p = array();
                foreach ($items as $item) {

                    if ($this->configHelper->isUsingGroupSkus($magento_store_id)) {
                        // If product is a variant of a grouped/configurable parent, send the
                        // parent details so all variant reviews aggregate onto the parent SKU
                        // (the on-site widget queries the parent SKU). Mirrors Feed.php:
                        // resolve grouped parents first, then configurable.
                        $productId = $item->getProduct()->getId();
                        $groupedParentIds = $this->groupedProductModel->getParentIdsByChild($productId);
                        if (!empty($groupedParentIds)) {
                            $productId = $groupedParentIds[0];
                        } else {
                            $configurableParentIds = $this->configProductModel->getParentIdsByChild($productId);
                            if (!empty($configurableParentIds)) {
                                $productId = $configurableParentIds[0];
                            }
                        }
                        $item = $this->productModel->create()->load($productId);
                    }
                    $imageUrl = $this->imageHelper->init($item, 'product_page_image_large')->getUrl();
                    // Key by SKU so multiple children of the same parent collapse to a single
                    // invitation entry once their SKUs are rolled up to the parent.
                    $p[$item->getSku()] = [
                        'image' => $imageUrl,
                        'id' => $item->getId(),
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'pageUrl' => $item->getProductUrl()
                    ];
                }
                $p = array_values($p);

                $name = $order->getCustomerName();

                if ($order->getCustomerIsGuest()) {
                    $name = $order->getBillingAddress()->getFirstName();
                }

                $productResponse = $this->apiModel->apiPost('invitation', [
                    'source'       => 'magento',
                    'name'         => $name,
                    'email'        => $order->getCustomerEmail(),
                    'order_id'     => $order->getRealOrderId(),
                    'country_code' => $order->getShippingAddress()->getCountryId(),
                    'phone'        => $order->getBillingAddress()->getTelephone(),
                    'products'     => $p
                ], $magento_store_id);

                $this->apiModel->addStatusMessage($productResponse, "Product Review Invitation");

            }
        } catch (\Exception $e) {
        }
    }
}
