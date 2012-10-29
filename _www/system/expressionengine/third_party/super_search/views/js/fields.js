var character_explanation		= "<?=ee()->lang->line('character_explanation'); ?>";
var float_explanation			= "<?=ee()->lang->line('float_explanation'); ?>";
var decimal_explanation			= "<?=ee()->lang->line('decimal_explanation'); ?>";
var integer_explanation			= "<?=ee()->lang->line('integer_explanation'); ?>";
var small_integer_explanation	= "<?=ee()->lang->line('small_integer_explanation'); ?>";
var text_explanation			= "<?=ee()->lang->line('text_explanation'); ?>";
var tiny_integer_explanation	= "<?=ee()->lang->line('tiny_integer_explanation'); ?>";
var varchar_explanation			= "<?=ee()->lang->line('varchar_explanation'); ?>";

jQuery(document).ready(function()
{
	set_rows();

	jQuery("select#type").change( set_rows );
	

});

function set_rows()
{
	jQuery( "tr#length, tr#precision" ).hide();
	
	var type = jQuery("select#type").val();
	
	switch ( type )
	{
		case 'character':
			jQuery( "tr#length" ).show();
			jQuery( "div#type_explanation" ).text( character_explanation );
			break;
		case 'decimal':
			jQuery( "tr#length" ).show();
			jQuery( "tr#precision" ).show();
			jQuery( "div#type_explanation" ).text( decimal_explanation );
			break;
		case 'float':
			jQuery( "tr#length" ).show();
			jQuery( "tr#precision" ).show();
			jQuery( "div#type_explanation" ).text( float_explanation );
			break;
		case 'integer':
			jQuery( "div#type_explanation" ).text( integer_explanation );
			break;
		case 'small integer':
			jQuery( "div#type_explanation" ).text( small_integer_explanation );
			break;
		case 'text':
			jQuery( "div#type_explanation" ).text( text_explanation );
			break;
		case 'tiny integer':
			jQuery( "div#type_explanation" ).text( tiny_integer_explanation );
			break;
		case 'varchar':
			jQuery( "tr#length" ).show();
			jQuery( "div#type_explanation" ).text( varchar_explanation );
			break;
	}
}
