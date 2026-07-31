<?php

namespace Reviewscouk\Reviews\Block\Product;

use Magento\Framework\View\Element\Template;

class CustomRatingSnippet extends \Reviewscouk\Reviews\Block\Product\RatingSnippet
{
    /**
     * Get the template for the block based on the admin setting
     *
     * @return string
     */
    public function getTemplate()
    {
        // Outer gate on the same feature getRatingSnippet() guards, so it has to
        // read the same scope: go through the helper (which owns the config path)
        // and the store resolution inherited from RatingSnippet, instead of a
        // bare isSetFlag() that only ever sees default/global scope.
        $isEnabled = $this->reviewsConfig->isCategoryRatingSnippetWidgetEnabled($this->getCurrentStoreId());

        if ($isEnabled) {
            return 'Reviewscouk_Reviews::category/list.phtml';
        }

        // Use the default template if the admin setting is not enabled
        return parent::getTemplate();
    }
}
