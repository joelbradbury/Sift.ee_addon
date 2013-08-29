<?php if (empty($collections)): ?>

	<p><?=lang('no_collections_exist')?></p>

<?php else: ?>

	<table cellpadding="0" cellspacing="0" class="mainTable" id="low-search-index">
		<colgroup>
			<col style="width:5%" />
			<col style="width:25%" />
			<col style="width:25%" />
			<col style="width:40%" />
			<col style="width:5%" />
		</colgroup>
		<thead>
			<tr>
				<th scope="col">#</th>
				<th scope="col"><?=lang('collection_label')?></th>
				<th scope="col"><?=lang('collection_name')?></th>
				<th scope="col"><?=lang('search_index')?></th>
				<th scope="col"><?=lang('delete')?></th>
			</tr>
		</thead>
		<!--
			<tfoot>
				<tr>
					<td colspan="3"></td>
					<td><a href="#" id="build-all-indexes">Build all indexes</a></td>
					<td></td>
				</tr>
			</tfoot>
		-->
		<tbody>
			<?php foreach ($collections AS $row): ?>
				<tr class="<?=low_zebra()?>">
					<td><?=$row['collection_id']?></td>
					<td><a href="<?=$base_url?>&amp;method=edit_collection&amp;collection_id=<?=$row['collection_id']?>" title="<?=lang('edit_preferences')?>"><?=htmlspecialchars($row['collection_label'])?></a></td>
					<td><?=htmlspecialchars($row['collection_name'])?></td>
					<td class="low-index ready">
						<div class="index-options">
							<a href="<?=$row['index_url']?>"><?=lang(($row['index_status'] == 'empty') ? 'build_index' : 'rebuild_index')?></a>
						</div>
						<div class="index-progress">
							<span class="index-progress-bar">
								0 / <?=$totals[$row['channel_id']]?>
							</span>
						</div>
						<?php if ($row['index_status'] != 'ok'): ?>
							<div class="index-status">
								<em><?=lang('index_status_'.$row['index_status'])?></em>
							</div>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?=$base_url?>&amp;method=delete_collection_confirm&amp;collection_id=<?=$row['collection_id']?>">
							<img src="<?=$themes_url?>cp_themes/default/images/icon-delete.png" alt="<?=lang('delete')?>" />
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>