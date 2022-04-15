<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for payment save for order
 * @api
 * @since 1.0.0
 */
interface PaymentAuthInterface
{
    /**
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function authPayment($cartId, $paymentId);

    /**
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function authCustomerPayment($cartId, $paymentId);
}
