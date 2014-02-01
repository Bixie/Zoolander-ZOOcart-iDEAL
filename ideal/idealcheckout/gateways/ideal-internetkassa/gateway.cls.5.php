<?php

	class Gateway extends GatewayCore
	{
		//bixie
		public $order_id;
		public $order_code;
		public $issuerID;
		public $rPluginParams;
		public $zoo;

		// Load iDEAL settings
		public function __construct($settingsData=array(),$rPluginParams) {
			$this->init($settingsData);
			$this->rPluginParams = $rPluginParams;
		}

		public function doSetup() {
			$sHtml = '';

			// Look for proper GET's en POST's
			if(!isset($this->order_id) || !isset($this->order_code)) {
				$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
			} else {
				//bixie
				$sOrderId = $this->order_id;
				$sOrderCode = $this->order_code;
				$issuerID = $this->issuerID?$this->issuerID:'';

				// Lookup transaction
				if($this->getRecordByOrder($sOrderId,$sOrderCode)) {
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
					} else {
						$oIdeal = new IdealInternetKassa();

						// Account settings
						$oIdeal->setValue('ACQUIRER', $this->aSettings['GATEWAY_NAME']); // Aquirer Name
						$oIdeal->setValue('PSPID', $this->aSettings['PSP_ID']); // Merchant Id
						$oIdeal->setValue('SHA1_IN_KEY', $this->aSettings['SHA_1_IN']); // Secret Hash Key
						$oIdeal->setValue('SHA1_OUT_KEY', $this->aSettings['SHA_1_OUT']); // Secret Hash Key
						$oIdeal->setValue('TEST_MODE', $this->aSettings['TEST_MODE']); // True=TEST, False=LIVE

						// Webshop settings
						// Set return URLs //bixie
						$returnUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal');
						
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
						//WERKTNOGNIETBIJBANKEN!!!
						$oIdeal->setValue('ISSUERID', $issuerID);
						// Order settings
						$oIdeal->setValue('orderID', $this->oRecord['order_id']); // Order ID
						$oIdeal->setValue('COM', $this->oRecord['transaction_description']); // Order Description
						$oIdeal->setValue('amount', intval(round($this->oRecord['transaction_amount'] * 100))); // Order Amount
						
						$sHtml = '' . $oIdeal->createForm(JText::_('PLG_ZOOCART_PAYMENT_IDEAL_BUTTONTEXT')) . '';

					}
				} else {
					$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
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
		public function doReturn() {
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
			
			$returnResult['transaction_date'] = JHtml::_('date','now','Y-m-d H:i:s');

			if(!empty($_GET['order_id']) && !empty($_GET['order_code']) && !empty($_GET['status'])) { // Cancelled
				$sOrderId = $_GET['order_id'];
				$sOrderCode = $_GET['order_code'];
				$sTransactionStatus = 'CANCELLED';

				if($this->getRecordByOrder($sOrderId, $sOrderCode)) {
					$returnResult['valid'] = true;
					$returnResult['transaction_id'] = $this->oRecord['transaction_id'];
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$returnResult['success'] = 1;
						$returnResult['status'] = 'SUCCESS';
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');;
						$returnResult['messageStyle'] = 'uk-alert-success';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					} else {
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_FAILED');
						$returnResult['messageStyle'] = 'uk-alert-danger';
						$returnResult['success'] = 0;
						$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];

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

			if($sTransactionStatus === false) {
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['success'] = 0;
				$returnResult['redirect'] = $this->oRecord['transaction_payment_url'];
			} else {
				$sOrderId = $oIdeal->getValue('ORDERID');
				$sTransactionId = $oIdeal->getValue('PAYID');

				// Lookup record
				if($this->getRecordByOrder($sOrderId)) {
					$returnResult['transaction_id'] = $sTransactionId;
					if(strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
						$returnResult['valid'] = true;
						$returnResult['success'] = 1;
						$returnResult['status'] = 'SUCCESS';
						$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');;
						$returnResult['messageStyle'] = 'uk-alert-success';
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					} else {
						$this->oRecord['transaction_id'] = $sTransactionId;
						$this->oRecord['transaction_status'] = $sTransactionStatus;

						$returnResult['response']['transactionId'] = $sTransactionId;
						$returnResult['response']['transactionStatus'] = $sTransactionStatus;

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

						if(empty($this->oRecord['transaction_log']) == false)
						{
							$this->oRecord['transaction_log'] .= "\n\n";
						}

						$this->oRecord['transaction_log'] .= 'Recieved status ' . $this->oRecord['transaction_status'] . ' for #' . $sTransactionId . ' on ' . date('Y-m-d, H:i:s') . '.';

						// Update transaction
						$sql = "UPDATE `" . $aIdealCheckout['database']['table'] . "` SET `transaction_date` = '" . time() . "', `transaction_id` = '" . idealcheckout_escapeSql($this->oRecord['transaction_id']) . "', `transaction_status` = '" . idealcheckout_escapeSql($this->oRecord['transaction_status']) . "', `transaction_log` = '" . idealcheckout_escapeSql($this->oRecord['transaction_log']) . "' WHERE (`id` = '" . idealcheckout_escapeSql($this->oRecord['id']) . "') LIMIT 1;";
						idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . ', ERROR: ' . idealcheckout_database_error(), __FILE__, __LINE__);

					}
				} else {
					$returnResult['message'] = 'Invalid response data.';
					$returnResult['messageStyle'] = 'uk-alert-danger';
					$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
				}
			}
			return $returnResult;
		}
	}

