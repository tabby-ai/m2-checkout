/**
 * @api
 */
define(
    [
        'mage/url',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/storage'
    ],
    function (url, urlBuilder, fullScreenLoader, storage) {
        'use strict';

        return {
            authPageUrl   : '/guest-carts/:cartId/tabby/payment-auth/:paymentId',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id, payment_id) {
                fullScreenLoader.startLoader();

                storage.get(
                    urlBuilder.createUrl(this.authPageUrl, {cartId: quote_id, paymentId: payment_id})
                ).always(function(response) {
                    window.location.replace(url.build(window.checkoutConfig.defaultSuccessPageUrl));
                });

            }
        };
    }
);
