<?php

namespace Reviewscouk\Reviews\Block\Product;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;

class Reviewwidget extends Framework\View\Element\Template
{

    private $_configHelper;
    private $_dataHelper;
    private $_registry;
    private $_store;

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Helper\Data $dataHelper,
        Framework\Registry $registry,
        Framework\View\Element\Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);

        $this->_configHelper = $config;
        $this->_dataHelper = $dataHelper;
        $this->_registry = $registry;

        $this->_store = $this->_storeManager->getStore();
    }

    public function isProductWidgetEnabled()
    {
        return $this->_configHelper->isProductWidgetEnabled($this->_store->getId());
    }

    public function isIframeWidget()
    {
        $productWidgetVersion = $this->_configHelper->getProductWidgetVersion($this->_store->getId());

        if ($productWidgetVersion == '2') {
            return false;
        } else {
            return true;
        }
    }

    public function getStaticWidget()
    {
        $store_id = $this->_configHelper->getStoreId($this->_store->getId());
        $productSkus = $this->getProductSkus();
        $colour = $this->getWidgetColor();

        $url = 'https://widget.reviews.co.uk/product-seo/widget?store=' . $store_id . '&sku=' .
            implode(';', $productSkus) . '&primaryClr=' . urlencode($colour);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $widgetHtml = curl_exec($ch);
        curl_close($ch);
        return $widgetHtml;
    }

    public function getSettings()
    {
        $data = [
            'store_id' => $this->_configHelper->getStoreId($this->_store->getId()),
            'api_url' => $this->getWidgetURL(),
            'colour' => $this->getWidgetColor(),
        ];

        return $data;
    }

    public function getProductSkus()
    {
        $skus = [];

        if ($this->_registry->registry('current_product')) {
            $skus = $this->_dataHelper->getProductSkus($this->_registry->registry('current_product'));
        }

        return $skus;
    }

    protected function getWidgetColor()
    {
        $colour = $this->_configHelper->getProductWidgetColour($this->_store->getId());
        // people will sometimes put hash and sometimes they will forgot so we need to check for this error
        if (strpos($colour, '#') === FALSE) $colour = '#' . $colour;
        // checking to see if we hare a valid colour. If not then we change it to reviews default hex colour
        if (!preg_match('/^#[a-f0-9]{6}$/i', $colour)) $colour = '#5db11f';
        return $colour;
    }

    protected function getWidgetURL()
    {
        $region = $this->_configHelper->getRegion($this->_store->getId());
        $api_url = 'widget.reviews.co.uk';
        if ($region == 'US') $api_url = 'widget.review.io';
        return $api_url;
    }
}
