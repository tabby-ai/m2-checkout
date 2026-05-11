<?php
namespace Tabby\Checkout\Gateway\Helper;

use Magento\Framework\Module\ModuleList;

class Meta
{
    /**
     * @var ModuleList
     */
    private $moduleList;

    /**
     * Tabby config constructor
     *
     * @param ModuleList $moduleList
     */
    public function __construct(
        ModuleList $moduleList
    ) {
        $this->moduleList = $moduleList;
    }

    /**
     * Getter for payment meta fields
     *
     * @return mixed|null
     */
    public function getPaymentObjectMetaFields()
    {
        $moduleInfo = $this->moduleList->getOne('Tabby_Checkout');
        return [
            "tabby_plugin_platform" => 'magento',
            "tabby_plugin_version"  => $moduleInfo["setup_version"],
        ];
    }

}
