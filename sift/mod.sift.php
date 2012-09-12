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


}

?>