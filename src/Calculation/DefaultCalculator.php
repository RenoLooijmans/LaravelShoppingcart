<?php

namespace Gloudemans\Shoppingcart\Calculation;

use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\Calculator;

class DefaultCalculator implements Calculator
{
    public static function getAttribute(string $attribute, CartItem $cartItem)
    {
        switch ($attribute) {
            case 'discount':
                return $cartItem->price * ($cartItem->getDiscountRate() / 100);
            case 'tax':
                return round($cartItem->priceTarget * ($cartItem->taxRate / 100), 0);
            case 'priceTax':
                return round($cartItem->priceTarget + $cartItem->tax, 0);
            case 'discountTotal':
                return round($cartItem->discount * $cartItem->qty, 0);
            case 'priceTotal':
                return round($cartItem->price * $cartItem->qty, 0);
            case 'subtotal':
                return max(round($cartItem->priceTotal - $cartItem->discountTotal, 0), 0);
            case 'priceTarget':
                return round(($cartItem->priceTotal - $cartItem->discountTotal) / $cartItem->qty, 0);
            case 'taxTotal':
                return round($cartItem->subtotal * ($cartItem->taxRate / 100), 0);
            case 'total':
                return round($cartItem->subtotal + $cartItem->taxTotal, 0);
            default:
                return;
        }
    }
}
