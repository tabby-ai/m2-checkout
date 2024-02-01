<?php

namespace Tabby\Checkout\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Tabby\Checkout\Api\GuestOrderHistoryInformationInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Tabby\Checkout\Model\Checkout\Payment\OrderHistory;

class GuestOrderHistoryInformation extends AbstractExtensibleModel implements GuestOrderHistoryInformationInterface
{
    /**
     * @var ConfigProvider
     */
    protected $orderHistory;

    /**
     * @param OrderHistory $orderHistory
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        OrderHistory $orderHistory,
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

        $this->orderHistory = $orderHistory;
    }

    /**
     * Returns order history limited to 10 latest records
     *
     * @param string $email
     * @param string|null $phone
     * @return array
     */
    public function getOrderHistory($email, $phone = null)
    {
        return $this->orderHistory->getOrderHistoryLimited(null, $email, $phone);
    }
}
