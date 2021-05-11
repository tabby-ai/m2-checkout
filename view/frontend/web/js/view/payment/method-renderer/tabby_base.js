define(
  [
	'ko',
	'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Tabby_Checkout/js/model/tabby_checkout'
  ],
  function (ko, $, Component, modelTabbyCheckout) {
    'use strict';

	return Component.extend({
	defaults: {
		template: 'Tabby_Checkout/payment/form',
        redirectAfterPlaceOrder : false
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
            if (method == this.getCode()) modelTabbyCheckout.setProduct(this.getTabbyCode());
        }, this);

        if (this.isChecked() == this.getCode()) modelTabbyCheckout.setProduct(this.getTabbyCode());

		return this;
	},
	register: function (code, renderer) {
		modelTabbyCheckout.renderers[code] = renderer;
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
        this.tabbyCheckout();
        return false;
    },
	tabbyCheckout: function () {
		modelTabbyCheckout.tabbyCheckout();
	},
	getCode: function() {
		return 'tabby_base';
	},
	getTabbyCode: function() {
		return 'base';
	}
    });
  }
);
