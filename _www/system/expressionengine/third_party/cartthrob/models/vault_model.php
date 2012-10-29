<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Vault_model extends CI_Model
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
	
	public function update_vault($data, $id = NULL)
	{
		foreach (array('description', 
						'status', 
						'expires', 
						'timestamp', 
						'vault_id', 
						'sub_id', 
						'order_id',
				 		'member_id', 
						'gateway', 
						'error_message', 
						'trial_price', 
						'price', 
						'allow_modification', 
						'interval_units', 
						'total_intervals', 
						'trial_occurrences', 
						'trial_occurrences') as $key)
		{
			$insert[$key] = (isset($data[$key])) ? $data[$key] : '';
		}
		
		// only these following are available currently. 
		if (isset($insert['status']))
		{
			switch ($insert['status'])
			{
				case "open": 
				case "closed": 
				case "failed": 
				case "declined": 
				case "expired":
				case "pending":  
				break; 
				default: 
				$insert['status']= "closed"; 
				
			}
		}
		
		if ($id)  
		{     
			$this->db->update('cartthrob_vault', array('status' => $insert['status']), array('id' => $id));
		} 
		else
		{
			if (!isset($insert['status']))
			{
				$insert['status'] = "closed"; 
			}
		   	$this->db->insert('cartthrob_vault', $insert);
		}

	}
	public function delete_vault($id=NULL, $order_id = NULL, $member_id = NULL, $sub_id=NULL)
	{
		if ($sub_id)
		{
			$this->db->delete('cartthrob_vault', array('sub_id' => $sub_id));
		}
		if ($order_id)
		{
			$this->db->delete('cartthrob_vault', array('order_id' => $order_id));
		}
		elseif ($member_id)
		{
			$this->db->delete('cartthrob_vault', array('member_id' => $member_id));
		}
		elseif ($id)
		{
			$this->db->delete('cartthrob_vault', array('id' => $id));
		}
		// @TODO error
	}
	
	public function get_member_vaults($member_id, $limit = NULL, $offset = 0)
	{
		return $this->get_vaults(array('member_id' => $member_id), $limit, $offset);
	}
	
	public function get_vaults($params = array(), $limit = NULL, $offset = 0)
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
				  ->order_by('order_id', 'desc')
				  ->order_by('id', 'desc')
				  ->get('cartthrob_vault');
		
		$vaults = $query->result_array();
		
		$query->free_result();

		return $vaults;
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

}
