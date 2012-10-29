<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once PATH_THIRD.'cartthrob/config.php';
require_once PATH_THIRD.'cartthrob/fieldtypes/ft.cartthrob_matrix.php';

/**
 * @property CI_Controller $EE
 * @property Cartthrob_core_ee $cartthrob;
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */
class Cartthrob_package_ft extends Cartthrob_matrix_ft
{
	public $info = array(
		'name' => 'CartThrob Package',
		'version' => CARTTHROB_VERSION,
	);
	
	public $default_row = array(
		'entry_id' => 0,
		'title' => 0,
		'description' => '',
		'option_presets' => '',
		'allow_selection' => '',
	);
	
	public $buttons = array();
	
	public $show_default_row = FALSE;
	
	//public $hidden_columns = array();
	
	//public $additional_controls = '';
	
	//public $variable_prefix = '';
	
	//public $row_nomenclature = '';
	
	public function pre_process($data)
	{
		//unserializes it
		$data = parent::pre_process($data);
		
		$this->EE->load->library('cartthrob_loader');
		
		$this->EE->load->library('data_filter');
		
		$this->EE->load->model('cartthrob_entries_model');
		
		//get the entry_ids from the array
		$entry_ids = $this->EE->data_filter->key_values($data, 'entry_id');
		
		//preload all the entries pertaining to this package
		$this->EE->cartthrob_entries_model->load_entries_by_entry_id($entry_ids);
		
		return $data;
	}
	
