<?php

namespace Reviewscouk\Reviews\Model\Config\Source;

class InvitationTrigger implements \Magento\Framework\Option\ArrayInterface
{
    const TRIGGER_SHIPPED = 'shipped';
    const TRIGGER_COMPLETED = 'completed';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::TRIGGER_SHIPPED, 'label' => __('Shipped (when shipment is created)')],
            ['value' => self::TRIGGER_COMPLETED, 'label' => __('Completed (when order state is complete)')]
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
            self::TRIGGER_SHIPPED => __('Shipped (when shipment is created)'),
            self::TRIGGER_COMPLETED => __('Completed (when order state is complete)')
        ];
    }
}
