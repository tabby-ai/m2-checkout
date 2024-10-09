<?php

namespace Tabby\Checkout\Controller\Result;

class Failure extends Cancel
{
    protected const MESSAGE = 'Sorry, Tabby is unable to approve this purchase. ' .
        'Please use an alternative payment method for your order.';
}
