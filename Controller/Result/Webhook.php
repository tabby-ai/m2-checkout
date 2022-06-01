<?php

namespace Tabby\Checkout\Controller\Result;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Tabby\Checkout\Controller\CsrfCompatibility;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Layout;
use Tabby\Checkout\Helper\Order;
use Tabby\Checkout\Model\Api\DdLog;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;


class Webhook extends CsrfCompatibility
{
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
     * @param DdLog $ddlog
     * @param Order $orderHelper
     */
    public function __construct(
        Context $context,
        DdLog $ddlog,
        Order $orderHelper,
        StoreManagerInterface $storeManager,
        Emulation $emulation
    ) {
        $this->_ddlog = $ddlog;
        $this->_orderHelper  = $orderHelper;
        $this->_storeManager = $storeManager;
        $this->_emulation    = $emulation;
        return parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Layout
     */
    public function execute()
    {

        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $json->setData(['success' => true]);

        $emulation = false;
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
            // emulate order store if needed
            if (($storeId = $this->_orderHelper->getOrderStoreId($webhook->order->reference_id)) !== $this->_storeManager->getStore()->getId()) {
                $this->_emulation->startEnvironmentEmulation($storeId);
                $emulation = true;
            }

            if (is_object($webhook) && $this->isAuthorized($webhook)) {
                $this->_orderHelper->authorizeOrder($webhook->order->reference_id, $webhook->id, 'webhook');
            } elseif ($this->isRejectedOrExpired($webhook)) {
                $this->_orderHelper->noteRejectedOrExpired($webhook);
            } else {
                $this->_ddlog->log("error", "webhook ignored", null, ['data' => $this->getRequest()->getContent()]);
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "webhook error", $e, ['data' => $this->getRequest()->getContent()]);
            $json->setData(['success' => false]);
        } finally {
            if ($emulation) {
                $this->_emulation->stopEnvironmentEmulation();
            };
        }

        return $json;
    }

    /**
     * @param $webhook
     * @return bool
     */
    protected function isRejectedOrExpired($webhook)
    {
        if (property_exists($webhook, 'status') && in_array(strtoupper($webhook->status), ['REJECTED', 'EXPIRED'])) {
            return true;
        }
        return false;
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
