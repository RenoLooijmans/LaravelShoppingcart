<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Gloudemans\Shoppingcart\Contracts\Buyable;

class BuyableProduct implements Buyable
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $price;

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

    /**
     * Get the identifier of the Buyable item.
     *
     * @param array|null $options
     * @return int
     */
    public function getBuyableIdentifier($options = null)
    {
        return $this->id;
    }

    /**
     * Get the description or title of the Buyable item.
     *
     * @param array|null $options
     * @return string
     */
    public function getBuyableDescription($options = null)
    {
        return $this->name;
    }

    /**
     * Get the price of the Buyable item.
     *
     * @param array|null $options
     * @return int
     */
    public function getBuyablePrice($options = null)
    {
        return $this->price;
    }
}
