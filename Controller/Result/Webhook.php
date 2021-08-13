<?php
namespace Tabby\Checkout\Controller\Result;

class Webhook extends \Magento\Framework\App\Action\Action 
    implements \Magento\Framework\App\CsrfAwareActionInterface
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

            $this->_ddlog->log("info", "webhook received", null, [
                'payment.id'            => $webhook->id,
                'order.reference_id'    => $webhook->order->reference_id,
                'content'               => $webhook
            ]);
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
    public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): ? \Magento\Framework\App\Request\InvalidRequestException {
        return null;
    }
    
    public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool {
        return true;
    }
}

