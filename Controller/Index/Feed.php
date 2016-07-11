<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework;
use Magento\Catalog;
use Magento\Store;
use Reviewscouk\Reviews;

class Feed extends Framework\App\Action\Action
{

    protected $_configHelper;
    protected $_data;
    protected $_cache;
    protected $_productModel;
    protected $_imageHelper;
    protected $_storeModel;

    public function __construct(Framework\App\Action\Context $context,
                                Framework\Controller\Result\JsonFactory $resultJsonFactory,
                                Framework\Cache\Core $core,
                                Catalog\Mode\Product $product,
                                Catalog\Helper\Image $image,
                                Store\Model\StoreManagerInterface $storeManagerInterface,
                                Reviews\Helper\Config $config,
                                Reviews\Helper\Data $data)
    {
        parent::__construct($context);

        $this->_configHelper = $config;
        $this->_helper = $data;
        $this->_cache = $core;
        $this->_productModel = $product;
        $this->_imageHelper = $image;
        $this->_storeModel = $storeManagerInterface;
    }

    public function execute()
    {
        $productFeedEnabled = $this->_configHelper->isProductFeedEnabled(Mage::app()->getStore());
        if ($productFeedEnabled)
        {
            $saveCached = $this->_cache->load("feed");
            if(!$saveCached)
            {
                $store = $this->_storeModel->getStore();

                $productFeed = "<?xml version='1.0'?>
						<rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
						<channel>
						<title><![CDATA[" . $store->getName() . "]]></title>
						<link>" . $store->getBaseUrl() . "</link>";

                $products = $this->_productModel->getCollection();
                foreach ($products as $prod)
                {
                    $product = $this->_productModel->load($prod->getId());

                    $brand = $product->getAttributeText('manufacturer') ? $product->getAttributeText('manufacturer') : 'Not Available';

                    $price      = $product->getPrice();
                    $finalPrice = $product->getFinalPrice();

                    $productFeed .= "<item>
							<g:id><![CDATA[" . $product->getSku() . "]]></g:id>
							<title><![CDATA[" . $product->getName() . "]]></title>
							<link>" . $product->getProductUrl() . "</link>
							<g:price>" . number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() . "</g:price>
							<g:sale_price>" . number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() . "</g:sale_price>
							<description><![CDATA[" . $product->getDescription() . "]]></description>
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
                    if (count($categoryCollection) > 0)
                    {
                        foreach ($categoryCollection as $category)
                        {
                            $productFeed .= "<g:google_product_category><![CDATA[" . $category->getName() . "]]></g:google_product_category>";
                        }
                    }

                    $stock = $product->getStockItem();
                    if ($stock->getIsInStock())
                    {
                        $productFeed .= "<g:availability>in stock</g:availability>";
                    }
                    else
                    {
                        $productFeed .= "<g:availability>out of stock</g:availability>";
                    }

                    $productFeed .= "</item>";
                }

                $productFeed .= "</channel></rss>";

                $this->_cache->save($productFeed, "feed", array("reviews_feed_cache"), 86400);
            }
            else
            {
                $productFeed = $saveCached;
            }

            echo $productFeed;
            exit();
        }
        else
        {
            echo "Product Feed is disabled.";
        }
    }


}