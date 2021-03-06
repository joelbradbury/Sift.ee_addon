<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// Load CI model if it doesn't exist
if ( ! class_exists('CI_model'))
{
	load_class('Model', 'core');
}

class Sift_model extends CI_Model {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	private $_table;

	private $_pk;

	private $_attributes = array();

	protected $EE;

	private $_params = array();
	private $cache_lifetime = 3600; // 12 hours


	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * PHP5 Constructor
	 *
	 * @return     void
	 */
	function __construct( )
	{
		// Call parent constructor
		parent::__construct();

		// Set global object
		$this->EE =& get_instance();

		$this->EE->load->helper('sift_helper');
	}

	
	// --------------------------------------------------------------------

	/**
	 * Sets table, PK and attributes
	 *
	 * @access      protected
	 * @param       string    Table name
	 * @param       string    Primary Key name
	 * @param       array     Attributes
	 * @return      void
	 */
	protected function initialize($table, $pk, $attributes)
	{
		// Check table prefix
		$prefix = $this->EE->db->dbprefix;

		// Add prefix to table name if not there
		if (substr($table, 0, strlen($prefix)) != $prefix)
		{
			$table = $prefix.$table;
		}

		// Set the values
		$this->_table       = $table;
		$this->_pk          = $pk;
		$this->_attributes  = $attributes;
	}

	// --------------------------------------------------------------------

	/**
	 * Load models based on this main model
	 *
	 * @access      public
	 * @return      void
	 */
	public function load_models()
	{
		$EE =& get_instance();

		$EE->load->model("sift_core_model");
		$EE->load->model("sift_data_model");
		$EE->load->model("sift_cookie_model");
	}

	// --------------------------------------------------------------------

	/**
	 * Return table name
	 *
	 * @access      public
	 * @return      string
	 */
	public function table()
	{
		return $this->_table;
	}

	// --------------------------------------------------------------------

	/**
	 * Return primary key
	 *
	 * @access      public
	 * @return      string
	 */
	public function pk()
	{
		return $this->_pk;
	}

	// --------------------------------------------------------------------

