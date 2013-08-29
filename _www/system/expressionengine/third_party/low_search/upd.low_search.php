<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// include base class
if ( ! class_exists('Low_search_base'))
{
	require_once(PATH_THIRD.'low_search/base.low_search.php');
}

/**
 * Low Search Update class
 *
 * @package        low_search
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-search
 * @copyright      Copyright (c) 2013, Low
 */
class Low_search_upd extends Low_search_base {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Actions used
	 *
	 * @access      private
	 * @var         array
	 */
	private $actions = array(
		array('Low_search', 'catch_search'),
		array('Low_search', 'build_index'),
		array('Low_search_mcp', 'build_index')
	);

	/**
	 * Hooks used
	 *
	 * @access      private
	 * @var         array
	 */
	private $hooks = array(
		'entry_submission_end',
		'delete_entries_loop',
		'channel_entries_query_result'
	);

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Install the module
	 *
	 * @access      public
	 * @return      bool
	 */
	public function install()
	{
		// --------------------------------------
		// Install tables
		// --------------------------------------

		ee()->low_search_collection_model->install();
		ee()->low_search_index_model->install();
		ee()->low_search_log_model->install();
		ee()->low_search_replace_log_model->install();

		// --------------------------------------
		// Add row to modules table
		// --------------------------------------

		ee()->db->insert('modules', array(
			'module_name'    => $this->class_name,
			'module_version' => $this->version,
			'has_cp_backend' => 'y'
		));

		// --------------------------------------
		// Add rows to action table
		// --------------------------------------

		foreach ($this->actions AS $row)
		{
			list($class, $method) = $row;

			ee()->db->insert('actions', array(
				'class'  => $class,
				'method' => $method
			));
		}

		// --------------------------------------
		// Add rows to extensions table
		// --------------------------------------

		foreach ($this->hooks AS $hook)
		{
			$this->_add_hook($hook);
		}

		// --------------------------------------

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Uninstall the module
	 *
	 * @return	bool
	 */
	public function uninstall()
	{
		// --------------------------------------
		// get module id
		// --------------------------------------

		$query = ee()->db->select('module_id')
		       ->from('modules')
		       ->where('module_name', $this->class_name)
		       ->get();

		// --------------------------------------
		// remove references from module_member_groups
		// --------------------------------------

		ee()->db->where('module_id', $query->row('module_id'));
		ee()->db->delete('module_member_groups');

		// --------------------------------------
		// remove references from modules
		// --------------------------------------

		ee()->db->where('module_name', $this->class_name);
		ee()->db->delete('modules');

		// --------------------------------------
		// remove references from actions
		// --------------------------------------

		ee()->db->where_in('class', array($this->class_name, $this->class_name.'_mcp'));
		ee()->db->delete('actions');

		// --------------------------------------
		// remove references from extensions
		// --------------------------------------

		ee()->db->where('class', $this->class_name.'_ext');
		ee()->db->delete('extensions');

		// --------------------------------------
		// Uninstall tables
		// --------------------------------------

		ee()->low_search_collection_model->uninstall();
		ee()->low_search_index_model->uninstall();
		ee()->low_search_log_model->uninstall();
		ee()->low_search_replace_log_model->uninstall();

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Update the module
	 *
	 * @return	bool
	 */
	public function update($current = '')
	{
		// --------------------------------------
		// Same version? A-okay, daddy-o!
		// --------------------------------------

		if ($current == '' OR version_compare($current, $this->version) === 0)
		{
			return FALSE;
		}

		// --------------------------------------
		// Update to 1.2.0
		// --------------------------------------

		if (version_compare($current, '1.2.0', '<'))
		{
			ee()->low_search_replace_log_model->install();
		}

		// --------------------------------------
		// Update to 2.0.0
		// --------------------------------------

		if (version_compare($current, '2.0.0', '<'))
		{
			// Insert another action
			ee()->db->insert('actions', array(
				'class'  => 'Low_search_mcp',
				'method' => 'build_index'
			));

			// Change hook entry_submission_absolute_end to entry_submission_end
			// so the API triggers it
			ee()->db->where('class', $this->class_name.'_ext');
			ee()->db->where('hook', 'entry_submission_absolute_end');
			ee()->db->update('extensions', array(
				'method' => 'entry_submission_end',
				'hook'   => 'entry_submission_end'
			));
		}

		// --------------------------------------
		// Update to 2.1.0
		// --------------------------------------

		if (version_compare($current, '2.1.0', '<'))
		{
			$this->_v210();
		}

		// Return TRUE to update version number in DB
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Add hook to extensions table
	 *
	 * @access     private
	 * @param      string
	 * @return     void
	 */
	private function _add_hook($hook)
	{
		ee()->db->insert('extensions', array(
			'class'     => $this->class_name.'_ext',
			'method'    => $hook,
			'hook'      => $hook,
			'priority'  => 10,
			'version'   => $this->version,
			'enabled'   => 'y',
			'settings'  => serialize($this->default_settings)
		));
	}

	// --------------------------------------------------------------------

	/**
	 * Update routines for version 2.1.0
	 *
	 * @access     private
	 * @return     void
	 */
	private function _v210()
	{
		// Fields to add to the DB
		$fields = array(
			'modifier' => 'decimal(2,1) unsigned NOT NULL default 1.0',
			'excerpt'  => 'int(6) unsigned NOT NULL default 0'
		);

		// Template query
		$tmpl = 'ALTER TABLE `%s` ADD `%s` %s AFTER `collection_label`';
		$tbl = ee()->low_search_collection_model->table();

		// Add fields
		foreach ($fields AS $field => $properties)
		{
			ee()->db->query(sprintf($tmpl, $tbl, $field, $properties));
		}

		// Get the collections and re-do the settings
		foreach (ee()->low_search_collection_model->get_all() AS $row)
		{
			// Initiate data array
			$data = array();

			// Decode the settings
			$settings = $this->decode($row['settings'], FALSE);

			// Set new property values
			$data['modifier'] = (float) (isset($settings['modifier']) ? $settings['modifier'] : 1.0);
			$data['excerpt']  = (int) (isset($settings['excerpt']) ? $settings['excerpt'] : 0);

			// Remove these properties from settings
			unset($settings['modifier'], $settings['excerpt']);

			// filter it
			$settings = array_filter($settings);

			// Encode the new settings for DB usage
			$data['settings'] = $this->encode($settings, FALSE);

			// Update row
			ee()->low_search_collection_model->update($row['collection_id'], $data);
		}
	}

} // End class

/* End of file upd.low_search.php */