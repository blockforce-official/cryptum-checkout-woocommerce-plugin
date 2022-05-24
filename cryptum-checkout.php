<?php

/**
 * Plugin Name: Cryptum Checkout
 * Plugin URI: https://github.com/blockforce-official/cryptum-checkout-woocommerce-plugin
 * Description: Cryptum Checkout Payment Gateway for Woocommerce
 * Version: 2.0.0
 * Author: Blockforce
 * Author URI: https://blockforce.in
 * Text Domain: woocommerce
 * Domain Path: /languages/
 * Requires at least: 5.5
 * Requires PHP: 7.0
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

use Cryptum\Checkout\Utils\Api;
use Cryptum\Checkout\Utils\Log;

defined('ABSPATH') or exit;

if (defined('CRYPTUM_CHECKOUT_PATH')) {
	return;
}

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('CRYPTUM_CHECKOUT_PATH', dirname(__FILE__));
define('CRYPTUM_CHECKOUT_PLUGIN_DIR', plugin_dir_url(__FILE__));
define('TEXT_DOMAIN', 'cryptum-checkout');

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action('admin_notices', function () {
		echo '<div id="setting-error-settings_updated" class="notice notice-error">
			<p>' . __("Cryptum Checkout Plugin needs Woocommerce enabled to work correctly. Please install and/or enable Woocommerce plugin", 'cryptumcheckout-wc-gateway') . '</p>
		</div>';
	});
	return;
}

require_once(plugin_dir_path(__FILE__) . '/lib/autoload.php');

function cryptumcheckout_add_to_gateways($gateways)
{
	$gateways[] = 'CryptumCheckout_Payment_Gateway';
	return $gateways;
}

function cryptumcheckout_set_plugin_action_links($links)
{
	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=cryptumcheckout_gateway') . '">' . __('Configure', 'cryptumcheckout-wc-gateway') . '</a>'
	);
	return array_merge($plugin_links, $links);
}

function cryptumcheckout_gateway_init()
{
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	class CryptumCheckout_Payment_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id                 = 'cryptumcheckout_gateway';
			$this->icon               = CRYPTUM_CHECKOUT_PLUGIN_DIR . '/assets/images/cryptum-checkout-logo.png';
			$this->has_fields         = false;
			$this->method_title       = __('Cryptum Checkout');
			$this->method_description = __('Connects your WooCommerce store to the Cryptum Checkout Payment Gateway.', 'cryptum-checkout');
			$this->description		  = __('Pay with Cryptum, you will be redirected to Cryptum Checkout to finish your order payment', 'cryptum-checkout');

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$environment = $this->get_option('environment');
			$this->title 					= $this->get_option('title');
			$this->storeId 			  		= $this->get_option('storeId');
			$this->apikey 					= $this->get_option('apikey');
			$this->productionEnvironment	= 'production' == $environment;
			$this->backendUrl  				= Api::get_cryptum_store_url($environment);
			$this->storeMarkupPercentage	= $this->get_option('storeMarkupPercentage');
			$this->storeDiscountPercentage	= $this->get_option('storeDiscountPercentage');
			$this->frontendUrl				= Api::get_cryptum_checkout_frontend($environment);

			$this->add_hooks();
		}

		private function add_hooks()
		{
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'callback_payment_handler'));
		}

		public function process_admin_options()
		{
			$url = Api::get_cryptum_store_url($this->get_option('environment'));
			$response = Api::request("{$url}/stores/verification", array(
				'body' => json_encode(array(
					'storeId' => $this->storeId
				)),
				'headers' => array(
					'content-type' => 'application/json',
					'x-api-key' => $this->apikey
				),
				'data_format' => 'body',
				'method' => 'POST',
				'timeout' => 60
			));
			if (isset($response['error'])) {
				$error_message = $response['message'];
				add_action('admin_notices', function () use ($error_message) {
					echo '<div class="notice notice-error">
						<p>' . __($error_message, 'cryptum-checkout') . '</p>
					</div>';
				});
				return false;
			}

			parent::process_admin_options();
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
					'title'   => __('Enable/Disable', 'cryptum-checkout'),
					'type'    => 'checkbox',
					'label'   => __('Enable Cryptum E-commerce Checkout', 'cryptum-checkout'),
				),
				'title' => array(
					'title' => __('Title', 'woothemes'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
					'default' => __('Cryptum Checkout', 'woothemes')
				),
				'environment' => array(
					'title' => __('Environment', 'cryptum-checkout'),
					'type' => 'select',
					'description' => __('Choose your environment. The Test environment should be used for testing only.', 'cryptum-checkout'),
					'default' => 'production',
					'options' => array(
						'production' => __('Production', 'cryptum-checkout'),
						'test' => __('Test', 'cryptum-checkout'),
					),
				),
				'storeId' => array(
					'title'       => __('Store ID', 'cryptum-checkout'),
					'type'        => 'text',
					'description' => __('Enter your Cryptum Checkout Store ID (Automatically Generated in Cryptum Dashboard, Woocommerce Section.)', 'cryptum-checkout'),
					'default'     => __('', 'cryptum-checkout'),
				),
				'apikey' => array(
					'title'       => __('API Key', 'cryptum-checkout'),
					'type'        => 'text',
					'description' => __('Enter your Cryptum API Key (Generated in Cryptum Dashboard, API Keys Section)'),
					'default'     => __('', 'cryptum-checkout'),
				),

				'storeMarkupPercentage' => array(
					'title'       => __('Store Markup Percentage', 'cryptum-checkout'),
					'type'        => 'text',
					'description' => __('Enter your percentage markup value', 'cryptum-checkout'),
					'default'     => __('0', 'cryptum-checkout'),
				),

				'storeDiscountPercentage' => array(
					'title'       => __('Store Discount Percentage', 'cryptum-checkout'),
					'type'        => 'text',
					'description' => __('Enter your percentage discount value'),
					'default'     => __('0', 'cryptum-checkout'),
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
					__('Unsupported currency: ' . $currency . '(Only USD is supported for now)', 'cryptum-checkout'),
					'error'
				);
				return array('result' => 'error', 'redirect' => '');
			}

			$body = array(
				'store' => $this->storeId,
				'ecommerceType' => 'wordpress',
				'ecommerceOrderId' => '' . $order->get_id(),
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

			$response = Api::request($this->backendUrl . '/orders/checkout', array(
				'body' => json_encode($body),
				'headers' => $headers,
				'data_format' => 'body',
				'method' => 'POST',
				'timeout' => 60
			));
			if (isset($response['error'])) {
				$error_message = $response['message'];
				wc_add_notice(__('Error processing Cryptum Checkout', 'cryptum-checkout') . ': ' . $error_message, 'error');
				return array('result' => 'error', 'redirect' => '');
			}

			$order->update_status('pending', __('Pending Cryptum Checkout payment', 'cryptum-checkout'));

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
				$ecommerceOrderId = $decoded->ecommerceOrderId;
				$storeId = $decoded->storeId;

				$response = Api::request($this->backendUrl . '/orders/' . $orderId, [
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

				$status = $response['paymentStatus'];
				Log::info(json_encode($response));

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
							$order->update_status('processing',  __('Payment was successful ', 'cryptum-checkout'));
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
							$order->update_status('cancelled',  __('Payment was cancelled ', 'cryptum-checkout'));
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
							$order->update_status('on-hold',  __('Payment awaiting to complete ', 'cryptum-checkout'));
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
							$order->update_status('failed',  __('Payment failed', 'cryptum-checkout'));
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
						Log::info('Could not change order ' . $ecommerceOrderId . ' status to ' . $status);
						wp_send_json_error(array('message' => 'Could not change order ' . $ecommerceOrderId . ' status to ' . $status), 400);
					}
				}
				wp_send_json_error(array('message' => 'Invalid order ' . $ecommerceOrderId), 500);
			}
		}
	}
}

add_filter('woocommerce_payment_gateways', 'cryptumcheckout_add_to_gateways');
add_filter('plugin_action_links_' . plugin_basename(CRYPTUM_CHECKOUT_PLUGIN_DIR), 'cryptumcheckout_set_plugin_action_links');
add_action('plugins_loaded', 'cryptumcheckout_gateway_init', 11);
