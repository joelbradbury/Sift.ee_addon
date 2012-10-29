<?php 
class Cartthrob_sage_s extends Cartthrob_payment_gateway
{
	public $title = 'sage_server_title';
	public $affiliate = 'sage_affiliate'; 
	public $overview = 'sage_overview';
	public $language_file = TRUE;
 	public $settings = array(
		array(
			'name' => 'sage_payment_page_style', 
			'short_name' => 'profile', 
			'type' => 'radio',  
			'default' => 'NORMAL', 
			'options' => array(
				'NORMAL' => 'sage_normal',
				'LOW' => 'sage_minimal_formatting'
				),
		),
		array(
			'name' =>  'mode',
			'short_name' => 'mode', 
			'type' => 'radio',  
			'default' => 'test', 
			'options' => array(
				'simulator' => 'simulator',
				'test' => 'test',
				'live' => 'live'
				),
		),
		array(
			'name' => 'sage_vendor_name',
			'short_name' => 'vendor_name', 
			'type' => 'text',
		),
	);
	
	public $required_fields = array(
		'first_name',
		'last_name',
		'address',
		'city',
		'zip',
		'country_code'
	);
	
	
	public $fields = array(
		'first_name'           ,
		'last_name'            ,
		'address'              ,
		'address2'             ,
		'city'                 ,
		'state'                ,
		'zip'                  ,
		'country_code'         ,
		'shipping_first_name'  ,
		'shipping_last_name'   ,
		'shipping_address'     ,
		'shipping_address2'    ,
		'shipping_city'        ,
		'shipping_state'       ,
		'shipping_zip'         ,
		'shipping_country_code',
		'phone'                ,
		'email_address'        ,
 		); 
		
	public $hidden = array('description','currency_code');
	
