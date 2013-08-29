<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include base class
if ( ! class_exists('Low_search_base'))
{
	require_once(PATH_THIRD.'low_search/base.low_search.php');
}

/**
 * Low Search Module Control Panel class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_mcp extends Low_search_base {

	// --------------------------------------------------------------------
	// CONSTANTS
	// --------------------------------------------------------------------

	const MAX_FIELD_WEIGHT = 3;
	const VIEW_LOG_LIMIT   = 50;
	const PREVIEW_PAD      = 50;
	const PREVIEW_LIMIT    = 100;

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Holder for error messages
	 *
	 * @access     private
	 * @var        string
	 */
	private $error_msg = '';

	/**
	 * Allowed field types for replacing
	 *
	 * @access     private
	 * @var        array
	 */
	private $allowed_types = array(
		'text',
		'textarea',
		'rte',
		'wygwam',
		'matrix',
		'nsm_tiny_mce',
		'wyvern',
		'expresso',
		'editor',
		'reedactor',
		'redactee',
		'illuminated'
	);

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * PHP4 Constructor
	 *
	 * @see         __construct()
	 */
	function Low_search_mcp()
	{
		$this->__construct();
	}

	// --------------------------------------------------------------------

	/**
	 * PHP5 Constructor
	 *
	 * @return      void
	 */
	function __construct()
	{
		// -------------------------------------
		//  Call parent constructor
		// -------------------------------------

		parent::__construct();

		// -------------------------------------
		//  Get settings from extension, cache or DB
		// -------------------------------------

		if ($this->get_settings())
		{
			$this->data['settings'] = $this->settings;
		}
		else
		{
			$this->error_msg = lang('settings_not_found');
			return;
		}

		// -------------------------------------
		//  Define base url for module
		// -------------------------------------

		if (REQ == 'CP') $this->set_base_url();
	}

	// --------------------------------------------------------------------

	/**
	 * Module home page
	 *
	 * @access      public
	 * @return      string
	 */
	public function index()
	{
		// --------------------------------------
		// Get action ID for open search URL
		// --------------------------------------

		$this->data['search_url']
			= ee()->functions->fetch_site_index(0, 0)
			. QUERY_MARKER.'ACT='
			. ee()->cp->fetch_action_id($this->class_name, 'catch_search')
			. AMP.'keywords={searchTerms}';

		// --------------------------------------
		// Get action ID for building an index
		// --------------------------------------

		$this->data['build_url']
			= ee()->functions->fetch_site_index(0, 0)
			. QUERY_MARKER.'ACT='
			. ee()->cp->fetch_action_id($this->class_name, 'build_index')
			. AMP.'key='
			. $this->settings['license_key'];

		// --------------------------------------
		// Set title and breadcrumb and view page
		// --------------------------------------

		$this->_set_cp_var('cp_page_title', lang('low_search_module_name'));

		return $this->view('mcp_index');
	}

	/**
	 * Manage collections screen
	 *
	 * @access      public
	 * @return      string
	 */
	public function manage()
	{
		// --------------------------------------
		// Get all collections
		// --------------------------------------

		ee()->db->where('site_id', $this->site_id);
		ee()->db->order_by('collection_label', 'asc');
		$this->data['collections'] = ee()->low_search_collection_model->get_all();

		// --------------------------------------
		// Get entry count for each collection
		// Plus oldest index for each collection
		// --------------------------------------

		if ($this->data['collections'])
		{
			// Get channel ids
			$channels = array_unique(low_flatten_results($this->data['collections'], 'channel_id'));

			// Query DB to get totals
			$query = ee()->db->select('channel_id, COUNT(*) AS num_entries')
			       ->from('channel_titles')
			       ->where_in('channel_id', $channels)
			       ->group_by('channel_id')
			       ->get();

			// Set totals in data array
			foreach ($query->result() AS $row)
			{
				$this->data['totals'][$row->channel_id] = $row->num_entries;
			}

			// Get oldest index dates
			$index_dates = ee()->low_search_index_model->get_oldest_index();

			// Get index url (action)
			// $index_url
			// 	= ee()->functions->fetch_site_index(0, 0)
			// 	. QUERY_MARKER.'ACT='
			// 	. ee()->cp->fetch_action_id($this->class_name.'_mcp', 'build_index')
			// 	. AMP.'collection_id=%s'
			// 	. AMP.'start=0';

			// Get index url (MCP)
			$index_url
				= $this->base_url
				. AMP.'method=build_index'
				. AMP.'collection_id=%s'
				. AMP.'start=0';

			foreach ($this->data['collections'] AS &$row)
			{
				if ( ! isset($index_dates[$row['collection_id']]))
				{
					// Index doesn't exist for this collection
					$row['index_status'] = 'empty';
				}
				else
				{
					// If oldest index in collection is older than the collection edit date,
					// then we need to suggest to rebuild the index.
					$row['index_status'] = ($index_dates[$row['collection_id']] < $row['edit_date']) ? 'old' : 'ok';
				}

				// Add update index url to collection
				$row['index_url'] = sprintf($index_url, $row['collection_id']);

				// Force rebuild when old
				if ($row['index_status'] == 'old')
				{
					$row['index_url'] .= '&amp;rebuild=yes';
				}
			}
		}

		// --------------------------------------
		// Add themes url for images
		// --------------------------------------

		$this->data['themes_url'] = ee()->config->item('theme_folder_url');

		// --------------------------------------
		// Set title and breadcrumb and view page
		// --------------------------------------

		$this->_set_cp_var('cp_page_title', lang('manage_collections'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));

		return $this->view('mcp_manage_collections');
	}

	// --------------------------------------------------------------------

	/**
	 * Create new collection or edit existing one
	 *
	 * @access      public
	 * @return      string
	 */
	public function edit_collection()
	{
		// --------------------------------------
		// Get collection by id or empty row
		// --------------------------------------

		$collection_id = ee()->input->get('collection_id');
		$collection    = ($collection_id === FALSE)
		               ? ee()->low_search_collection_model->empty_row()
		               : ee()->low_search_collection_model->get_one($collection_id);

		// --------------------------------------
		// Get settings for this collection
		// --------------------------------------

		if (strlen($collection['settings']))
		{
			$collection['settings'] = $this->decode($collection['settings'], FALSE);
		}

		// // --------------------------------------
		// // Set default excerpt data
		// // --------------------------------------

		if ( ! strlen($collection['excerpt']))
		{
			$collection['excerpt'] = '0';
		}

		// // --------------------------------------
		// // Set default modifier data
		// // --------------------------------------

		if ( ! strlen($collection['modifier']))
		{
			$collection['modifier'] = '1';
		}

		// --------------------------------------
		// Merge collection data with view data
		// --------------------------------------

		$this->data = array_merge($this->data, $collection);

		// --------------------------------------
		// Get searchable channel fields
		// --------------------------------------

		$query = ee()->db->select('field_id, group_id, field_label')
		       ->from('channel_fields')
		       ->where('site_id', $this->site_id)
		       ->where('field_search', 'y')
		       ->order_by('field_order')
		       ->get();
		$fields = array();

		foreach ($query->result() AS $row)
		{
			$fields[$row->group_id][$row->field_id] = $row->field_label;
		}

		// --------------------------------------
		// Get channels
		// --------------------------------------

		$query = ee()->db->select('channel_id, field_group, channel_name, channel_title')
		       ->from('channels')
		       ->where('site_id', $this->site_id)
		       ->order_by('channel_title')
		       ->get();
		$channels = low_associate_results($query->result_array(), 'channel_id');

		// Add simple channel data to global JS object for later reference
		ee()->javascript->set_global('low_search_channels', $channels);

		// Add fields to channels
		foreach ($channels AS &$row)
		{
			$row['fields'] = array(0 => lang('title'));

			if (array_key_exists($row['field_group'], $fields))
			{
				$row['fields'] += $fields[$row['field_group']];
			}
		}

		// --------------------------------------
		// Add to view data
		// --------------------------------------

		$this->data['channels'] = $channels;
		$this->data['max_field_weight'] = self::MAX_FIELD_WEIGHT;

		// --------------------------------------
		// Set title and breadcrumb
		// --------------------------------------

		$this->_set_cp_var('cp_page_title', lang($collection_id === FALSE ? 'create_new_collection' : 'edit_collection'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));
		ee()->cp->set_breadcrumb($this->base_url.AMP.'method=manage', lang('manage_collections'));

		return $this->view('mcp_edit_collection');
	}

	// --------------------------------------------------------------------

	/**
	 * Save changes to given collection
	 *
	 * @access      public
	 * @return      string
	 */
	public function save_collection()
	{
		// --------------------------------------
		// Get collection id
		// --------------------------------------

		if (($collection_id = ee()->input->post('collection_id')) !== FALSE)
		{
			// --------------------------------------
			// Set site id to current site
			// --------------------------------------

			$_POST['site_id'] = $this->site_id;

			// --------------------------------------
			// Title shouldn't be empty
			// --------------------------------------

			if ( ! strlen($_POST['collection_label']))
			{
				$_POST['collection_label'] = lang('new_collection');
			}

			// --------------------------------------
			// Check channel
			// --------------------------------------

			if ( ! ($channel_id = ee()->input->post('channel_id')))
			{
				show_error(lang('channel_cannot_be_empty'));
			}

			// --------------------------------------
			// Check collection name
			// --------------------------------------

			// It should be filled in
			if ( ! ($collection_name = trim(ee()->input->post('collection_name'))))
			{
				show_error(lang('collection_name_cannot_be_empty'));
			}

			// It should be formatted correctly
			if ( ! preg_match('#^[\-a-zA-Z0-9_]+$#', $collection_name))
			{
				show_error(lang('collection_name_has_wrong_chars'));
			}

			// And it should be unique
			if (ee()->db->where(array(
					'site_id'          => $this->site_id,
					'collection_name'  => $collection_name,
					'collection_id !=' => $collection_id
				))->from(ee()->low_search_collection_model->table())->count_all_results())
			{
				show_error(lang('collection_name_exists'));
			}

			// --------------------------------------
			// Check modifier
			// --------------------------------------

			$mod = (float) ee()->input->post('modifier');

			// Check modifier validity
			if ($mod <= 0) $mod = 1;
			if ($mod > 10) $mod = 10;

			$_POST['modifier'] = $mod;

			// --------------------------------------
			// Check Excerpt
			// --------------------------------------

			$excerpts = ee()->input->post('excerpt');

			$_POST['excerpt'] = isset($excerpts[$channel_id]) ? $excerpts[$channel_id] : 0;

			// --------------------------------------
			// Check Settings
			// --------------------------------------

			$settings = (array) ee()->input->post('settings');

			// Check field weights
			if (isset($settings[$channel_id]))
			{
				$settings = $settings[$channel_id];
			}
			else
			{
				$settings = array();
			}

			// Clean it
			$settings = array_filter($settings);

			// It's nicer to sort the settings
			ksort($settings);

			// Set settings in POST so model can handle it
			$_POST['settings'] = $this->encode($settings, FALSE);

			// --------------------------------------
			// Add edit date to POST vars if new or settings changed
			// --------------------------------------

			// Initiate edit date
			$edit_date = ee()->localize->now;

			// Check old settings
			if (is_numeric($collection_id))
			{
				$old_collection = ee()->low_search_collection_model->get_one($collection_id);

				// continue if channel is still the same
				if ($old_collection['channel_id'] == $channel_id)
				{
					$old_settings = $this->decode($old_collection['settings'], FALSE);
					$same = TRUE;

					foreach ($old_settings AS $key => $val)
					{
						// Only check fields
						if ( ! is_numeric($key)) continue;

						// Check new settings against old one
						if ( ! isset($settings[$key]) || $settings[$key] != $val)
						{
							$same = FALSE;
							break;
						}
					}

					// If settings are the same, don't update edit date
					if ($same)
					{
						$edit_date = FALSE;
					}
				}
			}

			if ($edit_date)
			{
				$_POST['edit_date'] = $edit_date;
			}

			// --------------------------------------
			// Insert or update record
			// --------------------------------------

			if (is_numeric($collection_id))
			{
				ee()->low_search_collection_model->update($collection_id);
			}
			else
			{
				$collection_id = ee()->low_search_collection_model->insert();
			}

			// --------------------------------------
			// Set feedback message
			// --------------------------------------

			ee()->session->set_flashdata('msg', 'changes_saved');
		}

		// --------------------------------------
		// Redirect back to overview
		// --------------------------------------

		ee()->functions->redirect($this->base_url.AMP.'method=manage');
	}

	// --------------------------------------------------------------------

	/**
	 * Confirm deletion of a collection
	 *
	 * @access      public
	 * @return      string
	 */
	public function delete_collection_confirm()
	{
		// --------------------------------------
		// Redirect back to module home if no collection is given
		// --------------------------------------

		if ( ! ($collection_id = ee()->input->get('collection_id')))
		{
			ee()->functions->redirect($this->base_url);
		}

		// --------------------------------------
		// Get collection from DB
		// --------------------------------------

		if ( ! ($collection = ee()->low_search_collection_model->get_one($collection_id)))
		{
			ee()->functions->redirect($this->base_url);
		}

		// --------------------------------------
		// Compose data
		// --------------------------------------

		$this->data = array_merge($this->data, $collection);

		// --------------------------------------
		// Title and Crumbs
		// --------------------------------------

		$this->_set_cp_var('cp_page_title', lang('delete_collection_confirm'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));

		// --------------------------------------
		// Load up view
		// --------------------------------------

		return $this->view('mcp_delete_confirm');
	}

	/**
	 * Delete a collection
	 *
	 * @access      public
	 * @return      void
	 */
	public function delete_collection()
	{
		// --------------------------------------
		// Check collection id
		// --------------------------------------

		if ($collection_id = ee()->input->post('collection_id'))
		{
			// --------------------------------------
			// Delete in 2 tables
			// --------------------------------------

			ee()->low_search_collection_model->delete($collection_id);
			ee()->low_search_index_model->delete($collection_id, 'collection_id');

			// --------------------------------------
			// Set feedback message
			// --------------------------------------

			ee()->session->set_flashdata('msg', 'collection_deleted');
		}

		// --------------------------------------
		// Go home
		// --------------------------------------

		ee()->functions->redirect($this->base_url);
	}

	// --------------------------------------------------------------------

	/**
	 * First half of Find & Replace
	 *
	 * @access      public
	 * @return      string
	 */
	public function find()
	{
		// --------------------------------------
		// Get this member's id and group id
		// --------------------------------------

		$member_id    = ee()->session->userdata('member_id');
		$member_group = $this->member_group;

		// --------------------------------------
		// Get allowed channels
		// --------------------------------------

		$channel_ids = ee()->functions->fetch_assigned_channels();

		// --------------------------------------
		// Get hidden fields according to publish layouts
		// --------------------------------------

		$hidden = array();

		$query = ee()->db->select('channel_id, field_layout')
		       ->from('layout_publish')
		       ->where('member_group', $member_group)
		       ->where_in('channel_id', $channel_ids)
		       ->get();

		// Loop thru each publish layout
		foreach ($query->result() AS $row)
		{
			// Unserialize details and loop thru tabs
			foreach (unserialize($row->field_layout) AS $tab => $layout)
			{
				// For each tab, loop thru fields and check if they're visible
				// If not visible, add it to hidden_fields array
				foreach ($layout AS $field => $options)
				{
					if (isset($options['visible']) && $options['visible'] == FALSE && is_numeric($field))
					{
						$hidden[] = $field;
					}
				}
			}
		}

		// --------------------------------------
		// Get list of channels and fields for selection
		// --------------------------------------

		$channels = $cat_groups = array();

		ee()->db->select('c.channel_id, c.cat_group, c.channel_title, f.field_id, f.field_label')
		     ->from('channels c')
		     ->join('channel_fields f', 'c.field_group = f.group_id')
		     ->where('c.site_id', $this->site_id)
		     ->where_in('c.channel_id', $channel_ids)
		     ->where_in('f.field_type', $this->allowed_types)
		     ->order_by('c.channel_title', 'asc')
		     ->order_by('f.field_order', 'asc');

		if ($hidden)
		{
			ee()->db->where_not_in('f.field_id', $hidden);
		}

		$query = ee()->db->get();

		// Change flat resultset into nested one
		foreach ($query->result() AS $row)
		{
			if ( ! isset($channels[$row->channel_id]))
			{
				$channels[$row->channel_id] = array(
					'channel_title'   => $row->channel_title,
					'fields'          => array('title' => lang('title'))
				);

				if ($row->cat_group) $cat_groups = array_merge($cat_groups, explode('|', $row->cat_group));
			}

			$channels[$row->channel_id]['fields'][$row->field_id] = $row->field_label;
		}

		$this->data['channels'] = $channels;

		// --------------------------------------
		// Categories filter
		// --------------------------------------

		$categories = array();
		$allowed    = ($this->member_group == 1) ? FALSE : $this->_get_permitted_categories($member_id);

		ee()->load->library('api');
		ee()->api->instantiate('channel_categories');

		// Generate category tree array
		if ($cat_groups && $tree = ee()->api_channel_categories->category_tree($cat_groups))
		{
			// Get category group names
			$query = ee()->db->select('group_id, group_name')
			       ->from('category_groups')
			       ->where_in('group_id', $cat_groups)
			       ->order_by('group_name')
			       ->get();
			$groups = low_flatten_results($query->result_array(), 'group_name', 'group_id');

			// Loop thru tree
			foreach ($tree AS $row)
			{
				// Skip categories that aren't allowed
				if (is_array($allowed) && ! in_array($row[0], $allowed))
				{
					continue;
				}

				// Add category group to array
				if ( ! isset($categories[$row[2]]))
				{
					$categories[$row[2]] = array(
						'group_name' => $groups[$row[2]],
						'cats'       => array()
					);
				}

				// Indent level for child categories
				$indent = ($row[5] > 1) ? str_repeat(NBS, $row[5] - 1) : '';

				// Add category itself to array
				$categories[$row[2]]['cats'][$row[0]] = array(
					'name' => $row[1],
					'indent' => $indent
				);
			}
		}

		// Add categories array to data
		$this->data['categories'] = $categories;

		// --------------------------------------
		// Check if we need to preview
		// --------------------------------------

		if (ee()->input->get('preview') == 'yes')
		{
			// Move this bulk to different method for clarity
			$this->_show_preview();
		}

		// --------------------------------------
		// Check if we need to show feedback message
		// --------------------------------------

		if ($feedback = ee()->session->flashdata('replace_feedback'))
		{
			$this->data['feedback'] = $this->decode($feedback);
		}

		// --------------------------------------
		// Set title and breadcrumb
		// --------------------------------------

		$this->_set_cp_var('cp_page_title', lang('find_replace'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));

		return $this->view('mcp_find_replace');
	}

	/**
	 * Show preview table based on given keywords and fields
	 *
	 * @access      public
	 * @return      string
	 */
	private function _show_preview()
	{
		// --------------------------------------
		// Check prerequisites
		// --------------------------------------

		$member_id = ee()->session->userdata('member_id');
		$keywords  = ee()->input->post('keywords');
		$fields    = ee()->input->post('fields');
		$cats      = ee()->input->post('cats');

		if ( ! ($keywords && $fields))
		{
			if (is_ajax())
			{
				die('No keywords or fields given.');
			}
			else
			{
				return;
			}
		}

		// Save this POST data as encoded data, so we know that what has been
		// previewed, is also used for the actual replacement
		$this->data['encoded_preview'] = $this->encode($_POST);
		$this->data['keywords'] = htmlspecialchars($keywords);

		// --------------------------------------
		// Get permitted categories, if it's installed
		// --------------------------------------

		$allowed_cats  = ($this->member_group == 1) ? FALSE : $this->_get_permitted_categories($member_id, TRUE);
		$selected_cats = empty($cats) ? array() : $cats;

		// --------------------------------------
		// Compose query to get the matching entries
		// --------------------------------------

		// First, define some vars to help build the SQL
		$sql_keywords     = ee()->db->escape_str($keywords);
		$sql_channel_tmpl = "(t.channel_id = '%s' AND (%s)%s)";
		$sql_field_tmpl   = "(%s LIKE '%%%s%%' COLLATE utf8_bin)";
		$sql_cat_tmpl     = " AND cp.cat_id IN (%s)";
		$sql_select       = array();
		$sql_where        = array();

		// Loop thru each channel and its fields
		foreach ($fields AS $channel_id => $field_ids)
		{
			$sql_fields = array();
			$sql_cat    = '';

			// Per field, we need to add the LIKE clause to search it
			foreach ($field_ids AS $field_id)
			{
				// Field id could be numeric (for field_id_1) or not (for title)
				$field_name   = (is_numeric($field_id) ? 'd.field_id_' : 't.') . $field_id;

				// Add field name to select clause
				$sql_select[] = $field_name;

				// Add LIKE clause to temporary
				$sql_fields[] = sprintf($sql_field_tmpl, $field_name, $sql_keywords);
			}

			// If we need to limit by category, create that clause here
			if (isset($allowed_cats[$channel_id]))
			{
				$sql_cat = sprintf($sql_cat_tmpl, implode(',', $allowed_cats[$channel_id]));
			}

			// Add the full WHERE clause per channel to the where-array
			$sql_where[] = sprintf($sql_channel_tmpl, $channel_id, implode(' OR ', $sql_fields), $sql_cat);
		}

		// Add mandatory fields to SELECT array
		$sql_select = array_unique(array_merge(array('t.entry_id', 't.title', 't.channel_id'), $sql_select));

		// Start building query
		ee()->db->select($sql_select)
		     ->from('channel_titles t')
		     ->join('channel_data d', 't.entry_id = d.entry_id')
		     ->where_in('t.channel_id', array_keys($fields))
		     ->where('('.implode(' OR ', $sql_where).')')
		     ->group_by('t.entry_id')
		     ->order_by('t.entry_id', 'desc')
		     ->limit(self::PREVIEW_LIMIT);

		// Limit to user's own entries
		if ($this->member_group != 1 && ee()->session->userdata('can_edit_other_entries') == 'n')
		{
			ee()->db->where('t.author_id', $member_id);
		}

		// Join category_posts if necessary
		if ($allowed_cats || $selected_cats)
		{
			ee()->db->join('category_posts cp', 'd.entry_id = cp.entry_id', 'left');

			// Limit by selected cats, if given
			if ($selected_cats)
			{
				ee()->db->where_in('cp.cat_id', $selected_cats);
			}
		}

		// And get the results
		$query = ee()->db->get();

		// --------------------------------------
		// Create nested array from results, with match preview
		// --------------------------------------

		$preview = array();
		$keyword_length = strlen($keywords);

		foreach ($query->result_array() AS $row)
		{
			$row['matches'] = array();

			foreach ($fields[$row['channel_id']] AS $field_id)
			{
				// Field name shortcut
				$field = (is_numeric($field_id) ? $row['field_id_'.$field_id] : $row[$field_id]);

				if ($matches = low_strpos_all($field, $keywords))
				{
					$subs = low_substr_pad($field, $matches, $keyword_length, self::PREVIEW_PAD);
					$subs = array_map('htmlspecialchars', $subs);
					foreach ($subs AS &$sub)
					{
						$sub = low_hilite($sub, htmlspecialchars($keywords));
					}
					$row['matches'][$field_id] = $subs;
				}
			}

			if ($row['matches']) $preview[] = $row;
		}

		$this->data['preview'] = $preview;

		// --------------------------------------
		// If Ajax request, load parial view and exit
		// --------------------------------------

		if (is_ajax())
		{
			die(ee()->load->view('ajax_preview', $this->data, TRUE));
		}
	}

	/**
	 * Perform find & replace in DB
	 *
	 * @access      public
	 * @return      void
	 */
	public function replace()
	{
		if ( ! ($data = ee()->input->post('encoded_preview')))
		{
			ee()->functions->redirect($this->base_url.AMP.'method=find');
			exit;
		}

		$data        = $this->decode($data);
		$keywords    = $data['keywords'];
		$replacement = ee()->input->post('replacement');
		$entries     = ee()->input->post('entries');

		if ( ! ($data && $entries))
		{
			ee()->functions->redirect($this->base_url.AMP.'method=find');
			exit;
		}

		// --------------------------------------
		// Compose all needed queries
		// --------------------------------------

		$sql = array();
		$sql_replace_tmpl = "%s = REPLACE(%s, '%s', '%s')";
		$sql_update_tmpl  = "UPDATE %s SET %s WHERE entry_id IN (%s);";
		$sql_keywords     = ee()->db->escape_str($keywords);
		$sql_replacement  = ee()->db->escape_str($replacement);
		$all_entries      = array();

		foreach ($entries AS $channel_id => $entry_ids)
		{
			// initiate arrays per channel
			$channel_titles = $channel_data = $matrix_data = array();

			// Get field ids
			$field_ids = $data['fields'][$channel_id];

			// SQL safe entry ids
			$sql_entries = implode(',', $entry_ids);

			// Add query to change edit date
			$channel_titles[] = "edit_date = '{ee()->localize->now}'";

			// Loop thru each field id and add update statement to batch
			foreach ($field_ids AS $field_id)
			{
				$field   = is_numeric($field_id) ? "field_id_{$field_id}" : $field_id;
				$replace = sprintf($sql_replace_tmpl, $field, $field, $sql_keywords, $sql_replacement);

				if (is_numeric($field_id))
				{
					$channel_data[] = $replace;
				}
				else
				{
					$channel_titles[] = $replace;
				}

				// Check Matrix fields, add queries to update cols
				if ($this->_is_matrix_field($field_id))
				{
					foreach ($this->_get_matrix_cols($field_id) AS $col_id)
					{
						$col = "col_id_{$col_id}";
						$matrix_data[] = sprintf($sql_replace_tmpl, $col, $col, $sql_keywords, $sql_replacement);
					}
				}
			}

			if ($channel_titles)
			{
				$sql[] = sprintf($sql_update_tmpl, 'exp_channel_titles', implode(', ', $channel_titles), $sql_entries);
			}

			if ($channel_data)
			{
				$sql[] = sprintf($sql_update_tmpl, 'exp_channel_data', implode(', ', $channel_data), $sql_entries);
			}

			if ($matrix_data)
			{
				$sql[] = sprintf($sql_update_tmpl, 'exp_matrix_data', implode(', ', $matrix_data), $sql_entries);
			}

			// Add entry_ids to all_entries
			$all_entries = array_merge($all_entries, $entry_ids);
		}

		// --------------------------------------
		// Execute all queries!
		// --------------------------------------

		foreach ($sql AS $query)
		{
			ee()->db->query($query);
		}

		// --------------------------------------
		// Update index for affected entries
		// --------------------------------------

		$this->_build_index(FALSE, $all_entries);

		// --------------------------------------
		// Clear cache
		// --------------------------------------

		ee()->functions->clear_caching('all', '', TRUE);

		// --------------------------------------
		// Add to replace log
		// --------------------------------------

		ee()->low_search_replace_log_model->insert(array(
			'site_id'      => $this->site_id,
			'member_id'    => ee()->session->userdata['member_id'],
			'replace_date' => ee()->localize->now,
			'keywords'     => $data['keywords'],
			'replacement'  => $replacement,
			'fields'       => $this->encode($data['fields'], FALSE),
			'entries'      => '|'.implode('|', $all_entries).'|'
		));

		// -------------------------------------
		// 'low_search_post_replace' hook.
		//  - Do something after the replace action
		// -------------------------------------

		if (ee()->extensions->active_hook('low_search_post_replace') === TRUE)
		{
			ee()->extensions->call('low_search_post_replace', $all_entries);
		}

		// --------------------------------------
		// Set feedback msg
		// --------------------------------------

		$this->data['feedback'] = array(
			'keywords'      => $keywords,
			'replacement'   => $replacement,
			'total_entries' => count($all_entries)
		);

		// --------------------------------------
		// Go back to F&R home
		// --------------------------------------

		if (is_ajax())
		{
			die(ee()->load->view('ajax_replace_feedback', $this->data, TRUE));
		}
		else
		{
			ee()->session->set_flashdata('replace_feedback', $this->encode($this->data['feedback']));
			ee()->functions->redirect($this->base_url.AMP.'method=find');
		}
	}

	// --------------------------------------------------------------------

	/**
	 * View replace log
	 *
	 * @access      public
	 * @return      string
	 */
	public function replace_log()
	{
		$member_id = ee()->session->userdata('member_id');

		// --------------------------------------
		// Get total rows of log
		// --------------------------------------

		if ($this->member_group != 1)
		{
			ee()->db->where('member_id', $member_id);
		}

		$total = ee()->db->where('site_id', $this->site_id)->count_all_results(ee()->low_search_replace_log_model->table());

		// --------------------------------------
		// Get start row
		// --------------------------------------

		if (($start = ee()->input->get('start')) === FALSE)
		{
			$start = 0;
		}

		// --------------------------------------
		// Load pagination class, if necessary
		// --------------------------------------

		if ($total > self::VIEW_LOG_LIMIT)
		{
			ee()->load->library('pagination');

			// Pagination link template
			$pagi_link = '<img src="'.ee()->cp->cp_theme_url.'images/pagination_%s_button.gif" width="13" height="13" alt="%s" />';

			// Set pagination parameters
			ee()->pagination->initialize(array(
				'base_url'             => $this->base_url.AMP.'method=replace_log',
				'total_rows'           => $total,
				'per_page'             => self::VIEW_LOG_LIMIT,
				'page_query_string'    => TRUE,
				'query_string_segment' => 'start',
				'full_tag_open'        => '<span>',
				'full_tag_close'       => '</span>',
				'prev_link'            => sprintf($pagi_link, 'prev', '&larr;'),
				'next_link'            => sprintf($pagi_link, 'next', '&rarr;'),
				'first_link'           => sprintf($pagi_link, 'first', '&ldarr;'),
				'last_link'            => sprintf($pagi_link, 'last', '&rdarr;')
			));

			// Create the links
			$this->data['pagination'] = ee()->pagination->create_links();
		}
		else
		{
			$this->data['pagination'] = FALSE;
		}

		// --------------------------------------
		// Get search log
		// --------------------------------------

		ee()->db->select('members.screen_name, low_search_replace_log.*')
		             ->where('site_id', $this->site_id)
		             ->order_by('replace_date', 'desc')
		             ->limit(self::VIEW_LOG_LIMIT, $start)
		             ->join('members', 'members.member_id = low_search_replace_log.member_id', 'left');

		// Filter by member_id if not a superadmin
		if ($this->member_group != 1)
		{
			ee()->db->where('member_id', $member_id);
		}

		if ($log = ee()->low_search_replace_log_model->get_all())
		{
			// --------------------------------------
			// Set pagination details
			// --------------------------------------

			$this->data['viewing_rows'] = sprintf(lang('viewing_rows'),
				$start + 1,
				(($to = $start + self::VIEW_LOG_LIMIT) > $total) ? $total : $to,
				$total
			);

			// --------------------------------------
			// Modify rows
			// --------------------------------------

			foreach ($log AS &$row)
			{
				// Display a nice date
				$row['replace_date'] = ee()->localize->set_human_time($row['replace_date']);

				// Account for guests
				$row['member_id'] = $row['member_id'] ? $row['screen_name'] : '--';

				// Affected entries
				$row['entries'] = array_filter(explode('|', $row['entries']));
			}
		}

		// --------------------------------------
		// Add log to data array
		// --------------------------------------

		$this->data['log'] = $log;
		$this->data['is_admin'] = ($this->member_group == 1);

		// --------------------------------------
		// Set title and breadcrumb and view page
		// --------------------------------------

		$this->_set_cp_var('cp_page_title',   lang('replace_log'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));

		return $this->view('mcp_replace_log');
	}

	/**
	 * Clear the replace log for current site
	 *
	 * @access      public
	 * @return      void
	 */
	public function clear_replace_log()
	{
		// Delete
		ee()->db->where('site_id', $this->site_id);
		ee()->db->delete(ee()->low_search_replace_log_model->table());

		// And go back
		ee()->functions->redirect($this->base_url.AMP.'method=replace_log');
	}

	/**
	 * View replace details, called by ajax in modal
	 *
	 * @access      public
	 * @return      string
	 */
	public function replace_details()
	{
		// --------------------------------------
		// Get the id and row of details
		// --------------------------------------

		$log_id = ee()->input->get('log_id');
		$log    = ee()->low_search_replace_log_model->get_one($log_id);

		// --------------------------------------
		// Get titles for entries
		// --------------------------------------

		$query = ee()->db->select('entry_id, channel_id, title')
		       ->from('channel_titles')
		       ->where_in('entry_id', array_filter(explode('|', $log['entries'])))
		       ->order_by('entry_id', 'asc')
		       ->get();

		$log['entries'] = $query->result_array();

		// --------------------------------------
		// Get Channel and Field details
		// --------------------------------------

		$query = ee()->db->select('channel_id, channel_title')
		       ->from('channels')
		       ->where('site_id', $this->site_id)
		       ->get();

		$channels = low_flatten_results($query->result_array(), 'channel_title', 'channel_id');

		$query = ee()->db->select('field_id, field_label')
		       ->from('channel_fields')
		       ->where('site_id', $this->site_id)
		       ->where_in('field_type', $this->allowed_types)
		       ->get();

		$fields = low_flatten_results($query->result_array(), 'field_label', 'field_id');

		// --------------------------------------
		// Create nested array for selected channels & fields
		// --------------------------------------

		$searched_fields = array();
		/*
		foreach ($this->decode($log['fields'], FALSE) AS $channel_id => $field_ids)
		{
			$searched_fields[$channels[$channel_id]] = array();

			foreach ($field_ids AS $field_id)
			{
				$searched_fields[$channels[$channel_id]][] = is_numeric($field_id) ? $fields[$field_id] : lang($field_id);
			}
		}
		*/
		$log['fields'] = $searched_fields;

		// --------------------------------------
		// Add to data array for display purposes
		// --------------------------------------

		$this->data = array_merge($this->data, $log);
		$this->data['channels'] = $channels;

		if (is_ajax())
		{
			die(ee()->load->view('ajax_replace_details', $this->data, TRUE));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * View search log
	 *
	 * @access      public
	 * @return      string
	 */
	public function search_log()
	{
		// --------------------------------------
		// Get total rows of log
		// --------------------------------------

		$total = ee()->db->where('site_id', $this->site_id)
		       ->count_all_results(ee()->low_search_log_model->table());

		// Prune now?
		if (($search_log_size = $this->get_settings('search_log_size')) !== '0' && $total > $search_log_size)
		{
			ee()->low_search_log_model->prune($this->site_id, $search_log_size);
			$total = $search_log_size;
		}

		// --------------------------------------
		// Get start row
		// --------------------------------------

		if (($start = ee()->input->get('start')) === FALSE)
		{
			$start = 0;
		}

		// --------------------------------------
		// Load pagination class, if necessary
		// --------------------------------------

		if ($total > self::VIEW_LOG_LIMIT)
		{
			ee()->load->library('pagination');

			// Pagination link template
			$pagi_link = '<img src="'.ee()->cp->cp_theme_url.'images/pagination_%s_button.gif" width="13" height="13" alt="%s" />';

			// Set pagination parameters
			ee()->pagination->initialize(array(
				'base_url'             => $this->base_url.AMP.'method=search_log',
				'total_rows'           => $total,
				'per_page'             => self::VIEW_LOG_LIMIT,
				'page_query_string'    => TRUE,
				'query_string_segment' => 'start',
				'full_tag_open'        => '<span>',
				'full_tag_close'       => '</span>',
				'prev_link'            => sprintf($pagi_link, 'prev', '&larr;'),
				'next_link'            => sprintf($pagi_link, 'next', '&rarr;'),
				'first_link'           => sprintf($pagi_link, 'first', '&ldarr;'),
				'last_link'            => sprintf($pagi_link, 'last', '&rdarr;')
			));

			// Create the links
			$this->data['pagination'] = ee()->pagination->create_links();
		}
		else
		{
			$this->data['pagination'] = FALSE;
		}

		// --------------------------------------
		// Get search log
		// --------------------------------------

		ee()->db->select('members.screen_name, low_search_log.*')
		             ->where('site_id', $this->site_id)
		             ->order_by('search_date', 'desc')
		             ->limit(self::VIEW_LOG_LIMIT, $start)
		             ->join('members', 'members.member_id = low_search_log.member_id', 'left');

		if ($log = ee()->low_search_log_model->get_all())
		{
			// --------------------------------------
			// Set pagination details
			// --------------------------------------

			$this->data['viewing_rows'] = sprintf(lang('viewing_rows'),
				$start + 1,
				(($to = $start + self::VIEW_LOG_LIMIT) > $total) ? $total : $to,
				$total
			);

			// --------------------------------------
			// Modify rows
			// --------------------------------------

			foreach ($log AS &$row)
			{
				// Display a nice date
				$row['search_date'] = ee()->localize->set_human_time($row['search_date']);

				// Account for guests
				$row['member_id'] = $row['member_id'] ? $row['screen_name'] : '--';

				// Parameters
				$row['parameters'] = $this->decode($row['parameters'], FALSE);
			}
		}

		// --------------------------------------
		// Add log to data array
		// --------------------------------------

		$this->data['log'] = $log;
		$this->data['is_admin'] = ($this->member_group == 1);

		// --------------------------------------
		// Set title and breadcrumb and view page
		// --------------------------------------

		$this->_set_cp_var('cp_page_title',   lang('view_search_log'));
		ee()->cp->set_breadcrumb($this->base_url, lang('low_search_module_name'));

		return $this->view('mcp_search_log');
	}

	/**
	 * Clear the search log for current site
	 *
	 * @access      public
	 * @return      void
	 */
	public function clear_search_log()
	{
		// Delete
		ee()->db->where('site_id', $this->site_id);
		ee()->db->delete(ee()->low_search_log_model->table());

		// And go back
		ee()->functions->redirect($this->base_url.AMP.'method=search_log');
	}


	/**
	 * Download/export search log
	 *
	 * @access      public
	 * @return      void
	 */
	public function export_search_log()
	{
		// --------------------------------------
		// Load util and download helper
		// --------------------------------------

		ee()->load->dbutil();
		ee()->load->helper('download');

		// --------------------------------------
		// Table/prefix
		// --------------------------------------

		$t = ee()->low_search_log_model->table();

		// --------------------------------------
		// Get all log records
		// --------------------------------------

		$query = ee()->db->select(array(
				"{$t}.keywords", 'members.screen_name AS member',
				"{$t}.ip_address", "FROM_UNIXTIME({$t}.search_date) AS `date`"))
		       ->from($t)
		       ->join('members', "members.member_id = {$t}.member_id", 'left')
		       ->where('site_id', $this->site_id)
		       ->order_by('search_date', 'desc')
		       ->get();

		// --------------------------------------
		// Build CSV from result
		// --------------------------------------

		$log = ee()->dbutil->csv_from_result($query);

		// --------------------------------------
		// Clean trailing commas from result. Tsk.
		// --------------------------------------

		$log = preg_replace('/,$/m', '', $log);

		// --------------------------------------
		// File name
		// --------------------------------------

		$name = 'search_log_'.date('YmdHi').'.csv';

		// --------------------------------------
		// And download it
		// --------------------------------------

		force_download($name, $log);
	}

	// --------------------------------------------------------------------

	/**
	 * Rebuild index
	 *
	 * @access      public
	 * @return      string
	 */
	public function build_index()
	{
		// --------------------------------------
		// Only members are allowed to do this
		// --------------------------------------

		if ( ! ee()->session->userdata('member_id'))
		{
			show_error('Operation not permitted');
		}

		// --------------------------------------
		// Get info from Query String
		// --------------------------------------

		$col_id  = (int) ee()->input->get('collection_id');
		$start   = (int) ee()->input->get('start');
		$rebuild = (string) ee()->input->get('rebuild');

		// --------------------------------------
		// Delete existing collection if rebuild == 'yes'
		// --------------------------------------

		if ($start === 0 && $rebuild == 'yes')
		{
			ee()->low_search_index_model->delete($col_id, 'collection_id');
		}

		// --------------------------------------
		// Call private build_index method
		// --------------------------------------

		$response = $this->_build_index($col_id, NULL, $start);

		// --------------------------------------
		// Optimize table when we're done
		// --------------------------------------

		if ($response['status'] == 'done')
		{
			ee()->db->query('OPTIMIZE TABLE '.ee()->low_search_index_model->table());
		}

		// --------------------------------------
		// Return JSON
		// --------------------------------------

		if (is_ajax())
		{
			die(json_encode($response));
		}
	}

	// --------------------------------------------------------------------
	// PRIVATE METHODS
	// --------------------------------------------------------------------

	/**
	 * Get array of channel_id => cat_ids for this member
	 *
	 * @access      private
	 * @param       int
	 * @return      array
	 */
	private function _get_permitted_categories($member_id, $nested = FALSE)
	{
		$categories = FALSE;

		// --------------------------------------
		// Bail out if category permissions ext is not installed
		// --------------------------------------

		$package = 'category_permissions';

		if (array_key_exists($package, ee()->addons->get_installed('extensions')))
		{
			$categories = array();

			// Load CatPerm model so we can use their stuff
			ee()->load->add_package_path(PATH_THIRD.$package);
			ee()->load->model($package.'_model', $package);

			// Get array of category ids
			if (ee()->$package->member_has_category_permissions($member_id))
			{
				$cat_ids = ee()->$package->get_member_permitted_categories($member_id);
			}
			else
			{
				$cat_ids = array();
			}

			// Clean up after us
			ee()->load->remove_package_path(PATH_THIRD.$package);

			// If we have categories, associate them with group ids
			if ($cat_ids)
			{
				// $query = ee()->db->select('t.channel_id, t.entry_id')
				//        ->from('channel_titles t')
				//        ->join('category_posts cp', 't.entry_id = cp.entry_id', 'left')
				//        ->where_in('cp.cat_id', $cat_ids)
				//        ->or_where('cp.cat_id IS NULL')
				//        ->get();
				//
				// foreach ($query->result() AS $row)
				// {
				// 	$categories[$row->channel_id][] = $row->entry_id;
				// }

				if ($nested === FALSE)
				{
					return $cat_ids;
				}

				$cats_by_group = array();

				// First, organize categories by category group
				$query = ee()->db->select('cat_id, group_id')
				       ->from('categories')
				       ->where('site_id', $this->site_id)
				       ->where_in('cat_id', $cat_ids)
				       ->get();

				foreach ($query->result() AS $row)
				{
					$cats_by_group[$row->group_id][] = $row->cat_id;
				}

				// Then get channel and their cat groups
				$query = ee()->db->select('channel_id, cat_group')
				       ->from('channels')
				       ->where('site_id', $this->site_id)
				       ->get();

				// And associate channel with cat ids
				foreach ($query->result() AS $row)
				{
					foreach (array_filter(explode('|', $row->cat_group)) AS $group_id)
					{
						if ( ! isset($categories[$row->channel_id]))
						{
							$categories[$row->channel_id] = array();
						}

						$categories[$row->channel_id] = array_merge(
							$categories[$row->channel_id],
							$cats_by_group[$group_id]
						);
					}
				}
			}
		}

		return $categories;
	}

	// --------------------------------------------------------------------

	/**
	 * Is given field ID a Matrix field?
	 *
	 * @access      private
	 * @param       int
	 * @return      bool
	 */
	private function _is_matrix_field($id)
	{
		static $matrix_fields;

		if ( ! $matrix_fields)
		{
			$query = ee()->db->select('field_id')
			       ->from('channel_fields')
			       ->where('field_type', 'matrix')
			       ->get();

			$matrix_fields = low_flatten_results($query->result_array(), 'field_id');
		}

		return in_array($id, $matrix_fields);
	}

	/**
	 * Get array of column ids for given Matrix field
	 *
	 * @access      private
	 * @param       int
	 * @return      array
	 */
	private function _get_matrix_cols($id)
	{
		static $matrix_cols = array();

		if ( ! isset($matrix_cols[$id]))
		{
			$query = ee()->db->select('col_id')
			       ->from('matrix_cols')
			       ->where('col_search', 'y')
			       ->get();

			$matrix_cols[$id] = low_flatten_results($query->result_array(), 'col_id');
		}

		return $matrix_cols[$id];
	}

	// --------------------------------------------------------------------

	/**
	 * Set cp var
	 *
	 * @access     private
	 * @param      string
	 * @param      string
	 * @return     void
	 */
	private function _set_cp_var($key, $val)
	{
		if (version_compare(APP_VER, '2.6.0', '<'))
		{
			ee()->cp->set_variable($key, $val);
		}
		else
		{
			ee()->view->$key = $val;
		}
	}

} // End Class

/* End of file mcp.low_search.php */