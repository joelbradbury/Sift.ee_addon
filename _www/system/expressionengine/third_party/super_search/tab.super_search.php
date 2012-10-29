<?php if ( ! defined('EXT') ) exit('No direct script access allowed');

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
 * Super Search - Module Class - Tabs
 *
 * Placeholder for now
 *
 * @package		Solspace:Super Search
 * @author		Solspace DevTeam
 * @filesource	./system/expressionengine/third_party/super_search/tab.super_search.php
 */

require_once 'addon_builder/module_builder.php';

class Super_search_tab extends Module_builder_super_search
{
	// --------------------------------------------------------------------

	/**
	 *	Constructor
	 *
	 *	@access		public
	 *	@return		null
	 */
	
	public function __construct()
	{
		parent::Module_builder_super_search('super_search');
	}
	/* END constructor */
	
	// --------------------------------------------------------------------

	/**
	 *	Publish Tabs
	 *
	 *	Creates the fields that will be displayed on the Publish page for EE 2.x
	 *
	 *	@access		public
	 *	@param		integer
	 *	@param		integer
	 *	@return		array
	 */

	public function publish_tabs($channel_id, $entry_id = '')
	{
		$settings = array();
		
		// @bugfix - EE 2.x on submit of an entry calls this method with incorrect arguments
		if (is_array($channel_id))
		{
			$entry_id	= $channel_id[1];
			$channel_id	= $channel_id[0];
		}

		return $settings;
	}
	/* END publish_tabs() */
	
	// --------------------------------------------------------------------

	/**
	 *	Validate Submitted Publish data
	 *
	 *	Allows you to validate the data after the publish form has been submitted but before any 
	 *	additions to the database. Returns FALSE if there are no errors, an array of errors otherwise.
	 *
	 *	@access		public
	 *	@param		array
	 *	@return		bool|array
	 */

	public function validate_publish($params)
	{
		return FALSE;
	}
	/* END validate_publish() */
}
/* END Tag_tab CLASS */

/* End of file tab.tag.php */
/* Location: ./system/expressionengine/third_party/modules/tag/tab.tag.php */
?>