	/**
	 * process_payment
	 *
 	 * @param string $credit_card_number 
 	 * @return mixed | array | bool An array of error / success messages  is returned, or FALSE if all fails.
	 * @author Chris Newton
	 * @access public
	 * @since 1.0.0
	 */
	public function process_payment($credit_card_number)
	{

 		if ($this->plugin_settings('mode') == "test")
		{
 			$this->_host = "https://test.sagepay.com/gateway/service/vspserver-register.vsp";
			
		}
		elseif ($this->plugin_settings('mode') == "simulator")
		{
			$this->_host = "https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorRegisterTx";
		}
		else
		{
			$this->_host = "https://live.sagepay.com/gateway/service/vspserver-register.vsp";
		}
		
		$auth['authorized'] 	=	FALSE; 
		$auth['declined']		=	FALSE; 
		$auth['failed']			=	TRUE; 
		$auth['error_message']	= 	NULL; 
		$auth['transaction_id']	=	NULL;
		
		/*
		// find out if this needs SSL 
		if (!function_exists('openssl_open'))
		{
			$auth['error_message']	= $this->lang('gateway_requires_ssl'); 
			return $auth;
		}
		*/
		
		$basket=""; 
			
		if ($this->order('items'))
		{
			$basket = (count($this->order('items'))+2).":"; 
 
			foreach ($this->order('items') as $row_id => $item)
			{
 				$basket .= str_replace(":","",$item['title']) .":";
				$basket .= $item['quantity'] .":";
				$basket .= number_format($item['price'],2,'.','').":";
				$basket .= ":";
				$basket .= number_format($item['price'],2,'.','').":";
				$basket .= number_format(($item['price']*$item['quantity']),2,'.','').":";
 
			}
			$basket .= 'Shipping:----:----:----:----:';
			$basket .= number_format($this->order('shipping'), 2, '.', '').":";
			$basket .= 'VAT/Tax:----:----:----:----:';
			$basket .= number_format($this->order('tax'), 2, '.', '');
		}
 
		$country_code = $this->order('country_code') ? $this->alpha2_country_code($this->order('country_code')) : "GB"; 
		$shipping_country_code = $this->order('shipping_country_code') ? $this->alpha2_country_code($this->order('shipping_country_code')) : $country_code; 
		
		$post_array = array(
			'VPSProtocol' 				=> "2.23",
			'TxType'					=> 'PAYMENT',
			'Vendor'					=> $this->plugin_settings('vendor_name'),
			'VendorTXCode'				=> $this->order('entry_id')."_".time(), // needs a unique ID for this transaction. 
			'Amount'					=> number_format($this->total(),2,'.',''),
			'Currency'					=> ($this->order('currency_code') ? $this->order('currency_code') : "GBP"),
			'Description'				=> ($this->order('description') ? $this->order('description') : "Purchase from ".$this->order('site_name')),
			'BillingSurname'			=> $this->order('last_name'),
			'BillingFirstnames'			=> $this->order('first_name'),
			'BillingAddress1'			=> $this->order('address'),
			'BillingAddress2'			=> $this->order('address2'),
			'BillingCity'				=> $this->order('city'),
			'BillingPostCode'			=> $this->order('zip'),
			'BillingCountry'			=> $country_code,
			'BillingPhone'				=> preg_replace('/[^0-9-]/', '', $this->order('phone')),
			'DeliverySurname'			=> ($this->order('shipping_last_name') ? $this->order('shipping_last_name') : $this->order('last_name')),
			'DeliveryFirstnames'		=> ($this->order('shipping_first_name') ? $this->order('shipping_first_name') : $this->order('first_name')),
			'DeliveryAddress1'			=> ($this->order('shipping_address') ? $this->order('shipping_address') : $this->order('address')),
			'DeliveryAddress2'			=> ($this->order('shipping_address2') ? $this->order('shipping_address2') : $this->order('address2')),
			'DeliveryCity'				=> ($this->order('shipping_city') ? $this->order('shipping_city') : $this->order('city')),
			'DeliveryPostCode'			=> ($this->order('shipping_zip') ? $this->order('shipping_zip') : $this->order('zip')),
			'DeliveryCountry'			=> $shipping_country_code,
			'CustomerEMail'				=> $this->order('email_address'),
			'Basket'					=> $basket,
			'NotificationURL'			=> $this->get_notify_url(ucfirst(get_class($this)),'s'),
		); 


		// We don't want to pass the state data to eWay unless it has 2 characters and is a us state. They don't accept any non-us state values
		if ("US" != $post_array['DeliveryCountry'])
		{
		    $post_array['DeliveryState']  = "";
		}
		else
		{
			$post_array['DeliveryState'] = strtoupper($this->order('shipping_state') ? $this->order('shipping_state') : $this->order('state'));
		}
		if ("US" != $post_array['BillingCountry'])
		{
		    $post_array['BillingState']  = ""; 
		}
		else
		{
		    $post_array['BillingState'] = strtoupper($this->order('state')); 
		}
		
		$post_array['Profile']  = $this->plugin_settings('profile'); 

		$data = 	$this->data_array_to_string($post_array);
		
		$connect = 	$this->curl_transaction($this->_host,$data); 
		

		if (!$connect)
		{
			$auth['failed']			= TRUE;
			$auth['authorized']		= FALSE;
			$auth['declined']		= FALSE;
			$auth['error_message']	= $this->lang('curl_gateway_failure'); 
			return $auth; 
		}
		
		$transaction =  $this->split_url_string($connect, "\r\n");
		$next_url = explode("NextURL=", $connect);

		if (!empty($next_url[1]))
		{
			$next_url = str_replace("\r\n","",$next_url[1]);
		}

		if (!is_array($transaction))
		{
			$auth['failed']			= TRUE;
			$auth['authorized']		= FALSE;
			$auth['declined']		= FALSE;
			$auth['error_message']	= $this->lang('sage_failed');
			
			return $auth;
		}
		
		if ("OK" != strtoupper($transaction['Status']))
		{
			switch(strtoupper($transaction['Status']))
			{
				case "MALFORMED":
					$auth['error_message'] = $this->lang('sage_malformed'); 
					$auth['error_message'] .= $transaction['StatusDetail']; 
					break; 
				case "INVALID":
					$auth['error_message'] = $this->lang('sage_invalid');
					$auth['error_message'] .= $transaction['StatusDetail']; 
					break;
				case "ERROR":
					$auth['error_message'] = $this->lang('sage_error');
					break;
				default:
					$auth['error_message'] = $this->lang('sage_default');
			}
			
			$auth['failed']			= TRUE;
			$auth['authorized']		= FALSE;
			$auth['declined']		= FALSE;
			$auth['transaction_id']	= NULL; 
			return $auth; 
		}
 
  		$this->update_order(array('sage_key' => $transaction['SecurityKey']));
		$this->gateway_exit_offsite(NULL, $next_url); exit;
	}
 
