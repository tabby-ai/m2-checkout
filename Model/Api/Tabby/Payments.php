<?php

namespace Tabby\Checkout\Model\Api\Tabby;

class Payments extends \Tabby\Checkout\Model\Api\Tabby {
    const API_PATH = 'payments/';

    public function getPayment($storeId, $id) {
        return $this->request($storeId, $id);
    }

    public function updatePayment($storeId, $id, $data) {
        return $this->request($storeId, $id, \Zend_Http_Client::PUT, $data);
    }
    
    public function capturePayment($storeId, $id, $data) {
        return $this->request($storeId, $id . '/captures', \Zend_Http_Client::POST, $data);
    }

    public function refundPayment($storeId, $id, $data) {
        return $this->request($storeId, $id . '/refunds', \Zend_Http_Client::POST, $data);
    }
    
    public function closePayment($storeId, $id) {
        return $this->request($storeId, $id . '/close', \Zend_Http_Client::POST);
    }
    public function updateReferenceId($storeId, $id, $referenceId) {
        $data = ["order" => ["reference_id"  => $referenceId]];

        return $this->updatePayment($storeId, $id, $data);
    }

}
