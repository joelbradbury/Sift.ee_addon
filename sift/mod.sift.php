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
		return $this->tagdata;
	}


	private function _handle_search()
	{
		/* Hand this whole thing over to the 
			Sift core model to do all the heavy lifting
			Mostly this is just to keep this mod. file nice
			and clean and clutter free */

		$this->EE->sift_core_model->handle_search();

	}



	/* 
	* Quick helper function that wraps tagdata
	* in an opening and closing form tags
	* and also adds some hidden fields while it's at it
	*/
	private function _wrap_form( $tagdata )
	{
		$form_class = ' ';
		$form_id = ' ';

		$form_class = $this->EE->TMPL->fetch_param('class');
		$form_id 	= $this->EE->TMPL->fetch_param('id');

		// Get the action_id 
		$action_url = '/search';//$this->_get_action_url('invite_to_group');

		$hidden = '<input type="hidden" name="sift_search" value="yes"/>';
		$bare = "<form name='sift_form' id='".$form_id."' class='".$form_class."' method='post' action='".$action_url."'>";

		$bare .= $hidden;
		$bare .= $tagdata;
		$bare .= "</form>";

		return $bare;
	}

}

?>