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
 * CT Admin - Customers
 *
 * Contains most wrapper methods for manipulating CartThrob customers
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/libraries/ct_admin_customers.php
 */
class Ct_admin_customers
{

	/**
	 * A mapping of the CartThrob data model with EE
	 * @var array
	 */
	public $ct_order_data_map = array(
				'cust_first_name' => 'orders_billing_first_name', 
				'cust_last_name' => 'orders_billing_last_name', 
				'cust_address' => 'orders_billing_address', 
				'cust_address2' => 'orders_billing_address2', 
				'cust_city' => 'orders_billing_city', 
				'cust_state' => 'orders_billing_state', 
				'cust_zip' => 'orders_billing_zip', 
				'cust_country' => 'orders_billing_country', 
				'cust_country_code' => 'orders_country_code', 
				'cust_company' => 'orders_billing_company', 
				'cust_email_address' => 'orders_customer_email', 
				'cust_shipping_first_name' => 'orders_shipping_first_name', 
				'cust_shipping_last_name' => 'orders_shipping_last_name', 
				'cust_shipping_address' => 'orders_shipping_address', 
				'cust_shipping_address2' => 'orders_shipping_address2', 
				'cust_shipping_city' => 'orders_shipping_city', 
				'cust_shipping_state' => 'orders_shipping_state', 
				'cust_shipping_zip' => 'orders_shipping_zip', 
				'cust_shipping_country' => 'orders_shipping_country', 
				'cust_shipping_country_code' => 'orders_shipping_country_code', 
				'cust_shipping_company' => 'orders_shipping_company'
	);
	
