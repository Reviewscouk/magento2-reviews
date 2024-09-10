<?php

namespace Reviewscouk\Reviews\Block\Product;

use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;

class Reviewwidget extends Framework\View\Element\Template
{
    /**
     * @var SecureHtmlRenderer
     */
    private $secureHtmlRenderer;
    private $nonce;
    private $configHelper;
    private $dataHelper;
    private $registry;
    private $store;

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Helper\Data $dataHelper,
        Framework\Registry $registry,
        Framework\View\Element\Template\Context $context,
        SecureHtmlRenderer $secureHtmlRenderer,
        array $data = []
    )
    {
        parent::__construct($context, $data);

        $this->configHelper = $config;
        $this->dataHelper = $dataHelper;
        $this->registry = $registry;
        $this->secureHtmlRenderer = $secureHtmlRenderer;
        $this->store = $this->_storeManager->getStore();
    }

    public function isProductWidgetEnabled()
    {
        return $this->configHelper->isProductWidgetEnabled($this->store->getId());
    }

    public function isProductRatingSnippetWidgetEnabled()
    {
        return $this->configHelper->isProductRatingSnippetWidgetEnabled($this->store->getId());
    }

    public function isCategoryRatingSnippetWidgetEnabled()
    {
        return $this->configHelper->isCategoryRatingSnippetWidgetEnabled($this->store->getId());
    }

    public function isQuestionWidgetEnabled()
    {
        return $this->configHelper->isQuestionWidgetEnabled($this->store->getId());
    }

    public function isTabMode()
    {
        return $this->configHelper->isTabModeEnabled($this->store->getId());
    }

    public function isIframeWidget()
    {
        $productWidgetVersion = $this->configHelper->getProductWidgetVersion($this->store->getId());

        return ($productWidgetVersion == '1' ? true : false);
    }

    public function isPolarisWidget()
    {
        $productWidgetVersion = $this->configHelper->getProductWidgetVersion($this->store->getId());

        return ($productWidgetVersion == '3' ? true : false);
    }

    public function isPolarisQuestionWidget()
    {
        $questionWidgetVersion = $this->configHelper->getQuestionWidgetVersion($this->store->getId());

        return ($questionWidgetVersion == '2' ? true : false);
    }

    public function getStaticWidget()
    {
        $store_id = $this->configHelper->getStoreId($this->store->getId());
        $productSkus = $this->getProductSkus();
        $colour = $this->getWidgetColor();

        $url = $this->getWidgetURL() . '/product-seo/widget?store=' . $store_id . '&sku=' .
            urlencode(trim(implode(';', $productSkus))) . '&primaryClr=' . urlencode($colour);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $widgetHtml = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);


        if ($httpcode == 200) {
            return $widgetHtml;
        }
        return '';
    }

    public function getSettings()
    {
        $data = [
            'store_id' => $this->configHelper->getStoreId($this->store->getId()),
            'api_url' => $this->getWidgetURL(),
            'colour' => $this->getWidgetColor(),
        ];

        return $data;
    }

    public function getProductSkus()
    {
        $skus = [];

        if ($this->registry->registry('current_product')) {
            $skus = $this->dataHelper->getProductSkus($this->registry->registry('current_product'));
        }

        return $skus;
    }

    protected function getWidgetColor()
    {
        $colour = $this->configHelper->getProductWidgetColour($this->store->getId());
        // people will sometimes put hash and sometimes they will forgot so we need to check for this error
        if (isset($colour) && strpos($colour, '#') === false) {
            $colour = '#' . $colour;
        }
        // checking to see if we hare a valid colour. If not then we change it to reviews default hex colour
        if (!preg_match('/^#[a-f0-9]{6}$/i', $colour)) {
            $colour = '#5db11f';
        }
        return $colour;
    }

    protected function getWidgetURL()
    {
        $region = $this->configHelper->getRegion($this->store->getId());
        $api_url = 'widget.reviews.co.uk';
        if ($region == 'US') {
            $api_url = 'widget.reviews.io';
        }
        return $api_url;
    }

    public function getSecureHtmlRenderer()
    {
        return $this->secureHtmlRenderer;
    }
}
