define(
  [
    'Magento_Checkout/js/view/payment/default',
    'Tabby_Checkout/js/model/tabby_checkout'
  ],
  function (Component, modelTabbyCheckout) {
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
