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
 * CT Admin - Helper Functions
 *
 * Helper Functions
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/helpers/utilities_helper.php
 */

if ( ! function_exists('m62_format_number'))
{
	/**
	 * Format Number
	 * Formats a number according to how CartThrob would do things
	 * @param string $number
	 */
	function m62_format_number($number)
	{
		$EE =& get_instance();
		$number = $EE->order_model->store->config('number_format_defaults_prefix');
		return $number;
	}
	
	/**
	 * Format Money
	 * Formats money strings according to how CartThrob settings
	 * @param string $number
	 */
	function m62_format_money($number)
	{
		$EE =& get_instance();
		$number = $EE->order_model->store->config('number_format_defaults_prefix').
				  number_format(
				  		$number, 
				  		$EE->order_model->store->config('number_format_defaults_decimals'),
				  		$EE->order_model->store->config('number_format_defaults_dec_point'),
				  		$EE->order_model->store->config('number_format_defaults_thousands_sep')
				  	);
		return $number;
	}

	/**
	 * Timestamp Format
	 * Wrapper that takes a string and converts it according to CT Admin settings
	 * @param string $date
	 * @param string $format
	 */
	function m62_convert_timestamp($date, $format = FALSE)
	{
		$EE =& get_instance();
		$EE->load->helper('date');
		if(!$format)
		{
			$format = $EE->ct_admin_lib->settings['ct_date_format'];
		}
		
		return mdate($format, $date);		
	}
	
	/**
	 * Returns the status color based on $status
	 * @param string $status
	 * @param array $statuses
	 * @return boolean|array
	 */
	function m62_status_color($status, array $statuses = array())
	{
		if(!is_array($statuses))
		{
			return FALSE;
		}

		foreach($statuses AS $color)
		{
			if($status == $color['status'])
				return $color['highlight'];
		}
	}
	
	function m62_country_code($code)
	{
		include APPPATH .'config/countries.php';
		if(isset($countries[$code]))
		{
			return $countries[$code];
		}
		else
		{
			return $code;
		}
	}
	
	function m62_get_invoice_url($entry_id, $packingslip = FALSE)
	{
		$EE =& get_instance();
		return $EE->ct_admin_lib->get_invoice_url($entry_id, $packingslip);
	}
}