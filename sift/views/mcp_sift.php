

<div id="shortlist_container" class="mor">

	

	<div class="tg">

		<table class="mainTable padTable" border="0" cellspacing="0" cellpadding="0">
			<thead>
				<tr class="even">
					<th class="header" colspan="2">Caches</th>
				</tr>
			</thead>
			<tbody>
				<tr class="even">
					<td>Sift caches search data for performance.<br/><br/>Caches have a lifetime of 24 hours, but you can manually clear them here.</td>
					<td>
						<form method="post" action="<?=$clear_cache_form_uri?>">
							<input type="hidden" name="XID" value="<?=XID_SECURE_HASH?>"/>
							<input type="submit" name="clear_sift_caches" value="Clear Sift Caches" class="submit"/>
						</form>
					</td>
				</tr>
			</tbody>
		</table>

		
	</div>


</div>