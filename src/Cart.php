<?php

namespace Gloudemans\Shoppingcart;

use Carbon\Carbon;
use Closure;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\InstanceIdentifier;
use Gloudemans\Shoppingcart\Exceptions\InvalidRowIDException;
use Gloudemans\Shoppingcart\Exceptions\UnknownModelException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;

class Cart
{
    public const DEFAULT_INSTANCE = 0;

    public const DEFAULT_TAX_RATE = 21;

    public const DEFAULT_FORMAT_DECIMALS = 2;
    public const DEFAULT_FORMAT_DECIMAL_SEP = ',';
    public const DEFAULT_FORMAT_THOUSAND_SEP = '.';

    /**
     * Instance of the session manager.
     *
     * @var SessionManager
     */
    private SessionManager $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var Dispatcher
     */
    private Dispatcher $events;

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private string $instance;

    /**
     * Defines the fixed discount.
     *
     * @var int
     */
    private int $discountFixed;

    /**
     * Defines the discount percentage.
     *
     * @var int
     */
    private int $discountRate;

    /**
     * Defines the tax rate.
     *
     * @var int
     */
    private int $taxRate;

    /**
     * Cart constructor.
     *
     * @param SessionManager $session
     * @param Dispatcher $events
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;
        $this->taxRate = self::DEFAULT_TAX_RATE;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param int|mixed|null $instance
     *
     * @return Cart
     */
    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        if ($instance instanceof InstanceIdentifier) {
            $this->discountRate = $instance->getInstanceGlobalDiscountRate();
            $this->discountFixed = $instance->getInstanceGlobalDiscountFixed();
            $instance = $instance->getInstanceIdentifier();
        }

        $this->instance = 'cart.' . $instance;

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance()
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     *
     * @param mixed $id
     * @param mixed $name
     * @param int|null $qty
     * @param int|null $price
     * @param array $options
     *
     * @return array|CartItem|CartItem[]
     */
    public function add($id, $name = null, $qty = null, $price = null, array $options = [])
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $cartItem = $this->createCartItem($id, $name, $qty, $price, $options);

