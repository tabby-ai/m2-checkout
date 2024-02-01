<?php

namespace Tabby\Checkout\Model\Config\Source;

use Magento\Directory\Model\ResourceModel\Country\Collection;

/**
 * Source model for Tabby allowed countries list
 */
class Country extends \Magento\Directory\Model\Config\Source\Country
{

    /**
     * Class constructor
     *
     * @param Collection $countryCollection
     * @param string|null $countryCodes
     */
    public function __construct(
        Collection $countryCollection,
        string $countryCodes = null
    ) {
        parent::__construct($countryCollection);

        if (!empty($countryCodes)) {
            $this->_countryCollection->addCountryCodeFilter(explode(',', $countryCodes), ['iso2']);
        }
    }
}
