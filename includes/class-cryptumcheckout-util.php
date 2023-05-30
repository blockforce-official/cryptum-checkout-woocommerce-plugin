<?php

class CryptumCheckout_Util
{

	public static function is_valid_signature(string $payload, string $signature, string $secret)
	{
		$p = preg_replace('/\s(?=([^"]*"[^"]*")*[^"]*$)/', "", $payload);
		$computedSignature = \base64_encode(\hash_hmac('sha256', $p, $secret, true));
		return $signature == $computedSignature;
	}
}
