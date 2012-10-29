<?php 
class Cartthrob_eway_3d extends Cartthrob_payment_gateway
{
	public $title = 'eway_title_3d';
	public $customer_id = "87654321"; 
 	public $overview = 'eway_overview_3d';
  	public $settings = array(
		array(
			'name' =>  'eway_customer_id_3d',
			'short_name' => 'customer_id', 
			'type' => 'text', 
			'default' => '87654321', 
		),
		array(
			'name' => 'eway_payment_method_3d',
			'short_name' => 'payment_method', 
			'type' => 'radio', 
			'default' => 'REAL-TIME', 
			'options' => array(
				'REAL-TIME'		    =>'REAL-TIME', 
				'REAL-TIME-CVN' 	=>'REAL-TIME-CVN', 
				'GEO-IP-ANTI-FRAUD'	=>'GEO-IP-ANTI-FRAUD',
				'3D-SECURE'			=>'3D-Secure'
				),
			
		),
		array(
			'name' => 'mode',
			'short_name' => 'test_mode', 
			'type' => 'radio', 
			'default' => 'test',
			'options' => array(
				'test' => 'test',
				'live' => 'live',
				'sandbox' => 'sandbox',
			)
		),
		array(
			'name'	=> 'test_response',
			'short_name'	=> 'test_response',
			'type' => 'select', 
			'default' => '00',
			'options' => array(
				'100' => 'approved',
				'114' => 'declined',
				'130' => 'failed',
				'use_total'=> 'use_total'
			)
		)
	);
	
	public $required_fields = array(
		'first_name',
		'last_name',
		'address',
		'city',
		'zip',
		'credit_card_number',
		'expiration_year',
		'expiration_month',
	);
 
	public $fields = array(
		'first_name',
		'last_name',
		'address',
		'address2',
		'city',
		'zip',
		'country_code',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_address',
		'shipping_address2',
		'shipping_city',
		'shipping_zip',
		'shipping_country_code',
		'phone',
		'email_address',
		'card_type',
		'credit_card_number',
		'expiration_month',
		'expiration_year',
 	);
		
 	public $hidden = array('description');

	public $card_types = NULL;
	
 	public function initialize()
	{
		switch ($this->plugin_settings('payment_method'))
		{
			case "REAL-TIME":
				(($this->plugin_settings('test_mode') != 'live')? 
					$this->_host='https://www.eway.com.au/gateway/xmltest/testpage.asp': 
					$this->_host='https://www.eway.com.au/gateway/xmlpayment.asp');
					break;
				$this->required_fields[] = 'CVV2'; 
			case "REAL-TIME-CVN":
				(($this->plugin_settings('test_mode') != 'live')? 
					$this->_host='https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp': 
					$this->_host='https://www.eway.com.au/gateway_cvn/xmlpayment.asp');
					break;
				$this->required_fields[] = 'CVV2'; 
			case "GEO-IP-ANTI-FRAUD":
				$this->required_fields[] = 'country_code'; 
				$this->required_fields[] = 'CVV2'; 
				
				(($this->plugin_settings('test_mode') != 'live')? 
					$this->_host='https://www.eway.com.au/gateway_cvn/test/xmlbeagle_test.asp':
					$this->_host='https://www.eway.com.au/gateway_cvn/xmlbeagle.asp');
					break;
			case "3D-SECURE": 
			
				unset($this->fields[array_search('expiration_month', $this->fields)]);
				unset($this->fields[array_search('expiration_year', $this->fields)]);
				unset($this->fields[array_search('credit_card_number', $this->fields)]);
				unset($this->fields[array_search('card_type', $this->fields)]);
		
				unset($this->required_fields[array_search('credit_card_number', $this->required_fields)]);
				unset($this->required_fields[array_search('expiration_year', $this->required_fields)]);
				unset($this->required_fields[array_search('expiration_month', $this->required_fields)]);
				
				/*
 				(($this->plugin_settings('test_mode') != 'live')? 
					$this->_host='https://www.eway.com.au/gateway_3d/test/payment_test.asp':
					$this->_host='https://www.eway.com.au/gateway_3d/payment.asp');*/
				$this->_host = "https://www.eway.com.au/gateway_3d/payment.asp"; 
				//$this->_host = "https://www.eway.com.au/gateway/payment.asp"; 
				
					break;
					
				break;
			default: 
				$this->_host = 'https://www.eway.com.au/gateway/xmltest/testpage.asp';
				break;
		}
		
 		if ($this->plugin_settings('test_mode')== "test")
		{
 			$this->customer_id = "87654321";
		}
 		else
		{
			$this->customer_id = $this->plugin_settings('customer_id'); 
		}
 	}
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
		// eWay processes with no decimal values. 
		$total = round($this->total()*100);
		
