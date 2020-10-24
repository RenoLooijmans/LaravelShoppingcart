<?php

namespace Gloudemans\Tests\Shoppingcart;

use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\ShoppingcartServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;

class CartItemTest extends TestCase
{
    /**
     * Set the package service provider.
     *
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $cartItem = new CartItem(1, 'Some item', 1000, ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        self::assertEquals([
            'id'      => 1,
            'name'    => 'Some item',
            'price'   => 1000,
            'rowId'   => '07d5da5550494c62daf9993cf954303f',
            'qty'     => 2,
            'options' => [
                'size'  => 'XL',
                'color' => 'red',
            ],
            'tax'      => 0,
            'subtotal' => 2000,
            'discountRate' => 0,
            'discountFixed' => 0
        ], $cartItem->toArray());
    }

    /** @test
     * @throws \JsonException
     */
    public function it_can_be_cast_to_json()
    {
        $cartItem = new CartItem(1, 'Some item', 1000, ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        self::assertJson($cartItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"Some item","qty":2,"price":1000,"options":{"size":"XL","color":"red"},"discountRate":0,"discountFixed":0,"tax":0,"subtotal":2000}';

        self::assertEquals($json, $cartItem->toJson());
    }

    /** @test */
    public function it_formats_price_total_correctly()
    {
        $cartItem = new CartItem(1, 'Some item', 1000, ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        self::assertSame('20,00', $cartItem->priceTotalFormat());
    }
}
