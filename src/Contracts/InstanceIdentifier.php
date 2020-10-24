<?php

namespace Gloudemans\Shoppingcart\Contracts;

interface InstanceIdentifier
{
    /**
     * Get the unique identifier to load the Cart from.
     *
     * @param array|null $options
     * @return int
     */
    public function getInstanceIdentifier($options = null);

    /**
     * Get the unique identifier to load the Cart from.
     *
     * @param array|null $options
     * @return int
     */
    public function getInstanceGlobalDiscount($options = null);
}
