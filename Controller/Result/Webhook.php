<?php

namespace Tabby\Checkout\Controller\Result;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Tabby\Checkout\Controller\CsrfCompatibility;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Layout;
use Tabby\Checkout\Helper\Order;
use Tabby\Checkout\Model\Api\DdLog;

class Webhook extends CsrfCompatibility
{
    /**
     * @var DefaultConfigProvider
     */
    protected $_checkoutConfigProvider;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    /**
     * Webhook constructor.
     *
     * @param Context $context
     * @param DefaultConfigProvider $checkoutConfigProvider
     * @param Session $checkoutSession
     * @param DdLog $ddlog
     * @param Order $orderHelper
     */
    public function __construct(
        Context $context,
        DefaultConfigProvider $checkoutConfigProvider,
        Session $checkoutSession,
        DdLog $ddlog,
        Order $orderHelper
    ) {
        $this->_checkoutConfigProvider = $checkoutConfigProvider;
        $this->_checkoutSession = $checkoutSession;
        $this->_ddlog = $ddlog;
        $this->_orderHelper = $orderHelper;
        return parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Layout
     */
    public function execute()
    {

        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $json->setData(['success' => true]);

        try {
            $webhook = $this->getRequest()->getContent();

            $webhook = json_decode($webhook);

            $data = [
                'payment.id' => $webhook->id,
                'order.reference_id' => $webhook->order->reference_id,
                'content' => $webhook
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

    /**
     * @param $webhook
     * @return bool
     */
    protected function isAuthorized($webhook)
    {
        if (property_exists($webhook, 'status') && in_array(strtoupper($webhook->status), ['AUTHORIZED', 'CLOSED'])) {
            return true;
        }
        return false;
    }
}
