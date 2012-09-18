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
	private $search_data;

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
		$grouper  = ' OR ';

		// Step three - build up the query
		if( !isset( $this->ids['matrix_field_id'] ) ) return FALSE;		
		$sql = 'SELECT * FROM exp_matrix_data WHERE field_id = '.$this->ids['matrix_field_id'] . ' AND ';


		$cell_parts = array();
		foreach( $this->ids['cells'] as $cell_id )
		{
			$cell_parts[] = ' col_id_'.$cell_id . $operator. '"%'.$this->search_data['cells'][ $cell_id ].'%" ';
		}

		$sql .= ' ('. implode( $grouper, $cell_parts ) . ') ';

		$res = $this->EE->db->query( $sql )->result_array();

		// No results (or bad query)
		if( empty( $res ) ) return FALSE;

		dumper( $res );

	}

	// --------------------------------------------------------------------

	/* 
	*  Runs away and try to get corresponding channel_ids, 
	*  Field_ids, cell col_ids for all the passed params
	*  Relies on a peristent cache to speed things up
	*/
	private function _collect_ids()
	{

		// At a minimum all we need is the matrix field id
		// That can be supplied as either : 
		//  matrix_field_name or matrix_field_id 

		// Any extra filters like channel(s) entry_id(s) status(es) will 
		// be applied via the channel->entries class before we cleanup later
		$matrix_field_id = FALSE;

		if( $this->_isset_and_is_int( 'matrix_field_id', $this->sift_data ))
		{
			$matrix_field_id = $this->sift_data['matrix_field_id'];
		}
		else
		{
			// Fallback to name

			$matrix_field_name = '';

			if( isset( $this->sift_data['matrix_field'] ) ) $matrix_field_name = $this->sift_data['matrix_field'];
			elseif( isset( $this->sift_data['matrix_field_name'] ) ) $matrix_field_name = $this->sift_data['matrix_field_name'];

			// Now get the id
			$matrix_field_id = $this->EE->sift_data_model->get_matrix_id( $matrix_field_name );	
		}

		if( $matrix_field_id === FALSE ) return FALSE;


		// Now get the cell column names and ids for this matrix field
		$possible_cells = $this->EE->sift_data_model->get_cells_for_matrix( $matrix_field_id );
		if( $possible_cells === FALSE )
		{
			// This isn't a valid matrix field, or there are no cells to search on, either way, rebuff
			return FALSE;
		}

		// Now we can cleanup and just get the search data for the cells that are possible
		$passed_cells = array();
		foreach( $possible_cells as $cell_name => $cell_id )
		{
			// Is this cell assigned as an id?
			if( isset( $this->sift_data[ 'col_id_'.$cell_id ] ) )
			{
				$passed_cells[ $cell_id ] = $this->sift_data['col_id_'.$cell_id];
			}
			elseif( isset( $this->sift_data[ $cell_name ] ) )
			{
				$passed_cells[ $cell_id ] = $this->sift_data[ $cell_name ];
			}

		}

		// Nothing to do
		if( empty( $passed_cells ) ) return FALSE;



		$this->ids['matrix_field_id']				= $matrix_field_id;
		$this->ids['cells']							= array_keys( $passed_cells );

		$this->search_data['cells'] 				= $passed_cells;

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