	// END
	/**
	 * payment_notification
	 *
	 * @return void
	 * @author Chris Newton
	 * @since 1.0
	 **/
	function s($post)
	{
		$auth['authorized'] 	=	FALSE; 
		$auth['declined']		=	FALSE; 
		$auth['failed']			=	TRUE; 
		$auth['error_message']	= 	NULL; 
		$auth['transaction_id']	=	NULL;
		
		
 		if (!empty($post['VendorTxCode']))
		{
			list($order_id) = explode("_", $post['VendorTxCode']); 
			
			$this->relaunch_cart_snapshot($order_id); 	
		}
		else
		{
			die($this->lang('sage_default')); 
		}
		
		if (strpos( $this->order('return'), 'http') === 0)
		{
			$return_url =  $this->order('return'); 
		}
		else
		{
			$return_url = $this->create_url($this->order('return'));
		}
		
 		
		if ("OK" == strtoupper($post['Status']))
		{
			$tmp = array(
				'VPSTxId'			=>	$post['VPSTxId'],
				'VendorTxCode'		=>	$post['VendorTxCode'],
				'Status'  			=>	$post['Status'],
				'TxAuthNo'			=>	$post['TxAuthNo'],
				'VendorName' 		=>	strtolower($this->plugin_settings('vendor_name')),
				'AVSCV2'			=>	$post['AVSCV2'],
				'SecurityKey'		=>	$this->order('sage_key'),
				'AddressResult'		=>	$post['AddressResult'],
				'PostCodeResult'	=>	$post['PostCodeResult'],
				'CV2Result'			=>	$post['CV2Result'],
				'GiftAid'			=>	$post['GiftAid'],
				'3DSecureStatus'	=>	$post['3DSecureStatus'],
				'CAVV'				=>	(!empty($post['CAVV'])?$post['CAVV']: ""),
				'AddressStatus'		=>	(!empty($post['AddressStatus'])?$post['AddressStatus']: ""),
				'PayerStatus'		=>	(!empty($post['PayerStatus'])?$post['PayerStatus']: ""),
				'CardType'			=>	$post['CardType'],
				'Last4Digits'		=>	$post['Last4Digits']
				); 
			
			if (empty($tmp['AddressStatus']))
			{
				unset ($tmp['AddressStatus']);
				unset ($tmp['PayerStatus']); 
			}
			$md5 = implode("",$tmp);
			
			$md5hash = strtoupper(md5($md5));
			
			if ($md5hash != strtoupper($post['VPSSignature']))
			{
				$auth['authorized'] 	=	FALSE; 
				$auth['declined']		=	FALSE; 
				$auth['failed']			=	TRUE; 
				$auth['error_message']	= 	$this->lang('sage_signature_not_valid'); 
				$auth['transaction_id']	=	NULL;
				
				$this->gateway_order_update($auth,$this->order('order_id'));

				// SAGE requires that we output this stuff. 
				@ob_clean();
				header("Content-type: text/plain");
				echo "Status=INVALID\r\n";
				echo "RedirectURL=".$return_url."\r\n";
				@ob_flush();
				exit; 
			}
			$auth['authorized'] 	=	TRUE; 
			$auth['declined']		=	FALSE; 
			$auth['failed']			=	FALSE; 
			$auth['error_message']	= 	NULL; 
			$auth['transaction_id']	=	trim($post['VPSTxId'], "{}"); 
			// "Auth:".$post['TxAuthNo']."_Tx:".$post['VPSTxId']."_Vnd:".$post['VendorTxCode']."_Sec:".$_SESSION['cartthrob']['sage_key'];
			
			
			$this->gateway_order_update($auth,$this->order('order_id'));
 			
			// SAGE requires that we output this stuff. 
			@ob_clean();
			header("Content-type: text/plain");
			echo "Status=OK\r\n";
			echo "RedirectURL=".$return_url."\r\n";
			@ob_flush();
			exit; 
 
		}
		else
		{
			
			// SAGE requires that we output this stuff. 
			@ob_clean();
			header("Content-type: text/plain");
			
			switch(strtoupper($post['Status']))
			{
				case "NOTAUTHED":
					$auth['error_message'] = $this->lang('sage_notauthed');
					echo "Status=OK\r\n";
					break; 
				case "ABORT":
					$auth['error_message'] =  $this->lang('transaction_cancelled');  
					$this->set_status_canceled($auth, $this->order('entry_id'),  FALSE); 	
					$this->save_cart();
 					echo "Status=OK\r\n";
					echo "RedirectURL=".$return_url."\r\n";
					@ob_flush();
					exit;
					break;
				case "REJECTED": 
					$auth['error_message'] =  $this->lang('sage_rejected'); 
					$auth['declined']		=	TRUE; 
					echo "Status=INVALID\r\n";
					break; 
				case "AUTHENTICATED":
					$auth['error_message'] = $this->lang('sage_authenticated');
					echo "Status=OK\r\n";
					break;
				case "REGISTERED":
					$auth['error_message'] =  $this->lang('sage_registered');
					echo "Status=OK\r\n";
					break;
				case "ERROR":
					$auth['error_message'] = $this->lang('sage_error');
					echo "Status=INVALID\r\n";
					break;
				default:
					$auth['error_message'] =  $this->lang('sage_default');
			}
			$auth['authorized'] 	=	FALSE; 
			$auth['transaction_id']	=	NULL;

			$this->gateway_order_update($auth,$this->order('order_id'));
			// SAGE requires that we output this stuff. 
			echo "RedirectURL=".$return_url."\r\n";
			@ob_flush();
			exit; 
			
		}
	}// END
}
// END Class