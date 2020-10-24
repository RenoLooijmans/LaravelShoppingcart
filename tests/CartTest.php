<?php

namespace Gloudemans\Tests\Shoppingcart;

use Gloudemans\Shoppingcart\Cart;
use Gloudemans\Shoppingcart\CartItem;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;
use Gloudemans\Shoppingcart\Exceptions\UnknownModelException;
use Gloudemans\Shoppingcart\ShoppingcartServiceProvider;
use Gloudemans\Tests\Shoppingcart\Fixtures\BuyableProduct;
use Gloudemans\Tests\Shoppingcart\Fixtures\BuyableProductTrait;
use Gloudemans\Tests\Shoppingcart\Fixtures\ProductModel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

class CartTest extends TestCase
{
    use CartAssertions;

    /**
     * Set the package service provider.
     *
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders(Application $app)
    {
        return [ShoppingcartServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('session.driver', 'array');
    }

    /** @test */
    public function it_has_a_default_instance()
    {
        $cart = $this->getCart();

        self::assertEquals(Cart::DEFAULT_INSTANCE, $cart->currentInstance());
    }

    /** @test */
    public function it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item'));

        $testInstance = 99;

        $cart->instance($testInstance)->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
        $this->assertItemsInCart(1, $cart->instance($testInstance));
    }

    /** @test */
    public function it_can_add_an_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        self::assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_the_cartitem_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct());

        self::assertInstanceOf(CartItem::class, $cartItem);
        self::assertEquals('027c91341fd5cf4d2579b49c4b6a90da', $cartItem->rowId);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        self::assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_an_array_of_cartitems_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        self::assertIsArray($cartItems);
        self::assertCount(2, $cartItems);
        self::assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 1000);

        self::assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(['id' => 1, 'name' => 'Test item', 'qty' => 1, 'price' => 1000]);

        self::assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([
            ['id' => 1, 'name' => 'Test item 1', 'qty' => 1, 'price' => 1000],
            ['id' => 2, 'name' => 'Test item 2', 'qty' => 1, 'price' => 1000],
        ]);

        self::assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_with_options()
    {
        Event::fake();

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $cart->add(new BuyableProduct(),1, $options);

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        self::assertInstanceOf(CartItem::class, $cartItem);
        self::assertEquals('XL', $cartItem->options->size);
        self::assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    /**
     * @test
     */
    public function it_will_validate_the_identifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid identifier.');

        $cart = $this->getCart();

        $cart->add(null, 'Some title', 1, 1000);
    }

    /**
     * @test
     */
    public function it_will_validate_the_name()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid name.');

        $cart = $this->getCart();

        $cart->add(1, null, 1, 1000);
    }

    /**
     * @test
     */
    public function it_will_validate_the_quantity()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid quantity.');

        $cart = $this->getCart();

        $cart->add(1, 'Some title', 'invalid', 1000);
    }

    /**
     * @test
     */
    public function it_will_validate_the_price()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid price.');

        $cart = $this->getCart();

        $cart->add(1, 'Some title', 1, 'invalid');
    }

    /** @test */
    public function it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct();

        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct();

        $cart->add($item);
        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        self::assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        self::assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    /**
     * @test
     */
    public function it_will_throw_an_exception_if_a_rowid_was_not_found()
    {
        $this->expectException(InvalidRowIDException::class);

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('none-existing-rowid', new BuyableProduct(1, 'Different description'));
    }

    /** @test */
    public function it_will_regenerate_the_rowid_if_the_options_changed()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(), 1, ['color' => 'red']);

        $cart->update('ea65e0bdcd1967c4b3149e9e780177c0', ['options' => ['color' => 'blue']]);

        $this->assertItemsInCart(1, $cart);
        self::assertEquals('7e70a1e9aaadd18c72921a07aae5d011', $cart->content()->first()->rowId);
        self::assertEquals('blue', $cart->get('7e70a1e9aaadd18c72921a07aae5d011')->options->color);
    }

    /** @test */
    public function it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(), 1, ['color' => 'blue']);

        $cart->update('7e70a1e9aaadd18c72921a07aae5d011', ['options' => ['color' => 'red']]);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_will_keep_items_sequence_if_the_options_changed()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(), 1, ['color' => 'green']);
        $cart->add(new BuyableProduct(), 1, ['color' => 'blue']);

        $cart->update($cart->content()->values()[1]->rowId, ['options' => ['color' => 'yellow']]);

        $this->assertRowsInCart(3, $cart);
        self::assertEquals('yellow', $cart->content()->values()[1]->options->color);
    }

    /** @test */
    public function it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->remove('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_can_get_an_item_from_the_cart_by_its_rowid()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertInstanceOf(CartItem::class, $cartItem);
    }

    /** @test */
    public function it_can_get_the_content_of_the_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        self::assertInstanceOf(Collection::class, $content);
        self::assertCount(2, $content);
    }

    /** @test */
    public function it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $cart = $this->getCart();

        $content = $cart->content();

        self::assertInstanceOf(Collection::class, $content);
        self::assertCount(0, $content);
    }

    /** @test */
    public function it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        self::assertInstanceOf(Collection::class, $content);
        self::assertEquals([
            '027c91341fd5cf4d2579b49c4b6a90da' => [
                'rowId'    => '027c91341fd5cf4d2579b49c4b6a90da',
                'id'       => 1,
                'name'     => 'Item name',
                'qty'      => 1,
                'price'    => 1000,
                'tax'      => 210,
                'subtotal' => 790,
                'options'  => [],
                'discountRate' => 0,
                'discountFixed' => 0
            ],
            '370d08585360f5c568b18d1f2e4ca1df' => [
                'rowId'    => '370d08585360f5c568b18d1f2e4ca1df',
                'id'       => 2,
                'name'     => 'Item name',
                'qty'      => 1,
                'price'    => 1000,
                'tax'      => 210,
                'subtotal' => 790,
                'options'  => [],
                'discountRate' => 0,
                'discountFixed' => 0
            ],
        ], $content->toArray());
    }

    /** @test */
    public function it_can_destroy_a_cart()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_get_the_total_price_of_the_cart_content()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 1000));
        $cart->add(new BuyableProduct(2, 'Second item', 2500), 2);

        $this->assertItemsInCart(3, $cart);
        self::assertEquals(6000, $cart->total());
    }

    /** @test */
    public function it_can_return_a_formatted_total()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 100000));
        $cart->add(new BuyableProduct(2, 'Second item', 250000), 2);

        $this->assertItemsInCart(3, $cart);
        self::assertEquals('6.000,00', $cart->totalFormat(2, ',', '.'));
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Another item'));

        $cartItem = $cart->search(function ($cartItem) {
            return $cartItem->name === 'Some item';
        });

        self::assertInstanceOf(Collection::class, $cartItem);
        self::assertCount(1, $cartItem);
        self::assertInstanceOf(CartItem::class, $cartItem->first());
        self::assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_can_search_the_cart_for_multiple_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Some item'));
        $cart->add(new BuyableProduct(3, 'Another item'));

        $cartItem = $cart->search(function ($cartItem) {
            return $cartItem->name === 'Some item';
        });

        self::assertInstanceOf(Collection::class, $cartItem);
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(2, 'Another item'), 1, ['color' => 'blue']);

        $cartItem = $cart->search(function ($cartItem) {
            return $cartItem->options->color === 'red';
        });

        self::assertInstanceOf(Collection::class, $cartItem);
        self::assertCount(1, $cartItem);
        self::assertInstanceOf(CartItem::class, $cartItem->first());
        self::assertEquals(1, $cartItem->first()->id);
    }

    /** @test */
    public function it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(BuyableProduct::class, $cartItem->modelFQCN);
    }

    /** @test */
    public function it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 1000);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(ProductModel::class, $cartItem->modelFQCN);
    }

    /**
     * @test
     */
    public function it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $this->expectException(UnknownModelException::class);
        $this->expectExceptionMessage('The supplied model SomeModel does not exist.');

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 1000);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel');
    }

    /** @test */
    public function it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 1000);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertInstanceOf(ProductModel::class, $cartItem->model);
        self::assertEquals('Some value', $cartItem->model->someValue);
    }

    /** @test */
    public function it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 999), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(2368, $cartItem->subtotal);
    }

    /** @test */
    public function it_can_return_a_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 50000), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals('1.185,00', $cartItem->subtotalFormat(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(210, $cartItem->tax);
    }

    /** @test */
    public function it_can_calculate_tax_based_on_the_specified_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000), 1);

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 9);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(90, $cartItem->tax);
    }

    /** @test */
    public function it_can_return_the_calculated_tax_formatted()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000000), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals('2.100,00', $cartItem->taxFormat(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000), 2);

        self::assertEquals(1050, $cart->tax);
    }

    /** @test */
    public function it_can_return_formatted_total_tax()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 100000), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 200000), 2);

        self::assertEquals('1.050,00', $cart->taxFormat(2, ',', '.'));
    }

    /** @test */
    public function it_can_access_tax_as_percentage()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000), 1);

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(19, $cartItem->taxRate);
    }

    /** @test */
    public function it_can_return_the_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000), 2);

        self::assertEquals(3950, $cart->subtotal());
    }

    /** @test */
    public function it_can_return_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 100000), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 200000), 2);

        self::assertEquals('3.950,00', $cart->subtotalFormat(2, ',', '.'));
    }

    /** @test */
    public function it_can_calculate_all_values()
    {
        $cart = $this->getCartDiscount(50);

        $cart->add(new BuyableProduct(1, 'First item', 1000), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        self::assertEquals(1000, $cartItem->price);
        self::assertEquals(500, $cartItem->discountPerc);
        self::assertEquals(1000, $cartItem->discountTotal);
        self::assertEquals(500, $cartItem->priceTarget);
        self::assertEquals(810, $cartItem->subtotal);
        self::assertEquals(95, $cartItem->tax);
        self::assertEquals(190, $cartItem->taxTotal);
        self::assertEquals(405, $cartItem->priceSubtotal);
        self::assertEquals(1000, $cartItem->total);
    }

    /** @test */
    public function it_can_calculate_all_values_after_updating_from_array()
    {
        $cart = $this->getCartDiscount(50);
        $cart->add(new BuyableProduct(1, 'First item', 1000), 1);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['qty' => 2]);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        self::assertEquals(1000, $cartItem->price);
        self::assertEquals(500, $cartItem->discountPerc);
        self::assertEquals(1000, $cartItem->discountTotal);
        self::assertEquals(500, $cartItem->priceTarget);
        self::assertEquals(810, $cartItem->subtotal);
        self::assertEquals(95, $cartItem->tax);
        self::assertEquals(190, $cartItem->taxTotal);
        self::assertEquals(405, $cartItem->priceSubtotal);
        self::assertEquals(1000, $cartItem->total);
    }

    /** @test */
    public function it_can_calculate_all_values_after_updating_from_buyable()
    {
        $cart = $this->getCartDiscount(50);
        $cart->add(new BuyableProduct(1, 'First item', 500), 2);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'First item', 1000));

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        self::assertEquals(1000, $cartItem->price);
        self::assertEquals(500, $cartItem->discountPerc);
        self::assertEquals(1000, $cartItem->discountTotal);
        self::assertEquals(500, $cartItem->priceTarget);
        self::assertEquals(810, $cartItem->subtotal);
        self::assertEquals(95, $cartItem->tax);
        self::assertEquals(190, $cartItem->taxTotal);
        self::assertEquals(405, $cartItem->priceSubtotal);
        self::assertEquals(1000, $cartItem->total);
    }

    /** @test */
    public function can_change_tax_globally()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Item', 1000), 2);

        $cart->setGlobalTax(0);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(2000, $cartItem->total);
    }

    /** @test */
    public function can_change_discount_globally()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Item', 1000), 2);

        $cart->setGlobalTax(0);
        $cart->setGlobalDiscountRate(50);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(1000, $cartItem->total);
    }

    /** @test */
    public function cart_has_no_rounding_errors()
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Item', 999), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        self::assertEquals(210, $cartItem->tax);
    }

    /** @test */
    public function cart_can_calculate_all_values()
    {
        $cart = $this->getCartDiscount(50);
        $cart->add(new BuyableProduct(1, 'First item', 1000), 1);
        $cart->get('027c91341fd5cf4d2579b49c4b6a90da');
        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        self::assertEquals('10,00', $cart->initialFormat());
        self::assertEquals(1000, $cart->initial());
        self::assertEquals('5,00', $cart->discountFormat());
        self::assertEquals(500, $cart->discount());
        self::assertEquals('4,05', $cart->subtotalFormat());
        self::assertEquals(405, $cart->subtotal());
        self::assertEquals('0,95', $cart->taxFormat());
        self::assertEquals(95, $cart->tax());
        self::assertEquals('5,00', $cart->totalFormat());
        self::assertEquals(500, $cart->total());
    }

    /** @test */
    public function can_access_cart_item_propertys()
    {
        $cart = $this->getCartDiscount(50);
        $cart->add(new BuyableProduct(1, 'First item', 1000), 1);
        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');
        self::assertEquals(50, $cartItem->discountRate);
    }

    /** @test */
    public function cant_access_non_existant_propertys()
    {
        $cart = $this->getCartDiscount(50);
        $cart->add(new BuyableProduct(1, 'First item', 1000), 1);
        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');
        self::assertEquals(null, $cartItem->doesNotExist);
        self::assertEquals(null, $cart->doesNotExist);
    }

    /** @test */
    public function can_set_cart_item_discount()
    {
        $cart = $this->getCart();
        $cart->add(new BuyableProduct(1, 'First item', 1000), 1);
        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');
        $cart->setDiscountRate('027c91341fd5cf4d2579b49c4b6a90da', 50);
        self::assertEquals(50, $cartItem->discountRate);
    }

    /** @test */
    public function cart_can_create_items_from_models_using_the_canbebought_trait()
    {
        $cart = $this->getCartDiscount(50);

        $cart->add(new BuyableProductTrait(1, 'First item', 1000), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        self::assertEquals(1000, $cartItem->price);
        self::assertEquals(500, $cartItem->discountPerc);
        self::assertEquals(1000, $cartItem->discountTotal);
        self::assertEquals(500, $cartItem->priceTarget);
        self::assertEquals(810, $cartItem->subtotal);
        self::assertEquals(95, $cartItem->tax);
        self::assertEquals(190, $cartItem->taxTotal);
        self::assertEquals(405, $cartItem->priceSubtotal);
        self::assertEquals(1000, $cartItem->total);
    }

    /** @test */
    public function it_use_correctly_rounded_values_for_totals_and_cart_summary()
    {
        $cart = $this->getCartDiscount(0);

        $cart->add(new BuyableProduct(1, 'First item', 18929), 1000);
        $cart->add(new BuyableProduct(2, 'Second item', 441632), 5);
        $cart->add(new BuyableProduct(3, 'Third item', 37995), 25);

        $cart->setGlobalTax(21);

        // check total
        self::assertEquals(22087035, $cart->total());

        // check that the sum of cart subvalues matches the total (in order to avoid cart summary to looks wrong)
        self::assertEquals($cart->total(), $cart->subtotal() + $cart->tax());
    }

    /**
     * Get an instance of the cart.
     *
     * @return Cart
     */
    private function getCart()
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Get an instance of the cart with discount.
     *
     * @param int $discount
     *
     * @return Cart
     */
    private function getCartDiscount($discount = 50)
    {
        $cart = $this->getCart();
        $cart->setGlobalDiscountRate($discount);

        return $cart;
    }
}
