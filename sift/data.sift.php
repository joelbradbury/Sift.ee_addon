<?php if ( ! defined('EXT')) exit('No direct script access allowed');

 
// include config file
include PATH_THIRD.'sift/config'.EXT;

/**
 * Sift Data Class
 *
 * @package         sift_ee_addon
 * @version         0.1
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2012, Joel 
 */


class Sift_data
{
	
	/**
	 * Set preference
	 *
	 * @access	public
	 * @return	array
	 */
    
	function set_preference( $preferences = array(), $site_id = '1' )
    {    
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
    
 		/** --------------------------------------------
        /**	Grab prefs from DB
        /** --------------------------------------------*/
        
        if( $site_id == '' ) $site_id = ee()->config->item('site_id');

        $sql	= "SELECT site_system_preferences
					FROM exp_sites
					WHERE site_id = " . ee()->db->escape_str( $site_id );
        	
        $query	= ee()->db->query( $sql );

        if ( $query->num_rows() == 0 ) return FALSE;
        
        ee()->load->helper('string');
 		
 		$this->cached[$cache_name][$cache_hash] = unserialize( base64_decode( $query->row('site_system_preferences') ) ); 
 		

 		/** --------------------------------------------
        /**	Add our prefs
        /** --------------------------------------------*/
        
        $prefs	= array();
        
        foreach ( explode( "|", Sift_PREFERENCES ) as $val )
        {
        	if ( isset( $preferences[$val] ) === TRUE )
        	{
        		$this->cached[$cache_name][$cache_hash][$val]	= $preferences[$val];
        	}
        }
			

		$prefs = base64_encode( serialize( $this->cached[$cache_name][$cache_hash] ) );
		

		ee()->db->query( ee()->db->update_string(
					'exp_sites',
					array(
						'site_system_preferences' => $prefs
					),
					array(
						'site_id'	=> ee()->db->escape_str( $site_id )
					)
				)
			);

		return TRUE;
	}
	
	/* End set preference */

	// --------------------------------------------------------------------
	
	/**
	 * Repload Preferences
	 *
	 * @access	public
	 * @return	array
	 */
    
	function reload_preferences()
    {    
 		/** --------------------------------------------
        /**  Prep Cache, Return if Set
        /** --------------------------------------------*/
 		
 		$cache_name = __FUNCTION__;
 		$cache_hash = $this->_imploder(func_get_args());
 		
 		if (isset($this->cached[$cache_name][$cache_hash]))
 		{
 			return $this->cached[$cache_name][$cache_hash];
 		}
 		
 		$this->cached[$cache_name][$cache_hash] = array();
    
 		/** --------------------------------------------
        /**	Grab prefs from DB
        /** --------------------------------------------*/

        $sql	= "SELECT site_system_preferences
					FROM exp_sites
					WHERE site_id = " . ee()->db->escape_str( ee()->config->item('site_id') );
        	
        $query	= ee()->db->query( $sql );

        if ( $query->num_rows() == 0 ) return FALSE;
        
        ee()->load->helper('string');
 		
 		$this->cached[$cache_name][$cache_hash] = unserialize( base64_decode( $query->row('site_system_preferences') ) ); 
 		

 		return $this->cached[$cache_name][$cache_hash];
	}
	
	/* End set preference */

	// --------------------------------------------------------------------
	


}

// End of file data.Sift.php