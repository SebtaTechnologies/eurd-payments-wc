(function($) {
  "use strict";

  $ = $ || window.jQuery;
  // If jQuery isn't loaded, bail out and log an error.
  if (typeof $ === "undefined") {
    console.error("[EURD] jQuery missing - aborting.");
    return;
  }

  // Run when DOM is ready
  $(function() {
    // Ensure your data object is present
    if (typeof EURDPAYFWData === "undefined") {
      console.warn("[EURD] EURDPAYFWData missing - aborting.");
      return;
    }

    // 1) Generate QR if the container exists
    if ($("#eurd-payment-qr-code").length) {
      try {
        new QRCode("eurd-payment-qr-code", {
          text:        buildPaymentUrl(EURDPAYFWData.eurd_payment_request_code),
          width:       200,
          height:      200,
          correctLevel: QRCode.CorrectLevel.H,
          quietZone:    8
        });
      } catch (err) {
        console.error("[EURD] Failed to generate QR code:", err);
      }
    }

    setTimeout(function() {
        $('#manualPaymentConfirmBtn').fadeIn();
    }, 10000); // 30000 milliseconds = 30 seconds

    // 2) Kick off polling
    try {
      startOrderPolling($, {
        ajax_url:    EURDPAYFWData.ajax_url,
        order_id:    EURDPAYFWData.order_id,
        nonce:       EURDPAYFWData.nonce,
        confirm_url: EURDPAYFWData.confirm_url
      }, 5000);
    } catch (err) {
      console.error("[EURD] startOrderPolling error:", err);
    }

    // 3) Return-to-payment link
    $("body").on("click", ".EURDPAYFW-return-link", function(e) {
      e.preventDefault();
      const url = EURDPAYFWData.payment_url +
                  "&eurd_payment_request_code=" +
                  encodeURIComponent(EURDPAYFWData.eurd_payment_request_code);
      window.location.href = url;
    });

    // 4) Confirm-payment button
    $("body").on("click", "#EURDPAYFW-confirm-payment", function(e) {
      e.preventDefault();
      window.location.href = EURDPAYFWData.confirm_url;
    });

    // 5) Update link href
    $("#eurd-payment-url").attr("href", EURDPAYFWData.eurd_payment_url);
  });

})(window.jQuery);


// paymentLib.js
// ---------------------------

/**
 * Build a payment URL by combining the base URL with the provided suffix.
 * @param {string} urlSuffix
 * @returns {string}
 */
function buildPaymentUrl(urlSuffix) {
  const baseUrl = "https://pay.quantozpay.com/";
  return baseUrl + urlSuffix;
}

/**
 * Display a centered overlay with spinner + message.
 * Ensures only one notice is ever added.
 */
function showRedirectNotice() {
  if (document.querySelector("#eurd-redirect-notice")) return;

  document.body.insertAdjacentHTML("beforeend", `
    <div id="eurd-redirect-notice">
      <div class="spinner"></div>
      <span>✓ Payment received — redirecting now…</span>
    </div>
    <style>
      @keyframes eurd-spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
      }
      #eurd-redirect-notice {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.85);
        color: #fff;
        padding: 20px 30px;
        border-radius: 8px;
        display: none;
        align-items: center;
        font-size: 16px;
        z-index: 100000;
      }
      #eurd-redirect-notice .spinner {
        width: 20px; height: 20px;
        margin-right: 12px;
        border: 3px solid #fff;
        border-top: 3px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        animation: eurd-spin 1s linear infinite;
      }
    </style>
  `);

  const notice = document.querySelector("#eurd-redirect-notice");
  jQuery(notice).fadeIn(200);
}

/**
 * Begin polling the server for order status.
 * @param {object} config   Must include ajax_url, order_id, nonce, confirm_url
 * @param {number} interval Poll interval in ms
 */
function startOrderPolling(jQuery, config, interval = 5000) {
  console.info("[EURD] Polling every", interval, "ms for order", config.order_id);

  const poller = setInterval(() => {
    console.debug("[EURD] Checking order status…");
    jQuery.post(
      config.ajax_url,
      {
        action:       "eurd_check_order_status",
        order_id:     config.order_id,
        EURDPAYFW_nonce: config.nonce
      },
      (response) => {
        if (!response.success) {
          console.error("[EURD] Poll error:", response);
          return;
        }
        const status = response.data.status;
        console.debug("[EURD] Status:", status);

        // WooCommerce order statuses
        // https://woocommerce.github.io/code-reference/classes/WC-Order.html#method_get_status
        if ( status === "completed" || status === "processing" ) {
          clearInterval(poller);
          showRedirectNotice();
          setTimeout(() => {
            console.info("[EURD] Redirecting now");
            window.location.href = config.confirm_url;
          }, 2000);
        }
      },
      "json"
    ).fail((xhr, textStatus, err) => {
      console.error("[EURD] AJAX failed:", textStatus, err);
    });
  }, interval);

  return () => clearInterval(poller); // returns a stop function if needed
}
