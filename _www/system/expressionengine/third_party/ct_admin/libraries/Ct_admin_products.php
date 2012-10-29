<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.4.1
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Product Library
 *
 * Wrapper for the various CartThrob Product functionality
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/libraries/ct_admin_products.php
 */
class Ct_admin_products
{	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->library('channel_data');
		$this->EE->load->library('ct_admin_orders');
		$this->channel_ids = $this->EE->order_model->store->config('product_channels');
		$this->purchased_items_channel = $this->EE->order_model->store->config('purchased_items_channel'); 
		$this->settings = $this->EE->ct_admin_lib->get_settings();
		$this->config = $this->EE->order_model->store->config();
		$this->dbprefix = $this->EE->db->dbprefix;
		//$this->channel_data = $this->EE->channel_model->get_channel_info($this->channel_id)->row();
	}

	/**
	 * Utiltity to only return products a user can view stats for
	 * @param int $author_id
	 */
	public function get_author_product_ids($author_id)
	{
		
		if ( ! isset($this->EE->session->cache[__CLASS__]['settings']))
		{		
			$data = $this->EE->db->select(array('entry_id'))
								->from('channel_titles')
								->where(array('author_id' => $author_id))
								->where("channel_id IN(".implode(',',$this->channel_ids).")")
								->get()->result_array();
			$return = array();
			foreach($data AS $item)
			{
				$return[] = $item['entry_id'];
			}
			
			$this->EE->session->cache[__CLASS__][__METHOD__] = $return;
		}
			
		return $this->EE->session->cache[__CLASS__][__METHOD__];
	}
	
	/**
	 * Returns the amount of orders in the system
	 * @param string	$where
	 */
	public function get_total_items_sold($where = FALSE)
	{
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$sql = "SELECT COUNT(ect.entry_id) AS total
				FROM ".$this->dbprefix."cartthrob_order_items ecoi, ".$this->dbprefix."channel_titles ect
				WHERE
				ect.entry_id = ecoi.order_id
				$where
		";
		$data = $this->EE->db->query($sql)->result_array();
		if(count($data) == '0')
		{
			return '0';
		}
	
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['total']))
			{
				return $data['0']['total'];
			}
		}
	}

	public function get_total_gross_sales($where = FALSE)
	{
		$sql = "SELECT SUM( price ) AS total
				FROM ".$this->dbprefix."cartthrob_order_items ecoi, ".$this->dbprefix."channel_titles ect
				WHERE ect.entry_id = ecoi.order_id
				$where
		";
		
		$data = $this->EE->db->query($sql)->result_array();
		if(count($data) == '0')
		{
			return '0';
		}	

		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['total']) && $data['0']['total'] != '')
			{
				return $data['0']['total'];
			}
		}	
		return '0';	
	}
	
	public function get_purchased_products($where = FALSE)
	{
		$sql = "SELECT ecoi.title AS product_title, ecoi.*, ect.*, ecd.*
				FROM ".$this->dbprefix."cartthrob_order_items ecoi, ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE ect.entry_id = ecoi.order_id
				AND ecd.entry_id = ect.entry_id
				$where
		";	

		$data = $this->EE->db->query($sql)->result_array();
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			$data = $this->EE->channel_data->_translate_custom_fields($data, $this->EE->ct_admin_orders->channel_id);
		}	

		//now some cleanup
		$keys = array();
		foreach($data AS $key => $value)
		{
			$data[$key]['combined_price'] = $value['quantity']*$value['price'];
			$keys[] = $value['order_id'];
		}

		$product_license_key = $this->EE->order_model->store->config('purchased_items_license_number_field');
		if(count($keys) >= '1' && $product_license_key)
		{
			$key = 'field_id_'.$product_license_key;
			$sql = "SELECT $key AS product_license_key, entry_id
					FROM ".$this->dbprefix."channel_data ecd
					WHERE
					ecd.entry_id IN(".implode(',',$keys).")
			";
			$licenses = $this->EE->db->query($sql)->result_array();	
			foreach($data AS $key => $value)
			{
				foreach($licenses AS $l_key => $license)
				{
					if($value['entry_id'] == $license['entry_id'])
					{
						$data[$key]['product_license_key'] = $license['product_license_key'];
					}
				}
			}
		}
		
		return $data;
	}
}