=== EURD Payments for WooCommerce ===
Contributors: Sebta, Quantoz
Tags: woocommerce, payment gateway, eurd, payments, stablecoin
Requires at least: 4.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

WooCommerce gateway for EURD Stablecoin. Accept payments in Europe quickly and securely at your checkout page. The best thing of it: ZERO fees.

== Description ==
**WooCommerce EURD Payments Gateway** allows your WooCommerce store to accept EURD Stablecoin payments seamlessly. Generate secure payment requests as links or QR codes, simplifying checkout for your customers and enhancing transaction security.

### Key Features:

* ✅ **Secure Stablecoin Payments:** Instantly generate secure payment requests in EURD.
* ✅ **URL  Payment Links:**  Let customers pay by simply clicking on a link.
* ✅ **QR Code Payments:** Enhance checkout convenience by providing scannable QR codes.
* ✅ **Automatic Payment Confirmation:** Webhook integration for automatic order completion upon payment.
* ✅ **Order Status Management:** Automatically updates order status upon successful payments.
* ✅ **Robust API Integration:** Seamless integration with QuantozPay API for reliability and security.
* ✅ **Easy Setup:** Quickly configure the gateway settings directly within WooCommerce.

Simplify your Stablecoin payment process and provide your customers with an intuitive and secure checkout experience!


== Installation ==

1. Upload the `eurd-payments-wc` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce > Settings > Payments.
4. Enable the "EURD Payments Gateway" and configure your API credentials.

== External services ==

This plugin connects to the **QuantozPay** external payment service at  
`https://api.quantozpay.com/` to generate and confirm EURD Stablecoin payment requests.

**What it is & what it’s used for:**  
  The QuantozPay API is provided by QuantozPay B.V. (a Dutch Electronic Money Institution) and is used to:  
  1. **Create** payment requests (links or QR codes) for your WooCommerce orders.  
  2. **Verify** payment status via webhook callbacks so that orders can be marked “paid” automatically.

**What data is sent & when:**  
  1. **At checkout**: your WooCommerce **Order ID**, **total amount**, **currency**, and your store’s **callback URL** along with your **API key/secret**.  
  2. **On payment confirmation**: QuantozPay sends back the **transaction ID**, **paid amount**, **payment status**, and **order reference** to your configured webhook endpoint.

**Why & under which conditions:**  
  - To display a valid EURD payment link or QR code to the customer.  
  - To automatically update the WooCommerce order status when the customer’s EURD payment is received.

**Terms & Privacy:**  
  - Terms of Service: https://www.quantoz.com/terms-of-service  
  - Privacy Policy: https://www.quantoz.com/privacy-policy  

== External services ==

This plugin connects to the [QuantozPay API](https://api.quantozpay.com/) to generate and confirm EURD Stablecoin payment requests.

It sends your WooCommerce order ID, amount, currency, callback URL and API credentials when creating a payment link or QR code, and receives back the transaction ID, payment status and order reference via webhook to update the order automatically.

This service is provided by “QuantozPay B.V.”: [Terms of Service](https://www.quantoz.com/terms-of-service), [Privacy Policy](https://www.quantoz.com/privacy-policy)

== Frequently Asked Questions ==

= What is EURD? =
EURD is an instant, programmable euro that transfers with zero fees. It's the digital version of the euro, provided by Quantoz, a Dutch Electronic Money Institution. Individuals and businesses in Europe can hold and receive EURD on a wallet. You can read more about EURD here: [https://www.quantoz.com/products/eurd](https://www.quantoz.com/products/eurd)

= How do customers pay using EURD? =
Customers simply click on the payment link or scan the QR code generated at checkout with their EURD-compatible wallet and complete the payment.

= Is this gateway secure? =
Yes, payments are secured through encrypted API requests and webhook verifications.

= Is this gateway free? =
Yes, EURD payments and transfers are designed to be instant and with zero fees. Also there is not setup cost. You just sign up for free account, get TIER2 verified (TIER2 verified is a compliance status), and start accepting payments using this plugin on your store from your customers. 

= Do I need to create an account with QuantozPay? =
Yes, an account with QuantozPay is required to use this plugin. For account  creation please visit or install the EURD from the App or Play Store. [https://portal.quantozpay.com/](https://portal.quantozpay.com)

Also please note for being able to use this payment method on your store, you need to create Business account in QuantozPay, and be a TIER2 approved business to be able to accept EURD payments from customers. In order to do so, first create a Consumer account and then request to upgrade to a Business account. Follow the steps and provide the information needed.

= Are there  any limitations or restrictions? =
Yes currently it can be used  only with euro currency. The EURD is the digital version of the euro, provided by Quantoz. Thus you can exchange your received EURD for euro at Quantoz whenever you want. If your store uses any currency other than euro, you will not be able to use this payment method.

== Screenshots ==

1. Gateway settings in WooCommerce
2. Payment method "Pay with EURD" on order page
3. QR code payment request at checkout

== Changelog ==

= 1.0.0 =

* Initial release with secure EURD Stablecoin payment integration.

== Upgrade Notice ==

= 1.0.0 =
Initial plugin release.

== Support ==
For support, please contact us at [eurd@sebta.com](mailto:eurd@sebta.com).
