<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait April_Order_Pay_Trait {


  /**
	 * Remove payment gateways from order pay page to show 3DS modal
	 *
	 * @param WC_Payment_Gateway[]
	 * @return WC_Payment_Gateway[]
	 */
	public function remove_order_pay_page_gateways( $gateways ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) || ! isset( $_GET['wc-april-payment-action'] ) ) {
			return $gateways;
		}

		add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		add_filter( 'woocommerce_pay_order_button_html', '__return_false' );
		add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array' );
		add_filter( 'woocommerce_no_available_payment_methods_message', [ $this, 'change_no_available_methods_message' ] );
		add_action( 'woocommerce_pay_order_after_submit', [ $this, 'render_payment_action_inputs' ] );

		return [];
	}

  /**
   * Add "wc-april-payment-action" parameter to payment url
   *
   * @param string   $pay_url Current computed checkout URL for the given order.
   * @param WC_Order $order Order object.
   *
   * @return string Checkout URL for the given order.
   */
  public function get_checkout_payment_url( $pay_url, $order ) {
    global $wp;
    if ( isset( $_GET['wc-april-payment-action'] ) && isset( $wp->query_vars['order-pay'] ) && $wp->query_vars['order-pay'] == $order->get_id() ) {
      $pay_url = add_query_arg( 'wc-april-payment-action', 1, $pay_url );
    }
    return $pay_url;
  }

	/**
	 * Renders hidden inputs to store payment action and payment token data.
	 *
	 * @param WC_Order|null $order Order object, or null to get the order from the "order-pay" URL parameter
	 *
	 */
	public function render_payment_action_inputs( $order = null ) {
		if ( ! isset( $order ) || empty( $order ) ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
		}

    $payment_data = $this->get_payment_data_from_order( $order );
    $pay_action_url = $this->build_action_url( $order->get_id(), $this->get_return_url( $order ) );

		echo '<input type="hidden" id="' . esc_attr( $this->method_name ) . '-payment-action-url" value="' . esc_attr( $pay_action_url ) . '" />';
		echo '<input type="hidden" id="' . esc_attr( $this->method_name ) . '-payment-action" value="' . esc_attr( $payment_data->payment_action ) . '" />';
	}

  /**
   * Change "No available methods" message
   *
   * @return string the new message.
   */
  public function change_no_available_methods_message() {
    return wpautop( __( "Authorize payment!", 'april-payment-gateway-for-woocommerce' ) );
  }

	public function is_order_pay() {
		return is_wc_endpoint_url( 'order-pay' );
	}
}
