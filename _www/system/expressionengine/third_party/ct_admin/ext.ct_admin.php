<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.3.3
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Ext Class
 *
 * Extension class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/ext.ct_admin.php
 */
class Ct_admin_ext 
{	
	public $settings = array();
	
	public $description	= 'Extension for modifying how CartThrob works';
	
	public $settings_exist	= 'y';
	
	public $docs_url = 'http://mithra62.com/docs/view/ct-admin-installation'; 
	
	public $required_by = array('module');	
		
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct()
	{
		$this->EE =& get_instance();
		
		include PATH_THIRD.'ct_admin/config'.EXT;
		
		$this->version = $config['version'];
		$this->name = $config['name'];		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/'); 
		$this->EE->load->library('cartthrob_loader');
		$this->EE->cartthrob_loader->setup($this);
				
		$this->EE->load->library('email');	
		$this->EE->lang->loadfile('ct_admin');
		
		if ($this->EE->input->post('clear_cart'))
		{
			$this->delete_saved_cart();
		}
	}
	
	public function settings_form()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ct_admin'.AMP.'method=settings');
	}
	
	public function update_saved_cart()
	{
		$this->EE->load->model('ct_admin_carts_model', 'ct_admin_carts', TRUE);
		$check = $this->EE->ct_admin_carts->get_items(array('cart_id' => $this->EE->ct_admin_carts->cart_id));
		$removed = array();
		if(isset($_POST['delete']) && is_array($_POST['delete']))
		{
			foreach($_POST['delete'] AS $key => $value)
			{
				$removed[] = $key;
				$this->EE->ct_admin_carts->delete_items(array('cart_id' => $this->EE->ct_admin_carts->cart_id, 'row_id' => $key));
			}
		}
		
		
		foreach ($this->cart->items() as $row_id => $item)
		{	
			if(in_array($row_id, $removed))	
			{
				continue;
			}
			
			$data = array();
			foreach ($_POST as $key => $value)
			{
				if (!isset($value[$row_id]))
				{
					continue;
				}
				
				if(in_array($key, $item->default_keys()))
				{
					$data[$key] = $this->EE->security->xss_clean($value[$row_id]);
					if(is_array($data[$key]))
					{
						$data[$key] = base64_encode(serialize($data[$key]));
					}
				}
			}
			
			$where = array('cart_id' => $this->EE->ct_admin_carts->cart_id, 'row_id' => $row_id);
			$this->EE->ct_admin_carts->update_item($data, $where, FALSE);
		}
	}
	
	public function save_add_to_cart($item)
	{
		//fix for updating individual items issue within CT
		if(!$item)
		{
			return;
		}
		$this->EE->load->model('ct_admin_carts_model', 'ct_admin_carts', TRUE);
		$data = $item->to_array();
		if(isset($this->EE->session->userdata['member_id']) && $this->EE->session->userdata['member_id'] >= '1')
		{
			$data['member_id'] = $this->EE->session->userdata['member_id'];
		}
		$this->EE->ct_admin_carts->add_item($data);
	}

	public function delete_saved_cart()
	{
		$this->EE->load->model('ct_admin_carts_model', 'ct_admin_carts', TRUE);
		$this->EE->ct_admin_carts->delete_items(array('cart_id' => $this->EE->ct_admin_carts->cart_id));
	}

	public function add_cartthrob_settings_nav($nav)
	{
		$nav = array('ct_admin' => array(''));
		return $nav;
	}
	
	public function add_cartthrob_settings_views($settings_views)
	{
		return array('ct_admin' => 
			array(
				'path' => 'settings', 
				'title' => 'ct_admin_settings'
			)
		);
	}
	
	/**
	 * Adds a new parameter to add_to_cart tags to limit the number of products a cart can have on an item
	 */
	public function cart_max_quantity()
	{
		if(!isset($this->EE->TMPL))
		{
			return;
		}
		
		$return = $this->EE->TMPL->fetch_param('return');
		$max_quantity = $this->EE->TMPL->fetch_param('max_quantity');
		$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		if($max_quantity >= '1' && $entry_id >= '1')
		{
			$cart = $this->EE->cartthrob->cart->filter_items(array('entry_id' => $entry_id));
			if(isset($cart['0']))
			{
				$in_cart = $cart['0']->quantity();	
				if($max_quantity <= $in_cart )
				{
					$this->EE->functions->redirect($this->EE->functions->create_url($return));
					exit;
				}
			}
		}
	}
	
	public function require_valid_coupon()
	{
		$this->EE->load->library('ct_admin_coupons');
		$coupon_code = $this->EE->input->post('coupon_code', TRUE);
		if(!$this->EE->ct_admin_coupons->is_valid_code($coupon_code))
		{
			show_error('Please enter a valid coupon code');
		}
	}
	
	public function activate_extension() 
	{
		return TRUE;

	}
	
	public function update_extension($current = '')
	{
		return TRUE;
	}

	public function disable_extension()
	{
		return TRUE;

	}
}