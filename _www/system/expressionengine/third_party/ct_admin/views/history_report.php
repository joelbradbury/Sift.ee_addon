<?php 
$this->load->view('errors'); 
?>
<?php echo form_open($query_base.'reports', array('id'=>'my_accordion'))?> 
<?php
$this->table->set_template($cp_table_template); 
$this->table->set_empty("&nbsp;");
?> 

	<div> 
			<div class="ct_top_nav">
				<div class="ct_nav">
				<?php if($prev_nav_date): ?>
					<span class="button"> 
						<a class="nav_button " href="<?php echo $url_base.'history_report'.AMP.$prev_range; ?>"><?php echo $prev_nav_date; ?></a>
					</span>
				<?php endif; ?>
				</div>
			</div> 
			
			<div class="ct_top_nav" style="float:right">
				<div class="ct_nav">
				<?php if($next_nav_date): ?>
					<span class="button"> 
						<a class="nav_button " href="<?php echo $url_base.'history_report'.AMP.$next_range; ?>"><?php echo $next_nav_date; ?></a>
					</span>
				<?php endif; ?>
				</div>
			</div>			
			
			<br />		
		<?php 
		if($total_orders > '0')
		{
			$this->table->set_heading(
				lang('total_sales'),
				lang('average_order'),
				lang('total_orders'),
				lang('total_customers')
			);
			
			$data = array(
					m62_format_money($total_sales), 
					m62_format_money($average_order), 
					$total_orders,
					$total_customers
			);			
			if(isset($prev))
			{
				if($total_sales < $prev['total_sales'])
				{
					$diff = $prev['total_sales']-$total_sales;
					$total_sales = m62_format_money($total_sales).' <span style="color: rgb(255, 0, 0);">(↓ '.m62_format_money($diff).')</span>';
				}
				else
				{
					$diff = $total_sales-$prev['total_sales'];
					$total_sales = m62_format_money($total_sales).' <span style="color: rgb(0, 153, 51);">(↑ '.m62_format_money($diff).')</span>';
				}
				
				if($average_order < $prev['average_order'])
				{
					$diff = $prev['average_order']-$average_order;
					$average_order = m62_format_money($average_order).' <span style="color: rgb(255, 0, 0);">(↓ '.m62_format_money($diff).')</span>';
				}
				else
				{
					$diff = $average_order-$prev['average_order'];
					$average_order = m62_format_money($average_order).' <span style="color: rgb(0, 153, 51);">(↑ '.m62_format_money($diff).')</span>';
				}

				if($total_orders < $prev['total_orders'])
				{
					$diff = $prev['total_orders']-$total_orders;
					$total_orders = $total_orders.' <span style="color: rgb(255, 0, 0);">(↓ '.$diff.')</span>';
				}
				else
				{
					$diff = $total_orders-$prev['total_orders'];
					$total_orders = $total_orders.' <span style="color: rgb(0, 153, 51);">(↑ '.$diff.')</span>';
				}

				if($total_customers < $prev['total_customers'])
				{
					$diff = $prev['total_customers']-$total_customers;
					$total_customers = $total_customers.' <span style="color: rgb(255, 0, 0);">(↓ '.$diff.')</span>';
				}
				else
				{
					$diff = $total_customers-$prev['total_customers'];
					$total_customers = $total_customers.' <span style="color: rgb(0, 153, 51);">(↑ '.$diff.')</span>';
				}				
				
				$data = array(
						$total_sales, 
						$average_order, 
						$total_orders,
						$total_customers
				);					
			}
			$this->table->add_row($data);	
			echo $this->table->generate(); 
			// Clear out of the next one 
			$this->table->clear(); 					
		}
		else
		{
			echo lang('nothing_to_report'); 
		}
		
		?>	
	</div>
	
	<h3 class="accordion"><?=lang('details')?></h3> 
	<div id="details">
	
	<?php 		
	if(count($chart_order_history) >= '1'):
	?>
		<div id="chart_div"></div>
	    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
	    <script type="text/javascript">
	      google.load("visualization", "1", {packages:["corechart"]});
	      google.setOnLoadCallback(drawChart);
	      function drawChart() {
	        var data = new google.visualization.DataTable();
	        data.addColumn('string', 'Year');
	        data.addColumn('number', 'Totals');
	        data.addColumn('number', 'Subtotals');
	        data.addRows(<?php echo count($chart_order_history);?>);
	        <?php 
	        $i = 0;
	        foreach($chart_order_history AS $date)
	        { 
	        	echo "data.setValue($i, 0, '".m62_convert_timestamp(strtotime($date['order_date']), $settings['graph_date_format'])."');";
	        	echo "data.setValue($i, 1, ".$date['total'].");";
	        	echo "data.setValue($i, 2, ".$date['subtotal'].");";
	        	$i++;
	        }
	        ?>
	
	        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
			var chart_width = document.getElementById("chart_div").offsetWidth+10;
			var area_width = chart_width+20;
			//alert(chart_width);

	        var formatter = new google.visualization.NumberFormat({prefix: '<?php echo $number_prefix; ?>', negativeColor: 'red', negativeParens: true});
	        formatter.format(data, 1);
	        formatter.format(data, 2);	        
	        chart.draw(data, {
	            width: chart_width, 
	            height: 208, 
	            legend:'in', 
	            select: 'myClickHandler',
	            hAxis: {slantedText: true},
	            backgroundColor: 'none',
	            chartArea: {
	            	width: area_width, 
	            	height: "160",
	            	top: 10,
	            	left:30
	            }           
			});
	
	
	        // a click handler which grabs some values then redirects the page
	        google.visualization.events.addListener(chart, 'select', function() {
	          // grab a few details before redirecting
	          var selection = chart.getSelection();
	          var row = selection[0].row;
	          var col = selection[0].column;
	          var date = data.getValue(row, 0);
	          //location.href = '<?php echo html_entity_decode($url_base.'history_report'.AMP); ?>date=' + date;
	        });		
	
	        //google.visualization.events.addListener(chart, 'select', myClickHandler);
	      }
	
	      function myClickHandler(){
	
	    	  var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
	    	  var selection = chart.getSelection();
	
	    	  for (var i = 0; i < selection.length; i++) {
	    	    var item = selection[i];
	    	    if (item.row != null && item.column != null) {
	    	      message += '{row:' + item.row + ',column:' + item.column + '}';
	    	    } else if (item.row != null) {
	    	      message += '{row:' + item.row + '}';
	    	    } else if (item.column != null) {
	    	      message += '{column:' + item.column + '}';
	    	    }
	    	  }
	    	  if (message == '') {
	    	    message = 'nothing';
	    	  }
	    	  alert('You selected ' + message);
	    	}
	    </script>
	<?php 
	else:
	
	?>
	Nothing to report :(
	
	<?php 
	
	endif; 
	
	?>	

	</div>	
	
	<h3 class="accordion"><?=lang('products')?></h3> 
	<div id="product_sum_report">
		<div class="ct_top_nav">
			<div class="ct_nav">
				<span class="button"> 
					<a class="nav_button " href="<?php echo $url_base.'export'.AMP.'type=product_sum_report&month='.$month.AMP.'year='.$year; ?>">Export</a>
				</span>
				<span class="button"> 
					<a class="nav_button" href="javascript:;" id="product_sum_chart_opener">Chart</a>
				</span>					
			</div>
		</div>	<br />		
		<?php 
		if($product_sum_report && count($product_sum_report) != '0')
		{
			// Add Markup into the table 
			$this->table->set_heading(
				lang('product'),
				lang('quantity'),
				lang('unit_price'),
				lang('total')
			);
			foreach($product_sum_report AS $report)
			{
				$data = array(
						'<a href="?'.AMP.'D=cp'.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id='.$report['product_channel_id'].AMP.'entry_id='.$report['entry_id'].'">'.$report['title'].'</a>', 
						$report['total_quantity'], 
						m62_format_money($report['price']), 
						m62_format_money($report['total_quantity']*$report['price'])
				);
				$this->table->add_row($data);
			}
											
			echo $this->table->generate(); 
			// Clear out of the next one 
			$this->table->clear(); 
		}
		else
		{
			echo '<br />'.lang('nothing_to_report');
		}
		?>
		<div id="product_chart" title="<?=lang('product_sum_report')?>">
			<div id="product_chart_div"></div>
		    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
		    <script type="text/javascript">
		      google.load("visualization", "1", {packages:["corechart"]});
		      google.setOnLoadCallback(drawChart);
		      function drawChart() {
		        var data = new google.visualization.DataTable();
		        data.addColumn('string', 'Product/Price');
		        data.addColumn('number', 'Total');
		        data.addRows([
					<?php
					$items = array(); 
					$i = 1;
					$total = count($product_sum_report);
					foreach($product_sum_report AS $report)
					{
						echo "['".$report['title']." (".$report['total_quantity']."x".m62_format_money($report['price']).")',    ".$report['total_quantity']*$report['price']."]";
						if($i != $total)
						{
							echo ",";
						}
						$i++;
					}
					?>
		        ]);

		        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
				var chart_width = 820;				
		        var options = {
		            width: chart_width, 
		            height: 390, 
		            chartArea: { width: 680, height:300, top: 30,left:50},
		            backgroundColor: 'none'		          
		        };
		
		        var chart = new google.visualization.PieChart(document.getElementById('product_chart_div'));

		        var formatter = new google.visualization.NumberFormat({prefix: '<?php echo $number_prefix; ?>', negativeColor: 'red', negativeParens: true});
		        formatter.format(data, 1);
		        			        
		        chart.draw(data, options);
		      }
		    </script>
	    </div>			
	</div>
	
	<h3 class="accordion"><?=lang('orders')?></h3>
	<div id="todays_orders">
	
	<?php 
	if(count($orders) > 0)
	{		
		$this->table->set_template($cp_pad_table_template);
		$this->table->set_heading(
			lang('order_id').'/'.lang('edit'),
			lang('customer_name'),
			lang('status'),
			lang('order_date'),
			lang('total'),
			''
		);
	
		foreach($orders as $order)
		{	
			$customer_link = 'customer_view&email=';
			$packingslip_url = m62_get_invoice_url($order['entry_id'], TRUE);
			$this->table->add_row(
									'<a href="'.$url_base.'order_view'.AMP.'id='.$order['entry_id'].'">'.$order['title'].'</a>',
									'<a href="'.$url_base.'customer_view'.AMP.'email='.$order['email'].'">'.$order['first_name'].' '.$order['last_name'].'</a>',
									'<span style="color:#'.m62_status_color($order['status'], $order_channel_statuses).'">'.lang($order['status']).'</span>',
									m62_convert_timestamp($order['entry_date']),
									$number_format_defaults_prefix.$order['order_total'],
									'<a class="nav_button" href="'.$packingslip_url.'" target="_blank">'.lang('packing_slip').'</a>'
									);
		}
		
		echo $this->table->generate();
		?>
		
		<span class="js_hide"><?php echo $pagination?></span>	
		<span class="pagination" id="filter_pagination"></span>
	
	
	<?php } else { ?>
	<?php echo lang('no_matching_orders')?>
	<?php } ?>
	</div>
	
	<h3 class="accordion"><?=lang('customers')?></h3>
	<div id="history_customers">
	
	<?php 
	if(count($customers) > 0)
	{		
		$this->table->set_heading(
			lang('name'),
			lang('email'),
			lang('sales'),
			lang('orders'),
			lang('last_order')
		);
		
		foreach($customers as $customer)
		{				  
			$this->table->add_row(
									'<a href="'.$url_base.'customer_view'.AMP.'email='.$customer['email'].'">'.$customer['first_name'].' '.$customer['last_name'].'</a>',
									'<a href="mailto:'.$customer['email'].'">'.$customer['email'].'</a>',
									m62_format_money($customer['total_sales']),
									$customer['total_orders'],
									m62_convert_timestamp($customer['last_order'])
								
									);
									//form_checkbox($toggle)
		}
		
		echo $this->table->generate();
	?>
	
	
	<?php } else { ?>
	<?php echo lang('no_matching_customers')?>
	<?php } ?>
	</div>		
		
<?php echo form_close()?>