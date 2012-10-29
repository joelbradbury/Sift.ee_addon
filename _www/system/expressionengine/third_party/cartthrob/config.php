<?php
if ( ! defined('CARTTHROB_VERSION'))
{
	define('CARTTHROB_VERSION', '2.1.3');
}

if (defined('PATH_THEMES'))
{
	if ( ! defined('PATH_THIRD_THEMES'))
	{
		define('PATH_THIRD_THEMES', PATH_THEMES.'third_party/');
	}
	
	if ( ! defined('URL_THIRD_THEMES'))
	{
		define('URL_THIRD_THEMES', get_instance()->config->slash_item('theme_folder_url').'third_party/');
	}
}

$config['name'] = 'CartThrob';
$config['version'] = CARTTHROB_VERSION;
$config['nsm_addon_updater']['versions_xml'] = 'http://cartthrob.com/versions.xml';