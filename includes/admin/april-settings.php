<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$april_settings = array(
  'enabled'         => array(
    'title'		      => __( 'Enable / Disable', 'april-payment-gateway-for-woocommerce' ),
    'label'		      => __( 'Enable this payment gateway', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'checkbox',
    'default'	      => 'no',
  ),
  'title'           => array(
    'title'		      => __( 'Title', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'text',
    'desc_tip'	    => __( 'Payment title the customer will see during the checkout process.', 'april-payment-gateway-for-woocommerce' ),
    'default'	      => __( 'Card Payment or Payment Plan', 'april-payment-gateway-for-woocommerce' ),
  ),
  'description'     => array(
    'title'		      => __( 'Description', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'textarea',
    'desc_tip'	    => __( 'Payment description the customer will see during the checkout process.', 'april-payment-gateway-for-woocommerce' ),
    'default'	      => __( 'Credit, debit or Amex card - Full payment or Payment plan.', 'april-payment-gateway-for-woocommerce' ),
    'css'		        => 'max-width:350px;'
  ),
  'publishable_key' => array(
    'title'		      => __( 'Publishable Key', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'text',
    'desc_tip'	    => __( 'This key is provided to you by April.', 'april-payment-gateway-for-woocommerce' ),
  ),
  'secret_key'      => array(
    'title'		      => __( 'Secret Key', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'text',
    'desc_tip'	    => __( 'This key is provided to you by April.', 'april-payment-gateway-for-woocommerce' ),
  ),
  'payment_option'  => array(
    'title'		      => __( 'Available payment options', 'april-payment-gateway-for-woocommerce' ),
    'type'          => 'select',
    'description'   => __( 'Allows to provide only one payment option at checkout.', 'april-payment-gateway-for-woocommerce' ),
    'default'       => '0',
    'desc_tip'      => true,
    'options'       => array(
        '0'		      => __( 'Full payment & split payment', 'april-payment-gateway-for-woocommerce' ),
        'paycard'   => __( 'Full payment only', 'april-payment-gateway-for-woocommerce' ),
        'payplan'   => __( 'Split payment only', 'april-payment-gateway-for-woocommerce' ),
    ),
  ),
  'hide_icon'     => array(
    'title'		      => __( 'Hide cards image', 'april-payment-gateway-for-woocommerce' ),
    'label'         => ' ',
    'type'          => 'checkbox',
    'default'       => 'no',
    'desc_tip'      => true,
  ),
  'request_3ds'     => array(
    'title'		      => __( 'Request 3DS on payments', 'april-payment-gateway-for-woocommerce' ),
    'label'         => ' ',
    'type'          => 'checkbox',
    'default'       => 'no',
    'desc_tip'      => true,
  ),
  'minimum_amount_3ds'      => array(
    'title'		      => __( 'Minimum Amount for 3DS', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'text',
    'desc_tip'	    => __( 'Minimum amount to request 3DS.', 'april-payment-gateway-for-woocommerce' ),
  ),
  'primary_color'      => array(
    'title'		      => __( 'Primary Color (hex code)', 'april-payment-gateway-for-woocommerce' ),
    'type'		      => 'text',
    'desc_tip'	    => __( 'Primary color of checkout.', 'april-payment-gateway-for-woocommerce' ),
  ),
  'wallet_payments_place_order' => array(
    'title'		      => __( 'Allow Wallet payments to place the order', 'april-payment-gateway-for-woocommerce' ),
    'label'         => 'Automatically place the order with Wallet payments',
    'description'   => __( 'Submit orders immediately when a digital wallet payment such as Apple Pay or Google Pay is selected.', 'april-payment-gateway-for-woocommerce' ),
    'type'          => 'checkbox',
    'default'       => 'no',
    'desc_tip'      => true,
  ),
);

return $april_settings;
