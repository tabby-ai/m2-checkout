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
     * Cancel payment by cart id for Guests
     *
     * @param string $cartId
     * @return string
     */
    public function cancelPayment($cartId);

    /**
     * Cancel payment by cart id for Customers
     *
     * @param string $cartId
     * @return string
     */
    public function cancelCustomerPayment($cartId);
}
