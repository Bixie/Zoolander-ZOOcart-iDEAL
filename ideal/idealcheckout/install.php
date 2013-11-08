<?php

	global $aIdealCheckout;

	// Load database settings
	require_once(dirname(__FILE__) . '/includes/init.php');

	$aQueries = array();


	// Add idealcheckout table
	$aQueries[] = "CREATE TABLE IF NOT EXISTS `" . $aIdealCheckout['database']['table'] . "` (
`id` int(8) UNSIGNED NOT NULL AUTO_INCREMENT, 
`order_id` VARCHAR(64) DEFAULT NULL, 
`order_code` VARCHAR(64) DEFAULT NULL, 
`order_params` TEXT DEFAULT NULL, 
`store_code` VARCHAR(64) DEFAULT NULL, 
`gateway_code` VARCHAR(64) DEFAULT NULL, 
`language_code` VARCHAR(2) DEFAULT NULL, 
`country_code` VARCHAR(2) DEFAULT NULL, 
`currency_code` VARCHAR(3) DEFAULT NULL, 
`transaction_id` VARCHAR(64) DEFAULT NULL, 
`transaction_code` VARCHAR(64) DEFAULT NULL, 
`transaction_params` TEXT DEFAULT NULL, 
`transaction_date` INT(11) UNSIGNED DEFAULT NULL, 
`transaction_amount` DECIMAL(10, 2) UNSIGNED DEFAULT NULL, 
`transaction_description` VARCHAR(100) DEFAULT NULL, 
`transaction_status` VARCHAR(16) DEFAULT NULL, 
`transaction_url` VARCHAR(255) DEFAULT NULL, 
`transaction_payment_url` VARCHAR(255) DEFAULT NULL, 
`transaction_success_url` VARCHAR(255) DEFAULT NULL, 
`transaction_pending_url` VARCHAR(255) DEFAULT NULL, 
`transaction_failure_url` VARCHAR(255) DEFAULT NULL, 
`transaction_log` TEXT DEFAULT NULL, 
PRIMARY KEY (`id`));";

/*

	// Detect Joomla 1.5 or 1.7
	$sql = "SELECT `id` FROM `" . $aIdealCheckout['database']['prefix'] . "plugins` LIMIT 1;";
	if(idealcheckout_database_query($sql)) // Joomla 1.5
	{
		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - iDEAL', 
`element` = 'idealcheckoutideal',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - Mister Cash',
`element` = 'idealcheckoutmistercash',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - Direct E-Banking',
`element` = 'idealcheckoutdirectebanking',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - Credit Card',
`element` = 'idealcheckoutcreditcard',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - MiniTix',
`element` = 'idealcheckoutminitix',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - PayPal',
`element` = 'idealcheckoutpaypal',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "plugins` SET 
`id` = NULL, 
`name` = 'iDEAL Checkout - PaySafeCard',
`element` = 'idealcheckoutpaysafecard',
`folder` = 'vmpayment',
`access` = 0, 
`ordering` = 0, 
`published` = 1, 
`iscore` = 0, 
`client_id` = 0, 
`checked_out` = 0, 
`checked_out_time` = '0000-00-00 00:00:00', 
`params` = '';";
	}
	else // Joomla 1.7
	{
		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - iDEAL',
`type` = 'plugin',
`element` = 'idealcheckoutideal',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - Mister Cash',
`type` = 'plugin',
`element` = 'idealcheckoutmistercash',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - Direct E-Banking',
`type` = 'plugin',
`element` = 'idealcheckoutdirectebanking',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - Credit Card',
`type` = 'plugin',
`element` = 'idealcheckoutcreditcard',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - MiniTix',
`type` = 'plugin',
`element` = 'idealcheckoutminitix',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - PayPal',
`type` = 'plugin',
`element` = 'idealcheckoutpaypal',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";


		$aQueries[] = "INSERT INTO `" . $aIdealCheckout['database']['prefix'] . "extensions` SET
`extension_id` = NULL,
`name` = 'iDEAL Checkout - PaySafeCard',
`type` = 'plugin',
`element` = 'idealcheckoutpaysafecard',
`folder` = 'vmpayment',
`client_id` = '0',
`enabled` = '1',
`access` = '1',
`protected` = '0',
`manifest_cache` = '',
`params` = '',
`custom_data` = '',
`system_data` = '',
`checked_out` = '0',
`checked_out_time` = '0000-00-00 00:00:00',
`ordering` = '0',
`state` = '0';";
	}



*/


	$query_html = '';
	
	for($i = 0; $i < sizeof($aQueries); $i++)
	{
		if(idealcheckout_database_query($aQueries[$i]))
		{
			// Query success
			//echo $aQueries[$i]. "<br> \n";
		}
		else
		{
			$query_html .= '<b>Query:</b> ' . $aQueries[$i] . '<br><b>Error:</b> ' . idealcheckout_database_error() . '<br><br><br>';
		}
	}


	// Validate files & directories
	$sBasePath = dirname(__FILE__);

	$aPaths = array();

	// Gateway files
	$aPaths[] = array('path' => $sBasePath . '/certificates', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/configuration', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/gateways', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/gateways/gateway.core.cls.4.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/gateways/gateway.core.cls.5.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/images', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/images/ideal.gif', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/temp', 'write' => true);
	$aPaths[] = array('path' => $sBasePath . '/.htaccess', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/includes/init.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/index.html', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/report.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/return.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/setup.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/transaction.php', 'write' => false);
	$aPaths[] = array('path' => $sBasePath . '/validate.php', 'write' => false);


	$files_html = '';

	for($i = 0; $i < sizeof($aPaths); $i++)
	{
		if(is_file($aPaths[$i]['path']))
		{
			if($aPaths[$i]['write'] && !is_writable($aPaths[$i]['path']))
			{
				$files_html .= 'File <b>' . $aPaths[$i]['path'] . '</b> not writable.<br>';
			}
		}
		elseif(is_dir($aPaths[$i]['path']))
		{
			if($aPaths[$i]['write'] && !is_writable($aPaths[$i]['path']))
			{
				$files_html .= 'Directory <b>' . $aPaths[$i]['path'] . '</b> not writable.<br>';
			}
		}
		else
		{
			$files_html .= 'File <b>' . $aPaths[$i]['path'] . '</b> does not exist.<br>';
		}
	}


	echo '
<h1>INSTALL LOG</h1>
<p style="color: red;">Please remove this file (FTP: /idealcheckout/install.php) after installation!</p>

<p>&nbsp;</p>

<h3>Queries:</h3>
<code>' . ($query_html ? $query_html : 'No warnings found') . '</code>

<p>&nbsp;</p>

<h3>Files &amp; Folders:</h3>
<code>' . ($files_html ? $files_html : 'No warnings found') . '</code>

<h3>Server checks:</h3>
<code>PHP Version: ' . PHP_VERSION . '<br>OPENSSL Library: ' . (function_exists('openssl_sign') ? 'Installed' : 'Not installed') . '<br>FSOCK Library: ' . (function_exists('fsockopen') ? 'Installed' : 'Not installed') . '<br>CURL Library: ' . (function_exists('curl_init') ? 'Installed' : 'Not installed') . '</code>';

?>