define(
    [
        'jquery',
        'ko',
        'Tabby_Checkout/js/view/payment/method-renderer/tabby_base',
        'mage/translate'
    ],
    function ($, ko, Component) {
        'use strict';

        return Component.extend({
            isTabbyPlaceOrderActionAllowed: ko.observable(false),
            isRejected: ko.observable(false),

            initialize: function () {
                this._super()
                this.register(this);
            },

            getCode: function () {
                return 'tabby_checkout';
            },

            getTabbyCode: function () {
                return 'payLater';
            },

            getMethodDescription: function () {
                return $.mage.__('No upfront payments. No fees.');
            }
        });
    }
);
