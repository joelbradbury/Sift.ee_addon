<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Sift Cookie Model class
 *
 * @package         sift_ee_addon
 * @version         1.2.0
 * @author          Joel Bradbury ~ <joel@squarebit.co.uk>
 * @link            http://squarebit.co.uk/sift
 * @copyright       Copyright (c) 2013, Joel Bradbury
 */

class Sift_cookie_model extends Sift_model {

	private $prefix = 'sift_';
	private $default_lifespan = '31557600'; // 1 year
	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access      public
	 * @return      void
	 */
	function __construct()
	{
		// Call parent constructor
		parent::__construct();
	}

	// --------------------------------------------------------------------

	/**
	 * Installs given table
	 *
	 * @access      public
	 * @return      void
	 */
	public function install()
	{
		// Nothing to install
	}


	// --------------------------------------------------------------


	public function set( $data = array() )
	{
		if( isset( $data['overwrite'] ) AND $data['overwrite'] == 'no' )
		{
			if( $this->get( $data['name'] ) != '' ) return FALSE;
		}
		else
		{
			// Kill the existing value
			$this->functions->set_cookie( $this->prefix. $data['name']);
		}

		if( !isset( $data['lifespan'] ) ) $data['lifespan'] = $this->default_lifespan;

		$this->EE->functions->set_cookie( $this->prefix.$data['name'], $data['value'], $data['lifespan'] );

		return TRUE;
	}

	public function get( $name = '' )
	{
		return $this->EE->input->cookie( $this->prefix. $name );
	}


	public function all()
	{
		$ee_prefix = ( ! $this->EE->config->item('cookie_prefix')) ? 'exp_' : $this->EE->config->item('cookie_prefix').'_';

		$all_cookies = $_COOKIE;
		$our_cookies = array();

		foreach( $all_cookies as $key => $val )
		{
			if( substr( $key, 0, strlen( $ee_prefix.$this->prefix ) ) == $ee_prefix.$this->prefix )
			{
				// this is a device set cookie, use it
				$our_cookies[ substr( $key, strlen( $ee_prefix.$this->prefix ) ) ] = $this->EE->security->xss_clean( $val );
			}
		}

		return $our_cookies;
	}

} // End class

/* End of file Device_project_model.php */