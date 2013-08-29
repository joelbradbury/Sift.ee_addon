<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include config file
include(PATH_THIRD.'low_search/config.php');

/**
 * Low Search Base Class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_base {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Add-on name
	 *
	 * @var        string
	 * @access     public
	 */
	public $name = LOW_SEARCH_NAME;

	/**
	 * Add-on version
	 *
	 * @var        string
	 * @access     public
	 */
	public $version = LOW_SEARCH_VERSION;

	/**
	 * URL to module docs
	 *
	 * @var        string
	 * @access     public
	 */
	public $docs_url = LOW_SEARCH_DOCS;

	/**
	 * Settings array
	 *
	 * @var        array
	 * @access     public
	 */
	public $settings = array();

	// --------------------------------------------------------------------

	/**
	 * Package name
	 *
	 * @var        string
	 * @access     protected
	 */
	protected $package = LOW_SEARCH_PACKAGE;

	/**
	 * Main class shortcut
	 *
	 * @var        string
	 * @access     protected
	 */
	protected $class_name;

	/**
	 * Site id shortcut
	 *
	 * @var        int
	 * @access     protected
	 */
	protected $site_id;

	/**
	 * Shortcut to current member group
	 *
	 * @var        int
	 * @access     protected
	 */
	protected $member_group;

	/**
	 * Base url for module
	 *
	 * @var        string
	 * @access     protected
	 */
	protected $base_url;

	/**
	 * Base url for extension
	 *
	 * @var        string
	 * @access     protected
	 */
	protected $ext_url;

	/**
	 * Data array for views
	 *
	 * @var        array
	 * @access     protected
	 */
	protected $data = array();

	/**
	 * Default settings
	 *
	 * @var        array
	 * @access     protected
	 */
	protected $default_settings = array(
		'license_key'         => '',
		'encode_query'        => 'y',
		'min_word_length'     => '4',
		'excerpt_length'      => '50',
		'excerpt_hilite'      => '',
		'title_hilite'        => '',
		'batch_size'          => '100',
		'default_search_mode' => 'all',
		'default_result_page' => 'search/results',
		'search_log_size'     => '500',
		'stop_words'
			// http://dev.mysql.com/doc/refman/5.5/en/fulltext-stopwords.html
		=> "a's able about above according accordingly across actually after afterwards again against ain't
			all allow allows almost alone along already also although always am among amongst an and another
			any anybody anyhow anyone anything anyway anyways anywhere apart appear appreciate appropriate are
			aren't around as aside ask asking associated at available away awfully be became because become
			becomes becoming been before beforehand behind being believe below beside besides best better between
			beyond both brief but by c'mon c's came can can't cannot cant cause causes certain certainly changes
			clearly co com come comes concerning consequently consider considering contain containing contains
			corresponding could couldn't course currently definitely described despite did didn't different do
			does doesn't doing don't done down downwards during each edu eg eight either else elsewhere enough
			entirely especially et etc even ever every everybody everyone everything everywhere ex exactly example
			except far few fifth first five followed following follows for former formerly forth four from further
			furthermore get gets getting given gives go goes going gone got gotten greetings had hadn't happens
			hardly has hasn't have haven't having he he's hello help hence her here here's hereafter hereby herein
			hereupon hers herself hi him himself his hither hopefully how howbeit however i'd i'll i'm i've ie if
			ignored immediate in inasmuch inc indeed indicate indicated indicates inner insofar instead into
			inward is isn't it it'd it'll it's its itself just keep keeps kept know known knows last lately later
			latter latterly least less lest let let's like liked likely little look looking looks ltd mainly many
			may maybe me mean meanwhile merely might more moreover most mostly much must my myself name namely nd
			near nearly necessary need needs neither never nevertheless new next nine no nobody non none noone nor
			normally not nothing novel now nowhere obviously of off often oh ok okay old on once one ones only
			onto or other others otherwise ought our ours ourselves out outside over overall own particular
			particularly per perhaps placed please plus possible presumably probably provides que quite qv rather
			rd re really reasonably regarding regardless regards relatively respectively right said same saw say
			saying says second secondly see seeing seem seemed seeming seems seen self selves sensible sent
			serious seriously seven several shall she should shouldn't since six so some somebody somehow someone
			something sometime sometimes somewhat somewhere soon sorry specified specify specifying still sub such
			sup sure t's take taken tell tends th than thank thanks thanx that that's thats the their theirs them
			themselves then thence there there's thereafter thereby therefore therein theres thereupon these they
			they'd they'll they're they've think third this thorough thoroughly those though three through
			throughout thru thus to together too took toward towards tried tries truly try trying twice two un
			under unfortunately unless unlikely until unto up upon us use used useful uses using usually value
			various very via viz vs want wants was wasn't way we we'd we'll we're we've welcome well went were
			weren't what what's whatever when whence whenever where where's whereafter whereas whereby wherein
			whereupon wherever whether which while whither who who's whoever whole whom whose why will willing
			wish with within without won't wonder would wouldn't yes yet you you'd you'll you're you've your
			yours yourself yourselves zero",
		'ignore_words' => '',
		'can_manage'   => array(),
		'can_replace'  => array(),
		'can_view_search_log'  => array(),
		'can_view_replace_log' => array()
	);

	/**
	 * Permission options
	 *
	 * @var        array
	 * @access     protected
	 */
	protected $permissions = array('can_manage', 'can_replace', 'can_view_search_log', 'can_view_replace_log');

	/**
	 * Low Search specific params
	 *
	 * @var        array
	 * @access     protected
	 */
	protected $custom_params = array(
		'collection', 'loose_ends', 'keywords', 'min_score',
		'require_all', 'required', 'result_page', 'search_mode',
		'exclude', 'exact', 'orderby_sort'
	);

	/**
	 * Low Search prefix for
	 *
	 * @var        string
	 * @access     protected
	 */
	protected $prefix = 'low_search_';

	// --------------------------------------------------------------------

	/**
	 * Search modes
	 *
	 * @var        array
	 * @access     private
	 */
	private $_modes = array('any', 'all', 'exact');

	/**
	 * Control Panel assets
	 *
	 * @var        array
	 * @access     private
	 */
	private $mcp_assets = array();

	/**
	 * JS Lang lines
	 *
	 * @var        array
	 * @access     private
	 */
	private $js_lang_lines = array(
		'deleting',
		'optimizing',
		'done',
		'no_keywords_given',
		'no_fields_selected',
		'no_entries_selected',
		'working'
	);

	/**
	 * Characters to translate when query en/decoding
	 *
	 * @var        array
	 * @access     private
	 */
	private static $uri_chars = array(
		'/' => '_'
	);

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 *
	 * @access     public
	 * @return     void
	 */
	public function __construct()
	{
		// -------------------------------------
		//  Define the package path
		// -------------------------------------

		ee()->load->add_package_path(PATH_THIRD.$this->package);

		// -------------------------------------
		//  Load helper
		// -------------------------------------

		ee()->load->helper($this->package);

		// -------------------------------------
		//  Libraries
		// -------------------------------------

		ee()->load->library('Low_search_model');
		ee()->load->library('javascript');

		// -------------------------------------
		//  Load the models
		// -------------------------------------

		Low_search_model::load_models();

		// -------------------------------------
		//  Class name shortcut
		// -------------------------------------

		$this->class_name = ucfirst(LOW_SEARCH_PACKAGE);

		// -------------------------------------
		//  Get site shortcut
		// -------------------------------------

		$this->site_id = (int) ee()->config->item('site_id');

		// -------------------------------------
		//  Get member group shortcut
		// -------------------------------------

		$this->member_group = (int) @ee()->session->userdata['group_id'];
	}

	// --------------------------------------------------------------------

	/**
	 * Return available search modes
	 *
	 * @access     protected
	 * @return     array
	 */
	protected function search_modes()
	{
		return $this->_modes;
	}

	// --------------------------------------------------------------------

	/**
	 * Get settings
	 *
	 * @access     protected
	 * @param      string
	 * @return     mixed
	 */
	protected function get_settings($which = FALSE)
	{
		if (empty($this->settings))
		{
			// Check cache
			if (($this->settings = low_get_cache($this->package, 'settings')) === FALSE)
			{
				// Not in cache? Get from DB and add to cache
				$query = ee()->db->select('settings')
				       ->from('extensions')
				       ->where('class', $this->class_name.'_ext')
				       ->limit(1)
				       ->get();

				$this->settings = (array) @unserialize($query->row('settings'));

				// Add to cache
				low_set_cache($this->package, 'settings', $this->settings);
			}
		}

		// Always fallback to default settings
		$this->settings = array_merge($this->default_settings, $this->settings);

		if ($which !== FALSE)
		{
			return isset($this->settings[$which]) ? $this->settings[$which] : FALSE;
		}
		else
		{
			return $this->settings;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Permissions: can current user manage collections?
	 *
	 * @access      protected
	 * @return      bool
	 */
	protected function can_manage()
	{
		return $this->_can_i('manage');
	}

	/**
	 * Permissions: can current user find and replace?
	 *
	 * @access      protected
	 * @return      bool
	 */
	protected function can_replace()
	{
		return $this->_can_i('replace');
	}

	/**
	 * Permissions: can current user view search log?
	 *
	 * @access      protected
	 * @return      bool
	 */
	protected function can_view_search_log()
	{
		return $this->_can_i('view_search_log');
	}

	/**
	 * Permissions: can current user view replace log?
	 *
	 * @access      protected
	 * @return      bool
	 */
	protected function can_view_replace_log()
	{
		return $this->_can_i('view_replace_log');
	}

	/**
	 * Can I do what? SuperAdmins always can.
	 *
	 * @access      private
	 * @return      bool
	 */
	private function _can_i($do_what)
	{
		$can = (array) $this->get_settings('can_'.$do_what);
		return ($this->member_group === 1 || in_array($this->member_group, $can));
	}

	// --------------------------------------------------------------------

	/**
	 * Sets base url for views
	 *
	 * @access     protected
	 * @return     void
	 */
	protected function set_base_url()
	{
		$this->data['base_url'] = $this->base_url = BASE.AMP.'C=addons_modules&amp;M=show_module_cp&amp;module='.$this->package;
		$this->data['ext_url'] = $this->ext_url = BASE.AMP.'C=addons_extensions&amp;M=extension_settings&amp;file='.$this->package;
	}

	/**
	 * View add-on page
	 *
	 * @access     protected
	 * @param      string
	 * @return     string
	 */
	protected function view($file)
	{
		// -------------------------------------
		//  Load CSS and JS
		// -------------------------------------

		$version = '&amp;v=' . (LOW_SEARCH_DEBUG ? time() : LOW_SEARCH_VERSION);

		ee()->cp->load_package_css($this->package.$version);
		ee()->cp->load_package_js($this->package.$version);

		// -------------------------------------
		//  Add feedback msg to output
		// -------------------------------------

		if ($this->data['message'] = ee()->session->flashdata('msg'))
		{
			ee()->javascript->output(array(
				'$.ee_notice("'.lang($this->data['message']).'",{type:"success",open:true});',
				'window.setTimeout(function(){$.ee_notice.destroy()}, 2000);'
			));
		}

		// -------------------------------------
		//  Add permissions to data
		// -------------------------------------

		foreach ($this->permissions AS $perm)
		{
			$this->data['member_'.$perm] = $this->$perm();
		}

		$this->data['member_group'] = $this->member_group;

		// -------------------------------------
		//  Add menu to page if manager
		// -------------------------------------

		$nav = array('low_search_module_name' => $this->base_url);

		if ($this->can_manage())
		{
			$nav['manage_collections']   = $this->base_url.AMP.'method=manage';
			$nav['create_new_collection'] = $this->base_url.AMP.'method=edit_collection';
		}

		if ($this->can_view_search_log())
		{
			$nav['view_search_log'] = $this->base_url.AMP.'method=search_log';
		}

		if ($this->can_replace())
		{
			$nav['find_replace'] = $this->base_url.AMP.'method=find';
		}

		if ($this->can_view_replace_log())
		{
			$nav['view_replace_log'] = $this->base_url.AMP.'method=replace_log';
		}

		ee()->cp->set_right_nav($nav);


		// -------------------------------------
		//  Add JS language object
		// -------------------------------------

		$lang = array();

		foreach ($this->js_lang_lines AS $line)
		{
			$lang[$line] = lang($line);
		}

		ee()->javascript->output(";$.LOW_lang = ".json_encode($lang).";");


		return ee()->load->view($file, $this->data, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * Build collection index
	 *
	 * @access      protected
	 * @return      array
	 */
	protected function _build_index($collection_id = FALSE, $entry_ids = FALSE, $start = FALSE)
	{
		// --------------------------------------
		// Check for collection_id or entry_id
		// --------------------------------------

		$collection_id = ($collection_id !== FALSE) ? $collection_id : ee()->input->get_post('collection_id');
		$entry_ids     = ($entry_ids !== FALSE) ? $entry_ids : ee()->input->get_post('entry_id');

		// --------------------------------------
		// Either collection_id or entry_id or both must be given
		// --------------------------------------

		if ( ! ($collection_id || $entry_ids)) show_error(ee()->lang->line('not_authorized'));

		// --------------------------------------
		// Start building query to get collection details
		// --------------------------------------

		ee()->db->select('lsc.collection_id, lsc.channel_id, lsc.settings, lsc.site_id');
		ee()->db->from('low_search_collections lsc');

		// --------------------------------------
		// If there's a collection id, limit query by that one
		// --------------------------------------

		if ($collection_id)
		{
			ee()->db->where('lsc.collection_id', $collection_id);
		}

		// --------------------------------------
		// If there's an entry_id, limit query by those
		// --------------------------------------

		if ($entry_ids)
		{
			// Force array
			if ( ! is_array($entry_ids))
			{
				$entry_ids = preg_split('/\D+/', $entry_ids);
			}

			// Get collections for given entries
			ee()->db->select('GROUP_CONCAT(ct.entry_id) AS entries');
			ee()->db->join('channel_titles ct', 'lsc.channel_id = ct.channel_id');
			ee()->db->where_in('entry_id', $entry_ids);
			ee()->db->group_by('lsc.collection_id');
		}

		// --------------------------------------
		// Execute query and get results. Bail out if none
		// --------------------------------------

		if ( ! ($collections = ee()->db->get()->result_array()))
		{
			die('No collections found');
		}

		$collections = low_associate_results($collections, 'collection_id');
		$channel_ids = array_unique(low_flatten_results($collections, 'channel_id'));

		// --------------------------------------
		// Get batch size
		// --------------------------------------

		$batch_size = $this->get_settings('batch_size');

		// --------------------------------------
		// Get total number of entries that need to be indexed
		// --------------------------------------

		if ($entry_ids)
		{
			$num_entries = count($entry_ids);
		}
		else
		{
			ee()->db->where_in('channel_id', $channel_ids);
			$num_entries = ee()->db->count_all_results('channel_titles');
		}

		// --------------------------------------
		// Get weighted field settings only, keep track of field ids
		// --------------------------------------

		$fields  = array();
		$entries = array();

		foreach ($collections AS &$col)
		{
			$col['settings'] = array_filter($this->decode($col['settings'], FALSE));

			// Add field ids to fields array
			$fields = array_merge($fields, array_keys($col['settings']));

			if (isset($col['entries']))
			{
				foreach (explode(',', $col['entries']) AS $eid)
				{
					$entries[$eid][] = $col['collection_id'];
				}
			}
		}

		// Get rid of duplicate field ids
		$fields = array_unique($fields);
		sort($fields);

		// --------------------------------------
		// Let an extension take over?
		// --------------------------------------

		if (ee()->extensions->active_hook('low_search_get_index_entries') === TRUE)
		{
			$index_entries = ee()->extensions->call('low_search_get_index_entries',
				$fields, $channel_ids, $entry_ids, $start, $batch_size);
		}
		else
		{
			// --------------------------------------
			// Create select list
			// --------------------------------------

			$select = array('t.entry_id', 't.channel_id');

			foreach ($fields AS $field_id)
			{
				// Skip non-numeric settings
				if ( ! is_numeric($field_id)) continue;

				$select[] = ($field_id == '0') ? 't.title AS field_id_0' : 'd.field_id_'.$field_id;
			}

			// --------------------------------------
			// Optional: Limit to given entries
			// --------------------------------------

			if ($entry_ids)
			{
				ee()->db->where_in('t.entry_id', $entry_ids);
			}

			// --------------------------------------
			// Optional: Limit entries by batch size
			// --------------------------------------

			if ($start !== FALSE && is_numeric($start))
			{
				ee()->db->limit($batch_size, $start);
			}

			// --------------------------------------
			// Query it!
			// --------------------------------------

			$query = ee()->db->select($select)
			       ->from('channel_titles t')
			       ->join('channel_data d', 't.entry_id = d.entry_id', 'inner')
			       ->where_in('t.channel_id', $channel_ids)
			       ->order_by('entry_id', 'asc')
			       ->get();

			$index_entries = $query->result_array();
		}

		// --------------------------------------
		// Loop thru the entries to index
		// --------------------------------------

		foreach ($index_entries AS $row)
		{
			// If it's a given entry, loop thru its collections and rebuild index
			if (isset($entries[$row['entry_id']]))
			{
				foreach ($entries[$row['entry_id']] AS $col_id)
				{
					// Collection details
					$col = $collections[$col_id];

					// Build index for this entry/collection combo
					ee()->low_search_index_model->build($col, $row);
				}
			}
			// If it's not a given entry, loop thru all collections (which should be 1) and rebuild index
			else
			{
				foreach ($collections AS $col_id => $col)
				{
					if ($row['channel_id'] == $col['channel_id'])
					{
						ee()->low_search_index_model->build($col, $row);
					}
				}
			}
		}

		// Determine new start
		$new_start = $start + $batch_size;

		// Are we done?
		$done = ($new_start >= $num_entries);

		// --------------------------------------
		// Prep response
		// --------------------------------------

		$response = array(
			'status'        => $done ? 'done' : 'building',
			'start'         => (int) $new_start,
			'total_entries' => (int) $num_entries,
			'processed'     => count($index_entries)
		);

		return $response;
	}

	// --------------------------------------------------------------------

	/**
	 * Encode an array for use in the URI
	 */
	public function encode($array = array(), $url = TRUE)
	{
		// Filter the array
		$array = array_filter($array, 'low_not_empty');

		// basic stuff
		//$str = ee()->javascript->generate_json($array);
		$str = json_encode($array);
		//$str = serialize($array);

		// If we want a url-safe encode, base64-it
		if ($url)
		{
			// Our own version of URL encoding
			$str = base64_encode($str);

			// Clean stuff
			$str = rtrim($str, '=');
			$str = strtr($str, self::$uri_chars);
		}

		return $str;
	}

	/**
	 * Decode a query back to the array
	 */
	public function decode($str = '', $url = TRUE)
	{
		// Bail out if not valid
		if ( ! (is_string($str) && strlen($str))) return array();

		// Override url setting if we're looking at an encoded string
		if (substr($str, 0, 3) == 'YTo') $url = TRUE;

		// Are we decoding a url-safe query?
		if ($url)
		{
			// Translate back
			$str = strtr($str, array_flip(self::$uri_chars));

			// In a URI, plusses get replaced by spaces. Put the plusses back
			$str = str_replace(' ', '+', $str);

			// Decode back
			$str = base64_decode($str);
		}

		// Decoding method
		$array = (substr($str, 0, 2) == 'a:') ? @unserialize($str) : @json_decode($str, TRUE);

		// Force array output
		if ( ! is_array($array)) $array = array();

		return $array;
	}

	// --------------------------------------------------------------------

} // End class Low_search_base