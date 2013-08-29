/**
 * Low Search JS file
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
(function($){
$(function(){

	// Language line
	var lang = function(str) {
		return (typeof $.LOW_lang[str] == 'undefined') ? str : $.LOW_lang[str];
	};

	// Create re-usable throbber element
	var $throbber = $('#filter_ajax_indicator');
	$throbber.on  = function() { $(this).css('visibility','visible'); }
	$throbber.off = function() { $(this).css('visibility','hidden');  }

	// (Re)build of search index
	var Index = function(cell) {

		var self  = this,
			$cell = $(cell),
			$link = $cell.find('a'),
			url   = $link.attr('href'),
			$bar  = $cell.find('.index-progress-bar');

		var build = function() {
			$.ajax({
				url: url,
				success: function(data, status) {
					url = url.replace(/start=\d+/, 'start='+data.start);
					var new_width = (data.status == 'done') ? 'auto' : (data.start / data.total_entries * 100) + '%';
					var new_text  = (data.status == 'building') ? data.start + ' / ' + data.total_entries : lang(data.status);
					$cell.addClass(data.status);
					$bar.css('width', new_width).text(new_text);
					(data.status == 'done') ? $throbber.off() : build();
				},
				dataType: 'json'
			});
		};

		$link.click(function(event){
			event.preventDefault();
			// Remove tick, display feedback message
			$cell.removeClass('ready').addClass('loading');
			// Enable throbber
			$throbber.on();
			// Call function
			build();
		});

	};

	$('td.low-index').each(function(){ new Index(this); });

	// @TODO: Chain this!
	// $('#build-all-indexes').click(function(e){
	// 	$('td.low-index a').click();
	// 	// Cancel event
	// 	e.preventDefault();
	// });

	// Show search fields in Edit Collection screen
	var show_fields = function() {
		var val = $('#collection_channel').val();
		$('.low-search-field-group').addClass('hidden');
		if (val) {
			$('#low-search-field-group-'+val).removeClass('hidden');
			if ( ! $('#collection_id').val()) {
				$('#collection_label').val(EE.low_search_channels[val].channel_title);
				$('#collection_name').val(EE.low_search_channels[val].channel_name);
			}
		}
	};

	show_fields();
	$('#collection_channel').change(show_fields);

	// Show search params in Search Log
	$('.low-show-params').click(function(e){
		e.preventDefault();
		$(this).parent().toggleClass('open');
	});

	// ------------------------------------------
	// Find & Replace functions
	// ------------------------------------------

	// Tabs
	$('#low-tabs a').click(function(event){
		event.preventDefault();
		$('#low-tabs li').removeClass('active');
		$('fieldset.tab').removeClass('active');
		$(this).parent().addClass('active');
		$($(this).attr('href')).addClass('active');
	});

	// Remember preview element
	var $preview  = $('#low-preview');

	// Get dialog element
	var $dialog   = $('#low-dialog');

	// Channel / field selection options
	$('#low-filters fieldset').each(function(){

		// Define local variables
		var $self      = $(this),
			$sections  = $self.find('div.low-boxes'),
			$allBoxes  = $self.find('input[name]'),
			$selectAll = $self.find('input.low-select-all');

		// Define channel object: to (de)select all fields that belong to the channel
		var Section = function(el) {
			var $el     = $(el),
				$boxes  = $el.find(':checkbox'),
				$toggle = $el.find('h4 span');

			// Add toggle function to channel header
			$toggle.click(function(event){
				event.preventDefault();
				var $unchecked = $el.find('input:not(:checked)');

				if ($unchecked.length) {
					$unchecked.attr('checked', true);
				} else {
					$boxes.attr('checked', false);
				}
			});
		};

		// Init channel object per one channel found in main element
		$sections.each(function(){
			new Section(this);
		});

		// Enable the (de)select all checkbox
		$selectAll.change(function(){
			var check = ($selectAll.attr('checked') ? true : false);
			$allBoxes.attr('checked', check);
		});
	});


	// Show preview of find & replace action
	$('#low-find-replace').submit(function(event){

		// Don't believe the hype!
		event.preventDefault();

		// Set local variables
		var $form = $(this),
			$keywords = $('#low-keywords');

		// Validate keywords
		if ( ! $keywords.val()) {
			$.ee_notice(lang('no_keywords_given'),{type:"error",open:true});
			return;
		}

		// Validate field selection
		if ( ! $('#low-channel-fields :checked').length) {
			$.ee_notice(lang('no_fields_selected'),{type:"error",open:true});
			return;
		}

		// Turn on throbber, empty out preview
		$.ee_notice.destroy();
		$throbber.on();
		$preview.html('');

		// Submit form via Ajax, show result in Preview
		$.post(
			this.action,
			$(this).serialize(),
			function(data){
				$throbber.off();
				$preview.html(data);
		});
	});

	// (de)select all checkboxes in preview table
	$preview.delegate('#low-select-all', 'change', function(){
		var $tbody = $(this).parents('table').find('tbody');
		$tbody.find(':checkbox').attr('checked', this.checked);
	});

	// Form submission after previewing
	$preview.delegate('#low-previewed-entries', 'submit', function(event){

		// Don't believe the hype!
		event.preventDefault();

		// Set local vars
		var $form = $(this);

		// Validate checked entries, destroy notice if okay
		if ( ! $form.find('tbody :checked').length) {
			$.ee_notice(lang('no_entries_selected'),{type:"alert",open:true});
			return;
		}

		// Turn throbber on, show message in preview
		$.ee_notice.destroy();
		$throbber.on();
		$preview.html(lang('working'));

		// Submit form via Ajax, show result in Preview
		$.post(
			this.action,
			$form.serialize(),
			function(data){
				$throbber.off();
				$preview.html(data);
		});
	});

	// Replace log: open details in dialog
	$('.low-show-dialog').click(function(event){

		// Don't follow the link
		event.preventDefault();

		// Enable throbber
		$throbber.on();

		// Load details via Ajax, then show in dialog
		$dialog.load(this.href, function(){
			$throbber.off();
			$dialog.dialog({
				modal: true,
				title: $('#breadCrumb .last').text(),
				width: '50%'
			});
		});
	});

	// Toggle hilite title settings
	$('#excerpt_hilite').change(function(){
		var method = $(this).val() ? 'slideDown' : 'slideUp';
		$('#title_hilite')[method](150);
	});

});
})(jQuery);