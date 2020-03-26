<?php

namespace Tabby\Checkout\Model\Config\Source;

class Services implements \Magento\Framework\Option\ArrayInterface {

    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @param string|array $foregroundCountries
     * @return array
     */
    public function toOptionArray()
    {
		$options = [];

		foreach (\Tabby\Checkout\Gateway\Config\Config::ALLOWED_SERVICES as $key => $title) {
			$options[] = [
				'value'	=> $key,
				'label'	=> __($title)
			];
		}

        return $options;
    }

}
