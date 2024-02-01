<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for payment save for order
 * @api
 * @since 1.0.0
 */
interface PaymentSaveInterface
{
    /**
     * Save payment id for Guests
     *
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function savePayment($cartId, $paymentId);

    /**
     * Save payment id for Customers
     *
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function saveCustomerPayment($cartId, $paymentId);
}
