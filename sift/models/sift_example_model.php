<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Project Model class
 *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */
class Sift_example_model extends Sift_model {

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access      public
	 * @return      void
	 */
	function __construct()
	{
		// Call parent constructor
		parent::__construct();

		// Initialize this model
		$this->initialize(
			'sift_example',
			'account_id',
			array(	
				'site_id'       		=> 'int(4) unsigned NOT NULL',
				'account_email'			=> 'varchar(100) NOT NULL',
				'currency'				=> 'varchar(100) NOT NULL',
				'return_success'		=> 'varchar(100) NOT NULL',
				'return_failure'		=> 'varchar(100) NOT NULL',
				'debug_mode'			=> 'int(1) unsigned NOT NULL',
				'status'				=> 'varchar(100) NOT NULL',
				'created_on'			=> 'int(10) unsigned NOT NULL')
		);
	}

	// --------------------------------------------------------------------

	/**
	 * Installs given table
	 *
	 * @access      public
	 * @return      void
	 */
	public function install()
	{
		// Call parent install
		parent::install();

		// Add indexes to table
		$this->EE->db->query("ALTER TABLE {$this->table()} ADD INDEX (`site_id`)");
	}


	// --------------------------------------------------------------

} // End class

/* End of file Sift_project_model.php */