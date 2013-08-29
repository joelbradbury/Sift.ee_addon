<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include base class
if ( ! class_exists('Low_search_base'))
{
	require_once(PATH_THIRD.'low_search/base.low_search.php');
}

/**
 * Low Search Module class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search extends Low_search_base {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Query Parameters
	 *
	 * @access      private
	 * @var         array
	 */
	private $params = array();

	/**
	 * Collection ids to limit the search by
	 * - Empty array for all collections
	 *
	 * @access      private
	 * @var         array
	 */
	private $collection_ids = array();

	/**
	 * Site ids to limit the search by
	 * - Empty array for all sites
	 *
	 * @access      private
	 * @var         array
	 */
	private $site_ids = array();

	/**
	 * Entry ids to limit the search by:
	 * - NULL for no limit
	 * - empty array to trigger no_results
	 *
	 * @access      private
	 * @var         mixed
	 */
	private $entry_ids = NULL;

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Filters
	 *
	 * @access      public
	 * @return      string
	 */
	public function filters()
	{
		// --------------------------------------
		// Load up language file
		// --------------------------------------

		ee()->lang->loadfile($this->package);

		// --------------------------------------
		// Read parameters
		// --------------------------------------

		$this->_set_params();

		// --------------------------------------
		// Prep params for template variables
		// --------------------------------------

		$vars = array();

		foreach ($this->params AS $key => $val)
		{
			// Be on the safe side
			// if (is_array($val)) continue;

			$vars[$this->prefix.$key.':raw'] = $val;
			$vars[$this->prefix.$key] = low_format($val);

			// Backward compatibility; non-prefixed vars
			if (in_array($key, $this->custom_params))
			{
				$vars[$key] = $vars[$this->prefix.$key];
			}
		}

		// --------------------------------------
		// Get search collections for this site
		// --------------------------------------

		// Check to see whether we actually need to get collections
		$get_collections    = FALSE;
		$collections        = array();
		$active_collections = array();

		// Get them only if the var pair exists
		foreach (ee()->TMPL->var_pair AS $key => $val)
		{
			// That's the one
			if (substr($key, 0, 11) == 'collections')
			{
				$this->_log('Collections variable pair found');

				$get_collections = TRUE;

				// Is the 'show' parameter set?
				// If so, get the ids
				if (isset($val['show']))
				{
					$get_collections = $val['show'];
				}
			}
		}

		// Get the collections if necessary
		if ($get_collections !== FALSE)
		{
			$this->_log('Getting search collection details');

			// --------------------------------------
			// Possibly limit by ids
			// --------------------------------------

			$collections = $this->_filter_collections('site_id', array_values(ee()->TMPL->site_ids));

			if ($get_collections !== TRUE)
			{
				list($show, $in) = low_explode_param($get_collections);
				$key = low_array_is_numeric($show) ? 'collection_id' : 'collection_name';
				$collections = $this->_filter_collections($key, $show, $in, $collections);
			}

			// --------------------------------------
			// Define collection meta data
			// --------------------------------------

			$meta = array(
				'collection_count'  => 0,
				'total_collections' => count($collections)
			);

			// --------------------------------------
			// Get array of active collections
			// --------------------------------------

			if ( ! empty($this->params['collection']))
			{
				if (is_string($this->params['collection']))
				{
					list($active_collections, $in) = low_explode_param($this->params['collection']);
				}
				elseif (is_array($this->params['collection']))
				{
					$active_collections = $this->params['collection'];
				}
			}

			// --------------------------------------
			// Loop thru collections, modify rows
			// --------------------------------------

			// Numeric collections?
			$attr = low_array_is_numeric($active_collections) ? 'collection_id' : 'collection_name';

			foreach ($collections AS &$row)
			{
				// Forget some
				unset($row['site_id'], $row['settings']);

				// Make strings html-safe
				$row = array_map('htmlspecialchars', $row);

				// Is collection selected?
				$row['collection_is_active'] = in_array($row[$attr], $active_collections) ? 'y' : '';

				// Increment collection count
				$meta['collection_count']++;

				// Merge meta with row
				$row = array_merge($row, $meta);
			}
		}

		// --------------------------------------
		// Add collections to vars array
		// --------------------------------------

		$vars['collections'] = $vars[$this->prefix.'collections'] = $collections;

		// --------------------------------------
		// Add additional vars to vars array
		// --------------------------------------

		$vars['error_message'] = lang(ee()->session->flashdata('error_message'));

		// --------------------------------------
		// Parse it now
		// --------------------------------------

		$tagdata = ee()->TMPL->parse_variables_row(ee()->TMPL->tagdata, $vars);
		$tagdata = $this->_post_parse($tagdata);

		// --------------------------------------
		// Return output
		// --------------------------------------

		return $tagdata;
	}

	/**
	 * Call $this->filters and wrap a form around it
	 *
	 * @access     public
	 * @return     string
	 */
	public function form()
	{
		// --------------------------------------
		// Collect form params
		// --------------------------------------

		$form_params = array();

		foreach (ee()->TMPL->tagparams AS $key => $val)
		{
			if (substr($key, 0, 5) == 'form_')
			{
				$form_params[substr($key, 5)] = $val;
			}
		}

		// --------------------------------------
		// Initiate data array for form creation
		// --------------------------------------

		$data = array(
			'secure' => (ee()->TMPL->fetch_param('secure', 'yes') == 'yes')
		);

		// --------------------------------------
		// Parameters to send as hidden field
		// --------------------------------------

		$valid_params = array_merge($this->custom_params, array('site'));
		$params       = array();

		foreach ($valid_params AS $key)
		{
			$params[$key] = ee()->TMPL->fetch_param($key);
		}

		// --------------------------------------
		// Define default hidden fields
		// --------------------------------------

		$data['hidden_fields'] = array(
			'ACT'    => ee()->functions->fetch_action_id($this->class_name, 'catch_search'),
			'params' => $this->encode(array_filter($params, 'low_not_empty'))
		);

		// --------------------------------------
		// Get opening form tag
		// --------------------------------------

		$form = ee()->functions->form_declaration($data);

		// --------------------------------------
		// Add form params to it
		// --------------------------------------

		if ($form_params)
		{
			$form = str_replace('<form ', '<form '.low_param_string($form_params).' ', $form);
		}

		// --------------------------------------
		// Return output
		// --------------------------------------

		return $form . $this->filters() . '</form>';
	}

	// --------------------------------------------------------------------

	/**
	 * Show search results
	 *
	 * @access      public
	 * @return      string
	 */
	public function results()
	{
		// --------------------------------------
		// Avoid no_results conflict
		// --------------------------------------

		$this->_prep_no_results();

		// --------------------------------------
		// If query parameter is set but empty or invalid,
		// show no_results and abort
		// --------------------------------------

		if ($this->get_settings('encode_query') == 'y')
		{
			$query_param = ee()->TMPL->fetch_param('query');
			$query       = $this->decode($query_param);

			// If query="" param was set, but invalid
			if ($query_param !== FALSE && empty($query))
			{
				$what = (strlen($query_param)) ? 'invalid' : 'empty';
				$this->_log("Query parameter set, but {$what}");
				return $this->_no_results();
			}
		}
		else
		{
			$query = $_GET;
		}

		// --------------------------------------
		// Consolidate all parameters into query
		// --------------------------------------

		if ( ! empty(ee()->TMPL->tagparams))
		{
			$query = array_merge($query, ee()->TMPL->tagparams);
		}

		// --------------------------------------
		// And set the parameters
		// --------------------------------------

		$this->_set_params($query);

		// Clean up
		unset($query_param, $query);

		// -------------------------------------
		// 'low_search_pre_search' hook.
		//  - Do something just before the search is executed
		// -------------------------------------

		
		if (ee()->extensions->active_hook('low_search_pre_search') === TRUE)
		{
			$this->params = ee()->extensions->call('low_search_pre_search', $this->params);
			if (ee()->extensions->end_script === TRUE) return ee()->TMPL->tagdata;
		}

		// --------------------------------------
		// Set internal collection and site IDs
		// --------------------------------------

		$this->_set_collection_ids();
		$this->_set_site_ids();

		// --------------------------------------
		// Optionally log search
		// --------------------------------------

		if (ee()->TMPL->fetch_param('log_search') == 'yes' &&
			! preg_match('#/P\d+/?$#', ee()->uri->uri_string()))
		{
			$this->_log_search($this->params);
		}

		// --------------------------------------
		// Check the entry_id param beforehand
		// --------------------------------------

		/* if (isset($this->params['entry_id']))
		 {
		 	// Read it
		 	list($ids, $in) = low_explode_param($this->params['entry_id']);

		 	// Only set $this->entry_ids when it's an inclusive list
		 	if ($in) { $this->_add_to_ids($ids);  }
		 }*/

		// --------------------------------------
		// Custom Filters - will modify $this->entry_ids
		// --------------------------------------

		$this->_filter_by_ranges();
		$this->_filter_by_relationships();
		$this->_filter_by_titles();
		$this->_filter_by_grouped_categories();

		// --------------------------------------
		// Check the entry ids array
		// --------------------------------------
	

		if (is_array($this->entry_ids))
		{
			// If not empty, check existing entry_id param
			if ( ! empty($this->entry_ids) && isset($this->params['entry_id']))
			{
				// Read the entry_id parameter
				list($ids, $in) = low_explode_param($this->params['entry_id']);

				// Either remove $ids from $this->entry_ids OR limit $this->entry_ids to $ids
				$method = $in ? 'array_intersect' : 'array_diff';

				// The final limited array of entry_ids valid for this search
				$this->entry_ids = $method($this->entry_ids, $ids);
			}

			// If the entry_ids are empty now, bail out
			if (empty($this->entry_ids))
			{
				$this->_log('Custom filters found no matches, returning no results');
				return $this->_no_results();
			}

			// Still here: set the entry_id param
			$this->_log('Limiting search by entry ids');
			$this->params['entry_id'] = low_implode_param($this->entry_ids);
		}


		// --------------------------------------
		// Check orderby_sort=""
		// --------------------------------------

		if (isset($this->params['orderby_sort']) &&
			strpos($this->params['orderby_sort'], '|') !== FALSE)
		{
			list(
				$this->params['orderby'],
				$this->params['sort']
			) = explode('|', $this->params['orderby_sort'], 2);
		}

		// --------------------------------------
		// Backwards compat - loose_ends
		// --------------------------------------

		if ( ! empty($this->params['loose_ends']) &&
			! in_array($this->params['loose_ends'], array('left', 'right', 'both')))
		{
			// loose_ends="yes" => loose_ends="right"
			$this->params['loose_ends']
				= ($this->params['loose_ends'] == 'no')
				? ''
				: 'right';
		}

		// --------------------------------------
		// Analyze keywords
		// --------------------------------------

		$raw_keywords = (string) @$this->params['keywords'];

		// --------------------------------------
		// Handle the keywords just like the index_text
		// --------------------------------------

		$clean_keywords = low_clean_string($raw_keywords, $this->get_settings('ignore_words'));
		$raw_keywords   = low_clean_string($raw_keywords);

		// --------------------------------------
		// Only perform actual search if keywords are given
		// --------------------------------------

		$do_search = strlen($clean_keywords) ? TRUE : FALSE;

		// --------------------------------------
		// Are we searching?
		// --------------------------------------

		if ($do_search)
		{
			// --------------------------------------
			// Analyse keywords for stop words
			// --------------------------------------

			$use_fulltext = TRUE;

			if ($this->params['loose_ends'] == 'both' || $this->params['loose_ends'] == 'left')
			{
				// Substring search forces fallback method
				$use_fulltext = FALSE;
			}
			else
			{
				// Get stop words from extension
				if ($stop_words = $this->get_settings('stop_words'))
				{
					// Clean stopwords and convert into array
					$stop_words = array_unique(preg_split('/\s+/', low_clean_string($stop_words)));
				}
				else
				{
					// If none, make it an empty array for below code
					$stop_words = array();
				}

				// Check if the keywords contain stop words
				// or fall below the minimum word length threshold
				// if so, don't use fulltext search
				foreach (explode(' ', $clean_keywords) AS $word)
				{
					$word_length = function_exists('mb_strlen') ? mb_strlen($word) : strlen($word);

					if ($word_length < $this->get_settings('min_word_length') OR in_array($word, $stop_words))
					{
						$use_fulltext = FALSE;
						break;
					}
				}
			}

			// --------------------------------------
			// Check validity of search mode
			// --------------------------------------

			if ( ! in_array($this->params['search_mode'], $this->search_modes()))
			{
				$this->params['search_mode'] = $this->get_settings('default_search_mode');
			}

			// --------------------------------------
			// Check minimum score
			// --------------------------------------

			if ($this->params['min_score'])
			{
				if ($include_min_score = (substr($this->params['min_score'], 0, 1) == '='))
				{
					$this->params['min_score'] = trim($this->params['min_score'], '=');
				}

				// Force proper value
				$this->params['min_score'] = floatval($this->params['min_score']);
			}

			// --------------------------------------
			// Begin composing query
			// --------------------------------------

			if ($use_fulltext)
			{
				// Create fulltext keywords based on clean keywords
				switch ($this->params['search_mode'])
				{
					case 'exact':
						$ft_keywords = '"'.$raw_keywords.'"';
					break;

					case 'all':
						$ft_keywords = '+'.str_replace(' ', ' +', $clean_keywords);
					break;

					default:
					case 'any':
						$ft_keywords = $clean_keywords;
					break;
				}

				// Account for loose ends
				if ($this->params['search_mode'] != 'exact' && $this->params['loose_ends'] == 'right')
				{
					$ft_keywords = str_replace(' ', '* ', $ft_keywords).'*';
				}
			}

			ee()->db->select(($use_fulltext
				? "entry_id, collection_id, MATCH(index_text) AGAINST('{$clean_keywords}') AS score"
				: 'entry_id, collection_id, index_text'), FALSE)
				->from(ee()->low_search_index_model->table());

			// --------------------------------------
			// Filters used by both searches
			// --------------------------------------

			// Multiple keywords and/or
			$andor = ($this->params['search_mode'] == 'all') ? 'AND' : 'OR';

			// Limit query by collection
			if ($this->collection_ids)
			{
				ee()->db->where_in('collection_id', $this->collection_ids);
			}

			// Limit query by site
			if ($this->site_ids)
			{
				ee()->db->where_in('site_id', array_values($this->site_ids));
			}

			// Limit by exact match
			if ($this->params['search_mode'] == 'exact' && ! $use_fulltext)
			{
				$this->_where_exact($raw_keywords);
			}

			// Account for quoted keywords
			if ($this->params['search_mode'] != 'exact' && preg_match_all('#"(.*?)"#', $this->params['keywords'], $matches))
			{
				$this->_where_exact($matches[1], $andor);
			}

			// If entry ids were set, limit it to those
			if (isset($this->params['entry_id']))
			{
				list($entry_ids, $in) = low_explode_param($this->params['entry_id']);
				$method = $in ? 'where_in' : 'where_not_in';
				ee()->db->$method('entry_id', $entry_ids);
			}

			if ($use_fulltext)
			{
				// Actual fulltext search
				ee()->db->where("MATCH(index_text) AGAINST('{$ft_keywords}' IN BOOLEAN MODE)", NULL, FALSE);
				ee()->db->order_by('score', 'desc');

				// Limit by min_score
				if (isset($this->params['min_score']))
				{
					$oper = $include_min_score ? '>=' : '>';
					ee()->db->having("score {$oper}", $this->params['min_score']);
				}
			}
			else
			{
				// Fallback search
				if ($this->params['search_mode'] != 'exact')
				{
					$this->_where_exact(explode(' ', $clean_keywords), $andor, $this->params['loose_ends']);
				}
			}

			// --------------------------------------
			// Extra search stuff
			// --------------------------------------

			if (isset($this->params['add_to_query']) && is_array($this->params['add_to_query']))
			{
				foreach ($this->params['add_to_query'] AS $key => $val)
				{
					// If value is array, we'll do a WHERE IN
					$where = is_array($val)	? 'where_in' : 'where';

					ee()->db->$where($key, $val);
				}

				// And get rid of it again
				unset($this->params['add_to_query']);
			}

			ee()->db->or_where('collection_id','1');
			// --------------------------------------
			// Perform the search
			// --------------------------------------

			$this->_log('Starting search '.($use_fulltext ? '(fulltext)' : '(fallback)'));
			$query = ee()->db->get();

			// --------------------------------------
			// If the search had no results, return no results bit
			// --------------------------------------

			if ($query->num_rows == 0)
			{
				$this->_log('Searched but found nothing. Returning no results.');
				return $this->_no_results();
			}

			// --------------------------------------
			// If we do have results, continue
			// --------------------------------------

			if ($use_fulltext)
			{
				// Get results like $results[entry_id] = array(entry_id, collection_id, score)
				$results = low_associate_results($query->result_array(), 'entry_id');
			}
			else
			{
				// Calculate scores ourselves
				$results = array();

				// --------------------------------------
				// Loop thru results, calculate score
				// based on total words / word count
				// --------------------------------------

				$this->_log('Calculating relevance score');

				// Prep keyword pattern
				$keyword_array = explode(' ', $clean_keywords);
				$keyword_count = count($keyword_array);

				$pattern = low_get_pattern_from_keywords(
					$keyword_array,
					$this->params['loose_ends'],
					$this->params['search_mode'] == 'exact'
				);

				foreach ($query->result() AS $row)
				{
					// Calculate score
					$score = 0;

					// Check occurrence of each word in index_text
					// Added score is number of occurrences / total words * 10
					if ($found = preg_match_all($pattern, $row->index_text, $matches))
					{
						// Removes weight
						$text = preg_replace('/^\|(.+?)\|.*$/m', '$1', $row->index_text);

						// Safe word count
						$word_count = count(explode(' ', trim($text)));

						// Add score
						$score = ($found / $word_count) * 100 / $keyword_count;
					}

					// Skip entries that fall below the threshold
					if (isset($this->params['min_score']))
					{
						if (($include_min_score && $score < $this->params['min_score']) ||
							( ! $include_min_score && $score <= $this->params['min_score'])) continue;
					}

					// Add row to results only if the entry doesn't exist yet
					// or if existing score is lower than this one
					if ( ! array_key_exists($row->entry_id, $results) || $results[$row->entry_id]['score'] < $score)
					{
						$results[$row->entry_id] = array(
							'entry_id'      => $row->entry_id,
							'collection_id' => $row->collection_id,
							'score'         => $score
						);
					}
				}

				// Bail out if no entry falls above the min_score threshold
				if (empty($results))
 				{
 					$this->_log('No valid results after scoring');
 					return $this->_no_results();
 				}
			}

			// Clean up
			unset($query);

			// --------------------------------------
			// Make sure collections are registered to cache (done by _filter_collections())
			// --------------------------------------

			$collection_ids = array_unique(low_flatten_results($results, 'collection_id'));
			$collections    = $this->_filter_collections('collection_id', $collection_ids);
			$collections    = low_associate_results($collections, 'collection_id');

			// --------------------------------------
			// Modify scores for each collection
			// --------------------------------------

			$modifiers = array_unique(low_flatten_results($collections, 'modifier'));

			if ( ! (count($modifiers) == 1 && $modifiers[0] == 1.0))
			{
				$this->_log('Applying collection modifier to search results');

				foreach ($results AS &$row)
				{
					if ($mod = (float) $collections[$row['collection_id']]['modifier'])
					{
						$row['score'] = $row['score'] * $mod;
					}
				}
			}

			// -------------------------------------
			// 'low_search_modify_score' hook.
			//  - Modify scoring for keyword searches
			// -------------------------------------

			if (ee()->extensions->active_hook('low_search_modify_score') === TRUE)
			{
				$results = ee()->extensions->call('low_search_modify_score', $results);

				if (empty($results) || ee()->extensions->end_script === TRUE)
				{
					return $this->_no_results();
				}
			}

			// --------------------------------------
			// Sort by score
			// --------------------------------------

			uasort($results, 'low_by_score');

			// --------------------------------------
			// Add results to cache, so extension can look this up
			// --------------------------------------

			$this->_log('Caching search results');
			low_set_cache($this->package, 'results', $results);

			// --------------------------------------
			// Is there a custom sort order?
			// If so, set the entry_id param instead of the fixed_order
			// --------------------------------------

			$orderby = $this->_get_param('orderby', 'low_search_score');
			$par     = 'entry_id';

			if (substr($orderby, 0, 16) == 'low_search_score')
			{
				$par = 'fixed_order';

				$this->_set_param('orderby');
				$this->_set_param('sort');

				// Unset so they don't get re-set
				unset($this->params['orderby'], $this->params['sort']);
			}

			$this->_log("Setting {$par} parameter");
			$this->params[$par] = low_implode_param(array_keys($results));

			// Clean up
			unset($results);

		} // End if ($do_search === TRUE)
		else
		{
			$this->_log('No search, just filter entries by given parameters');

			// --------------------------------------
			// No Low Search means no excerpt from collection,
			// so we need to get the excerpt field from the
			// channel preferences. We'll do that here and store
			// the ids in cache for the extension to use
			// --------------------------------------

			// Get channel excerpt data from DB
			ee()->db->select('ch.channel_name, ch.channel_id, ch.search_excerpt')->from('channels ch');

			// Filter by given collections
			if ($this->collection_ids)
			{
				ee()->db->from(ee()->low_search_collection_model->table().' co')
					         ->where('ch.channel_id = co.channel_id')
					         ->where_in('co.collection_id', $this->collection_ids);
			}

			// Get the data
			$channels = ee()->db->get()->result_array();
			$excerpts = low_flatten_results($channels, 'search_excerpt', 'channel_id');

			// Register excerpt data to cache
			low_set_cache($this->package, 'excerpts', $excerpts);

			// --------------------------------------
			// If collections were given, we need to filter
			// the channel entries by channel, so we'll set
			// the channel parameter based on collections
			// --------------------------------------

			if ($this->params['collection'])
			{
				$channels = array_unique(low_flatten_results($channels, 'channel_name'));
				$this->params['channel'] = low_implode_param($channels);
			}

			// Remove low_search_score from the orderby parameter and adjust sort param accordingly
			if ( ! empty($this->params['orderby']) && substr($this->params['orderby'], 0, 16) == 'low_search_score')
			{
				$this->params['orderby'] = preg_replace('#^low_search_score\|?#', '', $this->params['orderby']);

				if ( ! empty($this->params['sort']))
				{
					$this->params['sort'] = preg_replace('#^(asc|desc)\|?#i', '', $this->params['sort']);
				}
			}
		} // End if ($do_search === FALSE)

		// -------------------------------------
		// 'low_search_post_search' hook.
		//  - Do something just after the search is executed
		// -------------------------------------

		if (ee()->extensions->active_hook('low_search_post_search') === TRUE)
		{
			$this->params = ee()->extensions->call('low_search_post_search', $this->params);
			if (ee()->extensions->end_script === TRUE) return ee()->TMPL->tagdata;
		}

		// --------------------------------------
		// Keep track of params
		// --------------------------------------

		low_set_cache($this->package, 'params', $this->params);

		// --------------------------------------
		// Set misc tagparams
		// --------------------------------------

		$ignore = array_merge(
			$this->custom_params,
			array('query', 'log_search')
		);

		foreach ($this->params AS $key => $val)
		{
			// Skip the ones we know are pointless for the Channel module or we checked already
			if (in_array($key, $ignore)) continue;

			// Exclude this specific key
			$val = $this->_prep_param($key, $val);

			// Set the parameter
			$this->_set_param($key, $val);
		}

		// --------------------------------------
		// Call Channel::entries
		// --------------------------------------

		$tagdata = $this->_channel_entries();

		// Don't post_parse no_results
		$tagdata = ($tagdata == ee()->TMPL->no_results)
			? $this->_no_results($tagdata)
			: $this->_post_parse($tagdata);

		return $tagdata;
	}

	// --------------------------------------------------------------------

	/**
	 * Display search collections
	 *
	 * @access      public
	 * @return      string
	 */
	public function collections()
	{
		// --------------------------------------
		// Check site
		// --------------------------------------

		$site_ids = ee()->TMPL->site_ids;

		// --------------------------------------
		// Get collections
		// --------------------------------------

		$rows = $this->_filter_collections('site_id', $site_ids);

		// --------------------------------------
		// Parse template
		// --------------------------------------

		return $rows
			? ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $rows)
			: ee()->TMPL->no_results();
	}

	// --------------------------------------------------------------------

	/**
	 * Display given keywords
	 *
	 * @access      public
	 * @return      string
	 */
	public function keywords()
	{
		// Get params
		$this->_set_params();

		// And get the keywords
		$keywords = $this->params['keywords'];

		// Check if keywords need to be encoded
		$format = ee()->TMPL->fetch_param('format', 'html');

		// To be sure
		if (is_array($keywords))
		{
			$keywords = implode(' ', $keywords);
		}

		// Return formatted keywords
		return low_format($keywords, $format);
	}

	// --------------------------------------------------------------------

	/**
	 * Display popular keywords
	 *
	 * @access      public
	 * @return      string
	 */
	public function popular()
	{
		// --------------------------------------
		// Filter by site
		// --------------------------------------

		ee()->db->where('site_id', $this->site_id);

		// --------------------------------------
		// Limiting?
		// --------------------------------------

		if ( ! ($limit = (int) ee()->TMPL->fetch_param('limit')))
		{
			$limit = 10;
		}

		ee()->db->limit($limit);

		// --------------------------------------
		// Get terms
		// --------------------------------------

		if ($rows = ee()->low_search_log_model->get_popular_keywords())
		{
			// Get orderby and sort params
			$orderby = ee()->TMPL->fetch_param('orderby', 'search_count');
			$sort    = ee()->TMPL->fetch_param('sort', (($orderby == 'search_count') ? 'desc' : 'asc'));

			foreach ($rows AS &$row)
			{
				$kw = $row['keywords'];
				$row['keywords_raw']   = $kw;
				$row['keywords']       = low_format($kw, 'html');
				$row['keywords_url']   = low_format($kw, 'url');
				$row['keywords_clean'] = low_format($kw, 'clean');
				$row['keywords_param'] = low_format($kw, 'ee-encode');
			}

			// Different orderby?
			switch (ee()->TMPL->fetch_param('orderby'))
			{
				case 'keywords':
					usort($rows, 'low_by_keywords');
					if ($sort == 'desc') $rows = array_reverse($rows);
				break;

				case 'random':
					shuffle($rows);
				break;

				default:
					if ($sort == 'asc') $rows = array_reverse($rows);
			}
		}

		return $rows
			? ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $rows)
			: ee()->TMPL->no_results();
	}

	// --------------------------------------------------------------------

	/**
	 * Generate Open Search URL
	 *
	 * @access      public
	 * @return      string
	 */
	public function url()
	{
		// --------------------------------------
		// Set internal params
		// --------------------------------------

		$this->_set_params();

		// --------------------------------------
		// Params to ignore
		// --------------------------------------

		$ignore = array('query', 'encode', 'cache', 'refresh', 'parse');

		// --------------------------------------
		// Loop through tagparams and add them to the query string
		// --------------------------------------

		// init toggle array
		$toggle = array();

		// Override with tagparams
		foreach (ee()->TMPL->tagparams AS $key => $val)
		{
			if (in_array($key, $ignore) || ! is_string($val)) continue;

			// Decode value
			$val = low_format($val, 'ee-decode');

			// Check for toggle values
			if (substr($key, 0, 7) == 'toggle:')
			{
				$toggle[substr($key, 7)] = $val;
				continue;
			}

			// Add to query string
			$this->params[$key] = $val;
		}

		// --------------------------------------
		// Handle toggle values
		// --------------------------------------

		foreach ($toggle AS $key => $val)
		{
			if (isset($this->params[$key]))
			{
				// Read current value
				list($values, $in) = low_explode_param($this->params[$key]);

				// check if value is there
				if (($i = array_search($val, $values)) === FALSE)
				{
					// Not there, add it
					$values[] = $val;
				}
				else
				{
					// Is there, remove it
					unset($values[$i]);
				}

				$val = empty($values) ? NULL : low_implode_param($values);
			}

			// Add the new value to the parameter array (could be NULL)
			$this->params[$key] = $val;
		}

		// --------------------------------------
		// Clean up the parameters before making the URL
		// --------------------------------------

		$this->params = array_filter($this->params, 'low_not_empty');

		// --------------------------------------
		// Then compose the URL, encoded or not
		// --------------------------------------

		if (ee()->TMPL->fetch_param('encode', 'yes') == 'no')
		{
			// Build non-encoded URL
			$url = ee()->functions->fetch_site_index()
			     . QUERY_MARKER.'ACT='
			     . ee()->functions->fetch_action_id($this->package, 'catch_search')
			     . AMP.http_build_query($this->params, '', AMP);
		}
		else
		{
			// Get the result page from the params
			$url = $this->_create_url($this->params);
		}

		return $url;
	}

	// --------------------------------------------------------------------
	// ACT METHODS
	// --------------------------------------------------------------------

	/**
	 * Build collection index
	 *
	 * @access      public
	 * @return      string
	 */
	public function build_index()
	{
		// License key must be given
		$license_key = $this->get_settings('license_key');
		$given_key   = ee()->input->get_post('key');

		if ($given_key && $license_key == $given_key)
		{
			return $this->_build_index();
		}
		else
		{
			show_error(ee()->lang->line('not_authorized'));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Catch search form submission
	 *
	 * @access      public
	 * @return      void
	 */
	public function catch_search()
	{
		// --------------------------------------
		// Check if XID was posted and if it's valid
		// --------------------------------------

		if (($xid = ee()->input->post('XID')) && ! ee()->security->secure_forms_check($xid))
		{
			return ee()->output->show_user_error('general', lang('not_authorized'));
		}

		// --------------------------------------
		// Initiate data array; will be encrypted
		// and put in the URI later
		// --------------------------------------

		$data = array();

		if ($params = ee()->input->post('params'))
		{
			$data = $this->decode($params);
		}

		// --------------------------------------
		// Check other data
		// --------------------------------------

		foreach (array_merge($_GET, $_POST) AS $key => $val)
		{
			// Keys to skip
			if (in_array($key, array('ACT', 'XID', 'params', 'site_id'))) continue;

			// Add post var to data
			$data[$key] = is_array($val) ? implode('|', array_filter($val, 'low_not_empty')) : $val;
		}

		// --------------------------------------
		// 'low_search_catch_search' extension hook
		//  - Check incoming data and optionally change it
		// --------------------------------------

		if (ee()->extensions->active_hook('low_search_catch_search') === TRUE)
		{
			$data = ee()->extensions->call('low_search_catch_search', $data);
			if (ee()->extensions->end_script === TRUE) return;
		}

		// --------------------------------------
		// Check for required parameter
		// --------------------------------------

		if (isset($data['required']))
		{
			// Get required as array
			list($required, $in) = low_explode_param($data['required']);

			foreach ($required AS $req)
			{
				// Break out when empty
				// @TODO: enhance for multiple fields
				if (empty($data[$req]))
				{
					$this->_go_back($req.'_missing');
				}
			}

			// remove from data
			unset($data['required']);
		}

		// --------------------------------------
		// Clean up the data array
		// --------------------------------------

		$data = array_filter($data, 'low_not_empty');

		// --------------------------------------
		// Optionally log search query
		// --------------------------------------

		$this->_log_search($data);

		// --------------------------------------
		// Result URI: result page & cleaned up data, encoded
		// --------------------------------------

		$url = $this->_create_url($data, '&');

		// --------------------------------------
		// Redirect to result page
		// --------------------------------------

		// Empty out flashdata to avoid serving of JSON for ajax request
		if (AJAX_REQUEST && count(ee()->session->flashdata))
		{
			ee()->session->flashdata = array();
		}

		ee()->functions->redirect($url);
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Get search parameters from query 'n stuff
	 */
	private function _set_params($query = FALSE)
	{
		// --------------------------------------
		// Pre-set custom parameters
		// --------------------------------------

		foreach ($this->custom_params AS $key)
		{
			$this->params[$key] = ee()->TMPL->fetch_param($key, NULL);
		}

		// --------------------------------------
		// Check for existing query
		// --------------------------------------

		if ($query === FALSE)
		{
			if ($this->get_settings('encode_query') == 'y')
			{
				// Get query from parameter
				$query = $this->decode(ee()->TMPL->fetch_param('query'));
			}
			else
			{
				// Or else get it from the GET vars
				$query = $_GET;
			}

			// log msg
			$this->_log(empty($query) ?  'No or invalid query given' : 'Valid query found');
		}

		// If valid, add to params
		if (is_array($query))
		{
			$this->params = array_merge($this->params, $query);
		}
	}

	/**
	 * Set TMPL parameter
	 *
	 * @access     private
	 * @param      string
	 * @param      string
	 * @return     void
	 */
	private function _set_param($key, $val = '')
	{
		// Check for search fields and add parameter to either tagparams or search_fields
		if (substr($key, 0, 7) == 'search:')
		{
			ee()->TMPL->search_fields[substr($key, 7)] = $val;
		}
		else
		{
			ee()->TMPL->tagparams[$key] = $val;
		}
	}

	/**
	 * Get parameter from $this->params with fallback
	 *
	 * @access     private
	 * @param      string
	 * @param      mixed
	 * @return     mixed
	 */
	private function _get_param($key, $fallback = NULL)
	{
		return (empty($this->params[$key]))
			? $fallback
			: $this->params[$key];
	}

	// --------------------------------------------------------------------

	/**
	 * Create URL for given page and encoded query
	 *
	 * @access     private
	 * @param      array
	 * @param      string
	 * @return     string
	 */
	private function _create_url($query = array(), $amp = AMP)
	{
		// --------------------------------------
		// If no page, get default
		// --------------------------------------

		$page = isset($query['result_page'])
		      ? $query['result_page']
		      : $this->get_settings('default_result_page');

		// Remove trailing slash
		$page = rtrim($page, '/');

		// --------------------------------------
		// Encode the query or not?
		// --------------------------------------

		if ($this->get_settings('encode_query') == 'y')
		{
			// Custom query position?
			if (strpos($page, '%s') === FALSE)
			{
				$page .= '/%s';
			}

			$url = sprintf($page, $this->encode($query));
			$qs  = '';
		}
		else
		{
			unset($query['result_page']);

			$url = $page;
			$qs = http_build_query($query, '', $amp);

			// Clean up and remove 'dangerous' chars
			foreach (array('?', ';', ':', '|') AS $i => $char)
			{
				$replacement = ($i < 2) ? '' : $char;
				$qs = str_replace(urlencode($char), $replacement, $qs);
			}

			if ($qs) $qs = '?'.$qs;
		}

		// --------------------------------------
		// If URI isn't a full url, make it so
		// --------------------------------------

		if ( ! preg_match('/^https?:\/\//', $url))
		{
			$url = ee()->functions->create_url($url);
		}

		return $url.$qs;
	}

	// --------------------------------------------------------------------

	/**
	 * Log given search parameters
	 *
	 * @access      private
	 * @param       array
	 * @return      void
	 */
	private function _log_search($data = array())
	{
		if (($search_log_size = $this->get_settings('search_log_size')) !== '0' && is_numeric($search_log_size))
		{
			$keywords = isset($data['keywords']) ? $data['keywords'] : '';

			// Don't add keywords to log parameters
			unset($data['keywords']);

			// Log search
			ee()->low_search_log_model->insert(array(
				'site_id'     => $this->site_id,
				'member_id'   => ee()->session->userdata['member_id'],
				'search_date' => ee()->localize->now,
				'ip_address'  => ee()->session->userdata['ip_address'],
				'keywords'    => $keywords,
				'parameters'  => $this->encode($data, FALSE)
			));

			// Prune log
			// Rand trick borrowed from native search module
			if ((rand() % 100) < 5)
			{
				ee()->low_search_log_model->prune($this->site_id, $search_log_size);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Check for {if low_search_no_results}
	 *
	 * @access      private
	 * @return      void
	 */
	private function _prep_no_results()
	{
		// Shortcut to tagdata
		$td =& ee()->TMPL->tagdata;
		$open = "if {$this->package}_no_results";
		$close = '/if';

		// Check if there is a custom no_results conditional
		if (strpos($td, $open) !== FALSE && preg_match('#'.LD.$open.RD.'(.*?)'.LD.$close.RD.'#s', $td, $match))
		{
			$this->_log("Prepping {$open} conditional");

			// Check if there are conditionals inside of that
			if (stristr($match[1], LD.'if'))
			{
				$match[0] = ee()->functions->full_tag($match[0], $td, LD.'if', LD.'\/if'.RD);
			}

			// Set template's no_results data to found chunk
			ee()->TMPL->no_results = substr($match[0], strlen(LD.$open.RD), -strlen(LD.$close.RD));

			// Remove no_results conditional from tagdata
			$td = str_replace($match[0], '', $td);
		}
	}

	/**
	 * Process no_results
	 *
	 * @access      private
	 * @return      string
	 */
	private function _no_results($tagdata = NULL)
	{
		// Set to default no_results data by default
		if (is_null($tagdata))
		{
			$tagdata = ee()->TMPL->no_results;
		}

		// Check if there are low_search vars present
		if (strpos($tagdata, $this->prefix) !== FALSE)
		{
			$this->_log('Found low_search variables in no_results block, calling filters to parse');

			// TMPL lib says this is legacy code. For over 5 years now. Feck it, using it.
			$vars = ee()->functions->assign_variables($tagdata);

			ee()->TMPL->var_single	= $vars['var_single'];
			ee()->TMPL->var_pair   = $vars['var_pair'];
			ee()->TMPL->tagdata    = $tagdata;

			$tagdata = $this->filters();
		}
		else
		{
			$tagdata = ee()->TMPL->no_results();
		}

		return $tagdata;
	}

	// --------------------------------------------------------------------

	/**
	 * Loads the Channel module and runs its entries() method
	 *
	 * @access      private
	 * @return      void
	 */
	private function _channel_entries()
	{
		// --------------------------------------
		// Set parameter so extension kicks in
		// --------------------------------------

		ee()->TMPL->tagparams['low_search'] = 'yes';

		// --------------------------------------
		// Make sure the following params are set
		// --------------------------------------
		//die('dd');
		$set_params = array(
			'dynamic'  => 'no',
			'paginate' => 'bottom'
		);

		foreach ($set_params AS $key => $val)
		{
			if ( ! ee()->TMPL->fetch_param($key))
			{
				ee()->TMPL->tagparams[$key] = $val;
			}
		}

		// --------------------------------------
		// Take care of related entries
		// --------------------------------------

		// We must do this, 'cause the template engine only does it for
		// channel:entries or search:search_results. The bastard.
		ee()->TMPL->tagdata = ee()->TMPL->assign_relationship_data(ee()->TMPL->tagdata);

		// Add related markers to single vars to trigger replacement
		foreach (ee()->TMPL->related_markers AS $var)
		{
			ee()->TMPL->var_single[$var] = $var;
		}

		// --------------------------------------
		// Get channel module
		// --------------------------------------

		$this->_log('Calling the channel module');

		if ( ! class_exists('channel'))
		{
			require_once PATH_MOD.'channel/mod.channel'.EXT;
		}

		// --------------------------------------
		// Create new Channel instance
		// --------------------------------------

		$channel = new Channel;

		// --------------------------------------
		// Let the Channel module do all the heavy lifting
		// --------------------------------------

		return $channel->entries();
	}

	// --------------------------------------------------------------------

	/**
	 * Get field id for given field short name
	 *
	 * @access      private
	 * @param       string
	 * @param       array
	 * @return      int
	 */
	private function _get_field_id($str, $fields = array())
	{
		// --------------------------------------
		// Get custom channel fields from cache
		// --------------------------------------

		if (empty($fields) && ! ($fields = low_get_cache('channel', 'custom_channel_fields')))
		{
			// If not present, get them from the API
			// Takes some effort, but its reusable for others this way
			$this->_log('Getting channel field info from API');

			ee()->load->library('api');
			ee()->api->instantiate('channel_fields');

			$fields = ee()->api_channel_fields->fetch_custom_channel_fields();

			foreach ($fields AS $key => $val)
			{
				low_set_cache('channel', $key, $val);
			}

			$fields = $fields['custom_channel_fields'];
		}

		// --------------------------------------
		// To be somewhat compatible with MSM,
		// get the first ID that matches,
		// not just for current site, but all given.
		// --------------------------------------

		// Initiate ID
		$it = 0;

		// Check active site IDs, return first match encountered
		foreach ($this->site_ids AS $site_id)
		{
			if (isset($fields[$site_id][$str]))
			{
				$it = $fields[$site_id][$str];
				break;
			}
		}

		// Please
		return $it;
	}

	/**
	 * Check whether given string is a date field
	 */
	private function _is_date_field($str)
	{
		$it = FALSE;

		if ($fields = low_get_cache('channel', 'date_fields'))
		{
			$it = (bool) $this->_get_field_id($str, $fields);
		}

		return $it;
	}

	/**
	 * Check whether given string is a relationship field
	 */
	private function _is_rel_field($str)
	{
		$it = FALSE;

		if ($fields = low_get_cache('channel', 'relationship_fields'))
		{
			$it = (bool) $this->_get_field_id($str, $fields);
		}

		return $it;
	}

	/**
	 * Check whether given field id is a playa field
	 */
	private function _is_playa_field($id)
	{
		$it = FALSE;

		if ($fields = low_get_cache('channel', 'pair_custom_fields'))
		{
			$it = ($this->_get_field_id($id, $fields) == 'playa');
		}

		return $it;
	}

	// --------------------------------------------------------------------

	/**
	 * Redirect to referrer with some flashdata
	 *
	 * @access      private
	 * @param       string
	 * @return      void
	 */
	private function _go_back($with_message)
	{
		ee()->session->set_flashdata('error_message', $with_message);
		ee()->functions->redirect($_SERVER['HTTP_REFERER']);
	}

	// --------------------------------------------------------------------

	/**
	 * Search parameters for range:field params and return set of ids that match it
	 *
	 * @access      private
	 * @return      void
	 */
	private function _filter_by_ranges()
	{
		// --------------------------------------
		// Bail out if no entry ids
		// --------------------------------------

		if ( ! $this->_has_ids()) return;

		// --------------------------------------
		// initiate ranges
		// --------------------------------------

		$ranges = $range_ids = array();

		// --------------------------------------
		// Check parameters and analyse range params
		// --------------------------------------

		foreach ($this->params AS $key => $val)
		{
			if ( ! preg_match('#^range-?(from|to)?:([\w\-]+)$#', $key, $match)) continue;

			// Init this range
			$from = $to = NULL;
			$fromto = $match[1];
			$field  = $match[2];

			// range:field="x;y" syntax
			if ( ! $fromto)
			{
				if (strpos($val, ';') !== FALSE)
				{
					list($from, $to) = explode(';', $val, 2);
				}
			}
			else
			{
				// Sets $from or $to to the right value
				$$fromto = $val;
			}

			if ( ! is_null($from))
			{
				$ranges[$field]['from'] = $from;
			}

			if ( ! is_null($to))
			{
				$ranges[$field]['to'] = $to;
			}
		}

		// --------------------------------------
		// Exit of no ranges were defined
		// --------------------------------------

		if ( ! $ranges) return;

		// --------------------------------------
		// Ranges found, go query them
		// --------------------------------------

		// Initiate array of WHERE clauses
		$where = array();

		foreach ($ranges AS $field => $range)
		{
			// Skip if field isn't a valid one
			if ( ! ($field_id = $this->_get_field_id($field))) continue;

			$sql_field = 'field_id_'.$field_id;

			if (isset($range['from']) && $range['from'])
			{
				// Convert to timestamp if datefield and not numeric
				if ($this->_is_date_field($field) && ! is_numeric($range['from']))
				{
					$range['from'] = ($time = strtotime($range['from'])) ? $time : $range['from'];
				}

				$where[] = "({$sql_field} >= '".ee()->db->escape_str($range['from'])."')";
			}

			if (isset($range['to']) && $range['to'])
			{
				// Convert to timestamp if datefield and not numeric
				if ($this->_is_date_field($field) && ! is_numeric($range['to']))
				{
					$range['to'] = ($time = strtotime($range['to'])) ? $time : $range['to'];
				}

				$where[] = "({$sql_field} <= '".ee()->db->escape_str($range['to'])."')";
			}
		}

		if ($where)
		{
			$this->_log('Applying filter by ranges');

			// Filter by channel ids
			if ($channel_ids = $this->_get_channels_ids())
			{
				ee()->db->where_in('channel_id', $channel_ids);
			}

			// Possibly limit by entry ids
			if ( ! empty($this->entry_ids))
			{
				ee()->db->where_in('entry_id', $this->entry_ids);
			}

			// Query the rest of the ids
			$query = ee()->db->select('entry_id')
			       ->from('channel_data')
			       ->where_in('site_id', $this->site_ids)
			       ->where(implode(' AND ', $where))
			       ->get();

			$range_ids = array_merge($range_ids, low_flatten_results($query->result_array(), 'entry_id'));

			// Adds found range ids to
			$this->_add_to_ids(array_unique($range_ids));
		}
	}

	/**
	 * Search parameters for (parents|children):field params and return set of ids that match it
	 *
	 * @access      private
	 * @return      void
	 */
	private function _filter_by_relationships()
	{
		// --------------------------------------
		// Bail out if no entry ids
		// --------------------------------------

		if ( ! $this->_has_ids()) return;

		foreach ($this->params AS $key => $val)
		{
			if ( ! $val || ! preg_match('#^(parent|child):([\w\-]+)$#', $key, $match)) continue;

			// List out match
			list($param, $type, $field) = $match;

			// Get the field id, skip if non-existent
			if ( ! ($field_id = $this->_get_field_id($field))) continue;

			// Do we need to check all values?
			$all = FALSE;

			if (strpos($val, '&'))
			{
				$all = TRUE;
				$val = str_replace('&', '|', $val);
			}

			// Get the parameter properly
			list($ids, $in) = low_explode_param($val);

			// Init vars
			$rel_ids = $table = FALSE;
			$get_children = ($type == 'parent');

			// Native relationship field
			if ($this->_is_rel_field($field))
			{
				// Set the table & attributes
				$table = 'relationships';
				$select = $get_children ? 'rel_child_id' : 'rel_parent_id';
				$where  = $get_children ? 'rel_parent_id' : 'rel_child_id';
			}
			elseif ($this->_is_playa_field($field_id))
			{
				// Set the table
				$table = 'playa_relationships';
				$select = $get_children ? 'child_entry_id' : 'parent_entry_id';
				$where  = $get_children ? 'parent_entry_id' : 'child_entry_id';

				// Focus on specific field
				ee()->db->where('parent_field_id', $field_id);
			}

			// Log it
			$this->_log("Applying filter by {$type} relationship");

			// Execute query
			if ($table)
			{
				ee()->db->select($select.' AS entry_id')
				            ->from($table)
				            ->{$in ? 'where_in' : 'where_not_in'}($where, $ids);

				// Limit by already existing ids
				if ( ! empty($this->entry_ids))
				{
					ee()->db->where_in($select, $this->entry_ids);
				}

				// Do the having-trick to account for *all* given entry ids
				if ($in && $all)
				{
					ee()->db->select('COUNT(*) AS num')
					             ->group_by($select)
					             ->having('num', count($ids));
				}

				$query   = ee()->db->get();
				$rel_ids = low_flatten_results($query->result_array(), 'entry_id');
			}

			// Add it
			$this->_add_to_ids($rel_ids);
		}
	}

	/**
	 * Allows for search:title="foo|bar" parameter
	 *
	 * @access     private
	 * @return     void
	 */
	private function _filter_by_titles()
	{
		// --------------------------------------
		// Bail out if no entry ids
		// --------------------------------------

		if ( ! $this->_has_ids()) return;

		// --------------------------------------
		// Check if search:title is there
		// --------------------------------------

		$key = 'search:title';

		if (($val = (string) @$this->params[$key]) == '') return;

		// --------------------------------------
		// Log it
		// --------------------------------------

		$this->_log('Applying filter by title search');

		// --------------------------------------
		// Loop through search filters and create where clause accordingly
		// --------------------------------------

		// Initiate some vars
		$exact = $all = FALSE;
		$val   = $this->_prep_param($key, $val);
		$field = 'title';

		// Exact matches
		if (substr($val, 0, 1) == '=')
		{
			$val   = substr($terms, 1);
			$exact = TRUE;
		}

		// All items? -> && instead of |
		if (strpos($val, '&&') !== FALSE)
		{
			$all = TRUE;
			$val = str_replace('&&', '|', $val);
		}

		// Convert parameter to bool and array
		list($items, $in) = low_explode_param($val);

		// Init sql for where clause
		$sql = array();

		// Loop through each sub-item of the filter an create sub-clause
		foreach ($items AS $item)
		{
			// Convert IS_EMPTY constant to empty string
			$empty = ($item == 'IS_EMPTY');
			$item  = str_replace('IS_EMPTY', '', $item);

			// whole word? Regexp search
			if (substr($item, -2) == '\W')
			{
				$operand = $in ? 'REGEXP' : 'NOT REGEXP';
				$item    = "'[[:<:]]".preg_quote(substr($item, 0, -2))."[[:>:]]'";
			}
			else
			{
				// Not a whole word
				if ($exact || $empty)
				{
					// Use exact operand if empty or = was the first char in param
					$operand = $in ? '=' : '!=';
					$item = "'".ee()->db->escape_str($item)."'";
				}
				else
				{
					// Use like operand in all other cases
					$operand = $in ? 'LIKE' : 'NOT LIKE';
					$item = "'%".ee()->db->escape_str($item)."%'";
				}
			}

			// Add sub-clause to this statement
			$sql[] = sprintf("(%s %s %s)", $field, $operand, $item);
		}

		// Inclusive or exclusive
		$andor = $all ? ' AND ' : ' OR ';

		// Add complete clause to where array
		$where = (count($sql) == 1) ? $sql[0] : '('.implode($andor, $sql).')';

		// --------------------------------------
		// Where now contains a list of clauses
		// --------------------------------------

		// Filter by channel ids
		if ($channel_ids = $this->_get_channels_ids())
		{
			ee()->db->where_in('channel_id', $channel_ids);
		}

		// Possibly limit by entry ids
		if ( ! empty($this->entry_ids))
		{
			ee()->db->where_in('entry_id', $this->entry_ids);
		}

		$query = ee()->db->select('entry_id')
		       ->from('channel_titles')
		       ->where_in('site_id', $this->site_ids)
		       ->where($where)
		       ->get();

		$entry_ids = low_flatten_results($query->result_array(), 'entry_id');

		$this->_add_to_ids($entry_ids);
	}

	/**
	 * Allows for category groups filtering: (1|2|3) && (4|5|6)
	 *
	 * @access     private
	 * @return     void
	 */
	private function _filter_by_grouped_categories()
	{
		// --------------------------------------
		// Bail out if no entry ids
		// --------------------------------------

		if ( ! $this->_has_ids()) return;

		// --------------------------------------
		// See if there are groups present
		// --------------------------------------

		$groups = array();

		foreach ($this->params AS $key => $val)
		{
			// Get groups only
			if (substr($key, 0, 9) != 'category:') continue;

			// Check if we're negating these
			$groups[] = $this->_prep_param($key, $val);
		}

		$groups = array_filter($groups);

		// --------------------------------------
		// If there are groups, compose query
		// --------------------------------------

		if ($groups)
		{
			// Log it
			$this->_log('Applying filter by grouped categories');

			// Prepare main select and subselect
			$sql = "SELECT DISTINCT(entry_id) FROM `exp_category_posts` WHERE cat_id ";
			$sql_tmpl = 'SELECT entry_id FROM `exp_category_posts` WHERE cat_id %s (%s)';

			// Loop through groups
			foreach ($groups AS $i => $val)
			{
				// Get the parameter
				list($ids, $in) = low_explode_param($val);

				$sql_in = $in ? 'IN' : 'NOT IN';
				$sql_ids = implode(',', $ids);

				// First one in normal select
				if ($i == 0)
				{
					$sql .= "{$sql_in} ($sql_ids) ";
				}
				// the rest in subselects
				else
				{
					$sql .= 'AND entry_id IN ('. sprintf($sql_tmpl, $sql_in, $sql_ids) .') ';
				}
			}

			// Query it
			$query = ee()->db->query($sql);

			// And add results to the internal array
			$entry_ids = low_flatten_results($query->result_array(), 'entry_id');

			$this->_add_to_ids($entry_ids);
		}
	}

	/**
	 * Add given IDs to $this->entry_ids, refining it the more it is called
	 *
	 * @access      private
	 * @param       mixed
	 * @return      void
	 */
	private function _add_to_ids($ids = FALSE)
	{
		// Do nothing if no ids are given
		if ($ids === FALSE) return;

		// Set this->entry_ids to empty array if empty
		if (is_array($ids))
		{
			if (empty($ids))
			{
				$this->entry_ids = array();
			}
			else
			{
				$this->entry_ids
					= (is_array($this->entry_ids))
					? array_intersect($this->entry_ids, $ids)
					: $ids;

				// Keep it clean
				$this->entry_ids = array_unique(array_filter($this->entry_ids));
			}
		}
	}

	/**
	 * Check if there are entry ids given or an empty set, which means no_results
	 *
	 * @access      private
	 * @return      bool
	 */
	private function _has_ids()
	{
		return ! (is_array($this->entry_ids) && empty($this->entry_ids));
	}

	// --------------------------------------------------------------------

	/**
	 * Prep param value
	 */
	private function _prep_param($key, $val)
	{
		$val = $this->_require_all_value($key, $val);
		$val = $this->_exclude_value($key, $val);
		$val = $this->_exact_value($key, $val);
		return $val;
	}

	/**
	 * Check if given key is in the require_all="" parameter
	 */
	private function _require_all_value($key, $val)
	{
		if ( ! empty($this->params['require_all']))
		{
			list($fields, $in) = low_explode_param($this->params['require_all']);

			if (in_array($key, $fields))
			{
				$amp = (substr($key, 0, 7) == 'search:') ? '&&' : '&';
				$val = str_replace('|', $amp, $val);
			}
		}

		return $val;
	}

	/**
	 * Check if given key is in the exclude="" parameter
	 */
	private function _exclude_value($key, $val)
	{
		if ( ! empty($this->params['exclude']))
		{
			list($fields, $in) = low_explode_param($this->params['exclude']);

			if (in_array($key, $fields) && substr($val, 0, 4) != 'not ')
			{
				$val = 'not '.$val;
			}
		}

		return $val;
	}

	/**
	 * Check if given key is in the exact="" parameter
	 */
	private function _exact_value($key, $val)
	{
		if ( ! empty($this->params['exact']))
		{
			list($fields, $in) = low_explode_param($this->params['exact']);

			if (in_array($key, $fields) && substr($val, 0, 1) != '=')
			{
				$val = '='.$val;
			}
		}

		return $val;
	}

	// --------------------------------------------------------------------

	/**
	 * Add where clause to current query for exact matching
	 *
	 * @access      private
	 * @param       mixed
	 * @param       string
	 * @param       bool
	 * @return      void
	 */
	private function _where_exact($terms, $andor = 'AND', $loose = FALSE)
	{
		// Init where clause
		$where = array();

		// Look at terms
		if ( ! is_array($terms))
		{
			$terms = array($terms);
		}

		// Make unique
		$terms = array_unique($terms);

		// Loop through the terms and process them
		foreach ($terms AS $term)
		{
			// Clean it first
			$term = low_clean_string($term);

			// Skip if empty
			if ( ! strlen($term)) continue;

			// By default, add space around term
			$term = " {$term} ";

			// Remove spaces depending on loose ends
			switch ($loose)
			{
				case 'left':
					$term = ltrim($term);
				break;

				case 'right':
					$term = rtrim($term);
				break;

				case 'both':
					$term = trim($term);
				break;
			}

			// Add to where clause
			// Use space before term to force word lookup
			// instead of substring
			$where[] = " (index_text LIKE '%{$term}%') ";
		}

		// Add to where clause
		if ($where)
		{
			$sql_where = implode($andor, $where);
			ee()->db->where("({$sql_where})", '', FALSE);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get collections from DB or Cache
	 *
	 * @access      private
	 * @return      array
	 */
	private function _get_collections()
	{
		if ( ! ($set = low_get_cache($this->package, 'collections')))
		{
			ee()->db->order_by('collection_label', 'asc');
			$set = ee()->low_search_collection_model->get_all();

			foreach ($set AS &$row)
			{
				$row['settings'] = $this->decode($row['settings'], FALSE);
			}

			$set = low_associate_results($set, 'collection_id');

			low_set_cache($this->package, 'collections', $set);
		}

		return $set;
	}

	/**
	 * Filter collections by given key and values
	 *
	 * @access      private
	 * @param       string
	 * @param       mixed
	 * @param       bool
	 * @param       array
	 * @return      array
	 */
	private function _filter_collections($key, $value, $in = TRUE, $set = array())
	{
		// Initiate output
		$filtered = array();

		// If set is not given, get all
		if ( ! $set)
		{
			$set = $this->_get_collections();
		}

		// Force the value to be an array
		if ( ! is_array($value))
		{
			$value = array($value);
		}

		// Loop through items in set and filter it
		foreach ($set AS $row)
		{
			if ($in === in_array($row[$key], $value))
			{
				$filtered[] = $row;
			}
		}

		// Return the filtered set
		return $filtered;
	}

	/**
	 * Get collection ids by parameter
	 *
	 * @access      private
	 * @return      void
	 */
	private function _set_collection_ids()
	{
		// Bail out if no collection is given
		if (empty($this->params['collection'])) return;

		$in = TRUE;
		$collection = $this->params['collection'];

		// If not array, it's a parameter. Convert to array and bool (inlclusive or not)
		if ( ! is_array($collection))
		{
			list($collection, $in) = low_explode_param($collection);
		}

		// If it's anything other than an inclusive array of ids, get the details
		if ( ! ($numeric = low_array_is_numeric($collection) && $in))
		{
			// Check parameter to filter by
			$key = $numeric ? 'collection_id' : 'collection_name';

			// Get collections and flatten to ids
			$collection_ids = low_flatten_results($this->_filter_collections($key, $collection, $in), 'collection_id');
		}
		else
		{
			// Copy numeric array as is
			$collection_ids = $collection;
		}

		$this->collection_ids = $collection_ids;
	}

	/**
	 * Get channel IDs based on set collection IDs
	 */
	private function _get_channels_ids()
	{
		$channel_ids = NULL;

		// Filter by channel ids
		if ($this->collection_ids)
		{
			$set = $this->_filter_collections('collection_id', $this->collection_ids);

			// Filter by channel (increases performance big time)
			$channel_ids = array_unique(low_flatten_results($set, 'channel_id'));
		}

		return $channel_ids;
	}

	// --------------------------------------------------------------------

	/**
	 * Get site ids by parameter
	 *
	 * @access      private
	 * @return      void
	 */
	private function _set_site_ids()
	{
		if (empty($this->params['site']))
		{
			// No site param? limit to current site only
			$this->site_ids[] = $this->site_id;
		}
		else
		{
			// Read sites from parameter
			list($sites, $in) = low_explode_param($this->params['site']);

			// Shortcut to all sites
			$all_sites = ee()->TMPL->sites;

			// Numeric?
			$check = low_array_is_numeric($sites) ? 'key' : 'val';

			// Loop through all sites and add some of them
			foreach ($all_sites AS $key => $val)
			{
				if ($in === in_array($$check, $sites))
				{
					$this->site_ids[$val] = $key;
				}
			}

			// And set to global TMPL
			ee()->TMPL->site_ids = $this->site_ids;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Transform {low_search:url} to {exp:low_search:url query=""}
	 *
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	private function _rewrite_url_vars($haystack)
	{
		$needle  = LD.'low_search:url';
		$replace = LD.'exp:low_search:url query="%s"';

		if (strpos($haystack, $needle) !== FALSE)
		{
			$query = $this->encode(array_filter($this->params, 'low_not_empty'));

			$haystack = str_replace($needle, sprintf($replace, $query), $haystack);
		}

		return $haystack;
	}

	/**
	 * Replace query_string var, automatically fix pagination links
	 *
	 * @access     private
	 * @param      string
	 * @return     string
	 */
	private function _maintain_query_string($tagdata)
	{
		// Don't do anything if query's encoded
		if ($this->get_settings('encode_query') == 'y') return $tagdata;

		// Query string var
		$var = LD.$this->prefix.'query_string'.RD;

		// Fix pagination for Results tag
		if (ee()->TMPL->fetch_param('low_search') == 'yes')
		{
			// Load up URL helper
			ee()->load->helper('url');

			// Get current URL
			$url = current_url();

			// Strip away pagination segment
			$url = preg_replace('#/P\d+/?$#', '', $url);

			// Make it safe
			$url = preg_quote($url, '#');

			// Now find all similar URLs in tagdata without the var next to it
			$tagdata = preg_replace("#({$url}(/P\d+)?/?)(?!".preg_quote($var, '#').")#", '$1'.$var, $tagdata);
		}

		// Get the query string
		if ($qs = (string) ee()->input->server('QUERY_STRING'))
		{
			$qs = '?' . $qs;
		}

		// Replace {low_search_query_string} vars
		$tagdata = str_replace($var, $qs, $tagdata);

		return $tagdata;
	}

	/**
	 * Post parse tagdata
	 *
	 * @access      private
	 * @param       string
	 * @return      string
	 */
	private function _post_parse($tagdata)
	{
		// If we're not encoding, maintain query string vars/URLs
		$tagdata = $this->_maintain_query_string($tagdata);

		// CLean up prefixed variables
		$tagdata = preg_replace('#'.LD.$this->prefix.'.*?'.RD.'#i', '', $tagdata);

		// Prep {if foo IN (bar)} conditionals
		$tagdata = low_prep_in_conditionals($tagdata);

		// Transform {low_search:url ...} to their tag syntax equivalents
		// to avoid parse order woes
		$tagdata = $this->_rewrite_url_vars($tagdata);

		return $tagdata;
	}

	// --------------------------------------------------------------------

	/**
	 * Log message to Template Logger
	 *
	 * @access     private
	 * @param      string
	 * @return     void
	 */
	private function _log($msg)
	{
		ee()->TMPL->log_item("Low Search: {$msg}");
	}

} // End Class

/* End of file mod.low_search.php */