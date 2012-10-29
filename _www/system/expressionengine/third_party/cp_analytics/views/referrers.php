<?php $this->EE =& get_instance(); $i = 1; ?>

<table class="analytics-panel analytics-reports" cellspacing="0">
	<tr>
		<th class="top-left"><?= $this->EE->lang->line('analytics_referrer')?></th>
		<th class="top-right"><?= $this->EE->lang->line('analytics_visits')?></th>			
	</tr>
<?php foreach($lastmonth['referrers'] as $result): ?>
	<tr>
		<td class="analytics-top-referrer-row<? if($i == count($lastmonth['referrers'])) echo(" bottom-left cap"); ?>"><?=$result['title']?></td>
		<td class="analytics-count<? if($i == count($lastmonth['referrers'])) echo(" bottom-right cap"); ?>"><?=$result['count']?></td>
	</tr>
<?php $i++; endforeach; ?>
</table>	