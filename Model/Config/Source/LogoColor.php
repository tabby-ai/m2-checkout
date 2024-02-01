<?php

namespace Tabby\Checkout\Model\Config\Source;

/**
 * Source model for logo color
 */
class LogoColor extends ConstantArray
{
    protected const VALUES = [
        'green' => 'Green',
        'black' => 'Black'
    ];
}
