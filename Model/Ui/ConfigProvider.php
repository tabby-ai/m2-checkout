<?php
/**
 * Config provider model
 */
namespace Tabby\Checkout\Model\Ui;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\ConfigInterface;

/**
 * Config Provider for checkout front-end
 */
class ConfigProvider implements ConfigProviderInterface
{

    public const CODE = 'tabby_checkout';

    /**
     * @var ConfigInterface
     */
    protected $moduleConfig;

    /**
     * @var ConfigInterface
     */
    protected $methodConfig;

    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * Constructor
     *
     * @param ConfigInterface         $moduleConfig
     * @param ConfigInterface         $methodConfig
     * @param SessionManagerInterface $session
     * @param Session                 $_checkoutSession
     * @param CollectionFactory       $orderCollectionFactory
     * @param Repository              $assetRepo
     * @param RequestInterface        $request
     * @param StoreManagerInterface   $storeManager
     * @param Resolver                $resolver
     * @param UrlInterface            $urlInterface
     */
    public function __construct(
        ConfigInterface $moduleConfig,
        ConfigInterface $methodConfig,
        SessionManagerInterface $session,
        Session $_checkoutSession,
        CollectionFactory $orderCollectionFactory,
        Repository $assetRepo,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Resolver $resolver,
        UrlInterface $urlInterface
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->methodConfig = $methodConfig;
        $this->session = $session;
        $this->checkoutSession = $_checkoutSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->resolver = $resolver;
        $this->storeManager = $storeManager;
        $this->urlInterface = $urlInterface;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        // bypass config for promotions only mode
        if ($this->moduleConfig->getValue('plugin_mode') == '1') {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'config' => $this->getTabbyConfig(),
                    'defaultRedirectUrl' => $this->urlInterface
                        ->getUrl('tabby/redirect'),
                    'storeGroupCode' => $this->storeManager->getGroup()->getCode(),
                    'lang' => $this->resolver->getLocale(),
                    'methods' => $this->_getMethodsAdditionalInfo(),
                ],
            ],
        ];
    }

    /**
     * Provides additional configuration for payment methods
     *
     * @return array
     */
    private function _getMethodsAdditionalInfo()
    {
        $result = [];
        foreach (\Tabby\Checkout\Gateway\Helper\Data::ALLOWED_SERVICES as $method => $title) {
            $this->methodConfig->setMethodCode($method);
            $description_type = (int)$this->methodConfig->getValue('description_type');
            if ($description_type == 0)
            {
                $description_type = 1;
            }
            $inherit_bg = (bool)$this->methodConfig->getValue('inherit_bg');
            $result[$method] = [
                'description_type'  => $description_type,
                'inherit_bg'        => $inherit_bg,
                'card_direction'    => $description_type == 1 ? 'narrow' : 'wide',
            ];
        }
        return $result;
    }

    /**
     * Provides Tabby Config for frontend
     *
     * @return array
     */
    private function getTabbyConfig()
    {
        $params = ['_secure' => $this->request->isSecure()];

        $config = [
            'apiKey'            => $this->moduleConfig->getValue(\Tabby\Checkout\Gateway\Helper\Data::KEY_PUBLIC_KEY),
            'hideMethods'       => (bool)$this->moduleConfig->getValue('hide_methods'),
            'local_currency'    => (bool)$this->moduleConfig->getValue('local_currency'),
            'checkout_remove_tax' => (bool)$this->moduleConfig->getValue('checkout_remove_tax'),
            'showLogo'          => (bool)$this->moduleConfig->getValue('show_logo'),
            'paymentLogoSrc'    => $this->assetRepo->getUrlWithParams(
                'Tabby_Checkout::images/logo_' . $this->moduleConfig->getValue('logo_color') . '.png',
                $params
            ),
            'paymentInfoSrc'    => $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/info.png', $params),
            'paymentInfoHref'   => $this->assetRepo->getUrlWithParams(
                'Tabby_Checkout::template/payment/info.html',
                $params
            ),
            'useRedirect'       => 1,
        ];

        return $config;
    }
}
