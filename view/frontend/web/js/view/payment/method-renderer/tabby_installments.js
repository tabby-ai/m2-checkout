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
                this._super(),
                    this.register(this);
            },

            getCode: function () {
                return 'tabby_installments';
            },

            getTabbyCode: function () {
                return 'installments';
            },

            getMethodDescription: function () {
                return $.mage.__('Use any card.');
            },
            createTabbyCard: function (payment) {
                return new TabbyCard(this.getTabbyCardConfig(payment));
            }
        });
    }
);
