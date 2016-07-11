<?php

namespace Reviewscouk\Reviews\Block;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;


class Richsnippet extends Framework\View\Element\Template
{
    private $_dataHelper;
    private $_configHelper;
    private $_registry;
    private $_store;

    public function __construct(Reviews\Helper\Config $config,
                                Reviews\Helper\Data $dataHelper,
                                Framework\View\Element\Template\Context $context,
                                Framework\Registry $registry,
                                array $data = [])
    {
        parent::__construct($context, $data);

        $this->_configHelper = $config;
        $this->_dataHelper = $dataHelper;
        $this->_registry = $registry;
        $this->_store = $this->_storeManager->getStore();
    }

    public function autoRichSnippet()
    {
        $merchant_enabled = $this->_configHelper->isMerchantRichSnippetsEnabled($this->_store->getId());
        $product_enabled = $this->_configHelper->isProductRichSnippetsEnabled($this->_store->getId());
        $current_product = $this->_registry->registry('current_product');

        if ($current_product && $product_enabled) {
            $sku = $this->_dataHelper->getProductSkus($current_product);
            return $this->getRichSnippet($sku);
        } elseif ($merchant_enabled) {
            return $this->getRichSnippet();
        }
        return '';
    }

    public function getRichSnippet($sku = null)
    {
        if (isset($sku) && is_array($sku)) {
            $sku = implode(';', $sku);
        }

        $region = $this->_configHelper->getRegion($this->_store->getId());
        $storeName = $this->_configHelper->getStoreId($this->_store->getId());
        $url = $region == 'us' ? 'https://widget.reviews.io/rich-snippet/dist.js' : 'https://widget.reviews.co.uk/rich-snippet/dist.js';

        $output = '<script src="' . $url . '"></script>';
        $output .= '<script>richSnippet({ store: "' . $storeName . '", sku:"' . $sku . '" })</script>';

        return $output;
    }
}