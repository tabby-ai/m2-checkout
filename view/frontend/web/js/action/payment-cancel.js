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
            cancelPageUrl: '/carts/:quoteId/tabby/payment-cancel',
            cancelPageUrlGuest: '/guest-carts/:quoteId/tabby/payment-cancel',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id, fsl) {
                fullScreenLoader.startLoader();

                storage.get(urlBuilder.createUrl(
                    customer.isLoggedIn() ? this.cancelPageUrl : this.cancelPageUrlGuest,
                    { quoteId: quote.getQuoteId() }
                )).always(function (response) {
                    fullScreenLoader.stopLoader(true);
                    fsl.stopLoader(true);
                    //window.location.replace(url.build('checkout/cart'));
                });

            }
        };
    }
);
