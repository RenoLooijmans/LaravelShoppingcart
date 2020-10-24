<?php

namespace Gloudemans\Shoppingcart\Calculation;

use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Contracts\Calculator;

class DefaultCalculator implements Calculator
{
    /**
     * @param string $attribute
     * @param CartItem $cartItem
     * @return float|int|mixed
     */
    public static function getAttribute(string $attribute, CartItem $cartItem)
    {
        switch ($attribute) {
            case 'discount':
                return round($cartItem->price * ($cartItem->getDiscountRate() / 100));
            case 'discountFixed':
                return min(round($cartItem->price), $cartItem->getDiscountFixed());
            case 'tax':
                return round($cartItem->priceTarget * ($cartItem->taxRate / 100), 0);
            case 'priceTax':
                return round($cartItem->priceTarget + $cartItem->tax, 0);
            case 'discountTotal':
                return min($cartItem->price, round($cartItem->discountRate * $cartItem->qty + $cartItem->discountFixed, 0));
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
                return 0;
        }
    }
}
