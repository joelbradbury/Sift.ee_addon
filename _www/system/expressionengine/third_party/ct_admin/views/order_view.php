<?php 
$this->load->view('errors'); 
?>
<?=form_open($query_base.'update_order', array('id'=>'my_accordion'))?>
<?php
echo form_hidden('order_id', $order_id);
$this->table->set_template($cp_table_template);
$this->table->set_empty("&nbsp;");
?>	
<div>
<div class="ct_top_nav">
	<div class="ct_nav">
		<span class="button"> 
			<a class="nav_button" href="<?php echo $invoice_url; ?>" target="_blank"><?php echo lang('print_invoice')?></a>
		</span>
		
		<span class="button"> 
			<a class="nav_button" href="<?php echo $packingslip_url; ?>" target="_blank"><?php echo lang('packing_slip')?></a>
		</span>
		
		<span class="button"> 
			<a class="nav_button" href="<?php echo $publishview_url; ?>" ><?php echo lang('edit_in_publish_view')?></a>
		</span>				
	</div>
</div>

<br clear="all" />
<div>
<?php 
if(is_array($statuses) && count($statuses) != '0')
{
	$options = array();
	foreach($statuses AS $status)
	{
		$options[$status['status']] = lang($status['status']);
	}
}
$this->table->set_heading(lang('name'), lang('email'), lang('order_date'),lang('status'),lang('subtotal'),lang('total'));
$data = array(
		'<a href="'.$url_base.'customer_view'.AMP.'email='.$order_details['orders_customer_email'].'">'.$order_details['orders_billing_first_name'].' '.$order_details['orders_billing_last_name'].'</a>', 
		'<a href="mailto:'.$order_details['orders_customer_email'].'">'.$order_details['orders_customer_email'].'</a>', 
		m62_convert_timestamp($order_details['entry_date']),
		form_dropdown('status', $options, $order_details['status']),
		m62_format_money($order_details['order_subtotal']),
		m62_format_money($order_details['order_total'])
);
$this->table->add_row($data);
echo $this->table->generate();
$this->table->clear();
?>
</div>
<h3 class="accordion"><?=lang('products').' ('.count($order_items).') ';?>
</h3>
<div id="order_products"><?php 
$this->table->set_heading(
lang('product'),
lang('quantity'),
lang('unit_price'),
lang('total')
);
foreach($order_items AS $item)
{
	$cell = '<a href="?'.AMP.'D=cp'.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id=80'.AMP.'entry_id='.$item['entry_id'].'">'.$item['title'].'</a>';
	if(isset($item['extra']) && $item['extra'] != '')
	{
		$options = @unserialize(base64_decode($item['extra']));
		if(is_array($options) && count($options) >= '1')
		{
			//print_r($options);
			$cell .= '<div class="subtext">(';
			if(isset($options['sub_items']) && is_array($options['sub_items']) && count($options['sub_items']) >= '1')
			{
				$cell .= count($options['sub_items']).' Product Package';
			}
			else
			{
			   $cell .= implode(' / ', $options);
			}
			
			$cell .= ')</div>';
		}
	}
	$data = array(
		$cell, 
	$item['quantity'],
	m62_format_money($item['price']),
	m62_format_money($item['quantity']*$item['price'])
	);
	$this->table->add_row($data);
}

echo $this->table->generate();
// Clear out of the next one
$this->table->clear();
?></div>

<h3 class="accordion"><?=lang('order_details')?></h3>
<div>
<?php 
$this->table->set_heading('&nbsp;','');


if($order_details['order_transaction_id'] != '')
{
	$this->table->add_row(lang('order_transaction_id'), '#'.$order_details['order_transaction_id']);
}
$this->table->add_row(lang('order_shipping'), m62_format_money($order_details['order_shipping']));
$this->table->add_row(lang('order_tax'), m62_format_money($order_details['order_tax']));
$this->table->add_row(lang('order_coupons'), ($order_details['order_coupons'] == '' ? 'N/A' : $order_details['order_coupons']));
$this->table->add_row(lang('order_shipping_option'), ($order_details['order_shipping_option'] == '' ? 'N/A' : $order_details['order_shipping_option']));
$this->table->add_row(lang('order_discount'), ($order_details['order_discount'] == '' ? 'N/A' : $order_details['order_discount']));
echo $this->table->generate();
// Clear out of the next one
$this->table->clear();
?>
</div>

<h3 class="accordion"><?=lang('billing_address')?></h3>
<div><?php 
$this->table->set_heading('&nbsp;','');
$this->table->add_row(lang('first_name'), form_input('billing_first_name', $order_details['orders_billing_first_name']));
$this->table->add_row(lang('last_name'), form_input('billing_last_name', $order_details['orders_billing_last_name']));
$this->table->add_row(lang('address'), form_input('billing_address', $order_details['orders_billing_address']));
$this->table->add_row(lang('address2'), form_input('billing_address2', $order_details['orders_billing_address2']));
$this->table->add_row(lang('city'), form_input('billing_city', $order_details['orders_billing_city']));
$this->table->add_row(lang('state'), form_input('billing_state', $order_details['orders_billing_state']));
$this->table->add_row(lang('zip'), form_input('billing_zip', $order_details['orders_billing_zip']));
$this->table->add_row(lang('company'), form_input('billing_company', $order_details['orders_billing_company']));
$this->table->add_row(lang('country'), form_input('billing_country', $order_details['orders_billing_country']));
$this->table->add_row('Country Code', form_input('billing_country_code', $order_details['orders_country_code']));

echo $this->table->generate();
// Clear out of the next one
$this->table->clear();
?></div>
</div>

<h3 class="accordion"><?=lang('shipping_address')?></h3>
<div><?php 
$this->table->set_heading('&nbsp;','');
$this->table->add_row(lang('first_name'), form_input('shipping_first_name', $order_details['orders_shipping_first_name']));
$this->table->add_row(lang('last_name'), form_input('shipping_last_name', $order_details['orders_shipping_last_name']));
$this->table->add_row(lang('address'), form_input('shipping_address', $order_details['orders_shipping_address']));
$this->table->add_row(lang('address2'), form_input('shipping_address2', $order_details['orders_shipping_address2']));
$this->table->add_row(lang('city'), form_input('shipping_city', $order_details['orders_shipping_city']));
$this->table->add_row(lang('state'), form_input('shipping_state', $order_details['orders_shipping_state']));
$this->table->add_row(lang('zip'), form_input('shipping_zip', $order_details['orders_shipping_zip']));
$this->table->add_row(lang('company'), form_input('shipping_company', $order_details['orders_shipping_company']));
$this->table->add_row(lang('country'), form_input('shipping_country', $order_details['orders_shipping_country']));
$this->table->add_row('Country Code', form_input('shipping_country_code', $order_details['orders_shipping_country_code']));

echo $this->table->generate();
// Clear out of the next one
$this->table->clear();
?></div>
<div class="">
<div class=""><?=form_submit(array('name' => 'submit', 'value' => lang('update'), 'class' => 'submit'))?>
			<?=form_submit(array('name' => 'return', 'value' => lang('update_and_finished'), 'class' => 'submit'))?>
</div>
</div>
			<?=form_close()?>