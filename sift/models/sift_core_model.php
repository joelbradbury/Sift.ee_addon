<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Core Model class
 *
 * @package         sift_ee_addon
 * @version         1.0
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */
class Sift_core_model extends Sift_model {

	private $sift_data;
	private $ids;
	private $search_data;
	public 	$result_data;
	private $tagdata;
	private $matrix_field_name;
	private $seperate_matrix_row_limit = 25;
	private $specials = array(	'category', 
								'category_group_',
								'limit', 
								'sort',
								'loose_ends', 
									'loose_ends_on', 
									'loose_ends_off');
	private $force_single_matrix_rows = FALSE;

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

	public function setup_matrix_cell_data( $matrix_field, $tagdata )
	{
		$data = array('tagdata' => $tagdata );

		// Check the field passed is valid
		$matrix_field_id = $this->EE->sift_data_model->get_matrix_id( $matrix_field );	
		if( $matrix_field_id === FALSE ) return FALSE;

		// Now get the cells and possible values
		$cells = $this->EE->sift_data_model->get_cells_for_matrix( $matrix_field_id );

		// Setup a blank data array for blank population
		$blank = array();
		foreach( $cells as $cell_name => $cell_id )
		{
			$blank[ $cell_name ] = '';
			$blank[ 'col_id_'.$cell_id ] = '';

			
			if ( preg_match("|" . LD . $cell_name . RD . "(.*)". LD . '/' . $cell_name . RD . "|s", $tagdata, $match))
			{
				// There is a match!
				// Kick into collection and population mode
				$data['options'][ $cell_name ] = $this->EE->sift_data_model->get_cell_possible_values( $cell_id );
			}		

		}
		$data['blanks'] = $blank;

		if( isset( $data['options'] ) AND is_array( $data['options'] ) )
		{
			$vars = array();

			// We have sub-options, we need to parse this in the tagdata
			foreach( $data['options'] as $cell_name => $options )
			{
				// Find the matching markers in the tagdata					
				if ( preg_match("|" . LD . $cell_name . RD . "(.*)". LD . '/' . $cell_name . RD . "|s", $tagdata, $match))
				{
					// Build up the data array and pass over to native parse_variables
					foreach( $options as $option ) 
					{
						$vars[ $cell_name ][] = array( 'value' => $option );
					}

					$tagdata = $this->EE->TMPL->parse_variables( $tagdata, array($vars) );
				}	
			}
		}

		$data['tagdata'] = $tagdata;

		return $data;
	}


	// --------------------------------------------------------------

	public function handle_get_post()
	{	
		$this->sift_data = array();

		$this->_check_tmpl();
		$this->_check_post();
		$this->_check_get();
		$this->_check_cookie();


		// Clean it up for now
		foreach( $this->sift_data as $key => $val ) 
		{
			if( is_array( $val ) ) unset( $this->sift_data[ $key ] );
		}

		return $this->sift_data;
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
		// $this->check_cache();

		$status = $this->_validate_sift_data();
		if( $status == FALSE ) 
		{

			/* Something failed the validation, we're not going 
			* 	to even try to do any searching, return False 
			*/
			return FALSE;
		}

		// Log these search terms as cookies for the user
		$this->_save_params();

		// Ok, it seems good, now try and do some real search 
		$status = $this->_perform_sift();
		if( $status == FALSE ) return FALSE;

		// The result data should be in $result_data, do a quick sanity check 
		if( empty( $this->result_data ) ) return FALSE;

		// Now, actually use the data for something we need
		$status = $this->_parse_results();
		if( $status == FALSE ) return FALSE;

		return $this->tagdata;
	}

	private function _save_params()
	{
		$tmpl_params = $this->EE->TMPL->tagparams;

		$passed = array_diff( $this->sift_data, $this->EE->TMPL->tagparams );
		if( empty( $passed ) ) return;

		// Drop each of these into it's own cookie
		foreach( $passed as $key => $val )
		{
			$data['name'] = $key;
			$data['value'] = $val;
			$this->EE->sift_cookie_model->set( $data );
		}

		// done
		return;
	}


	
	// --------------------------------------------------------------------

