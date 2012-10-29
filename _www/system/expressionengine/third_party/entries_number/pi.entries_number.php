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
 Purpose: Find number of entries posted into certain
 channels and/or into certain categories and/or having
 certain url_title. Use the number found in conditionals. 
=====================================================
*/

$plugin_info = array(
						'pi_name'			=> 'Entries Number',
						'pi_version'		=> '2.2.1',
						'pi_author'			=> 'Laisvunas',
						'pi_author_url'		=> 'http://devot-ee.com/developers/ee/laisvunas/',
						'pi_description'	=> 'Allows you to find number of entries posted into certain
 channels and/or into certain categories and/or having
 certain url_title. Use the number found in conditionals.',
						'pi_usage'			=> entries_number::usage()
					);
					
class Entries_number {

  var $return_data="";
  
  function Entries_number()
  {
    $this->EE =& get_instance();
    
    // Fetch the tagdata
		  $tagdata = $this->EE->TMPL->tagdata;
		  
		  // Fetch params
    $weblog = $this->EE->TMPL->fetch_param('weblog') ? $this->EE->TMPL->fetch_param('weblog') : $this->EE->TMPL->fetch_param('channel');
    $categoryid = $this->EE->TMPL->fetch_param('category');
    $urltitle = $this->EE->TMPL->fetch_param('url_title');
    $entryid = $this->EE->TMPL->fetch_param('entry_id');
    $site = $this->EE->TMPL->fetch_param('site');
    $status = $this->EE->TMPL->fetch_param('status');
    $invalid_input = $this->EE->TMPL->fetch_param('invalid_input');
    $show_expired = $this->EE->TMPL->fetch_param('show_expired');
    $author_id = $this->EE->TMPL->fetch_param('author_id');
    $field_name = $this->EE->TMPL->fetch_param('field_name');
    $field_value = $this->EE->TMPL->fetch_param('field_value');
    $field = $this->EE->TMPL->fetch_param('field') ? $this->EE->TMPL->fetch_param('field') : 'include';
    $show_future_entries = $this->EE->TMPL->fetch_param('show_future_entries') == 'yes' ? TRUE : FALSE;
    
    // Define variables
    $categoryidclause = '';
    $weblogclause = '';
    $urltitleclause = '';
    $entryidclause = '';
    $siteclause = '';
    $siteclause2 = '';
    $statusclause = '';
    $entryidequalityclause = '';
    $groupbyclause = '';
    $havingclause = '';
    $authoridclause = '';
    $categorytablesclause = '';
    $expiredentriesclause = '';
    $futureentriesclause = '';
    $fieldclause = '';
    $distinctoperator = '';
    $found_invalid = FALSE;
    $entriesnumber = 0;
    
    // Simple validation of params values
    $invalidchars = array('~', '#', '*', '{', '}', '[', ']', '/', '\\', '<', '>', '\'', '\"');
    foreach($invalidchars as $char)
    {
      if (strpos($weblog, $char) > 0 OR strpos($weblog, $char) === 0)
      {
        if ($invalid_input === 'alert')
        {
          echo 'Error! Parameter "weblog" of exp:entries_number tag contains illegal character.<br><br>'.PHP_EOL;
        }
        $found_invalid = TRUE;
      }
    }
    foreach($invalidchars as $char)
    {
      if (strpos($categoryid, $char) > 0 OR strpos($categoryid, $char) === 0)
      {
        if ($invalid_input === 'alert')
        {
          echo 'Error! Parameter "category" of exp:entries_number tag contains illegal character.<br><br>'.PHP_EOL;
        }
        $found_invalid = TRUE;
      }
    }
    foreach($invalidchars as $char)
    {
      if (strpos($urltitle, $char) > 0 OR strpos($urltitle, $char) === 0)
      {
        if ($invalid_input === 'alert')
        {
          echo 'Error! Parameter "url_title" of exp:entries_number tag contains illegal character.<br><br>'.PHP_EOL;
        }
        $found_invalid = TRUE;
      }
    }
    foreach($invalidchars as $char)
    {
      if (strpos($entryid, $char) > 0 OR strpos($entryid, $char) === 0)
      {
        if ($invalid_input === 'alert')
        {
          echo 'Error! Parameter "entry_id" of exp:entries_number tag contains illegal character.<br><br>'.PHP_EOL;
        }
        $found_invalid = TRUE;
      }
    }
    foreach($invalidchars as $char)
    {
      if (strpos($site, $char) > 0 OR strpos($site, $char) === 0)
      {
        if ($invalid_input === 'alert')
        {
          echo 'Error! Parameter "site" of exp:entries_number tag contains illegal character.<br><br>'.PHP_EOL;
        }
        $found_invalid = TRUE;
      }
    }
    
    if ($found_invalid === FALSE)
    {
      // If "category" parameter is defined
      if ($categoryid !== FALSE)
      {
        // Clean whitespace from "category" parameter value
        $categoryid = str_replace(' ', '', $categoryid);
        // Check if "category" param contains "not"
        if (strpos($categoryid, 'not')===0)  // In case "category" param contains "not" form SQL clause using "AND" and "!=" operators
        {
          $categoryid = substr($categoryid, 3);
          $categoryidarray = explode('|', $categoryid);
          foreach($categoryidarray as $categoryidnumber)
          {
            $categoryidclause .= " AND exp_category_posts.cat_id!='".$categoryidnumber."' ";
          }
          //exit('$categoryidclause: '.$categoryidclause);
        }
        else  // the case "category" param does not contain "not"
        {
          $categoryidarray = explode('|', $categoryid);
          $categoryidarray2 = explode('&', $categoryid);
          // the case in "category" param there is neither "|" symbol nor "&" symbol
          if (count($categoryidarray)==1 AND count($categoryidarray2)==1)
          {
            $categoryidclause = " AND exp_category_posts.cat_id='".$categoryidarray[0]."' ";
          }
          //the case in "category" param there is at least one "|" symbol
          elseif (count($categoryidarray)>1)
          {
            foreach($categoryidarray as $categoryidnumber)
            {
              $categoryidclause .= " OR exp_category_posts.cat_id='".$categoryidnumber."' ";
            }
            $categoryidclause = substr($categoryidclause, 4);
            $categoryidclause = " AND (".$categoryidclause.")";
            $distinctoperator = ' DISTINCT ';
          }
          //the case in "category" param there is at least one "&" symbol
          elseif (count($categoryidarray2)>1)
          {
            //echo 'count($categoryidarray2): '.count($categoryidarray2).'<br><br>';
            foreach($categoryidarray2 as $categoryidnumber2)
            {
              $categoryidclause .= " OR exp_category_posts.cat_id='".$categoryidnumber2."' ";
            }
            $categoryidclause = substr($categoryidclause, 4);
            $categoryidclause = " AND (".$categoryidclause.")";
            $groupbyclause = " GROUP BY exp_channel_titles.entry_id ";
            $havingclause = " HAVING count(exp_channel_titles.entry_id) = '".count($categoryidarray2)."' ";
          }
          //echo '$categoryidclause: '.$categoryidclause.'<br><br>';
          //echo '$havingclause: '.$havingclause.'<br><br>';
        }
        // Form category related clauses
        $entryidequalityclause = " AND exp_category_posts.entry_id=exp_channel_titles.entry_id ";
        $categorytablesclause = ", exp_category_posts ";
      }
      
      // If "weblog" parameter is defined
      if ($weblog !== FALSE)
      {
        // Clean whitespace from "weblog" parameter value
        $weblog = str_replace(' ', '', $weblog);
        // Check if "weblog" param contains "not"
        if (strpos($weblog, 'not')===0)
        {
          // In case "weblog" param contains "not" form SQL clause using "AND" and "!=" operators
          $weblog = substr($weblog, 3);
          $weblogarray = explode('|', $weblog);
          foreach($weblogarray as $weblogname)
          {
            $weblogclause .= " AND exp_channels.channel_name!='".$weblogname."' ";
          }
          //exit('$weblogclause: '.$weblogclause);
        }
        else
        {
          // In case "weblog" param does not contain "not" form SQL clause using "OR" and "=" operators
          $weblogarray = explode('|', $weblog);
          if (count($weblogarray)==1)
          {
            $weblogclause = " AND exp_channels.channel_name='".$weblogarray[0]."' ";
          }
          else
          {
            foreach($weblogarray as $weblogname)
            {
              $weblogclause .= " OR exp_channels.channel_name='".$weblogname."' ";
            }
            $weblogclause = substr($weblogclause, 4);
            $weblogclause = " AND (".$weblogclause.") ";
          }
          //exit('$weblogclause: '.$weblogclause);
        }
      }
      
      // If "author_id" parameter is defined
      if ($author_id !== FALSE)
      {
        $author_id = str_replace('{logged_in_member_id}', $this->EE->session->userdata['member_id'], $author_id);
        // Clean whitespace from "author_id" parameter value
        $author_id = str_replace(' ', '', $author_id);
        // Check if "author_id" param contains "not"
        if (strpos($author_id, 'not')===0)
        {
          // In case "author_id" param contains "not" form SQL clause using "AND" and "!=" operators
          $author_id = substr($author_id, 3);
          $authoridarray = explode('|', $author_id);
          foreach($authoridarray as $authoridnum)
          {
            $authoridclause .= " AND exp_channel_titles.author_id!='".$authoridnum."' ";
          }
        }
        else
        {
          // In case "author_id" param does not contain "not" form SQL clause using "OR" and "=" operators
          $authoridarray = explode('|', $author_id);
          if (count($authoridarray)==1)
          {
            $authoridclause = " AND exp_channel_titles.author_id='".$authoridarray[0]."' ";
          }
          else
          {
            foreach($authoridarray as $authoridnum)
            {
              $authoridclause .= " OR exp_channel_titles.author_id='".$authoridnum."' ";
              $authoridclause = substr($authoridclause, 4);
              $authoridclause = " AND (".$authoridclause.") ";
            }
          }
        }
        //echo 'authoridclause: '.$authoridclause.'<br><br>';
      }
      
      // Form status clause
      // By default not display entries having status "closed"
      $statusclause = " AND exp_channel_titles.status NOT IN ('closed') ";
      if ($status !== FALSE)
      {
        // Check if "status" param contains "not"
        if (strpos($status, 'not')===0)
        {
          // In case "status" param contains "not" form SQL clause using "AND" and "!=" operators
          $status = substr($status, 3);
          $statusarray = explode('|', $status);
          foreach($statusarray as $statusname)
          {
            $statusname = trim($statusname);
            $statusclause .= " AND exp_channel_titles.status!='".$statusname."' ";
          }
          //echo '$statusclause: '.$statusclause;
        }
        else
        {
          // In case "status" param does not contain "not" form SQL clause using "OR" and "=" operators
          $statusarray = explode('|', $status);
          if (count($statusarray)==1)
          {
            $statusclause = " AND exp_channel_titles.status='".$statusarray[0]."' ";
          }
          else
          {
            foreach($statusarray as $statusname)
            {
              $statusname = trim($statusname);
              $statusclause .= " OR exp_channel_titles.status='".$statusname."' ";
            }
            $statusclause = substr($statusclause, 4);
            $statusclause = " AND (".$statusclause.") ";
          }
        }
        //echo '$statusclause: '.$statusclause.'<br><br>';
      }
      
      // If "site" parameter is defined
      if ($site !== FALSE)
      {
        // Clean whitespace from "site" parameter value
        $site = str_replace(' ', '', $site);
        // Check if "site" param contains "not"
        if (strpos($site, 'not')===0)
        {
          // In case "site" param contains "not" form SQL clause using "AND" and "!=" operators
          $site = substr($site, 3);
          $sitearray = explode('|', $site);
          foreach($sitearray as $siteid)
          {
            $siteclause .= " AND exp_channel_titles.site_id!='".$siteid."' ";
            $siteclause2 .= " AND exp_sites.site_id!='".$siteid."' ";
          }
          //exit('$siteclause: '.$siteclause);
        }
        else
        {
          // In case "site" param does not contain "not" form SQL clause using "OR" and "=" operators
          $sitearray = explode('|', $site);
          if (count($sitearray)==1)
          {
            $siteclause = " AND exp_channel_titles.site_id='".$sitearray[0]."' ";
            $siteclause2 = " AND exp_sites.site_id='".$sitearray[0]."' ";
          }
          else
          {
            foreach($sitearray as $siteid)
            {
              $siteclause .= " OR exp_channel_titles.site_id='".$siteid."' ";
              $siteclause2 .= " OR exp_sites.site_id='".$siteid."' ";
            }
            $siteclause = substr($siteclause, 4);
            $siteclause2 = substr($siteclause2, 4);
            $siteclause = " AND (".$siteclause.") ";
            $siteclause2 = " AND (".$siteclause2.") ";
            //exit('$siteclause: '.$siteclause);
          }
        }
      }
      
      if ($urltitle !== FALSE)
      {
        $urltitleclause = " AND exp_channel_titles.url_title ";
        $urltitle = trim($urltitle);
        if (strpos($urltitle, 'not') === 0)
        {
          $urltitle .= ' NOT ';
          $urltitle = substr($urltitle, 3);
          $urltitle = trim($urltitle);
        }
        $urltitleclause .= " IN ('".str_replace("|", "','", $urltitle)."') ";
      }
      
      if ($entryid !== FALSE)
      {
        $entryidclause = " AND exp_channel_titles.entry_id ";
        $entryid = trim($entryid);
        if (strpos($entryid, 'not') === 0)
        {
          $entryid .= ' NOT ';
          $entryid = substr($entryid, 3);
          $entryid = trim($entryid);
        }
        $entryidclause .= " IN ('".str_replace("|", "','", $entryid)."') ";
      }
      
      // Form expired entries clause
      if ($show_expired === FALSE)
      {
        $expiredentriesclause = " AND (exp_channel_titles.expiration_date = '0' OR exp_channel_titles.expiration_date > '".$this->EE->localize->now."') ";
      }
      
      // Form future entries clause
      if ($show_future_entries === FALSE)
      {
        $futureentriesclause = " AND exp_channel_titles.entry_date < '".$this->EE->localize->now."' ";
      }
      
      // Form fieldclause
      // form fieldclause
      if ($field_name !== FALSE AND $field_value !== FALSE)
      {
        $field_id = 0;
        $sql_field_id = "SELECT exp_channel_fields.field_id 
                         FROM 
                           exp_channel_fields
                             INNER JOIN
                           exp_sites
                             ON 
                           exp_channel_fields.site_id = exp_sites.site_id 
                         WHERE exp_channel_fields.field_name='".$field_name."' ".$siteclause2." 
                         LIMIT 1";
        $query_field_id = $this->EE->db->query($sql_field_id);
        if ($query_field_id->num_rows() == 1)
        {
          $field_id_row = $query_field_id->row_array();
          $field_id = $field_id_row['field_id'];
          $compare = ' = ';
          if ($field === "exclude" OR $field === "!")
          {
            $compare = '!= '; 
          }
          if ($field === "like")
          {
            $compare = ' LIKE '; 
          }
          if ($field_id)
          {
            $fieldclause = " AND exp_channel_titles.entry_id IN ( 
                                 SELECT exp_channel_titles.entry_id 
                                 FROM 
                                   exp_channel_titles
                                     INNER JOIN
                                   exp_channel_data
                                     ON
                                   exp_channel_data.entry_id = exp_channel_titles.entry_id 
                                 WHERE exp_channel_data.field_id_".$field_id.$compare."'".$field_value."'
                            ) ";
          }
        }
      }
      
  		  // Create SQL query string
      $todo = "SELECT ".$distinctoperator." exp_channel_titles.url_title, exp_channel_titles.title, exp_channel_titles.entry_id, exp_channel_titles.status, exp_channel_titles.expiration_date, exp_channel_titles.author_id, exp_channels.channel_name FROM exp_channel_titles, exp_channels ".$categorytablesclause." WHERE exp_channel_titles.channel_id=exp_channels.channel_id ";
      $todo .= $entryidequalityclause.$categoryidclause.$weblogclause.$urltitleclause.$entryidclause.$statusclause.$authoridclause.$siteclause.$expiredentriesclause.$futureentriesclause.$fieldclause.$groupbyclause.$havingclause;
      //echo '$todo: '.$todo.'<br><br>';
      
      // Perform SQL query
      $query = $this->EE->db->query($todo);
      
      //Find number of entries
      $entriesnumber = $query->num_rows();
      
      //Create conditionals array
      $conds['entries_number'] = $entriesnumber;
      
      //Make entries_number variable available for use in conditionals
      $tagdata = $this->EE->functions->prep_conditionals($tagdata, $conds);
      
      // Output the value of {entries_number} variable
      $tagdata = str_replace('{entries_number}', $entriesnumber, $tagdata);
      
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

1) category - Optional. Allows you to specify category id number 
(the id number of each category is displayed in the Control Panel).
You can stack categories using pipe character to get entries 
with any of those categories, e.g. category="3|6|8". Or use "not" 
(with a space after it) to exclude categories, e.g. category="not 4|5|7".
Also you can use "&" symbol to get entries each of which was posted into all 
specified categories, e.g. category="3&6&8". 

2) weblog - Optional. Allows you to specify weblog name.
You can use the pipe character to get entries from any of those 
weblogs, e.g. weblog="weblog1|weblog2|weblog3".
Or you can add the word "not" (with a space after it) to exclude weblogs,
e.g. weblog="not weblog1|weblog2|weblog3".

