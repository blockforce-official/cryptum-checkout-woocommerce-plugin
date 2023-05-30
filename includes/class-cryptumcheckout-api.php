<?php

require_once('class-cryptumcheckout-log.php');

// @codeCoverageIgnoreStart
defined('ABSPATH') or exit;
// @codeCoverageIgnoreEnd

class CryptumCheckout_Api
{
	static $apikey;
	static $environment;

	static function get_cryptum_url($environment)
	{
		return $environment == 'production' ? 'https://api.cryptum.io' : 'https://api-hml.cryptum.io';
	}
	static function get_cryptum_store_url($environment)
	{
		return $environment == 'production' ? 'https://api.cryptum.io/plugins' : 'https://api-hml.cryptum.io/plugins';
	}
	static function get_cryptum_checkout_frontend($environment)
	{
		return $environment == 'production' ? 'https://plugin-checkout.cryptum.io/public/payment-details/' : 'https://plugin-checkout-hml.cryptum.io/public/payment-details/';
	}

	static function set_options($apikey, $environment)
	{
		CryptumCheckout_Api::$apikey = $apikey;
		CryptumCheckout_Api::$environment = $environment;
	}

	static function request($url, $args = array())
	{
		$response = wp_safe_remote_request($url, $args);
		if (is_wp_error($response)) {
			CryptumCheckout_Log::error('CryptumCheckout_Api::request 37', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			return [
				'error' => 'Error',
				'message' => $response->get_error_message()
			];
		}

		$responseObj = $response['response'];
		$responseBody = json_decode($response['body'], true);
		if (isset($responseBody['error']) || (isset($responseObj) && $responseObj['code'] >= 400)) {
			$error_message = isset($responseBody['error']['message']) ? $responseBody['error']['message'] : $responseBody['message'];
			CryptumCheckout_Log::error('CryptumCheckout_Api::request 48', json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			return [
				'error' => 'Error',
				'message' => $error_message
			];
		}
		return $responseBody;
	}

	static function verify_store($storeId)
	{
		$url = CryptumCheckout_Api::get_cryptum_store_url(CryptumCheckout_Api::$environment);
		return CryptumCheckout_Api::request("{$url}/stores/verification", array(
			'body' => json_encode(array(
				'storeId' => $storeId,
				'plugin' => 'checkout',
				'ecommerceType' => 'wordpress'
			)),
			'headers' => array(
				'content-type' => 'application/json',
				'x-api-key' => CryptumCheckout_Api::$apikey
			),
			'data_format' => 'body',
			'method' => 'POST',
			'timeout' => 60
		));
	}
	static function create_order($body)
	{
		$url = CryptumCheckout_Api::get_cryptum_store_url(CryptumCheckout_Api::$environment);
		return CryptumCheckout_Api::request($url . '/orders/checkout', array(
			'body' => json_encode($body),
			'headers' => array(
				'x-api-key' => CryptumCheckout_Api::$apikey,
				'Content-Type' => 'application/json; charset=utf-8'
			),
			'data_format' => 'body',
			'method' => 'POST',
			'timeout' => 60
		));
	}
	static function get_order($orderId)
	{
		$url = CryptumCheckout_Api::get_cryptum_store_url(CryptumCheckout_Api::$environment);
		return CryptumCheckout_Api::request($url . '/orders/' . $orderId, [
			'headers' => [
				'x-api-key' => CryptumCheckout_Api::$apikey
			],
			'method' => 'GET',
			'timeout' => 60
		]);
	}
	static function get_tx_explorer_url($protocol, $hash, $environment)
	{
		switch ($protocol) {
			case 'CELO':
				$middle = $environment == "production" ? 'mainnet' : 'alfajores';
				return "https://explorer.celo.org/$middle/tx/$hash";
			case 'ETHEREUM':
				$middle = $environment == "production" ? 'etherscan' : 'goerli.etherscan';
				return "https://$middle.io/tx/$hash";
			case 'BSC':
				$middle = $environment == "production" ? 'bscscan' : 'testnet.bscscan';
				return "https://$middle.com/tx/$hash";
			case 'AVAXCCHAIN':
				$middle = $environment == "production" ? 'snowtrace' : 'testnet.snowtrace';
				return "https://$middle.io/tx/$hash";
			case 'POLYGON':
				$middle = $environment == "production" ? 'polygonscan' : 'mumbai.polygonscan';
				return "https://$middle.com/tx/$hash";
			default:
				return "";
		}
	}
}
