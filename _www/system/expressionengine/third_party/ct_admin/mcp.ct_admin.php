<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.3.3
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - CP Class
 *
 * Control Panel class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/mcp.ct_admin.php
 */
class Ct_admin_mcp 
{
	public $url_base = '';
	
	/**
	 * The amount of pagination items per page
	 * @var int
	 */
	public $perpage = 10;
	
	/**
	 * The delimiter for the datatables jquery
	 * @var stirng
	 */
	public $pipe_length = 1;
	
	/**
	 * The name of the module; used for links and whatnots
	 * @var string
	 */
	private $mod_name = 'ct_admin';
	
	/**
	 * The amount of items remaining in inventory to trigger inclusion within a report
	 * @var int
	 */
	public $inventory_report_threshold = 20;
	
	/**
	 * The post statuses to ignore within calculations
	 * @var array
	 */
	public $ignore_statuses = array('closed');	
	
	
	public function __construct()
	{
		$this->EE =& get_instance();
		
		//load EE stuff
		$this->EE->load->library('javascript');
		$this->EE->load->library('table');
		$this->EE->load->helper('form');

		//load cartthrob stuff
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');  
		$this->EE->load->library('cartthrob_loader', 'cartthrob_loader');		
		$this->EE->load->library('reports', 'reports');
		$this->EE->cartthrob_loader->setup($this);		
		$this->EE->load->model('order_model', 'order_model');
		
		//make sure cartthrob is ready and set up
		if($this->EE->order_model->store->config('orders_total_field') == '' 
		   || $this->EE->order_model->store->config('orders_customer_email') == ''
		   || $this->EE->order_model->store->config('orders_billing_first_name') == ''
		   || $this->EE->order_model->store->config('orders_billing_last_name') == ''
		   )
		{
			show_error($this->EE->lang->line('cartthrob_not_setup'));
		}

		//load our stuff
		$this->EE->load->model('ct_admin_settings_model', 'ct_admin_settings', TRUE);
		$this->EE->load->library('ct_admin_js');
		$this->EE->load->library('ct_admin_lib');
		$this->EE->load->library('ct_admin_orders');
		$this->EE->load->library('ct_admin_reports'); 
		$this->EE->load->library('ct_admin_customers');	
		$this->EE->load->helper('utilities');

		$this->settings = $this->EE->ct_admin_settings->get_settings();		

		$this->query_base = 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$this->mod_name.AMP.'method=';
		$this->url_base = BASE.AMP.$this->query_base;
		$this->EE->ct_admin_lib->set_url_base($this->url_base);
		
		$this->EE->cp->set_variable('url_base', $this->url_base);
		$this->EE->cp->set_variable('query_base', $this->query_base);
		
		$this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$this->mod_name, $this->EE->lang->line('ct_admin_module_name'));
		$this->EE->cp->set_right_nav($this->EE->ct_admin_lib->get_right_menu());	
		
		//echo $this->EE->order_model->store->config('orders_channel');
		$this->number_format_defaults_prefix = $this->EE->order_model->store->config('number_format_defaults_prefix');
		$this->EE->cp->set_variable('number_format_defaults_prefix', $this->number_format_defaults_prefix);
		
		$this->errors = $this->EE->ct_admin_lib->error_check();
		$this->order_channel_statuses = $this->EE->ct_admin_orders->get_channel_statuses();
		
		$this->EE->cp->set_variable('order_channel_statuses', $this->order_channel_statuses);
		$this->EE->cp->set_variable('errors', $this->errors);
		$this->EE->cp->set_variable('settings', $this->settings);
		$this->EE->cp->set_variable('theme_folder_url', $this->EE->config->item('theme_folder_url'));
		$this->EE->cp->set_variable('order_channel_statuses', $this->order_channel_statuses);
		$this->EE->cp->set_variable('number_prefix', $this->EE->order_model->store->config('number_format_defaults_prefix'));
		
