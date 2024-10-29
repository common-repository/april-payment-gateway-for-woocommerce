<?php
/*
Plugin Name: April Payment Gateway for WooCommerce
Plugin URI: https://docs.meetapril.io/developer-portal/checkout/woocommerce/
Description: Accept April payments on your Wordpress - WooCommerce site
Version: 1.0.3
Author: April
Text Domain: april-payment-gateway-for-woocommerce
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'APRIL_VERSION', '1.0.3' );
define( 'APRIL_CHECKOUTJS', 'https://checkout-v3.au.meetapril.io/v3/checkout-v3.0.0.min.js' );
define( 'APRIL_CHECKOUTJS_VERSION', '3.0.0' );

define( 'APRIL_GATEWAY_FILE', __FILE__ );
define( 'APRIL_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

add_action( 'plugins_loaded', 'april_init', 11 );
function april_init() {
  // If the parent WC_Payment_Gateway class doesn't exist
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

  // Subscriptions
  require_once dirname( __FILE__ ) . '/includes/traits/april-order-pay.php';
  require_once dirname( __FILE__ ) . '/includes/traits/april-subscriptions.php';

  // Include April Gateway Class
  include_once( dirname( __FILE__ ) . '/includes/woocommerce-april.php' );
  // Include April API Class
  include_once( dirname( __FILE__ ) . '/includes/april-api.php' );
  // Include April paymentaction controller
  require_once( dirname( __FILE__ ) . '/includes/payment-action-controller.php');
  // Include April Widgets
  require_once( dirname( __FILE__ ) . '/includes/april-widgets.php');
  // Include April Helper
  require_once( dirname( __FILE__ ) . '/includes/helper.php');
  // Include April Customer Class
  require_once( dirname( __FILE__ ) . '/includes/april-customer.php');

  // Add April Gateway too WooCommerce
  add_filter( 'woocommerce_payment_gateways', 'add_april_gateway' );
  function add_april_gateway( $methods ) {
    $methods[] = 'April';
    return $methods;
  }
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'april_action_links' );

function april_action_links( $links ) {
  $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'april-payment-gateway-for-woocommerce' ) . '</a>',
  );

  return array_merge( $plugin_links, $links );
}
