<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function april_get_bnpl_installment_amount( $amount ) {
  return ( $amount / 4 );
}

function april_enqueue_related_pages_scripts_and_styles() {
  wp_enqueue_style( 'april-installment-style', plugins_url( '../public/css/april-installment-show.css', __FILE__ ) );
}

add_action( 'wp_enqueue_scripts','april_enqueue_related_pages_scripts_and_styles' );

function april_installment_script() {
  wp_enqueue_script('april-installment-show', plugins_url( '../public/js/april-installment-show.js', __FILE__ ), array( 'jquery' ), true );
}

add_action( 'wp_enqueue_scripts', 'april_installment_script' );

/* April Shortcodes */
/*
@params ['amount']
==========================
Default Values:
amount: cartAmount
*/

function april_bnpl_toggle_shortcode( $params ) {
  global $woocommerce;

  $april_one_time_class = 'active';
  $april_split_payment_class = null;
  $is_option_checked = null;
  $payplan_disabled_class = null;
  $bnpl_default_amount = "cartAmount";

  $amount = array_key_exists( 'amount', $params ) && !empty( $params['amount'] ) ? $params['amount'] : $bnpl_default_amount;
  $cart_amount = $amount == "cartAmount" || !is_numeric( $amount ) ? $woocommerce->cart->total : $amount;

  if ( $cart_amount <= 0 ) {
      return;
  }

  if( !empty( $_COOKIE['april-preferred-bnpl-option'] ) ) {
      $april_one_time_class = null;
      $april_split_payment_class = 'active';
      $is_option_checked = 'checked';
  }

  $html = '<div class="april-switcher-toggle-container april-toggle-container '. esc_html( $payplan_disabled_class ) .'">
			<div class="april-one-time payment-type '. esc_html( $april_one_time_class ) .'">
				<h6>One Time Payment</h6>
				<div class="payment-amt"><span>' . wc_price( $cart_amount ) . '</span></div>
			</div>
			<div class="april-switcher">
				<label class="switch">
					<input type="checkbox" id="aprilInstallmentSwitch" '. esc_html( $is_option_checked ) .'>
					<span class="slider round"></span>
				</label>
			</div>
			<div class="april-split-payment payment-type '. esc_html( $april_split_payment_class ) . '">
				<h6>4 <strong>Interest Free</strong> Payments of</h6>
				<div class="payment-amt"><span>' . wc_price( april_get_bnpl_installment_amount( $cart_amount ) ) . '</span></div>
			</div>
		</div>';

  return $html;
}

add_shortcode('april_bnpl_toggle', 'april_bnpl_toggle_shortcode');

/*
@params ['amount']
==========================
Default Values:
amount: productPrice
*/

function april_product_bnpl_price_shortcode( $params ) {
  global $woocommerce;

  $bnpl_default_amount = "productPrice";
  $amount = is_array( $params ) && array_key_exists( 'amount', $params ) && !empty( $params['amount'] ) ? $params['amount'] : $bnpl_default_amount;
  $product = wc_get_product( get_the_ID() );

  $actual_product_price = empty( $product ) ? 0.00 : $product->get_price();
  $product_price = !empty( $amount ) && $amount !== "productPrice" && is_numeric( $amount ) ? $amount : $actual_product_price;

  if ($product_price <= 0) {
      return;
  }
  $formatted_price = wc_price( april_get_bnpl_installment_amount( $product_price ) );
  $html = '<div class="april_installment_offer april-installment-offer__shortcode">
			<div class="grey-option-text"><span>or</span></div>
			<div class="april-installment-price">' . __( '4 <strong>Interest Free</strong> Payments of ', 'april-payment-gateway-for-woocommerce' ) . '<span class="formatted-installment-amt">' . $formatted_price . '</span></div>
		</div>';

  return $html;
}

add_shortcode( 'april_product_bnpl_price', 'april_product_bnpl_price_shortcode' );

/*
@params ['amount']
==========================
Default Values:
amount: productPrice
*/

function april_product_bnpl_toggle_shortcode( $params ) {
  $product = wc_get_product( get_the_ID() );
  $bnpl_default_amount = 'productPrice';
  $is_option_checked = null;
  $april_split_payment_class = null;

  $amount = is_array( $params ) && array_key_exists( 'amount', $params ) && !empty( $params['amount'] ) ? $params['amount'] : $bnpl_default_amount;
  $actual_product_price = empty( $product ) ? 0.00 : $product->get_price();
  $product_price = !empty( $amount ) && $amount !== "productPrice" && is_numeric( $amount ) ? $amount : $actual_product_price;

  if ($product_price <= 0) {
    return;
  }

  if( !empty($_COOKIE['april-preferred-bnpl-option']) ) {
    $april_split_payment_class = 'active';
    $is_option_checked = 'checked';
  }

  $formatted_price = wc_price( april_get_bnpl_installment_amount( $product_price ) );
  $html = '<div class="april_installment_offer april-toggle-container april-installment-offer__toggle april-installment-offer__shortcode">
  		<div class="april-switch-container">
  			<div class="april-switcher">
  				<label class="switch">
  					<input type="checkbox" id="aprilInstallmentSwitch" '. esc_html( $is_option_checked ) .'>
  					<span class="slider round"></span>
  				</label>
  			</div>
  		</div>
  		<div class="april-installment-price '. esc_html( $april_split_payment_class ) . '">' . __( '4 <strong>Interest Free</strong> Payments of ', 'april-payment-gateway-for-woocommerce' ) . '<span class="formatted-installment-amt">' . $formatted_price . '</span></div>
  	</div>';

  return $html;
}

add_shortcode('april_product_bnpl_toggle', 'april_product_bnpl_toggle_shortcode');
