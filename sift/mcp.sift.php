<?php defined('BASEPATH') or exit('No direct script access allowed');


class Sift_mcp
{
	public $module_name;
	private $nocache;
	private $cached_vars = array();

	function __construct()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		$this->module_name = strtolower(str_replace('_mcp', '', get_class($this)));
		$this->base = str_replace( '&amp;D=', '&D=', BASE.'&C=addons_modules&M=show_module_cp&module=' . $this->module_name );


		$this->contols[]  = $this->base.AMP.'method=sets';
		$this->contols[]  = $this->base.AMP.'method=settings';


		$controls = array(  lang('sift')		=> $this->base . '&method=index',
							lang('sets')		=> $this->base . '&method=sets',
							lang('settings')	=> $this->base . '&method=settings');

		$this->EE->cp->set_right_nav( $controls );


		// Load helper
		$this->EE->load->helper('Sift');
		
		// Load Sift base model
		$this->EE->load->library('Sift_model');

		// Load other models  
		Sift_model::load_models();		
	}


	// --------------------------------------------------------------------

	/**
	 * Module home page
	 *
	 * @access      public
	 * @return      string
	 */
	function index()
	{	
		$this->EE->cp->set_variable('cp_page_title', lang('sift_module_name'));

		return $this->EE->load->view('index', array(), TRUE);
	
	}


	// --------------------------------------------------------------------

	/**
	 * Module home page
	 *
	 * @access      public
	 * @return      string
	 */
	function sets()
	{		
		$this->EE->cp->set_variable('cp_page_title', lang('sift_set_overview'));

		// Get the current sift sets
		$this->cached_vars['sets'] = $this->EE->sift_set_model->get_all();

		// Give the option to create a new set
		$this->cached_vars['new_set_uri'] = $this->base . '&method=new_set';


		return $this->EE->load->view('mcp_sift_sets', $this->cached_vars, TRUE );
	}

	function settings()
	{
		// --------------------------------------
		// Load some libraries
		// --------------------------------------

		$this->EE->load->library('javascript');

		$this->EE->cp->set_variable('cp_page_title', lang('settings'));
		$this->EE->cp->set_breadcrumb($this->base, lang('sift_module_name'));

		$this->cached_vars['form_post_url'] = $this->base . '&method=save_settings';

		return $this->EE->load->view('settings', $this->cached_vars, TRUE);
	}





	public function save_settings()
	{
		$data = array();

		foreach( $this->EE->sift_example_model->attributes() as $attribute )
		{
			if( $this->EE->input->get_post( $attribute ) != '' )
			{
				$data[ $attribute ] = $this->EE->input->get_post( $attribute );
			}
		}

		$this->EE->sift_example_model->insert( $data );

        // ----------------------------------
        //  Redirect to Settings page with Message
        // ----------------------------------
        
        $this->EE->functions->redirect($this->base . '&method=settings&msg=preferences_updated');
        exit;

	}




	
}
