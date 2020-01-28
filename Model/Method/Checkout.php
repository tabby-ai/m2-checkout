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
				'checkout_id' => $additionalData->getCheckoutId()
			]
		);

		$this->logger->debug(['assignData', $info->getAdditionalInformation('checkout_id')]);
		//$this->logger->debug(['assignData - info', $info->getCheckoutId()]);
		return $this;
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
		$id = $payment->getAdditionalInformation('checkout_id');
		$result = $this->request($id);
		$this->logger->debug(['authorize - result - ', (array)$result]);
		
		// check transaction details
		$order = $payment->getOrder();
		if ($order->getOrderCurrencyCode() != $result->currency) {
			$this->logger->debug([
				'message'			=> "Wrong currency code", 
				'Order currency'	=> $order->getOrderCurrencyCode(), 
				'Trans currency'	=> $result->currency
			]);
			throw new \Magento\Framework\Exception\LocalizedException(
				__("Something wrong with your transaction, please contact support.")
			);
		}
		if ($amount != $result->amount) {
			$this->logger->debug([
				'message'		=> "Wrong transaction amount", 
				'Order amount'	=> $amount, 
				'Trans amount'	=> $result->amount
			]);
			throw new \Magento\Framework\Exception\LocalizedException(
				__("Something wrong with your transaction, please contact support.")
			);
		}

		$payment->setLastTransId  ($payment->getAdditionalInformation('checkout_id'));
		$payment->setTransactionId($payment->getAdditionalInformation('checkout_id'))
			->setIsTransactionClosed(0);

		$payment->setAmountAuthorized($amount);

		$this->logger->debug(['authorize', 'end']);

		$data = ["order" => [
			"reference_id"	=> $order->getIncrementId()
		]];

		$result = $this->request($id, \Zend_Http_Client::PUT, $data);
		$this->logger->debug(['authorize - update order #  - ', (array)$result]);


		return $this;
	}
	public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{

		$invoice = $this->_registry->registry('current_invoice');

		$data = [
			"amount"			=> $payment->formatAmount($invoice->getGrandTotal()),
			"tax_amount"		=> $payment->formatAmount($invoice->getTaxAmount ()),
			"shipping_amount"	=> $payment->formatAmount($invoice->getShippingAmount()),
			"created_at"		=> null
		];

		$data['items'] = [];
		foreach ($invoice->getItems() as $item) {
			$data['items'][] = [
				'title'			=> $item->getName(),
				'description'	=> $item->getName(),
				'quantity'		=> (int)$item->getQty(),
				'unit_price'	=> $payment->formatAmount($item->getPrice()),
				'reference_id'	=> $item->getProductId() . '|' . $item->getSku()
			];
		}
		
		$auth = $payment->getAuthorizationTransaction();
		$payment_id = $auth->getTxnId();

		$this->logger->debug(['capture', $payment_id, $data]);
		$result = $this->request($payment_id . '/captures', \Zend_Http_Client::POST, $data);
		$this->logger->debug(['capture - result', (array)$result]);

		$txn = array_pop($result->captures);
		if (!$txn) {
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
			"capture_id"		=> $invoice->getTransactionId(),
			"amount"			=> $payment->formatAmount($creditmemo->getGrandTotal())
		];

		$data['items'] = [];
		foreach ($creditmemo->getItems() as $item) {
			$data['items'][] = [
				'title'			=> $item->getName(),
				'description'	=> $item->getName(),
				'quantity'		=> (int)$item->getQty(),
				'unit_price'	=> $payment->formatAmount($item->getPrice()),
				'reference_id'	=> $item->getProductId() . '|' . $item->getSku()
			];
		}
		$this->logger->debug(['refund', $payment_id, $data]);
		$result = $this->request($payment_id . '/refunds', \Zend_Http_Client::POST, $data);
		$this->logger->debug(['refund - result', (array)$result]);

		$txn = array_pop($result->refunds);
		if (!$txn) {
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

		$result = $this->request($payment->getParentTransactionId() . '/close', \Zend_Http_Client::POST);
		
		$this->logger->debug(['void - result', (array)$result]);

        return $this;
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
		$client = $this->_httpClientFactory->create();

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

		$response= $client->request();
		
		$result = [];
		$this->logger->debug(['response', (array)$response]);
		switch ($response->getStatus()) {
			case 200:
				$result = json_decode($response->getBody());
				$this->logger->debug(['response - success data', (array)$result]);
				break;
			default:
				$body = $response->getBody();
				$msg = "Server returned: " . $response->getStatus() . '. ';
				if (!empty($body)) {
					$result = json_decode($body);
					$this->logger->debug(['response - body - ', (array)$result]);
					$msg .= $result->errorType . ': ' . $result->error;
				}
				throw new \Magento\Framework\Exception\LocalizedException(
					__($msg)
				);
		}
		
		return $result;
	}
}
