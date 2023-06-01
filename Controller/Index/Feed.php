<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework as Framework;
use Magento\Catalog as Catalog;
use Magento\CatalogInventory as CatalogInventory;
use Magento\Store as Store;
use Reviewscouk\Reviews as Reviews;
use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Feed implements HttpGetActionInterface
{

    private $configHelper;
    private $cache;
    private $productModel;
    private $stockModel;
    private $imageHelper;
    private $storeModel;
    private $productCollectionFactory;
    private $directory;
    protected $_fileCsv;

    public function __construct(
        //Framework\App\Action\Context $context,
        Framework\Cache\Core $core,
        Catalog\Model\Product $product,
        CatalogInventory\Api\StockRegistryInterface $stockRegistryInterface,
        Catalog\Helper\Image $image,
        Store\Model\StoreManagerInterface $storeManagerInterface,
        Reviews\Helper\Config $config,
        //\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\File\Csv $fileCsv,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
     )
    {
        // parent::__construct($context);

        $this->configHelper = $config;
        $this->cache = $core;
        $this->productModel = $product;
        $this->stockModel = $stockRegistryInterface;
        $this->imageHelper = $image;
        $this->storeModel = $storeManagerInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->_moduleReader = $moduleReader;
        $this->_fileCsv = $fileCsv;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_categoryFactory = $categoryFactory;
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

    public function getCategoryName($categoryId)
    {
        $category = $this->_categoryFactory->create()->load($categoryId);
        $categoryName = $category->getName();
        return $categoryName;
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

            $products = $this->getProductCollection();

            // $productArrayHeader[] = array('id', 'title', 'link', 'price', 'sale_price', 'description', 'condition', 'image_link', 'brand', 'mpn', 'gtin', 'product_type', 'shipping_country', 'shipping_service', 'shipping_price', 'availability');
            $productArrayHeader = ['id', 'title', 'link', 'price', 'sale_price', 'description', 'condition', 'image_link', 'brand', 'mpn', 'gtin', 'product_type', 'shipping_country', 'shipping_service', 'shipping_price', 'availability', 'google_product_category', 'custom_attribute'];


            $filepath = 'export/list.csv';
            $this->directory->create('export');
            $stream = $this->directory->openFile($filepath, 'w+');
            $stream->lock();
            $header = $productArrayHeader;
            $stream->writeCsv($header);



            foreach ($products as $product) {
                // Load image url via helper.
                $imageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();                
                $price = $product->getPrice();
                $finalPrice = $product->getFinalPrice();
                $brand = $product->hasData('manufacturer') ? $product->getAttributeText('manufacturer') : ($product->hasData('brand') ? $product->getAttributeText('brand') : 'Not Available');

                $stock = $this->stockModel->getStockItem(
                    $product->getId(),
                    $product->getStore()->getWebsiteId()
                );
                $stockAvailibility = "out of stock";
                if ($stock->getIsInStock()) {
                    $stockAvailibility = "in stock";
                }

                // Add the parent product
                $productArrayBody = [
                    $product->getSku(),
                    $product->getName(),
                    $product->getProductUrl(),
                    (!empty($price) ? number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() : ''),
                    (!empty($finalPrice) ? number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() : ''),
                    "",
                    "new",
                    $imageUrl,
                    $brand,
                    ($product->hasData('mpn') ? $product->getData('mpn') : $product->getSku()),
                    ($product->hasData('gtin') ? $product->getData('gtin') : ($product->hasData('upc') ? $product->getData('upc') : '')),
                    $product->getTypeID(),
                    "UK",
                    "Standard Free Shipping",
                    "0 GBP",
                    $stockAvailibility
                ];

                $categoryIds = $product->getCategoryIds();
                $categoryNames = [];
                foreach ($categoryIds as $categoryId) {
                    array_push($categoryNames, $this->getCategoryName($categoryId));
                }
                $productArrayBody[] = implode(', ', $categoryNames);




                $attributes = $product->getAttributes();
                $attributeNames = [];
                // foreach($attributes as $attribute) {
                //     $attributeNames[] = $attribute->getName();
                // }
                // $productArrayBody[] = implode(', ', $attributeNames);
                
                foreach ($attributes as $attribute) { 
                    $attributeNames[] = $attribute->getFrontend()->getLabel();
                }
                $productArrayBody[] = implode(', ', $attributeNames);
                print_r($attributeNames);
                die();



                $stream->writeCsv($productArrayBody);
            }

            // TODO:- Implement caching of feed

            // exit();

            
            $csvContents = array_merge($productArrayHeader, $productArrayBody);
            print_r($productArrayHeader);
            print_r($productArrayBody);

            $resultJson = $this->resultJsonFactory->create();
            return $resultJson->setData(['data' => $csvContents]);


        } else {
            print "Product Feed is disabled.";
        }
    }
}