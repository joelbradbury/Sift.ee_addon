<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

 /**
 * mithra62 - CT Admin
 *
 * @package		mithra62:Ct_admin
 * @author		Eric Lamb
 * @copyright	Copyright (c) 2012, mithra62, Eric Lamb.
 * @link		http://mithra62.com/projects/view/ct-admin/
 * @since		1.4.1
 * @filesource 	./system/expressionengine/third_party/ct_admin/
 */
 
 /**
 * CT Admin - Upd Class
 *
 * Updater class
 *
 * @package 	mithra62:Ct_admin
 * @author		Eric Lamb
 * @filesource 	./system/expressionengine/third_party/ct_admin/upd.ct_admin.php
 */
class Ct_admin_upd 
{ 
    
    public $name = 'Ct_admin';
    
    public $class = 'Ct_admin';
    
    public $settings_table = 'ct_admin_settings';
    
    public $cart_items_table = 'ct_admin_cart_items';    
     
    public function __construct() 
    { 
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();
		include PATH_THIRD.'ct_admin/config'.EXT;
		
		$this->version = $config['version'];		
    } 
    
	public function install() 
	{
		$this->EE->load->dbforge();
	
		$data = array(
			'module_name' => $this->name,
			'module_version' => $this->version,
			'has_cp_backend' => 'y',
			'has_publish_fields' => 'n'
		);
	
		$this->EE->db->insert('modules', $data);
		
		$sql = "INSERT INTO exp_actions (class, method) VALUES ('".$this->name."', 'void')";
		$this->EE->db->query($sql);
		
		$this->add_settings_table();
		$this->add_cart_items_table();
		
		$this->activate_extension();
		
		return TRUE;
	} 
	
	public function activate_extension()
	{
		$data = array();
		$data[] = array(
					'class'      => 'Ct_admin_ext',
					'method'    => 'delete_saved_cart',
					'hook'  => 'cartthrob_on_authorize',
				
					'settings'    => '',
					'priority'    => 1,
					'version'    => $this->version,
					'enabled'    => 'y'
		);	

		$data[] = array(
					'class'      => 'Ct_admin_ext',
					'method'    => 'save_add_to_cart',
					'hook'  => 'cartthrob_add_to_cart_end',
				
					'settings'    => '',
					'priority'    => 1,
					'version'    => $this->version,
					'enabled'    => 'y'
		);	

		$data[] = array(
					'class'      => 'Ct_admin_ext',
					'method'    => 'update_saved_cart',
					'hook'  => 'cartthrob_update_cart_start',
				
					'settings'    => '',
					'priority'    => 1,
					'version'    => $this->version,
					'enabled'    => 'y'
		);
		
		$data[] = array(
				'class'      => 'Ct_admin_ext',
				'method'    => 'cart_max_quantity',
				'hook'  => 'cartthrob_add_to_cart_start',
					
				'settings'    => '',
				'priority'    => 22,
				'version'    => $this->version,
				'enabled'    => 'y'
		);	
		
		$data[] = array(
				'class'      => 'Ct_admin_ext',
				'method'    => 'require_valid_coupon',
				'hook'  => 'cartthrob_pre_process',
					
				'settings'    => '',
				'priority'    => 99999,
				'version'    => $this->version,
				'enabled'    => 'y'
		);
	
		foreach($data AS $ex)
		{
			$this->EE->db->insert('extensions', $ex);	
		}		
	}

	public function uninstall()
	{
		$this->EE->load->dbforge();
	
		$this->EE->db->select('module_id');
		$query = $this->EE->db->get_where('modules', array('module_name' => $this->class));
	
		$this->EE->db->where('module_id', $query->row('module_id'));
		$this->EE->db->delete('module_member_groups');
	
		$this->EE->db->where('module_name', $this->class);
		$this->EE->db->delete('modules');
	
		$this->EE->db->where('class', $this->class);
		$this->EE->db->delete('actions');
		
		//$this->EE->dbforge->drop_table($this->settings_table);
		//$this->EE->dbforge->drop_table($this->cart_items_table);
		
		$this->disable_extension();
	
		return TRUE;
	}
	
	public function disable_extension()
	{
		$this->EE->db->where('class', 'Ct_admin_ext');
		$this->EE->db->delete('extensions');
	}

