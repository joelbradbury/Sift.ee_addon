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

	public function get_cell_possible_values( $cell_id )
	{

		static $cell_possible_values;
		if( isset( $cell_possible_values[ $cell_id ] ) ) return $cell_possible_values[ $cell_id ];

		// Check the persistent cache next
		die('persistent cache here, but need to move the cache methods out of the _core_model into the parent model so the data model can access them');

		
		// Get all the unique values for this cell in the db.
		// This can be quite intensive, so we'll cache this on the file system also

		$sql = ' SELECT DISTINCT col_id_' . $cell_id . ' AS cell FROM exp_matrix_data ';
		$res = $this->EE->db->query( $sql )->result_array();

		$tmp = array();
		foreach( $res as $row )
		{
			// Cleanup and flatten
			$str = trim( $row['cell'] );
			if( $str != '' ) $tmp[] = $str;
		}

		// Now reorder the values
		array_multisort($tmp);


		// Serialize and store in cache
		$ser = serialize( $tmp );
		// Write to cache
		// @TODO

		$cell_possible_values[ $cell_id ] = $tmp;

		return $cell_possible_values[ $cell_id ];
	}


	public function get_matrix_field_name( $matrix_field_id = '' )
	{
		static $matrix_field_data;

		if( $matrix_field_id == '' ) return FALSE;

		if( isset( $matrix_field_data['id'][ $matrix_field_id ] ) ) return $matrix_field_data['id'][ $matrix_field_id ];

		$row = $this->EE->db->where('field_id', $matrix_field_id)
						->where('field_type', 'matrix')
						->where('site_id', $this->EE->config->item('site_id'))
						->get('channel_fields')
						->row_array();

		if( empty( $row ) ) $matrix_field_data['id'][ $matrix_field_id ] = FALSE;
		else 
		{
			$matrix_field_data['id'][ $matrix_field_id ] = $row['field_name'];

			// Also set the opposite for completeness
			$matrix_field_data['name'][ $row['field_name'] ] = $matrix_field_id;
		}

		return $matrix_field_data['id'][ $matrix_field_id ];
	}


	public function get_matrix_id( $matrix_field_name = '' )
	{
		static $matrix_field_data;

		if( $matrix_field_name == '' ) return FALSE;

		if( isset( $matrix_field_data['name'][ $matrix_field_name ] ) ) return $matrix_field_data['name'][ $matrix_field_name ];


		$row = $this->EE->db->where('field_name', $matrix_field_name)
						->where('field_type', 'matrix')
						->where('site_id', $this->EE->config->item('site_id'))
						->get('channel_fields')
						->row_array();

		if( empty( $row ) ) $matrix_field_data['name'][ $matrix_field_name ] = FALSE;
		else 
		{
			$matrix_field_data['name'][ $matrix_field_name ] = $row['field_id'];

			// Also set the opposite for completeness
			$matrix_field_data['id'][ $row['field_id'] ] = $matrix_field_name;
		}

		return $matrix_field_data['name'][ $matrix_field_name ];
	}

	public function get_cell_ids( $cell_names = array() )
	{
		if( empty( $cell_names ) ) return array();

		die('this is hardcoded');
		return array('cell_title'=> 1, 'cell_extra'=> 2);
	}


	public function get_cells_for_matrix( $matrix_field_id )
	{
		static $matrix_field_data;

		if( isset( $matrix_field_data['cell'][ $matrix_field_id ] ) ) return $matrix_field_data['cell'][ $matrix_field_id ];

		$res = $this->EE->db->where('field_id', $matrix_field_id)
						->where('col_search', 'y')
						->order_by('col_order','asc')
						->get('matrix_cols')
						->result_array();
		if( empty( $res ) ) 
		{
			$matrix_field_data['cell'][ $matrix_field_id ] = FALSE;
			return FALSE;
		}



		$return = array();
		foreach( $res as $row )
		{
			$return[ $row['col_name'] ] = $row['col_id'];
		}

		$matrix_field_data['cell'][ $matrix_field_id ] = $return;

		return $matrix_field_data['cell'][ $matrix_field_id ];
	}


} // End class

/* End of file Sift_project_model.php */