	public function display_field($data, $replace_tag = FALSE)
	{
		if ( ! isset($this->EE->session->cache['cartthrob_package']['head']))
		{
			$this->EE->session->cache['cartthrob_package']['head'] = TRUE;
			
			$this->EE->cp->add_to_head('
			<style type="text/css">
			.cartthrobPackageFilter {
				border: 1px solid #D0D7DF;
				border-top-width: 0;
				padding: 15px;
			}
			table.cartthrobPackage th:nth-child(2), table.cartthrobPackage th:nth-child(5), table.cartthrobPackage th:nth-child(6) {
				width: 1%;
			}
			table.cartthrobPackage td:nth-child(2) {
				text-align: center;
			}
			table.cartthrobPackage td:nth-child(2), table.cartthrobPackage td:nth-child(5), table.cartthrobPackage td:nth-child(6) {
				padding-right: 10px;
			}
			table.cartthrobPackageOptionPresets {
				table-layout: fixed;
				margin: 0 auto;
				border-collapse: collapse;
			}
			ul.cartthrobPackageFilteredEntries {
				background-color: white;
				list-style: none;
				margin: 10px 0 0;
				padding: 0;
				text-indent: 0;
				border: 1px solid #D0D7DF;
				height: 240px; /* 8 li */
				overflow: auto;
				overflow-x: none;
				overflow-y: scroll;
			}
			ul.cartthrobPackageFilteredEntries li {
				background-color: white;
				cursor: pointer;
				padding: 8px;
				height: 14px;
			}
			ul.cartthrobPackageFilteredEntries li:hover {
				background-color:#CCE6FF;
			}
			table.cartthrobMatrix table.cartthrobPackageOptionPresets td {
				border: 0 !important;
				padding: 0 !important;
				height: 28px;
				overflow: hidden;
				white-space: nowrap;
			}
			table.cartthrobMatrix table.cartthrobPackageOptionPresets td label {
				padding-right: 15px;
			}
			</style>
			');
			
			$this->EE->lang->loadfile('cartthrob');
			
			$this->EE->load->library('javascript');
			
			$this->EE->cp->add_js_script(array('ui' => array('autocomplete')));
			
			$this->EE->javascript->output('
			$(".cartthrobPackageFilter").parent().css("marginTop", 0);
			EE.cartthrobPackage = {
				filteredEntries: {},
				getFilters: function(package, exclude_keywords){
					var filter = {};
					var selector = ":input";
					if (exclude_keywords === true) {
						selector += ":not(.keywords)";
					}
					$(package).next(".cartthrobMatrixControls").find(".cartthrobPackageFilter").children(selector).each(function(){
						filter[$(this).attr("class")] = $(this).val();
					});
					return filter;
				},
				showFilteredEntries: function(package) {
					var filter = {
						XID: EE.XID,
						C: "addons_modules",
						M: "show_module_cp",
						module: "cartthrob",
						method: "package_filter"
					};
					$.extend(filter, EE.cartthrobPackage.getFilters(package));
					var list = $(package).next(".cartthrobMatrixControls").find(".cartthrobPackageFilteredEntries");
					var color = list.css("color");
					list.children("li").animate({color: "#999"}, 100);
					$.getJSON(EE.BASE, filter, function(data) {
						list.html("");
						$.each(data, function(i, entry){
							EE.cartthrobPackage.filteredEntries[entry.entry_id] = entry;
							list.append($("<li />", {text: entry.title+" (id: "+entry.entry_id+")", rel: entry.entry_id, "class": "entry"}).css({color: "#999"}));
						});
						if (data.length === 0) {
							list.append($("<li />", '.$this->EE->javascript->generate_json(array('text' => $this->EE->lang->line('no_products_in_search'))).'));
						}
						list.children("li").animate({color: color}, 100);
					});
				},
				loadEntry: function(entryID, package){
					var entry = EE.cartthrobPackage.filteredEntries[entryID];
					var row = $.cartthrobMatrix.addRow(package);
					row.find(".title").html(entry.title);
					row.find(".entry_id:not(:input)").html(entry.entry_id);
					row.find(".entry_id:input").val(entry.entry_id);
					var fieldName = row.find(".entry_id:input").attr("name").replace("entry_id", "NAME");
					var optionPresets = "<table border=\'0\' cellpadding=\'0\' cellspacing=\'0\' class=\'cartthrobPackageOptionPresets\'><tbody>";
					var allowSelection = optionPresets;
					$.each(entry.price_modifiers, function(priceModifier, data){
						var options = $.extend({}, data);
						var label = options.label;
						delete options.label;
						if ($.isEmptyObject(options)) {
							return;
						}
						allowSelection += "<tr><td><input type=\'checkbox\' value=\'1\' name=\'"+fieldName.replace("NAME", "allow_selection")+"["+priceModifier+"]\'></td></tr>";
						optionPresets += "<tr><td><label>"+label+"</label></td><td><select name=\'"+fieldName.replace("NAME", "option_presets")+"["+priceModifier+"]\'>";
						optionPresets += "<option>-----</option>";
						$.each(options, function(i, option){
							optionPresets += "<option value=\'"+option.option_value+"\'>"+option.option_name+"</option>";
						});
						optionPresets += "</select></td></tr>";
					});
					optionPresets += "</tbody></table>";
					allowSelection += "</tbody></table>";
					row.children("td:eq(4)").html(optionPresets);
					row.children("td:eq(5)").html(allowSelection);
				}
			};
			$(".cartthrobPackageFilter :input").bind("change", function(event){
				EE.cartthrobPackage.showFilteredEntries($(event.target).parents(".cartthrobMatrixControls").prev("table.cartthrobPackage"));
			}).bind("keypress", function(event){
				if (event.keyCode === 13) {
					return false;
				}
			});
			$(".cartthrobPackageFilter input.keywords").bind("keyup", function(event){
				EE.cartthrobPackage.showFilteredEntries($(event.target).parents(".cartthrobMatrixControls").prev("table.cartthrobPackage"));
			});
			$(".cartthrobPackageFilteredEntries li.entry").live("click", function(event){
				EE.cartthrobPackage.loadEntry($(event.target).attr("rel"), $(event.target).parents(".cartthrobMatrixControls").prev("table.cartthrobPackage"));
			});
			
			//call it on load
			EE.cartthrobPackage.showFilteredEntries($("table.cartthrobPackage"));
			');
		}
		
		$data = $this->pre_process($data);
		
		$vars['categories'] = array('' => lang('filter_by_category'));
		
		$channel_ids = $this->EE->cartthrob->store->config('product_channels');
		
		if ( ! $channel_ids)
		{
			$vars['channels'] = array('X' => lang('no_product_channels'));
		}
		else
		{
			$vars['channels'] = array();
			
			$vars['channels']['null'] = lang('filter_by_channel');
	
			/*
			if (count($channel_ids) > 1)
			{
				$vars['categories']['all'] = $vars['channels']['all'] = lang('all');
			}
			*/
			
			$vars['categories']['none'] = lang('none');
			
			$this->EE->load->model('channel_model');
			
			$query = $this->EE->channel_model->get_channels(NULL, array('channel_id', 'channel_title', 'cat_group'), array(array('channel_id' => $channel_ids)));
			
			$used_cat_groups = array();
	
			foreach ($query->result() as $row)
			{
				$vars['channels'][$row->channel_id] = $row->channel_title;
				
				if ($row->cat_group)
				{
					$this->EE->load->model('category_model');
					
					$cat_groups = explode('|', $row->cat_group);
					
					foreach ($cat_groups as $group_id)
					{
						if (in_array($group_id, $used_cat_groups))
						{
							continue;
						}
						
						$used_cat_groups[] = $group_id;
						
						$categories = $this->EE->category_model->get_channel_categories($group_id);
						
						if ($categories->num_rows() > 0)
						{
							$vars['categories']['NULL_1'] = '-------';
							
							foreach($categories->result() as $row)
							{
								$vars['categories'][$row->cat_id] = $row->cat_name;
							}
						}
					}
				}
			}
		}
		
		if (version_compare(APP_VER, '2.2', '<'))
		{
			$orig_view_path = $this->EE->load->_ci_view_path;
			
			$this->EE->load->_ci_view_path = PATH_THIRD.'cartthrob/views/';
			
			$this->additional_controls = $this->EE->load->view('cartthrob_package_filter', $vars, TRUE);
			
			$this->EE->load->_ci_view_path = $orig_view_path;
		}
		else
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
			
			$this->additional_controls = $this->EE->load->view('cartthrob_package_filter', $vars, TRUE);
		}
		
		$output = parent::display_field($data, $replace_tag);
		
		return $output;
	}
	
	public function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
		$this->EE->load->model(array('product_model', 'cartthrob_entries_model'));
		
		foreach ($data as $row_id => $row)
		{
			if (isset($row['entry_id']) && $product = $this->EE->product_model->get_product($row['entry_id']))
			{
				if (!empty($row['option_presets']) && !isset($row['allow_selection']))
				{
					$row['allow_selection'] = array(); 
				}
				
				$row['parent_id'] = '';
				$row['row_id'] = ':'.$row_id;
				$row['child_id'] = $row_id;
				
				$data[$row_id] = array_merge($row, $this->EE->cartthrob_entries_model->entry_vars($product, $tagdata, 'sub:'));
			}
		}
		
		return parent::replace_tag($data, $params, $tagdata);
	}
	
