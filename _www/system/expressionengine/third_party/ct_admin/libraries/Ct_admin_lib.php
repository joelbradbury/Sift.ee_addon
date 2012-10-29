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
 * CT Admin - Generic methods
 *
 * Library Class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/libraries/ct_admin_lib.php
 */
class Ct_admin_lib
{
	/**
	 * The default URL to use within the CP
	 */
	private $url_base = FALSE;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->settings = $this->get_settings();
	}
	
	/**
	 * Returns the array needed for the CP menu
	 */
	public function get_right_menu()
	{
		return array(
				'dashboard'		=> $this->url_base.'index',
				'orders'		=> $this->url_base.'orders',
				'customers'		=> $this->url_base.'customers',
				'reports'		=> $this->url_base.'reports',
				'settings'	=> $this->url_base.'settings'
		);
	}	
	
	/**
	 * Wrapper to handle CP URL creation
	 * @param string $method
	 */
	public function _create_url($method)
	{
		return $this->url_base.$method;
	}

	/**
	 * Creates the value for $url_base
	 * @param string $url_base
	 */
	public function set_url_base($url_base)
	{
		$this->url_base = $url_base;
	}
	
	public function perpage_select_options()
	{
		return array(
			   '10' => '10 '.lang('results'),
			   '25' => '25 '.lang('results'),
			   '75' => '75 '.lang('results'),
			   '100' => '100 '.lang('results'),
			   '150' => '150 '.lang('results')
		);		
	}
	
	public function date_select_options()
	{
		return array(
			   '' => lang('date_range'),
			   '1' => lang('past_day'),
			   '7' => lang('past_week'),
			   '31' => lang('past_month'),
			   '182' => lang('past_six_months'),
			   '365' => lang('past_year'),
			   'custom_date' => lang('any_date')
		);				
	}
	
	public function create_pagination($method, $total, $per_page)
	{
		$config = array();
		$config['page_query_string'] = TRUE;
		$config['base_url'] = $this->url_base.'edit_orders_ajax_filter';
		$config['total_rows'] = $total;
		$config['per_page'] = $this->settings['orders_list_limit'];
		$config['page_query_string'] = TRUE;
		$config['full_tag_open'] = '<p id="paginationLinks">';
		$config['full_tag_close'] = '</p>';
		$config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />';
		$config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />';
		$config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />';
		$config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />';

		$this->EE->pagination->initialize($config);
		return $this->EE->pagination->create_links();		
	}
	
	/**
	 * Wrapper that runs all the tests to ensure system stability
	 * @return array;
	 */
	public function error_check()
	{
		$errors = array();
		if($this->settings['license_number'] == '')
		{
			$errors['license_number'] = 'missing_license_number';
		}
		return $errors;
	}	
	
	/**
	 * Returns an array for configuring the EE pagination mechanism 
	 * @param string $method
	 * @param int 	$total_rows
	 * @param int	 $perpage
	 */
	public function pagination_config($method, $total_rows, $perpage)
	{
		// Pass the relevant data to the paginate class
		$config['base_url'] = $this->_create_url($method);
		$config['total_rows'] = $total_rows;
		$config['per_page'] = $perpage;
		$config['page_query_string'] = TRUE;
		$config['query_string_segment'] = 'rownum';
		$config['full_tag_open'] = '<p id="paginationLinks">';
		$config['full_tag_close'] = '</p>';
		$config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />';
		$config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />';
		$config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />';
		$config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />';

		return $config;
	}

	/**
	 * Half ass attempt at license verification.
	 * @param string $license
	 */
	public function valid_license($license)
	{
		//return TRUE; //if you want to disable the check uncomment this line. You should pay me though eric@mithra62.com :) 
		return preg_match("/^([a-z0-9]{8})-([a-z0-9]{4})-([a-z0-9]{4})-([a-z0-9]{4})-([a-z0-9]{12})$/", $license);
	}	
	
	/**
	 * Returns the setting array and caches it if none exists
	 */
	public function get_settings()
	{
		if ( ! isset($this->EE->session->cache[__CLASS__]['settings']))
		{
			$this->EE->session->cache[__CLASS__]['settings'] = $this->EE->ct_admin_settings->get_settings();
		}
	
		return $this->EE->session->cache[__CLASS__]['settings'];
	}	
	
	/**
	 * Forces an array to download as a csv file
	 * @param array $arr
	 * @param bool $keys_as_headers
	 * @param bool $file_name
	 */
	public function downloadArray(array $arr, $keys_as_headers = TRUE, $file_name = FALSE)
	{
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"$file_name\"");
		$cols = '';
		$rows = '';			
		if(is_array($arr) && count($arr) >= 1)
		{
			$rows = array();
			$cols = array_keys($arr['0']);
			foreach($arr AS $key => $value)
			{
				foreach($value AS $k => $v)
				{
					$value[$k] = $this->escape_csv_value($v, "\t");
				}
								
				$rows[] = implode("\t", $value);
			}
						
			echo implode("\t", $cols)."\n";
			echo implode("\n", $rows);
		}
		
		
		exit;

	}	
	
	public function escape_csv_value($value, $delim = ',') 
	{
		$value = str_replace('"', '""', $value);
		if(preg_match('/'.$delim.'/', $value) or preg_match("/\n/", $value) or preg_match('/"/', $value))
		{ 
			return '"'.$value.'"'; 
		} 
		else 
		{
			return $value; 
		}
	}	
	
	public function is_installed_module($module_name)
	{
		$data = $this->EE->db->select('module_name')->from('modules')->like('module_name', $module_name)->get();
		if($data->num_rows == '1')
		{
			return TRUE;
		}
	}
	
	public function get_template_options()
	{
		if ( ! isset($this->EE->session->cache[__CLASS__]['template_options']))
		{
			$query = $this->EE->template_model->get_templates();
			$this->EE->session->cache[__CLASS__]['template_options'][] = '';
			
			if ($query->num_rows() > 0)
			{
				foreach ($query->result() as $template)
				{
					$this->EE->session->cache[__CLASS__]['template_options'][$template->template_id] = $template->group_name.'/'.$template->template_name;
				}
			}			
		}
		
		return $this->EE->session->cache[__CLASS__]['template_options'];
	}
	
	public function get_invoice_url($order_id, $packingslip = FALSE)
	{
		$key = 'invoice_template_url';
		$tail = '';
		if($packingslip)
		{
			$key = 'packingslip_template_url';
			$tail = '&packing_slip=1';
		}
		
		if($this->settings[$key] != '0')
		{ 
			$options = $this->get_template_options();
			
			//clean up the end for slashes
			$url = $this->EE->config->config['site_url'];
			if($url[strlen($url)-1] != '/')
			{
				$url = $url.'/';
			}

			$index_page = $this->EE->config->config['site_index'];
			if($index_page != '/' && $index_page != '')
			{
				if($url[strlen($index_page)-1] != '/')
				{
					$index_page = $index_page.'/';
				}
				$url = $url.$index_page;
			}
			
			if(isset($options[$this->settings[$key]]))
			{
				return $url.$options[$this->settings[$key]].'/'.$order_id;	
			}
		}
		
		return $this->url_base.'order_view&id='.$order_id.'&print_invoice=1'.$tail;
	}
}