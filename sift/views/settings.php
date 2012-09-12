
<div id="drivebox_settings">



	<form method="post" action="<?=$form_post_url?>">

	<input type="hidden" name="XID" value="<?=XID_SECURE_HASH?>"/>


	<table id="preferences" class="mainTable padTable" style="width:100%;" cellspacing="0" cellpadding="0" border="0">
		<thead>
			<tr>
				<th style="width:40%; text-align:left;">Channels</th>
				<th style="width:60%; text-align:left;"></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>Sift Account</td>
				<td>
					<input type="text" name="account_email" id="account_email" placeholder="eg. bill@sift.com"/>
				</td>
			</tr>
			<tr>
				<td>Currency</td>
				<td>	
					<input type="text" name="currency" id="currency" placeholder="USD"/>
				</td>
			</tr>
				
			<tr>
				<td>Success Return Url</td>
				<td>	
					<input type="text" name="return_success" id="return_success" placeholder="/payment/success"/>
				</td>
			</tr>	

			<tr>
				<td>Cancel Return Url</td>
				<td>	
					<input type="text" name="return_failure" id="return_failure" placeholder="/payment/failure"/>
				</td>
			</tr>
			
			<tr>
				<td>Debug</td>
				<td>	
					<input type="text" name="debug_mode" id="debug_mode" placeholder="TRUE"/>
				</td>
			</tr>	
				
			
	
		</tbody>	
	</table>

	<input type="submit" class="submit" name="submit" value="Save" />
	</form>

</div>
