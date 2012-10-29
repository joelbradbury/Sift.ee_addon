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
 * CT Admin - Settings Model
 *
 * Control Panel class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/models/ct_admin_settings_model.php
 */
class Ct_admin_settings_model extends CI_Model
{
	/**
	 * Name of the settings table
	 * @var string
	 */
	private $_table = 'ct_admin_settings';
	
	/**
	 * The default settings to use if none are found
	 * @var array
	 */
	public $_defaults = array(
						'allowed_access_levels' => '',
						'orders_list_limit' => 10,
						'cust_list_limit' => 10,
						'order_graph_limit' => 14,
						'license_number' => '',
						'export_date_format' => '%M %d, %Y, %H:%i:%s%A',
						'ct_date_format' => '%M %d, %Y, %H:%i:%s%A',
						'graph_date_format' => '%M %d',
						'success_statuses' => array(),
						'disable_accordions' => FALSE,
						'packingslip_template_url' => '',
						'invoice_template_url' => '',
						'country_report_data' => 'country_field',
						'inventory_report_enable' => '1'
	);
	
	/**
	 * The key names for values that should be serialized. 
	 * @var array
	 */
	private $_serialized = array(
						'cron_notify_emails'
	);
	
	/**
	 * Which fields should be encrypted before storage
	 * @var array
	 */
	private $_encrypted = array(
						'ftp_username',
						'ftp_password',
						's3_access_key',
						's3_secret_key'
	);	
	
	public function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Adds a setting to the databse
	 * @param string $setting
	 */
	public function add_setting($setting)
	{
		$data = array(
		   'setting_key' => $setting
		);
		
		return $this->db->insert($this->_table, $data); 
	}	
	
	public function get_settings()
	{
		$this->db->select('setting_key, setting_value, `serialized`');
		$query = $this->db->get($this->_table);	
		$_settings = $query->result_array();
		$settings = array();	
		foreach($_settings AS $setting)
		{
			$settings[$setting['setting_key']] = ($setting['serialized'] == '1' ? unserialize($setting['setting_value']) : $setting['setting_value']);
		}
		
		//now check to make sure they're all there and set default values if not
		foreach ($this->_defaults as $key => $value)
		{
			//setup the override check
			if(isset($this->config->config['ct_admin'][$key]))
			{
				$settings[$key] = $this->config->config['ct_admin'][$key];
			}
			
			//normal default check				
			if(!isset($settings[$key]))
			{
				$settings[$key] = $value;
			}
		}		

		return $settings;
	}
	
	/**
	 * Returns the value straigt from the database
	 * @param string $setting
	 */
	public function get_setting($setting)
	{
		return $this->db->get_where($this->_table, array('setting_key' => $setting))->result_array();
	}	
	
	public function update_settings(array $data)
	{
		$this->load->library('encrypt');
		foreach($data AS $key => $value)
		{
			
			if(in_array($key, $this->_serialized))
			{
				$value = explode("\n", $value);
				
				//hack to remove bad email addresses from list
				if($key == 'cron_notify_emails')
				{
					$temp = array();
					foreach($value AS $email)
					{
						if(filter_var($email, FILTER_VALIDATE_EMAIL))
						{
							$temp[] = $email;
						}						
					}
					$value = $temp;
				}				
			}
			
			if(in_array($key, $this->_encrypted) && $value != '')
			{
				$value = $this->encrypt->encode($value);
			}
			
			$this->update_setting($key, $value);
		}
		
		return TRUE;
	}
	
	/**
	 * Updates the value of a setting
	 * @param string $key
	 * @param string $value
	 */
	public function update_setting($key, $value)
	{
		if(!$this->_check_setting($key))
		{
			return FALSE;
		}

		$data = array();
		if(is_array($value))
		{
			$value = serialize($value);
			$data['serialized '] = '1';
		}
		
		$data['setting_value'] = $value;
		$this->db->where('setting_key', $key);
		$this->db->update($this->_table, $data);
		
	}

	/**
	 * Verifies that a submitted setting is valid and exists. If it's valid but doesn't exist it is created.
	 * @param string $setting
	 */
	private function _check_setting($setting)
	{
		if(array_key_exists($setting, $this->_defaults))
		{
			if(!$this->get_setting($setting))
			{
				$this->add_setting($setting);
			}
			
			return TRUE;
		}		
	}	
	
	public function get_channel_statuses()
	{
		$channel_id = $this->order_model->store->config('orders_channel');
		$this->db->select('group_title , group_id')->where('group_id != 1');
		$query = $this->db->get('member_groups');	
		$_groups = $query->result_array();	
		$groups = array();
		$groups[''] = '';
		foreach($_groups AS $group)
		{
			$groups[$group['group_id']] = $group['group_title'];
		}
		return $groups;
	}
	
}