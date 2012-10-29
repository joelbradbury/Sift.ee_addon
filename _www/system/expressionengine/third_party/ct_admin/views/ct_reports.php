<?php 
$this->load->view('errors'); 
?>
<?php echo form_open($query_base.'reports', array('id'=>'my_accordion'))?> 
<?php
$this->table->set_template($cp_table_template); 
$this->table->set_empty("&nbsp;"); 
?> 
<div> 
	<h3 class="accordion"><?=lang('summary_report')?></h3> 	
	<div> 	
		<?php 
		if($current_year_total > '0')
		{
			$this->table->set_heading(
				lang('current_day_total'),
				lang('current_month_total'),
				lang('current_year_total'),
				lang('total_sales')
			);
			$data = array(
					m62_format_money($current_day_total), 
					m62_format_money($current_month_total), 
					m62_format_money($current_year_total),
					m62_format_money($total_sales)
			);
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
	
	<h3 class="accordion"><?=lang('product_sum_report')?></h3> 
		<div id="product_sum_report"> 
			<div class="ct_top_nav">
				<div class="ct_nav">
					<span class="button"> 
						<a class="nav_button " href="<?php echo $url_base.'export'.AMP.'type=product_sum_report'; ?>">Export</a>
					</span>
					<span class="button"> 
						<a class="nav_button" href="javascript:;" id="product_sum_chart_opener">Chart</a>
					</span>						
				</div>
			</div> <br />		
		<?php 
		if($product_sum_report && count($product_sum_report) != '0')
		{
			?>			
			<?php 
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
						echo "['".addslashes($report['title'])." (".$report['total_quantity']."x".m62_format_money($report['price']).")',    ".$report['total_quantity']*$report['price']."]";
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
		            title: 'Revenue',
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
	
	<h3 class="accordion"><?php echo lang('monthly_history_report')?></h3> 
	<div id="monthly_history_report"> 
		<div class="ct_top_nav">
			<div class="ct_nav">
				<span class="button"> 
					<a class="nav_button " href="<?php echo $url_base.'export'.AMP.'type=monthly_history_report'; ?>">Export</a>
				</span>
				<span class="button"> 
					<a class="nav_button" href="javascript:;" id="monthly_chart_opener">Chart</a>
				</span>				
				
				
			</div>
		</div><br />	
		<?php 
		
		if(is_array($all_totals) && count($all_totals) >= 1)
		{
			$this->table->set_heading(
				lang('date'),
				lang('tax'),
				lang('shipping'),
				lang('discount'),
				lang('total')
			);
			
			foreach($all_totals AS $report)
			{
			$data = array(
					'<!-- '.$report['entry_date'].'--><a href="'.$url_base.'history_report&month='.date('m', $report['entry_date']).'&year='.date('Y', $report['entry_date']).'">'.$report['name'].'</a>', 
					m62_format_money($report['tax']), 
					m62_format_money($report['shipping']), 
					m62_format_money($report['discount']), 
					m62_format_money($report['total'])
			);
			$this->table->add_row($data);				 
			}
			echo $this->table->generate();
			$this->table->clear(); 			
		}
		else
		{
			echo '<br />'.lang('nothing_to_report');
		}
		?>
		<div id="monthly_history_report_chart" title="<?php echo lang('monthly_history_report')?>">
			
		<div id="chart_div"></div>
	    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
	    <script type="text/javascript">
	      google.load("visualization", "1", {packages:["corechart"]});
	      google.setOnLoadCallback(drawChart);
	      function drawChart() {
	        var data = new google.visualization.DataTable();
	        data.addColumn('string', 'Year');
	        data.addColumn('number', 'Totals');
	        data.addColumn('number', 'Discount');
	        data.addColumn('number', 'Tax');
	        data.addColumn('number', 'Shipping');
	        data.addRows(<?php echo count($all_totals);?>);
	        <?php 
	        $i = 0;
	        $totals = array_reverse($all_totals, TRUE);
	        foreach($totals AS $report)
	        { 
	        	echo "data.setValue($i, 0, '".m62_convert_timestamp(strtotime($report['name']), "%M %Y")."');";
	        	echo "data.setValue($i, 1, ".$report['total'].");";
	        	echo "data.setValue($i, 2, ".$report['discount'].");";
	        	echo "data.setValue($i, 3, ".$report['tax'].");";
	        	echo "data.setValue($i, 4, ".$report['shipping'].");";
	        	$i++;
	        }
	        ?>
	
	        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
			var chart_width = 820;
			//alert(chart_width);

	        var options = {
		            width: chart_width, 
		            height: 390, 
		            backgroundColor: 'none',
		            hAxis: {slantedText: true},
		            legend: { position: 'right' },
		            chartArea: { width: 680, height:300, top: 30,left:50}
		    };

	        var formatter = new google.visualization.NumberFormat({prefix: '<?php echo $number_prefix; ?>', negativeColor: 'red', negativeParens: true});
	        formatter.format(data, 1);
	        formatter.format(data, 2);		    
	        chart.draw(data, options);
	
	
	      }
	    </script>		
		</div>	
	</div>
	
	<?php if(isset($enable_low_stock_report)): ?>
	<h3 class="accordion"><?=lang('low_stock_report')?></h3> 
	<div id="low_stock_report"> 
		<div class="ct_top_nav">
			<div class="ct_nav">
				<span class="button"> 
					<a class="nav_button " href="<?php echo $url_base.'export'.AMP.'type=low_stock_report'; ?>">Export</a>
				</span>
			</div>
		</div>	<br />
		<?php 
		if($low_stock_report && count($low_stock_report) != '0')
		{
			// Add Markup into the table 
			$this->table->set_heading(
				lang('product'),
				lang('quantity')
			);
			foreach($low_stock_report AS $channel_report)
			{
				foreach($channel_report AS $report)
				{
					$url = '?'.AMP.'D=cp'.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id=80'.AMP.'entry_id='.$report['entry_id'];
					//first check if we're dealing with a custom field.
					if(is_numeric($report['inventory'])) 
					{
						$data = array(
								'<a href="?'.$url.'">'.$report['title'].'</a>', 
								$report['inventory']
						);
						$this->table->add_row($data);					
					}
					else
					{
						$options = unserialize(base64_decode($report['inventory']));
						foreach($options AS $option)
						{
							$data = array(
									'<a href="?'.$url.'">'.$report['title'].' ('.$option['option_name'].')</a>', 
									$option['inventory']
							);
							$this->table->add_row($data);							
						}
					}
				}
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
	</div>
	<?php endif; ?>
	
	<?php if($country_data): ?>
	<h3 class="accordion"><?php echo lang('order_country_report')?></h3> 
	<div id="country_report"> 

	<div id='location_chart'></div>
	<script type='text/javascript' src='https://www.google.com/jsapi'></script>
	
	<script type='text/javascript'>
	//var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
	var chart_width = document.getElementById("location_chart").offsetWidth-30;
	var area_width = chart_width+20;  
	google.load('visualization', '1', {'packages': ['geochart']});
	google.setOnLoadCallback(drawRegionsMap);

	function drawRegionsMap() {
		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Country');
		data.addColumn('number', 'Popularity');
		data.addRows([
		<?php
		$arr = array(); 
		foreach($country_data AS $key => $value)
		{
			$arr[] = "['".m62_country_code($key)."', ".$value."]";
		}
		
		echo implode(',', $arr);
		?>
		]);

		var options = {width:chart_width, height:'500', backgroundColor:'none'};
		var chart = new google.visualization.GeoChart(document.getElementById('location_chart'));
		chart.draw(data, options);
	}	
	</script>
	</div>
	<?php endif; ?>	
</div>		
<?php echo form_close()?>