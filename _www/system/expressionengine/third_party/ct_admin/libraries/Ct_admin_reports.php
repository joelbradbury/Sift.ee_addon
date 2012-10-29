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
 * CT Admin - Reports
 *
 * Contains most wrapper methods for reporting
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/libraries/ct_admin_reports.php
 */
class Ct_admin_reports
{	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->library('ct_admin_cache');
		$this->channel_id = $this->EE->order_model->store->config('orders_channel');
		$this->settings = $this->EE->ct_admin_lib->get_settings();
		$this->config = $this->EE->order_model->store->config();
		$this->dbprefix = $this->EE->db->dbprefix;		
	}
	
	public function product_sum_report($where = FALSE)
	{
		$sql = "SELECT SUM( price ) AS total_price, ecoi.title, price, SUM( quantity ) AS total_quantity, ecoi.entry_id, ecoi.order_id, ect.url_title, ecd2.channel_id AS product_channel_id 
				FROM ".$this->dbprefix."cartthrob_order_items ecoi, ".$this->dbprefix."channel_titles ect, exp_channel_data ecd2
				WHERE ect.entry_id = ecoi.order_id
				AND ecd2.entry_id = ecoi.entry_id
				$where
				GROUP BY ecoi.entry_id, price
		";

		return $this->EE->db->query($sql)->result_array();
	}
	
	public function get_totals($where = FALSE)
	{		
		$total_field = 'field_id_'.$this->EE->order_model->store->config('orders_total_field');	
		$order_channel = $this->EE->order_model->store->config('orders_channel');
		$sql = "SELECT SUM($total_field) AS total 
				FROM ".$this->dbprefix."channel_titles ect, exp_channel_data ecd 
				WHERE ecd.entry_id = ect.entry_id AND ect.channel_id = '$order_channel' $where
				GROUP BY ect.channel_id
		";
		
		$data = $this->EE->db->query($sql)->row();
		if(isset($data->total))
		{
			return $data->total;
		}
		else
		{
			return '0';
		}
	}

	public function get_order_countries($where = FALSE)
	{
		$col = $this->EE->order_model->store->config('orders_billing_country');
		if($col == '')
		{
			return;
		}		
		$country_field = 'field_id_'.$col;
		$sql = "SELECT COUNT( $country_field ) AS total_orders, $country_field AS country
		FROM ".$this->dbprefix."channel_data ecd, ".$this->dbprefix."channel_titles ect
		WHERE ect.entry_id = ecd.entry_id
		AND $country_field != ''
		$where
		GROUP BY $country_field
		";

		$data = $this->EE->db->query($sql)->result_array();	
		$return = array();
		if(count($data) >= '1')	
		{
			foreach($data AS $item)
			{
				$return[$item['country']] = $item['total_orders'];
			}
		}
		
		return $return;
	}
	
	public function get_all_totals($where = FALSE)
	{
		$total_sum = $tax_sum = $shipping_sum = $discount_sum = FALSE;
		if($this->EE->order_model->store->config('orders_total_field') != '')
		{
			$total_field = 'field_id_'.$this->EE->order_model->store->config('orders_total_field');	
			$total_sum = ", SUM($total_field) AS total";
		}
		
		if($this->EE->order_model->store->config('orders_tax_field') != '')
		{
			$tax_field = 'field_id_'.$this->EE->order_model->store->config('orders_tax_field');
			$tax_sum = ", SUM($tax_field) AS tax";
		}
		
		if($this->EE->order_model->store->config('orders_shipping_field') != '')
		{
			$shipping_field = 'field_id_'.$this->EE->order_model->store->config('orders_shipping_field');
			$shipping_sum = ", SUM($shipping_field) AS shipping";
		}
		
		if($this->EE->order_model->store->config('orders_discount_field') != '')
		{
			$discount_field = 'field_id_'.$this->EE->order_model->store->config('orders_discount_field');
			$discount_sum = ", SUM($discount_field) AS discount";
		}
		
		$order_channel = $this->EE->order_model->store->config('orders_channel');	;
		$sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(entry_date), \"%M %Y\") AS order_range $total_sum $tax_sum $shipping_sum $discount_sum, entry_date
				FROM ".$this->dbprefix."channel_titles ect, exp_channel_data ecd 
				WHERE ecd.entry_id = ect.entry_id AND ect.channel_id = '$order_channel' $where
				GROUP BY order_range ORDER BY entry_date DESC
		";
		
		$data = $this->EE->db->query($sql)->result_array();
		foreach($data AS $key => $value)
		{
			$parts = explode(" ", $value['order_range']);
			$value['order_range'] = lang($parts['0']).' '.$parts['1'];
			$data[$key]['name'] = $value['order_range'];
			if(!isset($data[$key]['discount']))
			{
				$data[$key]['discount'] = '0';
			}
			
			if(!isset($data[$key]['shipping']))
			{
				$data[$key]['shipping'] = '0';
			}

			if(!isset($data[$key]['tax']))
			{
				$data[$key]['tax'] = '0';
			}

			if(!isset($data[$key]['total']))
			{
				$data[$key]['total'] = '0';
			}			
		}

		return $data;
	}
	
	public function get_chart_order_history($range = 14, $where = FALSE)
	{
		$start_date = date('Y-m-d', mktime(0, 0, 0, date("m")  , (date("d")-$range), date("Y")));
		$orders_total_key = $this->config['orders_total_field'];
		$orders_sub_total_key = $this->EE->order_model->store->config('orders_subtotal_field');
		if($orders_total_key == '' || $orders_sub_total_key == '')
		{
			return FALSE;
		}
		
		$sql = "SELECT entry_date, SUM(field_id_$orders_total_key) AS total, SUM(field_id_$orders_sub_total_key) AS subtotal,
				CONCAT_WS('-', year, month, day) AS order_date
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE ect.entry_id = ecd.entry_id 
				AND entry_date >= '".strtotime($start_date)."'
				AND entry_date <= '".time()."'
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				GROUP BY order_date
		";
				
		$data = $this->EE->db->query($sql)->result_array();
		
		//wrap up the data so it's ready for the chart
		$total_times = 0;
		$arr = array();
		for($i=0; $i<=$range;$i++)
		{
			$math = $range-$i;
			$date = date('Y-m-d', mktime(0, 0, 0, date("m")  , date("d")-$math, date("Y"))); 
			$found = false;
			foreach($data AS $day)
			{
				if($date == $day['order_date'])
				{
					$found = true;
					$arr[$day['order_date']] = $day;
					break;
				}
			}
			
			if(!$found)
			{
				$arr[$date] = array('order_date' => $date, 'total' => '0', 'subtotal' => '0');
			}
		}

		return $arr;
	}
	
	public function get_chart_order_date($range = 14, $where = FALSE, $month = FALSE, $year = FALSE, $day = FALSE)
	{
		if(!$month)
		{
			$month = date("n");
		}
		
		if(!$year)
		{
			$year = date('Y');
		}
		
		if(!$day)
		{
			$day = date('j');
		}		
		
		$orders_total_key = $this->config['orders_total_field'];
		$orders_sub_total_key = $this->EE->order_model->store->config('orders_subtotal_field');
		if($orders_total_key == '' || $orders_sub_total_key == '')
		{
			return FALSE;
		}
		
		$sql = "SELECT entry_date, SUM(field_id_$orders_total_key) AS total, SUM(field_id_$orders_sub_total_key) AS subtotal,
				CONCAT_WS('-', year, month, day) AS order_date
				FROM ".$this->dbprefix."channel_titles ect, ".$this->dbprefix."channel_data ecd
				WHERE ect.entry_id = ecd.entry_id 
				AND ecd.channel_id = '".$this->channel_id."'
				$where
				GROUP BY order_date
				ORDER BY order_date DESC
		";

		$data = $this->EE->db->query($sql)->result_array();
		
		
		//wrap up the data so it's ready for the chart
		$total_times = 0;
		$arr = array();
		for($i=0; $i<$range;$i++)
		{
			$month = (int)$month;
			$day = (int)$day;
			$new_day = ($day+$i);
			$date = date('Y-m-d', mktime(0, 0, 0, $month, $new_day, $year)); 
			$found = false;
			foreach($data AS $day)
			{
				if($date == $day['order_date'])
				{
					$found = true;
					$arr[$day['order_date']] = $day;
					break;
				}
			}
			
			if(!$found)
			{
				$arr[$date] = array('order_date' => $date, 'total' => '0', 'subtotal' => '0');
			}
		}
		
		return $arr;
	}	

	public function low_stock_report()
	{
		$fields = $this->EE->order_model->store->config('product_channel_fields');
		$key = FALSE;
		if(is_array($fields) && count($fields) == '1')
		{
			$key = FALSE;
			foreach($fields AS $field)
			{
				if(isset($field['inventory']) && is_numeric($field['inventory']))
				{
					$key = $field['inventory'];
					break;
				}
			}
			
			if($key)
			{
				$product_channels = $this->EE->order_model->store->config('product_channels');
				if(isset($product_channels) && is_array($product_channels))
				{
					$return = array();
					foreach($product_channels AS $channel)
					{
						$sql = "SELECT title, field_id_$key AS inventory, ect.entry_id FROM `".$this->dbprefix."channel_titles` ect, ".$this->dbprefix."channel_data ecd 
								WHERE 
								ect.channel_id = '$channel' 
								AND ect.entry_id = ecd.entry_id
								AND field_id_$key != ''
						";
						
						$return[] = $this->EE->db->query($sql)->result_array(); 
					}
					return $return;
				}
			}
		}		
	}
	
	public function get_ip_to_country()
	{
		$data = $this->EE->ct_admin_cache->read_cache(md5(__METHOD__));
		if (!$data)
		{
			$col = $this->EE->order_model->store->config('orders_customer_ip_address');
			if($col == '')
			{
				return;
			}
			$ip_col = 'field_id_'.$col.' AS ip_addy';
			$this->EE->db->select($ip_col);
			$this->EE->db->where('channel_id', $this->channel_id);
			$query = $this->EE->db->get('channel_data')->result_array();
			$countries = array();
			foreach($query AS $ip)
			{
				$query = $this->EE->db->query("SELECT country FROM exp_ip2nation WHERE ip < INET_ATON('".$this->EE->db->escape_str($ip['ip_addy'])."') ORDER BY ip DESC LIMIT 0,1");
				if($query->num_rows == '1')
				{
					$row = $query->row();
					if(!isset($countries[$row->country]))
					{
						$countries[$row->country] = 1;
					}
					else
					{
						$countries[$row->country]++;
					}
				}
			}

			$data = serialize($countries);
			$this->EE->ct_admin_cache->create_cache_file($data, md5(__METHOD__));
		}
		
		return unserialize($data);
		
	}
}