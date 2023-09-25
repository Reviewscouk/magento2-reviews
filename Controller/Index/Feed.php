<?php
/**
 * Add Your COPPYRIGHTS here
 *
 * See COPYING.txt for license details.
 */

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableTypeResourceModel;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Store\Model\StoreManagerInterface;
use Reviewscouk\Reviews\Helper\Config;

/**
 * Feed Controller - prepare list of products and format it to xml format
 */
class Feed implements HttpGetActionInterface
{
    /**
     * Constructor for Feed
     *
     * @param StockRegistryInterface            $stockModel
     * @param Image                             $imageHelper
     * @param StoreManagerInterface             $storeModel
     * @param Config                            $configHelper
     * @param CollectionFactory                 $productCollectionFactory
     * @param ResultFactory                     $resultFactory
     * @param Grouped                           $groupedProductModel
     * @param ConfigurableTypeResourceModel     $configurableProductModel
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param CategoryCollectionFactory         $categoryCollectionFactory
     */
    public function __construct(
        private readonly StockRegistryInterface            $stockModel, // StockRegistryInterface is deprecated.
        private readonly Image                             $imageHelper,
        private readonly StoreManagerInterface             $storeModel,
        private readonly Config                            $configHelper,
        private readonly CollectionFactory                 $productCollectionFactory,
        private readonly ResultFactory                     $resultFactory,
        private readonly Grouped                           $groupedProductModel,
        private readonly ConfigurableTypeResourceModel     $configurableProductModel,
        private readonly ProductRepositoryInterfaceFactory $productRepositoryFactory,
        private readonly CategoryCollectionFactory         $categoryCollectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $store = $this->storeModel->getStore();

        if (!$this->configHelper->isProductFeedEnabled($store->getId())) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents("Product Feed is disabled");

            return $result;
        }

        // Set timelimit to 0 to avoid timeouts when generating feed.
        ob_start();
        set_time_limit(0);
        // Basically not good solution. Even with that, timeout can be thrown with many products.
        // Consider using pagination as for API Controller and remove the ob_start and set_time_limit
        // Eventually create cron, that would create the Feed in the background and the controller would only display it

        // TODO:- Implement caching of Feed
        $productFeed = "<?xml version='1.0'?>
<rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
    <channel>
        <title><![CDATA[" . $store->getName() . "]]></title>
        <link>" . $store->getBaseUrl() . "</link>";

        $page = 0;
        do {
            $productCollection = $this->getProductCollection($page);

            /** @var ProductInterface $product */
            foreach ($productCollection as $product) {

                // Load image url via helper.
                $productImageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();
                $imageLink = $productImageUrl;
                $productUrl = $product->getProductUrl();

                $parentProduct = $this->provideParentProduct($product);
                if (!is_null($parentProduct)) {
                    $parentProductImageUrl = $this->imageHelper
                        ->init($parentProduct, 'product_page_image_large')->getUrl();

                    if (!$this->validateImageUrl($productImageUrl)) {
                        $imageLink = $parentProductImageUrl;
                    }

                    $productUrl = $parentProduct->getProductUrl();
                }

                if ($product->hasData('brand')) {
                    $brand = $product->hasData('manufacturer') ? $product->getAttributeText('manufacturer')
                        : ($product->getAttributeText('brand'));
                } else {
                    $brand = $product->hasData('manufacturer') ? $product->getAttributeText('manufacturer')
                        : ('Not Available');
                }

                $price = $product->getPrice();
                $finalPrice = $product->getFinalPrice();

                // I dont think, UK should be hardcoded as Shipping. The implementation of it is not very pretty.
                $productFeed .= "
        <item>
            <g:id><![CDATA[" . $product->getSku() . "]]></g:id>
            <title><![CDATA[" . $product->getName() . "]]></title>
            <link><![CDATA[" . $productUrl . "]]></link>
            <g:price>" . (!empty($price) ? number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:price>
            <g:sale_price>" . (!empty($finalPrice) ? number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:sale_price>
            <description><![CDATA[]]></description>
            <g:condition>new</g:condition>
            <g:image_link><![CDATA[" . $imageLink . "]]></g:image_link>
            <g:brand><![CDATA[" . $brand . "]]></g:brand>
            <g:mpn><![CDATA[" . ($product->hasData('mpn') ?: $product->getSku()) . "]]></g:mpn>
            <g:gtin><![CDATA[" . ($product->hasData('gtin') ?: ($product->hasData('upc') ?: '')) . "]]></g:gtin>
            <g:product_type><![CDATA[" . $product->getTypeID() . "]]></g:product_type>
            <g:shipping>
            <g:country>UK</g:country>
            <g:service>Standard Free Shipping</g:service>
            <g:price>0 GBP</g:price>
            </g:shipping>";

                // If You really need to provide also Category names, the Category collection have to be loaded as well.
                // It is not very optimized for many products.
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
                        $productFeed .= sprintf(
                            "\n\t\t\t<g:google_product_category><![CDATA[%s]]></g:google_product_category>",
                            $category->getName()
                        );
                    }
                }

