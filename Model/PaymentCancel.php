<?php
namespace Tabby\Checkout\Model;

class PaymentCancel extends \Magento\Framework\Model\AbstractExtensibleModel
    implements \Tabby\Checkout\Api\PaymentCancelInterface {

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
    public function cancelPayment($cartId)
    {
        $this->_helper->ddlog("info", "cancel payment");

        $result = [];

        $result['success'] = $this->_helper->cancelCurrentOrder($cartId);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelCustomerPayment($cartId)
    {
        $this->_helper->ddlog("info", "cancel customer payment");

        $result = [];

        $result['success'] = $this->_helper->cancelCurrentCustomerOrder($cartId, $this->_userContext->getUserId());

        return $result;
    }
}
