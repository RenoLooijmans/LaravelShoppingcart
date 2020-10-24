<?php

namespace Gloudemans\Shoppingcart;

use Gloudemans\Shoppingcart\Calculation\DefaultCalculator;
use Gloudemans\Shoppingcart\Contracts\Buyable;
use Gloudemans\Shoppingcart\Contracts\Calculator;
use Gloudemans\Shoppingcart\Exceptions\InvalidCalculatorException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use ReflectionClass;

class CartItem implements Arrayable, Jsonable
{
    public const DEFAULT_FORMAT_DECIMALS = 2;
    public const DEFAULT_FORMAT_DECIMAL_SEP = ',';
    public const DEFAULT_FORMAT_THOUSAND_SEP = '.';

    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public string $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int;
     */
    public int $id;

    /**
     * The quantity for this cart item.
     *
     * @var int
     */
    public int $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public string $name;

    /**
     * The price without TAX of the cart item.
     *
     * @var int
     */
    public int $price;

    /**
     * The options for this cart item.
     *
     * @var array|CartItem
     */
    public $options;

    /**
     * The tax rate for the cart item.
     *
     * @var int;
     */
    public int $taxRate;

    /**
     * The FQN of the associated model.
     *
     * @var string|null
     */
    private ?string $associatedModel = null;

    /**
     * The discount rate for the cart item.
     *
     * @var int
     */
    private int $discountRate;

    /**
     * CartItem constructor.
     *
     * @param int $id
     * @param string $name
     * @param int $price
     * @param array $options
     */
    public function __construct(int $id, string $name, int $price, array $options = [])
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }

        if (strlen($price) < 0) {
            throw new \InvalidArgumentException('Please supply a valid price.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->options = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Returns the formatted price without TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function priceFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->price / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price with discount applied.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function priceTargetFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->priceTarget / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted price with TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function priceTaxFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->priceTax / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted subtotal.
     * Subtotal is price for whole CartItem without TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function subtotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->subtotal / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total.
     * Total is price for whole CartItem with TAX.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function totalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->total / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function taxFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->tax / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted tax.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function taxTotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->taxTotal / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted discount.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function discountFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->discount / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total discount for this cart item.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function discountTotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->discountTotal / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Returns the formatted total price for this cart item.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return string
     */
    public function priceTotalFormat($decimals = null, $decimalPoint = null, $thousandSeparator = null)
    {
        return $this->numberFormat($this->priceTotal / 100, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int $qty
     */
    public function setQuantity(int $qty)
    {
        if (empty($qty) || !is_numeric($qty)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param Buyable $item
     *
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id = $item->getBuyableIdentifier($this->options);
        $this->name = $item->getBuyableDescription($this->options);
        $this->price = $item->getBuyablePrice($this->options);
    }

    /**
     * Update the cart item from an array.
     *
     * @param array $attributes
     *
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
    }

    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     *
     * @return CartItem
     */
    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);

        return $this;
    }

    /**
     * Set the tax rate.
     *
     * @param int $taxRate
     *
     * @return CartItem
     */
    public function setTaxRate(int $taxRate)
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    /**
     * Set the discount rate.
     *
     * @param int $discountRate
     *
     * @return CartItem
     */
    public function setDiscountRate(int $discountRate)
    {
        $this->discountRate = $discountRate;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    public function __get(string $attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if(($attribute === 'model') && isset($this->associatedModel)) {
            return with(new $this->associatedModel())->find($this->id);
        }

        if(($attribute === 'modelFQCN') && isset($this->associatedModel)) {
            return $this->associatedModel;
        }

        $class = new ReflectionClass(DefaultCalculator::class);
        if (!$class->implementsInterface(Calculator::class)) {
            throw new InvalidCalculatorException('The configured Calculator seems to be invalid. Calculators have to implement the Calculator Contract.');
        }

        return call_user_func($class->getName().'::getAttribute', $attribute, $this);
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param Buyable $item
     * @param array $options
     *
     * @return CartItem
     */
    public static function fromBuyable(Buyable $item, array $options = [])
    {
        return new self($item->getBuyableIdentifier($options), $item->getBuyableDescription($options), $item->getBuyablePrice($options), $options);
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     *
     * @return CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options = Arr::get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $attributes['price'], $options);
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param int $id
     * @param string $name
     * @param int $price
     * @param array $options
     *
     * @return CartItem
     */
    public static function fromAttributes(int $id, string $name, int $price, array $options = [])
    {
        return new self($id, $name, $price, $options);
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param int $id
     * @param array $options
     *
     * @return string
     */
    protected function generateRowId(int $id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'rowId'    => $this->rowId,
            'id'       => $this->id,
            'name'     => $this->name,
            'qty'      => $this->qty,
            'price'    => $this->price,
            'options'  => $this->options->toArray(),
            'discount' => $this->discount,
            'tax'      => $this->tax,
            'subtotal' => $this->subtotal,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     * @throws \JsonException
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Get the formatted number.
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
        if (is_null($decimals)) {
            $decimals = self::DEFAULT_FORMAT_DECIMALS;
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = self::DEFAULT_FORMAT_DECIMAL_SEP;
        }

        if (is_null($thousandSeparator)) {
            $thousandSeparator = self::DEFAULT_FORMAT_THOUSAND_SEP;
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Getter for the raw internal discount rate.
     * Should be used in calculators.
     *
     * @return float
     */
    public function getDiscountRate()
    {
        return $this->discountRate;
    }
}
