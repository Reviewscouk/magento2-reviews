<?php

namespace Reviewscouk\Reviews\Helper;

use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    private $_configHelper;
    private $_registry;
    private $_scopeInterface;

    protected $storeId;

    public function __construct(Config $config, Registry $registry, ScopeInterface $scopeInterface)
    {
        $this->_configHelper = $config;
        $this->_registry = $registry;
        $this->_scopeInterface = $scopeInterface;

        $this->storeId = $scopeInterface::SCOPE_STORE;
    }

    public function autoRichSnippet(){
        $merchant_enabled  = $this->_configHelper->isMerchantRichSnippetsEnabled($this->storeId);
        $product_enabled  = $this->_configHelper->isProductRichSnippetsEnabled($this->storeId);
        $current_product = $this->_registry->registry('current_product');

        if($current_product && $product_enabled){
            $sku = $this->getProductSkus($current_product);
            return $this->getRichSnippet($sku);
        }
        elseif($merchant_enabled){
            return $this->getRichSnippet();
        }
        return '';
    }

    public function getRichSnippet($sku=null){
        if(isset($sku) && is_array($sku)){
            $sku = implode(';',$sku);
        }

        $region = $this->_configHelper->getRegion($this->storeId);
        $storeName = $this->_configHelper->getStoreId($this->storeId);
        $url = $region == 'us'? 'https://widget.reviews.io/rich-snippet/dist.js' : 'https://widget.reviews.co.uk/rich-snippet/dist.js';

        $output = '<script src="'.$url.'"></script>';
        $output .= '<script>richSnippet({ store: "'.$storeName.'", sku:"'.$sku.'" })</script>';

        return $output;
    }

    /*
     * Product Parameter: Mage::registry('current_product')
     */
    public function getProductSkus($product){
        $sku      = $product->getSku();
        $type = $product->getTypeID();

        $productSkus = array($sku);

        if($type == 'configurable'){
            $usedProducts = $product->getTypeInstance() ->getUsedProducts();
            foreach($usedProducts as $usedProduct){
                $productSkus[] = $usedProduct->getSku();
            }
        }

        if($type == 'grouped'){
            $usedProducts = $product->getTypeInstance()->getAssociatedProducts();
            foreach($usedProducts as $usedProduct){
                $productSkus[] = $usedProduct->getSku();
            }
        }

        return $productSkus;
    }

}