	public function update($current = '')
	{
		
		if ($current == $this->version)
		{
			return FALSE;
		}	

		if ($current < 1.1)
		{
			$this->add_settings_table();		
		}
		
		$data = array();
		if($current < 1.2)
		{
			$this->add_cart_items_table();
			$data[] = array(
						'class'      => 'Ct_admin_ext',
						'method'    => 'delete_saved_cart',
						'hook'  => 'cartthrob_on_authorize',
					
						'settings'    => '',
						'priority'    => 1,
						'version'    => $this->version,
						'enabled'    => 'y'
			);	
	
			$data[] = array(
						'class'      => 'Ct_admin_ext',
						'method'    => 'save_add_to_cart',
						'hook'  => 'cartthrob_add_to_cart_end',
					
						'settings'    => '',
						'priority'    => 1,
						'version'    => $this->version,
						'enabled'    => 'y'
			);	
	
			$data[] = array(
						'class'      => 'Ct_admin_ext',
						'method'    => 'update_saved_cart',
						'hook'  => 'cartthrob_update_cart_start',
					
						'settings'    => '',
						'priority'    => 1,
						'version'    => $this->version,
						'enabled'    => 'y'
			);			
	
			//
		
			foreach($data AS $ex)
			{
				$this->EE->db->insert('extensions', $ex);	
			}

			//$this->update_extension();
		}
		
		if($current < 1.4)
		{
			$sql_member_id_exists = "SHOW COLUMNS FROM ".$this->EE->db->dbprefix.$this->cart_items_table." LIKE 'member_id'";
			$member_id_exists = $this->EE->db->query($sql_member_id_exists);
			if($member_id_exists->num_rows == 0) 
			{
				$sql_view_id_add = "ALTER TABLE `".$this->EE->db->dbprefix.$this->cart_items_table."` ADD `member_id` INT( 10 ) NULL DEFAULT NULL AFTER `cart_id` , ADD INDEX ( `member_id` ) ";
				$this->EE->db->query($sql_view_id_add);
			}
		}
		
		if($current < 1.5)
		{
			/**
			$data = array();
			$data[] = array(
					'class'      => 'Ct_admin_ext',
					'method'    => 'cart_max_quantity',
					'hook'  => 'cartthrob_update_cart_start',
						
					'settings'    => '',
					'priority'    => 22,
					'version'    => $this->version,
					'enabled'    => 'y'
			);
			
			$data[] = array(
					'class'      => 'Ct_admin_ext',
					'method'    => 'require_valid_coupon',
					'hook'  => 'cartthrob_pre_process',
						
					'settings'    => '',
					'priority'    => 99999,
					'version'    => $this->version,
					'enabled'    => 'y'
			);
			
			foreach($data AS $ex)
			{
				$this->EE->db->insert('extensions', $ex);
			}
			*/						
		}
		
	}	
	
	private function add_settings_table()
	{
		$this->EE->load->dbforge();
		$fields = array(
						'id'	=> array(
											'type'			=> 'int',
											'constraint'	=> 10,
											'unsigned'		=> TRUE,
											'null'			=> FALSE,
											'auto_increment'=> TRUE
										),
						'setting_key'	=> array(
											'type' 			=> 'varchar',
											'constraint'	=> '30',
											'null'			=> FALSE,
											'default'		=> ''
										),
						'setting_value'  => array(
											'type' 			=> 'text',
											'null'			=> FALSE
										),
						'serialized' => array(
											'type' => 'int',
											'constraint' => 1,
											'null' => TRUE,
											'default' => '0'
						)										
		);

		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('id', TRUE);
		$this->EE->dbforge->create_table($this->settings_table, TRUE);		
	}	
	
	private function add_cart_items_table()
	{
		$this->EE->load->dbforge();
		$fields = array(
						'cart_id'	=> array(
											'type'			=> 'varchar',
											'constraint'	=> 100,
											'null'			=> FALSE
										),
						'row_id'	=> array(
											'type' 			=> 'int',
											'constraint'	=> '10',
											'null'			=> FALSE,
											'default'		=> '0'
										),
						'member_id'	=> array(
											'type' 			=> 'int',
											'constraint'	=> '10',
											'null'			=> FALSE,
											'default'		=> '0'
										),										
						'quantity' => array(
											'type' => 'int',
											'constraint' => '10',
											'null' => FALSE,
											'default' => '0'
										),
						'product_id'	=> array(
											'type' 			=> 'int',
											'constraint'	=> '10',
											'null'			=> FALSE,
											'default'		=> '0'
										),	
						'entry_id'	=> array(
											'type' 			=> 'int',
											'constraint'	=> '10',
											'null'			=> FALSE,
											'default'		=> '0'
										),											
						'shipping'	=> array(
											'type' 			=> 'varchar',
											'constraint'	=> '100',
											'null'			=> TRUE,
											'default'		=> ''
										),																																																												
						'weight'  => array(
											'type' 			=> 'varchar',
											'constraint' 	=> '100',
											'null'			=> TRUE,
											'default'		=> ''
										),										
						'price'	=> array(
											'type' 			=> 'varchar',
											'constraint'	=> '100',
											'null'			=> TRUE,
											'default'		=> ''
										),
										
						'no_tax'	=> array(
											'type' 			=> 'varchar',
											'constraint'	=> '100',
											'null'			=> TRUE,
											'default'		=> ''
										),
						'no_shipping'	=> array(
											'type' 			=> 'varchar',
											'constraint'	=> '100',
											'null'			=> TRUE
										),
						'item_options'  => array(
											'type' 			=> 'text',
											'null'			=> TRUE
										),	
						'meta'  => array(
											'type' 			=> 'text',
											'null'			=> TRUE
										),											
						'last_modified'	=> array(
											'type' 			=> 'datetime'
										),
						'created_date'	=> array(
											'type' 			=> 'datetime'
						)										
		);

		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('cart_id');
		$this->EE->dbforge->add_key('member_id');
		$this->EE->dbforge->add_key('entry_id');
		$this->EE->dbforge->add_key('product_id');
		$this->EE->dbforge->add_key('last_modified');
		$this->EE->dbforge->create_table($this->cart_items_table, TRUE);	

	}
    
}