<?php

namespace Gloudemans\Tests\Shoppingcart\Fixtures;

class ProductModel
{
    public string $someValue = 'Some value';

    public function find($id)
    {
        return $this;
    }
}
