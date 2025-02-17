<?php

namespace Tabby\Checkout\Model;

// AbstractExtensibleModel
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
// this class requirements
use Tabby\Checkout\Api\SessionDataInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Tabby\Checkout\Model\Api\DdLog;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Model\Api\Tabby\Checkout as CheckoutApi;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Model\Checkout\Payment\OrderHistory;
use Tabby\Checkout\Model\Checkout\Payment\BuyerHistory;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Tabby\Checkout\Model\MerchantCodeProvider;
use Magento\Authorization\Model\UserContextInterface;

class SessionData extends AbstractExtensibleModel implements SessionDataInterface
{
    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var OrderHistory
     */
    protected $_orderHistory;

    /**
     * @var BuyerHistory
     */
    protected $_buyerHistory;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var RestRequest
     */
    protected $_request;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var CheckoutApi
     */
    protected $_checkoutApi;

    /**
     * @var QuoteFactory
     */
    protected $_quoteFactory;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $_quoteIdMaskFactory;

    /**
     * @var PriceCurrencyInterface
     */
    protected $_priceCurrencyInterface;

    /**
     * @var MerchantCodeProvider
     */
    protected $merchantCodeProvider;

    /**
     * @var UserContextInterface
     */
    protected $userContext;

    /**
     * Class constructor
     *
     * @param Config $config
     * @param OrderHistory $orderHistory
     * @param BuyerHistory $buyerHistory
     * @param Session $checkoutSession
     * @param RestRequest $request
     * @param DdLog $ddlog
     * @param StoreManagerInterface $storeManager
     * @param CheckoutApi $checkoutApi
     * @param QuoteFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PriceCurrencyInterface $priceCurrencyInterface
     * @param MerchantCodeProvider $merchantCodeProvider
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Config $config,
        OrderHistory $orderHistory,
        BuyerHistory $buyerHistory,
        Session $checkoutSession,
        RestRequest $request,
        DdLog $ddlog,
        StoreManagerInterface $storeManager,
        CheckoutApi $checkoutApi,
        QuoteFactory $quoteFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PriceCurrencyInterface $priceCurrencyInterface,
        MerchantCodeProvider $merchantCodeProvider,
	UserContextInterface $userContext,
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

        $this->_config = $config;
        $this->_orderHistory = $orderHistory;
        $this->_buyerHistory = $buyerHistory;
        $this->_checkoutSession = $checkoutSession;
        $this->_request = $request;
        $this->_ddlog = $ddlog;
        $this->_storeManager = $storeManager;
        $this->_checkoutApi = $checkoutApi;
        $this->_quoteFactory = $quoteFactory;
        $this->_quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->_priceCurrencyInterface = $priceCurrencyInterface;
        $this->merchantCodeProvider = $merchantCodeProvider;
        $this->userContext = $userContext;
    }

    /**
     * @inheritdoc
     */
    public function createSessionForCustomer(string $cartId) : array
    {
        $quote = $this->_quoteFactory->create()->load($cartId);

	if (!$this->userContext->getUserId() || ($quote->getCustomerId() != $this->userContext->getUserId())) {
            return ['status' => 'Acceess denied.'];
        }
        return $this->createSession($quote);
    }

    /**
     * @inheritdoc
     */
    public function createSessionForGuest(string $cartId) : array
    {
        $quoteIdMask = $this->_quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        if (!$quoteIdMask || !$quoteIdMask->getQuoteId()) {
            return ['status' => 'Wrong quote'];
        }

        $quote = $this->_quoteFactory->create()->load($quoteIdMask->getQuoteId());

        if (!$quote->getCustomerIsGuest()) {
            return ['status' => 'Acceess denied.'];
        }

        return $this->createSession($quote);
    }

    /**
     * @inheritdoc
     */
    public function createSession($quote) : array
    {
        try {

            $data = json_decode($this->_request->getContent(), true);
            // allow use local store currency
            $data['payment'] = array_merge($data['payment'], $this->getPaymentObject($quote));
            // add order and buyer history
            $data['payment']['order_history'] = $this->_orderHistory
                ->getOrderHistoryLimited(null, $data['payment']['buyer']['email'], $data['payment']['buyer']['phone']);
            $data['payment']['buyer_history'] = $this->_buyerHistory->getBuyerHistoryObject(
                $this->_checkoutSession->getQuote()->getCustomer(),
                $data['payment']['order_history']
            );
            $data['payment']['meta'] = $this->_config->getPaymentObjectMetaFields();
            // get right merchant code
            $data['merchant_code'] = $this->merchantCodeProvider->getMerchantCodeForCart($quote);
            $session = $this->_checkoutApi->createSession(
                (int)$this->_storeManager->getStore()->getStoreId(),
                $data
            );
            return [[
                "status"                => $session->status,
                "payment_id"            => $session->payment->id,
                "available_products"    => $session->configuration->available_products
            ]];
        } catch (\Exception $e) {
            $this->_ddlog->log(
                'error',
                'Error creating prescoring session',
                $e,
                ['data' => $data]
            );
        }

        return [[
            'status'    => 'rejected'
        ]];
    }

    /**
     * Returns payment object from quote provided
     *
     * @param \Magentj\Quote\Api\Data\QuoteInterface $quote
     * @return array
     */
    protected function getPaymentObject($quote)
    {

        $address = $quote->getShippingAddress();
        $address->collectShippingRates();
    
        return [
            "amount"    => $this->getTabbyPrice($quote, 'grand_total'),
            "currency"  => $this->getIsInLocalCurrency() ? $quote->getQuoteCurrencyCode() : $quote->getBaseCurrencyCode(),
            "description" => $quote->getEntityId(),
            "order"     => [
                "tax_amount"        => $this->getTabbyPrice($address, 'tax_amount'),
                "shipping_amount"   => $this->getTabbyPrice($address, 'shipping_amount'),
                "discount_amount"   => $this->getTabbyPrice($quote, 'discount_amount'),
                "reference_id"      => $quote->getEntityId(),
                "items"             => $this->getQuoteItems($quote)
            ]
        ];
    }

    /**
     * Returns items from quote provided
     *
     * @param \Magentj\Quote\Api\Data\QuoteInterface $quote
     * @return array
     */
    private function getQuoteItems($quote)
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'title'         => $item->getName(),
                'description'   => $item->getDescription(),
                'quantity'      => $item->getQty() * 1,
                'unit_price'    => $this->getTabbyPrice($item, 'price')
                    - $this->getTabbyPrice($item, 'discount_amount')
                    + $this->getTabbyPrice($item, 'tax_amount'),
                'tax_amount'    => $this->getTabbyPrice($item, 'tax_amount'),
                'reference_id'  => $item->getSku(),
                //'image_url'     => $this->getSessionItemImageUrl($item),
                'product_url'   => $item->getProduct()->getUrlInStore(),
                //'category'      => $this->getSessionCategoryName($item)
            ];
        }
        return $items;
    }

    /**
     * Returns formatted price field value from object
     *
     * @param mixed $object
     * @param string $field
     * @return string
     */
    private function getTabbyPrice($object, $field)
    {
        return number_format(
            $this->getIsInLocalCurrency()
                ? ($object->getData($field) ?: 0)
                : ($object->getData('base_' . $field) ?: 0),
            2,
            '.',
            ''
        );
    }
    /**
     * Returns use local currency setting
     *
     * @return bool
     */
    private function getIsInLocalCurrency()
    {
        return $this->_config->getValue('local_currency');
    }
}
