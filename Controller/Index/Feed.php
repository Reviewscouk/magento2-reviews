<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework as Framework;
use Magento\Catalog as Catalog;
use Magento\CatalogInventory as CatalogInventory;
use Magento\Store as Store;
use Reviewscouk\Reviews as Reviews;

class Feed extends Framework\App\Action\Action
{

    private $configHelper;
    private $cache;
    private $productModel;
    private $stockModel;
    private $imageHelper;
    private $storeModel;

    public function __construct(
        Framework\App\Action\Context $context,
        Framework\Cache\Core $core,
        Catalog\Model\Product $product,
        CatalogInventory\Api\StockRegistryInterface $stockRegistryInterface,
        Catalog\Helper\Image $image,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Reviews\Helper\Config $config,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurableType,
        \Magento\Framework\HTTP\Client\Curl $curl
     )
    {
        parent::__construct($context);

        $this->configHelper = $config;
        $this->cache = $core;
        $this->productModel = $product;
        $this->stockModel = $stockRegistryInterface;
        $this->imageHelper = $image;
        $this->storeModel = $storeManagerInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->configurableType = $configurableType;
        $this->curl = $curl;
    }

    private function getProductCollection()
    {
        $collection = $this->productCollectionFactory->create();
            /* Addtional */
            $collection
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addTaxPercents()
                ->addAttributeToSelect('*')
                ->addUrlRewrite();
            return $collection;
    }

    private function validateImageUrl($imageUrl)
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
        ];

        if(strpos($imageUrl, "Magento_Catalog/images/product/placeholder/image.jpg") !== false){
            return false;
        }

        return true;
    }

    private function validateVariantUrl($url)
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
        ];

        try {
            $this->curl->get($url);
            $this->curl->setOptions($options);
        
            if ($this->curl->getStatus() == 200) {
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function execute()
    {
        // Set timelimit to 0 to avoid timeouts when generating feed.
        ob_start();
        set_time_limit(0);

        $store = $this->storeModel->getStore();

        $productFeedEnabled = $this->configHelper->isProductFeedEnabled($store->getId());
        if ($productFeedEnabled) {
            // TODO:- Implement caching of Feed
            $productFeed = "<?xml version='1.0'?>
                    <rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
                    <channel>
                    <title><![CDATA[" . $store->getName() . "]]></title>
                    <link>" . $store->getBaseUrl() . "</link>";

            $products = $this->getProductCollection();

            foreach ($products as $product) {
                $parentProductId = null;
                $groupedParentId = null;
                $configurableParentId = null;
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                
                if ($objectManager->create('Magento\GroupedProduct\Model\Product\Type\Grouped')->getParentIdsByChild($product->getId())) {
                    $groupedParentId = $objectManager->create('Magento\GroupedProduct\Model\Product\Type\Grouped')->getParentIdsByChild($product->getId());
                }
                if ($objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId())) {
                    $configurableParentId = $objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($product->getId());
                }

                $parentId = null;
                $parentProduct = null;
                
                if (isset($groupedParentId[0])) {
                    $parentId = $groupedParentId[0];
                } else if (isset($configurableParentId[0])) {
                    $parentId = $configurableParentId[0];
                }

                // Load image url via helper.
                $productImageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();
                $imageLink = $productImageUrl;
                $productUrl = $product->getProductUrl();
                
                if (isset($parentId)) {
                    $parentProduct = $objectManager->create('Magento\Catalog\Model\Product')->load($parentId);
                    
                    $parentProductImageUrl = $this->imageHelper->init($parentProduct, 'product_page_image_large')->getUrl();
                    $validVariantImage = $this->validateImageUrl($productImageUrl);
                    if (!$validVariantImage) {
                        $imageLink = $parentProductImageUrl;
                    }

                    $productUrl = $parentProduct->getProductUrl();
                }
                
                $brand = $product->hasData('manufacturer') ? $product->getAttributeText('manufacturer') : ($product->hasData('brand') ? $product->getAttributeText('brand') : 'Not Available');
                $price = $product->getPrice();
                $finalPrice = $product->getFinalPrice();

                $productFeed .= "<item>
                        <g:id><![CDATA[" . $product->getSku() . "]]></g:id>
                        <title><![CDATA[" . $product->getName() . "]]></title>
                        <link><![CDATA[" . $productUrl . "]]></link>
                        <g:price>" . (!empty($price) ? number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:price>
                        <g:sale_price>" . (!empty($finalPrice) ? number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:sale_price>
                        <description><![CDATA[]]></description>
                        <g:condition>new</g:condition>
                        <g:image_link><![CDATA[" . $imageLink . "]]></g:image_link>
                        <g:brand><![CDATA[" . $brand . "]]></g:brand>
                        <g:mpn><![CDATA[" . ($product->hasData('mpn') ? $product->getData('mpn') : $product->getSku()). "]]></g:mpn>
                        <g:gtin><![CDATA[" . ($product->hasData('gtin') ? $product->getData('gtin') : ($product->hasData('upc') ? $product->getData('upc') : '')). "]]></g:gtin>
                        <g:product_type><![CDATA[" . $product->getTypeID() . "]]></g:product_type>
                        <g:shipping>
                        <g:country>UK</g:country>
                        <g:service>Standard Free Shipping</g:service>
                        <g:price>0 GBP</g:price>
                        </g:shipping>";

                $categoryCollection = $product->getCategoryCollection();
                if (count($categoryCollection) > 0) {
                    foreach ($categoryCollection as $category) {
                        $productFeed .= "<g:google_product_category><![CDATA[" . $category->getName() . "]]></g:google_product_category>";
                    }
                }

                $stock = $this->stockModel->getStockItem(
                    $product->getId(),
                    $product->getStore()->getWebsiteId()
                );
                if ($stock->getIsInStock()) {
                    $productFeed .= "<g:availability>in stock</g:availability>";
                } else {
                    $productFeed .= "<g:availability>out of stock</g:availability>";
                }

                $productFeed .= "</item>";
                $parentProduct = null;
            }

            $productFeed .= "</channel></rss>";

            // TODO:- Implement caching of feed

            print $productFeed;
            // exit();
        } else {
            print "Product Feed is disabled.";
        }
    }
}
