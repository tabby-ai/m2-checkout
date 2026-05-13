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
    public function getTabbyDomain($country)
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
    public function getTabbyDomainByCurrencyCode($currency_code)
    {
        return $this->getTabbyDomain(substr($currency_code, 0, 2));
    }

    public function getTabbyCheckoutDomain($currency_code)
    {
        return sprintf('checkout.%s', $this->getTabbyDomainByCurrencyCode($currency_code));
    }
}
