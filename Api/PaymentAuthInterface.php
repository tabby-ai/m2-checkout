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
     * Authorize payment for Guests
     *
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function authPayment($cartId, $paymentId);

    /**
     * Authorize payment for Customers
     *
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function authCustomerPayment($cartId, $paymentId);
}
