<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Data Model class
 *
 * @package         sift_ee_addon
 * @version         1.4.0
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/addons/sift
 * @copyright       Copyright (c) 2014, Joel Bradbury
 */
class Sift_data_model extends Sift_model {

	public $cell_possible_values;

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
		// Nothing to install
	}


	public function get_cell_possible_values( $cell_id )
	{
		static $cell_possible_values;
		if( isset( $cell_possible_values[ $cell_id ] ) ) return $cell_possible_values[ $cell_id ];


		if( $this->check_cache('get_cell_possible', array('cell_id'=>$cell_id), 'cell_possible_values' ) === FALSE )
		{

			// Check the persistent cache next
			//die('persistent cache here, but need to move the cache methods out of the _core_model into the parent model so the data model can access them');

			// Get this cell type from the matrix_cols table
			$sql = " SELECT col_type, col_settings FROM exp_matrix_cols WHERE col_id = '".$cell_id."' ";
			$cell = $this->EE->db->query( $sql )->row_array();
			// Get all the unique values for this cell in the db.
			// This can be quite intensive, so we'll cache this on the file system also

			// Now depending on the col type we can try a few options to get this possible value data
			//if( $cell['col_type'] == 'pt_checkboxes' )
			if( $cell['col_type'] == 'pt_checkboxes' )
			{
				$settings = unserialize( base64_decode( $cell['col_settings'] ) );

				// Directly use these settings for the possible values
				if( isset( $settings['options'] ) )
				{
					$tmp = $settings['options'];
				}
			}
			else
			{
				// Fallback to direct inspection
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
			}

			// Serialize and store in cache
			$ser = serialize( $tmp );

			$cell_possible_values[ $cell_id ] = $tmp;

			// Write to the cache
			$this->write_cache('get_cell_possible',array('cell_id'=>$cell_id), $tmp );
		}
		else
		{
			$cell_possible_values[ $cell_id ] = $this->cell_possible_values;
		}

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

	public function clear_caches()
	{
		$this->empty_caches();
	}

} // End class

/* End of file Sift_project_model.php */