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
use Tabby\Checkout\Api\PaymentAuthInterface;
use Tabby\Checkout\Helper\Order;

class PaymentAuth extends AbstractExtensibleModel implements PaymentAuthInterface
{
    /**
     * @var Order
     */
    protected $_helper;

    /**
     * @var UserContextInterface
     */
    protected $_userContext;

    /**
     * @param UserContextInterface $userContext
     * @param Order $orderHelper
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
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource,
            $resourceCollection, $data);

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

        $result['success'] = $this->_helper->authorizeCustomerPayment($cartId, $paymentId,
            $this->_userContext->getUserId());

        return $result;
    }
}