	/* 
	* Does the hard work of taking a result set, cleaning and passing
	* over to the channel model, then cleaning and returning tag data
	* to be output by the front-end tags
	*/
	private function _parse_results()
	{
		if( empty( $this->result_data ) ) return FALSE;

		$entry_ids = array();
		foreach( $this->result_data as $row )
		{
			$entry_ids[] = $row['entry_id'];
		}


		// We need to cleanup and add some magic dust to the matrix pair
		// check for the logic and flags first though
		$this->_sift_matrix_tagdata();

		$this->tagdata = $this->_pass_to_channel( $entry_ids, $this->result_data );

		$this->_add_variables();

		return TRUE;
	}

	private function _add_variables()
	{
		$vars['total_rows'] = count( $this->result_data );

		$tmp = array();
		foreach( $this->result_data as $row )
		{
			$tmp[ $row['entry_id'] ] = 1;
		}

		$vars['total_unique_results'] = count( $tmp );

		$this->tagdata = $this->EE->TMPL->parse_variables( $this->tagdata, array( $vars ), FALSE );

		return;
	}


	// --------------------------------------------------------------------

	private function _sift_matrix_tagdata()
	{

		if( $this->force_single_matrix_rows === FALSE ) return;

		// Do we actually _need_ to do anything?
		/* 
			* this only needs to kick in when we have the condition
			* that there is at least one entry with multiple 
			* matrix rows in the result set 
		*/

		$clean = FALSE;
		$tmp = array();
		foreach( $this->result_data as $row )
		{
			if( isset( $tmp[ $row['entry_id'] ] ) ) 
			{
				$clean = TRUE;
				continue;
			}

			$tmp[ $row['entry_id'] ] = TRUE;
		}

		if( $clean == FALSE ) return;

		// This becomes expensive quick. so we'll only allow it when
		// the local conditions allow. 
		if( count( $this->result_data ) > $this->seperate_matrix_row_limit ) return;

		// ok, we have something to actually do. This get's tricky fast
		// we're tricking the matrix data to look like they're single rows
		// for each duplicate entry by wrapping them in simple conditionals.
		$tagdata = $this->EE->TMPL->tagdata;

		// Get just the matrix field data from the tagdata
		$matrix_field_name = $this->matrix_field_name;
		preg_match( "/" . LD . $matrix_field_name .'(.*)'. RD . "/s", $tagdata, $matches);

		$master = $matches[0];
		$golden = '';
		// As they'd say on MTV Cribs, this is where the magic happens

		$cond_tmp = array();

		foreach( $this->result_data as $key => $row )
		{
			$i = $key + 1;
			$cond_tmp[] = 'count=='.$i.' AND row_id=='.$row['row_id'];
		}

		// Build the conditional
		$cond = LD.'if ('.implode( ') OR (', $cond_tmp ).')'.RD;

		// Drop an {if ..} inside the wrapping matrix_field markers
		$old_start 		= LD.$matrix_field_name.RD;
		$old_end		= LD.'/'.$matrix_field_name.RD;

		$cond_end 	= LD.'/if'.RD.' ';

		$master_row 	= str_replace( $old_start, $old_start.$cond, $master);
		$golden 	= str_replace( $old_end, $cond_end.$old_end, $master_row);


		// Magic!
		// Now replace the orginal master with our new golden master
		$tagdata = str_replace( $master, $golden, $tagdata );

		$this->EE->TMPL->tagdata = $tagdata;

		return TRUE;
	}

	// --------------------------------------------------------------------

