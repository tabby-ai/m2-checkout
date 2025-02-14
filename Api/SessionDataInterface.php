<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for prescoring
 * @api
 * @since 6.0.0
 */
interface SessionDataInterface
{
    /**
     * Create session for Customers
     *
     * @param string $cartId
     * @return array
     */
    public function createSessionForCustomer(string $cartId) : array;
    /**
     * Create session for Customers
     *
     * @param string $cartId
     * @return array
     */
    public function createSessionForGuest(string $cartId) : array;
}
