<?php 
class Cartthrob_worldpay_redirect extends Cartthrob_payment_gateway
{
	public $title = 'worldpay_redirect_title';
 	public $overview = 'worldpay_redirect_overview';
 	public $settings = array(
		array(
			'name' =>  'worldpay_redirect_installation_id', 
			'short_name' => 'installation_id', 
			'type' => 'text', 
			'default' => '', 
		),
		array(
			'name' =>  'mode',
			'short_name' => 'test_mode',
			'type' => 'radio',
			'default' => 'test',
			'options' => array(
				'test' => 'test',
				'live' => 'live'
			)
		),
		array(
			'name'=>'worldpay_default_template',
			'short_name'=>'order_complete_template',
			'type'=>'select',
			'attributes' => array(
				'class' 	=> 'templates',
				),
		),
	);
	
	public $required_fields = array(
 		'first_name',
		'last_name',
		'country_code',
		'zip',
		'city',
		'address',
		'email_address'
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
	
	public $hidden = array('currency_code'); 
 
	public function initialize()
	{
		//'https://secure-test.wp3.rbsworldpay.com/wcc/purchase'
		$this->_submit_url		 	= 'https://secure-test.worldpay.com/wcc/purchase';
		$this->_test_mode 			= 100;
		
		if($this->plugin_settings('test_mode') == 'live') 
		{
			// https://secure.wp3.rbsworldpay.com/wcc/purchase
			$this->_submit_url 		= 'https://secure.worldpay.com/wcc/purchase';
			$this->_test_mode 		= 0;
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
		$post_array = array(
			'name'				=> $this->order('first_name').' '.$this->order('last_name'),
			'address'			=> $this->order('address')."\n"
									.$this->order('address2')
									."\n".$this->order('city')
									."\n".$this->order('state'),
			'postcode'			=> $this->order('zip'),
			'country'			=> $this->alpha2_country_code($this->order('country_code')),
			'tel'				=> $this->order('phone'),
			'email'				=> $this->order('email_address'),
			'withDelivery'		=> 'false',
			'delvName'			=> $this->order('shipping_first_name').' '
									.$this->order('shipping_last_name'),
			'delvAddress'		=> 	$this->order('shipping_address')
									."\n".$this->order('shipping_address2')
									."\n".$this->order('shipping_city')
									."\n".$this->order('shipping_state'),
			'delvPostcode' 		=> $this->order('shipping_zip'),
			'delvCountry'		=> $this->alpha2_country_code(($this->order('shipping_country_code')? $this->order('shipping_country_code') : $this->order('country_code'))),
			
			'instId'			=> $this->plugin_settings('installation_id'),
			'cartId' 			=> $this->order('entry_id'),
			'currency' 			=> ($this->order('currency_code') ? $this->order('currency_code') : "GBP" ),
			'amount'			=> $this->total(),
			'desc' 				=> ($this->order('description') ?  $this->order('description') : $this->order('order_id')),
			'testMode' 			=> $this->_test_mode,
			'fixContact'		=> 'true',
			'MC_callback'		=> $this->response_script(ucfirst(get_class($this))),  
		);
 
		$this->gateway_exit_offsite($post_array, $this->_submit_url); 
		exit;
	}
	// END
	/**
	 * worldpay_success
	 * 
	 * @param array $post 
	 * @return void
	 * @author Chris Newton
	 */		
	function extload($post) 
	{		
		$auth  = array(
			'authorized' 	=> FALSE,
			'error_message'	=> NULL,
			'failed'		=> TRUE,
			'processing' 	=> FALSE,
			'declined'		=> FALSE,
			'transaction_id'=> NULL 
			);
		
		$this->relaunch_cart(NULL, $post['cartId']);
		
		$template = ""; $fetched = "";
		// get the template path (templategroup/template)
		if ($this->order('return') && !strstr($this->order('return'), 'http'))
		{
			$template =  $this->order('return');
			$fetched = $this->fetch_template($template); 
		}
		if (!$fetched)
		{
			$template = $this->plugin_settings('order_complete_template'); 
			$fetched = $this->fetch_template($template); 
		}
		
	
		if (empty($post['transId'])) 
		{
			$auth  = array(
				'authorized' 	=> FALSE,
				'error_message'	=> $this->lang('worldpay_transaction_failure'),
				'failed'		=> TRUE,
				'processing' 	=> FALSE,
				'declined'		=> FALSE,
				'transaction_id'=> NULL 
				);
		}
		elseif ($post['transStatus'] == 'Y') 
		{
			$auth = array(
				'authorized' 	=> TRUE,
				'error_message'	=> NULL,
				'failed'		=> FALSE,
				'declined'		=> FALSE,
				'transaction_id'=> $post['transId'], 
				);
		} 
		elseif ($post['transStatus'] == 'C') 
		{
			$auth = array(
				'authorized' 	=> FALSE,
				'error_message'	=> $this->lang('transaction_cancelled'),
				'failed'		=> FALSE,
				'declined'		=> TRUE,
				'transaction_id'=> $post['transId'], 
				);
		}
		$this->gateway_order_update($auth,$this->order('order_id') );
		
 		if ($fetched)
		{
			echo $this->parse_template( $fetched ); 
		}
		else
		{
			echo $this->lang("template_not_found"). "(". $template.")"; 
		}
		exit;
	}//END
}
// END Class