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
            getSessionUrl: '/carts/:quoteId/tabby/session-data/',
            getSessionUrlGuest: '/guest-carts/:quoteId/tabby/session-data/',

            /**
             * Provide session creation data
             */
            execute: function (data) {
                fullScreenLoader.startLoader();

                return storage.post(
                    urlBuilder.createUrl(
                        customer.isLoggedIn() ? this.getSessionUrl : this.getSessionUrlGuest, 
                        { quoteId: quote.getQuoteId() }
                    ),
                    JSON.stringify(data)
                ).always(function (response) {
                    fullScreenLoader.stopLoader();
                });

            }
        };
    }
);
