<?php

namespace Tabby\Checkout\Model\Config\Source;

/**
 * Class ConfigOrderActionFailedPayment
 */
class ConfigOrderActionFailedPayment implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'cancel',
                'label' => __('Cancel the order')
            ],
            [
                'value' => 'delete',
                'label' => __('Delete the order')
            ]
        ];
    }
}

