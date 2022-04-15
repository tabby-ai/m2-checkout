/**
 * @api
 */
define(
    [
        'mage/url',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'mage/storage'
    ],
    function (url, urlBuilder, fullScreenLoader, quote, customer, storage) {
        'use strict';

        return {
            savePageUrl: '/carts/:quoteId/tabby/payment-save/:paymentId',
            savePageUrlGuest: '/guest-carts/:quoteId/tabby/payment-save/:paymentId',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id, payment_id) {
                fullScreenLoader.startLoader();

                storage.get(urlBuilder.createUrl(
                    customer.isLoggedIn() ? this.savePageUrl : this.savePageUrlGuest,
                    { quoteId: quote.getQuoteId(), paymentId: payment_id }
                )).always(function (response) {
                    fullScreenLoader.stopLoader(true);
                });

            }
        };
    }
);
