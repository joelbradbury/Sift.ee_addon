<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cartthrob_payments
{
	private $gateway;
	private $paths = array();
	private $errors = array();
	private $total;
	private $current_status  = NULL; 
	private $third_party_path;
	
	public $cartthrob, $store, $cart;
	
	public function __construct($params = array())
	{
		$this->EE =& get_instance();
		
		include_once PATH_THIRD.'cartthrob/config.php';
		
		require_once PATH_THIRD.'cartthrob/payment_gateways/Cartthrob_payment_gateway.php';
		
		$this->third_party_path = ($this->EE->config->item('cartthrob_third_party_path')) ? rtrim($this->EE->config->item('cartthrob_third_party_path'), '/').'/' : PATH_THIRD.'cartthrob/third_party/';
		
		$this->paths[] = $this->third_party_path.'payment_gateways/';
		
		$this->paths[] = PATH_THIRD.'cartthrob/payment_gateways/';
		
		if ( ! function_exists('json_decode'))
		{
			$this->EE->load->library('services_json');
		}
	}
	
	public function add_error($key, $value = FALSE)
	{
		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				$this->add_error($k, $v);
			}
		}
		else
		{
			if ($value === FALSE)
			{
				$this->errors[] = $key;
			}
			else
			{
				$this->errors[$key] = $value;
			}
		}
		
		return $this;
	}
	
	public function set_total($total)
	{
		$this->total = $total;
		return $this;
	}
	
	public function total()
	{
		return $this->total;
	}
	
	//this is only for gateways who need the total when the checkout form is rendered, like stripe
	public function get_total()
	{
		return $this->EE->cartthrob->cart->total();
	}
	
	//this is for loading third party libraries, usually api wrappers, in payment_gateways/vendor
	public function library_path()
	{
		return $this->vendor_path();
	}
	
	public function vendor_path()
	{
		return PATH_THIRD.'cartthrob/payment_gateways/libraries/';
	}
	
	public function config($key = FALSE)
	{
		return $this->EE->cartthrob->store->config($key);
	}
	
	public function theme_path()
	{
		return $this->EE->config->item('theme_folder_url');
	}
	
	public function order($order = FALSE)
	{
		return $this->EE->cartthrob->cart->order($order);
	}
	
	public function payment_url()
	{
		return $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$this->EE->functions->fetch_action_id('Cartthrob', 'checkout_action');
	}
	
	public function paths()
	{
		return $this->paths;
	}
	
	public function process_payment($credit_card_number)
	{
		if ($this->total <= 0)
		{
			return array(
				'processing' => FALSE,
				'authorized' => TRUE,
				'declined' => FALSE,
				'failed' => FALSE,
				'error_message' => '',
				'transaction_id' => time()
			);
		}
		
		if ( ! $this->gateway)
		{
			return array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('invalid_payment_gateway'),
				'transaction_id' => ''
			);
		}
		
		return $this->gateway->process_payment($credit_card_number);
	}
	public function create_recurrent_billing($subscription_amount, $credit_card_number, $sub_data)
	{
		if ( ! $this->gateway && !is_callable(array($this->gateway, "create_recurrent_billing")))
		{
			return array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('invalid_payment_gateway'),
				'transaction_id' => ''
			);
		}
		return $this->gateway->create_recurrent_billing($subscription_amount, $credit_card_number, $sub_data);
	}
	public function update_recurrent_billing($id, $credit_card_number)
	{
		if ( ! $this->gateway && !is_callable(array($this->gateway, "update_recurrent_billing")))
		{
			return array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('invalid_payment_gateway'),
				'transaction_id' => ''
			);
		}
		$this->EE->load->model("order_model");
		$this->EE->load->model("vault_model");
		
		$auth =  $this->gateway->update_recurrent_billing($id, $credit_card_number);
		
		if ($auth['authorized']) 
		{
			if ($auth['transaction_id'])
			{
				$data['sub_id']  = $auth['transaction_id']; 
			}
			
			$this->EE->vault_model->update_vault($data, $id); 
			
		}
		return $auth; 
	}

	public function update_vault_data($data, $id=NULL)
	{
		if (!is_array($data))
		{
			return FALSE; 
		}
		
		$this->EE->load->model("vault_model");
		return $this->EE->vault_model->update_vault($data, $id); 
	}
	public function delete_recurrent_billing($id)
	{
		$auth =  array(
			'processing' => FALSE,
			'authorized' => FALSE,
			'declined' => FALSE,
			'failed' => TRUE,
			'error_message' => $this->lang('invalid_payment_gateway'),
			'transaction_id' => ''
		);
		
		if ( ! $this->gateway && !is_callable(array($this->gateway, "delete_recurrent_billing")))
		{
			return $auth; 
		}

		$this->EE->load->model("vault_model");

		$auth =  $this->gateway->delete_recurrent_billing($id);
		

		if ($auth['authorized'])
		{
			$this->EE->vault_model->delete_vault($vault_id=NULL, $order_id = NULL, $member_id = NULL, $sub_id=$id);
			
		}
		return $auth; 
	}
	public function subscription_info($subscription_data=array(), $key, $default=FALSE)
	{
		return (isset($subscription_data[$key])) ? $subscription_data[$key] : $default;
	}
	public function required_fields()
	{
		return ($this->gateway) ? $this->gateway->required_fields : array();
	}
	
	public function set_gateway($gateway)
	{
		static $loaded_gateways = array();
		
		if (strpos($gateway, 'Cartthrob_') !== 0)
		{
			$gateway = 'Cartthrob_'.$gateway;
		}
		
		if ( ! is_object($this->gateway) || get_class($this->gateway) != $gateway)
		{
			$this->gateway = NULL;
			
			foreach ($this->paths as $path)
			{
				if (file_exists($path.$gateway.EXT))
				{
					if ( ! in_array($gateway, $loaded_gateways))
					{
						require_once $path.$gateway.EXT;
					}
					
					$loaded_gateways[] = $gateway;
					
					$this->gateway = new $gateway;
					
					$this->gateway->set_core($this);
					
					if ($path === $this->third_party_path.'payment_gateways/')
					{
						$this->load_lang(strtolower($gateway), $this->third_party_path);
					}
					else
					{
						$this->load_lang(strtolower($gateway));
					}
					
					$this->gateway->initialize();
				}
			}
		}
		
		return $this;
	}
	
	public function load_lang($which, $path = NULL)
	{
		static $user_lang;
		
		if (is_null($path))
		{
			$path = PATH_THIRD.'cartthrob/';
		}
		
		if (is_null($user_lang))
		{
			if ( ! empty($this->EE->session->userdata['language']))
			{
				$user_lang = $this->EE->session->userdata['language'];
			}
			else if ($this->EE->input->cookie('language'))
			{
				$user_lang = $this->EE->input->cookie('language');
			}
			else if ($this->EE->config->item('deft_lang'))
			{
				$user_lang = $this->EE->config->item('deft_lang');
			}
			else
			{
				$user_lang = 'english';
			}
			
			$user_lang = $this->EE->security->sanitize_filename($user_lang);
		}
		
		$this->EE->lang->load($which, $user_lang, FALSE, TRUE, $path, FALSE);
	}
	
	public function gateway()
	{
		return $this->gateway;
	}
	
	/* utilities for the payment gateways */
	
	public function log($msg)
	{
		$this->EE->load->model('log_model');
		
		return $this->EE->log_model->log($msg);
	}
	
	public function create_url($path)
	{
		return $this->EE->functions->create_url($path);
	}
	
	public function fetch_template($template)
	{
		$template = explode('/', $template);
		
		$template_group = $template[0];
		
		$template_name = (isset($template[1])) ? $template[1] : 'index';
		
		$query = $this->EE->db->select('template_data')
				    ->join('template_groups', 'templates.group_id = template_groups.group_id')
				    ->where('group_name', $template_group)
				    ->where('template_name', $template_name)
				    ->where('templates.site_id', $this->EE->config->item('site_id'))
				    ->get('templates');
		
		$data = $query->row('template_data');
		
		$query->free_result();
		
		return $data;
	}
	
	public function parse_template($template, $vars = array())
	{
		if (isset($this->EE->TMPL))
		{
			unset($this->EE->TMPL);
		}
		$this->EE->load->library('template', NULL, 'TMPL');
		
		if ($vars)
		{
			$template = $this->EE->TMPL->parse_variables($template, array($vars));
		}
		
		$this->EE->TMPL->parse($template);
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		return $this->EE->TMPL->parse_globals($this->EE->TMPL->final_template);
	}
	
	public function lang($key)
	{
		return $this->EE->lang->line($key);
	}
	
	public function year_2($year)
	{
		if (strlen($year > 2))
		{
			return substr($year, -2);
		}
		
		return str_pad($year, 2, '0', STR_PAD_LEFT);
	}
	
	public function year_4($year)
	{
		$length = strlen($year);
		
		switch($length)
		{
			case 3:
				return '2'.$year;
			case 2:
				return '20'.$year;
			case 1:
				return '200'.$year;
			case ($length > 4):
				return substr($year, -4);
		}
		
		return $year;
	}
	
	public function alpha2_country_code($country_code)
	{
		$this->EE->load->library('locales');
		
		return $this->EE->locales->alpha2_country_code($country_code);
	}
	
	public function alpha3_country_code($country_code)
	{
		$this->EE->load->library('locales');
		
		return $this->EE->locales->alpha3_country_code($country_code);
	}
	
	public function curl_transaction($url, $data = FALSE, $header = FALSE, $mode = 'POST', $suppress_errors = FALSE, $options = NULL)
	{
		if ( ! function_exists('curl_exec'))
		{
			return show_error(lang('curl_not_installed'));
		}
		
		// CURL Data to institution
		$curl = curl_init($url);
		
		if ($this->EE->config->item('cartthrob:curl_proxy'))
		{
			curl_setopt($curl, CURLOPT_PROXY, $this->EE->config->item('cartthrob:curl_proxy'));
			
			if ($this->EE->config->item('cartthrob:curl_proxy_port'))
			{
				curl_setopt($curl, CURLOPT_PROXYPORT, $this->EE->config->item('cartthrob:curl_proxy_port'));
			}
		}
		
		if ($header)
		{
			curl_setopt($curl, CURLOPT_HEADER, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array($header));
		}
		else
		{
			// set to 0 to eliminate header info from response
			curl_setopt($curl, CURLOPT_HEADER, 0);
		}
		
		// Returns response data instead of TRUE(1)
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		
		if ($data)
		{
			if ($mode === 'POST')
			{
				// use HTTP POST to send form data
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			}
			else
			{
				// check for query  string
				if (strrpos($url, "?") === FALSE)
				{
					curl_setopt($curl, CURLOPT_URL, $url.'?'.$data); 
				}
				else
				{
					curl_setopt($curl, CURLOPT_URL, $url.$data);
				}
				
				curl_setopt($curl, CURLOPT_HTTPGET, 1);
			}
		}
		else
		{
			// if there's no data passed in, then it's a GET
			curl_setopt($curl, CURLOPT_HTTPGET, 1);
		}
		
		// curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107 Firefox/1.0');
		// Turn off the server and peer verification (PayPal TrustManager Concept).
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		if (is_array($options))
		{
			foreach ($options as $key => $value)
			{
				curl_setopt($curl, $key, $value);
			}
		}
		// execute post and get results
		$response = curl_exec($curl);

		if ( ! $response)
		{
			$error = curl_error($curl).' ('.curl_errno($curl).')';
		}

		curl_close($curl);
		
		if ( ! $suppress_errors && ! empty($error))
		{
			return show_error($error);
		}
		
		return $response; 
	}
	
	/**
	 * curl_post
	 *
	 * @param array $data (string:url, array:params, array:options)
	 * @return string
	 * @author Rob Sanchez
	 * 
	 * $this->EE->curl->simple_post('http://example.com', array('foo'=>'bar'), array(CURLOPT_BUFFERSIZE => 10));  
	 * @see http://codeigniter.com/wiki/Curl_library/
	 * @see http://uk3.php.net/manual/en/function.curl-setopt.php
	 */
	public function curl_post($url, $params = array(), $options = array())
	{
		if (is_array($url))
		{
			$options = (isset($url[2])) ? $url[2] : array();
			$params = (isset($url[1])) ? $url[1] : array();
			$url = $url[0];
		}
		
		$this->EE->load->library('curl');
		
		return $this->EE->curl->simple_post($url, $params, $options);
	}
	/**
	 * curl_get
	 *
	 * @param array $data (string:url, array:options)
	 * @return string
	 * @author Rob Sanchez
	 * 
	 * $this->curl->simple_get('http://example.com', array(CURLOPT_PORT => 8080)); 
	 * @see http://codeigniter.com/wiki/Curl_library/
	 * @see http://uk3.php.net/manual/en/function.curl-setopt.php
	 */
	public function curl_get($url, $options = array())
	{
		if (is_array($url))
		{
			$options = (isset($url[1])) ? $url[1] : array();
			$url = $url[0];
		}
		
		$this->EE->load->library('curl');
		
		return $this->EE->curl->simple_get($url, $options);
	}
	
	public function curl_error_message()
	{
		$this->EE->load->library('curl');
		
		return $this->EE->curl->error_string;
	}
	
	public function curl_error_code()
	{
		$this->EE->load->library('curl');
		
		return $this->EE->curl->error_code;
	}
	
	public function customer_id()
	{
		return $this->EE->session->userdata('member_id');
	}
	
	public function order_id()
	{
		return $this->order('order_id');
	}
	
	public function input_post($key, $xss_clean = FALSE)
	{
		return $this->EE->input->post($key, $xss_clean);
	}
	
	public function input_get($key, $xss_clean = FALSE)
	{
		return $this->EE->input->get($key, $xss_clean);
	}
	
	public function input_get_post($key, $xss_clean = FALSE)
	{
		return $this->EE->input->get_post($key, $xss_clean);
	}
	
	public function input_cookie($key, $xss_clean = FALSE)
	{
		return $this->EE->input->cookie($key, $xss_clean);
	}
	
	public function xss_clean($data)
	{
		return $this->EE->security->xss_clean($data);
	}
	
	public function split_url_string($data,  $split_character = '&')
	{
		$this->EE->load->helper('data_formatting');
		return split_url_string($data, $split_character);
	}
	
	public function convert_response_xml($xml)
	{
		$this->EE->load->helper('data_formatting');
		return convert_response_xml($xml);
	}
	
	public function get_formatted_phone($phone)
	{
		$this->EE->load->helper('data_formatting');
		return get_formatted_phone($phone);
	}
	
	public function data_array_to_string($data)
	{
		if (function_exists('http_build_query'))
		{
			return http_build_query($data, '', '&');
		}

		$string = '';
		
		while (list($key, $val) = each($data)) 
		{
			$string .= $key."=".urlencode(stripslashes(str_replace("\n", "\r\n", $val))).'&';
		}
		
		if ($string)
		{
			$string = substr($data, 0, -1);
		}
		
		return $string;
	}
	
	public function clear_cart($cart_id = NULL)
	{
		if ($cart_id)
		{
			$this->relaunch_cart($cart_id ); 
		}
		
		$this->EE->cartthrob->cart->clear()
					  ->clear_coupon_codes()
					  ->clear_totals()
					  ->save();
	}
	public function response_script($gateway, $segments = array())
	{
		if (substr($gateway, 0, 10) == 'Cartthrob_')
		{
			$gateway = substr($gateway, 10);
		}
		
		if ( ! $extload = $this->EE->config->item('cartthrob:extload_path'))
		{
			$extload = URL_THIRD_THEMES.'cartthrob/lib/extload.php'; 
		}
		
		$extload .= "/".$gateway; 
		
		foreach ($segments as $item)
		{
			$extload .="/".$item; 
		}
		return $extload; 
	}
	public function update_order($data)
	{
		$this->EE->cartthrob->cart->update_order($data);
	}
	public function update_order_by_id($entry_id, $order_data)
	{
		$this->EE->load->model("order_model");
		return $this->EE->order_model->update_order($entry_id, $order_data); 
		
	}
	public function get_language_abbrev($language)
	{
		$this->EE->load->library('languages');
		
		return $this->EE->languages->get_language_abbrev($language);
	}
	// deprecated
	public function relaunch_session_full($session_id)
	{
		$this->relaunch_session($session_id); 
	}
	public function relaunch_session($session_id)
	{
 		if ($session_id != @session_id())
		{
			@session_destroy(); 
			@session_id($session_id);
			@session_start();
		}
		
		$this->EE->load->model('order_model'); 
		$order_id = $this->EE->order_model->get_order_id_from_session($session_id); 

		$this->relaunch_cart_snapshot($order_id); 
 	}
	
	public function get_notify_url($gateway, $method = FALSE)
	{
		$this->EE->load->library('encrypt');
		
		if (substr($gateway, 0, 10) == 'Cartthrob_')
		{
			$gateway = substr($gateway, 10);
		}

		$notify_url = $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$this->EE->functions->insert_action_ids($this->EE->functions->fetch_action_id('Cartthrob', 'payment_return_action')).'&G='.base64_encode($this->EE->encrypt->encode($gateway));

		if ($method)
		{
			$notify_url .= "&M=".base64_encode($this->EE->encrypt->encode($method));
		}
		
		return $notify_url; 
	}
	
	/**
	 * gateway_exit_offsite
	 *
	 * sends a customer offsite to finish a payment transaction
	 * 
	 * @param array $post_array 
	 * @param string $url 
	 * @return void
	 * @author Chris Newton 
	 * @since 1.0
	 * @access public 
	 */
	function gateway_exit_offsite($post_array=NULL, $url=FALSE, $jump_url= FALSE)
	{
		
		if ($this->EE->cartthrob->store->config('save_purchased_items') && $this->EE->cartthrob->cart->order('items'))
		{
			$this->EE->load->model('purchased_items_model');
			
			$purchased_items = array();
			
			foreach ($this->EE->cartthrob->cart->order('items') as $row_id => $item)
			{
				//if it's a package, we'll make purchased items from the sub_items and not the package itself
				if ( ! empty($item['sub_items']))
				{
					foreach ($item['sub_items'] as $_row_id => $_item)
					{
						$purchased_items[$row_id.':'.$_row_id] = $this->EE->purchased_items_model->create_purchased_item($_item, $this->order('order_id'), $this->EE->cartthrob->store->config('purchased_items_processing_status'));
					}
				}
				else
				{
					$purchased_items[$row_id] = $this->EE->purchased_items_model->create_purchased_item($item, $this->order('order_id'), $this->EE->cartthrob->store->config('purchased_items_processing_status'));
				}
			}
			
			$this->update_order(array('purchased_items' => $purchased_items));
			
			$this->save_cart();
		}
		
		$this->save_cart_snapshot($this->order('entry_id')); 
		$this->set_status_offsite(array(), $this->order('order_id'),  $send_email=FALSE);
		
		if ($jump_url !== FALSE)
		{
			echo $this->jump_form($jump_url, $post_array, $hide_jump_form=TRUE, $this->lang('jump_header'), $this->lang('jump_alert'), $this->lang('jump_submit')); 
			exit; 
		}
		elseif ($url !== FALSE)
		{
			if ($post_array)
			{
				$url .= '?'.$this->data_array_to_string($post_array);	
			}
			$this->EE->functions->redirect($url);
		}
		else
		{
			return; 
		}

	}
    public function set_order_meta($order_id, $internal_status=NULL, $ee_status= NULL, $transaction_id = NULL, $error_message = NULL )
	{
 		$this->EE->load->model("order_model");
		if ($internal_status)
		{
			if ($internal_status == "authorized" || $internal_status == "completed")
			{
				// garbage cleanup
				$this->EE->order_model->update_order($order_id, array('cart' => "" ));
			}
			$this->EE->order_model->set_order_status($order_id, $internal_status);
		}
		if ($transaction_id)
		{
			$this->EE->order_model->set_order_transaction_id($order_id, $transaction_id);
		}
		if ($error_message)
		{
			$this->EE->order_model->set_order_error_message($order_id, $error_message);
		}

		if ($this->EE->cartthrob->store->config('save_orders'))
		{
			if ($ee_status)
			{
				$data['status'] = $ee_status; 
			}
			if ($transaction_id !== NULL)
			{
				$data['transaction_id'] = $transaction_id; 
			}
			if ($error_message !== NULL)
			{
				$data['error_message'] = $error_message; 
			}
			$this->EE->order_model->update_order($order_id, $data);
		}
		
	}
	
	public function set_purchased_items_status($ee_status=NULL, $order_id, $transaction_id = NULL )
	{
		if (empty($ee_status))
		{
			return NULL; 
		}
		if ($this->EE->cartthrob->store->config('save_purchased_items') && $this->order('purchased_items'))
		{
			$this->EE->load->model('purchased_items_model');

			foreach ($this->order('purchased_items') as $entry_id)
			{
				$this->EE->purchased_items_model->update_purchased_item($entry_id, array(
					'status' => $ee_status
				));
			}

		}
	}
	
	public function round($number)
	{
		return $this->EE->cartthrob->round($number);
	}
	/* @NOTE remember that the cart has to have been saved first. This happens automatically in gateway exit offsite using save_cart_snapshot. If that's not used though, you'll have to manually save the cart. */
	public function relaunch_cart_snapshot($order_id)
	{
 		$this->EE->load->model('order_model'); 
		$data = $this->EE->order_model->get_cart_from_order($order_id);
		
		if ($data)
		{
			unset($this->EE->cartthrob); 
			$this->EE->cartthrob = Cartthrob_core::instance('ee', array('cart' => $data));
			return $data; 
		}
		return NULL; 
	}
	public function relaunch_cart($cart_id = NULL, $order_id = NULL)
	{
		if ($order_id && !$cart_id)
		{
			$this->EE->load->model('order_model'); 
			$cart_id = $this->EE->order_model->get_order_cart_id($order_id); 
		}

 		$this->EE->load->model('cart_model'); 
		$data = $this->EE->cart_model->read_cart($cart_id);

		if ($data)
		{
			unset($this->EE->cartthrob); 
			$this->EE->cartthrob = Cartthrob_core::instance('ee', array('cart' => $data));
			$this->EE->load->library('cartthrob_session', array('core' => $this, 'use_regenerate_id' => FALSE, 'use_fingerprint' => FALSE));
			$this->EE->cartthrob_session->set_cart_id($cart_id); 

			if (!empty($data['language']))
			{
				$this->EE->load->library('languages');
				$this->EE->languages->set_language($data['language']);
			}
			
			return $data; 
		}			

		return NULL; 
	}
	public function save_cart_snapshot($order_id, $inventory_processed = FALSE, $discounts_processed = FALSE)
	{
		$this->EE->load->model('order_model'); 
		// for backward compatibility I'm saving the session id in the order table.
		// systems that previously used session id to relaunch the session will at least be able to 
		// continue to use the same identifier. The CT session will be relaunched using the order id tied to the session. 
		$session_id = @session_id(); 
		if (!$session_id)
		{
			@session_start();
			$session_id = @session_id(); 
		}
 		$this->EE->order_model->save_cart_snapshot($order_id, $inventory_processed, $discounts_processed, $this->EE->cartthrob->cart_array(), $this->cart_id(), $session_id ); 
		
	}
	public function cart_id()
	{
		$this->EE->load->library('cartthrob_session', array('core' => $this, 'use_regenerate_id' => FALSE, 'use_fingerprint' => FALSE));
		return $this->EE->cartthrob_session->cart_id();
	}
	public function get_order_status($order_id)
	{
		if ($this->current_status == NULL)
		{
			$this->EE->load->model("order_model");
	 		return $this->current_status = $this->EE->order_model->get_order_status($order_id);   
		}
		else
		{
			return $this->current_status; 
		}
		
	}
	public function save_cart()
	{
		$this->EE->cartthrob->cart->save();
	}
	public function process_cart()
	{
		$this->EE->cartthrob->process_discounts()
				->process_inventory();
	}
	public function set_status_authorized($auth, $order_id,  $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => TRUE,
				'declined' => FALSE,
				'failed' => FALSE,
				'error_message' => '',
				'transaction_id' => NULL, 
			),
			$auth
		);
		$customer_info = $this->EE->cartthrob->cart->customer_info();

		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{        
			
 			$this->update_order(array('auth' => $auth));
			$this->set_order_meta($order_id, 'authorized', $this->EE->cartthrob->store->config('orders_default_status'), element('transaction_id', $auth),element('error_message', $auth) ); 
 
			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_default_status'), $order_id ); 
 
			// PROCESS 
			$this->process_cart(); 

			$this->EE->load->model('order_model'); 
			$cart_id = $this->EE->order_model->get_order_cart_id($order_id);
			$this->clear_cart($cart_id); 

			if ($this->EE->extensions->active_hook('cartthrob_on_authorize') === TRUE)
			{
				$edata = $this->EE->extensions->call('cartthrob_on_authorize');
				if ($this->EE->extensions->end_script === TRUE) return;
			}
			// SEND EMAIL

			if ($send_email !==FALSE)
			{
				$this->EE->load->library('cartthrob_emails');
				if (is_array($send_email))
				{
					$this->send_email($send_email); 
 				}
				else
				{
					/*
					$this->EE->cartthrob_emails->send_confirmation_email($customer_info['email_address'], $this->EE->cartthrob->cart->order());
	 				$this->EE->cartthrob_emails->send_admin_notification_email($this->EE->cartthrob->cart->order());
					*/
					$emails = $this->EE->cartthrob_emails->get_email_for_event("completed"); 
					if (!empty($emails))
					{
						foreach ($emails as $email_content)
						{
							$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
						}
					}
				}
 			}
		}
		$this->save_cart(); 
		
		return NULL; 
	}
	public function send_email($email_content)
	{
		$this->EE->load->library('cartthrob_emails');
		$this->EE->cartthrob_emails->send_email_from_array($email_content, $this->EE->cartthrob->cart->order()); 
		
	}
	public function set_status_declined($auth, $order_id,   $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => TRUE,
				'failed' => FALSE,
				'error_message' => '',
				'transaction_id' => NULL, 
			),
			$auth
		);
		$customer_info = $this->EE->cartthrob->cart->customer_info();

		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{
 			$this->update_order(array('auth' => $auth));
			
			$this->set_order_meta($order_id,'declined', $this->EE->cartthrob->store->config('orders_declined_status'), element('transaction_id', $auth),element('error_message', $auth)); 
 
			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_declined_status'), $order_id ); 
			
 			// SEND EMAIL
			if ($send_email !==FALSE )
			{
				$this->EE->load->library('cartthrob_emails');
				if (is_array($send_email))
				{
					$this->send_email($send_email); 
				}
				else
				{
					$emails = $this->EE->cartthrob_emails->get_email_for_event("declined"); 
					if (!empty($emails))
					{
						foreach ($emails as $email_content)
						{
							$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
						}
					}
					/*
					$this->EE->cartthrob_emails->send_customer_declined_email($customer_info['email_address'], $this->EE->cartthrob->cart->order());
					$this->EE->cartthrob_emails->send_admin_declined_email($this->EE->cartthrob->cart->order());
					*/
					
				}
 			}
			
			if ($this->EE->extensions->active_hook('cartthrob_on_decline') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_decline');
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		}
		$this->save_cart(); 
		
		return NULL; 
	}
	
	public function set_status_failed($auth, $order_id,   $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => 'Unknown Failure', // @TODO lang
				'transaction_id' => NULL, 
			),
			$auth
		);
		$customer_info = $this->EE->cartthrob->cart->customer_info();

		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{
			$this->update_order(array('auth' => $auth));
 			
 
 			$this->set_order_meta($order_id,'failed', $this->EE->cartthrob->store->config('orders_failed_status'), element('transaction_id', $auth),element('error_message', $auth)); 
 
			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_failed_status'), $order_id ); 
 
			// SEND EMAIL
 			if ($send_email !==FALSE )
			{
				$this->EE->load->library('cartthrob_emails');
				if (is_array($send_email))
				{
					$this->send_email($send_email); 
				}
				else
				{
					$emails = $this->EE->cartthrob_emails->get_email_for_event("failed"); 
					if (!empty($emails))
					{
						foreach ($emails as $email_content)
						{
							$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
						}
					}
					/*
					$this->EE->cartthrob_emails->send_customer_failed_email($customer_info['email_address'], $this->EE->cartthrob->cart->order());
					$this->EE->cartthrob_emails->send_admin_failed_email($this->EE->cartthrob->cart->order());
					*/
				}
 			}

			if ($this->EE->extensions->active_hook('cartthrob_on_fail') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_fail');
				if ($this->EE->extensions->end_script === TRUE) return;
			}
			
		}
		$this->save_cart(); 
		
		
		return NULL; 
	}
	//@TODO add a reference to this function on order model sav_cart_to_order($entry_id, $inventory_processed=FALSE, $discounts_processed=FALSE, $cart = NULL) so that paypal can update this info
	public function set_status_processing($auth, $order_id, $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => TRUE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => FALSE,
				'error_message' => '',
				'transaction_id' => NULL, 
			),
			$auth
		);
		$customer_info = $this->EE->cartthrob->cart->customer_info();

		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized" && $this->get_order_status($order_id)!="pending")
		{
			$this->update_order(array('auth' => $auth));
 			
  			$this->set_order_meta($order_id,'processing', $this->EE->cartthrob->store->config('orders_processing_status'),  element('transaction_id', $auth),element('error_message', $auth) ); 
 
			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_processing_status'), $order_id ); 

 
			if ($send_email !==FALSE )
			{
				$this->EE->load->library('cartthrob_emails');
				if (is_array($send_email))
				{
					$this->send_email($send_email); 
				}
				else
				{
					$emails = $this->EE->cartthrob_emails->get_email_for_event("processing"); 
					if (!empty($emails))
					{
						foreach ($emails as $email_content)
						{
							$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
						}
					}
					/*
					$this->EE->cartthrob_emails->send_customer_processing_email($customer_info['email_address'], $this->EE->cartthrob->cart->order());
					$this->EE->cartthrob_emails->send_admin_processing_email($this->EE->cartthrob->cart->order());
					*/
				}
 			}

			if ($this->EE->extensions->active_hook('cartthrob_on_processing') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_processing');
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		}
		$this->save_cart(); 
		
		
		return NULL; 
	}
	public function set_status_pending($auth, $order_id,   $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => TRUE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => FALSE,
				'error_message' => $this->lang('status_pending'),
				'transaction_id' => '', 
			),
			$auth
		);
		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{
			$this->update_order(array('auth' => $auth));
 			
 			$this->set_order_meta($order_id,'pending', $this->EE->cartthrob->store->config('orders_status_pending'),element('transaction_id', $auth),element('error_message', $auth) ); 
 			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_pending'), $order_id ); 

			if ($send_email !==FALSE )
			{
				if (is_array($send_email))
				{
 					$this->send_email($send_email); 
				}
 			}
		}
		$this->save_cart(); 
		
		
		return NULL; 
	}
	public function set_status_expired($auth, $order_id, $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('status_expired'),
				'transaction_id' => '', 
			),
			$auth
		);
		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{
			$this->update_order(array('auth' => $auth));
 			
 			$this->set_order_meta($order_id,'expired', $this->EE->cartthrob->store->config('orders_status_expired'), element('transaction_id', $auth),element('error_message', $auth)); 
 			$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_expired'), $order_id ); 

			if ($send_email !==FALSE )
			{
				if (is_array($send_email))
				{
 					$this->send_email($send_email); 
				}
 			}
		}
		$this->save_cart(); 
		
		
		return NULL; 
	}
	public function set_status_canceled($auth, $order_id,  $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('status_canceled'),
				'transaction_id' => '', 
			),
			$auth
		);

		$this->update_order(array('auth' => $auth));
		
		$this->set_order_meta($order_id, 'canceled', $this->EE->cartthrob->store->config('orders_status_canceled'),element('transaction_id', $auth),element('error_message', $auth) ); 
		$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_canceled'), $order_id ); 

		if ($send_email !==FALSE )
		{
			if (is_array($send_email))
			{
				$this->send_email($send_email); 
			}
		}
		
 		$this->save_cart(); 
		
 
		return NULL; 
	}
	
	public function set_status_offsite($auth, $order_id,  $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => TRUE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => FALSE,
				'error_message' => $this->lang('status_offsite'),
				'transaction_id' => '', 
			),
			$auth
		);

		$this->update_order(array('auth' => $auth));
		
		$this->set_order_meta($order_id, 'offsite', $this->EE->cartthrob->store->config('orders_status_offsite'),element('transaction_id', $auth),element('error_message', $auth) ); 
		$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_offsite'), $order_id ); 

		if ($send_email !==FALSE )
		{
			if (is_array($send_email))
			{
				$this->send_email($send_email); 
			}
			else
			{
				$emails = $this->EE->cartthrob_emails->get_email_for_event("offsite"); 
				if (!empty($emails))
				{
					foreach ($emails as $email_content)
					{
						$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
					}
				}
			}
		}
		
 		$this->save_cart(); 
		
 
		return NULL; 
	}
	public function set_status_voided($auth, $order_id,  $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('status_voided'),
				'transaction_id' => '', 
			),
			$auth
		);

		$this->update_order(array('auth' => $auth));
		
		$this->set_order_meta($order_id,'voided', $this->EE->cartthrob->store->config('orders_status_voided'), element('transaction_id', $auth),element('error_message', $auth) ); 
		$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_voided'), $order_id ); 

		if ($send_email !==FALSE )
		{
			if (is_array($send_email))
			{
				$this->send_email($send_email); 
			}
		}
		$this->save_cart(); 
		
		
		
		return NULL; 
	}
	public function set_status_refunded($auth, $order_id,  $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('status_refunded'),
				'transaction_id' => '', 
			),
			$auth
		);
		$this->update_order(array('auth' => $auth));
		
		$this->set_order_meta($order_id,'refunded', $this->EE->cartthrob->store->config('orders_status_refunded'),element('transaction_id', $auth),element('error_message', $auth) ); 
		$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_refunded'), $order_id ); 

		if ($send_email !==FALSE )
		{
			if (is_array($send_email))
			{
				$this->send_email($send_email); 
			}
			else
			{
				$emails = $this->EE->cartthrob_emails->get_email_for_event("refunded"); 
				if (!empty($emails))
				{
					foreach ($emails as $email_content)
					{
						$this->EE->cartthrob_emails->send_email($email_content, $this->EE->cartthrob->cart->order()); 
					}
				}
			}
		}
		$this->save_cart(); 
		
		
		return NULL; 
	}
	public function set_status_reversed($auth, $order_id,   $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => $this->lang('status_reversed'),
				'transaction_id' => '', 
			),
			$auth
		);
		
		$this->update_order(array('auth' => $auth));
		
		$this->set_order_meta($order_id,'reversed', $this->EE->cartthrob->store->config('orders_status_reversed'), element('transaction_id', $auth),element('error_message', $auth)); 
		$this->set_purchased_items_status($this->EE->cartthrob->store->config('purchased_items_status_reversed'), $order_id ); 

		if ($send_email !==FALSE )
		{
			if (is_array($send_email))
			{
				$this->send_email($send_email); 
			}
		}
		$this->save_cart(); 
		
		
 		return NULL; 
	}
 
	/**
	 * gateway_order_update
	 *
	 * @param array $auth_array 
	 * @param string $order_id 
	 * @return void
	 * @author Chris Newton
	 * @since 1.0.0
	 */
	public function gateway_order_update($auth, $order_id, $return_url = NULL, $send_email=NULL)
	{
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => '',
				'transaction_id' => '', 
			),
			$auth
		);
		

		if ($this->get_order_status($order_id) != "completed" && $this->get_order_status($order_id) != "authorized")
		{        
			/////// AUTHORIZED
			if ($auth['authorized'] )
			{     
					$this->set_status_authorized($auth, $order_id,   $send_email); 
	 		}
			///////// DECLINED
			elseif ($auth['declined'])
			{            
				$this->set_status_declined($auth, $order_id,  $send_email); 
			}
			////////// PROCESSING
			elseif ($auth['processing'])
			{       
				$this->set_status_processing($auth, $order_id, $send_email); 
			}
			////////// FAILED
			else
			{        
				$this->set_status_failed($auth, $order_id,  $send_email); 
			}
		}  
 		
		$this->save_cart();
		// REDIRECT
		if ($return_url)
		{
			return $this->final_redirect($return_url); 
		}
	}
	public function final_redirect($return_url= NULL)
	{
		if ($return_url)
		{
			if ( ! preg_match('#^https?://#', $return_url))
			{
				$return_url = $this->EE->functions->create_url($return_url);
			}
			
			$this->EE->functions->redirect($return_url);
			exit; 
		}
		else
		{
			return TRUE; 
		}
	}
 	
	public function hook($hook, $params = NULL)
	{
		//this allows you to call hook('your_hook', $param1, $param2) or hook('your_hook', array($param1, $param2))
		if (func_num_args() > 2)
		{
			$params = func_get_args();
			
			array_shift($params);
		}
		
		if ( ! $this->EE->extensions->active_hook($hook))
		{
			return FALSE;
		}
		
		if ( ! is_null($params))
		{
			if ( ! is_array($params))
			{
				$params = array($params);
			}
			
			array_unshift($params, $hook);
			
			return call_user_func_array(array($this->EE->extensions, 'call'), $params);
		}
		
		return $this->EE->extensions->call($hook);
	}
 	public function jump_form($url, $fields_array = array(), $hide_jump_form=TRUE, $title =FALSE, $overview=FALSE, $submit_text=FALSE, $full_page=TRUE, $hidden_fields_array = array())
	{
		if ($overview ===FALSE)
		{
			$overview = $this->lang('jump_alert');
		}
		if ($title === FALSE)
		{
			$title = $this->lang('jump_header'); 
		}
		if ($submit_text === FALSE)
		{
			$submit_text = $this->lang('jump_finish'); 
		}
		
		
		$return_html = ""; 
		if ($full_page)
		{
			$jump_html[] = "<html><head>
			<script type='text/javascript'>
				window.onload = function(){ document.forms[0].submit(); };
			</script>
			</head><body>";
		}

		if ($hide_jump_form)
		{
			// hiding contents from JS users.
			$jump_html[] =  "<script type='text/javascript'>document.write('<div style=\'display:none\'>');</script>";
		}
		
		if ($full_page)
		{
			$jump_html[] =   "<h1>".$title."</h1>";
			$jump_html[] =   "<p>".$overview."</p>";  
		}
		
		$jump_html[] =  "<form name='jump' id='jump' method='POST' action='".$url."' >"; 
		foreach ($fields_array as $key=> $value)
		{
			if (is_array($value))
			{
				// authorize.net SIM requries the same field be sent over and over for line items. stupid.
				foreach ($value as $subkey=> $subvalue)
				{
					$jump_html[] ="<input type='text' name='".$key."' value='".$subvalue."' />";
				}
			}
			else
			{
				$jump_html[] = "<input type='text' name='".$key."' value='".$value."' />";
				
			}
		}
		foreach ($hidden_fields_array as $key=> $value)
		{
			if (is_array($value))
			{
				foreach ($value as $subkey=> $subvalue)
				{
					$jump_html[] ="<input type='hidden' name='".$key."' value='".$subvalue."' />";
				}
			}
			else
			{
				$jump_html[] = "<input type='hidden' name='".$key."' value='".$value."' />";
				
			}
		}
		$jump_html[] =   "<input type='submit' value='".$submit_text."' />"; 
		$jump_html[] =   "</form>"; 

		if ($hide_jump_form)
		{
			$jump_html[] =   "<script type='text/javascript'>document.write('</div>');</script>";
		}

		if ($full_page)
		{
			$jump_html[]=  "</body></html>";
		}

		// turned this into an array so I could add lines to the above code without 
		// inevitably forgetting .= and thus screwing up my output code. 
		foreach ($jump_html as $line)
		{
			$return_html .=$line;
		}
		return $return_html; 
	}
	/**
	 * xml_to_array
	 *
	 * @param string $xml 
	 * @param string $build_type 
	 * @return array
	 * @author Chris Newton
	 * @since 1.0
	 */
	public function xml_to_array($xml, $build_type = 'basic')
	{
		$this->EE->load->helper('data_formatting');
		return xml_to_array($xml, $build_type);
	}
	
	public function strip_punctuation($text)
	{
		return preg_replace('/[^a-zA-Z0-9\s-_]/', ' ', $text);
	}
	
	/**
	 * Strip punctuation characters from UTF-8 text.
	 *
	 * Characters stripped from the text include characters in the following
	 * Unicode categories:
	 *
	 * 	Separators
	 * 	Control characters
	 *	Formatting characters
	 *	Surrogates
	 *	Open and close quotes
	 *	Open and close brackets
	 *	Dashes
	 *	Connectors
	 *	Numer separators
	 *	Spaces
	 *	Other punctuation
	 *
	 * Exceptions are made for punctuation characters that occur withn URLs
	 * (such as [ ] : ; @ & ? and others), within numbers (such as . , % # '),
	 * and within words (such as - and ').
	 *
	 * Parameters:
	 * 	text		the UTF-8 text to strip
	 *
	 * Return values:
	 * 	the stripped UTF-8 text.
	 *
	 * See also:
	 * 	http://nadeausoftware.com/articles/2007/9/php_tip_how_strip_punctuation_characters_web_page
	 * 
	 * used under license. 
	 */
	/*
	function strip_punctuation( $text )
	{
		//ignore the rest
		$urlbrackets    = '\[\]\(\)';
		$urlspacebefore = ':;\'_\*%@&?!' . $urlbrackets;
		$urlspaceafter  = '\.,:;\'\-_\*@&\/\\\\\?!#' . $urlbrackets;
		$urlall         = '\.,:;\'\-_\*%@&\/\\\\\?!#' . $urlbrackets;

		$specialquotes = '\'"\*<>';

		$fullstop      = '\x{002E}\x{FE52}\x{FF0E}';
		$comma         = '\x{002C}\x{FE50}\x{FF0C}';
		$arabsep       = '\x{066B}\x{066C}';
		$numseparators = $fullstop . $comma . $arabsep;

		$numbersign    = '\x{0023}\x{FE5F}\x{FF03}';
		$percent       = '\x{066A}\x{0025}\x{066A}\x{FE6A}\x{FF05}\x{2030}\x{2031}';
		$prime         = '\x{2032}\x{2033}\x{2034}\x{2057}';
		$nummodifiers  = $numbersign . $percent . $prime;
		
		return preg_replace(
			array(
			// Remove separator, control, formatting, surrogate,
			// open/close quotes.
				'/[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Pi}\p{Pf}]/u',
			// Remove other punctuation except special cases
				'/\p{Po}(?<![' . $specialquotes .
					$numseparators . $urlall . $nummodifiers . '])/u',
			// Remove non-URL open/close brackets, except URL brackets.
				'/[\p{Ps}\p{Pe}](?<![' . $urlbrackets . '])/u',
			// Remove special quotes, dashes, connectors, number
			// separators, and URL characters followed by a space
				'/[' . $specialquotes . $numseparators . $urlspaceafter .
					'\p{Pd}\p{Pc}]+((?= )|$)/u',
			// Remove special quotes, connectors, and URL characters
			// preceded by a space
				'/((?<= )|^)[' . $specialquotes . $urlspacebefore . '\p{Pc}]+/u',
			// Remove dashes preceded by a space, but not followed by a number
				'/((?<= )|^)\p{Pd}+(?![\p{N}\p{Sc}])/u',
			// Remove consecutive spaces
				'/ +/',
			),
			' ',
			$text );
		
		/* this was an attempt at catching the error if PCRE not compiled and falling back. it worked. */
		/*
		try
		{
			set_error_handler(array($this, 'strip_punctuation_error_handler'));
			
			$text = preg_replace(
				array(
				// Remove separator, control, formatting, surrogate,
				// open/close quotes.
					'/[\p{Z}\p{Cc}\p{Cf}\p{Cs}\p{Pi}\p{Pf}]/u',
				// Remove other punctuation except special cases
					'/\p{Po}(?<![' . $specialquotes .
						$numseparators . $urlall . $nummodifiers . '])/u',
				// Remove non-URL open/close brackets, except URL brackets.
					'/[\p{Ps}\p{Pe}](?<![' . $urlbrackets . '])/u',
				// Remove special quotes, dashes, connectors, number
				// separators, and URL characters followed by a space
					'/[' . $specialquotes . $numseparators . $urlspaceafter .
						'\p{Pd}\p{Pc}]+((?= )|$)/u',
				// Remove special quotes, connectors, and URL characters
				// preceded by a space
					'/((?<= )|^)[' . $specialquotes . $urlspacebefore . '\p{Pc}]+/u',
				// Remove dashes preceded by a space, but not followed by a number
					'/((?<= )|^)\p{Pd}+(?![\p{N}\p{Sc}])/u',
				// Remove consecutive spaces
					'/ +/',
				),
				' ',
				$text
			);
		}
		catch(Exception $e)
		{	
			restore_error_handler();
			
			if (preg_match('/Compilation failed: support for \\\P, \\\p, and \\\X has not been compiled/', $e->getMessage()))
			{
			 	return preg_replace('/[^a-zA-Z0-9\s-_]/', ' ', $text);
			}
			else
			{
				trigger_error($e->getMessage());
			}
		}
		
		restore_error_handler();
		
		return $text;
	}
	
	public function strip_punctuation_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
	{
		throw new Exception($errstr, $errno);
	}
	*/
}