define([
    'jquery'
], function ($) {
    'use strict';

    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {
            _Rebuild: function () {
                this._super();
            },

            _UpdatePrice: function () {
                this._super();
                let price = $('.product-info-main .price-box.price-final_price').
                    find('[data-price-type=finalPrice]').
                    attr('data-price-amount');
                try {
                    price = Number.parseFloat(price);
                } catch (error) {
                    return;
                }
                if (price) {
                    this._updateTabbyPromotions(price);
                }
            },

            _updateTabbyPromotions: function (price) {
                if (typeof tabbyConfig !== 'undefined' && (price * tabbyConfig.currencyRate).toFixed(2) != tabbyConfig.price.toFixed(2)) {
                    tabbyConfig.price = price * tabbyConfig.currencyRate;
                    new window.TabbyPromo(tabbyConfig);
                }
            }
        });

        return $.mage.SwatchRenderer;
    };
});
