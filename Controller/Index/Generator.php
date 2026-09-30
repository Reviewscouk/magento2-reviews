<?php

namespace Reviewscouk\Reviews\Model\Feed;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Api\Data\StoreInterface;
use Reviewscouk\Reviews\Helper\Config;

/**
 * Builds the REVIEWS.io product feed as a static XML file instead of
 * generating it live inside an HTTP request. Each page of products is
 * written to its own small file, then all pages are concatenated into one
 * final file, swapped into place atomically so a concurrent fetch never
 * sees a half-written feed.
 */
class Generator
{
    private const PAGE_SIZE = 1000;

    private $productCollectionFactory;
    private $imageHelper;
    private $stockModel;
    private $configHelper;
    private $directoryList;
    private $resultFactory;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        Image $imageHelper,
        StockRegistryInterface $stockRegistryInterface,
        Config $configHelper,
        DirectoryList $directoryList,
        ResultFactory $resultFactory
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper = $imageHelper;
        $this->stockModel = $stockRegistryInterface;
        $this->configHelper = $configHelper;
        $this->directoryList = $directoryList;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Generate the feed for one store and return the path it was written to.
     *
     * @throws \RuntimeException if the product feed isn't enabled for the store.
     */
    public function generate(StoreInterface $store): string
    {
        if (!$this->configHelper->isProductFeedEnabled($store->getId())) {
            throw new \RuntimeException(sprintf('Product feed is disabled for store "%s".', $store->getCode()));
        }

        $workDir = $this->getWorkDir($store);
        $this->prepareDirectory($workDir);

        // Serialize generation per store: an on-demand request can arrive
        // while cron/console generation for the same store is already
        // running, and a cache-cold burst of requests could otherwise pile
        // up several full-catalog regenerations at once. Waiting for the
        // in-flight run to finish (rather than racing it) keeps that burst
        // down to one regeneration instead of N.
        $lockFile = fopen($workDir . '/generate.lock', 'c');
        if ($lockFile === false) {
            throw new \RuntimeException(sprintf('Unable to open feed generation lock for store "%s".', $store->getCode()));
        }

        try {
            flock($lockFile, LOCK_EX);

            $pageSize = self::PAGE_SIZE;
            $totalProducts = (int) $this->productCollectionFactory->create()->getSize();
            $totalPages = (int) max(1, ceil($totalProducts / $pageSize));

            $pageFiles = [];
            for ($page = 1; $page <= $totalPages; $page++) {
                $pageFile = $workDir . '/page_' . $page . '.xml';
                $this->writePage($store, $pageSize, $page, $pageFile);
                $pageFiles[] = $pageFile;
            }

            $finalPath = $this->getFinalPath($store);
            $this->assemble($store, $pageFiles, $finalPath);

            foreach ($pageFiles as $pageFile) {
                @unlink($pageFile);
            }

            return $finalPath;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
        }
    }

    /**
     * The public path the finished feed lives at for a given store.
     */
    public function getFinalPath(StoreInterface $store): string
    {
        $mediaDir = $this->directoryList->getPath(DirectoryList::MEDIA) . '/reviewscouk';
        $this->prepareDirectory($mediaDir);
        return $mediaDir . '/product_feed_' . $store->getCode() . '.xml';
    }

    /**
     * Handle feed generation error: serve cached file if fallback enabled, else return error response.
     */
    public function handleError(StoreInterface $store)
    {
        if (!$this->configHelper->isProductFeedFallbackEnabled($store->getId())) {
            return $this->errorResponse('There was an error generating the product feed. Try enabling the feed fallback in the admin.');
        }

        $feedPath = $this->getFinalPath($store);
        if (is_file($feedPath)) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Length: ' . filesize($feedPath));
            readfile($feedPath);
            exit;
        }

        return $this->errorResponse('Your file is being generated. Please check back shortly.');
    }

    private function errorResponse(string $message)
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode(503);
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
        $result->setContents($message);
        return $result;
    }

    private function getWorkDir(StoreInterface $store): string
    {
        return $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/reviewscouk/feed/' . $store->getCode();
    }

    private function getProductCollection($pageSize, $currentPage)
    {
        $collection = $this->productCollectionFactory->create();
        $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addUrlRewrite()
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);
        return $collection;
    }

    /**
     * Write one page's <item> entries (no <rss>/<channel> wrapper) to its own file.
     */
    private function writePage(StoreInterface $store, $pageSize, $page, $pageFile): void
    {
        $handle = fopen($pageFile, 'w');
        $products = $this->getProductCollection($pageSize, $page);

        foreach ($products as $product) {
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

            $description = $product->getDescription();
            if (!is_string($description)) {
                $description = '';
            }

            if ($description === '') {
                $shortDescription = $product->getShortDescription();
                if (is_string($shortDescription)) {
                    $description = $shortDescription;
                }
            }

            fwrite($handle, "<item>
                    <g:id><![CDATA[" . $product->getSku() . "]]></g:id>
                    <magento_product_id><![CDATA[" . $product->getId() . "]]></magento_product_id>
                    <title><![CDATA[" . $product->getName() . "]]></title>
                    <link><![CDATA[" . $productUrl . "]]></link>
                    <g:price>" . (!empty($price) ? number_format($price, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:price>
                    <g:sale_price>" . (!empty($finalPrice) ? number_format($finalPrice, 2) . " " . $store->getCurrentCurrency()->getCode() : '') . "</g:sale_price>
                    <description><![CDATA[" . $description . "]]></description>
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
                    </g:shipping>");

            $categoryCollection = $product->getCategoryCollection();
            if (count($categoryCollection) > 0) {
                foreach ($categoryCollection as $category) {
                    fwrite($handle, "<g:google_product_category><![CDATA[" . $category->getName() . "]]></g:google_product_category>");
                }
            }

            $stock = $this->stockModel->getStockItem(
                $product->getId(),
                $product->getStore()->getWebsiteId()
            );
            if ($stock->getIsInStock()) {
                fwrite($handle, "<g:availability>in stock</g:availability>");
            } else {
                fwrite($handle, "<g:availability>out of stock</g:availability>");
            }

            fwrite($handle, "</item>");
        }

        fclose($handle);
        unset($products);
    }

    private function validateImageUrl($imageUrl)
    {
        return strpos($imageUrl, "Magento_Catalog/images/product/placeholder/image.jpg") === false;
    }

    /**
     * Concatenate every page file into one final feed, wrapped in the XML
     * envelope, writing to a temp path first and renaming into place so
     * readers never see a partially-written file.
     */
    private function assemble(StoreInterface $store, array $pageFiles, string $finalPath): void
    {
        $tmpPath = $finalPath . '.tmp';
        $handle = fopen($tmpPath, 'w');

        fwrite($handle, "<?xml version='1.0'?>
<rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
<channel>
<title><![CDATA[" . $store->getName() . "]]></title>
<link>" . $store->getBaseUrl() . "</link>");

        foreach ($pageFiles as $pageFile) {
            if (!is_file($pageFile)) {
                continue;
            }
            $pageHandle = fopen($pageFile, 'r');
            stream_copy_to_stream($pageHandle, $handle);
            fclose($pageHandle);
        }

        fwrite($handle, "</channel></rss>");
        fclose($handle);

        rename($tmpPath, $finalPath);
    }

    private function prepareDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
