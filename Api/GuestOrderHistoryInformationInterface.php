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
     * @param string $email
     * @param string $phone
     * @return string
     */
    public function getOrderHistory(
        $email,
        $phone = null
    );
}
