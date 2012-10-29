<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

abstract class Cartthrob_shipping extends Cartthrob_child
{
	public $title = '';
	public $note = '';
	public $overview = '';
	public $html = '';
	public $settings = array();
	public $required_fields = array();
	
	public function initialize()
	{
		return $this;
	}
	
	public function plugin_settings($key, $default = FALSE)
	{
		$settings = $this->core->store->config(get_class($this).'_settings');
		
		if ($key === FALSE)
		{
			return ($settings) ? $settings : $default;
		}
		
		return (isset($settings[$key])) ? $settings[$key] : $default;
	}
	
	abstract public function get_shipping();
	
	public function get_thresholds()
	{
		$thresholds = array();
		
		foreach ($this->plugin_settings('thresholds', array()) as $threshold)
		{
			$thresholds[$threshold['threshold']] = $threshold['rate'];
		}
		
		return $thresholds;
	}
	
	public function threshold($number, $thresholds)
	{
		ksort($thresholds);
		
		$rate = FALSE;
	
		foreach ($thresholds as $threshold => $threshold_rate)
		{
			if ($number > $threshold)
			{
				continue;
			}
			
			$rate = $threshold_rate;
			
			break;
		}
	
		if ($rate === FALSE)
		{
			$rate = end($thresholds);
		}
		
		return $rate;
	}
}