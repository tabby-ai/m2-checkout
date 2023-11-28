<?php

namespace Tabby\Checkout\Model\Config\Source;

class PluginMode extends ConstantArray
{

    const VALUES = [
        0 => 'Payment gateway',
        1 => 'Promotions only'
    ];

}
