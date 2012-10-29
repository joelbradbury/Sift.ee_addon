<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once PATH_THIRD.'cartthrob/config.php';

class Cartthrob_discount_ft extends EE_Fieldtype
{
	public $info = array(
		'name' => 'CartThrob Discount Settings',
		'version' => CARTTHROB_VERSION,
	);
	
	public function __construct()
	{
		parent::EE_Fieldtype();
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
	}
	
	/**
	 * Display Field on Publish
	 *
	 * @access	public
	 * @param	$data
	 * @return	field html
	 *
	 */
	public function display_field($data)
	{
		$this->EE->load->helper('data_formatting');
		
		$this->data = _unserialize($data, TRUE);
		
		$settings = array();
		
		$options = array();
		
		$this->EE->load->library('api/api_cartthrob_discount_plugins');
		
		foreach ($this->EE->api_cartthrob_discount_plugins->get_plugins() as $type => $plugin)
		{
			$options[$type] = lang($plugin['title']);
			
			foreach ($plugin['settings'] as $setting)
			{
				$settings[] = $this->setting_metadata($setting, $type);
			}
		}
		
		foreach ($this->EE->api_cartthrob_discount_plugins->global_settings() as $setting)
		{
			$settings[] = $this->setting_metadata($setting);
		}
		
		array_unshift($settings, $this->setting_metadata(array(
			'type' => 'select',
			'name' => 'Type',
			'short_name' => 'type',
			'extra' => ' class="cartthrob_discount_plugin"',
			'options' => $options
		)));
		
		if (empty($this->EE->session->cache['cartthrob_discount']['display_field']))
		{
			$this->EE->load->library('javascript');
			
			$this->EE->javascript->output('
				$(".cartthrob_discount_plugin").bind("change", function() {
					$(this).parents("table").eq(0).find("tbody tr").not(".global").hide().find(":input").attr("disabled", true);
					$(this).parents("table").eq(0).find("tbody tr."+$(this).val()).show().find(":input").attr("disabled", false);
				}).change();
			');
			
			if (REQ != 'CP')
			{
				$this->EE->cp->add_to_head('<link rel="stylesheet" href="'.URL_THIRD_THEMES.'cartthrob/css/cartthrob_matrix_table.css" type="text/css" media="screen" />');
			}
			
			$this->EE->session->cache['cartthrob_discount']['display_field'] = TRUE;
		}
		
		if (version_compare(APP_VER, '2.2', '<'))
		{
			$orig_view_path = $this->EE->load->_ci_view_path;
			
			$this->EE->load->_ci_view_path = PATH_THIRD.'cartthrob/views/';
			
			$output = $this->EE->load->view('cartthrob_discount_display_field', array('settings' => $settings), TRUE);
			
			$this->EE->load->_ci_view_path = $orig_view_path;
		}
		else
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
			
			$output = $this->EE->load->view('cartthrob_discount_display_field', array('settings' => $settings), TRUE);
		}
		
		return $output;
	}
	
	private function setting_metadata($setting, $plugin_type = 'global')
	{
		//retrieve the current set value of the field
		$current_value = (isset($this->data[$setting['short_name']])) ? $this->data[$setting['short_name']] : NULL;
		
		//set the value to the default value if there is no set value and the default value is defined
		$current_value = ($current_value === NULL && isset($setting['default'])) ? $setting['default'] : $current_value;
		
		$setting['current_value'] = $current_value;
		
		switch ($setting['type'])
		{
			case 'text':
				$input_data = array('name' => $this->field_name.'['.$setting['short_name'].']', 'value' => $current_value);
				if (isset($setting['size']))
				{
					$input_data['style'] = 'width:'.$setting['size'].';';
				}
				$setting['display_field'] = form_input($input_data);
				break;
			case 'textarea':
				$setting['display_field'] = form_textarea(array('name' => $this->field_name.'['.$setting['short_name'].']', 'value' => $current_value, 'rows' => 2));
				break;
			case 'hidden':
				$setting['display_field'] = form_hidden($this->field_name.'['.$setting['short_name'].']', $current_value);
				break;
			case 'select':
				if (array_values($setting['options']) === $setting['options'])
				{
					foreach($setting['options'] as $key => $value)
					{
						unset($setting['options'][$key]);
						$setting['options'][$value] = $value;
					}
				}
				$setting['display_field'] = form_dropdown($this->field_name.'['.$setting['short_name'].']', $setting['options'], $current_value, @$setting['extra']);
				break;
			case 'multiselect':
				if (array_values($setting['options']) === $setting['options'])
				{
					foreach($setting['options'] as $key => $value)
					{
						unset($setting['options'][$key]);
						$setting['options'][$value] = $value;
					}
				}
				$setting['display_field'] = form_multiselect($this->field_name.'['.$setting['short_name'].'][]', $setting['options'], $current_value, @$setting['extra']);
				break;
			case 'radio':
				if ( ! isset($setting['options']) || ! is_array($setting['options']))
				{
					$setting['display_field'] = form_label($this->field_name.'['.$setting['short_name'].']', form_radio($this->field_name.'['.$setting['short_name'].']', 1, $current_value).$this->EE->lang->line('yes'));
					$setting['display_field'] .= form_label($this->field_name.'['.$setting['short_name'].']', form_radio($this->field_name.'['.$setting['short_name'].']', 0, ! $current_value).$this->EE->lang->line('no'));
				}
				else
				{
					$setting['display_field'] = '';
					
					//if is index array
					if (array_values($setting['options']) === $setting['options'])
					{
						foreach($setting['options'] as $value)
						{
							$setting['display_field'] .= form_label($this->field_name.'['.$setting['short_name'].']', form_radio($this->field_name.'['.$setting['short_name'].']', $value, ($current_value == $value)).$value);
						}
					}
					//if associative array
					else
					{
						foreach($setting['options'] as $key => $value)
						{
							$setting['display_field'] .= form_label($this->field_name.'['.$setting['short_name'].']', form_radio($this->field_name.'['.$setting['short_name'].']', $key, ($current_value == $key)).$value);
						}
					}
				}
				break;
			default:
				$setting['display_field'] = '';
		}
		
		$setting['plugin_type'] = $plugin_type;
		
		return $setting;
	}
	
	public function save($data)
	{
		return (is_array($data)) ? base64_encode(serialize($data)) : '';
	}
	
	public function save_settings($data)
	{
		$data['field_fmt'] = 'none';
		
		return $data;
	}
	
	/**
	 * Replace tag
	 *
	 * @access	public
	 * @param	field contents
	 * @return	replacement text
	 *
	 */
	public function replace_tag($data, $params = array(), $tagdata = FALSE)
	{
		return '';
	}
}

/* End of file ft.cartthrob_discount.php */
/* Location: ./system/expressionengine/third_party/cartthrob_discount/ft.cartthrob_discount.php */