3) channel (only for EE2.x) - Optional. Allows you to specify channel name.
You can use the pipe character to get entries from any of those 
channels, e.g. channel="channel1|channel2|channel3".
Or you can add the word "not" (with a space after it) to exclude channels,
e.g. channel="not channel1|channel2|channel3".

4) author_id - Optional. Allows you to specify author id number.
You can use the pipe character to get entries posted by any of those 
authors, e.g. author_id="5|11|18".
Or you can add the word "not" (with a space after it) to exclude authors,
e.g. author_id="not 1|9". If you need to output number of entries by
logged-in user, use variable {logged_in_member_id} as the value of this
parameter.

5) site - Optional. Allows you to specify site id number.
You can stack site id numbers using pipe character to get entries 
from any of those sites, e.g. site="1|3". Or use "not" 
(with a space after it) to exclude sites, e.g. site="not 1|2".

6) status - Optional. Allows you to specify status of entries.
You can stack statuses using pipe character to get entries 
having any of those statuses, e.g. status="open|draft". Or use "not" 
(with a space after it) to exclude statuses, 
e.g. status="not submitted|processing|closed".

7) url_title - Optional. Allows you to specify url_title of an entry.
You can stack url_titles using pipe character to get entries 
having any of those url_titles. Or use "not" 
(with a space after it) to exclude url_titles.

