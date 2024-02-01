<?php

namespace Tabby\Checkout\Controller\Redirect;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Tabby\Checkout\Helper\Order;

class Index extends Action
{
    /**
     * @var DefaultConfigProvider
     */
    protected $_checkoutConfigProvider;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * Success constructor.
     *
     * @param Context $context
     * @param DefaultConfigProvider $checkoutConfigProvider
     * @param Session $checkoutSession
     * @param Order $orderHelper
     */
    public function __construct(
        Context $context,
        DefaultConfigProvider $checkoutConfigProvider,
        Session $checkoutSession,
        Order $orderHelper
    ) {
        $this->_checkoutConfigProvider = $checkoutConfigProvider;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderHelper = $orderHelper;
        
        parent::__construct($context);
    }

    /**
     * Process redirect to tabby
     *
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $redirectUrl = $this->_checkoutConfigProvider->getDefaultSuccessPageUrl();

        if ($incrementId = $this->_checkoutSession->getLastRealOrderId()) {
            $redirectUrl = $this->_orderHelper->getOrderRedirectUrl($incrementId);
        }

        return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
    }
}
