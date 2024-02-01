<?php

namespace Tabby\Checkout\Model\Config\Source;

class ConstantArray implements \Magento\Framework\Option\ArrayInterface
{

    protected const VALUES = [
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
        foreach (static::VALUES as $key => $label) {
            $result[$key] = __($label);
        };
        return $result;
    }
}
