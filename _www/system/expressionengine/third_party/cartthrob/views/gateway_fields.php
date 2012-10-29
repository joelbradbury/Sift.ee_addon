<?php echo $hidden; ?>
<?php foreach ($sections as $section => $fields) : ?>
<?=form_fieldset(lang($section), array('class' => $section, 'id' => $section))?>
<?php foreach ($fields as $field) : ?>

	<?=form_label(lang($field), $field, (in_array($field, $required_fields)) ? array('class' => 'required') : array())?>

<?php
	$attributes = array('id' => $field);
	
	if (in_array($field, $required_fields))
	{
		$attributes['class'] = 'required';
	}
	
	$value = $this->cartthrob->cart->customer_info($field);
	
	if (in_array($field, $nameless_fields))
	{
		$field = '';
	}

	switch ($field)
	{
		case 'account_type': 
			$field = form_dropdown($field, $account_types, $value, _parse_attributes($attributes)); 
			break;
		case 'card_type': 
			$field = form_dropdown($field, $card_types, $value, _parse_attributes($attributes)); 
			break;
		case 'subscription_interval': 
			$field = form_dropdown($field, array('months' => 'Months', 'days' => 'Days', 'weeks' => 'Weeks', 'years' => 'Years'), $value, _parse_attributes($attributes)); 
		case 'subscription_interval_units': 
			$field = form_dropdown($field, $subscription_interval_units, $value, _parse_attributes($attributes)); 
			break;	
		case 'expiration_month':
		case 'begin_month':
			$field = form_dropdown($field, $months, $value, _parse_attributes($attributes)); 
			break;
		case 'expiration_year':
			$field =form_dropdown($field, $exp_years, $value, _parse_attributes($attributes)); 
			break;
		case 'begin_year':
			$field = form_dropdown($field, $begin_years, $value, _parse_attributes($attributes)); 
			break;
		case 'state': 
		case 'shipping_state':
			$states = array_merge(array('' => '---'), $states);
			$field = form_dropdown($field, $states, $value, _parse_attributes($attributes)); 
			break;
		case 'country': 
		case 'shipping_country': 
		case 'country_code': 
		case 'shipping_country_code':
			#$countries = array_merge(array('' => '---'), $countries);
			$field = form_dropdown($field, $countries, $value, _parse_attributes($attributes));
			break;
		default:
			$attributes['name'] = $field;
			$attributes['value'] = $value;
			$field = form_input($attributes);
	}
	
	$field = str_replace('<option', str_repeat("\t", 2).'<option', $field);
	$field = str_replace('</select', str_repeat("\t", 1).'</select', $field);
?>
	<?=$field?>

<?php endforeach; ?>
<?=form_fieldset_close()?>

<?php endforeach; ?>
