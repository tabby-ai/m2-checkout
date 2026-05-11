<?php

namespace Tabby\Checkout\Model\Config\Source;

use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

/**
 * Allowed services drop-down config model
 */
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

        foreach (DataHelper::ALLOWED_SERVICES as $key => $title) {
            $options[] = [
                'value' => $key,
                'label' => __($title),
            ];
        }

        return $options;
    }
}
