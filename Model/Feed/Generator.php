<?php

namespace Reviewscouk\Reviews\Model\Feed;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Reviewscouk\Reviews\Helper\Config;

/**
 * Builds the REVIEWS.io product feed page by page, either to a static file (cron/CLI) or streamed to a response.
 */
class Generator
{
    private const PAGE_SIZE = 1000;
    private const LOCK_TIMEOUT = 600;

    private $productCollectionFactory;
    private $imageHelper;
    private $stockModel;
    private $configHelper;
    private $directoryList;
    private $emulation;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        Image $imageHelper,
        StockRegistryInterface $stockRegistryInterface,
        Config $configHelper,
        DirectoryList $directoryList,
        Emulation $emulation
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper = $imageHelper;
        $this->stockModel = $stockRegistryInterface;
        $this->configHelper = $configHelper;
        $this->directoryList = $directoryList;
        $this->emulation = $emulation;
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

        // Serialize file generation per store so overlapping cron/CLI runs don't race on the same file.
        $lockFile = fopen($workDir . '/generate.lock', 'c');
        if ($lockFile === false) {
            throw new \RuntimeException(sprintf('Unable to open feed generation lock for store "%s".', $store->getCode()));
        }

        try {
            $this->acquireLock($lockFile, $store);
        } catch (\RuntimeException $e) {
            fclose($lockFile);
            throw $e;
        }

        // Generation runs from admin, cron or CLI, none of which is the
        // storefront. Emulate the target store's frontend so product/image
        // URLs, prices and currency come out as that store would show them.
        $this->emulation->startEnvironmentEmulation($store->getId(), Area::AREA_FRONTEND, true);

        try {
            $finalPath = $this->getFinalPath($store);
            $this->writeFile($store, $finalPath);

            return $finalPath;
        } finally {
            flock($lockFile, LOCK_UN);
            fclose($lockFile);
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Wait for the per-store lock, giving up after LOCK_TIMEOUT seconds so a
     * stuck run can't hang every later one.
     *
     * @param resource $lockFile
     * @throws \RuntimeException if the lock can't be acquired in time.
     */
    private function acquireLock($lockFile, StoreInterface $store): void
    {
        $deadline = microtime(true) + self::LOCK_TIMEOUT;
        do {
            if (flock($lockFile, LOCK_EX | LOCK_NB)) {
                return;
            }
            usleep(250000);
        } while (microtime(true) < $deadline);

        throw new \RuntimeException(sprintf(
            'Timed out after %d seconds waiting for the feed generation lock for store "%s".',
            self::LOCK_TIMEOUT,
            $store->getCode()
        ));
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

    private function getWorkDir(StoreInterface $store): string
    {
        return $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/reviewscouk/feed/' . $store->getCode();
    }

    // Caller must already be in the store's frontend context (i.e. a storefront request).
    public function stream(StoreInterface $store): void
    {
        $output = fopen('php://output', 'wb');
        try {
            $this->writeFeed($store, $output);
        } finally {
            fclose($output);
        }
    }

    // Keyset on entity_id: stable order, no overlapping/skipped pages, no separate count query.
    private function getProductCollection(StoreInterface $store, int $lastId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection
            ->setStoreId($store->getId())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect('*')
            ->addUrlRewrite()
            ->addAttributeToFilter('entity_id', ['gt' => $lastId])
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(self::PAGE_SIZE);
        return $collection;
    }

    /** @param resource $handle */
    private function writeFeed(StoreInterface $store, $handle): void
    {
        $this->write($handle, "<?xml version='1.0'?>
<rss version ='2.0' xmlns:g='http://base.google.com/ns/1.0'>
<channel>
<title><![CDATA[" . $store->getName() . "]]></title>
<link>" . $store->getBaseUrl() . "</link>");

        $lastId = 0;
        do {
            $products = $this->getProductCollection($store, $lastId);
            $count = count($products);
            if ($count > 0) {
                $lastId = (int) $products->getLastItem()->getId();
            }
            $this->writePageItems($store, $products, $handle);
            unset($products);
        } while ($count === self::PAGE_SIZE);

        $this->write($handle, "</channel></rss>");
    }

    /** @param resource $handle */
    private function writePageItems(StoreInterface $store, $products, $handle): void
    {
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

            $this->write($handle, "<item>
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
                    $this->write($handle, "<g:google_product_category><![CDATA[" . $category->getName() . "]]></g:google_product_category>");
                }
            }

            $stock = $this->stockModel->getStockItem(
                $product->getId(),
                $product->getStore()->getWebsiteId()
            );
            if ($stock->getIsInStock()) {
                $this->write($handle, "<g:availability>in stock</g:availability>");
            } else {
                $this->write($handle, "<g:availability>out of stock</g:availability>");
            }

            $this->write($handle, "</item>");
        }
    }

    /**
     * fwrite() returns false or a short count when the disk is full; treat
     * either as a failure rather than silently producing a truncated feed.
     */
    private function write($handle, string $data): void
    {
        $written = fwrite($handle, $data);
        if ($written === false || $written < strlen($data)) {
            throw new \RuntimeException(sprintf('Failed writing to "%s" (disk full?).', stream_get_meta_data($handle)['uri']));
        }
    }

    private function validateImageUrl($imageUrl)
    {
        return strpos($imageUrl, "Magento_Catalog/images/product/placeholder/image.jpg") === false;
    }

    /**
     * Write the feed to a temp path, then rename into place so readers never see a partial file.
     */
    private function writeFile(StoreInterface $store, string $finalPath): void
    {
        $tmpPath = $finalPath . '.tmp';
        $handle = fopen($tmpPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open "%s" for writing.', $tmpPath));
        }

        try {
            $this->writeFeed($store, $handle);

            // fclose flushes buffered data, so a full disk can surface here.
            $closed = fclose($handle);
            $handle = null;
            if (!$closed) {
                throw new \RuntimeException(sprintf('Failed flushing "%s" to disk.', $tmpPath));
            }

            if (!rename($tmpPath, $finalPath)) {
                throw new \RuntimeException(sprintf('Unable to move "%s" to "%s".', $tmpPath, $finalPath));
            }
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($tmpPath);
            throw $e;
        }
    }

    private function prepareDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $path));
        }
    }
}
