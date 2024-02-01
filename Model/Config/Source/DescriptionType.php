<?php

namespace Tabby\Checkout\Model\Config\Source;

class DescriptionType implements \Magento\Framework\Option\ArrayInterface
{
    public const OPTION_DESC_PW = 0;
    public const OPTION_DESC_P = 1;
    public const OPTION_DESC_TEXT = 2;
    public const OPTION_DESC_NONE = 3;

    /**
     * @var array
     */
    public $allowed = [];

    /**
     * Class constructor
     *
     * @param array $allowed
     */
    public function __construct(array $allowed)
    {
        $this->allowed = $allowed;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array_map(function ($key, $value) {
            return [
                'value' => $key,
                'label' => $value
            ];
        }, array_keys($this->toArray()), array_values($this->toArray()));
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $all = [
            self::OPTION_DESC_PW => __('PromoCardWide'),
            self::OPTION_DESC_P => __('PromoCard'),
            self::OPTION_DESC_TEXT => __('Text description'),
            self::OPTION_DESC_NONE => __('Blanc description')
        ];
        $options = [];
        foreach ($all as $value => $title) {
            if (in_array($value, $this->allowed)) {
                $options[$value] = $title;
            }
        }
        return $options;
    }
}
