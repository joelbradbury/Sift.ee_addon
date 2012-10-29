<?php 
/**
 * WAIT!! Don't edit this file. 
 * If you want to customize the print invoice rename this file to:
 * print_invoice_custom.php
 * 
 * This will prevent the template from being overwritten when you upgrade :)
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en" xml:lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $order_details['title']; ?></title>
<style>
body, td, th, input, select, textarea, option, optgroup {
	font-family: Verdana, Arial, Helvetica, sans-serif;
	font-size: 12px;
}
h1 {
	text-transform: uppercase;
	color: #CCCCCC;
	text-align: right;
	font-size: 24px;
	font-weight: normal;
	padding-bottom: 5px;
	margin-top: 0px;
	margin-bottom: 15px;
	border-bottom: 1px solid #CDDDDD;
}
.content-wrap {
	width: 100%;
	margin-bottom: 20px;
}
.heading td {
	background: #ECF1F4;
}
.address, .product {
	border-collapse: collapse;
}
.address {
	width: 100%;
	margin-bottom: 20px;
	border-top: 1px solid #CDDDDD;
	border-right: 1px solid #CDDDDD;
}
.address th, .address td {
	border-left: 1px solid #CDDDDD;
	border-bottom: 1px solid #CDDDDD;
	padding: 5px;
}
.address td {
	width: 50%;
}
.product {
	width: 100%;
	margin-bottom: 20px;
	border-top: 1px solid #CDDDDD;
	border-right: 1px solid #CDDDDD;
}
.product td {
	border-left: 1px solid #CDDDDD;
	border-bottom: 1px solid #CDDDDD;
	padding: 5px;
}

</style>
</head>
<body>
<div style="page-break-after: always;">
  <?php if($order_details['order_transaction_id'] != ''): ?>
  <h1><?php echo ($packing_slip ? 'Packing Slip for order number:' : 'Invoice'); ?> #<?php echo $order_details['order_transaction_id']; ?></h1>
  <?php endif; ?>
  <div class="content-wrap">
    <table width="100%">
      <tr>
        <td>
        	<?php echo $site_name; ?><br />
			<?php echo $webmaster_email; ?><br />
          	<?php echo $site_url; ?>
        </td>
        <td align="right" valign="top"><table>
            <tr>
              <td><b><?php echo lang('order_date'); ?>:</b></td>
              <td><?php echo m62_convert_timestamp($order_details['entry_date']); ?></td>
            </tr>
            <tr>
              <td><b><?php echo ($order_details['order_transaction_id'] != '' ? lang('order_transaction_id').':' : ''); ?></b></td>
              <td>
              <?php if($order_details['order_transaction_id'] != ''): ?>
              	#<?php echo $order_details['order_transaction_id']; ?>
              <?php endif; ?>	
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div>
  <table class="address">
    <tr class="heading">
      <td width="50%"><b>To</b></td>
      <td width="50%"><b>Ship To</b></td>
    </tr>
    <tr>
      <td>
        <?php echo $order_details['orders_billing_first_name'].' '.$order_details['orders_billing_last_name']; ?><br />
        <?php echo $order_details['orders_billing_address']; ?><br />
        <?php echo $order_details['orders_billing_address2']; ?><br />
        <?php echo $order_details['orders_billing_city']; ?>, <?php echo $order_details['orders_billing_state']; ?> <?php echo $order_details['orders_billing_zip']; ?><br/>
        <?php echo $order_details['orders_customer_email']?>
      </td>
      <td>
        <?php echo $order_details['orders_shipping_first_name'].' '.$order_details['orders_shipping_last_name']; ?><br />
        <?php echo $order_details['orders_shipping_address']; ?><br />
        <?php echo $order_details['orders_shipping_address2']; ?><br />
        <?php echo $order_details['orders_shipping_city']; ?>, <?php echo $order_details['orders_shipping_state']; ?> <?php echo $order_details['orders_shipping_zip']; ?>
      </td>
    </tr>
  </table>
  <table class="product">
    <tr class="heading">
      <td><b><?php echo lang('product'); ?></b></td>
      <td align="right"><b>Quantity</b></td>
      <?php if(!$packing_slip): ?>
      <td align="right"><b>Unit Price</b></td>
      <td align="right"><b>Total</b></td>
      <?php endif; ?>
    </tr>
    <?php foreach($order_items AS $item): ?>
    
    <tr>
      <td>
      <?php 
      	$cell = $item['title']; 
      	if(isset($item['extra']) && $item['extra'] != '')
		{
			$options = @unserialize(base64_decode($item['extra']));
			if(is_array($options) && count($options) >= '1')
			{
				if(isset($options['sub_items']) && is_array($options['sub_items']) && count($options['sub_items']) >= '1')
				{
					$cell .= ' ('.count($options['sub_items']).' Product Package'.')';
				}
				else
				{
				   $cell .= ' ('.implode(' / ', $options).')';
				}
			}
		}
		
		echo $cell;
      
      ?>
      </td>
      <td align="right"><?php echo $item['quantity']; ?></td>
      <?php if(!$packing_slip): ?>
      <td align="right"><?php echo $number_format_defaults_prefix.$item['price']; ?></td>
      <td align="right"><?php echo $number_format_defaults_prefix.($item['quantity']*$item['price']); ?></td>
      <?php endif; ?>
    </tr>		
	<?php endforeach; ?>
	<?php if(!$packing_slip): ?>
    <tr>
      <td align="right" colspan="3"><b>Sub-Total:</b></td>
      <td align="right"><?php echo $number_format_defaults_prefix.$order_details['order_subtotal']; ?></td>
    </tr>
     <tr>
      <td align="right" colspan="3"><b>Shipping:</b></td>

      <td align="right"><?php echo $number_format_defaults_prefix.$order_details['order_shipping']; ?></td>
    </tr>
    <tr>
      <td align="right" colspan="3"><b>Tax:</b></td>
      <td align="right"><?php echo $number_format_defaults_prefix.$order_details['order_tax']; ?></td>
    </tr>
    <tr>
      <td align="right" colspan="3"><b>Discount:</b></td>
      <td align="right"><?php echo $number_format_defaults_prefix.$order_details['order_discount']; ?></td>
    </tr>    
    <tr>
      <td align="right" colspan="3"><b>Total:</b></td>
      <td align="right"><?php echo $number_format_defaults_prefix.$order_details['order_total']; ?></td>
    </tr>
    <?php endif; ?>
  </table>
</div>
</body>
</html>