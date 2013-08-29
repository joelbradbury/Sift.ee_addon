<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Low Search helper functions
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */

// --------------------------------------------------------------------

/**
 * Returns an array of all substring index positions
 *
 * @param      string
 * @param      string
 * @return     array
 */
if ( ! function_exists('low_strpos_all'))
{
	function low_strpos_all($haystack, $needle)
	{
		$all = array();

		if (preg_match_all('#'.preg_quote($needle, '#').'#', $haystack, $matches))
		{
			$total = count($matches[0]);
			$offset = 0;

			while ($total--)
			{
				$pos = strpos($haystack, $needle, $offset);
				$all[] = $pos;
				$offset = $pos + 1;
			}
		}

		return $all;
	}
}

/**
 * Returns an array of all substrings within haystack with given length and optional padding
 *
 * @param      string
 * @param      array
 * @param      int
 * @param      int
 * @return     array
 */
if ( ! function_exists('low_substr_pad'))
{
	function low_substr_pad($haystack, $pos = array(), $length = 0, $pad = 0)
	{
		$all = array();
		$haystack_length = strlen($haystack);

		foreach ($pos AS $p)
		{
			// account for left padding
			$p -= $pad;
			if ($p < 0) $p = 0;

			// Account for right padding
			$l = $length + ($pad * 2);
			if (($p + $l) > $haystack_length) $l = $haystack_length - $p;

			$all[] = substr($haystack, $p, $l);
		}

		return $all;
	}
}

/**
 * Wraps occurrences of $needle found in $haystack in <strong> tags
 *
 * @param      string
 * @param      string
 * @return     string
 */
if ( ! function_exists('low_hilite'))
{
	function low_hilite($haystack, $needle)
	{
		return preg_replace('#('.preg_quote($needle, '#').')#', '<strong>$1</strong>', $haystack);
	}
}

// --------------------------------------------------------------------

/**
 * Strip string from unwanted chars for better sorting
 *
 * @param      string    String to clean up
 * @param      array     Array of words to ignore (strip out)
 * @return     string
 */
if ( ! function_exists('low_clean_string'))
{
	function low_clean_string($str, $ignore = array())
	{
		static $chars = array();

		// --------------------------------------
		// Get translation array from native foreign_chars.php file
		// --------------------------------------

		if ( ! $chars)
		{
			// This will replace accented chars with non-accented chars
			if (file_exists(APPPATH.'config/foreign_chars.php'))
			{
				include APPPATH.'config/foreign_chars.php';

				if (isset($foreign_characters) && is_array($foreign_characters))
				{
					foreach ($foreign_characters AS $k => $v)
					{
						$chars[low_chr($k)] = $v;
					}
				}
			}

			// Punctuation characters and misc ascii symbols
			$punct = array(
				33,34,35,38,39,40,41,42,43,44,45,46,47,58,59,60,62,63,64,
				91,92,93,94,123,124,125,126,161,162,163,164,165,166,167,
				168,169,170,171,172,174,175,176,177,178,179,180,181,182,
				183,184,185,186,187,188,189,190,191,215,402,710,732,
				8211,8212,8213,8216,8217,8218,8220,8221,8222,8224,8225,
				8226,8227,8230,8240,8242,8243,8249,8250,8252,8254,8260,
				8364,8482,8592,8593,8594,8595,8596,8629,8656,8657,8658,
				8659,8660,8704,8706,8707,8709,8711,8712,8713,8715,8719,
				8721,8722,8727,8730,8733,8734,8736,8743,8744,8745,8746,
				8747,8756,8764,8773,8776,8800,8801,8804,8805,8834,8835,
				8836,8838,8839,8853,8855,8869,8901,8968,8969,8970,8971,
				9001,9002,9674,9824,9827,9829,9830
			);

			// Add punctuation characters to chars array
			foreach ($punct AS $k)
			{
				$chars[low_chr($k)] = ' ';
			}
		}

		// --------------------------------------
		// Get rid of tags
		// --------------------------------------

		$str = strip_tags($str);

		// --------------------------------------
		// Convert non-breaking spaces entities to regular ones
		// --------------------------------------

		$str = str_replace(array('&nbsp;', '&#160;', '&#xa0;') , ' ', $str);

		// --------------------------------------
		// Get rid of entities
		// --------------------------------------

		$str = html_entity_decode($str, ENT_QUOTES, (UTF8_ENABLED ? 'UTF-8' : NULL));

		// --------------------------------------
		// Replace accented chars with unaccented versions
		// Options explored:
		// - CI's convert_accented_characters() with a preg_replace_callback ==> Very slow
		// - Static array with 'accented char' => 'unaccented char' and a strtr() ==> Missing chars
		//   But using the native foreign_chars.php file, users can edit the array themselves
		// - iconv seems to work nicely: http://stackoverflow.com/questions/3542717/
		// --------------------------------------

		//$str = preg_replace_callback('/(.)/', 'convert_accented_characters', $str);
		//$str = iconv((UTF8_ENABLED ? 'UTF-8' : 'ISO-8859-1'), 'ASCII//TRANSLIT//IGNORE', $str);

		if ($chars)
		{
			$str = strtr($str, $chars);
		}

		// --------------------------------------
		// Change to lowercase
		// --------------------------------------

		$str = function_exists('mb_strtolower') ? mb_strtolower($str) : strtolower($str);

		// --------------------------------------
		// Ignore words
		// --------------------------------------

		if ($ignore)
		{
			if ( ! is_array($ignore))
			{
				$ignore = explode(' ', $ignore);
			}

			foreach ($ignore AS $word)
			{
				$str = preg_replace('#\b'.preg_quote($word).'\b#', '', $str);
			}
		}

		// --------------------------------------
		// Strip out new lines and superfluous spaces
		// --------------------------------------

		$str = preg_replace('/[\n\r]+/', ' ', $str);
		$str = preg_replace('/\s{2,}/', ' ', $str);

		// --------------------------------------
		// Return trimmed
		// --------------------------------------

		return trim($str);
	}
}

