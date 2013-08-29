<form method="post" action="<?=$base_url?>&amp;method=delete_collection">
	<div>
		<input type="hidden" name="collection_id" value="<?=$collection_id?>" />
		<input type="hidden" name="XID" value="<?=XID_SECURE_HASH?>" />
	</div>
	<p>
		<?=sprintf(lang('delete_collection_confirm_message'), $collection_label)?>
	</p>
	<p>
		<input type="submit" class="submit" value="<?=lang('delete_collection_confirm')?> &ldquo;<?=htmlspecialchars($collection_label)?>&rdquo;" />
		<a style="margin-left:20px" class="cancel" href="<?=$base_url?>"><?=lang('cancel_go_back')?></a>
	</p>
</form>