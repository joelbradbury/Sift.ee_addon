<?php if ( ! defined('EXT')) exit('No direct script access allowed');


// include config file
include PATH_THIRD.'sift/config'.EXT;

/**
 * Sift Module Class
 *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */

class Sift {
	
	public $return_data;
	private $EE;
	private $tagdata;

	/**
	 * Constructor: sets EE instance
	 *
	 * @access      public
	 * @return      null
	 */
	public function __construct()
	{
		// Set global object
		$this->EE =& get_instance();

		// Load base model
		$this->EE->load->library('Sift_model');

		// Load any additional libs
		//$this->EE->load->library('example_lib');

		// Load other models
		Sift_model::load_models();

		ini_set('memory_limit', '512M');

	}

	public function view()
	{
		$this->tagdata = $this->EE->TMPL->tagdata;

		$this->_handle_search();

		return $this->tagdata;
	}

	public function form()
	{
		$this->tagdata = $this->EE->TMPL->tagdata;
		$this->tagdata = $this->_wrap_form( $this->tagdata );

		$this->EE->sift_data_model->get_matrix_id('matrix_field');
		return $this->tagdata;
	}


	private function _handle_search()
	{
		/* Hand this whole thing over to the 
			Sift core model to do all the heavy lifting
			Mostly this is just to keep this mod. file nice
			and clean and clutter free */

		$return = $this->EE->sift_core_model->handle_search();
		if( $return === FALSE ) 
		{
			// Something was invalid in the search set
			$this->tagdata = $this->EE->TMPL->no_results; //ie('invalid search');
		}
		$this->tagdata = $return;

		return TRUE;
	}



	/* 
	* Quick helper function that wraps tagdata
	* in an opening and closing form tags
	* and also adds some hidden fields while it's at it
	*/
	private function _wrap_form( $tagdata )
	{

		if( $this->EE->TMPL->fetch_param('matrix_field') == '' ) return $this->EE->TMPL->no_results;
		$matrix_field = $this->EE->TMPL->fetch_param('matrix_field');

		$data = $this->EE->sift_core_model->handle_get_post();


		$form_class = ' ';
		$form_id = ' ';

		$form_class = $this->EE->TMPL->fetch_param('class');
		$form_id 	= $this->EE->TMPL->fetch_param('id');

		// Get the action_id 
		$action_url = $this->EE->functions->fetch_site_index(). '/search';
		if( $this->EE->TMPL->fetch_param('return') != '' ) $action_url = $this->EE->TMPL->fetch_param('return'); 

		$hidden = '<input type="hidden" name="sift_search" value="yes"/>';
		$bare = "<form name='sift_form' id='".$form_id."' class='".$form_class."' method='post' action='".$action_url."'>";

		$bare .= $hidden;
		$bare .= $tagdata;
		$bare .= "</form>";


		// We need to get the raw cell data for this matrix field to allow
		// various form value options
		$matrix_data = $this->EE->sift_core_model->setup_matrix_cell_data( $matrix_field, $tagdata );
		if( $matrix_data === FALSE ) return $this->EE->TMPL->no_results;

		$tagdata = $matrix_data['tagdata'];


		$data = array_merge( $matrix_data['blanks'], $data );

		// Is there a loop pair in the form? 
		// If there is we need to get the possible values for a cell
	//	$option_data = $this->EE->sift_core_model->get_options_matrix_cell_data( $blank_data );


		// Populate with some defaults
		//$defaults = array('seperate_matrix_rows' => 'no');
		//$data = array_merge( $data, $defaults );

		// Parse tagdata
		$t = $this->EE->TMPL->parse_variables(
				$tagdata,
				array($data),
				TRUE 
			);
			
		return $t;
	}

}

?>