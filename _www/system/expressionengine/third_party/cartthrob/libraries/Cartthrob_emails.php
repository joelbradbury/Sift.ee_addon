<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 * @property Template $TMPL
 */
class Cartthrob_emails
{
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->library('cartthrob_loader');
		
		$this->EE->cartthrob_loader->setup($this);
		
		if ( ! isset($this->EE->TMPL))
		{
			$this->EE->load->library('template', NULL, 'TMPL');
		}
		
		$this->EE->load->library('template_helper');
	}
	
	public function get_email_for_event($event, $status_start = NULL, $status_end = NULL)
	{
		$emails = array();

	 	if ($this->EE->cartthrob->store->config('notifications'))
		{
			foreach ($this->EE->cartthrob->store->config('notifications') as $notification)
			{
				if ($event && !empty($notification['email_event']) && $notification['email_event'] == $event)
				{
					$emails[] = array(
						'from' 				=> $notification['email_from'],
						'from_name' 		=> $notification['email_from_name'],
						'to' 				=> $notification['email_to'],
						'message_template' 			=> $notification['email_template'],
						'subject' 			=> $notification['email_subject'],
						'plaintext' 		=> ($notification['email_type'] == "text") ? TRUE: FALSE, 
					);	
				}
				elseif(empty($notification['email_event']) && $status_start && ($notification['status_start'] == "ANY" || $notification['status_start'] == $status_start))
				{
					if ($status_end && ($notification['status_end'] == "ANY" || $notification['status_end'] == $status_end))
					{
						$emails[] = array(
							'from' 				=> $notification['email_from'],
							'from_name' 		=> $notification['email_from_name'],
							'to' 				=> $notification['email_to'],
							'message_template' 			=> $notification['email_template'],
							'subject' 			=> $notification['email_subject'],
							'plaintext' 		=> ($notification['email_type'] == "text") ? TRUE: FALSE, 
						);
					}
				} 
			}
		}

		return $emails; 
	}
	
	/**
	 * deprecated, use send_email option b instead
	 */
	public function send_email_from_array($array = array(), $order_data = array())
	{
		$array = array_merge(
			array(
				'from' => NULL,
				'from_name' => NULL,
				'to' => NULL,
				'message' => '',
				'subject' => '',
				'plaintext' => FALSE, 
			),
			$array
		);
		
 		$this->send_email($array['from'], $array['from_name'], $array['to'], $array['subject'], $array['message'], $array['plaintext'], $order_data); 
	}
	
	/**
	 * Utility function, sends an email using the EE Core email class.
	 *
	 * Two ways to use:
	 *
	 * a) send_email($from, $from_name, $to, $subject, $message, $plaintext, $variables, $constants, $message_template)
	 *
	 * b) send_email(array('from' => $from, 'from_name' => $from_name, 'to' => $to, 'subject' => $subject, 'message' => $message, 'plaintext' => $plaintext), $variables, $message_template)
	 *
	 * @access public
	 * @param string $from
	 * @param string $from_name
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
 	public function send_email($from = NULL, $from_name = '', $to = '', $subject = '', $message = '', $plaintext = FALSE, $variables = array(), $constants = NULL, $message_template = NULL)
	{
		//this is the alternate way of using send_email,
		//passing in an array of params as the first arg
		//variables as second arg
		if (is_array($from))
		{
			$args = func_get_args();
			
			$params = $args[0];
			
			$variables = (isset($args[1])) ? $args[1] : array();
			
			$from_name = '';
			
			foreach ($params as $key => $value)
			{
				${$key} = $value;
			}
		}
		
		$this->EE->load->library('email');
		
		$mailtype = ($plaintext) ? 'text' : 'html';
		
		if ( ! $from)
		{
			$from = $this->EE->config->item('webmaster_email');
		}
		
		if ( ! $from_name)
		{
			$from_name = $this->EE->config->item('webmaster_name');
		}
		
		if (is_null($constants))
		{
			if ( ! isset($variables['order_id']))
			{
				$variables['order_id'] = ''; 
			}
			
			//default behavior, for backwards compat.
			$constants = array(
				'ORDER_ID' => $variables['order_id'],
				'{order_id}' => $variables['order_id'],
			);
		}
		
		/// Added 5.18, due to issue with Lea A's site choking on parsing the second email
		unset($this->EE->TMPL);
		$this->EE->load->library('template', NULL, 'TMPL');
		
		$from = $this->parse($from, $variables);
		$from_name = $this->parse($from_name, $variables);
		$to = $this->parse($to, $variables);
 		$subject = $this->parse($subject, $variables, $constants);
		$message = $this->parse($message, $variables, $constants, TRUE, $message_template);
		
		$this->EE->email->clear(); 
		$this->EE->email->initialize(array('mailtype' => $mailtype, 'validate' => TRUE));
		$this->EE->email->from($from, $from_name)
				->to($to)
				->subject($subject)
				->message($message);
		
		$this->EE->email->send();
		$this->EE->email->clear(); 

		
		if ($this->EE->config->item('email_debug') == "y")
		{
			echo "<h2>".$this->EE->lang->line('email_debugger')."</h2>"; 
			echo "<p>".$this->EE->lang->line('email_debugger_warning')."</p>"; 
			echo $this->EE->email->print_debugger();
		}
	}
	
	/**
	 * Parses CONSTANTS, {variables}, and can optionally run the template enging
	 * 
	 * @param string $template            the template to parse
	 * @param array $variables           array('foo' => 'bar')  ==  {foo} => bar
	 * @param array $constants           arrray('FOO' => 'bar')  ==  FOO => bar
	 * @param bool $run_template_engine whether or not to run the full template engine
	 * @param string $template_to_fetch   template_group/template to fetch
	 * 
	 * @return Type    Description
	 */
	public function parse($template, $variables = array(), $constants = array(), $run_template_engine = FALSE, $template_to_fetch = '')
	{
		/// Added 5.06, due to issue with Lea A's site choking on parsing the second email
		/// might have had to do with caching tool
		/// the problem with this method though, is that embeds don't work, and code has to be put in the actual box. 
		#unset($this->EE->TMPL);
		#$this->EE->load->library('template', NULL, 'TMPL');
		/// end new addition
		
		if ($run_template_engine)
		{
			$this->EE->load->library('template_helper');
			
			if ($template_to_fetch && is_string($template_to_fetch))
			{
				$template_info = $this->EE->template_helper->fetch_template($template_to_fetch, TRUE);
				
				foreach ($constants as $key => $value)
				{
					$template_info['template_data'] = str_replace($key, $value, $template_info['template_data']);
				}
				
				$template = $this->EE->template_helper->parse_template($template_info['template_data'], $variables, $template_info['parse_php'], $template_info['php_parse_location'], $template_info['template_type']);
			}
			else
			{
				foreach ($constants as $key => $value)
				{
					$template = str_replace($key, $value, $template);
				}
				
				$template = $this->EE->template_helper->parse_template($template, $variables);
			}
		}
		else
		{
			foreach ($constants as $key => $value)
			{
				$template = str_replace($key, $value, $template);
			}
			
			if ($variables)
			{
				$template = $this->EE->TMPL->parse_variables($template, array($variables));
			}
		}
		
		return $template;
	}

	/**
	 * Send the member order confirmation email
	 *
	 * @access private
	 * @param array $order_data
	 * @return void
	 */
	public function send_confirmation_email($to, $order_data)
	{
		if ($this->store->config('send_confirmation_email'))
		{
			$order_data['order_id'] = $order_data['entry_id'];

			unset($order_data['entry_id']);

			$this->send_email(
				$this->store->config('email_order_confirmation_from'),
				$this->store->config('email_order_confirmation_from_name'),
				$to,
				$this->store->config('email_order_confirmation_subject'),
				$this->store->config('email_order_confirmation'),
				$this->store->config('email_order_confirmation_plaintext'),
				$order_data
			);
 		}
	}
	
	
	public function send_customer_declined_email($to, $order_data){}
	public function send_admin_declined_email($order_data){}

	public function send_customer_processing_email($to, $order_data){}
	public function send_admin_processing_email($order_data){}

	public function send_customer_failed_email($to, $order_data){}
	public function send_admin_failed_email($order_data){}
	
	public function send_low_inventory_email($entry_id, $stock_level)
	{
		$variable_array['entry_id'] = $entry_id;
		
		$constants = array('ENTRY_ID' => $entry_id, 'STOCK_LEVEL' => $stock_level);

		$this->send_email(
			$this->store->config('email_inventory_notification_from'),
			$this->store->config('email_inventory_notification_from_name'),
			$this->store->config('low_stock_email'),
			$this->store->config('email_inventory_notification_subject'),
			$this->store->config('email_inventory_notification'),
			$this->store->config('email_low_stock_notification_plaintext'),
			$variable_array,
			$constants
		);
	}
	
	/**
	 * Send the admin order notification email
	 *
	 * @access private
	 * @param array $order_data
	 * @return void
	 */
	public function send_admin_notification_email($order_data)
	{
		if ($this->store->config('send_email'))
		{
			$order_data['order_id'] = $order_data['entry_id'];

			unset($order_data['entry_id']);

			$this->send_email(
				$this->store->config('email_admin_notification_from'),
				$this->store->config('email_admin_notification_from_name'),
				$this->store->config('admin_email'),
				$this->store->config('email_admin_notification_subject'),
				$this->store->config('email_admin_notification'),
				$this->store->config('email_admin_notification_plaintext'),
				$order_data
			);
 		}
	}
}
