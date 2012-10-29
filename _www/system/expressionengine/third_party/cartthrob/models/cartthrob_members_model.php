<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cartthrob_members_model extends CI_Model
{
	public $errors = array();
	
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		parent::__construct();
		
		$this->load->library('cartthrob_loader');
		$this->cartthrob_loader->setup($this);
	}
	/**
	 * create_member
	 *
	 * @param string $username 
	 * @param string $email_address 
	 * @param string $screenname 
	 * @param string $password 
	 * @param string $password_confirm (must match password)
	 * @param string $group_id
	 * @param string $language 
	 * @return int / array. If successful will return member_id, if unsuccessful, will return an array of errors. 
	 * @author Chris Newton 
	 * @access public
	 * @since 1.0
	 */
	public function create_member($user=FALSE, $email_address, $screenname = FALSE, $password = FALSE, $password_confirm = FALSE, $group_id = FALSE, $language = FALSE, $send_activation_email = TRUE)
	{
		$username = ($user) ? $user : $email_address;
		
		$this->load->helper(array('security', 'string', 'text'));
		$this->load->library('cartthrob_emails'); 

		$default_group_id = ($this->config->item('default_member_group')) ? $this->config->item('default_member_group') : 4;
		
		if ($group_id && $group_id < $default_group_id)
		{
			$data['group_id']= $default_group_id; 
		}
		else
		{
			$this->db->select('group_id')
					->from('member_groups')
					->where('site_id', $this->config->item('site_id'))
					->where('group_id', $group_id);
			
			$data['group_id'] = ( ! $this->db->count_all_results()) ? $default_group_id : $group_id; 
		}
		
		if ($this->config->item('req_mbr_activation') === 'manual' || $this->config->item('req_mbr_activation') === 'email')
		{
			$data['group_id'] = 4;
		}
		
		if ($this->config->item('req_mbr_activation') === 'email')
		{
			$data['authcode'] = $this->functions->random('alnum', 10);
		}

		// GENERATING A PASSWORD IF NONE IS PROVIDED
		if (empty($password))
		{
			$password = $this->functions->random('alpha');
			$password_confirm = $password;
		}

		if (empty($screenname))
		{
			$screenname = $username; 
		}

		if ($language)
		{
			$data['language'] = $language; 
		}
		else
		{
			$data['language'] = $this->config->item('deft_lang');
		}

		$data['username'] = $username; 
		$data['password'] = do_hash(stripslashes($password));
		$data['ip_address'] = $this->input->ip_address();
		$data['unique_id'] = $this->functions->random('encrypt');
		$data['email'] = $email_address;
		$data['screen_name'] = $screenname;
		$data['join_date'] = $this->localize->now;
		$data['timezone'] = ($this->config->item('default_site_timezone') && $this->config->item('default_site_timezone') != '') ? $this->config->item('default_site_timezone') : $this->config->item('server_timezone');
		$data['daylight_savings'] = ($this->config->item('default_site_dst') && $this->config->item('default_site_dst') != '') ? $this->config->item('default_site_dst') : $this->config->item('daylight_savings');
		$data['time_format'] = ($this->config->item('time_format') && $this->config->item('time_format') != '') ? $this->config->item('time_format') : 'us';
		
		/** -------------------------------------
		/**  Instantiate validation class
		/** -------------------------------------*/
		if ( ! class_exists('EE_Validate'))
		{
			require APPPATH.'libraries/Validate'.EXT;
		}

		$VAL = new EE_Validate(array(
			'member_id' => '',
			'val_type' => 'new', // new or update
			'fetch_lang' => TRUE,
			'require_cpw' => FALSE,
			'enable_log' => FALSE,
			'username' => $data['username'],
			'cur_username' => '',
			'screen_name' => $data['screen_name'],
			'cur_screen_name' => '',
			'password' => $password,
			'password_confirm' => $password_confirm,
			'cur_password' => '',
			'email' => $data['email'],
			'cur_email' => ''
		));
		
		
		$VAL->validate_username();
		$VAL->validate_screen_name();
		$VAL->validate_password();
		$VAL->validate_email();

		if (count($VAL->errors) > 0)
		{
			// return the array of errors. 
			return $VAL->errors;
 		}

		
		$this->load->model('member_model');
		
		if ($this->config->item('req_mbr_activation') == 'email')
		{
			$data['authcode'] = $this->functions->random('alnum', 10);
		}
		
	 	$member_id = $this->member_model->create_member($data, array());
	
		/**************** admin notification emails ************/

		if ($this->config->item('new_member_notification') == 'y' && $this->config->item('mbr_notification_emails') != '')
		{
			$vars = array(
							'name'					=> $data['screen_name'],
							'site_name'				=> stripslashes($this->config->item('site_name')),
							'control_panel_url'		=> $this->config->item('cp_url'),
							'username'				=> $data['username'],
							'email'					=> $data['email']
						 );

			$template = $this->functions->fetch_email_template('admin_notify_reg');

			foreach ($vars as $key => $val)
			{
				$template['title'] = str_replace('{'.$key.'}', $val, $template['title']);
				$template['data'] = str_replace('{'.$key.'}', $val, $template['data']);
			}
			$email_to = reduce_multiples($this->config->item('mbr_notification_emails'), ',', TRUE);
			
			$this->cartthrob_emails->send_email($this->config->item('webmaster_email'), $this->config->item('webmaster_name'), $email_to, $template['title'], $template['data'], $plaintext = FALSE); 
		}
			
		//// NOTE this does not display any warning to the user when account activation is required
		/**************** send emails *****************************/
		if ($this->config->item('req_mbr_activation') == 'none')
		{
			$this->stats->update_member_stats();
		}
		elseif ($this->config->item('req_mbr_activation') == 'email' && $send_activation_email)
		{
			$this->send_activation_email($data['email'], $data['username'], $data['authcode']);
 		}
		/**************** end send emails *****************************/
		
		// -------------------------------------------
		// 'cartthrob_create_member' hook.
		//  - Developers, if you want to modify the $this object remember
		//	to use a reference on function call.
		//
		if ($this->extensions->active_hook('cartthrob_create_member') === TRUE)
		{
			$edata = $this->extensions->call('cartthrob_create_member', array_merge($data, array('member_id' => $member_id)), $this);
			if ($this->extensions->end_script === TRUE) return;
		}

		return $member_id;
	}
	
	/**
	 * send the member activation email
	 *
	 * used to delay the sending of the activation email until the order is authorized
	 * 
	 * @param array   $data requires: authcode, username, email
	 * 
	 * @return void
	 */
	function send_activation_email($email, $username = '', $authcode = '')
	{
		$this->load->library('cartthrob_emails');
		
		//it's a member id!
		if (is_numeric($email))
		{
			$query = $this->db->select('username, email, authcode')
					  ->where('member_id', $email)
					  ->get('members');
			
			if ($query->num_rows() === 0)
			{
				return;
			}
			
			extract($query->row_array());
			
			$query->free_result();
		}
		
		$action_id  = $this->functions->fetch_action_id('Member', 'activate_member');

		$vars = array(
			'activation_url'	=> $this->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$action_id.'&id='.$authcode,
			'site_name'			=> stripslashes($this->config->item('site_name')),
			'site_url'			=> $this->config->item('site_url'),
			'username'			=> $username,
			'email'				=> $email,
		 );

		$template = $this->functions->fetch_email_template('mbr_activation_instructions');
		
		foreach ($vars as $key => $val)
		{
			$template['title'] = str_replace('{'.$key.'}', $val, $template['title']);
			$template['data'] = str_replace('{'.$key.'}', $val, $template['data']);
		}
		
		$this->cartthrob_emails->send_email($this->config->item('webmaster_email'), $this->config->item('webmaster_name'), $email, $template['title'], $template['data'], $plaintext = FALSE); 
	}
	// END
	/**
	 * get_member_id
	 *
	 * Returns the member id of the current user
	 * If logged out, it will return the member id of the oldest superadmin
	 * 
	 * @access public
	 * @author Chris Newton
	 * @return int
	 * @since 1.0
	 */
	public function get_member_id()
	{
		//get logged in member id if logged in 
		if ($this->session->userdata('member_id'))
		{
			return $this->session->userdata('member_id');
		}
		
		//get cached created member id if newly created member
		if (isset($this->session->cache['cartthrob']['member_id']))
		{
			return $this->session->cache['cartthrob']['member_id'];
		}
		
		//get the default logged out member id if set in the settings and valid
		if ($this->store->config('default_member_id') && (ctype_digit($this->store->config('default_member_id')) || is_int($this->store->config('default_member_id'))))
		{
			return $this->store->config('default_member_id');
		}
		
		//get the oldest superadmin
		return $this->session->cache['cartthrob']['member_id'] = $this->db->select('member_id')
											->where('group_id', 1)
											->order_by('member_id', 'asc')
											->limit(1)
											->get('members')
											->row('member_id');
	}
	// END
	/**
	 * member_loging
	 *
	 * @param string $member_id 
	 * @param string $username 
	 * @param string $password 
	 * @param string $unique_id
	 * @return void
	 * @author Chris Newton
	 * @since 2.0
	 * does not execute multi-logins 
	 */
	public function login_member($member_id)
	{
		$query = $this->db->from('members')
				  ->select('password, unique_id')
				  ->where('member_id', $member_id)
				  ->get();

		if ($query->num_rows() === 0)
		{
			$this->errors[] = $this->lang->line('unauthorized_access');
			
			return FALSE;
		}

		$this->lang->loadfile('login');

		if ($this->config->item('user_session_type') != 's')
		{
			$this->functions->set_cookie($this->session->c_expire, time(), 0);
			$this->functions->set_cookie($this->session->c_anon, 1, 0);
			
			if (version_compare(APP_VER, '2.1.5', '<'))
			{
				$this->functions->set_cookie($this->session->c_uniqueid, $query->row('unique_id'), 0);
				$this->functions->set_cookie($this->session->c_password, $query->row('password') , 0);
			}
		}

		$this->session->create_new_session($member_id, TRUE);

		$this->session->delete_password_lockout();
	}
	// END
}
// END CLASS
