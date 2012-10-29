<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once PATH_THIRD.'cartthrob/config.php';

class Cartthrob_price_simple_ft extends EE_Fieldtype
{
	public $info = array(
		'name' => 'CartThrob Price - Simple',
		'version' => CARTTHROB_VERSION,
	);
	
	public function __construct()
	{
		parent::EE_Fieldtype();
		
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->helper('data_formatting');
	}
	
	public function validate($data)
	{
		if ($data && ! $this->EE->form_validation->numeric($data))
		{
			return $this->EE->lang->line('numeric');
		}
		
		return TRUE;
	}
	
	public function replace_tag($data, $params = '', $tagdata = '')
	{
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->library('cartthrob_loader');

		$this->EE->load->library('number');
		
		return $this->EE->number->format($data);
	}
	
	public function replace_no_tax($data, $params = '', $tagdata = '')
	{
		return $this->replace_tag($data, $params, $tagdata); 
	}
	
	public function replace_plus_tax($data, $params = '', $tagdata = '')
	{
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->library('cartthrob_loader');
		
		$this->EE->load->library('number');

		if ($plugin = $this->EE->cartthrob->store->plugin($this->EE->cartthrob->store->config('tax_plugin')))
		{
			$data = $plugin->get_tax($data) + $data;
		}
		return $this->EE->number->format($data);
	}
	public function replace_plus_tax_numeric($data, $params = '', $tagdata = '')
	{
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
		
		$this->EE->load->library('cartthrob_loader');
		
		$this->EE->load->library('number');

		if ($plugin = $this->EE->cartthrob->store->plugin($this->EE->cartthrob->store->config('tax_plugin')))
		{
			$data = $plugin->get_tax($data) + $data;
		}
		return $data; 
	}
	public function replace_numeric($data, $params = '', $tagdata = '')
	{
		return $data;
	}
	
	public function display_field($data)
	{
		if (empty($this->EE->session->cache[__CLASS__]['js']))
		{
			$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/');
			
			$this->EE->load->library('cartthrob_loader');
			
			$span = '<span style="position:absolute;padding:5px 0 0 5px;">'.$this->EE->cartthrob->store->config('number_format_defaults_prefix').'</span>';
			
			$this->EE->javascript->output('
				var span = $(\''.$span.'\').appendTo("body").css({top:-9999});
				var indent = span.width()+4;
				span.remove();
				$(".cartthrob_price_simple").each(function(){
					$(this).before(\''.$span.'\');
					$(this).css({paddingLeft: indent});
				});
			');
			
			$this->EE->session->cache[__CLASS__]['js'] = TRUE;
		}
		
		return form_input(array(
			'name' => $this->field_name,
			'id' => $this->field_name,
			'class' => 'cartthrob_price_simple',
			'value' => $data,
			'maxlength' => $this->settings['field_maxl']
		));
	}

	public function display_settings($data)
	{
		$field_maxl = (empty($data['field_maxl'])) ? 12 : $data['field_maxl'];
		
		$this->EE->table->add_row(
			lang('field_max_length', 'field_maxl'),
			form_input(array('id' => 'cartthrob_price_simple_field_maxl', 'name' => 'cartthrob_price_simple_field_maxl', 'size' => 4, 'value' => $field_maxl))
		);
	}
	
	public function save_settings($data)
	{
		return array(
			'field_maxl' => $this->EE->input->post('field_maxl'),
		);
	}
}

/* End of file ft.cartthrob_price.php */
/* Location: ./system/expressionengine/third_party/cartthrob_discount/ft.cartthrob_price.php */