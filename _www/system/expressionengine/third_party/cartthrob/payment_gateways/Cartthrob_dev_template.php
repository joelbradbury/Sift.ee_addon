<?php
// BASIC SAMPLE TEMPLATE FOR GENERATING PAYMENT PLUGINS.

/* AVAILABLE CUSTOMER FIELDS
 * If your required customer field is not in this list, it will not be processed. 
 * Please contact the CartThrob development team at http://cartthrob.com, to have additional fields added. 

'first_name',
'last_name',
'address',
'address2',
'city',
'state',
'zip',
'description',
'company',
'phone',
'email_address',
'shipping_first_name',
'shipping_last_name',
'shipping_address',
'shipping_address2',
'shipping_city',
'shipping_state',
'shipping_zip',
'expiration_month',
'expiration_year',
'begin_month',
'begin_year',
'bday_month',
'bday_day',
'bday_year',
'CVV2',
'card_code',
'issue_number',
'card_type',
'currency_code',
'country_code',
'shipping_option',
'credit_card_number'

*/

class Cartthrob_dev_template extends Cartthrob_payment_gateway
{
	public $title = 'dev_template_title';
	public $affiliate = '';
	public $overview = 'dev_template_overview';
		// THESE SETTINGS WILL GENERATE THE INPUT FIELDS ON THE PAYMENT CONFIGURE SCREEN
	public $settings = array(
		array(
			'name' => 'Mode', 
			'short_name' => 'mode', 
			'type'	=> 'select',
			'default'	=> 'random',
			'options' => array(
				'random'	=> 'dev_mode_random',
				'always_fail' => 'dev_mode_fail',
				'always_decline' => 'dev_mode_decline', 
				'always_succeed' => 'dev_mode_succeed'
				),  
		)
	);
	// These fields will be required in the checkout form when a payment is submitted to this gateway
	public $required_fields = array(
		'first_name',
		'last_name',
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
		'country_code',
 	);
	
	
	/**
	 * _process_payment function
	 *
 	 * @param string $credit_card_number purchaser's credit cart number
 	 * @access public
	 * @return array $resp an array containing the following keys: authorized, declined, failed, error_message, and transaction_id 
	 * the returned fields can be displayed in the templates using template tags. 
	 **/
	public function process_payment($credit_card_number)
	{
		// DO PAYMENT STUFF
		/* 
		helper functions
		// converts an array to a urlencoded string of name / value pairs.
		$data = 	$this->data_array_to_string($array);
		* 
		// connects to curl. pass in the curl server url, and a url encoded data string... whatever data is required by whoever you're sending it to
		$connect = 	$this->curl_transaction($curl_server,$data);
		* 
		// Splits a URL encoded string of name / value pairs into an array.
		$transaction =  $this->split_url_string($connect);
		* 
		// converts XML to an array. 
		$transaction = $this->convert_response_xml($connect);
		* 
		// for 3-d secure and other offsite payment gateways, you'll want to use this. 
		$redirect_url = $this->get_notify_url(__CLASS__, 'my_return_processing_method_name' );
		
		//retrive input
		$data = $this->input_post('something');
		$data = $this->input_get('something');
		
		//convert a language key
		$this->lang('key');
		*/
		
		// THESE ARRAY KEYS WILL USE DEFAULTS IF NOT SET. THEY ARE USED TO DISPLAY MESSAGING IN TEMPLATES & TO PASS DATA TO THE ORDERS BLOG
		// IF THIS IS NOT BLANK, TRANS IS GOOD; DECLINED & FAILED BELOW ARE IGNORED

		$random_responses = $this->random_response(); 
		$resp['authorized'] = $random_responses['bool1'];

		// OTHERWISE THE PLUGIN WILL REDIRECT BASED ON THE FOLLOWING CONDITIONS (in this order)
		$resp['declined']		=	 $random_responses['bool2'];
		// A FAILED RESPONSE MEANS THAT THE REASON USUALLY HAS TO DO WITH THE GATEWAY EXPERIENCING A PROBLEM
		$resp['failed']			=	 $random_responses['bool3'];
		// THIS ERROR MESSAGE CAN BE DISPLAYED IN THE TEMPLATE AS NECESSARY
		$resp['error_message']	=	$random_responses['error_message'];
		
		// THE TRANS ID (if available) IF NO TRANSID IS RETURNED A TIME STAMP IS USED. 
		$resp['transaction_id']	=	$random_responses['transaction_id'];
		//var_dump($random_responses);var_dump($resp); exit();
		
		return $resp;
	}
	
	/**
	 * _random_response
	 *
	 * this generates random booleans, error_messages, and transaction ids for testing purposes. 
	 * do not use this function with a real payment gateway.
	 * 
	 * @return array
	 * @since 1.0.0
	 * @author Chris Newton
	 */
	private function random_response()
	{
		$return_data=array();

		$bools_array = array(TRUE,FALSE);
		$errors_array= array(
			$this->lang('dev_template_error_1'),
			$this->lang('dev_template_error_2'),
			$this->lang('dev_template_error_3'),
			$this->lang('dev_template_error_4'),
		);
		
		$ids_array = array(rand(10000000,99999999),rand(10000000,99999999));
		
		$return_data['bool1'] = $bools_array[array_rand($bools_array)];
		$return_data['bool2'] = $bools_array[array_rand($bools_array)];
		
		if ($return_data['bool1'])
		{
			$return_data['bool3'] = FALSE;
		}
		else
		{
			$return_data['bool3'] = TRUE;
			
		}
		
		switch($this->plugin_settings('mode'))
		{
			case "always_fail":
				$return_data['transaction_id'] ="NULL"; 
				$return_data['bool2'] = FALSE; 
				$return_data['bool3'] = TRUE; 
				$return_data['bool1'] = FALSE; 
				$return_data['error_message'] = $errors_array[array_rand($errors_array)];
			break;
			case "always_succeed": 
				$return_data['bool2'] = FALSE; 
				$return_data['bool3'] = FALSE; 
				$return_data['bool1'] = TRUE;
				$return_data['transaction_id'] =$ids_array[array_rand($ids_array)];
				$return_data['error_message'] = NULL; 
			break;
			
			case "always_decline":
				$return_data['transaction_id'] =NULL; 
				$return_data['bool2'] = TRUE; 
				$return_data['bool3'] = FALSE; 
				$return_data['bool1'] = FALSE;
				$return_data['error_message'] = $errors_array[array_rand($errors_array)];
			break;
			default: 
				$return_data['transaction_id'] = $ids_array[array_rand($ids_array)];
				$return_data['error_message'] = $errors_array[array_rand($errors_array)];
			
		}
		return $return_data; 
	}
	// END 
}// END CLASS

/* End of file cartthrob.dev_template.php */
/* Location: ./system/modules/payment_gateways/cartthrob.dev_template.php */