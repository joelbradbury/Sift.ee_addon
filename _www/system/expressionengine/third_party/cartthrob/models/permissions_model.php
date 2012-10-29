<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Permissions_model extends CI_Model
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
	
	public function update($data, $id = NULL)
	{
		foreach (array('member_id', 'sub_id', 'permission', 'item_id', 'order_id') as $key)
		{
			// check if set... and not totally blank. 0 values are OK. As are 000 values
			if (isset($data[$key]) && $data[$key] !== NULL)
			{
				$insert[$key] = $data[$key]; 
			}
		}
		
			if ($id)  
			{     
				$this->db->update('cartthrob_permissions', $insert, array('id' => $id));
			} 
			else
			{
			   	$this->db->insert('cartthrob_permissions', $insert);
				$id = $this->db->insert_id(); 
				
			}
			
		return $id; 
	}
	public function delete($id, $sub_id = NULL, $member_id = NULL, $item_id = NULL, $order_id = NULL)
	{
		if ($order_id)
		{
			$this->db->delete('cartthrob_permissions', array('order_id' => $order_id));
		}
		if ($item_id)
		{
			$this->db->delete('cartthrob_permissions', array('item_id' => $item_id));
		}
		if ($sub_id)
		{
			$this->db->delete('cartthrob_permissions', array('sub_id' => $sub_id));
		}
		elseif ($member_id)
		{
			$this->db->delete('cartthrob_permissions', array('member_id' => $member_id));
		}
		else
		{
			$this->db->delete('cartthrob_permissions', array('id' => $id));
		}
	}
	
	public function get($params = array(), $limit = NULL, $offset = 0)
	{
		$this->load->helper('array');

		if ($id = element('id', $params))
		{
			if ( ! is_array($id))
			{
				$this->db->where('id', $id);
			}
			else
			{
				$this->db->where_in('id', $id);
			}
		}
		
		if ($order_id = element('order_id', $params))
		{
			if ( ! is_array($order_id))
			{
				$this->db->where('order_id', $order_id);
			}
			else
			{
				$this->db->where_in('order_id', $order_id);
			}
		}
		
		if ($item_id = element('item_id', $params))
		{
			if ( ! is_array($item_id))
			{
				$this->db->where('item_id', $item_id);
			}
			else
			{
				$this->db->where_in('item_id', $item_id);
			}
		}
		
		if ($sub_id = element('sub_id', $params))
		{
			if ( ! is_array($sub_id))
			{
				$this->db->where('sub_id', $sub_id);
			}
			else
			{
				$this->db->where_in('sub_id', $sub_id);
			}
		}
 
		if ($member_id = element('member_id', $params))
		{
			if ( ! is_array($member_id))
			{
				$this->db->where('member_id', $member_id);
			}
			else
			{
				$this->db->where_in('member_id', $member_id);
			}
		}
		
		if (isset($params['limit']))
		{
			$limit = $params['limit'];
			
			if (isset($params['offset']))
			{
				$offset = $params['offset'];
			}
		}
		
		if ( ! is_null($limit))
		{
			$this->db->limit((int) $limit, (int) $offset);
		}
		
		$query = $this->db->order_by('member_id', 'asc')
				  ->order_by('sub_id', 'desc')
				  ->order_by('id', 'desc')
				  ->get('cartthrob_permissions');
		
		$data = $query->result_array();
		
		$query->free_result();

		return $data;
	}
 }
