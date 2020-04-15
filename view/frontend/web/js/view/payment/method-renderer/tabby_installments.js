define(
  [
	'ko',
	'Tabby_Checkout/js/view/payment/method-renderer/tabby_base'
  ],
  function (ko, Component) {
    'use strict';

	return Component.extend({
		isTabbyPlaceOrderActionAllowed: ko.observable(false),
		isRejected: ko.observable(false),

		initialize: function () {
			this._super(),
			this.register(this.getTabbyCode(), this);
		},

		getCode: function() {
			return 'tabby_installments';
		},

		getTabbyCode: function() {
			return 'installments';
		}
    });
  }
);
