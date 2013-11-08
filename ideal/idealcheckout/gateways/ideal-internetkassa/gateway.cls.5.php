<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		public $issuerID;
		// Load iDEAL settings
		public function __construct($settingsData=array())
		{
			$this->init($settingsData);
		}

		public function doSetup()
		{
			$sHtml = '';

			// Look for proper GET's en POST's
			if(!isset($this->order_id) || !isset($this->order_code)) //bixie
			{
				$sHtml .= '<p>Invalid setup request.</p>
<!-- Invalid orderid/ordercode -->';
			}
			else
			{
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;
				$issuerID = $this->issuerID?$this->issuerID:'';

				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) //bixie
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$sHtml .= '<p>Transaction already completed</p>';
					}
					else
					{
						$oIdeal = new IdealInternetKassa();

						// Account settings
						$oIdeal->setValue('ACQUIRER', $this->aSettings['GATEWAY_NAME']); // Aquirer Name
						$oIdeal->setValue('PSPID', $this->aSettings['PSP_ID']); // Merchant Id
						$oIdeal->setValue('SHA1_IN_KEY', $this->aSettings['SHA_1_IN']); // Secret Hash Key
						$oIdeal->setValue('SHA1_OUT_KEY', $this->aSettings['SHA_1_OUT']); // Secret Hash Key
						$oIdeal->setValue('TEST_MODE', $this->aSettings['TEST_MODE']); // True=TEST, False=LIVE

						// Webshop settings
						// Set return URLs //bixie
						$uri = JURI::getInstance();
						$prot = BixTools::config('algemeen.betalen.bix_ideal.useSecure',1)?'https://':'http://';
						$siteRoot = $prot.$uri->toString(array('host', 'port'));
						$returnUrl = $siteRoot.'/index.php?option=com_bixprintshop&task=cart.betaalreturn';
						$pushUrl = $siteRoot.'/index.php?option=com_bixprintshop&task=cart.betaalpush';
						
						$oIdeal->setValue('accepturl', $returnUrl ); // Success/pending URL
						$oIdeal->setValue('declineurl', $returnUrl . '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'] . '&status=CANCELLED'); // Failure URL
						$oIdeal->setValue('exceptionurl', $returnUrl . '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'] . '&status=CANCELLED'); // Failure URL
						$oIdeal->setValue('cancelurl', $returnUrl . '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'] . '&status=CANCELLED'); // Cancelled URL
						$oIdeal->setValue('backurl', $returnUrl . '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'] . '&status=CANCELLED'); // Cart/Checkout URL
						// $oIdeal->setValue('homeurl', idealcheckout_getRootUrl()); // Homepage URL
						// $oIdeal->setValue('catalogurl', idealcheckout_getRootUrl()); // Catalog URL

						// Payment method(s)
						$oIdeal->setValue('PM', 'iDEAL'); // Force 'iDEAL' or 'CreditCard'
						// $oIdeal->setValue('PMLIST', 'iDEAL;CreditCard'); // Available payment methods in acquirer GUI (when no PM was forced)
						$oIdeal->setValue('OPERATION', 'SAL');
						//WERKTNOGNIET!!!
						$oIdeal->setValue('ISSUERID', $issuerID);
						// Order settings
						$oIdeal->setValue('orderID', $this->oRecord['order_id']); // Order ID
						$oIdeal->setValue('COM', $this->oRecord['transaction_description']); // Order Description
						$oIdeal->setValue('amount', intval(round($this->oRecord['transaction_amount'] * 100))); // Order Amount
						
						// Customer settings (Optional)
						// $oIdeal->setValue('CN', 'Martijn Wieringa'); // Customer Name
						// $oIdeal->setValue('EMAIL', 'info@ideal-checkout.nl'); // Customer Email

						$sHtml = '' . $oIdeal->createForm('Verder >>') . '';

						// Add auto-submit button
						if($this->aSettings['TEST_MODE'] == false)
						{
							//$sHtml .= '<script type="text/javascript"> function doAutoSubmit() { document.forms[0].submit(); } setTimeout(\'doAutoSubmit()\', 100); </script>';
						}
					}
				}
				else
				{
					$sHtml .= '<p>Invalid setup request.</p>
<!-- Invalid orderid/ordercode -->';
				}
			}

			return $sHtml;
		}


		// Catch return
		public function doReturn()
		{
			global $aIdealCheckout; 
			$sHtml = '';
			$returnResult = array('response'=>array());
			$returnResult['request'] = JRequest::get('GET');
			$returnResult['debugEmail'] = "Ideal Simulator \n";


			if(!empty($_GET['order_id']) && !empty($_GET['order_code']) && !empty($_GET['status'])) // Cancelled
			{
				$sOrderId = $_GET['order_id'];
				$sOrderCode = $_GET['order_code'];
				$sTransactionStatus = 'CANCELLED';
				$statusName = 'BIX_CANCELLED';

				if($this->getRecordByOrder($sOrderId, $sOrderCode))
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$returnResult['succes'] = false;
						$returnResult['betaalStatus'] = 'BIX_SUCCESS';
						$returnResult['debugEmail'] .= $returnResult['message'] = 'al afgehandeld';
					}
					else
					{

						$returnResult['succes'] = true;
						$returnResult['betaalID'] = $sOrderCode;
						$returnResult['debugEmail'] .= "OrderCode: ".$sOrderCode." \n";
						$returnResult['betaalStatus'] = $statusName;
						$returnResult['debugEmail'] .= "Status: $statusName \n";
						$returnResult['betaalDatum'] = strftime('%Y-%m-%d %T',time());
						


						$this->oRecord['transaction_status'] = $sTransactionStatus;


						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Recieved status ' . $this->oRecord['transaction_status'] . ' on ' . date('Y-m-d, H:i:s') . '.';


						// Update transaction
						$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "' , `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
						idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);

					}
				}

				return $returnResult;
			}





			$oIdeal = new IdealInternetKassa();

			// Account settings
			$oIdeal->setValue('ACQUIRER', $this->aSettings['GATEWAY_NAME']); // Aquirer Name
			$oIdeal->setValue('PSPID', $this->aSettings['PSP_ID']); // Merchant Id
			$oIdeal->setValue('SHA1_IN_KEY', $this->aSettings['SHA_1_IN']); // Secret Hash Key
			$oIdeal->setValue('SHA1_OUT_KEY', $this->aSettings['SHA_1_OUT']); // Secret Hash Key
			$oIdeal->setValue('TEST_MODE', $this->aSettings['TEST_MODE']); // True=TEST, False=LIVE


			$sTransactionStatus = $oIdeal->validate();

			if($sTransactionStatus === false)
			{
				$returnResult['debugEmail'] .= $returnResult['message'] = 'Invalid return request.';
			}
			else
			{
				$sOrderId = $oIdeal->getValue('ORDERID');
				$sTransactionId = $oIdeal->getValue('PAYID');

				// Lookup record
				if($this->getRecordByOrder($sOrderId))
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$returnResult['succes'] = false;
						$returnResult['betaalStatus'] = 'BIX_SUCCESS';
						$returnResult['debugEmail'] .= $returnResult['message'] = 'al afgehandeld';
					}
					else
					{
						$this->oRecord['transaction_id'] = $sTransactionId;
						$this->oRecord['transaction_status'] = $sTransactionStatus;

						$returnResult['response']['transactionId'] = $sTransactionId;
						$returnResult['response']['transactionStatus'] = $sTransactionStatus;

						$statusName = '';
						switch ($sTransactionStatus) {
							case 'SUCCESS':
								$statusName = 'BIX_SUCCESS';
							break;
							case 'FAILURE':
								$statusName = 'BIX_FAILURE';
							break;
							case 'CANCELLED':
								$statusName = 'BIX_CANCELLED';
							break;
							case 'PENDING':
								$statusName = 'BIX_PENDING';
							break;
						}

						$returnResult['succes'] = true;
						$returnResult['betaalID'] = $sTransactionId;
						$returnResult['debugEmail'] .= "BetaalID: ".$sTransactionId." \n";
						$returnResult['betaalStatus'] = $statusName;
						$returnResult['debugEmail'] .= "Status: $statusName \n";
						$returnResult['betaalDatum'] = strftime('%Y-%m-%d %T',time());

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $sTransactionId . ' on ' . date('Y-m-d, H:i:s') . '.';


						if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
						{
							$returnResult['valid'] = true;
							//$sHtml .= '<p>Uw betaling is met succes ontvangen.<br><input style="margin: 6px;" type="button" value="Verder" onclick="javascript: document.location.href = \'' . htmlspecialchars($this->oRecord['transaction_success_url']) . '\'"></p>';
						}
						else
						{
							if(strcmp($this->oRecord['transaction_status'], 'CANCELLED') === 0)
							{
						//		$sHtml .= '<p>Uw betaling is geannuleerd. Probeer opnieuw te betalen.<br><input style="margin: 6px;" type="button" value="Verder" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1) . 'idealcheckout/setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
							}
							elseif(strcmp($this->oRecord['transaction_status'], 'EXPIRED') === 0)
							{
								//$sHtml .= '<p>Uw betaling is mislukt. Probeer opnieuw te betalen.<br><input style="margin: 6px;" type="button" value="Verder" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1) . 'idealcheckout/setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
							}
							else // if(strcmp($this->oRecord['transaction_status'], 'FAILURE') === 0)
							{
							//	$sHtml .= '<p>Uw betaling is mislukt. Probeer opnieuw te betalen.<br><input style="margin: 6px;" type="button" value="Verder" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1) . 'idealcheckout/setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
							}


							if($this->oRecord['transaction_payment_url'])
							{
								//$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_payment_url']) . '">kies een andere betaalmethode</a></p>';
							}
							elseif($this->oRecord['transaction_failure_url'])
							{
								//$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_failure_url']) . '">ik kan nu niet betalen via deze betaalmethode</a></p>';
							}
						}


						// Update transaction
						$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
						idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);



						// Handle status change
						if(function_exists('idealcheckout_update_order_status'))
						{
							//idealcheckout_update_order_status($this->oRecord, 'doReturn');
						}



						if(!empty($this->oRecord['transaction_success_url']) && (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0))
						{
							//header('Location: ' . $this->oRecord['transaction_success_url']);
							//exit;
						}
					}
				}
				else
				{
					$returnResult['debugEmail'] .= $returnResult['message'] = 'Invalid return request.';
				}
			}

			return $returnResult;
		}
	}

