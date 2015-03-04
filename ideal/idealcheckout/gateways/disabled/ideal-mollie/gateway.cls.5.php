<?php

class Gateway extends GatewayCore {
	//bixie
	public $order_id;
	public $order_code;
	public $rPluginParams;
	public $zoo;

	// Load iDEAL settings
	public function __construct ($settingsData = array(), $rPluginParams) {
		$this->init($settingsData);
		$this->rPluginParams = $rPluginParams;
	}


	// Setup payment
	public function doSetup () {
		global $aIdealCheckout;

		$sHtml = '';

		// Look for proper GET's en POST's
		if (!isset($this->order_id) || !isset($this->order_code)) {
			$sHtml .= '<p>Invalid issuer request.</p>';
		} else {
			//bixie
			$sOrderId = $this->order_id;
			$sOrderCode = $this->order_code;


			// Lookup transaction
			if ($this->getRecordByOrder($sOrderId, $sOrderCode)) {
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$sHtml .= JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_ALREADY_COMPLETE');
				} elseif ((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					header('Location: ' . $this->oRecord['transaction_url']);
					exit;
				} else {
					$oMollie = new iDEAL_Payment($this->aSettings['PARTNER_ID']);

					if (empty($this->aSettings['PROFILE_KEY']) == false) {
						$oMollie->setProfileKey($this->aSettings['PROFILE_KEY']);
					}

					if (!empty($this->aSettings['TEST_MODE'])) {
						$oMollie->setTestmode();
					}

					$aIssuerList = $oMollie->getBanks();
					$sIssuerList = '';
					$actionUrl = $this->zoo->zoocart->payment->getCallbackUrl('ideal', 'html') . '&transaction=1&order_id=' . $sOrderId . '&order_code=' . $sOrderCode;


					if ($aIssuerList === false) {
						$sHtml .= '<code>Er is een fout opgetreden bij het ophalen van de banklijst: ' . $oMollie->getErrorMessage() . '</code>';
					}

					if (empty($this->oRecord['transaction_log']) == false) {
						$this->oRecord['transaction_log'] .= "\n\n";
					}

					$this->oRecord['transaction_log'] .= 'Executing IssuerRequest on ' . date('Y-m-d, H:i:s') . '.';

					$this->save();


					foreach ($aIssuerList as $k => $v) {
						$sIssuerList .= '<option value="' . $k . '">' . htmlspecialchars($v) . '</option>';
					}

					$sHtml .= '
<div class="uk-margin">
	<form action="' . htmlspecialchars($actionUrl) . '" method="post" id="checkout" class="uk-form uk-form-horizontal">
		<div class="uk-form-formrow">
			<label class="uk-form-label">' . JText::_('PLG_ZOOCART_PAYMENT_IDEAL_CHOOSE_BANK') . '</label>
			<div class="uk-form-controls">
				<select name="issuer_id">' . $sIssuerList . '</select>
			</div>
		</div>
		<div class="zoocart-checkout-buttons uk-nbfc">
			<button class="uk-button uk-button-success uk-float-right">
				<i class="uk-icon-shopping-cart uk-margin-small-right"></i>' . JText::_('PLG_ZOOCART_CHECKOUT') . '
			</button>
		</div>
	</form>
</div>
';

				}
			} else {
				$sHtml .= '<p>Invalid issuer request.</p>';
			}
		}

		return $sHtml;
	}


