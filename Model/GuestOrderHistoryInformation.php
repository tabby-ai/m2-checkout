<?php

namespace Tabby\Checkout\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
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
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $quoteRepository
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
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $quoteRepository,
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
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Returns order history limited to 10 latest records
     *
     * @param string $cartId
     * @return array
     */
    public function getOrderHistory($cartId)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        /** @var Quote $quote */
        $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());

        $email = $quote->getCustomerEmail();
        $phone = $quote->getBillingAddress()?->getTelephone();

        return $this->orderHistory->getOrderHistoryLimited(null, $email, $phone);
    }
}
