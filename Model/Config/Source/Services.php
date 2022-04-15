<?php

namespace Tabby\Checkout\Model\Config\Source;

use Tabby\Checkout\Gateway\Config\Config;

class Services implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Return options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];

        foreach (Config::ALLOWED_SERVICES as $key => $title) {
            $options[] = [
                'value' => $key,
                'label' => __($title)
            ];
        }

        return $options;
    }

}
