<?php 
class Cartthrob_paypal_express extends Cartthrob_payment_gateway
{
	public $title = 'paypal_express_title';
	public $affiliate = 'paypal_express_affiliate'; 
	public $overview = "paypal_express_overview"; 
	public $settings = array(
		array(
			'name' =>  'paypal_express_api_username',
			'short_name' => 'api_username', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' =>  'paypal_express_api_password',
			'short_name' => 'api_password', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' =>  'paypal_express_signature',
			'short_name' => 'api_signature', 
			'type' => 'text', 
			'default' => '', 
		),
		
		array(
			'name' => "paypal_express_sandbox_api_username",
			'short_name' => 'test_username', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' => "paypal_express_sandbox_api_password",
			'short_name' => 'test_password', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' => "paypal_express_sandbox_signature",
			'short_name' => 'test_signature', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' =>  'mode',
			'short_name' => 'mode', 
			'type' => 'radio', 
			'default' => 'test',
			'options' => array(
					'test'=> "sandbox",
					'live'=> "live"
				),
		),
		
		array(
			'name' => "paypal_express_allow_note", 
			'note'	=> 'paypal_express_allow_note_note',
			'short_name' => 'allow_note', 
			'type' => 'radio',
			'default' => 'no', 
			'options' => array(
				'no' => 'no', 
				'yes' => 'yes'
			)
		),
		array(
			'name' => "paypal_express_show_item_id", 
			'short_name' => 'show_item_id', 
			'type' => 'radio',
			'default' => 'yes', 
			'options' => array(
				'no' => 'no', 
				'yes' => 'yes'
			)
		),
		array(
			'name' => "paypal_express_show_item_options", 
			'short_name' => 'show_item_options', 
			'type' => 'radio',
			'default' => 'no', 
			'options' => array(
				'no' => 'no', 
				'yes' => 'yes'
			)
		),
		array(
			'name' => 'paypal_express_advanced_settings_header',
			'short_name' => 'advanced_settings_header',
			'type' => 'header',
		),
		array(
			'name' => 'paypal_account_preferences', 
			'short_name' => 'solutiontype', 
			'type' => 'radio',
			'default' => 'Mark',
			'options' => array(
				'Sole' => 'paypal_sole',
				'Mark' => 'paypal_mark'
			)
		),
		array(
			'name' => 'paypal_display_billing_page', 
			'short_name' => 'display_billing_page', 
			'type' => 'radio',
			'default' => 'Login',
			'note'	=> 'paypal_display_billing_page_note',
			'options' => array(
				'Login' => 'paypal_show_login',
				'Billing' => 'paypal_show_credit'
			)
		),
		array(
			'name' => "paypal_express_shipping_settings", 
			'short_name' => 'shipping_settings', 
			'type' => 'select',
			'note'	=> 'paypal_express_no_shipping_note',
			'default' => 'editable_shipping', 
			'options' => array(
				'hide_shipping'		=> 'paypal_express_hide_shipping_address',
				'editable_shipping'	=> 'paypal_express_editable_shipping',
				'static_shipping'	=> 'paypal_express_static_shipping',
				'paypal_shipping'	=> 'paypal_express_paypal_shipping',
			)
		),
		array(
			'name' => "paypal_express_payment_action",
			'short_name' => 'payment_action', 
			'type' => 'radio', 
			'default' => 'Sale',
			'options'	=> array(
				'Sale' => 'sale',
				'Authorization' => 'authorization'
				) 
		),
 
 	);
	
	public $required_fields = array(
	);
	
	public $fields = array(
		'first_name',
		'last_name',
		'address',
		'address2',
		'city',
		'state',
		'zip',
		'country_code',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_address',
		'shipping_address2',
		'shipping_city',
		'shipping_state',
		'shipping_zip',
		'shipping_country_code',
		'phone',
		'email_address',
		);
	public $hidden = array('currency_code'); 
 	public $paypal_server; 
	public $API_UserName; 
	public $API_Password; 
	public $API_Signature; 
	public $paypal_offsite; 

