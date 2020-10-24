<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Gloudemans\Shoppingcart\CanBeBought;
use Gloudemans\Shoppingcart\Contracts\Buyable;

class BuyableProductTrait implements Buyable
{
    use CanBeBought;

    /**
     * @var int
     */
    private int $id;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var int
     */
    private int $price;

    /**
     * BuyableProduct constructor.
     *
     * @param int $id
     * @param string $name
     * @param int $price
     */
    public function __construct($id = 1, $name = 'Item name', $price = 1000)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
    }
}
