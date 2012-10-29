<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

abstract class Api_cartthrob_plugins
{
	protected $paths = array();
	protected $plugins = array();
	protected $plugin;
	
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->library('cartthrob_loader');
		$this->EE->cartthrob_loader->setup($this);
	}
	
	public function set_plugin($plugin)
	{
		if ($plugin)
		{
			$this->plugin = $plugin;
			
			if ( ! isset($this->plugins[$this->plugin]))
			{
				$this->plugins[$this->plugin] = $this->cartthrob->create_child($this->cartthrob, $this->plugin);
			}
		}
		
		return $this;
	}
	
	public function plugin()
	{
		if ( ! $this->plugin || ! isset($this->plugins[$this->plugin]))
		{
			return FALSE;
		}
		
		return $this->plugins[$this->plugin];
	}
}