<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Sift Module Class
 *
 * @package         sift_ee_addon
 * @version         1.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2013, Joel Bradbury
 */

// include config file
include PATH_THIRD.'sift/config'.EXT;


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
		ini_set('memory_limit', '1024M');

	}

	public function view()
	{
		$this->tagdata = $this->EE->TMPL->tagdata;

		$this->_prep_no_results();
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
		else
		{
			$this->tagdata = $return;
		}

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

		$form_class = $this->EE->TMPL->fetch_param('form_class');
		$form_id 	= $this->EE->TMPL->fetch_param('form_id');
		$form_method= $this->EE->TMPL->fetch_param('form_method');

		$form_class = $form_class != '' ? ' class="'.$form_class.'"' : '';
		$form_id 	= $form_id != '' ? ' id="'.$form_id.'"' : '';
		$form_method= $form_method != '' ? 'get' : $form_method;

		// Get the action_id 
		$action_url = $this->EE->functions->fetch_site_index(). '/search';
		if( $this->EE->TMPL->fetch_param('return') != '' ) $action_url = $this->EE->TMPL->fetch_param('return'); 

		$hidden = '<input type="hidden" name="sift_search" value="yes"/>';
		$bare = "<form name='sift_form'". $form_class . $form_id ." method='".$form_method."' action='".$action_url."'>";

		$bare .= $hidden;
		$bare .= $tagdata;
		$bare .= "</form>";


		// We need to get the raw cell data for this matrix field to allow
		// various form value options
		$matrix_data = $this->EE->sift_core_model->setup_matrix_cell_data( $matrix_field, $bare );
		if( $matrix_data === FALSE ) return $this->EE->TMPL->no_results;

		$tagdata = $matrix_data['tagdata'];


		$data = array_merge( $matrix_data['blanks'], $data );

		// Parse tagdata
		$t = $this->EE->TMPL->parse_variables(
				$tagdata,
				array($data),
				TRUE 
			);
			
		return $t;
	}

	private function _prep_no_results()
	{
		// Shortcut to tagdata
		$td    =& $this->EE->TMPL->tagdata;
		$open  = "if no_sift_results";
		$close = '/if';

		// Check if there is a custom no_results conditional
		if (strpos($td, $open) !== FALSE && preg_match('#' .LD .$open .RD .'(.*?)' .LD .$close .RD .'#s', $td, $match))
		{
		    $this->EE->TMPL->log_item("Prepping {$open} conditional");

		    // Check if there are conditionals inside of that
		    if (stristr($match[1], LD.'if'))
		    {
		      	$match[0] = $this->EE->functions->full_tag($match[0], $td, LD.'if', LD.'\/if'.RD);
		    }

		    // Set template's no_results data to found chunk
		    $this->EE->TMPL->no_results = substr($match[0], strlen(LD.$open.RD), -strlen(LD.$close.RD));

		    // Remove no_results conditional from tagdata
		    $td = str_replace($match[0], '', $td);
		}
	}

}

?>