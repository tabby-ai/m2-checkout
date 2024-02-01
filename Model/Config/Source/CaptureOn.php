<?php

namespace Tabby\Checkout\Model\Config\Source;

/**
 * Source model for capture on setting
 */
class CaptureOn implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Returns configuration options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'order',
                'label' => __('Order placed')
            ],
            [
                'value' => 'invoice',
                'label' => __('Invoice')
            ],
            [
                'value' => 'shipment',
                'label' => __('Shipment')
            ],
            [
                'value' => 'nocapture',
                'label' => __('No Capture')
            ],
        ];
    }
}