        return $this->addCartItem($cartItem);
    }

    /**
     * Add an item to the cart.
     *
     * @param CartItem $item
     * @param bool $keepDiscount
     * @param bool $keepTax
     *
     * @param bool $dispatchEvent
     * @return CartItem
     */
    public function addCartItem(CartItem $item, $keepDiscount = false, $keepTax = false, $dispatchEvent = true)
    {
        if (!$keepDiscount) {
            $item->setDiscountRate($this->discountRate);
            $item->setDiscountFixed($this->discountFixed);
        }

        if (!$keepTax) {
            $item->setTaxRate($this->taxRate);
        }

        $content = $this->getContent();

        if ($content->has($item->rowId)) {
            $item->qty += $content->get($item->rowId)->qty;
        }

        $content->put($item->rowId, $item);

        if ($dispatchEvent) {
            $this->events->dispatch('cart.adding', $item);
        }

        $this->session->put($this->instance, $content);

        if ($dispatchEvent) {
            $this->events->dispatch('cart.added', $item);
        }

        return $item;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed $qty
     *
     * @return CartItem|null
     */
    public function update(string $rowId, $qty)
    {
        $cartItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $itemOldIndex = $content->keys()->search($rowId);

            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);

            return null;
        }

        if (isset($itemOldIndex)) {
            $content = $content->slice(0, $itemOldIndex)
                ->merge([$cartItem->rowId => $cartItem])
                ->merge($content->slice($itemOldIndex));
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.updating', $cartItem);

        $this->session->put($this->instance, $content);

        $this->events->dispatch('cart.updated', $cartItem);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     *
     * @return void
     */
    public function remove(string $rowId)
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->events->dispatch('cart.removing', $cartItem);

        $this->session->put($this->instance, $content);

        $this->events->dispatch('cart.removed', $cartItem);
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     *
     * @return CartItem
     */
    public function get(string $rowId)
    {
        $content = $this->getContent();

        if (!$content->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");
        }

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return Collection
     */
    public function content()
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }

        return $this->session->get($this->instance);
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        return $this->getContent()->sum('qty');
    }

    /**
     * Get the number of items instances in the cart.
     *
     * @return int|float
     */
    public function countInstances()
    {
        return $this->getContent()->count();
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @return int
     */
    public function total()
    {
        return $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->total;
        }, 0);
    }

    /**
     * Get the total price of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return string
     */
    public function totalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->total() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @return int
     */
    public function tax()
    {
        return $this->getContent()->reduce(function ($tax, CartItem $cartItem) {
            return $tax + $cartItem->taxTotal;
        }, 0);
    }

    /**
     * Get the total tax of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function taxFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->tax() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @return int
     */
    public function subtotal()
    {
        return $this->getContent()->reduce(function ($subTotal, CartItem $cartItem) {
            return $subTotal + $cartItem->subtotal;
        }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function subtotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->subtotal() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the discount of the items in the cart.
     *
     * @return int
     */
    public function discount()
    {
        return $this->getContent()->reduce(function ($discount, CartItem $cartItem) {
            return $discount + $cartItem->discountTotal;
        }, 0);
    }

    /**
     * Get the discount of the items in the cart as formatted string.
     *
     * @param null $decimals
     * @param null $decimalPoint
     * @param null $thousandSeparator
     *
     * @return string
     */
    public function discountFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->discount() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the price of the items in the cart (not rounded).
     *
     * @return int
     */
    public function initial()
    {
        return $this->getContent()->reduce(function ($initial, CartItem $cartItem) {
            return $initial + ($cartItem->qty * $cartItem->price);
        }, 0);
    }

    /**
     * Get the price of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function initialFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->initial() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Get the price of the items in the cart (previously rounded).
     *
     * @return float
     */
    public function priceTotal()
    {
        return $this->getContent()->reduce(function ($initial, CartItem $cartItem) {
            return $initial + $cartItem->priceTotal;
        }, 0);
    }

    /**
     * Get the price of the items in the cart as formatted string.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function priceTotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->priceTotal() / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param Closure $search
     *
     * @return Collection
     */
    public function search(Closure $search)
    {
        return $this->getContent()->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed $model
     *
     * @return void
     */
    public function associate(string $rowId, $model)
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int|float $taxRate
     *
     * @return void
     */
    public function setTax(string $rowId, $taxRate)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the global tax rate for the cart.
     * This will set the tax rate for all items.
     *
     * @param int $taxRate
     */
    public function setGlobalTax(int $taxRate)
    {
        $this->taxRate = $taxRate;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setTaxRate($this->taxRate);
            });
        }
    }

    /**
     * Set the discount rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int $discount
     * @return void
     */
    public function setDiscountRate(string $rowId, int $discountRate)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setDiscountRate($discountRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the fixed discount rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int $discountFixed
     * @return void
     */
    public function setDiscountFixed(string $rowId, int $discountFixed)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setDiscountFixed($discountFixed);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the global discount percentage for the cart.
     * This will set the discount for all cart items.
     *
     * @param int $discount
     *
     * @return void
     */
    public function setGlobalDiscountRate(int $discountRate)
    {
        $this->discountRate = $discountRate;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setDiscountRate($this->discountRate);
            });
        }
    }

    /**
     * Set the global fixed discount for the cart.
     * This will set the discount for all cart items.
     *
     * @param int $discountFixed
     * @return void
     */
    public function setGlobalDiscountFixed(int $discountFixed)
    {
        $this->discountFixed = $discountFixed;

        $content = $this->getContent();
        if ($content && $content->count()) {
            $content->each(function ($item, $key) {
                $item->setDiscountRate($this->discountFixed);
            });
        }
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     *
     * @return int
     */
    public function __get(string $attribute)
    {
        switch ($attribute) {
            case 'total':
                return $this->total();
            case 'tax':
                return $this->tax();
            case 'subtotal':
                return $this->subtotal();
            default:
                return 0;
        }
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     *
     * @return Collection
     */
    protected function getContent()
    {
        if ($this->session->has($this->instance)) {
            return $this->session->get($this->instance);
        }

        return new Collection();
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param mixed $id
     * @param mixed $name
     * @param int $qty
     * @param int $price
     * @param array $options
     *
     * @return CartItem
     */
    private function createCartItem($id, $name, int $qty, int $price, array $options)
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id, $qty ?: []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name, $price, $options);
            $cartItem->setQuantity($qty);
        }

        return $cartItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyables.
     *
     * @param mixed $item
     *
     * @return bool
     */
    private function isMulti($item)
    {
        if (!is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * Get the Formatted number.
     *
     * @param $value
     * @param $decimals
     * @param $decimalPoint
     * @param $thousandSeparator
     *
     * @return string
     */
    private function numberFormat($value, $decimals, $decimalPoint, $thousandSeparator)
    {
        if(is_null($decimals)) {
            $decimals = self::DEFAULT_FORMAT_DECIMALS;
        }

        if(is_null($decimalPoint)) {
            $decimalPoint = self::DEFAULT_FORMAT_DECIMAL_SEP;
        }

        if(is_null($thousandSeparator)) {
            $thousandSeparator = self::DEFAULT_FORMAT_THOUSAND_SEP;
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }
}
