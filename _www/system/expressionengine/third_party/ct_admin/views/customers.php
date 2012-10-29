<?php 
$this->load->view('errors'); 
?>
<div class="clear_left shun"></div>
<?php if(count($customer_data) > 0):?>
<div class="ct_top_nav">

    <?=form_open($query_base.'edit_orders_ajax_filter', array('id' => 'customer_form'))?>
	<div id="filterMenu">
		<fieldset>
			<legend><?=lang('total_customers')?> <?=$total_customers; ?></legend>


			<div class="group" style="clear:both;">
				<?=form_dropdown('status', $success_statuses, $status_selected, 'id="f_status"').NBS.NBS?>
				<?=form_dropdown('date_range', $date_select_options, $date_selected, 'id="date_range"').NBS.NBS?>
				<?//=form_dropdown('total_range', $date_select_options, $date_selected, 'id="total_range"').NBS.NBS?>
				<input type="hidden" value="" name="perpage" id="f_perpage" />
				<input type="hidden" value="<?php echo $default_start_date; ?>" id="default_start_date" />
			</div>

			<div id="total_range_picker" style="display: none; margin: 0 auto 25px auto;width: 500px; height: 60px; padding: 5px 15px 5px 15px; border: 1px solid black;  background: #FFF;">
				<input type="text" id="amount" style="border:0; color:#f6931f; font-weight:bold;" />
				<div id="slider-range"></div>
			</div>
			
			<div id="custom_date_picker" style="display: none; margin: 0 auto 25px auto;width: 500px; height: 245px; padding: 5px 15px 5px 15px; border: 1px solid black;  background: #FFF;">
				<div id="cal1" style="width:250px; float:left; text-align:center;">
					<p style="text-align:left; margin-bottom:5px"><?=lang('start_date', 'custom_date_start')?>:&nbsp; <input type="text" name="custom_date_start" id="custom_date_start" value="yyyy-mm-dd" size="12" /></p>
					<span id="custom_date_start_span"></span>
				</div>
				<div id="cal2" style="width:250px; float:left; text-align:center;">
					<p style="text-align:left; margin-bottom:5px"><?=lang('end_date', 'custom_date_end')?>:&nbsp; <input type="text" name="custom_date_end" id="custom_date_end" value="yyyy-mm-dd" size="12" /></p>
					<span id="custom_date_end_span"></span>          
				</div>
			</div>
                						
			<div style="clear:both">
				<?=form_label(lang('keywords').NBS, 'order_keywords', array('class' => 'field js_hide'))?>
				<?=form_input(array('id'=>'order_keywords', 'name'=>'order_keywords', 'class'=>'field', 'placeholder' => lang('keywords'), 'value'=>$order_keywords))?>
				&nbsp;&nbsp;
				<?=form_submit('submit', lang('search'), 'id="filter_order_submit" class="submit"')?>
				&nbsp;&nbsp;
				<?=form_submit('submit', lang('export'), 'id="export_submit" class="submit"')?>				 
			</div>
		</fieldset>
	</div>
    <?=form_close()?>
    
<?=form_open($query_base.'delete_customer_confirm')?>

<?php
$this->table->set_template($cp_pad_table_template);
$this->table->set_heading(
	lang('name'),
	lang('email'),
	lang('total_sales'),
	lang('total_orders'),
	lang('last_order')
);

foreach($customer_data as $customer)
{				  
	$this->table->add_row(
							'<a href="'.$url_base.'customer_view'.AMP.'email='.$customer['email'].'">'.$customer['first_name'].' '.$customer['last_name'].'</a>',
							'<a href="mailto:'.$customer['email'].'">'.$customer['email'].'</a>',
							m62_format_money($customer['total_sales']),
							$customer['total_orders'],
							m62_convert_timestamp($customer['last_order'])
							);
}

echo $this->table->generate();
?>
<div class="tableFooter">
	<div class="tableSubmit">
		<? //=form_submit(array('name' => 'submit', 'value' => lang('delete_selected'), 'class' => 'submit'));?>
	</div>

	<span class="js_hide"><?=$pagination?></span>	
	<span class="pagination" id="filter_pagination"></span>
</div>	

<?=form_close()?>

<?php else: ?>
<?=lang('no_customers')?>
<?php endif; ?>