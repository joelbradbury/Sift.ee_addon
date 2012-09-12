<?php

/**
 * Sift Config File *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */

if ( ! defined('SIFT_NAME'))
{
	define('SIFT_NAME',         'Sift');
	define('SIFT_CLASS_NAME',   'Sift');
	define('SIFT_VERSION',      '0.1');
	define('SIFT_DOCS',         '##');
	define('SIFT_DEBUG',        TRUE);
	define('SIFT_CACHE_TIME',    900);

	define('SIFT_PREFERENCES', 'pref_one|pref_two' );


}

$config['name']    = SIFT_NAME;
$config['version'] = SIFT_VERSION;
