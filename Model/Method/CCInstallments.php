<?php

namespace Tabby\Checkout\Model\Method;

class CCInstallments extends Checkout
{
    const ALLOWED_COUNTRIES = 'AE';

    /**
     * @var string
     */
    protected $_code = 'tabby_cc_installments';
    protected $_codeTabby = 'credit_card_installments';

}
