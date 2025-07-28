<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class EURDPAYFW_WC_EURD_API {
    private $api_base = 'https://api.quantozpay.com/';
    public $pay_url = 'https://pay.quantozpay.com/';
    private $merchant_api_key;
    private $timeout = 60;
    protected $logger;

    public function __construct( $merchant_api_key ) {
        $this->merchant_api_key     = $merchant_api_key;

        // WC  logging
		if ( class_exists( 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		} else {
			$this->logger = wc_get_logger();
		}
    }

    /**
     * Makes an API request and returns decoded JSON or WP_Error.
     */
    private function api_request( $method, $endpoint, $params = array(), $body = null ) {
        // Build URL
        $url = strpos( $endpoint, 'http' ) === 0
            ? $endpoint
            : trailingslashit( $this->api_base ) . ltrim( $endpoint, '/' );

        if ( ! empty( $params ) && in_array( strtoupper( $method ), array( 'GET', 'DELETE' ) ) ) {
            $url = add_query_arg( $params, $url );
        }

        $args = array(
            'method'    => strtoupper( $method ),
            'headers'   => array(
                'Content-Type' => 'application/json',
                'x-api-key'    => $this->merchant_api_key,
            ),
            'timeout'   => $this->timeout,
        );

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            $this->logger->error( sprintf( 'EURD API Error [%s %s]: %s', $method, $url, $response->get_error_message() ), array( 'source' => 'eurd-payments-wc' ) );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = isset( $data['Errors'][0]['Message'] ) ? $data['Errors'][0]['Message'] : $raw;
            $this->logger->error( sprintf( 'EURD API Error [%s %s]: %s', $method, $url, $code, $message ), array( 'source' => 'eurd-payments-wc' ) );
            return new WP_Error( 'eurd_api_error', $message );
        }

        return $data;
    }

    private function get( $endpoint, $params = array() ) {
        return $this->api_request( 'GET', $endpoint, $params );
    }

    /**
     * Helper for POST requests.
     */
    private function post( $endpoint, $body = array() ) {
        return $this->api_request( 'POST', $endpoint, array(), $body );
    }

    private function delete( $endpoint ) {
        return $this->api_request( 'DELETE', $endpoint );
    }

    private function fetch_payment_items() {
        $res = $this->get( 'payment-request' );
        if ( is_wp_error( $res ) || empty( $res['value']['items'] ) ) {
            $this->logger->error( sprintf( 'EURD API Error [%s]: %s', 'payment-request', $res->get_error_message() ), array( 'source' => 'eurd-payments-wc' ) );
            return false;
        }
        return $res['value']['items'];
    }

    private function find_payment_item( $items, $code ) {
        foreach ( $items as $item ) {
            if ( isset( $item['code'] ) && $item['code'] === $code ) {
                return $item;
            }
        }
        return false;
    }

    public function validateApiKey() {
        $res = $this->get( 'account/list', array() );
        if ( is_wp_error( $res ) ) {
            $this->logger->error( sprintf( 'EURD API Error2 [%s]: %s', 'account/list', $res->get_error_message() ), array( 'source' => 'eurd-payments-wc' ) );
            return false;
        }

        if ($res  === 403){
            $this->logger->error( sprintf( 'EURD API Error [%s]: %s', 'account/list', 'Invalid API Key' ), array( 'source' => 'eurd-payments-wc' ) );
            return false;
        }

        return isset($res['value']) && is_array($res['value']) && count($res['value']) > 0;
    }

    public function getAccounts() {
        $res = $this->get( 'account/list', array() );
        if ( is_wp_error( $res ) ) {
            return $res->get_error_message();
        }

        return $res['value'];

        // if ( isset( $res['value']['enabled'] ) && $res['value']['enabled'] ) {
        //     return true;
        // }

        // return sprintf( 'Pay with EURD ERROR: %s', wp_json_encode( $res ) );
    }

    public function validateAccount( $accountCode ) {
        $res = $this->get( 'account/balance', array( 'accountCode' => $accountCode ) );
        if ( is_wp_error( $res ) ) {
            return $res->get_error_message();
        }

        if ( isset( $res['value']['enabled'] ) && $res['value']['enabled'] ) {
            return true;
        }

        return sprintf( 'Pay with EURD ERROR: %s', wp_json_encode( $res ) );
    }


    public function getPaymentStatus( $payCode = null ) {
        if ( empty( $payCode ) ) {
            return false;
        }

        $items = $this->fetch_payment_items();
        if ( ! $items ) {
            return false;
        }

        return $this->find_payment_item( $items, $payCode );
    }

    public function getExistingPaymentRequest( $payCode ) {
        if ( empty( $payCode ) ) {
            return false;
        }

        $item = $this->getPaymentStatus( $payCode );
        return $item && isset( $item['status'] ) ? $item : false;
    }

    public function getPaymentRequestCode( $amount, $order_id, $merchant_account_code) {
        $existing_code = get_post_meta( $order_id, 'eurd_pr_code', true );
        $order         = wc_get_order( $order_id );

        if ( $existing_code ) {
            $existing = $this->getExistingPaymentRequest( $existing_code );
            if ( $existing ) {
                if ( $existing['status'] === 'Paid' ) {
                    return 'Paid';
                }
                if ( $existing['status'] === 'Open' && (float) $order->get_total() === (float) $existing['requestedAmount'] ) {
                    return $existing_code;
                }
            }
            // If the existing code is not valid or has a different amount, delete it
            $this->deletePaymentRequest( $existing_code );
            delete_post_meta( $order_id, 'eurd_pr_code' );
        }

        // Generate a new payment request
        $new_code = $this->createPaymentRequest($amount, $order_id,  $merchant_account_code);
        if (! $new_code) {
            $this->logger->error( sprintf( 'Failed to create new payment request for order %d', $order_id ), array( 'source' => 'eurd-payments-wc' ) );
            $order->add_order_note( 'Failed to create EURD Payment Request Code', false );
            return false;
        }

        update_post_meta( $order_id, 'eurd_pr_code', $new_code );
        $order->add_order_note( 'EURD Payment Request Code Generated: ' . $new_code, false );

        return $new_code;
    }

    public function createPaymentRequest($amount, $order_id, $merchant_account_code)
    {
        // Set expiration to 6 hours from now
        $expires_on = (new DateTime())->modify('+6 hours')->format('d/m/Y H:i:s P');
        $callback_url = get_rest_url(null, 'quantoz/v1/webhook/');

        // Build payload
        $payload = [
            'accountCode' => $merchant_account_code,
            'amount'      => $amount,
            'options'     => [
                'expiresOn'                     => $expires_on,
                'shareName'                     => true,
                'message'                       => time() . '_wc_orderId_' . $order_id,
                'isOneOffPayment'               => true,
                'payerCanChangeRequestedAmount' => false,
                'callbackUrl'                   => $callback_url,
            ],
        ];

        $response = $this->post('payment-request', $payload);
        if (is_wp_error($response) || empty($response['value']['code'])) {
            $this->logger->error( sprintf( 'EURD API Error [%s]: %s [[%s]]', 'payment-request', $response->get_error_message(), json_encode($payload) ), array( 'source' => 'eurd-payments-wc' ) );
            return false;
        }

        return $response['value']['code'];
    }

    public function deletePaymentRequest( $payment_request_code ) {
        if ( empty( $payment_request_code ) ) {
            return false;
        }

        return $this->delete( 'payment-request/' . $payment_request_code );
    }
}
