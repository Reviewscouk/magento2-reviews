<?php

namespace Reviewscouk\Reviews\Block;

use Reviewscouk\Reviews as Reviews;
use Magento\Framework as Framework;

class Richsnippet extends Framework\View\Element\Template
{
    private $dataHelper;
    private $configHelper;
    private $registry;
    private $store;

    public function __construct(
        Reviews\Helper\Config $config,
        Reviews\Helper\Data $dataHelper,
        Framework\View\Element\Template\Context $context,
        Framework\Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->configHelper = $config;
        $this->dataHelper = $dataHelper;
        $this->registry = $registry;
        $this->store = $this->_storeManager->getStore();
    }

    public function autoRichSnippet()
    {
        $merchant_enabled = $this->configHelper->isMerchantRichSnippetsEnabled($this->store->getId());
        $product_enabled = $this->configHelper->isProductRichSnippetsEnabled($this->store->getId());
        $current_product = $this->registry->registry('current_product');

        if ($current_product && $product_enabled) {
            $sku = $this->dataHelper->getProductSkus($current_product);
            return $this->getRichSnippet($sku);
        } else if ($merchant_enabled) {
            return $this->getRichSnippet();
        }
        return '';
    }

    public function getRichSnippet($sku = null)
    {
        if (isset($sku) && is_array($sku)) {
            $sku = implode(';', $sku);
        }

        $region = $this->configHelper->getRegion($this->store->getId());
        $storeName = $this->configHelper->getStoreId($this->store->getId());
        $url = $region == 'us' ? 'https://widget.reviews.io/rich-snippet/dist.js' : 'https://widget.reviews.co.uk/rich-snippet/dist.js';

        $output = '<script src="' . $url . '"></script>';
        $output .= '<script>richSnippet({ store: "' . $storeName . '", sku:"' . $sku . '" })</script>';

        return $output;
    }
}