	private function _pass_to_channel( $entry_ids = array(), $items = array() ) 
	{
		if ( class_exists('Channel') === FALSE )
		{
			require PATH_MOD.'channel/mod.channel'.EXT;
		}

		$settings = array('seperate_rows' => $this->force_single_matrix_rows );

		$channel = new Channel;

		$this->EE->TMPL->tagparams['limit'] = '25';

		// Allow our specials to override
		foreach( $this->specials as $key )
		{
			if( isset( $this->sift_data[ $key ] ) AND $this->sift_data[ $key ] != '' )
			{
				$this->EE->TMPL->tagparams[ $key ] = $this->sift_data[ $key ];
			}
		}

		$this->EE->TMPL->tagparams['dynamic'] = FALSE;
		$this->EE->TMPL->tagparams['entry_id'] = implode( '|', $entry_ids);
		$this->EE->TMPL->tagparams['fixed_order'] = implode( '|', $entry_ids);
		$this->EE->TMPL->tagparams['dynamic_parameters'] = FALSE;
		unset( $this->EE->TMPL->tagparams['orderby'] );

		// Add our markers to the channel object, so that the sift ext
		// can jump in later and clean this up a bit
		$this->EE->is_sift = TRUE;
		$channel->is_sift = TRUE;

		$this->EE->sift->sift_items = $items;
		$channel->sift_items = $items;

		$this->EE->sift->sift_order = $entry_ids;
		$channel->sift_order = $entry_ids;

		$this->EE->sift->settings = $settings;
		$channel->sift_settings = $settings;

		$t = $channel->entries();

		return $t;
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

		if( $this->check_cache('perform_sift', $this->sift_data, 'result_data' ) === FALSE )
		{
			// Step two - check the logical states we want
			$operator 		= ' LIKE ';
			$grouper  		= ' AND ';
			$subgrouper  	= ' AND ';

			// Allow the loose ends to be overridden
			$loose 			= '%';
			if( isset( $this->sift_data['loose_ends'] ) AND $this->check_no( $this->sift_data['loose_ends'] ) )
			{
				$loose = '';
			}
			$loose_ends_on = array();
			$loose_ends_off = array();
			if( isset( $this->sift_data['loose_ends_on'] ) )
			{
				$loose_ends_on = explode( '|', $this->sift_data['loose_ends_on'] );
			}

			if( isset( $this->sift_data['loose_ends_off'] ) )
			{
				$loose_ends_off = explode( '|', $this->sift_data['loose_ends_off'] );
			}


			// Step three - build up the query
			if( !isset( $this->ids['matrix_field_id'] ) ) return FALSE;		
			$sql = 'SELECT * FROM exp_matrix_data WHERE field_id = '.$this->ids['matrix_field_id'] . ' AND ';


			$cell_parts = array();
			foreach( $this->ids['cells'] as $cell_id )
			{
				if( is_array( $this->search_data['cells'][ $cell_id ] ) )
				{
					$tmp = array();
					// Arrays get the sub-group treatment
					foreach( $this->search_data['cells'][ $cell_id ] as $cell )
					{	
						if( trim( $cell ) != '' ) 
						{

							// Per cell, loose on/off also
							if( $loose == '' )
							{
								if( in_array( $this->ids['cell_ids'][ $cell_id ], $loose_ends_on ) ) $loose = '%';
							}
							elseif( in_array( $this->ids['cell_ids'][ $cell_id ], $loose_ends_off ) ) $loose = '';

							$tmp[] = ' col_id_'.$cell_id . $operator. '"'.$loose.$cell.$loose.'" ';
						}
					}

					// Implode and group the sub-group
					if( count( $tmp > 0 ) )	$cell_parts[] = ' ( ' . implode( $subgrouper, $tmp ) . ' ) ';

				}
				else
				{
					if( trim( $this->search_data['cells'][ $cell_id ] ) != '' ) 
					{
						// Per cell loose ends on/off
						if( $loose == '' )
						{
							if( in_array( $this->ids['cell_ids'][ $cell_id ], $loose_ends_on ) ) $loose = '%';
						}
						elseif( in_array( $this->ids['cell_ids'][ $cell_id ], $loose_ends_off ) ) $loose = '';


						$cell_parts[] = ' col_id_'.$cell_id . $operator. '"'.$loose.$this->search_data['cells'][ $cell_id ].$loose.'" ';
					}
				}
			}

			$sql .= ' ('. implode( $grouper, $cell_parts ) . ') ';

			// Add orderby and sort params
			if( isset( $this->sift_data['orderby'] ) AND $this->sift_data['orderby'] != '' )
			{
				$sort = 'asc';

				$sort_order = explode(":", $this->sift_data['orderby'] );
				if( count( $sort_order > 1 ) )
				{
					if( strtolower($sort_order[1]) == 'desc' ) $sort = 'desc';
				}

				// Allow the sort param to directly override this
				if( isset( $this->sift_data['sort'] ) )
				{
					if( $this->sift_data['sort'] == 'asc' ) $sort = 'asc';
					elseif( $this->sift_data['sort'] == 'desc' ) $sort = 'desc';
				}


				// Allow multiple sort fields, seperated by pipes
				$sort_fields = explode( "|", $this->sift_data['orderby'] );
				$sort_tmp = array();

				foreach( $sort_fields as $col_name )
				{
					if( isset( $this->ids['cell_names'][ $col_name ] ) )
					{
						$sort_tmp[] = 'col_id_'.$this->ids['cell_names'][ $col_name ];
					}
				}

				if( !empty( $sort_tmp ) )
				{
					$sql .= ' ORDER BY ' . implode(", ", $sort_tmp) . " " . $sort;
				}
			}

			$res = $this->EE->db->query( $sql )->result_array();


			// No results (or bad query)
			if( empty( $res ) ) return FALSE;


			// Great! We have some actual matrix rows
			// Now we need to actually pass them back up to the channel model
			// and do something useful with them
			// also cache perhaps

			$this->result_data = $res;

			// Write to the cache
			$this->write_cache('perform_sift',$this->sift_data, $this->result_data );
		}

		return TRUE;
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
			$this->matrix_field_name = $this->EE->sift_data_model->get_matrix_field_name( $matrix_field_id );	
		}
		else
		{
			// Fallback to name
			$matrix_field_name = '';

			if( isset( $this->sift_data['matrix_field'] ) ) $matrix_field_name = $this->sift_data['matrix_field'];
			elseif( isset( $this->sift_data['matrix_field_name'] ) ) $matrix_field_name = $this->sift_data['matrix_field_name'];

			$this->matrix_field_name = $matrix_field_name;

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

		// Cache these cell names
		$this->ids['cell_names'] = $possible_cells;
		$this->ids['cell_ids'] = array_flip($possible_cells);

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
		$has_val = FALSE;
		foreach( $passed_cells as $cell_val )
		{
			if( trim( $cell_val ) != '' ) $has_val = TRUE;
		}
		if( $has_val === FALSE ) return FALSE;



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

		// Overrides for the default settings
		if( isset( $this->sift_data[ 'seperate_view' ] ) )
		{
			if( $this->check_yes( $this->sift_data['seperate_view'] ) )
			{
				$this->force_single_matrix_rows = TRUE;
			}
		}

		/*
		*
		* We let users pass category groups in seperate fields
		* by using the field name category_group_# , or category_group_#_#_#
		*
		* We need to split these out here, and drop them into the combined 
		* category param for later passing over to the channel model
		*/
		function category_group_split( $var )
		{
			if( strpos( $var, 'category_group_' ) > -1 ) return $var;
		}

		$cats = array_filter( array_keys( $this->sift_data ), 'category_group_split' ); 
		$cats = array_intersect_key( $this->sift_data, array_flip( $cats ) );
		
		if( !empty( $cats ) ) 
		{
			// We have some cats, re-assign 
			$current = array();
			if( isset( $this->sift_data['category'] ) ) $current[] = $this->sift_data['category'];
			$current = array_merge( $current, $cats );

			$tmp = implode( '&', $current );
			if( $tmp != '' ) $this->sift_data['category'] = $tmp;
		}

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

	
	private function _check_cookie()
	{
		$all = $this->EE->sift_cookie_model->all();

		if( empty( $all ) ) return;

		$this->_clean_and_assign_params( $all );
	}

	// ----------------
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

		// Also have a look for any special parameters
		foreach( $this->specials as $key )
		{
			if( isset( $raw[ $key ] ) )
			{					
				// Don't allow overrides from post data (for now)
				if( isset( $this->sift_data[ $key ] ) ) continue;

				$this->sift_data[ $key ] = $raw[ $key ];
			}
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