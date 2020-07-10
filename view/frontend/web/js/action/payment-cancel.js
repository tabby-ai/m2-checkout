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
            cancelPageUrl: '/guest-carts/:cartId/tabby/payment-cancel',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function (quote_id) {
                fullScreenLoader.startLoader();

                storage.get(
                    urlBuilder.createUrl(this.cancelPageUrl, {cartId: quote_id})
                ).always(function(response) {
                    fullScreenLoader.stopLoader(true);
                    //window.location.replace(url.build('checkout/cart'));
                });

            }
        };
    }
);
