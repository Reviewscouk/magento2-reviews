<?php

namespace Reviewscouk\Reviews\Helper;

use Magento\Framework as Framework;

class Data extends Framework\App\Helper\AbstractHelper
{

    public function getProductSkus($product)
    {
        $sku = $product->getSku();
        $type = $product->getTypeID();

        $productSkus = [$sku];
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
