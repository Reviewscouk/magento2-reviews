<?php

namespace Reviewscouk\Reviews\Model\Config\Source;

use Magento\Framework as Framework;

class QuestionWidgetVersion implements Framework\Option\ArrayInterface
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '2', 'label' => __('Questions tab on Product Reviews Widget')],
            ['value' => '1', 'label' => __('Legacy Question Widget')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '2' => __('Questions tab on Product Reviews Widget'),
            '1' => __('Legacy Question Widget'),
        ];
    }
}
