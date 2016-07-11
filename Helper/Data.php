<?php

namespace Reviewscouk\Reviews\Helper;

//use Magento\Framework\Registry;
use Magento\Framework as Framework;
use Reviewscouk\Reviews as Reviews;
use Magento\Store as Store;

class Data extends Framework\App\Helper\AbstractHelper
{

    private $_configHelper;
    private $_registry;
    private $_storeModel;

    protected $store;

    public function __construct(Framework\App\Helper\Context $context,
                                Reviews\Helper\Config $config,
                                Framework\Registry $registry,
                                Store\Model\StoreManagerInterface $storeManagerInterface)
    {
        $this->_configHelper = $config;
        $this->_registry = $registry;
        $this->_storeModel = $storeManagerInterface;

        $this->store = $this->_storeModel->getStore();

        parent::__construct($context);
    }

    public function autoRichSnippet()
    {
        $merchant_enabled = $this->_configHelper->isMerchantRichSnippetsEnabled($this->store->getId());
        $product_enabled = $this->_configHelper->isProductRichSnippetsEnabled($this->store->getId());
        $current_product = $this->_registry->registry('current_product');

        if ($current_product && $product_enabled) {
            $sku = $this->getProductSkus($current_product);
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

        $region = $this->_configHelper->getRegion($this->store->getId());
        $storeName = $this->_configHelper->getStoreId($this->store->getId());
        $url = $region == 'us' ? 'https://widget.reviews.io/rich-snippet/dist.js' : 'https://widget.reviews.co.uk/rich-snippet/dist.js';

        $output = '<script src="' . $url . '"></script>';
        $output .= '<script>richSnippet({ store: "' . $storeName . '", sku:"' . $sku . '" })</script>';

        return $output;
    }

    public function getProductSkus($product)
    {
        $sku = $product->getSku();
        $type = $product->getTypeID();

        $productSkus = array($sku);
        if ($type == 'configurable') {
            $usedProducts = $product->getTypeInstance()->getUsedProducts();
            foreach ($usedProducts as $usedProduct) {
                $productSkus[] = $usedProduct->getSku();
            }
        }

        if ($type == 'grouped') {
            $usedProducts = $product->getTypeInstance()->getAssociatedProducts();
            foreach ($usedProducts as $usedProduct) {
                $productSkus[] = $usedProduct->getSku();
            }
        }

        return $productSkus;
    }
}