<?php defined('BASEPATH') or exit('No direct script access allowed');


/**
 * Sift MCP Class
 *
 * @package         sift_ee_addon
 * @version         1.3.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/addons/sift
 * @copyright       Copyright (c) 2013, Joel Bradbury
 */

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


	/*	$controls = array(  lang('sift')		=> $this->base . '&method=index',
							lang('sets')		=> $this->base . '&method=sets',
							lang('settings')	=> $this->base . '&method=settings');*/

	//	$this->EE->cp->set_right_nav( $controls );

		$this->_prep_message();

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

		$this->cached_vars['clear_cache_form_uri'] = $this->base . '&method=clear_caches';

		return $this->EE->load->view('mcp_sift', $this->cached_vars, TRUE);

	}

	function clear_caches()
	{
		$this->EE->sift_data_model->clear_caches();

		   // ----------------------------------
        //  Redirect to Settings page with Message
        // ----------------------------------

        $this->EE->functions->redirect($this->base . '&method=index&msg=caches_cleared');
        exit;

	}

	/**
	 * Prep message
	 * @access	private
	 * @param	message
	 * @return	boolean
	 */

	function _prep_message( $message = '' )
	{
        if ( $message == '' AND isset( $_GET['msg'] ) )
        {
        	$message = $this->EE->lang->line( $_GET['msg'] );
        }

		$this->cached_vars['message']	= $message;

		return TRUE;
	}




}