8) entry_id - Optional. Allows you to specify entry id number of an entry.
You can stack entry_ids using pipe character to get entries 
having any of those entry_ids. Or use "not" 
(with a space after it) to exclude entry_ids.

9) show_expired - Optional. Allows you to specify if you wish expired entries
to be counted. If the value is "yes", expired entries will be counted; if the
value is "no", expired entries will not be counted. Default value is "no".

10) show_future_entries - Optional. Allows you to specify if you wish future entries
to be counted. If the value is "yes", future entries will be counted; if the
value is "no", future entries will not be counted. Default value is "no".

11) invalid_input - Optional. Accepts two values: “alert” and “silence”.
Default value is “silence”. If the value is “alert”, then in cases when some
parameter’s value is invalid plugin exits and PHP alert is being shown;
if the value is “silence”, then in cases when some parameter’s value
is invalid plugin finishes its work without any alert being shown. 
Set this parameter to “alert” for development, and to “silence” - for deployment.

12) field_name - Optional. Used when there is a need to display entries
having certain custom field equal to or not equal to or like  
specific value.

13) field_value - Optional. Used when there is a need to display entries
having certain custom field equal to or not equal to or like specific value.

14) field - Optional. Used when there is a need to display entries having certain custom field
 *not* equal or *like* to specific value. Acceps the value "include", "exclude" and "like" (only entries 
having field_name LIKE field_value will be displayed - SQL LIKE notation will be used). 
Default value is "include".

