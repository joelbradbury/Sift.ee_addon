<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_discount_limit_by_quantity extends Cartthrob_discount
{
	public $title = 'limit_by_quantity_title';
	public $settings = array(
		array(
			'name' => 'percentage_off',
			'short_name' => 'percentage_off',
			'note' => 'percentage_off_note',
			'type' => 'text'
		),	
		array(
			'name' => 'qualifying_entry_ids',
			'short_name' => 'entry_ids',
			'note' => 'qualifying_entry_ids_note',
			'type' => 'text'
		),
		array(
			'name' => 'per_item_limit',
			'short_name' => 'item_limit',
			'note' => 'per_item_limit_note',
			'type' => 'text'
		)
	);
	
	public function get_discount()
	{
		if ($this->plugin_settings('entry_ids'))
		{
			$entry_ids = preg_split('/\s*,\s*/', trim($this->plugin_settings('entry_ids')));
			
			$percentage_off = $this->core->sanitize_number($this->plugin_settings('percentage_off')) / 100;
			
			foreach ($this->core->cart->items() as $item)
			{
				if ($item->product_id() && in_array($item->product_id(), $entry_ids))
				{
					$item_limit = $this->core->sanitize_number($this->plugin_settings('item_limit')); 
					
					if ($item->quantity() <= $item_limit)
					{
						return ($item->price() * $item->quantity()) * $percentage_off;
					}
					elseif ($item->quantity() > $item_limit)
					{
						return ($item->price() * $item_limit) * $percentage_off;
					}
					else
					{
						return 0; 
					}
				}
			}
		}
		
		return 0;
	}
}