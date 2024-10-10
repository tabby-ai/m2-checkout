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
     * @return string
     */
    public function createSession();
}
