<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for managing guest order history information
 * @api
 * @since 1.0.0
 */
interface GuestOrderHistoryInformationInterface
{
    /**
     * Getter for order history array
     *
     * @param string $cartId
     * @return string
     */
    public function getOrderHistory(
        $cartId
    );
}
