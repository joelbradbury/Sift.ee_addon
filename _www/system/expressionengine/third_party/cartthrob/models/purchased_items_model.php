<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Purchased_items_model extends CI_Model
{
	public $cartthrob;
	
	public function __construct()
	{
		$this->load->library('cartthrob_loader');
		$this->cartthrob =& get_instance()->cartthrob;
		$this->load->model('cartthrob_entries_model');
	}
	
	public function purchased_entry_ids()
	{
		$query = $this->db->select('field_id_'.$this->cartthrob->store->config('purchased_items_id_field')." AS entry_id")
				  	->distinct()
	    			->where('channel_id', $this->cartthrob->store->config('purchased_items_channel'))
				  	->get('channel_data');
		
		$entry_ids = array(); 
		foreach ($query->result() as $row)
		{
			$entry_ids[] = $row->entry_id; 
		}
		return $entry_ids; 
	}
	
	//returns an array
	//  entry_id => count
	public function also_purchased($entry_id, $limit = FALSE)
	{
		static $cache;
		
		if (isset($cache[$entry_id]))
		{
			return $cache[$entry_id];
		}
		
		if ( ! $entry_id || ! $this->cartthrob->store->config('orders_channel'))
		{
			return array();
		}
		
		$purchased = array();
		
		$query = $this->db->select('order_id')
				  ->distinct()
				  ->from('cartthrob_order_items')
				  ->where('entry_id', $entry_id)
				  ->get();
		
		if ($query->num_rows() === 0)
		{
			return array();
		}
		
		$order_ids = array();
		
		foreach ($query->result() as $row)
		{
			$order_ids[] = $row->order_id;
		}
		
		$query->free_result();
		
		$query = $this->db->select('entry_id')
				  ->distinct()
				  ->from('cartthrob_order_items')
				  ->where_in('order_id', $order_ids)
				  ->where('entry_id !=', $entry_id)
				  ->get();
		
		if ($query->num_rows() === 0)
		{
			return array();
		}
		
		foreach ($query->result() as $row)
		{
			if (isset($purchased[$row->entry_id]))
			{
				$purchased[$row->entry_id]++;
			}
			else
			{
				$purchased[$row->entry_id] = 1;
			}
		}
		
		$query->free_result();
		
		if ( ! $limit)
		{
			$limit = 20;
		}
		
		arsort($purchased);
		
		$purchased = array_slice($purchased, 0, $limit, TRUE);
		
		$cache[$entry_id] = $purchased;
		
		return $purchased;
	}
	
	public function has_purchased($entry_id)
	{
		if ( ! $this->cartthrob->store->config('orders_channel'))
		{
			return FALSE;
		}
		
		$status = $this->cartthrob->store->config('orders_default_status') ? $this->cartthrob->store->config('orders_default_status') : 'open';
		
		$this->db->from('cartthrob_order_items')
			 ->join('channel_titles', 'channel_titles.entry_id = cartthrob_order_items.order_id')
			 ->where('cartthrob_order_items.entry_id', $entry_id)
			 ->where('channel_titles.author_id', $this->session->userdata('member_id'))
			 ->where('channel_titles.site_id', $this->config->item('site_id'))
			 ->where('channel_titles.status', $status)
			 ->where('channel_titles.channel_id', $this->cartthrob->store->config('orders_channel'));
		
		return ($this->db->count_all_results() > 0);
	}
	
	public function update_purchased_item($entry_id, $data)
	{
		return $this->cartthrob_entries_model->update_entry($entry_id, $data);
	}
	
	public function create_purchased_item($item_data, $order_id, $status)
	{
		$this->load->model('cartthrob_members_model');
		$this->load->helper('url');
		
		if ( ! $channel_id = $this->cartthrob->store->config('purchased_items_channel'))
		{
			return 0;
		}
		
		$product = $this->cartthrob->store->product($item_data['product_id']);
		
		$title = element('title', $item_data);
		
		if ($product && ! $title)
		{
			$title = $product->title();
		}
		
		$word_separator = ($this->config->item('word_separator') === 'underscore') ? '_' : $this->config->item('word_separator');
		
		$data = array(
			'title' => $this->cartthrob->store->config('purchased_items_title_prefix').$title,
			'url_title' => url_title($title, $word_separator, TRUE).$word_separator.uniqid(NULL, TRUE),
			'author_id' => $this->cartthrob_members_model->get_member_id(),
			'channel_id' => $channel_id,
			'status' => ($status) ? $status : 'closed',
		);
		
		if ( ! empty($item_data['meta']['expires']))
		{
			$data['expiration_date'] = $this->localize->now + ($item_data['meta']['expires']*24*60*60);
		}

		if ($this->cartthrob->store->config('purchased_items_id_field') && isset($item_data['product_id']))
		{
			$data['field_id_'.$this->cartthrob->store->config('purchased_items_id_field')] = $item_data['product_id'];
		}
		
		if ($this->cartthrob->store->config('purchased_items_quantity_field') && isset($item_data['quantity']))
		{
			$data['field_id_'.$this->cartthrob->store->config('purchased_items_quantity_field')] = $item_data['quantity'];
		}
		
		if ($this->cartthrob->store->config('purchased_items_price_field'))
		{
			if ( ! empty($item_data['price']))
			{
				$data['field_id_'.$this->cartthrob->store->config('purchased_items_price_field')] = $item_data['price'];
			}
			else if ($product)
			{
				$data['field_id_'.$this->cartthrob->store->config('purchased_items_price_field')] = $product->price();
			}
		}
		
		if ($this->cartthrob->store->config('purchased_items_order_id_field') && $order_id)
		{
			$data['field_id_'.$this->cartthrob->store->config('purchased_items_order_id_field')] = $order_id;
		}
		
		if ($this->cartthrob->store->config('purchased_items_license_number_field') && ! empty($item_data['meta']['license_number']))
		{
			$limit = 25;

			$license_number = '';

			$this->load->helper('license_number');
			
			do
			{
				$license_number = generate_license_number($this->cartthrob->store->config('purchased_items_license_number_type'));

				$this->db->from('channel_data')
						->where('field_id_'.$this->cartthrob->store->config('purchased_items_license_number_field'), $license_number);

				$limit --;

			} while($this->db->count_all_results() > 0 && $limit >= 0);

			if ($limit >= 0 && $license_number)
			{
				$data['field_id_'.$this->cartthrob->store->config('purchased_items_license_number_field')] = $license_number;
			}
		}
		
		foreach ($this->cartthrob_field_model->get_fields_by_channel($channel_id) as $field)
		{
			if ($this->input->post($field['field_name']) !== FALSE)
			{
				$data['field_id_'.$field['field_id']] = $this->input->post($field['field_name'], TRUE);
			}

			if (isset($item_data['item_options'][$field['field_name']]))
			{
				$data['field_id_'.$field['field_id']] = $item_data['item_options'][$field['field_name']];
			}
			
			if (preg_match('/^purchased_(.*)/', $field['field_name'], $match) && isset($item_data['item_options'][$match[1]]))
			{
				$data['field_id_'.$field['field_id']] = $item_data['item_options'][$match[1]];
			}
		}
		
		return $this->cartthrob_entries_model->create_entry($data);
	}
}
