<?php


	// Update order status when required
	function idealcheckout_update_order_status($oRecord, $sView)
	{
		$aDatabaseSettings = idealcheckout_getDatabaseSettings();

		$aOrderParams = idealcheckout_unserialize($oRecord['order_params']);

		if(strcasecmp($oRecord['transaction_status'], 'SUCCESS') === 0)
		{
			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "virtuemart_orders` SET `order_status` = 'C' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "virtuemart_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['success']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);

			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "vm_orders` SET `order_status` = 'C' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "vm_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['success']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);
		}
		elseif(strcasecmp($oRecord['transaction_status'], 'PENDING') === 0)
		{
			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "virtuemart_orders` SET `order_status` = '" . ($aOrderParams['status']['pending']) . "' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "virtuemart_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['pending']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);

			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "vm_orders` SET `order_status` = '" . ($aOrderParams['status']['pending']) . "' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "vm_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['pending']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);
		}
		else // Failed
		{
			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "virtuemart_orders` SET `order_status` = '" . ($aOrderParams['status']['cancelled']) . "' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "virtuemart_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['cancelled']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);

			$sql = "UPDATE `" . $aDatabaseSettings['prefix'] . "vm_orders` SET `order_status` = '" . ($aOrderParams['status']['cancelled']) . "' WHERE (`order_number` = '" . $oRecord['order_id'] . "') LIMIT 1;";
			idealcheckout_database_query($sql);

			$sql = "INSERT INTO `" . $aDatabaseSettings['prefix'] . "vm_order_histories` SET `virtuemart_order_history_id` = NULL, `virtuemart_order_id` = '" . $oRecord['order_id'] . "', `order_status_code` = '" . ($aOrderParams['status']['cancelled']) . "', `customer_notified` = '0', `comments` = '" . idealcheckout_getTranslation('nl', 'virtuemart', 'Payment status recieved from PSP: {status}', array('status' => $oRecord['transaction_status'])) . "', `published` = '1', `created_on` = '" . time() . "', `created_by` = '0', `modified_on` = '" . time() . "', `modified_by` = '0';";
			idealcheckout_database_query($sql);
		}
	}

?>