<?php
/**
 * WC_Gateway_EURD class
 *
 * @package   WooCommerce EURD Payments Gateway
 * @author    Sebta <info@sebta.com>
 * @since     1.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include_once 'class-wc-eurd-helper.php';
include_once 'class-wc-eurd-api.php';
include_once 'class-quantoz-webhook.php';

/**
 * EURD Gateway.
 *
 * @class   WC_Gateway_EURD
 * @version 1.0.0
 */
class WC_Gateway_EURD extends WC_Payment_Gateway {

	/**
	 * Payment gateway instructions.
	 *
	 * @var string
	 */
	protected $instructions;

	/**
	 * Logger instance.
	 */

	protected $logger;

	/**
	 * Unique id for the gateway.
	 *
	 * @var string
	 */
	public $id = 'eurd';

	/**
	 * Merchant API key.
	 *
	 * @var string
	 */
	private $merchant_api_key;

	/**
	 * Merchant account code.
	 *
	 * @var string
	 */
	protected $merchant_account_code;

	/**
	 * API client instance.
	 *
	 * @var EURDPAYFW_WC_EURD_API
	 */
	protected $api_client;

	/**
	 * EURD payment request code.
	 *
	 * @var string
	 */
	protected $eurd_payment_request_code;

	/**
	 * EURD payment link.
	 *
	 * @var string
	 */
	protected $eurd_payment_link;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	protected $order_id;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                   = 'eurd';
		$this->icon                 = plugins_url( 'includes/images/eurd-logo.png', dirname( __FILE__ ) );
		$this->has_fields           = true;
		$this->supports             = array( 'products' );
		$this->method_title         = _x( 'Pay with EURD', 'EURD payment method', 'eurd-payments-wc' );
		$this->method_description   = __( 'No middleman, no fees. Support the seller directly with paying with EURD.', 'eurd-payments-wc' );
		$this->max_amount           = apply_filters( 'EURDPAYFW_max_transaction_amount', 10000 );
		$this->order_button_text    = __( 'Proceed to Payment', 'eurd-payments-wc' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// User set variables.
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->merchant_account_code = $this->get_option( 'merchant_account_code' );
		$this->instructions         = $this->get_option( 'instructions', $this->description );

		$encrypted = $this->get_option( 'merchant_api_key' );
        $this->merchant_api_key = $encrypted
            ? $this->decrypt_value( $encrypted )
            : '';

		// Admin and frontend hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ], 99 );
		add_action( 'woocommerce_receipt_' . $this->id, [ $this, 'generate_qr_code' ], 100 );
		add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'custom_checkout_url' ], 99, 2 );
		add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text' ], 99, 2 );
		add_action( 'wp_ajax_nopriv_eurd_check_order_status', [ $this, 'ajax_check_order_status' ] );
		add_action( 'wp_ajax_eurd_check_order_status', [ $this, 'ajax_check_order_status' ] );
		add_action('quantoz_payment_request_paid_verify', [ $this, 'quantoz_payment_request_paid_verify' ], 10, 3 );

		// WC  logging
		if ( class_exists( 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		} else {
			$this->logger = wc_get_logger();
		}

		//$this->logger->error( 'EURD API encrypted: '. $encrypted . ' Original: '.$this->merchant_api_key, array( 'source' => 'eurd-payments-wc' ) );

	}

	/**
	 * Initialize gateway form fields.
	 */
	public function init_form_fields() {

		$account_options = $this->populate_merchant_accounts_field($this->merchant_api_key);

		$this->form_fields = array(
			'enabled'               => array(
				'title'       => __( 'Enable/Disable', 'eurd-payments-wc' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable EURD Payments', 'eurd-payments-wc' ),
				'default'     => 'yes',
			),
			'title'                 => array(
				'title'       => __( 'Title', 'eurd-payments-wc' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'eurd-payments-wc' ),
				'default'     => _x( 'Pay with EURD', 'EURD payment method', 'eurd-payments-wc' ),
				'desc_tip'    => true,
			),
			'description'           => array(
				'title'       => __( 'Description', 'eurd-payments-wc' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'eurd-payments-wc' ),
				'default'     => __( 'No middleman, no fees. Support the seller directly with paying with EURD.', 'eurd-payments-wc' ),
				'desc_tip'    => true,
			),
			'merchant_api_key'      => array(
				'title'       => __( 'API Key', 'eurd-payments-wc' ),
				'type'        => 'password',
				'required'      => true,
				'default'     => '',
				'value'       => '', // alway blank, as it will be encrypted
				'custom_attributes' => array(
                    'autocomplete' => 'off',
                ),
				'description' => sprintf( '%s <a href="https://portal.quantozpay.com/home" target="_blank">%s</a>', __( 'The API Key needed for the Plugin, can be generated using: ', 'eurd-payments-wc' ), 'Quantoz Pay Portal' ),
				'desc_tip'    => false,
			),
			'merchant_account_code' => array(
				'title'       => __( 'Merchant Account', 'eurd-payments-wc' ),
				'type'        => 'select',
				'description' => __( 'EURD Merchant account code used for the payments', 'eurd-payments-wc' ),
				'required'      => true,
				'options'    => $account_options,
				'desc_tip'    => false,
			),
		);
	}

	public function populate_merchant_accounts_field($api_key){
		// 1) Build dropdown options from API
		$account_options = array(
			'' => __( '— enter a valid API key first —', 'eurd-payments-wc' )
		);
		if ( ! empty( $api_key ) ) {
			try {
				// Make sure your client is using the saved key
				$this->api_client = new EURDPAYFW_WC_EURD_API( $api_key );
				$accounts = $this->api_client->getAccounts();

				if ( is_array( $accounts ) && count( $accounts ) ) {
					$account_options = array(); // reset placeholder
					foreach ( $accounts as $acct ) {
						// adjust these keys to match your API response
						$account_options[ $acct['accountCode'] ] = sprintf(
							'%s (%s)',
							$acct['customName'] ? $acct['customName'] : "No custom name",
							$acct['accountCode']
						);
					}
				} else {
					// no accounts returned
					$account_options = array(
						'' => __( 'No accounts found for this key', 'eurd-payments-wc' )
					);
				}
			} catch ( Exception $e ) {
				// invalid key or network error
				$account_options = array(
					'' => __( 'Could not fetch accounts (invalid API key?)', 'eurd-payments-wc' )
				);
			}
		}

		return $account_options;
	}

	public function validate_merchant_api_key_field( $merchant_api_key, $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Decrypt the value to ensure it's valid.
		$api_key = $this->decrypt_value( $value );

		$this->api_client = new EURDPAYFW_WC_EURD_API( $api_key );
		$isValid = $this->api_client->validateApiKey();

		if(!$isValid) {
			WC_Admin_Settings::add_error( 'EURD API Error: Provided API key is not valid. Key: '.  $api_key );
			$value = '';
		}
		
		return $value;
	}

	public function validate_merchant_api_key( $merchant_api_key ) {
		if ( empty( $merchant_api_key ) ) {
			return false;
		}

		// Decrypt the value to ensure it's valid in case already encrypted.
		$api_key = $this->decrypt_value( $merchant_api_key );

		$this->api_client = new EURDPAYFW_WC_EURD_API( $api_key );
		$isValid = $this->api_client->validateApiKey();
		if($isValid){
			return true;
		}
		
		return false;
	}

	/**
	 * Return whether or not this gateway still requires setup to function.
	 *
	 * When this gateway is toggled on via AJAX, if this returns true a
	 * redirect will occur to the settings page instead.
	 * @return bool
	 */
	public function needs_setup() {
		return empty( $this->merchant_api_key ) || empty( $this->merchant_account_code );
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options() {
		if ( $this->is_currency_supported() ) {
			//initiate  options to get latest api key
			$this->init_form_fields();

			parent::admin_options();
		} else {
			?>
			<div class="inline error">
				<p>
					<strong><?php esc_html_e( 'Gateway disabled', 'eurd-payments-wc' ); ?></strong>: <?php esc_html_e( 'This plugin does not support your store currency. Only EURO Currency is supported.', 'eurd-payments-wc' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check if the gateway is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		// Check if the merchant account code and API key are set.
		if ( empty( $this->merchant_account_code ) || empty( $this->merchant_api_key ) ) {
			return false;
		}

		// Check if the currency is supported.
		if ( ! $this->is_currency_supported() ) {
			return false;
		}

		// Check if the API key is valid.
		if ( ! $this->validate_merchant_api_key( $this->merchant_api_key ) ) {
			return false;
		}

		return true;
	}
	/**
	 * Check if the current currency is supported.
	 *
	 * @return bool
	 */
	public function is_currency_supported() {
	
		if ( in_array( get_woocommerce_currency(), [ 'EUR' ] ) ) {
			return true;
		}

		return false;
	}

	 /**
     * When the admin saves settings, intercept any new plaintext key,
     * encrypt it, and store only the ciphertext+IV blob.
     */
	public function process_admin_options() {
		parent::process_admin_options();
		
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash(sanitize_key($_POST['_wpnonce'] )), 'woocommerce-settings' ) ) {
			die( 'Security check failed' ); 
		}

		$api_field = 'woocommerce_' . $this->id . '_merchant_api_key';
		if ( isset( $_POST[ $api_field ] )) {
			$raw = sanitize_text_field( wp_unslash( $_POST[ $api_field ] ) );
			if ( empty( $raw ) ) {
				WC_Admin_Settings::add_error( 'The API key cannot be empty' );
				$this->enabled = 'no';
				$this->update_option( 'enabled', '' );
				return;			
			}
			if ( '' !== $raw ) {
				// Make sure it is valid API key before encrypting
				$isValid = $this->validate_merchant_api_key( $raw );

				if ($isValid ) {
					$encrypted = $this->encrypt_value( $raw );
					$this->update_option( 'merchant_api_key', $encrypted );
					$this->merchant_api_key = $encrypted; // update the class property					
					WC_Admin_Settings::add_message( __( 'API Key is validated & saved successfully.', 'eurd-payments-wc' ) );
				}
				else {
					$this->enabled = 'no';
					$this->update_option( 'enabled', '' );
					return;				
				}
			}
		}

		$isValid = $this->validate_merchant_api_key( $this->merchant_api_key );
		if ( ! $isValid ) {
			$this->enabled = 'no';
			$this->update_option( 'enabled', '' );
			WC_Admin_Settings::add_error( __( 'Invalid API Key. Please check your API Key and try again.', 'eurd-payments-wc' ) );
		}
	}


	/**
     * Build a 256-bit cipher key from WP’s AUTH_KEY + SECURE_AUTH_SALT.
     * @return string binary 32-byte key
     */
    private function get_cipher_key() {
        $auth_key  = defined( 'AUTH_KEY' )         ? AUTH_KEY         : '';
        $auth_salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '';
        return hash( 'sha256', $auth_key . $auth_salt, true );
    }

	private function encrypt_value( $plaintext ) {
		$prefix = 'WPEURDENC_';

		// If already encrypted, return the value as is
		if ( strpos( $plaintext, $prefix ) === 0 ) {
			return $plaintext;
		}

		// encrypte only if valid api key
		if ( $this->validate_merchant_api_key( $this->merchant_api_key ) === false ) {
			return $plaintext; // return as is if not valid
		}

		$key    = $this->get_cipher_key();
		$cipher = 'AES-256-CBC';
		$iv_len = openssl_cipher_iv_length( $cipher );
		$iv     = openssl_random_pseudo_bytes( $iv_len );
		$ct     = openssl_encrypt( $plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv );
		// add a marker so we never mistake raw strings for cipher blobs
		return $prefix . base64_encode( $iv . $ct );
	}


    private function decrypt_value( $value ) {
		// Marker we added in encrypt_value()
		$prefix = 'WPEURDENC_';

		// If it doesn’t start with our prefix, treat it as raw plaintext
		if ( strpos( $value, $prefix ) !== 0 ) {
			return $value;
		}

		// Strip off the prefix, then decrypt
		$b64    = substr( $value, strlen( $prefix ) );
		$data   = base64_decode( $b64 );
		$cipher = 'AES-256-CBC';
		$iv_len = openssl_cipher_iv_length( $cipher );
		$iv     = substr( $data, 0, $iv_len );
		$ct     = substr( $data, $iv_len );
		$key    = $this->get_cipher_key();

		$plaintext = openssl_decrypt( $ct, $cipher, $key, OPENSSL_RAW_DATA, $iv );

		// If decryption somehow fails, fall back to returning the original value
		return $plaintext !== false ? $plaintext : $value;
	}


	/**
	 * Perform API operations to generate payment link/QR.
	 *
	 * @return bool|void
	 */
	public function eurd_api_operations() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		$order_id = get_query_var( 'order-pay' );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->order_id = $order->get_id();
		$total          = apply_filters( 'EURDPAYFW_order_total_amount', $order->get_total(), $order );
		$this->api_client = new EURDPAYFW_WC_EURD_API( $this->merchant_api_key );
		$this->eurd_payment_request_code = $this->api_client->getPaymentRequestCode($total,$this->order_id, $this->merchant_account_code);

		if ( 'Paid' === $this->eurd_payment_request_code ) {
			$order->payment_complete( $this->eurd_payment_request_code );
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$this->eurd_payment_link = $this->api_client->pay_url . $this->eurd_payment_request_code;

		if ( $this->eurd_payment_request_code ) {
			$order->update_meta_data( 'eurd_pr_code', $this->eurd_payment_request_code );
			$order->save();
		} else {
			wc_get_logger()->debug('EURD API Error: Unable to generate payment request code.', array( 'source' => 'eurd-payments-wc' ) );
		}

		if ( $this->max_amount && $total > $this->max_amount ) {
			$this->logger->debug( 'EURD API Error: Amount exceeds the maximum limit of ' . $this->max_amount. ' EURD. Order id:'. $order_id, array( 'source' => 'eurd-payments-wc' ) );
			return;
		}

		if ( empty( $this->merchant_account_code ) || empty( $this->merchant_api_key ) ) {
			return;
		}

		return false;
	}

	/**
	 * Enqueue custom scripts for the payment.
	 */
	public function payment_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_register_script(
			'EURDPAYFW-qr-code',
			plugins_url( 'js/lib/easy.qrcode.min.js', __FILE__ ),
			array( 'jquery' ),
			EURDPAYFW_VERSION,
			true
		);
		wp_register_script(
			'EURDPAYFW-payment',
			plugins_url( 'js/eurd-payments.min.js', __FILE__ ),
			array( 'jquery', 'EURDPAYFW-qr-code' ),
			EURDPAYFW_VERSION,
			true
		);

		if ( 'no' === $this->enabled ) {
			return;
		}

		$order_id = get_query_var( 'order-pay' );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->eurd_api_operations();

		if ( ! $this->eurd_payment_request_code || ! $this->eurd_payment_link ) {
			$order->add_order_note( __( 'Payment request code or link is not set.', 'eurd-payments-wc' ), false );
			return;
		}

		wp_localize_script(
			'EURDPAYFW-payment',
			'EURDPAYFWData',
			array(
				'order_id'                  => $order_id,
				'eurd_payment_request_code' => $this->eurd_payment_request_code,
				'eurd_payment_url'          => $this->eurd_payment_link,
				'payment_url'               => $order->get_checkout_payment_url(),
				'confirm_url'               => $this->custom_confirm_url( $order->get_checkout_payment_url(), $this->eurd_payment_request_code ),
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'nonce'                     => wp_create_nonce( 'EURDPAYFW' ),
			)
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order   = wc_get_order( $order_id );
		$message = __( 'Awaiting EURD Payment!', 'eurd-payments-wc' );

		$order->update_status( $this->default_status );
		$order->update_meta_data( '_EURDPAYFW_order_paid', 'no' );
		$order->add_order_note( apply_filters( 'EURDPAYFW_process_payment_note', $message, $order ), false );
		$order->save();

		if ( apply_filters( 'EURDPAYFW_payment_empty_cart', false ) ) {
			WC()->cart->empty_cart();
		}

		do_action( 'EURDPAYFW_after_payment_init', $order_id, $order );

		return array(
			'result'   => 'success',
			'redirect' => apply_filters( 'EURDPAYFW_process_payment_redirect', $order->get_checkout_payment_url( true ), $order ),
		);
	}

	protected function render_pending_payment() {
    	echo '<p><strong>' . esc_html__( 'Order Status:', 'eurd-payments-wc' ) . '</strong><br>' . esc_html__( 'Pending payment.', 'eurd-payments-wc' ) . '</p>';
	}

	/**
	 * Manually confirm payment (used on receipt page).
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function confirm_payment_manual( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$this->render_pending_payment();
			return;
		}

		$order->add_order_note( 'Payment confirmation link clicked by user on order page.', false );

		if ( ! $order->needs_payment() ) {
			// Order already paid or completed.
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}

		$code    = $order->get_meta( 'eurd_pr_code', true );
		if ( ! $code ) {
			$this->render_pending_payment();
			return;
		}

		if ( ! $this->api_client ) {
				$this->api_client = new EURDPAYFW_WC_EURD_API( $this->merchant_api_key );
		}

		$payment = $this->api_client->getPaymentStatus( $code );

		if ( !isset( $payment['status'] ) || 'Paid' !== $payment['status'] ) {
			$order->add_order_note('Payment status not paid. Current status is '. $payment['status'] . ' for the code '.$code,false);
			$this->render_pending_payment();
			return;
		}

		$tx = $payment['payments'][0] ?? [];
    	$transactionCode   = trim( $tx['transactionCode'] ?? '' );
    	$transactionAmount = $tx['amount'] ?? null;

		if ( !$tx || !$transactionCode || !$transactionAmount ) {
			$order->add_order_note(__( 'Payment confirmation failed: Transaction details are missing.', 'eurd-payments-wc' ),false);
			$this->render_pending_payment();
			return;
		}

		if ( (float) $transactionAmount !== (float) $order->get_total() ) {
			// Amount mismatch
			$order->add_order_note(__( 'Payment confirmation failed: Amount mismatch.', 'eurd-payments-wc' ),false);
			$this->render_pending_payment();
			return;
		}

		// If amount matches the order total, proceed with payment completion.
		if ( $transactionCode && (float) $transactionAmount === (float) $order->get_total() ) {
			$order->payment_complete($transactionCode);
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;	
		}

		$this->render_pending_payment();
	}

	/**
	 * Generate QR code and link on the receipt page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function generate_qr_code( $order_id ) {
		$order = wc_get_order( $order_id );

		wp_enqueue_style( 'EURDPAYFW-payment' );
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'EURDPAYFW-qr-code' );
		wp_enqueue_script( 'EURDPAYFW-payment' );

		$this->confirm_payment_manual( $order_id );
		?>
		<div class="payment-container" style="box-shadow:0 0 10px rgba(0,0,0,0.1); padding:15px; border-radius:5px; background-color:#fff; margin:20px 0; display:flex; max-width:220px; flex-direction:column; justify-content:center; align-items:center; text-align:center;">
			<p class="payment-subtitle" style="font-size:0.9rem; text-decoration:none; color:#000; font-weight:bold; text-align:center">Scan with your EURD wallet to pay or open the link</p>
			<!-- QR Code Section -->
			<div class="qr-code">
				<div id="eurd-payment-qr-code" class="EURDPAYFW-payment-qr-code show"></div>
			</div>
			<div class="action-buttons">
				<a id="eurd-payment-url" href="#" target="_blank" style="display:block; margin-top:10px; font-size:0.9rem; font-weight:bold; color:navy;">
					<?php esc_html_e( 'Open Mobile App Link', 'eurd-payments-wc' ); ?>
				</a>
			</div>
			<div id="EURDPAYFW-payment-success-container" style="display:none;"></div>
		
		</div>
		<div id="manualPaymentConfirmBtn" style="padding:10px; margin-top:10px; max-width:300px;display:none">
		If you have already paid, please <a href="#" id="EURDPAYFW-confirm-payment" class="">confirm your payment</a>.
		</div>
		<?php
	}

	/**
	 * Custom thank you text.
	 *
	 * @param string   $text  Default text.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function order_received_text( $text, $order ) {
		if ( is_a( $order, 'WC_Order' ) && $this->id === $order->get_payment_method() && ! empty( $this->thank_you ) ) {
			return esc_html( $this->thank_you );
		}

		return $text;
	}

	/**
	 * Custom checkout URL for on-hold/pending orders.
	 *
	 * @param string   $url   Default URL.
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function custom_checkout_url( $url, $order ) {
		if ( is_a( $order, 'WC_Order' )
			&& $this->id === $order->get_payment_method()
			&& $order->has_status( 'pending' )
		) {
			return esc_url( remove_query_arg( 'pay_for_order', $url ) );
		}

		return $url;
	}

	/**
	 * Add payment request code to the confirmation URL.
	 *
	 * @param string   $url                       Base URL.
	 * @param string   $eurd_payment_request_code Code.
	 * @return string
	 */
	public function custom_confirm_url( $url, $eurd_payment_request_code ) {
		$url = remove_query_arg( 'pay_for_order', $url );
		$url = add_query_arg( 'eurd_payment_request_code', $eurd_payment_request_code, $url );

		return esc_url( $url );
	}

	/**
	 * AJAX: Check order status.
	 */
	public function ajax_check_order_status() {
		if ( empty( $_POST['EURDPAYFW_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['EURDPAYFW_nonce'] ) ), 'EURDPAYFW' ) ) {
			wp_send_json_error( 'bad_nonce', 400 );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( 'no_order', 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'not_found', 404 );
		}

		wp_send_json_success( array( 'status' => $order->get_status() ) );
	}

	/**
	 * Verify payment request is paid when a webhook notification is received.
	 *
	 * @param int    $order_id         Order ID.
	 * @param string $paymentReqCode   Payment Request Code.
	 * @param string $transactionCode  Transaction Code.
	 */
	public function quantoz_payment_request_paid_verify( $order_id, $paymentReqCode, $transactionCode ) {
		
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		// Verify order is valid and has the correct payment request code.
		$existing_code = $order->get_meta( 'eurd_pr_code', true );

		if ( $existing_code !== $paymentReqCode || !$order->needs_payment() ) {
			return;
		}

		$order->add_order_note( 'Payment notification received with code: '.$transactionCode, false );

		// Verify payment status on Quantoz side.
		if ( ! $this->api_client ) {
			$this->api_client = new EURDPAYFW_WC_EURD_API( $this->merchant_api_key );
		}

		$payment = $this->api_client->getPaymentStatus( $paymentReqCode );

		if ( !isset( $payment['status'] ) || 'Paid' !== $payment['status'] ) {
			return;
		}

		$tx = $payment['payments'][0] ?? [];
		$txCode   = trim( $tx['transactionCode'] ?? '' );
		$txAmount = $tx['amount'] ?? null;

		if ( !$tx || !$txCode || !$txAmount || $txCode != $transactionCode ) {
			return;
		}

		if ( (float) $txAmount !== (float) $order->get_total() ) {
			// Amount mismatch
			$order->add_order_note(__( 'Payment confirmation failed: Amount mismatch.', 'eurd-payments-wc' ),false);
			return;
		}

		// If amount matches the order total, proceed with payment completion.
		$order->payment_complete($transactionCode);
	}
}
