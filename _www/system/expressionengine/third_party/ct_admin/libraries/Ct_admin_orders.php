<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.3.2
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Order Library
 *
 * Wrapper for the various CartThrob Order functionality
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/libraries/ct_admin_orders.php
 */
class Ct_admin_orders
{	
	/**
	 * A mapping of the EE fields we'll be using for orders
	 * @var array
	 */
	public $ee_data_map = array(
				'entry_id' => 'entry_id', 
				'title' => 'title', 
				'status' => 'status', 
				'item_count' => 'item_count',
				'product_count' => 'product_count',
				'entry_date' => 'entry_date'
	);	
	
	/**
	 * A mapping of the CartThrob data model with EE
	 * @var array
	 */
	public $ct_order_data_map = array(
				//'order_items' => 'orders_items_field',
				'order_total' => 'orders_total_field', 
				'order_subtotal' => 'orders_subtotal_field', 
				'order_shipping' => 'orders_shipping_field', 
				'order_coupons' => 'orders_coupon_codes', 
				'order_tax' => 'orders_tax_field', 
				'order_shipping_option' => 'orders_shipping_option', 
				'order_error' => 'orders_error_message_field',
				'order_language' => 'orders_language_field',
				'order_discount' => 'orders_discount_field', 
				'orders_customer_email' => 'orders_customer_email', 
				'order_customer_full_name' => 'order_customer_full_name', 
				'order_customer_phone' => 'order_customer_phone', 
				'order_transaction_id' => 'orders_transaction_id', 
				'order_last_four' => 'orders_last_four_digits', 
				'order_ip_address' => 'order_ip_address', 
				'orders_billing_first_name' => 'orders_billing_first_name', 
				'orders_billing_last_name' => 'orders_billing_last_name', 
				'orders_billing_address' => 'orders_billing_address', 
				'orders_billing_address2' => 'orders_billing_address2', 
				'orders_billing_city' => 'orders_billing_city', 
				'orders_billing_state' => 'orders_billing_state', 
				'orders_billing_zip' => 'orders_billing_zip', 
				'orders_billing_country' => 'orders_billing_country', 
				'orders_country_code' => 'orders_country_code', 
				'orders_billing_company' => 'orders_billing_company', 
				'orders_shipping_first_name' => 'orders_shipping_first_name', 
				'orders_shipping_last_name' => 'orders_shipping_last_name', 
				'orders_shipping_address' => 'orders_shipping_address', 
				'orders_shipping_address2' => 'orders_shipping_address2', 
				'orders_shipping_city' => 'orders_shipping_city', 
				'orders_shipping_state' => 'orders_shipping_state', 
				'orders_shipping_zip' => 'orders_shipping_zip',
				'orders_shipping_country' => 'orders_shipping_country', 
				'orders_shipping_country_code' => 'orders_shipping_country_code', 
				'orders_shipping_company' => 'orders_shipping_company'
	);	
	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->channel_id = $this->EE->order_model->store->config('orders_channel');
		$this->settings = $this->EE->ct_admin_lib->get_settings();
		$this->config = $this->EE->order_model->store->config();
		$this->dbprefix = $this->EE->db->dbprefix;
		$this->channel_data = $this->EE->channel_model->get_channel_info($this->channel_id)->row();
	}
	
	public function get_first_date($format = FALSE)
	{
		$sql = "SELECT month, year, day FROM ".$this->dbprefix."channel_titles ect WHERE channel_id = '".$this->channel_id."' ORDER BY entry_date ASC LIMIT 1";
		$data = $this->EE->db->query($sql)->row();
		if(!is_array($data))	
		{
			return array('month' => $data->month, 'year' => $data->year, 'day' => $data->day);
		}
	}
	
	public function get_minmax_orders()
	{
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$sql = "SELECT MIN(CAST(field_id_$orders_total_key AS UNSIGNED)) AS min_order, 
					   MAX(CAST(field_id_$orders_total_key AS UNSIGNED)) AS max_order
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				
				WHERE ect.entry_id = ecd.entry_id 
				AND ecd.channel_id = '".$this->channel_id."'
		";
		$data = $this->EE->db->query($sql)->row();
		
		$return = array('min_order' => $data->min_order, 'max_order' => $data->max_order);
		return $return;
	}
	
	/**
	 * Returns an array of the statuses for the order channel
	 * @return array
	 */
	public function get_channel_statuses()
	{
		$channel_info = $this->EE->channel_model->get_channel_info($this->channel_id);
		$status_group = FALSE;
		foreach($channel_info->result_array() AS $row)
		{
			$status_group = $row['status_group'];
			break;
		}
		if($status_group)
		{
			return $this->EE->channel_model->get_channel_statuses($status_group)->result_array();
		}		
	}
	
	/**
	 * Wrapper to handle order retrieval
	 * @param int 		$per_page
	 * @param int 		$row_num
	 * @param string 	$order
	 * @param string	$where
	 */
	public function get_orders($per_page, $row_num, $order = 'entry_date DESC', $where = FALSE)
	{
		//EE stores everything as tinytext so we have to cast the order_total so it'll sort properly
		$parts = explode(' ', $order);
		$direction = $parts['1'];
		$order = ($parts['0'] == 'order_total' ? 'CAST(order_total  AS UNSIGNED)' : $parts['0']);

		//now we can run it
		$cust_email_key = $this->EE->order_model->store->config('orders_customer_email');
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$cust_last_name_key = $this->EE->order_model->store->config('orders_billing_last_name');
		$cust_first_name_key = $this->EE->order_model->store->config('orders_billing_first_name');
		$sql = "SELECT ect.entry_id, ect.title, em.member_id,
				field_id_$cust_first_name_key AS first_name, 
				field_id_$cust_last_name_key AS last_name,
				field_id_$cust_email_key AS email,
				ect.status,
				field_id_$orders_total_key AS order_total,
				entry_date 
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				LEFT JOIN ".$this->dbprefix."members em ON field_id_$cust_email_key = em.email
				WHERE ect.entry_id = ecd.entry_id
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				GROUP BY ect.entry_id
				ORDER BY $order $direction
				LIMIT $row_num, $per_page
		";
		return $this->EE->db->query($sql)->result_array();		
	}
	
	/**
	 * Returns the amount of orders in the system
	 * @param string	$where
	 */
	public function get_total_orders($where = FALSE)
	{
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$sql = "SELECT COUNT(ect.entry_id) AS total
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE 
				ect.channel_id = '".$this->channel_id."'
				AND ecd.entry_id = ect.entry_id
				$where
		";		
		$data = $this->EE->db->query($sql)->result_array();
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['total']))
			{
				return $data['0']['total'];
			}
		}
	}
	
	/**
	 * Returns the amount of money in the system
	 * @param string	$where
	 */
	public function get_total_sales($where = FALSE)
	{
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		if($orders_total_key == '')
		{
			return FALSE;
		}
		
		$sql = "SELECT SUM(field_id_$orders_total_key) AS total
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE ect.entry_id = ecd.entry_id 
				$where
				AND ecd.channel_id = '".$this->channel_id."'
		";
				
		$data = $this->EE->db->query($sql)->result_array();
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['total']))
			{
				return $data['0']['total'];
			}
		}
	}	
	
	public function get_average_order($where = FALSE)
	{
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		if($orders_total_key == '')
		{
			return FALSE;
		}
		
		$sql = "SELECT AVG(field_id_$orders_total_key) AS average_order
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE ect.entry_id = ecd.entry_id 
				$where
				AND ecd.channel_id = '".$this->channel_id."'
		";		
		$data = $this->EE->db->query($sql)->result_array();
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['average_order']))
			{
				return round($data['0']['average_order']);
			}
		}		
	}
	
	/**
	 * Wrapper to return an order 
	 * @param int $order_id
	 */
	public function get_order($order_id)
	{
		return $this->parse_ct_order_data($this->EE->order_model->get_order($order_id));	
	}
	
	/**
	 * Wrapper to update an order
	 * @param $order_id
	 * @param $data
	 */
	public function update_order($order_id, $data)
	{
		return $this->EE->order_model->update_order($order_id, $data);
	}
	
	/**
	 * Returns the items related to an order
	 * @param int $order_id
	 */
	public function get_order_items($order_id)
	{
		
		if ( ! is_array($order_id))
		{
			$this->EE->db->where('order_id', $order_id);
		}
		else
		{
			$this->EE->db->where_in('order_id', $order_id);
		}		
		return $this->EE->db->order_by('row_order', 'asc')->get('cartthrob_order_items')->result_array();
	}
	
	/**
	 * Removes all the items from an order
	 * @param int $order_ids
	 */
	public function delete_order_items($order_ids)
	{
		return $this->EE->order_model->delete_order_items($order_ids);
	}
	
	/**
	 * Removes orders
	 * @param array $order_ids
	 */
	public function delete_orders($order_ids)
	{
		if(!is_array($order_ids) || count($order_ids) == '0')
		{
			return FALSE;
		}
		$this->EE->load->library('api');
		$this->EE->api->instantiate('channel_entries');		
		if($this->EE->api_channel_entries->delete_entry($order_ids))
		{
			$this->delete_order_items($order_ids);			
			return TRUE;
		}		
	}
	
	public function json_ordering($perpage, $url_base)
	{

		$this->EE->output->enable_profiler(FALSE);
		$this->EE->load->helper('text');

		$col_map = array('title', 'first_name', 'status', 'entry_date', 'order_total');
		$id = ($this->EE->input->get_post('id')) ? $this->EE->input->get_post('id') : '';
		$keywords = ($this->EE->input->get_post('order_keywords')) ? $this->EE->input->get_post('order_keywords') : FALSE;
		$status = ($this->EE->input->get_post('f_status') && $this->EE->input->get_post('f_status') != '') ? $this->EE->input->get_post('f_status') : FALSE;
		$date_range = ($this->EE->input->get_post('date_range') && $this->EE->input->get_post('date_range') != '') ? $this->EE->input->get_post('date_range') : FALSE;
		$keyword = ($this->EE->input->get_post('k_search') && $this->EE->input->get_post('k_search') != '') ? $this->EE->input->get_post('k_search') : FALSE;
		$total_range = ($this->EE->input->get_post('total_range') && $this->EE->input->get_post('total_range') != '') ? $this->EE->input->get_post('total_range') : FALSE;
		
		$perpage = ($this->EE->input->get_post('perpage')) ? $this->EE->input->get_post('perpage') : $this->settings['orders_list_limit'];
		$offset = ($this->EE->input->get_post('iDisplayStart')) ? $this->EE->input->get_post('iDisplayStart') : 0; // Display start point
		$sEcho = $this->EE->input->get_post('sEcho');

		$order = array();
		
		if ($this->EE->input->get('iSortCol_0') !== FALSE)
		{
			for ( $i=0; $i < $this->EE->input->get('iSortingCols'); $i++ )
			{
				if (isset($col_map[$this->EE->input->get('iSortCol_'.$i)]))
				{
					$order[$col_map[$this->EE->input->get('iSortCol_'.$i)]] = ($this->EE->input->get('sSortDir_'.$i) == 'asc') ? 'asc' : 'desc';
				}
			}
		}

		$tdata = array();
		$i = 0;
		
		if (count($order) == 0)
		{
			$order = 'entry_date DESC';
		}
		else
		{
			$sort = '';
			foreach($order AS $key => $value)
			{
				$sort = $key.' '.$value;
			}
			$order = $sort;
		}

		$where = $this->_build_filter_where($keyword, $date_range, $status, $total_range);
		
		$total = $this->get_total_orders();
		$j_response['sEcho'] = $sEcho;
		$j_response['iTotalRecords'] = $total;
		$j_response['iTotalDisplayRecords'] = $this->get_total_orders($where);
		
				
		$data = $this->get_orders($perpage, $offset, $order, $where);
		$order_channel_statuses = $this->get_channel_statuses();
		
		foreach ($data as $item)
		{			
			$m[] = '<a href="'.$url_base.'order_view'.AMP.'id='.$item['entry_id'].'">'.$item['title'].'</a>';

			if($item['email'] != '')
			{
				$m[] = '<a href="'.$url_base.'customer_view'.AMP.'email='.$item['email'].'">'.$item['first_name'].' '.$item['last_name'].'</a>';
			}
			else
			{
				$m[] = $item['first_name'].' '.$item['last_name'];
			}
			
			$m[] = '<span style="color:#'.m62_status_color($item['status'], $order_channel_statuses).'">'.lang($item['status']).'</span>';
			$m[] = m62_convert_timestamp($item['entry_date']);
			$m[] = m62_format_money($item['order_total']);
			$m[] = '<input class="toggle" id="edit_box_'.$item['entry_id'].'" type="checkbox" name="toggle[]" value="'.$item['entry_id'].'" />';		

			$tdata[$i] = $m;
			$i++;
			unset($m);
		}		

		$j_response['aaData'] = $tdata;	
		return $this->EE->javascript->generate_json($j_response, TRUE);	
	}	
	
	public function get_export_orders()
	{
		$status = ($this->EE->input->get_post('f_status') && $this->EE->input->get_post('f_status') != '') ? $this->EE->input->get_post('f_status') : FALSE;
		$date_range = ($this->EE->input->get_post('date_range') && $this->EE->input->get_post('date_range') != '') ? $this->EE->input->get_post('date_range') : FALSE;
		$keyword = ($this->EE->input->get_post('k_search') && $this->EE->input->get_post('k_search') != '') ? $this->EE->input->get_post('k_search') : FALSE;
		$total_range = ($this->EE->input->get_post('total_range') && $this->EE->input->get_post('total_range') != '') ? $this->EE->input->get_post('total_range') : FALSE;
		
		$where = $this->_build_filter_where($keyword, $date_range, $status, $total_range);		
		$sql = "SELECT ect.*, ecd.*, ect.entry_id, COUNT(ecoi.order_id) AS product_count, SUM(ecoi.quantity) AS item_count
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd, ".$this->dbprefix."cartthrob_order_items ecoi
				WHERE 
				ect.entry_id = ecd.entry_id
				AND ecoi.order_id = ect.entry_id
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				GROUP BY ecoi.order_id
				ORDER BY ect.entry_id DESC
		";
		
		$data = $this->EE->db->query($sql)->result_array();	
		
		$i = 0;
		$arr = array();
		//print_r($data);
		//exit;
		foreach($data AS $order)
		{
			$arr[$i] = $this->parse_ct_order_data($order);
			$i++;
		}

		return $arr;
	}	
	
	private function parse_ct_order_data($customer)
	{	
		$arr = array();
		foreach($this->ee_data_map AS $key => $value)
		{
			if(isset($customer[$key]))
			{
				$arr[$key] = $customer[$value];
			}
			else
			{
				$arr[$key] = '';
			}
		}
				
		//check ct 
		foreach($this->ct_order_data_map AS $key => $value)
		{
			if(isset($this->config[$value]))
			{			
				$_key = $this->_make_key($this->config[$value]);
				if($_key)
				{
					$arr[$key] = $customer[$_key];
				}
				else
				{
					$arr[$key] = '';
				}
			}
			else
			{
				$arr[$key] = '';
			}				
		}

		return $arr;
	}

	public function _build_filter_where($keyword = FALSE, $date_range = FALSE, $status = FALSE, $total_range = FALSE)
	{
		$where = '';
		if($status)
		{
			$where .= " AND status = '".$status."'";
		}
		
		if($date_range && $date_range != 'custom_date')
		{
			if(is_numeric($date_range))
			{
				$where .= " AND entry_date > ".(mktime()-($date_range*24*60*60));
			}
			else
			{
				$parts = explode('to', $date_range);
				if(count($parts) == '2')
				{
					$start = strtotime($parts['0']);
					$end = strtotime($parts['1']);
					$where .= " AND entry_date BETWEEN $start AND $end";
				}
			}
		}
		
		if($keyword)
		{
			$cols = array();
			foreach($this->ct_order_data_map AS $key => $value)
			{
				$check = $this->EE->order_model->store->config($value);
				if($check != '')
				{
					$cols[] = "field_id_$check LIKE '%$keyword%'";
				}
			}

			if(count($cols) >= 1)
			{
				$where .= " AND (".implode(' OR ', $cols).") ";
			}
		}
		
		if($total_range)
		{
			$parts = explode("-", $total_range);
			if(count($parts) == '2')
			{
				$parts = array_map('trim', $parts);
				if(isset($parts['0']) && is_numeric($parts['0']) && isset($parts['1']) && is_numeric($parts['1']))
				{
					$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
					$where .= " AND CAST(field_id_$orders_total_key  AS UNSIGNED) BETWEEN '".$parts['0']."' AND '".$parts['1']."'";
				}
			}
		}
				
		return $where;
	}
	
	private function _make_key($key, $pre = '')
	{
		if($key == '')
		{
			return FALSE;
		}
		return $pre.'field_id_'.$key;
	}	
}