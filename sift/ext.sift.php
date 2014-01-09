<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Sift Extension Class
 *
 * @package         sift_ee_addon
 * @version         1.3.2
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/addons/sift
 * @copyright       Copyright (c) 2014, Joel Bradbury
 */

// include config file
include PATH_THIRD.'sift/config'.EXT;

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
		'matrix_data_query',
		'template_post_parse',
		'low_search_pre_search',
		'low_search_post_search'
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
		if( !isset( $this->EE->sift_core_model ) ) Sift_model::load_models();

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


	public function low_search_pre_search($params)
	{
		// Triggered from a low search
		// our search fields will be prefixed with 'sift:'

		$data = $this->EE->sift_core_model->handle_low_search($params);
		$params['sift_searched'] = 'yes';
		$params['sift_data'] = $data;
		//$data = FALSE;
		if( $data !== FALSE )
		{
			if( isset($data['entry_ids']) )
			{
				$params['entry_id'] = implode('|', $data['entry_ids']);
			}
		}

		// @TODO

		return $params;
	}


	public function low_search_post_search($params)
	{
		// @TODO

		return $params;
	}



	public function template_post_parse( &$final, $sub, $site_id )
	{
		if( isset( $this->EE->config->_sifted_vars ) AND !empty( $this->EE->config->_sifted_vars ) )
		{
			foreach( $this->EE->config->_sifted_vars as $key => $val )
			{
				// Blind replace
				$final = str_replace(LD.$key.RD, $val, $final );
			}
		}
		return $final;
	}


	public function channel_entries_query_result( &$that, $query_result )
	{
		if( isset( $that->is_sift ) AND $that->is_sift === TRUE )
		{

			// Drop a marker on the EE object to keep track of this
			// object loop
			$this->EE->is_sift = TRUE;
			$this->EE->sift_items = $that->sift_items;

			// This special handling can be disabled via params, check our marker
			if( !(isset( $that->sift_settings['seperate_rows'] ) AND $that->sift_settings['seperate_rows'] == TRUE ))
			{
				return $query_result;
			}

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
					if( isset( $temp_array[ $row ] ) )
					{
						$clean_array[] = $temp_array[ $row ];
					}
				}

				// Before 2.6.0? We can just return the simple array
				// and let the channel model do it's thing
				if(APP_VER < 2.6) return $clean_array;


				// After 2.6.0 EL 'wisely' replaced the internal parsing logic
				// of the channel model.
				// so all of our neat maniuplation is for naught
				// We'll have to self process here on out.
				$this->self_process($that, $clean_array);
				ee()->extensions->end_script = TRUE;
			}
		}

		return $query_result;
	}

	public function matrix_data_query( $that, $params, $sql, $select_mode)
	{
		if( isset( $that->EE->is_sift ) AND isset( $that->EE->sift->sift_items ))
		{

			// check this is the same matrix field we're sifting on
			if( strpos($sql, 'field_id = '.$this->EE->sift_matrix_id) === FALSE)
			{
				return $this->EE->db->query( $sql );
			}

			$marker = ' ORDER BY ';

			$extra = array();
			foreach( $that->EE->sift->sift_items as $item )
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



	private function self_process($that, $query_result)
	{
		ee()->load->library('channel_entries_parser');
		$parser = ee()->channel_entries_parser->create(ee()->TMPL->tagdata/*, $prefix=''*/);

		$disable = array();

		foreach ($that->enable as $k => $v)
		{
			if ($v === FALSE)
			{
				$disable[] = $k;
			}
		}

		// Relate entry_ids to their entries for quick lookup and then parse
		$entries = array();

		foreach ($query_result as $i => $row)
		{
			unset($query_result[$i]);
			if(isset($entries[$row['entry_id']]) )
			{
				// We have a duplicate
				$entries['-1'.$i.$row['entry_id']] = $row;
			}
			else
			{
				$entries[$row['entry_id']] = $row;
			}
		}

		$data = array(
			'entries'			=> $entries,
			'categories'		=> $that->categories,
			'absolute_results'	=> $that->absolute_results,
			'absolute_offset'	=> $that->pagination->offset
		);

		$config = array(
			'callbacks' => array(
				'entry_row_data'	 => array($that, 'callback_entry_row_data'),
				'tagdata_loop_start' => array($that, 'callback_tagdata_loop_start'),
				'tagdata_loop_end'	 => array($that, 'callback_tagdata_loop_end')
			),
			'disable' => $disable
		);


		$that->return_data = $parser->parse($that, $data, $config);


		unset($parser, $entries, $data);

		if (function_exists('gc_collect_cycles'))
		{
			gc_collect_cycles();
		}

		// Kill multi_field variable
		if (strpos($that->return_data, 'multi_field=') !== FALSE)
		{
			$that->return_data = preg_replace("/".LD."multi_field\=[\"'](.+?)[\"']".RD."/s", "", $that->return_data);
		}

		// Do we have backspacing?
		if ($back = ee()->TMPL->fetch_param('backspace'))
		{
			if (is_numeric($back))
			{
				$that->return_data = substr($this->return_data, 0, - $back);
			}
		}
	}



}
