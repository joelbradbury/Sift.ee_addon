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
 * Super Search - Config
 *
 * NSM Addon Updater Config File
 *
 * @package 	Solspace:Super Search
 * @author		Solspace DevTeam
 * @filesource	./system/expressionengine/third_party/super_search/config.php
 */

//since we are 1.x/2.x compatible, we only want this to run in 1.x just in case
if (defined('APP_VER') AND APP_VER >= 2.0)
{
	require_once PATH_THIRD . '/super_search/constants.super_search.php';

	$config['name']    								= 'Super Search';
	$config['version'] 								= SUPER_SEARCH_VERSION;
	$config['nsm_addon_updater']['versions_xml'] 	= 'http://www.solspace.com/software/nsm_addon_updater/super_search';
}