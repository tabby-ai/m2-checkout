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
        //if (!in_array($value, [1,3])) $value = 1;

        //return $value;
        return 1;
    }
}
