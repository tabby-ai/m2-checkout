<?php

namespace Tabby\Checkout\Controller\Result;

class Failure extends Cancel
{
    protected const MESSAGE = 'Payment with Tabby is failed';
}
