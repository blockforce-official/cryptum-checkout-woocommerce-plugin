<?php

/**
 * Plugin Name: Cryptum Checkout
 * Plugin URI: https://github.com/blockforce-official/cryptum-checkout-woocommerce-plugin
 * Description: Cryptum Checkout Payment Gateway for Woocommerce
 * Version: 1.0.0
 * Author: Blockforce
 * Author URI: https://blockforce.in
 * Text Domain: cryptum-checkout
 * Domain Path: /languages
 * Requires at least: 5.7
 * Requires PHP: 7.0
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') or exit;

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('CRYPTUM_CHECKOUT_PATH', dirname(__FILE__));
define('CRYPTUM_CHECKOUT_PLUGIN_DIR', plugin_dir_url(__FILE__));
define('TEXT_DOMAIN', 'cryptum-checkout');

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	add_action('admin_notices', function () {
		echo '<div id="setting-error-settings_updated" class="notice notice-error">
			<p>' . __("Cryptum Checkout Plugin needs Woocommerce enabled to work correctly. Please install and/or enable Woocommerce plugin", 'cryptum-checkout') . '</p>
		</div>';
	});
	return;
}

function cryptumcheckout_add_to_gateways($gateways)
{
	$gateways[] = 'CryptumCheckout_Payment_Gateway';
	return $gateways;
}

function cryptumcheckout_set_plugin_action_links($links)
{
	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=cryptumcheckout_gateway') . '">' . __('Configure', 'cryptum-checkout') . '</a>'
	);
	return array_merge($plugin_links, $links);
}

function cryptumcheckout_gateway_init()
{
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}
	require_once('class-wc-gateway-cryptumcheckout.php');
}

add_filter('woocommerce_payment_gateways', 'cryptumcheckout_add_to_gateways');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cryptumcheckout_set_plugin_action_links');
add_action('plugins_loaded', 'cryptumcheckout_gateway_init', 11);
