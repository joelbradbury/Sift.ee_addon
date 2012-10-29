<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

abstract class Cartthrob_core
{
	public $cart;
	public $store;
	private $cache;
	public $hooks;
	private $lang = array();
	private $errors = array();
	public $cart_defaults = array();
	public $item_defaults = array();
	public $product_defaults = array();
	public $customer_info_defaults = array();
	
	public static $_drivers = array('core', 'payment');
	public static $_plugins = array('shipping', 'discount', 'price', 'tax');
	public static $_plugin_paths = array();
	
	/* inherited methods */
	
	public function cache_pop($key)
	{
		$data = $this->cache($key);
		
		$this->clear_cache($key);
		
		return $data;
	}
	
	public function set_error($error)
	{
		$this->errors[] = $error;
		return $this;
	}
	
	public function set_errors($errors)
	{
		$this->errors = $errors;
		return $this;
	}
	
	public function errors()
	{
		return $this->errors;
	}
	
	public function clear_errors()
	{
		$this->errors = array();
		return $this;
	}
	
	public function lang($key)
	{
		return (isset($this->lang[$key])) ? $this->lang[$key] : $key;
	}
	
	public function cache($key)
	{
		if (is_array($key) && $key)
		{
			$cache =& $this->cache;
			
			foreach ($key as $value)
			{
				if ( ! isset($cache[$value]))
				{
					return FALSE;
				}
				
				$cache = $cache[$value];
			}
			
			return $cache;
		}
		
		return (isset($this->cache[$key])) ? $this->cache[$key] : FALSE;
	}
	
	public function set_cache($key, $value)
	{
		if ( ! is_array($key))
		{
			$key = array($key);
		}
		
		$cache =& $this->cache;
		
		foreach ($key as $k)
		{
			if ( ! isset($cache[$k]))
			{
				$cache[$k] = NULL;
			}
			
			$cache =& $cache[$k];
		}
		
		$cache = $value;
		
		return $this;
	}
	
	public function clear_cache($key = FALSE)
	{
		if ($key === FALSE)
		{
			$this->cache = array();
		}
		else if (is_array($key) && count($key) > 1)
		{
			$cache =& $this->cache;
			
			for ($i = 0; $i < count($key) - 1; $i++)
			{
				if ( ! isset($cache[$key[$i]]))
				{
					return;
				}
				
				$cache =& $cache[$key[$i]];
			}
			
			unset($cache[end($key)]);
		}
		else
		{
			unset($this->cache[$key]);
		}
	}
	
	/* static methods */
	
	public static function instance($driver, $params = array())
	{
		if (empty($driver))
		{
			Cartthrob_core::core_error('No driver specified.');
		}

		spl_autoload_register('Cartthrob_core::autoload');
		
		$driver = 'Cartthrob_core_'.$driver;
		
		$instance = new $driver($params);
		
		
		//the sequence is important here!
		$instance->hooks = Cartthrob_core::create_child($instance, 'hooks', (isset($params['hooks'])) ? $params['hooks'] : $instance->get_hooks());
		$instance->store = Cartthrob_core::create_child($instance, 'store', (isset($params['settings'])) ? $params['settings'] : $instance->get_settings());
		$instance->cart = Cartthrob_core::create_child($instance, 'cart', (isset($params['cart'])) ? $params['cart'] : $instance->get_cart());

		spl_autoload_unregister('Cartthrob_core::autoload');
		
		return $instance;
	}
	
	public static function add_plugin_path($type, $path)
	{
		if ( ! isset(self::$_plugin_paths[$type]) || ! is_array(self::$_plugin_paths[$type]))
		{
			self::$_plugin_paths[$type] = array();
		}
		
		if ( ! in_array($path, self::$_plugin_paths[$type]))
		{
			self::$_plugin_paths[$type][] = $path;
		}
	}
	
	public static function create_child(Cartthrob_core $core, $class, $params = array(), $defaults = array())
	{
		spl_autoload_register('Cartthrob_core::autoload');
		
		//$child = self::create_object($name, array(), $path);
		$class = 'Cartthrob_'.Cartthrob_core::get_class($class);
		
		$child = new $class;
		
		$child->set_core($core);
		
		$child->initialize($params, $defaults);

		spl_autoload_unregister('Cartthrob_core::autoload');
		
		return $child;
	}
	
