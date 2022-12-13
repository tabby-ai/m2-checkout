define(
    [
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (url, fullScreenLoader) {
        'use strict';

        return {
            redirectUrl: window.checkoutConfig.payment.tabby_checkout.defaultRedirectUrl,

            /**
             * Provide redirect to page
             */
            execute: function () {
                fullScreenLoader.startLoader();
                window.location.replace(url.build(this.redirectUrl));
            }
        };
    }
);
