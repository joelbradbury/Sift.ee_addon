<?php $this->EE =& get_instance(); $i = 1;?>
<table class="analytics-panel analytics-reports" cellspacing="0">
	<tr>
		<th class="top-left">URL</th>
		<th class="top-right"><?=$this->EE->lang->line('analytics_views')?></th>
	</tr>
	<?php foreach($lastmonth['content'] as $result): ?>
	<tr>
		<td class="analytics-top-content-row<? if($i == count($lastmonth['content'])) echo(" bottom-left cap"); ?>"><?=$result['title']?></td>
		<td class="analytics-count<? if($i == count($lastmonth['content'])) echo(" bottom-right cap"); ?>"><?=number_format($result['count'])?></td>
	</tr>
	<?php $i++; endforeach;?>
</table>
