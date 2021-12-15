<?php

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Framework as Framework;
use Magento\Catalog as Catalog;
use Magento\CatalogInventory as CatalogInventory;
use Magento\Store as Store;
use Reviewscouk\Reviews as Reviews;

class API extends Framework\App\Action\Action
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

    private function getProductCollection($page, $perPage)
    {
        $collection = $this->productCollectionFactory->create();
            /* Addtional */
            $collection
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addTaxPercents()
                ->addAttributeToSelect('*')
                ->addUrlRewrite()
                ->setPageSize($perPage)
                ->setCurPage($page);
            return $collection;
    }

    public function execute()
    {
        ob_start();
        set_time_limit(0);

        $store = $this->storeModel->getStore();
        $productFeedEnabled = $this->configHelper->isProductFeedEnabled($store->getId());
        $auth['actual_key'] = $this->configHelper->getApiKey($store->getId());

        if ($productFeedEnabled) {

            $auth['page'] = !empty($_GET['page']) ? $_GET['page'] : '1';
            $auth['per_page'] = !empty($_GET['per_page']) ? (int) $_GET['per_page'] : 100;
            $auth['submitted_key'] = !empty($_GET['key']) ? $_GET['key'] : '';

            //Authenticate
            if(!isset($auth['submitted_key'], $auth['actual_key']) || $auth['actual_key'] != $auth['submitted_key']) {
              echo json_encode(array('success' => false, 'message' => 'Unauthenticated.'));
              die();
            }

            $products = $this->getProductCollection($auth['page'], $auth['per_page']);

            $collection = [];

            foreach ($products as $product) {
                // Load image url via helper.
                $imageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();
                $brand = $product->hasData('manufacturer') ? $product->getAttributeText('manufacturer') : ($product->hasData('brand') ? $product->getAttributeText('brand') : 'Not Available');
                $price = $product->getPrice();

                $finalPrice = $product->getFinalPrice();

                $item = [
                  'id' => $product->getSku(),
                  'title' => $product->getName(),
                  'link' => $product->getProductUrl(),
                  'price' => number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode(),
                  'sale_price' => number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode(),
                  'image_link' => $imageUrl,
                  'brand' => $brand,
                  'mpn' => ($product->hasData('mpn') ? $product->getData('mpn') : $product->getSku()),
                  'gtin' => ($product->hasData('gtin') ? $product->getData('gtin') : ''),
                  'product_type' => $product->getTypeID(),
                ];

                $item['category'] = [];

                $categoryCollection = $product->getCategoryCollection();
                if (count($categoryCollection) > 0) {
                    foreach ($categoryCollection as $category) {
                        $item['category'][] =  $category->getName();
                    }
                }

                $stock = $this->stockModel->getStockItem(
                    $product->getId(),
                    $product->getStore()->getWebsiteId()
                );

                $item['in_stock'] = $stock->getIsInStock() ? true : false;

                $collection[] = $item;
            }

            echo json_encode(array('success' => true, 'products' => $collection, 'total' => count($collection)));
            die();

        } else {
          echo json_encode(array('success' => false, 'message' => 'API disabled.'));
          die();
        }
    }
}