VARIABLES:

1) entries_number - outputs the number of entries which satisfy condition 
entered in prameters.


EXAMPLE OF USAGE:

{exp:entries_number category="6" weblog="not weblog1|weblog4" site="1"}
{entries_number}
{/exp:entries_number}

The variable {entries_number} placed between {exp:entries_number} and {/exp:entries_number} tags
will output the number of entries which satisfy condition entered in prameters.

You can use {entries_number} variable in conditionals:

{exp:entries_number category="6" weblog="not weblog1|weblog4" site="1"}
{if entries_number==0}
Some code
{if:elseif entries_number==1}
Some other code
{if:else}
Yet another code
{/if}
{/exp:entries_number}

In contrast with "if no_results" conditional, which does not allow its parent tag {exp:weblog:entries} to be
wrapped in a plugin, contionals inside {exp:entries_number} does not interfere with outer plugins. That is,
while the code as this 

{exp:category_id category_group="3" category_url_title="segment_3" parse="inward"}
{exp:channel:entries channel="my_channel" category="{category_id}"}
{if no_results}
No entry found! 
{/if}
{/exp:channel:entries}
{/exp:category_id}

will not work, the code as this

{exp:category_id category_group="3" category_url_title="segment_3" parse="inward"}
{exp:entries_number channel="my_channel" category="{category_id}"}
{if entries_number==0}
No entry found! 
{/if}
{/exp:entries_number}
{/exp:category_id}

will work properly.

If you need to find number of entries in which certain field has definite value, use the
code as this:

{exp:entries_number channel="my_channel" field_name="my_cust_field" field_value="mycustomvalue"}
{entries_number}
{/exp:entries_number}  


<?php
$buffer = ob_get_contents();
	
ob_end_clean(); 

return $buffer;
}
// END USAGE

}
// END CLASS
?>