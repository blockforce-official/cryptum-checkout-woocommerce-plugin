<?php

class CryptumCheckoutUtils
{
	private static function _log($message, $level = 'info')
	{
		wc_get_logger()->log($level, $message, array('source' => 'cryptumcheckout'));
	}

	static function get_cryptum_url($environment)
	{
		return $environment == 'production' ? 'https://api.cryptum.io' : 'https://api-dev.cryptum.io';
	}

	static function request($url, $args = [])
	{
		$response = wp_safe_remote_request($url, $args);
		if (is_wp_error($response)) {
			CryptumCheckoutUtils::_log(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			return [
				'error' => 'Error',
				'message' => $response->get_error_message()
			];
		}
		$responseBody = json_decode($response['body'], true);
		if (isset($responseBody['error'])) {
			$error_message = $responseBody['error']['message'];
			if (!isset($error_message)) {
				$error_message = $responseBody['message'];
			}
			CryptumCheckoutUtils::_log(json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
			return [
				'error' => 'Error',
				'message' => $error_message
			];
		}
		return $responseBody;
	}
}
