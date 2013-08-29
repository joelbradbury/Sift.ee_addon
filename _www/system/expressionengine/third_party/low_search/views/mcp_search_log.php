<?php if (empty($log)): ?>

	<p><?=lang('search_log_is_empty')?></p>

<?php else: ?>

	<div class="low-search-log-msg">
		<?php if ($is_admin): ?><a class="submit" href="<?=$base_url?>&amp;method=clear_search_log" style="margin-left:7px"><?=lang('clear_search_log')?></a><?php endif; ?>
		<a class="submit" href="<?=$base_url?>&amp;method=export_search_log"><?=lang('export_search_log')?></a>
		<p><?=$viewing_rows?></p>
	</div>

	<table cellpadding="0" cellspacing="0" style="width:100%" class="mainTable" id="low-search-log">
		<colgroup>
			<col style="width:25%" />
			<col style="width:15%" />
			<col style="width:15%" />
			<col style="width:15%" />
			<col style="width:30%" />
		</colgroup>
		<thead>
			<tr>
				<th scope="col"><?=lang('keywords')?></th>
				<th scope="col"><?=lang('member')?></th>
				<th scope="col"><?=lang('ip_address')?></th>
				<th scope="col"><?=lang('search_date')?></th>
				<th scope="col"><a href="#" onclick="$('.low-show-params').click();return false;"><?=lang('parameters')?></a></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($log AS $row): ?>
				<tr class="<?=low_zebra()?>">
					<td><?=htmlspecialchars($row['keywords'])?></td>
					<td><?=htmlspecialchars($row['member_id'])?></td>
					<td><?=htmlspecialchars($row['ip_address'])?></td>
					<td><?=htmlspecialchars($row['search_date'])?></td>
					<td>
						<?php if ($row['parameters']): ?>
							<a href="#" class="low-show-params"><?=lang('show_parameters')?></a>
							<ul>
								<?php foreach ($row['parameters'] AS $key => $val): ?>
									<li><strong><?=$key?></strong>: <?=htmlspecialchars(is_array($val) ? implode('|', $val) : $val)?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ($pagination !== FALSE): ?>
		<p id="paginationLinks">
			<?=$pagination?>
		</p>
	<?php endif; ?>

<?php endif; ?>