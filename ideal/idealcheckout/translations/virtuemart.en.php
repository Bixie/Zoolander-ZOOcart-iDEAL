<?php

	$aTranslations = array();

	$aTranslations['PAYMENT_SUCCESS'] = '<p><b>Order</b>: {order}<br><b>Price</b>: &euro; {amount}<br><b>State</b>: Paid</p>';
	$aTranslations['PAYMENT_PENDING'] = '<p><b>Order</b>: {order}<br><b>Price</b>: &euro; {amount}<br><b>State</b>: Pending</p>';
	$aTranslations['PAYMENT_FAILURE'] = '<p><b>Order</b>: {order}<br><b>Price</b>: &euro; {amount}<br><b>State</b>: Payment failed</p>';
	$aTranslations['Payment status recieved from PSP: {status}'] = 'Payment status recieved from PSP: {status}';

	return $aTranslations;

?>