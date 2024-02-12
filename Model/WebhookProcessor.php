<?php

namespace Tabby\Checkout\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use Tabby\Checkout\Model\Api\DdLog;
use Tabby\Checkout\Api\WebhookProcessorInterface;
use Tabby\Checkout\Helper\Order;
use Tabby\Checkout\Model\Api\Tabby\Payments as PaymentApi;

class WebhookProcessor extends AbstractExtensibleModel implements WebhookProcessorInterface
{
    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * @var UserContextInterface
     */
    protected $_userContext;

    /**
     * @var RestRequest
     */
    protected $_request;

    /**
     * @var PaymentApi
     */
    protected $_paymentApi;

    /**
     * Class constructor
     *
     * @param UserContextInterface $userContext
     * @param Order $orderHelper
     * @param RestRequest $request
     * @param DdLog $ddlog
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param PaymentApi $paymentApi
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        UserContextInterface $userContext,
        Order $orderHelper,
        RestRequest $request,
        DdLog $ddlog,
        StoreManagerInterface $storeManager,
        Emulation $emulation,
        PaymentApi $paymentApi,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_userContext = $userContext;
        $this->_request = $request;
        $this->_ddlog = $ddlog;
        $this->_orderHelper = $orderHelper;
        $this->_storeManager = $storeManager;
        $this->_emulation = $emulation;
        $this->_paymentApi = $paymentApi;
    }

    /**
     * @inheritdoc
     */
    public function process($id, $status) : string
    {
        $webhook = $this->_request->getContent();

        return $this->processPaymentUpdate($webhook) ? 'success' : 'failed';
    }

    /**
     * @inheritdoc
     */
    public function processPaymentUpdate($webhook) : bool
    {
        $emulation = false;
        $result = true;
        try {
            $webhook = json_decode($webhook);

            if (is_object($webhook)) {
                $data = [
                    'payment.id' => $webhook->id,
                    'content' => $webhook
                ];
                if (!property_exists($webhook, 'order') || !is_object($webhook->order)
                    || !property_exists($webhook->order, 'reference_id')) {
                    $this->_ddlog->log("info", "webhook received - no reference id - ignored", null, $data);
                    return false;
                }

                $data['order.reference_id'] = $webhook->order->reference_id;
                $this->_ddlog->log("info", "webhook received", null, $data);
                // emulate order store if needed
                if (($storeId = $this->_orderHelper->getOrderStoreId($webhook->order->reference_id)) !==
                    $this->_storeManager->getStore()->getId()) {
                    $this->_emulation->startEnvironmentEmulation($storeId);
                    $emulation = true;
                }

                if (is_object($webhook) && $this->isAuthorized($webhook)) {
                    $this->_orderHelper->authorizeOrder($webhook->order->reference_id, $webhook->id, 'webhook');
                } elseif ($this->isRejectedOrExpired($webhook)) {
                    $this->_orderHelper->noteRejectedOrExpired($webhook);
                } else {
                    $this->_ddlog->log("error", "webhook ignored", null, ['data' => $webhook]);
                }
            } else {
                $this->_ddlog->log("error", "webhook wrong", null, ['data' => $webhook]);
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "webhook error", $e, ['data' => $webhook]);
            $result = false;
        } finally {
            if ($emulation) {
                $this->_emulation->stopEnvironmentEmulation();
            };
        }
        return $result;
    }

    /**
     * Check for rejected or expired event
     *
     * @param \StdClass $webhook
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
     * Check for authorized event
     *
     * @param \StdClass $webhook
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
