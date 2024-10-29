=== April Payment Gateway for WooCommerce ===
Contributors: april
Tags: Split Payments, Checkout, Credit card, Debit card
Tested up to: 6.5.3
Stable tag: 1.0.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Woo-Commerce gateway extension to support April payments


== Description ==
Integrates April Payments Gateway into WooCommerce site. It allows merchants to accept Credit/Debit card payments on their store. April allows merchants to enable 3DS protection for the transactions. For signed in users, April allows to re-use their last used card making the payment process more efficient. Also customers will be provided with option to split their payment in to multiple instalments at the checkout.

This plugin utilises following April APIs in order to create orders, pay and refund.
- https://api.au.meetapril.io/
- https://api.sandbox.au.meetapril.io/ (For testing)

In the checkout it embed the https://checkout-v3.au.meetapril.io/v3/checkout-v3.0.0.min.js Javascript module which allows the plugin to render the April Payment option.

Click [here to check the April Privacy Policy page](https://meetapril.com/privacy/)

== Installation ==
Make sure WooCommerce plugin is installed and activated.
1. Obtain API Access keys from April
2. Install April Payment Gateway for WooCommerce
3. Activate the plugin
4. From WooCommerce -> Settings -> Payments tab, open April Payments setup page
5. Enter you Publishable key and Secret key provided by April and enable the gateway
6. Check the [plugin documentation](https://docs.meetapril.io/developer-portal/checkout/woocommerce/) for more details on configurations.

== Enable ApplePay and GooglePay ==
To enable ApplePay and GooglePay, please follow the instructions available in your April Merchant Dashboard's Settings -> Configurations -> Checkout payment options section.

== WooCommerce Subscriptions support ==
April Payments Gateway supports WooCommerce Subscriptions. It allows customers to set payment methods for subscription checkouts.

== Change Log ==


= v1.0.3 =
*Release Date: 27th June 2024*

1. Removed inline styles from widgets
2. Stopped echoing js code
3. Sanitised and validated user data
4. Made constant names unique
5. Added translator comment
6. Escaped exception messages

= v1.0.2 =
*Release Date: 4th June 2024*

1. Escape echo variable
2. Remooved SSL check
3. Removed ApplePay domain association file
4. Fixed internationalization functions

= v1.0.1 =
*Release Date: 10th May 2024*

1. Prevented direct php file Access
2. Validate, escape and sanitise user data
3. Removed tst and dev environment support
4. Fixed licence version and url mismatch
5. Fixed stable tag version
6. Updated ApplePay domain verification file

= v1.0.0 =
*Release Date: 15th January 2024*

1. First release of April plugin