	public static function get_class($class)
	{
		if (is_object($class))
		{
			$class = get_class($class);
		}
		
		if (strpos($class, 'Cartthrob_') === 0)
		{
			$class = substr($class, 10);
		}
		
		return $class;
	}
	
	public static function autoload($class)
	{
		if (strpos($class, 'Cartthrob_') !== 0)
		{
			return;
		}
		
		$class = Cartthrob_core::get_class($class);
		
		//grab first "node" of class name
		$parts = explode('_', $class);
		$type = current($parts);
		
		$class = 'Cartthrob_'.$class;
		
		$paths = array(CARTTHROB_CORE_PATH."Cartthrob_{$type}.php");
		
		if (in_array($type, Cartthrob_core::$_drivers))
		{
			$paths[] = CARTTHROB_DRIVER_PATH."{$type}/{$class}.php";
		}
		else if (in_array($type, Cartthrob_core::$_plugins))
		{
			$path_added = FALSE;
			
			if (isset(self::$_plugin_paths[$type]) && is_array(self::$_plugin_paths[$type]))
			{
				foreach (self::$_plugin_paths[$type] as $path)
				{
					if (file_exists($path."{$class}.php"))
					{
						$path_added = TRUE;
						$paths[] = $path."{$class}.php";
						break;
					}
				}
			}
			
			if ( ! $path_added)
			{
				$paths[] = CARTTHROB_PLUGIN_PATH."{$type}/{$class}.php";
			}
		}
		else if (count($parts) > 1)
		{
			$paths[] = CARTTHROB_CORE_PATH.'Cartthrob_child.php';
			$paths[] = CARTTHROB_CORE_PATH."{$type}/{$class}.php";
		}
		
		foreach ($paths as $path)
		{
			if ( ! file_exists($path))
			{
				Cartthrob_core::core_error(sprintf('File %s not found.', basename($path)));
			}
			
			require_once $path;
		}
		
		if ( ! class_exists($class))
		{
			Cartthrob_core::core_error(sprintf('Class %s not found.', $class));
		}
	}
	
	protected static function core_error($error)
	{
		trigger_error($error);
		//exit($error);
	}
	
	/* utilities */
	public function sanitize_number($number = NULL, $allow_negative = FALSE, $integer = FALSE)
	{
		if (is_int($number))
		{
			return $number;
		}
		
		if (is_float($number))
		{
			if ($integer)
			{
				//it IS an integer but is cast as float
				if ((int) $number === $number)
				{
					return $number;
				}
				else
				{
					$number = (string) $number;
				}
			}
			else
			{
				return $number;
			}
		}

		if ( ! $number || ! is_string($number))
		{
			return 0;
		}
		
		$regex = ($integer) ? '/[^\d]/' : '/[^\d\.]/';
		
		if ($integer)
		{
			$number = floor($number);
		}

		if (substr($number, 0, 1) === '-')
		{
			$number = preg_replace($regex, '', substr($number, 1));
			
			if ($allow_negative)
			{
				$number = '-'.$number;
			}
		}
		else
		{
			$number = preg_replace($regex, '', $number);
		}

		return floatval($number);
	}
	
	public function sanitize_integer($number = NULL, $allow_negative = FALSE)
	{
		return (int) $this->sanitize_number($number, $allow_negative, TRUE);
	}
	
	public function round($number)
	{
		$number = $this->sanitize_number($number);
		
		switch ($this->store->config('rounding_default'))
		{
			case 'swedish':
				return number_format(round(20*$number)/20, 2, '.', '') ;
			case 'new_zealand': 
				return number_format(round(10*$number)/10, 2, '.', '') ;
			default: 
				return number_format($number, $this->store->config('number_format_defaults_decimals'), '.', '');
		}
		
		return number_format($number, $this->store->config('number_format_defaults_decimals'), '.', '');
	}
	
	public function log($msg)
	{
	}
	
	public function array_merge($a, $b)
	{
		foreach ($b as $key => $value)
		{
			if (is_array($value) && isset($a[$key]))
			{
				$a[$key] = $this->array_merge($a[$key], $value);
			}
			else
			{
				$a[$key] = $value;
			}
		}
		
		return $a;
	}
	
	/* abstract methods */
	
	abstract public function get_cart();
	abstract public function get_hooks();
	abstract public function get_settings();
	abstract public function get_product($product_id);
	abstract public function get_categories();
	abstract public function save_cart();
	abstract public function validate_coupon_code($coupon_code);
}