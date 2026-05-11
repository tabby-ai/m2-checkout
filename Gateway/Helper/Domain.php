<?php
namespace Tabby\Checkout\Gateway\Helper;

class Domain
{
    /**
     * Getter for Tabby domain
     *
     * @param string $country
     * @return string
     */
    public static function getTabbyDomain($country)
    {
        $dev = defined('TABBY_DEV_DOMAINS');
        $d2 = ($country == 'SA' && $dev ? 'tabbysa' : 'tabby');
        $d1 = (defined('TABBY_DEV_DOMAINS') ? 'dev' : ($country == 'SA' ? 'sa' : 'ai'));
        return $d2 . '.' . $d1;
    }

    /**
     * Getter for Tabby domain by currency
     *
     * @param string $currency
     * @return string
     */
    public static function getTabbyDomainByCurrencyCode($currency_code)
    {
        return self::getTabbyDomain(substr($currency_code, 0, 2));
    }
}
