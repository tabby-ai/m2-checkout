<?php

namespace Tabby\Checkout\Plugin\Tabby\Checkout\Gateway\Response;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class CreateSessionHandler
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
     * @param array $handlingSubject
     * @param array $response
     * @param string $result
     */
    public function afterHandle(
        \Tabby\Checkout\Gateway\Response\CreateSessionHandler $handler,
        $result,
        array $handlingSubject,
        array $response
    ) {
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        // Extract web_url from nested structure
        if (isset($response['configuration']['available_products']['installments'][0]['web_url'])) {
            $webUrl = $response['configuration']['available_products']['installments'][0]['web_url'];
            if ($var_value = $this->cookieManager->getCookie(self::COOKIE_VAR_NAME)) {
                $webUrl .= '&' . self::COOKIE_VAR_NAME . '=' . urlencode($var_value);
            }
            $payment->setAdditionalInformation(DataHelper::TABBY_WEB_URL, $webUrl);
        }
    }
}
