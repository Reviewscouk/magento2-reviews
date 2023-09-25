<?php
/**
 * Add Your COPPYRIGHTS here
 *
 * See COPYING.txt for license details.
 */

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;
use Reviewscouk\Reviews\Helper\Config;

/**
 * Api Controller - prepare list of products and format it to json format
 */
class API implements HttpGetActionInterface
{
    /**
     * API Constructor
     *
     * @param StockRegistryInterface    $stockModel
     * @param Image                     $imageHelper
     * @param StoreManagerInterface     $storeModel
     * @param Config                    $configHelper
     * @param CollectionFactory         $productCollectionFactory
     * @param Http                      $request
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ResultFactory             $resultFactory
     */
    public function __construct(
        private readonly StockRegistryInterface $stockModel,
        private readonly Image $imageHelper,
        private readonly StoreManagerInterface $storeModel,
        private readonly Config $configHelper,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Http $request,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ResultFactory             $resultFactory,

    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \JsonException
     */
    public function execute()
    {
        $store = $this->storeModel->getStore();
        $productFeedEnabled = $this->configHelper->isProductFeedEnabled($store->getId());

        if (!$productFeedEnabled) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents(
                json_encode(
                    ['success' => false, 'message' => 'API disabled.'],
                    JSON_THROW_ON_ERROR
                )
            );

            return $result;
        }

        if($this->canAccessResource($store->getId())) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents(
                json_encode(
                    ['success' => false, 'message' => 'Unauthenticated.'],
                    JSON_THROW_ON_ERROR
                )
            );

            return $result;
        }

        ob_start();
        set_time_limit(0);

        $products = $this->getProductCollection();
        $collection = [];

        foreach ($products as $product) {
            // Load image url via helper. Refers to every occurrence in module
            $imageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();
            // Basically nested ternary operator should not be used.
            $brand = $product->hasData('manufacturer')
                ?: ($product->hasData('brand') ? $product->getAttributeText('brand') : 'Not Available');
            $price = $product->getPrice();
            $finalPrice = $product->getFinalPrice();

            $item = [
                'id' => $product->getSku(),
                'title' => $product->getName(),
                'link' => $product->getProductUrl(),
                'price' => !empty($price)
                    ? number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() : '',
                'sale_price' => !empty($finalPrice)
                    ? number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() : '',
                'image_link' => $imageUrl,
                'brand' => $brand,
                'mpn' => $product->hasData('mpn') ?: $product->getSku(),
                'gtin' => $product->hasData('gtin') ?: ($product->hasData('upc') ?: ''),
                'product_type' => $product->getTypeID(),
            ];

            $item['category'] = [];

            // Basically the same as for Feed. Moreover, it can be moved to Model or Service - Its used by Api and Feed
            $categoryIds = $product->getCategoryIds();
            try {
                $categoryCollection = $this->categoryCollectionFactory->create()
                    ->addAttributeToSelect(['name'])
                    ->addAttributeToFilter('entity_id', $categoryIds);
            } catch (\Exception $e) {
                $categoryCollection = null;
            }

            if (!is_null($categoryCollection) && count($categoryCollection) > 0) {
                foreach ($categoryCollection as $category) {
                    $item['category'][] =  $category->getName();
                }
            }

            // Basically the same as for Feed. Moreover, it can be moved to Model or Service - Its used by Api and Feed
            $stock = $this->stockModel->getStockItem(
                $product->getId(),
                $product->getStore()->getWebsiteId()
            );
            $item['in_stock'] = (bool)$stock->getIsInStock();

            $collection[] = $item;
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents(
            json_encode(
                ['success' => true, 'products' => $collection, 'total' => count($collection)],
                JSON_THROW_ON_ERROR
            )
        );

        return $result;
    }

    /**
     * Check if Controller can be accessed.
     *
     * @param int $storeId
     *
     * @return bool
     */
    private function canAccessResource(int $storeId): bool
    {
        $auth['actual_key'] = $this->configHelper->getApiKey($storeId);
        $auth['submitted_key'] = !empty($_GET['key']) ? $_GET['key'] : '';

        //Authenticate
        if(!isset($auth['submitted_key'], $auth['actual_key']) || $auth['actual_key'] != $auth['submitted_key']) {
            return false;
        }

        return true;
    }

    /**
     * Provide page of product collection
     *
     * Basically the same as for Feed. Can be moved to Model
     *
     * @return Collection
     */
    private function getProductCollection(): Collection
    {
        $page = $this->request->getParam('page') ?: '1';
        $perPage = $this->request->getParam('per_page') ?: 10;

        $collection = $this->productCollectionFactory->create();
        $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect(['name', 'manufacturer', 'gtin', 'brand', 'image', 'upc', 'mpn'])
            ->addUrlRewrite()
            ->setPageSize($perPage)
            ->setCurPage($page);

        return $collection;
    }
}
