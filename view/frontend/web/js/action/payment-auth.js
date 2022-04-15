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
            authPageUrl: '/carts/:quoteId/tabby/payment-auth/:paymentId',
            authPageUrlGuest: '/guest-carts/:quoteId/tabby/payment-auth/:paymentId',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id, payment_id) {
                fullScreenLoader.startLoader();

                storage.get(urlBuilder.createUrl(
                    customer.isLoggedIn() ? this.authPageUrl : this.authPageUrlGuest,
                    { quoteId: quote.getQuoteId(), paymentId: payment_id }
                )).always(function (response) {
                    window.location.replace(url.build(window.checkoutConfig.defaultSuccessPageUrl));
                });

            }
        };
    }
);
