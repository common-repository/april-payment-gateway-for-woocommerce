<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * April_Payment_Action_Controller class.
 *
 */
class April_Payment_Action_Controller {

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		add_action( 'wc_ajax_wc_lp_payment_action', [ $this, 'handle_payment_action_completion' ] );
	}

	/**
	 * Returns an instantiated gateway.
	 *
	 * @return April payment gateway
	 */
	protected function get_april_gateway() {
		$methodId = April::APRIL_PAYMENT_METHOD_ID;
		$gateways = WC()->payment_gateways()->payment_gateways();
		$this->april_gateway = $gateways[ $methodId ];

		return $this->april_gateway;
	}

	/**
	 * Get the order from the GET request.
	 *
	 * @throws Exception If order doesn't exist.
	 * @return WC_Order
	 */
	protected function get_order_from_request() {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'wc_april_payment_action' ) ) {
			throw new Exception( esc_html( __( 'CSRF verification failed.', 'april-payment-gateway-for-woocommerce' ) ) );
		}

		$order_id = null;
		if ( isset( $_GET['order'] ) && absint( $_GET['order'] ) ) {
			$order_id = absint( $_GET['order'] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new Exception( esc_html( __( 'Missing order ID for payment confirmation', 'april-payment-gateway-for-woocommerce' ) ) );
		}

		return $order;
	}

	/**
	 * Handle payment action completion.
	 */
	public function handle_payment_action_completion() {
		global $woocommerce;

		$april_gateway = $this->get_april_gateway();

		try {
			$order = $this->get_order_from_request();
		} catch ( Exception $e ) {
			$message = sprintf( 'Payment verification error: %s', $e->getMessage() );
			$this->handle_error( $message );
			exit;
		}

		try {
			$resp = $april_gateway->complete_payment_action( $order );
			wp_send_json_success( $resp, 200 );
			exit;
		} catch ( Exception $e ) {
			$this->handle_error( $e->getMessage() );
			exit;
		}
	}

	/**
	 * Redirect on error
	 */
	protected function handle_error( $message ) {
		/* translators: $s is replaced with error message */
		$error_message = sprintf( __( 'Error: %s', 'april-payment-gateway-for-woocommerce' ), $message );
		wp_send_json_error(	['message' => $error_message] );
	}

}

new April_Payment_Action_Controller();
