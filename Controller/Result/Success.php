<?php
namespace Tabby\Checkout\Controller\Result;

class Success extends \Magento\Framework\App\Action\Action
{
    const MESSAGE = 'Payment with Tabby is cancelled';

    protected $_checkoutConfigProvider;
    protected $_checkoutSession;
    protected $_orderHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\DefaultConfigProvider $checkoutConfigProvider,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
        $this->_checkoutConfigProvider    = $checkoutConfigProvider;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderHelper     = $orderHelper;
        return parent::__construct($context);
    }

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

