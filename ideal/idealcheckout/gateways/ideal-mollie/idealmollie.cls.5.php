<?php


class iDEAL_Payment {


	protected $api_key = '';
	/**
	 * @var Mollie_API_Client
	 */
	protected $mollie;

	public function __construct () {

		require_once __DIR__ . "/Mollie/API/Autoloader.php";

		$this->mollie = new Mollie_API_Client;

	}

	/**
	 * @param $api_key
	 * @throws Mollie_API_Exception
	 */
	public function setApiKey ($api_key) {
		$this->api_key = $api_key;
		$this->mollie->setApiKey($this->api_key);
	}

	// Haal de lijst van beschikbare banken
	public function getBanks () {
		return $this->mollie->issuers->all();
	}

	/**
	 * Zet een betaling klaar bij de bank en maak de betalings URL beschikbaar
	 * @param      $order_id
	 * @param      $order_code
	 * @param      $amount
	 * @param      $description
	 * @param      $return_url
	 * @param null $issuer
	 * @return Mollie_API_Object_Payment
	 */
	public function createPayment ($order_id, $order_code, $amount, $description, $return_url, $issuer = null) {
		return $this->mollie->payments->create(array(
			"amount"       => $amount,
			"method"       => Mollie_API_Object_Method::IDEAL,
			"description"  => $description,
			"redirectUrl"  => $return_url,
			"metadata"     => array(
				"order_id" => $order_id,
				"order_code" => $order_code
			),
			"issuer"       => $issuer
		));
	}

	/**
	 * @param $transaction_id
	 * @return Mollie_API_Object_Payment
	 * @throws Mollie_API_Exception
	 */
	public function checkPayment ($transaction_id) {

		return $this->mollie->payments->get($transaction_id);

	}


}
