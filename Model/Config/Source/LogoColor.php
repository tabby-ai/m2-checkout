<?php

namespace Tabby\Checkout\Model\Config\Source;

class LogoColor implements \Magento\Framework\Option\ArrayInterface
{

    const LOGOS = [
        'green' => 'Green',
        'black' => 'Black'
    ];

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        foreach ($this->toArray() as $value => $label) {
            $result[] = [
                'value' => $value,
                'label' => $label
            ];
        }
        return $result;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $result = [];
        foreach (static::LOGOS as $key => $label) {
            $result[$key] = __($label);
        };
        return $result;
    }

}
