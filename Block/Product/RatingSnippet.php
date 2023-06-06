<?php

namespace Reviewscouk\Reviews\Block\Product;

use Magento\Catalog\Block\Product\ListProduct;
use Framework\Registry as Registry;
use Magento\Framework\Escaper as Escaper;
// use Reviewscouk\Reviews\Helper\Config as ReviewsConfig;
use Magento\Framework as Framework;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\Context as ContextHelper;
use \Reviewscouk\Reviews\Helper\Config as ReviewsConfig;


class RatingSnippet extends ListProduct
{
    protected $_customerSession;
    protected $categoryFactory;
    //protected $reviewsConfig = Reviews\Helper\Config;
    // protected $_storeManager = StoreManagerInterface::class;
    protected $store;
    protected $reviewsConfig;
    /**
     * ListProduct constructor.
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Framework\Data\Helper\PostHelper $postDataHelper
     * @param \Magento\Catalog\Model\Layer\Resolver $layerResolver
     * @param \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param Helper $helper
     * @param array $data
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Data\Helper\PostHelper $postDataHelper,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        array $data = [],
        \Magento\Customer\Model\Session $customerSession = null,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory = null,
        ReviewsConfig $reviewsConfigHelper = null,
        StoreManagerInterface $storeManager = null
    ) {
        $this->_customerSession = $customerSession;
        $this->categoryFactory = $categoryFactory;
        $this->reviewsConfig = $reviewsConfigHelper ?: \Magento\Framework\App\ObjectManager::getInstance()->get(ReviewsConfig::class);
        $this->store = $storeManager;

        parent::__construct(
            $context,
            $postDataHelper,
            $layerResolver,
            $categoryRepository,
            $urlHelper,
            $data
        );
    }


    private function getProductSkus($product)
    {
        $sku = $product->getSku();
        $type = $product->getTypeID();

        $productSkus = [$sku];
        if ($type == 'configurable') {
            $usedProducts = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($usedProducts as $usedProduct) {
                $productSkus[] = $usedProduct->getSku();
            }
        }

        if ($type == 'grouped') {
            $usedProducts = $product->getTypeInstance()->getAssociatedProducts($product);
            foreach ($usedProducts as $usedProduct) {
                $productSkus[] = $usedProduct->getSku();
            }
        }
        return $productSkus;
    }

    public function getRatingSnippet($product)
    {
        $escaper = new Escaper();
        $ratingSnippetEnabled = $this->reviewsConfig->isCategoryRatingSnippetWidgetEnabled($this->store);
        $skus = $this->getProductSkus($product);
        $html = '';
        if($ratingSnippetEnabled) {
            $html = '<div class="ruk_rating_snippet" data-sku="' . $escaper->escapeHtml((!empty($skus) ? implode(';', $skus) : '')) . '"></div>';
        }
        return $html;
    }

}
