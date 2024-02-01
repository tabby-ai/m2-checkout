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
    protected const MESSAGE = 'Payment with Tabby is cancelled';

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
     * Main method, logic for payment authorization and redirecting to success page
     *
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        if ($incrementId = $this->_checkoutSession->getLastRealOrderId()) {
            if ($paymentId = $this->getRequest()->getParam('payment_id', false)) {
                $this->_orderHelper->authorizeOrder($incrementId, $paymentId, 'success page');
            }
        }

        return $this->resultRedirectFactory->create()->setUrl(
            $this->_checkoutConfigProvider->getDefaultSuccessPageUrl()
        );
    }
}
