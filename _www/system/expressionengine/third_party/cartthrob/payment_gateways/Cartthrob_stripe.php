<?php

class Cartthrob_stripe extends Cartthrob_payment_gateway
{
	public $title = 'stripe_title';
	public $affiliate = '';
	public $overview = 'stripe_overview';
		// THESE SETTINGS WILL GENERATE THE INPUT FIELDS ON THE PAYMENT CONFIGURE SCREEN
	public $settings = array(
		array(
			'name' => 'mode', 
			'short_name' => 'mode', 
			'type'	=> 'select',
			'default'	=> 'test',
			'options' => array(
				'test'	=> 'stripe_mode_test',
				'live' => 'stripe_mode_live',
			),
		),
		array(
			'name' => 'stripe_private_key',
			'short_name' => 'api_key_test_secret',
			'type' => 'text',
		),
		array(
			'name' => 'stripe_api_key',
			'short_name' => 'api_key_test_publishable',
			'type' => 'text',
		),
		array(
			'name' => 'stripe_live_key_secret',
			'short_name' => 'api_key_live_secret',
			'type' => 'text',
		),
		array(
			'name' => 'stripe_live_key',
			'short_name' => 'api_key_live_publishable',
			'type' => 'text',
		),

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
		'credit_card_number',
		'CVV2',
		'expiration_year',
		'expiration_month',
 	);
	
	public $nameless_fields = array(
		'credit_card_number',
		'CVV2',
		'expiration_year',
		'expiration_month',
	);
	
	public function initialize()
	{
		$key = ($this->plugin_settings('mode') === 'live') ? $this->plugin_settings('api_key_live_publishable') : $this->plugin_settings('api_key_test_publishable');
		
		$total_in_cents = $this->get_total() * 100;
		
		$this->form_extra = '
		<script type="text/javascript" src="https://js.stripe.com/v1/"></script>
		<script type="text/javascript">Stripe.setPublishableKey("'.$key.'");</script>
		<script type="text/javascript" src="'.$this->theme_path().'third_party/cartthrob/scripts/ender.min.js"></script>
		<script type="text/javascript" src="'.$this->theme_path().'third_party/cartthrob/scripts/cartthrob-tokenizer.js"></script>
		<script type="text/javascript">
		CartthrobTokenizer.init(function(){
			Stripe.createToken({
				number: CartthrobTokenizer.val("#credit_card_number"),
				cvc: CartthrobTokenizer.val("#CVV2"),
				exp_month: CartthrobTokenizer.val("#expiration_month"),
				exp_year: CartthrobTokenizer.val("#expiration_year")
			},
			'.$total_in_cents.',
			function(status, response){
				if (response.error) {
					CartthrobTokenizer.errorHandler(response.error.message);
					CartthrobTokenizer.submissionState = false;
				} else {
					CartthrobTokenizer.addHidden("stripeToken", response["id"])
							  .addHidden("credit_card_number", response.card["last4"])
							  .addHidden("card_type", response.card["type"])
							  .submitHandler();
				}
			})
		});
		</script>
		';
	}
	
	public function process_payment($ignored)
	{
		//if there's no token it means that the end user doesn't have javascript enabled
		if (FALSE === ($token = $this->input_post('stripeToken')))
		{
			return array(
				'authorized' => FALSE,
				'failed' => TRUE,
				'declined' => FALSE,
				'error_message' => $this->lang('stripe_javascript_required'),
				'transaction_id' => '',
			);
		}
		
		$key = ($this->plugin_settings('mode') === 'live') ? $this->plugin_settings('api_key_live_secret') : $this->plugin_settings('api_key_test_secret');
		
		try
		{
			require_once $this->vendor_path().'stripe/Stripe.php';
			
			Stripe::setApiKey($key);
			
			$charge = Stripe_Charge::create(array(
				'amount' => $this->total() * 100,
				'currency' => 'usd',//this is currently the only valid currency
				'card' => $token,
				'description' => $this->order('entry_id')
			));
			
			//this is what's available
			//$charge->id
			//$charge->amount
			//$charge->created
			//$charge->currency
			//$charge->description
			//$charge->fee
			//$charge->livemode
			//$charge->object
			//$charge->paid
			//$charge->refunded
			//$charge->card->country
			//$charge->card->cvc_check
			//$charge->card->exp_month
			//$charge->card->exp_year
			//$charge->card->last4
			//$charge->card->object
			//$charge->card->type
			
			if ($charge->paid === FALSE)
			{
				return array(
					'authorized' => FALSE,
					'failed' => FALSE,
					'declined' => TRUE,
					'error_message' => $this->lang('stripe_card_declined'),
					'transaction_id' => $charge->id,
				);
			}
			else if ($charge->paid === TRUE)
			{
				return array(
					'authorized' => TRUE,
					'failed' => FALSE,
					'declined' => FALSE,
					'error_message' => '',
					'transaction_id' => $charge->id,
				);
			}
			
			return array(
				'authorized' => FALSE,
				'failed' => TRUE,
				'declined' => FALSE,
				'error_message' => $this->lang('stripe_unknown_error'),
				'transaction_id' => '',
			);
		}
		catch(Exception $e)
		{
			return array(
				'authorized' => FALSE,
				'failed' => TRUE,
				'declined' => FALSE,
				'error_message' => $e->getMessage(),
				'transaction_id' => '',
			);
		}
	}
	// END 
}// END CLASS

/* End of file cartthrob.dev_template.php */
/* Location: ./system/modules/payment_gateways/cartthrob.dev_template.php */