<?php
/**
* @package		ZOOcart
* @author		ZOOlanders http://www.zoolanders.com
* @author		Matthijs Alles - Bixie
* @copyright	Copyright (C) JOOlanders, SL
* @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 only
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

class Idealcheckout {

	public $id;

	public $order_id;
	
	public $order_code;

	public $order_params;

	public $store_code;

	public $gateway_code;

	public $language_code;

	public $country_code;

	public $currency_code;

	public $transaction_id;

	public $transaction_code;

	public $transaction_params;

	public $transaction_date;

	public $transaction_amount;

	public $transaction_description;

	public $transaction_status;

	public $transaction_url;
	
	public $transaction_payment_url;
	
	public $transaction_success_url;
	
	public $transaction_pending_url;
	
	public $transaction_failure_url;

	public $transaction_log;

	protected $_data = null;

	public function getData() {

		if (!$this->_data) {
			$zoo = App::getInstance('zoo');
			$this->_data = $zoo->data->create($this->data);
		}

		return $this->_data;
	}

}