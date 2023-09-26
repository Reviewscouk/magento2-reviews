<?php
/**
 * Add Your COPPYRIGHTS here
 *
 * See COPYING.txt for license details.
 */

namespace Reviewscouk\Reviews\Observer;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Reviewscouk\Reviews\Helper\Config;
use Reviewscouk\Reviews\Model\Api;

/**
 * Observer class SendOrderDetails.
 *
 * Prepare and send info about bought items
 */
class SendOrderDetails implements ObserverInterface
{
    /**
     * SendOrderDetails Constructor
     *
     * @param Config            $configHelper
     * @param Api               $apiModel
     * @param Image             $imageHelper
     * @param CollectionFactory $productCollectionFactory
     */
    public function __construct(
        private readonly Config            $configHelper,
        private readonly Api               $apiModel,
        private readonly Image             $imageHelper,
        private readonly CollectionFactory $productCollectionFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        /** @var OrderInterface $order */
        $order = $shipment->getOrder();

        if ($this->canSendOrderDetails($order)) {
            $this->dispatchNotification($order);
        }
    }

    /**
     * Send Order data to Review
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    public function dispatchNotification(OrderInterface $order): void
    {
        $storeId = $order->getStoreId();
        /** @var OrderItemInterface[] $items */
        $orderItems = $order->getAllVisibleItems();

        $itemIds = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($orderItems as $orderItem) {
            $itemIds[] = $orderItem->getProductId();
        }
        $productCollection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'url', 'image'])
            ->addAttributeToFilter('entity_id', ['in' => $itemIds]);

        $productData = [];
        foreach ($productCollection as $item) {
            # The whole IF makes no sense. Right now, if the isUsingGroupSkus is true, then literally:
            # Check if Product is Simple OR Config. If yes, then load Product via repository (->getProduct())
            # If not, use OrderItemInterface $Item from Order.
            # Just to check its Id. Then load the same product via Model. Also the comment is not accurate at all.
            # Especially using `$order->getAllVisibleItems()`. The child products will not be provided here.
            # Consider removing that IF or refactor it to something useful.
            # And for God’s sake, do not use deprecated methods like $model->load($productId)
//                if ($this->configHelper->isUsingGroupSkus($storeId)) {
//                    // If product is part of a configurable product, use the configurable product details.
//                    if ($item->getProduct()->getTypeId() == 'simple' || $item->getProduct()->getTypeId() == Configurable::TYPE_CODE) {
//                        $productId = $item->getProduct()->getId();
//                        $model = $this->productModel->create();
//                        $item = $model->load($productId);
//                    }
//                }

            $imageUrl = $this->imageHelper->init($item, 'product_page_image_large')->getUrl();
            $productData[] = [
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

        try {
            $productResponse = $this->apiModel->apiPost('/invitation', [
                'source'       => 'magento',
                'name'         => $name,
                'email'        => $order->getCustomerEmail(),
                'order_id'     => $order->getRealOrderId(),
                'country_code' => $order->getShippingAddress()->getCountryId(),
                'products'     => $productData
            ], $storeId);

            $this->apiModel->addStatusMessage($productResponse, "Product Review Invitation");
        } catch (\Exception $e) {
        }
    }

    /**
     * Check if Order can be sent to Review
     *
     * @param OrderInterface $order
     *
     * @return bool
     */
    private function canSendOrderDetails(OrderInterface $order): bool
    {
        if ($this->configHelper->getStoreId($order->getStoreId())
            && $this->configHelper->getApiKey($order->getStoreId())
            && $this->configHelper->isProductReviewsEnabled($order->getStoreId())
        ) {
            return true;
        }
        return false;
    }
}