	public $ee_member_data_map = array(
				'cust_first_name' => 'member_first_name_field', 
				'cust_last_name' => 'member_last_name_field', 
				'cust_address' => 'member_address_field', 
				'cust_address2' => 'member_address2_field', 
				'cust_city' => 'member_city_field', 
				'cust_state' => 'member_state_field', 
				'cust_zip' => 'member_zip_field', 
				'cust_country' => 'member_country_field', 
				'cust_country_code' => 'member_country_code_field', 
				'cust_company' => 'member_company_field', 
				'cust_email_address' => 'member_email_address_field', 
				'cust_use_billing_info' => 'member_use_billing_info_field', 
				'cust_shipping_first_name' => 'member_shipping_first_name_field', 
				'cust_shipping_last_name' => 'member_shipping_last_name_field', 
				'cust_shipping_address' => 'member_shipping_address_field', 
				'cust_shipping_address2' => 'member_shipping_address2_field', 
				'cust_shipping_city' => 'member_shipping_city_field', 
				'cust_shipping_state' => 'member_shipping_state_field', 
				'cust_shipping_zip' => 'member_shipping_zip_field', 
				'cust_shipping_country' => 'member_shipping_country_field', 
				'cust_shipping_country_code' => 'member_shipping_country_code_field', 
				'cust_shipping_company' => 'member_shipping_company_field', 
				'cust_language' => 'member_language_field', 
				'cust_language' => 'member_shipping_option_field', 
				'cust_region' => 'member_region_field'
	);
		
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->channel_id = $this->EE->order_model->store->config('orders_channel');
		$this->config = $this->EE->order_model->store->config();
		$this->settings = $this->EE->ct_admin_lib->get_settings();
		$this->dbprefix = $this->EE->db->dbprefix;
	}
	
	public function get_customers($per_page, $row_num, $order = 'entry_date DESC', $where = FALSE)
	{

		$cust_email_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_customer_email'));
		$orders_total_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_total_field'));
		$cust_last_name_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_billing_last_name'));
		$cust_first_name_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_billing_first_name'));
		$sql = "SELECT COUNT( ect.entry_id ) AS total_orders, SUM( field_id_$orders_total_key ) AS total_sales, 
				field_id_$cust_email_key AS email, 
				field_id_$cust_first_name_key AS first_name, 
				field_id_$cust_last_name_key AS last_name,
				MIN(ect.entry_date) AS first_order,
				MAX(ect.entry_date) AS last_order
				FROM ".$this->dbprefix."channel_data ecd, ".$this->dbprefix."channel_titles ect
				WHERE ect.entry_id = ecd.entry_id
				AND field_id_$cust_email_key != ''
				$where
				GROUP BY field_id_$cust_email_key
				ORDER BY ".$this->EE->db->escape_str($order)."
				LIMIT ".$this->EE->db->escape_str($row_num).", ".$this->EE->db->escape_str($per_page)."
		";
		
		return $this->EE->db->query($sql)->result_array(); 		
	}
	
	public function get_orders_by_email($email, $where = FALSE)
	{
		//return $this->EE->order_model->get_orders(array());
		$cust_email_key = $this->EE->order_model->store->config('orders_customer_email');
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$cust_last_name_key = $this->EE->order_model->store->config('orders_billing_last_name');
		$cust_first_name_key = $this->EE->order_model->store->config('orders_billing_first_name');

		$sql = "SELECT ect.entry_id, ect.title, em.member_id,
				field_id_$cust_first_name_key AS first_name, 
				field_id_$cust_last_name_key AS last_name,
				field_id_$cust_email_key AS email,
				ect.status,
				ect.channel_id,
				field_id_$orders_total_key AS order_total,
				entry_date 
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				LEFT JOIN exp_members em ON field_id_$cust_email_key = em.email
				WHERE ect.entry_id = ecd.entry_id
				AND field_id_$cust_email_key = '".$this->EE->db->escape_str($email)."'
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				ORDER BY entry_date DESC
		";
		return $this->EE->db->query($sql)->result_array();			
	}
	
	public function get_products_by_email($email, $where = FALSE)
	{
		$cust_email_key = $this->EE->order_model->store->config('orders_customer_email');
		$orders_total_key = $this->EE->order_model->store->config('orders_total_field');
		$cust_last_name_key = $this->EE->order_model->store->config('orders_billing_last_name');
		$cust_first_name_key = $this->EE->order_model->store->config('orders_billing_first_name');

		$sql = "SELECT ecoi.title, ecoi.entry_id, ect.status, price, COUNT(field_id_$orders_total_key) AS product_sales, SUM(field_id_$orders_total_key) AS product_total, ect2.channel_id AS product_channel_id, ect.entry_date
				FROM exp_channel_titles ect, exp_cartthrob_order_items ecoi, exp_channel_data ecd, exp_channel_titles ect2
				WHERE ect.entry_id = ecd.entry_id
				AND ecoi.order_id = ect.entry_id
				AND ect2.entry_id = ecoi.entry_id
				AND field_id_$cust_email_key = '".$this->EE->db->escape_str($email)."'
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				GROUP BY ecoi.title, ect.status, price
				ORDER BY entry_date DESC
		";
		return $this->EE->db->query($sql)->result_array();
	}
	
	public function get_export_customers($where = FALSE)
	{
		$status = ($this->EE->input->get_post('f_status') && $this->EE->input->get_post('f_status') != '') ? $this->EE->input->get_post('f_status') : FALSE;
		$date_range = ($this->EE->input->get_post('date_range') && $this->EE->input->get_post('date_range') != '') ? $this->EE->input->get_post('date_range') : FALSE;
		$keyword = ($this->EE->input->get_post('k_search') && $this->EE->input->get_post('k_search') != '') ? $this->EE->input->get_post('k_search') : FALSE;
				
		$where .= $this->EE->ct_admin_orders->_build_filter_where($keyword, $date_range, $status);
				
		$cust_email_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_customer_email'));
		$orders_total_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_total_field'));
		$cust_last_name_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_billing_last_name'));
		$cust_first_name_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_billing_first_name'));
		$sql = "SELECT COUNT( ect.entry_id ) AS total_orders, SUM( field_id_$orders_total_key ) AS total_sales, 
				MIN(ect.entry_date) AS first_order,
				MAX(ect.entry_date) AS last_order,
				ecd.*
				FROM ".$this->dbprefix."channel_data ecd, ".$this->dbprefix."channel_titles ect
				WHERE ect.entry_id = ecd.entry_id
				AND field_id_$cust_email_key != ''
				$where
				GROUP BY field_id_$cust_email_key
				ORDER BY ect.entry_id DESC
		";

		$data = $this->EE->db->query($sql)->result_array(); 
		$arr = array();
		$keys = array();
		$i = 0;
		foreach($data AS $customer)
		{
			$arr[$i] = $this->parse_ct_order_data($customer);
			$arr[$i]['total_orders'] = $customer['total_orders'];
			$arr[$i]['total_sales'] = $customer['total_sales'];
			$arr[$i]['first_order'] = m62_convert_timestamp($customer['first_order'], $this->settings['export_date_format']);
			$arr[$i]['last_order'] = m62_convert_timestamp($customer['last_order'], $this->settings['export_date_format']);
			$i++;
		}

		return $arr;
	}
	
	public function get_purchase_dates($email)
	{
		$cust_email_key = $this->EE->order_model->store->config('orders_customer_email');
		$sql = "SELECT MAX(entry_date) AS last_order, MIN(entry_date) AS first_order
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE
				field_id_$cust_email_key = '".$this->EE->db->escape_str($email)."'
				AND ect.entry_id = ecd.entry_id
				AND ecd.channel_id = '".$this->channel_id."'
		";
		
		$data = $this->EE->db->query($sql)->result_array();
		if($data)
		{
			return $data['0'];
		}
	}	
	
	public function get_total_customers($where = FALSE)
	{
		$cust_email_key = $this->EE->db->escape_str($this->EE->order_model->store->config('orders_customer_email'));
		$sql = "SELECT COUNT(DISTINCT(field_id_$cust_email_key)) AS total
				FROM `".$this->dbprefix."cartthrob_order_items` ecot, ".$this->dbprefix."channel_data ecd, ".$this->dbprefix."channel_titles ect
				WHERE ecot.order_id = ecd.entry_id
				AND ect.entry_id = ecd.entry_id
				AND field_id_$cust_email_key != ''
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
	
	public function get_customer_data($email)
	{
		$customer_data = $this->get_ee_member_by_email($email);
		if($customer_data)
		{
			$customer_data = $customer_data['0'];
			$ee_member_id = $customer_data['ee_member_id'];
			$customer_data = $this->parse_ee_member_data($customer_data);
			$customer_data['ee_member_id'] = $ee_member_id;
		}
		else
		{
			$customer_data = $this->fake_customer_data($email);
			if(!$customer_data)
			{
				return FALSE;
			}
			
			$customer_data = $customer_data['0'];
			$customer_data = $this->parse_ct_order_data($customer_data);
		}
		
		return $customer_data;
	}
	
	public function get_ee_member_by_email($email)
	{
		$member_id = $this->get_ee_member_id($email);
		$data = $this->EE->member_model->get_all_member_data($member_id)->result_array();
		if($data)
		{
			$data['0']['ee_member_id'] = $member_id;
			return $data;
		}
	}
	
	public function fake_customer_data($email)
	{
		$cust_email_key = $this->EE->order_model->store->config('orders_customer_email');

		$sql = "SELECT 
				ecd.*,
				em.member_id AS ee_member_id
				FROM ".$this->dbprefix."cartthrob_order_items ecot, ".$this->dbprefix."channel_data ecd
				LEFT JOIN ".$this->dbprefix."members em ON field_id_$cust_email_key = em.email
				WHERE field_id_$cust_email_key = '".$this->EE->db->escape_str($email)."'
				ORDER BY ecd.entry_id DESC
				LIMIT 1
		";
		return $this->EE->db->query($sql)->result_array();
	}
	
	private function parse_ct_order_data($customer)
	{	
		$arr = array();
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
	
	private function parse_ee_member_data($customer)
	{	
		$arr = array();
		foreach($this->ee_member_data_map AS $key => $value)
		{
			if(isset($this->config[$value]))
			{
				$_key = $this->_make_key($this->config[$value], 'm_');
				if($_key && isset($customer[$_key]))
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
	
	private function _make_key($key, $pre = '')
	{
		if($key == '')
		{
			return FALSE;
		}
		return $pre.'field_id_'.$key;
	}
		
	public function delete_customers($customer_ids)
	{
	
	}
	
	public function get_ee_member_id($email)
	{
		$sql = "SELECT member_id FROM ".$this->dbprefix."members WHERE email = '".$this->EE->db->escape_str($email)."'";
		$data = $this->EE->db->query($sql)->result_array();
		$customer_data = array();
		$customer_data['ee_member_id'] = FALSE;
		if($data && is_array($data) && array_key_exists('0', $data))
		{
			if(isset($data['0']['member_id']))
			{
				return $data['0']['member_id'];
			}
		}		
	}
	
	public function json_ordering($perpage, $url_base, $where)
	{

		$this->EE->output->enable_profiler(FALSE);
		$this->EE->load->helper('text');

		$col_map = array('first_name', 'email', 'total_sales', 'total_orders', 'last_order');
		$id = ($this->EE->input->get_post('id')) ? $this->EE->input->get_post('id') : '';
		
		$keywords = ($this->EE->input->get_post('order_keywords')) ? $this->EE->input->get_post('order_keywords') : FALSE;
		$status = ($this->EE->input->get_post('f_status') && $this->EE->input->get_post('f_status') != '') ? $this->EE->input->get_post('f_status') : FALSE;
		$date_range = ($this->EE->input->get_post('date_range') && $this->EE->input->get_post('date_range') != '') ? $this->EE->input->get_post('date_range') : FALSE;
		$keyword = ($this->EE->input->get_post('k_search') && $this->EE->input->get_post('k_search') != '') ? $this->EE->input->get_post('k_search') : FALSE;
		
		$sales_range = ($this->EE->input->get_post('sales_range') && $this->EE->input->get_post('sales_range') != '') ? $this->EE->input->get_post('sales_range') : FALSE;
		
		
		$perpage = ($this->EE->input->get_post('perpage')) ? $this->EE->input->get_post('perpage') : $this->settings['orders_list_limit'];
						

		// Note- we pipeline the js, so pull more data than are displayed on the page		
		$perpage = $this->EE->input->get_post('iDisplayLength');
		$offset = ($this->EE->input->get_post('iDisplayStart')) ? $this->EE->input->get_post('iDisplayStart') : 0; // Display start point
		$sEcho = $this->EE->input->get_post('sEcho');

		
		/* Ordering */
		$order = array();
		
		if ( isset($_GET['iSortCol_0']))
		{
			for ( $i=0; $i < $_GET['iSortingCols']; $i++ )
			{
				//$order[$col_map[$_GET['iSortCol_'.$i]]] = $_GET['sSortDir_'.$i];
				$order = $col_map[$_GET['iSortCol_'.$i]].' '.$_GET['sSortDir_'.$i];
			}
		}
		
		$where .= $this->EE->ct_admin_orders->_build_filter_where($keyword, $date_range, $status);
		$where .= $this->_build_filter_where($sales_range);
		
		$total = $this->get_total_customers();
		$j_response['sEcho'] = $sEcho;
		$j_response['iTotalRecords'] = $total;
		$j_response['iTotalDisplayRecords'] = $this->get_total_customers($where);;

		$tdata = array();
		$i = 0;

		$data = $this->get_customers($perpage, $offset, $order, $where);
		
		// Note- empty string added because otherwise it will throw a js error
		foreach ($data as $customer)
		{
					
			$m[] = '<a href="'.$url_base.'customer_view'.AMP.'email='.$customer['email'].'">'.$customer['first_name'].' '.$customer['last_name'].'</a>';
			$m[] = '<a href="mailto:'.$customer['email'].'">'.$customer['email'].'</a>';
			$m[] = m62_format_money($customer['total_sales']);
			$m[] = $customer['total_orders'];
			$m[] = m62_convert_timestamp($customer['last_order']);
			//$m[] = '<input class="toggle" id="edit_box_'.$customer['email'].'" type="checkbox" name="toggle[]" value="'.$customer['email'].'" />';		

			$tdata[$i] = $m;
			$i++;
			unset($m);
		}		

		$j_response['aaData'] = $tdata;	
		return $this->EE->javascript->generate_json($j_response, TRUE);	
	}
	
	public function _build_filter_where($sales_range)
	{
		$where = '';
		if($sales_range)
		{
			$parts = explode("-", $sales_range);
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
}