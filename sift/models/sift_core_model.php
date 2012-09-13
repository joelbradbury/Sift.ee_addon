<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Core Model class
 *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */
class Sift_core_model extends Sift_model {

	private $sift_data;
	private $ids;

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
			'sift_core',
			'set_id',
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


	// --------------------------------------------------------------

	public function handle_search()
	{
		/* There are 3 options for getting the sift parameters 
			in the following order of precendence :
				1. TMPL Params
				2. POST Data
				3. GET Data
			We'll look at each in turn to figure out what to do
			There are certain params that can _only_ be supplied via TMPL
			and those that if supplied via TMPL cannot be overridden
			by GET or POST data */

		// Clear it out just in case
		$this->sift_data = array();

		$this->_check_tmpl();
		$this->_check_post();
		$this->_check_get();

		// Ok, the sift_data array should be nice and neat for us now
		// Lets have a quick shifty to see if there's anything to do

		// Here would be a good place to check some cache 
		// @TODO
		// $this->_check_cache();

		$status = $this->_validate_sift_data();
		if( $status == FALSE ) 
		{
			/* Something failed the validation, we're not going 
			* 	to even try to do any searching, return False 
			*/
			return FALSE;
		}

		// Ok, it seems good, now try and do some real search 
		$status = $this->_perform_sift();
		if( $status == FALSE ) return FALSE;

		die('<pre>hi!'.print_r($this->sift_data,1));
	}


	// --------------------------------------------------------------------

	/* 
	* Actually does ths work of sifting matrix rows
	* Depending on the search settings it'll return
	* different sets based on our inclusiveness options
	* May also return FALSE to indicate it wasn't a 
	* valid search set 
	*/
	private function _perform_sift()
	{
		// Step one - get the various ids we'll need
		$status = $this->_collect_ids();
		if( $status === FALSE ) return FALSE;

		// Step two - check the logical states we want
		$operator = ' LIKE ';

		// Step three - build up the query
		$sql = 'SELECT * FROM exp_matrix_data WHERE field_id = '.$this->ids['matrix_fields'][ $matrix_field ];

		// TEMP
		$cells = array('one'=>1, 'two'=>2);

		$cell_parts = array();
		foreach( $cells as $cell_name => $cell_id )
		{
			$cell_parts[] = ' col_id_'.$cell_id . $operator . '%one% ';
		}

		$sql .= implode( 'AND ', $cell_parts );

		die('<pre>--'.print_R($sql,1));

		/* Basic sql query would look like : 
		
			SELECT * FROM exp_matrix_data 
				WHERE field_id = $matrix_field_id 
				AND col_id_3 LIKE %$value% 
		*/

	}

	// --------------------------------------------------------------------

