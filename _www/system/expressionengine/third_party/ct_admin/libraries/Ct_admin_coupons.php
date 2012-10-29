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
class Ct_admin_coupons
{	
	public function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->model('ct_admin_settings_model', 'ct_admin_settings', TRUE);
		$this->EE->load->model('order_model', 'order_model');
		$this->EE->load->library('ct_admin_lib');
		$this->channel_id = $this->EE->order_model->store->config('coupon_code_channel');
		$this->settings = $this->EE->ct_admin_lib->get_settings();
		$this->config = $this->EE->order_model->store->config();
		$this->dbprefix = $this->EE->db->dbprefix;
	}
	
	public function is_valid_code($code)
	{
		if($code == '') //no code was sent so just allow it
		{
			return TRUE;
		}
		
		$data = $this->EE->db->select(array('entry_id'))
				->from('channel_titles')
				->where(array('channel_id' => $this->channel_id, 'title' => $code))
				->get();		
		if($data->num_rows == '1')
		{
			return TRUE;
		}
	}
	
}