	public function initialize()
	{
		if ($this->plugin_settings('mode') == "test") 
		{
			// Sandbox server for use with API signatures;use for testing your API
			//$this->_paypal_server = "https://api-3t.sandbox.paypal.com/nvp"; 
			
			// it seems that PayPal requires an https connection these days... so the next bit's a bit irrelevant
			$this->paypal_server = (!function_exists('openssl_open') ? "https://api.sandbox.paypal.com/nvp":"https://api.sandbox.paypal.com/nvp" ); 
			$this->API_UserName = urlencode($this->plugin_settings('test_username'));
			$this->API_Password = urlencode($this->plugin_settings('test_password'));
			$this->API_Signature = urlencode($this->plugin_settings('test_signature'));
			$this->paypal_offsite = (!function_exists('openssl_open') ? "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=": "https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token=");
			
		}
		else
		{
			// PayPal "live" production server for usewith API signatures
			$this->paypal_server = (!function_exists('openssl_open') ? "https://api-3t.paypal.com/nvp" : "https://api-3t.paypal.com/nvp" ); 
			$this->paypal_offsite =(!function_exists('openssl_open') ?  "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=" :  "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=" ); 
			$this->API_UserName = urlencode($this->plugin_settings('api_username'));
			$this->API_Password = urlencode($this->plugin_settings('api_password'));
			$this->API_Signature = urlencode($this->plugin_settings('api_signature'));
		}
	}
 	/**
	 * process_payment
	 *
	 * @param string $credit_card_number 
	 * @return mixed | array | bool An array of error / success messages  is returned, or FALSE if all fails.
	 * @author Chris Newton
	 * @access private
	 * @since 1.0.0
	 */
	public function process_payment($credit_card_number)
	{
		$auth['authorized'] 	= 	FALSE; 
		$auth['declined']		=	FALSE; 
		$auth['failed']			=	FALSE; 
		$auth['error_message']	= 	NULL; 
		$auth['transaction_id']	=	NULL;
		
		$post_array = $this->assemble_post_array($method ="SetExpressCheckout", $version = "65.1"); 
		$data = 	$this->data_array_to_string($post_array);
		$connect = 	$this->curl_transaction($this->paypal_server,$data); 
		if (!$connect)
		{
			exit($this->lang('curl_gateway_failure'));
		}
		$transaction =  $this->split_url_string($connect);
 		$token =""; 
 
		if (is_array($transaction))
		{
			if ("SUCCESS" == strtoupper($transaction['ACK']) || "SUCCESSWITHWARNING" == strtoupper($transaction["ACK"])) 
			{
				$token = urldecode($transaction["TOKEN"]);
			} 
			else  
			{  
				if (!empty($transaction['L_LONGMESSAGE0']))
				{
					$auth['failed'] = TRUE; 
					$auth['error_message']	=$transaction['L_LONGMESSAGE0']. " ". $transaction['L_ERRORCODE0']; 
				}
				return $auth; 
			}
		}
		else
		{
			$auth['failed'] = TRUE; 
			$auth['error_message']	= $this->lang('paypal_express_did_not_respond') ;
			return $auth;
		}
		
		$this->gateway_exit_offsite(NULL, $this->paypal_offsite.$token.'&useraction=commit'); exit;
	}// END
	

	function cancel_payment($post)
	{
		$auth = array(
			'authorized' 	=> FALSE,
			'error_message'	=> $this->lang('paypal_express_you_cancelled'),  
			'failed'		=> TRUE,
			'declined'		=> FALSE,
			'transaction_id'=> NULL, 
			);
		$this->gateway_order_update($auth, $this->order('entry_id'), $this->order('return')); 	
		exit;
	}
	// @TODO need to add methods for handling refunds like PayPal standard. 
	
