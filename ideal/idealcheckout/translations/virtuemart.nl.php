<?php

	$aTranslations = array();

	$aTranslations['PAYMENT_SUCCESS'] = '<p><b>Bestelling</b>: {order}<br><b>Prijs</b>: &euro; {amount}<br><b>Status</b>: Betaald</p>';
	$aTranslations['PAYMENT_PENDING'] = '<p><b>Bestelling</b>: {order}<br><b>Prijs</b>: &euro; {amount}<br><b>Status</b>: Betaling wordt verwerkt</p>';
	$aTranslations['PAYMENT_FAILURE'] = '<p><b>Bestelling</b>: {order}<br><b>Prijs</b>: &euro; {amount}<br><b>Status</b>: Betaling mislukt</p>';
	$aTranslations['Payment status recieved from PSP: {status}'] = 'Betaalstatus update ontvangen van de Payment Service Provider: {status}';

	return $aTranslations;

?>