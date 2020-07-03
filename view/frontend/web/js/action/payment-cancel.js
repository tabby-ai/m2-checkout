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
            cancelPageUrl: '/tabby/payment-cancel',

            /**
             * Provide order cancel and redirect to page
             */
            execute: function () {
                fullScreenLoader.startLoader();

                storage.get(
                    urlBuilder.createUrl(this.cancelPageUrl, {})
                ).always(function(response) {
                    fullScreenLoader.stopLoader();
                    //window.location.replace(url.build('checkout/cart'));
                });

            }
        };
    }
);
