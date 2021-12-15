<?php
namespace Tabby\Checkout\Controller\Result;

class Webhook extends \Tabby\Checkout\Controller\CsrfCompatibility
{
    protected $_checkoutConfigProvider;
    protected $_checkoutSession;
    protected $_orderHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\DefaultConfigProvider $checkoutConfigProvider,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Tabby\Checkout\Model\Api\DdLog $ddlog,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
        $this->_checkoutConfigProvider    = $checkoutConfigProvider;
        $this->_checkoutSession = $checkoutSession;
        $this->_ddlog           = $ddlog;
        $this->_orderHelper     = $orderHelper;
        return parent::__construct($context);
    }

    public function execute() {

        $json = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);

        $json->setData(['success' => true]);

        try {
            $webhook = $this->getRequest()->getContent();

            $webhook = json_decode($webhook);

            $data = [
                'payment.id'            => $webhook->id,
                'order.reference_id'    => $webhook->order->reference_id,
                'content'               => $webhook
            ];
            if (!$webhook->order->reference_id) {
                $this->_ddlog->log("info", "webhook received - no reference id - ignored", null, $data);
                $json->setData(['success' => false, 'message' => 'no reference id assigned']);
                return $json;
            }

            $this->_ddlog->log("info", "webhook received", null, $data);
            
            if (is_object($webhook) && $this->isAuthorized($webhook)) {
                $this->_orderHelper->authorizeOrder($webhook->order->reference_id, $webhook->id, 'webhook');
            } else {
                $this->_ddlog->log("error", "webhook ignored", null, ['data' => $this->getRequest()->getContent()]);
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "webhook error", $e, ['data' => $this->getRequest()->getContent()]);
            $json->setData(['success' => false]);
        }
        
        return $json;
    }
    protected function isAuthorized($webhook) {
        if (property_exists($webhook, 'status') && in_array(strtoupper($webhook->status), ['AUTHORIZED', 'CLOSED'])) {
            return true;
        }
        return false;
    }
}

