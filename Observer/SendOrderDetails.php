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
    private $_productModel;
    private $_imageHelper;
    private $_configProductModel;
    private $_store;
    private $_messageInterface;

    public function __construct(Reviews\Helper\Config $config,
                                Catalog\Model\Product $product,
                                Catalog\Helper\Image $image,
                                ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurable,
                                Store\Model\StoreManagerInterface $storeManagerInterface,
                                Framework\Message\ManagerInterface $managerInterface)
    {
        $this->_configHelper = $config;
        $this->_productModel = $product;
        $this->_imageHelper = $image;
        $this->_configProductModel = $configurable;
        $this->_store = $storeManagerInterface->getStore();
        $this->_messageInterface = $managerInterface;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        $this->dispatch_notification($order);
    }

    public function dispatch_notification($order)
    {
        try
        {
            $magento_store_id = $order->getStoreId();

            if ($this->_configHelper->getStoreId($magento_store_id) && $this->_configHelper->getApiKey($magento_store_id) && $this->_configHelper->isMerchantReviewsEnabled($magento_store_id))
            {
                $merchantResponse = $this->apiPost('merchant/invitation', array(
                    'source' => 'magento',
                    'name' => $order->getCustomerName(),
                    'email' => $order->getCustomerEmail(),
                    'order_id' => $order->getRealOrderId(),
                ), $magento_store_id);
                $this->addStatusMessage($merchantResponse, "Merchant Review Invitation");
            }

            if ($this->_configHelper->getStoreId($magento_store_id) && $this->_configHelper->getApiKey($magento_store_id) && $this->_configHelper->isProductReviewsEnabled($magento_store_id))
            {
                $items = $order->getAllVisibleItems();
                foreach ($items as $item)
                {
                    $item = $this->_productModel->load($item->getProductId());

                    if ($this->_configHelper->isUsingGroupSkus($magento_store_id))
                    {
                        // If product is part of a grouped product, use the grouped product details.
                        $parentIds = $this->_configProductModel->getParentIdsByChild($item->getId());
                        if (!empty($parentIds))
                        {
                            $item = $this->_productModel->load($parentIds[0]);
                        }
                    }
                    $imageUrl = $this->_imageHelper->init($item, 'product_page_image_large')->getUrl();
                    $p[]   = array(
                        'image'   => $imageUrl,
                        'id'      => $item->getProductId(),
                        'sku'     => $item->getSku(),
                        'name'    => $item->getName(),
                        'pageUrl' => $item->getProductUrl()
                    );
                }

                $productResponse = $this->apiPost('product/invitation', array(
                    'source' => 'magento',
                    'name' => $order->getCustomerName(),
                    'email' => $order->getCustomerEmail(),
                    'order_id' => $order->getRealOrderId(),
                    'products' => $p
                ), $magento_store_id);
                $this->addStatusMessage($productResponse, "Product Review Invitation");

            }
        }
        catch (Exception $e)
        {
        }
    }

    protected function apiPost($url, $data, $magento_store_id=null){
        if($magento_store_id == null){
            $magento_store_id = $this->_store->getId();
        }

        $api_url = 'https://'.$this->getApiDomain($magento_store_id).'/'.$url;
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'store: '.$this->_configHelper->getStoreId($magento_store_id),
            'apikey: '.$this->_configHelper->getApiKey($magento_store_id),
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    protected function getApiDomain($magento_store_id=null){
        return $this->_configHelper->getRegion($magento_store_id) == 'US'? 'api.reviews.io' : 'api.reviews.co.uk';
    }

    private function addStatusMessage($object, $task) {
        $object = json_decode($object);
        var_dump($object);
        if($object->status == 'error') {
            $this->_messageInterface->addError($task . ' Error: ' . $object->message);
        }
    }
}