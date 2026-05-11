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
     * @param array|null $countries
     */
    public function __construct(
        Collection $countryCollection,
        ?array $countries = null
    ) {
        parent::__construct($countryCollection);

        if (!empty($countries)) {
            $this->_countryCollection->addCountryCodeFilter($countries, ['iso2']);
        }
    }
}
