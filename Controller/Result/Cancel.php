<?php
namespace Tabby\Checkout\Controller\Result;

class Cancel extends \Magento\Framework\App\Action\Action
{
    const MESSAGE = 'Payment with Tabby is cancelled';

    protected $_urlInterface;
    protected $_checkoutSession;
    protected $_orderHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
        $this->_urlInterface    = $urlInterface;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderHelper     = $orderHelper;
        return parent::__construct($context);
    }

    public function execute()
    {
        if ($incrementId = $this->_checkoutSession->getLastRealOrderId()) {
            $this->_orderHelper->cancelCurrentOrderByIncrementId($incrementId);
        }
        
        //$this->messageManager->addErrorMessage(static::MESSAGE);

        return $this->resultRedirectFactory->create()->setUrl($this->_urlInterface->getUrl('checkout'));
    }
}