// --------------------------------------------------------------------

/**
 * Get utf-8 character from ascii integer
 *
 * @access     public
 * @param      int
 * @return     string
 */
if ( ! function_exists('low_chr'))
{
	function low_chr($int)
	{
		return html_entity_decode('&#'.$int.';', ENT_QUOTES, (UTF8_ENABLED ? 'UTF-8' : NULL));
		//return mb_convert_encoding("&#{$int};", 'UTF-8', 'HTML-ENTITIES');
	}
}

// --------------------------------------------------------------------

/**
 * Get regex pattern from keywords
 *
 * @access     public
 * @param      string|array
 * @param      bool
 * @param      bool
 * @return     string
 */
if ( ! function_exists('low_get_pattern_from_keywords'))
{
	function low_get_pattern_from_keywords($keywords, $loose_ends = FALSE, $exact = FALSE)
	{
		// Convert to string
		if (is_array($keywords))
		{
			$keywords = implode(' ', $keywords);
		}

		// Escape for pattern
		$keywords = preg_quote($keywords, '/');

		// Account for non-exact matching
		if ( ! $exact)
		{
			$keywords = str_replace(' ', '|', $keywords);
		}

		// Loose ends?
		switch ($loose_ends)
		{
			case 'left':
				$pattern = "({$keywords})\b";
			break;

			case 'right':
				$pattern = "\b({$keywords})";
			break;

			case 'both':
				$pattern = "({$keywords})";
			break;

			default:
				$pattern = "\b({$keywords})\b";
			break;
		}

		return "/{$pattern}/i";
	}
}

// --------------------------------------------------------------------

/**
 * Clean up given list of words
 *
 * @access      private
 * @param       string
 * @return      string
 */
if ( ! function_exists('low_prep_word_list'))
{
	function low_prep_word_list($str = '')
	{
		$str = strtolower($str);
		$str = preg_replace("/[^a-z0-9'\s\n]/", '', $str);
		$str = array_unique(array_filter(preg_split('/(\s|\n)/', $str)));
		sort($str);

		return implode(' ', $str);
	}
}

// --------------------------------------------------------------------

/**
 * Format string in given format
 *
 * @access     public
 * @param      string
 * @param      string
 * @return     string
 */
if ( ! function_exists('low_format'))
{
	function low_format($str = '', $format = 'html')
	{
		// Encode/decode chars specifically for EE params
		$code = array(
			'&quot;' => '"',
			'&apos;' => "'",
			'&#123;' => '{',
			'&#125;' => '}'
		);

		switch ($format)
		{
			case 'url':
				$str = urlencode($str);
			break;

			case 'html':
				$str = htmlspecialchars($str);
				$str = low_format($str, 'ee-encode');
			break;

			case 'clean':
				$str = low_clean_string($str);
			break;

			case 'ee-encode':
				$str = str_replace(array_values($code), array_keys($code), $str);
			break;

			case 'ee-decode':
				$str = str_replace(array_keys($code), array_values($code), $str);
			break;
		}

		return $str;
	}
}

