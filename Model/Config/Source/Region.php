<?php

namespace Reviewscouk\Reviews\Model\Config\Source;

use Magento\Framework\App\ObjectManager;

class Region implements \Magento\Framework\Option\ArrayInterface {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'UK', 'label'=>Mage::helper('adminhtml')->__('UK')),
            array('value' => 'US', 'label'=>Mage::helper('adminhtml')->__('US')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'UK' => Mage::helper('adminhtml')->__('UK'),
            'US' => Mage::helper('adminhtml')->__('US'),
        );
    }

}
