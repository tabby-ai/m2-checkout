define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'tabby_checkout',
                component: 'Tabby_Checkout/js/view/payment/method-renderer/tabby_checkout'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
