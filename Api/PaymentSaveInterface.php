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
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function savePayment($cartId, $paymentId);

    /**
     * @param string $cartId
     * @param string $paymentId
     * @return string
     */
    public function saveCustomerPayment($cartId, $paymentId);
}
