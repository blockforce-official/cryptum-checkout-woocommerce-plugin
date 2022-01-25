<?php

/**
 * Plugin Name: Cryptum Checkout
 * Plugin URI: https://github.com/blockforce-official/cryptum-checkout-woocommerce-plugin
 * Description: Cryptum Checkout Payment Gateway for Woocommerce
 * Version: 1.0.0
 * Author: Blockforce
 * Author URI: https://blockforce.in
 * Text Domain: woocommerce
 * Domain Path: /i18n/languages/
 * Requires at least: 5.5
 * Requires PHP: 7.0
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action('admin_notices', function () {
		echo '<div id="setting-error-settings_updated" class="notice notice-error">
			<p>' . __("Cryptum Checkout Plugin needs Woocommerce enabled to work correctly. Please install and/or enable Woocommerce plugin", 'cryptumcheckout-wc-gateway') . '</p>
		</div>';
	});
	return;
}

require_once 'utils.php';

function cryptumcheckout_gateway_init()
{
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class CryptumCheckout_WC_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{

			$this->id                 = 'cryptumcheckout_gateway';
			$this->icon               = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/assets/images/cryptum-checkout-logo.png';
			$this->has_fields         = false;
			$this->method_title       = __('Cryptum Checkout', 'cryptumcheckout-wc-gateway');
			$this->method_description = __('Connects your WooCommerce store to the Cryptum Checkout Payment Gateway.', 'woocommerce');
			$this->description		  = __('Pay with Cryptum, you will be redirected to Cryptum Checkout to finish your order payment');

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title 					= $this->get_option('title');
			$this->storeId 			  		= $this->get_option('storeId');
			$this->apikey 					= $this->get_option('apikey');
			$this->productionEnvironment	= 'production' == $this->get_option('environment');
			$this->backendUrl  				= CryptumCheckoutUtils::get_cryptum_url($this->get_option('environment')) . '/plugins';
			$this->storeMarkupPercentage	= $this->get_option('storeMarkupPercentage');
			$this->storeDiscountPercentage	= $this->get_option('storeDiscountPercentage');
			$this->frontendUrl				= $this->productionEnvironment ? 'https://plugin-checkout.cryptum.io/public/payment-details.html' : 'https://plugin-checkout-dev.cryptum.io/public/payment-details.html';

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'callback_payment_handler'));
		}

		public function payment_fields()
		{
			if (!$this->description) {
				$desc = '';
			} else {
				$desc = wpautop(wptexturize($this->description));
			}
			$html = "<div class=''>$desc</div>";
			echo $html;
		}

		public function get_icon()
		{
			$icon_html =
				'<img src="' . plugins_url('assets/images/cryptum-checkout-logo.png', __FILE__) .
				'" style="padding: 0; margin-top: -10px; max-height:55px;" />';
			return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 * This is where the Store will enter all of their Cryptum Checkout Settings.
		 */
		public function init_form_fields()
		{

			$this->form_fields = apply_filters('cryptumcheckout_form_fields', array(

				'enabled' => array(
					'title'   => __('Enable/Disable', 'cryptumcheckout-wc-gateway'),
					'type'    => 'checkbox',
					'label'   => __('Enable Cryptum E-commerce Checkout', 'cryptumcheckout-wc-gateway'),
				),
				'title' => array(
					'title' => __('Title', 'woothemes'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
					'default' => __('Cryptum Checkout', 'woothemes')
				),
				'environment' => array(
					'title' => __('Environment', 'cryptumcheckout-wc-gateway'),
					'type' => 'select',
					'description' => __('Choose your environment. The Test environment should be used for testing only.', 'cryptumcheckout-wc-gateway'),
					'default' => 'production',
					'options' => array(
						'production' => __('Production', 'cryptumcheckout-wc-gateway'),
						'test' => __('Test', 'cryptumcheckout-wc-gateway'),
					),
				),
				'storeId' => array(
					'title'       => __('Store ID', 'cryptumcheckout-wc-gateway'),
					'type'        => 'text',
					'description' => __('Enter your Cryptum Checkout Store ID (Automatically Generated in Cryptum Dashboard, Woocommerce Section.)', 'cryptumcheckout-wc-gateway'),
					'default'     => __('', 'cryptumcheckout-wc-gateway'),
				),
				'apikey' => array(
					'title'       => __('API Key', 'cryptumcheckout-wc-gateway'),
					'type'        => 'text',
					'description' => __('Enter your Cryptum API Key (Generated in Cryptum Dashboard, API Keys Section)'),
					'default'     => __('', 'cryptumcheckout-wc-gateway'),
				),

				'storeMarkupPercentage' => array(
					'title'       => __('Store Markup Percentage', 'cryptumcheckout-wc-gateway'),
					'type'        => 'text',
					'description' => __('Enter your percentage markup value', 'cryptumcheckout-wc-gateway'),
					'default'     => __('0', 'cryptumcheckout-wc-gateway'),
				),

				'storeDiscountPercentage' => array(
					'title'       => __('Store Discount Percentage', 'cryptumcheckout-wc-gateway'),
					'type'        => 'text',
					'description' => __('Enter your percentage discount value'),
					'default'     => __('0', 'cryptumcheckout-wc-gateway'),
				),
			));
		}

		private function coalesce_string($str1, $str2)
		{
			if (isset($str1) and $str1 != '') {
				return $str1;
			}
			return $str2;
		}

		/**
		 * Process the payment and return the result
		 * This will put the order into on-hold status, reduce inventory levels, and empty customer shopping cart.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment($order_id)
		{

			$order = wc_get_order($order_id);

			$headers = array(
				'x-api-key' => $this->apikey,
				'Content-Type' => 'application/json; charset=utf-8'
			);

			$currency = $order->get_currency();
			if ($currency !== 'USD') {
				wc_add_notice(
					__('Unsupported currency: ' . $currency . '(Only USD is supported for now)', 'cryptumcheckout-wc-gateway'),
					'error'
				);
				return array('result' => 'error', 'redirect' => '');
			}

			$body = array(
				'store' => $this->storeId,
				'ecommerce' => 'wordpress',
				'orderId' => '' . $order->get_id(),
				'orderTotal' => $order->get_total(),
				'orderCurrency' => $currency,
				'cancelReturnUrl' => wp_specialchars_decode($order->get_cancel_order_url()),
				'successReturnUrl' => wp_specialchars_decode($this->get_return_url($order)),
				'callbackUrl' => WC()->api_request_url(get_class($this)),
				'deliveryInfo' => array(
					'firstName' => $this->coalesce_string($order->get_shipping_first_name(), $order->get_billing_first_name()),
					'lastName' => $this->coalesce_string($order->get_shipping_last_name(), $order->get_billing_last_name()),
					'email' => $order->get_billing_email(),
					'city' => $this->coalesce_string($order->get_shipping_city(), $order->get_billing_city()),
					'country' => $this->coalesce_string($order->get_shipping_country(), $order->get_billing_country()),
					'zip' => $this->coalesce_string($order->get_shipping_postcode(), $order->get_billing_postcode()),
					'address' => $this->coalesce_string($order->get_shipping_address_1(), $order->get_billing_address_1()),
					'complement' => $this->coalesce_string($order->get_shipping_address_2(), $order->get_billing_address_2()),
					'state' => $this->coalesce_string($order->get_shipping_state(), $order->get_billing_state())
				)
			);

			$response = CryptumCheckoutUtils::request($this->backendUrl . '/orders/checkout', array(
				'body' => json_encode($body),
				'headers' => $headers,
				'data_format' => 'body',
				'method' => 'POST',
				'timeout' => 60
			));
			if (isset($response['error'])) {
				$error_message = $response['message'];
				wc_add_notice(__('Error processing Cryptum Checkout', 'cryptumcheckout-wc-gateway') . ': ' . $error_message, 'error');
				return array('result' => 'error', 'redirect' => '');
			}

			$order->update_status('pending', __('Pending Cryptum Checkout payment', 'cryptumcheckout-wc-gateway'));

			// Reduce stock levels
			wc_reduce_stock_levels($order_id);

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_request_url($order, $response)
			);
		}

		function get_request_url($order, $createOrderResponse)
		{
			$form_params = array(
				'cancelReturnUrl' => urlencode(wp_specialchars_decode($order->get_cancel_order_url())),
				'successReturnUrl' => urlencode(wp_specialchars_decode($this->get_return_url($order))),
				'callbackUrl' => WC()->api_request_url(get_class($this)),
				'sessionToken' => $createOrderResponse['sessionToken'],
				'orderId' => $createOrderResponse['id'],
				'ecommerceOrderId' => $order->get_id(),
				'environment' => $this->get_option('environment'),
			);
			$form_params_joins = '';
			foreach ($form_params as $key => $value) {
				$form_params_joins .= $key . '=' . $value . '&';
			}
			return $this->frontendUrl . '?' . $form_params_joins;
		}

		public function callback_payment_handler()
		{	
			if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
				status_header(200);
				exit();
			} elseif ('POST' == $_SERVER['REQUEST_METHOD']) {
				$apikey = $_SERVER['HTTP_X_API_KEY'];
				if ($this->apikey != $apikey) {
					wp_send_json_error(array('message' => 'Unauthorized'), 401);
				}

				$raw_post = file_get_contents('php://input');
				$decoded  = json_decode($raw_post);
				$orderId = $decoded->orderId;
				$message = $decoded->message;

				$response = CryptumCheckoutUtils::request($this->backendUrl . '/orders/' . $orderId, [
					'headers' => [
						'x-api-key' => $apikey
					],
					'method' => 'GET',
					'timeout' => 60
				]);
				if (isset($response['error'])) {
					$error_message = $response['message'];
					wp_send_json_error(['message' => $error_message], 400);
				}

				$storeId = $response['pluginCheckoutStoreId'];
				$status = $response['paymentStatus'];
				$ecommerceOrderId = $response['pluginCheckoutEcommerce']['orderId'];
				$this->_log(json_encode($response));

				if (!isset($storeId) or $this->storeId != $storeId) {
					wp_send_json_error(array('message' => 'Incorrect store id'), 400);
				}

				if (isset($ecommerceOrderId) and isset($status)) {
					$order = wc_get_order($ecommerceOrderId);
					if (isset($message)) {
						$order->add_order_note(esc_html_e($message));
					}

					$error = null;
					if (empty($order)) {
						$error = "Payment callback received for non-existent order ID: $ecommerceOrderId";
					} elseif ($order->has_status('completed') or $order->has_status('processing')) {
						$error = "This order is currently being procesed or completed.";
					}
					if (isset($error)) {
						wp_send_json_error(
							array(
								'message' => $error,
							),
							400
						);
					}

					if ($status == 'confirmed') {
						try {
							$order->update_status('processing',  __('Payment was successful ', 'cryptumcheckout-wc-gateway'));
							$order->payment_complete();
							wp_send_json_error(
								array(
									'message' => 'Successful payment',
								),
								200
							);
						} catch (\Throwable $th) {
							wp_send_json_error(
								array(
									'message' => 'Failed to update order status [Order id: ' . $ecommerceOrderId . ']',
									'extra' => $th->getMessage()
								),
								500
							);
						}
					} elseif ($status == 'cancelled') {
						try {
							wc_increase_stock_levels($order);
							$order->update_status('cancelled',  __('Payment was cancelled ', 'cryptumcheckout-wc-gateway'));
							wp_send_json_error(
								array(
									'message' => 'Cancelled payment',
								),
								200
							);
						} catch (\Throwable $th) {
							wp_send_json_error(
								array(
									'message' => 'Failed to update order status [Order id: ' . $ecommerceOrderId . ']',
									'extra' => $th->getMessage()
								),
								500
							);
						}
					} elseif ($status == 'on-hold') {
						try {
							$order->update_status('on-hold',  __('Payment awaiting to complete ', 'cryptumcheckout-wc-gateway'));
							wp_send_json_error(
								array(
									'message' => 'Payment awaiting to complete',
								),
								200
							);
						} catch (\Throwable $th) {
							wp_send_json_error(
								array(
									'message' => 'Failed to update order status [Order id: ' . $ecommerceOrderId . ']',
									'extra' => $th->getMessage()
								),
								500
							);
						}
					} elseif ($status == 'failed') {
						try {
							wc_increase_stock_levels($order);
							$order->update_status('failed',  __('Payment failed', 'cryptumcheckout-wc-gateway'));
							wp_send_json_error(
								array(
									'message' => 'Payment failed',
								),
								200
							);
						} catch (\Throwable $th) {
							wp_send_json_error(
								array(
									'message' => 'Failed to update order status [Order id: ' . $ecommerceOrderId . ']',
									'extra' => $th->getMessage()
								),
								500
							);
						}
					} else {
						$this->_log('Could not change order ' . $ecommerceOrderId . ' status to ' . $status);
						wp_send_json_error(array('message' => 'Could not change order ' . $ecommerceOrderId . ' status to ' . $status), 400);
					}
				}
				wp_send_json_error(array('message' => 'Invalid order ' . $ecommerceOrderId), 500);
			}
		}

		private static function _log($message, $level = 'info')
		{
			wc_get_logger()->log($level, $message, array('source' => 'cryptumcheckout'));
		}
	}
}

function cryptumcheckout_add_to_gateways($gateways)
{
	$gateways[] = 'CryptumCheckout_WC_Gateway';
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'cryptumcheckout_add_to_gateways');

function cryptumcheckout_gateway_plugin_links($links)
{

	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=cryptumcheckout_gateway') . '">' . __('Configure', 'cryptumcheckout-wc-gateway') . '</a>'
	);

	return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cryptumcheckout_gateway_plugin_links');

add_action('plugins_loaded', 'cryptumcheckout_gateway_init', 11);
