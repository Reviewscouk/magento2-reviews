<?php

namespace Reviewscouk\Reviews\Model\Config\Source;

use Magento\Framework as Framework;

class Widgetversion implements Framework\Option\ArrayInterface
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '3', 'label' => __('Product Reviews Widget')],
            ['value' => '1', 'label' => __('Legacy Javascript Widget')],
            ['value' => '2', 'label' => __('Legacy Static Content Widget')],
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
            '1' => __('V1 - Javascript Widget'),
            '2' => __('V2 - Static Content'),
            '3' => __('V3 - Product Reviews Widget'),
        ];
    }
}
