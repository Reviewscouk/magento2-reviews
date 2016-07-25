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
        Catalog\Model\Product $product,
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
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $this->dispatch_notification($order);
    }

    public function dispatch_notification($order)
    {
        try {
            $magento_store_id = $order->getStoreId();

            if ($this->configHelper->getStoreId($magento_store_id) && $this->configHelper->getApiKey($magento_store_id) && $this->configHelper->isMerchantReviewsEnabled($magento_store_id)) {
                $merchantResponse = $this->apiModel->apiPost(
                    'merchant/invitation',
                    [
                        'source' => 'magento',
                        'name' => $order->getCustomerName(),
                        'email' => $order->getCustomerEmail(),
                        'order_id' => $order->getRealOrderId(),
                    ],
                    $magento_store_id
                );
                $this->apiModel->addStatusMessage($merchantResponse, "Merchant Review Invitation");
            }

            if ($this->configHelper->getStoreId($magento_store_id) && $this->configHelper->getApiKey($magento_store_id) && $this->configHelper->isProductReviewsEnabled($magento_store_id)) {
                $items = $order->getAllVisibleItems();
                foreach ($items as $item) {
                    $item = $this->productModel->load($item->getProductId());

                    if ($this->configHelper->isUsingGroupSkus($magento_store_id)) {
                        // If product is part of a grouped product, use the grouped product details.
                        $parentIds = $this->configProductModel->getParentIdsByChild($item->getId());
                        if (!empty($parentIds)) {
                            $item = $this->productModel->load($parentIds[0]);
                        }
                    }
                    $imageUrl = $this->imageHelper->init($item, 'product_page_image_large')->getUrl();
                    $p = [
                        'image' => $imageUrl,
                        'id' => $item->getProductId(),
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'pageUrl' => $item->getProductUrl()
                    ];
                }

                $productResponse = $this->apiModel->apiPost(
                    'product/invitation',
                    [
                        'source' => 'magento',
                        'name' => $order->getCustomerName(),
                        'email' => $order->getCustomerEmail(),
                        'order_id' => $order->getRealOrderId(),
                        'products' => $p
                    ],
                    $magento_store_id
                );
                $this->apiModel->addStatusMessage($productResponse, "Product Review Invitation");

            }
        } catch (\Exception $e) {
        }
    }
}
