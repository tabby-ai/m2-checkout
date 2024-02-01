<?php

namespace Tabby\Checkout\Model\Method;

/**
 * Pay in installments payment
 */
class Installments extends Checkout
{
    /**
     * @var string
     */
    protected $_code = 'tabby_installments';
    /**
     * @var string
     */
    protected $_codeTabby = 'installments';
}