	function confirm_payment($post)
	{
		
		$auth = array(
			'authorized' 	=> FALSE,
			'error_message'	=> NULL,
			'failed'		=> TRUE,
			'declined'		=> FALSE,
			'transaction_id'=> NULL, 
			);
		
		$post_array = array(
			'METHOD'	=> 'GetExpressCheckoutDetails',
			'VERSION'	=> urlencode("65.1"),
			'PWD'		=> $this->API_Password,
			'USER'		=> $this->API_UserName,
			'SIGNATURE'	=> $this->API_Signature,
			'TOKEN'		=> $post['token'],
			);
		
		$data = 	$this->data_array_to_string($post_array);
		
		$connect = 	$this->curl_transaction($this->paypal_server,$data); 
		
		if (!$connect)
		{
			exit( $this->lang('curl_gateway_failure'));
		}
		
		$transaction =  $this->split_url_string($connect);
 		$payer_id = NULL; 
		if (is_array($transaction))
		{
			if ("SUCCESS" == strtoupper($transaction['ACK']) || "SUCCESSWITHWARNING" == strtoupper($transaction["ACK"])) 
			{
				$payer_id =	$transaction['PAYERID'];
			} 
			else  
			{
				if (!empty($transaction['L_LONGMESSAGE0']))
				{
					$auth['failed'] = TRUE; 
					
					$auth['error_message']	=$transaction['L_LONGMESSAGE0']. " ". $transaction['L_ERRORCODE0']; 
				}
				$this->gateway_order_update($auth, $this->order('entry_id'),$this->order('return')); 	
				exit;
			}
		}
		else
		{
			$auth['error_message']	= $this->lang('paypal_express_did_not_respond') ;
			$this->gateway_order_update($auth, $this->order('entry_id'),$this->order('return')); 	
			exit;
		}
		
		$post_array = $this->assemble_post_array($method="DoExpressCheckoutPayment", $version = "65.1", $post['token'], $payer_id); 
		
		$data = 	$this->data_array_to_string($post_array);
		$connect = 	$this->curl_transaction($this->paypal_server,$data); 
		if (!$connect)
		{
			exit( $this->lang('curl_gateway_failure'));
		}
		$transaction =  $this->split_url_string($connect);
		
		if (is_array($transaction))
		{
			if ("SUCCESS" == strtoupper($transaction['ACK']) || "SUCCESSWITHWARNING" == strtoupper($transaction["ACK"])) 
			{
				$auth = array(
					'authorized' 	=> TRUE,
					'error_message'	=> NULL,
					'failed'		=> FALSE,
					'declined'		=> FALSE,
					'transaction_id'=> $transaction['PAYMENTINFO_0_TRANSACTIONID'], 
	 				);
	
				//$this->handle_subscriptions( $post['token']); 
			} 
			else  
			{
				if (!empty($transaction['L_LONGMESSAGE0']))
				{
					$auth['failed'] = TRUE; 
					
					$auth['error_message']	=$transaction['L_LONGMESSAGE0']. " ". $transaction['L_ERRORCODE0']; 
				}
			}
		}
		else
		{
			$auth['error_message']	= $this->lang('paypal_express_did_not_respond') ;
		}
		$this->gateway_order_update($auth, $this->order('entry_id'), $this->order('return')); 	
		exit;
	}
	