                // The StockRegistryInterface is deprecated. Implemented logic will not reflect real Stock Status.
                // See https://developer.adobe.com/commerce/php/development/components/web-api/inventory-management/
                // For quicker resolve, consider using $product->isSalable() instead $stock->getIsInStock()
                // Otherwise if Shop uses MSI load StockInventories and check availability there
                $stock = $this->stockModel->getStockItem(
                    $product->getId(),
                    $product->getStore()->getWebsiteId()
                );
                if ($stock->getIsInStock()) {
                //if ($product->isSalable()) {
                    $productFeed .= "\n\t\t\t<g:availability>in stock</g:availability>";
                } else {
                    $productFeed .= "\n\t\t\t<g:availability>out of stock</g:availability>";
                }

                $productFeed .= "\n\t\t</item>";
            }

            $page++;
        } while ($productCollection->count());

        $productFeed .= "\n\t</channel>\n</rss>";

        // TODO:- Implement caching of feed
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($productFeed);

        ob_end_clean(); //Should occur when using ob_start

        return $result;
    }

    /**
     * Provide page of product collection
     *
     * @param int $page
     *
     * @return Collection
     */
    private function getProductCollection(int $page): Collection
    {
        $collection = $this->productCollectionFactory->create();

        // If You want to use Collection, do not load all Attributes if not required. Especially with big collection.
        // Add just required attributes.
        $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect(['name', 'manufacturer', 'gtin', 'brand', 'image', 'upc', 'mpn'])
            ->addUrlRewrite()
            ->setPageSize(2)
            ->setCurPage($page);

        return $collection;
    }

    /**
     * Provide Parent Product if available
     *
     * @param ProductInterface $product
     *
     * @return ProductInterface|null
     */
    private function provideParentProduct(ProductInterface $product): ?ProductInterface
    {
        $parentId = null;
        if ($this->groupedProductModel->getParentIdsByChild($product->getId())) {
            $groupedParentId = $this->groupedProductModel->getParentIdsByChild($product->getId());
            if (isset($groupedParentId[0])) {
                $parentId = $groupedParentId[0];
            }
        }
        if ($this->configurableProductModel->getParentIdsByChild($product->getId())) {
            $configurableParentId = $this->configurableProductModel->getParentIdsByChild($product->getId());
            if (isset($configurableParentId[0])) {
                $parentId = $configurableParentId[0];
            }
        }

        $parentProduct = null;
        if (isset($parentId)) {
            try {
                $parentProduct = $this->productRepositoryFactory->create()->getById($parentId);
            } catch (\Exception $e) {
            }
        }

        return $parentProduct;
    }

    /**
     * Validate Image Url
     *
     * @param string $imageUrl
     *
     * @return bool
     */
    private function validateImageUrl(string $imageUrl): bool
    {
        if (str_contains($imageUrl, "Magento_Catalog/images/product/placeholder/image.jpg")) {
            return false;
        }

        return true;
    }
}