	public function display_field_entry_id($name, $value, $row, $index, $blank = FALSE)
	{
		$output = '';
		
		//$output .= '<strong class="title">'.element('title', $product).'</strong>'.NBS.'(id: <span class="entry_id">'.$value.'</span>)';//.NBS.NBS.NBS.anchor('#', 'change &raquo;');
		$output .= '<span class="entry_id">'.$value.'</span>';
		
		$attributes = array(
			'type' => 'hidden',
			'name' => $name,
			'value' => $value,
			'class' => 'entry_id',
		);
		
		if ($blank)
		{
			$attributes['disabled'] = 'disabled';
		}
		
		$output .= '<input '._parse_attributes($attributes).'>';
		
		return $output;
	}
	
	public function display_field_title($name, $value, $row, $index, $blank = FALSE)
	{
		$title = '';
		
		if ( ! empty($row['entry_id']))
		{
			$this->EE->load->model('product_model');
			
			$this->EE->load->helper(array('array', 'html'));
			
			$product = $this->EE->product_model->get_product($row['entry_id']);
			
			$title = element('title', $product);
			
			if ($product)
			{
				$title = anchor(BASE.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'entry_id='.$row['entry_id'], $title, array('target' => '_blank'));
			}
		}
		
		return '<strong class="title">'.$title.'</strong>';
	}
	
	public function display_field_option_presets($name, $value, $row, $index, $blank = FALSE)
	{
		if (empty($row['entry_id']))
		{
			return NBS;
		}
		
		$ol = array();
		
		$this->EE->load->model('product_model');
		
		$this->EE->load->helper('array');
		
		$price_modifiers = $this->EE->product_model->get_all_price_modifiers($row['entry_id']);
		
		if ( ! $price_modifiers)
		{
			return NBS;
		}
		
		$this->EE->load->model('cartthrob_field_model');
		
		$this->EE->load->library('table');
		
		//i already know the table lib is loaded
		$table = new CI_Table();
		
		$table->set_template(array('table_open' => '<table border="0" cellpadding="0" cellspacing="0" class="cartthrobPackageOptionPresets">'));
		
		foreach ($price_modifiers as $field_name => $options)
		{
			if (count($options) === 0)
			{
				continue;
			}
			
			$select_options = array('' => '-----');
			
			foreach ($options as $option)
			{
				$select_options[$option['option_value']] = $option['option_name'];
			}
			
			$label = $this->EE->cartthrob_field_model->get_field_label($this->EE->cartthrob_field_model->get_field_id($field_name));
			
			$input_name = $name.'['.$field_name.']';
			
			$attributes = array('id' => $input_name);
			
			if ($blank)
			{
				$attributes['disabled'] = 'disabled';
			}
			
			$table->add_row(form_label($label, $input_name), form_dropdown($input_name, $select_options, element($field_name, $value), _parse_attributes($attributes)));
		}
		
		return ($table->rows) ? $table->generate() : NBS;
	}
	
	public function display_field_allow_selection($name, $value, $row, $index, $blank = FALSE)
	{
		if (empty($row['entry_id']))
		{
			return NBS;
		}
		
		$ol = array();
		
		$this->EE->load->model('product_model');
		
		$this->EE->load->helper('array');
		
		$price_modifiers = $this->EE->product_model->get_all_price_modifiers($row['entry_id']);
		
		if ( ! $price_modifiers)
		{
			return NBS;
		}
		
		$table = new CI_Table();
		
		$table->set_template(array('table_open' => '<table border="0" cellpadding="0" cellspacing="0" class="cartthrobPackageOptionPresets">'));
		
		foreach ($price_modifiers as $field_name => $options)
		{
			if (count($options) === 0)
			{
				continue;
			}
			
			$extra = ($blank) ? 'disabled="disabled"' : '';
			
			$table->add_row(form_checkbox($name.'['.$field_name.']', '1', element($field_name, $value), $extra));
		}
		
		return ($table->rows) ? $table->generate() : NBS;
	}
}

/* End of file ft.cartthrob_discount.php */
/* Location: ./system/expressionengine/third_party/cartthrob_discount/ft.cartthrob_discount.php */