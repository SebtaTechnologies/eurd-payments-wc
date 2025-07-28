<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * EURD Payments Blocks integration
 *
 * @since 1.0.0
 */
final class WC_Gateway_EURD_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_EURD
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'eurd';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_eurd_settings', [] );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/assets/js/frontend/blocks.js';
		$script_asset_path = EURDPAYFW_WC_EURD_Payments::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => EURDPAYFW_VERSION,
			);
		$script_url        = EURDPAYFW_WC_EURD_Payments::plugin_url() . $script_path;

		wp_register_script(
			'wc-eurd-payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		return [ 'wc-eurd-payments-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'id'            => $this->gateway->id,
			'title'         => $this->gateway->title,
			'description'   => $this->gateway->description,
			'icon'          => $this->gateway->icon,
			'order_btn_txt' => $this->gateway->order_button_text,
			'supports'      => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
		];
	}
}
