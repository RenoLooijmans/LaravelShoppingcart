<?php

namespace Gloudemans\Shoppingcart\Contracts;

use Gloudemans\Shoppingcart\CartItem;

interface Calculator
{
    /**
     * @param string $attribute
     * @param CartItem $cartItem
     * @return mixed
     */
    public static function getAttribute(string $attribute, CartItem $cartItem);
}
