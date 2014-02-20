<?php

	class Gateway extends GatewayCore {
		
		//bixie
		public $order_id;
		public $order_code;
		public $rPluginParams;
		public $zoo;
		
		// Load iDEAL settings
		public function __construct($settingsData=array(),$rPluginParams) {
			$this->init($settingsData);
			$this->rPluginParams = $rPluginParams;
		}
		
		// Setup payment
		public function doSetup()
		{
			global $aIdealCheckout;

			$sHtml = '';

			// Look for proper GET's en POST's
			if (!isset($this->order_id) || !isset($this->order_code)) //bixie
			{
				$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
			}
			else
			{
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;

				// Lookup transaction
				if ($this->getRecordByOrder($sOrderId,$sOrderCode)) //bixie
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
					}
					elseif((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url']))
					{
						header('Location: ' . $this->oRecord['transaction_url']);
						exit;
					}
					else
					{
						$oIssuerRequest = new IssuerRequest();
						$oIssuerRequest->setSecurePath($this->aSettings['CERTIFICATE_PATH']);
						$oIssuerRequest->setCachePath($this->aSettings['TEMP_PATH']);
						$oIssuerRequest->setPrivateKey($this->aSettings['PRIVATE_KEY_PASS'], $this->aSettings['PRIVATE_KEY_FILE'], $this->aSettings['PRIVATE_CERTIFICATE_FILE']);
						$oIssuerRequest->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
						$oIssuerRequest->setAquirer($this->aSettings['GATEWAY_NAME'], $this->aSettings['TEST_MODE']);

						$actionUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal','html').'&transaction=1&order_id=' . $sOrderId . '&order_code=' . $sOrderCode;
						
						$aIssuerList = $oIssuerRequest->doRequest();
						$sIssuerList = '';

						if($oIssuerRequest->hasErrors())
						{
							if($this->aSettings['TEST_MODE'])
							{
								return '<pre>' . var_export($oIssuerRequest->getErrors(), true) . '</pre>';
							}
							else
							{
								$this->oRecord['transaction_status'] = 'FAILURE';

								if(empty($this->oRecord['transaction_log']) == false)
								{
									$this->oRecord['transaction_log'] .= "\n\n";
								}

								$this->oRecord['transaction_log'] .= 'Executing IssuerRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ERROR' . "\n" . var_export($oIssuerRequest->getErrors(), true);
								$this->save();

								$sHtml .= '<p>Door een technische storing kunnen er momenteel helaas geen betalingen via iDEAL worden verwerkt. Onze excuses voor het ongemak.</p>';
								
								if($this->oRecord['transaction_payment_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_payment_url']) . '">kies een andere betaalmethode</a></p>';
								}
								elseif($this->oRecord['transaction_failure_url'])
								{
									$sHtml .= '<p><a href="' . htmlentities($this->oRecord['transaction_failure_url']) . '">terug naar de website</a></p>';
								}
								
								return $sHtml;
							}
						}

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing IssuerRequest on ' . date('Y-m-d, H:i:s') . '.';

						$this->save();


						foreach($aIssuerList as $k => $v)
						{
							$sIssuerList .= '<option value="' . $k . '">' . htmlspecialchars($v) . '</option>';
						}

						$sHtml .= '
<form action="' . htmlspecialchars($actionUrl) . '" method="post" id="checkout" class="uk-form">
	<fieldset>
		<legend>'.JText::_('PLG_ZOOCART_PAYMENT_IDEAL_CHOOSE_BANK').'</legend>
		<div class="uk-form-formrow"><select name="issuer_id">' . $sIssuerList . '</select></div>
		<div class="zoocart-checkout-buttons uk-nbfc">
			<button class="uk-button uk-button-success uk-float-right">
				<i class="uk-icon-shopping-cart"></i>&nbsp;&nbsp;&nbsp;'.JText::_('PLG_ZOOCART_CHECKOUT').'
			</button>
		</div>
		
	</fieldset>
</form>';
					}
				}
				else
				{
					$sHtml .= '<p>Invalid issuer request.</p>';
				}
			}

			return $sHtml;
		}


		// Execute payment
		public function doTransaction()
		{
			$returnResult = array(
				'valid'=>true,
				'order_id'=>0,
				'transaction_id'=>'',
				'transaction_date'=>'',
				'message'=>'',
				'success'=>-1,
				'redirect'=>false,
				'messageStyle'=>'',
				'formHtml'=>''
			);
			
			$returnResult['message'] = '';

			if(empty($_POST['issuer_id']))
			{
				if(!empty($_GET['issuer_id']))
				{
					$_POST['issuer_id'] = $_GET['issuer_id'];
				}
			}

			// Look for proper GET's en POST's
			if(empty($_POST['issuer_id']) || empty($_GET['order_id']) || empty($_GET['order_code']))
			{
				$returnResult['message'] .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
			}
			else
			{
				$sIssuerId = $_POST['issuer_id'];
				$sOrderId = $_GET['order_id'];
				$sOrderCode = $_GET['order_code'];
				
				$returnResult['order_id'] = $sOrderId;
				// Lookup transaction
				if($this->getRecordByOrder())
				{
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0)
					{
						$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
						$returnResult['transaction_date'] = JHtml::_('date',$this->oRecord['transaction_date'],'Y-m-d H:i:s');;
						$returnResult['message'] .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
						$returnResult['messageStyle'] = 'uk-alert-success';
					}
					elseif((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url']))
					{
						$returnResult['message'] .= '<p>'.JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BEING_REDIRECTED').'</p>';
						$returnResult['formHtml'] = '<script>setTimeout(function(){document.location.href=\''.$this->oRecord['transaction_url'].'\'},500)();</script>';
						return $returnResult;
					}
					else
					{
						$oTransactionRequest = new TransactionRequest();
						$oTransactionRequest->setSecurePath($this->aSettings['CERTIFICATE_PATH']);
						$oTransactionRequest->setCachePath($this->aSettings['TEMP_PATH']);
						$oTransactionRequest->setPrivateKey($this->aSettings['PRIVATE_KEY_PASS'], $this->aSettings['PRIVATE_KEY_FILE'], $this->aSettings['PRIVATE_CERTIFICATE_FILE']);
						$oTransactionRequest->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
						$oTransactionRequest->setAquirer($this->aSettings['GATEWAY_NAME'], $this->aSettings['TEST_MODE']);

						$oTransactionRequest->setOrderId($this->oRecord['order_id']);
						$oTransactionRequest->setOrderDescription($this->oRecord['transaction_description']);
						$oTransactionRequest->setOrderAmount($this->oRecord['transaction_amount']);

						$oTransactionRequest->setIssuerId($sIssuerId);
						$oTransactionRequest->setEntranceCode($this->oRecord['transaction_code']);
						$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal');
						$oTransactionRequest->setReturnUrl(htmlentities($returnUrl));


						// Find TransactionID
						$sTransactionId = $oTransactionRequest->doRequest();

						if($oTransactionRequest->hasErrors())
						{
							if($this->aSettings['TEST_MODE'])
							{
								$returnResult['formHtml'] = '<pre>' . var_export($oTransactionRequest->getErrors(), true) . '</pre>';
								return $returnResult;
							}
							else
							{
								$this->oRecord['transaction_status'] = 'FAILURE';

								if(empty($this->oRecord['transaction_log']) == false)
								{
									$this->oRecord['transaction_log'] .= "\n\n";
								}

								$this->oRecord['transaction_log'] .= 'Executing TransactionRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ERROR' . "\n" . var_export($oTransactionRequest->getErrors(), true);
								$this->save();

								$returnResult['message'] = '<p>Door een technische storing kunnen er momenteel helaas geen betalingen via iDEAL worden verwerkt. Onze excuses voor het ongemak.</p>';
								
								if($this->oRecord['transaction_payment_url'])
								{
									$returnResult['message'] .= '<p><a href="' . htmlentities($this->oRecord['transaction_payment_url']) . '">kies een andere betaalmethode</a></p>';
								}
								elseif($this->oRecord['transaction_failure_url'])
								{
									$returnResult['message'] .= '<p><a href="' . htmlentities($this->oRecord['transaction_failure_url']) . '">terug naar de website</a></p>';
								}

								$returnResult['message'] .= '<!--

' . var_export($oTransactionRequest->getErrors(), true) . '

-->';
								$returnResult['messageStyle'] = 'uk-alert-danger';
								
								return $returnResult;
							}
						}

						$sTransactionUrl = $oTransactionRequest->getTransactionUrl();

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing TransactionRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ' . $sTransactionId;
						$this->oRecord['transaction_id'] = $sTransactionId;
						$this->oRecord['transaction_url'] = $sTransactionUrl;
						$this->oRecord['transaction_status'] = 'OPEN';
						$this->oRecord['transaction_date'] = time();

						$this->save();
						
						$returnResult['transaction_id'] = $sTransactionId;
						$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');;
						$returnResult['message'] .= '<p>'.JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BEING_REDIRECTED').'</p>';
						$returnResult['formHtml'] = '<a href="'.$this->oRecord['transaction_url'].'">'.JText::_('PLG_ZOOCART_PAYMENT_IDEAL_CLICKTOPAY').'</a>';
						$returnResult['formHtml'] .= '<script>setTimeout(function(){document.location.href=\''.$this->oRecord['transaction_url'].'\'},500)();</script>';
					}
				}
				else
				{
					$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
					$returnResult['messageStyle'] = 'uk-alert-danger';
				}
			}

			return $returnResult;
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
		public function doReturn() {
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
			

			if(empty($_GET['trxid']) || empty($_GET['ec']))	{
				$returnResult['valid'] = false;
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			} else {
				$sTransactionId = $_GET['trxid'];
				$sTransactionCode = $_GET['ec'];

				// Lookup record
				if($this->getRecordByTransaction()) {
					$returnResult['valid'] = true;
					$returnResult['order_id'] = $this->oRecord['order_id'];
					// Transaction already finished
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$returnResult['success'] = 1;
						$returnResult['status'] = 'SUCCESS';
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');;
						$returnResult['messageStyle'] = 'uk-alert-success';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					} else { 
						// Check status
						$oStatusRequest = new StatusRequest();
						$oStatusRequest->setSecurePath($this->aSettings['CERTIFICATE_PATH']);
						$oStatusRequest->setCachePath($this->aSettings['TEMP_PATH']);
						$oStatusRequest->setPrivateKey($this->aSettings['PRIVATE_KEY_PASS'], $this->aSettings['PRIVATE_KEY_FILE'], $this->aSettings['PRIVATE_CERTIFICATE_FILE']);
						$oStatusRequest->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
						$oStatusRequest->setAquirer($this->aSettings['GATEWAY_NAME'], $this->aSettings['TEST_MODE']);

						$oStatusRequest->setTransactionId($sTransactionId);

						$this->oRecord['transaction_status'] = $oStatusRequest->doRequest();
						//valid new request
						$returnResult['valid'] = true;
						$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
						$returnResult['status'] = $this->oRecord['transaction_status'];
						$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');
						//return errors
						if($oStatusRequest->hasErrors()) {
							$returnResult['success'] = 0;
							if($this->aSettings['TEST_MODE']) {
								$returnResult['message'] = '<pre>' . var_export($oStatusRequest->getErrors(), true) . '</pre>';
								return $returnResult;
							} else {
								$this->oRecord['transaction_status'] = 'FAILURE';

								if(empty($this->oRecord['transaction_log']) == false)
								{
									$this->oRecord['transaction_log'] .= "\n\n";
								}

								$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ERROR' . "\n" . var_export($oStatusRequest->getErrors(), true);
								$this->save();

								$sHtml = '<div>Door een technische storing kunnen er momenteel helaas geen betalingen via iDEAL worden verwerkt. Onze excuses voor het ongemak.</div>';
								
								$sHtml .= '<!--

' . var_export($oStatusRequest->getErrors(), true) . '

-->';
								$returnResult['message'] = $sHtml;
								return $returnResult;
							}
						}
						//log valid
						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $this->oRecord['transaction_id'] . '. Recieved: ' . $this->oRecord['transaction_status'];

						$this->save();

						//get status
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
					}
				} else {
					$returnResult['valid'] = false;
					$returnResult['message'] = 'Invalid return request.';
					$returnResult['messageStyle'] = 'uk-alert-danger';
					$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
				}
			}
			return $returnResult;
		}


		// Catch report
		public function doReport()
		{
			idealcheckout_output('Invalid report request.');
		}


		// Validate all open transactions
		// TODO make this accessable from admin
		public function doValidate()
		{
			global $aIdealCheckout; 

			$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_status` = 'OPEN') AND (`gateway_code` = '" . idealcheckout_escapeSql($aIdealCheckout['record']['gateway_code']) . "') AND " . (empty($aIdealCheckout['record']['store_code']) ? "((`store_code` IS NULL) OR (`store_code` = ''))" : "(`store_code` = '" . idealcheckout_escapeSql($aIdealCheckout['record']['store_code']) . "')") . " AND ((`transaction_success_url` IS NULL) OR (`transaction_success_url` = '') OR (`transaction_success_url` LIKE '" . idealcheckout_escapeSql(idealcheckout_getRootUrl(1)) . "%')) ORDER BY `id` ASC;";
			$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

			$sHtml = '<b>Controle van openstaande transacties.</b><br>';

			if(idealcheckout_database_num_rows($oRecordset))
			{
				while($oRecord = idealcheckout_database_fetch_assoc($oRecordset))
				{
					// Execute status request
					$oStatusRequest = new StatusRequest();
					$oStatusRequest->setSecurePath($this->aSettings['CERTIFICATE_PATH']);
					$oStatusRequest->setCachePath($this->aSettings['TEMP_PATH']);
					$oStatusRequest->setPrivateKey($this->aSettings['PRIVATE_KEY_PASS'], $this->aSettings['PRIVATE_KEY_FILE'], $this->aSettings['PRIVATE_CERTIFICATE_FILE']);
					$oStatusRequest->setMerchant($this->aSettings['MERCHANT_ID'], $this->aSettings['SUB_ID']);
					$oStatusRequest->setAquirer($this->aSettings['GATEWAY_NAME'], $this->aSettings['TEST_MODE']);

					$oStatusRequest->setTransactionId($oRecord['transaction_id']);

					$oRecord['transaction_status'] = $oStatusRequest->doRequest();

					if(empty($oRecord['transaction_log']) == false)
					{
						$oRecord['transaction_log'] .= "\n\n";
					}

					if($oStatusRequest->hasErrors())
					{
						$oRecord['transaction_status'] = 'FAILURE';
						$oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ERROR' . "\n" . var_export($oStatusRequest->getErrors(), true);
					}
					else
					{
						$oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $oRecord['transaction_id'] . '. Recieved: ' . $oRecord['transaction_status'];
					}

					$this->save($oRecord);


					// Add to body
					$sHtml .= '<br>#' . $oRecord['transaction_id'] . ' : ' . $oRecord['transaction_status'];


					// Handle status change
					if(function_exists('idealcheckout_update_order_status'))
					{
						idealcheckout_update_order_status($oRecord, 'doValidate');
					}
				}

				$sHtml .= '<br><br><br>Alle openstaande transacties zijn bijgewerkt.';
			}
			else
			{
				$sHtml .= '<br>Er zijn geen openstaande transacties gevonden.';
			}

			return $sHtml;
		}
	}

