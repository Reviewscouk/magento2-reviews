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
        $isEnabled = $this->_scopeConfig->isSetFlag('reviewscouk_reviews_onpage/widget/category_rating_snippet_widget_enabled');
   
        if ($isEnabled) {
            return 'Reviewscouk_Reviews::category/list.phtml';
        }

        // Use the default template if the admin setting is not enabled
        return parent::getTemplate();
    }
}
