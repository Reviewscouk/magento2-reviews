<?php

namespace Reviewscouk\Reviews\Observer;

use Magento\Framework as Framework;
use Reviewscouk\Reviews as Reviews;
use Magento\Catalog as Catalog;
use Magento\ConfigurableProduct as ConfigurableProduct;
use Magento\Store as Store;

class SendOrderDetails implements Framework\Event\ObserverInterface
{

    private $configHelper;
    private $apiModel;
    private $productModel;
    private $imageHelper;
    private $configProductModel;

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Model\Api $api,
        Catalog\Model\ProductFactory $product,
        Catalog\Helper\Image $image,
        ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable
    ) {
        $this->configHelper = $config;
        $this->apiModel = $api;
        $this->productModel = $product;
        $this->imageHelper = $image;
        $this->configProductModel = $configurable;
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

                if ($state === \Magento\Sales\Model\Order::STATE_COMPLETE) {
                    $this->dispatchNotification($order);
                }
            }
        }
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
                        // If product is part of a configurable product, use the configurable product details.
                        if ($item->getProduct()->getTypeId() == 'simple' || $item->getProduct()->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                            $productId = $item->getProduct()->getId();
                            $model = $this->productModel->create();
                            $item = $model->load($productId);
                        }
                    }
                    $imageUrl = $this->imageHelper->init($item, 'product_page_image_large')->getUrl();
                    $p[] = [
                        'image' => $imageUrl,
                        'id' => $item->getId(),
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'pageUrl' => $item->getProductUrl()
                    ];
                }

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
