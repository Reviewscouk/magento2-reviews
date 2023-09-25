<?php
/**
 * Add Your COPPYRIGHTS here
 *
 * See COPYING.txt for license details.
 */

namespace Reviewscouk\Reviews\Controller\Index;

use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableTypeResourceModel;
use Magento\Framework;
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
    private Config $configHelper;
    private StockRegistryInterface $stockModel;
    private Image $imageHelper;
    private StoreManagerInterface $storeModel;
    private CollectionFactory $productCollectionFactory;
    private ResultFactory $resultFactory;
    private Grouped $groupedProductModel;
    private ConfigurableTypeResourceModel $configurableProductModel;
    private ProductRepositoryInterfaceFactory $productRepositoryFactory;

    /**
     * Feed Constructor
     *
     * @param StockRegistryInterface            $stockRegistryInterface
     * @param Image                             $image
     * @param StoreManagerInterface             $storeManagerInterface
     * @param Config                            $config
     * @param CollectionFactory                 $productCollectionFactory
     * @param ResultFactory                     $resultFactory
     * @param Grouped                           $groupedProductModel
     * @param ConfigurableTypeResourceModel     $configurableProductModel
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     */
    public function __construct(
        StockRegistryInterface          $stockRegistryInterface, // StockRegistryInterface is deprecated.
        Image                           $image,
        StoreManagerInterface           $storeManagerInterface,
        Config                          $config,
        CollectionFactory               $productCollectionFactory,
        ResultFactory                   $resultFactory,
        Grouped                         $groupedProductModel,
        ConfigurableTypeResourceModel   $configurableProductModel,
        ProductRepositoryInterfaceFactory $productRepositoryFactory
    ) {
        $this->configHelper = $config;
        $this->stockModel = $stockRegistryInterface;
        $this->imageHelper = $image;
        $this->storeModel = $storeManagerInterface;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->resultFactory = $resultFactory;
        $this->groupedProductModel = $groupedProductModel;
        $this->configurableProductModel = $configurableProductModel;
        $this->productRepositoryFactory = $productRepositoryFactory;
    }

    /**
     * Return feed of all products
     *
     * @return Framework\App\ResponseInterface|Framework\Controller\Result\Raw
     * @throws Framework\Exception\LocalizedException
     * @throws Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $store = $this->storeModel->getStore();

        $productFeedEnabled = $this->configHelper->isProductFeedEnabled($store->getId());
        if (!$productFeedEnabled) {
            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents("Product Feed is disabled");

            return $result;
        }

        // Set timelimit to 0 to avoid timeouts when generating feed.
        ob_start();
        set_time_limit(0); // even with that, timeout can be thrown with many products.

        // TODO:- Implement caching of Feed
        $productFeed = "<?xml version='1.0'?>
                <rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
                <channel>
                <title><![CDATA[" . $store->getName() . "]]></title>
                <link>" . $store->getBaseUrl() . "</link>";

        $page = 0;
        do {
            $productCollection = $this->getProductCollection($page);

            foreach ($productCollection as $product) {

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

                // Load image url via helper.
                $productImageUrl = $this->imageHelper->init($product, 'product_page_image_large')->getUrl();
                $imageLink = $productImageUrl;
                $productUrl = $product->getProductUrl();

                if (isset($parentId)) {
                    $parentProduct = $this->productRepositoryFactory->create()->getById($parentId);

                    $parentProductImageUrl = $this->imageHelper
                        ->init($parentProduct, 'product_page_image_large')->getUrl();

                    $validVariantImage = $this->validateImageUrl($productImageUrl);
                    if (!$validVariantImage) {
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
                    <g:mpn><![CDATA[" . ($product->hasData('mpn') ? $product->getData('mpn') : $product->getSku()) . "]]></g:mpn>
                    <g:gtin><![CDATA[" . ($product->hasData('gtin') ? $product->getData('gtin') : ($product->hasData('upc') ? $product->getData('upc') : '')) . "]]></g:gtin>
                    <g:product_type><![CDATA[" . $product->getTypeID() . "]]></g:product_type>
                    <g:shipping>
                    <g:country>UK</g:country>
                    <g:service>Standard Free Shipping</g:service>
                    <g:price>0 GBP</g:price>
                    </g:shipping>";

                $categoryCollection = $product->getCategoryCollection();
                if (count($categoryCollection) > 0) {
                    foreach ($categoryCollection as $category) {
                        $productFeed .= sprintf(
                            "<g:google_product_category><![CDATA[%s]]></g:google_product_category>",
                            $category->getName()
                        );
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

            $page++;
        } while ($productCollection->count());

        $productFeed .= "</channel></rss>";

        // TODO:- Implement caching of feed
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($productFeed);

        ob_end_clean();

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

        $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addUrlRewrite()
            ->setPageSize(100)
            ->setCurPage($page);

        return $collection;
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
