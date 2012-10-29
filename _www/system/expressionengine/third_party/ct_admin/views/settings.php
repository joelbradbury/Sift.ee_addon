<?php $this->load->view('errors'); ?>
<?php 

$tmpl = array (
	'table_open'          => '<table class="mainTable" border="0" cellspacing="0" cellpadding="0">',

	'row_start'           => '<tr class="even">',
	'row_end'             => '</tr>',
	'cell_start'          => '<td style="width:50%;">',
	'cell_end'            => '</td>',

	'row_alt_start'       => '<tr class="odd">',
	'row_alt_end'         => '</tr>',
	'cell_alt_start'      => '<td>',
	'cell_alt_end'        => '</td>',

	'table_close'         => '</table>'
);

$this->table->set_template($tmpl); 
$this->table->set_empty("&nbsp;");
?>
<div class="clear_left shun"></div>

<?php echo form_open($query_base.'settings', array('id'=>'my_accordion'))?>
<input type="hidden" value="yes" name="go_settings" />

<h3  class="accordion"><?=lang('configure')?></h3>
<div>
	<?php
	$this->table->set_heading(lang('settings'),' ');
	$this->table->add_row('<label for="success_statuses">'.lang('success_statuses').'</label><div class="subtext">'.lang('success_statuses_instructions').'</div>', form_multiselect('success_statuses[]', $success_statuses, $settings['success_statuses'], 'id="success_statuses"'. $settings_disable));
	$this->table->add_row('<label for="packingslip_template_url">'.lang('packingslip_template_url').'</label><div class="subtext">'.lang('packingslip_template_url_instructions').'</div>', form_dropdown('packingslip_template_url', $template_options, $settings['packingslip_template_url'], 'id="packingslip_template_url"' . $settings_disable));
	$this->table->add_row('<label for="invoice_template_url">'.lang('invoice_template_url').'</label><div class="subtext">'.lang('invoice_template_url_instructions').'</div>', form_dropdown('invoice_template_url', $template_options, $settings['invoice_template_url'], 'id="invoice_template_url"' . $settings_disable));
	echo $this->table->generate();
	$this->table->clear();
	?>
</div>

<h3 class="accordion"><?=lang('limits')?></h3>
<div>
	<?php 
	
	
	$this->table->set_heading(lang('settings'),' ');
	$this->table->add_row('<label for="orders_list_limit">'.lang('orders_list_limit').'</label><div class="subtext">'.lang('orders_list_limit_instructions').'</div>', form_input('orders_list_limit', $settings['orders_list_limit'], 'id="orders_list_limit"' . $settings_disable));
	$this->table->add_row('<label for="cust_list_limit">'.lang('cust_list_limit').'</label><div class="subtext">'.lang('cust_list_limit_instructions').'</div>', form_input('cust_list_limit', $settings['cust_list_limit'], 'id="cust_list_limit"' . $settings_disable));
	$this->table->add_row('<label for="order_graph_limit">'.lang('order_graph_limit').'</label><div class="subtext">'.lang('order_graph_limit_instructions').'</div>', form_input('order_graph_limit', $settings['order_graph_limit'], 'id="order_graph_limit"'. $settings_disable));
	echo $this->table->generate();
	$this->table->clear();	
	?>
</div>

<h3  class="accordion"><?=lang('date_formats')?></h3>
<div>
	<?php
	$this->table->set_heading(lang('settings'),' ');
	$this->table->add_row('<label for="graph_date_format">'.lang('graph_date_format').'</label><div class="subtext">'.lang('graph_date_format_instructions').'</div>', form_input('graph_date_format', $settings['graph_date_format'], 'id="graph_date_format"'. $settings_disable));
	$this->table->add_row('<label for="export_date_format">'.lang('export_date_format').'</label><div class="subtext">'.lang('export_date_format_instructions').'</div>', form_input('export_date_format', $settings['export_date_format'], 'id="export_date_format"'. $settings_disable));
	$this->table->add_row('<label for="ct_date_format">'.lang('ct_date_format').'</label><div class="subtext">'.lang('ct_date_format_instructions').'</div>', form_input('ct_date_format', $settings['ct_date_format'], 'id="ct_date_format"'. $settings_disable));
	echo $this->table->generate();
	$this->table->clear();
	?>
</div>

<h3  class="accordion"><?=lang('reporting_options')?></h3>
<div>
	<?php
	$this->table->set_heading(lang('settings'),' ');
	$this->table->add_row('<label for="inventory_report_enable">'.lang('inventory_report_enable').'</label><div class="subtext">'.lang('inventory_report_enable_instructions').'</div>', form_checkbox('inventory_report_enable', '1', $settings['inventory_report_enable'], 'id="inventory_report_enable"' . $settings_disable));
	$this->table->add_row('<label for="country_report_data">'.lang('country_report_data').'</label><div class="subtext">'.lang('country_report_data_instructions').'</div>', form_dropdown('country_report_data', $country_report_data_options, $settings['country_report_data'], 'id="country_report_data"' . $settings_disable));
	echo $this->table->generate();
	$this->table->clear();
	?>
</div>

<h3  class="accordion"><?=lang('license_number')?></h3>
<div>
	<?php
	$this->table->set_heading(lang('settings'),' ');
	$this->table->add_row('<label for="license_number">'.lang('license_number').'</label>', form_input('license_number', $settings['license_number'], 'id="license_number"'. $settings_disable));	
	echo $this->table->generate();
	$this->table->clear();
	?>
</div>

<br />
<div class="tableFooter">
	<div class="tableSubmit">
		<?php echo form_submit(array('name' => 'submit', 'value' => lang('submit'), 'class' => 'submit'));?>
	</div>
</div>	
<?php echo form_close()?>