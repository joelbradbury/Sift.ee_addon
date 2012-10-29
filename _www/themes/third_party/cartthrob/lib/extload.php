<?php
/* 
* if you've moved or renamed the system folder, set the location here
* relative to the location of the EE themes folder (where this file is located)
* on most systems this is simply "system"
* For instance: if you've moved your system folder one level up, change to "../system"
*
* typical default EE folder setup is as follows: 

admin.php
images
index.php
system
themes
etc...

*/

$EE_system_folder = "../ee_system"; 
$EE_themes_folder = "themes"; 

// you can put in the absolute server URL for your EE system if you experience problems
// example: /usr/var/www/htdocs/system

//$EE_absolute_server_url = '';


/////////////////////////// END OF CONFIGURATION //////////////////////////////////
// getting the $_GET information and then deleting it, so that EE doesn't choke on IP addresses and other nasties. 
$get = array(); 
if (isset($_GET))
{
	$get = $_GET; 
	unset($_GET); 
}

// Getting the query string, and turning it into an array
parse_str($_SERVER['QUERY_STRING'], $query_string);

// find the location of EE based on what we have available 
if ( ! empty($EE_absolute_server_url))
{
	$system_location = $EE_absolute_server_url;
}
else
{
	$current_location = substr(__FILE__,0,strrpos(__FILE__,'/'));
	$system_location = substr($current_location, 0,  strrpos($current_location, $EE_themes_folder.'/third_party/cartthrob/lib') ).$EE_system_folder; 
}
// check the path of the system to make sure it's set right. 
if (realpath($system_location) !== FALSE)
{
	$system_location = realpath($system_location).'/';
}
$system_location = rtrim($system_location, '/').'/';

// set all of the default variables and load up all of the required core files.
define('BASEPATH', str_replace("\\", "/", $system_location.'codeigniter/system/'));
define('APPPATH', $system_location.'expressionengine/');
define('SYSDIR', trim(strrchr(trim(str_replace("\\", "/", $system_location), '/'), '/'), '/'));
define('CI_VERSION', '2.0');
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('FCPATH', str_replace(SELF, '', __FILE__));
define('EXT', '.php');
define('UTF8_ENABLED', FALSE);
define('DEBUG',FALSE);


if (!is_file(BASEPATH.'core/Common'.EXT) || 
	!is_file(APPPATH.'config/constants'.EXT) ||
	!is_file(BASEPATH.'core/Controller'.EXT))
	{
		die("CartThrob ecommerce could not find necessary files required to complete this transaction. Please update the system folder location in the extload.php library."); 
	}
require BASEPATH.'core/Common'.EXT;
require APPPATH.'config/constants'.EXT;

$CFG	=& load_class('Config', 'core');
$URI	=& load_class('URI', 'core');
$IN		=& load_class('Input', 'core');	
$OUT	=& load_class('Output', 'core');
$LANG	=& load_class('Lang', 'core');
$SEC	=& load_class('Security', 'core');

$loader = load_class('Loader', 'core');

require BASEPATH.'core/Controller'.EXT;
function &get_instance()
{
    return CI_Controller::get_instance();
}
class EE_ext_loader extends CI_Controller {}
$EE = new EE_ext_loader;
// get the URI information
$EE->uri->_fetch_uri_string();
$EE->uri->_remove_url_suffix();
$EE->uri->_explode_segments();

// add info about cartthrob
$EE->load->add_package_path(PATH_THIRD.'cartthrob/');
$EE->load->library('cartthrob_loader');
$EE->load->library('cartthrob_payments');

// the gateway is the first (zero) segment. We're getting that...  need it to load the gateway
$gateway = $EE->uri->segment(0);

// these are optional, and may not need them, but we're capturing them anyway just in case
// currently anything more than the first 3 segments are ignored. More than that should be passed 
// via post variables or get variables
$post['ct_gateway'] = $gateway; 
$post['ct_action']  = $EE->uri->segment(1); 
$post['ct_option'] = $EE->uri->segment(2); 

if (!$gateway)
{
	die("No gateway was specified"); 
}
$EE->cartthrob_payments->set_gateway($gateway); 

// conglomerate all of the query and post data
$vars = array_merge($query_string, $post, $_POST, $get); 

// only calling one specific method, so that it's not easy to just take over the system
// if this method exists, it should handle its own security. 
if (method_exists($EE->cartthrob_payments->gateway(), "extload"))
{
	$EE->cartthrob_payments->gateway()->extload($vars);
}
else
{
	die('Response method for gateway '.$gateway.' does not exist'); 
}

