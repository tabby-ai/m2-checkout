<?php

namespace Tabby\Checkout\Plugin\Tabby\Checkout\Model\Method;

use Magento\Framework\Stdlib\CookieManagerInterface;

class Checkout
{
    /**
     * Name of Cookie that holds private content version
     */
    private const COOKIE_VAR_NAME = 'xxx111otrckid';

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * Class constructor
     *
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        CookieManagerInterface $cookieManager
    ) {
        $this->cookieManager = $cookieManager;
    }

    /**
     * Append cookie value to redirect Url
     *
     * @param \Tabby\Checkout\Model\Method\Checkout $payment
     * @param string $result
     * @return string
     */
    public function afterGetOrderRedirectUrl(
        \Tabby\Checkout\Model\Method\Checkout $payment,
        $result
    ) {
        if ($var_value = $this->cookieManager->getCookie(self::COOKIE_VAR_NAME)) {
            $result .= '&' . self::COOKIE_VAR_NAME . '=' . urlencode($var_value);
        }

        return $result;
    }
}
