<?php

namespace Tabby\Checkout\Controller\Result;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Tabby\Checkout\Helper\Order;

class Success extends Action
{
    const MESSAGE = 'Payment with Tabby is cancelled';

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
        return parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        if ($incrementId = $this->_checkoutSession->getLastRealOrderId()) {
            if ($paymentId = $this->getRequest()->getParam('payment_id', false)) {
                $this->_orderHelper->authorizeOrder($incrementId, $paymentId, 'success page');
            }
        }

        //$this->messageManager->addErrorMessage(static::MESSAGE);

        return $this->resultRedirectFactory->create()->setUrl($this->_checkoutConfigProvider->getDefaultSuccessPageUrl());
    }
}