// --------------------------------------------------------------------

/**
 * Create parameter string from array
 *
 * @access     public
 * @param      array
 * @return     string
 */
if ( ! function_exists('low_param_string'))
{
	function low_param_string($array)
	{
		// prep output
		$out = array();

		foreach ($array AS $key => $val)
		{
			// Disallow non-string values
			if ( ! is_string($val)) continue;

			$out[] = sprintf('%s="%s"', $key, $val);
		}

		// Return the string
		return implode(' ', $out);
	}
}

// --------------------------------------------------------------------

/**
 * Converts EE parameter to workable php vars
 *
 * @access     public
 * @param      string    String like 'not 1|2|3' or '40|15|34|234'
 * @return     array     [0] = array of ids, [1] = boolean whether to include or exclude: TRUE means include, FALSE means exclude
 */
if ( ! function_exists('low_explode_param'))
{
	function low_explode_param($str)
	{
		// --------------------------------------
		// Initiate $in var to TRUE
		// --------------------------------------

		$in = TRUE;

		// --------------------------------------
		// Check if parameter is "not bla|bla"
		// --------------------------------------

		if (strtolower(substr($str, 0, 4)) == 'not ')
		{
			// Change $in var accordingly
			$in = FALSE;

			// Strip 'not ' from string
			$str = substr($str, 4);
		}

		// --------------------------------------
		// Return two values in an array
		// --------------------------------------

		return array(preg_split('/(&&?|\|)/', $str), $in);
	}
}

/**
 * Converts array to EE parameter
 *
 * @access     public
 * @param      array
 * @param      bool
 * @param      string
 * @return     string
 */
if ( ! function_exists('low_implode_param'))
{
	function low_implode_param($array = array(), $in = TRUE, $sep = '|')
	{
		// --------------------------------------
		// Initiate string
		// --------------------------------------

		$str = '';

		// --------------------------------------
		// Implode array
		// --------------------------------------

		if ( ! empty($array))
		{
			$str = implode($sep, $array);

			// Prepend 'not '
			if ($in === FALSE) $str = 'not '.$str;
		}

		// --------------------------------------
		// Return string
		// --------------------------------------

		return $str;
	}
}


// --------------------------------------------------------------------

/**
 * Converts {if foo IN (1|2|3)} to {if foo == "1" OR foo == "2" OR foo == "3"}
 * in given tagdata
 *
 * @access     public
 * @param      string    tagdata
 * @return     string    Prep'ed tagdata
 */
if ( ! function_exists('low_prep_in_conditionals'))
{
	function low_prep_in_conditionals($tagdata = '')
	{
		if (preg_match_all('#'.LD.'if (([\w\-_]+)|((\'|")(.+)\\4)) (NOT)?\s?IN \((.*?)\)'.RD.'#', $tagdata, $matches))
		{
			//low_dump($matches);
			foreach ($matches[0] AS $key => $match)
			{
				$left    = $matches[1][$key];
				$operand = $matches[6][$key] ? '!=' : '==';
				$andor   = $matches[6][$key] ? ' AND ' : ' OR ';
				$items   = preg_replace('/(&(amp;)?)+/', '|', $matches[7][$key]);
				$cond    = array();
				foreach (explode('|', $items) AS $right)
				{
					$tmpl   = preg_match('#^(\'|").+\\1$#', $right) ? '%s %s %s' : '%s %s "%s"';
					$cond[] = sprintf($tmpl, $left, $operand, $right);
				}

				// Replace {if foo IN (a|b|c)} with {if foo == 'a' OR foo == 'b' OR foo == 'c'}
				$tagdata = str_replace(
					$match,
					LD.'if '.implode($andor, $cond).RD,
					$tagdata
				);
			}
		}
		return $tagdata;
	}
}

// --------------------------------------------------------------------

/**
 * Flatten results
 *
 * Given a DB result set, this will return an (associative) array
 * based on the keys given
 *
 * @param      array
 * @param      string    key of array to use as value
 * @param      string    key of array to use as key (optional)
 * @return     array
 */
if ( ! function_exists('low_flatten_results'))
{
	function low_flatten_results($resultset, $val, $key = FALSE)
	{
		$array = array();

		foreach ($resultset AS $row)
		{
			if ($key !== FALSE)
			{
				$array[$row[$key]] = $row[$val];
			}
			else
			{
				$array[] = $row[$val];
			}
		}

		return $array;
	}
}

