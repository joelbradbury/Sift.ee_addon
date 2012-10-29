<?php 
$this->load->view('errors'); 
?>
<div class="clear_left shun"></div>
<?php 
if(count($orders) > 0)
{
?>
	<div style="clear:left"></div>
	
    <?=form_open($query_base.'edit_orders_ajax_filter', array('id' => 'order_form'))?>
	<div id="filterMenu">
		<fieldset>
			<legend><?=lang('total_orders')?> <?=$total_orders; ?></legend>


			<div class="group">
				<?=form_dropdown('status', $success_statuses, $status_selected, 'id="f_status"').NBS.NBS?>
				<?=form_dropdown('date_range', $date_select_options, $date_selected, 'id="date_range"').NBS.NBS?>
				<?=form_dropdown('total_range', array('null' => lang('total_range'), 'select_range' => lang('select_range')), FALSE, 'id="total_range"').NBS.NBS?>
				<input type="hidden" value="" name="perpage" id="f_perpage" />
				<input type="hidden" value="<?php echo $default_start_date; ?>" id="default_start_date" />
				<input type="hidden" value="<?php echo $minmax_orders['min_order']; ?>" id="filter_min_order" />
				<input type="hidden" value="<?php echo $minmax_orders['max_order']; ?>" id="filter_max_order" />
				
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
			
			<div id="total_range_picker" style="display: none; margin: 0 auto 25px auto;width: 500px; height: 60px; padding: 5px 15px 5px 15px; border: 1px solid black;  background: #FFF;">
				<input type="text" id="amount" style="border:0; color:#f6931f; font-weight:bold;" />
				<div id="slider-range"></div>
			</div>			
			
			
									
			<p>
				<?=form_label(lang('keywords').NBS, 'order_keywords', array('class' => 'field js_hide'))?>
				<?=form_input(array('id'=>'order_keywords', 'name'=>'order_keywords', 'class'=>'field', 'placeholder' => lang('keywords'), 'value'=>$order_keywords))?>
				&nbsp;&nbsp;
				<?=form_submit('submit', lang('search'), 'id="filter_order_submit" class="submit"')?>
				&nbsp;&nbsp;
				<?=form_submit('submit', lang('export'), 'id="export_submit" class="submit"')?>				 
			</p>
		</fieldset>
	</div>
    <?=form_close()?>	
<?php 

	echo form_open($query_base.'delete_order_confirm'); 
	
	$this->table->set_template($cp_pad_table_template);
	$this->table->set_heading(
		lang('order_id').'/'.lang('edit'),
		lang('customer_name'),
		lang('status'),
		lang('order_date'),
		lang('total'),
		form_checkbox('select_all', 'true', FALSE, 'class="toggle_all" id="select_all"').NBS.lang('delete', 'select_all')
	);

	foreach($orders as $order)
	{
		$toggle = array(
				  'name'		=> 'toggle[]',
				  'id'		=> 'edit_box_'.$order['entry_id'],
				  'value'		=> $order['entry_id'],
				  'class'		=>'toggle'
				  );
		
		if($order['member_id'] != '')
		{
			$member_edit_link = '<a href="?D=cp&C=myaccount&M=edit_profile&id="'.$order['member_id'].'>'.$order['first_name'].' '.$order['last_name'].'</a>';
		}
		else
		{
			$member_edit_link = $order['first_name'].' '.$order['last_name'];
		}
		
		$customer_link = 'customer_view&email=';
		$this->table->add_row(
								'<a href="'.$url_base.'order_view'.AMP.'id='.$order['entry_id'].'">'.$order['title'].'</a>',
								'<a href="'.$url_base.'customer_view'.AMP.'email='.$order['email'].'">'.$order['first_name'].' '.$order['last_name'].'</a>',
								'<span style="color:#'.m62_status_color($order['status'], $order_channel_statuses).'">'.lang($order['status']).'</span>',
								m62_convert_timestamp($order['entry_date']),
								m62_format_money($order['order_total']),
								form_checkbox($toggle)
								);
	}
	
	echo $this->table->generate();
	
?>
<div class="tableFooter">
	<div class="tableSubmit">
		<?php echo form_submit(array('name' => 'submit', 'value' => lang('delete_selected'), 'class' => 'submit'));?>
	</div>

	<span class="js_hide"><?php echo $pagination?></span>	
	<span class="pagination" id="filter_pagination"></span>
</div>	

<?php echo form_close()?>

<?php } else { ?>
<?php echo lang('no_matching_orders')?>
<?php } ?>