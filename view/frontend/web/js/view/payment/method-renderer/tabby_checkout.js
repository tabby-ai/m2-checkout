define(
  [
	'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Tabby_Checkout/js/model/tabby_checkout'
  ],
  function ($, Component, modelTabbyCheckout) {
    'use strict';

	return Component.extend({
	defaults: {
		template: 'Tabby_Checkout/payment/form'
	},

	initialize: function () {
		this._super();
		modelTabbyCheckout.tabbyRenderer = this;
		return this;
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
		this.placeOrder(this.getData());
	},
	tabbyCheckout: function () {
		modelTabbyCheckout.tabbyCheckout();
	},

	getCode: function() {
		return 'tabby_checkout';
	},

	getData: function() {
		return {
			'method': this.item.method,
			'additional_data': {
				'checkout_id': modelTabbyCheckout.checkout_id
			}
		}
	}
    });
  }
);
