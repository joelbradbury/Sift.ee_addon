<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @version		1.3.2
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Carts Model
 *
 * Used to manipulate saved carts
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/models/ct_admin_carts_model.php
 */
class Ct_admin_carts_model extends CI_Model
{
	private $_table = 'ct_admin_cart_items';
	
	public $cart_id;
	
	public $statuses = array(
							 '0' => 'inactive',
							 '1' => 'active'
	);
	
	public function __construct()
	{
		@session_start();
		parent::__construct();
		$this->cart_id = session_id();
	}
	
	private function get_sql($item)
	{
		$entry_id = '0';
		$meta = '';
		if(!isset($item['meta']) || !is_array($item['meta']))
		{
			$item['meta'] = array(); //weird fix because SOMETIMES CT doesn't send over the meta key
		}
		
		if(isset($item['entry_id']) && $item['entry_id'] >= '1')
		{
			 $entry_id = $item['entry_id'];
			 $meta = base64_encode(serialize($item['meta']));
		}
		elseif(isset($item['product_id']) && $item['product_id'] >= '1') //package setup
		{
			$entry_id = $item['product_id'];
			$meta = base64_encode(serialize($item['sub_items']));
		}

		return $data = array(
		   'row_id' => $item['row_id'],
		   'quantity' => $item['quantity'],
		   'product_id' => $item['product_id'],
		   'shipping' => $item['shipping'],
		   'weight' => $item['weight'],
		   'price' => $item['price'],
		   'no_tax' => $item['no_tax'],
		   'no_shipping' => $item['no_shipping'],
		   'item_options' => base64_encode(serialize($item['item_options'])),
		   'meta' => $meta,
		   'entry_id' => $entry_id,
		   'last_modified' => date('Y-m-d H:i:s')
		);
	}
	
	public function _set_lang($arr)
	{
		foreach($arr AS $key => $value)
		{
			$arr[$key] = lang($value);
		}
		return $arr;
	}
	
	/**
	 * Adds a item to the databse
	 * @param string $item
	 */
	public function add_item($item)
	{
		if(count($item) == '0')
		{
			return;
		}
		
		$data = $this->get_sql($item);
		if(count($data) == '0')
		{
			return;
		}		
		
		$data['cart_id'] = $this->cart_id;
		$data['created_date'] = date('Y-m-d H:i:s');
		if(isset($item['member_id']))
		{
			$data['member_id'] = $item['member_id'];
		}

		return $this->db->insert($this->_table, $data); 
	}	
	
	public function get_items($where = array())
	{
		if(count($where) == '0')
		{
			return;
				
		}		
		foreach($where AS $key => $value)
		{
			$this->db->where($key, $value);
		}
		$query = $this->db->get($this->_table);
		$data = $query->result_array();
		return $data;
	}
	
	/**
	 * Returns the value straigt from the database
	 * @param string $setting
	 */
	public function get_item(array $where)
	{
		if(count($where) == '0')
		{
			return;
				
		}		
		$data = $this->db->get_where($this->_table, $where)->result_array();
		if($data)
		{
			return $data['0'];
		}
	}	
	
	public function update_items(array $data, $where)
	{
		if(count($data) == '0' || count($where) == '0')
		{
			return;
				
		}		
		foreach($data AS $key => $value)
		{	
			$this->update_cron($data, $where);
		}
		
		return TRUE;
	}
	
	/**
	 * Updates a cart item
	 * @param string $key
	 * @param string $value
	 */
	public function update_item($data, $where, $complete = TRUE)
	{	
		if(count($data) == '0')
		{
			return;
			
		}
		if($complete)
		{
			$data = $this->get_sql($data);
		}
		
		return $this->db->update($this->_table, $data, $where);
	}
	
	public function delete_items(array $where)
	{
		if(count($where) == '0')
		{
			return;
				
		}		
		return $this->db->delete($this->_table, $where);		
	}
}