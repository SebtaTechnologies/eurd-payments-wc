<?php

// Get WC Order by PaymentRequestId

function eurdpayfw_get_wc_order_by_payment_request_id($payment_request_id) {
    $meta_key   = 'eurd_pr_code';     
    $meta_value = $payment_request_id; 
    
    $orders = wc_get_orders( [
        'limit'      => 1,
        'meta_key'   => $meta_key,
        'meta_value' => $meta_value,
    ] );
    
    if ( ! empty( $orders ) ) {
        $order   = $orders[0];
        return $order->get_id();       
    }
    
    return false; // Order not found
}