	function assemble_post_array($method="SetExpressCheckout", $version = "65.1", $token = NULL, $payer_id= NULL)
	{
		if ($this->plugin_settings('allow_note')=="yes")
		{
			$allow_note = 1; 
		}
		else
		{
			$allow_note = 0; 
		}

		// added to handle situations when a person has an alternate total and goes to PayPal. 
		if ($method == "DoExpressCheckoutPayment" && $this->order('pp_alt_total'))
		{
			$total = $this->order('pp_alt_total'); 
		}
		else
		{
			if ($this->total())
			{
				$total = $this->total();
			}
			else
			{
				$total = $this->order('total');
			}
		}
		$info = array(
			'PAYMENTREQUEST_0_AMT'					=> round($total,2), 
			'PAYMENTREQUEST_0_ITEMAMT'				=> round($this->order('subtotal'),2),
			'PAYMENTREQUEST_0_TAXAMT'				=> round($this->order('tax'),2),
	 		'PAYMENTREQUEST_0_SHIPPINGAMT'			=> round($this->order('shipping'),2),
			'PAYMENTREQUEST_0_SHIPTONAME'			=> substr(($this->order('shipping_first_name') 		? $this->order('shipping_first_name') . " ". $this->order('shipping_last_name') : $this->order('first_name') ." ". $this->order('last_name')),0, 31),
			'PAYMENTREQUEST_0_SHIPTOSTREET'			=> substr(($this->order('shipping_address') 			? $this->order('shipping_address') : $this->order('address')), 0, 99),
			'PAYMENTREQUEST_0_SHIPTOSTREET2'		=> substr(($this->order('shipping_address2') 			? $this->order('shipping_address2') : $this->order('address2')), 0, 99),
			'PAYMENTREQUEST_0_SHIPTOCITY'			=> substr(($this->order('shipping_city') 				? $this->order('shipping_city') : $this->order('city')), 0, 40),
			'PAYMENTREQUEST_0_SHIPTOSTATE'			=> ($this->order('shipping_state')				? strtoupper($this->order('shipping_state')) : strtoupper($this->order('state'))),
			'PAYMENTREQUEST_0_SHIPTOZIP'			=> ($this->order('shipping_zip') 				? $this->order('shipping_zip') : $this->order('zip')),                                                                           
			'PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'	=> $this->alpha2_country_code(($this->order('shipping_country_code') 		? $this->order('shipping_country_code') : $this->order('country_code'))),
			'PAYMENTREQUEST_0_SHIPTOPHONENUM'		=> $this->order('phone'),
			'EMAIL'									=> $this->order('email_address'),
			); 
			
 		$post_array = array(
			'METHOD'								=> $method,
			'VERSION'								=> urlencode($version),
			'PWD'  									=> $this->API_Password,
			'USER' 									=> $this->API_UserName,
			'SIGNATURE'								=> $this->API_Signature,
			'PAYMENTREQUEST_0_PAYMENTACTION'		=> $this->plugin_settings('payment_action'),
			'RETURNURL'								=> $this->get_notify_url(ucfirst(get_class($this)),'confirm_payment'),
			'CANCELURL'								=> $this->get_notify_url(ucfirst(get_class($this)),'cancel_payment') ,
			'PAYMENTREQUEST_0_CURRENCYCODE'			=> ($this->order('currency_code') ? $this->order('currency_code') : "USD"),
			'ALLOWNOTE'								=> $allow_note,
			'CHANNELTYPE'							=> 'Merchant', // non ebay item
			'PAYMENTREQUEST_0_ALLOWEDPAYMENTMETHOD'	=> 'InstantPaymentOnly',
		);
		

		// paypal won't just let us send the language. lame
		switch($this->order('language'))
		{
			case "EN":
				switch ($this->order('country_code'))
				{
					case "GBR": $post_array['LOCALECODE'] = "GB"; break;
					case "AUS": $post_array['LOCALECODE'] = "AU"; break; 
					case "USA": $post_array['LOCALECODE'] = "US"; break;
					case "CAN": $post_array['LOCALECODE'] = "CA"; break;
					default: $post_array['LOCALECODE'] = "US"; break;
				}
				break;
			case "DE": 
				switch ($this->order('country_code'))
				{
					case "AUT": $post_array['LOCALECODE'] = "AT"; break;
					case "DEU": $post_array['LOCALECODE'] = "DE"; break; 
					default: $post_array['LOCALECODE'] = "DE"; break;
				}
				break;
			case "ES": 
				$post_array['LOCALECODE'] = "ES"; 
				break;
			case "NL": 
				switch ($this->order('country_code'))
				{
					case "BEL": $post_array['LOCALECODE'] = "BE"; break;
					case "NED": $post_array['LOCALECODE'] = "NL"; break; 
					default: $post_array['LOCALECODE'] = "NL"; break;
				}
				break;
			case "IT": 
				$post_array['LOCALECODE'] = "IT"; 
				break;
			case "FR": 
				$post_array['LOCALECODE'] = "FR"; 
				break;
			case "ZH": 
				$post_array['LOCALECODE'] = "CN"; 
				break;
			case "PL": 
				$post_array['LOCALECODE'] = "PL"; 
				break;
			default: $post_array['LOCALECODE'] = "US"; 
		}
		
		switch( $this->plugin_settings('shipping_settings')  )
		{
			case "hide_shipping"; 
				$post_array['ADDROVERRIDE'] 	= 0; 
				$post_array['NOSHIPPING']		= 1; 
				unset($info['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'],
					$info['PAYMENTREQUEST_0_SHIPTOZIP'],
					$info['PAYMENTREQUEST_0_SHIPTOSTATE'],
					$info['PAYMENTREQUEST_0_SHIPTOCITY'],
					$info['PAYMENTREQUEST_0_SHIPTOSTREET2'],
					$info['PAYMENTREQUEST_0_SHIPTOSTREET'],
					$info['PAYMENTREQUEST_0_SHIPTONAME']
					); 
				break;
			case "paypal_shipping"; 
				unset($info['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'],
					$info['PAYMENTREQUEST_0_SHIPTOZIP'],
					$info['PAYMENTREQUEST_0_SHIPTOSTATE'],
					$info['PAYMENTREQUEST_0_SHIPTOCITY'],
					$info['PAYMENTREQUEST_0_SHIPTOSTREET2'],
					$info['PAYMENTREQUEST_0_SHIPTOSTREET'],
					$info['PAYMENTREQUEST_0_SHIPTONAME']
					); 
				$post_array['ADDROVERRIDE'] 	= 1; 
				$post_array['NOSHIPPING']		= 0; 
				break;
			case "static_shipping": 
				$post_array['ADDROVERRIDE'] 	= 1; 
				$post_array['NOSHIPPING']		= 0; 
			break; 
			
			case "editable_shipping": 
			default: 
				$post_array['ADDROVERRIDE'] 	= 0; 
				$post_array['NOSHIPPING']		= 0; 
 				break;
		}
		
		
		foreach ($info as $key => $value)
		{
			if (empty($value))
			{
				unset($info[$key]); 
			}
		}
		
		$post_array = array_merge($info, $post_array); 

		// making it so you can checkout with a CC
		if ($this->plugin_settings('solutiontype') == "Sole")
		{
			$post_array['SOLUTIONTYPE'] = "Sole"; 
			$post_array['LANDINGPAGE'] = ($this->plugin_settings('display_billing_page')? $this->plugin_settings('display_billing_page'): "Login"); 
		}
		// if it's not a SALE type, then we can't demand immediate payment
		if ($this->plugin_settings('payment_action')!="Sale")
		{
			unset($post_array['PAYMENTREQUEST_0_ALLOWEDPAYMENTMETHOD']); 
		}

		$item_array = array(); 

		foreach ($this->order('items') as $row_id => $item)
		{
			if ($item['price'] == 0) // paypal doesn't like 0 priced items, but it doesn't mind negative items
			{
				continue; 
			}
			if (!isset($count))
			{
				$count=0;
			}

			$item_array["L_PAYMENTREQUEST_0_NAME".$count]			= substr($item['title'], 0, 126);
 			$item_array["L_PAYMENTREQUEST_0_AMT".$count]			= round($item['price'], 2); 
			$item_array["L_PAYMENTREQUEST_0_QTY".$count] 			= $item['quantity']; 

			if ( $this->plugin_settings('show_item_options') == "yes" && !empty($item['item_options']))
			{
				$item_options = ""; 
				foreach($item['item_options'] as $key=> $value)
				{
					$item_options .= $key.": ". $value. ", "; 
				}
				$item_array["L_PAYMENTREQUEST_0_DESC".$count] 		= substr($item_options, 0, 126);  
			}
			if ($this->plugin_settings('show_item_id') == "yes")
			{
				if (empty($item['entry_id']))
				{
					$item_array["L_PAYMENTREQUEST_0_NUMBER".$count] 		= "000"; 
				}
				else
				{
					$item_array["L_PAYMENTREQUEST_0_NUMBER".$count] 		= $item['entry_id']; 
				}
 			}
			$count++;
		}	

		if ($this->order('discount') > 0)
		{
			// oh god, the discount's greater than the subtotal. WHAT DO WE DO NOW!?!?!
			// oh that's right paypal can't handle it. so we'll just send one line item with the
			// entire cart contents *sigh*
			if ($this->order('discount') > $this->order('subtotal'))
			{
				// killing off item array, shipping and tax. 
				$item_array= array(); 
				unset($post_array['PAYMENTREQUEST_0_SHIPPINGAMT']); 
				unset($post_array['PAYMENTREQUEST_0_TAXAMT']); 
				unset($post_array['PAYMENTREQUEST_0_ITEMAMT']); 
	 		}
			else
			{ 
				$post_array['PAYMENTREQUEST_0_ITEMAMT']				= round(($this->order('subtotal')-$this->order('discount')), 2); 
			
				$item_array["L_PAYMENTREQUEST_0_NAME".$count]			= $this->lang('discount'); 
	 			$item_array["L_PAYMENTREQUEST_0_AMT".$count]			= -round($this->order('discount'), 2);
				$item_array["L_PAYMENTREQUEST_0_QTY".$count] 			= 1;
				if ($this->plugin_settings('show_item_id') == "yes")
				{
					$item_array["L_PAYMENTREQUEST_0_NUMBER".$count] 			= "000"; 
				}
			}
		}
 
		if (!empty($_POST['PR']) || $this->order('pp_alt_total'))
		{
			if ($this->total())
			{
				$this->update_order(array('pp_alt_total' => $this->total()));
			}
	  		
			$item_array= array(); 
			unset($post_array['PAYMENTREQUEST_0_SHIPPINGAMT']); 
			unset($post_array['PAYMENTREQUEST_0_TAXAMT']); 
			unset($post_array['PAYMENTREQUEST_0_ITEMAMT']);
		}

		$post_array = array_merge($item_array, $post_array);
		
		if ($token)
		{
			$post_array['TOKEN'] = $token; 
		}
		if ($payer_id)
		{
			$post_array['PAYERID']	= $payer_id;
			$post_array['IPADDRESS'] = $_SERVER['SERVER_NAME'];
		}
		return $post_array; 
	}
	
