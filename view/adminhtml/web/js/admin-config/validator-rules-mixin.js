define([
    'jquery',
    'Magento_Ui/js/lib/validation/utils'
], function ($, utils) {
    'use strict';
    return function (target) {
        $.validator.addMethod(
            'validate-tabby-public-key',
            function (value) {
                return value === '******' || utils.isEmptyNoTrim(value) || /^pk_(test_)?[\da-f]{8}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{12}$/.test(value);
            },
            $.mage.__('Wrong PUBLIC key format. Must be <b>pk_[test_]xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</b>.')
        );
        $.validator.addMethod(
            'validate-tabby-secret-key',
            function (value) {
                return value === '******' || utils.isEmptyNoTrim(value) || /^sk_(test_)?[\da-f]{8}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{4}\-[\da-f]{12}$/.test(value);
            },
            $.mage.__('Wrong SECRET key format. Must be <b>sk_[test_]xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</b>.')
        );
        return target;
    };
});
