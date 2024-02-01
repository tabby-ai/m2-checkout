<?php

namespace Tabby\Checkout\Model\Method;

/**
 * Credit Card Installments class
 */
class CCInstallments extends Checkout
{
    public const ALLOWED_COUNTRIES = 'AE';

    /**
     * @var string
     */
    protected $_code = 'tabby_cc_installments';

    /**
     * @var string
     */
    protected $_codeTabby = 'credit_card_installments';
}
