<?php

namespace Tabby\Checkout\Model\Config\Source;

class DescriptionTypePL extends DescriptionType
{
    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            self::OPTION_DESC_TEXT  => __('Text description'),
            self::OPTION_DESC_NONE  => __('Blanc description')
        ];
    }
}

