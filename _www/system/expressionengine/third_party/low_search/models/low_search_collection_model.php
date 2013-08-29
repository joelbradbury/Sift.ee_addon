<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Low Search Collection Model class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_collection_model extends Low_search_model {

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
			'low_search_collections',
			'collection_id',
			array(
				'site_id'          => 'int(4) unsigned NOT NULL',
				'channel_id'       => 'int(6) unsigned NOT NULL',
				'collection_name'  => 'varchar(40) NOT NULL',
				'collection_label' => 'varchar(100) NOT NULL',
				'modifier'         => 'decimal(2,1) unsigned NOT NULL default 1.0',
				'excerpt'          => 'int(6) unsigned NOT NULL default 0',
				'settings'         => 'text NOT NULL',
				'edit_date'        => 'int(10) unsigned NOT NULL'
			)
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
		ee()->db->query("ALTER TABLE {$this->table()} ADD INDEX (`site_id`)");
		ee()->db->query("ALTER TABLE {$this->table()} ADD INDEX (`channel_id`)");
	}

	// --------------------------------------------------------------

	/**
	 * Get collection ids based on names
	 *
	 * @access      public
	 * @param       array     Names to get
	 * @param       bool      Include / exclude
	 * @return      array
	 */
	public function get_id_by_name($names = array(), $in = TRUE)
	{
		$query = ee()->db->select($this->pk())
		       ->from($this->table())
		       ->{$in === FALSE ? 'where_not_in' : 'where_in'}('collection_name', $names)
		       ->where('site_id', $this->site_id)
		       ->get();

		// Return flat array with ids
		return low_flatten_results($query->result_array(), $this->pk());
	}

	// --------------------------------------------------------------

	/**
	 * Get collection ids based which ones to exclude
	 *
	 * @access      public
	 * @param       array     ids to exclude
	 * @return      array
	 */
	public function get_other_ids_in_site($ids)
	{
		$query = ee()->db->select($this->pk())
		       ->from($this->table())
		       ->where_not_in($this->pk(), $ids)
		       ->where('site_id', $this->site_id)
		       ->get();

		// Return flat array with ids
		return low_flatten_results($query->result_array(), $this->pk());
	}

	// --------------------------------------------------------------

} // End class

/* End of file Low_search_collection_model.php */