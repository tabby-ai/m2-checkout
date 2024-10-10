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
            getSessionUrl: '/tabby/session-data/',

            /**
             * Provide session creation data
             */
            execute: function (data) {
                fullScreenLoader.startLoader();

                return storage.post(
                    urlBuilder.createUrl(this.getSessionUrl, {}),
                    JSON.stringify(data)
                ).always(function (response) {
                    fullScreenLoader.stopLoader();
                });

            }
        };
    }
);
