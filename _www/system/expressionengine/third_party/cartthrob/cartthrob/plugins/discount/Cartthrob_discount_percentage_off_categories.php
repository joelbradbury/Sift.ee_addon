<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_discount_percentage_off_categories extends Cartthrob_discount
{
	public $title = 'percentage_off_categories';
	public $settings = array(
		array(
			'name' => 'percentage_off',
			'short_name' => 'percentage_off',
			'note' => 'percentage_off_note',
			'type' => 'text'
		),
		array(
			'name' => 'categories',
			'short_name' => 'categories',
			'type' => 'multiselect',
			'options' => array()
		),
	);
	
	public function initialize($plugin_settings = array())
	{
		$this->settings[1]['options'] = $this->core->get_categories();
		
		parent::initialize($plugin_settings);
	}
	
	public function get_discount()
	{
		$discount = 0;
		
		$valid_categories = $this->plugin_settings('categories', array());
		
		foreach ($this->core->cart->items() as $item)
		{
			$product = $this->core->store->product($item->product_id()); 
			
			if ( ! $product)
			{
				continue;
			}
			
			if (array_intersect($valid_categories, $product->categories()))
			{
				$discount += $item->price() * $item->quantity() * ($this->core->sanitize_number($this->plugin_settings('percentage_off')) / 100);
			}
		}
		
		return $discount;
	}
}