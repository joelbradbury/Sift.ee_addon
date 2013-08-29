<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include base class
if ( ! class_exists('Low_search_base'))
{
	require_once(PATH_THIRD.'low_search/base.low_search.php');
}

/**
 * Low Search extension class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_ext extends Low_search_base {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Do settings exist?
	 *
	 * @access      public
	 * @var         bool
	 */
	public $settings_exist = TRUE;

	/**
	 * Extension is required by module
	 *
	 * @access      public
	 * @var         array
	 */
	public $required_by = array('module');

	// --------------------------------------------------------------------

	/**
	 * Available tags to use to hilite keywords in excerpt
	 *
	 * @access      private
	 * @var         array
	 */
	private $hilite_tags = array(
		'em',
		'span',
		'strong',
		'mark'
	);

	/**
	 * Cleaned up keywords
	 */
	private $clean_keywords;

	// --------------------------------------------------------------------
	// PUBLIC METHODS
	// --------------------------------------------------------------------

	/**
	 * PHP4 Constructor
	 *
	 * @see         __construct()
	 */
	public function Low_search_ext($settings = FALSE)
	{
		$this->__construct($settings);
	}

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access      public
	 * @param       array
	 * @return      null
	 */
	public function __construct($settings = array())
	{
		// Call Base constructor
		parent::__construct();

		// Assign current settings
		$this->settings = array_merge($this->default_settings, $settings);
	}

	// --------------------------------------------------------------------

	/**
	 * Settings form
	 *
	 * @access      public
	 * @param       array     Current settings
	 * @return      string
	 */
	function settings_form($current)
	{
		$this->set_base_url();

		// --------------------------------------
		// Determine min_word_len
		// --------------------------------------

		$query = ee()->db->query("SHOW VARIABLES LIKE 'ft_min_word_len'");

		if ($row = $query->row_array() && isset($row['Value']))
		{
			$this->default_settings['min_word_length'] = $row['Value'];
		}

		// --------------------------------------
		// Merge default and current settings
		// --------------------------------------

		$this->data = array_merge($this->default_settings, $current);

		// --------------------------------------
		// Fill data array
		// --------------------------------------

		$this->data['name'] = $this->class_name;

		// --------------------------------------
		// Get URI protocol
		// --------------------------------------

		$this->data['uri_protocol'] = ee()->config->item('uri_protocol');

		// --------------------------------------
		// Add search modes to data array
		// --------------------------------------

		$this->data['search_modes'] = $this->search_modes();

		// --------------------------------------
		// Add hilite tags to data array
		// --------------------------------------

		$this->data['hilite_tags'] = $this->hilite_tags;

		// --------------------------------------
		// Format stop words and ignore words
		// --------------------------------------

		$this->data['stop_words']   = preg_replace('/\s+/', "\n", $this->data['stop_words']);
		$this->data['ignore_words'] = preg_replace('/\s+/', "\n", $this->data['ignore_words']);

		// -------------------------------------
		//  Get member groups; exclude superAdmins, guests, pending and banned
		// -------------------------------------

		$query = ee()->db->select('group_id, group_title')
		       ->from('member_groups')
		       ->where('can_access_cp', 'y')
		       ->where_not_in('group_id', array(1, 2, 3, 4))
		       ->order_by('group_title', 'asc')
		       ->get();

		$this->data['groups'] = low_flatten_results($query->result_array(), 'group_title', 'group_id');
		$this->data['permissions'] = $this->permissions;

		// --------------------------------------
		// Set breadcrumb
		// --------------------------------------

		ee()->cp->set_breadcrumb($this->base_url, ee()->lang->line('low_search_module_name'));

		// --------------------------------------
		// Load view
		// --------------------------------------

		return $this->view('ext_settings');
	}

	// --------------------------------------------------------------------

	/**
	 * Save extension settings
	 *
	 * @return      void
	 */
	function save_settings()
	{
		// --------------------------------------
		// Initiate settings array
		// --------------------------------------

		$settings = array();

		// --------------------------------------
		// Loop through default settings, check
		// for POST values, fallback to default
		// --------------------------------------

		foreach ($this->default_settings AS $key => $val)
		{
			if (($settings[$key] = ee()->input->post($key)) === FALSE)
			{
				$settings[$key] = $val;
			}
		}

		// --------------------------------------
		// Convert stop/ignore words
		// --------------------------------------

		$settings['stop_words']   = low_prep_word_list($settings['stop_words']);
		$settings['ignore_words'] = low_prep_word_list($settings['ignore_words']);

		// --------------------------------------
		// Save serialized settings
		// --------------------------------------

		ee()->db->where('class', $this->class_name.'_ext');
		ee()->db->update('extensions', array('settings' => serialize($settings)));
	}

	// --------------------------------------------------------------------
	// HOOKS
	// --------------------------------------------------------------------

	/**
	 * Add/modify entry in search index
	 *
	 * @access      public
	 * @param       int
	 * @param       array
	 * @param       array
	 * @return      void
	 */
	public function entry_submission_end($entry_id, $meta, $data)
	{
		// Get collections for this channel
		ee()->db->where('channel_id', $meta['channel_id']);

		if ($collections = ee()->low_search_collection_model->get_all())
		{
			foreach ($collections AS $collection)
			{
				// Compose entry array
				$entry = array_merge($meta, $data);

				// Make sure title is there
				if ( ! ($title = @$entry['title']))
				{
					$title = ee()->input->post('title');
				}
				$entry['field_id_0'] = (string) $title;

				// Make sure entry_id is there
				$entry['entry_id'] = $entry_id;

				// Build index
				ee()->low_search_index_model->build($collection, $entry);
			}
		}
	}

	/**
	 * Delete entry from search index
	 *
	 * @access      public
	 * @param       int
	 * @param       int
	 * @return      void
	 */
	public function delete_entries_loop($entry_id, $channel_id)
	{
		ee()->low_search_index_model->delete($entry_id, 'entry_id');
	}

	/**
	 * Add search score to channel entries
	 *
	 * @access      public
	 * @param       object
	 * @param       array
	 * @return      array
	 */
	public function channel_entries_query_result($obj, $query)
	{
		// -------------------------------------------
		// Get the latest version of $query
		// -------------------------------------------

		if (ee()->extensions->last_call !== FALSE)
		{
			$query = ee()->extensions->last_call;
		}

		// -------------------------------------------
		// Bail out if we're not Low Searching
		// -------------------------------------------

		if (ee()->TMPL->fetch_param('low_search') != 'yes') return $query;

		// -------------------------------------------
		// Get results and collections from cache
		// -------------------------------------------

		$params      = low_get_cache($this->package, 'params');
		$results     = low_get_cache($this->package, 'results');
		$collections = low_get_cache($this->package, 'collections');
		$excerpts    = low_get_cache($this->package, 'excerpts');
		$vars        = array();

		foreach ($params AS $key => $val)
		{
			$vars[$this->prefix.$key.':raw'] = $val;
			$vars[$this->prefix.$key] = low_format($val);
		}

		// -------------------------------------------
		// Prep keywords
		// -------------------------------------------

		if ($keywords = (string) @$params['keywords'])
		{
			// Prep cleaned keywords for excerpt and hilite
			$iw = str_replace(' ', '|', $this->settings['ignore_words']);
			$kw = preg_replace("/\b({$iw})\b/i", '', $keywords);
			$kw = preg_replace("/\s{2,}/", ' ', $kw);
			$kw = preg_quote($kw, '/');
			$kw = explode(' ', $kw);
			$this->clean_keywords = $kw;
		}

		// -------------------------------------------
		// Is there a low_search_excerpt var in tagdata?
		// -------------------------------------------

		$has_excerpt = (strpos(ee()->TMPL->tagdata, $this->prefix.'excerpt') !== FALSE);

		// -------------------------------------------
		// Get entry ids
		// -------------------------------------------

		$entry_ids = low_flatten_results($query, 'entry_id');

		// -------------------------------------------
		// Loop through entries and add items
		// -------------------------------------------

		foreach ($query AS &$row)
		{
			// Shortcut to result
			if ($isset = isset($results[$row['entry_id']]))
			{
				$r = $results[$row['entry_id']];
			}

			// Shortcut to collection
			if ($isset && isset($collections[$r['collection_id']]))
			{
				$c = $collections[$r['collection_id']];
			}

			// Add all search parameters to entry
			$row = array_merge($row, $vars);

			// Add new variables to row
			$row[$this->prefix.'keywords']         = low_format($keywords);
			$row[$this->prefix.'score']            = $isset ? number_format(round($r['score'], 2), 2) : 0;
			$row[$this->prefix.'collection_id']    = $isset ? $r['collection_id'] : '';
			$row[$this->prefix.'collection_name']  = $isset ? $c['collection_name'] : '';
			$row[$this->prefix.'collection_label'] = $isset ? $c['collection_label'] : '';
			$row[$this->prefix.'excerpt']          = '';

			// Skip the rest if no excerpt exists
			if ( ! $has_excerpt) continue;

			// Get excerpt ID, the field ID to use as excerpt; 0 for title
			$eid = isset($c['excerpt']) ? $c['excerpt'] : FALSE;

			// Backup excerpt from channel preferences
			if ($eid === FALSE && ! empty($excerpts[$row['channel_id']]))
			{
				$eid = $excerpts[$row['channel_id']];
			}

			if ($eid !== FALSE)
			{
				// Get string and format for excerpt
				$str = ($eid == '0' || ! isset($row['field_id_'.$eid]))  ? $row['title'] : $row['field_id_'.$eid];
				$fmt = ($eid == '0' || ! isset($row['field_fmt_'.$eid])) ? 'xhtml'       : $row['field_fmt_'.$eid];

				// -------------------------------------------
				// 'low_search_excerpt' hook
				// - change the excerpt for an entry
				// -------------------------------------------

				if (ee()->extensions->active_hook('low_search_excerpt') === TRUE)
				{
					$str = ee()->extensions->call('low_search_excerpt', $entry_ids, $row, $eid);

					// Check return value
					if (is_array($str) && isset($str[0]) && isset($str[1]))
					{
						// Set excerpt string to first item in array
						$str = $str[0];

						// If second item in return value, skip native creation of excerpt
						if ($str[1] === TRUE)
						{
							$row[$this->prefix.'excerpt'] = $str;
							continue;
						}
					}
				}

				// Overwrite empty excerpt with formatted one
				$row[$this->prefix.'excerpt'] = $this->_create_excerpt($params, $str, $fmt);

				// Hilite keywords?
				if ($keywords && ($tag = @$this->settings['excerpt_hilite']))
				{
					$row[$this->prefix.'excerpt'] = $this->_highlight_keywords($params, $row[$this->prefix.'excerpt'], $tag);

					// Optionally hilite keywords in title too
					if (@$this->settings['title_hilite'] == 'y')
					{
						$row['title'] = $this->_highlight_keywords($params, $row['title'], $tag);
					}
				}
			}
		}

		return $query;
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Add/modify entry in search index
	 *
	 * @access      private
	 * @param       string
	 * @param       string
	 * @return      string
	 */
	private function _create_excerpt($params, $str = '', $fmt = 'none')
	{
		// Strip tags first
		$str = strip_tags($str);

		// Typography options
		$options = array(
			'text_format'   => $fmt,
			'html_format'   => 'safe',
			'auto_links'    => 'n',
			'allow_img_url' => 'n'
		);

		// Format text
		$str = ee()->typography->parse_type($str, $options);

		// Strip again and trim it
		$str = trim(strip_tags($str));

		// Clean white space
		$str = preg_replace('/(&nbsp;|\s)+/', ' ', $str);

		// Limited string
		if ($length = (int) $this->settings['excerpt_length'])
		{
			// Bail out if string is shorter than the amount of words given
			if ($length >= strlen($str) || $length >= str_word_count($str, 0, '1234567890')) return $str;

			// Keywords - clean em up for preg_split use
			if ($this->clean_keywords)
			{
				// Prep the keywords
				$pattern = low_get_pattern_from_keywords(
					$this->clean_keywords,
					$params['loose_ends'],
					$params['search_mode'] == 'exact'
				);

				// Split the excerpt at the first keyword found
				$tmp = preg_split($pattern, $str, 2, PREG_SPLIT_DELIM_CAPTURE);

				// If we have a split, check the left part
				if ($tmp && count($tmp) >= 2)
				{
					// Amount of words to put on the left
					$left_count = round($length / 10);

					// Explode left words into an array
					$tmp[0] = explode(' ', $tmp[0]);

					// If there are more words on the left than allowed...
					if (count(array_filter($tmp[0])) > $left_count)
					{
						// ...slice off excess words...
						$tmp[0] = array_slice($tmp[0], -$left_count);

						// ...add horizontal ellipsis to the now first word and...
						$tmp[0][0] = '&#8230;'.$tmp[0][0];
					}

					// ...put it together again.
					$tmp[0] = implode(' ', $tmp[0]);

					// Now bring the whole excerpt together again...
					$str = implode('', $tmp);
				}
			}

			// ...and let EE's word limiter do the rest
			$str = ee()->functions->word_limiter($str, $length);
		}

		return $str;
	}

	/**
	 * Hilite keywords in params in given string
	 *
	 * @access      private
	 * @param       string
	 * @param       string
	 * @return      string
	 */
	private function _highlight_keywords($params, $str, $tag)
	{
		// Prep the keywords
		$pattern = low_get_pattern_from_keywords(
			$this->clean_keywords,
			$params['loose_ends'],
			$params['search_mode'] == 'exact'
		);

		// Case insensitive replace
		$str = preg_replace($pattern, "<{$tag}>$1</{$tag}>", $str);

		return $str;
	}
}
// END CLASS

/* End of file ext.low_search.php */
