<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

use Gloudemans\Shoppingcart\Contracts\InstanceIdentifier;

class Identifiable implements InstanceIdentifier
{
    /**
     * @var int
     */
    private int $identifier;

    /**
     * @var int
     */
    private int $discountRate;

    /**
     * @var int
     */
    private int $discountFixed;

    /**
     * BuyableProduct constructor.
     *
     * @param int $identifier
     * @param int $discountRate
     * @param int $discountFixed
     */
    public function __construct($identifier = 100, $discountRate = 0, $discountFixed = 0)
    {
        $this->identifier = $identifier;
        $this->discountRate = $discountRate;
        $this->discountFixed = $discountFixed;
    }

    /**
     * Get the unique identifier to load the Cart from.
     *
     * @param array|null $options
     * @return int|string
     */
    public function getInstanceIdentifier($options = null)
    {
        return $this->identifier;
    }

    /**
     * Get the unique identifier to load the Cart from.
     *
     * @param array|null $options
     * @return int|string
     */
    public function getInstanceGlobalDiscountRate($options = null)
    {
        return $this->discountRate;
    }

    /**
     * @param array|null $options
     * @return int
     */
    public function getInstanceGlobalDiscountFixed($options = null)
    {
        return $this->discountFixed;
    }
}
