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
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
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

    public function execute()
    {
        // Set timelimit to 0 to avoid timeouts when generating feed.
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
                $brand = $product->getAttributeText('manufacturer') ? $product->getAttributeText('manufacturer') : 'Not Available';
                $price = $product->getPrice();

                $finalPrice = $product->getFinalPrice();

                $productFeed .= "<item>
                        <g:id><![CDATA[" . $product->getSku() . "]]></g:id>
                        <title><![CDATA[" . $product->getName() . "]]></title>
                        <link>" . $product->getProductUrl() . "</link>
                        <g:price>" . number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() . "</g:price>
                        <g:sale_price>" . number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() . "</g:sale_price>
                        <description><![CDATA[]]></description>
                        <g:condition>new</g:condition>
                        <g:image_link>" . $product->getImageUrl() . "</g:image_link>
                        <g:brand><![CDATA[" . $brand . "]]></g:brand>
                        <g:mpn><![CDATA[" . $product->getSku() . "]]></g:mpn>
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
            }

            $productFeed .= "</channel></rss>";

            // TODO:- Implement caching of feed

            echo $productFeed;
            exit();
        } else {
            echo "Product Feed is disabled.";
        }
    }
}
