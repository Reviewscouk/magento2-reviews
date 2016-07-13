<?php

namespace Reviewscouk\Reviews\Observer;

use Magento\Framework as Framework;
use Reviewscouk\Reviews as Reviews;
use Magento\Catalog as Catalog;
use Magento\ConfigurableProduct as ConfigurableProduct;
use Magento\Store as Store;

class SendOrderDetails implements Framework\Event\ObserverInterface
{

    private $_configHelper;
    private $_apiModel;
    private $_productModel;
    private $_imageHelper;
    private $_configProductModel;
    private $_store;

    public function __construct(Reviews\Helper\Config $config,
                                Reviews\Model\Api $api,
                                Catalog\Model\Product $product,
                                Catalog\Helper\Image $image,
                                ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable,
                                Store\Model\StoreManagerInterface $storeManagerInterface)
    {
        $this->_configHelper = $config;
        $this->_apiModel = $api;
        $this->_productModel = $product;
        $this->_imageHelper = $image;
        $this->_configProductModel = $configurable;
        $this->_store = $storeManagerInterface->getStore();
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $this->dispatch_notification($order);
    }

    public function dispatch_notification($order)
    {
        try {
            $magento_store_id = $order->getStoreId();

            if ($this->_configHelper->getStoreId($magento_store_id) && $this->_configHelper->getApiKey($magento_store_id) && $this->_configHelper->isMerchantReviewsEnabled($magento_store_id)) {
                $merchantResponse = $this->_apiModel->apiPost('merchant/invitation', array(
                    'source' => 'magento',
                    'name' => $order->getCustomerName(),
                    'email' => $order->getCustomerEmail(),
                    'order_id' => $order->getRealOrderId(),
                ), $magento_store_id);
                $this->_apiModel->addStatusMessage($merchantResponse, "Merchant Review Invitation");
            }

            if ($this->_configHelper->getStoreId($magento_store_id) && $this->_configHelper->getApiKey($magento_store_id) && $this->_configHelper->isProductReviewsEnabled($magento_store_id)) {
                $items = $order->getAllVisibleItems();
                foreach ($items as $item) {
                    $item = $this->_productModel->load($item->getProductId());

                    if ($this->_configHelper->isUsingGroupSkus($magento_store_id)) {
                        // If product is part of a grouped product, use the grouped product details.
                        $parentIds = $this->_configProductModel->getParentIdsByChild($item->getId());
                        if (!empty($parentIds)) {
                            $item = $this->_productModel->load($parentIds[0]);
                        }
                    }
                    $imageUrl = $this->_imageHelper->init($item, 'product_page_image_large')->getUrl();
                    $p[] = array(
                        'image' => $imageUrl,
                        'id' => $item->getProductId(),
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'pageUrl' => $item->getProductUrl()
                    );
                }

                $productResponse = $this->_apiModel->apiPost('product/invitation', array(
                    'source' => 'magento',
                    'name' => $order->getCustomerName(),
                    'email' => $order->getCustomerEmail(),
                    'order_id' => $order->getRealOrderId(),
                    'products' => $p
                ), $magento_store_id);
                $this->_apiModel->addStatusMessage($productResponse, "Product Review Invitation");

            }
        } catch (Exception $e) {
        }
    }
}