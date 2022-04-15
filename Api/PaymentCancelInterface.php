<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for managing guest order history information
 * @api
 * @since 1.0.0
 */
interface PaymentCancelInterface
{
    /**
     * @param string $cartId
     * @return string
     */
    public function cancelPayment($cartId);

    /**
     * @param string $cartId
     * @return string
     */
    public function cancelCustomerPayment($cartId);
}
