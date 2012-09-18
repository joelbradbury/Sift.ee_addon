<?php if ( ! defined('EXT')) exit('No direct script access allowed');


// include config file
include PATH_THIRD.'sift/config'.EXT;

/**
 * Sift Extension Class
 *
 * @package         sift_ee_addon
 * @version         1.0.2
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */

class Sift_ext {

		// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Extension settings
	 *
	 * @access      public
	 * @var         array
	 */
	public $name 			= SIFT_NAME;
	public $version 		= SIFT_VERSION;
	public $description 	= 'Sift extension';
	public $settings_exist 	= FALSE;
	public $docs_url	 	= SIFT_DOCS;
	public $class_name 		= SIFT_CLASS_NAME;

	//public $required_by 	= array('module');

	private $EE;

	/**
	 * Hooks used
	 *
	 * @access      private
	 * @var         array
	 */
	private $hooks = array(
		'channel_entries_query_result',
		'matrix_data_query'
	);

	// --------------------------------------------------------------------
	// PUBLIC METHODS
	// --------------------------------------------------------------------

	/**
	 * PHP4 Constructor
	 *
	 * @see         __construct()
	 */
	public function Sift_ext($settings = FALSE)
	{
		$this->__construct($settings);
	}

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access      public
	 * @param       mixed     Array with settings or FALSE
	 * @return      null
	 */
	public function __construct($settings = FALSE)
	{
		// Get global instance
		$this->EE =& get_instance();

		// Set Class name
		$this->class_name = ucfirst(get_class($this));

		// Set settings
		$this->settings = $settings;

		// Define the package path
		$this->EE->load->add_package_path(PATH_THIRD.'sift');

		// Load helper
		$this->EE->load->helper('Sift_helper');

		// Load base model
		if( !isset( $this->EE->sift_model ) ) $this->EE->load->library('Sift_model');

		// Load other models
		Sift_model::load_models();
	}


	// --------------------------------------------------------------------
	// EXTENSION ACTIVATION, UPDATE AND DISABLING
	// --------------------------------------------------------------------

	/**
	 * Activate extension
	 *
	 * @access      public
	 * @return      null
	 */
	public function activate_extension()
	{
		// Loop through hooks and insert them in the DB
		foreach ($this->hooks AS $hook)
		{
			$this->EE->db->insert('extensions', array(
				'class'     => $this->class_name,
				'method'    => $hook,
				'hook'      => $hook,
				'priority'  => 10,
				'version'   => SIFT_VERSION,
				'enabled'   => 'y'
			));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Update extension
	 *
	 * @access      public
	 * @param       string    Saved extension version
	 * @return      null
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR version_compare($current, SIFT_VERSION) === 0)
		{
			return FALSE;
		}

		// init data array
		$data = array();

		// Add version to data array
		$data['version'] = SIFT_VERSION;

		// Update records using data array
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->update('extensions', $data);
	}

	// --------------------------------------------------------------------

	/**
	 * Disable extension
	 *
	 * @access      public
	 * @return      null
	 */
	function disable_extension()
	{
		// Delete records
		$this->EE->db->where('class', $this->class_name);
		$this->EE->db->delete('extensions');
	}

	public function channel_entries_query_result( &$that, $query_result )
	{
		if( isset( $that->is_sift ) AND $that->is_sift === TRUE )
		{
			// Drop a marker on the EE object to keep track of this 
			// object loop
			$this->EE->is_sift = TRUE;
			$this->EE->sift_items = $that->sift_items;

			// Right, we have a sift order, and need to rearrange
			// the passed query result to match
			// This also allows us to inject repeats into the loop

			$new_order = array();
			$old_order = $query_result;

			// 1. Do we need to do anything - only actually req'd 
			// if there are repeats in the sift_order 
			$entry_items = array();
			$cleanup = FALSE;

			foreach( $that->sift_order as $row )
			{
				if( isset( $entry_items[ $row ] ) ) 
				{
					$cleanup = TRUE;
				}
				
				$entry_items[ $row ] = array();
			}

			if( $cleanup )
			{
				$clean_array = array();
				$temp_array = array();

				foreach( $query_result as $row )
				{
					$temp_array[ $row['entry_id'] ] = $row;
				}

				// 2. Reorder as required
				foreach( $that->sift_order as $row )
				{
					$clean_array[] = $temp_array[ $row ];
				}


				return $clean_array;
			}
		}

		return $query_result;
	}

	public function matrix_data_query( $that, $params, $sql, $select_mode)
	{
		if( isset( $that->EE->is_sift ) AND isset( $that->EE->sift_items ) ) 
		{
			$marker = ' ORDER BY ';

			$extra = array();
			foreach( $that->EE->sift_items as $item )
			{
				$extra[] = $item['row_id'];
			}
			$extra_sql = ' AND row_id IN ('. implode(',', $extra).') ';

			// Ok, rework the sql to just get the rows we know we need
			$sql_new = str_replace( $marker, $extra_sql . $marker , $sql );
			

			return $this->EE->db->query( $sql_new );
		}

		// Unset it now
		unset( $this->EE->is_sift );
		return $this->EE->db->query( $sql );
	}

}