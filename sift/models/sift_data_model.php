<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Data Model class
 *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */
class Sift_data_model extends Sift_model {


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
			'sift_data',
			'data_id',
			array(	
				'site_id'       		=> 'int(4) unsigned NOT NULL',
				'channel_id'			=> 'int(4) unsigned NOT NULL',
				'matrix_id'				=> 'int(4) unsigned NOT NULL',
				'set_name'				=> 'varchar(100) NOT NULL',
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
		//parent::install();

		// Add indexes to table
		//$this->EE->db->query("ALTER TABLE {$this->table()} ADD INDEX (`site_id`)");
	}

	/*
		
		$channel_id 		= $this->EE->sift_data_model->get_channel_id( $channel );
		$matrix_field_id	= $this->SS->sift_data_model->get_matrix_id( $matrix_field );
		$cell_ids 			= $this->EE->sift_data_model->get_cell_ids( $cell_names );*/


	public function get_channel_id( $channel_name = '' )
	{
		if( $channel_name == '' ) return FALSE;

		die('nope');
		return 1;
	}


	public function get_matrix_field_name( $matrix_field_id = '' )
	{
		if( $matrix_field_id == '' ) return FALSE;

		// @TODO use cache here

		$row = $this->EE->db->where('field_id', $matrix_field_id)
						->where('field_type', 'matrix')
						->where('site_id', $this->EE->config->item('site_id'))
						->get('channel_fields')
						->row_array();

		if( empty( $row ) ) return FALSE;

		// @TODO add to cache here
		return $row['field_name'];
	}


	public function get_matrix_id( $matrix_field_name = '' )
	{
		if( $matrix_field_name == '' ) return FALSE;

		// @TODO use cache here

		$row = $this->EE->db->where('field_name', $matrix_field_name)
						->where('field_type', 'matrix')
						->where('site_id', $this->EE->config->item('site_id'))
						->get('channel_fields')
						->row_array();

		if( empty( $row ) ) return FALSE;

		// @TODO add to cache here
		return $row['field_id'];
	}

	public function get_cell_ids( $cell_names = array() )
	{
		if( empty( $cell_names ) ) return array();

		return array('cell_title'=> 1, 'cell_extra'=> 2);
	}


	public function get_cells_for_matrix( $matrix_field_id )
	{
		$res = $this->EE->db->where('field_id', $matrix_field_id)
						->where('col_search', 'y')
						->order_by('col_order','asc')
						->get('matrix_cols')
						->result_array();
		if( empty( $res ) ) return FALSE;

		// @TODO cache things a bit

		$return = array();

		foreach( $res as $row )
		{
			$return[ $row['col_name'] ] = $row['col_id'];
		}

		return $return;
	}


} // End class

/* End of file Sift_project_model.php */