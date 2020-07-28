<?php
namespace Tabby\Checkout\Model;

class PaymentAuth extends \Magento\Framework\Model\AbstractExtensibleModel
    implements \Tabby\Checkout\Api\PaymentAuthInterface {

    /**
     * @param \Tabby\Checkout\Helper\Order $orderHelper
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Authorization\Model\UserContextInterface $userContext,
        \Tabby\Checkout\Helper\Order $orderHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource, $resourceCollection, $data);

        $this->_helper = $orderHelper;
        $this->_userContext = $userContext;
    }

    /**
     * {@inheritdoc}
     */
    public function authPayment($cartId, $paymentId)
    {
        $data = array("payment.id" => $paymentId);
        $this->_helper->ddlog("info", "authorize payment", null, $data);

        $result = [];

        $result['success'] = $this->_helper->authorizePayment($cartId, $paymentId);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function authCustomerPayment($cartId, $paymentId)
    {
        $data = array("payment.id" => $paymentId);
        $this->_helper->ddlog("info", "authorize customer payment", null, $data);

        $result = [];

        $result['success'] = $this->_helper->authorizeCustomerPayment($cartId, $paymentId, $this->_userContext->getUserId());

        return $result;
    }
}
