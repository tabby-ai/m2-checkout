define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Tabby_Checkout/js/action/redirect-on-success',
        'Tabby_Checkout/js/model/tabby_checkout'
    ],
    function (ko, $, Component, redirectOnSuccessAction, modelTabbyCheckout) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Tabby_Checkout/payment/form',
                redirectAfterPlaceOrder: false
            },
            isTabbyPlaceOrderActionAllowed: ko.observable(false),

            initialize: function () {
                this._super();

                this.isPlaceOrderActionAllowed = ko.computed({
                    read: this.isTabbyPlaceOrderActionAllowed,
                    write: function (value) { },
                    owner: this
                }),

                    this.isChecked.subscribe(function (method) {
                        if (method == this.getCode()) {
                            modelTabbyCheckout.setProduct(this.getTabbyCode());
                        }
                    }, this);

                if (this.isChecked() == this.getCode()) {
                    modelTabbyCheckout.setProduct(this.getTabbyCode());
                }

                return this;
            },
            /**
            * Get payment method data
            */
            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {'checkout_id': modelTabbyCheckout.payment_id}
                };
            },

            register: function (renderer) {
                modelTabbyCheckout.registerRenderer(renderer);
            },
            enableButton: function () {
                this.isTabbyPlaceOrderActionAllowed(true);
            },
            disableButton: function () {
                this.isTabbyPlaceOrderActionAllowed(false);
            },
            getHideMethods: function () {
                return window.checkoutConfig.payment.tabby_checkout.config.hideMethods;
            },
            getShowLogo: function () {
                return window.checkoutConfig.payment.tabby_checkout.config.showLogo;
            },
            getPaymentLogoSrc: function () {
                return window.checkoutConfig.payment.tabby_checkout.config.paymentLogoSrc;
            },
            getPaymentInfoImageSrc: function () {
                return window.checkoutConfig.payment.tabby_checkout.config.paymentInfoSrc;
            },
            getPaymentInfoHref: function () {
                return window.checkoutConfig.payment.tabby_checkout.config.paymentInfoHref;
            },
            getCanShowTextDescription: function () {
                return window.checkoutConfig.payment.tabby_checkout.methods[this.getCode()].description_type == 2;
            },
            getIsTabbyCard: function () {
                return [0, 1].includes(
                    window.checkoutConfig.payment.tabby_checkout.methods[this.getCode()].description_type);
            },
            getTextDescription: function () {
                return this.getCanShowTextDescription() ? this.getMethodDescription() : '';
            },
            getDescriptionDivId: function () {
                return this.getTabbyCode() + 'Card'; // == 'installments' ? 'tabbyCard' : 'tabbyDesc';
                //return this.getTabbyCode() == 'installments' ? 'tabbyCard' : 'tabbyDesc';
            },
            initTabbyCard: function (payment = null) {
                if (!this.getIsTabbyCard()) {
                    return;
                }

                if (payment === null || (typeof payment == 'object' && !payment.hasOwnProperty('amount'))) {
                    payment = {
                        'amount': modelTabbyCheckout.getGrandTotal(),
                        'currency': modelTabbyCheckout.getTabbyCurrency()
                    };
                }

                try {
                    this.createTabbyCard(payment);
                } catch (error) {
                }

            },
            createTabbyCard: function (payment) {
            },
            getTabbyCardConfig: function (payment) {
                return {
                    selector: '#' + this.getDescriptionDivId(),
                    currency: payment.currency,
                    lang: window.checkoutConfig.payment.tabby_checkout.lang &&
                    window.checkoutConfig.payment.tabby_checkout.lang.length > 1
                        ? window.checkoutConfig.payment.tabby_checkout.lang.substr(0, 2)
                        : 'en',
                    price: payment.amount,
                    size: window.checkoutConfig.payment.tabby_checkout.methods['tabby_installments'].card_direction,
                    theme: window.checkoutConfig.payment.tabby_checkout.methods['tabby_installments'].card_theme,
                    header: false
                };
            },
            showInfoWindow: function (data, event) {
                window.open(
                    $(event.currentTarget).attr('href'),
                    'tabbyinfowindow',
                    'toolbar=no, location=no,' +
                    ' directories=no, status=no,' +
                    ' menubar=no, scrollbars=yes,' +
                    ' resizable=yes, ,left=0,' +
                    ' top=0, width=400, height=350'
                );

                return false;
            },
            placeTabbyOrder: function () {
                Component.prototype.placeOrder.apply(this, this.getData());
            },
            afterPlaceOrder: function (data, event) {
                redirectOnSuccessAction.execute();
            },
            getCode: function () {
                return 'tabby_base';
            },
            getTabbyCode: function () {
                return 'base';
            },
            getTabbyPrice: function () {
                return modelTabbyCheckout.getGrandTotal();
            },
            getTabbyCurrency: function () {
                return modelTabbyCheckout.getTabbyCurrency();
            },
            getMethodDescription: function () {
                return '';
            },
            getInstallmentsCount: function () {
                return window.checkoutConfig.payment.tabby_checkout.methods[this.getCode()].installments_count;
            },
            getLanguageCode: function () {
                if (modelTabbyCheckout.config.lang) {
                    return modelTabbyCheckout.config.lang.substring(0, 2).toLowerCase();
                } else {
                    return 'en';
                }
            }
        });
    }
);
