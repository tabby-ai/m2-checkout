<?php

namespace Tabby\Checkout\Model\Api\Tabby;

class Payments extends \Tabby\Checkout\Model\Api\Tabby {
    const API_PATH = 'payments/';

    public function getPayment($id) {
        return $this->request($id);
    }

    public function updatePayment($id, $data) {
        return $this->request($id, \Zend_Http_Client::PUT, $data);
    }
    
    public function capturePayment($id, $data) {
        return $this->request($id . '/captures', \Zend_Http_Client::POST, $data);
    }

    public function refundPayment($id, $data) {
        return $this->request($id . '/refunds', \Zend_Http_Client::POST, $data);
    }
    
    public function closePayment($id) {
        return $this->request($id . '/close', \Zend_Http_Client::POST);
    }
    public function updateReferenceId($id, $referenceId) {
        $data = ["order" => ["reference_id"  => $referenceId]];

        return $this->updatePayment($id, $data);
    }

}
