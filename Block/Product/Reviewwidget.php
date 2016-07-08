<?php

namespace Reviewscouk\Reviews\Block\Product;

use Reviewscouk\Reviews\Helper\Config;
use Magento\Framework\View\Element\Template\Context;
use Reviewscouk\Reviews\Helper\Data;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

class Reviewwidget extends \Magento\Framework\View\Element\Template
{

    private $_configHelper;
    private $_dataHelper;
    private $_registry;
    private $_scopeInterface;

    protected $storeId;

    public function __construct(Config $config,
                                Context $context,
                                Data $data,
                                Registry $registry,
                                ScopeInterface $scopeInterface)
    {
        $this->_configHelper = $config;
        $this->_dataHelper = $data;
        $this->_registry = $registry;
        $this->_scopeInterface = $scopeInterface;

        $this->storeId = $scopeInterface::SCOPE_STORE;

        parent::__construct($context, array());
    }

    public function isProductWidgetEnabled()
    {
        return $this->_configHelper->isProductWidgetEnabled($this->storeId);
    }

    public function isIframeWidget()
    {
        $productWidgetVersion = $this->_configHelper->getProductWidgetVersion($this->storeId);

        if ($productWidgetVersion == '2') {
            return false;
        } else {
            return true;
        }
    }

    public function getStaticWidget()
    {
        $store_id = $this->_configHelper->getStoreId($this->storeId);
        $productSkus = $this->getProductSkus();
        $colour = $this->getWidgetColor();

        $url = 'https://widget.reviews.co.uk/product-seo/widget?store=' . $store_id . '&sku=' . implode(';', $productSkus) . '&primaryClr=' . urlencode($colour);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $widgetHtml = curl_exec($ch);
        curl_close($ch);
        return $widgetHtml;
    }

    public function getSettings()
    {
        $data = array(
            'store_id' => $this->_configHelper->getStoreId($this->storeId),
            'api_url' => $this->getWidgetURL(),
            'colour' => $this->getWidgetColor(),
        );

        return $data;
    }

    protected function getProductSkus()
    {
        $skus = array();

        if ($this->_registry->registry('current_product')) {
            $skus = $this->_dataHelper->getProductSkus($this->_registry->registry('current_product'));
        }

        return $skus;
    }

    protected function getWidgetColor()
    {
        $colour = $this->_configHelper->getProductWidgetColour($this->storeId);
        // people will sometimes put hash and sometimes they will forgot so we need to check for this error
        if (strpos($colour, '#') === FALSE) $colour = '#' . $colour;
        // checking to see if we hare a valid colour. If not then we change it to reviews default hex colour
        if (!preg_match('/^#[a-f0-9]{6}$/i', $colour)) $colour = '#5db11f';
        return $colour;
    }

    protected function getWidgetURL()
    {
        $region = $this->_configHelper->getRegion($this->storeId);
        $api_url = 'widget.reviews.co.uk';
        if ($region == 'US') $api_url = 'widget.review.io';
        return $api_url;
    }

}