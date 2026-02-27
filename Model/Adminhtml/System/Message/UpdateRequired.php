<?php
namespace Tabby\Checkout\Model\Adminhtml\System\Message;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\FlagManager;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Notification\MessageInterface;

/**
 * Class UpdateRequired
 *
 * class for admin notification about update required for Tabby module
 */
class UpdateRequired implements MessageInterface
{
    /**
     * Message identity
     */
    private const MESSAGE_IDENTITY = 'tabby_checkout_update_required_system_message';

    /**
     * Flag for available version
     */
    private const FLAG_VERSION = 'tabby_checkout_available_version';

    /**
     * Flag for time available version last checked
     */
    private const FLAG_CHECKED = 'tabby_checkout_available_version_checked';

    /**
     * @var FlagManager
     */
    protected $flagManager;

    /**
     * @var FileDriver
     */
    protected $fileDriver;

    /**
     * @var ModuleList
     */
    protected $moduleList;

    /**
     * class constructor
     *
     * @param FlagManager $flagManager
     * @param FileDriver $fileDriver
     * @param ModuleList $moduleList
     */
    public function __construct(
        FlagManager $flagManager,
        FileDriver $fileDriver,
        ModuleList $moduleList
    ) {
        $this->flagManager = $flagManager;
        $this->fileDriver = $fileDriver;
        $this->moduleList = $moduleList;
    }
    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        if (version_compare($this->getInstalledVersion(), $this->getAvailableVersion(), '<')) {
            return true;
        }
        return false;
    }

    /**
     * Returns setup version of Tabby_Checkout module
     *
     * @return string
     */
    private function getInstalledVersion()
    {
        $moduleInfo = $this->moduleList->getOne('Tabby_Checkout');

        return $moduleInfo["setup_version"];
    }

    /**
     * Returns available version of Tabby_Checkout module
     *
     * @return string
     */
    private function getAvailableVersion()
    {
        $available = $this->flagManager->getFlagData(self::FLAG_VERSION);

        if ($this->isRecheckRequired() || empty($available)) {
            $available = $this->updateAvailableVersionFlag();
        }

        return $available;
    }

    /**
     * Flag expired or time passed
     *
     * @return bool
     */
    private function isRecheckRequired()
    {
        return time() - (int)$this->flagManager->getFlagData(self::FLAG_CHECKED) > 24 * 60 * 60;
    }

    /**
     * Updates available version flag in DB
     *
     * @return string
     */
    private function updateAvailableVersionFlag()
    {
        $available = '1.0.0';
        try {
            $obj = json_decode(
                $this->fileDriver->fileGetContents("https://packagist.org/packages/tabby/m2-checkout/stats.json")
            );
            uasort($obj->versions, 'version_compare');
            $available = array_pop($obj->versions);
            // save result
            $this->flagManager->saveFlag(self::FLAG_VERSION, $available);
            $this->flagManager->saveFlag(self::FLAG_CHECKED, time());
        } catch (\Exception $e) {
            // return default version if failed
            $available = '1.0.0';
        }
        return $available;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return sprintf(
            __('New version (%s) of Tabby module available. Your current version is \'%s\'.'),
            $this->getAvailableVersion(),
            $this->getInstalledVersion()
        );
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
