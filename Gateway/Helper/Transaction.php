<?php
namespace Tabby\Checkout\Gateway\Helper;

class Transaction {
    public static function packPaymentDetails($details) {
        $result = [];
        foreach ($details as $key => $value) {
            if (!is_scalar($value)) {
                $value = json_encode($value);
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