		if ($this->plugin_settings('test_mode')== "test")
		{
			if ($this->plugin_settings('test_response')!= "use_total")
			{
				$total = $this->plugin_settings('test_response');
			}
			$credit_card_number = "4444333322221111";
		}
		
		if (strlen($this->order('expiration_year') == 4))
		{
			$expiration_year = substr($this->order('expiration_year'), -2);
		}
		else
		{
			$expiration_year = str_pad($this->order('expiration_year'), 2, '0', STR_PAD_LEFT); 
		}
		
		$post_array = array(
			'ewayTotalAmount'					=> $total,
			'ewayCustomerLastName'				=> $this->order('last_name'),	
			'ewayCustomerFirstName'				=> $this->order('first_name'),
			'ewayCustomerEmail'					=> $this->order('email_address'),
			'ewayCustomerAddress'				=> $this->order('address')." ".$this->order('address2') ." ". $this->order('city'),	
			'ewayCustomerPostcode'				=> $this->order('zip'),	
			'ewayCustomerInvoiceDescription'	=> $this->order('description'),				
			'ewayCustomerInvoiceRef'			=> $this->order('entry_id'), 		
			'ewayCardHoldersName'				=> $this->order('first_name')." ".$this->order('last_name'),	
			'ewayCardNumber'					=> $credit_card_number,
			'ewayCardExpiryMonth'				=> str_pad($this->order('expiration_month'), 2, '0', STR_PAD_LEFT), 
			'ewayCardExpiryYear'				=> $expiration_year, 	
			'ewayTrxnNumber'					=> "",
			'ewayOption1'					    => "",
			'ewayOption2'					    => "",
			'ewayOption3'					    => "",
			'ewayCVN'                           => $this->order("CVV2")
		);
		if ($this->plugin_settings('payment_method')== "GEO-IP-ANTI-FRAUD")
		{
			$post_array['ewayCustomerBillingCountry'] = $this->alpha2_country_code($this->order('country_code'));
			$post_array['ewayCustomerIPAddress']	= $this->order('ip_address'); 
		}
		elseif( $this->plugin_settings('payment_method') == "3D-SECURE")
		{
			$post_array = array(
				'ewayCustomerID'		=> $this->customer_id,
				'ewayTotalAmount'		=> $total,
				'ewayCustomerFirstName'	=> $this->order('first_name'),
				'ewayCustomerLastName'	=> $this->order('last_name'),
				'ewayCustomerEmail'		=> $this->order('email_address'),
				'ewayCustomerAddress'	=> $this->order('address')." ".$this->order('address2') ." ". $this->order('city'),
				'ewayCustomerPostcode'	=> $this->order('zip'),
				'ewayCustomerInvoiceDescription'	=> $this->order('description'),
				'ewayCustomerInvoiceRef'	=> $this->order('entry_id'),
				'ewayAutoRedirect'		=> 1,
				'eWAYURL'				=> $this->response_script(ucfirst(get_class($this))),
				#'ewaySiteTitle'			=> "WebActive", // @TODO change  
				'ewayOption1'		=> $this->order('entry_id')
				); 
 				$this->gateway_exit_offsite($post_array, $url = FALSE, $jump_url = $this->_host); 
				exit; 
		}
		$data = "<ewaygateway><ewayCustomerID>" . $this->customer_id  . "</ewayCustomerID>";
		foreach($post_array as $key=>$value)
		{
			$value = str_replace("&", "and", $value); 
			$data .= "<{$key}>{$value}</{$key}>";
		}
		$data .= "</ewaygateway>";

