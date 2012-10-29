<?php

// Setup the environment
if(!defined('NSM_ENV')) {
    define('NSM_SERVER_NAME', $_SERVER['SERVER_NAME']);
    define('NSM_BASEPATH', dirname(__FILE__));
    define('NSM_SYSTEM_FOLDER', 'system');

    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") { 
        define('NSM_SITE_URL', "http://".NSM_SERVER_NAME);
    } else {
        define('NSM_SITE_URL', "https://".NSM_SERVER_NAME);
    }

    // Set the environment
    if ( strstr( NSM_SERVER_NAME, '.loc' ) ) define('NSM_ENV', 'local');
    elseif( strstr( NSM_SERVER_NAME, 'sift.' ) ) define('NSM_ENV', 'development');
    else define('NSM_ENV', 'production');
}

// Define the environment settings

$env_config = array();
$env_db_config = array();
$env_global_vars = array();

// Set the environmental config and global vars
if (NSM_ENV == 'local') { 
    $env_db_config = array(
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => 'root',
        'database' => 'sift.loc',
    );
}
elseif(NSM_ENV == 'development') {
    $env_db_config = array(
        'hostname' => 'internal-db.s129826.gridserver.com',
        'username' => 'db129826',
        'password' => 'tom3if4owg9ab4efs5yef6hin6yauf9g',
        'database' => 'db129826_sift_squarebit_co_uk',
    );
}
else {
    $env_db_config = array(
        'hostname' => '',
        'username' => '',
        'password' => '',
        'database' => '',
    );
}

// Config bootsrap... GO!
if(isset($config)) {


    // Make this global so we can add some of the config variables here
    global $assign_to_config;
    $env_global_vars = array();
    $default_global_vars = array();

    if(!isset($assign_to_config['global_vars']))
        $assign_to_config['global_vars'] = array();

    $env_config['tmpl_file_basepath'] = NSM_BASEPATH . '/templates/';

    $default_global_vars['uie_date_format'] = '%Y-%m-%d';
  
    $assign_to_config['global_vars'] = array_merge($assign_to_config['global_vars'], $default_global_vars, $env_global_vars);

  /**
     * Config. This shouldn't have to be changed if you're using the Newism EE2 template.
     */
    $default_config = array(

        // Theme preferences
        'theme_folder_path' => NSM_BASEPATH . '/../../../themes/',
        'theme_folder_url' => NSM_SITE_URL . '/themes/',

    );


    // Build the new config object
    $config = array_merge($config, $default_config, $env_config);
}

// DB bootsrap... GO!
if(isset($db['expressionengine']))
{
    $default_db_config = array("cachedir" => APPPATH . "cache/db_cache/");
    $db['expressionengine'] = array_merge($db['expressionengine'], $default_db_config, $env_db_config);
}