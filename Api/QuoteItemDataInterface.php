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
     * @param string $cartId
     * @return string
     */
    public function getGuestQuoteItemData($cartId);

    /**
     * @param string $cartId
     * @return string
     */
    public function getQuoteItemData($cartId);
}
