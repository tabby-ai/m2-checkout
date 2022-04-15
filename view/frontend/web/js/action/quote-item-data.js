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
            authPageUrl: '/carts/:quoteId/tabby/quote-item-data',
            authPageUrlGuest: '/guest-carts/:quoteId/tabby/quote-item-data',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function () {
                fullScreenLoader.startLoader();

                return storage.get(urlBuilder.createUrl(
                    customer.isLoggedIn() ? this.authPageUrl : this.authPageUrlGuest,
                    { quoteId: quote.getQuoteId() }
                )).always(function (response) {
                    fullScreenLoader.stopLoader();
                });

            }
        };
    }
);
