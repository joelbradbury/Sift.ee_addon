<?php   
$lang = array(
	'stripe_title' => 'Stripe',
	'stripe_affiliate' => '',
	'stripe_overview' => '<p>Stripe requires PHP 5.3 or greater to run. </p>
	
	<p>Stripe relies on JavaScript to submit a payment. This is what
allows it to process a credit card without the number ever having to touch your server, since
the card number is posted directly to Stripe via JavaScript. In order for CartThrob to work
properly, you must not set a custom id for the checkout_form; it must have the default id, which is checkout_form.</p>

<p>The following fields must have blank name attributes, and instead use id attributes for naming:
<code>credit_card_number</code>, <code>CVV2</code>, <code>expiration_month</code>, <code>expiration_year</code>
(ex. <code>&lt;input type="text" name="" id="credit_card_number" /&gt;</code>).
This happens by default when using the {gateway_fields} variable.</p>

<p>Most error messages will happen via Javascript. This means that should a customer enter an incorrect
CC number, they will recieve a JavaScript alert() with an error message. To override this default
behavior, you can write your own JavaScript callback function when an error is encountered.
This callback must be added <i>after</i> your form close, for example:<br><br>
<pre>...

{/exp:cartthrob:checkout_form}

&lt;script type="text/javascript"&gt;
CartthrobStripe.setErrorHandler(function(errorMessage){
	$("#checkout_form div.error").html(errorMessage).show();
});
&lt;/script&gt;
</pre></p>',
	'stripe_mode_test'   => 'Test',
	'stripe_mode_live'     => 'Live',
	'stripe_javascript_required' => 'You must have JavaScript turned on to check out.',
	'stripe_unknown_error' => 'An unknown error has occurred.',
	'stripe_card_declined' => 'The card was declined.',
	'stripe_api_key'		=> 'Test Mode API Key (publishable)',
	'stripe_private_key'		=> 'Test Mode API Key (secret)',
	'stripe_live_key'			=> 'Live Mode API Key (publishable)',
	'stripe_live_key_secret'	=> 'Live Mode API Key (secret)',
	
);