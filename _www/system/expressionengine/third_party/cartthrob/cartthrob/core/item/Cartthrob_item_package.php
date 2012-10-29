<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_item_package extends Cartthrob_item
{
	protected $sub_items;
	
	protected $defaults = array(
		'row_id' => NULL,
		'quantity' => 1,
		'product_id' => NULL,
		'price' => 0,
		'weight' => 0,
		'shipping' => 0,
		'title' => '',
		'no_tax' => FALSE,
		'no_shipping' => FALSE,
		'item_options' => array(),
		'sub_items' => array(),
	);
	
	public function initialize($params)
	{	
		parent::initialize($params);
		
		if (isset($params['sub_items']))
		{
			$this->set_sub_items($params['sub_items']);
		}
	}
	
	public function sub_items()
	{
		return $this->sub_items;
	}
	
	public function sub_item($row_id)
	{
		return (isset($this->sub_items[$row_id])) ? $this->sub_items[$row_id] : FALSE;
	}
	
	protected function set_sub_items($items)
	{
		foreach ($items as $item)
		{
			$class = (isset($item['class'])) ? $item['class'] : 'default';
			
			//$item['parent_item'] =& $this;
			
			$this->sub_items[$item['row_id']] = Cartthrob_core::create_child($this->core, 'item_'.$class, $item, $this->core->item_defaults);
			
			$this->sub_items[$item['row_id']]->set_parent_item($this);
		}
	}
	
	//@TODO add fixed pricing too
	public function price()
	{
		//if the price is set explicitly via the product, then return it
		if (is_numeric($this->product()->price()))
		{
			return $this->product()->price($this);
		}
		
		$price = 0;
		
		foreach ($this->sub_items() as $row_id => $item)
		{
			$price += $item->price() * $item->quantity();
		}
		
		return $price;
	}
	
	public function taxed_price()
	{
		if (is_numeric($this->product()->price()))
		{
			return $this->product()->price($this) * (1 + $this->core->store->tax_rate());
		}
		
		$price = 0;
		
		foreach ($this->sub_items() as $item)
		{
			$price += $item->price() * $item->quantity() * (1 + $this->core->store->tax_rate());
		}
		
		return $price;
	}
	
	public function inventory()
	{
		$inventory = FALSE;
		
		foreach ($this->sub_items() as $row_id => $item)
		{
			if ( ! $item->product_id())
			{
				continue;
			}
			
			$_inventory = floor($item->inventory($item->item_options()) / $item->quantity());
			
			if ($inventory === FALSE || $_inventory < $inventory)
			{
				$inventory = $_inventory;
			}
		}
		
		return ($inventory === FALSE) ? parent::inventory() : $inventory;
	}
	
	public function in_stock()
	{
		return $this->inventory() > 0;
	}
	
	public function weight()
	{
		$weight = 0;
		
		foreach ($this->sub_items() as $item)
		{
			$weight += $item->weight() * $item->quantity();
		}
		
		return $weight;
	}
	
	public function data()
	{
		$data = $this->product()->to_array();

		foreach ($this->to_array() as $key => $value)
		{
			$data[$key] = $value;
		}
		
		foreach ($this->sub_items() as $row_id => $item)
		{
			$data['sub_items'][$row_id] = $item->data();
		}
		
		return $data;
	}
	
	public function to_array($strip_defaults = FALSE)
	{
		$data = parent::to_array($strip_defaults);
		
		foreach ($this->sub_items() as $row_id => $item)
		{
			$data['sub_items'][$row_id] = array_merge($item->to_array(), array('row_id' => $row_id));
		}
		
		return $data;
	}
	
	/**
	 * Get the product title
	 *
	 * @return  string
	 */
	public function title()
	{
		return $this->product()->title();
	}
	/**
	 * Get a value from the meta array, or
	 * from the product's meta array, or
	 * get the whole array by not specifying a key
	 *
	 * @param   string|false $key
	 * @return  mixed|false
	 */
	public function meta($key = FALSE)
	{
		if ($key === FALSE)
		{
			return array_merge(parent::meta(), $this->product()->meta());
		}
		
		$meta = parent::meta($key);
	
		if ($meta === FALSE)
		{
			return $this->product()->meta($key);
		}
		
		return $meta;
	}
	
	//shortcut to this item's corresponding product object
	public function product($force_create = TRUE)
	{
		if ( ! $product = $this->core->store->product($this->product_id))
		{
			//create a NULLed product
			if ($force_create)
			{
				$product = Cartthrob_core::create_child($this->core, 'product');
			}
		}
		
		return $product;
	}
	
	/*item_product*/
	
	public function base_price()
	{
		$item = clone $this;
		
		$item->clear_item_options();
		
		return $this->product()->price($item);
	}
	
	public function shipping()
	{
		if ($this->no_shipping)
		{
			return 0;
		}
		
		if ($this->core->hooks->set_hook('item_shipping_start')->run() && $this->core->hooks->end())
		{
			$shipping = $this->core->hooks->value();
		}
		else
		{
			$shipping = (is_null($this->shipping)) ? $this->product()->shipping() * $this->quantity() : $this->shipping * $this->quantity();
			
			if ($this->core->hooks->set_hook('item_shipping_end')->run($shipping) && $this->core->hooks->end())
			{
				$shipping = $this->core->hooks->value();
			}
		}
		
		return $this->core->round($shipping);
	}
	
	/**
	 * Update the item's attributes with an array
	 *
	 * @param   array $params
	 * @return  Cartthrob_item
	 */
	public function update($params)
	{
		$sub_items = (isset($params['sub_items'])) ? $params['sub_items'] : array();
		
		unset($params['sub_items']);
		
		parent::update($params);
		
		foreach ($sub_items as $row_id => $item)
		{
			if (isset($this->sub_items[$row_id]))
			{
				$this->sub_items[$row_id]->update($item);
			}
		}
		
		//@TODO do something with sub_items
	}
	
	/* cartthrob_item */
	
	/**
	 * Get the product id
	 *
	 * @return int
	 */
	/*
	public function product_id()
	{
		return $this->product_id;
	}
	*/
	
	/**
	 * Get the row id
	 *
	 * @return int
	 */
	/*
	public function row_id()
	{
		return $this->row_id;
	}
	*/
	
	/**
	 * Get a value from the item options array, or
	 * get the whole array by not specifying a key
	 *
	 * @param   string|false $key
	 * @return  mixed|false
	 */
	/*
	public function item_options($key = FALSE)
	{
		if ($key === FALSE)
		{
			return $this->item_options;
		}
		
		return (isset($this->item_options[$key])) ? $this->item_options[$key] : FALSE;
	}
	*/
	
	/**
	 * Set a value in the item options array,
	 * or set many values by providing an array.
	 *
	 * @param   array|string $key
	 * @param   mixed $value
	 * @return  Cartthrob_item
	 */
	/*
	public function set_item_options($key, $value = FALSE)
	{
		if ( ! is_array($key))
		{
			$key = array($key => $value);
		}
		
		$this->item_options = array_merge($this->item_options, $key);
		
		return $this;
	}
	*/
	
	/**
	 * Empty the item_options array
	 *
	 * @return  Cartthrob_item
	 */
	/*
	public function clear_item_options()
	{
		$this->item_options = array();
		
		return $this;
	}
	*/
	
	/**
	 * Get a value from the meta array, or
	 * get the whole array by not specifying a key
	 *
	 * @param   string|false $key
	 * @return  mixed|false
	 */
	/*
	public function meta($key = FALSE)
	{
		if ($key === FALSE)
		{
			return $this->meta;
		}
		
		return (isset($this->meta[$key])) ? $this->meta[$key] : FALSE;
	}
	*/
	
	/**
	 * Set a value in the meta array,
	 * or set many values by providing an array.
	 *
	 * @param   array|string $key
	 * @param   mixed $value
	 * @return  Cartthrob_item
	 */
	/*
	public function set_meta($key, $value = FALSE)
	{
		if ( ! is_array($key))
		{
			$key = array($key => $value);
		}
		
		$this->meta = array_merge($this->meta, $key);
		
		return $this;
	}
	*/
	
	/**
	 * Set this item's row id
	 *
	 * @param   int $row_id
	 * @return  Cartthrob_item
	 */
	/*
	public function set_row_id($row_id)
	{
		$this->row_id = $row_id;
		
		return $this;
	}
	*/
	
	/**
	 * Change this item's quantity
	 * a) removes if quantity is 0
	 * b) checks global quantity limit
	 * c) checks split items by quantity preference
	 *
	 * @param   int $quantity
	 * @return  Cartthrob_item
	 */
	/*
	public function set_quantity($quantity = 0)
	{
		$quantity = $this->core->sanitize_integer($quantity);

		if ($quantity <= 0)
		{
			$this->remove();
			
			return $this;
		}
		
		if (is_numeric($quantity) && $quantity != $this->quantity())
		{
			if ($this->core->store->config('global_item_limit') && $quantity > $this->core->store->config('global_item_limit'))
			{
				$quantity = $this->core->store->config('global_item_limit');
			}

			if ($this->core->store->config('product_split_items_by_quantity') && $quantity > 1)
			{
				for ($i = 2; $i <= $quantity; $i++)
				{
					$this->core->cart->duplicate_item($this->row_id());
				}
			}
			else
			{
				$this->quantity = $quantity;
			}
		}
		
		return $this;
	}
	*/
	
	/**
	 * Increase the item's quantity
	 *
	 * @param   int $quantity
	 * @return  Cartthrob_item
	 */
	/*
	public function add_quantity($quantity = 1)
	{
		$this->quantity += $this->core->sanitize_integer($quantity);
		
		return $this;
	}
	*/
	
	/**
	 * Decrease the item's quantity
	 *
	 * @param   int $quantity
	 * @return  Cartthrob_item
	 */
	/*
	public function remove_quantity($quantity = 1)
	{
		$this->quantity -= $this->core->sanitize_integer($quantity);
		
		return $this;
	}
	*/
	
	/**
	 * Get the item's quantity
	 *
	 * @return  int
	 */
	/*
	public function quantity()
	{
		return $this->quantity;
	}
	*/
	
	
	/**
	 * Set the product id
	 *
	 * @param   int $product_id
	 * @return  Cartthrob_item
	 */
	/*
	public function set_product_id($product_id)
	{
		$this->product_id = $product_id;
		
		return $this;
	}
	*/
	
	/**
	 * Get the item base price
	 *
	 * @return int|float
	 */
	/* might need this one @TODO
	public function base_price()
	{
		return $this->core->round($this->product()->price());
	}
	*/
	
	/**
	 * Get the item base price w/ tax
	 *
	 * @return int|float
	 */
	/*
	public function taxed_base_price()
	{
		if ($plugin = $this->core->store->plugin($this->core->store->config('tax_plugin')))
		{
			return $plugin->get_tax($this->base_price()) + $this->base_price();
		}
		
		return $this->core->round( $this->price());
	}
	*/
	
	/**
	 * Get the item shipping cost
	 *
	 * @return int|float
	 */
	/*@TODO might need to be customized
	public function shipping()
	{
		if ($this->no_shipping)
		{
			return 0;
		}
		
		if ($this->core->hooks->set_hook('item_shipping_start')->run() && $this->core->hooks->end())
		{
			$shipping = $this->core->hooks->value();
		}
		else
		{
			$plugin = ($this->core->store->config('shipping_plugin')) ? Cartthrob_core::create_child($this->core, $this->core->store->config('shipping_plugin')) : NULL;
			
			$shipping = (method_exists($plugin, 'get_item_shipping') && is_callable(array($plugin, 'get_item_shipping'))) ? $plugin->get_item_shipping($this) * $this->quantity() : $this->shipping * $this->quantity();
			
			if ($this->core->hooks->set_hook('item_shipping_end')->run($shipping) && $this->core->hooks->end())
			{
				$shipping = $this->core->hooks->value();
			}
		}
		
		return $this->core->round($shipping);
	}
	*/
	
	/**
	 * Set the item title
	 *
	 * @param string $title
	 * @return Cartthrob_item
	 */
	/*
	public function set_title($title)
	{
		$this->title = $title;
		
		return $this;
	}
	*/
	/**
	 * Set the item price
	 *
	 * @param string $title
	 * @return Cartthrob_item
	 */
	/*
	public function set_price($price)
	{
		$this->price = $this->core->sanitize_number($price, TRUE);
		
		return $this;
	}
	*/
	
	/**
	 * Set the item weight
	 *
	 * @param string $title
	 * @return Cartthrob_item
	 */
	/*
	public function set_weight($weight)
	{
		$this->weight = $this->core->sanitize_number($weight, FALSE);
		
		return $this;
	}
	*/
	
	/**
	 * Set the item shipping cost
	 *
	 * @param string $title
	 * @return Cartthrob_item
	 */
	/*
	public function set_shipping($shipping)
	{
		$this->shipping = $this->core->sanitize_number($shipping, FALSE);
		
		return $this;
	}
	*/
	
	/**
	 * True if item not marked no_shipping
	 *
	 * @return bool
	 */
	/*
	public function is_shippable()
	{
		return ! $this->no_shipping;
	}
	*/
	
	/**
	 * True if item not marked no_tax
	 *
	 * @return bool
	 */
	/*
	public function is_taxable()
	{
		return ! $this->no_tax;
	}
	*/
	
	/**
	 * Remove this item from the cart
	 *
	 * @return bool
	 */
	/*
	public function remove()
	{
		$this->core->cart->remove_item($this->row_id);
	}
	*/
	
	/**
	 * Convert self to array
	 * 
	 * @param bool $strip_defaults To minimize the size of the array, you can set to true to not save its values that are the default values
	 * 
	 * @return array
	 */
	/*
	public function to_array($strip_defaults = FALSE)
	{
		$data = parent::to_array();
		
		if ($this->subclass())
		{
			$data['class'] = $this->subclass();
		}
		
		if ($strip_defaults)
		{
			foreach ($this->defaults as $key => $value)
			{
				if (isset($data[$key]) && $data[$key] === $value)
				{
					unset($data[$key]);
				}
			}
		}
		
		return $data;
	}
	*/
	
	/**
	 * Validate/sanitize parameters when initializing
	 * 
	 * @param array $params
	 * 
	 * @return Cartthrob_item
	 */
	/*
	public function prepare_params(&$params)
	{
		if ( ! is_array($params))
		{
			return $this;
		}
		
		$numeric = array('quantity', 'price', 'weight', 'shipping');
		
		foreach ($numeric as $key)
		{
			if (isset($params[$key]))
			{
				$params[$key] = $this->core->sanitize_number($params[$key]);
			}
		}
		
		if (isset($params['quantity']))
		{
			if ($this->core->store->config('global_item_limit') != FALSE && $params['quantity'] > $this->core->store->config('global_item_limit'))
			{
				$params['quantity'] = $this->core->store->config('global_item_limit');
			}
		}
		
		if (isset($params['item_options']) && is_array($params['item_options']) && is_array($this->item_options))
		{
			$params['item_options'] = array_merge($this->item_options, $params['item_options']);
		}
		
		return $this;
	}
	*/
}