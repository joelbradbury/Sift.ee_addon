<?php 
///@TODO double check the CT1 version of this. This is wrong.... requires more items in the Post_array to work. 
class Cartthrob_transaction_central extends Cartthrob_payment_gateway
{
	public $title = 'trans_central_title';
 	public $overview = 'trans_central_overview';
	public $language_file = TRUE;
 	public $settings = array(
		array(
			'name' =>  'merchant_id',
			'short_name' => 'merchant_id',
			'type' => 'text'
		),
		array(
			'name' =>   'registration_key',
			'short_name' => 'reg_key',
			'type' => 'text'
		),
		array(
			'name' =>  'tax_type',
			'short_name' => 'tax_type',
			'type' => 'radio',
			'default'	=> '1',
			'options' => array(
				'0' 	=>  'non_taxable', 
				'1' 		=>  'taxable',
				'2' 	=>  'tax_exempt',
			),
		),
 
	);
	
	public $required_fields = array(
		'first_name',
		'last_name',
		'address',
		'city',
		'state',
		'zip',
		'CVV2',
		'credit_card_number',
		'expiration_year',
		'expiration_month'
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
		'card_type'            ,
		'po_number'			   ,
		'credit_card_number'   ,
		'CVV2'                 ,
		'expiration_month'     ,
		'expiration_year'      ,
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
		$post_array = array(
			'MerchantID'	=> $this->plugin_settings('merchant_id'),		// * Number (8): 			Merchant Number assigned by TransFirst
			'RegKey'		=> $this->plugin_settings('reg_key'),			// * Text (16): 			TransFirst Security Key
			'Amount'		=> $this->total(),
			'CardNumber'	=> $credit_card_number,
			'CardHolderName'=> $this->order('first_name') . " ". $this->order('last_name'), 
			'Expiration'	=> str_pad($this->order('expiration_month'), 2, '0', STR_PAD_LEFT) .  $this->year_2($this->order('expiration_year')),
			'CVV2'			=> $this->order('CVV2'),
			'Address'		=> $this->order('address') ." ". $this->order('address2') .' '. $this->order('city') .' '.$this->order('state'),
			'ZipCode'		=> $this->order('zip'),
			'RefID'			=> $this->order('member_id'),
			'SaleTaxAmount'	=> $this->order('tax'),
			'PONumber'		=> $this->order('entry_id'),
			'TaxIndicator'	=> $this->plugin_settings('tax_type'),
		);
		reset($post_array);
		
		$data 		= 	$this->data_array_to_string($post_array);
		$connect 	= 	$this->curl_transaction('https://webservices.primerchants.com/creditcard.asmx/CCSale?',$data);
		
		if (!$connect)
		{
			$resp['authorized'] 	= FALSE;
			$resp['declined']		= FALSE;
			$resp['failed']			= TRUE;
			$resp['error_message'] = $this->lang('curl_gateway_failure');  
			
			return $resp; 
		}
		$parsed		=	$this->convert_response_xml($connect);
		
		if (!empty($parsed['Status']))
		{
			switch (strtolower($parsed['Status']))
			{
				case "authorized":
					$resp['authorized']	= TRUE;
					$resp['transaction_id']	=	$parsed["TransactionIdentifier"];
					break;
				case "declined": 
				case "canceled": 
					$resp['authorized'] 	= FALSE;
					$resp['declined']		= TRUE;
					$resp['failed']			= FALSE;
					$resp['error_message'] = @$parsed["Message"];
					break;
				default:
					$resp['authorized'] 	= FALSE;
					$resp['declined']		= TRUE;
					$resp['failed']			= FALSE;
					$resp['error_message'] 	= $this->lang('trans_central_unexpected');
					break;
			}
		}
		else
		{
			$resp['authorized'] 	= FALSE;
			$resp['declined']		= FALSE;
			$resp['failed']			= TRUE;
			$resp['error_message'] 	= $this->lang('trans_central_no_response');
		}
		return $resp;
	}
	// END Auth
}
// END Class