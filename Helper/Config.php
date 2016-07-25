<?php

namespace Reviewscouk\Reviews\Helper;

use Magento\Framework as Framework;
use Magento\Store as Store;

class Config extends Framework\App\Helper\AbstractHelper
{

    const XML_CONFIG_REVIEWS_REGION = "reviewscouk_reviews_setup/settings/region";
    const XML_CONFIG_API_KEY = 'reviewscouk_reviews_setup/settings/api_key';
    const XML_CONFIG_STORE_ID = 'reviewscouk_reviews_setup/settings/store_id';
    const XML_CONFIG_PRODUCT_WIDGET_ENABLED = 'reviewscouk_reviews_onpage/widget/product_widget_enabled';
    const XML_CONFIG_PRODUCT_WIDGET_VERSION = 'reviewscouk_reviews_onpage/widget/product_widget_version';
    const XML_CONFIG_PRODUCT_WIDGET_COLOUR = 'reviewscouk_reviews_onpage/widget/product_widget_colour';
    const XML_CONFIG_MERCHANT_RICH_SNIPPETS_ENABLED = 'reviewscouk_reviews_onpage/richsnippets/merchant_enabled';
    const XML_CONFIG_PRODUCT_RICH_SNIPPETS_ENABLED = 'reviewscouk_reviews_onpage/richsnippets/product_enabled';
    const XML_CONFIG_MERCHANT_REVIEWS_ENABLED = 'reviewscouk_reviews_automation/collection/merchant_enabled';
    const XML_CONFIG_PRODUCT_REVIEWS_ENABLED = 'reviewscouk_reviews_automation/collection/product_enabled';
    const XML_CONFIG_PRODUCT_FEED_ENABLED = 'reviewscouk_reviews_automation/product_feed/product_feed_enabled';
    const XML_CONFIG_USE_GROUP_SKU = "reviewscouk_reviews_advanced/settings/used_grouped_skus";

    private $config;

    public function __construct(Framework\App\Config\ScopeConfigInterface $scopeConfigInterface)
    {
        $this->config = $scopeConfigInterface;
    }

    public function getRegion($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_REVIEWS_REGION, $magentoStore);
    }

    public function getApiKey($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_API_KEY, $magentoStore);
    }

    public function getStoreId($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_STORE_ID, $magentoStore);
    }

    public function getProductWidgetVersion($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_WIDGET_VERSION, $magentoStore);
    }

    public function getProductWidgetColour($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_WIDGET_COLOUR, $magentoStore);
    }

    public function isProductWidgetEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_WIDGET_ENABLED, $magentoStore);
    }

    public function isUsingGroupSkus($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_USE_GROUP_SKU, $magentoStore);
    }

    public function isMerchantReviewsEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_MERCHANT_REVIEWS_ENABLED, $magentoStore);
    }

    public function isProductReviewsEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_REVIEWS_ENABLED, $magentoStore);
    }

    public function isMerchantRichSnippetsEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_MERCHANT_RICH_SNIPPETS_ENABLED, $magentoStore);
    }

    public function isProductRichSnippetsEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_RICH_SNIPPETS_ENABLED, $magentoStore);
    }

    public function isProductFeedEnabled($magentoStore)
    {
        return $this->getValue(self::XML_CONFIG_PRODUCT_FEED_ENABLED, $magentoStore);
    }

    /**
     * @param $code
     * @param $magentoStore
     * @return mixed
     */
    private function getValue($code, $magentoStore)
    {
        $value = $this->config->getValue($code, Store\Model\ScopeInterface::SCOPE_STORE, $magentoStore);
        return $value;
    }
}
