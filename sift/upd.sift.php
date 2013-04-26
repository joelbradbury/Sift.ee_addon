<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Update Class
 *
 * @package         sift_ee_addon
 * @version         1.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2013, Joel Bradbury
 */

// include config file
include PATH_THIRD.'sift/config'.EXT;

class Sift_upd {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Version number
	 *
	 * @access      public
	 * @var         string
	 */
	public $version = SIFT_VERSION;


	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

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

		// Define the package path
		$this->EE->load->add_package_path(PATH_THIRD.'Sift');

		// Load libraries...
		$this->EE->load->library('Sift_model');

		// Load other models
		Sift_model::load_models();
	}

	// --------------------------------------------------------------------

	/**
	 * Install the module
	 *
	 * @access      public
	 * @return      bool
	 */
	public function install()
	{
		// --------------------------------------
		// Install tables
		// --------------------------------------

		// None to install


		// --------------------------------------
		// Add row to modules table
		// --------------------------------------

		$this->EE->db->insert('modules', array(
			'module_name'    => SIFT_CLASS_NAME,
			'module_version' => SIFT_VERSION,
			'has_cp_backend' => 'y'
		));

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Uninstall the module
	 *
	 * @return	bool
	 */
	function uninstall()
	{
		// --------------------------------------
		// get module id
		// --------------------------------------

		$query = $this->EE->db->select('module_id')
		       ->from('modules')
		       ->where('module_name', SIFT_CLASS_NAME)
		       ->get();


		// --------------------------------------
		// remove references from modules
		// --------------------------------------

		$this->EE->db->where('module_name', SIFT_CLASS_NAME);
		$this->EE->db->delete('modules');

		// --------------------------------------
		// Uninstall tables
		// --------------------------------------

		// None to remove

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update the module
	 *
	 * @return	bool
	 */
	function update($current = '')
	{
		// --------------------------------------
		// Same Version - nothing to do
		// --------------------------------------

		if ($current == '' OR version_compare($current, SIFT_VERSION) === 0)
		{
			return FALSE;
		}

		// Returning TRUE updates db version number
		return TRUE;
	}

	// --------------------------------------------------------------------

} // End class

/* End of file upd.sift.php */