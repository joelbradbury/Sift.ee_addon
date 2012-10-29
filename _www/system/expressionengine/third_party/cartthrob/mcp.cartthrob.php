<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cartthrob_mcp
{
	public $required_settings = array();
	public $template_errors = array();
	public $templates_installed = array();
	public $extension_enabled = 0;
	public $module_enabled = 0;
	public $version;
	
	private $initialized = FALSE;
	
	public static $nav = array(
		'global_settings' => array(
			'general_settings' => 'nav_general_settings',
			'number_format_defaults' => 'nav_number_format_defaults',
			'default_location' => 'nav_default_location',
			'locales' => 'nav_locales',
			'set_license_number' => 'nav_set_license_number',
		),
		'product_settings' => array(
			'product_channels' => 'nav_product_channels',
			'product_options' => 'nav_product_options',
		),
		'order_settings' => array(
			'order_channel_configuration' => 'nav_order_channel_configuration',
			'purchased_items' => 'nav_purchased_items'
		),
		'shipping' => array(
			'shipping' => 'nav_shipping',
		),
		'taxes' => array(
			'tax' => 'nav_tax',
		),
		'coupons_discounts' => array(
			'coupon_options' => 'nav_coupon_options',
			'discount_options' => 'nav_discount_options',
		),
 		'notifications' => array(
			'notifications' => 'notifications',
		),
		/*
 		'email_notifications' => array(
			'email_admin' => 'nav_email_admin',
			'email_customer' => 'nav_email_customer',
			'email_low_stock'	=> 'nav_email_low_stock',
		),
		*/
		'members' => array(
			'member_configuration' => 'nav_member_configuration',
		),
		'payment_gateways' => array(
			'payment_gateways' => 'nav_payment_gateways',
			'payment_security' => 'nav_payment_security',
		),
		'reports' => array(
			'reports' => 'reports',	
		),
		'installation' => array(
			'install_channels' => 'nav_install_channels',
		),
		'import_export' => array(
			'import_settings' => 'nav_import_settings',
			'export_settings' => 'nav_export_settings',
		),
		'support' => array(
			'get_started' => 'nav_get_started',
			'support' => '',
		),
		'global_item_options'	=> array(
			'global_item_options'	=>   'global_item_options',
		),
		'order_admin'	=> array(
			'order_admin'	=>   'order_admin',
		),
		'add_tax'	=> array(
			'add_tax'	=> '',
		),
		'edit_tax' => array(
			'edit_tax' => '',
		),
		'delete_tax' => array(
			'delete_tax' => '',
		),
		
	);
	
	public static $no_nav = array(
		'edit_tax',
		'add_tax',
		'delete_tax'
	); 
	private $remove_keys = array(
		'name',
		'submit',
		'x',
		'y',
		'templates',
		'XID',
	);
	
	public $cartthrob, $store, $cart;
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		//@TODO remove
		$this->EE->load->helper('debug');
		
		include_once PATH_THIRD.'cartthrob/config.php';
	}
	
	private function initialize()
	{
		if ($this->initialized === TRUE)
		{
			return;
		}
		
		$this->initialized = TRUE;
		
		$this->EE->load->library('cartthrob_loader');
		$this->EE->cartthrob_loader->setup($this);
		
		$this->EE->load->model(array('field_model', 'channel_model', 'product_model'));
		
		$this->EE->product_model->load_products($this->EE->cartthrob->cart->product_ids());
		
		$this->EE->load->library('locales');
		$this->EE->load->library('encrypt');
		$this->EE->load->library('languages');
		$this->EE->load->helper(array('security', 'data_formatting', 'form', 'file', 'string', 'inflector'));
		
		$this->EE->config->load();
		$this->EE->config->load('locales');
		
		$this->EE->lang->loadfile('cartthrob', 'cartthrob');
		$this->EE->lang->loadfile('cartthrob_errors', 'cartthrob');
		
		$this->module_enabled = (bool) $this->EE->db->where('module_name', 'Cartthrob')->count_all_results('modules');
		$this->extension_enabled = (bool) $this->EE->db->where(array('class' => 'Cartthrob_ext', 'enabled' => 'y'))->count_all_results('extensions');
		
		$this->no_form = array(
			'support',
			'import_export',
			'reports',
			'add_tax',
			'taxes',
			'edit_tax',
			'delete_tax',
		);
 
	}
	
	public function index()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=global_settings');
	}
	
	/* CP Controller */
	// pass the request on to the cartthrob_cp library
	public function global_settings()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function order_admin()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=ct_admin');
	}
	
	public function global_item_options()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob_item_options');
	}
	
	public function product_settings()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function order_settings()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function shipping()
	{
		return $this->load_view(__FUNCTION__);
	}
	public function coupons_discounts()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function email_notifications()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function notifications()
	{
		$channels = $this->EE->channel_model->get_channels()->result_array();
		$statuses = array();
		
		$statuses[] = "---"; 
		$statuses[] = "ANY"; 
		foreach ($channels as $channel)
		{
			$statuses[] = $this->EE->channel_model->get_channel_statuses($channel['status_group'])->result_array();
		}
		$statuses = array_unique($statuses); 
		
		
		$structure['class']	= 'notifications'; 
		$structure['stacked'] = TRUE; 
		$structure['description']	=''; 
		$structure['caption']	=''; 
		$structure['title']	= "notifications"; 
	 	$structure['settings'] = array(
			array(
				'name' => 'notifications',
				'short_name' => 'notifications',
				'type' => 'matrix',
				'settings' => array(
					array(
						'name' => 'email_subject',
						'short_name' => 'email_subject',
						'type' => 'text', 
					),
					array(
						'name'			=>	'email_from_name',
						'short_name'	=>	'email_from_name',
						'type'			=>	'text',
					),
					array(
						'name'			=>	'email_from',
						'short_name'	=>	'email_from',
						'type'			=>	'text',
					),
					array(
						'name'			=>	'email_to',
						'short_name'	=>	'email_to',
						'type'			=>	'text',
						'default'		=> '{customer_email}'
					),
					array(
						'name'=>'email_template',
						'short_name'=>'email_template',
						'type'=>'select',
						'attributes' => array(
							'class' 	=> 'templates',
							),
					),
					array(
						'name' => "cartthrob_initiated_event", 
						'short_name' => 'email_event', 
						'type' => 'select',
						'default' => '',
						'options' => array(
							'payment_triggers' => array(
								'completed' 	=> 'ct_completed',
								'declined' 		=> 'ct_declined',
								'failed' 		=> 'ct_failed',
								'offsite' 		=> 'ct_offsite',
								'processing'	=> 'ct_processing',
								'refunded' 		=> 'ct_refunded',
								),
							'other_events'	=> array(
								'low_stock'		=> 'ct_low_stock',
								''	=> 'status_change'
								),
							) 
					),
					array(
						'name'=>'starting_status',
						'short_name'=>'status_start',
						'type'=>'select',
						'default'	=> '---',
						'attributes' => array(
							'class' 	=> 'statuses_blank',
							),
					),
					array(
						'name'=>'ending_status',
						'short_name'=>'status_end',
						'type'=>'select',
						'default'	=> '---',
						'attributes' => array(
							'class' 	=> 'statuses_blank',
							),
					),
					array(
						'name' => "email_type", 
						'short_name' => 'email_type', 
						'type' => 'select',
						'default' => 'html',
						'options' => array(
							'html' => 'send_html_email',
							'text' => 'send_text_email', 
							) 
					),
				)
			),
	 	);
		return $this->load_view(__FUNCTION__, array(), $structure);
	}
	
	public function payment_gateways()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function support()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function members()
	{
		return $this->load_view(__FUNCTION__);
	}
	
	public function import_export()
	{
		return $this->load_view(
			__FUNCTION__,
			array(
				'form_open' => form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=import_settings', array('enctype' => 'multipart/form-data')),
			)
		);
	}
	
	public function installation()
	{
		return $this->load_view(
			__FUNCTION__,
			array(
				'form_open' => form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=install_templates'),
				'template_errors' => ($this->EE->session->flashdata('template_errors')) ? $this->EE->session->flashdata('template_errors') : array(),
				'templates_installed' => ($this->EE->session->flashdata('templates_installed')) ? $this->EE->session->flashdata('templates_installed') : array(),
				'theme_errors' => ($this->EE->session->flashdata('theme_errors')) ? $this->EE->session->flashdata('theme_errors') : array(),
				'themes_installed' => ($this->EE->session->flashdata('themes_installed')) ? $this->EE->session->flashdata('themes_installed') : array(),
				'themes' => $this->get_themes(),
			)
		);
	}
	
	private function load_view($current_nav, $more = array(), $structure = array())
	{
		if ( ! $this->EE->config->item('encryption_key'))
		{
			$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('cartthrob_module_name').' - '.$this->EE->lang->line('encryption_key'));
			
			return $this->EE->load->view('encryption_key', array(), TRUE);
		}
		
		$this->EE->load->library('addons');
		
		$modules = $this->EE->addons->get_installed();
		
		if ( ! isset($modules['cartthrob']['module_version']) || version_compare($this->version(), $modules['cartthrob']['module_version'], '>'))
		{
			$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('cartthrob_module_name').' - '.$this->EE->lang->line('update_required'));
			
			return $this->EE->load->view('update_required', array(), TRUE);
		}
		
		$this->initialize();
		
		$this->EE->load->library('package_installer', array('xml' => PATH_THIRD.'cartthrob/installer/installer.xml'));
		
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('cartthrob_module_name').' - '.$this->EE->lang->line('nav_'.$current_nav));
		
		$vars = array();
		
		$settings = $this->get_settings();
		
		$channels = $this->EE->channel_model->get_channels()->result_array();
		
		$fields = array();
		
		$channel_titles = array();
		
		$statuses = array();
		
		foreach ($channels as $channel)
		{
			$channel_titles[$channel['channel_id']] = $channel['channel_title'];
			
			// $fields[$channel['channel_id']] = $this->EE->field_model->get_fields($channel['field_group'])->result_array();
			// only want to capture a subset of data, because we're using this for JSON and we were getting too much data previously
			$channel_fields = $this->EE->field_model->get_fields($channel['field_group'])->result_array(); 
			foreach ($channel_fields as $key => &$data)
			{
				$fields[$channel['channel_id']][$key] = array_intersect_key($data, array_fill_keys(array('field_id', 'site_id', 'group_id', 'field_name', 'field_type', 'field_label'), TRUE));
			}
			
			$statuses[$channel['channel_id']] = $this->EE->channel_model->get_channel_statuses($channel['status_group'])->result_array();
		}
		$status_titles = array(); 
		foreach ($statuses as $status)
		{
			foreach ($status as $item)
			{
				$status_titles[$item['status']] = $item['status']; 
			}
		}
		if ( ! empty($settings['product_channels']))
		{
			foreach ($settings['product_channels'] as $i => $channel_id)
			{
				if ( ! isset($channel_titles[$channel_id]))
				{
					unset($settings['product_channels'][$i]);
				}
			}
		}
		
		if ( ! empty($settings['product_channel_fields']))
		{
			foreach ($settings['product_channel_fields'] as $channel_id => $values)
			{
				if ( ! isset($channel_titles[$channel_id]))
				{
					unset($settings['product_channel_fields'][$channel_id]);
				}
			}
		}
		
		$nav = self::$nav;
		$no_nav = self::$no_nav; 

		// if CT Admin is not installed, remove nav link to it. 
		if (! (bool) $this->EE->db->where('module_name', 'Ct_admin')->count_all_results('modules'))
		{
			unset($nav['order_admin']); 
		}
		// if global item options is not installed, remove nav link to it. 
		if (! (bool) $this->EE->db->where('module_name', 'Cartthrob_item_options')->count_all_results('modules'))
		{
			unset($nav['global_item_options']); 
		}
		
		$settings_views = array();

		$view_paths = array();
		
		// -------------------------------------------
		// 'cartthrob_add_settings_nav' hook.
		//
		if ($this->EE->extensions->active_hook('cartthrob_add_settings_nav') === TRUE)
		{
			if ($addl_nav = $this->EE->extensions->call('cartthrob_add_settings_nav', $nav))
			{
				$nav = array_merge($nav, $addl_nav);
			}
		}
		
		// -------------------------------------------
		// 'cartthrob_add_settings_views' hook.
		//
		if ($this->EE->extensions->active_hook('cartthrob_add_settings_views') === TRUE)
		{
			$settings_views = $this->EE->extensions->call('cartthrob_add_settings_views', $settings_views);
		}
		
		if (is_array($settings_views) && count($settings_views))
		{
			foreach ($settings_views as $key => $value)
			{
				if (is_array($value))
				{
					if (isset($value['path']))
					{
						$view_paths[$key] = $value['path'];
					}
					
					if (isset($value['title']))
					{
						$nav['more_settings'][$key] = $value['title'];
					}
				}
				else
				{
					$nav['more_settings'][$key] = $value;
				}
			}
		}
		
		$sections = array();
		
		foreach ($nav as $top_nav => $subnav)
		{
			if ($top_nav != $current_nav)
			{
				continue;
			}
			
			foreach ($subnav as $url_title => $section)
			{
				if ( ! preg_match('/^http/', $url_title))
				{
					$sections[] = $url_title;
				}
			}
		}
		
		$member_fields = array('' => '----');
		
		if (isset($this->EE->extensions->extensions['safecracker_submit_entry_start'][10]['Profile_ext']))
		{
			$this->EE->load->add_package_path(PATH_THIRD.'profile/');
			
			$this->EE->load->model('profile_model');
			
			$this->EE->load->remove_package_path(PATH_THIRD.'profile/');
			
			$profile_edit_channel_id = $this->EE->profile_model->settings('channel_id');
			
			if ($profile_edit_channel_id && isset($fields[$profile_edit_channel_id]))
			{
				foreach ($fields[$profile_edit_channel_id] as $field)
				{
					$member_fields[$field['field_id']] = $field['field_label'];
				}
			}
		}
		
		//if profile edit's settings are bad or if it's not installed
		if (count($member_fields) === 1)
		{
			$this->EE->load->model('member_model');
			
			foreach ($this->EE->member_model->get_all_member_fields(array(), FALSE)->result() as $row)
			{
				$member_fields[$row->m_field_id] = $row->m_field_label;
			}
		}
		
		if ( ! version_compare(APP_VER, '2.2', '<'))
		{
			foreach ($view_paths as $path)
			{
				$this->EE->load->add_package_path($path);
			}
		}
		
		$data = array(
			'structure'	=> $structure, 
			'nav' => $nav,
			'sections' => $sections,
			'channels' => $channels,
			'channel_titles' => $channel_titles,
			'fields' => $fields,
			'statuses' => $statuses,
			'status_titles' => $status_titles,
			'templates' => array('' => $this->EE->lang->line('choose_a_template')),
			'payment_gateways' => $this->get_payment_gateways(),
			'shipping_plugins' => $this->get_shipping_plugins(),
			'tax_plugins' => $this->get_tax_plugins(),
			//'news' => $this->get_news(),
			'install_channels' => array(),
			'install_template_groups' => array(),
			'install_member_groups' => array(),
			'view_paths' => $view_paths,
			'cartthrob_mcp' => $this,
			'form_open' => form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=quick_save'.AMP.'return='.$this->EE->input->get('method', TRUE)),
			'extension_enabled' => $this->extension_enabled,
			'module_enabled' => $this->module_enabled,
			'settings' => $settings,
			'orders_status'	 => $settings['orders_status'],
			'states_and_countries' => array_merge(array('global' => 'Global', '' => '---'), $this->EE->locales->states(), array('0' => '---'), $this->EE->locales->all_countries()),
			'states' => $this->EE->locales->states(),
			'countries' => $this->EE->locales->all_countries(),
			'no_form' => (in_array($current_nav, $this->no_form)),
			'no_nav'	=> $no_nav,
			'member_fields' => $member_fields,
			'customer_data_fields' => array(
				'first_name',
				'last_name',
				'address',
				'address2',
				'city',
				'state',
				'zip',
				'country',
				'country_code',
				'company',
				'phone',
				'email_address',
				'use_billing_info',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_address',
				'shipping_address2',
				'shipping_city',
				'shipping_state',
				'shipping_zip',
				'shipping_country',
				'shipping_country_code',
				'shipping_company',
				'language',
				'shipping_option',
				'region'
			),
		);
		
		$data['templates'] = $this->get_templates();

		foreach ($this->EE->package_installer->packages() as $index => $template)
		{
			switch($template->getName())
			{
				case 'channel':
					$data['install_channels'][$index] = $template->attributes()->channel_title;
					break;
				case 'template_group':
					$data['install_template_groups'][$index] = $template->attributes()->group_name;
					break;
				case 'member_group':
					$data['install_member_groups'][$index] = $template->attributes()->group_name;
					break;
			}
		}
		
		if (!empty($structure))
		{
			$data['html'] = $this->EE->load->view('settings_template', $data, TRUE);
		}
		$data = array_merge($data, $more);
		
		$self = $data;
		
		$data['data'] = $self;
		
		unset($self);
		
		$this->EE->cp->add_js_script('ui', 'accordion');
		
		if (version_compare(APP_VER, '2.2', '<'))
		{
			$this->EE->cp->add_to_head($this->EE->load->view('settings_form_head', $data, TRUE));
			
			$output = $this->EE->load->view('settings_form', $data, TRUE);
		}
		else
		{
			//$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
			
			$this->EE->cp->add_to_head($this->EE->load->view('settings_form_head', $data, TRUE));
			
			$output = $this->EE->load->view('settings_form', $data, TRUE);
			
			foreach ($view_paths as $path)
			{
				$this->EE->load->remove_package_path($path);
			}
		}
		
		return $output;
	}
	
	public function reports()
	{
		if ($this->EE->input->get('save'))
		{
			$reports_settings = $this->EE->input->post('reports_settings');
			
			if (is_array($reports_settings) && isset($reports_settings['reports']))
			{
				$_POST = array('reports' => $reports_settings['reports']);
			}
			else
			{
				$_POST = array('reports' => array());
			}
			
			$_GET['return'] = 'reports';
			
			return $this->quick_save();
		}
		
		$this->initialize();
		
		if ($this->EE->input->get('entry_id'))
		{
			$this->EE->functions->redirect(BASE.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'entry_id='.$this->EE->input->get('entry_id'));
		}
		
		$this->EE->load->library('reports');
		
		$this->EE->load->library('number');
		
		if ($this->EE->input->get_post('report'))
		{
			$this->EE->load->library('template_helper');
			
			$this->EE->template_helper->reset(array(
				'base_url' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=reports'.AMP.'report=',
				'template_key' => 'report',
			));
			
			$data['view'] = $this->EE->template_helper->cp_render();
		}
		//default view
		else
		{
			if ($this->EE->input->get('year'))
			{
				if ($this->EE->input->get('month'))
				{
					if ($this->EE->input->get('day'))
					{
						$name = date('D d', mktime(0, 0, 0, $this->EE->input->get('month'), $this->EE->input->get('day'), $this->EE->input->get('year')));
						
						$rows = $this->EE->reports->get_daily_totals($this->EE->input->get('day'), $this->EE->input->get('month'), $this->EE->input->get('year'));
						
						$overview = lang('narrow_by_order');
					}
					else
					{
						$name = date('F Y', mktime(0, 0, 0, $this->EE->input->get('month'), 1, $this->EE->input->get('year')));
						
						$rows = $this->EE->reports->get_monthly_totals($this->EE->input->get('month'), $this->EE->input->get('year'));
						
						$overview = lang('narrow_by_day');
					}
				}
				else
				{
					$name = $this->EE->input->get('year');
					
					$rows = $this->EE->reports->get_yearly_totals($this->EE->input->get('year'));
					
					$overview = lang('narrow_by_month');
				}
			}
			else
			{
				$name = $this->EE->lang->line('reports_order_totals_to_date');
				
				$rows = $this->EE->reports->get_all_totals();
				
				$overview = lang('narrow_by_month');
			}
		
			if ($rows)
			{
				$this->EE->javascript->output('cartthrobChart('.$this->EE->javascript->generate_json($rows, TRUE).', "'.$name.'");');
			}
			
			$data['view'] = $this->EE->load->view('reports_home', array('overview' => $overview), TRUE);
		}
		
		$this->EE->load->library('table');
		
		$this->EE->table->clear();
		
		$this->EE->table->set_template(array('table_open' => '<table border="0" cellpadding="0" cellspacing="0" class="mainTable padTable">'));
		
		$data['order_totals'] = $this->EE->table->generate(array(
			array(lang('order_totals'), lang('amount')),
			array(lang('today_sales'), $this->EE->number->format($this->EE->reports->get_current_day_total())),
			array(lang('month_sales'), $this->EE->number->format($this->EE->reports->get_current_month_total())),
			array(lang('year_sales'), $this->EE->number->format($this->EE->reports->get_current_year_total())),
		));
		
		$data['current_report'] = $this->EE->input->get_post('report');
		
		$data['reports'] = array(
			'' => lang('order_totals'),
		);
		
		if ($this->EE->cartthrob->store->config('reports'))
		{
			foreach ($this->EE->cartthrob->store->config('reports') as $report)
			{
				$data['reports'][$report['template']] = $report['name'];
			}
		}
		
		$plugin_vars = array(
			'cartthrob_mcp' => $this,
			'settings' => array(
				'reports_settings' => array(
					'reports' => $this->EE->cartthrob->store->config('reports'),
				),
			),
			'plugin_type' => 'reports',
			'plugins' => array(
				array(
					'classname' => 'reports',
					'title' => 'reports_settings_title',
					'overview' => 'reports_settings_overview',
					'settings' => array(
						array(
							'name' => 'reports',
							'short_name' => 'reports',
							'type' => 'matrix',
							'settings' => array(
								array(
									'name' => 'report_name',
									'short_name' => 'name',
									'type' => 'text'
								),
								array(
									'name' => 'report_template',
									'short_name' => 'template',
									'type' => 'select',
									'options' => $this->get_templates(),
								),
							)
						)
					)
				)
			),
		);
		
		$data['reports_list'] = $this->EE->load->view('plugin_settings', $plugin_vars, TRUE);
		
		return $this->load_view(__FUNCTION__, $data);
	}
	
	// --------------------------------
	//  Plugin Settings
	// --------------------------------
	/**
	 * Creates setting controls
	 * 
	 * @access private
	 * @param string $type text|textarea|radio The type of control that is being output
	 * @param string $name input name of the control option
	 * @param string $current_value the current value stored for this input option
	 * @param array|bool $options array of options that will be output (for radio, else ignored) 
	 * @return string the control's HTML 
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function plugin_setting($type, $name, $current_value, $options = array(), $attributes = array())
	{
		$output = '';
		
		if ( ! is_array($options))
		{
			$options = array();
		}
		else
		{
			$new_options = array(); 
			foreach ($options as $key => $value)
			{
				// optgropus
				if (is_array($value))
				{	
					$key = lang($key); 
					foreach ($value as $sub=> $item)
					{
						$new_options[$key][$sub] = lang($item);
					}
				}
				else
				{
					$new_options[$key] = lang($value);
				}
			}
			$options = $new_options; 
		}
		
		if ( ! is_array($attributes))
		{
			$attributes = array();
		}

		switch ($type)
		{
			case 'note':
				$output = $current_value;
				break;
			case 'select':
				if (empty($options)) $attributes['value'] = $current_value;
				$output = form_dropdown($name, $options, $current_value, _attributes_to_string($attributes));
				break;
			case 'multiselect':
				$output = form_multiselect($name."[]", $options, $current_value, _attributes_to_string($attributes));
				break;
			case 'checkbox':
				$output = form_label(form_checkbox($name, 1, ! empty($current_value), isset($options['extra']) ? $options['extra'] : '').'&nbsp;'.(!empty($options['label'])? $options['label'] : $this->EE->lang->line('yes') ), $name);
				break;
			case 'text':
				$attributes['name'] = $name;
				$attributes['value'] = $current_value;
				$output =  form_input($attributes);
				break;
			case 'textarea':
				$attributes['name'] = $name;
				$attributes['value'] = $current_value;
				$output =  form_textarea($attributes);
				break;
			case 'radio':
				if (empty($options))
				{
					$output .= form_label(form_radio($name, 1, (bool) $current_value).'&nbsp;'. $this->EE->lang->line('yes'), $name, array('class' => 'radio'));
					$output .= form_label(form_radio($name, 0, (bool) ! $current_value).'&nbsp;'. $this->EE->lang->line('no'), $name, array('class' => 'radio'));
				}
				else
				{
					//if is index array
					if (array_values($options) === $options)
					{
						foreach($options as $option)
						{
							$output .= form_label(form_radio($name, $option, ($current_value === $option)).'&nbsp;'. $option, $name, array('class' => 'radio'));
						}
					}
					//if associative array
					else
					{
						foreach($options as $option => $option_name)
						{
							$output .= form_label(form_radio($name, $option, ($current_value === $option)).'&nbsp;'. lang($option_name), $name, array('class' => 'radio'));
						}
					}
				}
				break;
			default:
		}
		return $output;
	}
	// END
	public function email_test()
	{
		if ( ! AJAX_REQUEST)
		{
			exit;
		}
		
		if (REQ !== 'CP')
		{
			exit;
		}
		
		$this->EE->load->library('cartthrob_emails');
		$email_event = $this->EE->input->post('email_event'); 
		if (!$email_event)
		{
			$emails = $this->EE->cartthrob_emails->get_email_for_event(NULL, "open", "closed"); 
		}
		else
		{
			$emails = $this->EE->cartthrob_emails->get_email_for_event($email_event); 
		}
		if (!empty($emails))
		{
			$test_panel = array(
				'inventory'						=> 5,
				'billing_address'              =>	'Test Avenue'	,
				'billing_address2'             =>	'Apt 1',
				'billing_city'                 =>	'Testville',
				'billing_company'              =>	'Testco',
				'billing_country'              =>	'United States',
				'billing_country_code'         =>	'USA',
				'billing_first_name'           =>	'Testy',
				'billing_last_name'            =>	'Testerson',
				'billing_state'                =>	'MO',
				'billing_zip'                  =>	'63303',
				'customer_email'               =>	'test@yoursite.com',
				'customer_name'                =>	'Test Testerson',
				'customer_phone'               =>	'555-555-5555',
				'discount'                     =>	'0.00',
				'entry_id'                     =>	'111',
				'group_id'                     =>	'1',
				'member_id'                    =>	'1',
				'order_id'                     =>	'111',
				'shipping'                     =>	'10',
				'shipping_plus_tax'            =>	'10.80',
				'subtotal'                     =>	'110.00',
				'subtotal_plus_tax'            =>	'123.45',
				'tax'                          =>	'13.45',
				'title'                        =>	'111',
				'total'                        =>	'123.45',
				'total_cart'                   =>	'123.45'	,
				'transaction_id'               => "12345678"	,
			);
			foreach ($emails as $email_content)
			{
				$this->EE->cartthrob_emails->send_email($email_content, $test_panel); 
			}
		}
		
 		//forces json output
		$this->EE->output->send_ajax_response(array('XID' => $this->EE->functions->add_form_security_hash('{XID_HASH}')));
	}
	public function save_price_modifier_presets_action()
	{
		if ( ! AJAX_REQUEST)
		{
			exit;
		}
		
		if (REQ !== 'CP' && ! $this->EE->security->secure_forms_check($this->EE->input->post('XID')))
		{
			exit;
		}
		
		$this->EE->db->from('cartthrob_settings')
				->where('`key`', 'price_modifier_presets')
				->where('site_id', $this->EE->config->item('site_id'));
		
		$presets = ($this->EE->input->post('price_modifier_presets')) ? $this->EE->input->post('price_modifier_presets', TRUE) : array();
		
		$value = array();
		
		foreach ($presets as $preset)
		{
			if ( ! is_array($preset['values']))
			{
				continue;
			}
			
			$value[$preset['name']] = $preset['values'];
		}
		
		$data = array(
			'value' => serialize($value),
			'serialized' => 1,
		);
		
		if ($this->EE->db->count_all_results() == 0)
		{
			$data['site_id'] = $this->EE->config->item('site_id');
			$data['`key`'] = 'price_modifier_presets';
			
			$this->EE->db->insert('cartthrob_settings', $data);
		}
		else
		{
			$this->EE->db->update(
				'cartthrob_settings',
				$data,
				array(
					'site_id' => $this->EE->config->item('site_id'),
					'`key`' => 'price_modifier_presets',
				)
			);
		}
		
		//forces json output
		$this->EE->output->send_ajax_response(array('XID' => $this->EE->functions->add_form_security_hash('{XID_HASH}')));
	}
	
	private function json_response($data)
	{
		$this->EE->load->library('javascript');
		
		if ($this->EE->config->item('send_headers') == 'y')
		{
			$this->EE->load->library('user_agent', NULL, 'user_agent');
			
			//many browsers do not consistently like this content type
			//array('Firefox', 'Mozilla', 'Netscape', 'Camino', 'Firebird')
			if (0 && is_array($msg) && in_array($this->EE->user_agent->browser(), array('Safari', 'Chrome')))
			{
				@header('Content-Type: application/json');
			}
			else
			{
				@header('Content-Type: text/html; charset=UTF-8');	
			}
		}
		
		die($this->EE->javascript->generate_json($data));
	}
	
	// END
	// --------------------------------
	//  Save Settings
	// --------------------------------
	/**
	 * Validates, cleans, saves data, reports errors if fields were not filled in, saves and updates CartThrob settings in the database
	 * 
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function install_templates()
	{
		$this->initialize();
		
		if (version_compare(APP_VER, '2.2', '<'))
		{
			$orig_view_path = $this->EE->load->_ci_view_path;
			
			$this->EE->load->_ci_view_path = PATH_THIRD.'cartthrob/views/';
			
			$this->EE->load->library('package_installer', array('xml' => PATH_THIRD.'cartthrob/installer/installer.xml'));
			
			$this->EE->load->_ci_view_path = $orig_view_path;
		}
		else
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
			
			$this->EE->load->library('package_installer', array('xml' => PATH_THIRD.'cartthrob/installer/installer.xml'));
		}
		
		if (is_array($templates_to_install = $this->EE->input->post('templates')))
		{
			foreach ($this->EE->package_installer->packages() as $row_id => $package)
			{
				if ( ! in_array($row_id, $templates_to_install))
				{
					$this->EE->package_installer->remove_package($row_id);
				}
			}
			
			$this->EE->package_installer->set_template_path(PATH_THIRD.'cartthrob/installer/templates/')->install();
			
			$this->EE->session->set_flashdata('template_errors', $this->EE->package_installer->errors());
			
			$this->EE->session->set_flashdata('templates_installed', $this->EE->package_installer->installed());
			
			$this->EE->session->set_flashdata('message_failure', implode('<br>', $this->EE->package_installer->errors()));
			
			$this->EE->session->set_flashdata('message_success', implode('<br>', $this->EE->package_installer->installed()));
			
			$_POST = array();
			
			$settings = $this->get_settings();
			
			$_POST['product_channels'] = element('product_channels', $settings);
			$_POST['product_channel_fields'] = element('product_channel_fields', $settings);
			
			$query = $this->EE->channel_model->get_channels(NULL, array(), array(array('channel_name' => array('products', 'store_packages', 'orders', 'purchased_items', 'coupon_codes', 'discounts'))));

			foreach ($query->result() as $channel)
			{
				$query = $this->EE->field_model->get_fields($channel->field_group);
				
				if ($channel->channel_name == 'products')
				{
					if (is_array($_POST['product_channels']))
					{
						$_POST['product_channels'][] = $channel->channel_id;
					}
					else
					{
						$_POST['product_channels'] = array($channel->channel_id);
					}
					
					$_POST['product_channels'] = array_unique($_POST['product_channels']);
					
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'product_price':
								$_POST['product_channel_fields'][$channel->channel_id]['price'] = $field->field_id;
								break;
							case 'product_shipping':
								$_POST['product_channel_fields'][$channel->channel_id]['shipping'] = $field->field_id;
								break;
							case 'product_weight':
								$_POST['product_channel_fields'][$channel->channel_id]['weight'] = $field->field_id;
								break;
							case 'product_inventory':
								$_POST['product_channel_fields'][$channel->channel_id]['inventory'] = $field->field_id;
								break;
							case 'product_size':
							case 'product_options_other':
							case 'product_color':
								if (isset($_POST['product_channel_fields'][$channel->channel_id]['price_modifiers']))
								{
									$_POST['product_channel_fields'][$channel->channel_id]['price_modifiers'][] = $field->field_id;
								}
								else
								{
									$_POST['product_channel_fields'][$channel->channel_id]['price_modifiers'] = array($field->field_id);
								}
								break;
						}
					}
				}
				
				if ($channel->channel_name === 'store_packages')
				{
					if (is_array($_POST['product_channels']))
					{
						$_POST['product_channels'][] = $channel->channel_id;
					}
					else
					{
						$_POST['product_channels'] = array($channel->channel_id);
					}
					
					$_POST['product_channels'] = array_unique($_POST['product_channels']);
					
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'packages_price':
								$_POST['product_channel_fields'][$channel->channel_id]['price'] = $field->field_id;
								break;
						}
					}
				}
				
				if ($channel->channel_name == 'orders')
				{
					$_POST['save_orders'] = 1;
					
					$_POST['orders_channel'] = $channel->channel_id;
				
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'order_items':
								$_POST['orders_items_field'] = $field->field_id;
								break;
							case 'order_subtotal':
								$_POST['orders_subtotal_field'] = $field->field_id;
								break;
							case 'order_ip_address':
								$_POST['orders_customer_ip_address'] = $field->field_id;
								break;
							case 'order_payment_gateway':
								$_POST['orders_payment_gateway'] = $field->field_id;
								break;
							case 'order_full_billing_address':
								$_POST['orders_full_billing_address'] = $field->field_id;
								break;
							case 'order_billing_company':
								$_POST['orders_billing_company'] = $field->field_id;
								break;
							case 'order_billing_country':
								$_POST['orders_billing_country'] = $field->field_id;
								break;
							case 'order_country_code':
								$_POST['orders_country_code'] = $field->field_id;
								break;
							case 'order_full_shipping_address':
								$_POST['orders_full_shipping_address'] = $field->field_id;
								break;
							case 'order_shipping_company':
								$_POST['orders_shipping_company'] = $field->field_id;
								break;
							case 'order_shipping_country':
								$_POST['orders_shipping_country'] = $field->field_id;
								break;
							case 'order_shipping_country_code':
								$_POST['orders_shipping_country_code'] = $field->field_id;
								break;
							case 'order_customer_full_name':
								$_POST['orders_customer_name'] = $field->field_id;
								break;
							case 'order_discount':
								$_POST['orders_discount_field'] = $field->field_id;
								break;
							case 'order_subtotal_plus_tax':
								$_POST['orders_subtotal_plus_tax_field'] = $field->field_id;
								break;	
							case 'order_tax':
								$_POST['orders_tax_field'] = $field->field_id;
								break;
							case 'order_shipping':
								$_POST['orders_shipping_field'] = $field->field_id;
								break;
							case 'order_shipping_plus_tax':
								$_POST['orders_shipping_plus_tax_field'] = $field->field_id;
								break;	
							case 'order_total':
								$_POST['orders_total_field'] = $field->field_id;
								break;
							case 'order_transaction_id':
								$_POST['orders_transaction_id'] = $field->field_id;
								break;
							case 'order_last_four':
								$_POST['orders_last_four_digits'] = $field->field_id;
								break;
							case 'order_coupons':
								$_POST['orders_coupon_codes'] = $field->field_id;
								break;
							case 'order_customer_email':
								$_POST['orders_customer_email'] = $field->field_id;
								break;
							case 'order_customer_phone':
								$_POST['orders_customer_phone'] = $field->field_id;
								break;
							case 'order_billing_first_name':
								$_POST['orders_billing_first_name'] = $field->field_id;
								break;
							case 'order_billing_last_name':
								$_POST['orders_billing_last_name'] = $field->field_id;
								break;
							case 'order_billing_address':
								$_POST['orders_billing_address'] = $field->field_id;
								break;
							case 'order_billing_address2':
								$_POST['orders_billing_address2'] = $field->field_id;
								break;
							case 'order_billing_city':
								$_POST['orders_billing_city'] = $field->field_id;
								break;
							case 'order_billing_state':
								$_POST['orders_billing_state'] = $field->field_id;
								break;
							case 'order_billing_zip':
								$_POST['orders_billing_zip'] = $field->field_id;
								break;
							case 'order_shipping_first_name':
								$_POST['orders_shipping_first_name'] = $field->field_id;
								break;
							case 'order_shipping_last_name':
								$_POST['orders_shipping_last_name'] = $field->field_id;
								break;
							case 'order_shipping_address':
								$_POST['orders_shipping_address'] = $field->field_id;
								break;
							case 'order_shipping_address2':
								$_POST['orders_shipping_address2'] = $field->field_id;
								break;
							case 'order_shipping_city':
								$_POST['orders_shipping_city'] = $field->field_id;
								break;
							case 'order_shipping_state':
								$_POST['orders_shipping_state'] = $field->field_id;
								break;
							case 'order_shipping_zip':
								$_POST['orders_shipping_zip'] = $field->field_id;
								break;
							case 'order_shipping_option':
								$_POST['orders_shipping_option'] = $field->field_id;
								break;
							case 'order_error_message':
								$_POST['orders_error_message_field'] = $field->field_id;
								break;
						}
					}
				}
				
				if ($channel->channel_name == 'purchased_items')
				{
					$_POST['save_purchased_items'] = 1;
					
					$_POST['purchased_items_channel'] = $channel->channel_id;
				
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'purchased_id':
								$_POST['purchased_items_id_field'] = $field->field_id;
								break;
							case 'purchased_quantity':
								$_POST['purchased_items_quantity_field'] = $field->field_id;
								break;
							case 'purchased_price':
								$_POST['purchased_items_price_field'] = $field->field_id;
								break;
							case 'purchased_order_id':
								$_POST['purchased_items_order_id_field'] = $field->field_id;
								break;
							case 'purchased_license_number':
								$_POST['purchased_items_license_number_field'] = $field->field_id;
								break;
						}
					}
				}
				
				if ($channel->channel_name == 'coupon_codes')
				{
					$_POST['coupon_code_field'] = 'title';
					
					$_POST['coupon_code_channel'] = $channel->channel_id;
				
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'coupon_code_type':
								$_POST['coupon_code_type'] = $field->field_id;
								break;
						}
					}
				}
				
				if ($channel->channel_name == 'discounts')
				{
					$_POST['discount_channel'] = $channel->channel_id;
				
					foreach ($query->result() as $field)
					{
						switch($field->field_name)
						{
							case 'discount_type':
								$_POST['discount_type'] = $field->field_id;
								break;
						}
					}
				}
			}
			
			$_GET['return'] = 'installation';
			
			$this->quick_save(FALSE);
		}
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob');
	}
	
	public function install_theme()
	{
		$themes = $this->get_themes();
		
		$theme = $this->EE->input->post('theme');
		
		if ( ! in_array($theme, $themes))
		{
			show_error(lang('invalid_theme'));
		}
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		if ($this->EE->config->item('cartthrob_third_party_path'))
		{
			$theme_path = $this->EE->config->slash_item('cartthrob_third_party_path').'installer/'.$theme.'/';
		}
		else
		{
			$theme_path = PATH_THIRD.'cartthrob/third_party/installer/'.$theme.'/';
		}
		
		$this->EE->load->library('package_installer', array('xml' => $theme_path.'installer.xml'));
		
		$this->EE->package_installer->set_template_path($theme_path.'templates/')->install();
		
		$this->EE->session->set_flashdata('theme_errors', $this->EE->package_installer->errors());
		
		$this->EE->session->set_flashdata('themes_installed', $this->EE->package_installer->installed());
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=installation');
	}
	
	private function get_themes()
	{
		if ($this->EE->config->item('cartthrob_third_party_path'))
		{
			$theme_base_path = $this->EE->config->slash_item('cartthrob_third_party_path').'installer/';
		}
		else
		{
			$theme_base_path = PATH_THIRD.'cartthrob/third_party/installer/';
		}
		
		$this->EE->load->helper('directory');
		
		$themes = array();
		
		if ($map = directory_map($theme_base_path, 1))
		{
			foreach ($map as $theme)
			{
				$theme_path = $theme_base_path.$theme;
				
				if (@is_file($theme_path) || ! @is_dir($theme_path) || ! @file_exists($theme_path.'/installer.xml') || ! @is_dir($theme_path.'/templates'))
				{
					continue;
				}
				
				$themes[$theme] = $theme;
			}
		}
		
		return $themes;
	}
	
	public function import_settings()
	{
		$this->initialize();
		
		if (isset($_FILES['settings']) && $_FILES['settings']['error'] == 0)
		{
			$this->EE->load->helper('file');
			
			if ($new_settings = read_file($_FILES['settings']['tmp_name']))
			{
				$_POST = _unserialize($new_settings);
			}
			
			$_GET['return'] = 'import_export';
			
			$this->quick_save();
		}
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=import_export');
	}
	// END
	
	public function quick_save($set_success_message = TRUE)
	{
		$this->initialize();
		
		$settings = $this->get_saved_settings();
		
		$data = array();
		
		//if they change fingerprint method we have to wipe their sessions
		if (isset($_POST['session_fingerprint_method']) && isset($settings['session_fingerprint_method']) && $_POST['session_fingerprint_method'] != $settings['session_fingerprint_method'])
		{
			$this->EE->db->truncate('cartthrob_sessions');
		}
		
		foreach (array_keys($_POST) as $key)
		{
			if ( ! in_array($key, $this->remove_keys) && ! preg_match('/^(Cartthrob_.*?_settings|product_weblogs|product_weblog_fields|default_location|tax_settings)_.*/', $key))
			{
				$data[$key] = $this->EE->input->post($key);
			}
		}
		
		foreach ($data as $key => $value)
		{
			$where = array(
				'site_id' => $this->EE->config->item('site_id'),
				'`key`' => $key
			);
			
			//custom key actions
			switch($key)
			{
				/*
				case 'use_session_start_hook':
					
					$is_installed = (bool) $this->EE->db->where('class', 'Cartthrob_ext')->where('hook', 'sessions_end')->count_all_results('extensions');
					
					if ($value)
					{
						if ( ! $is_installed)
						{
							$this->EE->db->insert('extensions', array(
								'class' => 'Cartthrob_ext', 
								'method' => 'sessions_end',
								'hook' => 'sessions_end', 
								'settings' => '', 
								'priority' => 10, 
								'version' => $this->version(),
								'enabled' => 'y',
							));
						}
					}
					else
					{
						if ($is_installed)
						{
							$this->EE->db->where('class', 'Cartthrob_ext')->where('hook', 'sessions_end')->delete('extensions');
						}
					}
					
					break;
					*/
				case 'cp_menu':
					
					$is_installed = (bool) $this->EE->db->where('class', 'Cartthrob_ext')->where('hook', 'cp_menu_array')->count_all_results('extensions');
					
					if ($value)
					{
						if ( ! $is_installed)
						{
							$this->EE->db->insert('extensions', array(
								'class' => 'Cartthrob_ext', 
								'method' => 'cp_menu_array',
								'hook' => 'cp_menu_array', 
								'settings' => '', 
								'priority' => 10, 
								'version' => $this->version(),
								'enabled' => 'y',
							));
						}
					}
					else
					{
						if ($is_installed)
						{
							$this->EE->db->where('class', 'Cartthrob_ext')->where('hook', 'cp_menu_array')->delete('extensions');
						}
					}
					
					break;
			}
			
			if (is_array($value))
			{
				$row['serialized'] = 1;
				$row['value'] = serialize($value);
			}
			else
			{
				$row['serialized'] = 0;
				$row['value'] = $value;
			}
			
			if (isset($settings[$key]))
			{
				if ($value !== $settings[$key])
				{
					$this->EE->db->update('cartthrob_settings', $row, $where);
				}
			}
			else
			{
				$this->EE->db->insert('cartthrob_settings', array_merge($row, $where));
			}
		}
		
		if ($set_success_message)
		{
			$this->EE->session->set_flashdata('message_success', sprintf('%s %s %s', lang('cartthrob_module_name'), lang('nav_'.$this->EE->input->get('return')), lang('settings_saved')));
		}
		
		$return = ($this->EE->input->get('return')) ? AMP.'method='.$this->EE->input->get('return', TRUE) : '';
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.$return);
	}
	
	public function set_encryption_key()
	{
		$this->EE->config->_update_config(array('encryption_key' => $this->EE->input->post('encryption_key', TRUE)));
		
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob');
	}
	
	// --------------------------------
	//  Validate Settings
	// --------------------------------
	/**
	 * Checks to see if any fields are missing. If the fields are missing, The "missing" array is returned, and 'valid' boolean is false. 
	 * 
	 * @access private
	 * @param NULL
	 * @return array
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	function validate_settings()
	{
		$valid = TRUE;
		
		$missing = array();
		
		foreach ($this->required_settings as $required)
		{
			if ( ! $this->EE->input->post($required))
			{
				$missing[] = $required;
				
				$valid = FALSE;
			}
		}
		
		return array('valid'=>$valid, 'missing'=>$missing);
	}
	//END 
	
	// --------------------------------
	//  Export Settings
	// --------------------------------
	/**
	 * Generates & downloads a file called "cartthrob_settings.txt" that contains current settings for CartThrob 
	 * Useful for backup and transfer. 
	 *
	 * @access private
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function export_settings()
	{
		$this->initialize();
		
		$this->EE->load->helper('download');
		
		force_download('cartthrob_settings.txt', serialize($this->get_settings()));
	}
	//END
	
	// --------------------------------
	//  GET Settings
	// --------------------------------
	/**
	 * Loads cart, and gets default settings, then gets saved settings
	 *
	 * @access private
	 * @param NULL
	 * @return array $settings
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function get_settings()
	{
		$this->initialize();
		
		return $this->EE->cartthrob->get_settings();
	}
	
	public function get_saved_settings()
	{
		$settings = array();
		
		foreach ($this->EE->db->where('site_id', $this->EE->config->item('site_id'))->get('cartthrob_settings')->result() as $row)
		{
			if ($row->serialized)
			{
				$settings[$row->key] = unserialize($row->value);
			}
			else
			{
				$settings[$row->key] = $row->value;
			}
		}
		
		return $settings;
	}
	// END 
	
	// --------------------------------
	//  Get Payment Gateways
	// --------------------------------
	/**
	 * Loads payment gateway files
	 *
	 * @access private
	 * @param NULL
	 * @return array $gateways Array containing settings and information about the gateway
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	function get_payment_gateways()
	{
		$this->initialize();
		
		$this->EE->load->helper('file');
		$this->EE->load->library('api/api_cartthrob_payment_gateways');
			
		$templates = array('' => $this->EE->lang->line('gateways_default_template'));
		
		$this->EE->load->model('template_model');
		
		$query = $this->EE->template_model->get_templates();
		
		foreach ($query->result_array() as $row)
		{
			$templates[$row['group_name'].'/'.$row['template_name']] = $row['group_name'].'/'.$row['template_name'];
		}
		
		$gateways = $this->EE->api_cartthrob_payment_gateways->gateways();
		
		foreach ($gateways as &$plugin_data)
		{
			$this->EE->lang->loadfile(strtolower($plugin_data['classname']), 'cartthrob', FALSE);
			
			foreach (array('title', 'affiliate', 'overview') as $key)
			{
				if (isset($plugin_data[$key]))
				{
					$plugin_data[$key] = $this->EE->lang->line($plugin_data[$key]);
				}
			}
			
			$plugin_data['html'] = $this->EE->api_cartthrob_payment_gateways->set_gateway($plugin_data['classname'])->gateway_fields(TRUE);
			
			if (isset($plugin_data['settings']) && is_array($plugin_data['settings']))
			{
				foreach ($plugin_data['settings'] as $key => $setting)
				{
					$plugin_data['settings'][$key]['name'] = $this->EE->lang->line($setting['name']);
				}
				
				$plugin_data['settings'][] = array(
					'name' => $this->EE->lang->line('template_settings_name'),
					'note' => $this->EE->lang->line('template_settings_note'),
					'type' => 'select',
					'short_name' => 'gateway_fields_template',
					'options' => $templates
				);
			}
		}
		
		$this->EE->load->library('data_filter');
		
		$this->EE->data_filter->sort($gateways, 'title');
		
		return $gateways;
	}
	// END
	
	function get_shipping_plugins()
	{
		return $this->get_plugins('shipping');
	}
	
	function get_tax_plugins()
	{
		return $this->get_plugins('tax');
	}
	// --------------------------------
	//  Get Shipping Plugins
	// --------------------------------
	/**
	 * Loads shipping plugin files
	 *
	 * @access private
	 * @param NULL
	 * @return array $plugins Array containing settings and information about the plugin
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	function get_plugins($type)
	{
		$this->initialize();
		
		$this->EE->load->helper(array('file', 'data_formatting'));
	
		$plugins = array();
		
		$paths[] = CARTTHROB_PATH.'plugins/'.$type.'/';
		
		if ($this->EE->config->item('cartthrob_third_party_path'))
		{
			$paths[] = rtrim($this->EE->config->item('cartthrob_third_party_path'), '/').'/'.$type.'_plugins/';
		}
		else
		{
			$paths[] = PATH_THIRD.'cartthrob/third_party/'.$type.'_plugins/';
		}
		
		require_once CARTTHROB_PATH.'core/Cartthrob_'.$type.EXT;
		
		foreach ($paths as $path)
		{
			if ( ! is_dir($path))
			{
				continue;
			}
			
			foreach (get_filenames($path, TRUE) as $file)
			{
				if ( ! preg_match('/^Cartthrob_/', basename($file, EXT)))
				{
					continue;
				}
				
				require_once $file;
			
				$class = basename($file, EXT);
				
				$language = set($this->EE->session->userdata('language'), $this->EE->input->cookie('language'), $this->EE->config->item('deft_lang'), 'english');			
				
				if (file_exists(PATH_THIRD.'cartthrob/language/'.$language.'/'.strtolower($class).'_lang.php'))
				{
					$this->EE->lang->loadfile(strtolower($class), 'cartthrob', FALSE);
				}
				else if (file_exists($path.'../language/'.$language.'/'.strtolower($class).'_lang.php'))
				{
					$this->EE->lang->load(strtolower($class), $language, FALSE, TRUE, $path.'../', FALSE);
				}
				
				$plugin_info = get_class_vars($class);
				
				$plugin_info['classname'] = $class;
				
				$settings = $this->get_settings();
				
				if (isset($plugin_info['settings']) && is_array($plugin_info['settings']))
				{
					foreach ($plugin_info['settings'] as $key => $setting)
					{
						//retrieve the current set value of the field
						$current_value = (isset($settings[$class.'_settings'][$setting['short_name']])) ? $settings[$class.'_settings'][$setting['short_name']] : FALSE;
						//set the value to the default value if there is no set value and the default value is defined
						$current_value = ($current_value === FALSE && isset($setting['default'])) ? $setting['default'] : $current_value;
						
						if ($setting['type'] == 'matrix')
						{
							if ( ! is_array($current_value) || ! count($current_value))
							{
								$current_values = array(array());
								
								foreach ($setting['settings'] as $matrix_setting)
								{
									$current_values[0][$matrix_setting['short_name']] = isset($matrix_setting['default']) ? $matrix_setting['default'] : '';
								}
							}
							else
							{
								$current_values = $current_value;
							}
						}
					}
				}
				
				$plugins[] = $plugin_info;
			}
		}
		
		return $plugins;
	}
	
	public function get_templates()
	{
		static $templates;
		
		if (is_null($templates))
		{
			$templates = array();
			
			$this->EE->load->model('template_model');
			
			$query = $this->EE->template_model->get_templates();
			
			foreach ($query->result() as $row)
			{
				$templates[$row->group_name.'/'.$row->template_name] = $row->group_name.'/'.$row->template_name;
			}
		}
		
		return $templates;
	}
	
	
	/// BEGIN  TAXES ****************************************
	
	
	public function taxes()
	{
		// @TODO tax model
		$this->EE->load->model('tax_model');
		$limit ="50";
		/////////// pagination //////////////////////////////
		if ( ! $offset = $this->EE->input->get_post('rownum'))
		{		
			$offset = 0;
		}
		$this->EE->load->library('pagination');
		
		$total = $this->EE->db->count_all('cartthrob_tax');


		if ($total == 0)
		{
		//	$this->EE->session->set_flashdata('message_failure', sprintf('%s %s %s', lang('cartthrob_module_name'), lang('nav_'.$this->EE->input->get('return')), lang('taxes_none')));	
		}
		$this->EE->pagination->initialize( $this->pagination_config('taxes', $total, $limit) );

		$pagination = $this->EE->pagination->create_links();
		/////////// end pagination //////////////////////////////

		$data = $this->EE->tax_model->read(NULL,$limit,$offset); 
		
		return $this->load_view(
					__FUNCTION__,
					array(
						'taxes' => $data, 
						'pagination' => $pagination,
						'form_open'	=> form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=quick_save'.AMP.'return=taxes'),
						'add_href'	=> BASE.AMP. 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=add_tax',
						'edit_href'	=> BASE.AMP. 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=edit_tax'.AMP.'id=',
						'delete_href'	=> BASE.AMP. 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=delete_tax'.AMP.'id=',
					)
				);
	}

	private function pagination_config($method, $total_rows, $per_page=50)
	{
		$config['base_url'] = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method='.$method;
		$config['total_rows'] = $total_rows;
		$config['per_page'] = $per_page;
		$config['page_query_string'] = TRUE;
		$config['query_string_segment'] = 'rownum';
		$config['full_tag_open'] = '<p id="paginationLinks">';
		$config['full_tag_close'] = '</p>';
		$config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="<" />';
		$config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt=">" />';
		$config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="< <" />';
		$config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="> >" />';

		return $config;
	}

	public function add_tax()
	{
		return $this->load_view(
					__FUNCTION__,
					array(
						'form_edit'	=> form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=form_update_tax'.AMP.'return=taxes')
					)
				);
	}
	public function edit_tax()
	{
		$this->EE->load->model('tax_model'); 

		$data = $this->EE->tax_model->read($this->EE->input->get('id')); 
		return $this->load_view(
					__FUNCTION__,
					array(
						'tax' => $data, 
						'form_edit'	=> form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=form_update_tax'.AMP.'return=taxes')
					)
				);
	}
	public function delete_tax()
	{
		$this->EE->load->model('tax_model'); 

		$data = $this->EE->tax_model->read($this->EE->input->get('id')); 
		return $this->load_view(
					__FUNCTION__,
					array(
						'tax' => $data, 
						'form_edit'	=> form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method=form_update_tax'.AMP.'return=taxes')
					)
				);
	}

	public function form_update_tax()
	{
		// @TODO add tax
		$this->initialize();
		$this->EE->load->model('tax_model'); 
		$data = array();

		foreach (array_keys($_POST) as $key)
		{
			if ( ! in_array($key, $this->remove_keys) && ! preg_match('/^(Cartthrob_.*?_settings|product_weblogs|product_weblog_fields|default_location)_.*/', $key))
			{
				$data[$key] = $this->EE->input->post($key, TRUE);
			}
		}

		if (!$this->EE->input->post('id'))
		{
			$data['id'] = $this->EE->input->post('add_id'); 
			$this->EE->tax_model->create($data);

		}
		elseif($this->EE->input->post('delete_tax'))
		{
			$this->EE->tax_model->delete($this->EE->input->post('id'));
		}
		else
		{
			$this->EE->tax_model->update($data, $this->EE->input->post('id'));

		}
		$this->EE->session->set_flashdata('message_success', sprintf('%s %s %s', lang('cartthrob_module_name'), lang('nav_'.$this->EE->input->get('return')), lang('settings_saved')));

		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=cartthrob'.AMP.'method='.$this->EE->input->get('return', TRUE));
	}
	/// END TAXES ****************************************

	/**
	 * package filter
	 *
	 * used in package fieldtype to process entry filter ajax request
	 * 
	 * @return Type    Description
	 */
	public function package_filter()
	{
		if ( ! AJAX_REQUEST)
		{
			show_error($this->EE->lang->line('unauthorized_access'));
		}
		
		$this->EE->load->library('cartthrob_loader');
		
		$channels = $this->EE->cartthrob->store->config('product_channels');
		
		$this->EE->load->model('search_model');
		
		if ($this->EE->input->get_post('channel_id') && $this->EE->input->get_post('channel_id') != 'null')
		{
			$channels = $this->EE->input->get_post('channel_id');
		}
		
		$keywords = $this->EE->input->get_post('keywords');
		
		$this->EE->load->model('cartthrob_entries_model');
		
		//typed in an entry_id
		if (is_numeric($keywords))
		{
			$entries = array();
			
			if ($entry = $this->EE->cartthrob_entries_model->entry($keywords))
			{
				$entries[] = $entry;
			}
		}
		else
		{
			$this->EE->load->helper('text');
			
			$search = array(
				'channel_id' => $channels,
				'cat_id' => ($this->EE->input->get_post('cat_id') != 'all') ? $this->EE->input->get_post('cat_id') : '',
				'status' => ($this->EE->input->get_post('status') != 'all') ? $this->EE->input->get_post('status') : '',
				'date_range' => $this->EE->input->get_post('date_range'),	
				'author_id' => $this->EE->input->get_post('author_id'),
				'search_in' => ($this->EE->input->get_post('search_in')) ? $this->EE->input->get_post('search_in') : 'title',
				'exact_match' => $this->EE->input->get_post('exact_match'),
				'keywords' => $keywords,
				'search_keywords' => ($this->EE->config->item('auto_convert_high_ascii') === 'y') ? ascii_to_entities($keywords) : $keywords,
				'_hook_wheres' => array(),
				//'perpage' => $this->EE->input->get_post('perpage'),
				//'rownum' => $this->EE->input->get_post('rownum'),
			);
			
			$data = $this->EE->search_model->build_main_query($search, array('title' => 'asc'));
			
			$this->EE->load->library('data_filter');
			
			$entry_ids = $this->EE->data_filter->key_values($data['result_obj']->result_array(), 'entry_id');
		
			$entries = $this->EE->cartthrob_entries_model->entries($entry_ids);
		}
		
		$this->EE->load->model(array('product_model', 'cartthrob_field_model'));
		
		foreach ($entries as &$entry)
		{
			$entry['price_modifiers'] = $this->EE->product_model->get_all_price_modifiers($entry['entry_id']);
			
			foreach ($entry['price_modifiers'] as $price_modifier => $options)
			{
				$entry['price_modifiers'][$price_modifier]['label'] = $this->EE->cartthrob_field_model->get_field_label($this->EE->cartthrob_field_model->get_field_id($price_modifier));
			}
		}
		
		$this->EE->output->send_ajax_response($entries);
	}
	
	/**
	 * get_news
	 *
	 * @return string
	 * @author Newton
	 **/
	public function get_news()
	{
		$this->initialize();
		
		$this->EE->load->library('curl');
		$this->EE->load->library('simple_cache');
		$this->EE->load->helper('data_formatting');
		
		$return_data['version_update'] = NULL; 
		$return_data['news'] = NULL; 
	
		$cache = $this->EE->simple_cache->get('cartthrob/version');
		
		if ( ! $cache)
		{
			$data = $this->EE->curl->simple_get('http://cartthrob.com/site/versions/cartthrob_2');
			
			if ( ! $data)
			{
				return $return_data;
			}
			
			$cache = $this->EE->simple_cache->set('cartthrob/version', $data);
		}
		
		if (empty($cache))
		{
			return $return_data;
		}
		
		parse_str($cache, $content);
		
		//$data = $this->curl_transaction("http://cartthrob.com/site/versions/cartthrob_ecommerce_system");
		//$content = $this->split_url_string($data);
		
		if (isset($content['version']) && $content['version'] > $this->version())
		{
			$return_data['version_update'] = "<a href='http://cartthrob.com/cart/purchased_items/'>CartThrob has been updated to version ". $content['version']. "</a>";
		}
		else
		{
			$return_data['version_update'] 	= $this->EE->lang->line('there_are_no_updates'); 
		}
		
		if ( ! empty($content['news']))
		{
			$return_data['news'] = stripslashes(urldecode($content['news']));
		}
		
		return $return_data; 
	}
	
	public function garbage_collection()
	{
		header('X-Robots-Tag: noindex');
		
		$this->EE->db->where('expires <', @time())->delete('cartthrob_sessions');
		
		$this->EE->db->query('DELETE `'.$this->EE->db->dbprefix('cartthrob_cart').'`
				  FROM `'.$this->EE->db->dbprefix('cartthrob_cart').'`
				  LEFT OUTER JOIN `'.$this->EE->db->dbprefix('cartthrob_sessions').'`
				  ON `'.$this->EE->db->dbprefix('cartthrob_cart').'`.`id` = `'.$this->EE->db->dbprefix('cartthrob_sessions').'`.`cart_id`
				  WHERE `'.$this->EE->db->dbprefix('cartthrob_sessions').'`.`cart_id` IS NULL');
		
		exit;
	}

	public function version()
	{
		if (is_null($this->version))
		{
			include_once PATH_THIRD.'cartthrob/config.php';
			
			$this->version = CARTTHROB_VERSION;
		}
		
		return $this->version;
	}
	
	protected function html($content, $tag = 'p', $attributes = '')
	{
		if (is_array($attributes))
		{
			$attributes = _parse_attributes($attributes);
		}
		
		return '<'.$tag.$attributes.'>'.$content.'</'.$tag.'>';
	}
}

/* End of file mcp.cartthrob.php */
/* Location: ./system/expressionengine/third_party/cartthrob/mcp.cartthrob.php */