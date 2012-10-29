<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_core_ee $cartthrob;
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cartthrob
{
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		$this->EE->load->library('cartthrob_loader');
		$this->EE->cartthrob_loader->setup($this);
		
		$this->EE->lang->loadfile('cartthrob');
		
		$this->EE->load->helper(array('security', 'data_formatting', 'credit_card', 'form'));
		
		$this->EE->load->library(array('cartthrob_variables', 'template_helper'));
		
		$this->EE->load->model('product_model');
		$this->EE->product_model->load_products($this->EE->cartthrob->cart->product_ids());
		
		$this->EE->load->helper('debug');
	}
	
	public function __call($method, $args)
	{
		$this->EE->load->library('cartthrob_addons');
		
		if ( ! $this->EE->cartthrob_addons->method_exists($method))
		{
			throw new Exception("Call to undefined method Cartthrob::$method()");
		}
		
		return $this->EE->cartthrob_addons->call($method);
	}
	
	public function delete_from_cart_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_delete_from_cart_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_delete_from_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->EE->load->library('form_builder');
		
		if ( ! $this->EE->form_builder->validate())
		{
			return $this->EE->form_builder->action_complete();
		}
		
		$this->cartthrob->save_customer_info();
		
		if ($this->EE->input->post('row_id') !== FALSE)
		{
			$this->cart->remove_item($this->EE->input->post('row_id', TRUE));
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_delete_from_cart_end') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_delete_from_cart_end');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->EE->form_builder->set_success_callback(array($this->cart, 'save'))
					->action_complete();
 	}
	public function field()
	{
		$entry_id = $this->EE->TMPL->fetch_param('entry_id'); 
		$field = $this->EE->TMPL->fetch_param('field'); 
		
		$this->EE->load->model('cartthrob_entries_model');

		$entry = $this->EE->cartthrob_entries_model->entry($this->EE->TMPL->fetch_param('entry_id'));
			
		$this->EE->load->helper('array');
 		return element($this->EE->TMPL->fetch_param('field'), $entry);
	}
	public function download_file_action()
	{
		//@TODO add in debug to output member and group id, and whether the file's protected or not
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		// cartthrob_download_start hook
		if ($this->EE->extensions->active_hook('cartthrob_download_start') === TRUE)
		{
			//@TODO work on hook parameters
			//$edata = $EXT->universal_call_extension('cartthrob_download_start');
			$this->EE->extensions->call('cartthrob_download_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->EE->load->library('form_builder');
		$this->EE->load->library('cartthrob_file');
		$this->EE->load->library('curl');
		$this->EE->load->library('paths');
		$this->EE->load->library('encrypt');
		$this->EE->load->helper(array('string'));
		
		$this->EE->form_builder->set_require_form_hash(FALSE);
		$this->EE->form_builder->set_require_rules(FALSE);
		$this->EE->form_builder->set_require_errors(FALSE);

		$path = NULL;		
		
		if (!$this->EE->input->get('FP') && !$this->EE->input->get('FI'))
		{
			if ( ! $this->EE->form_builder->validate())
			{
				return $this->EE->form_builder->action_complete();
			}
		}
		
		$this->cartthrob->save_customer_info();
		
		// Check member id. 
		if ($this->EE->input->get_post('MI') == TRUE)
		{
			// have to check for get or post due to slightly different encoding types
			if ($this->EE->input->get('MI'))
			{
				$member_id = sanitize_number(xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->input->get('MI'))))));
			}
			else
			{
				$member_id = sanitize_number(xss_clean($this->EE->encrypt->decode($this->EE->input->post('MI'))));
			}
 		}
			
		// Check group id. 
		if ($this->EE->input->get_post('GI'))
		{
			// have to check for get or post due to slightly different encoding types
			if ($this->EE->input->get('GI'))
			{
				$group_id = sanitize_number(xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->input->get('GI'))))));
			}
			else
			{
				$group_id = sanitize_number(xss_clean($this->EE->encrypt->decode($this->EE->input->post('GI'))));
			}
 		}
		// standard file from form, or free_file from download link
		if ($this->EE->input->get_post('FI'))
		{
			// have to check for get or post due to slightly different encoding types
			if ($this->EE->input->get('FI'))
			{
				$path = xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->input->get('FI')))));
			}
			else
			{
				$path = xss_clean($this->EE->encrypt->decode($this->EE->input->post('FI')));
			}
			
			if (substr($path, 0, 2) !== 'FI')
			{
				$this->EE->form_builder->add_error($this->EE->lang->line('download_file_not_authorized'));
			}
			else
			{
				$path = substr($path, 2);
			}
		}
		// protected file from the download link
		elseif ($this->EE->input->get_post('FP'))
		{	
			if ($this->EE->input->get('FP'))
			{
				$path = xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->input->get('FP')))));
			}
			else
			{
				$path = xss_clean($this->EE->encrypt->decode($this->EE->input->post('FP')));
			}
			
			if (substr($path, 0, 2) !== 'FP')
			{
				$this->EE->form_builder->add_error($this->EE->lang->line('download_file_not_authorized'));
			}
			else
			{
				$path = substr($path, 2);
			}
 			
			if (empty($member_id) && empty($group_id))
			{
				$this->EE->form_builder->add_error($this->EE->lang->line('download_file_not_authorized'));
			}
		}
		else
		{
 			$this->EE->form_builder->add_error($this->EE->lang->line('download_url_not_specified'));
 		}
		
		if ($this->EE->form_builder->errors())
		{
			$this->EE->form_builder->action_complete();
		}
 	
		// Check member id. 
		if ( ! empty($member_id) && $member_id != $this->EE->session->userdata('member_id'))
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('download_file_not_authorized_for_member'));
 		}
		
 		// Check group id
		if ( ! empty($group_id) && $group_id != $this->EE->session->userdata('group_id'))
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('download_file_not_authorized_for_group'));
		}

		
		// cartthrob_download_end hook
		if ($this->EE->extensions->active_hook('cartthrob_download_end') === TRUE)
		{
			//@TODO work on hook parameters
			//$edata = $EXT->universal_call_extension('cartthrob_download_end', $path);
			$path = $this->EE->extensions->call('cartthrob_download_end', $path);
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		if ( ! $this->EE->form_builder->errors())
		{
			$this->EE->cartthrob_file->force_download($path, $this->EE->input->get('debug'));
		
			if ($this->EE->cartthrob_file->errors())
			{
				$this->EE->form_builder->add_error($this->EE->cartthrob_file->errors());
			}
		}
	
		$this->EE->form_builder->action_complete();
	}
	public function add_to_cart_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->EE->load->library('form_builder');
		$this->EE->load->library('encrypt');
		
		// cartthrob_add_to_cart_start hook
		if ($this->EE->extensions->active_hook('cartthrob_add_to_cart_start') === TRUE)
		{
			//@TODO work on hook parameters
			//$edata = $EXT->universal_call_extension('cartthrob_add_to_cart_start', $this, $_SESSION['cartthrob']);
			$this->EE->extensions->call('cartthrob_add_to_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		if ( ! $this->EE->form_builder->validate())
		{
			$this->EE->cartthrob_variables->set_global_values();
			
			$this->EE->form_builder->set_value(array(
				'item_options',
				'quantity',
				'title',
			));
			
			return $this->EE->form_builder->action_complete();
		}
		
		$this->cartthrob->save_customer_info();
		
		$data = array(
			'entry_id' => $this->EE->input->post('entry_id', TRUE),
		);
		
		$item_options = $this->EE->input->post('item_options', TRUE);
		
		if ($item_options && is_array($item_options))
		{
			//don't grab numeric item_options, those are for sub_items
			foreach ($item_options as $key => $value)
			{
				if (strpos($key, ':') === FALSE)
				{
					$data['item_options'][$key] = $value;
				}
			}
		}
		
		if ($this->EE->input->post('AUP') && $this->EE->input->post('price') !== FALSE && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUP'))))
		{
			$data['price'] = sanitize_number($this->EE->input->post('price', TRUE));
		}

		if ($this->EE->input->post('PR'))
		{
			$PR = xss_clean($this->EE->encrypt->decode($this->EE->input->post('PR')));

			if ($PR == sanitize_number($PR))
			{
				$data['price'] = $PR;
			}
		}

		if ($this->EE->input->post('WGT'))
		{
			$WGT = xss_clean($this->EE->encrypt->decode($this->EE->input->post('WGT')));

			if ($WGT == sanitize_number($WGT))
			{
				$data['weight'] = $WGT;
			}
		}
		elseif ($this->EE->input->post('AUW') && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUW'))) && $this->EE->input->post('weight') !== FALSE)
		{
			$data['weight'] = $this->EE->input->post('weight', TRUE);
		}

		if ($this->EE->input->post('SHP'))
		{
			$SHP = xss_clean($this->EE->encrypt->decode($this->EE->input->post('SHP')));

			if ($SHP == sanitize_number($SHP))
			{
				$data['shipping'] = $SHP;
			}
		}
		elseif ($this->EE->input->post('AUS') && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUS'))) && $this->EE->input->post('shipping') !== FALSE)
		{
			$data['shipping'] = $this->EE->input->post('shipping', TRUE);
		}

		if ($this->EE->input->post('NSH'))
		{
			$data['no_shipping'] = bool_string($this->EE->encrypt->decode($this->EE->input->post('NSH')));
		}

		if ($this->EE->input->post('NTX'))
		{
			$data['no_tax'] = bool_string($this->EE->encrypt->decode($this->EE->input->post('NTX')));
		}
		
		$data['product_id'] = $data['entry_id'];
		
		if ($this->EE->input->post('quantity'))
		{
			$data['quantity'] = $this->EE->input->post('quantity', TRUE);
		}
		
		if ($this->EE->input->post('title'))
		{
			$data['title'] = $this->EE->input->post('title', TRUE);
		}
		
		//if it's not on_the_fly, it's a product-based item
		if ( ! $this->EE->input->post('OTF') || ! bool_string($this->EE->encrypt->decode($this->EE->input->post('OTF'))))
		{
			if ($this->EE->input->post('title'))
			{
				$data['title'] = $this->EE->input->post('title', TRUE);
			}
			$data['class'] = 'product';
			
			$this->EE->load->model(array('cartthrob_field_model', 'product_model'));
			
			//it's a package
			if ($entry = $this->EE->product_model->get_product($data['entry_id']))
			{
				$field_id = $this->EE->cartthrob_field_model->channel_has_fieldtype($entry['channel_id'], 'cartthrob_package', TRUE);
				
				if ($field_id && ! empty($entry['field_id_'.$field_id]))
				{
					$data['class'] = 'package';
					
					$this->EE->load->library('api');
					
					$this->EE->api->instantiate('channel_fields');
					
					if (empty($this->EE->api_channel_fields->field_types))
					{
						$this->EE->api_channel_fields->fetch_installed_fieldtypes();
					}
					
					$data['sub_items'] = array();
					
					if ($this->EE->api_channel_fields->setup_handler('cartthrob_package'))
					{
						$field_data = $this->EE->api_channel_fields->apply('pre_process', array($entry['field_id_'.$field_id]));
						
						foreach ($field_data as $row_id => $row)
						{
							$item = array(
								'entry_id' => $row['entry_id'],
								'product_id' => $row['entry_id'],
								'row_id' => $row_id,
								'class' => 'product',
							);
							
							$item['item_options'] = (isset($row['option_presets'])) ? $row['option_presets'] : array();
							
							$row_item_options = array();
							
							if (isset($_POST['item_options'][$row_id]))
							{
								$row_item_options = $_POST['item_options'][$row_id];
							}
							else if (isset($_POST['item_options'][':'.$row_id]))
							{
								$row_item_options = $_POST['item_options'][':'.$row_id];
							}
							
							$price_modifiers = $this->EE->product_model->get_all_price_modifiers($row['entry_id']);
							
							foreach ($row_item_options as $key => $value)
							{
								//if it's not a price modifier (ie, an on-the-fly item option), add it
								//if it is a price modifier, check that it's been allowed before adding
								if ( ! isset($price_modifiers[$key]) || ! empty($row['allow_selection'][$key]))
								{
									$item['item_options'][$key] = $this->EE->security->xss_clean($value);
								}
							}
							
							$data['sub_items'][$row_id] = $item;
						}
					}
				}
			}
		}
		
		$original_last_row_id = ($this->cart->items()) ? $this->cart->last_row_id() : -1;
		
		if ( ! isset($data['quantity']) || (isset($data['quantity']) && $data['quantity'] !== '0' && $data['quantity'] !== 0))
		{
			$item = $this->cart->add_item($data);
			
			if ($item && $this->EE->input->post('PER') && $this->EE->encrypt->decode($this->EE->input->post('PER')))
			{
				$item->set_meta('permissions',$this->EE->encrypt->decode($this->EE->input->post('PER')) );
			}
			
			if ($item && $this->EE->input->post('LIC') && bool_string($this->EE->encrypt->decode($this->EE->input->post('LIC'))))
			{
				$new_last_row_id = ($this->cart->items()) ? $this->cart->last_row_id() : -1;
				
				for ($i = $original_last_row_id; $i <= $new_last_row_id; $i++)
				{
					if ($i < 0 || ! $_item = $this->cart->item($i))
					{
						continue;
					}
					
					if ($data['class'] === 'package')
					{
						foreach ($_item->sub_items() as $sub_item)
						{
							$sub_item->set_meta('license_number', TRUE);
						}
					}
					else
					{
						$_item->set_meta('license_number', TRUE);
					}
				}
			}
			// if item and SUB OR subscription (to account for select boxes)
			if ($item && ( ($this->EE->input->post('SUB') && bool_string($this->EE->encrypt->decode($this->EE->input->post('SUB')))) || ($this->EE->input->post('subscription') && bool_string($this->EE->input->post('subscription')))))
			{
	 			// these are all of the subscription options
				$sub_array = array(
					'SUN' 	=> 'subscription_name',
					'SO'	=> 'subscription_total_occurrences',
					'ST'	=> 'subscription_trial_price',
					'SP'	=> 'subscription_trial_occurrences',
					'SSD'	=> 'subscription_start_date',
					'SED'	=> 'subscription_end_date',
					'SI'	=> 'subscription_interval',
					'SIU'	=> 'subscription_interval_units',
					'SM'	=> 'subscription_allow_modification',
					'SUT'	=> 'subscription_type'); 

				$subscription = array(); 

				// iterating through those options. if they're in post, we'll add them to the "subscription_options" meta	
				foreach ($sub_array as $key => $sub_property)
				{	
					$sub_post_data = NULL; 
					if ($this->EE->input->post($key))
					{
						$sub_post_data = $this->EE->encrypt->decode($this->EE->input->post($key)); 
					}
					else
					{
						$sub_post_data =$this->EE->input->post($sub_property); 
					}
					if ($sub_post_data)
					{
						$subscription[$sub_property] = $sub_post_data; 
					}
				}
				// adding subscription meta. even if there's no new info, we still want the subscription meta set
				$item->set_meta('subscription_options', $subscription );
				$item->set_meta('subscription', TRUE);
 			}
			
			if ($item && $this->EE->input->post('EXP'))
			{
				$EXP = xss_clean($this->EE->encrypt->decode($this->EE->input->post('EXP')));
	
				if ($EXP == sanitize_number($EXP))
				{
					$item->set_meta('expires', $EXP);
				}
			}
			
			if ($item && $inventory_reduce = $this->EE->input->post('inventory_reduce', TRUE))
			{
				$item->set_meta('inventory_reduce', $inventory_reduce);
			}
	
			// cartthrob_add_to_cart_end hook
			if ($this->EE->extensions->active_hook('cartthrob_add_to_cart_end') === TRUE)
			{
				//@TODO work on hook parameters
				//$edata = $EXT->universal_call_extension('cartthrob_add_to_cart_end', $this, $_SESSION['cartthrob'], $row_id);
				$this->EE->extensions->call('cartthrob_add_to_cart_end', $item);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		}
		
		//if they're using inline stuff we wanna clear the added item upon error
		if ($this->EE->input->post('error_handling') === 'inline' && $item)
		{
			$this->EE->form_builder->set_error_callback(array($this->cart, 'remove_item', $item->row_id()));
		}
		
		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
					->action_complete();
	}
	
	/**
	 * update_cart_form
	 * 
	 * handles submissions from the update_cart_form 
	 * redirects on completion
	 * 
	 * @access protected
	 * @since 1.0
	 * @return void
	 * @author Rob Sanchez
	 */
	public function update_cart_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_update_cart_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_update_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->EE->load->library('form_builder');
		
		if ( ! $this->EE->form_builder->validate())
		{
			$this->EE->cartthrob_variables->set_global_values();
			
			$this->EE->form_builder->set_value(array(
				'clear_cart',
			));
			
			return $this->EE->form_builder->action_complete();
		}

		$this->cartthrob->save_customer_info();

		if ($this->EE->input->post('clear_cart'))
		{
			$this->cart->clear();
		}
		else
		{
			foreach ($this->cart->items() as $row_id => $item)
			{
				if (element($row_id, element('delete', $_POST)))
				{
					$_POST['quantity'][$row_id] = 0;
				}
				
				$data = array();
	
				foreach ($_POST as $key => $value)
				{
					if ($item->sub_items())
					{
						foreach ($item->sub_items() as $sub_item)
						{
							if (isset($value[$row_id.':'.$sub_item->row_id()]) && in_array($key, $sub_item->default_keys()))
							{
								$_value = $value[$row_id.':'.$sub_item->row_id()];
								
								$this->EE->load->library('api');
								
								$this->EE->api->instantiate('channel_fields');
								
								if (empty($this->EE->api_channel_fields->field_types))
								{
									$this->EE->api_channel_fields->fetch_installed_fieldtypes();
								}
								
								if ($key === 'item_options' && $this->EE->api_channel_fields->setup_handler('cartthrob_package'))
								{
									$field_data = $this->EE->api_channel_fields->apply('pre_process', array($item->meta('field_id_'.$field_id)));
									
									foreach ($field_data as $row)
									{
										if (isset($row['allow_selection']))
										{
											foreach ($row['allow_selection'] as $key => $allowed)
											{
												if ( ! $allowed && isset($_value[$key]))
												{
													unset($_value[$key]);
												}
											}
										}
									}
								}
								
								$sub_item->update(array($key => $this->EE->security->xss_clean($_value)));
							}
						}
					}
					
					if (isset($value[$row_id]) && in_array($key, $item->default_keys()))
					{
						$data[$key] = $this->EE->security->xss_clean($value[$row_id]);
					}
					
					if (isset($value[$row_id]) && $key === 'subscription')
					{
						$item->set_meta('subscription', bool_string($value[$row_id]));
					}
				}
				
				if ($data)
				{
					$item->update($data);
				}
			}
		}

		if ($this->EE->extensions->active_hook('cartthrob_update_cart_end') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_update_cart_end');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		if (trim($this->EE->input->post('coupon_code', TRUE)))
		{
			$this->cart->add_coupon_code(trim($this->EE->input->post('coupon_code', TRUE)));
		}
		
		$this->cart->check_inventory();
	
		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
					->action_complete();
	}
	
	public function add_coupon_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->EE->load->library('form_builder');
		
		if ( ! $this->EE->form_builder->validate())
		{
			$this->EE->cartthrob_variables->set_global_values();
			
			$this->EE->form_builder->set_value('coupon_code');
			
			return $this->EE->form_builder->action_complete();
		}
		
		$this->cartthrob->save_customer_info();
		
		$this->cart->add_coupon_code(trim($this->EE->input->post('coupon_code', TRUE)));
	
		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
					->action_complete();
	}
	
	public function cart_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->cartthrob->save_customer_info();
	}
	protected function order_data_array($vars = array())
	{
		$shipping = NULL; 
		$tax = NULL; 
		$subtotal = NULL; 
		$discount = NULL; 
		$total = NULL; 
		$credit_card_number = NULL; 
		$create_member_id = NULL; 
		$group_id = NULL; 
		$subscription = array(); 
		$subscription_options = array();
		$payment_gateway = NULL;
		
		extract($vars, EXTR_IF_EXISTS);	
		
		$this->cart->set_calculation_caching(FALSE);
		if (empty($total))
		{
			$total = $this->cart->total();
		}
		if (empty($tax))
		{
			$tax = $this->cart->tax();
		}
		if (empty($discount))
		{
			$discount = $this->cart->discount();
		}
		if (empty($shipping))
		{
			$shipping = $this->cart->shipping();
		}
		if (empty($subtotal))
		{
			$subtotal = $this->cart->subtotal();
		}
		
		$this->EE->load->library('api/api_cartthrob_tax_plugins');
		
		$order_data = array(
			'items' => array(),
			'transaction_id' => '',
			'card_type' => ($this->EE->input->post('card_type')) ? $this->EE->input->post('card_type', TRUE) : card_type($credit_card_number),
			'shipping' => $this->cartthrob->round($shipping),
			'shipping_plus_tax'	=>  $this->cartthrob->round($shipping + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $shipping)),
			'tax' => $this->cartthrob->round($tax),
			'subtotal' => $this->cartthrob->round($subtotal),
			'subtotal_plus_tax' => $this->cartthrob->round($subtotal + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $this->cart->taxable_subtotal())),
			'discount' => $this->cartthrob->round($discount),
			'total' => $this->cartthrob->round($total),
			'customer_name' => $this->cart->customer_info('first_name').' '.$this->cart->customer_info('last_name'),
			'customer_email' => $this->cart->customer_info('email_address'),
			'customer_ip_address' => $this->EE->input->ip_address(),
			'ip_address' => $this->EE->input->ip_address(),
			'customer_phone' => $this->cart->customer_info('phone'),
			'coupon_codes' => implode(',', $this->cart->coupon_codes()),
			'coupon_codes_array' => $this->cart->coupon_codes(),
			'last_four_digits' => substr($credit_card_number,-4,4),
			'full_billing_address' => $this->cart->customer_info('address')."\r\n".
				( $this->cart->customer_info('address2')  ? $this->cart->customer_info('address2')."\r\n" : '').
				$this->cart->customer_info('city').', '.$this->cart->customer_info('state').' '.$this->cart->customer_info('zip'),
			'full_shipping_address' => $this->cart->customer_info('shipping_address')."\r\n".
				($this->cart->customer_info('shipping_address2') ? $this->cart->customer_info('shipping_address2')."\r\n" : '').
				$this->cart->customer_info('shipping_city').', '.$this->cart->customer_info('shipping_state').
				' '.$this->cart->customer_info('shipping_zip'),
			'billing_first_name' => $this->cart->customer_info('first_name'),
			'billing_last_name' => $this->cart->customer_info('last_name'),
			'billing_company' => $this->cart->customer_info('company'),
			'billing_address' => $this->cart->customer_info('address'),
			'billing_address2' => $this->cart->customer_info('address2'),
			'billing_city' => $this->cart->customer_info('city'),
			'billing_state' => $this->cart->customer_info('state'),
			'billing_zip' => $this->cart->customer_info('zip'),
			'billing_country' => $this->cart->customer_info('country'),
			'billing_country_code' => $this->cart->customer_info('country_code'),
			'entry_id' => '',
			'order_id' => '',
			'total_cart' => $this->cartthrob->round($total),
			'auth' => array(),
			'purchased_items' => array(),
			'create_user' => ( ! empty($create_member_id)) ? $create_member_id : FALSE,
			'member_id' => ( ! empty($create_member_id)) ? $create_member_id :  $this->EE->session->userdata('member_id'),
			'group_id' => ( ! empty($group_id)) ? $group_id :  $this->EE->session->userdata('group_id'),
			'authorized_redirect' => $this->EE->input->post('authorized_redirect', TRUE),
			'failed_redirect' => $this->EE->input->post('failed_redirect', TRUE),
			'declined_redirect' => $this->EE->input->post('declined_redirect', TRUE),
			'return' => ($this->EE->input->post('return')) ? $this->EE->input->post('return', TRUE) : $this->EE->functions->fetch_site_index(1),
			'site_name' => $this->EE->config->item('site_name'),
			'custom_data' => $this->cart->custom_data(),
			'subscription'	=> $subscription,
			'subscription_options'	=> $subscription_options,
			'payment_gateway' => (strncmp($payment_gateway, 'Cartthrob_', 10) === 0) ? substr($payment_gateway, 10) : $payment_gateway,
		);
		
		foreach ($this->cart->items() as $row_id => $item)
		{
			$row = $item->to_array();
			
			$row['price'] = $item->price();
			$row['weight'] = $item->weight();
			$row['shipping'] = $item->shipping();
			$row['title'] = $item->title();
			$order_data['items'][$row_id] = $row;
		}
		
		$order_data = array_merge($order_data, $this->cart->customer_info());
		
		return $order_data;
	}
	public function checkout_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		// Save the current customer info for use after checkout
		// needed for return trip after offsite processing
		$this->cartthrob->save_customer_info();
		
		$this->EE->cartthrob_variables->set_global_values();
		
		$this->EE->form_builder->set_value(array(
			'coupon_code',
		));
		
		$this->EE->load->library('form_validation');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->set_show_errors(TRUE)
					->set_captcha($this->EE->session->userdata('member_id') == 0 && $this->EE->cartthrob->store->config('checkout_form_captcha'))
					->set_success_callback(array($this->cart, 'save'))
					->set_error_callback(array($this->cart, 'save'));
		
		$create_user = bool_string($this->EE->input->post('create_user'));
		
		if ( ! $create_user && ! $this->EE->session->userdata('member_id') && $this->store->config('logged_in'))
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('must_be_logged_in'))
						->action_complete();
		}
		
		if ($this->cart->is_empty() && ! $this->store->config('allow_empty_cart_checkout'))
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('empty_cart'))
						->action_complete();
		}

		$this->EE->load->library('languages');
		
		$this->EE->languages->set_language($this->EE->input->post('language', TRUE));
		
		$not_required = array();

		$required = array();

		if ($this->EE->input->post('NRQ'))
		{
			$not_required = explode('|', xss_clean($this->EE->encrypt->decode($this->EE->input->post('NRQ'))));
		}

		$gateway = ($this->store->config('allow_gateway_selection') && $this->EE->input->post('gateway')) ? xss_clean($this->EE->encrypt->decode($this->EE->input->post('gateway'))) : $this->store->config('payment_gateway');

		$credit_card_number = sanitize_credit_card_number($this->EE->input->post('credit_card_number', TRUE));
		
		// Load the payment processing plugin that's stored in the extension's settings.
		$this->EE->load->library('cartthrob_payments');
		
		if ( ! $this->EE->cartthrob_payments->set_gateway($gateway)->gateway())
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('invalid_payment_gateway'))
						->action_complete();
		}

		$authorized_redirect = $this->EE->input->post('authorized_redirect', TRUE);

		$failed_redirect = $this->EE->input->post('failed_redirect', TRUE);

		$declined_redirect = $this->EE->input->post('declined_redirect', TRUE);
		
		$this->cart->add_coupon_code(trim($this->EE->input->post('coupon_code', TRUE)));

		$this->cart->check_inventory();
		
		if ($this->store->config('modulus_10_checking') && ! modulus_10_check($credit_card_number))
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('validation_card_modulus_10'))
						->action_complete();
		}
		
		$this->cart->set_calculation_caching(FALSE);
		
		$tax = $this->cart->tax();
		$shipping = $this->cart->shipping();
		// discount MUST be calculated before shipping to set shipping free, etc. 
		$discount = $this->cart->discount();
		$shipping = $this->cart->shipping();
		$subtotal = $this->cart->subtotal();
		$total = $this->cart->total();
		$subscription = FALSE; 
		$subscription_options = FALSE; 
		$expiration_date = FALSE;
		
		if ($this->EE->input->post('EXP'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('EXP')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$expiration_date = $data;
			}
		}

		if ($this->EE->input->post('TX'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('TX')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$total -= $tax;
				$tax = $data;
				$total += $tax;
			}
		}

		if ($this->EE->input->post('SHP'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('SHP')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$total -= $shipping;
				$shipping = $data;
				$total += $shipping;
			}
		}
		
		$group_id = 5;
		
		if ($this->EE->input->post('GI'))
		{
			$group_id = xss_clean($this->EE->encrypt->decode($this->EE->input->post('GI')));
			
			if ($group_id < 5)
			{
				$group_id = 5; 
			}
		}
		
		if ($this->EE->input->post('PR'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('PR')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$total -= $subtotal;
				$subtotal = $data;
				$total += $subtotal;
			}
		}
		elseif ($this->EE->input->post('AUP'))
		{
			if (bool_string($this->EE->encrypt->decode($this->EE->input->post('AUP'))))
			{
				$total = sanitize_number($this->EE->input->post('price', TRUE));
			}
		}

		//fetch payment_gateway's required fields
		//bypass if cart total is zero
		if ($total > 0)//@TODO REMOVE THIS
		{
			$required = array_merge($required, $this->EE->cartthrob_payments->required_fields());
		}
		
		foreach ($not_required as $key)
		{
			unset($required[array_search($key, $required)]);
		}
		
		if ( ! $this->EE->form_builder->set_required($required)->validate())
		{
			return $this->EE->form_builder->action_complete();
		}
		
		$this->EE->cartthrob_payments->set_total($total);
		
		if ($this->EE->extensions->active_hook('cartthrob_pre_process') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_pre_process');
			if ($this->EE->extensions->end_script === TRUE) return;
		}

		$entry_id = '';
		
		// @TODO move all of this stuff into the checkout complete method somehow
		// if you checkout with PayPal (for instance) and you created a member but left the site and come back
		// that member is taken. OOPS!
		$this->EE->load->model('cartthrob_members_model');
		
		if ($create_user && ! $this->EE->session->userdata('member_id'))
		{
			// @TODO put in a check for members existence here. 
			// move the actual creation procedure to checkout_complete.
			// in the checkout_complete function, that's where we need to create the member
			// if somehow the member was created in between the checkout and checkout complete
			// tack on some random gibberish to the username. 
			$create_member_id = $this->EE->cartthrob_members_model->create_member(
				$this->EE->input->post('username'),  
				$this->cart->customer_info('email_address'),  
				$this->EE->input->post('screen_name', TRUE), 
				$this->EE->input->post('password', TRUE), 
				$this->EE->input->post('password_confirm', TRUE), 
				$group_id,
				$this->cart->customer_info('language'),
				FALSE//delay the activation email until the order is authorized
			);
			
			// should only be an array if errors are returned
			if (is_array($create_member_id))
			{
				return $this->EE->form_builder->add_error($create_member_id)
							->action_complete();
			}
			else
			{
				// @TODO add this information to the order object
				//save the USERid for later
				$this->EE->session->cache['cartthrob']['member_id'] = $create_member_id;
				
				// @TODO move all of this save member data action to checkout_complete
				// use order object to grab the data, if customer_info is no longer available
				// remember, the session will be lost.... only the order object respawned for some offsite gateways
				
				// saving the member data now.
				if ($this->store->config('save_member_data'))
				{
					$member = array();
					
					$member_data = array();
				
					foreach (array_keys($this->cart->customer_info()) as $field)
					{
						// setting an alternate variable because we may be changing where the data's going in a second.
						$orig_field = $field; 
						
						if (bool_string($this->cart->customer_info('use_billing_info')) && strpos($field, 'shipping_') !== FALSE)
						{
							// we're going to get the data from the billing field
							$field = str_replace('shipping_', '', $field); 
						}
						
						// saving the data.
						if ($this->EE->input->post($field) !== FALSE && $field_id = $this->store->config('member_'.$orig_field.'_field'))
						{
							if (is_numeric($field_id))
							{
								if ($this->EE->cartthrob->load_profile_edit())
								{
									$member_data['field_id_'.$field_id] = $this->cart->customer_info($field);
								}
								else
								{
									$member_data['m_field_id_'.$field_id] = $this->cart->customer_info($field);
								}
							}
							else
							{
								$member[$field_id] = $this->cart->customer_info($field);
							}
						}
					}

					$this->EE->load->model('member_model');
					
					if ( ! empty($member_data))
					{
						//@TODO test this
						if ($profile_edit_channel_id = $this->EE->cartthrob->load_profile_edit())
						{
							$this->EE->load->model('cartthrob_entries_model');
							
							$member_data['channel_id'] = $profile_edit_channel_id;
							
							$this->EE->cartthrob_entries_model->update_entry($this->EE->profile_model->get_profile_id($create_member_id), $member_data);
						}
						else
						{
							$this->EE->member_model->update_member_data($create_member_id, $member_data);
						}
					}
					
					if ( ! empty($member))
					{
						$this->EE->member_model->update_member($create_member_id, $member);
					}
				}
 			}
		}
		$this->EE->load->library('api/api_cartthrob_tax_plugins');
 		
		$vars = array(
		'shipping'       			=> $shipping,
		'shipping_plus_tax'			=> $shipping + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $shipping),
		'tax'               		=> $tax,
		'subtotal'          		=> $subtotal,
		'subtotal_plus_tax'    		=> $subtotal + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $this->cart->taxable_subtotal()), 
		'discount'          		=> $discount,
		'total'             		=> $total,
		'credit_card_number'		=> $credit_card_number,
		'create_member_id'  		=> (!empty($create_member_id) ? $create_member_id: NULL),
		'group_id'          		=>  (!empty($group_id) ? $group_id : NULL), 
		'subscription'				=> $subscription,
		'subscription_options'		=> $subscription_options,
		'payment_gateway'		=> $gateway,
		);
		
		$order_data = $this->order_data_array($vars);
				
		if ($this->store->config('save_orders'))
		{
			if ( ! empty($expiration_date))
			{
				$order_data['expiration_date'] = $expiration_date;
			}
			
			$this->EE->load->model('order_model');
			if (!empty($create_member_id))
			{
				$order_data['member_id'] = $create_member_id; 
			}
			$order_entry = $this->EE->order_model->create_order($order_data);
			
			$order_data['entry_id'] = $order_data['order_id'] = $order_entry['entry_id'];
			
 			$order_data['title'] = $order_data['invoice_number'] = $order_entry['title'];
			
			unset($order_data['expiration_date']);
		}
		else
		{
			$order_data['title'] = $order_data['invoice_number'] = '';
		}
		
		//save order to session
		$this->cart->set_order($order_data);
		
		// @TODO SUB we should change start date and end date to individual fields maybe? month, day year? 
		if ($this->EE->input->post('SUB') && bool_string($this->EE->encrypt->decode($this->EE->input->post('SUB'))))
		{
 			// these are all of the subscription options
			$sub_array = array(
				'SUN' 	=> 'subscription_name',
				'SO'	=> 'subscription_total_occurrences',
				'ST'	=> 'subscription_trial_price',
				'SP'	=> 'subscription_trial_occurrences',
				'SSD'	=> 'subscription_start_date',
				'SED'	=> 'subscription_end_date',
				'SU'	=> 'subscription_interval',
				'SIU'	=> 'subscription_interval_units',
				'SM'	=> 'subscription_allow_modification',
				'SUT'	=> 'subscription_type'); 

			$subscription_options = array(); 

			// iterating through those options. if they're in post, we'll add them to the "subscription_options" meta	
			foreach ($sub_array as $key => $sub_property)
			{	
				$sub_post_data = NULL; 
				if ($this->EE->input->post($key))
				{
					$sub_post_data = $this->EE->encrypt->decode($this->EE->input->post($key)); 
				}
				else
				{
					$sub_post_data =$this->EE->input->post($sub_property); 
				}
				if ($sub_post_data)
				{
					$subscription_options[$sub_property] = $sub_post_data; 
				}
			}
			$this->cart->update_order($subscription_options);
		}
		
		$this->cart->save();
		
		// IF the payment gateway directs users offsite, we will lose them at this point.
		// so the second half of the process is offloaded. 
		$auth = $this->EE->cartthrob_payments->process_payment($credit_card_number);
		
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => '',
				'transaction_id' => '',
			),
			$auth
		);
		

		if ($auth['authorized'] == TRUE)
		{
			$subscription_items = array(); 
			$permissions = array(); 
			//////// begin permissions ///////
			
			if ($this->cart->order('items'))
			{
				foreach ($this->cart->order('items') as $row_id => $item)
				{
					if (!isset($count))
					{
						$count=1;
					}
					$count++;

					if (empty($item['entry_id']))
					{
						$item['entry_id'] = "000";
					}
					if (!empty($item['meta']['subscription']))
					{
						$subscription_items[$row_id] = $item; 
					}
					if (!empty($item['meta']['permissions']))
					{
						$permissions[$row_id] = $item; 
					}
				}
			}
			
			$this->EE->load->model("permissions_model");
			foreach ($permissions as $row_id => $item)
			{
					$perms = explode("|", $item['meta']['permissions']); 
				
					foreach ($perms as $perm)
					{
					$data['permission']		= $perm;
					$data['order_id']		= $this->cart->order('entry_id'); 
					$data['member_id']		= $this->cart->order('member_id');
					$data['item_id']		= $item['entry_id']; 
				
					$id = $this->EE->permissions_model->update($data);
					// saving the new permissions id so that we can update it later with the subscription id.
					if (array_key_exists($row_id, $subscription_items))
					{
						$subscription_items[$row_id]['permissions_id'][] = $id; 
					} 
				}
			}
			
			//////// end permissions /////////
			
			//////// subscriptions //////////
			// offsite payment gateways need to handle their own recurrent billing 

			if ($this->cart->order('subscription'))
			{
				$subscription_items = array(); 
				$sub['quantity'] = 1; 
				$sub['price'] = $this->cart->order('total'); 
				$sub['meta']['subscription'] = TRUE; 
				$sub['meta']['subscription_options'] = $this->cart->order('subscription_options'); 
	 			$subscription_items = array($sub);  
			}
 
			if (!empty($subscription_items) )
			{
				foreach($subscription_items as $row_id => $item)
				{

					for ($i=0; $i < $item['quantity']; $i++)
					{
						$sub_total = $item['price']; 
						$sub_data['subscription'] = TRUE;
						$sub_data = array(); 
						foreach ($item['meta']['subscription_options'] as $key=> $value)
						{
							$sub_data[$key] = $value; 
						}
						$recurrent_billing_auth = $this->EE->cartthrob_payments->create_recurrent_billing($sub_total, $credit_card_number, $sub_data); 

						$save_data['description'] 	= $this->EE->cartthrob_payments->subscription_info($sub_data, 'subscription_name', $item['title']); 
						$save_data['timestamp']		= time(); 
						$save_data['order_id']		= $this->cart->order('entry_id'); 
						$save_data['member_id']		= $this->cart->order('member_id');
						$save_data['status']		= "failed"; 

						if ($recurrent_billing_auth['authorized'])
						{
							// @TODO need to add more content into the save_data like interval units,
							$save_data['sub_id']			= $recurrent_billing_auth['transaction_id']; 
							$save_data['status']			= "open"; 
							
							// @NOTE when working with permissions... we aren't creating multiple sets of permissions per quantity
							// could potentially cause some weirdness if someone sells multiple quantities of one 
							// subscription and then cancels one of them.
							

							if (!empty($subscription_items[$row_id]['permissions_id'] ))
							{
								foreach($subscription_items[$row_id]['permissions_id'] as $perm_id)
								{
									$this->EE->permissions_model->update(array('sub_id'=> $recurrent_billing_auth['transaction_id'] ), $perm_id);
								}
 							}
						}
						else
						{
							$save_data['status']			= "failed"; 
							$save_data['error_message']		= $recurrent_billing_auth['error_message']; 
							
							if (!empty($subscription_items[$row_id]['permissions_id'] ))
							{
								foreach($subscription_items[$row_id]['permissions_id'] as $perm_id)
								{
									$this->EE->permissions_model->delete($perm_id);
								}
 							}

						}
						$save_data['gateway'] = $gateway; 

						$this->EE->cartthrob_payments->update_vault_data($save_data); 
					}
				}
			}
			//////// end subscriptions ///////

		}
		
		$this->checkout_complete($auth);
	}
	
	protected function checkout_complete($auth)
	{
		$this->EE->load->library('form_builder');
		
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => '',
				'transaction_id' => '',
			),
			$auth
		);
		
		$this->cart->update_order(array_merge($auth, array('auth' => $auth)));

		$order_id = $this->cart->order('order_id');
		
		$this->EE->session->set_flashdata($auth);
		
		//since we use the authorized variables as tag conditionals in submitted_order_info,
		//we won't throw any errors from here on out
		$this->EE->form_builder->set_show_errors(FALSE);

		unset($_POST['ERR']);
		
		$this->EE->load->library('cartthrob_emails');

		if ($auth['authorized'])
		{
			$this->EE->cartthrob_payments->update_order(array('auth' => $auth));
			$this->EE->cartthrob_payments->set_order_meta($order_id, 'authorized', $this->EE->cartthrob->store->config('orders_default_status'), element('transaction_id', $auth),element('error_message', $auth) );
			
			if ($this->cart->order('group_id') && $this->cart->order('create_user'))
			{
				$this->EE->load->model('member_model');
				
				//send the email we didn't send before
				if ($this->EE->config->item('req_mbr_activation') === 'email')
				{
					$this->EE->cartthrob_members_model->send_activation_email($this->EE->cartthrob->cart->order('create_user'));
				}
				
				if ($this->EE->config->item('req_mbr_activation') !== 'manual' && $this->EE->config->item('req_mbr_activation') !== 'email')
				{
					$this->EE->member_model->update_member($this->cart->order('create_user'), array('group_id' => $this->cart->order('group_id'))); 
				}
				
				if ($this->store->config('checkout_registration_options') == "auto-login" ||  
						($this->EE->config->item('req_mbr_activation') !== 'manual' && $this->EE->config->item('req_mbr_activation') !== 'email'))
				{
					$this->EE->cartthrob_members_model->login_member($this->cart->order('create_user'));
				}
				$this->cartthrob->save_customer_info();
				
			}
			
			$update_data = array(
				'status' => ($this->store->config('orders_default_status')) ? $this->store->config('orders_default_status') : 'open',
				'transaction_id' => element('transaction_id', $auth)
			);
				
			if ($this->store->config('save_orders'))
			{
				$this->EE->load->model('order_model');
				$this->EE->order_model->update_order($order_id, $update_data);
			}

			if ($this->store->config('save_purchased_items') && $this->cart->order('items'))
			{
				$this->EE->load->model('purchased_items_model');
				
				$purchased_items = array();
				
				foreach ($this->cart->order('items') as $row_id => $item)
				{
					//if it's a package, we'll make purchased items from the sub_items and not the package itself
					if ( ! empty($item['sub_items']))
					{
						foreach ($item['sub_items'] as $_row_id => $_item)
						{
							$purchased_items[$row_id.':'.$_row_id] = $this->EE->purchased_items_model->create_purchased_item($_item, $order_id, $this->store->config('purchased_items_default_status'));
						}
					}
					else
					{
						$purchased_items[$row_id] = $this->EE->purchased_items_model->create_purchased_item($item, $order_id, $this->store->config('purchased_items_default_status'));
					}
				}
				
				$this->cart->update_order(array('purchased_items' => $purchased_items));
			}

			if ($this->EE->extensions->active_hook('cartthrob_on_authorize') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_authorize');
				if ($this->EE->extensions->end_script === TRUE) return;
			}
			
			/*
			if ($this->store->config('send_confirmation_email'))
			{
				$this->EE->cartthrob_emails->send_confirmation_email($this->cart->customer_info('email_address'), $this->cart->order());
			}

			if ($this->store->config('send_email'))
			{
				$this->EE->cartthrob_emails->send_admin_notification_email($this->cart->order());
			}
			*/ 
			$emails = $this->EE->cartthrob_emails->get_email_for_event("completed"); 
			if (!empty($emails))
			{
				foreach ($emails as $email_content)
				{
					$this->EE->cartthrob_emails->send_email($email_content, $this->cart->order()); 
				}
			}
			$this->cartthrob->process_discounts()->process_inventory();
			
			$this->cart->clear()
				   ->clear_coupon_codes()
				   ->clear_totals();
			
			// turning this off for next order
			$this->cart->set_customer_info('use_billing_info', '0');
			
			$this->EE->form_builder->set_return($this->cart->order('authorized_redirect'));
		}
		elseif ($auth['declined'])
		{
			$this->EE->cartthrob_payments->update_order(array('auth' => $auth));
			$this->EE->cartthrob_payments->set_order_meta($order_id, 'declined', $this->EE->cartthrob->store->config('orders_declined_status'), element('transaction_id', $auth),element('error_message', $auth) );
			
			
			if ($this->cart->order('create_user'))
			{
				$this->EE->load->model('member_model');
				$this->EE->member_model->delete_member($this->cart->order('create_user'));
			}
			
			if ($this->store->config('save_orders'))
			{
				$status = ($this->store->config('orders_declined_status')) ? $this->store->config('orders_declined_status') : 'closed';
				$this->EE->load->model('order_model');
				$this->EE->order_model->update_order($order_id, array('error_message' => 'DECLINED: '.element('error_message', $auth), 'status' => $status));
			}
 			$emails = $this->EE->cartthrob_emails->get_email_for_event("declined"); 
			if (!empty($emails))
			{
				foreach ($emails as $email_content)
				{
					$this->EE->cartthrob_emails->send_email($email_content, $this->cart->order()); 
				}
			}
			if ($this->EE->extensions->active_hook('cartthrob_on_decline') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_decline');
				if ($this->EE->extensions->end_script === TRUE) return;
			}

			$this->EE->form_builder->set_return($this->cart->order('declined_redirect'))
					       ->add_error(element('error_message', $auth));
		}
		elseif ($auth['processing'])
		{
			$this->EE->cartthrob_payments->update_order(array('auth' => $auth));
 			
  			$this->EE->cartthrob_payments->set_order_meta($order_id,'processing', $this->EE->cartthrob->store->config('orders_processing_status'),  element('transaction_id', $auth),element('error_message', $auth) );
 			$emails = $this->EE->cartthrob_emails->get_email_for_event("processing"); 
			if (!empty($emails))
			{
				foreach ($emails as $email_content)
				{
					$this->EE->cartthrob_emails->send_email($email_content, $this->cart->order()); 
				}
			}

			if ($this->EE->extensions->active_hook('cartthrob_on_processing') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_processing');
				if ($this->EE->extensions->end_script === TRUE) return;
			}

			$this->EE->form_builder->set_return($this->cart->order('processing_redirect'));
		}
		elseif ($auth['failed'])
		{
			$this->EE->cartthrob_payments->update_order(array('auth' => $auth));
			$this->EE->cartthrob_payments->set_order_meta($order_id, 'failed', $this->EE->cartthrob->store->config('orders_failed_status'), element('transaction_id', $auth),element('error_message', $auth) );
			
			if ($this->cart->order('create_user'))
			{
				$this->EE->load->model('member_model');
				$this->EE->member_model->delete_member($this->cart->order('create_user'));
			}
			
			if ($this->store->config('save_orders'))
			{
				// @TODO update "failed" with lang files (similar with "declined")
				$status = ($this->store->config('orders_failed_status')) ? $this->store->config('orders_failed_status') : 'closed';
				$this->EE->load->model('order_model');
				$this->EE->order_model->update_order($order_id, array('error_message' => 'FAILED: '.element('error_message', $auth), 'status' => $status));
			}
 			$emails = $this->EE->cartthrob_emails->get_email_for_event("failed"); 
			if (!empty($emails))
			{
				foreach ($emails as $email_content)
				{
					$this->EE->cartthrob_emails->send_email($email_content, $this->cart->order()); 
				}
			}
 			
			if ($this->EE->extensions->active_hook('cartthrob_on_fail') === TRUE)
			{
				$this->EE->extensions->call('cartthrob_on_fail');
				if ($this->EE->extensions->end_script === TRUE) return;
			}

			$this->EE->form_builder->set_return($this->cart->order('failed_redirect'))
					       ->add_error(element('error_message', $auth));
		}
		
		$this->cart->save();
		// @TODO we could get rid of gateway_order_update if we could pass in the return URL. If the return URL is passed in
		// we can assume that the form builder doesn't need to complete maybe? 
		
 		if ($this->EE->config->item('email_debug') == "y")
		{
			exit; 
		}
		
		
		$this->EE->form_builder->action_complete();
	}
	
	public function multi_add_to_cart_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->EE->load->library('form_builder');
		
		// cartthrob_multi_add_to_cart_start hook
		if ($this->EE->extensions->active_hook('cartthrob_multi_add_to_cart_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_multi_add_to_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		if ( ! $this->EE->form_builder->validate())
		{
			return $this->EE->form_builder->action_complete();
		}
		
		$this->cartthrob->save_customer_info();

		$entry_ids = $this->EE->input->post('entry_id', TRUE);

		if (is_array($entry_ids))
		{
			$this->EE->load->library('encrypt');
			
			$on_the_fly = ($this->EE->input->post('OTF') && bool_string($this->EE->encrypt->decode($this->EE->input->post('OTF'))));

			$json = ($this->EE->input->post('JSN') && bool_string($this->EE->encrypt->decode($this->EE->input->post('JSN'))));
		
			$allow_user_price = ($this->EE->input->post('AUP') && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUP'))));
		
			$allow_user_shipping = ($this->EE->input->post('AUS') && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUS'))));
		
			$allow_user_weight = ($this->EE->input->post('AUW') && bool_string($this->EE->encrypt->decode($this->EE->input->post('AUW'))));

			foreach ($entry_ids as $row_id => $entry_id)
			{
				$quantity = xss_clean(array_value($_POST, 'quantity', $row_id));
				
				if ( ! is_numeric($quantity) || $quantity <= 0)
				{
					continue;
				}
				
				$data = array(
					'entry_id' => xss_clean(array_value($_POST, 'entry_id', $row_id)),
					'quantity' => $quantity,
				);
				// thanks to Dion40 for catching an error related to no_shipping, no_tax
				if ($this->EE->input->post('NSH'))
				{
					$data['no_shipping'] = bool_string($this->EE->encrypt->decode($this->EE->input->post('NSH')));
				}

				if ($this->EE->input->post('NTX'))
				{
					$data['no_tax'] = bool_string($this->EE->encrypt->decode($this->EE->input->post('NTX')));
				}
				
				if (($allow_user_price || $on_the_fly) && ($value = array_value($_POST, 'price', $row_id)) !== FALSE)
				{
					$data['price'] = xss_clean($value);
				}
				
				if (($allow_user_weight || $on_the_fly) && ($value = array_value($_POST, 'weight', $row_id)) !== FALSE)
				{
					$data['weight'] = xss_clean($value);
				}
		
				if (($allow_user_shipping || $on_the_fly) && ($value = array_value($_POST, 'shipping', $row_id)) !== FALSE)
				{
					$data['shipping'] = xss_clean($value);
				}
				
				if ($value = array_value($_POST, 'title', $row_id))
				{
					$data['title'] = xss_clean($value);
				}
				
				if ($value = array_value($_POST, 'item_options', $row_id))
				{
					$data['item_options'] = xss_clean($value);
				}
				
				if ( ! $on_the_fly)
				{
					$data['class'] = 'product';
				}
				
				$data['product_id'] = $data['entry_id'];
				
				$item = $this->cart->add_item($data);
				
				if ($item && $value = array_value($_POST, 'license_number', $row_id))
				{
					$item->set_meta('license_number', TRUE);
				}
			}
		}

		$this->cart->check_inventory();
		
		// cartthrob_multi_add_to_cart_end hook
		if ($this->EE->extensions->active_hook('cartthrob_multi_add_to_cart_end') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_multi_add_to_cart_end', $entry_ids);
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
					->action_complete();
	}
	
	public function save_customer_info_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->EE->load->library('form_builder');
		
		if ($this->EE->extensions->active_hook('cartthrob_save_customer_info_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_save_customer_info_start');
		}
		
		if ($this->EE->form_builder->validate())
		{
			$this->cartthrob->save_customer_info();
		}
		else
		{
			$this->EE->cartthrob_variables->set_global_values();
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_save_customer_info_end') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_save_customer_info_end');
		}
	
		$this->EE->form_builder->set_success_callback(array($this->cart, 'save'))
					->action_complete();
	}
	

	/**
	 * payment_return_action
	 *
	 * handles information from PayPal's IPN, offsite gateways, or other payment notification systems. 
	 * @param string $gateway the payment gateway class/file that should called
	 * @param string $method the method in the gateway class that should handle the transaction
	 * @return void
	 * @author Chris Newton
	 * @since 1.0
	 * @access public
	 */
	public function payment_return_action($gateway = NULL, $method = NULL)
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		$this->EE->load->library('encrypt');
		
		$gateway = xss_clean($this->EE->encrypt->decode(str_replace(' ', '+', base64_decode($this->EE->input->get_post('gateway')))));
		if (!$gateway)
		{
			$gateway = xss_clean($this->EE->encrypt->decode(str_replace(' ', '+', base64_decode($this->EE->input->get_post('G')))));
		}
		
		
		// When offsite payments are returned, they're expected to have a method
		// set to handle processing the payments. 	
		if ($this->EE->input->get_post('method'))
		{
			$method = xss_clean($this->EE->encrypt->decode(str_replace(' ', '+', base64_decode($this->EE->input->get_post('method')))));
		}
		elseif ($this->EE->input->get_post('M'))
		{
			$method = xss_clean($this->EE->encrypt->decode(str_replace(' ', '+', base64_decode($this->EE->input->get_post('M')))));
		}
		$this->EE->load->library('cartthrob_payments');

		$auth = array(
			'processing' => FALSE,
			'authorized' => FALSE,
			'declined' => FALSE,
			'failed' => TRUE,
			'error_message' => '',
			'transaction_id' => '',
		);
		
		if ( ! $this->EE->cartthrob_payments->set_gateway($gateway)->gateway())
		{
			$auth['error_message'] = $this->EE->lang->line('invalid_payment_gateway');
		}
		else
		{
			if ($method && method_exists($this->EE->cartthrob_payments->gateway(), $method))
			{
				$data = $this->EE->security->xss_clean($_POST);
				
				// handling get variables.
				if ($_SERVER['QUERY_STRING'])
				{
					// the following was added to convert the query string manually into an array
					// because something like &company=abercrombie&fitch&name=joe+jones was causing the return
					// data to get hosed. Stupid PayPal. You suck. URLencode your goddamned querystrings in your
					// IPN notifications. Fucking bastards.
					$_SERVER['QUERY_STRING'] = preg_replace("/&(?=[^=]*&)/", "%26", $_SERVER['QUERY_STRING']);
					
					$get = array();
					parse_str($_SERVER['QUERY_STRING'], $get);
					
					foreach($get as $key => $value) 
					{
						if ( ! isset($data[$key]))
						{
							$data[$key] = xss_clean($value);
						}
					}
				}
				
				foreach ($data as $key=> $item)
				{
					$this->cartthrob->log($key.' - '.$item);
				}
				
				$auth = $this->EE->cartthrob_payments->gateway()->$method($data);
			}
			else
			{
				$auth['error_message']	= $this->EE->lang->line('gateway_function_does_not_exist');
			}
		}
		
		$this->checkout_complete($auth);
	}
	// END
 
	/* NOT READY FOR PRIME TIME!
	public function create_token_form()
	{
		if ($this->EE->session->userdata('member_id') == 0)
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}

		$this->EE->load->library('api/api_cartthrob_payment_gateways');

		if ($this->store->config('allow_gateway_selection'))
		{
			if ($this->EE->TMPL->fetch_param('gateway'))
			{
				$this->EE->api_cartthrob_payment_gateways->set_gateway($this->EE->TMPL->fetch_param('gateway'));
			}
		}
		else
		{
			unset($this->EE->TMPL->tagparams['gateway']);
		}
		// @TODO add token_fields method
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$data = ['token_fields'] = $this->EE->api_cartthrob_payment_gateways->token_fields();

		$this->EE->load->library('form_builder');

		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
				'authorized_redirect',
				'failed_redirect',
			),
			'encoded_form_data' => array(
				'gateway' => 'gateway'
			),
			'classname' => 'Cartthrob',
			'method' => 'token_action',
			'params' => $this->EE->TMPL->tagparams,
			'action' => $this->store->config('payment_system_url'),
			'content' => $this->EE->TMPL->parse_variables(array($data)),
		));

		return $this->EE->form_builder->form();
	}


	protected function token_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}

		$this->cartthrob->save_customer_info();

		$this->EE->load->library('form_validation');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('form_builder');

		$this->EE->form_builder->set_show_errors(TRUE)
					->set_success_callback(array($this->cart, 'save'))
					->set_error_callback(array($this->cart, 'save'));

		$this->EE->load->library('languages');

		$this->EE->languages->set_language($this->EE->input->post('language', TRUE));

		$not_required = array();

		$required = array();

		if ($this->EE->input->post('REQ'))
		{
			$required_string = xss_clean($this->EE->encrypt->decode($this->EE->input->post('REQ')));

			if (preg_match('/^not (.*)/', $required_string, $matches))
			{
				$not_required = explode('|', $matches[1]);
				$required_string = '';
			}

			if ($required_string)
			{
				$required = explode('|', $required_string);
			}
			unset($required_string);
		}	
		$gateway = ($this->store->config('allow_gateway_selection') && $this->EE->input->post('gateway')) ? xss_clean($this->EE->encrypt->decode($this->EE->input->post('gateway'))) : $this->store->config('payment_gateway');

		$credit_card_number = sanitize_credit_card_number($this->EE->input->post('credit_card_number', TRUE));
		if ($this->store->config('modulus_10_checking') && ! modulus_10_check($credit_card_number))
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('validation_card_modulus_10'))
						->action_complete();
		}

		// Load the payment processing plugin that's stored in the extension's settings.
		$this->EE->load->library('cartthrob_payments');

		if ( ! $this->EE->cartthrob_payments->set_gateway($gateway)->gateway())
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('invalid_payment_gateway'))
						->action_complete();
		}

		$authorized_redirect = $this->EE->input->post('authorized_redirect', TRUE);
		$failed_redirect = $this->EE->input->post('failed_redirect', TRUE);

		// @TODO add the required_token_fields method
	 	$required = array_merge($required, $this->EE->cartthrob_payments->required_token_fields());
		foreach ($not_required as $key)
		{
			unset($required[array_search($key, $required)]);
		}
		if ($required)
		{
			foreach ($required as $key)
			{
				if (preg_match('/^custom_data\[(.*)\]$/', $key, $match))
				{
					$message = sprintf($this->EE->lang->line('validation_custom_data'), $match[1]);
				}
				else
				{
					$message = $this->EE->lang->line('validation_'.$key);
				}

				$this->EE->form_validation->set_rules($key, $message, 'required');
			}

			if ( ! $this->EE->form_validation->run())
			{
				$this->EE->form_builder->add_error($this->EE->form_validation->_error_array)
							->action_complete();
			}
		}

		$token_data = array(
	 		'member_id'			=> $this->cart->customer_info('member_id'),
			'last_four'			=> substr($credit_card_number,-4,4),
			'description'		=> $this->EE->input->post('description', TRUE),
			'token_id'			=> NULL,
			'id'				=> NULL,
		);


		$auth = $this->EE->cartthrob_payments->create_token($credit_card_number);

		if ($auth['authorized'])
		{
			$token_array['token_id'] = $auth['transaction_id'];

			$this->EE->db->insert('cartthrob_tokens', $token_data);

			$token_array['id'] = $this->EE->db->insert_id();
		}
		else
		{
			$this->EE->form_builder->set_return($this->cart->order('failed_redirect'))
					       ->add_error(element('error_message', $auth));
		}

		$this->EE->form_builder->action_complete();
	}
	*/
	
	// @TODO make this function read the gateway out of the database based on the provided entry id
	public function update_recurrent_billing_form()
	{
		if ($this->EE->session->userdata('member_id') == 0)
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}

		$this->EE->load->library('api/api_cartthrob_payment_gateways');

		if ($this->EE->TMPL->fetch_param('gateway'))
		{
			$this->EE->api_cartthrob_payment_gateways->set_gateway($this->EE->TMPL->fetch_param('gateway'));
		}
		
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$data['recurrent_billing_fields'] = $this->EE->api_cartthrob_payment_gateways->gateway_fields(FALSE, 'recurrent_billing_update');
		$data['gateway_fields'] = $this->EE->api_cartthrob_payment_gateways->gateway_fields();

 		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->initialize(array(
			'classname' => 'Cartthrob',
			'method' => 'update_recurrent_billing_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
				
			),
			'encoded_form_data' => array(
				'required' 					=> 'REQ',
				'gateway' 					=> 'gateway',
				'subscription_name'					=> 'SUN',
				'subscription_start_date'			=> 'SSD',
				'subscription_end_date'				=> 'SED',
				'subscription_interval_units'		=> 'SIU',
				'sub_id'							=> 'SD',
				'subscription_type'					=> 'SUT',
			),
			'encoded_numbers' => array(
				'subscription_total_occurrences'	=> 'SO',
				'subscription_trial_price'			=> 'ST',
				'subscription_trial_occurrences'	=> 'SP',
				'subscription_interval'				=> 'SI',
				'order_id'							=> 'OI',
				
			),
			'encoded_bools' => array(
				'allow_user_price' => 'AUP',
				//'show_errors' => array('ERR', TRUE),
				'json' => 'JSN',
				'subscription_allow_modification'		=> 'SM',
				
			),
		));
		return $this->EE->form_builder->form();
	}
	
	public function update_recurrent_billing_action()
	{
		// currently we allow the customer information stored on file with the recurrent bill to be changed. 
		// the actual details of the original order are not changed however. 
		// over time we need feedback about what needs to be added / changed in the original order
		// or purchased items when someone decides to update their subscription
		// not all systems allow the sub itself to be updated, but they all allow customer information
		// like credit card numbers to be changed. For our purposes, we're currently only using this
		// as a card data update. 
		
		// @TODO catch the sub id, and order id. 
		
		$total = 0; 
		
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}

		$this->cartthrob->save_customer_info();
		
		$this->EE->load->library('form_validation');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->set_show_errors(TRUE)
					->set_success_callback(array($this->cart, 'save'))
					->set_error_callback(array($this->cart, 'save'));
		
		if (! $this->store->config('save_orders'))
		{
			$this->EE->form_builder->action_complete();
		}
				
		$this->EE->load->library('languages');

		$this->EE->languages->set_language($this->EE->input->post('language', TRUE));

		$not_required = array();
		$required = array();

		if ($this->EE->input->post('REQ'))
		{
			$required_string = xss_clean($this->EE->encrypt->decode($this->EE->input->post('REQ')));

			if (preg_match('/^not (.*)/', $required_string, $matches))
			{
				$not_required = explode('|', $matches[1]);
				$required_string = '';
			}

			if ($required_string)
			{
				$required = explode('|', $required_string);
			}

			unset($required_string);
		}

		$gateway = ($this->EE->input->post('gateway')) ? xss_clean($this->EE->encrypt->decode($this->EE->input->post('gateway'))) : $this->store->config('payment_gateway');

		$credit_card_number = sanitize_credit_card_number($this->EE->input->post('credit_card_number', TRUE));

		// Load the payment processing plugin that's stored in the extension's settings.
		$this->EE->load->library('cartthrob_payments');

		if ( ! $this->EE->cartthrob_payments->set_gateway($gateway)->gateway())
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('invalid_payment_gateway'))
						->action_complete();
		}

		$authorized_redirect = $this->EE->input->post('authorized_redirect', TRUE);
		$failed_redirect = $this->EE->input->post('failed_redirect', TRUE);
		$declined_redirect = $this->EE->input->post('declined_redirect', TRUE);
 
		if ($this->EE->input->post('EXP'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('EXP')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$expiration_date = $data;
			}
		}
		
		if ($this->EE->input->post('PR'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('PR')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$total -= $subtotal;
				$subtotal = $data;
				$total += $subtotal;
			}
		}
		elseif ($this->EE->input->post('AUP'))
		{
			if (bool_string($this->EE->encrypt->decode($this->EE->input->post('AUP'))))
			{
				$total = sanitize_number($this->EE->input->post('price', TRUE));
			}
		}
		
		if ($this->EE->input->post('OI'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('OI')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$order_id = $data; 
			}
		}
		
		if ($this->EE->input->post('SD'))
		{
			$sub_id = xss_clean($this->EE->encrypt->decode($this->EE->input->post('SD')));
		}
		
		foreach ($not_required as $key)
		{
			unset($required[array_search($key, $required)]);
		}
		
		
		if ( ! $this->EE->form_builder->set_required($required)->validate())
		{
			$this->EE->form_builder->action_complete();
		}
	
		$order_data = $this->order_data_array(); 
		
		$this->cart->set_order($order_data);
		
		$this->EE->cartthrob_payments->set_total($total);
		
		$this->cart->save();
		
		$auth = $this->EE->cartthrob_payments->update_recurrent_billing($sub_id, $credit_card_number);
		
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => '',
				'transaction_id' => '',
			),
			$auth
		);
		
		#$this->EE->session->set_flashdata($auth);
		
		//since we use the authorized variables as tag conditionals in submitted_order_info,
		//we won't throw any errors from here on out
		$this->EE->form_builder->set_show_errors(FALSE);

 
		if ($auth['authorized'])
		{
			$this->EE->form_builder->set_return($this->cart->order('authorized_redirect'));
		}
		else 
		{
			$this->EE->form_builder->set_return($this->cart->order('failed_redirect'))
					       ->add_error(element('error_message', $auth));
		}
		$this->cart->save();

		$this->EE->form_builder->action_complete();
		
	}
	// @TODO make this function read the gateway out of the database based on the provided entry id
	
	public function delete_recurrent_billing_form()
	{
		if ($this->EE->session->userdata('member_id') == 0)
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}

		$this->EE->load->library('api/api_cartthrob_payment_gateways');

		if ($this->EE->TMPL->fetch_param('gateway'))
		{
			$this->EE->api_cartthrob_payment_gateways->set_gateway($this->EE->TMPL->fetch_param('gateway'));
		}
		
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);

		$data['gateway_fields'] = $this->EE->api_cartthrob_payment_gateways->gateway_fields(FALSE, 'recurrent_billing_delete');

 		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->initialize(array(
			'classname' => 'Cartthrob',
			'method' => 'delete_recurrent_billing_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
				
			),
			'encoded_form_data' => array(
				'required' 					=> 'REQ',
				'sub_id'							=> 'SD',
				'gateway' 					=> 'gateway',
			),
			'encoded_numbers' => array(
				'order_id'							=> 'OI',
			),
			'encoded_bools' => array(
				'allow_user_price' => 'AUP',
				//'show_errors' => array('ERR', TRUE),
				'json' => 'JSN',
			),
		));
		return $this->EE->form_builder->form();
	}
	public function delete_recurrent_billing_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}

		$this->cartthrob->save_customer_info();
		
		$this->EE->load->library('form_validation');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->set_show_errors(TRUE)
					->set_success_callback(array($this->cart, 'save'))
					->set_error_callback(array($this->cart, 'save'));
		
		if (! $this->store->config('save_orders'))
		{
			$this->EE->form_builder->action_complete();
		}
				
		$this->EE->load->library('languages');

		$this->EE->languages->set_language($this->EE->input->post('language', TRUE));

		$not_required = array();
		$required = array();

		if ($this->EE->input->post('REQ'))
		{
			$required_string = xss_clean($this->EE->encrypt->decode($this->EE->input->post('REQ')));

			if (preg_match('/^not (.*)/', $required_string, $matches))
			{
				$not_required = explode('|', $matches[1]);
				$required_string = '';
			}

			if ($required_string)
			{
				$required = explode('|', $required_string);
			}

			unset($required_string);
		}

		$gateway = ($this->EE->input->post('gateway')) ? xss_clean($this->EE->encrypt->decode($this->EE->input->post('gateway'))) : $this->store->config('payment_gateway');

		// Load the payment processing plugin that's stored in the extension's settings.
		$this->EE->load->library('cartthrob_payments');

		if ( ! $this->EE->cartthrob_payments->set_gateway($gateway)->gateway())
		{
			$this->EE->form_builder->add_error($this->EE->lang->line('invalid_payment_gateway'))
						->action_complete();
		}

		$authorized_redirect = $this->EE->input->post('authorized_redirect', TRUE);
		$failed_redirect = $this->EE->input->post('failed_redirect', TRUE);
		$declined_redirect = $this->EE->input->post('declined_redirect', TRUE);
		
		if ($this->EE->input->post('OI'))
		{
			$data = xss_clean($this->EE->encrypt->decode($this->EE->input->post('OI')));

			if ($data == sanitize_number($data)) // ignore a non-numeric input
			{
				$order_id = $data; 
			}
		}
		
		if ($this->EE->input->post('SD'))
		{
			$sub_id = xss_clean($this->EE->encrypt->decode($this->EE->input->post('SD')));
		}
		
 		foreach ($not_required as $key)
		{
			unset($required[array_search($key, $required)]);
		}
		
		
		if ( ! $this->EE->form_builder->set_required($required)->validate())
		{
			$this->EE->form_builder->action_complete();
		}
	
		$order_data = $this->order_data_array(); 
		
		$this->cart->set_order($order_data);
		
		$this->cart->save();
		
		$auth = $this->EE->cartthrob_payments->delete_recurrent_billing($sub_id);
		
		$auth = array_merge(
			array(
				'processing' => FALSE,
				'authorized' => FALSE,
				'declined' => FALSE,
				'failed' => TRUE,
				'error_message' => '',
				'transaction_id' => '',
			),
			$auth
		);
		
		$this->cart->update_order(array_merge($auth, array('auth' => $auth)));
		
		$this->EE->session->set_flashdata($auth);
		
		//since we use the authorized variables as tag conditionals in submitted_order_info,
		//we won't throw any errors from here on out
		$this->EE->form_builder->set_show_errors(FALSE);
 
		if ($auth['authorized'])
		{
			$this->EE->form_builder->set_return($this->cart->order('authorized_redirect'));
		}
		else 
		{
			$this->EE->form_builder->set_return($this->cart->order('failed_redirect'))
					       ->add_error(element('error_message', $auth));
		}
		$this->cart->save();

		$this->EE->form_builder->action_complete();
	}
 	/**
	 * Prints a coupon code form.
	 *
	 * @access public
	 * @param string $TMPL->fetch_param('action')
	 * @param string $TMPL->fetch_param('id')
	 * @param string $TMPL->fetch_param('class')
	 * @param string $TMPL->fetch_param('name')
	 * @param string $TMPL->fetch_param('onsubmit')
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function add_coupon_form()
	{
		if ( ! $this->EE->session->userdata('member_id'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('form_builder');

		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$data['allowed'] = 1;

		if ($this->store->config('global_coupon_limit') && count($this->cart->coupon_codes()) >= $this->store->config('global_coupon_limit'))
		{
			$data['allowed'] = 0;
		}
		
		$this->EE->form_builder->initialize(array(
			'classname' => 'Cartthrob',
			'method' => 'add_coupon_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
			),
			'encoded_form_data' => array(
			),
			'encoded_numbers' => array(
			),
			'encoded_bools' => array(
				//'show_errors' => array('ERR', TRUE),
				'json' => 'JSN',
			),
		));

		return $this->EE->form_builder->form();
	}

	public function add_to_cart()
	{
		// cartthrob_add_to_cart_start hook
		if ($this->EE->extensions->active_hook('cartthrob_add_to_cart_start') === TRUE)
		{
			//@TODO work on hook parameters
			//$edata = $EXT->universal_call_extension('cartthrob_add_to_cart_start', $this, $_SESSION['cartthrob']);
			$this->EE->extensions->call('cartthrob_add_to_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$data = array(
			'entry_id' => $this->EE->TMPL->fetch_param('entry_id'),
			'quantity' => ($this->EE->TMPL->fetch_param('quantity') !== FALSE) ? $this->EE->TMPL->fetch_param('quantity') : 1,
			'class' => 'product',
		);
		
		foreach ($this->EE->TMPL->tagparams as $key => $value)
		{
			if (preg_match('/^item_options?:(.*)$/', $key, $match))
			{
				if ( ! isset($data['item_options']))
				{
					$data['item_options'] = array();
				}
				
				$data['item_options'][$match[1]] = $value;
			}
		}

		if (bool_string($this->EE->TMPL->fetch_param('no_shipping')))
		{
			$data['no_shipping'] = TRUE;
		}

		if (bool_string($this->EE->TMPL->fetch_param('no_tax')))
		{
			$data['no_tax'] = TRUE;
		}
		
		$data['product_id'] = $data['entry_id'];
		
		if ( ! $data['entry_id'])
		{
			$this->cartthrob->set_error(lang('add_to_cart_no_entry_id'));
		}
		
		if ( ! $this->cartthrob->errors())
		{
			$entry = $this->EE->product_model->get_product($data['entry_id']);
			
			//it's a package
			if ($entry && $field_id = $this->EE->cartthrob_field_model->channel_has_fieldtype($entry['channel_id'], 'cartthrob_package', TRUE))
			{
				$data['class'] = 'package';
				
				$this->EE->load->library('api');
				
				$this->EE->api->instantiate('channel_fields');
				
				if (empty($this->EE->api_channel_fields->field_types))
				{
					$this->EE->api_channel_fields->fetch_installed_fieldtypes();
				}
				
				$data['sub_items'] = array();
				
				if ($this->EE->api_channel_fields->setup_handler('cartthrob_package'))
				{
					$field_data = $this->EE->api_channel_fields->apply('pre_process', array($entry['field_id_'.$field_id]));
					
					foreach ($field_data as $row_id => $row)
					{
						$item = array(
							'entry_id' => $row['entry_id'],
							'product_id' => $row['entry_id'],
							'row_id' => $row_id,
							'class' => 'product',
						);
						
						$item['item_options'] = (isset($row['option_presets'])) ? $row['option_presets'] : array();
						
						$row_item_options = array();
						
						if (isset($_POST['item_options'][$row_id]))
						{
							$row_item_options = $_POST['item_options'][$row_id];
						}
						else if (isset($_POST['item_options'][':'.$row_id]))
						{
							$row_item_options = $_POST['item_options'][':'.$row_id];
						}
						
						$price_modifiers = $this->EE->product_model->get_all_price_modifiers($row['entry_id']);
						
						foreach ($row_item_options as $key => $value)
						{
							//if it's not a price modifier (ie, an on-the-fly item option), add it
							//if it is a price modifier, check that it's been allowed before adding
							if ( ! isset($price_modifiers[$key]) || ! empty($row['allow_selection'][$key]))
							{
								$item['item_options'][$key] = $this->EE->security->xss_clean($value);
							}
						}
						
						$data['sub_items'][$row_id] = $item;
					}
				}
			}
			
			$item = $this->cart->add_item($data);
			
			if ($item && $this->EE->input->post('PER') )
			{
				$item->set_meta('permissions', $this->EE->TMPL->fetch_param('permissions') );
			}
			if ($item && bool_string($this->EE->TMPL->fetch_param('license_number')))
			{
				$item->set_meta('license_number', TRUE);
			}
	
			// cartthrob_add_to_cart_end hook
			if ($this->EE->extensions->active_hook('cartthrob_add_to_cart_end') === TRUE)
			{
				//@TODO work on hook parameters
				//$edata = $EXT->universal_call_extension('cartthrob_add_to_cart_end', $this, $_SESSION['cartthrob'], $row_id);
				$this->EE->extensions->call('cartthrob_add_to_cart_end', $item);
				if ($this->EE->extensions->end_script === TRUE) return;
			}
		}
		
		$show_errors = bool_string($this->EE->TMPL->fetch_param('show_errors'), TRUE);
		
		$this->EE->session->set_flashdata(array(
			'success' => ! (bool) $this->cartthrob->errors(),
			'errors' => $this->cartthrob->errors(),
		));
		
		if ($show_errors && $this->cartthrob->errors() && ! AJAX_REQUEST)
		{
			return $this->EE->output->show_user_error('general', $this->cartthrob->errors());
		}
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	// --------------------------------
	//  Add to Cart Form
	// --------------------------------
	/**
	 * add_to_cart_form
	 *
	 * This tag creates a form for adding one or more products to the cart object
	 * 
	 * @return string Tagdata output
	 * @author Rob Sanchez, Chris Newton
	 * @since 1.0
	 * @access public
	 */
	public function add_to_cart_form()
	{
		if ( ! $this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'entry_id',
				'quantity',
				'secure_return',
				'title',
				'language',
				'return'
			),
			'encoded_form_data' => array(
				'shipping' => 'SHP',
				'weight' => 'WGT', 
				'permissions'	=> 'PER',
				'subscription_name'					=> 'SUN',
				'subscription_start_date'			=> 'SSD',
				'subscription_end_date'				=> 'SED',
				'subscription_interval_units'		=> 'SIU',
				'subscription_type'					=> 'SUT',
			),
			'encoded_numbers' => array(
				'price' => 'PR',
				'expiration_date' => 'EXP',
				'subscription_total_occurrences'	=> 'SO',
				'subscription_trial_price'			=> 'ST',
				'subscription_trial_occurrences'	=> 'SP',
				'subscription_interval'				=> 'SI',
			),
			'encoded_bools' => array(
				'allow_user_price' => 'AUP',
				'allow_user_weight' => 'AUW',
				'allow_user_shipping' => 'AUS',
				'no_tax' => 'NTX',
				'no_shipping' => 'NSH',
				'on_the_fly' => 'OTF',
				'subscription'	=> 'SUB',
				'show_errors' => array('ERR', TRUE),
				'json' => 'JSN',
				'license_number' => 'LIC', 
				'subscription_allow_modification'		=> 'SM',
			),
			'array_form_data' => array(
				'item_options',
			),
			'classname' => 'Cartthrob',
			'method' => 'add_to_cart_action',
			'params' => $this->EE->TMPL->tagparams,
		));
 		
		$data = array_merge(
			$this->EE->cartthrob_variables->item_option_vars($this->EE->TMPL->fetch_param('entry_id')),
			$this->EE->cartthrob_variables->global_variables(TRUE)
		);
		
		foreach ($this->EE->TMPL->var_single as $var)
		{
			if (preg_match('/^inventory:reduce(.+)$/', $var, $match))
			{
				$data[$match[0]] = '';
				
				$var_params = $this->EE->functions->assign_parameters($match[1]);
				
				if ( ! empty($var_params['entry_id']))
				{
					if (empty($var_params['quantity']))
					{
						$var_params['quantity'] = 1;
					}
					else
					{
						$var_params['quantity'] = sanitize_number($var_params['quantity']);
					}
					
					$this->EE->form_builder->set_hidden('inventory_reduce['.$var_params['entry_id'].']', $var_params['quantity']);
				}
			}
		}
		
		$this->EE->load->library('languages');
		
		$this->EE->languages->set_language($this->EE->TMPL->fetch_param('language'));
		
		$this->EE->form_builder->set_content($this->EE->template_helper->parse_variables(array($data)));
		
		return $this->EE->form_builder->form();
	}

	public function add_coupon_code()
	{
		$this->cart->add_coupon_code($this->EE->TMPL->fetch_param('coupon_code'));
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	// this tag should be used to display purchased entry ids by a specific person, or also purchased, or best sellers. 
	public function history()
	{
		$entry_id = $this->EE->TMPL->fetch_param('entry_id'); 
		$also_purchased = $this->EE->TMPL->fetch_param('also_purchased'); 
		$author = $this->EE->session->userdata('member_id'); 
		// sort
		// order by date. popularity. author, etc
	}
	
	public function purchased_entry_ids()
	{
		$data = array(); 
		
		$this->EE->load->model('purchased_items_model');
		
		$purchased = $this->EE->purchased_items_model->purchased_entry_ids();
		
		foreach ($purchased as  $entry_id)
		{
			$data[] = array('entry_id' => $entry_id);
 		}
		
		return $this->EE->template_helper->parse_variables($data);
	}
	/**
	 * also_purchased
	 *
	 * Tag pair will replace {entry_id} with entry id of related purchased items.
	 * @return string
	 * @param $TMPL entry_id
	 * @param $TMPL limit
	 * @author Chris Newton
	 * @since 1.0
	 **/
	public function also_purchased()
	{
		$data = array();
		
		if ($parent_id = $this->EE->TMPL->fetch_param('entry_id'))
		{
			$this->EE->load->model(array('purchased_items_model', 'cartthrob_entries_model'));
			
			$purchased = $this->EE->purchased_items_model->also_purchased($parent_id, $this->EE->TMPL->fetch_param('limit'));
			
			foreach ($purchased as $entry_id => $count)
			{
				if ($row = $this->EE->cartthrob_entries_model->entry_vars($entry_id))
				{
					$data[] = $row;
				}
			}
		}
		
		return $this->EE->template_helper->parse_variables($data);
	}
	// END

	/**
	 * arithmetic
	 * 
	 * This function does arithmetic calculations
	 *
	 * @return string
	 * @param string TEMPLATE PARAM operator + / - etc
	 * @author Rob Sanchez, Chris Barrett
	 * @access public
	 * @since 1.0
	 */
	public function arithmetic()
	{
		$this->EE->load->library(array('math', 'number'));
		
		if ($this->EE->TMPL->fetch_param('expression') !== FALSE)
		{
			if (bool_string($this->EE->TMPL->fetch_param('debug')))
			{
				return $this->EE->TMPL->fetch_param('expression');
			}
			
			$evaluation = ($this->EE->TMPL->fetch_param('expression')) ? $this->EE->math->evaluate($this->EE->TMPL->fetch_param('expression')) : 0;
		}
		else
		{
			$evaluation = $this->EE->math->arithmetic($this->EE->TMPL->fetch_param('num1'), $this->EE->TMPL->fetch_param('num2'), $this->EE->TMPL->fetch_param('operator'));
		}
		
		if ($evaluation === FALSE && bool_string($this->EE->TMPL->fetch_param('show_errors'), TRUE))
		{
			return $this->EE->math->last_error;
		}
		
		return $this->EE->number->format($evaluation);
	}
	// --------------------------------
	//  Cart Empty Redirect
	// --------------------------------
	/**
	 * Redirects if cart is empty.
	 * Place on your view cart page.
	 *
	 * @access public
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	*/
	public function cart_empty_redirect()
	{
 		if ($this->cart->is_empty())
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
		}
	}
	// END
	
	public function cart_form()
	{
		$this->EE->load->library(array('number', 'form_builder'));
		
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$data['items'] = array();
		
		foreach ($this->cart->items() as $row_id => $item)
		{
			$data['items'][$row_id] = $item->data();
			$data['items'][$row_id]['entry_id'] = $item->product_id();
			
			
			$row['item_price:numeric'] = $row['price:numeric'] = $row['item_price_numeric'] = $row['price_numeric'] = $row['price'];
			$row['item_price_plus_tax:numeric'] =$row['price_numeric:plus_tax'] = $row['price_plus_tax:numeric'] = $row['item_price_plus_tax_numeric'] = $row['price_plus_tax_numeric'] = $item->taxed_price();
			
			$row['item_price'] = $row['price'] = $this->EE->number->format($row['price_numeric']);
			$row['item_price_plus_tax'] = $row['price:plus_tax'] = $row['item_price:plus_tax'] =  $row['price_plus_tax'] = $this->EE->number->format( $item->taxed_price() );
			
			foreach ($this->EE->cartthrob_variables->item_option_vars($item->product_id(), $row_id) as $key => $value)
			{
				$data['items'][$row_id][$key] = $value;
			}
		}
		
		$this->EE->load->library('data_filter');
		
		$order_by = ($this->EE->TMPL->fetch_param('order_by')) ?  $this->EE->TMPL->fetch_param('order_by') : $this->EE->TMPL->fetch_param('orderby');
		
		$this->EE->data_filter->sort($data['items'], $order_by, $this->EE->TMPL->fetch_param('sort'));
		$this->EE->data_filter->limit($data['items'], $this->EE->TMPL->fetch_param('limit'), $this->EE->TMPL->fetch_param('offset'));
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
			),
			'encoded_form_data' => array(
			),
			'encoded_numbers' => array(
			),
			'encoded_bools' => array(
			),
			'classname' => 'Cartthrob',
			'method' => 'cart_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
		));
		
		return $this->EE->form_builder->form();
	}
	
	/**
	 * cart_entry_ids
	 * 
	 * returns a pipe delimited list of entry ids
	 *
	 * @return string
	 * @author Chris Newton
	 * @since 1.0
	 */
	public function cart_entry_ids()
	{	
		return implode('|', $this->cart->product_ids());
 	}
	/**
	 * cart_info
	 *
	 * Template tag that outputs generic cart info & conditionals related to totals and shipping
	 * 
	 * @return string
	 * @since 1.0
	 * @author Chris Newton
	 */
	public function cart_info()
	{
		$this->EE->TMPL->tagdata = $this->EE->functions->prep_conditionals($this->EE->TMPL->tagdata, $this->cart->info(FALSE));
		
 		return $this->EE->template_helper->parse_variables(array($this->EE->cartthrob_variables->global_variables()));
 	}
	/**
	 * cart_items_info
	 * 
	 * Prints out cart contents
	 *
	 * @access public
	 * @return string
	 * @since 1.0.
	 * @author Rob Sanchez
	*/
	public function cart_items_info()
	{
		$this->EE->load->library(array('number', 'typography'));
		
		$data = array();
		
		$global_vars = $this->EE->cartthrob_variables->global_variables();
		
		//@TODO add ability to limit to certain channels too. 
		$entry_ids = ($this->EE->TMPL->fetch_param('entry_id')) ? explode('|', $this->EE->TMPL->fetch_param('entry_id')) : FALSE;
		$row_ids = ($this->EE->TMPL->fetch_param('row_id') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('row_id')) : FALSE;
		
		$this->EE->load->library('api');
		$this->EE->api->instantiate('channel_fields');
		$this->EE->load->model(array('product_model', 'cartthrob_field_model'));
		
		$categories = (strpos($this->EE->TMPL->tagdata, '{categories') !== FALSE) ? $this->EE->product_model->get_categories() : FALSE;
		
		if ($categories)
		{
			$this->EE->cartthrob_entries_model->load_categories_by_entry_id($this->EE->cartthrob->cart->product_ids());
		}
		
		if (preg_match_all('#{packages?(.*?)}(.*?){/packages?}#s', $this->EE->TMPL->tagdata, $matches))
		{
			$package_tagdata = array();
			
			foreach ($matches[0] as $i => $full_match)
			{
				$package_tagdata[substr($full_match, 1, -1)] = $matches[2][$i];
			}
		}
		
		foreach ($this->cart->items() as $row_id => $item)
		{
			if (($entry_ids && ! in_array($item->product_id(), $entry_ids)) || ($row_ids && ! in_array($row_id, $row_ids)))
			{
				continue;
			}
			
			$row = $this->EE->cartthrob_variables->item_vars($item, $global_vars);
			
			if (isset($package_tagdata))
			{
				foreach ($package_tagdata as $full_match => $_package_tagdata)
				{
					$row[$full_match] = '';
					
					foreach ($this->EE->cartthrob_variables->sub_item_vars($item, $global_vars, $_package_tagdata) as $sub_row)
					{
						$row[$full_match] .= $this->EE->TMPL->parse_variables($_package_tagdata, array($sub_row));
					}
				}
			}
			
			$row['is_package'] = ($item->sub_items()) ? 1 : 0;
			
			$data[] = $row;
		}
		
		//alternate for nested tag
		if (preg_match('/'.LD.'if no_items'.RD.'(.*?)'.LD.'\/if'.RD.'/s', $this->EE->TMPL->tagdata, $match))
		{
			$this->EE->TMPL->tagdata = str_replace($match[0], '', $this->EE->TMPL->tagdata);
			
			$this->EE->TMPL->no_results = $match[1];
		}
		
		if ( ! $data)
		{
			return $this->EE->TMPL->no_results();
		}
		
 		$this->EE->load->library('data_filter');
		
		$order_by = ($this->EE->TMPL->fetch_param('order_by')) ? $this->EE->TMPL->fetch_param('order_by') : $this->EE->TMPL->fetch_param('orderby');
		
		$this->EE->data_filter->sort($data, $order_by, $this->EE->TMPL->fetch_param('sort'));
		$this->EE->data_filter->limit($data, $this->EE->TMPL->fetch_param('limit'), $this->EE->TMPL->fetch_param('offset'));
		
		foreach ((array) $this->EE->TMPL->tagparams as $key => $value)
		{
			if (strncmp($key, 'search:', 7) === 0)
			{
				$key = substr($key, 7);
				
				$exact = FALSE;
				
				$operator = NULL;
				
				if ($value && in_array($value[0], array('=', '>', '>=', '<', '<=')))
				{
					$exact = $value[0] === '=';
					
					$operator = $value[0];
					
					$value = substr($value, 1);
				}
				
				$not = FALSE;
				
				if (strncmp('not ', $value, 4) === 0)
				{
					$not = TRUE;
					
					$value = substr($value, 4);
				}
				
				$and = FALSE;
				
				$array = FALSE;
				
				if (strstr($value, '&&'))
				{
					$and = TRUE;
					
					$array = TRUE;
				}
				else if (strstr($value, '|'))
				{
					$array = TRUE;
				}
				
				if ($array)
				{
					if ($exact)
					{
						if ($not)
						{
							$this->EE->data_filter->filter($data, $key, $value, 'NOT_IN', TRUE);
						}
						else
						{
							$this->EE->data_filter->filter($data, $key, $value, 'IN', TRUE);
						}
					}
					else
					{
						if ($not)
						{
							if ($and)
							{
								$this->EE->data_filter->filter($data, $key, explode('&&', $value), 'DOES_NOT_CONTAIN_ALL_OF', TRUE);
							}
							else
							{
								$this->EE->data_filter->filter($data, $key, $value, 'DOES_NOT_CONTAIN_ONE_OF', TRUE);
							}
						}
						else
						{
							if ($and)
							{
								$this->EE->data_filter->filter($data, $key, explode('&&', $value), 'CONTAINS_ALL_OF', TRUE);
							}
							else
							{
								$this->EE->data_filter->filter($data, $key, $value, 'CONTAINS_ONE_OF', TRUE);
							}
						}
					}
				}
				else
				{
					if ($operator)
					{
						if ($exact)
						{
							$this->EE->data_filter->filter($data, $key, $value, '==', TRUE);
						}
						else
						{
							$this->EE->data_filter->filter($data, $key, $value, $operator, TRUE);
						}
					}
					else
					{
						$this->EE->data_filter->filter($data, $key, $value, 'CONTAINS', TRUE);
					}
				}
			}
		}
		
		if ( ! $data)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$count = 1;
		//$total_results = $this->cart->count();
		$total_results = count($data);
		
		foreach ($data as &$row)
		{
			$row['cart_count'] = $count;
			$row['cart_total_results'] = $total_results;
			$row['first_row'] = ($count === 1) ? TRUE : FALSE;
			$row['last_row'] = ($count === $total_results) ? TRUE : FALSE;
			
			$count++;
		}
		
		$return_data = $this->EE->template_helper->parse_variables($data);
		
		return $return_data;
	}

	public function cart_discount()
	{
		$this->EE->load->library('number');
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $this->EE->cartthrob->cart->discount();
		}
		
 		return $this->EE->number->format($this->EE->cartthrob->cart->discount());
	}

	
	/**
	 * Returns discount percentage of total
	 * Uses number format params.
	 *
	 * @access public
	 * @param int $TMPL->fetch_param('decimals')
	 * @param string $TMPL->fetch_param('dec_point')
	 * @param string $TMPL->fetch_param('thousands_sep')
	 * @param string $TMPL->fetch_param('prefix')
	 * @return string
	 * @since 1.0.0
	 * @author Chris Newton
	 */
	public function cart_discount_percent_of_total()
	{
		return $this->EE->cartthrob->cart->discount() / $this->EE->cartthrob->cart->total() * 100;
	}

	/**
	 * Returns discount percentage of subtotal
	 * Uses number format params.
	 *
	 * @access public
	 * @param int $TMPL->fetch_param('decimals')
	 * @param string $TMPL->fetch_param('dec_point')
	 * @param string $TMPL->fetch_param('thousands_sep')
	 * @param string $TMPL->fetch_param('prefix')
	 * @return string
	 * @since 1.0.0
	 * @author Chris Newton
	 */
	public function cart_discount_percent_of_subtotal()
	{
		return $this->EE->cartthrob->cart->discount() / $this->EE->cartthrob->cart->subtotal() * 100;
	}

	public function cart_subtotal()
	{
		$this->EE->load->library('number');
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $this->EE->cartthrob->cart->subtotal();
		}
		
		return $this->EE->number->format($this->EE->cartthrob->cart->subtotal());
	}

	public function cart_subtotal_plus_tax()
	{
		$this->EE->load->library('number');
		$this->EE->load->library('api/api_cartthrob_tax_plugins');
		// adding the subtotal to the tax on the taxable subtotal. Can't add the taxable subtotal to this value. We need the tax on the taxable amount, added to the WHOLE amount
		
		$subtotal_plus_tax = 0; 
		foreach ($this->EE->cartthrob->cart->taxable_items() as $key => $item)
		{
			$item_price_plus_tax = $item->price() + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $item->price()); 
			$subtotal_plus_tax += $this->EE->cartthrob->round( $item_price_plus_tax ) * $item->quantity();
		}
		
		#$subtotal_plus_tax = $this->cart->subtotal() + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $this->cart->taxable_subtotal() );
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $subtotal_plus_tax;
		}
		
		return $this->EE->number->format($subtotal_plus_tax);
	}
	
	public function cart_subtotal_plus_shipping()
	{
		$this->EE->load->library('number');
		
		$subtotal_plus_shipping = $this->EE->cartthrob->cart->subtotal() + $this->EE->cartthrob->cart->shipping();
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $subtotal_plus_shipping;
		}
		
		return $this->EE->number->format($subtotal_plus_shipping); 
	}

	public function cart_shipping()
	{
		$this->EE->load->library('number');
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $this->EE->cartthrob->cart->shipping();
		}
		
		return $this->EE->number->format($this->EE->cartthrob->cart->shipping());
	}
	
	public function cart_shipping_plus_tax()
	{
		$this->EE->load->library(array('number', 'api/api_cartthrob_tax_plugins'));
		
		$shipping_plus_tax = $this->EE->cartthrob->cart->shipping() + ($this->EE->api_cartthrob_tax_plugins->tax_rate() * $this->EE->cartthrob->cart->shipping());
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $shipping_plus_tax;
		}
		
		return $this->EE->number->format($shipping_plus_tax);
	}
	
	public function cart_tax()
	{
		$this->EE->load->library('number');
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $this->EE->cartthrob->cart->tax();
		}
		
		return $this->EE->number->format($this->EE->cartthrob->cart->tax());
	}

	public function cart_tax_rate()
	{
		$this->EE->load->library('api/api_cartthrob_tax_plugins');
		
		return $this->EE->api_cartthrob_tax_plugins->tax_rate();
	}

	/**
	 * Returns total price of all items in cart
	 * The formula is subtotal + tax + shipping - discount
	 * Uses number format params.
	 *
	 * @access public
	 * @param int $TMPL->fetch_param('decimals')
	 * @param string $TMPL->fetch_param('dec_point')
	 * @param string $TMPL->fetch_param('thousands_sep')
	 * @param string $TMPL->fetch_param('prefix')
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function cart_total()
	{
		$this->EE->load->library('number');
		
		if (isset($this->EE->TMPL->tagparts[2]) && $this->EE->TMPL->tagparts[2] === 'numeric')
		{
			return $this->EE->cartthrob->cart->total();
		}
		
		return $this->EE->number->format($this->EE->cartthrob->cart->total());
	}
	
	public function cart_weight()
	{
		return $this->cart->weight();
	}

	public function change_quantity()
	{
		if ($item = $this->cart->item($this->EE->TMPL->fetch_param('row_id')))
		{
			$item->set_quantity($this->EE->TMPL->fetch_param('quantity'));
		}
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	public function check_cc_number_errors()
	{
		$data = array(
			'errors' => '',
			'valid' => TRUE
		);
		
		if ( ! $this->EE->TMPL->fetch_param('credit_card_number'))
		{
			$data['errors'] = $this->EE->lang->line('validate_cc_number_missing');// return lang missing number. 
		}
		
		$response = validate_credit_card($this->EE->TMPL->fetch_param('credit_card_number'), $this->EE->TMPL->fetch_param('card_type')); 
		
		if ( ! $response['valid'])
		{
			$data['errors'] = $response['error_code'];
			
			$data['valid'] = FALSE;
			
			switch ($response['error_code'])
			{
				case "1": 
					$data['errors'] = $this->EE->lang->line('validate_cc_card_type_unknown');
				break;
				case "2":
					$data['errors'] = $this->EE->lang->line('validate_cc_card_type_mismatch');
				break;
				case "3": 
					$data['errors'] = $this->EE->lang->line('validate_cc_invalid_card_number');
				break;
				case "4":
					$data['errors'] = $this->EE->lang->line('validate_cc_incorrect_card_length');
				break;
				default: 
					$data['errors'] = $this->EE->lang->line('validate_cc_card_type_unknown');
			}
			
		}
		
		return $this->EE->template_helper->parse_variables(array($data));
 	}
	//END
 

	public function checkout_form()
	{
		if ($this->EE->session->userdata('member_id') == 0)
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}

		if ($this->cart->is_empty())
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('cart_empty_redirect'));
		}
		
		if ( ! $this->EE->TMPL->fetch_param('id'))
		{
			$this->EE->TMPL->tagparams['id'] = 'checkout_form';
		}
		
		$this->EE->load->library('api/api_cartthrob_payment_gateways');
		
		if ($this->store->config('allow_gateway_selection'))
		{
			if ($this->EE->TMPL->fetch_param('gateway'))
			{
				$this->EE->api_cartthrob_payment_gateways->set_gateway($this->EE->TMPL->fetch_param('gateway'));
			}
		}
		else
		{
			unset($this->EE->TMPL->tagparams['gateway']);
		}
		
		if (strpos($this->EE->TMPL->tagdata, '{gateway_fields}') !== FALSE)
		{
			$this->EE->TMPL->tagdata = str_replace('{gateway_fields}', $this->EE->api_cartthrob_payment_gateways->gateway_fields(), $this->EE->TMPL->tagdata);
		}
		
		if (isset($this->EE->TMPL->tagparams['required']) && strncmp($this->EE->TMPL->tagparams['required'], 'not ', 4) === 0)
		{
			$this->EE->TMPL->tagparams['not_required'] = substr($this->EE->TMPL->tagparams['required'], 4);
			
			unset($this->EE->TMPL->tagparams['required']);
		}
		
		$this->EE->load->library('form_builder');
		
		$this->EE->form_builder->initialize(array(
			'captcha' => (bool) ( ! $this->EE->session->userdata('member_id') && $this->EE->cartthrob->store->config('checkout_form_captcha')),
			'form_data' => array(
				'action',
				'secure_return',
				'return',
				'language',
				'authorized_redirect',
				'failed_redirect',
				'declined_redirect',
				'processing_redirect',
				'create_user'
			),
			'encoded_form_data' => array(
				'file' 								=> 'FI',
				'not_required' 							=> 'NRQ',
				'gateway' 							=> 'gateway',
				'permissions'						=> 'PER',
				'subscription_name'					=> 'SUN',
				'subscription_start_date'			=> 'SSD',
				'subscription_end_date'				=> 'SED',
				'subscription_interval_units'		=> 'SIU',
				'subscription_type'					=> 'SUT',
			),
			'encoded_numbers' => array(
				'price' => 'PR',
				'shipping' => 'SHP',
				'tax' => 'TX',
				'group_id' => 'GI',
				'expiration_date' => 'EXP',
				'subscription_total_occurrences'	=> 'SO',
				'subscription_trial_price'			=> 'ST',
				'subscription_trial_occurrences'	=> 'SP',
				'subscription_interval'				=> 'SI',
			),
			'encoded_bools' => array(
				'allow_user_price' 					=> 'AUP',
				'subscription'						=> 'SUB',
				'no_tax'							=> 'NTX',
				'no_shipping' 						=> 'NSH',
				'on_the_fly' 						=> 'OTF',
				//'show_errors' 						=> array('ERR', TRUE),
				'json'								=> 'JSN',
				'license_number' 					=> 'LIC',
				'subscription_allow_modification'	=> 'SM',
			),
			'classname' => 'Cartthrob',
			'method' => 'checkout_action',
			'params' => $this->EE->TMPL->tagparams,
			'action' => $this->store->config('payment_system_url'),
		));
		
		//do this after initialize so captch vars are set
		$variables = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$this->EE->form_builder->set_content($this->EE->template_helper->parse_variables(array($variables)));
		
		return $this->EE->form_builder->form().$this->EE->api_cartthrob_payment_gateways->gateway('form_extra');
	}

	/**
	 * Empties the cart
	 *
	 * @access public
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function clear_cart()
	{
		$this->cart->clear()
			   ->clear_coupon_codes()
			   ->clear_totals()
			   ->clear_shipping_info();

		if (bool_string($this->EE->TMPL->fetch_param('clear_customer_info')))
		{
			$this->cart->clear_customer_info()
				   ->clear_custom_data();
		}
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}

	public function clear_coupon_codes()
	{
		$this->cart->clear_coupon_codes()->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}

	public function convert_country_code()
	{
		$this->EE->load->library('locales');
		
		$code = $this->EE->locales->alpha3_country_code($this->EE->TMPL->fetch_param('country_code'));
		
		$countries = $this->EE->locales->all_countries();
		
		return (isset($countries[$code])) ? $countries[$code] : $country_code;
	}

	public function countries()
	{
		$this->EE->load->library('locales');
		
		$data = array();
		
		foreach ($this->EE->locales->countries(bool_string($this->EE->TMPL->fetch_param('alpha2'))) as $abbrev => $country)
		{
			$data[] = array(
				'country_code' => $abbrev,
				'countries:country_code' => $abbrev,
				'country' => $country,
				'countries:country' => $country
			);
		}
		
		return $this->EE->template_helper->parse_variables($data);
	}

	public function country_select()
	{
		$this->EE->load->library('locales');
		$this->EE->load->helper('form');
		
		$name = ($this->EE->TMPL->fetch_param('name')) ? $this->EE->TMPL->fetch_param('name') : 'country';
		
		$countries = $this->EE->locales->countries(
			bool_string($this->EE->TMPL->fetch_param('alpha2')),
			bool_string($this->EE->TMPL->fetch_param('country_codes'), TRUE)
		);
		
		if (bool_string($this->EE->TMPL->fetch_param('add_blank')))
		{
			$blank = array('' => '---'); 
			$countries = $blank + $countries;
		}
		
		$attrs = array();
		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$attrs['id'] = $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('class'))
		{
			$attrs['class'] = $this->EE->TMPL->fetch_param('class');
		}
		
		if ($this->EE->TMPL->fetch_param('onchange'))
		{
			$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
		}
		
		$extra = '';
		
		if ($attrs)
		{
			$extra .= _attributes_to_string($attrs);
		}
		
		if ($this->EE->TMPL->fetch_param('extra'))
		{
			if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) !== ' ')
			{
				$extra .= ' ';
			}
			
			$extra .= $this->EE->TMPL->fetch_param('extra');
		}
		
		return form_dropdown(
			$name,
			$countries,
			$this->EE->TMPL->fetch_param('selected'),
			$extra
		);
	}

	public function coupon_count()
	{
		return count($this->cart->coupon_codes());
	}
	
	public function coupon_info()
	{
		if ( ! $coupon_codes = $this->cart->coupon_codes())
		{
			return $this->EE->TMPL->no_results();
		}
		
		$this->EE->load->model('coupon_code_model');
		
		foreach ($coupon_codes as $coupon_code)
		{
			$row = array_key_prefix($this->EE->coupon_code_model->get_coupon_code_data($coupon_code), 'coupon_');
			
			$row['coupon_code'] = $coupon_code;
			
			$entry_id = $row['coupon_metadata']['entry_id'];
			
			unset($row['coupon_metadata']);
			
			$variables[] = array_merge($this->EE->cartthrob_entries_model->entry_vars($entry_id), $row);
		}
		
		return $this->EE->template_helper->parse_variables($variables);
	}
	
	public function discount_info()
	{
		$this->EE->load->model('discount_model');
		
		if ( ! $discounts = $this->EE->discount_model->get_valid_discounts())
		{
			return $this->EE->TMPL->no_results();
		}
		
		foreach ($discounts as $discount)
		{
			foreach ($discount as $key => $value)
			{
				if (strpos($key, 'discount_') !== 0)
				{
					$key = 'discount_'.$key;
				}
				
				$row[$key] = $value;
			}
			
			$row = array_merge($this->EE->cartthrob_entries_model->entry_vars($row['discount_entry_id']), $row);
			
			$variables[] = $row;
		}
		
		return $this->EE->template_helper->parse_variables($variables);
	}

	public function customer_info()
	{
		return $this->EE->template_helper->parse_variables(array($this->EE->cartthrob_variables->global_variables()));
	}
	// --------------------------------
	//  Debug Info
	// --------------------------------
	/**
	 * debug_info
	 * Outputs all data related to CartThrob
	 *
	 * @access public
	 * @since 1.0.0
	 * @return string
	 * @author Chris Newton, Rob Sanchez
	 */
	public function debug_info()
	{
		$debug['session'] = $this->EE->cartthrob_session->to_array();
		
		$debug = array_merge($debug, $this->cart->to_array());

	 	uksort($debug, 'strnatcasecmp'); 
		$output = '<fieldset id="ct_debug_info" style="border:1px solid #000;padding:6px 10px 10px 10px;margin:20px 0 20px 0;background-color:#ffbc9f ">';
		$output .= '<legend style="color:#000;">&nbsp;&nbsp;'.$this->EE->lang->line('cartthrob_profiler_data').'  </legend>';
		
		$output .= $this->format_debug($debug); 

		$output .= '</table>';
		$output .= "</fieldset>";
		
		return $output;
	}
	/**
	 * format_debug
	 * Formats debug arrays into tables
	 *
	 * @access private
	 * @since 2.1
	 * @return string
	 * @author Chris Newton
	 */
	private function format_debug($data, $parent_key = NULL)
	{
		$output = ""; 
		if (is_array($data))
		{
		 	uksort($data, 'strnatcasecmp'); 
			$output = "<table style='width:100%;'>"; 
			foreach ($data as $key => $value)
			{
				$content = ""; 
				$output_key = $key; 
				if (is_numeric($key))
				{
					$output_key = "Row ID: ". $key;
				}
				if (is_array($value))
				{
					$content.= $this->format_debug($value, $key);
				}
				else
				{
					if ($key == "inventory" && $value ==PHP_INT_MAX)
					{
						$value = "unlimited"; 
					}
					if ($key == "price")
					{
						if ($value == "" && $parent_key !==NULL)
						{
							$this->EE->load->model('cartthrob_field_model'); 

							$item = $this->EE->cartthrob->cart->item($parent_key); 
							$field_id = $this->EE->cartthrob->store->config('product_channel_fields', $item->meta('channel_id'), $key); 
							
							$field_name = "channel entry"; 
							if ($this->EE->cartthrob->store->config('product_channel_fields', $item->meta('channel_id'), "global_price"))
							{
								$field_name = "globally set"; 
							}
							elseif ($field_id)
							{
								$field_name = $this->EE->cartthrob_field_model->get_field_name($field_id)." field"; 
							}
							
							$value = $item->price(). " (uses ".$field_name." price)"; 
						}
						else
						{
							$value = $value . " (uses customer price)";
						}
					}
					if ($key == "entry_id" && empty($value))
					{
						$value="(dynamic item)"; 
					}
					$content.= htmlspecialchars($value);
				}
				$output .= "<tr><td style='padding:5px; vertical-align: top;color:#900;background-color:#ddd;'>".$output_key."&nbsp;&nbsp;</td><td style='padding:5px; color:#000;background-color:#ddd;'>".$content."</td></tr>\n";
			}
			$output .= '</table>';
		}
		else
		{
			$output = htmlspecialchars($data); 
		}
		return $output; 
	}
	
	
	/**
	 * decrypt
	 * 
	 * Encrypts and returns a string. 
	 * @see Encrypt Class encode
	 * @access public
	 * @param string $TMPL->fetch_param('string') the data to be decrypted
	 * @param string $TMPL->fetch_param('key') the key used to encrypt the data
	 * @return string decrypted string
	 * @author Chris Newton
	 * @since 1.0.0
	 **/
	function decrypt()
	{
		$this->EE->load->library('encrypt');
		
		return xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->TMPL->fetch_param('string'))), $this->EE->TMPL->fetch_param('key'))); 
	}

	public function delete_from_cart()
	{
		if ($this->EE->extensions->active_hook('cartthrob_delete_from_cart_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_delete_from_cart_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		if ($this->EE->TMPL->fetch_param('row_id') !== FALSE)
		{
			$this->cart->remove_item($this->EE->TMPL->fetch_param('row_id'));
		}
		else if ($this->EE->TMPL->fetch_param('entry_id'))
		{
			$data = array('entry_id' => xss_clean($this->EE->TMPL->fetch_param('entry_id')));
		
			foreach ($this->EE->TMPL->tagparams as $key => $value)
			{
				if (preg_match('/^item_options?:(.*)$/', $key, $match))
				{
					$data['item_options'][$match[1]] = $value;
				}
			}
			
			if ($this->EE->input->post('item_options') && is_array($this->EE->input->post('item_options')))
			{
				$data['item_options'] = (isset($data['item_options'])) ? array_merge($data['item_options'], $this->EE->input->post('item_options', TRUE)) : $this->EE->input->post('item_options', TRUE);
			}
			
			if ($item = $this->cart->find_item($data))
			{
				$item->remove();
			}
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_delete_from_cart_end') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_delete_from_cart_end');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	public function delete_from_cart_form()
	{
		if ( ! $this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('form_builder');
		
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'secure_return',
				'row_id',
				'return'
			),
			'classname' => 'Cartthrob',
			'method' => 'delete_from_cart_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			//'secure_action' => bool_string()
		));
		
		return $this->EE->form_builder->form();
	}

	/**
	 * download_file
	 *
	 * This uses curl for URLs, or fopen for paths to download files. 
	 * 
	 * @param string $TMPL->fetch_param('file')
	 * @param string $TMPL->fetch_param('return')
	 * @access public
	 * @return void
	 * @since 1.0
	 * @param 
	 * @author Chris Newton
	 **/
	public function download_file()
	{
		$this->EE->load->library('encrypt');
		$this->EE->load->library('paths');
		
		if ($this->EE->TMPL->fetch_param('field') && $this->EE->TMPL->fetch_param('entry_id'))
		{
			$this->EE->load->model(array('cartthrob_field_model', 'cartthrob_entries_model', 'tools_model'));
			
			$entry = $this->EE->cartthrob_entries_model->entry($this->EE->TMPL->fetch_param('entry_id'));
			
			$this->EE->load->helper('array');
			
			if ($path = element($this->EE->TMPL->fetch_param('field'), $entry))
			{
				$this->EE->load->library('paths');
				
				$path = $this->EE->paths->parse_file_server_paths($path);
				
				$this->EE->TMPL->tagparams['file'] = $path;
			}
		}
		
		if ($this->EE->TMPL->fetch_param('member_id') !== FALSE)
		{
			if ( ! $this->EE->TMPL->fetch_param('member_id'))
			{
				return $this->EE->output->show_user_error('general', $this->EE->lang->line('download_file_not_authorized'));
			}
			
			if (bool_string($this->EE->TMPL->fetch_param('encrypted')))
			{
				if (xss_clean($this->EE->encrypt->decode(base64_encode(rawurldecode($this->EE->TMPL->fetch_param('member_id'))))) != $this->EE->session->userdata('member_id'))
				{
					return $this->EE->output->show_user_error('general', $this->EE->lang->line('download_file_not_authorized'));
				}
			}
			else 
			{
				if ($this->EE->TMPL->fetch_param('member_id') != $this->EE->session->userdata['member_id'])
				{
					return $this->EE->output->show_user_error('general', $this->EE->lang->line('download_file_not_authorized'));
				}
			}
		}
		if ( ! $this->EE->TMPL->fetch_param('file'))
		{
			return $this->EE->output->show_user_error('general', $this->EE->lang->line('download_url_not_specified'));
		}
		else
		{
			$post_url = $this->EE->TMPL->fetch_param('file');
		}
		
		if (bool_string($this->EE->TMPL->fetch_param('encrypted')))
		{
			$post_url = xss_clean($this->EE->encrypt->decode(base64_decode(rawurldecode($post_url))));
		}
		
		$this->EE->load->library('cartthrob_file');
		
		$this->EE->cartthrob_file->force_download($post_url);
		
		if ($this->EE->cartthrob_file->errors())
		{
			$this->EE->output->show_user_error('general', $this->EE->cartthrob_file->errors());
		}
	}
 	
	public function download_file_form()
	{
		if ($this->EE->TMPL->fetch_param('member_id'))
		{
			if (in_array($this->EE->TMPL->fetch_param('member_id'), array('CURRENT_USER', '{logged_in_member_id}', '{member_id}')))
			{
				$this->EE->TMPL->tagparams['member_id'] = $this->EE->session->userdata('member_id');
			}
			else
			{
				$this->EE->TMPL->tagparams['member_id'] = sanitize_number($this->EE->TMPL->fetch_param('member_id'));
			}
		}
 		
		if ($this->EE->TMPL->fetch_param('group_id'))
		{
			if (in_array($this->EE->TMPL->fetch_param('group_id'), array('{logged_in_group_id}', '{group_id}')))
			{
				$this->EE->TMPL->tagparams['group_id'] = $this->EE->session->userdata('group_id');
			}
			else
			{
				$this->EE->TMPL->tagparams['group_id'] = sanitize_number($this->EE->TMPL->fetch_param('group_id'));
			}
		}
		
		
		if ($this->EE->TMPL->fetch_param('field') && $this->EE->TMPL->fetch_param('entry_id'))
		{
			$this->EE->load->model(array('cartthrob_field_model', 'cartthrob_entries_model', 'tools_model'));
			
			$entry = $this->EE->cartthrob_entries_model->entry($this->EE->TMPL->fetch_param('entry_id'));
			
			$this->EE->load->helper('array');
			// @NOTE if the developer has assigned an entry id and a field, but there's nothing IN the field,  then the path doesn't get set, and no debug information is output, because path, below would be set to NULL
			if ($path = element($this->EE->TMPL->fetch_param('field'), $entry))
			{
				$this->EE->load->library('paths');
				
				$path = $this->EE->paths->parse_file_server_paths($path);
				
				$this->EE->TMPL->tagparams['file'] = $path;
			}
		}
		
		if (bool_string($this->EE->TMPL->fetch_param('debug')) && $this->EE->TMPL->fetch_param('file') )
		{
			$this->EE->load->library('cartthrob_file');
			$this->EE->TMPL->tagdata.= $this->EE->cartthrob_file->file_debug($this->EE->TMPL->fetch_param('file')); 
		}
		
		$this->EE->load->library('form_builder');

		$data = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		if (in_array($this->EE->TMPL->fetch_param('member_id'), array('CURRENT_USER', '{member_id}', '{logged_in_member_id}')))
		{
			$this->EE->TMPL->tagparams['member_id'] = $this->EE->session->userdata('member_id');
		}

		if (in_array($this->EE->TMPL->fetch_param('group_id'), array('{group_id}', '{logged_in_group_id}')))
		{
			$this->EE->TMPL->tagparams['group_id'] = $this->EE->session->userdata('group_id');
		}

		if ($this->EE->TMPL->fetch_param('free_file'))
		{
			$this->EE->TMPL->tagparams['free_file'] = 'FI'.$this->EE->TMPL->fetch_param('free_file');
		}
		else
		{
			if ($this->EE->TMPL->fetch_param('file') && (! $this->EE->TMPL->fetch_param('group_id') && ! $this->EE->TMPL->fetch_param('group_id') ) )
			{
				$this->EE->TMPL->tagparams['free_file'] = 'FI'.$this->EE->TMPL->fetch_param('file');
			}
			elseif ($this->EE->TMPL->fetch_param('file'))
			{
				$this->EE->TMPL->tagparams['file'] = 'FP'.$this->EE->TMPL->fetch_param('file');
			}
 		}
		
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'secure_return',
				'language'
			),
			'encoded_form_data' => array(
				'file' => 'FP',
				'free_file' => 'FI',
			),
			'encoded_numbers' => array(
				'member_id' => 'MI',
				'group_id'	=> 'GI'
			),
			'classname' => 'Cartthrob',
			'method' => 'download_file_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
		));
		
		return $this->EE->form_builder->form();
 	}
	
	public function duplicate_item()
	{
		$this->cart->duplicate_item($this->EE->TMPL->fetch_param('row_id'));
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	/**
	 * encrypt
	 * 
	 * Encrypts and returns a string. 
	 * @see Encrypt Class encode
	 * @access public
	 * @param string $string | $TMPL->fetch_param('string') the data to be encrypted
	 * @param string $key | $TMPL->fetch_param('key') the text string key that will be used to encrypt the data
	 * @return string encrypted string
	 * @author Chris Newton
	 * @since 1.0.0
	 **/
	function encrypt()
	{
		$this->EE->load->library('encrypt');
		
		return rawurlencode(base64_encode($this->EE->encrypt->encode($this->EE->TMPL->fetch_param('string'), $this->EE->TMPL->fetch_param('key')))); 
	}

	//deprecated
	public function https_redirect()
	{
		$this->EE->load->helper('https');

		force_https($this->EE->TMPL->fetch_param('domain'), ($this->EE->config->item('send_headers') === 'y'));
		
		if (bool_string($this->EE->TMPL->fetch_param('secure_site_url')))
		{
			$this->EE->config->config['site_url'] = str_replace('http://', 'https://', $this->EE->config->item('site_url'));
		}
		
		return $this->EE->TMPL->tagdata;
	}
	
	/*
	public function gateway_info()
	{
		$this->EE->load->library('encrypt');
		
		$this->EE->load->library('api');
		
		$this->EE->load->library('api/api_cartthrob_payment_gateways');
		
		$gateways = array();
		
		if ($this->EE->TMPL->fetch_param('gateway'))
		{
			$gateways = explode('|', $this->EE->TMPL->fetch_param('gateway'));
		}
		else if ($this->store->config('payment_gateway'))
		{
			$gateways[] = Cartthrob_core::get_class($this->store->config('payment_gateway'));
		}
		
		if ( ! $gateways)
		{
			return '';
		}
		
		$data = array();
		
		foreach ($this->EE->api_cartthrob_payment_gateways->gateways() as $gateway_info)
		{
			//remove Cartthrob_ from classname
			$gateway = Cartthrob_core::get_class($gateway_info['classname']);
			
			if ( ! in_array($gateway, $gateways))
			{
				continue;
			}
			
			$row = array(
				'option_name' => lang($gateway_info['title']),
				'option_value' => $this->EE->encrypt->encode($gateway),
			);
			
			$data[] = $row;
		}
		
		return $this->EE->template_helper->parse_variables($data);
	}
	*/
	
	/**
	 * get_card_type
	 *
	 * @access public
	 * @param string $ccn | $this->EE->fetch_param('credit_card_number')
	 * @return string credit card type, ex. Amex, Visa, Mc, Discover
	 * @author Chris Newton
	 * @since 1.0.0
	 */
	public function get_card_type()
	{
		return card_type($this->EE->TMPL->fetch_param('credit_card_number')); 
	}

	public function get_cartthrob_logo()
	{
		$this->EE->load->helper(array('html', 'url'));
		
		return anchor(
			'http://cartthrob.com',
			img(array('src' => 'http://cartthrob.com/images/powered_by_logos/powered_by_cartthrob.png', 'alt' => $this->EE->lang->line('powered_by_title'))),
			array('title' => $this->EE->lang->line('powered_by_title'), 'onclick' => "javascript:window.open('http://cartthrob.com','cartthrob');return false;")
		);
	}
	/**
	 * Returns string of entry_id's separated by | for use in weblog:entries
	 *
	 * @access public
	 * @param $IN->GBL('price_min')
	 * @param $IN->GBL('price_max')
	 * @return string
	 */

	public function get_items_in_range()
	{
		$price_min = ($this->EE->TMPL->fetch_param('price_min') !== FALSE) ? xss_clean($this->EE->TMPL->fetch_param('price_min')) : $this->EE->input->get_post('price_min', TRUE);

		$price_max = ($this->EE->TMPL->fetch_param('price_max') !== FALSE) ? xss_clean($this->EE->TMPL->fetch_param('price_max')) : $this->EE->input->get_post('price_max', TRUE);

		if ( ! is_numeric($price_min))
		{
			$price_min = '';
		}
		if ( ! is_numeric($price_max))
		{
			$price_max = '';
		}

		if ($price_min == '' && $price_max == '')
		{
			return '';
		}
		
		$this->EE->load->model('product_model');
		
		$entry_ids = $this->EE->product_model->get_products_in_price_range($price_min, $price_max);

		if (count($entry_ids))
		{
			return implode('|', $entry_ids);
		}
		else
		{
			return NULL; 
		}	
	}
	
	/**
	 * Returns the options from the selected shipping plugin
	 *
	 * @access public
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	function get_shipping_options()
	{
		$this->EE->load->library('api/api_cartthrob_shipping_plugins');
		
		if ($this->EE->TMPL->fetch_param('shipping_plugin'))
		{
			$this->EE->api_cartthrob_shipping_plugins->set_plugin($this->EE->TMPL->fetch_param('shipping_plugin')); 
		}
		$options = $this->EE->api_cartthrob_shipping_plugins->shipping_options(); 
 		if ( ! $options )
		{
			if ($this->cart->custom_data("shipping_error")) 
			{
				$options['error_message'] = $this->cart->custom_data("shipping_error"); 
	 			return $this->EE->template_helper->parse_variables(array($options));
			}
		}
	
		$selected = ($this->cart->shipping_info('shipping_option')) ? $this->cart->shipping_info('shipping_option') : $this->EE->api_cartthrob_shipping_plugins->default_shipping_option();
	
		if ( ! trim($this->EE->TMPL->tagdata))
		{
			$attrs = array();
			
			if ($this->EE->TMPL->fetch_param('id'))
			{
				$attrs['id'] = $this->EE->TMPL->fetch_param('id');
			}
			
			if ($this->EE->TMPL->fetch_param('class'))
			{
				$attrs['class'] = $this->EE->TMPL->fetch_param('class');
			}
			
			if ($this->EE->TMPL->fetch_param('onchange'))
			{
				$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
			}
			
			$extra = '';
			
			if ($attrs)
			{
				$extra .= _attributes_to_string($attrs);
			}
			
			if ($this->EE->TMPL->fetch_param('extra'))
			{
				if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) != ' ')
				{
					$extra .= ' ';
				}
				
				$extra .= $this->EE->TMPL->fetch_param('extra');
			}
			
			$select_options = array();
			
			foreach ($options as $row)
			{
				$select_options[$row['rate_short_name']] = $row['rate_title'];
			}
			
			if (!empty($options))
			{
				return form_dropdown(
				'shipping_option',
				$select_options,
				$selected,
				$extra
				);
			}
           	return null; 
		}
		
		$this->EE->load->library('number');
		
		foreach ($options as &$option)
		{
			!isset($count)?$count=1: $count++; 
 			$option['price'] = $this->EE->number->format($option['price']);
			$option['option_value'] = $option['rate_short_name'];
			$option['option_name'] = $option['rate_title'];
			$option['checked'] = ($option['rate_short_name'] == $selected) ? ' checked="checked"' : '';
			$option['selected'] = ($option['rate_short_name'] == $selected) ? ' selected="selected"' : '';
			$option['count'] = $count;
			$option['first_row'] = ($count === 1) ? TRUE : FALSE;
			$option['last_row'] = ($count === count($options)) ? TRUE : FALSE;
			$option['total_results'] = count($options);
		}
		if ($this->cart->custom_data("shipping_error")) 
		{
			$options['error_message'] = $this->cart->custom_data("shipping_error"); 
		}
		return $this->EE->template_helper->parse_variables($options);
	}
	
	//@TODO this needs some serious work, it relies on a field specifically called product_shippable and also assumes it's value is "Yes"
	public function has_shippable_items()
	{
		foreach ($this->cart->items() as $row_id => $item)
		{        
			$product = ($item->product_id()) ? $this->EE->product_model->get_product($item->product_id()) : FALSE;
			
			if ($product)
			{
				$data = $this->EE->cartthrob_entries_model->entry_vars($product);
				
				if ($data && isset($data['product_shippable']) && $data['product_shippable'] == 'Yes')
				{
					return TRUE;
				}
			}
		}
		
		return FALSE; 
	}
	
	
	public function in_array()
	{
		$needle = $this->EE->TMPL->fetch_param('needle');
		
		$haystack = ($this->EE->TMPL->fetch_param('haystack')) ? explode('|', $this->EE->TMPL->fetch_param('haystack')) : array();
		
		return (in_array($needle, $haystack)) ? '1' : 0;
	}
	
	/**
	 * Returns a conditional whether item has been purchased
	 *
	 * @access public
	 * @param string $TMPL->fetch_param('entry_id')
	 * @return string (int)
	 * @since 1.0.0
	 * @author Rob Sanchez, Chris Newton
	 */
	public function is_purchased_item()
	{
		// @TODO add in the ability to pull up items with a particular status
		// or recognize only completed itms. 
		
		$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		
		$this->EE->load->model('purchased_items_model');
		
		$data['is_purchased_item'] = $this->EE->purchased_items_model->has_purchased($this->EE->TMPL->fetch_param('entry_id'));
		
		//single tag
		if ( ! $this->EE->TMPL->tagdata)
		{
			return (int) $data['is_purchased_item'];
		}
		
		return $this->EE->template_helper->parse_variables(array($data));
	}
	// END

	public function is_in_cart()
	{
		$data['is_in_cart'] = (int) ($this->EE->TMPL->fetch_param('entry_id') && $this->cart->find_item(array('entry_id' => $this->EE->TMPL->fetch_param('entry_id'))));
		
		//single tag
		if ( ! $this->EE->TMPL->tagdata)
		{
			return $data['is_in_cart'];
		}
		
		$data['item_in_cart'] = $data['is_in_cart'];
		
		return $this->EE->template_helper->parse_variables(array($data));
	}
	
	/**
	 * For use in a conditional, returns whether or not customer_info has been saved
	 *
	 * @access public
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function is_saved()
	{
		foreach ($this->cart->customer_info() as $key => $value)
		{
			if ( ! empty($value))
			{
				return '1';
			}
		}
		
		return 0;
	}
	
	public function item_options()
	{
		$this->EE->load->helper('inflector');
		
		$entry_id = $this->EE->TMPL->fetch_param('entry_id');
		
		$row_id = $this->EE->TMPL->fetch_param('row_id');
		
 		if ( ! $entry_id && $row_id === FALSE)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$item = FALSE;
		
		if (strpos($row_id, ':') !== FALSE)
		{
			$row_id_parts = explode(':', $row_id);
			
			if ($parent_item = $this->cart->item($row_id_parts[0]))
			{
				$item = $parent_item->sub_item($row_id_parts[1]);
			}
		}
		else
		{
			$item = $this->cart->item($row_id);
		}
		
		if ($item && $item->product_id())
		{
			$entry_id = $item->product_id();
		}
		
		$price_modifiers = $this->EE->product_model->get_all_price_modifiers($entry_id);
		
		//this will be an array of option field name => bool is dynamic
		$item_options = array();
		
		foreach (array_keys($price_modifiers) as $key)
		{
			$item_options[$key] = FALSE;
		}
		
		if ($item)
		{
			foreach (array_keys($item->item_options()) as $key)
			{
				if ( ! isset($item_options[$key]))
				{
					$item_options[$key] = TRUE;
				}
			}
		}
		
		$return_data = '';
		
		//if I leave {selected} in there, assign_variables output is wrong
		$this->EE->TMPL->tagdata = str_replace('{selected}', '8bdb34edd2d86eff7aa60be77e3002f5', $this->EE->TMPL->tagdata);
		$variables = $this->EE->functions->assign_variables($this->EE->TMPL->tagdata);
		$this->EE->TMPL->var_single = $variables['var_single'];
		$this->EE->TMPL->var_pair = $variables['var_pair'];
		$this->EE->TMPL->tagdata = str_replace('8bdb34edd2d86eff7aa60be77e3002f5', '{selected}', $this->EE->TMPL->tagdata);
		
		$tagdata = $this->EE->TMPL->tagdata;
		
		//only use one field instead of all fields
		$fields = $this->EE->TMPL->fetch_param('field') ? explode('|', $this->EE->TMPL->fetch_param('field')) : FALSE;
		
		foreach ($item_options as $field_name => $dynamic)
		{
			if ($fields && ! in_array($field_name, $fields))
			{
				continue;
			}
			
			$this->EE->TMPL->tagdata = $tagdata;
			
			//for early parsing
			$this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single('option_field', $field_name, $this->EE->TMPL->tagdata);
			
			$vars = $this->EE->cartthrob_variables->item_option_vars($entry_id, $row_id, $field_name);
			
			$vars['option_field'] = $field_name;
			$vars['dynamic'] = $dynamic;
			$vars['option_value'] = ($item) ? $item->item_options($field_name) : '';
			$vars['options_exist'] = (isset($price_modifiers[$field_name])) ? (int) (count($price_modifiers[$field_name]) > 0) : 0;
			
			$vars['allow_selection'] = 1;
			
			if ($item && $item->is_sub_item() && $entry = $this->EE->cartthrob_entries_model->entry($item->parent_item()->product_id()))
			{
				if ($field_id = $this->EE->cartthrob_field_model->channel_has_fieldtype($entry['channel_id'], 'cartthrob_package', TRUE))
				{
					$this->EE->load->library('api');
					
					$this->EE->api->instantiate('channel_fields');
					
					if (empty($this->EE->api_channel_fields->field_types))
					{
						$this->EE->api_channel_fields->fetch_installed_fieldtypes();
					}
					
					if ($this->EE->api_channel_fields->setup_handler('cartthrob_package'))
					{
						if ( ! isset($this->EE->session->cache['cartthrob']['cartthrob_package'][$entry['entry_id']][$field_id]))
						{
							$this->EE->session->cache['cartthrob']['cartthrob_package'][$entry['entry_id']][$field_id] = $this->EE->api_channel_fields->apply('pre_process', array($entry['field_id_'.$field_id]));
						}
						
						$field_data = $this->EE->session->cache['cartthrob']['cartthrob_package'][$entry['entry_id']][$field_id];
						
						if (isset($field_data[$item->row_id()]) && empty($field_data[$item->row_id()]['allow_selection'][$field_name]))
						{
							$vars['allow_selection'] = 0;
						}
					}
				}
				
				
				
				//@TODO find out whether it actually allows selection
			}
			
			$vars['option_label'] = $vars['item_options:option_label'] = $this->EE->cartthrob_field_model->get_field_label($this->EE->cartthrob_field_model->get_field_id($field_name));
			
 			if (empty($vars['option_label']))
			{
				$labels = $this->EE->cartthrob->cart->meta('item_option_labels'); 
				
 				if (isset($labels[$vars['option_field']]))
				{
					$vars['option_label'] = $vars['item_options:option_label'] = $labels[$vars['option_field']]; 
				}
				else
				{
					$vars['option_label'] = $vars['item_options:option_label'] = humanize($field_name);
				}
			}
			
			$return_data .= $this->EE->template_helper->parse_variables(array($vars));
		}
		
		return $return_data;
	}
	
	public function member_downloads()
	{
		if ( ! $this->EE->session->userdata('member_id'))
		{
			return $this->EE->TMPL->no_results();
		}
		
		$this->EE->load->model('cartthrob_entries_model');
		
		return $this->EE->cartthrob_entries_model->channel_entries(array(
			'dynamic' => 'no',
			'author_id' => $this->EE->session->userdata('member_id'),
			'channel_id' => $this->store->config('purchased_items_channel'),
		));
	}

	public function multi_add_to_cart_form()
	{
		if ( ! $this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('languages');
		
		$this->EE->languages->set_language($this->EE->TMPL->fetch_param('language'));
		
		$TMPL = array(
			'tagdata' => $this->EE->TMPL->tagdata,
			'var_single' => $this->EE->TMPL->var_single,
			'var_pair' => $this->EE->TMPL->var_pair,
			'tagparams' => $this->EE->TMPL->tagparams,
		);
		
		if (preg_match_all('/'. LD.'products\s*(.*?)'.RD.'(.*)'.LD.'\/products'.RD.'/s', $TMPL['tagdata'], $matches))
		{
			$this->EE->load->helper('form');
			
			require_once PATH_MOD.'channel/mod.channel'.EXT;
			
			$channel = new Channel;
			
			foreach ($matches[0] as $i => $match)
			{
				$this->EE->TMPL->tagparams = $this->EE->functions->assign_parameters($matches[1][$i]);
				
				$row_id_field = ($this->EE->TMPL->fetch_param('row_id_field')) ? LD.$this->EE->TMPL->fetch_param('row_id_field').RD : '{count}';
				
				$this->EE->TMPL->tagdata = form_hidden('entry_id['.$row_id_field.']', '{entry_id}').$matches[2][$i];
				
				if (preg_match_all('/'.LD.'(item_options?:)(select|input)(:[^\s]+\s*)(.*?)'.RD.'/s', $this->EE->TMPL->tagdata, $_matches))
				{
					foreach ($_matches[0] as $i => $_match)
					{
						$this->EE->TMPL->tagdata = str_replace($_match, LD.$_matches[1][$i].$_matches[2][$i].$_matches[3][$i].' entry_id="{entry_id}" row_id="'.$row_id_field.'"'.$_matches[4][$i].RD, $this->EE->TMPL->tagdata);
					}
				}
				
				$variables = $this->EE->functions->assign_variables($this->EE->TMPL->tagdata);
				
				$this->EE->TMPL->var_single = $variables['var_single'];
				
				$this->EE->TMPL->var_pair = $variables['var_pair'];
				
				$TMPL['tagdata'] = str_replace($match, $channel->entries(), $TMPL['tagdata']);
			}
			
			$variables = $this->EE->functions->assign_variables($TMPL['tagdata']);
			
			$TMPL['var_single'] = $variables['var_single'];
			
			$TMPL['var_pair'] = $variables['var_pair'];
		}
		
		foreach ($TMPL as $key => $value)
		{
			$this->EE->TMPL->{$key} = $value;
		}
		
		$this->EE->load->library('form_builder');
		
		$data = array_merge(
			$this->EE->cartthrob_variables->item_option_vars(),
			$this->EE->cartthrob_variables->global_variables(TRUE)
		);
		
		$this->EE->form_builder->initialize(array(
			'classname' => 'Cartthrob',
			'method' => 'multi_add_to_cart_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			'form_data' => array(
				'secure_return',
				'language',
				'return'
			),
			'encoded_bools' => array(
				'allow_user_price' => 'AUP',
				'allow_user_shipping' => 'AUS',
				'allow_user_weight' => 'AUW',
				//'show_errors' => array('ERR', TRUE),
				'on_the_fly' => 'OTF',
				'json' => 'JSN',
				'no_tax' => 'NTX',
				'no_shipping' => 'NSH',
			),
		));
		
		return $this->EE->form_builder->form();
	}

	public function new_cart()
	{
		$this->cart->initialize()->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	public function order_items()
	{
		$order_ids = ($this->EE->TMPL->fetch_param('order_id')) ? explode('|', $this->EE->TMPL->fetch_param('order_id')) : FALSE;
		$entry_ids = ($this->EE->TMPL->fetch_param('entry_id')) ? explode('|', $this->EE->TMPL->fetch_param('entry_id')) : FALSE;
		$member_ids = ($this->EE->TMPL->fetch_param('member_id')) ? explode('|', str_replace(array('CURRENT_USER', '{logged_in_member_id}', '{member_id}'), $this->EE->session->userdata('member_id'), $this->EE->TMPL->fetch_param('member_id'))) : FALSE;
		
		$this->EE->load->model(array('order_model', 'product_model'));
		
		$this->EE->load->library('number');
		
		$data = $this->EE->order_model->get_order_items($order_ids, $entry_ids, $member_ids);
		
		if ( ! $data)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$this->EE->load->library('api');
		
		$this->EE->api->instantiate('channel_fields');
		
		$this->EE->api_channel_fields->include_handler('cartthrob_order_items');
		
		$this->EE->load->model('cartthrob_entries_model');
		
		if ( ! $this->EE->api_channel_fields->setup_handler('cartthrob_order_items'))
		{
			return '';
		}
		
		if ($this->EE->TMPL->fetch_param('variable_prefix'))
		{
			$this->EE->api_channel_fields->field_types['cartthrob_order_items']->variable_prefix = $this->EE->TMPL->fetch_param('variable_prefix');
		}
		
		$this->EE->api_channel_fields->apply('pre_process', array($data));
		
		return $this->EE->api_channel_fields->apply('replace_tag', array($data, $this->EE->TMPL->tagparams, $this->EE->TMPL->tagdata));
	}
	
	public function order_totals()
	{
		$this->EE->load->library('number');
		
		$data = array(
			'total' => 0,
			'subtotal' => 0,
			'tax' => 0,
			'shipping' => 0,
			'discount' => 0,
			'count' => 0,
		);
		
		if ($this->store->config('orders_channel'))
		{
			$this->EE->load->model('cartthrob_entries_model');
			
			if ($query = $this->EE->cartthrob_entries_model->channel_entries(array('channel_id' => $this->store->config('orders_channel')), TRUE))
			{
				$data['count'] = $query->num_rows();
				
				foreach ($query->result_array() as $row)
				{
					if ($this->store->config('orders_total_field') && isset($row['field_id_'.$this->store->config('orders_total_field')]))
					{
						$data['total'] += sanitize_number($row['field_id_'.$this->store->config('orders_total_field')]);
					}
					
					if ($this->store->config('orders_subtotal_field') && isset($row['field_id_'.$this->store->config('orders_subtotal_field')]))
					{
						$data['subtotal'] += sanitize_number($row['field_id_'.$this->store->config('orders_subtotal_field')]);
					}
					
					if ($this->store->config('orders_tax_field') && isset($row['field_id_'.$this->store->config('orders_tax_field')]))
					{
						$data['tax'] += sanitize_number($row['field_id_'.$this->store->config('orders_tax_field')]);
					}
					
					if ($this->store->config('orders_shipping_field') && isset($row['field_id_'.$this->store->config('orders_shipping_field')]))
					{
						$data['shipping'] += sanitize_number($row['field_id_'.$this->store->config('orders_shipping_field')]);
					}
					
					if ($this->store->config('orders_discount_field') && isset($row['field_id_'.$this->store->config('orders_discount_field')]))
					{
						$data['discount'] += sanitize_number($row['field_id_'.$this->store->config('orders_discount_field')]);
					}
				}
			}
		}
		
		foreach ($data as $key => $value)
		{
			if ($key === 'count')
			{
				continue;
			}
			
			$data[$key] = $this->EE->number->format($value);
		}
		
		if ( ! $this->EE->TMPL->tagdata)
		{
			return $data['total'];
		}
		
		return $this->EE->template_helper->parse_variables(array($data));
	}
	
	//@TODO test
	public function package()
	{
		if ($this->EE->TMPL->fetch_param('row_id', '') !== '')
		{
			$item = $this->cart->item($this->EE->TMPL->fetch_param('row_id'));
		}
		
		$data = array();
		
		if (empty($item))
		{
			if ($this->EE->TMPL->fetch_param('entry_id', '') !== '')
			{
				$product = $this->EE->product_model->get_product($this->EE->TMPL->fetch_param('entry_id'));
				
				$this->EE->load->library('api');
				
				$this->EE->api->instantiate('channel_fields');
				
				if ($product && $this->EE->api_channel_fields->setup_handler('cartthrob_package'))
				{
					if ($this->EE->TMPL->fetch_param('variable_prefix'))
					{
						$this->EE->api_channel_fields->field_types['cartthrob_package']->variable_prefix = $this->EE->TMPL->fetch_param('variable_prefix');
					}
					
					$field_id = $this->EE->cartthrob_field_model->channel_has_fieldtype($product['channel_id'], 'cartthrob_package', TRUE);
					
					if ($field_id && isset($product['field_id_'.$field_id]))
					{
						$data = $this->EE->api_channel_fields->apply('pre_process', array($product['field_id_'.$field_id])); 
						
						return $this->EE->api_channel_fields->apply('replace_tag', array($data, $this->EE->TMPL->tagparams, $this->EE->TMPL->tagdata));
					}
				}
			}
		}
		else if ($item->sub_items())
		{
			$data = $this->EE->cartthrob_variables->sub_item_vars($item);
		}
		
		if (count($data) === 0)
		{
			return $this->EE->TMPL->no_results();
		}
		
		return $this->EE->template_helper->parse_variables($data);
	}

	public function save_customer_info()
	{
		$this->EE->load->library('form_builder');
		
		$_POST = array_merge($_POST, $this->EE->TMPL->tagparams);
		
		$customer_fields = array_keys($this->cart->customer_info());
		
		$required = $this->EE->TMPL->fetch_param('required');

		$save_shipping = bool_string($this->EE->TMPL->fetch_param('save_shipping'), TRUE);

		if ($required == 'all')
		{
			$required = $customer_fields;
			
			if ($save_shipping)
			{
				$required[] = 'shipping_option';
			}
		}
		elseif (preg_match('/^not\s/', $required))
		{
			$not_required = explode('|', substr($required, 4));
			
			$required = $customer_fields;
			
			if ($save_shipping)
			{
				$required[] = 'shipping_option';
			}
			
			foreach ($required as $key => $value)
			{
				if (in_array($value, $not_required))
				{
					unset($required[$key]);
				}
			}
		}
		elseif ($required)
		{
			$required = explode('|', $required);
		}

		if ( ! $required)
		{
			$required = array();
		}

		if ($this->EE->form_builder
							->set_require_rules(FALSE)
		                    ->set_require_errors(FALSE)
		                    ->set_require_form_hash(FALSE)
		                    ->set_required($required)->validate($required))
		{
            $this->cartthrob->save_customer_info();
        }

		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}

	public function save_customer_info_form()
	{
		if ($this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('form_builder');
		
		$variables = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'return',
				'secure_return',
				'derive_country_code',
				'error_handling',
			),
			'encoded_form_data' => array(
			),
			'classname' => 'Cartthrob',
			'method' => 'save_customer_info_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($variables)),
		));
		
		return $this->EE->form_builder->form();
	}

	/**
	 * Saves chosen shipping option to SESSION
	 *
	 * @access public
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function save_shipping_option()
	{
		$shipping_option = set($this->EE->TMPL->fetch_param('shipping_option'), $this->EE->input->post('shipping_option', TRUE));
		
		$this->cart->set_shipping_info('shipping_option', $shipping_option);
		
		$this->cart->save();
		
		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}
	
	public function gateway_select()
	{
		$this->EE->load->helper('form');
		$this->EE->load->library('encrypt');

		$attrs = array();

		if ($this->EE->TMPL->fetch_param('encrypt') && bool_string($this->EE->TMPL->fetch_param('encrypt'))==FALSE)
		{
 			$encrypt=FALSE; 
		}
		else
		{
			$encrypt=TRUE; 
		}
 
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$attrs['id'] = $this->EE->TMPL->fetch_param('id');
		}

		if ($this->EE->TMPL->fetch_param('class'))
		{
			$attrs['class'] = $this->EE->TMPL->fetch_param('class');
		}

		if ($this->EE->TMPL->fetch_param('onchange'))
		{
			$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
		}

		$extra = '';

		if ($attrs)
		{
			$extra .= _attributes_to_string($attrs);
		}

		if ($this->EE->TMPL->fetch_param('extra'))
		{
			if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) != ' ')
			{
				$extra .= ' ';
			}

			$extra .= $this->EE->TMPL->fetch_param('extra');
		}
		
		$selectable_gateways = $this->store->config('available_gateways'); 
 		
		$name = ($this->EE->TMPL->fetch_param('name')?  $this->EE->TMPL->fetch_param('name') : "gateway");
		$selected = ($this->EE->TMPL->fetch_param('selected') ? $this->EE->TMPL->fetch_param('selected'): $this->store->config('payment_gateway') ); 

 		// get the gateways that the user wants to output
		if ($this->EE->TMPL->fetch_param('gateways'))
		{
 			foreach (explode("|", $this->EE->TMPL->fetch_param('gateways')) as $my_gateways)
			{
				$final_g["Cartthrob_".$my_gateways] = "1"; 
			}
			// Making it so that it's possible to add the default gateway in this parameter without it having been selected as a choosable gateway. 
			// if its the default then it's choosable in my book. 
			if (isset($final_g[$this->store->config('payment_gateway')]) && !isset($selectable_gateways[$this->store->config('payment_gateway')]))
			{
				$selectable_gateways[$this->store->config('payment_gateway')] = 1; 
			}
			$selectable_gateways = array_intersect_key($final_g, $selectable_gateways ); 
		}
  		// if the users selected gateways is not an option, then we'll use the default
		if (!isset($selectable_gateways[$selected])  )
		{
			if (isset($selectable_gateways["Cartthrob_". $selected]))
			{
				$selected = "Cartthrob_".$selected; 
			}
			elseif (isset($selectable_gateways["Cartthrob_".$this->EE->encrypt->decode($selected)]))
			{
				$selected = "Cartthrob_".$this->EE->encrypt->decode($selected); 
			}
			// make sure this isn't an encoded value.
			elseif (!isset($selectable_gateways[$this->EE->encrypt->decode($selected)]))
			{
				$selected =  $this->store->config('payment_gateway'); 
				$selectable_gateways = array_merge(array($this->store->config('payment_gateway') => '1'), (array) $selectable_gateways);
			}
			else
			{
				$selected = $this->EE->encrypt->decode($selected); 
			}
		}	
			
 		// if none have been selected, OR if you're not allowed to select, then the default is shown
		if (!$this->store->config('allow_gateway_selection') || count($selectable_gateways) == 0)
		{
			$selectable_gateways = array($this->store->config('payment_gateway') => '1'); 
			$selected = $this->store->config('payment_gateway'); 
 		}
 	
		$this->EE->load->library('api');
		$this->EE->load->library('api/api_cartthrob_payment_gateways');
		$gateways = $this->EE->api_cartthrob_payment_gateways->gateways();

  		$data = array(); 
		foreach ($gateways as $plugin_data)
		{
 			if (isset($selectable_gateways[$plugin_data['classname']]) )
			{
				$this->EE->lang->loadfile(strtolower($plugin_data['classname']), 'cartthrob');

				if (isset($plugin_data["title"]))
				{
					$title = $this->EE->lang->line($plugin_data['title']);
				}
				else
				{
					$title = $plugin_data['classname']; 
				}	
				if ($encrypt)
				{
 					// have to create a variable here, because it'll be used in a spot
					// where it needs to match. each time we encode, the values change. 
 					$encoded = $this->EE->encrypt->encode($plugin_data['classname']); 
					$data[$encoded] = $title; 
 
					if ($plugin_data['classname'] == $selected)
					{
 						$selected = $encoded; 
					}	
				}
				else
				{
					$data[$plugin_data['classname']] = $title; 
				}
			}
		}
 
		asort($data); 
		
		if (bool_string($this->EE->TMPL->fetch_param('add_blank')))
		{
			$data = array_merge(array('' => '---'), $data);
		}
		
 		return form_dropdown(
			$name, 
			$data,
			$selected,
			$extra
		);
	}
	/**
	 * selected_gateway_fields
	 *
	 * returns data from the 'html' field of the currently selected gateway
	 * 
	 * @param bool $gateway 
	 * @return string
	 * @since 1.0
	 * @author Chris Newton
	 */
	public function selected_gateway_fields()
	{
		$this->EE->load->library('encrypt');

		$selectable_gateways = $this->store->config('available_gateways'); 

		if ($this->EE->input->post('gateway'))
		{
			$selected = $this->EE->input->post('gateway'); 
		}
		else
		{
			$selected = ($this->EE->TMPL->fetch_param('gateway') ? $this->EE->TMPL->fetch_param('gateway'): $this->store->config('payment_gateway') ); 
		}


		if (!isset($selectable_gateways[$selected])  )
		{
			if (isset($selectable_gateways["Cartthrob_". $selected]))
			{
				$selected = "Cartthrob_".$selected; 
			}
			elseif (isset($selectable_gateways["Cartthrob_".$this->EE->encrypt->decode($selected)]))
			{
				$selected = "Cartthrob_".$this->EE->encrypt->decode($selected); 
			}
			// make sure this isn't an encoded value.
			elseif (!isset($selectable_gateways[$this->EE->encrypt->decode($selected)]))
			{
				$selected =  $this->store->config('payment_gateway'); 
				$selectable_gateways = array_merge(array($this->store->config('payment_gateway') => '1'),$selectable_gateways);
			}
			else
			{
				$selected = $this->EE->encrypt->decode($selected); 
			}
		}	

 		// if none have been selected, OR if you're not allowed to select, then the default is shown
		if (!$this->store->config('allow_gateway_selection') || count($selectable_gateways) == 0)
		{
			$selectable_gateways = array($this->store->config('payment_gateway') => '1'); 
			$selected = $this->store->config('payment_gateway'); 
 		}

 		$this->EE->load->library('api');

		$this->EE->load->library('api/api_cartthrob_payment_gateways');
 		#$selected = str_replace("Cartthrob_","",$selected); 
		$this->EE->api_cartthrob_payment_gateways->set_gateway($selected);

		if ($this->EE->api_cartthrob_payment_gateways->template())
		{
			$return_data = '{embed="'.$this->EE->api_cartthrob_payment_gateways->template().'"}';
		}
		else
		{
			$return_data = $this->EE->api_cartthrob_payment_gateways->gateway_fields();
		}

		$this->EE->api_cartthrob_payment_gateways->reset_gateway();

		return $return_data;
	}
	
	/**
	 * selected_shipping_fields
	 *
	 * returns data from the 'html' field of the currently selected shipping plugin
	 * 
	 * @param bool $plugin 
	 * @return string
	 * @since 1.0
	 * @author Chris Newton
	 */
	function selected_shipping_fields()
	{
		$this->EE->load->library('api');
		
		$this->EE->load->library('api/api_cartthrob_shipping_plugins');
		
		return $this->EE->api_cartthrob_shipping_plugins->set_plugin($this->EE->TMPL->fetch_param('shipping_plugin'))->html();
	}

	/**
	 * selected_shipping_option
	 *
	 * outputs the description of the shipping item selected in the backend
	 * 
	 * @return string
	 * @author Rob Sanchez
	 * @since 1.0
	 */
	public function selected_shipping_option()
	{
		$this->EE->load->library('api');
		
		$this->EE->load->library('api/api_cartthrob_shipping_plugins');
		
		return ($this->cart->shipping_info('shipping_option')) ? $this->cart->shipping_info('shipping_option') : $this->EE->api_cartthrob_shipping_plugins->default_shipping_option();
	}

	public function set_config()
	{
		$this->EE->load->helper('array');
		
		$data = array_merge($this->cart->customer_info(), array_key_prefix($this->cart->customer_info(), 'customer_'), $this->cart->info(), $this->EE->TMPL->segment_vars, $this->EE->config->_global_vars);
		
		$this->EE->TMPL->tagdata = $this->EE->functions->prep_conditionals($this->EE->TMPL->tagdata, $data);
		
		$this->EE->TMPL->tagdata = $this->EE->TMPL->advanced_conditionals($this->EE->TMPL->tagdata);
		
		$hash = md5($this->EE->TMPL->tagdata);
		
		if ($this->EE->cartthrob->cart->meta('set_config_hash') === $hash)
		{
			//maybe we shouldn't reset it? leaving it for now @TODO
			$this->EE->cartthrob->cart->set_meta('set_config_hash', FALSE)->save();
			
			return '';
		}
		
		$this->EE->cartthrob->cart->set_meta('set_config_hash', $hash);
		
		$vars = $this->EE->functions->assign_variables($this->EE->TMPL->tagdata);
		
		foreach ($vars['var_single'] as $var_single)
		{
			$params = $this->EE->functions->assign_parameters($var_single);
			
			$method = (preg_match('/^set_(config_)?([^\s]+)\s*.*$/', $var_single, $match)) ? 'set_config_'.$match[2] : FALSE;
			
			if ($method && method_exists($this->cartthrob, $method))
			{
				$this->cartthrob->$method($params);
			}
			else if (isset($params['value']))
			{
				$this->cart->set_config($match[2], $params['value']);
			}
			
			if ($method)
			{
				$this->EE->TMPL->tagdata = $this->EE->TMPL->swap_var_single($var_single, '', $this->EE->TMPL->tagdata);
			}
		}
		
		$this->cart->save();
		
		$this->EE->functions->redirect($this->EE->functions->create_url($this->EE->uri->uri_string()));
		
		return $this->EE->TMPL->tagdata; 
		
	}

 	// @DEPRECATED
	/**
	 * live_rates
	 *
	 * @return string
	 * @since 1.0
	 * @author Chris Newton
	 **/
	/*
	public function live_rates()
	{
		$this->EE->load->library('number');
		
		$shipping = $this->cart->shipping_info();
		
		if (( ! $shipping || ! is_array($shipping)))
		{
			return '';
		}
		
		$defaults = array(
			'price' => '',
			'price_numeric' => '',
			'error_message' => '',
			'quoting_available' => '',
			'failed' => '',
			'shipping_option' => '',
			'shipping_methods' => array()
		);
		
		$data = array_merge($this->defaults, $shipping);
		
		if ($data['price'] !== '')
		{
			$data['price_numeric'] = $data['price'];
			$data['price'] = $this->EE->number->format($data['price']);
		}
		
		$tagdata = $this->EE->template_helper->parse_variables(array($data));

		if (preg_match_all('/'.LD.'shipping_methods'.RD.'(.*?)'.LD.SLASH.'shipping_methods'.RD.'/s', $tagdata, $matches))
		{
			$total_results = 0;
			
			if ( ! empty($data['shipping_methods']['option_values']))
			{
				$total_results = count($data['shipping_methods']['option_values']);
				
			}

			for ($i = 0; $i < count($matches[0]); $i++)
			{
				$match = array($matches[0][$i], $matches[1][$i]);

				$count = 1;

				if ( ! $total_results && preg_match('/'.LD.'if item:no_results'.RD.'(.*?)'.LD.SLASH.'if'.RD.'/s', $match[1], $no_results))
				{
					$tagdata = str_replace($match[0], $no_results[1], $tagdata);

					continue;
				}

				$output = '';

				if (!empty($data['shipping_methods']['option_values']))
				{
					foreach ($data['shipping_methods']['option_values'] as $key => $option_values)
					{	

						$item['option_value'] 	 = $option_values; 
						$item['option_price'] 	 = $data['shipping_methods']['option_prices'][$key]; 
						$item['option_title'] 	 = $data['shipping_methods']['option_titles'][$key]; 
						$item['count']			 = $count;
						$item['total_results'] 	 = $total_results;

						$subtagdata = $match[1];

						if (preg_match_all('/'.LD.'item:switch=["\']?(.+)["\']?'.RD.'/', $subtagdata, $switch_matches))
						{
							foreach ($switch_matches[0] as $i => $v)
							{
								$switch_values = explode('|', $switch_matches[1][$i]);

								$subtagdata = str_replace($switch_matches[0][$i], $switch_values[($count + count($switch_values) - 1) % count($switch_values)], $subtagdata);
							}
						}

						$cond = array();

						foreach ($item as $key => $value)
						{
							unset($item[$key]);

							$new_key = 'item:'.$key;

							$item[$new_key] = $value;
							$cond[$new_key] = (bool) $value;
							$subtagdata = $this->EE->TMPL->swap_var_single($new_key, $value, $subtagdata);						

						}

						$cond['item:first_item'] = ($count == 1);

						$cond['item:last_item'] = ($count == $total_results);

						$subtagdata = $this->EE->functions->prep_conditionals($subtagdata, $cond);

						$count++;

						$output .= $subtagdata;
					}
				}


				$tagdata = str_replace($match[0], $output, $tagdata);
			}
		}

		return $tagdata;
	}
 	*/
	
	/**
	 * get_live_rates_form
	 * Outputs a quote request form
	 * 
	 * @since 1.0
	 * @param $TMPL->shipping_plugin
	 * @return string
	 * @author Chris Newton
	 **/
	public function get_live_rates_form()
	{
		$this->EE->load->library('form_builder');
		
		$data = $this->EE->cartthrob_variables->global_variables(TRUE);

		$data['shipping_fields'] = $this->selected_shipping_fields();
		
		$this->EE->form_builder->initialize(array(
			'classname' => 'Cartthrob',
			'method' => 'update_live_rates_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
			'form_data' => array(
				'return',
				'secure_return',
				'derive_country_code',
				'shipping_plugin',
				'shipping_option',
				'activate_plugin'
			),
			'encoded_form_data' => array(
			),
		));
		
		return $this->EE->form_builder->form();
	}
	
	/**
	 * update_live_rates_action
	 * Gets a quoted shipping value from the default shipping method, and applies that value as the shipping value
	 * 
	 * @since 1.0
	 * @param $this->EE->TMPL->shipping_plugin
	 * @param $this->EE->TMPL->validate (checks required fields)
	 * @return string
	 * @author Chris Newton
	 **/
	function update_live_rates_action()
	{
		// save_shipping (if set in post...will automatically save the cheapest option)
		
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
		
		if ($this->EE->extensions->active_hook('cartthrob_update_live_rates_start') === TRUE)
		{
			$this->EE->extensions->call('cartthrob_update_live_rates_start');
			if ($this->EE->extensions->end_script === TRUE) return;
		}
		$this->cartthrob->save_customer_info();
		$this->cart->save();
		
		$this->EE->load->library('form_validation');
		$this->EE->load->library('form_builder');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('api/api_cartthrob_shipping_plugins');
		$this->EE->load->library('languages');
		
 		if ($this->cart->count() <= 0)
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('empty_cart'))->action_complete(); 
		}
		
		if ($this->cart->shippable_subtotal() <= 0)
		{
			$this->EE->form_builder->set_errors($this->cartthrob->errors())
						->set_success_callback(array($this->cart, 'save'))
							->action_complete();
		}
		
		
		$this->EE->languages->set_language($this->EE->input->post('language', TRUE));
	
		$not_required = array();
		
		$required = array();
		
		if ($this->EE->input->post('REQ'))
		{
			$required_string = xss_clean($this->EE->encrypt->decode($this->EE->input->post('REQ')));
			
			if (preg_match('/^not (.*)/', $required_string, $matches))
			{
				$not_required = explode('|', $matches[1]);
				$required_string = '';
			}
			
			if ($required_string)
			{
				$required = explode('|', $required_string);
			}

			unset($required_string);
		}
		
		if ($this->EE->input->post('shipping_plugin'))
		{
			$selected_plugin =xss_clean( $this->EE->input->post('shipping_plugin')); 
			$this->EE->api_cartthrob_shipping_plugins->set_plugin($selected_plugin);
			if (bool_string(xss_clean($this->EE->input->post('activate_plugin')), TRUE))
			{
				$this->cart->set_config("shipping_plugin", $selected_plugin);
			}
		}
		
		$shipping_name = $this->EE->api_cartthrob_shipping_plugins->title();
		
		$required = array_unique(array_merge($required, $this->EE->api_cartthrob_shipping_plugins->required_fields()));
		foreach ($not_required as $key)
		{
			unset($required[array_search($key, $required)]);
		}
		if ( ! $this->EE->form_builder->set_required($required)->validate())
		{
			return $this->EE->form_builder->action_complete();
		}
		
		$product_id = $this->EE->input->post('shipping_option') ? $this->EE->input->post('shipping_option') : 'ALL';
		
		$shipping_info= array(
			'error_message'	=> NULL, 
			'option_value'	=> array(),
			'option_name'	=> array(),
			'price'			=> array(),
			);
		
		$shipping_info = array_merge($shipping_info, $this->EE->api_cartthrob_shipping_plugins->get_live_rates($product_id));
		
		$this->EE->load->library('cartthrob_shipping_plugins');
		
  		// OUTPUTS ERROR IN STANDARD EE WAY
		if (!$shipping_info || (empty($shipping_info['error_message']) && empty($shipping_info['option_value']) ))
		{
			return $this->EE->form_builder->add_error($this->EE->lang->line('no_shipping_returned'))->action_complete();
 			
		}
 		if (!empty($shipping_info['error_message']) )
		{

 			return $this->EE->form_builder->add_error($shipping_info['error_message'])->action_complete();
		}
		else
		{
			// SAVE THE CHEAPEST OPTION AS SELECTED
			if (bool_string($this->EE->input->post('save_shipping'), TRUE))
			{
				if (!in_array($this->selected_shipping_option(), $shipping_info['option_value']))
				{
					//var_dump($shipping_info);
					$lowest_amount_key = array_pop(array_keys($shipping_info['price'], min($shipping_info['price'])));
					if (!empty( $shipping_info['option_value'][$lowest_amount_key]))
					{
						$this->cart->set_shipping( $shipping_info['price'][$lowest_amount_key]);
						$this->cart->set_shipping_info('shipping_option', $shipping_info['option_value'][$lowest_amount_key]);
						$this->cart->save();
					}
				}
			}

		}

		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
						->action_complete();
		}

	/**
	 * states
	 *
	 * swaps abbrev, and state from list in templates 
	 * @param $TMPL country_code 3 character country code (Default USA)
	 * @return string 
	 * @author Rob Sanchez, Chris Newton 
	 * @since 1.0
	 */
	public function states()
	{
		$this->EE->load->library('locales');
		
		$country_code = ($this->EE->TMPL->fetch_param('country_code')) ? $this->EE->TMPL->fetch_param('country_code') : FALSE;
		
 		$data = array();
		
 		foreach ($this->EE->locales->states($country_code) as $abbrev => $state)
		{
			$data[] = array('abbrev' => $abbrev, 'state' => $state);
		}
		
 		return $this->EE->template_helper->parse_variables($data);

	}
	//alias for state_select()
	public function states_select()
	{
		return $this->state_select();
	}
	
	public function state_select()
	{
		$this->EE->load->library('locales');
		$this->EE->load->helper('form');
		
		$name = ($this->EE->TMPL->fetch_param('name')) ? $this->EE->TMPL->fetch_param('name') : 'state';
		$selected = ($this->EE->TMPL->fetch_param('selected')) ? $this->EE->TMPL->fetch_param('selected') : $this->EE->TMPL->fetch_param('default');
		$abbrev_label = bool_string($this->EE->TMPL->fetch_param('abbrev_label'));
		$abbrev_value = bool_string($this->EE->TMPL->fetch_param('abbrev_value'), TRUE);
		
		$states = $this->EE->locales->states($this->EE->TMPL->fetch_param('country_code'));
		
		if (bool_string($this->EE->TMPL->fetch_param('add_blank')))
		{
			$blank = array('' => '---'); 
			$states = $blank + $states;
		}
		
		$states_converted= array(); 
		foreach ($states as $abbrev => $state)
		{
			$value = ($abbrev_value) ? $abbrev : $state;
			$states_converted[$value] = ($abbrev_label) ? $abbrev : $state;
		}
		
		$attrs = array();
		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$attrs['id'] = $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('class'))
		{
			$attrs['class'] = $this->EE->TMPL->fetch_param('class');
		}
		
		if ($this->EE->TMPL->fetch_param('onchange'))
		{
			$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
		}
		
		$extra = '';
		
		if ($attrs)
		{
			$extra .= _attributes_to_string($attrs);
		}
		
		if ($this->EE->TMPL->fetch_param('extra'))
		{
			if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) != ' ')
			{
				$extra .= ' ';
			}
			
			$extra .= $this->EE->TMPL->fetch_param('extra');
		}
		$this->EE->load->helper('form');
		
		return form_dropdown(
			$name,
			$states_converted,
			$this->EE->TMPL->fetch_param('selected'),
			$extra
		);
 	}
 
	public function submitted_order_info()
	{
		$this->EE->load->model(array('cartthrob_entries_model', 'order_model'));
		
		$data = $this->cart->order();
		
		if ( ! $data)
		{
			return $this->EE->template_helper->parse_variables();
		}
		
		foreach ($data as $i => $row)
		{
			//what's happening here:
			//not all of the data from cart->order() is suitable to be passed to parse_variables
			//particularly arrays of data that don't contain arrays
			//remove them.
			if (is_array($row) && count($row) > 0 && ! is_array(current($row)))
			{
				if ($i === 'custom_data')
				{
					foreach ($row as $key => $value)
					{
						$data['custom_data:'.$key] = $value;
					}
				}
				
				unset($data[$i]);
			}
		}
		
		$auth = array(
			'processing' => (isset($data['auth']['processing'])) ? $data['auth']['processing'] : '',
			'authorized' => (isset($data['auth']['authorized'])) ? $data['auth']['authorized'] : '',
			'declined' => (isset($data['auth']['declined'])) ? $data['auth']['declined'] : '',
			'failed' => (isset($data['auth']['failed'])) ? $data['auth']['failed'] : '',
			'error_message' => (isset($data['auth']['error_message'])) ? $data['auth']['error_message'] : '',
			'transaction_id' => (isset($data['auth']['transaction_id'])) ? $data['auth']['transaction_id'] : '',
			'no_order' => ! (bool) $data //deprecated, use no_results
		);
		
		$data = array_merge($data, $auth, array_key_prefix($data, 'cart_'));
		
		if ( ! empty($data['order_id']))
		{
			if ($order = $this->EE->order_model->get_order($data['order_id']))
			{
				$status = $this->EE->order_model->get_order_status($data['order_id']); 
				switch($status)
				{
					case "authorized": 
					case "completed": 
						$data['authorized'] = TRUE; 
						break; 
					case "declined": 
						$data['declined'] = TRUE; 
						break;
					case "failed": 
					case "refunded": 
					case "expired": 
					case "reversed": 
					case "canceled": 
					case "voided": 
						$data['failed'] = TRUE; 
						break; 
					default: 
						$data['processing'] = TRUE; 
					
				}
				$data['transaction_id'] = $this->EE->order_model->get_order_transaction_id($data['order_id']); 
				$data['error_message'] = $this->EE->order_model->get_order_error_message($data['order_id']); 
				$data = array_merge($this->EE->cartthrob_entries_model->entry_vars($order), $data);
			}
		}
		
		return $this->EE->template_helper->parse_variables(array($data));
	}
	
	// --------------------------------
	//  Total Items Count
	// --------------------------------
	/**
	 * Returns total number of ALL items (including indexes) in cart
	 * If you have 4 of product A, and 5 of product B, this would return 9. 
	 * To get total individual items, use total unique items
	 *
	 * @access public
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */ 
	public function total_items_count()
	{
		return $this->cart->count_all();
	}

	public function unique_items_count()
	{
		return $this->cart->count();
	}

	/**
	 * update_cart_form
	 * 
	 * outputs a form for updating data in the cart
	 * 
	 * @return string
	 * @access public 
	 * @param $this->EE->TMPL->id
	 * @param $this->EE->TMPL->name
	 * @param $this->EE->TMPL->onsubmit
	 * @param $this->EE->TMPL->show_errors
	 * @param $this->EE->TMPL->json
	 * @param $this->EE->TMPL->redirect deprecated
	 * @param $this->EE->TMPL->return
	 * @param $this->EE->TMPL->class
	 * @author Rob Sanchez, Chris Newton
	 * @since 1.0
	 */
	public function update_cart_form()
	{
		if ( ! $this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$this->EE->load->library('form_builder');
		
		$variables = $this->EE->cartthrob_variables->global_variables(TRUE);
		
		foreach ($this->EE->TMPL->var_single as $key)
		{
			if ( ! isset($variables[$key]) && strpos($key, 'custom_data:') === 0)
			{
				$variables[$key] = '';
			}
		}
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'secure_return',
				'return'
			),
			'encoded_bools' => array(
				//'show_errors' => array('ERR', TRUE),
				'json' => 'JSN'
			),
			'encoded_form_data' => array(
				'subscription_name'					=> 'SUN',
				'subscription_start_date'			=> 'SSD',
				'subscription_end_date'				=> 'SED',
				'subscription_interval_units'		=> 'SIU',
				'subscription_type'					=> 'SUT',
			),
			'encoded_numbers' => array(
				'subscription_total_occurrences'	=> 'SO',
				'subscription_trial_price'			=> 'ST',
				'subscription_trial_occurrences'	=> 'SP',
				'subscription_interval'				=> 'SI',
			),
			'encoded_bools' => array(
				'subscription'	=> 'SUB',
				'subscription_allow_modification'		=> 'SM',
			),
			
			'classname' => 'Cartthrob',
			'method' => 'update_cart_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($variables)),
		));
		
		return $this->EE->form_builder->form();
	}
	
	/**
	 * Updates an item's quantity and item_options
	 *
	 * @access public
	 * @param string $this->EE->TMPL->fetch_param('entry_id')
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function update_item()
	{
		foreach ($this->EE->TMPL->tagparams as $key => $value)
		{
			if (preg_match('/^item_options?:(.*)$/', $key, $match))
			{
				unset($this->EE->TMPL->tagparams[$key]);
				
				$this->EE->TMPL->tagparams['item_options'][$match[1]] = $value;
			}
		}
		
		$data = $this->EE->TMPL->tagparams;
		
		//should I?
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			$data = array_merge($data, xss_clean($_POST));
		}

		if ($item = $this->cart->item($this->EE->TMPL->fetch_param('row_id')))
		{
			$item->update($this->EE->TMPL->tagparams);
		
			$this->cart->save();
		}

		$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('return'));
	}

	public function update_item_form()
	{
		if ($this->EE->session->userdata('member_id') && $this->EE->TMPL->fetch_param('logged_out_redirect'))
		{
			$this->EE->template_helper->tag_redirect($this->EE->TMPL->fetch_param('logged_out_redirect'));
		}
		
		$item = $this->cart->item($this->EE->TMPL->fetch_param('row_id'));
		
		$entry_id = ($item && $item->product_id()) ? $item->product_id() : $this->EE->TMPL->fetch_param('entry_id');
		
		$this->EE->load->library('form_builder');
		
		$data = array_merge(
			$this->EE->cartthrob_variables->global_variables(TRUE),
			$this->EE->cartthrob_variables->item_option_vars($entry_id, $this->EE->TMPL->fetch_param('row_id'))
		);
		
		$this->EE->form_builder->initialize(array(
			'form_data' => array(
				'secure_return',
				'entry_id',
				'row_id',
				'quantity',
				'title',
				'language',
				'return',
				'delete',
				'delete_all'
			),
			'encoded_form_data' => array(
				'shipping' => 'SHP',
				'weight' => 'WGT', 
				'permissions'	=> 'PER',
				'subscription_name'					=> 'SUN',
				'subscription_start_date'			=> 'SSD',
				'subscription_end_date'				=> 'SED',
				'subscription_interval_units'		=> 'SIU',
				'subscription_type'					=> 'SUT',
			),
			'encoded_numbers' => array(
				'price' => 'PR',
				'expiration_date' => 'EXP',
				'subscription_total_occurrences'	=> 'SO',
				'subscription_trial_price'			=> 'ST',
				'subscription_trial_occurrences'	=> 'SP',
				'subscription_interval'				=> 'SI',
			),
			'encoded_bools' => array(
				'allow_user_price' => 'AUP',
				'allow_user_weight' => 'AUW',
				'allow_user_shipping' => 'AUS',
				'no_tax' => 'NTX',
				'no_shipping' => 'NSH',
				'on_the_fly' => 'OTF',
				//'show_errors' => array('ERR', TRUE),
				'json' => 'JSN',
				'subscription'	=> 'SUB',
				'license_number' => 'LIC',
				'subscription_allow_modification'		=> 'SM',
				
			),
			'array_form_data' => array(
				'item_option'
			),
			'classname' => 'Cartthrob',
			'method' => 'update_item_action',
			'params' => $this->EE->TMPL->tagparams,
			'content' => $this->EE->template_helper->parse_variables(array($data)),
		));
		
		return $this->EE->form_builder->form();
	}
	public function update_item_action()
	{
		if ( ! $this->EE->input->get_post('ACT'))
		{
			return;
		}
 
		$row_id =  $this->EE->input->post('row_id') ? $this->EE->input->post('row_id') : 0 ; 
		$post = $this->EE->security->xss_clean($_POST);
		
		$item = $this->cart->item($row_id); 
		if ( $item )
		{
			if (element($row_id, element('delete', $post)))
			{
				$this->cart->remove_item($row_id);
			}
			else
			{
				foreach ($post as $key => $value)
				{
 					if (in_array($key, $item->default_keys()))
					{
						$data[$key] = $value;
					}
				}

				if (!empty($data))
				{
					$item->update($data);
				}
			}
			
		}
		if ($this->EE->input->post('delete_all'))
		{
			$this->cart->clear();
		}
		$this->cart->check_inventory();

		$this->EE->load->library('form_builder');

		$this->EE->form_builder->set_errors($this->cartthrob->errors())
					->set_success_callback(array($this->cart, 'save'))
					->action_complete(TRUE);
 
	}

	/**
	 * view_converted_currency
	 *
	 * @param $number bool
	 * @return string
	 * @author Chris Newton
	 * @param string $TMPL->fetch_param('price')
	 * @param string $TMPL->fetch_param('currency_code')
	 * @param string $TMPL->fetch_param('new_currency_code')
	 * @param string $TMPL->fetch_param('decimals')
	 * @param string $TMPL->fetch_param('dec_point')
	 * @param string $TMPL->fetch_param('thousands_sep')
	 * @param string $TMPL->fetch_param('prefix')
	 * @param string $TMPL->fetch_param('new_prefix')
	 **/
	public function view_converted_currency()
	{
		$this->EE->load->library('number');
		$this->EE->load->library('curl');
 		
		// Check to see if this value is being passed in or not. 
		$number = $this->EE->TMPL->fetch_param('price');
		
		if ($number === FALSE)
		{
			return '';
		}
		
		// clean the number
		$number = sanitize_number($number);
		
		// -------------------------------------------
		// 'cartthrob_view_converted_currency' hook.
		//
		if ($this->EE->extensions->active_hook('cartthrob_view_converted_currency') === TRUE)
		{
			return $this->EE->extensions->call('cartthrob_view_converted_currency', $number);
		}

		// set defaults
		$currency = ($this->EE->TMPL->fetch_param('currency_code') !== FALSE) ? $this->EE->TMPL->fetch_param('currency_code') : $this->store->config('number_format_default_currency_code');
		$new_currency = ($this->EE->TMPL->fetch_param('new_currency_code') !== FALSE) ? $this->EE->TMPL->fetch_param('new_currency_code') : $this->store->config('number_format_default_currency_code');
		
		$currency = strtolower($currency);
		$new_currency = strtolower($new_currency);

		$new_prefix = bool_string($this->EE->TMPL->fetch_param('use_prefix')); 

		$prefix = ""; 

		if ($new_prefix)
		{
			switch ($new_currency)
			{
				case "eur":
					$prefix = "&#8364;";
					break;
				case "usd":
					$prefix = "$";
					break;
				case "gbp":
					$prefix = "&#163;";
					break;
				case "aud":
					$prefix = "$";
					break;
				case "brl":
					$prefix = "R$";
					break;
				case "nzd":
					$prefix = "$";
					break;
				case "cad":
					$prefix = "$";
					break;
				case "chf":
					$prefix = "CHF";
					break;
				case "cny":
					$prefix = "&#165;";
					break;
				case "dkk":
					$prefix = "kr";
					break;
				case "hkd":
					$prefix = "$";
					break;
				case "inr":
					$prefix = "&#8360;";
					break;
				case "jpy":
					$prefix = "&#165;";
					break;
				case "krw":
					$prefix = "&#8361;";
					break;
				case "mxn":
					$prefix = "$";
					break;
				case "myr":
					$prefix = "RM";
					break;
				case "nok":
					$prefix = "kr";
					break;
				case "sek":
					$prefix = "kr";
					break;
				case "sgd":
					$prefix = "$";
					break;
				case "thb":
					$prefix = "&#3647;";
					break;
				case "zar":
					$prefix = "R";
					break;
				case "bgn":
					$prefix = "&#1083;&#1074;";
					break;
				case "czk":
					$prefix = "&#75;&#269;";
					break;
				case "eek":
					$prefix = "kr";
					break;
				case "huf":
					$prefix = "Ft";
					break;
				case "ltl":
					$prefix = "Lt";
					break;
				case "lvl":
					$prefix = "&#8364;";
					break;
				case "pln":
					$prefix = "z&#322;";
					break;
				case "ron":
					$prefix = "kr";
					break;
				case "hrk":
					$prefix = "kn";
					break;
				case "rub":
					$prefix = "&#1088;&#1091;&#1073;";
					break;
				case "try":
					$prefix = "TL";
					break;
				case "php":
					$prefix = "Php";
					break;
				case "cop":
					$prefix = "$";
					break;
				case "ars":
					$prefix = "$";
					break;
				default: $prefix = "$"; 
			}
		}
		
		$this->EE->number->set_prefix($prefix);
		
		$this->EE->load->library('services_json');
		$this->EE->load->library('curl');
		
		$api_key = ($this->EE->TMPL->fetch_param('api_key')) ? '?key='.$this->EE->TMPL->fetch_param('api_key') : '';

		if ($json = $this->EE->curl->simple_get("http://xurrency.com/api/".$currency."/".$new_currency."/".$number.$api_key))
		{
			$obj = json_decode($json);

			if (is_object($obj) 
				&& isset($obj->{'result'}) 
				&& isset($obj->{'status'}) 
				&& $obj->{'status'} =="ok" 
				&& isset($obj->{'result'}->{'value'})
				)
			{
				return $this->EE->number->format($obj->{'result'}->{'value'});
			}
		}
		
		return $this->EE->number->format($number); 
	}

	public function view_download_link()
	{
		$this->EE->load->library('encrypt');
		
		$link = $this->EE->TMPL->fetch_param('template');
		
		if ( ! $this->EE->TMPL->fetch_param('file'))
		{
			return $this->EE->output->show_user_error('general', $this->EE->lang->line('download_url_not_specified'));
		}
		else
		{
			$link .= rawurlencode(base64_encode($this->EE->encrypt->encode($this->EE->TMPL->fetch_param('file'))));
		}
		
		if ($member_id = $this->EE->TMPL->fetch_param('member_id'))
		{
			if (in_array($member_id, array('{logged_in_member_id}', '{member_id}', 'CURRENT_USER')))
			{
				$member_id = $this->EE->session->userdata('member_id');
			}
			
			$link .= '/'.rawurlencode(base64_encode($this->EE->encrypt->encode($member_id)));
		}
		
		return $link; 
	}

	public function get_download_link()
	{
		$file = NULL; 
		
		$path = NULL;
		
		if ($this->EE->TMPL->fetch_param('field') && $this->EE->TMPL->fetch_param('entry_id'))
		{
			$this->EE->load->model(array('cartthrob_field_model', 'cartthrob_entries_model', 'tools_model'));
			
			$entry = $this->EE->cartthrob_entries_model->entry($this->EE->TMPL->fetch_param('entry_id'));
			
			$this->EE->load->helper('array');
			// @NOTE if the developer has assigned an entry id and a field, but there's nothing IN the field,  then the path doesn't get set, and no debug information is output, because path, below would be set to NULL
			if ($path = element($this->EE->TMPL->fetch_param('field'), $entry))
			{
				$this->EE->load->library('paths');
				
				$path = $this->EE->paths->parse_file_server_paths($path);
				
				$this->EE->TMPL->tagparams['file'] = $path;
				$this->EE->TMPL->tagparams['free_file'] = $path;
				
			}
		}
		
		if (bool_string($this->EE->TMPL->fetch_param('debug')) && $this->EE->TMPL->fetch_param('file') )
		{
			$this->EE->load->library('cartthrob_file');
			return $this->EE->cartthrob_file->file_debug($this->EE->TMPL->fetch_param('file')); 
		}
		
		
		foreach ($this->EE->TMPL->tagparams as $key => $value)
		{
			if ($value !== '' || $value !== FALSE)
			{
				$this->EE->load->library('encrypt');
				
				switch ($key)
				{
					case 'member_id':
						if (in_array($value, array('{logged_in_member_id}', '{member_id}', 'CURRENT_USER')))
						{
							$value = $this->EE->session->userdata('member_id');
						}
						$member_id = rawurlencode(base64_encode($this->EE->encrypt->encode(sanitize_number($value))));
						if (isset($this->EE->TMPL->tagparams['free_file']))
						{
							unset($this->EE->TMPL->tagparams['free_file']); 
						}
						break;
					case 'group_id':
						if (in_array($value, array('{logged_in_group_id}', '{group_id}')))
						{
							$value = $this->EE->session->userdata('group_id');
						}
						$group_id = rawurlencode(base64_encode($this->EE->encrypt->encode(sanitize_number($value))));
						if (isset($this->EE->TMPL->tagparams['free_file']))
						{
							unset($this->EE->TMPL->tagparams['free_file']); 
						}
						break;
					case 'language':
						$language = $value;
						break;
					case 'free_file':
						
						$file = '&FI='. rawurlencode(base64_encode($this->EE->encrypt->encode('FI'.$value)));
						break;
					case 'file':
						$file = '&FP='. rawurlencode(base64_encode($this->EE->encrypt->encode('FP'.$value)));
						break;
				}
			}
		}
		
		if (bool_string($this->EE->TMPL->fetch_param('debug')))
		{
			$this->EE->load->library('cartthrob_file');
			$this->EE->cartthrob_file->file_debug($file);
		}
 
		
		$download_url = $this->EE->functions->fetch_site_index(0, 0).QUERY_MARKER.'ACT='.$this->EE->functions->insert_action_ids($this->EE->functions->fetch_action_id('Cartthrob', 'download_file_action')).$file; 

		if (isset($member_id))
		{
			$download_url .="&MI=". $member_id; 
		}
		if (isset($group_id))
		{
			$download_url .="&GI=". $group_id; 
		}
		if (isset($language))
		{
			$download_url .="&L=".$language; 
		}
		return $download_url; 
	}
	/**
	 * Formats a number
	 *
	 * @access public
	 * @param int $this->EE->TMPL->fetch_param('number')
	 * @param int $this->EE->TMPL->fetch_param('decimals')
	 * @param string $this->EE->TMPL->fetch_param('dec_point')
	 * @param string $this->EE->TMPL->fetch_param('thousands_sep')
	 * @param string $this->EE->TMPL->fetch_param('prefix')
	 * @return string
	 * @since 1.0.0
	 * @author Rob Sanchez, Chris Newton, Chris Barrett
	**/
	public function view_formatted_number()
	{
		$this->EE->load->library('number');
		
		return $this->EE->number->format($this->EE->TMPL->fetch_param('number'));
	}

	public function view_country_name()
	{
		$this->EE->load->library('locales');
		
		$countries = $this->EE->locales->all_countries();
		
		return ($this->EE->TMPL->fetch_param('country_code') && isset($countries[$this->EE->TMPL->fetch_param('country_code')])) ? $countries[$this->EE->TMPL->fetch_param('country_code')] : '';
	}

	public function view_decrypted_string()
	{
		$this->EE->load->library('encrypt');
		
		if ( ! $this->EE->TMPL->fetch_param('string'))
		{
			return ''; 
		}
		
		return $this->EE->encrypt->decode(base64_decode(rawurldecode($this->EE->TMPL->fetch_param('string'))), $this->EE->TMPL->fetch_param('key'));
	}

	public function view_encrypted_string()
	{
		$this->EE->load->library('encrypt');
		
		if ( ! $this->EE->TMPL->fetch_param('string'))
		{
			return ''; 
		}
		
		return rawurlencode(base64_encode($this->EE->encrypt->encode($this->EE->TMPL->fetch_param('string'), $this->EE->TMPL->fetch_param('key')))); 
	}

	/**
	 * format_phone
	 *
	 * returns an array of phone parts
	 * @param string $phone 
	 * @return string formatted string | array of number parts
	 * @author Chris Newton
	 * @since 1.0
	 * @access protected
	 */
	public function view_formatted_phone_number() 
	{
		if ( ! $this->EE->TMPL->fetch_param('number'))
		{
			return ''; 
		}
		
		$return = get_formatted_phone($this->EE->TMPL->fetch_param('number'));

		$output = '';
		
		if ($return['international'])
		{
			$output .= $return['international'].'-';
		}
		
		if ($return['area_code'])
		{
			$output .= $return['area_code'].'-';
		}
		
		if ($return['prefix'])
		{
			$output .= $return['prefix'].'-';
		}
		
		if ($return['suffix'])
		{
			$output .= $return['suffix'];
		}
		
		return $output; 
		
  	}

	/**
	 * view_setting
	 *
	 * returns selected settings from the backend. 
	 *
	 * @return string
	 * @author Chris Newton
	 * @since 1.0
	 * @access public
	 **/
	function view_setting()
	{
		foreach ($this->EE->TMPL->tagparams as $key => $value)
		{
			switch ($key)
			{
				case ! $key:
				case ! bool_string($value);
					break;
				case 'prefix':
				case 'number_prefix':
					return $this->store->config('number_format_defaults_prefix');
				case 'country':
					return $this->store->config('default_location', 'country_code');
				case 'country_code':
				case 'state':
				case 'region':
				case 'zip':
					return $this->store->config('default_location', $key);
				case 'member_id':
					return $this->store->config('default_member_id');			
				case 'thousands_sep':
				case 'thousands_separator':
					return $this->store->config('number_format_defaults_thousands_sep');
				case 'prefix_position':
					return $this->store->config('number_format_defaults_prefix_position');
				case 'decimal':
				case 'decimal_point':
					return $this->store->config('number_format_defaults_dec_point');
				case 'decimal_precision':
					return $this->store->config('number_format_defaults_decimals');
				case 'currency_code':
					return $this->store->config('number_format_defaults_currency_code');
				case 'shipping_option':
				case 'selected_shipping_option':
					return $this->cart->shipping_info('shipping_option');
				default:
					return $this->store->config($key);
			}
		}
		
		return '';
	}
	
	/**
	 * DEPRECATED
	 */
	function view_summed_field()
	{
		return '';
	}
	// END
	
	public function vaults()
	{
		$this->EE->load->model('vault_model');
		
		$variables = array();
		
		$params = array();
		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$params['id'] = (strstr($this->EE->TMPL->fetch_param('id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('id')) : $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('order_id'))
		{
			$params['order_id'] = (strstr($this->EE->TMPL->fetch_param('order_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('order_id')) : $this->EE->TMPL->fetch_param('order_id');
		}
		
		if ($this->EE->TMPL->fetch_param('member_id'))
		{
			if (in_array($this->EE->TMPL->fetch_param('member_id'), array('CURRENT_USER', '{member_id}', '{logged_in_member_id}')))
			{
				$params['member_id'] = $this->EE->session->userdata('member_id');
			}
			else
			{
				$params['member_id'] = (strstr($this->EE->TMPL->fetch_param('member_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('member_id')) : $this->EE->TMPL->fetch_param('member_id');
			}
		}
		
		if ($this->EE->TMPL->fetch_param('sub_id'))
		{
			$params['sub_id'] = (strstr($this->EE->TMPL->fetch_param('sub_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('sub_id')) : $this->EE->TMPL->fetch_param('sub_id');
		}
		
		
		//default to current member's vaults if no other params are specified
		if ( ! $params)
		{
			$params = array('member_id' => $this->EE->session->userdata('member_id'));
		}
		
		//@TODO add pagination
		
		$params['limit'] = ($this->EE->TMPL->fetch_param('limit')) ? $this->EE->TMPL->fetch_param('limit') : 100;
		
		$variables = $this->EE->vault_model->get_vaults($params);
		
		return $this->EE->template_helper->parse_variables($variables);
	}
	public function has_permission()
	{
		if (in_array($this->EE->TMPL->fetch_param('member_id'), array('CURRENT_USER', '{member_id}', '{logged_in_member_id}')))
		{
			$params['member_id'] = $this->EE->session->userdata('member_id');
		}
		else
		{
			$params['member_id'] = (strstr($this->EE->TMPL->fetch_param('member_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('member_id')) : $this->EE->TMPL->fetch_param('member_id');
		}
		
		if (empty($params['member_id']))
		{
			$params['member_id'] = $this->EE->session->userdata('member_id');
		}
	
		if ($this->EE->TMPL->fetch_param('permissions'))
		{
			$permissions = explode('|',$this->EE->TMPL->fetch_param('permissions'));
			
			foreach($permissions as $key => $value)
			{
				$params['permission'] = $value; 
				$this->EE->load->model('permissions_model');
				
				$query = $this->EE->permissions_model->get($params, 1);
				
				if ( ! empty($query))
				{
					//single tag
					if ( ! $this->EE->TMPL->tagdata)
					{
						return 1; 
					}
					return $this->EE->TMPL->tagdata;
				}
			}
 		}
		return $this->EE->TMPL->no_results();
	}
	public function permissions()
	{
		$this->EE->load->model('permissions_model');
		
		$variables = array();
		
		$params = array();
		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$params['id'] = (strstr($this->EE->TMPL->fetch_param('id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('id')) : $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('sub_id'))
		{
			$params['sub_id'] = (strstr($this->EE->TMPL->fetch_param('sub_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('sub_id')) : $this->EE->TMPL->fetch_param('sub_id');
		}
		
		if ($this->EE->TMPL->fetch_param('item_id'))
		{
			$params['item_id'] = (strstr($this->EE->TMPL->fetch_param('item_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('item_id')) : $this->EE->TMPL->fetch_param('item_id');
		}
		
		if ($this->EE->TMPL->fetch_param('order_id'))
		{
			$params['order_id'] = (strstr($this->EE->TMPL->fetch_param('order_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('order_id')) : $this->EE->TMPL->fetch_param('order_id');
		}
		
		if ($this->EE->TMPL->fetch_param('member_id'))
		{
			if (in_array($this->EE->TMPL->fetch_param('member_id'), array('CURRENT_USER', '{member_id}', '{logged_in_member_id}')))
			{
				$params['member_id'] = $this->EE->session->userdata('member_id');
			}
			else
			{
				$params['member_id'] = (strstr($this->EE->TMPL->fetch_param('member_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('member_id')) : $this->EE->TMPL->fetch_param('member_id');
			}
		}
		
		if ($this->EE->TMPL->fetch_param('sub_id'))
		{
			$params['sub_id'] = (strstr($this->EE->TMPL->fetch_param('sub_id'), '|') !== FALSE) ? explode('|', $this->EE->TMPL->fetch_param('sub_id')) : $this->EE->TMPL->fetch_param('sub_id');
		}
		
		
		//default to current member's permissions if no other params are specified
		if ( ! $params)
		{
			$params = array('member_id' => $this->EE->session->userdata('member_id'));
		}
		
		$params['limit'] = ($this->EE->TMPL->fetch_param('limit')) ? $this->EE->TMPL->fetch_param('limit') : 100;
		
		$variables = $this->EE->permissions_model->get($params);
		
		return $this->EE->template_helper->parse_variables($variables);
	}
	
	public function years()
	{
		$years = (is_numeric($this->EE->TMPL->fetch_param('years'))) ? $this->EE->TMPL->fetch_param('years') : 5;
		
		$start_year = (is_numeric($this->EE->TMPL->fetch_param('start_year'))) ? $this->EE->TMPL->fetch_param('start_year') : date('Y');
		
		$final_year = $start_year + $years;
		
		$data = array();
		
		for ($year = $start_year; $year < $final_year; $year++)
		{
			$data[] = array('year' => $year);
		}
		
		return $this->EE->template_helper->parse_variables($data);
	}
	public function month_select()
	{
		$attrs = array(); 
		$this->EE->load->helper('form');
 		$data = array(
			"01"	=> $this->EE->lang->line('january'),
			"02"	=> $this->EE->lang->line('february'),
			"03"	=> $this->EE->lang->line('march'),
			"04"	=> $this->EE->lang->line('april'),
			"05"	=> $this->EE->lang->line('may'),
			"06"	=> $this->EE->lang->line('june'),
			"07"	=> $this->EE->lang->line('july'),
			"08"	=> $this->EE->lang->line('august'),
			"09"	=> $this->EE->lang->line('september'),
			"10"	=> $this->EE->lang->line('october'),
			"11"	=> $this->EE->lang->line('november'),
			"12"	=> $this->EE->lang->line('december'),
			);
 		
 		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$attrs['id'] = $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('class'))
		{
			$attrs['class'] = $this->EE->TMPL->fetch_param('class');
		}
		
		if ($this->EE->TMPL->fetch_param('onchange'))
		{
			$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
		}
		
		$extra = '';
		
		if ($attrs)
		{
			$extra .= _attributes_to_string($attrs);
		}
		
		if ($this->EE->TMPL->fetch_param('extra'))
		{
			if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) != ' ')
			{
				$extra .= ' ';
			}
			
			$extra .= $this->EE->TMPL->fetch_param('extra');
		}
		
		$name = ($this->EE->TMPL->fetch_param('name')?  $this->EE->TMPL->fetch_param('name') : "expiration_month"); 
		
		return form_dropdown(
			$name, 
			$data,
			$this->EE->TMPL->fetch_param('selected'),
			$extra
		);
	}
	public function year_select()
	{
		$this->EE->load->helper('form');
		
		$years = (is_numeric($this->EE->TMPL->fetch_param('years'))) ? $this->EE->TMPL->fetch_param('years') : 5;
		
		$start_year = (is_numeric($this->EE->TMPL->fetch_param('start_year'))) ? $this->EE->TMPL->fetch_param('start_year') : date('Y');
		
		$final_year = $start_year + $years;
		
		$data = array();
		
		for ($year = $start_year; $year < $final_year; $year++)
		{
			$data[$year] = $year;
		}
		
		$attrs = array();
		
		if ($this->EE->TMPL->fetch_param('id'))
		{
			$attrs['id'] = $this->EE->TMPL->fetch_param('id');
		}
		
		if ($this->EE->TMPL->fetch_param('class'))
		{
			$attrs['class'] = $this->EE->TMPL->fetch_param('class');
		}
		
		if ($this->EE->TMPL->fetch_param('onchange'))
		{
			$attrs['onchange'] = $this->EE->TMPL->fetch_param('onchange');
		}
		
		$extra = '';
		
		if ($attrs)
		{
			$extra .= _attributes_to_string($attrs);
		}
		
		if ($this->EE->TMPL->fetch_param('extra'))
		{
			if (substr($this->EE->TMPL->fetch_param('extra'), 0, 1) != ' ')
			{
				$extra .= ' ';
			}
			
			$extra .= $this->EE->TMPL->fetch_param('extra');
		}
		
		$name = ($this->EE->TMPL->fetch_param('name')?  $this->EE->TMPL->fetch_param('name') : "expiration_year"); 
 		
		return form_dropdown(
			$name,
			$data,
			$this->EE->TMPL->fetch_param('selected'),
			$extra
		);
	}

	public function years_select()
	{
		return $this->year_select();
	}
	
	
	/* protected methods */
}

/* End of file mod.cartthrob.php */
/* Location: ./system/expressionengine/third_party/cartthrob/mod.cartthrob.php */