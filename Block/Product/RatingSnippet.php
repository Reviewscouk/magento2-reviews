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
    /**
     * Store manager, not a store. Resolved to a concrete store view lazily in
     * getCurrentStoreId() — see REVIEWS-4382 for why this is not done in the
     * constructor.
     *
     * @var StoreManagerInterface|null
     */
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
        ?\Magento\Customer\Model\Session $customerSession = null,
        ?\Magento\Catalog\Model\CategoryFactory $categoryFactory = null,
        ?ReviewsConfig $reviewsConfigHelper = null,
        ?StoreManagerInterface $storeManager = null
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

    /**
     * Resolve the current store view id for config scope lookups.
     *
     * Prefers the explicitly injected store manager (kept for backward
     * compatibility with direct instantiation) and falls back to the one
     * AbstractBlock receives via $context, which DI always populates.
     *
     * Resolution is deliberately lazy rather than done in the constructor,
     * matching the pattern REVIEWS-4382 established in Model/Api.php: on
     * headless/multi-store installs getStore() can throw at construction time.
     *
     * Protected so CustomRatingSnippet can gate its template swap on the same
     * scope, rather than growing a second scope-resolution path.
     *
     * @return int|string|null Store id, or null to fall back to default scope.
     */
    protected function getCurrentStoreId()
    {
        $storeManager = $this->store ?: $this->_storeManager;
        if (!$storeManager) {
            return null;
        }

        try {
            return $storeManager->getStore()->getId();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // A category page must not fatal because scope resolution failed;
            // fall back to default scope, which is the pre-fix behaviour.
            return null;
        }
    }

    public function getRatingSnippet($product)
    {
        $escaper = new Escaper();
        $ratingSnippetEnabled = $this->reviewsConfig->isCategoryRatingSnippetWidgetEnabled($this->getCurrentStoreId());
        $skus = $this->getProductSkus($product);
        $html = '';
        if($ratingSnippetEnabled) {
            $html = '<div class="ruk_rating_snippet" data-sku="' . $escaper->escapeHtml((!empty($skus) ? implode(';', $skus) : '')) . '"></div>';
        }
        return $html;
    }

}
