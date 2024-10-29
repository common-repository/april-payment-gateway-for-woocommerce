<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait April_Subscriptions_Trait {

	/**
	 * Subscription support.
	 */
	public function init_subscriptions() {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$this->supports = array_merge(
			$this->supports,
			[
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			]
		);

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );
	}

	/**
	 * Payment method change.
	 *
	 * @param int $order_id
	 * @return bool
	 */
	public function change_subscription_payment_method( $order_id ) {
		return (
			$this->is_subscriptions_enabled() &&
			$this->has_subscription( $order_id ) &&
			$this->is_changing_payment_method_for_subscription()
		);
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @param int $order_id
	 * @return array|null
	 */
	public function process_change_subscription_payment_method( $order_id, $payment_source ) {
		try {
			$subscription    = wc_get_order( $order_id );
			$this->save_source_to_order( $subscription, $payment_source );

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			];
		} catch ( Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
		}
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
	 	$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}

	/**
	 * Process_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object.
	 */
	public function process_subscription_payment( $amount, $renewal_order ) {
		try {
			$order_id = $renewal_order->get_id();

			if ( isset( $_REQUEST['process_early_renewal'] ) && 'april' === $this->id ) {
				return $this->process_payment( $order_id );
			}

			// Get source from order
			$source = $this->prepare_order_source( $renewal_order );

			April_Helper::write_log('process_subscription_payment executed for order: ' . $order_id);

			$payment_token = $this->generate_payment_token( $source, $amount, get_woocommerce_currency() );
			April_Helper::write_log('process_subscription_payment payment token: ' . $payment_token);

			$april_merchant_order_id = $this->create_april_order( $order_id, $amount );
			$pay_resp = $this->pay_for_april_order( $april_merchant_order_id, $payment_token, null, false, false );

			if ( $pay_resp['success'] ) {
				$pay_data = $pay_resp['response'];
				$renewal_order->set_transaction_id( $pay_data['transactionId'] );
				$renewal_order->add_order_note( 'Subscription payment captured successfully.' );
				$renewal_order->payment_complete();
				April_Helper::write_log('process_subscription_payment success');
			} else {
				$error_msg = $this->get_error_message( $pay_resp, "An unknown error occurred while processing the payment with April");
				April_Helper::write_log('process_subscription_payment error: ' . $error_msg);
				throw new Exception( $error_msg );
			}

		} catch ( Exception $e ) {
			$renewal_order->add_order_note( $e->getMessage() );
			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Updates subscription source.
	 */
	public function update_source_on_subscription_order( $order, $source ) {
		if ( ! $this->is_subscriptions_enabled() ) {
			return;
		}

		$order_id = $order->get_id();

		// Also store it on the subscriptions being purchased or paid for in the order
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		} else {
			$subscriptions = [];
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription_id = $subscription->get_id();
			update_post_meta( $subscription_id, '_april_source_id', $source );
		}
	}

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public function is_subscriptions_enabled() {
		return class_exists( 'WC_Subscriptions' ) && version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
	}

	/**
	 * Is $order_id a subscription?
	 *
	 * @param  int $order_id
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Returns whether this user is changing the payment method for a subscription.
	 *
	 * @return bool
	 */
	public function is_changing_payment_method_for_subscription() {
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wcs_is_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return false;
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( $this->is_subscriptions_enabled() ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || $this->cart_contains_renewal();
		}
		return false;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @return mixed The cart item containing the renewal as an array, else false.
	 */
	public function cart_contains_renewal() {
		if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
			return false;
		}
		return wcs_cart_contains_renewal();
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_non_subscription_item_in_cart() {
		global $woocommerce;
    $items = $woocommerce->cart->get_cart();

    foreach($items as $item => $values) {
        $_product =  wc_get_product( $values['data']->get_id() );
				if ( $_product->get_type() !== 'subscription' && $_product->get_type() !== 'variable-subscription' ) {
						return true;
				}
    }
		return false;
	}

}