		$this->order_ignore_sql = FALSE;
		$this->success_statuses = array();
		if(count($this->settings['success_statuses']) >= '1')
		{
			$temp = array();
			foreach($this->order_channel_statuses AS $key => $status)
			{
				if(in_array($status['status_id'], $this->settings['success_statuses']))
				{
					$temp[] = "'".$status['status']."'";
					$this->success_statuses[] = $status['status'];
				}
			}
					
			if(count($temp) >= '1')
			{
				$this->order_ignore_sql = " AND status IN(".implode(',', $temp).")";
			}
		}
		
		
		$ignore_methods = array('orders', 'customers');
		$method = $this->EE->input->get('method', TRUE);
		if($this->settings['disable_accordions'] === FALSE && !in_array($method, $ignore_methods))
		{
			$this->EE->javascript->output($this->EE->ct_admin_js->get_accordian_css());
		}		
	}
	
	public function index()
	{
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('dashboard'));
		
		$this->EE->cp->add_js_script('ui', 'accordion'); 
		$this->EE->jquery->tablesorter('#todays_orders table', '{headers: {5: {sorter: false}}, widgets: ["zebra"], sortList: [[0,1]]}');  
		$this->EE->javascript->compile(); 		
		
		$vars = array();
		$vars['todays_orders'] = $this->EE->ct_admin_orders->get_orders($this->settings['orders_list_limit'], 0, 'entry_date DESC', " AND year = '".date('Y')."' AND month = '".date('m')."' AND day = '".date('d')."' ".$this->order_ignore_sql);
		$vars['total_sales'] = $this->EE->ct_admin_orders->get_total_sales($this->order_ignore_sql);
		$vars['average_order'] = $this->EE->ct_admin_orders->get_average_order($this->order_ignore_sql);
		$vars['this_years_sales'] = $this->EE->ct_admin_orders->get_total_sales(" AND year = '".date('Y')."' ".$this->order_ignore_sql);
		
		$vars['total_successful_orders'] = $this->EE->ct_admin_orders->get_total_orders($this->order_ignore_sql);
		$vars['total_orders'] = $this->EE->ct_admin_orders->get_total_orders();
		$vars['this_years_orders'] = $this->EE->ct_admin_orders->get_total_orders(" AND year = '".date('Y')."'".$this->order_ignore_sql);
		$vars['this_months_orders'] = $this->EE->ct_admin_orders->get_total_orders(" AND year = '".date('Y')."' AND month = '".date('m')."' ".$this->order_ignore_sql);
		$vars['total_customers'] = $this->EE->ct_admin_customers->get_total_customers();
		$vars['chart_order_history'] = $this->EE->ct_admin_reports->get_chart_order_history($this->settings['order_graph_limit'], $this->order_ignore_sql);
		$vars['todays_customers'] = 0;
		$vars['settings'] = $this->settings;
		return $this->EE->load->view('index', $vars, TRUE);
	}
	
	public function orders()
	{
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('orders'));
		
		$vars = array();
		$total = $this->EE->ct_admin_orders->get_total_orders();
		$vars['orders'] = $this->EE->ct_admin_orders->get_orders($this->settings['orders_list_limit'], 0);
		if ( ! $rownum = $this->EE->input->get_post('rownum'))
		{		
			$rownum = 0;
		}		
		
		$this->EE->cp->add_js_script(array('plugin' => 'dataTables','ui' => 'datepicker,slider'));
		$dt = $this->EE->ct_admin_js->get_orders_datatables('edit_orders_ajax_filter', 6, 1, $this->settings['orders_list_limit'], '"aaSorting": [[ 3, "desc" ]],');
		$this->EE->javascript->output($dt);
		$this->EE->javascript->output($this->EE->ct_admin_js->get_check_toggle());
		$this->EE->javascript->compile();
		$this->EE->load->library('pagination');


		$vars['pagination'] = $this->EE->ct_admin_lib->create_pagination('edit_orders_ajax_filter', $total, $this->settings['orders_list_limit']);
				
		$vars['total_orders'] = $total;
		
		$statuses = array();
		$i = 0;
		foreach($this->order_channel_statuses AS $status)
		{
			$statuses[$status['status']] = lang($status['status']);
		}
		
		asort($statuses);
		$prefix = array(''=>lang('filter_by_status'));
		$statuses = array_merge($prefix, $statuses);
		$vars['success_statuses'] = $statuses;
		$vars['status_selected'] = '';
		$vars['date_selected'] = '';
		$vars['order_keywords'] = '';
		
		$first_date = $this->EE->ct_admin_orders->get_first_date();
		if(isset($first_date['day']))
		{
			$vars['default_start_date'] = $first_date['year'].'-'.$first_date['month'].'-'.$first_date['day'];
		}
		else
		{
			$vars['default_start_date'] = date('Y-m-d');
		}		

		$vars['perpage_select_options'] = $this->EE->ct_admin_lib->perpage_select_options();
		$vars['date_select_options'] = $this->EE->ct_admin_lib->date_select_options();		
		$vars['minmax_orders'] = $this->EE->ct_admin_orders->get_minmax_orders();
		return $this->EE->load->view('orders', $vars, TRUE); 
	}
	
	function edit_orders_ajax_filter()
	{
		die($this->EE->ct_admin_orders->json_ordering($this->perpage, $this->url_base));
	}	
	
	public function order_view()
	{
		$vars = array();
		$order_id = (int)$this->EE->input->get('id', TRUE);
		$print_invoice = $this->EE->input->get('print_invoice', TRUE);
		$packing_slip = $this->EE->input->get('packing_slip', TRUE);
		if($order_id == 0)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_found'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;
		}
		
		$vars['statuses'] = $this->EE->ct_admin_orders->get_channel_statuses();
		$vars['order_details'] = $this->EE->ct_admin_orders->get_order($order_id);
		
		if(!$vars['order_details'] || count($vars['order_details']) == 0)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_found'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;			
		}
		
		$product_channel = $this->EE->order_model->store->config('product_channels');
		$vars['order_items'] = $this->EE->ct_admin_orders->get_order_items($order_id);
		$vars['order_id'] = $order_id;
		if(isset($vars['order_details']['order_customer_email']))
		{
			$vars['ee_member_id'] = $this->EE->ct_admin_customers->get_ee_member_id($vars['order_details']['order_customer_email']);
		}

		$this->EE->cp->set_variable('cp_page_title', $vars['order_details']['title']);
		
		$this->EE->cp->add_js_script('ui', 'accordion'); 
		$this->EE->jquery->tablesorter('#order_products table', '{headers: {4: {sorter: false}}, widgets: ["zebra"], sortList: [[0,1]]}');  
		
		$this->EE->javascript->compile(); 
		
		if($print_invoice)
		{
			$vars['site_name'] = $this->EE->config->config['site_name'];
			$vars['webmaster_email'] = $this->EE->config->config['webmaster_email'];
			$vars['site_url'] = $this->EE->config->config['site_url'];
			$vars['packing_slip'] = $packing_slip;
			
			//now check for the custom template
			$custom_path = dirname(__FILE__).'/views/print_invoice_custom.php';
			if(file_exists($custom_path))
			{
				die($this->EE->load->view('print_invoice_custom', $vars, TRUE));
			}
			
			die($this->EE->load->view('print_invoice', $vars, TRUE));
		}
		
		$vars['publishview_url'] = '?D=cp&C=content_publish&M=entry_form&channel_id='.$this->EE->ct_admin_orders->channel_id.'&entry_id='.$order_id;
		$vars['invoice_url'] = $this->EE->ct_admin_lib->get_invoice_url($order_id);
		$vars['packingslip_url'] = $this->EE->ct_admin_lib->get_invoice_url($order_id, TRUE);
		
		return $this->EE->load->view('order_view', $vars, TRUE); 				
	}
	
	public function update_order()
	{
		$order_id = $this->EE->input->post('order_id');
		if(!$order_id)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_updated'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;			
		}
		
		$data = $this->EE->ct_admin_orders->get_order($order_id);
		if(!$data)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_updated'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;			
		}

		if($this->EE->ct_admin_orders->update_order($order_id, $_POST))
		{
			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('order_updated'));
			if(isset($_POST['return']))
			{
				$this->EE->functions->redirect($this->url_base.'index');
			}
			else
			{
				$this->EE->functions->redirect($this->url_base.'order_view&id='.$order_id);
			}	
			exit;				
		}
		else
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_updated'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;				
		}
	}
	
	public function delete_order_confirm()
	{
		$order_ids = $this->EE->input->get_post('toggle', TRUE);
		if(!$order_ids || count($order_ids) == 0)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('order_not_found'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;			
		}
		
		$order_data = array();
		$ids = array();
		foreach($order_ids AS $id)
		{
			$data = $this->EE->ct_admin_orders->get_order($id);
			if(is_array($data) && count($data) != '0')
			{
				$order_data[] = array('id' => $data['entry_id'], 'title' => $data['title']);
				$ids[] = $data['entry_id'];
			}
		}
				
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('delete_order_confirm'));
		$this->EE->cp->set_variable('download_delete_question', $this->EE->lang->line('delete_order_confirm'));
		
		$vars = array();
		$vars['form_action'] = $this->query_base.'delete_orders';
		$vars['damned'] = $ids;
		$vars['data'] = $order_data;
		return $this->EE->load->view('delete_confirm', $vars, TRUE);
	}
	
	public function delete_orders()
	{
		$order_ids = $this->EE->input->get_post('delete', FALSE);
		if($this->EE->ct_admin_orders->delete_orders($order_ids))
		{
			$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('orders_deleted'));
			$this->EE->functions->redirect($this->url_base.'index');	
			exit;			
		}	
		$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('orders_delete_failure'));
		$this->EE->functions->redirect($this->url_base.'index');
		exit;	
				
	}
	
	public function customers()
	{	
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('customers'));
		
		$vars = array();
		$total = $this->EE->ct_admin_customers->get_total_customers();
		$vars['customer_data'] = $this->EE->ct_admin_customers->get_customers($this->settings['cust_list_limit'], 0, 'last_order DESC'); 
		if ( ! $rownum = $this->EE->input->get_post('rownum'))
		{		
			$rownum = 0;
		}
		
		$this->EE->cp->add_js_script(array('plugin' => 'dataTables','ui' => 'datepicker,slider'));
		$dt = $this->EE->ct_admin_js->get_customers_datatables('edit_customers_ajax_filter', 5, 1, $this->settings['cust_list_limit'], '"aaSorting": [[ 4, "desc" ]],');
		$this->EE->javascript->output($dt);
		$this->EE->javascript->output($this->EE->ct_admin_js->get_check_toggle());
		$this->EE->javascript->compile();

				
		$this->EE->load->library('pagination');
		$vars['pagination'] = $this->EE->ct_admin_lib->create_pagination('edit_customers_ajax_filter', $total, $this->settings['orders_list_limit']);
				
		$vars['total_customers'] = $total;		
		
		$statuses = array();
		$i = 0;
		foreach($this->order_channel_statuses AS $status)
		{
			$statuses[$status['status']] = lang($status['status']);
		}
		
		asort($statuses);
		$prefix = array(''=>lang('filter_by_status'));
		$statuses = array_merge($prefix, $statuses);
		$vars['success_statuses'] = $statuses;
		$vars['status_selected'] = '';
		$vars['date_selected'] = '';
		$vars['order_keywords'] = '';

		$first_date = $this->EE->ct_admin_orders->get_first_date();
		if(isset($first_date['day']))
		{
			$vars['default_start_date'] = $first_date['year'].'-'.$first_date['month'].'-'.$first_date['day'];
		}
		else
		{
			$vars['default_start_date'] = date('Y-m-d');
		}

		$vars['perpage_select_options'] = $this->EE->ct_admin_lib->perpage_select_options();
		$vars['date_select_options'] = $this->EE->ct_admin_lib->date_select_options();
		
		return $this->EE->load->view('customers', $vars, TRUE);
	}
	
	function edit_customers_ajax_filter()
	{
		die($this->EE->ct_admin_customers->json_ordering($this->settings['cust_list_limit'], $this->url_base, FALSE));
	}
		
	public function customer_view()
	{
		$email = $this->EE->input->get_post('email', FALSE);
		$this->EE->lang->loadfile('myaccount');
		$this->EE->lang->loadfile('member');
		$this->EE->load->model('member_model');	
		
		$vars = array();
		$vars['customer_data'] = $this->EE->ct_admin_customers->get_customer_data($email);
		$vars['order_data'] = $this->EE->ct_admin_customers->get_orders_by_email($email);
		if($vars['order_data'])
		{
			$vars['total_orders'] = count($vars['order_data']);
		}
		
		$vars['product_data'] = $this->EE->ct_admin_customers->get_products_by_email($email);
		$vars['total_products'] = 0;
		if($vars['product_data'])
		{
			$vars['total_products'] = count($vars['product_data']);
		}		
		
		$vars['total_amount_spent'] = 0;
		foreach($vars['order_data'] AS $order)
		{
			if(in_array($order['status'], $this->success_statuses))
			{
				$vars['total_amount_spent'] = $vars['total_amount_spent']+$order['order_total'];
			}
		}
		
		$purchase_dates = $this->EE->ct_admin_customers->get_purchase_dates($email);
		$vars['last_order'] = $purchase_dates['last_order'];
		$vars['first_order'] = $purchase_dates['first_order'];

		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('customer_view'));
		$this->EE->cp->set_variable('customer_email', $email); //only used as backup!
				
		$this->EE->cp->add_js_script('ui', 'accordion'); 
		$this->EE->jquery->tablesorter('#customer_orders table', '{headers: {4: {sorter: false}}, widgets: ["zebra"], sortList: [[0,1]]}');  
		$this->EE->javascript->compile(); 

		return $this->EE->load->view('customer_view', $vars, TRUE);	
	}
	
	public function products()
	{
		
	}
	
	public function reports()
	{
		$vars = array();
		$vars['product_sum_report'] = $this->EE->ct_admin_reports->product_sum_report($this->order_ignore_sql); 
		$vars['current_month_total'] = $this->EE->ct_admin_reports->get_totals(" AND year = '".date('Y')."' AND month = '".date('m')."' ". $this->order_ignore_sql);
		$vars['current_year_total'] = $this->EE->ct_admin_reports->get_totals(" AND year = '".date('Y')."' ". $this->order_ignore_sql);
		//$vars['yearly_totals'] = $this->EE->ct_admin_reports->get_yearly_totals(date('Y'));
		$vars['all_totals'] = $this->EE->ct_admin_reports->get_all_totals($this->order_ignore_sql);
		$vars['current_day_total'] = $this->EE->ct_admin_reports->get_totals(" AND year = '".date('Y')."' AND month = '".date('m')."' AND day = '".date('d')."' ".$this->order_ignore_sql);
		
		if($this->settings['inventory_report_enable'] == '1')
		{
			$vars['enable_low_stock_report'] = TRUE;
			$vars['low_stock_report'] = $this->EE->ct_admin_reports->low_stock_report();
		}
		
		$vars['total_sales'] = $this->EE->ct_admin_orders->get_total_sales($this->order_ignore_sql);
		$vars['country_data'] = FALSE;
		if($this->settings['country_report_data'] == 'ip_to_location' && $this->EE->ct_admin_lib->is_installed_module('ip_to_nation'))
		{
			$vars['country_data'] = $this->EE->ct_admin_reports->get_ip_to_country();
		}
		else
		{
			$vars['country_data'] = $this->EE->ct_admin_reports->get_order_countries();
		}
		
		$this->EE->cp->add_js_script('ui', 'accordion');
		$this->EE->cp->add_js_script(array('ui' => 'tabs'));
		$this->EE->cp->add_js_script(array('plugin' => array('overlay', 'overlay.apple')));
		$this->EE->javascript->output($this->EE->ct_admin_js->get_dialogs());
		$this->EE->jquery->tablesorter('#monthly_history_report table', '{headers: {5: {sorter: false}}, widgets: ["zebra"], sortList: [[0,1]]}');
		$this->EE->jquery->tablesorter('#low_stock_report table', '{headers: {2: {sorter: false}}, widgets: ["zebra"], sortList: [[1,0]]}');
		$this->EE->jquery->tablesorter('#product_sum_report table', '{headers: {4: {sorter: false}}, widgets: ["zebra"], sortList: [[1,1]]}'); 
		
		$this->EE->javascript->compile(); 	
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('reports'));	
		return $this->EE->load->view('ct_reports', $vars, TRUE);		
	}
	
	public function history_report()
	{	
		$month = $this->EE->input->get_post('month', FALSE);
		$year = $this->EE->input->get_post('year', FALSE);
		if(!$year || !$month)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('nothing_to_report'));
			$this->EE->functions->redirect($this->url_base.'reports');
			exit;
		}		
		
		$customer_where = " AND year='$year' AND month='$month'";
		$where = $this->order_ignore_sql." ".$customer_where;
		
		$min_date = $this->EE->ct_admin_orders->get_first_date();
		if($min_date['month'] < $month && $min_date['year'] < $year)
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('nothing_to_report'));
			$this->EE->functions->redirect($this->url_base.'reports');
			exit;			
		}
		
		$vars['product_sum_report'] = $this->EE->ct_admin_reports->product_sum_report($where);		
		if(count($vars['product_sum_report']) == '0')
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('nothing_to_report'));
			$this->EE->functions->redirect($this->url_base.'reports');
			exit;
		}		
		
		$title_date = m62_convert_timestamp(mktime(0,0,0, $month, 1, $year), '%M %Y');
		$vars['prev_range'] = 'month='.m62_convert_timestamp(mktime(0,0,0, $month-1, 1, $year), '%m').AMP.'year='.m62_convert_timestamp(mktime(0,0,0, $month-1, 1, $year), '%Y');
		$vars['next_range'] = 'month='.m62_convert_timestamp(mktime(0,0,0, $month+1, 1, $year), '%m').AMP.'year='.m62_convert_timestamp(mktime(0,0,0, $month+1, 1, $year), '%Y');
		$vars['graph_limit'] = days_in_month($month, $year);
		$vars['year'] = $year;
		$vars['month'] = $month;
		
		$time = mktime(0,0,0, $month+1, 1, $year);
		$vars['next_nav_date'] = FALSE;
		if($time < time())
		{
			$vars['next_nav_date'] = m62_convert_timestamp($time, '%M %Y');
		}
		
		$vars['prev_nav_date'] = FALSE;
		$min_date['month'] = (int)$min_date['month'];
		$time = mktime(0,0,0, $month-1, 1, $year);
		$first = mktime(0,0,0, $min_date['month'], 1, $min_date['year']);
		if($first <=  $time)
		{
			$vars['prev_nav_date'] = m62_convert_timestamp($time, '%M %Y');
		}
		
		$vars['current_month'] = FALSE;
		if($month == date('m') && $year == date('Y'))
		{
			$vars['current_month'] = TRUE;
			$vars['graph_limit'] = date('j');
		}
		
		$this->EE->cp->add_js_script('ui', 'accordion'); 	
		$this->EE->javascript->output($this->EE->ct_admin_js->get_dialogs());
		$this->EE->jquery->tablesorter('#history_customers table', '{headers: {5: {sorter: false}}, widgets: ["zebra"], sortList: [[4,1]]}');
		$this->EE->jquery->tablesorter('#todays_orders table', '{headers: {5: {sorter: false}}, widgets: ["zebra"], sortList: [[3,1]]}');
		$this->EE->jquery->tablesorter('#product_sum_report table', '{headers: {4: {sorter: false}}, widgets: ["zebra"], sortList: [[3,1]]}'); 
		$this->EE->javascript->compile();
				
		$vars['total_sales'] = $this->EE->ct_admin_orders->get_total_sales($where);
		$vars['average_order'] = $this->EE->ct_admin_orders->get_average_order($where);
		$vars['total_orders'] = $this->EE->ct_admin_orders->get_total_orders($where);
		$vars['orders'] = $this->EE->ct_admin_orders->get_orders($vars['total_orders'], 0, 'entry_date DESC', $customer_where);
		$this->EE->load->library('pagination');
		$vars['pagination'] = $this->EE->ct_admin_lib->create_pagination('edit_customers_ajax_filter', '20', $this->settings['orders_list_limit']);
		
		$vars['total_customers'] = $this->EE->ct_admin_customers->get_total_customers($customer_where);
		$vars['customers'] = $this->EE->ct_admin_customers->get_customers(200, 0, 'entry_date DESC', $customer_where); 
		$vars['chart_order_history'] = $this->EE->ct_admin_reports->get_chart_order_date($vars['graph_limit'], $where, $month, $year, 1);

		if($first <=  $time) //setup total comparisons for last month numbers
		{
			$prev['month'] = date('m', mktime(0,0,0, $month-1, 1, $year));
			$prev['year'] = date('Y', mktime(0,0,0, $month-1, 1, $year));
			$prev['customer_where'] = " AND year='".$prev['year']."' AND month='".$prev['month']."'";
			$prev['where'] = $this->order_ignore_sql." ".$prev['customer_where'];
						
			$vars['prev']['total_sales'] = $this->EE->ct_admin_orders->get_total_sales($prev['where']);
			$vars['prev']['average_order'] = $this->EE->ct_admin_orders->get_average_order($prev['where']);	
			$vars['prev']['total_orders'] = $this->EE->ct_admin_orders->get_total_orders($prev['where']);
			$vars['prev']['total_customers'] = $this->EE->ct_admin_customers->get_total_customers($prev['customer_where']);		
		}
					
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('activity_for').' '.$title_date);	
		return $this->EE->load->view('history_report', $vars, TRUE);
	}
	
	public function settings()
	{
		if(isset($_POST['go_settings']))
		{	
			if(!isset($_POST['inventory_report_enable']))
			{
				$_POST['inventory_report_enable'] = '0';
			}				
			if($this->EE->ct_admin_settings->update_settings($_POST))
			{	
				$this->EE->logger->log_action($this->EE->lang->line('log_settings_updated'));
				$this->EE->session->set_flashdata('message_success', $this->EE->lang->line('settings_updated'));
				$this->EE->functions->redirect($this->url_base.'settings');		
				exit;			
			}
			else
			{
				$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('settings_update_fail'));
				$this->EE->functions->redirect($this->url_base.'settings');	
				exit;					
			}
		}
		
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('settings'));
		
		$this->EE->cp->add_js_script('ui', 'accordion'); 
		$this->EE->javascript->compile();	

		$vars = array();
		$statuses = array();
		$i = 0;
		foreach($this->order_channel_statuses AS $status)
		{
			$statuses[$status['status_id']] = lang($status['status']);
		}
		
		asort($statuses);
		$vars['success_statuses'] = $statuses;		
		
		$vars['template_options'] = $this->EE->ct_admin_lib->get_template_options();
		$vars['country_report_data_options'] = array('country_field' => lang('country_field'), 'ip_to_location' => lang('ip_to_location_module'));
		$vars['settings_disable'] = FALSE;
		if(isset($this->EE->config->config['ct_admin']))
		{
			$vars['settings_disable'] = 'disabled="disabled"';
		}		
		return $this->EE->load->view('settings', $vars, TRUE);
	}
	
	public function export()
	{
		$type = $this->EE->input->get('type', FALSE);
		$arr = array();
		$i = 0;
		switch($type)
		{
			case 'product_sum_report':
				$year = $this->EE->input->get('year', FALSE);
				$month = $this->EE->input->get('month', FALSE);
				$where = $this->order_ignore_sql;
				if($year && $month)
				{
					$where .= " AND month='".$this->EE->db->escape_str($month)."' AND year='".$this->EE->db->escape_str($year)."'";
				}
				
				$_arr = $this->EE->ct_admin_reports->product_sum_report($where);
				foreach($_arr AS $item)
				{
					$arr[$i] = $item;
					$arr[$i]['total'] = ($item['total_quantity']*$item['price']);
					$i++;
				}
						
			break;
			
			case 'low_stock_report':
				$_arr = $this->EE->ct_admin_reports->low_stock_report();	
				foreach($_arr AS $item)
				{
					$arr[$i]['title'] = $item['title'];
					$arr[$i]['inventory'] = $item['inventory'];
					$i++;
				}
			break;
														
			case 'monthly_history_report':
				$_arr = $this->EE->reports->get_all_totals();
				foreach($_arr AS $item)
				{
					unset($item['name']);
					unset($item['href']);
					$arr[$i] = $item;
					$i++;
				}						
			break;
																	
			case 'customers':
				$arr = $this->EE->ct_admin_customers->get_export_customers();
			break;
			
			default:
				$_arr = $this->EE->ct_admin_orders->get_export_orders();
				foreach($_arr AS $item)
				{
					unset($item['name']);
					unset($item['href']);
					$arr[$i] = $item;
					$arr[$i]['entry_date'] = m62_convert_timestamp($item['entry_date'], $this->settings['export_date_format']);
					$i++;
				}
				$type = 'orders';
			break;	
		}

		if(count($arr) >= 1)
		{
			$this->EE->ct_admin_lib->downloadArray($arr, TRUE, $type.'.xls');
		}
		else
		{
			$this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('export_failed'));
			$this->EE->functions->redirect($this->url_base.'index');			
		}
		exit;
	}	
	
	function ajax_filters($ajax_method = '', $cols = '', $extra = FALSE)
	{
		if ($ajax_method == '')
		{
			return;
		}
		
		$last_sort = FALSE;
		switch($ajax_method)
		{
			case 'edit_customers_ajax_filter':
				$last_sort = TRUE;
				$limit = $this->settings['cust_list_limit'];
			break;
			
			case 'edit_orders_ajax_filter':
			default:
				$limit = $this->settings['orders_list_limit'];
			break;
		
		}
		$js = $this->EE->ct_admin_js->get_datatables($ajax_method, $cols, $this->pipe_length, $limit, $extra, $last_sort);
		return $js;
		
	}	
}