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
    const ALLOWED_COUNTRIES = 'AE,SA,KW,BH';
    const PAYMENT_ID_FIELD = 'checkout_id';
    const TABBY_CURRENCY_FIELD = 'tabby_currency';

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

    protected $_httpClientFactory = null;

    protected $paymentExtensionFactory = null;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Sales\Model\Service\OrderService $orderService
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Tabby\Checkout\Gateway\Config\Config $config,
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Tabby\Checkout\Model\Api\Tabby\Payments $api
     * @param \Tabby\Checkout\Model\Api\DdLog $ddlog
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param \Magento\Directory\Helper\Data $directory
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
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Tabby\Checkout\Gateway\Config\Config $config,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Tabby\Checkout\Model\Api\Tabby\Payments $api,
        \Tabby\Checkout\Model\Api\DdLog $ddlog,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        \Magento\Directory\Helper\Data $directory = null
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
        $this->_invoiceService    = $invoiceService;
        $this->_orderService      = $orderService;
        $this->_httpClientFactory = $httpClientFactory;
        $this->_configModule      = $config;
        $this->_transactionFactory     = $transactionFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->_api   = $api;
        $this->_ddlog = $ddlog;
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
        return parent::canUseForCountry($country) && in_array($country, explode(',', static::ALLOWED_COUNTRIES));
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
        if ($this->getConfigData('local_currency')) {
            $payment->setAdditionalInformation(self::TABBY_CURRENCY_FIELD, 'order');
            $payment->save();
        }
        $result = $this->_api->getPayment($payment->getOrder()->getStoreId(), $id);
        $this->logger->debug(['authorize - result - ', (array)$result]);

        // check transaction details
        $order = $payment->getOrder();

        $logData = array(
            "payment.id"          => $id,
            "order.reference_id"  => $order->getIncrementId()
        );

        // check if payment authorized
        if (!$this->isAuthorized($result)) {
            $logData["payment.status"] = $result->status;
            $this->_ddlog->log("info", "payment is not authorized", null, $logData);
            throw new \Tabby\Checkout\Exception\NotAuthorizedException(
                __("Payment not authorized for your transaction, please contact support.")
            );
        }

        if ($this->getIsInLocalCurrency()) {
            // currency must match when use local_currency setting
            if ($order->getOrderCurrencyCode() != $result->currency) {
                $this->logger->debug([
                    'message'           => "Wrong currency code",
                    'Order currency'    => $order->getOrderCurrencyCode(),
                    'Trans currency'    => $result->currency
                ]);
                $logData = array(
                    "payment.id"        => $id,
                    "payment.currency"  => $result->currency,
                    "order.currency"    => $order->getOrderCurrencyCode()
                );
                $this->_ddlog->log("error", "wrong currency code", null, $logData);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
            if ($order->getGrandTotal() != $result->amount) {
                $this->logger->debug([
                    'message'       => "Wrong transaction amount",
                    'Order amount'  => $order->getGrandTotal(),
                    'Trans amount'  => $result->amount
                ]);
                $logData = array(
                    "payment.id"      => $id,
                    "payment.amount"  => $result->amount,
                    "order.amount"    => $order->getGrandTotal()
                );
                $this->_ddlog->log("error", "wrong currency code", null, $logData);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
            $payment->setBaseAmountAuthorized($order->getGrandTotal());
            $message = 'Authorized amount of %1.';
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage(__($message, $order->getOrderCurrency()->formatTxt($order->getGrandTotal()))->render());
        } else {
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
                $this->_ddlog->log("error", "wrong currency code", null, $logData);
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
                $this->_ddlog->log("error", "wrong currency code", null, $logData);
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Something wrong with your transaction, please contact support.")
                );
            }
        }
        $logData = array(
            "payment.id" => $id
        );
        $this->_ddlog->log("info", "set transaction ID", null, $logData);
        $payment->setLastTransId  ($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD));
        $payment->setTransactionId($payment->getAdditionalInformation(self::PAYMENT_ID_FIELD))
                ->setIsTransactionClosed(0);

        $payment->setBaseAmountAuthorized($amount);

        $this->logger->debug(['authorize', 'end']);

        $this->_api->updateReferenceId($payment->getOrder()->getStoreId(), $id, $order->getIncrementId());
    
        $this->setAuthResponse($result);

        return $this;
    }

    protected function createInvoiceForAutoCapture(\Magento\Payment\Model\InfoInterface $payment, $response) {

        // creat einvoice for Tabby end autoCapture
        if ($response->status == 'CLOSED' && count($response->captures) > 0 && $payment->getOrder()->canInvoice()) {
            $txnId = $response->captures[0]->id;
            $invoice = $payment->getOrder()->prepareInvoice();
            $captureCase = \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE;
            $invoice->setRequestedCaptureCase($captureCase);
            $invoice->setTransactionId($txnId);

            $invoice->pay();

            $invoice->register();
            
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
    protected function possiblyCreateInvoice($order) {
        // create invoice for CaptureOn order
        try {
            if ($order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING && !$order->hasInvoices()) {
                if ($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::CAPTURE_ON) == 'order') {
                    $this->createInvoice(
                        $order,
                        \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE
                    );
                } else {
                    if ($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::CREATE_PENDING_INVOICE)) {
                        $this->createInvoice($order);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "could not possibly create invoice", $e);
            return false;
        }
    }
    public function createInvoice($order, $captureCase = \Magento\Sales\Model\Order\Invoice::NOT_CAPTURE)
    {
        try
        {
            // check order and order payment method code
            if (
                $order
                && $order->canInvoice()
                && $order->getPayment()
                && $order->getPayment()->getMethodInstance()
            ) {
                if (!$order->hasInvoices()) {

                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->_registry->register('current_invoice', $invoice);
                    }
                    $invoice->setRequestedCaptureCase($captureCase);
                    $invoice->register();
                    $invoice->getOrder()->setCustomerNoteNotify(false);
                    $invoice->getOrder()->setIsInProcess(true);
                    $transactionSave = $this->_transactionFactory
                                            ->create()
                                            ->addObject($invoice)
                                            ->addObject($order->getPayment())
                                            ->addObject($invoice->getOrder());
                    $transactionSave->save();
                    if ($captureCase == \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE) {
                        $this->_registry->unregister('current_invoice');
                    }
                }

            }
        } catch (\Exception $e) {
            $this->_ddlog->log("error", "could not create invoice", $e);
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
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $auth = $payment->getAuthorizationTransaction();
        if (!$auth) {
            $logData = array(
                "order.id"  => $payment->getOrder()->getIncrementId(),
                "noauth"   => true
            );
            $this->_ddlog->log("error", "capture error, no authorization transaction available", null, $logData);
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
            $this->_ddlog->log("info", "bypass payment capture", null, $logData);
            return $this;
        }

        $invoice = $this->_registry->registry('current_invoice');
        $data = [
            "amount"            => $payment->formatAmount($this->getTabbyPrice($invoice, 'grand_total')),
            "tax_amount"        => $payment->formatAmount($this->getTabbyPrice($invoice, 'tax_amount' )),
            "shipping_amount"   => $payment->formatAmount($this->getTabbyPrice($invoice, 'shipping_amount')),
            "created_at"        => null
        ];

        $data['items'] = [];
        foreach ($invoice->getItems() as $item) {
            $data['items'][] = [
                'title'         => $item->getName() ?: '',
                'description'   => $item->getName() ?: '',
                'quantity'      => (int)$item->getQty(),
                'unit_price'    => $payment->formatAmount($this->getTabbyPrice($item, 'price_incl_tax')),
                'reference_id'  => $item->getProductId() . '|' . $item->getSku()
            ];
        }

        $logData = array(
            "payment.id"  => $payment_id
        );
        $this->_ddlog->log("info", "capture payment", null, $logData);

        $this->logger->debug(['capture', $payment_id, $data]);
        $result = $this->_api->capturePayment($payment->getOrder()->getStoreId(), $payment_id, $data);
        $this->logger->debug(['capture - result', (array)$result]);

        $txn = $this->getLatestItem($result->captures);
        if (!$txn) {
            $this->_ddlog->log("error", "capture error, check Tabby response", null, $logData);
            throw new \Exception(
                __("Something wrong")
            );
        }

        $payment->setLastTransId  ($txn->id);
        $payment->setTransactionId($txn->id)
                ->setParentTransactionId($payment_id)
                ->setIsTransactionClosed(0);

        if ($this->getIsInLocalCurrency()) {
            $message = 'Captured amount of %1 online.';
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage(__($message, $payment->getOrder()->getOrderCurrency()->formatTxt($this->getTabbyPrice($invoice, 'grand_total')))->render());
        }

        return $this;
    }
    protected function getLatestItem($items) {
        $item = array_pop($items);
        foreach ($items as $temp) {
            if ($temp->created_at > $item->created_at) $item = $temp;
        }
        return $item;
    }
    protected function getIsInLocalCurrency() {
        return ($this->getInfoInstance()->getAdditionalInformation(self::TABBY_CURRENCY_FIELD) == 'order');
    }
    protected function getTabbyPrice($object, $field) {
        return $this->getIsInLocalCurrency() ? $object->getData($field) : $object->getData('base_' . $field); 
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $creditmemo = $this->_registry->registry('current_creditmemo');
        $invoice = $creditmemo->getInvoice();;
        $capture_txn = $payment->getAuthorizationTransaction();

        $payment_id = $capture_txn->getParentTxnId();

        $data = [
            "capture_id"        => $invoice->getTransactionId(),
            "amount"            => $payment->formatAmount($this->getTabbyPrice($creditmemo, 'grand_total'))
        ];

        $data['items'] = [];
        foreach ($creditmemo->getItems() as $item) {
            $data['items'][] = [
                'title'         => $item->getName() ?: '',
                'description'   => $item->getName() ?: '',
                'quantity'      => (int)$item->getQty(),
                'unit_price'    => $payment->formatAmount($this->getTabbyPrice($creditmemo, 'price_incl_tax')),
                'reference_id'  => $item->getProductId() . '|' . $item->getSku()
            ];
        }

        $logData = array(
            "payment.id"  => $payment_id
        );
        $this->_ddlog->log("info", "refund payment", null, $logData);

        $this->logger->debug(['refund', $payment_id, $data]);
        $result = $this->_api->refundPayment($payment->getOrder()->getStoreId(), $payment_id, $data);
        $this->logger->debug(['refund - result', (array)$result]);

        $txn = $this->getLatestItem($result->refunds);
        if (!$txn) {
            $this->_ddlog->log("error", "refund error, check Tabby response", null, $logData);
            throw new \Exception(
                __("Something wrong")
            );
        }

        if ($this->getIsInLocalCurrency()) {
            $message = 'We refunded %1 online.';
            $msg = __($message, $payment->getOrder()->getOrderCurrency()->formatTxt($this->getTabbyPrice($creditmemo, 'grand_total')));
            $this->getPaymentExtensionAttributes($payment)
                ->setNotificationMessage($msg->render());
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
        $this->_ddlog->log("info", "void payment", null, $logData);
        $result = $this->_api->closePayment($payment->getOrder()->getStoreId(), $payment->getParentTransactionId());

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
        $response = $this->_api->getPayment($payment->getOrder()->getStoreId(), $txn->getTxnId());

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

        $this->_api->updateReferenceId($payment->getOrder()->getStoreId(), $paymentId, $payment->getOrder()->getIncrementId());

        return true;
    }

    public function authorizePayment(\Magento\Payment\Model\InfoInterface $payment, $paymentId, $source = 'checkout') {

        $order = $payment->getOrder();

        if ($order->getId() && in_array($order->getState(), [\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, \Magento\Sales\Model\Order::STATE_NEW])) {

            if (!$payment->getAuthorizationTransaction()) {
                
                $this->setStore($order->getStoreId());

                $payment->setAdditionalInformation(['checkout_id' => $paymentId]);

                $this->_ddlog->log('info', 'authorize payment from ' . $source, null, ['payment.id' => $paymentId, "order.reference_id" => $order->getIncrementId()]);

                $payment->authorize(true, $order->getBaseGrandTotal());

                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, $order, true);

                if ($this->getAuthResponse()->status == 'CLOSED') $transaction->setIsClosed(true);

                $this->createInvoiceForAutoCapture($payment, $this->getAuthResponse());

                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $order->setStatus($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::AUTHORIZED_STATUS));

                $transactionSave = $this->_transactionFactory
                    ->create()
                    ->addObject($order)
                    ->addObject($payment)
                    ->addObject($transaction);

                $transactionSave->save();

                $this->possiblyCreateInvoice($order);

                if ($this->getConfigData(\Tabby\Checkout\Gateway\Config\Config::MARK_COMPLETE) == 1) {
                    $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
                    $order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE));
                    $order->addStatusHistoryComment("Autocomplete by Tabby", $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_COMPLETE));

                    $order->save();
                }

                $this->_orderService->notify($order->getId());

                return true;
            } else {
                $this->_ddlog->log('info', 'order not have auth transaction assigned', null, [
                    'payment.id'        => $paymentId, 
                    "order.reference_id"=> $order->getIncrementId()
                ]);
            }
        } else {
            $this->_ddlog->log('info', 'order state is not valid for auth', null, [
                'payment.id'        => $paymentId, 
                "order.reference_id"=> $order->getIncrementId(),
                "order.state"       => $order->getState()
            ]);
        };
        return false;
    }
    /**
     * Returns payment extension attributes instance.
     *
     * @param Payment $payment
     * @return \Magento\Sales\Api\Data\OrderPaymentExtensionInterface
     */
    private function getPaymentExtensionAttributes(\Magento\Sales\Api\Data\OrderPaymentInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }

        return $extensionAttributes;
    }
}
