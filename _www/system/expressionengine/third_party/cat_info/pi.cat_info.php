<?php

/*
=====================================================
 This ExpressionEngine plugin was created by Laisvunas
 - http://devot-ee.com/developers/ee/laisvunas/
=====================================================
 Copyright (c) Laisvunas
=====================================================
 This is commercial Software.
 One purchased license permits the use this Software on the SINGLE website.
 Unless you have been granted prior, written consent from Laisvunas, you may not:
 * Reproduce, distribute, or transfer the Software, or portions thereof, to any third party
 * Sell, rent, lease, assign, or sublet the Software or portions thereof
 * Grant rights to any other person
=====================================================
 File: pi.cat_info.php
-----------------------------------------------------
 Purpose: to output info about certain category. 
=====================================================
*/

$plugin_info = array(
						'pi_name'			=> 'Category Info',
						'pi_version'		=> '1.3',
						'pi_author'			=> 'Laisvunas',
						'pi_author_url'		=> 'http://devot-ee.com/developers/ee/laisvunas/',
						'pi_description'	=> 'Allows you to output info about certain category.',
						'pi_usage'			=> cat_info::usage()
					);
					
class Cat_info {

  var $return_data="";
  
  function Cat_info()
  {
    $this->EE =& get_instance();
    
    $this->EE->load->library('typography'); 
    $this->EE->typography->initialize(); 
    
    // Fetch the tagdata
		  $tagdata = $this->EE->TMPL->tagdata;
		  
		  // Fetch params
		  $category_id = $this->EE->TMPL->fetch_param('category_id');
		  $site = $this->EE->TMPL->fetch_param('site');
		  $invalid_input = $this->EE->TMPL->fetch_param('invalid_input');
		  
		  if ($site === FALSE)
		  {
      $site = 1;
    }
		  
		  // Define variables
		  $nonempty_subcategories_number = 0;
		  $parse_pos = 0;
		  $custom_field_variables = array();
    $conds = array();
    $conds2 = array();
		  
		  // Parameter "category_id" is required
		  if ($category_id === FALSE)
		  {
      if ($invalid_input === 'alert')
      {
        echo 'Parameter "category_id" of exp:cat_info tag must be defined!<br><br>';
      }
    }
    
    // The value of the parameter "category_id" must be a number
    if (is_numeric($category_id) === FALSE)
    {
      if ($invalid_input === 'alert')
      {
        echo 'The value of the parameter "category_id" of exp:cat_info tag must be a number!<br><br>';
      }
    }
    
    if ($category_id !== FALSE)
    {
      // Create SQL query string
      $todo = "SELECT exp_category_groups.group_name, exp_categories.group_id, exp_categories.parent_id, exp_categories.cat_name, exp_categories.cat_url_title, exp_categories.cat_description, exp_categories.cat_image 
               FROM 
                 exp_categories
                   INNER JOIN
                 exp_category_groups
                   ON
                 exp_categories.group_id=exp_category_groups.group_id 
               WHERE exp_categories.cat_id = '".$category_id."' AND exp_categories.site_id = '".$site."' LIMIT 0, 1";
      //echo '$todo: '.$todo.'<br><br>';
      
      // Perform SQL query
      $query = $this->EE->db->query($todo);
      
      // The case category having category id specified in parameter exists
      if ($query->num_rows() == 1)
      {
        // Create SQL query string
        $todo2 = "SELECT group_id, parent_id, cat_id, cat_name, cat_url_title, cat_description, cat_image FROM exp_categories WHERE parent_id = '".$category_id."' AND site_id = '".$site."'";
        
        // Perform SQL query
        $query2 = $this->EE->db->query($todo2);
        
        foreach ($query2->result_array() as $row)
        {
          $todo3 = "SELECT entry_id FROM exp_category_posts WHERE cat_id ='".$row['cat_id']."'";
          $query3 = $this->EE->db->query($todo3);
          if ($query3->num_rows() > 0)
          {
            $nonempty_subcategories_number++;
          }
        }
        
        // Create conditionals array
        $conds['cat_info_category_group'] = $query->row('group_id');
        $conds['cat_info_category_group_name'] = $query->row('group_name');
        $conds['cat_info_parent_category_id'] = $query->row('parent_id');
        $conds['cat_info_category_name'] = $query->row('cat_name');
        $conds['cat_info_category_url_title'] = $query->row('cat_url_title');
        $conds['cat_info_category_description'] = $query->row('cat_description');
        $conds['cat_info_category_image'] = $query->row('cat_image');
        $conds['cat_info_subcategories_number'] = $query2->num_rows();
        $conds['cat_info_nonempty_subcategories_number'] = $nonempty_subcategories_number;
      }
      
      // The case category having category id specified in parameter does not exist
      if ($query->num_rows() == 0)
      {
        // Create conditionals array
        $conds['cat_info_category_group'] = '';
        $conds['cat_info_category_group_name'] = '';
        $conds['cat_info_parent_category_id'] = '';
        $conds['cat_info_category_name'] = '';
        $conds['cat_info_category_url_title'] = '';
        $conds['cat_info_category_description'] = '';
        $conds['cat_info_category_image'] = '';
        $conds['cat_info_subcategories_number'] = ''; 
        $conds['cat_info_nonempty_subcategories_number'] = '';
      }
      
      // Prepare conditionals
      $tagdata = $this->EE->functions->prep_conditionals($tagdata, $conds);
      
      // Output values of variables
      $tagdata = str_replace('{cat_info_category_group_name}', $conds['cat_info_category_group_name'], $tagdata);
      $tagdata = str_replace('{cat_info_category_group}', $conds['cat_info_category_group'], $tagdata);
      $tagdata = str_replace('{cat_info_parent_category_id}', $conds['cat_info_parent_category_id'], $tagdata);
      $tagdata = str_replace('{cat_info_category_name}', $conds['cat_info_category_name'], $tagdata);
      $tagdata = str_replace('{cat_info_category_url_title}', $conds['cat_info_category_url_title'], $tagdata);
      $tagdata = str_replace('{cat_info_category_description}', $conds['cat_info_category_description'], $tagdata);
      $tagdata = str_replace('{cat_info_category_image}', $conds['cat_info_category_image'], $tagdata);
      $tagdata = str_replace('{cat_info_subcategories_number}', $conds['cat_info_subcategories_number'], $tagdata);
      $tagdata = str_replace('{cat_info_nonempty_subcategories_number}', $conds['cat_info_nonempty_subcategories_number'], $tagdata);
      
      // Parse custom field variables
      $custom_field_variables_number = substr_count($tagdata, 'cat_info_');
      //echo '$custom_field_variables_number: '.$custom_field_variables_number.'<br><br>';
      for ($i = 0; $i < $custom_field_variables_number; $i++)
      {
        $variable_main_part_parsed = '';
        $parse_pos = strpos($tagdata, 'cat_info_', $parse_pos) + 9;
        //echo '$parse_pos: '.$parse_pos.'<br><br>';
        $variable_length = strspn($tagdata, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', $parse_pos);
        //echo '$variable_length: '.$variable_length.'<br><br>';
        $variable_main_part_parsed = substr($tagdata, $parse_pos, $variable_length);
        //echo '$variable_main_part_parsed: ['.$variable_main_part_parsed.']<br><br>';
        array_push($custom_field_variables, $variable_main_part_parsed);
        //echo '1 count($custom_field_variables): '.count($custom_field_variables).'<br><br>';
        //echo '<hr>';
      }
      $custom_field_variables = array_unique($custom_field_variables);
      $custom_field_variables = array_values($custom_field_variables);
      //echo '2 count($custom_field_variables): '.count($custom_field_variables).'<br><br>';
      //echo '<hr>';
      
      for ($i = 0; $i < count($custom_field_variables); $i++)
      {
        $todo4 = "SELECT field_id FROM exp_category_fields WHERE field_name = '".$custom_field_variables[$i]."' AND group_id = '".$conds['cat_info_category_group']."' AND site_id = '".$site."' LIMIT 1";
        $query4 = $this->EE->db->query($todo4);
        if ($query4->num_rows() == 1)
        {
          $field_id = $query4->row('field_id');
          //echo '$field_id: '.$field_id.'<br><br>';
          $todo5 = "SELECT field_id_".$field_id.", field_ft_".$field_id." FROM exp_category_field_data WHERE group_id = '".$conds['cat_info_category_group']."' AND site_id = '".$site."' AND cat_id = '".$category_id."' LIMIT 1";
          $query5 = $this->EE->db->query($todo5);
          if ($query5->num_rows() == 1)
          {
            $custom_field_value = $query5->row('field_id_'.$field_id);
            $field_format = $query5->row('field_ft_'.$field_id);
            //echo '1 '.$custom_field_variables[$i].' : ['.$custom_field_value.']<br><br>';
            //echo '$field_format: ['.$field_format.']<br><br>';
            // Format custom field value
            $custom_field_value = $this->EE->typography->parse_type($custom_field_value, array('text_format' => $field_format));
            $custom_field_value = str_replace( array('&#123;', '&#125;', '{exp'), array(LD, RD, '&#123;exp'), $custom_field_value);
            //echo '2 '.$custom_field_variables[$i].' : ['.$custom_field_value.']<br><br>';
            $tagdata = str_replace(LD.'cat_info_'.$custom_field_variables[$i].RD, $custom_field_value, $tagdata);
            $conds2['cat_info_'.$custom_field_variables[$i]] = $custom_field_value;
          }
          //echo '<hr>';
        }
      }
      
      // Prepare custom field variables for use in conditionals
      if (count($custom_field_variables) > 0)
      {
        $tagdata = $this->EE->functions->prep_conditionals($tagdata, $conds2);
      }
      
      // Output tagdata
      $this->return_data = $tagdata; 
    }
  }
  // END FUNCTION
  
// ----------------------------------------
//  Plugin Usage
// ----------------------------------------
// This function describes how the plugin is used.
//  Make sure and use output buffering

function usage()
{
ob_start(); 
?>

PARAMETERS:

1) category_id - Required. Allows you to specify category id number.

2) site - Optional. Allows you to specify site id number.

3) invalid_input - Optional. Accepts two values: “alert” and “silence”.
Default value is “silence”. If the value is “alert”, then in cases when 
the plugin has some problem with parameters,  PHP alert is being shown;
if the value is “silence”, then in cases when the plugin has 
some problem with parameters, it finishes its work without any alert being shown. 
Set this parameter to “alert” for development, and to “silence” - for deployment. 

VARIABLES:

1) {cat_info_category_group_name}

2) {cat_info_category_group}

3) {cat_info_parent_category_id}

4) {cat_info_category_name}

5) {cat_info_category_url_title}

6) {cat_info_category_description}

7) {cat_info_category_image}

8) {cat_info_subcategories_number}

9) {cat_info_nonempty_subcategories_number}

Also the values of custom category fields can be retrieved using their names 
with "cat_info_" appended. E.g. if there is custom category field named 
"my_field", its value can be outputted using variable {cat_info_my_field}.

Conditionals are supported.

EXAMPLE OF USAGE:

{exp:cat_info category_id="{segment_3}"}

{if cat_info_category_group=="3"}
Some code
{/if}

{if cat_info_category_group=="45"}
Some code
{/if}

{/exp:cat_info}

<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
// END USAGE

}
// END CLASS
?>