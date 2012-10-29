<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
						'pi_name'			=> 'Extract URL',
						'pi_version'		=> '2.0',
						'pi_author'			=> 'Chris Ruzin',
						'pi_author_url'		=> 'http://www.chrisruzin.net/',
						'pi_description'	=> 'Extracts the URL from a given source',
						'pi_usage'			=> Extract_URL::usage()
					);

/**
 * Extract URL Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Chris Ruzin
 * @copyright		Copyright (c) 2010, Chris Ruzin
 * @link			http://chrisruzin.net/addons/extract_url/
 */

class Extract_URL
{
	var $return_data;
	var $urls = array();

    /**
	 * Extract_URL
	 *
	 * This function returns a string of URLS
	 *
	 * @access	public
	 * @return	string
	 */

    function Extract_URL($str = "")
	{
        $this->EE =& get_instance();

        $data = '';

		if ($str == '')
        	$str = $this->EE->TMPL->tagdata;

        $c = preg_match_all('/href="(.+?)"|src="(.+?)"/is', $str, $this->urls);

		if ($c)
		{
			for ($i = 0, $j = $c; $i < $j; $i++)
	        {
	        	$data .= $this->urls[1][$i];
	        	$data .= $this->urls[2][$i];
	        }
		}
		else
		{
			$data = $str;
		}

        $this->return_data = $data;
	}

    /**
	 * Usage
	 *
	 * This function describes how the plugin is used.
	 *
	 * @access	public
	 * @return	string
	 */

    function usage()
	{
		ob_start();
?>
This plugin is simple to use:

{exp:extract_url}
Whatever is your source for the URL.
{/exp:extract_url}

Example inside of EE entries tag:
--
{exp:weblog:entries weblog="main" limit="5" rdf="off"}
<strong>URLS in {title}:</strong><br />
<a href="{exp:extract_url}{file}{/exp:extract_url}">{title}</a><br />
{/exp:weblog:entries}
--

{file} is a custom field in this case.
<?php
		$buffer = ob_get_contents();

		ob_end_clean();

		return $buffer;
	}

}

?>
