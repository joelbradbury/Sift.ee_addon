<?php 
$this->load->view('errors'); 
?>
<?=form_open($query_base.'update_customer', array('id'=>'my_accordion'))?>
<?php
$this->table->set_template($cp_table_template);
$this->table->set_empty("&nbsp;");

?>	
<div>
<div class="ct_top_nav">
	<div class="ct_nav">
	<?php if(isset($customer_data['ee_member_id'])): ?>
		<span class="button"> 
			<a class="nav_button " href="?D=cp&C=myaccount&M=edit_profile&id=<?php echo $customer_data['ee_member_id']; ?>">Edit Profile</a>
		</span>
	<?php endif; ?>
	</div>
</div>
<br clear="all" />
<div>
<?php 
$this->table->set_heading(lang('name'), lang('email'), lang('first_order'),lang('last_order'),lang('total_amount_spent'));
$customer_email = (isset($customer_data['cust_email_address']) && $customer_data['cust_email_address'] != '' ? $customer_data['cust_email_address'] : $customer_email);
$data = array(
		$customer_data['cust_first_name'].' '.$customer_data['cust_last_name'], 
		'<a href="mailto:'.$customer_email.'">'.$customer_email.'</a>', 
		m62_convert_timestamp($first_order),
		m62_convert_timestamp($last_order),
		m62_format_money($total_amount_spent)
);
$this->table->add_row($data);
echo $this->table->generate();
$this->table->clear();
?>
</div>
			
<h3 class="accordion"><?=lang('customers_orders').' ('.$total_orders.') ';?></h3>
<div id="customer_orders"><?php 
// Add Markup into the table
$this->table->set_heading(
			lang('orders'),
			lang('status'),
			lang('order_total'),
			lang('date')
			);
			foreach($order_data AS $order)
			{
				$data = array(
					'<a href="'.$url_base.'order_view'.AMP.'id='.$order['entry_id'].'">'.$order['title'].'</a>', 
				'<span style="color:#'.m62_status_color($order['status'], $order_channel_statuses).'">'.lang($order['status']).'</span>',
				m62_format_money($order['order_total']),
				m62_convert_timestamp($order['entry_date'])
				);
				$this->table->add_row($data);
			}

			echo $this->table->generate();
			// Clear out of the next one
			$this->table->clear();
			?>
</div>

<h3 class="accordion"><?=lang('customers_products').' ('.$total_products.') ';?></h3>
<div id="customers_products">
<?php 
// Add Markup into the table 
$this->table->set_heading(
	lang('product'),
	lang('status'),
	lang('quantity'),
	
	lang('unit_price'),
	lang('total')
);
foreach($product_data AS $product)
{
	$data = array(
			'<a href="?'.AMP.'D=cp'.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id='.$product['product_channel_id'].AMP.'entry_id='.$product['entry_id'].'">'.$product['title'].'</a>', 
			'<span style="color:#'.m62_status_color($product['status'], $order_channel_statuses).'">'.lang($product['status']).'</span>',		
			$product['product_sales'], 
			m62_format_money(($product['price']*$product['product_sales'])), 
			m62_format_money($product['price'])
	);
	$this->table->add_row($data);
}
								
echo $this->table->generate(); 
// Clear out of the next one 
$this->table->clear(); 
?>
</div>

<h3 class="accordion"><?=lang('customer_address')?></h3>
<div><?php 
$this->table->set_heading(
			'&nbsp;',
			''
			);
			
			if(isset($customer_data['ee_member_id']))
			{
				$this->table->add_row(lang('first_name'), form_input('cust_first_name', $customer_data['cust_first_name']));
				$this->table->add_row(lang('last_name'), form_input('cust_last_name', $customer_data['cust_last_name']));
				$this->table->add_row(lang('address'), form_input('cust_address', $customer_data['cust_address']));
				$this->table->add_row(lang('address2'), form_input('cust_address2', $customer_data['cust_address2']));
				$this->table->add_row(lang('city'), form_input('cust_city', $customer_data['cust_city']));
				$this->table->add_row(lang('state'), form_input('cust_state', $customer_data['cust_state']));
				$this->table->add_row(lang('zip'), form_input('cust_zip', $customer_data['cust_zip']));
				$this->table->add_row(lang('country'), form_input('cust_country', $customer_data['cust_country']));
				$this->table->add_row('Country Code', form_input('cust_country_code', $customer_data['cust_country_code']));				
			}
			else
			{
				$this->table->add_row(lang('first_name'), $customer_data['cust_first_name']);
				$this->table->add_row(lang('last_name'), $customer_data['cust_last_name']);
				$this->table->add_row(lang('address'), $customer_data['cust_address']);
				$this->table->add_row(lang('address2'), $customer_data['cust_address2']);
				$this->table->add_row(lang('city'), $customer_data['cust_city']);
				$this->table->add_row(lang('state'), $customer_data['cust_state']);
				$this->table->add_row(lang('zip'), $customer_data['cust_zip']);	
				if(isset($customer_data['cust_country']))
				{
					$this->table->add_row(lang('country'), $customer_data['cust_country']);
				}
				
				if(isset($customer_data['cust_country_code']))
				{
					$this->table->add_row('Country Code', $customer_data['cust_country_code']);	
				}		
			}

			echo $this->table->generate();
			// Clear out of the next one
			$this->table->clear();
			?></div>
<h3 class="accordion"><?=lang('shipping_address')?></h3>
<div><?php 
$this->table->set_heading(
			'&nbsp;',
			''
			);
			if(isset($customer_data['ee_member_id']))
			{	
				$this->table->add_row(lang('first_name'), form_input('cust_shipping_first_name', $customer_data['cust_shipping_first_name']));
				$this->table->add_row(lang('last_name'), form_input('cust_shipping_last_name', $customer_data['cust_shipping_last_name']));
				$this->table->add_row(lang('address'), form_input('cust_shipping_address', $customer_data['cust_shipping_address']));
				$this->table->add_row(lang('address2'), form_input('cust_shipping_address2', $customer_data['cust_shipping_address2']));
				$this->table->add_row(lang('city'), form_input('cust_shipping_city', $customer_data['cust_shipping_city']));
				$this->table->add_row(lang('state'), form_input('cust_shipping_state', $customer_data['cust_shipping_state']));
				$this->table->add_row(lang('zip'), form_input('cust_shipping_zip', $customer_data['cust_shipping_zip']));
			}
			else
			{
				$this->table->add_row(lang('first_name'), $customer_data['cust_shipping_first_name']);
				$this->table->add_row(lang('last_name'), $customer_data['cust_shipping_last_name']);
				$this->table->add_row(lang('address'), $customer_data['cust_shipping_address']);
				$this->table->add_row(lang('address2'), $customer_data['cust_shipping_address2']);
				$this->table->add_row(lang('city'), $customer_data['cust_shipping_city']);
				$this->table->add_row(lang('state'), $customer_data['cust_shipping_state']);
				$this->table->add_row(lang('zip'), $customer_data['cust_shipping_zip']);
			}
				

			echo $this->table->generate();
			// Clear out of the next one
			$this->table->clear();
			?></div>
</div>
			<?=form_close()?>