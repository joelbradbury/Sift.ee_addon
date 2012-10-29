<?php if ( ! defined('EXT')) exit('No direct script access allowed');

 /**
 * Solspace - Super Search
 *
 * @package		Solspace:Super Search
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2009-2012, Solspace, Inc.
 * @link		http://www.solspace.com/docs/addon/c/Super_Search/
 * @version		2.0.4
 * @filesource	./system/expressionengine/third_party/super_search/
 */
 
 /**
 * Super Search - Module Class - Actions
 *
 * Handles All Form Submissions and Action Requests
 *
 * @package		Solspace:Super Search
 * @author		Solspace DevTeam
 * @filesource	./system/expressionengine/third_party/super_search/act.super_search.php
 */

require_once 'addon_builder/addon_builder.php';

class Super_search_actions extends Addon_builder_super_search
{
	var $errors				= array();
	
	var $module_preferences = array();
    
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 
	 * @access	public
	 * @return	null
	 */
    
	function Super_search_actions()
    {    	
    	parent::Addon_builder_super_search('super_search');
    	
		ee()->lang->loadfile('super_search');
	}
	
	//	END
	
	// --------------------------------------------------------------------

	/**
	 * Clear cache
	 *
	 * Clear cache for a given site
	 *
	 * @access	public
	 * @return	bool
	 */
    
    function clear_cache ()
    {		
		do
		{
			ee()->db->query(
				"DELETE FROM exp_super_search_cache
				WHERE site_id = ".ee()->config->item( 'site_id' )."
				LIMIT 1000 /* Super Search act.super_search.php clear_cache() */"
			);
		} 
		while ( ee()->db->affected_rows() == 1000 );
		
		do
		{			
			ee()->db->query(
				"DELETE FROM exp_super_search_history
				WHERE site_id = ".ee()->config->item( 'site_id' )."
				AND saved = 'n'
				AND cache_id NOT IN (
					SELECT cache_id
					FROM exp_super_search_cache
				)
				LIMIT 1000 /* Super Search act.super_search.php clear_cache() clear history */"
			);
		} 
		while ( ee()->db->affected_rows() == 1000 );
		
		return TRUE;
    }
    
	//	End clear cache

	// --------------------------------------------------------------------

	/**
	 *	Database Character Set Switch
	 *
	 *	Used because the EE 1.x database was not UTF-8, which was causing a problem with international character support.  EE 2.x is magically delicious and UTF-8
	 *
	 *	@access		public
	 *	@param		string
	 *	@return		null
	 */

	public function db_charset_switch($type = 'utf-8')
	{
		if (APP_VER >= 2.0)
		{
			return;
		}
	
		if ( ! isset($this->cache['character_set_client']))
		{
			$query = ee()->db->query("SHOW VARIABLES");

			foreach($query->result_array() as $row)
			{
				if ($row['Variable_name'] == 'character_set_client')
				{
					$this->cache['character_set_client'] = $row['Value'];
				}
				
				if ($row['Variable_name'] == 'collation_connection')
				{
					$this->cache['collation_connection'] = $row['Value'];
				}
			}
		}

		if ($this->cache['character_set_client'] == 'utf8')
		{
			$this->current_char_set = 'utf-8';
			
			return;
		}

		if (strtolower($type) == 'default')
		{
			@mysql_query(
				"SET NAMES `" . 
					ee()->db->escape_str($this->cache['character_set_client']) . 
					"` COLLATE `" . ee()->db->escape_str($this->cache['collation_connection']) . "`", 
				ee()->db->conn_id
			);
			
			$this->current_char_set = $this->cache['character_set_client'];
		}
		else
		{
			@mysql_query("SET NAMES `utf8` COLLATE `utf8_general_ci`", ee()->db->conn_id);
			
			$this->current_char_set = 'utf-8';
		}
	}
	
	// End DB UTF-8 Switch

	// --------------------------------------------------------------------

	/**
	 *  Get the Preferences for This Module
	 *
	 * @access	public
	 * @return	array
	 */
	
	function module_preferences()
	{
	}
	
	//	END module_preferences()
}

/* END Class */