	// Execute payment
	public function doTransaction () {
		$sHtml = '';

		// Look for proper GET's en POST's
		if (empty($_POST['issuer_id']) || empty($_GET['order_id']) || empty($_GET['order_code'])) {
			$sHtml .= '<p>Invalid transaction request.</p>';
		} else {
			$sIssuerId = $_POST['issuer_id'];

			// Lookup transaction
			if ($this->getRecordByOrder()) {
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$sHtml .= '<p>Transaction already completed</p>';
				} elseif ((strcmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					header('Location: ' . $this->oRecord['transaction_url']);
					exit;
				} else {
					$oMollie = new iDEAL_Payment($this->aSettings['PARTNER_ID']);

					if (empty($this->aSettings['PROFILE_KEY']) == false) {
						$oMollie->setProfileKey($this->aSettings['PROFILE_KEY']);
					}

					if (!empty($this->aSettings['TEST_MODE'])) {
						$oMollie->setTestmode();
					}

					$oPayment = $oMollie->createPayment($sIssuerId, round($this->oRecord['transaction_amount'] * 100), $this->oRecord['transaction_description'], idealcheckout_getRootUrl() . 'return.php?order_id=' . htmlspecialchars($this->oRecord['order_id']) . '&order_code=' . htmlspecialchars($this->oRecord['order_code']), idealcheckout_getRootUrl() . 'report.php?order_id=' . htmlspecialchars($this->oRecord['order_id']) . '&order_code=' . htmlspecialchars($this->oRecord['order_code']));

					if ($oPayment == false) {
						idealcheckout_output('<code>De betaling kon niet aangemaakt worden.<br><br><br><br><b>Foutmelding:</b><br><br>' . $oMollie->getErrorMessage() . '</code>');
					}

					$sTransactionId = $oMollie->getTransactionId();
					$sTransactionUrl = $oMollie->getBankURL();

					if (empty($this->oRecord['transaction_log']) == false) {
						$this->oRecord['transaction_log'] .= "\n\n";
					}

					$this->oRecord['transaction_log'] .= 'Executing TransactionRequest on ' . date('Y-m-d, H:i:s') . '. Recieved: ' . $sTransactionId;
					$this->oRecord['transaction_id'] = $sTransactionId;
					$this->oRecord['transaction_url'] = $sTransactionUrl;
					$this->oRecord['transaction_status'] = 'OPEN';
					$this->oRecord['transaction_date'] = time();

					$this->save();

					// idealcheckout_die('<a href="' . htmlentities($sTransactionUrl) . '">' . htmlentities($sTransactionUrl) . '</a>', __FILE__, __LINE__);
					header('Location: ' . $sTransactionUrl);
					exit;
				}
			} else {
				$sHtml .= '<p>Invalid transaction request.</p>';
			}
		}

		idealcheckout_output($sHtml);
	}


