<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		public $rPluginParams;
		public $zoo;
		// Load iDEAL settings
		public function __construct($settingsData=array(),$rPluginParams)
		{
			$this->init($settingsData);
			$this->rPluginParams = $rPluginParams;
			//test merchant, default ID
			if ($this->rPluginParams->get('test',0)) {
				$this->aSettings['MERCHANT_ID'] = '002020000000001';
				$this->aSettings['HASH_KEY'] = '002020000000001_KEY1';
				$this->aSettings['KEY_VERSION'] = '1';
			}
		}

		
		// Setup payment
		public function doSetup() {
			$sHtml = '';

			// Look for proper GET's en POST's
			if (!isset($this->order_id) || !isset($this->order_code)) //bixie
			{
				$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Invalid setup request.') . '</p>';
			}
			else
			{
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;

				// Lookup transaction
				if ($this->getRecordByOrder($sOrderId,$sOrderCode)) //bixie
				{
					if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$sHtml .= '<p>' . idealcheckout_getTranslation($this->oRecord['language_code'], 'idealcheckout', 'Transaction already completed.') . '</p>';
					}
					else
					{

							
						$oOmniKassa = new OmniKassa('ideal');
						$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

						if (!empty($this->aSettings['KEY_VERSION']))
						{
							$oOmniKassa->setKeyVersion($this->aSettings['KEY_VERSION']);
						}

						$oOmniKassa->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
						$oOmniKassa->setAquirer($this->aSettings['GATEWAY_NAME'], $this->rPluginParams->get('test',0));

						if (!empty($this->oRecord['language_code']))
						{
							$oOmniKassa->setLanguageCode($this->oRecord['language_code']);
						}
						
						$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal');
						$pushUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal').'&push=1';
						$oOmniKassa->setReturnUrl($returnUrl);
						$oOmniKassa->setNotifyUrl($pushUrl);

						// Set order details
						$oOmniKassa->setOrderId($this->oRecord['order_id']); // Unieke order referentie (tot 32 karakters)
						$oOmniKassa->setAmount($this->oRecord['transaction_amount'], $this->oRecord['currency_code']); // Bedrag (in EURO's)

						$iTry = 1;

						$aTransactionParams = array('omnikassa_try' => 1);

						if (!empty($this->oRecord['transaction_params']))
						{
							$aTransactionParams = idealcheckout_unserialize($this->oRecord['transaction_params']);

							if (empty($aTransactionParams['omnikassa_try']))
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
						if ($this->aSettings['TEST_MODE'] == false)
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


		/**
		 * catch return
		 *
		 * @return array(
		 *         		valid: true/false, 
		 *         		transaction_id
		 *         		transaction_date,
		 *         		status,
		 *         		success: 0 => failed, 1 => success, -1 => pending,
		 *				redirect: false (default) or internal url
		 *         )
		 */
		public function doReturn()
		{
			global $aIdealCheckout; 

			$returnResult = array(
				'valid'=>false,
				'order_id'=>0,
				'transaction_id'=>'',
				'transaction_date'=>'',
				'status'=>'',
				'message'=>'',
				'success'=>0,
				'redirect'=>false,
				'messageStyle'=>'',
				'formHtml'=>''
			);
			
			if (empty($_POST['Data']) || empty($_POST['Seal'])) {
				$returnResult['valid'] = false;
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			} else {
				$oOmniKassa = new OmniKassa('ideal');
				$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

				$aOmniKassaResponse = $oOmniKassa->validate();

				if ($aOmniKassaResponse && is_array($aOmniKassaResponse)) {
					$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_code` = '" . idealcheckout_escapeSql($aOmniKassaResponse['transaction_code']) . "') ORDER BY `id` DESC LIMIT 1;";
					$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

					if (idealcheckout_database_num_rows($oRecordset)) {
						$this->oRecord = idealcheckout_database_fetch_assoc($oRecordset);
						$returnResult['order_id'] = $this->oRecord['order_id'];
						if (strcmp(preg_replace('/[^a-zA-Z0-9]+/', '', $aIdealCheckout['record']['order_id']), $aOmniKassaResponse['order_id']) !== 0) {
							$returnResult['message'] = 'Invalid orderid.';
							$returnResult['messageStyle'] = 'uk-alert-danger';
							$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
						} elseif (false && strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
							$returnResult['valid'] = true;
							$returnResult['success'] = 1;
							$returnResult['status'] = 'SUCCESS';
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');;
							$returnResult['messageStyle'] = 'uk-alert-success';
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
						} else { //valid new request
							$returnResult['valid'] = true;
							$this->oRecord['transaction_id'] = $aOmniKassaResponse['transaction_id'];
							$this->oRecord['transaction_status'] = $aOmniKassaResponse['transaction_status'];
							$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
							$returnResult['status'] = $this->oRecord['transaction_status'];
							$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');

							if (empty($this->oRecord['transaction_log']) == false) {
								$this->oRecord['transaction_log'] .= "\n\n";
							}

							$this->oRecord['transaction_log'] .= 'RETURN: Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $this->oRecord['transaction_id'] . ' on ' . date('Y-m-d, H:i:s') . '.';

							switch ($this->oRecord['transaction_status']) {
								case 'SUCCESS':
									$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
									$returnResult['messageStyle'] = 'uk-alert-success';
									$returnResult['success'] = 1;
									$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
								break;
								case 'OPEN':
									$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_PENDING');
									$returnResult['messageStyle'] = 'uk-alert-warning';
									$returnResult['success'] = -1;
									$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
								break;
								case 'CANCELLED':
								case 'EXPIRED':
								case 'FAILURE':
								default:
									$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
									$returnResult['messageStyle'] = 'uk-alert-danger';
									$returnResult['success'] = 0;
									$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
								break;
							}


							// Update transaction
							$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
							idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);

						}
					} else {
						$returnResult['message'] = 'Invalid return request: db-record not found';
						$returnResult['messageStyle'] = 'uk-alert-danger';
						$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
					}
				} else {
					$returnResult['message'] = 'Invalid response data.';
					$returnResult['messageStyle'] = 'uk-alert-danger';
					$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
				}
			}
			return $returnResult;
		}


		// Catch report
		public function doReport()
		{
			global $aIdealCheckout; 

			$returnResult = array(
				'valid'=>false,
				'transaction_id'=>'',
				'transaction_date'=>'',
				'status'=>'',
				'message'=>'',
				'success'=>0,
				'redirect'=>false,
				'messageStyle'=>'',
				'formHtml'=>''
			);
			
			
			if (empty($_POST['Data']) || empty($_POST['Seal'])) {
				$returnResult['valid'] = false;
				$returnResult['message'] = 'Invalid return request.';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			} else {
				$oOmniKassa = new OmniKassa('ideal');
				$oOmniKassa->setHashKey($this->aSettings['HASH_KEY']);

				$aOmniKassaResponse = $oOmniKassa->validate();

				if ($aOmniKassaResponse && is_array($aOmniKassaResponse)) {
					$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_code` = '" . idealcheckout_escapeSql($aOmniKassaResponse['transaction_code']) . "') ORDER BY `id` DESC LIMIT 1;";
					$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

					if (idealcheckout_database_num_rows($oRecordset)) {
						$this->oRecord = idealcheckout_database_fetch_assoc($oRecordset);
						$returnResult['order_id'] = $this->oRecord['order_id'];

						if (strcmp(preg_replace('/[^a-zA-Z0-9]+/', '', $aIdealCheckout['record']['order_id']), $aOmniKassaResponse['order_id']) !== 0) {
							$returnResult['message'] = 'Invalid orderid.';
							$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
						} elseif (false && strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
							$returnResult['success'] = 1;
							$returnResult['status'] = 'SUCCESS';
							$returnResult['message'] = 'Al afgehandeld';
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
						} else {
							$returnResult['valid'] = true;
							$this->oRecord['transaction_id'] = $aOmniKassaResponse['transaction_id'];
							$this->oRecord['transaction_status'] = $aOmniKassaResponse['transaction_status'];
							$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
							$returnResult['status'] = $this->oRecord['transaction_status'];
							$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');

							if (empty($this->oRecord['transaction_log']) == false) {
								$this->oRecord['transaction_log'] .= "\n\n";
							}

							$this->oRecord['transaction_log'] .= 'REPORT: Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $this->oRecord['transaction_id'] . ' on ' . date('Y-m-d, H:i:s') . '.';

							switch ($this->oRecord['transaction_status']) {
								case 'SUCCESS':
									$returnResult['success'] = 1;
									$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
								break;
								case 'OPEN':
									$returnResult['success'] = -1;
									$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
								break;
								case 'CANCELLED':
								case 'EXPIRED':
								case 'FAILURE':
								default:
									$returnResult['success'] = 0;
									$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
								break;
							}

							// Update transaction
							$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
							idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);

						}
					} else {
						$returnResult['message'] = 'Invalid return request: db-record not found';
					}
				} else {
					$returnResult['message'] = 'Invalid response data.';
				}
			}

			return $returnResult;
		}
	}
