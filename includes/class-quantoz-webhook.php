<?php

defined('ABSPATH') || exit; // Prevent direct access
include_once 'class-wc-eurd-helper.php';

add_action('rest_api_init', function () {
    register_rest_route('quantoz/v1', '/webhook/', [
        'methods'             => ['POST', 'GET'],
        'callback'            => 'eurdpayfw_quantoz_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Webhook Handler Function
 */
function eurdpayfw_quantoz_handle_webhook(WP_REST_Request $request) {
  
#
    $wc_logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : null;

    if (is_null($wc_logger)) {
        $wc_logger = new WC_Logger();
    }
    // Parse & sanitize JSON payload
    $body    = $request->get_body(); // raw JSON
    $data    = json_decode( wp_unslash( $body ), true );
    if ( empty( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
        $wc_logger->debug( 'Quantoz Webhook: ERROR: Invalid or empty JSON body', [
            'source' => 'eurd-quantoz-webhook',
        ] );
        return rest_ensure_response( [
            'error' => esc_html__( 'Invalid or empty JSON payload', 'eurd-payments-wc' ),
        ] , 400 );
    }

    // Log the webhook event if debugging is enabled or woocommece store is in coming soon mode
    $woocommerce_coming_soon = get_option( 'woocommerce_coming_soon', false );
    if ( (defined( 'WP_DEBUG' ) && WP_DEBUG) || $woocommerce_coming_soon ) {
         $wc_logger->debug( 'Quantoz Webhook received:      ', [
            'source'  => 'eurd-quantoz-webhook',
            'payload' => $data,
        ] );
    }

    // Validate required fields exist
    if (
        ! isset( $data['code'], $data['type'] )
        || ! isset( $data['content']['PaymentRequestCode'] )
        || ! isset( $data['content']['Payment']['TransactionCode'] )
    ) {
        $wc_logger->debug( 'Quantoz Webhook VALIDATION ERROR: missing required field(s)', [
            'source'  => 'eurd-quantoz-webhook',
            'payload' => $data,
        ] );
        return rest_ensure_response( [
            'error' => esc_html__( 'Validation error: missing required field(s)', 'eurd-payments-wc' ),
        ], 422 );
    }

    // Sanitize input values
    $payment_req_code  = sanitize_text_field( $data['content']['PaymentRequestCode'] );
    $transaction_code  = sanitize_text_field( $data['content']['Payment']['TransactionCode'] );
    $type              = sanitize_text_field( $data['type'] );

    // Lookup order by payment request
    $order_id = eurdpayfw_get_wc_order_by_payment_request_id( $payment_req_code );
    if ( ! $order_id ) {
        $wc_logger->debug( sprintf(
            'Webhook ERROR: Order not found for PaymentRequestId %s',
            $payment_req_code
        ), [ 'source' => 'eurd-quantoz-webhook' ] );
        return rest_ensure_response( [
            'error' => esc_html__( 'Order not found', 'eurd-payments-wc' ),
        ], 404 );
    }

    // Handle based on webhook type
    switch ( $type ) {
        case 'PaymentRequestPaid':
            /**
             * Fires when a payment request has been marked as paid on the Quantoz side.
             *
             * @param int    $order_id
             * @param string $payment_req_code
             * @param string $transaction_code
             */
            do_action( 'quantoz_payment_request_paid_verify', $order_id, $payment_req_code, $transaction_code );
            break;

        default:
            $wc_logger->info( sprintf(
                'Webhook WARNING: Unknown type "%s" for PaymentRequestId %s',
                $type,
                $payment_req_code
            ), [ 'source' => 'eurd-quantoz-webhook' ] );
            break;
    }

    return rest_ensure_response( [
        'message' => 'Webhook received successfully',
    ], 200 );
}
