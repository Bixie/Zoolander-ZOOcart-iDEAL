<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		// Load iDEAL settings
		public function __construct($settingsData=array())
		{
			$this->init($settingsData);
		}

		
		// Setup payment
		public function doSetup()
		{
			$sHtml = '';

			// Look for proper GET's en POST's
			if(!isset($this->order_id) || !isset($this->order_code)) //bixie
			{
				$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid setup request.') . '</p>';
			}
			else
			{
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;

				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) //bixie
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Transaction already completed.') . '</p>';
					}
					else
					{
						$oOmniKassa = new OmniKassa('ideal');
						$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

						if(!empty($this->aSettings['KEY_VERSION']))
						{
							$oOmniKassa->setKeyVersion($this->aSettings['KEY_VERSION']);
						}

						$oOmniKassa->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
						$oOmniKassa->setAquirer($this->aSettings['GATEWAY_NAME'], $this->aSettings['TEST_MODE']);

						if(!empty($this->oRecord['language_code']))
						{
							$oOmniKassa->setLanguageCode($this->oRecord['language_code']);
						}
						
						// Set return URLs //bixie
						$uri = JURI::getInstance();
						$prot = BixTools::config('algemeen.betalen.bix_ideal.useSecure',1)?'https://':'http://';
						$siteRoot = $prot.$uri->toString(array('host', 'port'));
						$returnUrl = $siteRoot.'/index.php?option=com_bixprintshop&task=cart.betaalreturn';
						$pushUrl = $siteRoot.'/index.php?option=com_bixprintshop&task=cart.betaalpush';
						$oOmniKassa->setNotifyUrl($pushUrl);//. '&order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code'] . '&status=CANCELLED'??
						$oOmniKassa->setReturnUrl($returnUrl);

						// Set order details
						$oOmniKassa->setOrderId($this->oRecord['order_id']); // Unieke order referentie (tot 32 karakters)
						$oOmniKassa->setAmount($this->oRecord['transaction_amount'], $this->oRecord['currency_code']); // Bedrag (in EURO's)

						$iTry = 1;

						$aTransactionParams = array('omnikassa_try' => 1);

						if(!empty($this->oRecord['transaction_params']))
						{
							$aTransactionParams = idealcheckout_unserialize($this->oRecord['transaction_params']);

							if(empty($aTransactionParams['omnikassa_try']))
							{
								$aTransactionParams['omnikassa_try'] = 1;
							}
							else
							{
								$aTransactionParams['omnikassa_try']++;
								$iTry = $aTransactionParams['omnikassa_try'];
							}
						}

						$this->oRecord['transaction_params'] = idealcheckout_serialize($aTransactionParams);

						$sTransactionCode = $this->oRecord['order_id'] . 'n' . $this->oRecord['id'] . (($iTry > 1) ? 'p' . $iTry : '');

						// Save transaction_code in record
						$this->oRecord['transaction_code'] = $sTransactionCode;
						$this->save();

						$oOmniKassa->setTransactionReference($sTransactionCode);


						// Customize submit button
						$oOmniKassa->setButton('' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . ' >>');

						$sHtml = $oOmniKassa->createForm();

						// Add auto-submit button
						if($this->aSettings['TEST_MODE'] == false)
						{
							//$sHtml .= '<script type="text/javascript"> function doAutoSubmit() { document.forms[0].submit(); } setTimeout(\'doAutoSubmit()\', 100); </script>';
						}
					}
				}
				else
				{
					$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid  request.') . '</p>';
				}
			}

			return $sHtml;
		}


		// Catch return
		public function doReturn()
		{
			global $aIdealCheckout; 

			$sHtml = '';
			
			if(empty($_POST['Data']) || empty($_POST['Seal']))
			{
				$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid return request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Missing params in $_POST -->' : '');
			}
			else
			{
				$oOmniKassa = new OmniKassa('ideal');
				$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

				$aOmniKassaResponse = $oOmniKassa->validate();

				if($aOmniKassaResponse && is_array($aOmniKassaResponse))
				{
					$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_code` = '" . idealcheckout_escapeSql($aOmniKassaResponse['transaction_code']) . "') ORDER BY `id` DESC LIMIT 1;";
					$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

					if(idealcheckout_database_num_rows($oRecordset))
					{
						$this->oRecord = idealcheckout_database_fetch_assoc($oRecordset);

						if(strcmp(preg_replace('/[^a-zA-Z0-9]+/', '', $aIdealCheckout['record']['order_id']), $aOmniKassaResponse['order_id']) !== 0)
						{
							$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid return request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Invalid OrderId recieved -->' : '');
						}
						elseif(false && strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
						{
							if($this->oRecord['transaction_success_url'])
							{
								header('Location: ' . $this->oRecord['transaction_success_url']);
								exit;
							}
							else
							{
								$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was recieved.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Return to the website') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1)) . '\'"></p>';
							}
						}
						else
						{
							$this->oRecord['transaction_id'] = $aOmniKassaResponse['transaction_id'];
							$this->oRecord['transaction_status'] = $aOmniKassaResponse['transaction_status'];

							if(empty($this->oRecord['transaction_log']) == false)
							{
								$this->oRecord['transaction_log'] .= "\n\n";
							}

							$this->oRecord['transaction_log'] .= 'RETURN: Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $this->oRecord['transaction_id'] . ' on ' . date('Y-m-d, H:i:s') . '.';

							if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
							{
								$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was recieved.') . '' . ($this->oRecord['transaction_success_url'] ? '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars($this->oRecord['transaction_success_url']) . '\'">' : '') . '</p>';
							}
							elseif((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url']))
							{
								$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment is in progress.') . '' . ($this->oRecord['transaction_url'] ? '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars($this->oRecord['transaction_url']) . '\'">' : '') . '</p>';
							}
							else
							{
								if(strcmp($this->oRecord['transaction_status'], 'CANCELLED') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was cancelled.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}
								elseif(strcmp($this->oRecord['transaction_status'], 'EXPIRED') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment has failed.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}
								else // if(strcmp($this->oRecord['transaction_status'], 'FAILURE') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment has failed.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}


								if($this->oRecord['transaction_payment_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_payment_url']) . '">' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Select another payment method') . '</a></p>';
								}
								elseif($this->oRecord['transaction_failure_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_failure_url']) . '">' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Cancel payment') . '</a></p>';
								}
							}


							// Update transaction
							$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
							idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);



							// Handle status change
							if(function_exists('idealcheckout_update_order_status'))
							{
								idealcheckout_update_order_status($this->oRecord, 'doReturn');
							}


							if($this->oRecord['transaction_success_url'] && (strcasecmp($this->oRecord['transaction_status'], 'SUCCESS') === 0))
							{
								header('Location: ' . $this->oRecord['transaction_success_url']);
								exit;
							}
						}
					}
					else
					{
						$returnResult['debugEmail'] .= $returnResult['message'] = 'Invalid return request.';
					}
				}
				else
				{
					$returnResult['debugEmail'] .= $returnResult['message'] = 'Invalid return request.';
				}
			}

			return $returnResult;
		}


		// Catch report
		public function doReport()
		{
			global $aIdealCheckout; 

			$sHtml = '';
			
			if(empty($_POST['Data']) || empty($_POST['Seal']))
			{
				$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid report request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Missing params in $_POST -->' : '');
			}
			else
			{
				$oOmniKassa = new OmniKassa('ideal');
				$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

				$aOmniKassaResponse = $oOmniKassa->validate();

				if($aOmniKassaResponse && is_array($aOmniKassaResponse))
				{
					$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_code` = '" . idealcheckout_escapeSql($aOmniKassaResponse['transaction_code']) . "') ORDER BY `id` DESC LIMIT 1;";
					$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

					if(idealcheckout_database_num_rows($oRecordset))
					{
						$this->oRecord = idealcheckout_database_fetch_assoc($oRecordset);

						if(strcmp(preg_replace('/[^a-zA-Z0-9]+/', '', $aIdealCheckout['record']['order_id']), $aOmniKassaResponse['order_id']) !== 0)
						{
							$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid report request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Invalid OrderId recieved -->' : '');
						}
						elseif(false && strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
						{
							$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was recieved.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Return to the website') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl(1)) . '\'"></p>';
						}
						else
						{
							$this->oRecord['transaction_id'] = $aOmniKassaResponse['transaction_id'];
							$this->oRecord['transaction_status'] = $aOmniKassaResponse['transaction_status'];

							if(empty($this->oRecord['transaction_log']) == false)
							{
								$this->oRecord['transaction_log'] .= "\n\n";
							}

							$this->oRecord['transaction_log'] .= 'REPORT: Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $this->oRecord['transaction_id'] . ' on ' . date('Y-m-d, H:i:s') . '.';

							if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
							{
								$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was recieved.') . '' . ($this->oRecord['transaction_success_url'] ? '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars($this->oRecord['transaction_success_url']) . '\'">' : '') . '</p>';
							}
							elseif((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url']))
							{
								$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment is in progress.') . '' . ($this->oRecord['transaction_url'] ? '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars($this->oRecord['transaction_url']) . '\'">' : '') . '</p>';
							}
							else
							{
								if(strcmp($this->oRecord['transaction_status'], 'CANCELLED') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment was cancelled.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}
								elseif(strcmp($this->oRecord['transaction_status'], 'EXPIRED') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment has failed.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}
								else // if(strcmp($this->oRecord['transaction_status'], 'FAILURE') === 0)
								{
									$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Your payment has failed.') . '<br><input style="margin: 6px;" type="button" value="' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Continue') . '" onclick="javascript: document.location.href = \'' . htmlspecialchars(idealcheckout_getRootUrl() . 'setup.php?order_id=' . $this->oRecord['order_id'] . '&order_code=' . $this->oRecord['order_code']) . '\'"></p>';
								}


								if($this->oRecord['transaction_payment_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_payment_url']) . '">' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Select another payment method') . '</a></p>';
								}
								elseif($this->oRecord['transaction_failure_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_failure_url']) . '">' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Cancel payment') . '</a></p>';
								}
							}


							// Update transaction
							$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
							idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);



							// Handle status change
							if(function_exists('idealcheckout_update_order_status'))
							{
								idealcheckout_update_order_status($this->oRecord, 'doReport');
							}
						}
					}
					else
					{
						$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid report request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Cannot find record in database -->' : '');
					}
				}
				else
				{
					$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid report request.') . '</p>' . ($this->aSettings['TEST_MODE'] ? '<!-- Invalid response from OmniKassa -->' : '');
				}
			}

			idealcheckout_output($sHtml);
		}
	}

?>