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
            savePageUrl   : '/guest-carts/:cartId/tabby/payment-save/:paymentId',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id, payment_id) {
                fullScreenLoader.startLoader();

                storage.get(
                    urlBuilder.createUrl(this.savePageUrl, {cartId: quote_id, paymentId: payment_id})
                ).always(function (response) {
                    fullScreenLoader.stopLoader(true);
                });

            }
        };
    }
);
