<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift helper class
 *
 * @package         sift_ee_addon
 * @version         1.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2013, Joel Bradbury
 */

// --------------------------------------------------------------------

/**
 * Object to Array
 *
 * From a multi-dimensional object return a 
 * usable multi-dimensional array
 *
 * @param      array
 * @param 	   bool 
 * @return     array
 */
if ( ! function_exists('Sift_obj_to_array'))
{
	function Sift_obj_to_array($obj, $clean = FALSE, $convert = array() ) 
	{

	    if(is_object($obj)) $obj = (array) $obj;

	    if(is_array($obj)) {

	        $new = array();

	        foreach($obj as $key => $val) {

	        	if( $clean ) 
	        	{
		        	$key = str_replace( '-', '_', $key );

		        	if( isset( $convert[ $key ] ) ) $key = $convert[ $key ];
		        }

	            $new[$key] = Sift_obj_to_array($val, $clean);
	        }
	    }
	    else $new = $obj;

	    return $new;
	}
}

/**
 * Debug
 *
 * @param       mixed
 * @param       bool
 * @return      void
 */
if ( ! function_exists('dumper'))
{
	function dumper($var, $exit = TRUE)
	{
		echo '<pre>'.print_r($var, TRUE).'</pre>';

		if ($exit) exit;
	}
}

// --------------------------------------------------------------

/* End of file Sift_helper.php */