<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2011, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.3.2
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Mod Class
 *
 * Module class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/mod.ct_admin.php
 */
class Ct_admin {

	public $return_data	= '';
	
	/**
	 * The post statuses to ignore within calculations
	 * @var array
	 */
	public $ignore_statuses = array('closed');	
	
	public $order_ignore_sql = '';
	
	public $user_products = array();
	
	public $member_id = FALSE;
	
	public function __construct()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		$this->EE->load->model('channel_model', 'channel_model');
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/'); 
		$this->EE->load->library('cartthrob_loader');
		$this->EE->cartthrob_loader->setup($this);
		$this->EE->load->model('order_model', 'order_model');
		
		$this->EE->load->model('ct_admin_settings_model', 'ct_admin_settings', TRUE);
		$this->EE->load->library('ct_admin_lib');
		$this->EE->load->library('ct_admin_orders');
		$this->EE->load->library('ct_admin_reports');
		$this->EE->load->library('ct_admin_customers');
		$this->EE->load->library('ct_admin_products');
		$this->EE->load->library('channel_data');
		$this->EE->load->helper('utilities');
		
		$this->settings = $this->EE->ct_admin_lib->get_settings();		
		$this->number_format_defaults_prefix = $this->EE->order_model->store->config('number_format_defaults_prefix');
		$this->order_channel_statuses = $this->EE->ct_admin_orders->get_channel_statuses();
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
	}
	
	public function void()
	{
		
	}
	
    public function is_shippable()
    {
        foreach ($this->cart->items() as $row_id => $item)
        {        
            $product = ($item->product_id()) ? $this->EE->product_model->get_product($item->product_id()) : FALSE;
            if ($product)
            {
                $data = $this->EE->cartthrob_entries_model->entry_vars($product);
                if($data && isset($data['product_shippable']) && $data['product_shippable'] == 'Yes')
                {
                    return TRUE;
                }
            }
        }
    } 
    
    public function product_sum_report()
    {
    	$seller_id = $this->EE->TMPL->fetch_param('seller_id');
    	$product_id = $this->EE->TMPL->fetch_param('product_id');
    	$month = $this->EE->TMPL->fetch_param('month');
    	$year = $this->EE->TMPL->fetch_param('year');
    	$day = $this->EE->TMPL->fetch_param('day');
    	
    	$where = $this->order_ignore_sql;
    	
        //only setup seller id if we have one
    	//NOTE: locks results down to user products
    	if($seller_id)
    	{
	    	if($seller_id == 'CURRENT_USER')
	    	{
	    		$seller_id = $this->EE->session->userdata('member_id');
	    	}
	    	
	    	$seller_id = (int)$seller_id;
	    	$product_ids = $this->EE->ct_admin_products->get_author_product_ids($seller_id);
	    	if(count($product_ids) == '0')
	    	{
    			return $this->EE->TMPL->no_results();
	    	}
	    	
	    	$where .= " AND ecoi.entry_id IN( ".implode(',',$product_ids)." )";
    	}
    	
    	if($product_id)
    	{
    		$where .= " AND ecoi.entry_id = '".$product_id."'";
    	}
    	
    	if($year)
    	{
    		$where .= " AND year='".$this->EE->db->escape_str($year)."'";
    	}
    	
    	if($month)
    	{
    		$where .= " AND month='".$this->EE->db->escape_str($month)."'";
    	}

    	if($day)
    	{
    		$where .= " AND day='".$this->EE->db->escape_str($day)."'";
    	}    	
    	
    	$report = $this->EE->ct_admin_reports->product_sum_report($where);
    	if(count($report) == '0')
    	{
    		return $this->EE->TMPL->no_results();
    	} 
    	
    	foreach($report AS $key => $value)
    	{
    		$report[$key]['total_price'] = m62_format_money($value['total_price']);
    		$report[$key]['unit_price'] = m62_format_money($value['price']);
    	}

    	$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $report); 
    	return $output;
    }
    
    public function product_totals()
    {
    	$seller_id = $this->EE->TMPL->fetch_param('seller_id');
    	$product_id = $this->EE->TMPL->fetch_param('product_id');
    	$month = $this->EE->TMPL->fetch_param('month');
    	$year = $this->EE->TMPL->fetch_param('year');
    	$day = $this->EE->TMPL->fetch_param('day');
    	    	
    	$where = $this->order_ignore_sql;
    	$ecoi_where = FALSE;
    	$ect_where = FALSE;
        if($seller_id)
    	{
	    	if($seller_id == 'CURRENT_USER')
	    	{
	    		$seller_id = $this->EE->session->userdata('member_id');
	    	}
	    	
	    	$seller_id = (int)$seller_id;
	    	$product_ids = $this->EE->ct_admin_products->get_author_product_ids($seller_id);
	    	if(count($product_ids) == '0')
	    	{
	    		return $this->EE->TMPL->no_results();
	    	}
	    	
	    	$ecoi_where = " AND ecoi.entry_id IN( ".implode(',',$product_ids)." )";
	    	$ect_where = " AND ect.entry_id IN( ".implode(',',$product_ids)." )";
    	}
    	
    	if($product_id)
    	{
    		$where .= " AND ecoi.entry_id = '".$product_id."'";
    	}    	
    	 
    	if($year)
    	{
    		$where .= " AND year='".$this->EE->db->escape_str($year)."'";
    	}
    	 
    	if($month)
    	{
    		$where .= " AND month='".$this->EE->db->escape_str($month)."'";
    	}
    	
    	if($day)
    	{
    		$where .= " AND day='".$this->EE->db->escape_str($day)."'";
    	}	
    	
    	$report = array();
    	$report['items_sold'] = $this->EE->ct_admin_products->get_total_items_sold($where.$ecoi_where." AND ect.channel_id = '".$this->EE->ct_admin_orders->channel_id."'");
    	$report['gross_sales'] = $this->EE->ct_admin_products->get_total_gross_sales($where.$ecoi_where." AND ect.channel_id = '".$this->EE->ct_admin_orders->channel_id."'");
    	//$totals = $this->EE->ct_admin_reports->get_all_totals($where.$ect_where);

    	$report['gross_sales'] = m62_format_money($report['gross_sales']);
    
    	$output = $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $report);
    	return $output; 
    }
    
    public function purchased_products()
    {
    	$seller_id = $this->EE->TMPL->fetch_param('seller_id');
    	$product_id = $this->EE->TMPL->fetch_param('product_id');
    	$month = $this->EE->TMPL->fetch_param('month');
    	$year = $this->EE->TMPL->fetch_param('year');
    	$day = $this->EE->TMPL->fetch_param('day');
    	
    	$where = $this->order_ignore_sql;
    	$ecoi_where = FALSE;
    	$ect_where = FALSE;
    	if($seller_id)
    	{
    		if($seller_id == 'CURRENT_USER')
    		{
    			$seller_id = $this->EE->session->userdata('member_id');
    		}
    	
    		$seller_id = (int)$seller_id;
    		$product_ids = $this->EE->ct_admin_products->get_author_product_ids($seller_id);
    		if(count($product_ids) == '0')
    		{
    			return $this->EE->TMPL->no_results();
    		}
    	
    		$ecoi_where = " AND ecoi.entry_id IN( ".implode(',',$product_ids)." )";
    		$ect_where = " AND ect.entry_id IN( ".implode(',',$product_ids)." )";
    	}
    	 
    	if($product_id)
    	{
    		$where .= " AND ecoi.entry_id = '".$product_id."'";
    	}
    	
    	if($year)
    	{
    		$where .= " AND year='".$this->EE->db->escape_str($year)."'";
    	}
    	
    	if($month)
    	{
    		$where .= " AND month='".$this->EE->db->escape_str($month)."'";
    	}
    	 
    	if($day)
    	{
    		$where .= " AND day='".$this->EE->db->escape_str($day)."'";
    	}   

    	$data = $this->EE->ct_admin_products->get_purchased_products($where.$ecoi_where." AND ect.channel_id = '".$this->EE->ct_admin_orders->channel_id."'");
    	if(count($data) == '0')
    	{
    		return $this->EE->TMPL->no_results(); 		
    	}
    	
    	$output = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $data);
    	return $output;
    }
    
    public function invoice()
    {
    	$order_id = (int)$this->EE->TMPL->fetch_param('order_id');
    	if($order_id == 0)
    	{
    		return $this->EE->TMPL->no_results();
    	} 
    	
    	//check user stuff
    	$customer_id = $this->EE->TMPL->fetch_param('customer_id');
    	if($customer_id)
    	{
    		if($customer_id == 'CURRENT_USER')
    		{
    			$customer_id = $this->EE->session->userdata('member_id');
    		}

    		//does the user get a pass?
    		if(!$this->EE->session->userdata('can_edit_other_entries') || !$this->EE->session->userdata('can_access_cp'))
    		{
    			//check the customer's orders so they only see their stuff
    			$member_data = $this->EE->member_data->get_members(array('members.member_id' => $customer_id));
    			if(isset($member_data['0']) && is_array($member_data['0']))
    			{
    				$member_data = $member_data['0'];
    			}
    			else 
    			{
    				return $this->EE->TMPL->no_results();
    			}
    			
    			$orders = $this->EE->ct_admin_customers->get_orders_by_email($member_data['email']);
    			$display = FALSE;
    			foreach($orders AS $order)
    			{
    				if($order_id == $order['entry_id'])
    				{
    					$display = TRUE;
    					break;
    				}
    			}
    			
    			if(!$display)
    			{
    				return $this->EE->TMPL->no_results();
    			}
    		}
    	}

    	$order_data = $this->EE->channel_data->get_entry(array('entry_id' => $order_id));
    	if(!$order_data)
    	{
    		return $this->EE->TMPL->no_results();
    	}
    	
    	$conditionals = array();
    	$order_data = $order_data['0'];
    	$product_channel = $this->EE->order_model->store->config('product_channels');
    	$order_items = $this->EE->ct_admin_orders->get_order_items($order_id);
    	foreach($order_items AS $key => $value)
    	{
    		$order_items[$key]['item_title'] = $value['title'];
    		$order_items[$key]['item_price'] = m62_format_money($value['price']);
    		$order_items[$key]['item_quantity'] = $value['quantity'];
    		$order_items[$key]['item_total_price'] = m62_format_money($value['quantity']*$value['price']);
    		$order_items[$key]['sub_items'] = array();
    		$order_items[$key]['is_package'] = FALSE;
    		if(isset($value['extra']) && $value['extra'] != '')
    		{
    			$options = @unserialize(base64_decode($value['extra']));
    			if(is_array($options) && count($options) >= '1')
    			{
    				if(isset($options['sub_items']) && is_array($options['sub_items']) && count($options['sub_items']) >= '1')
    				{
    					$count = 1;
    					$order_items[$key]['is_package'] = TRUE;
    					foreach($options['sub_items'] AS $k => $v)
    					{
    						$order_items[$key]['sub_items'][$k]['sub_item_title'] = $v['title'];
    						$order_items[$key]['sub_items'][$k]['sub_item_quantity'] = $v['quantity'];
    						$order_items[$key]['sub_items'][$k]['sub_item_count'] = $count;
    						$count++;
    					}
    					
    				}
    				else
    				{
    					//$cell .= ' ('.implode(' / ', $options).')';
    				}
    			}
    		}    		
    	}

    	$order_data['order_items'] = $order_items;
    	$order_data['order_id'] = $order_id;
    	if(isset($order_data['order_details']['order_customer_email']))
    	{
    		$order_data['ee_member_id'] = $this->EE->ct_admin_customers->get_ee_member_id($order_data['order_details']['order_customer_email']);
    	}
    	$output = $this->EE->TMPL->parse_variables_row($this->EE->TMPL->tagdata, $order_data);
    	  	
    	return $output;    	
    }
}