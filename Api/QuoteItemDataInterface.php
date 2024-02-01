<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for quote items reload
 * @api
 * @since 1.0.0
 */
interface QuoteItemDataInterface
{
    /**
     * Retrive quote items data for Guests
     *
     * @param string $cartId
     * @return string
     */
    public function getGuestQuoteItemData($cartId);

    /**
     * Retrive quote items data for Customers
     *
     * @param string $cartId
     * @return string
     */
    public function getQuoteItemData($cartId);
}
