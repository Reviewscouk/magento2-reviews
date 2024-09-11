<?php

namespace Reviewscouk\Reviews\Block;

use Magento\Backend as Backend;
use Magento\Directory as Directory;
use Magento\Framework as Framework;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Reviewscouk\Reviews as Reviews;

class Richsnippet extends Framework\View\Element\Template
{
    private $secureHtmlRenderer;
    private $dataHelper;
    private $configHelper;
    private $registry;
    private $store;

    protected $currency;

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Helper\Data $dataHelper,
        Framework\View\Element\Template\Context $context,
        Framework\Registry $registry,
        Backend\Block\Template\Context $backend,
        Directory\Model\Currency $currency,

        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->configHelper = $config;
        $this->dataHelper   = $dataHelper;
        $this->registry     = $registry;
        $this->currency = $currency;
        $this->store = $this->_storeManager->getStore();

        if (class_exists('Magento\Framework\View\Helper\SecureHtmlRenderer')) {
            $this->secureHtmlRenderer = ObjectManager::getInstance()->get('Magento\Framework\View\Helper\SecureHtmlRenderer');
        }
    }

    public function autoRichSnippet()
    {
        $merchant_enabled = $this->configHelper->isMerchantRichSnippetsEnabled($this->store->getId());
        $product_enabled  = $this->configHelper->isProductRichSnippetsEnabled($this->store->getId());

        $current_product = $this->registry->registry('current_product');

        if ($current_product && $product_enabled) {

            $sku = $this->dataHelper->getProductSkus($current_product);


            $productAvailability = null;
            $stockStatus = $current_product->getData('quantity_and_stock_status');
            if ($stockStatus && array_key_exists('is_in_stock', $stockStatus)) {
                $productAvailability = $this->availability($stockStatus['is_in_stock']);
            }

            $product = [
                'availability'  => $productAvailability,
                'price'         => $current_product->getFinalPrice(),
                'url'         => $current_product->getProductUrl(),
                'description'         => $current_product->getMetaDescription(),
                'mpn' => ($current_product->hasData('mpn') ? $current_product->getData('mpn') : $current_product->getSku()),
                'priceCurrency' => $this->store->getDefaultCurrencyCode(),
                'brand' => ($current_product->hasData('manufacturer') ? $current_product->getAttributeText('manufacturer') : ($current_product->hasData('brand') ? $current_product->getAttributeText('brand') : 'Not Available')),
            ];

            return $this->getRichSnippet($sku, $product);
        } else if ($merchant_enabled) {
            return $this->getRichSnippet();
        }
        return '';
    }

    public function getRichSnippetSource()
    {
        $region = $this->configHelper->getRegion($this->store->getId());
        $url = $region == 'us' ? 'https://widget.reviews.io/rich-snippet/dist.js' : 'https://widget.reviews.co.uk/rich-snippet/dist.js';

        return '<script src="' . $url . '"></script>';
    }

    public function getRichSnippet($sku = null, $product = null)
    {
        if (isset($sku) && is_array($sku)) {
            $sku = implode(';', $sku);
        }

        $storeName = $this->configHelper->getStoreId($this->store->getId());
        $script = '
            richSnippet({
                store: "' . $storeName . '",
                sku:"' . $sku . '",
                data:{
                "url": "' . (isset($product['url']) ? $this->escapeHtml($product['url']) : null) . '",
                "description": `' . (isset($product['description']) ? $product['description'] : null) . '`,
                "mpn": "' . (isset($product['mpn']) ? $this->escapeHtml($product['mpn']) : null) . '",
                "offers" :[{
                    "@type":"Offer",
                    "availability": "' . (isset($product['availability']) ? $product['availability'] : null) . '",
                    "price": "' . (isset($product['price']) ? $product['price'] : null) . '",
                    "priceCurrency": "' . (isset($product['priceCurrency']) ? $product['priceCurrency'] : null) . '",
                    "url": "' . (isset($product['url']) ? $this->escapeHtml($product['url']) : null) . '",
                    "priceValidUntil": "' . date("Y-m-d", strtotime("+1 months")) . '",
                }],
                "brand": {
                "@type": "Brand",
                "name": "' . (isset($product['brand']) ? $this->escapeHtml($product['brand']) : null) . '",
                }
                }
            });
        ';

        return $this->getSecureHtmlRenderer()->renderTag('script', [], $script, false);
    }

    /**
     * Get Availability for Rich Shippets
     * @param $availability -- boolean
     *
     * @return string
     */
    private function availability($availability = true)
    {
        if ($availability == false) {
            return 'http://schema.org/OutOfStock';
        }

        return 'http://schema.org/InStock';
    }

    public function getSecureHtmlRenderer()
    {
        if (!$this->secureHtmlRenderer) {
            return new class {
                public function renderTag($type, $attributes, $content, $isText)
                {
                    return '<script> ' . $content . '</script>';
                }
            };
        }

        return $this->secureHtmlRenderer;
    }
}
