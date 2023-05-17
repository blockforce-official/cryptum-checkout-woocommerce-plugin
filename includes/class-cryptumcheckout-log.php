<?php

class CryptumCheckout_Log
{
	public static function log($prefix, $message, $level = 'info')
	{
		$log = $message;
		if (is_array($message) || is_object($message)) {
			$log = print_r($message, true);
		}
		if (empty($prefix)) {
			$prefix = '[Cryptum Checkout Log]';
		}
		$log = $prefix . ': ' . $log;
		if (function_exists('wc_get_logger')) {
			wc_get_logger()->log($level,  $log, array('source' => 'cryptum-checkout'));
		}
		error_log($log);
	}
	public static function info($prefix, $message)
	{
		self::log('[Cryptum Checkout Info]' . $prefix, $message, 'info');
	}
	public static function error($prefix, $message)
	{
		self::log('[Cryptum Checkout Error]' . $prefix, $message, 'error');
	}
}
