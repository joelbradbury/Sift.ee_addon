<?php 
class Cartthrob_cartthrob_direct extends Cartthrob_payment_gateway
{
	public $title = 'payleap_direct_title';
	public $affiliate = 'payleap_affiliate'; 
	public $overview = 'payleap_overview';
	public $settings = array(
		array(
			'name' => 'username',
			'short_name' => 'username',
			'type' => 'text'
		),
		array(
			'name' => 'password',
			'short_name' => 'password',
			'type' => 'text'
		),
		array(
			'name' => 'payleap_dev_username',
			'short_name' => 'dev_username',
			'type' => 'text'
		),
		array(
			'name' => 'payleap_dev_username',
			'short_name' => 'dev_password',
			'type' => 'text'
		),		
		array(
			'name' => "mode",
			'short_name' => 'mode',
			'type' => 'radio',
			'default' => "no_account",
			'options' => array(
				"no_account" => "payleap_no_account",
				"test"	=> "test",
				"live"	=> "live",
			)
		),
	);
	
	public $required_fields = array(
		'first_name',
		'last_name',
		'address',
		'city',
		'state',
		'zip',
		'phone',
		'email_address',
		'credit_card_number',
		'expiration_year',
		'expiration_month'
	);
	
	public $fields = array(
		'first_name',
		'last_name',
		'address',
		'address2',
		'city',
		'state',
		'zip',
		'phone',
		'email_address',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_address',
		'shipping_address2',
		'shipping_city',
		'shipping_state',
		'shipping_zip',
		'card_type',
		'credit_card_number',
		'CVV2',
		'expiration_year',
		'expiration_month'
	);
	
	public $hidden = array();
	public $card_types = array(
		"mc"	=> "MasterCard",
		"visa"	=> "Visa",
		"discover"	=> "Discover",
		"diners"	=> "Diners",
		"amex"		=> "AMEX"
		);
	
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
			$host	= "https://uat.payleap.com/TransactServices.svc/ProcessCreditCard";
			$username = $this->plugin_settings('dev_username'); 
			$password = $this->plugin_settings('dev_password'); 
 		}
		elseif($this->plugin_settings('mode') == "no_account")
		{
			$resp['authorized']	 	= FALSE; 
			$resp['declined'] 		= FALSE; 
			$resp['transaction_id']	= NULL;
			$resp['failed']			= TRUE; 
			$resp['error_message']	= "payleap_no_account_message";
			return $resp; 
			
		}
		else
		{
			$host = "https://secure1.payleap.com/TransactServices.svc/ProcessCreditCard";
 			$username = $this->plugin_settings('username'); 
			$password = $this->plugin_settings('password');
		}
 		
 		$extdata = "<TrainingMode>F</TrainingMode>
			<Invoice>
				<InvNum>".$this->order('title')."</InvNum>
				<BillTo>
					<Name>".$this->order('first_name') ." ". $this->order('last_name')."</Name>
					<Address>
						<Street>".$this->order('address') . ($this->order('address2') ? " ".$this->order('address2'):"")."</Street>
						<City>".$this->order('city')."</City>
						<State>".$this->order('state')."</State>
						<Zip>".$this->order('zip')."</Zip>
						<Country>".$this->order('country_code')."</Country>
					</Address>
					<Email>".$this->order('email_address')."</Email>
					<Phone>".$this->order('phone')."</Phone>
				</BillTo>
				<Description>Cart payment</Description>
				";
		if ($this->order('items'))
		{
			$extdata .="<Items>"; 
			foreach ($this->order('items') as $row_id => $item)
			{
				$extdata .="<Item>"; 
				$extdata .="<Sku>".$item['entry_id']."</Sku>"; 
				$extdata .="<TotalAmt>".( abs($item['quantity']) * number_format(abs($item['price']),2,'.','') )."</TotalAmt>"; 
 				$extdata .="</Item>"; 
 			}
			$extdata .="</Items>"; 
		}
		$extdata.="
				<ShippingAmt>".$this->order("shipping")."</ShippingAmt>
				<TaxAmt>".$this->order('tax')."</TaxAmt>
				<DiscountAmt>".$this->order('discount')."</DiscountAmt>
				<TotalAmt>".$this->total()."</TotalAmt>
			</Invoice>
			<City>".$this->order("city")."</City>
			<BillToState>".$this->order("state")."</BillToState>
			<BillToPostalCode>".$this->order("zip")."</BillToPostalCode>
			<Email>".$this->order('email_address')."</Email>
			<Phone>".$this->order('phone')."</Phone>
 			";
 		$post_array= array(
			'Username'		=> $username,
			'Password'		=> $password,
			'TransType'		=> "Sale", //Auth
			'NameOnCard'	=> $this->order('first_name') ." ". $this->order('last_name'),
			'CardNum'    	=> $credit_card_number,      
			'ExpDate'		=> str_pad($this->order('expiration_month'), 2, '0', STR_PAD_LEFT).$this->year_2($this->order('expiration_year')),
			'CVNum'			=> $this->order('CVV2'),
			'Amount'		=> $this->total(),
			'PNRef'			=> "",
			'MagData'		=> "",
			"ExtData"		=> $extdata
  			); 
 
 		$transaction = new SimpleXMLElement($this->curl_transaction($host, $this->data_array_to_string($post_array) ));

		$resp['authorized']	 	= FALSE; 
		$resp['declined'] 		= FALSE; 
		$resp['transaction_id']	= NULL;
		$resp['failed']			= TRUE; 
		$resp['error_message']	= "";
		$resp['processing']		= FALSE; 
		
		if (!$transaction)
		{
 			$resp['error_message']	= $this->lang('curl_gateway_failure'); 
			return $resp; 
		}
 		switch($transaction->Result)
		{
			case "0": 
				$resp['authorized']	 	= TRUE; 
				$resp['declined'] 		= FALSE; 
				$resp['transaction_id']	= (string) $transaction->AuthCode;
				$resp['failed']			= FALSE; 
				$resp['error_message']	= "";
				break;
			case "12": 
				$resp['authorized']	 	= FALSE; 
				$resp['declined'] 		= TRUE; 
				$resp['transaction_id']	= NULL;
				$resp['failed']			= FALSE; 
				$resp['error_message']	= (string) $transaction->RespMSG;
				break;
			case "-100": 
				$resp['authorized']	 	= FALSE; 
				$resp['declined'] 		= FALSE; 
				$resp['transaction_id']	= NULL;
				$resp['failed']			= TRUE; 
				$resp['error_message']	= $this->lang('payleap_address_mismatch');
				break;
			default: 
				$resp['authorized']	 	= FALSE; 
				$resp['declined'] 		= FALSE; 
				$resp['transaction_id']	= NULL;
				$resp['failed']			= TRUE; 
				$resp['error_message']	= (string) $transaction->RespMSG;
		}
 
		return $resp; 
 
 	}
 }
// END Class