		$connect = 	$this->curl_transaction($this->_host,$data); 
		
		$resp['authorized']					=	FALSE;
		$resp['error_message']				=	NULL;
		$resp['failed']						=	TRUE;
		$resp['declined']					=	FALSE;
		$resp['transaction_id'] 			=	NULL;
		
		if (!$connect)
		{
			$resp['failed']	 				= 	TRUE; 
			$resp['authorized']				=	FALSE;
			$resp['declined']				=	FALSE;
			$resp['error_message']			=	$this->lang('curl_gateway_failure');
			return $resp; 
		}
		$transaction = $this->xml_to_array($connect,'basic'); 
		
		$error = NULL; 
		if (!empty($transaction['ewayResponse']['ewayTrxnStatus']['data']))
		{
			if(strtolower($transaction['ewayResponse']['ewayTrxnStatus']['data'])=="false")
		  	{
				if (!empty($transaction['ewayResponse']['ewayTrxnStatus']['data']))
				{
					$error = $transaction['ewayResponse']['ewayTrxnError']['data'];
				}
				$resp['declined'] 				= TRUE;
				$resp['failed']					= FALSE;
				$resp['error_message'] 			= $this->lang('eway_transaction_error_3d'). " ". $error;

			}
			elseif(strtolower($transaction['ewayResponse']['ewayTrxnStatus']['data'])=="true")
			{
				$resp['declined']		   		 = FALSE;
				$resp['failed']			   		 = FALSE; 
				$resp['authorized']		   		 = TRUE;
				$resp['error_message']	   		 = NULL;
				$resp['transaction_id']    		 = (!empty($transaction['ewayResponse']['ewayTrxnNumber']['data']) ? $transaction['ewayResponse']['ewayTrxnNumber']['data'] : NULL);
			}
			else
			{
				$resp['authorized']				= FALSE;
				$resp['declined']				= FALSE;
				$resp['failed']					= TRUE;
				$resp['error_message'] 			= $this->lang('eway_invalid_response_3d');
			}
		}			

		return $resp;
	}
	// END
	
	public function extload($post)
	{
		$resp['authorized']	 	= FALSE; 
		$resp['declined'] 		= FALSE; 
		$resp['transaction_id']	= NULL;
		$resp['failed']			= TRUE; 
		$resp['error_message']	= "";
		
		if (empty($post['ewayTrxnStatus']) || !isset($post['eWAYoption1']) || !isset($post['ct_action']))
		{
			$resp['error_message']	= $this->lang('eway_invalid_response_3d'); 
			
			$this->gateway_order_update($resp, $post['ct_action'] );
		}
		$order_id = $this->xss_clean($post['eWAYoption1']); 
		$this->relaunch_cart_snapshot($order_id);
		
		if ( strtolower($post['ewayTrxnStatus']) != "true")
		{
			$resp['error_message']	= $post['eWAYresponseText'];
			$this->gateway_order_update($resp, $order_id, $this->order('return') );
		}
 
		$resp['authorized']	 	= TRUE; 
		$resp['declined'] 		= FALSE; 
		$resp['transaction_id']	= $post['ewayTrxnNumber'];
		$resp['failed']			= FALSE; 
		$resp['error_message']	= NULL; 
 
		$this->gateway_order_update($resp, $order_id, $this->order('return'));
		exit; 
		
 	}
}
// END Classv