	public function handle_subscriptions($token)
	{
		$subscription_items = array(); 
		if ($this->order('items'))
		{
			foreach ($this->order('items') as $row_id => $item)
			{
				if (!empty($item['meta']['subscription']))
				{
					$subscription_items[] = $item; 
				}
			}
		}
		if ($this->order('subscription'))
		{
			$sub['quantity'] = 1; 
			$sub['price'] = $this->order('total'); 
			$sub['meta']['subscription'] = $this->order('subscription'); 
 			$subscription_items = array($sub);  
		}
		if (empty($subscription_items))
		{
			return FALSE; 
		}
		
		foreach($subscription_items as $key => $item)
		{
			for ($i=0; $i < $item['quantity']; $i++)
			{
				$sub_total = $item['price']; 

				$sub_data = array(); 
				foreach ($item['meta']['subscription'] as $key=> $value)
				{
					$sub_data[$key] = $value; 
				}
				
				$recurrent_billing_auth = $this->create_recurrent_billing($sub_total, $credit_card_number, $sub_data, $token); 

				$save_data['description'] 	= $this->subscription_info($sub_data, 'subscription_name', $item['title']); 
				$save_data['timestamp']		= time(); 
				$save_data['order_id']		= $this->order('entry_id'); 
				$save_data['member_id']		= $this->order('member_id');
				
				if ($recurrent_billing_auth['authorized'])
				{
					$save_data['sub_id']			= $recurrent_billing_auth['transaction_id']; 
					$save_data['status']			= "open"; 
				}
				else
				{
					$save_data['status']			= "failed"; 
				}
				$save_data['gateway'] = ucfirst(get_class($this)); 

				$this->update_vault_data($save_data); 
			}
		}
	}
	function create_recurrent_billing($subscription_amount, $credit_card_number, $sub_data, $token)
	{
		$auth['authorized']	 	= FALSE; 
		$auth['declined'] 		= FALSE; 
		$auth['transaction_id']	= NULL;
		$auth['failed']			= TRUE; 
		$auth['error_message']	= "";
		
 
		if (!empty($sub_data['subscription_interval_units']))
		{
 			if ($sub_data['subscription_interval_units'] !="months" 
				&& $sub_data['subscription_interval_units'] !="days" 
				&& $sub_data['subscription_interval_units'] !="weeks"
				&& $sub_data['subscription_interval_units'] !="semimonths"
				&& $sub_data['subscription_interval_units'] != "years")
			{
				$sub_data['subscription_interval_units'] = "months"; 
			}
		}
 		// authorize.net does not allow intervals longer than 12 for month based subs
		if ($sub_data['subscription_interval_units'] == "months" && $sub_data['subscription_interval'] > 12)
		{
			$sub_data['subscription_interval'] = 12;
		}
		if ($sub_data['subscription_interval_units'] == "days" && $sub_data['subscription_interval'] > 365)
		{
			$sub_data['subscription_interval'] = 365;
		}
		if ($sub_data['subscription_interval_units'] == "weeks" && $sub_data['subscription_interval'] > 52)
		{
			$sub_data['subscription_interval'] = 52;
		}
		if ($sub_data['subscription_interval_units'] == "semimonths" && $sub_data['subscription_interval'] > 24)
		{
			$sub_data['subscription_interval'] = 24;
		}
		if ($sub_data['subscription_interval_units'] == "years" && $sub_data['subscription_interval'] > 1)
		{
			$sub_data['subscription_interval'] = 1;
		}
		switch ($sub_data['subscription_interval'])
		{
			case "months": $units= "Month"; break;
			case "days": $units= "Day"; break;
			case "weeks": $units= "Week"; break;
			case "years": $units= "Year"; break;
			case "semimonths": $units= "SemiMonth"; break;
			default: $units = "Month"; 
		}
 
		date_default_timezone_set("UTC"); 
		$timestamp = strtotime("now");
		$date = date('Y-m-d', $timestamp).'T'.date('H:i:s', $timestamp).'Z';
		
		
		$post_array= array(
			'TOKEN'					=> $token, 
			'AMT'					=> round($subscription_amount,2),
			'CURRENCYCODE'			=> $this->order('currency_code'),
			'PROFILESTARTDATE'		=> urlencode($date),
			'BILLINGPERIOD'			=> $units,
			'BILLINGFREQUENCY'		=> $sub_data['subscription_interval'],
			'DESC'					=> $sub_data['description'],
			'EMAIL'					=> $this->order('email_address'),
			);

		$data = $this->data_array_to_string($post_array); 
		
		$connect = 	$this->curl_transaction($this->paypal_server,$data); 
		
		if (!$connect)
		{
			$auth['error_message'] = $this->lang('curl_gateway_failure');
 
			return $auth; 
		}
		
		if (!$connect)
		{
			exit( $this->lang('curl_gateway_failure'));
		}
		$transaction =  $this->split_url_string($connect);
		
		if (is_array($transaction))
		{
			if (!empty($transaction['PROFILEID']) && ("SUCCESS" == strtoupper($transaction['ACK']) || "SUCCESSWITHWARNING" == strtoupper($transaction["ACK"]))) 
			{
				$auth = array(
					'authorized' 	=> TRUE,
					'error_message'	=> NULL,
					'failed'		=> FALSE,
					'declined'		=> FALSE,
					'transaction_id'=> $transaction['PROFILEID'], 
	 				);
			} 
			else  
			{
				if (!empty($transaction['L_LONGMESSAGE0']))
				{
					$auth['failed'] = TRUE; 
					
					$auth['error_message']	=$transaction['L_LONGMESSAGE0']. " ". $transaction['L_ERRORCODE0'];  
				}
			}
		}
		else
		{
			$auth['error_message']	= $this->lang('paypal_express_did_not_respond') ;
		}
 
		return $auth;
	}
}// END Class