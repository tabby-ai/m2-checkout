<?php
namespace Tabby\Checkout\Setup\DB;

use Magento\Framework\DB\DataConverter\DataConverterInterface;

class DescriptionTypeDataConverter implements DataConverterInterface
{
    /**
     * Convert obsolete values
     *
     * @param string $value
     * @return string
     */
    public function convert($value)
    {
        if (is_null($value) || $value < 2 || $value == 'NULL') $value = 2;

        return $value;
    }
}
