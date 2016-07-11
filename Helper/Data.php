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

    public function __construct(Framework\App\Helper\Context $context,
                                Reviews\Helper\Config $config,
                                Framework\Registry $registry)
    {
        $this->_configHelper = $config;
        $this->_registry = $registry;

        parent::__construct($context);
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