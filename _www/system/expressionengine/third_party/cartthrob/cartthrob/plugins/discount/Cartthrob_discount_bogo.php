<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_discount_bogo extends Cartthrob_discount
{
	public $title = 'buy_x_get_x';

	public $settings = array(
 		array(
			'name' => 'purchase_quantity',
			'short_name' => 'buy_x',
			'note' => 'enter_the_purchase_quantity',
			'type' => 'text'
		),
		array(
			'name' => 'discount_quantity',
			'short_name' => 'get_x_free',
			'note' => 'enter_the_number_of_items',
			'type' => 'text'
		),
		array(
			'name' => 'percentage_off',
			'short_name' => 'percentage_off',
			'note' => 'enter_the_percentage_discount',
			'type' => 'text'
		),
		array(
			'name' => 'amount_off',
			'short_name' => 'amount_off',
			'note' => 'enter_the_discount_amount',
			'type' => 'text'
		),
		array(
			'name' => 'qualifying_entry_ids',
			'short_name' => 'entry_ids',
			'note' => 'separate_multiple_entry_ids',
			'type' => 'text'
		),
		array(
			'name' => 'per_item_limit',
			'short_name' => 'item_limit',
			'note' => 'per_item_limit_note',
			'type' => 'text'
		),
	);


	function get_discount()
	{
		$discount 			= 0;
		$entry_ids 			= array();
		$not_entry_ids 		= array();
		
		// CHECK AMOUNTS AND PERCENTAGES
		if ($this->plugin_settings('percentage_off') !== '')
		{
			$percentage_off = ".01" * $this->core->sanitize_number( $this->plugin_settings('percentage_off') );

			if ($percentage_off > 100)
			{
				$percentage_off = 100; 
			}
			else if ($percentage_off < 0)
			{
				$percentage_off = 0; 
			}
		}
		else
		{
			$amount_off = $this->core->sanitize_number( $this->plugin_settings('amount_off') );
		}
		
		// CHECK ENTRY IDS
		if ( $this->plugin_settings('entry_ids') )
		{
			if (preg_match('/^not (.*)/',  trim( $this->plugin_settings('entry_ids') ) , $matches))
			{
				$not_entry_ids = preg_split('/\s*,\s*/',  $matches[1]);
				}
			else
			{
				$entry_ids = preg_split('/\s*,\s*/', trim( $this->plugin_settings('entry_ids') ));
			}
		}
		
		$item_limit = ( $this->plugin_settings('item_limit') ) ? $this->plugin_settings('item_limit') : FALSE;
			
		$items = array(); 

		if (count($entry_ids) > 0 || count($not_entry_ids) > 0)
		{
			foreach ($this->core->cart->items() as $item)
			{
				if (count($entry_ids) > 0)
				{
					if ( $item->product_id() && in_array( $item->product_id(), $entry_ids))
					{
						for ($i=0; $i<$item->quantity() ;$i++)
						{
							$items[] = $item->price(); 
						}
					}
				}
				else
				{
					if ( $item->product_id()  && ! in_array($item->product_id(), $not_entry_ids))
					{
						for ($i=0;$i<$item->quantity();$i++)
						{
							$items[] = $item->price(); 
						}
					}
				}

			}

		}
		else
		{
			foreach ($this->core->cart->items() as $item)
			{
				for ($i=0;$i<$item->quantity();$i++)
				{
					$items[] = $item->price(); 
				}
			}

		}
 		$counts = array();
		reset($items);			

		while (($price = current($items)) !== FALSE)
		{
			$key = key($items);

			$count = count($items);
			while($count > 0 && $count > $this->plugin_settings('buy_x') )
			{
					if ($item_limit !== FALSE && $item_limit < 1)
				{
					next($items);
						continue 2;
				}

				if (($count -= $this->plugin_settings('buy_x') ) > 0)
				{
					if ($this->plugin_settings('get_x_free'))
					{
					$free_count = ($count > $this->plugin_settings('get_x_free')) ? $this->plugin_settings('get_x_free') : $count;
					}
					else
					{
						$free_count = $count; 
					}
					if (isset($percentage_off))
					{
						//get the lowest price by grabbing the last array item
						//since our array is sorted by price
						for ($i=0;$i<$free_count;$i++)
						{
							$discount += end($items) * $percentage_off;
							array_pop($items);
						}

						//go back to where we were
						reset($items);
						while ($key != key($items)) next($items);
					}
					else
					{
						for ($i=0;$i<$free_count;$i++)
						{
							array_pop($items);
							$discount += $amount_off;
						}
					}

					//remove the buy_x items from begginning of array
					for ($i=0;$i<$this->plugin_settings('buy_x');$i++)
					{
						array_shift($items);
					}

					$count -= $free_count;
				}

				if ($item_limit !== FALSE)
				{
					$item_limit--;
				}
			}

			next($items);
		}

		return $discount;
	}

	function validate()
	{
		$entry_ids = array();
		$not_entry_ids = array();

		if (! $this->plugin_settings('entry_ids'))
		{
  			foreach ($this->core->cart->items() as $item)
			{
				if ( $item->quantity() > $this->core->sanitize_number( $this->plugin_settings('buy_x') ))
				{
					return TRUE;
				}
				$this->set_error( $this->core->lang('coupon_minimum_not_reached') );
				return FALSE;
			}
		}
		if (  $this->plugin_settings('entry_ids') )
		{
			$entry_ids = preg_split('/\s*,\s*/', trim($this->plugin_settings('entry_ids')));

			if (preg_match('/^not (.*)/',  trim( $this->plugin_settings('entry_ids') ) , $matches))
			{
				$codes = (explode('not', $matches[1], 2));
				$not_entry_ids = preg_split('/\s*,\s*/',  $codes[1]);
			}
		}
 		if (count($entry_ids))
		{
				foreach ($this->core->cart->items() as $item)
				{
					if ( $item->product_id()  && in_array( $item->product_id(), $entry_ids))
					{
						if ( $item->quantity() > $this->core->sanitize_number( $this->plugin_settings('buy_x') ))
						{
							return TRUE;
						}
					}
				$this->set_error( $this->core->lang('coupon_minimum_not_reached') );
				}
			}	
		elseif(count($not_entry_ids))
		{
			foreach ($this->core->cart->items() as $item)
			{
					if ( $item->product_id()  && ! in_array( $item->product_id(), $entry_ids))
					{
						if ( $item->quantity() > $this->core->sanitize_number( $this->plugin_settings('buy_x') ))
						{
							return TRUE;
						}
					}
				$this->set_error( $this->core->lang('coupon_minimum_not_reached'));
			}	
		}
		else
		{
			$this->set_error( $this->core->lang('coupon_not_valid_for_items') );
		}
		return FALSE;
	}
}