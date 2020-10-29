<?php
namespace Tabby\Checkout\Model\Method;

use Tabby\Checkout\Gateway\Config\Config;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\DataObject;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Directory\Helper\Data as DirectoryHelper;

class Checkout extends AbstractMethod {

    /**
     * @var string
     */
    protected $_code = 'tabby_checkout';

    /**
     * @var string
     */
    const API_URI = 'https://api.tabby.ai/api/v1/payments/';
    const ALLOWED_COUNTRIES = 'AE,SA';
    const PAYMENT_ID_FIELD = 'checkout_id';

    /**
     * @var string
     */
    protected $_formBlockType = \Magento\Payment\Block\Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \Tabby\Checkout\Block\Info::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canReviewPayment = false;

    /**
     * @var bool
     */
    protected $_canCancelInvoice = true;

    /**
     * @var bool
     */
    protected $_httpClientFactory = null;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
     * @param \Tabby\Checkout\Gateway\Config\Config $config,
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param DirectoryHelper $directory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Tabby\Checkout\Gateway\Config\Config $config,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->_httpClientFactory = $httpClientFactory;
        $this->_configModule = $config;
        $this->_transactionFactory = $transactionFactory;
    }
    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     * @deprecated 100.2.0
     */
    public function canUseForCountry($country)
    {
        return parent::canUseForCountry($country) && in_array($country, explode(',', self::ALLOWED_COUNTRIES));
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->setAdditionalInformation(
            [
                self::PAYMENT_ID_FIELD => $additionalData->getCheckoutId()
            ]
        );

        $this->logger->debug(['assignData', $info->getAdditionalInformation(self::PAYMENT_ID_FIELD)]);
        //$this->logger->debug(['assignData - info', $info->getCheckoutId()]);
        return $this;
    }

    /**
     * Instantiate state and set it to state object
     *
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();

        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        //$stateObject->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * Authorize payment Tabby Checkout
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $id = $payment->getAdditionalInformation(self::PAYMENT_ID_FIELD);
        $result = $this->request($id);
        $this->logger->debug(['authorize - result - ', (array)$result]);

        // check if payment authorized

        if (!$this->isAuthorized($result)) {
            $logData = array(
                "payment.id" => $id,
                "payment.status" => $result->status
            );
            $this->ddlog("error", "payment is not authorized", null, $logData);
            throw new \Tabby\Checkout\Exception\NotAuthorizedException(
                __("Payment not authorized for your transaction, please contact support.")
            );
        }

        // check transaction details
        $order = $payment->getOrder();

        $logData = array(
            "payment.id"          => $id,
            "order.reference_id"  => $order->getIncrementId()
        );
// Commented out, because we can send SAR for SA country. SAR = AED
/*
        if ($order->getBaseCurrencyCode() != $result->currency) {
            $this->logger->debug([
                'message'           => "Wrong currency code",
                'Order currency'    => $order->getBaseCurrencyCode(),
                'Trans currency'    => $result->currency
            ]);
            $logData = array(
                "payment.id"        => $id,
                "payment.currency"  => $result->currency,
                "order.currency"    => $order->getBaseCurrencyCode()
            );
            $this->ddlog("error", "wrong currency code", null, $logData);
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Something wrong with your transaction, please contact support.")
            );
        }
*/

        if ($amount != $result->amount) {
            $this->logger->debug([
                'message'       => "Wrong transaction amount",
                'Order amount'  => $amount,
                'Trans amount'  => $result->amount
            ]);
            $logData = array(
                "payment.id"      => $id,
                "payment.amount"  => $result->amount,
                "order.amount"    => $amount
            );
            $this->ddlog("error", "wrong currency code", null, $logData);
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Something wrong with your transaction, please contact support.")
            );
        }

        $logData = array(
            "payment.id" => $id
        );
        $this->ddlog("info", "set transaction ID", null, $logData);
        $payment->setLastTransId  ($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD));
        $payment->setTransactionId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD))
                ->setIsTransactionClosed(0);

        $payment->setAmountAuthorized($amount);

        $this->logger->debug(['authorize', 'end']);

        $this->updateReferenceId($id, $order->getIncrementId());
    
        $this->setAuthResponse($result);

        return $this;
    }

    protected function createInvoiceForAutoCapture(\Magento\Payment\Model\InfoInterface $payment, $response) {
        if ($response->status == 'CLOSED' && count($response->captures) > 0 && $payment->getOrder()->canInvoice()) {
            $txnId = $response->captures[0]->id;
            $invoice = $payment->getOrder()->prepareInvoice();
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
            $invoice->setRequestedCaptureCase($captureCase);
            $invoice->setTransactionId($txnId);

            $invoice->pay();

            $invoice->register();
            //$invoice->getOrder()->setCustomerNoteNotify(false);
            //$invoice->getOrder()->setIsInProcess(true);
            
            $payment->setParentTransactionId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD));
            $payment->setTransactionId($txnId);
            $payment->setShouldCloseParentTransaction(true);

            $txn = $payment->AddTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, $invoice, true);

            $formatedPrice = $invoice->getOrder()->getBaseCurrency()->formatTxt(
                $invoice->getOrder()->getGrandTotal()
            );

            $message = __('The Captured amount is %1.', $formatedPrice);
            $payment->addTransactionCommentsToOrder(
                $txn,
                $message
            );


            $transactionSave = $this->_transactionFactory
                                    ->create()
                                    ->addObject($invoice)
                                    ->addObject($payment)
                                    ->addObject($invoice->getOrder());

            $transactionSave->save();
        }
    }

    protected function isAuthorized($response) {
        $result = false;
        switch ($response->status) {
            case 'AUTHORIZED': 
                $result = true;
                break;
            case 'CLOSED':
                $result = (count($response->captures) > 0 && ($response->captures[0]->amount == $response->amount));
                break;
        }
        return $result;
    }
    public function updateReferenceId($id, $referenceId) {
        $data = ["order" => [
            "reference_id"  => $referenceId
        ]];

        $logData = array(
            "payment.id"          => $id,
            "order.reference_id"  => $referenceId
        );
        $this->ddlog("info", "set reference ID", null, $logData);
        $result = $this->request($id, \Zend_Http_Client::PUT, $data);
        $this->logger->debug(['updateReferenceId - update order #  - ', (array)$result]);
    }
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $auth = $payment->getAuthorizationTransaction();
        if (!$auth) {
            $logData = array(
                "order.id"  => $payment->getOrder()->getIncrementId(),
                "noauth"   => true
            );
            $this->ddlog("error", "capture error, no authorization transaction available", null, $logData);
            throw new \Exception(
                __("No information about authorization transaction.")
            );
        }
        $payment_id = $auth->getTxnId();

        // bypass payment capture
        if ($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::CAPTURE_ON) == 'nocapture') {
            $logData = array(
                "payment.id"  => $payment_id,
                "nocapture"   => true
            );
            $this->ddlog("info", "bypass payment capture", null, $logData);
            return $this;
        }

        $invoice = $this->_registry->registry('current_invoice');

        $data = [
            "amount"            => $payment->formatAmount($invoice->getGrandTotal()),
            "tax_amount"        => $payment->formatAmount($invoice->getTaxAmount ()),
            "shipping_amount"   => $payment->formatAmount($invoice->getShippingAmount()),
            "created_at"        => null
        ];

        $data['items'] = [];
        foreach ($invoice->getItems() as $item) {
            $data['items'][] = [
                'title'         => $item->getName() ?: '',
                'description'   => $item->getName() ?: '',
                'quantity'      => (int)$item->getQty(),
                'unit_price'    => $payment->formatAmount($item->getPriceInclTax()),
                'reference_id'  => $item->getProductId() . '|' . $item->getSku()
            ];
        }

        $logData = array(
            "payment.id"  => $payment_id
        );
        $this->ddlog("info", "capture payment", null, $logData);

        $this->logger->debug(['capture', $payment_id, $data]);
        $result = $this->request($payment_id . '/captures', \Zend_Http_Client::POST, $data);
        $this->logger->debug(['capture - result', (array)$result]);

        $txn = array_pop($result->captures);
        if (!$txn) {
            $this->ddlog("error", "capture error, check Tabby response", null, $logData);
            throw new \Exception(
                __("Something wrong")
            );
        }

        $payment->setLastTransId  ($txn->id);
        $payment->setTransactionId($txn->id)
                ->setIsTransactionClosed(0);

        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $creditmemo = $this->_registry->registry('current_creditmemo');
        $invoice = $creditmemo->getInvoice();;
        $capture_txn = $payment->getAuthorizationTransaction();

        $payment_id = $capture_txn->getParentTxnId();

        $data = [
            "capture_id"        => $invoice->getTransactionId(),
            "amount"            => $payment->formatAmount($creditmemo->getGrandTotal())
        ];

        $data['items'] = [];
        foreach ($creditmemo->getItems() as $item) {
            $data['items'][] = [
                'title'         => $item->getName() ?: '',
                'description'   => $item->getName() ?: '',
                'quantity'      => (int)$item->getQty(),
                'unit_price'    => $payment->formatAmount($item->getPriceInclTax()),
                'reference_id'  => $item->getProductId() . '|' . $item->getSku()
            ];
        }

        $logData = array(
            "payment.id"  => $payment_id
        );
        $this->ddlog("info", "refund payment", null, $logData);

        $this->logger->debug(['refund', $payment_id, $data]);
        $result = $this->request($payment_id . '/refunds', \Zend_Http_Client::POST, $data);
        $this->logger->debug(['refund - result', (array)$result]);

        $txn = array_pop($result->refunds);
        if (!$txn) {
            $this->ddlog("error", "refund error, check Tabby response", null, $logData);
            throw new \Exception(
                __("Something wrong")
            );
        }

        $payment->setLastTransId  ($txn->id);
        $payment->setTransactionId($txn->id)
                ->setIsTransactionClosed(0);

        return $this;
    }

    /**
     * Void payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $this->logger->debug(['void - txn_id', $payment->getParentTransactionId()]);

        $logData = array(
            "payment.id"  => $payment->getParentTransactionId()
        );
        $this->ddlog("info", "void payment", null, $logData);
        $result = $this->request($payment->getParentTransactionId() . '/close', \Zend_Http_Client::POST);

        $this->logger->debug(['void - result', (array)$result]);

        return $this;
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Fetch transaction info
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function fetchTransactionInfo(\Magento\Payment\Model\InfoInterface $payment, $transactionId)
    {

        $transactionId = preg_replace("/-void$/is", "", $transactionId);

        $txn = $payment->getAuthorizationTransaction();
        $this->logger->debug([$transactionId]);
        $response = $this->request($txn->getTxnId());

        $result = [];
        if ($txn->getTxnId() == $transactionId) {
            foreach ($response as $key => $value) {
                if ($key == 'order_history') continue;
                if (!is_scalar($value)) $value = json_encode($value);
                $result[$key] = $value;
            }
        } else {
            // search transaction in captures and refunds
            foreach ($response->captures as $capture) {
                if ($capture->id != $transactionId) continue;
                foreach ($capture as $key => $value) {
                    if (!is_scalar($value)) $value = json_encode($value);
                    $result[$key] = $value;
                }
            }
            foreach ($response->refunds as $refund) {
                if ($refund->id != $transactionId) continue;
                foreach ($refund as $key => $value) {
                    if (!is_scalar($value)) $value = json_encode($value);
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    public function request($endpoint, $method = \Zend_Http_Client::GET, $data = null) {
        //$client = $this->_httpClientFactory->create();
        $client = new \Zend_Http_Client(self::API_URI . $endpoint, array('timeout' => 120));

        $this->logger->debug(['request', $endpoint, $method, (array)$data]);

        $client->setUri(self::API_URI . $endpoint);
        $client->setMethod($method);
        $client->setHeaders("Authorization", "Bearer " . $this->_configModule->getSecretKey());

        if ($method !== \Zend_Http_Client::GET) {
            $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');
            $params = json_encode($data);
            $this->logger->debug(['request - params', $params]);
            $client->setRawData($params); //json
        }

        $response = $client->request();

        $logData = array(
            "request"           => $client->getLastRequest(),
            "response.body"     => $response->getBody(),
            "response.code"     => $response->getStatus(),
            "response.headers"  => $response->getHeaders()
        );
        $this->ddlog("info", "api call", null, $logData);

        $result = [];
        $this->logger->debug(['response', (array)$response]);
        switch ($response->getStatus()) {
        case 200:
            $result = json_decode($response->getBody());
            $this->logger->debug(['response - success data', (array)$result]);
            break;
        case 404:
            throw new \Tabby\Checkout\Exception\NotFoundException(
                __("Transaction does not exists")
            );
        default:
            $body = $response->getBody();
            $msg = "Server returned: " . $response->getStatus() . '. ';
            if (!empty($body)) {
                $result = json_decode($body);
                $this->logger->debug(['response - body - ', (array)$result]);
                $msg .= $result->errorType . ': ' . $result->error;
                if ($result->error == 'already closed' && preg_match("#close$#", $endpoint)) return $result;
            }
            throw new \Magento\Framework\Exception\LocalizedException(
                __($msg)
            );
        }

        return $result;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     * @deprecated 100.2.0
     */
    public function getConfigData($field, $storeId = null)
    {
        // bypass initial authorize
        //if ($field == 'payment_action') return null;

        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        if (in_array($field, ['active', 'title', 'sort_order'])) {
            $path = 'payment/' . $this->getCode() . '/' . $field;
        } else {
            $path = 'tabby/tabby_api/' . $field;
        }
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     * @deprecated 100.2.0
     */
    public function getTitle()
    {
        return __($this->getConfigData('title'));
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
        return parent::isAvailable($quote) && $this->checkSkus($quote);
    }

    public function checkSkus(\Magento\Quote\Api\Data\CartInterface $quote = null) {

        $skus = explode("\n", $this->getConfigData("disable_for_sku"));
        $result = true;

        foreach ($skus as $sku) {
            if (!$quote) break;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() == trim($sku, "\r\n ")) {
                    $result = false;
                    break 2;
                }
            }
        }

        return $result;
    }

    public function registerPayment(\Magento\Payment\Model\InfoInterface $payment, $paymentId) {
        $payment->setAdditionalInformation(self::PAYMENT_ID_FIELD, $paymentId);
        $payment->save();

        $this->updateReferenceId($paymentId, $payment->getOrder()->getIncrementId());

        return true;
    }

    public function authorizePayment(\Magento\Payment\Model\InfoInterface $payment, $paymentId) {

        $order = $payment->getOrder();

        if ($order->getId() && $order->getState() == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {

            if (!$payment->getAuthorizationTransaction()) {
                $payment->setAdditionalInformation(['checkout_id' => $paymentId]);

                $payment->authorize(true, $order->getBaseGrandTotal());

                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, $order, true);

                if ($this->getAuthResponse()->status == 'CLOSED') $transaction->setIsClosed(true);

                $this->createInvoiceForAutoCapture($payment, $this->getAuthResponse());

                if ($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::MARK_COMPLETE) == 1) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                    $order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE));
                    $order->addStatusHistoryComment("Autocomplete by Tabby", $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE));
                } else {
                    $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                    $order->setStatus($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::AUTHORIZED_STATUS));
                }

                $order->save();

                return true;
            }
        };
        return false;
    }

    public function ddlog($status = "error", $message = "Something went wrong", $e = null, $data = null) {
        $client = new \Zend_Http_Client("https://http-intake.logs.datadoghq.eu/v1/input");

        $client->setMethod(\Zend_Http_Client::POST);
        $client->setHeaders("DD-API-KEY", "a06dc07e2866305cda6ed90bf4e46936");
        $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        $storeURL = parse_url($storeManager->getStore()->getBaseUrl());

        $moduleInfo =  $objectManager->get('Magento\Framework\Module\ModuleList')->getOne('Tabby_Checkout');

        $log = array(
            "status"  => $status,
            "message" => $message,

            "service"  => "magento2",
            "hostname" => $storeURL["host"],

            "ddsource" => "php",
            "ddtags"   => sprintf("env:prod,version:%s", $moduleInfo["setup_version"])
        );

        if ($e) {
            $log["error.kind"]    = $e->getCode();
            $log["error.message"] = $e->getMessage();
            $log["error.stack"]   = $e->getTraceAsString();
        }

        if ($data) {
            $log["data"] = $data;
        }

        $params = json_encode($log);
        $client->setRawData($params);

        $client->request();
    }
}
