<?php
/**
 * Plugin Name: EURD Payments for WooCommerce
 * Plugin URI: https://sebta.com/woocommerce-eurd-payments
 * Description: Adds the EURD Payments gateway to your WooCommerce website.
 * Version: 1.0.0
 * Author: Sebta Technologies
 * Author URI: https://sebta.com/
 * Text Domain: eurd-payments-wc
 * Requires at least: 4.2
 * Requires Plugins: woocommerce
 * Tested up to: 6.8
 * Copyright: © 2024-2025 Sebta Technologies.
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin version
define( 'EURDPAYFW_VERSION', '1.0.0' );

/**
 * Main class for WooCommerce EURD Payments gateway.
 */
class EURDPAYFW_WC_EURD_Payments {

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	protected $version = EURDPAYFW_VERSION;

	/**
	 * Initialize the plugin by registering actions and filters.
	 */
	public static function init() {
		// Include necessary files and classes when the plugin is loaded.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );

		// Add the EURD gateway to WooCommerce payment gateways.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'conditionally_remove_gateway' ) );

		// Register WooCommerce Blocks support.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'woocommerce_gateway_eurd_woocommerce_block_support' ) );
	}

	/**
	 * Add the EURD payment gateway to the list of WooCommerce gateways.
	 *
	 * @param array $gateways List of available gateways.
	 * @return array Updated list of gateways.
	 */
	public static function add_gateway( $gateways ) {
		$options = get_option( 'woocommerce_eurd_settings', array() );
		$hide_for_non_admin_users = isset( $options['hide_for_non_admin_users'] ) ? $options['hide_for_non_admin_users'] : 'no';

		// Only show gateway to admin users
		if ( ( 'yes' === $hide_for_non_admin_users && current_user_can( 'manage_options' ) ) || 'no' === $hide_for_non_admin_users ) {
			$gateways[] = 'WC_Gateway_EURD';
		}

		return $gateways;
	}

	/**
	 * Include necessary plugin files.
	 */
	public static function includes() {
		// Ensure the WC_Gateway_EURD class is loaded only if WooCommerce is active.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once 'includes/class-wc-gateway-eurd.php';
		}
	}

	/**
	 * Get the plugin URL.
	 *
	 * @return string Plugin URL.
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the absolute plugin path.
	 *
	 * @return string Plugin directory path.
	 */
	public static function plugin_abspath() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Registers WooCommerce Blocks integration for the EURD payment gateway.
	 */
	public static function woocommerce_gateway_eurd_woocommerce_block_support() {
		// Check if the WooCommerce Blocks integration is available.
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once 'includes/blocks/class-wc-eurd-payments-blocks.php';

			// Register the EURD payment method with WooCommerce Blocks.
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WC_Gateway_EURD_Blocks_Support() );
				}
			);
		}
	}

	public static function conditionally_remove_gateway( $available_gateways ) {
		// If the EURD gateway is not available or the merchant API key is invalid, disable the gateway.
		$eurd_gateway = isset( $available_gateways['eurd'] ) ? $available_gateways['eurd'] : null;
		$merchant_api_key = $eurd_gateway ? $eurd_gateway->get_option( 'merchant_api_key', '' ) : '';

		if ( isset( $available_gateways['eurd'] ) && ! $eurd_gateway->validate_merchant_api_key($merchant_api_key)  ) {
			$available_gateways['eurd']->enabled = 'no';
		}

		return $available_gateways;
	}

}

// Initialize the plugin.
EURDPAYFW_WC_EURD_Payments::init();