	// Catch return
	public function doReturn () {
		$returnResult = array(
			'valid' => false,
			'order_id' => 0,
			'transaction_id' => '',
			'transaction_date' => '',
			'status' => '',
			'message' => '',
			'success' => 0,
			'redirect' => false,
			'messageStyle' => '',
			'formHtml' => ''
		);
		if (empty($_GET['order_id']) || empty($_GET['order_code'])) {
			$returnResult['valid'] = false;
			$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
			$returnResult['messageStyle'] = 'uk-alert-danger';
			$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
		} else {
			// Lookup transaction
			if ($this->getRecordByOrder()) {
				// Transaction already finished
				if (strcmp($this->oRecord['transaction_status'], 'SUCCESS') === 0) {
					$returnResult['valid'] = true;
					$returnResult['success'] = 1;
					$returnResult['status'] = 'SUCCESS';
					$returnResult['debug'] .= $returnResult['message'] = 'al afgehandeld';
					if ($this->oRecord['transaction_success_url']) {
						$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
					}
					return $returnResult;
				} elseif ((strcasecmp($this->oRecord['transaction_status'], 'OPEN') === 0) && !empty($this->oRecord['transaction_url'])) {
					$returnResult['valid'] = true;
					$returnResult['success'] = -1;
					$returnResult['status'] = 'PENDING';
					$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_PENDING');;
					$returnResult['messageStyle'] = 'uk-alert-warning';
					$returnResult['redirect'] = $this->oRecord['transaction_url'];
				} else {

					switch ($this->oRecord['transaction_status']) {
						case 'SUCCESS':
							$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_TRANS_SUCCESS');
							$returnResult['messageStyle'] = 'uk-alert-success';
							$returnResult['success'] = 1;
							$returnResult['redirect'] = $this->oRecord['transaction_success_url'];
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
				$returnResult['message'] = JText::_('PLG_ZOOCART_PAYMENT_IDEAL_INVALID_REQUEST');
				$returnResult['messageStyle'] = 'uk-alert-danger';
				$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
			}
		}

		return $returnResult;
	}


	// Catch report
	public function doReport () {
		$returnResult = array(
			'valid' => false,
			'transaction_id' => '',
			'status' => '',
			'message' => '',
			'success' => 0
		);

		if (empty($_GET['transaction_id']) || empty($_GET['order_id']) || empty($_GET['order_code'])) {
			$returnResult['valid'] = false;
			$returnResult['message'] = 'Invalid return request.';
			$returnResult['redirect'] = $this->oRecord['transaction_failure_url'];
		} else {
			$sTransactionId = $_GET['transaction_id'];
			$returnResult['transaction'] = $sTransactionId;

			// Lookup record
			if ($this->getRecordByOrder()) {
				// Check status
				$returnResult['valid'] = true;
				$oMollie = new iDEAL_Payment($this->aSettings['PARTNER_ID']);

				if (empty($this->aSettings['PROFILE_KEY']) == false) {
					$oMollie->setProfileKey($this->aSettings['PROFILE_KEY']);
				}

				if (!empty($this->aSettings['TEST_MODE'])) {
					$oMollie->setTestmode();
				}


				if ($oMollie->checkPayment($sTransactionId)) {
					if ($oMollie->getPaidStatus() == true) {
						$this->oRecord['transaction_status'] = 'SUCCESS';
						$returnResult['success'] = true;
					} else {
						$this->oRecord['transaction_status'] = 'CANCELLED';
					}
				} else {
					$this->oRecord['transaction_status'] = 'FAILURE';
					// idealcheckout_output('<code>' . var_export($oMollie->getErrors(), true) . '</code>');
				}

				if (empty($this->oRecord['transaction_log']) == false) {
					$this->oRecord['transaction_log'] .= "\n\n";
				}

				$this->oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $sTransactionId . '. Recieved: ' . $this->oRecord['transaction_status'];

				$this->save();

				$returnResult['status'] = $this->oRecord['transaction_status'];
				$returnResult['message'] = 'De transactie status is bijgewerkt.';
			} else {
				$returnResult['message'] = 'Invalid report request.';
			}
		}

		return $returnResult;
	}


	// Validate all open transactions
	public function doValidate () {
		global $aIdealCheckout;

		$sql = "SELECT * FROM `" . $aIdealCheckout['database']['table'] . "` WHERE (`transaction_status` = 'OPEN') AND (`gateway_code` = '" . idealcheckout_escapeSql($aIdealCheckout['record']['gateway_code']) . "') AND " . (empty($aIdealCheckout['record']['store_code']) ? "((`store_code` IS NULL) OR (`store_code` = ''))" : "(`store_code` = '" . idealcheckout_escapeSql($aIdealCheckout['record']['store_code']) . "')") . " AND ((`transaction_success_url` IS NULL) OR (`transaction_success_url` = '') OR (`transaction_success_url` LIKE '" . idealcheckout_escapeSql(idealcheckout_getRootUrl(1)) . "%')) ORDER BY `id` ASC;";
		$oRecordset = idealcheckout_database_query($sql) or idealcheckout_die('QUERY: ' . $sql . "\n\n" . 'ERROR: ' . idealcheckout_database_error() . '', __FILE__, __LINE__);

		$sHtml = '<b>Controle van openstaande transacties.</b><br>';

		if (idealcheckout_database_num_rows($oRecordset)) {
			while ($oRecord = idealcheckout_database_fetch_assoc($oRecordset)) {
				// Execute status request
				$oMollie = new iDEAL_Payment($this->aSettings['PARTNER_ID']);

				if (empty($this->aSettings['PROFILE_KEY']) == false) {
					$oMollie->setProfileKey($this->aSettings['PROFILE_KEY']);
				}

				if (!empty($this->aSettings['TEST_MODE'])) {
					$oMollie->setTestmode();
				}


				if ($oMollie->checkPayment($oRecord['transaction_id'])) {
					if ($oMollie->getPaidStatus() == true) {
						$oRecord['transaction_status'] = 'SUCCESS';
					} else {
						$oRecord['transaction_status'] = 'CANCELLED';
					}
				} else {
					$oRecord['transaction_status'] = 'FAILURE';
				}

				if (empty($oRecord['transaction_log']) == false) {
					$oRecord['transaction_log'] .= "\n\n";
				}

				$oRecord['transaction_log'] .= 'Executing StatusRequest on ' . date('Y-m-d, H:i:s') . ' for #' . $oRecord['transaction_id'] . '. Recieved: ' . $oRecord['transaction_status'];

				$this->save($oRecord);


				// Add to body
				$sHtml .= '<br>#' . $oRecord['transaction_id'] . ' : ' . $oRecord['transaction_status'];


				// Handle status change
				if (function_exists('idealcheckout_update_order_status')) {
					idealcheckout_update_order_status($oRecord, 'doValidate');
				}
			}

			$sHtml .= '<br><br><br>Alle openstaande transacties zijn bijgewerkt.';
		} else {
			$sHtml .= '<br>Er zijn geen openstaande transacties gevonden.';
		}

		idealcheckout_output('<p>' . $sHtml . '</p><p>&nbsp;</p><p><input type="button" value="Venster sluiten" onclick="javascript: window.close();"></p>');
	}
}
