<?php

namespace Tabby\Checkout\Controller\Result;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Tabby\Checkout\Helper\Order;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;

class Cancel extends Action
{
    protected const MESSAGE = 'Payment with Tabby is cancelled';

    /**
     * @var UrlInterface
     */
    protected $_urlInterface;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var Order
     */
    protected $_orderHelper;

    /**
     * Cancel constructor.
     *
     * @param Context $context
     * @param UrlInterface $urlInterface
     * @param Session $checkoutSession
     * @param Order $orderHelper
     */
    public function __construct(
        Context $context,
        UrlInterface $urlInterface,
        Session $checkoutSession,
        Order $orderHelper
    ) {
        $this->_urlInterface = $urlInterface;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderHelper = $orderHelper;
        
        parent::__construct($context);
    }

    /**
     * Main method, order cancel logic
     *
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        if ($incrementId = $this->_checkoutSession->getLastRealOrderId()) {
            $this->_orderHelper->cancelCurrentOrderByIncrementId($incrementId);
        }

        return $this->resultRedirectFactory->create()->setUrl($this->_urlInterface->getUrl('checkout'));
    }
}
