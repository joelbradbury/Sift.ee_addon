<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cartthrob_ext
{
	public $settings = array();
	public $name = 'CartThrob';
	public $version;
	public $description = 'CartThrob Shopping Cart';
	public $settings_exist = 'y';
	public $docs_url = 'http://cartthrob.com/docs';
	public $required_by = array('module');
	
	private $hooks = array(
		array('member_member_logout'),
		array('member_member_login', 'member_member_login_multi'),
		array('member_member_login', 'member_member_login_single'),
		array('member_member_login', 'cp_member_login'),
		array('cp_menu_array', 'cp_menu_array'),
		array('entry_submission_ready', 'entry_submission_ready'),
		array('publish_form_entry_data', 'publish_form_entry_data'),
		array('safecracker_submit_entry_start', 'safecracker_submit_entry_start'),
		array('safecracker_submit_entry_end', 'safecracker_submit_entry_end'),

	);

	/**
	* Cartthrob_ext
	*/
	public function __construct($settings='')
	{
		$this->EE =& get_instance();
		
		include_once PATH_THIRD.'cartthrob/config.php';
		
		$this->version = CARTTHROB_VERSION;
	}
	
	public function settings_form()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob');
	}
	
	/**
	 * Activates Extension
	 *
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function activate_extension()
	{
		foreach ($this->hooks as $row)
		{
			$this->EE->db->insert(
				'extensions',
				array(
					'class' => 'Cartthrob_ext',
					'method' => $row[0],
					'hook' => ( ! isset($row[1])) ? $row[0] : $row[1],
					'settings' => ( ! isset($row[2])) ? '' : $row[2],
					'priority' => ( ! isset($row[3])) ? 10 : $row[3],
					'version' => $this->version,
					'enabled' => 'y',
				)
			);
		}
		
		return TRUE;
	}
	// END
	
	
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	/**
	 * Updates Extension
	 *
	 * @access public
	 * @param string
	 * @return void|BOOLEAN False if the extension is current
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function update_extension($current='')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		$this->EE->db->update('extensions', array('version' => $this->version), array('class' => __CLASS__));
		
		return TRUE;
	}
	// END
	
	// --------------------------------
	//  Disable Extension
	// --------------------------------
	/**
	 * Disables Extension
	 * 
	 * Deletes mention of this extension from the exp_extensions database table
	 *
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function disable_extension()
	{
		$this->EE->db->delete('extensions', array('class' => __CLASS__));
	}
	// END
	
	// --------------------------------
	//  Settings Function
	// --------------------------------
	/**
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function settings()
	{
	}
	// END
	
	/**
	 * publish_form_entry_data
	 *
	 * @param array
	 * @return array
	 */
	public function publish_form_entry_data($data= array())
	{
		if ( ! isset($this->EE->cartthrob))
		{
			$this->EE->load->library('cartthrob_loader');
		}
		
		if ($this->EE->cartthrob->store->config('save_orders') && $this->EE->cartthrob->store->config('orders_channel'))
		{
				if (!empty($data['entry_id']) && !empty($data['status']))
				{
					if ($data['channel_id'] == $this->EE->cartthrob->store->config('orders_channel')) 
					{
						$this->set_status($data['entry_id'], $data['status']); 
					}
					if (!empty($data['entry_id']) && !empty($data['revision_post']['status']))
					{
						$this->set_status($data['entry_id'], $data['revision_post']['status']); 
					}
				}
			}

		return $data; 
	}
	public function safecracker_submit_entry_start( &$safecracker )
	{
		$data = array(
			'entry_id'		=> $this->EE->input->post('entry_id'),
			'channel_id'	=> $this->EE->input->post('channel_id', TRUE),
			'status'		=> $this->EE->input->post('status', TRUE),
		); 
		if ($this->EE->input->post('entry_id'))
		{
			$original_status =  $safecracker->entry('status');
			$data['revision_post']['status'] = $original_status; 
		}
		
		return $this->publish_form_entry_data($data);
	}
	public function safecracker_submit_entry_end(&$safecracker)
	{
		$data = array(
			'entry_id'		=> $this->EE->input->post('entry_id'),
			'channel_id'	=> $this->EE->input->post('channel_id', TRUE),
			'status'		=> $this->EE->input->post('status', TRUE),
		); 
		if ($this->EE->input->post('entry_id'))
		{
			$original_status =  $safecracker->entry('status');
			$data['revision_post']['status'] = $original_status; 
		}
		return $this->entry_submission_ready($meta = array(), $data, $autosave = FALSE);
		
	}
	private function set_status($entry_id, $status)
	{
		$this->EE->session->cache['cartthrob'][$entry_id]['status'] = $status; 
	}
	private function last_status($entry_id, $status)
	{
		if ($status == NULL) return FALSE; 
		
		if (!empty($this->EE->session->cache['cartthrob'][$entry_id]['status']))
		{
			$last_status = $this->EE->session->cache['cartthrob'][$entry_id]['status'];
			if ($status == 	$last_status)
		 	{
				return $status; 
			}
			else
			{
				return $last_status; 
			}
		}
		else
		{
			return FALSE; 
		}
		
	}
	public function entry_submission_ready( $meta= array(), $data=array(), $autosave)
	{
		if ( ! isset($this->EE->cartthrob))
		{
			$this->EE->load->library('cartthrob_loader');
		}
		
 		if (empty($data['revision_post']['status']))
		{
			return $data; 
		}
		
		$status_start = $this->last_status($data['entry_id'], $data['revision_post']['status']); 
		$data['revision_post']['previous_status'] = $status_start; 
		$this->EE->load->library('cartthrob_emails');
		
		$emails = $this->EE->cartthrob_emails->get_email_for_event($ct_event = NULL, $status_start,  $data['revision_post']['status']); 
		if (!empty($emails))
		{
			foreach ($emails as $email_content)
			{
				if (!empty($email_content['to']) && $email_content['to']  == "{customer_email}")
				{
					if ($this->EE->cartthrob->store->config('orders_customer_email') && !empty($data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_email')]))
					{
						$email_content['to'] = $data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_email')]; 
					}
				}
				if (!empty($email_content['from']) && $email_content['from']  == "{customer_email}")
				{
					if ($this->EE->cartthrob->store->config('orders_customer_email') && !empty($data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_email')]))
					{
						$email_content['from'] = $data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_email')]; 
					}
				}
				if (!empty($email_content['from_name']) && $email_content['from_name']  == "{customer_name}")
				{
					if ($this->EE->cartthrob->store->config('orders_customer_name') && !empty($data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_name')]))
					{
						$email_content['from_name'] = $data['field_id_'.$this->EE->cartthrob->store->config('orders_customer_name')]; 
					}
				}
				$this->EE->cartthrob_emails->send_email($email_content, $data['revision_post']); 
			}
		}
	
		return $data; 
	}
	//deprecated
	public function sessions_end(&$session)
	{
		return;
		
		$this->EE->session = $session;
		
		if ( ! method_exists($this->EE->load, 'get_package_paths') || ! in_array(PATH_THIRD.'cartthrob/', $this->EE->load->get_package_paths()))
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		}
		
		$this->EE->load->library('cartthrob_loader');
		
		//@TODO enable this setting someday
		if ($this->EE->cartthrob->store->config('set_global_vars'))
		{
			foreach ($this->EE->cartthrob->cart->customer_info() as $key => $value)
			{
				$this->EE->config->_global_vars[$key] = $value;
			}
			
			foreach ($this->EE->cartthrob->cart_info() as $key => $value)
			{
				$this->EE->config->_global_vars[$key] = $value;
			}
		}
	}
	
	// --------------------------------
	//  Member Logout Hook Access
	// --------------------------------
	/**
	 * Perform additional actions after logout
	 *
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 * @see http://expressionengine.com/developers/extension_hooks/member_member_logout/
	 */
	public function member_member_logout()
	{
		if ( ! method_exists($this->EE->load, 'get_package_paths') || ! in_array(PATH_THIRD.'cartthrob/', $this->EE->load->get_package_paths()))
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		}
		
		$this->EE->load->library('cartthrob_loader');
		
		if ($this->EE->cartthrob->store->config('clear_session_on_logout'))
		{
			$this->EE->cartthrob_session->destroy();
		}
		else
		{
			//@TODO should we update their member_id to 0? or save it for them for later?
			
			if ($this->EE->cartthrob->store->config('clear_cart_on_logout') && ! $this->EE->cartthrob->cart->is_empty())
			{
				$this->EE->cartthrob->cart->clear()->save();
			}
		}
	}
	// END
	
	public function member_member_login($userdata)
	{
		if ( ! method_exists($this->EE->load, 'get_package_paths') || ! in_array(PATH_THIRD.'cartthrob/', $this->EE->load->get_package_paths()))
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		}
		
		$this->EE->load->library('cartthrob_loader');
		
		//attach the user's member id to this cart
		if ( ! empty($userdata->member_id) && $this->EE->cartthrob_session->session_id())
		{
			$this->EE->cartthrob_session->update(array('member_id' => $userdata->member_id));
		}
	}
	
	public function cp_menu_array($menu)
	{
		if ($this->EE->extensions->last_call !== FALSE)
		{
			$menu = $this->EE->extensions->last_call;
		}
		
		//if the user has uploaded a new version, but hasn't run the updater yet
		//this hook can cause some pretty bad errors if it tries to access database tables/fields
		//that aren't yet created
		//we're gonna kill this feature if we detect that they need an update
		//so they don't get any errors trying to get to the modules page to do the update
		if (REQ === 'CP')
		{
			//i'm not worried about the overhead from this since a) we're in the CP and b) Accessories lib calls this on every CP page
			$this->EE->load->library('addons');
			
			$modules = $this->EE->addons->get_installed();
			
			if ( ! isset($modules['cartthrob']['module_version']) || version_compare($this->version, $modules['cartthrob']['module_version'], '>'))
			{
				return $menu;
			}
		}
		
		$channels = array();
		
		if (isset($menu['content']['publish']) && is_array($menu['content']['publish']))
		{
			//we've got a perfectly good list of channels right here in menu, let's grab it
			foreach ($menu['content']['publish'] as $channel_name => $url)
			{
				if (preg_match('/channel_id=(\d+)$/', $url, $match))
				{
					$channels[$match[1]] = $channel_name;
				}
			}
		}
		else if (isset($menu['content']['publish'])  && is_string($menu['content']['publish']) && preg_match('/channel_id=(\d+)$/', $menu['content']['publish'], $match))
		{
			$channels[$match[1]] = '';
		}
		
		$this->EE->lang->loadfile('cartthrob', 'cartthrob');
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->library('cartthrob_loader');
		
		$label = 'cartthrob';
		
		if ($this->EE->cartthrob->store->config('cp_menu_label'))
		{
			$this->EE->lang->language['nav_'.$this->EE->cartthrob->store->config('cp_menu_label')] = $this->EE->cartthrob->store->config('cp_menu_label');
			
			$label = $this->EE->cartthrob->store->config('cp_menu_label');
		}
		
		$menu[$label] = array();
		
		$has_channel = FALSE;
		
		if ($this->EE->cartthrob->store->config('product_channels'))
		{
			if (count($this->EE->cartthrob->store->config('product_channels')) > 1)
			{
				foreach ($this->EE->cartthrob->store->config('product_channels') as $channel_id)
				{
					if (isset($channels[$channel_id]))
					{
						$has_channel = TRUE;
						
						$this->EE->lang->language['nav_'.$channels[$channel_id]] = $channels[$channel_id];
						$menu[$label]['products'][$channels[$channel_id]] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$channel_id;
					}
				}
			}
			else
			{
				$channel_id = current($this->EE->cartthrob->store->config('product_channels'));
				
				if (isset($channels[$channel_id]))
				{
					$has_channel = TRUE;
					
					$menu[$label]['products'] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$channel_id;
				}
			}
		}
		
		if ($this->EE->cartthrob->store->config('save_orders') && $this->EE->cartthrob->store->config('orders_channel'))
		{
			if (isset($channels[$this->EE->cartthrob->store->config('orders_channel')]))
			{
				$has_channel = TRUE;
				
				$menu[$label]['orders'] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$this->EE->cartthrob->store->config('orders_channel');
			}
		}
		
		if ($this->EE->cartthrob->store->config('save_purchased_items') && $this->EE->cartthrob->store->config('purchased_items_channel'))
		{
			if (isset($channels[$this->EE->cartthrob->store->config('purchased_items_channel')]))
			{
				$has_channel = TRUE;
				
				$menu[$label]['purchased_items'] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$this->EE->cartthrob->store->config('purchased_items_channel');
			}
		}
		
		if ($this->EE->cartthrob->store->config('discount_channel'))
		{
			if (isset($channels[$this->EE->cartthrob->store->config('discount_channel')]))
			{
				$has_channel = TRUE;
				
				$menu[$label]['discounts'] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$this->EE->cartthrob->store->config('discount_channel');
			}
		}
		
		if ($this->EE->cartthrob->store->config('coupon_code_channel'))
		{
			if (isset($channels[$this->EE->cartthrob->store->config('coupon_code_channel')]))
			{
				$has_channel = TRUE;
				
				$menu[$label]['coupon_codes'] = BASE.AMP.'C=content_edit'.AMP.'channel_id='.$this->EE->cartthrob->store->config('coupon_code_channel');
			}
		}
		
		$add_settings_menu = TRUE;
		
		if ($this->EE->session->userdata('group_id') != 1)
		{
			if ( ! $this->EE->session->userdata('assigned_modules') || ! $this->EE->cp->allowed_group('can_access_addons', 'can_access_modules'))
			{
				$add_settings_menu = FALSE;
			}
			else
			{
				$module_id = $this->EE->db->select('module_id')
							  ->where('module_name', 'Cartthrob')
							  ->get('modules')
							  ->row('module_id');
				
				$assigned_modules = $this->EE->session->userdata('assigned_modules') ? $this->EE->session->userdata('assigned_modules') : array();
				
				if ( ! $module_id || ! array_key_exists($module_id, $assigned_modules))
				{
					$add_settings_menu = FALSE;
				}
			}
		}
		
		if ($add_settings_menu === TRUE)
		{
			require_once PATH_THIRD.'cartthrob/mcp.cartthrob.php';
			
			$settings = array();
			
			foreach (array_keys(Cartthrob_mcp::$nav) as $nav)
			{
				if (!in_array($nav, Cartthrob_mcp::$no_nav))
				{
					$settings[$nav] = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method='.$nav;
				}
			}
			
			if ($has_channel)
			{
				$menu[$label][] = '----';
				
				$menu[$label]['settings'] = $settings;
			}
			else
			{
				$menu[$label] = $settings;
			}
		}
		
		if (empty($menu[$label]))
		{
			unset($menu[$label]);
		}
		
		return $menu;
	}
}
// END CLASS
/* End of file ext.cartthrob_ext.php */
/* Location: ./system/extension/ext.cartthrob_ext.php */