<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cartthrob_loader
{
	private $setup = array();
	
	public function __construct($params = array())
	{
		$this->EE =& get_instance();
		
		if ( ! isset($this->EE->cartthrob))
		{
			include_once PATH_THIRD.'cartthrob/cartthrob/Cartthrob.php';
			
			$this->EE->cartthrob = Cartthrob_core::instance('ee', $params);
		}
	}
	
	public function setup(&$object)
	{
		if ( ! is_object($object))
		{
			return;
		}
		
		if ( ! in_array($object, $this->setup))
		{
			$this->setup[] =& $object;
		}
		
		$object->cartthrob =& $this->EE->cartthrob;
		$object->cart =& $this->EE->cartthrob->cart;
		$object->store =& $this->EE->cartthrob->store;
	}
	
	public function setup_all($which = array())
	{
		if ( ! is_array($which))
		{
			$which = func_get_args();
		}
		
		foreach ($this->setup as &$object)
		{
			if ( ! $which || in_array('core', $which))
			{
				$object->cartthrob =& $this->EE->cartthrob;
			}
			
			if ( ! $which || in_array('cart', $which))
			{
				$object->cart =& $this->EE->cartthrob->cart;
			}
			
			if ( ! $which || in_array('store', $which))
			{
				$object->store =& $this->EE->cartthrob->store;
			}
		}
	}
}