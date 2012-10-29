<?php if ( ! defined('EXT')) exit('No direct script access allowed');

/**
 * Super Search Code Pack
 *
 * @package 	Solspace:Super Search Code Pack
 * @author		Solspace DevTeam
 * @copyright	Copyright (c) 2011, Solspace, Inc.
 * @link		http://www.solspace.com/docs/
 * @version		1.0.0
 * @filesource 	./system/extensions/super_search_code_pack/
 */
 
 /**
 * Super Search Code Pack - Constants
 *
 * @package 	Solspace:Super Search Code Pack
 * @author		Solspace Dev Team
 * @filesource 	./system/extensions/super_search_code_pack/ext.super_search_code_pack.php
 */
 
require_once 'ext.super_search_code_pack.base.php';

if (APP_VER < 2.0)
{
	eval('class Super_search_code_pack extends Super_search_code_pack_extension_base { }');
}
else
{
	eval('class Super_search_code_pack_ext extends Super_search_code_pack_extension_base { }');
}
?>