	/* 
	*  Runs away and try to get corresponding channel_ids, 
	*  Field_ids, cell col_ids for all the passed params
	*  Relies on a peristent cache to speed things up
	*/
	private function _collect_ids()
	{
		// 1. Channel_id
		$channel_id = FALSE;

		if( $this->_isset_and_is_int( 'channel_id', $this->sift_data ))
		{
			$channel_id = $this->sift_data['channel_id'];
		}
		else
		{	
			// Fallback to name

			$channel_name = '';
			// Let them use 'channel', or 'channel_name', or 'channel_short_name' just to be nice
			if( isset( $this->sift_data['channel'] )) $channel_name = $this->sift_data['channel'];
			elseif( isset( $this->sift_data['channel_name'] )) $channel_name = $this->sift_data['channel_name'];
			elseif( isset( $this->sift_data['channel_short_name'] )) $channel_name = $this->sift_data['channel_short_name'];
			elseif( isset( $this->sift_data['channel_shortname'] )) $channel_name = $this->sift_data['channel_shortname'];

			$channel_id = $this->EE->sift_data_model->get_channel_id( $channel_name );	
		}

		// 2. Matrix Field ID
		$matrix_field_id = FALSE;

		if( $this->_isset_and_is_int( 'matrix_field_id', $this->sift_data ))
		{
			$matrix_field_id = $this->sift_data['matrix_field_id'];
		}
		elseif( $this->_isset_and_is_int( 'matrix_id', $this->sift_data ))
		{
			$matrix_field_id = $this->sift_data['matrix_id'];
		}
		elseif( $this->_isset_and_is_int( 'field_id', $this->sift_data ))
		{
			$matrix_field_id = $this->sift_data['field_id'];
		}
		else
		{
			// Fallback to name

			$matrix_field_name = '';

			if( isset( $this->sift_data['matrix_field'] ) ) $matrix_field_name = $this->sift_data['matrix_field'];
			elseif( isset( $this->sift_data['matrix_field_name'] ) ) $matrix_field_name = $this->sift_data['matrix_field_name'];
			elseif( isset( $this->sift_data['matrix_field_short_name'] ) ) $matrix_field_name = $this->sift_data['matrix_field_short_name'];
			elseif( isset( $this->sift_data['matrix_field_shortname'] ) ) $matrix_field_name = $this->sift_data['matrix_field_shortname'];
			elseif( isset( $this->sift_data['field_name'] ) ) $matrix_field_name = $this->sift_data['field_name'];
			elseif( isset( $this->sift_data['field_shortname'] ) ) $matrix_field_name = $this->sift_data['field_shortname'];
			elseif( isset( $this->sift_data['field_short_name'] ) ) $matrix_field_name = $this->sift_data['field_short_name'];

			// Now get the id
			$matrix_field_id = $this->EE->sift_data_model->get_channel_id( $matrix_field_name );	
		}

		// 3. Cells
		// This is trickier than channel and matrix field as we don't know what they'll be called ahead of time

		die('First we need to get the matrix cell ids and names to filter against. Also just relialized Channel shouldnt be a required param, matrix field id is all we needed, especially if the field_groups are shared across channels.');


		$cell_names = array('..'); // @TODO

		/* 
		* Pass over to the data model to get this, 
		* get from cache if required, refresh cache as nessecary
		*/
		$cells 				= $this->EE->sift_data_model->get_cell_ids( $cell_names );

		// Do a little sanity check
		if( $channel_id == FALSE OR $matrix_field_id == FALSE OR empty( $cells ) ) return FALSE;

		$this->ids = array();

		$this->ids['channels'][ $channel ] 				= $channel_id;
		$this->ids['matrix_fields'][ $matrix_field ] 	= $matrix_field_id;
		$this->ids['cells'] 							= $cells;

		return TRUE;
	}

	
	// --------------------------------------------------------------------

	/* 
	* Validates the passed sift data for consistency and 
	* logical validity, ie. we're searching on a real
	* channel and field, there's something to actually do
	*/
	private function _validate_sift_data() 
	{
		$status = FALSE;

		
		/* 
		* In addition to a basic existense check, we need to be sure
		* the channel and matrix field's are really real, but 
		* we'll check that on the fly later while trying 
		* to run the query 
		*/

		// @TODO more validation bits can be added here
		return TRUE;

	}

	// --------------------------------------------------------------------

	/* 
	* Gets any tmpl passed params and assigns them 
	* to our sift_data array
	*/
	private function _check_tmpl()
	{
		$this->sift_data = $this->EE->TMPL->tagparams;
	}

	// --------------------------------------------------------------------
	
	/* 
	* Gets any post passed params, cleans and checks them,
	* and assigns to sift_data array
	*/
	private function _check_post()
	{
		// Nothing to do
		if ( empty($_POST) === TRUE ) return;

		// We can't just use the native ->input->post as it's already been cleared
		$raw = $this->EE->security->xss_clean( $_POST );

		// Pass over to the general cleanup and assign routine
		$this->_clean_and_assign_params( $raw );
	}

	// --------------------------------------------------------------------
	
	/* 
	* Gets any post passed params, cleans and checks them,
	* and assigns to sift_data array
	*/
	private function _check_get()
	{
		// Nothing to do
		if ( empty($_GET) === TRUE ) return;

		// We can't just use the native ->input->get as it's already been cleared
		$raw = $this->EE->security->xss_clean( $_GET );

		// Pass over to the general cleanup and assign routine
		$this->_clean_and_assign_params( $raw );
	}

	// --------------------------------------------------------------------
	
	/* 
	* Takes a raw array of keys and does basic cleanup,
	* used by the post and get param passing
	*/
	private function _clean_and_assign_params( $raw )
	{
		// loop over them and do a quick cleanup
		foreach( $raw as $key => $val )
		{
			// Don't allow overrides from post data (for now)
			if( isset( $this->sift_data[ $key ] ) ) continue;

			$this->sift_data[ $key ] = $val;
		}
	}

	/* 
	* Checks if a key isset and the value is an integer 
	*/
	private function _isset_and_is_int( $needle, $haystack )
	{
		if( isset( $haystack[ $needle ] ) AND is_int( $haystack[ $needle ] )) return TRUE;

		return FALSE;
	}


} // End class

/* End of file Sift_project_model.php */