	/**
	 * Return array of attributes, sans PK
	 *
	 * @access      public
	 * @return      array
	 */
	public function attributes()
	{
		return array_keys($this->_attributes);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Check key is a valid attribute
	 *
	 * @access      public
	 * @return      array
	 */
	public function is_attribute( $key )
	{
		if( array_key_exists( $key, $this->_attributes ) ) return TRUE;

		elseif( $key == $this->_pk ) return TRUE;

		return FALSE;
	}



	// --------------------------------------------------------------------

	/**
	 * Return one record by primary key or attribute
	 *
	 * @access      public
	 * @param       int       id of the record to fetch
	 * @param       string    attribute to check
	 * @return      array
	 */
	public function get_one($id, $attr = FALSE)
	{
		if ($attr === FALSE) $attr = $this->_pk;

		return $this->EE->db->where($attr, $id)->get($this->_table)->row_array();
	}

	// --------------------------------------------------------------------

	/**
	 * Return multiple records
	 *
	 * @access      public
	 * @return      array
	 */
	public function get_all()
	{
		return $this->EE->db->get($this->_table)->result_array();
	}

	// --------------------------------------------------------------------

	/**
	 * Return multiple records' primary keys
	 *
	 * @access      public
	 * @return      array
	 */
	public function get_all_pk()
	{
		return $this->EE->db->select( $this->_pk )->get($this->_table)->result_array();
	}
	// --------------------------------------------------------------------

	/**
	 * Return an empty row for data initialisation
	 *
	 * @access      public
	 * @return      array
	 */
	public function empty_row()
	{
		$row = array_merge(array($this->_pk), $this->attributes());
		$row = array_combine($row, array_fill(0, count($row), ''));
		return $row;
	}

	// --------------------------------------------------------------------

	/**
	 * Insert record into DB
	 *
	 * @access      public
	 * @param       array     data to insert
	 * @return      int
	 */
	public function insert($data = array())
	{
		if (empty($data))
		{
			// loop through attributes to get posted data
			foreach ($this->attributes() AS $attr)
			{
				if (($val = $this->EE->input->post($attr)) !== FALSE)
				{
					$data[$attr] = $val;
				}
			}
		}
		else
		{
			// Check our passed data, and drop any non attributes
			foreach( $data as $key => $val )
			{
				if( is_array( $val ) ) unset( $data[ $key ] );
				elseif( ! $this->is_attribute( $key ) ) unset( $data[ $key ] );

			}
		}

		// Insert data and return inserted id
		$this->EE->db->insert($this->_table, $data);
		return $this->EE->db->insert_id();
	}

	// --------------------------------------------------------------------


	/**
	 * Update record into DB
	 *
	 * @access      public
	 * @param       array     data to insert
	 * @return      int
	 */
	public function update($id, $data = array())
	{
		if (empty($data))
		{
			// loop through attributes to get posted data
			foreach ($this->attributes() AS $attr)
			{
				if (($val = $this->EE->input->post($attr)) !== FALSE)
				{
					$data[$attr] = $val;
				}
			}
		}
		else
		{
			// Check our passed data, and drop any non attributes
			foreach( $data as $key => $val )
			{
				if( is_array( $val ) ) unset( $data[ $key ] );
				elseif( ! $this->is_attribute( $key ) ) unset( $data[ $key ] );

			}
		}

		// Insert data and return inserted id
		$this->EE->db->update($this->_table, $data, "{$this->_pk} = '{$id}'");
	}

	// --------------------------------------------------------------------

	/**
	 * Update record into DB
	 *
	 * @access      public
	 * @param       array     data to insert
	 * @return      int
	 */
	public function insert_update($id, $data = array())
	{
		if (empty($data))
		{
			// loop through attributes to get posted data
			foreach ($this->attributes() AS $attr)
			{
				if (($val = $this->EE->input->post($attr)) !== FALSE)
				{
					$data[$attr] = $val;
				}
			}
		}
		else
		{
			// Check our passed data, and drop any non attributes
			foreach( $data as $key => $val )
			{
				if( is_array( $val ) ) unset( $data[ $key ] );
				elseif( ! $this->is_attribute( $key ) ) unset( $data[ $key ] );
			}
		}

		$sql = $this->EE->db->insert_string($this->_table, $data);


		$sql .= " ON DUPLICATE KEY UPDATE ";

		$temp = array();

		foreach( $data as $key => $row ) 
		{
			$temp[] = " `" . $key . "` = '" . $this->EE->db->escape_str( $row ) . "'";
		}

		$sql .= implode( ", ", $temp );

		// Insert data and return inserted id
		$this->EE->db->query( $sql );
	}

	// --------------------------------------------------------------------

	/**
	 * Delete record
	 *
	 * @access      public
	 * @param       array     data to insert
	 * @param       string    optional attribute to delete records by
	 * @return      void
	 */
	public function delete($id, $attr = FALSE)
	{
		if ( ! is_array($id))
		{
			$id = array($id);
		}

		if ($attr === FALSE) $attr = $this->_pk;

		$this->EE->db->where_in($attr, $id)->delete($this->_table);
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
		// Begin composing SQL query
		$sql = "CREATE TABLE IF NOT EXISTS {$this->_table} ( ";

		// Add primary key -- is it an array?
		if (is_array($this->_pk))
		{
			foreach ($this->_pk AS $key)
			{
				$sql .= "{$key} int(10) unsigned NOT NULL, ";
			}
		}
		else
		{
			$sql .= "{$this->_pk} int(10) unsigned NOT NULL, ";
		}

		// add other attributes
		foreach ($this->_attributes AS $attr => $props)
		{
			$sql .= "{$attr} {$props}, ";
		}

		// Set PK
		$sql .= "PRIMARY KEY (".implode(',', (array) $this->_pk)."))";

		// Execute query
		$this->EE->db->query($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Uninstalls given table
	 *
	 * @access      public
	 * @return      void
	 */
	public function uninstall()
	{
		$this->EE->db->query("DROP TABLE IF EXISTS {$this->_table}");
	}

	// --------------------------------------------------------------------

	/**
	 *	Check if Submitted String is a Yes value
	 *
	 *	If the value is 'y', 'yes', 'true', or 'on', then returns TRUE, otherwise FALSE
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		bool
	 */

    function check_yes($which)
    {
		switch($which)
		{
			case 'y'	:
			case 'yes'	:
			case 'on'	:
			case 'true'	:
				return TRUE;
			break;
			default		:
				return FALSE;	
			break;
		}
    }
    /* END check_yes() */
    
	// --------------------------------------------------------------------

	/**
	 *	Check if Submitted String is a No value
	 *
	 *	If the value is 'n', 'no', 'false', or 'off', then returns TRUE, otherwise FALSE
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		bool
	 */

    function check_no($which)
    {
		switch($which)
		{
			case 'n'	:
			case 'no'	:
			case 'off'	:
			case 'false'	:
				return TRUE;
			break;
			default		:
				return FALSE;	
			break;
		}
    }
    /* END check_yes() */


	
	// --------------------------------------------------------------------

	/* 
	*  Checks the cache for a value, and optionally writes to local
	*	variable before returning
	*/
	public function check_cache( $method, $data = array(), $var_name = '' )
	{
		$this->EE->load->helper('file');

		$name = $this->_cache_name( $method, $data );
		
		$cache_path = APPPATH . 'cache/' . SIFT_CLASS_NAME . '/';
		if( $this->_check_cache_dir( $cache_path ) == FALSE ) return FALSE;

		$cached_data = read_file( $cache_path . $name );
		if( $cached_data === FALSE OR $cached_data == '' ) return FALSE;

		// What is the age of the file?
		$file_info = get_file_info( $cache_path . $name );
		if( $file_info['date'] < ( $this->EE->localize->now - $this->cache_lifetime ) ) 
		{
			// caches live for only a little while, but burn so brightly
			return FALSE;
		}

		// Decode the events json
		$cached_data = $this->_obj_to_array( json_decode($cached_data) );

		// Seems we have cache, write to our variable or return it
		if( $var_name != '' ) $this->{$var_name} = $cached_data;
		else return $cached_data;

		return TRUE;
	}

	private function _cache_name( $method, $data = array() )
	{
		// Clear out the empty data points
		$tmp = '';
		foreach( $data as $key => $val )
		{
			if( trim( $val ) != '' ) 
			{
				if( $key == 'channel' ) $key = 'c';
				elseif( $key == 'orderby' ) $key = 'o';
				elseif( $key == 'matrix_field' ) $key = 'm';
				$tmp .= $key.''.$val.'';
			}

		}
		$data = base64_encode( $tmp );

		// Generate the name for the cache file
		$name = $method . '_' . $data;

		return $name;
	}


	// --------------------------------------------------------------

	/**
	 * Checks the cache directories exist, and creates if needed
	 *
	 * @access      public
	 * @return      void
	 */
	private function _check_cache_dir( $path = '')
	{
		if( ! is_dir( $path ) ) 
		{
			mkdir($path, DIR_WRITE_MODE);
			@chmod($path, DIR_WRITE_MODE);	
		}

		return TRUE;
	}
	
	// --------------------------------------------------------------------

	public function empty_caches()
	{
		$this->EE->load->helper('file');

		// Generate the name for the cache file
		$cache_path = APPPATH . 'cache/' . SIFT_CLASS_NAME . '/';

		//if the cache directory doesn't exist, consider the cache cleared
		if ( ! is_dir( $cache_path ) )
		{
			return TRUE;
		}

		//delete files and directories
		delete_files( $cache_path, TRUE );

		return TRUE;
	}

	/* 
	*  Writes to the cache
	*/
	public function write_cache( $method, $filters = array(), $data = array() )
	{
		$this->EE->load->helper('file');

		// Generate the name for the cache file
		$name = $this->_cache_name( $method, $filters );
		$cache_path = APPPATH . 'cache/' . SIFT_CLASS_NAME . '/';

		if( $this->_check_cache_dir( $cache_path ) == FALSE ) return FALSE;

		// Encode the data
		$data = json_encode( $data );
		if( ! write_file( $cache_path . $name , $data ) ) return FALSE;

		return TRUE;
	}


	private function _obj_to_array($obj, $clean = FALSE, $convert = array() ) 
	{

	    if(is_object($obj)) $obj = (array) $obj;

	    if(is_array($obj)) {

	        $new = array();

	        foreach($obj as $key => $val) {

	        	if( $clean ) 
	        	{
		        	$key = str_replace( '-', '_', $key );

		        	if( isset( $convert[ $key ] ) ) $key = $convert[ $key ];
		        }

	            $new[$key] = $this->_obj_to_array($val, $clean);
	        }
	    }
	    else $new = $obj;

	    return $new;
	}


}
// End of file Sift_model.php