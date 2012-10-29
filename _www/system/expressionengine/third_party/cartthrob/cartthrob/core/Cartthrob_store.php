<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_store extends Cartthrob_child
{
	private $config;
	private $products;
	private $plugins;
	
	public function initialize($params = array())
	{
		$this->config = $params;
	}
	
	public function plugin($class)
	{
		if ( ! $class)
		{
			return NULL;
		}
		
		if (isset($this->plugins[$class]))
		{
			return $this->plugins[$class];
		}
		
		return $this->plugins[$class] = Cartthrob_core::create_child($this->core, $class);
	}
	
	public function tax_rate()
	{
		if ($plugin = $this->plugin($this->config('tax_plugin')))
		{
			return $plugin->tax_rate();
		}
		
		return 0;
	}
	
	public function tax_name()
	{
		if ($plugin = $this->plugin($this->config('tax_plugin')))
		{
			return $plugin->tax_name();
		}
		
		return '';
	}
	
	public function config()
	{
		$config = $this->config;
		
		foreach (func_get_args() as $key)
		{
			if (isset($config[$key]))
			{
				$config = $config[$key];
			}
			else
			{
				return FALSE;
			}
		}
		
		return $config;
	}
	
	public function set_config($key, $value = FALSE)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->config[$k] = $v;
			}
		}
		else
		{
			$this->config[$key] = $value;
		}
		
		return $this;
	}
	
	public function override_config($override_config)
	{
		if ( ! is_array($override_config))
		{
			return;
		}
		
		$this->config = $this->core->array_merge($this->config, $override_config);
	}
	
	public function product($product_id)
	{
		if (isset($this->products[$product_id]))
		{
			return $this->products[$product_id];
		}
		
		if ($product = $this->core->get_product($product_id))
		{
			return $this->products[$product_id] = $product;
		}
		
		return FALSE;
	}
}