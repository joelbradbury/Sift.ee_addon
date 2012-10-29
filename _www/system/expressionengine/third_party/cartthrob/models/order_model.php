<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Order_model extends CI_Model
{
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		$this->load->model('cartthrob_field_model');
		$this->load->model('cartthrob_entries_model');
		$this->load->library('cartthrob_loader');
		$this->load->helper('data_formatting');
		$this->cartthrob_loader->setup($this);
	}
	
	public function create_order($order_data)
	{
		if ( ! $this->store->config('orders_channel'))
		{
			return FALSE;
		}
		
		$this->load->model('cartthrob_members_model');
		
		$order_data['channel_id'] = $this->store->config('orders_channel');
		
		$data = $this->convert_order_data($order_data);
		
		$data['status'] = ($this->store->config('orders_processing_status')) ? $this->store->config('orders_processing_status') : 'closed';
		$data['author_id'] = (!empty($order_data['member_id']) ? $order_data['member_id'] : $this->cartthrob_members_model->get_member_id());
		
		if ( ! empty($order_data['expiration_date']))
		{
			$data['expiration_date'] = $this->localize->now + ($order_data['expiration_date']*24*60*60);
		}

		if ($this->store->config('orders_sequential_order_numbers'))
		{
			$last_order_number = $this->cartthrob->store->config('last_order_number');
			
			//fallback to the old way if the setting isn't present
			if ($last_order_number === FALSE)
			{
				$order_number = 1;
				
				$query = $this->db->select('title')
						->from('channel_titles')
						->where('channel_id', $data['channel_id'])
						->where('site_id', $this->config->item('site_id'))
						->like('title', $this->store->config('orders_title_prefix'), 'after')
						->like('title', $this->store->config('orders_title_suffix'), 'before')
						->order_by('entry_date', 'desc')
						->limit(1)
						->get();
				
				if ($query->num_rows())
				{
					$order_number = (int) str_replace(array($this->store->config('orders_title_prefix'), $this->store->config('orders_title_suffix')), '', $query->row('title')) + 1;
				}
			
				$this->db->insert('cartthrob_settings', array(
					'`key`' => 'last_order_number',
					'value' => $order_number,
					'site_id' => $this->config->item('site_id'),
				));
			}
			else
			{
				if ($last_order_number == 0)
				{
					$this->db->where(array(
						'`key`' => 'last_order_number',
						'site_id' => $this->config->item('site_id'),
					));
					
					if ($this->db->count_all_results('cartthrob_settings') === 0)
					{
						$this->db->insert('cartthrob_settings', array(
							'`key`' => 'last_order_number',
							'value' => 0,
							'site_id' => $this->config->item('site_id'),
							'serialized' => 0,
						));
					}
				}
				
				$order_number = $last_order_number + 1;
				
				$this->db->update('cartthrob_settings', array(
					'value' => $order_number,
				), array(
					'`key`' => 'last_order_number',
					'site_id' => $this->config->item('site_id'),
				));
			}
			
			$data['title'] = $this->store->config('orders_title_prefix').$order_number.$this->store->config('orders_title_suffix');
			$data['url_title'] = $this->store->config('orders_url_title_prefix').$order_number.$this->store->config('orders_url_title_suffix');
			
			$entry_id = $this->cartthrob_entries_model->create_entry($data);
		}
		else if ($entry_id = $this->cartthrob_entries_model->create_entry($data))
		{
			$data = array(
				'title' => $this->store->config('orders_title_prefix').$entry_id.$this->store->config('orders_title_suffix'),
				'url_title' => $this->store->config('orders_url_title_prefix').$entry_id.$this->store->config('orders_url_title_suffix'),
			);
			
			$this->cartthrob_entries_model->update_entry($entry_id, $data);
		}
		
		if ($this->store->config('orders_items_field'))
		{
			$field_type = $this->cartthrob_field_model->get_field_type($this->store->config('orders_items_field'));

			if ($field_type === 'cartthrob_order_items')
			{
				$items = array();

				foreach ($this->cart->items() as $item)
				{
					$items[] = $this->create_order_item_row($item);
				}
				
				$this->update_order_items($entry_id, $items);
				
				$this->cartthrob_entries_model->update_entry($entry_id, array('field_id_'.$this->store->config('orders_items_field') => 1));
			}
		}
		if (!empty($entry_id))
		{
			$data['entry_id'] = $entry_id; 
		}
		return $data;
	}
	
	protected function create_order_item_row(Cartthrob_item $item)
	{
		$row = array(
			'entry_id' => $item->product_id(),
			'title' => $item->title(),
			'quantity' => $item->quantity(),
			'price' => $item->price(),
			'price_plus_tax' => $item->taxed_price(),
			'weight' => $item->weight(),
			'shipping' => $item->shipping(),
			'no_tax' => ! $item->is_taxable(),
			'no_shipping' => ! $item->is_shippable(),
		);

		if (is_array($item->item_options()))
		{
			$row = array_merge($row, $item->item_options());
		}
		
		if ($item->sub_items())
		{
			foreach ($item->sub_items() as $i => $sub_item)
			{
				$sub_row = array(
					'entry_id' => $sub_item->product_id(),
					'title' => $sub_item->title(),
					'quantity' => $sub_item->quantity(),
					'price' => $sub_item->price(),
					'price_plus_tax' => $sub_item->taxed_price(),
					'weight' => $sub_item->weight(),
					'shipping' => $sub_item->shipping(),
					'no_tax' => ! $sub_item->is_taxable(),
					'no_shipping' => ! $sub_item->is_shippable(),
				);

				if (is_array($sub_item->item_options()))
				{
					$sub_row = array_merge($sub_row, $sub_item->item_options());
				}
				
				$row['sub_items'][$i] = $this->create_order_item_row($sub_item);
			}
		}
		
		return $row;
	}
	
	public function update_order($entry_id, $order_data)
	{
		if ( ! $this->store->config('orders_channel'))
		{
			return FALSE;
		}
		
		return $this->cartthrob_entries_model->update_entry($entry_id, $this->convert_order_data($order_data));
	}
	
	public function get_order_items($order_ids, $entry_ids = array(), $member_ids = array())
	{
		$this->load->helper('data_formatting');
		
		if ($order_ids)
		{
			if ( ! is_array($order_ids))
			{
				$this->db->where('order_id', $order_ids);
			}
			else
			{
				$this->db->where_in('order_id', $order_ids);
			}
		}
		
		if ($entry_ids)
		{
			if ( ! is_array($entry_ids))
			{
				$this->db->where('cartthrob_order_items.entry_id', $entry_ids);
			}
			else
			{
				$this->db->where_in('cartthrob_order_items.entry_id', $entry_ids);
			}
		}
		
		if ($member_ids)
		{
			$this->db->select('cartthrob_order_items.*')
				 ->join('channel_titles', 'channel_titles.entry_id = cartthrob_order_items.order_id');
			
			if ( ! is_array($member_ids))
			{
				$this->db->where('channel_titles.author_id', $member_ids);
			}
			else
			{
				$this->db->where_in('channel_titles.author_id', $member_ids);
			}
		}
		
		$query = $this->db->order_by('order_id, row_order', 'asc')
				  ->get('cartthrob_order_items');
		
		$order_items = $query->result_array();
		
		$query->free_result();
		
		foreach ($order_items as &$row)
		{
			if ($row['extra'] && $extra = _unserialize($row['extra'], TRUE))
			{
				$row = array_merge($row, $extra);
			}
			
			unset($row['extra']);
		}
		
		return $order_items;
	}
	/*
		add function to process permissions. 
		permissions should be passed in like so {exp:cartthrob:add_to_cart_form permissions="1|2|3"}
		$permissions = explode('|', $item_data['meta']['permissions']); 
		permissions can be tied to a purchase without a sub, in which case they are connected only to the existance of the purchased item. 
		they can also be tied to a sub. in which case, we need to track the sub id related to the purchase. 
	*/ 
	public function update_vault($data, $id = NULL)
	{
		foreach (array('description', 'status', 'expires', 'timestamp', 'vault_id', 'sub_id', 'order_id', 'member_id') as $key)
		{
			$insert[$key] = (isset($data[$key])) ? $data[$key] : '';
		}
		
		if ($id)  
		{     
			if (!isset($insert['status']))
			{
				$insert['status']= "closed"; 
			}
			$this->db->update('cartthrob_vault', array('status' => $insert['status']), array('id' => $id));
		} 
		else
		{
		   	$this->db->insert('cartthrob_vault', $insert);
		}

	}
	public function delete_vault($id, $order_id = NULL, $member_id = NULL)
	{
		if ($order_id)
		{
			$this->db->delete('cartthrob_vault', array('order_id' => $order_id));
		}
		elseif ($member_id)
		{
			$this->db->delete('cartthrob_vault', array('member_id' => $member_id));
		}
		else
		{
			$this->db->delete('cartthrob_vault', array('id' => $id));
		}
	}
	public function update_order_items($entry_id, $data)
	{
		$rows_to_keep = array();
		
		foreach ($data as $row_order => $row)
		{
			$insert = array(
				'order_id' => $entry_id,
				'row_order' => $row_order,
			);
			
			foreach (array('entry_id', 'title', 'quantity', 'price', 'price_plus_tax', 'weight', 'shipping', 'no_tax', 'no_shipping') as $key)
			{
				$insert[$key] = (isset($row[$key])) ? $row[$key] : '';
				unset($row[$key]);
			}
			
			$insert['extra'] = (count($row) > 0) ? base64_encode(serialize($row)) : '';
			
			if ( ! empty($row['row_id']))
			{
				$this->db->update('cartthrob_order_items', $insert, array('row_id' => $row['row_id']));
				
				$rows_to_keep[] = $row['row_id'];
			}
			else
			{
				$this->db->insert('cartthrob_order_items', $insert);
				
				$rows_to_keep[] = $this->db->insert_id();
			}
		}
		
		$this->delete_order_items($entry_id, $rows_to_keep);
	}
	
	public function delete_order_items($entry_ids, $rows_to_keep = FALSE)
	{
		if ( ! is_array($entry_ids))
		{
			$entry_ids = array($entry_ids);
		}
		
		foreach ($entry_ids as $entry_id)
		{
			if ($rows_to_keep)
			{
				$this->db->where_not_in('row_id', $rows_to_keep);
			}
			
			$this->db->delete('cartthrob_order_items', array('order_id' => $entry_id));
		}
	}
	
	private function convert_order_data($order_data)
	{
		$this->load->library('locales');
	
		$data = $order_data;
		
		$custom_data = $this->cart->custom_data();
		
		$fields = $this->cartthrob_field_model->get_fields_by_channel($this->store->config('orders_channel'));

		foreach ($fields as $field)
		{
			if ($this->input->post($field['field_name']) !== FALSE)
			{
				$data['field_id_'.$field['field_id']] = $this->input->post($field['field_name'], TRUE);
			}
			
			if (isset($custom_data[$field['field_name']]))
			{
				$data['field_id_'.$field['field_id']] = $custom_data[$field['field_name']];
			}
		}

		if ($this->store->config('orders_subtotal_field') && isset($order_data['subtotal']))
		{
			$data['field_id_'.$this->store->config('orders_subtotal_field')] = $order_data['subtotal'];
		}
		if ($this->store->config('orders_subtotal_plus_tax_field') && isset($order_data['subtotal_plus_tax']))
		{
			$data['field_id_'.$this->store->config('orders_subtotal_plus_tax_field')] = $order_data['subtotal_plus_tax'];
		}
		if ($this->store->config('orders_tax_field') && isset($order_data['tax']))
		{
			$data['field_id_'.$this->store->config('orders_tax_field')] = $order_data['tax'];
		}
		if ($this->store->config('orders_shipping_field') && isset($order_data['shipping']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_field')] = $order_data['shipping'];
		}
		if ($this->store->config('orders_shipping_plus_tax_field') && isset($order_data['shipping_plus_tax']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_plus_tax_field')] = $order_data['shipping_plus_tax'];
		}
		if ($this->store->config('orders_total_field') && isset($order_data['total']))
		{
			$data['field_id_'.$this->store->config('orders_total_field')] = $order_data['total'];
		}
		if ($this->store->config('orders_discount_field') && isset($order_data['discount']))
		{
			$data['field_id_'.$this->store->config('orders_discount_field')] = $order_data['discount'];
		}
		if ($this->store->config('orders_coupon_codes') && isset($order_data['coupon_codes']))
		{
			$data['field_id_'.$this->store->config('orders_coupon_codes')] = $order_data['coupon_codes'];
		}
		if ($this->store->config('orders_last_four_digits') && isset($order_data['last_four_digits']))
		{
			$data['field_id_'.$this->store->config('orders_last_four_digits')] = $order_data['last_four_digits'];
		}
		if ($this->store->config('orders_transaction_id') && isset($order_data['transaction_id']))
		{
			$data['field_id_'.$this->store->config('orders_transaction_id')] = $order_data['transaction_id'];
		}
		if ($this->store->config('orders_customer_name') && isset($order_data['customer_name']))
		{
			$data['field_id_'.$this->store->config('orders_customer_name')] = $order_data['customer_name'];
		}
		if ($this->store->config('orders_customer_email') && isset($order_data['customer_email']))
		{
			$data['field_id_'.$this->store->config('orders_customer_email')] = $order_data['customer_email'];
		}
		if ($this->store->config('orders_customer_ip_address') && isset($order_data['customer_ip_address']))
		{
			$data['field_id_'.$this->store->config('orders_customer_ip_address')] = $order_data['customer_ip_address'];
		}
		if ($this->store->config('orders_customer_phone') && isset($order_data['customer_phone']))
		{
			$data['field_id_'.$this->store->config('orders_customer_phone')] = $order_data['customer_phone'];
		}
		if ($this->store->config('orders_full_billing_address') && isset($order_data['full_billing_address']))
		{
			$data['field_id_'.$this->store->config('orders_full_billing_address')] = $order_data['full_billing_address'];
		}
		if ($this->store->config('orders_billing_first_name') && isset($order_data['billing_first_name']))
		{
			$data['field_id_'.$this->store->config('orders_billing_first_name')] = $order_data['billing_first_name'];
		}
		if ($this->store->config('orders_billing_last_name') && isset($order_data['billing_last_name']))
		{
			$data['field_id_'.$this->store->config('orders_billing_last_name')] = $order_data['billing_last_name'];
		}
		if ($this->store->config('orders_billing_company') && isset($order_data['billing_company']))
		{
			$data['field_id_'.$this->store->config('orders_billing_company')] = $order_data['billing_company'];
		}
		if ($this->store->config('orders_billing_address') && isset($order_data['billing_address']))
		{
			$data['field_id_'.$this->store->config('orders_billing_address')] = $order_data['billing_address'];
		}
		if ($this->store->config('orders_billing_address2') && isset($order_data['billing_address2']))
		{
			$data['field_id_'.$this->store->config('orders_billing_address2')] = $order_data['billing_address2'];
		}
		if ($this->store->config('orders_billing_city') && isset($order_data['billing_city']))
		{
			$data['field_id_'.$this->store->config('orders_billing_city')] = $order_data['billing_city'];
		}
		if ($this->store->config('orders_billing_state') && isset($order_data['billing_state']))
		{
			$data['field_id_'.$this->store->config('orders_billing_state')] = $order_data['billing_state'];
		}
		if ($this->store->config('orders_billing_zip') && isset($order_data['billing_zip']))
		{
			$data['field_id_'.$this->store->config('orders_billing_zip')] = $order_data['billing_zip'];
		}
		if ($this->store->config('orders_billing_country'))
		{
			if ($this->store->config('orders_convert_country_code'))
			{
				if (isset($order_data['billing_country_code']))
				{
					$order_data['billing_country'] = $this->locales->country_from_country_code($order_data['billing_country_code']);
				}
			}
			
			if (isset($order_data['billing_country']))
			{
				$data['field_id_'.$this->store->config('orders_billing_country')] = $order_data['billing_country'];
			}
		}
		if ($this->store->config('orders_country_code') && isset($order_data['country_code']))
		{
			$data['field_id_'.$this->store->config('orders_country_code')] = $order_data['country_code'];
		}
		if ($this->store->config('orders_full_shipping_address') && isset($order_data['full_shipping_address']))
		{
			$data['field_id_'.$this->store->config('orders_full_shipping_address')] = $order_data['full_shipping_address'];
		}
		if ($this->store->config('orders_shipping_first_name') && isset($order_data['shipping_first_name']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_first_name')] = $order_data['shipping_first_name'];
		}
		if ($this->store->config('orders_shipping_last_name') && isset($order_data['shipping_last_name']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_last_name')] = $order_data['shipping_last_name'];
		}
		if ($this->store->config('orders_shipping_company') && isset($order_data['shipping_company']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_company')] = $order_data['shipping_company'];
		}
		if ($this->store->config('orders_shipping_address') && isset($order_data['shipping_address']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_address')] = $order_data['shipping_address'];
		}
		if ($this->store->config('orders_shipping_address2') && isset($order_data['shipping_address2']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_address2')] = $order_data['shipping_address2'];
		}
		if ($this->store->config('orders_shipping_city') && isset($order_data['shipping_city']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_city')] = $order_data['shipping_city'];
		}
		if ($this->store->config('orders_shipping_state') && isset($order_data['shipping_state']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_state')] = $order_data['shipping_state'];
		}
		if ($this->store->config('orders_shipping_zip') && isset($order_data['shipping_zip']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_zip')] = $order_data['shipping_zip'];
		}
		if ($this->store->config('orders_shipping_country'))
		{
			if ($this->store->config('orders_convert_country_code'))
			{
				if (isset($order_data['shipping_country_code']))
				{
					$order_data['shipping_country'] = $this->locales->country_from_country_code($order_data['shipping_country_code']);
				}
			}
			
			if (isset($order_data['shipping_country']))
			{
				$data['field_id_'.$this->store->config('orders_shipping_country')] = $order_data['shipping_country'];
			}

		}
		if ($this->store->config('orders_shipping_country_code') && isset($order_data['shipping_country_code']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_country_code')] = $order_data['shipping_country_code'];
		}
		if ($this->store->config('orders_shipping_option') && isset($order_data['shipping_option']))
		{
			$data['field_id_'.$this->store->config('orders_shipping_option')] = $order_data['shipping_option'];
		}
		if ($this->store->config('orders_error_message_field') && isset($order_data['error_message']))
		{
			$data['field_id_'.$this->store->config('orders_error_message_field')] = $order_data['error_message'];
		}
		if ($this->store->config('orders_language_field'))
		{
			$data['field_id_'.$this->store->config('orders_language_field')] = ($this->input->cookie('language')) ? $this->input->cookie('language', TRUE) : $this->session->userdata('language');
		}
		if ($this->store->config('orders_payment_gateway') && isset($order_data['payment_gateway']))
		{
			$data['field_id_'.$this->store->config('orders_payment_gateway')] = $order_data['payment_gateway'];
		}
		
		$data['channel_id'] = $this->store->config('orders_channel');
		
		return $data;
	}
	public function get_sub_id($id)
	{
		$query = $this->db->select('sub_id')
				->from('cartthrob_vault')
				->where('id', $id)
				->limit(1)
				->get();
		
		if ($query->num_rows())
		{
			return $query->row('sub_id');
		}
		return NULL;
	}
	public function get_order_cart_id($entry_id)
	{
		$query = $this->db->select('cart_id')
				->from('cartthrob_status')
				->where('entry_id', $entry_id)
				->limit(1)
				->get();
		
		if ($query->num_rows())
		{
			return $query->row('cart_id');
		}
		return NULL; 
		
	} 
	public function get_order_id_from_session($session_id)
	{
		$query = $this->db->select('entry_id')
				->from('cartthrob_status')
				->where('session_id', $session_id)
				->limit(1)
				->get();
		
		if ($query->num_rows())
		{
			return $query->row('entry_id');
		}
		return NULL; 
		
	}
	
	public function get_status($entry_id, $key = FALSE, $default = FALSE)
	{
		if ( ! isset($this->session->cache['cartthrob'][__CLASS__][__FUNCTION__][$entry_id]))
		{
			$query = $this->db->where('entry_id', $entry_id)
					  ->limit(1)
					  ->get('cartthrob_status');
			
			if ($query->num_rows() === 0)
			{
				return FALSE;
			}
			
			$this->session->cache['cartthrob'][__CLASS__][__FUNCTION__][$entry_id] = $query->row_array();
			
			$query->free_result();
		}
		
		$cache =& $this->session->cache['cartthrob'][__CLASS__][__FUNCTION__][$entry_id];
		
		if ($key !== FALSE)
		{
			return (isset($cache[$key])) ? $cache[$key] : $default;
		}
		
		return ($cache) ? $cache : $default;
	}
	
	public function get_order_status($entry_id)
	{
		return $this->get_status($entry_id, 'status', NULL);
		
	}
	
	public function get_order_transaction_id($entry_id)
	{
		return $this->get_status($entry_id, 'transaction_id', NULL);
	}
	
	public function get_order_error_message($entry_id)
	{
		return $this->get_status($entry_id, 'error_message', NULL);
	}
	
	public function set_order_status($entry_id, $status="processing")
	{
		$statuses = array(
			'authorized', 
			'completed',
			'processing', 
			'reversed',
			'refunded',
			'voided',
			'expired',
			'canceled',
			'failed',
			'declined', 
			'offsite'
		);     
		if (!in_array($status, $statuses))
		{
			$status =  "processing"; 
		}
		if ($this->get_order_status($entry_id) !="authorized" && $this->get_order_status($entry_id) != "completed")
		{
			// set order status.
			if ( $this->get_order_status($entry_id) != NULL)  
			{     
				$this->db->update('cartthrob_status', array('status' => $status), array('entry_id' => $entry_id));
			} 
			else
			{
			   	$this->db->insert('cartthrob_status', array('entry_id' => $entry_id, 'status' => $status));
			}
		} 
		elseif ($status == "refunded" || $status== "reversed" || $status == "voided")
		{
			if ( $this->get_order_status($entry_id) != NULL)  
			{     
				$this->db->update('cartthrob_status', array('status' => $status), array('entry_id' => $entry_id));
			} 
			else
			{
			   	$this->db->insert('cartthrob_status', array('entry_id' => $entry_id, 'status' => $status));
			}
		}  
		return $status; 
	}
	public function set_order_transaction_id($entry_id, $transaction_id= NULL)
	{
		if (!$transaction_id)
		{
			return; 
		}

		if ($this->get_status($entry_id))  
		{
			$this->db->update('cartthrob_status', array('transaction_id' => $transaction_id), array('entry_id' => $entry_id));
		}
		else
		{
		   	$this->db->insert('cartthrob_status', array('entry_id' => $entry_id, 'transaction_id' => $transaction_id));
		}
 		return $transaction_id; 
	}
	public function set_order_error_message($entry_id, $error_message= NULL)
	{
		if (!$error_message)
		{
			return NULL; 
		}

		if ($this->get_status($entry_id))  
 		{     
			$this->db->update('cartthrob_status', array('error_message' => $error_message), array('entry_id' => $entry_id));
		} 
		else
		{
		   	$this->db->insert('cartthrob_status', array('entry_id' => $entry_id, 'error_message' => $error_message));
		}
 		return $error_message; 
	}
	public function get_cart_from_order($entry_id)
	{
		$query = $this->db->select('cart')
				->from('cartthrob_status')
				->where('entry_id', $entry_id)
				->limit(1)
				->get();
					
		if ($query->row('cart'))
		{
			$this->load->library('encrypt');
 			return _unserialize($this->encrypt->decode($query->row('cart')));
 		}

		return NULL; 
	}
	public function save_cart_snapshot($entry_id, $inventory_processed=FALSE, $discounts_processed=FALSE, $cart = NULL, $cart_id=NULL, $session_id = NULL)
	{
		$data = array(); 
		if ( $inventory_processed !==FALSE)
		{
			$data['inventory_processed'] = $inventory_processed; 
		}
		if ( $discounts_processed !==FALSE)
		{
			$data['discounts_processed'] = $discounts_processed; 
			
		}
		if ($cart)
		{
			$this->load->library('encrypt');
			$data['cart']	= $this->encrypt->encode(serialize($cart));
			
		}	
		if ($cart_id)
		{
			$data['cart_id'] = $cart_id; 
		}
		if ($session_id)
		{
			$data['session_id']  = $session_id; 
		}	
		
		$query = $this->db->select('inventory_processed')
				->select('discounts_processed')
				->select('cart')
				->select('cart_id')
				->from('cartthrob_status')
				->where('entry_id', $entry_id)
				->limit(1)
				->get();
					
		if ($query->num_rows())
		{
			$this->db->update('cartthrob_status', $data, array('entry_id' => $entry_id));
		}
		else
		{
			$data['entry_id'] = $entry_id; 
			$this->db->insert('cartthrob_status', $data);
		}
	}
	public function get_order($entry_id)
	{
		return $this->cartthrob_entries_model->entry($entry_id);
	}
	
	public function get_orders($where)
	{
		$where['channel_titles.channel_id'] = $this->store->config('orders_channel');
		
		return $this->cartthrob_entries_model->find_entries($where);
	}
	
	public function get_member_orders($member_id, $where = array())
	{
		$where['author_id'] = $member_id;
		
		return $this->get_orders($where);
	}
	
	public function get_member_last_order($member_id)
	{
		return current($this->get_member_orders($member_id));
	}
	
	public function order_totals($where = FALSE, $just_total = FALSE)
	{
		$defaults = array(
			'total' => 0,
			'subtotal' => 0,
			'tax' => 0,
			'shipping' => 0,
			'discount' => 0,
			'orders' => 0
		);
		
		if ( ! $this->store->config('orders_total_field') || ! $this->store->config('orders_channel'))
		{
			return ($just_total) ? 0 : $defaults;
		}
		
		$this->db->select('SUM(field_id_'.$this->store->config('orders_total_field').') AS total', TRUE);
		
		if (is_array($where))
		{
			foreach ($where as $key => $value)
			{
				$this->db->where($key, $value);
			}
		}
		
		if ($this->store->config('orders_subtotal_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_subtotal_field').') AS subtotal', TRUE);
		}
		
		if ($this->store->config('orders_subtotal_plus_tax_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_subtotal_plus_tax_field').') AS subtotal_plus_tax', TRUE);
		}
		
		if ($this->store->config('orders_tax_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_tax_field').') AS tax', TRUE);
		}
		
		if ($this->store->config('orders_shipping_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_shipping_field').') AS shipping', TRUE);
		}
		if ($this->store->config('orders_shipping_plus_tax_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_shipping_plus_tax_field').') AS shipping_plus_tax', TRUE);
		}
		
		if ($this->store->config('orders_discount_field'))
		{
			$this->db->select('SUM(field_id_'.$this->store->config('orders_discount_field').') AS discount', TRUE);
		}
		
		$this->db->select('COUNT(*) AS orders');
		
		$this->db->from('channel_data')
			->join('channel_titles', 'channel_titles.entry_id = channel_data.entry_id')
			->where('channel_titles.channel_id', $this->store->config('orders_channel'));
			
		$data = $this->db->get()->row_array();
		
		if (array_key_exists("shipping_plus_tax", $data))
		{
			$data['shipping:plus_tax'] = $data['shipping_plus_tax']; 
		}
		if (array_key_exists("subtotal_plus_tax", $data))
		{
			$data['subtotal:plus_tax'] = $data['subtotal_plus_tax']; 
		}
		#var_dump($data); 
		
		foreach ($defaults as $key => $value)
		{
			if (empty($data[$key]))
			{
				$data[$key] = $value;
			}
		}
		
		if ($just_total)
		{
			return $data['total'];
		}
		return $data;
	}
}
