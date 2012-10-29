<?php 
if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_shipping_by_item_w_free extends Cartthrob_shipping
{
 	public $title = 'Shipping by item with free option';
 	public $overview = "If custom_data:free_shipping is set to 'yes' shipping costs will be waived.";
	public $settings = array(
			 
 		);
	public function initialize()
	{
	}
	public function get_shipping()
	{
 		$shipping = 0; 
		if ($this->core->cart->custom_data("free_shipping") != "yes" )
		{
			foreach ($this->core->cart->shippable_items() as $row_id => $item)
			{
				$shipping += $item->shipping(); 
	 		}
		} 
 
		return $shipping; 
	}
}