// --------------------------------------------------------------------

/**
 * Associate results
 *
 * Given a DB result set, this will return an (associative) array
 * based on the keys given
 *
 * @param      array
 * @param      string    key of array to use as key
 * @param      bool      sort by key or not
 * @return     array
 */
if ( ! function_exists('low_associate_results'))
{
	function low_associate_results($resultset, $key, $sort = FALSE)
	{
		$array = array();

		foreach ($resultset AS $row)
		{
			if (array_key_exists($key, $row) && ! array_key_exists($row[$key], $array))
			{
				$array[$row[$key]] = $row;
			}
		}

		if ($sort === TRUE)
		{
			ksort($array);
		}

		return $array;
	}
}

// --------------------------------------------------------------

/**
 * Is current request an Ajax request or not?
 *
 * @return     bool
 */
if ( ! function_exists('is_ajax'))
{
	function is_ajax()
	{
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
	}
}

// --------------------------------------------------------------

/**
 * Returns TRUE if var is not empty (NULL, FALSE or empty string)
 *
 * @param      mixed
 * @return     bool
 */
if ( ! function_exists('low_not_empty'))
{
	function low_not_empty($var)
	{
		$empty = FALSE;

		if (is_null($var) || $var === FALSE || (is_string($var) && ! strlen($var)))
		{
			$empty = TRUE;
		}

		return ! $empty;
	}
}

// --------------------------------------------------------------

/**
 * Is array numeric; filled with numeric values?
 *
 * @param      array
 * @return     bool
 */
if ( ! function_exists('low_array_is_numeric'))
{
	function low_array_is_numeric($array = array())
	{
		$numeric = TRUE;

		foreach ($array AS $val)
		{
			if ( ! is_numeric($val))
			{
				$numeric = FALSE;
				break;
			}
		}

		return $numeric;
	}
}

// --------------------------------------------------------------

/**
 * Compare one score to another, used to sort search results for alternative method
 *
 * @param       array
 * @param       array
 * @return      int
 */
if ( ! function_exists('low_by_score'))
{
	function low_by_score($a, $b)
	{
		if ( ! isset($a['score']) || ! isset($b['score']) || $a['score'] == $b['score']) return 0;
		return ($a['score'] > $b['score']) ? -1 : 1;
	}
}

// --------------------------------------------------------------

/**
 * Order by keywords
 *
 * @param       array
 * @param       array
 * @return      int
 */
if ( ! function_exists('low_by_keywords'))
{
	function low_by_keywords($a, $b)
	{
		return strcasecmp($a['keywords_clean'], $b['keywords_clean']);
	}
}
// --------------------------------------------------------------

/**
 * Get cache value, either using the cache method (EE2.2+) or directly from cache array
 *
 * @param       string
 * @param       string
 * @return      mixed
 */
if ( ! function_exists('low_get_cache'))
{
	function low_get_cache($a, $b)
	{
		if (method_exists(ee()->session, 'cache'))
		{
			return ee()->session->cache($a, $b);
		}
		else
		{
			return (isset(ee()->session->cache[$a][$b]) ? ee()->session->cache[$a][$b] : FALSE);
		}
	}
}

// --------------------------------------------------------------

/**
 * Set cache value, either using the set_cache method (EE2.2+) or directly to cache array
 *
 * @param       string
 * @param       string
 * @param       mixed
 * @return      void
 */
if ( ! function_exists('low_set_cache'))
{
	function low_set_cache($a, $b, $c)
	{
		if (method_exists(ee()->session, 'set_cache'))
		{
			ee()->session->set_cache($a, $b, $c);
		}
		else
		{
			ee()->session->cache[$a][$b] = $c;
		}
	}
}

// --------------------------------------------------------------

/**
 * Zebra table helper
 *
 * @param       bool
 * @return      string
 */
if ( ! function_exists('low_zebra'))
{
	function low_zebra($reset = FALSE)
	{
		static $i = 0;

		if ($reset) $i = 0;

		return (++$i % 2 ? 'odd' : 'even');
	}
}

// --------------------------------------------------------------

/**
 * Debug
 *
 * @param       mixed
 * @param       bool
 * @return      void
 */
if ( ! function_exists('low_dump'))
{
	function low_dump($var, $exit = TRUE)
	{
		echo '<pre>'.print_r($var, TRUE).'</pre>';
		if ($exit) exit;
	}
}

// --------------------------------------------------------------

/* End of file low_search_helper.php */