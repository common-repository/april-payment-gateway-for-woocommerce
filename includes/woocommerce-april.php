<?php
/* April Payment Gateway Class */

class April extends WC_Payment_Gateway {

  use April_Subscriptions_Trait;
	use April_Order_Pay_Trait;

  const APRIL_PAYMENT_METHOD_ID = 'april';

  // Constructor
  function __construct() {

    $this->id = 'april';
    $this->method_name = 'april';
    $this->method_title = __( 'April', 'april-payment-gateway-for-woocommerce' );
    $this->method_description = __( 'Let customer pay in full or with flexible instalments. No third-party branding.', 'april-payment-gateway-for-woocommerce' );
    $this->description = ' ';

    if ( 'yes' !== $this->get_option( 'hide_icon' ) ) {
      $this->icon = plugins_url( 'public/images/card-icons-list.svg', APRIL_GATEWAY_FILE );
    }

    $this->has_fields = false;
    $this->supports = array_merge(
      $this->supports, [ 'refunds' ]
    );

    $this->init_form_fields();

    $this->init_settings();

    // Settings
    $this->title            = $this->get_option( 'title' );
    $this->publishable_key  = $this->get_option( 'publishable_key' );
    $this->secret_key       = $this->get_option( 'secret_key' );
    $this->payment_option   = $this->get_option( 'payment_option' );
    $this->request_3ds      = 'yes' === $this->get_option( 'request_3ds' );
    $this->minimum_amount_3ds   = $this->get_option( 'minimum_amount_3ds' );
    $this->primary_color    = $this->get_option( 'primary_color' );
    $this->prevent_wallet_submit = !('yes' === $this->get_option( 'wallet_payments_place_order' ));

    April_API::set_secret_key( $this->secret_key );

    // Check if subscriptions are enabled and add support for them.
    $this->init_subscriptions();

    // Queue script files
    add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

    // Repurpose order pay page for 3DS challenge
    add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_order_pay_page_gateways' ] );

    // Hook to modify process_payment result
    add_filter( 'woocommerce_payment_successful_result', [ $this, 'update_successful_payment_response' ], 99999, 2 );
    add_filter( 'woocommerce_get_checkout_payment_url', [ $this, 'get_checkout_payment_url' ], 10, 2 );

    // Potential fix where on some sites get_woocommerce_currency() returns null.
    add_filter('woocommerce_currency', function($currency) {
    	return $currency ?? get_option('woocommerce_currency');
    }, 9999);

    // Save settings
    if ( is_admin() ) {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }
  }

  /**
	 * Init admin setting form
	 *
	 * @return void
	 */
  public function init_form_fields() {
    $this->form_fields = require dirname( __FILE__ ) . "/admin/{$this->method_name}-settings.php";
  }

  protected function get_error_message($resp, $alt_message) {
    $error_msg = $alt_message;
    try {
    	$body = json_decode($resp['body'], true);
      if ( array_key_exists( 'errorCode', $body ) ) {
          $error_msg = April_Helper::get_api_error_message( $body );
      }
    } catch( Exception $e) {
      return __( 'Unknown error occured', 'april-payment-gateway-for-woocommerce' );
    }

    return $error_msg;
  }

  /**
	 * Render April checkout placeholder
	 *
	 * @return void
	 */
  public function payment_fields() {
    // Added ob_start(), ob_end_flush() as a potential fix for some sites not rendering this section.
    ob_start();

    echo '<input type="hidden" id="' . esc_html('wc_' . $this->id . '_params_amount') . '" name="' . esc_html('wc_' . $this->id . '_params_amount') . '" value="' . esc_html(strval(April_Helper::convert_to_cents($this->get_order_total()))) . '" >';
    echo '<input type="hidden" id="' . esc_html('wc_' . $this->id . '_params_currency') . '" name="' . esc_html( 'wc_' . $this->id . '_params_currency' ) . '" value="' . esc_html(strval(get_woocommerce_currency())) . '" >';
    echo '<p>' . esc_html( $this->get_option( 'description' ) ) . '</p>';
    echo '<div id="' . esc_attr( $this->method_name ) . '-placeholder"></div>';

    ob_end_flush();
  }

  /**
	 * Prepare order data for order creation request
	 *
	 * @param int  $order_id Reference.
	 * @return array
	 */
  protected function get_order_data( $order_id, $amount = null ) {
    $order = new WC_Order( $order_id );
    $currency = get_woocommerce_currency();
    $current_user = wp_get_current_user();
    $order_total = April_Helper::convert_to_cents( !empty( $amount ) ? $amount : $this->get_order_total() );
    $order_items = $order->get_items();
    $april_items = array();

    foreach( $order_items as $order_item ) {
      $product_id = $order_item['product_id'];
      $product = wc_get_product( $product_id );
      $attachment_ids = $product->get_gallery_image_ids();
      $imgUrl = '';

      if (count($attachment_ids) > 0) {
          $imgUrl = wp_get_attachment_url($attachment_ids[0]);
      }

      $new_item = array(
          'amount' => array(
            'minorCurrencyUnits' => April_Helper::convert_to_cents($order_item['total']),
            'currency' => strval($currency)
          ),
          'description' => strval($order_item['name']),
          'sku' => strval($product->get_sku()),
          'quantity' => round($order_item['qty']),
          'imageUrl' => strval($imgUrl ? $imgUrl : '')
      );
      array_push($april_items, $new_item);
    }

    $description = ( count( $april_items ) > 0 ? strval( count( $april_items ) ) : 'No' ) . ' item(s)';

    $order_data = array(
      'referenceOrderId' => strval( $order_id ),
      'amount' => array( 'minorCurrencyUnits' => $order_total, 'currency' => $currency ),
      'description' => $description,
      'items' => $april_items,
      'discountAmount' => April_Helper::convert_to_cents( $order->get_total_discount() ),
      'shipping' => array(
        'amount' => April_Helper::convert_to_cents( $order->get_total_shipping() ),
        'address' => array(
          'line1' => strval( $order->get_shipping_address_1() ),
          'line2' => strval( $order->get_shipping_address_2() ),
          'city' => strval( $order->get_shipping_city() ),
          'state' => strval( $order->get_shipping_state() ),
          'postalCode' => strval( $order->get_shipping_postcode() ),
          'country' => strval( $order->get_shipping_country() )
        ),
        'name' => strval( $order->get_shipping_first_name() ) . ' ' . strval( $order->get_shipping_last_name() )
      ),
      'billing' => array(
        'address' => array(
          'line1' => strval( $order->get_billing_address_1() ),
          'line2' => strval( $order->get_billing_address_2() ),
          'city' => strval( $order->get_billing_city() ),
          'state' => strval( $order->get_billing_state() ),
          'postalCode' => strval( $order->get_billing_postcode() ),
          'country' => strval( $order->get_billing_country() )
        ),
        'name' => strval( $order->get_billing_first_name() ) . ' ' . strval( $order->get_billing_last_name() ),
        'phoneNumber' => strval( $order->get_billing_phone() )
      )
    );

    if ( $current_user->exists() ) {
      $order_data['customerEmailAddress'] = $current_user->user_email;
    }

    return $order_data;
  }

  /**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @throws Exception If order creation and order payment fails.
	 * @return array|void
	 */
  public function process_payment( $order_id ) {
    April_Helper::write_log( 'process_payment for order: ' . $order_id );

    $order = new WC_Order( $order_id );
    $payment_token = array_key_exists( $this->id . '_payment_token', $_POST ) ? April_Helper::sanitize_validate_payment_token( $_POST[$this->id . '_payment_token'] ) : null;
    $payment_data = array_key_exists( $this->id . '_payment_data', $_POST ) ? April_Helper::sanitize_validate_payment_data_string( $_POST[$this->id . '_payment_data'] ) : null;
    $payment_source = array_key_exists( $this->id . '_payment_source', $_POST ) ? April_Helper::sanitize_validate_payment_source( $_POST[$this->id . '_payment_source'] ) : null;

    if ( ! $this->secret_key ) {
      $msg = 'April secret key is not provided.';
      April_Helper::write_log( $msg );
      throw new Exception( esc_html__( 'April secret key is not provided.', 'april-payment-gateway-for-woocommerce' ) );
    }

    if ( ! $payment_token && ! $payment_source ) {
      $msg = 'Transaction incomplete. No payment token or payment source found.';
      April_Helper::write_log( $msg );
      throw new Exception( esc_html__( 'Transaction incomplete. No payment token or payment source found.', 'april-payment-gateway-for-woocommerce' ) );
    }

    $has_subscriptions = $this->has_subscription( $order_id );
    if ( $has_subscriptions && !$payment_source ) {
      $msg = 'No payment source found.';
      April_Helper::write_log( $msg );
      throw new Exception( esc_html__( 'No payment source found.', 'april-payment-gateway-for-woocommerce' ) );
    }

    if ( $this->change_subscription_payment_method( $order_id ) ) {
      $msg = 'Changing subscription payment method for order: ' . $order_id . ' payment source: ' . $payment_source;
      April_Helper::write_log( $msg );
      return $this->process_change_subscription_payment_method( $order_id, $payment_source );
    }

    try {
      if ( $has_subscriptions ) {
        $this->save_source_to_order( $order, $payment_source );
        if ( 0 >= $order->get_total() ) {
          return $this->complete_zero_pay_order( $order );
        }
      }

      if ( !empty( $payment_source ) ) {
        $payment_token = $this->generate_payment_token( $payment_source, $this->get_order_total(), get_woocommerce_currency() );
      }

      $april_merchant_order_id = $this->create_april_order( $order_id );
      $request3DS = $this->getRequest3DS( $this->get_order_total() );
      $pay_resp = $this->pay_for_april_order( $april_merchant_order_id, $payment_token, $payment_data, false, $request3DS );

      return $this->process_pay_order_response( $pay_resp, $order, $payment_token, $april_merchant_order_id );

    } catch( Exception $e ) {
      $error_msg = $e->getMessage();
      throw new Exception( esc_html( $error_msg ) );
    }
  }

  protected function process_pay_order_response( $pay_resp, $order, $payment_token, $april_merchant_order_id ) {
    if ( $pay_resp['success'] ) {
      $this->complete_order( $order, $pay_resp['response'] );
      return [
        'result' => 'success',
        'redirect' => $this->get_return_url( $order ),
      ];
    }

    $pay_data = $pay_resp['response'];
    if ( isset( $pay_data['ThreeDSAuthorisationRequired'] )) {
      $payment_action = wp_json_encode( $pay_data['ThreeDSAuthorisationRequired'] );
      $this->save_payment_data_to_order( $order, $payment_token, $april_merchant_order_id, $payment_action );

      // If this is on order pay page, payment action (3DS challenge) will be performed after redirection
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$redirect_url = add_query_arg( 'wc-april-payment-action', 1, $order->get_checkout_payment_url( false ) );

				return [
					'result'   => 'success',
					'redirect' => $redirect_url,
				];
			} else {
        return [
          'result' => 'success',
          'payment_action_required' => $payment_action,
          'redirect' => $this->get_return_url( $order ),
        ];
      }
    }

    throw new Exception( 'Unknown error occured after order pay API call.' );
  }


  /**
	 * Creates April order using WC order data
	 *
	 * @param int  $order_id
	 * @throws Exception If order creation fails.
	 * @return string
	 */
  protected function create_april_order( $order_id, $amount = null ) {
    $order_data = [
      'CreateOnlineOrder' => $this->get_order_data( $order_id, $amount )
    ];
    $order_resp = April_API::request($order_data, 'orders', 'POST');

    if ($order_resp['response']['code'] != 200) {
      throw new Exception( 'An unknown error occurred while creating the order with April ' . wp_json_encode($order_resp) );
    }

    $april_order_data = json_decode( $order_resp['body'], true );
    return $april_order_data['OnlineOrder']['order']['orderId'];
  }

  /**
	 * Pay for April order with payment token generated in frontend
	 *
   * @param string  $april_merchant_order_id April order ID
   * @param string  $payment_token April payment token
	 * @param array  $payment_action April payment action object previously returned in order pay request
	 * @throws Exception If order creation fails.
	 * @return array
	 */
  protected function pay_for_april_order( $april_merchant_order_id, $payment_token, $payment_data = null, $payment_action = false, $request_3ds = false ) {

    if ( $payment_action !== false ) {
      $pay_data = [
          'Confirm3DSComplete' => [
              'paymentTokenId' => $payment_token,
              'threeDSResponse' => json_decode( $payment_action )
          ]
      ];
    } else if ( !is_null( $payment_data ) && $payment_data['paymentMethodType'] === 'Card' ) {
      $pay_data = [
        'PayByCard' => [
          'paymentTokenId' => $payment_token,
          'request3DS' => $request_3ds
        ]
      ];
    } else {
      $pay_data = [
        'PayByPaymentToken' => [
          'paymentTokenId' => $payment_token
        ]
      ];
    }

    $pay_resp = April_API::request( $pay_data, 'orders/' . $april_merchant_order_id . '/pay', 'POST' );
    $pay_data = json_decode( $pay_resp['body'], true );

    April_Helper::write_log( 'Order pay completed: LP Order ' . $april_merchant_order_id . ' LP Token ' . $payment_token );

    if ($pay_resp['response']['code'] >= 200 && $pay_resp['response']['code'] <= 299) {
      if ( array_key_exists( 'PayOrderComplete', $pay_data ) ) {
        April_Helper::write_log( 'Order pay success: LP Order ' . $april_merchant_order_id . ' LP Token ' . $payment_token  );
        return ['success' => true, 'response' => $pay_data['PayOrderComplete']];
      }
      if ( array_key_exists( 'ThreeDSAuthorisationRequired', $pay_data ) ) {
        April_Helper::write_log( 'Order pay action required: LP Order ' . $april_merchant_order_id . ' LP Token ' . $payment_token  );
        return ['success' => false, 'response' => $pay_data];
      }

      $error_msg = "Error in April API order pay response";
      throw new Exception( esc_html( $error_msg ) );

    } elseif ( $pay_resp['response']['code'] >= 400 && $pay_resp['response']['code'] <= 499 ) {
      $error_msg = $this->get_error_message( $pay_resp, "An unknown error occurred while processing the payment with April");
      throw new Exception( esc_html( $error_msg ) );

    } else {
      throw new Exception( 'Failed to process the payment' );
    }
  }

  /**
	 * Complete the WC order after a successful payment
	 *
   * @param WC_Order  $order
	 * @param array  $pay_data April payment response data
	 */
  protected function complete_order( $order, $pay_data ) {
    global $woocommerce;

    $order->set_transaction_id( $pay_data['transactionId'] );
    $order->add_order_note( __( 'April payment completed.', 'april-payment-gateway-for-woocommerce' ) );
    $order->payment_complete();
    $woocommerce->cart->empty_cart();
  }

  /**
	 * Process refund
	 *
   * @param string  $order_id Reference.
	 * @param int  $amount amount to refund.
   * @param string  reason for refund.
   * @throws Exception If refund fails.
   * @return void
	 */
  public function process_refund( $order_id, $amount = null, $reason = '' ) {
    $order = new WC_Order( $order_id );
    $transaction_id = $order->get_transaction_id();

    $refund_data = [
      'CreateRefund' => [
        'refundAmount' => April_Helper::convert_to_cents( $amount )
      ]
    ];

    try {
      $refund_resp = April_API::request( $refund_data, 'transactions/' . $transaction_id, 'PATCH' );
      if ($refund_resp['response']['code'] != 200) {
        throw new Exception( 'An unknown error occurred while processing the refund' );
      }
      return true;
    } catch( Exception $e ) {
      $error_msg = $this->get_error_message( $refund_resp, 'Failed to issue a refund via April' );
      throw new Exception( esc_html( $error_msg ) );
    }
  }


	/**
	 * Hooks in to `woocommerce_payment_successful_result`,
	 * Modify the process_payment response and adds order id, payment action array, and redirect url
	 *
	 * @param array $result  process_payment response
	 * @param int   $order_id
	 * @return array
	 */
   public function update_successful_payment_response( $result, $order_id ) {
     $order = wc_get_order( $order_id );
     $payment_method = $order->get_payment_method();

 		if ( ( $payment_method !== $this->id ) || ( ! isset( $result['payment_action_required'] ) ) ) {
 			return $result;
 		}

     $pay_action_url = $this->build_action_url( $order_id, $result['redirect'] );
     $redirect = sprintf( '#lpaction::%s::%s::%s', $result['payment_action_required'], rawurlencode( $pay_action_url ), $this->id );

 		return [
 			'result'   => 'success',
 			'redirect' => $redirect,
 		];
 	}

  protected function build_action_url( $order_id, $redirect_url ) {
    $this->set_session();
    $query_params = [
      'order'       => $order_id,
      'nonce'       => wp_create_nonce( 'wc_april_payment_action' ),
      'redirect_to' => rawurlencode( $redirect_url ),
    ];

    $pay_action_url = add_query_arg( $query_params, WC_AJAX::get_endpoint( 'wc_lp_payment_action' ) );
    return $pay_action_url;
  }

  /**
   * Start WooCommerce session if it's not already started.
   */
  protected function set_session() {
    if ( isset( WC()->session ) && WC()->session->has_session() ) {
      return;
    }

    WC()->session->set_customer_session_cookie( true );
  }

  /**
   * Save payment action to order.
   *
   * @param WC_Order $order For to which the source applies.
   * @param string $payment_token April payment token.
   * @param string $april_merchant_order_id April order ID.
   * @param array $payment_action April payment action array.
   */
  protected function save_payment_data_to_order( $order, $payment_token, $april_merchant_order_id, $payment_action ) {
    $meta_data = [
      'lp_merchant_order_id' => $april_merchant_order_id,
      'payment_token' => $payment_token,
      'payment_action' => $payment_action,
    ];
    $order->update_meta_data( '_april_payment_data', wp_json_encode( $meta_data ) );

    if ( is_callable( [ $order, 'save' ] ) ) {
      $order->save();
    }
  }

  /**
   * Fetch payment data from order and complete the payment.
   *
   * @param WC_Order $order For to which the source applies.
   */
  public function complete_payment_action( $order ) {
    $payment_method = $order->get_payment_method();
    if ( $payment_method !== $this->id ) {
      return;
    }

    $pay_data = $this->get_payment_data_from_order( $order );

    $april_merchant_order_id = $pay_data->lp_merchant_order_id;
    $payment_token = $pay_data->payment_token;
    $payment_action = $pay_data->payment_action;

    try {
      $request3DS = true;
      $pay_resp = $this->pay_for_april_order( $april_merchant_order_id, $payment_token, null, $payment_action, $request3DS );
      $result = $this->process_pay_order_response( $pay_resp, $order, $payment_token, $april_merchant_order_id );

      if ( ! isset( $result['payment_action_required'] ) ) {
        return $result;
      }

      $pay_action_url = $this->build_action_url( $order->get_id(), $result['redirect'] );

      return [
        'result'   => $result['result'],
        'payment_action_required' => $result['payment_action_required'],
        'redirect' => $pay_action_url
      ];
    } catch( Exception $e ) {
      // If this is from order pay page, update order status to failed and pass a success response,
      // then user will directed to order-recived page where they'll see the payment failed error
      if ( isset( $_GET['wc-april-order-review'] ) ) {
        $order->update_status( 'failed', $e->getMessage() );
        return [
          'result'   => 'success',
          'redirect' => $this->get_return_url( $order )
        ];
      }
      throw $e;
    }
  }


  /**
   * Fetch the saved payment data from order.
   *
   * @return stdClass
   */
  public function get_payment_data_from_order( $order ) {
    $meta_data = $order->get_meta( '_april_payment_data', true );
    return json_decode( $meta_data );
  }

  /**
   * validate
   */
  public function validate_fields() {
    return true;
  }

  /**
   * Prepare data necessary front end rendering of April checkout
   *
   * @return string
   */
  protected function javascript_params() {
    $publishable_key = $this->publishable_key;
    $prevent_wallet_submit = $this->prevent_wallet_submit;
    $available_payment_option = $this->payment_option;
    $primary_color = $this->primary_color;
    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    $first_name = $current_user->user_firstname;
    $last_name = $current_user->user_lastname;
    $amount = April_Helper::convert_to_cents($this->get_order_total());
    $currency = get_woocommerce_currency();

    if( array_key_exists( 'april-preferred-bnpl-option', $_COOKIE ) && !empty( $_COOKIE['april-preferred-bnpl-option'] ) ) {
      $payment_type = 'payplan';
    } else {
      $payment_type = 'paycard';
    }

    $js_params = [
      'checkout_disabled' => false,
      'element_id' => $this->method_name . '-placeholder',
      'publishable_key' => $publishable_key,
      'prevent_wallet_submit' => $prevent_wallet_submit,
      'available_payment_option' => $available_payment_option,
      'primary_color' => $primary_color,
      'payment_type' => $payment_type,
      'email' => $email,
      'first_name' => $first_name,
      'last_name' => $last_name,
      'custom_token' => $this->get_april_custom_token(),
      'amount' => $amount,
      'currency' => $currency,
      'pay_source_only' => ( !$this->is_order_pay() && $this->is_subscription_item_in_cart() ) || $this->is_changing_payment_method_for_subscription(),
      'platform' => 'woocommerce',
      'platform_version' => WC_VERSION,
      'platform_plugin_version' => APRIL_VERSION
    ];

    return $js_params;
  }

  /**
	 * If the user is logged in returns custom token for April Customer.
	 *
	 * @return string
	 */
  private function get_april_custom_token() {
    try {
      $april_customer = $this->get_current_april_customer();
      if ( !empty( $april_customer ) ) {
        $customer_id = $april_customer->get_id();
        return April_Customer::signin_customer( $customer_id );
      }
    } catch(Exception $e) {
       return null;
    }
    return null;
  }

  /**
	 * If the user is logged in returns April Customer.
	 *
	 * @return April_Customer || null
	 */
  private function get_current_april_customer() {
    if ( is_user_logged_in() ) {
      $user        = wp_get_current_user();
      $customer    = new April_Customer( $user->ID );

      if ( empty( $customer->get_id() ) ) {
        $customer_data = April_Customer::map_customer_data( new WC_Customer( $user->ID ) );
        $customer->create_customer( $customer_data );
      }
      return $customer;
    }

    return null;
  }

  /**
   * Add script files
   */
  public function payment_scripts() {
    if( $this->enabled == 'yes' ) {
      $scriptTag = 'woocommerce_' . $this->id;

      wp_register_script( 'april_checkout', APRIL_CHECKOUTJS, [], APRIL_CHECKOUTJS_VERSION, true );
      wp_register_script( $scriptTag, plugins_url( 'public/js/' . $this->method_name . '.js', APRIL_GATEWAY_FILE ), [ 'jquery', 'april_checkout' ], APRIL_VERSION, true );
      wp_localize_script( $scriptTag, 'wc_' . $this->id . '_params', apply_filters( 'wc_' . $this->id . '_params', $this->javascript_params() ) );

      wp_enqueue_script( $scriptTag );
    }
  }

  private function getRequest3DS( $amount ) {
      $request3DSSetting = $this->request_3ds;
      $minAmt3DSSetting = floatval( $this->minimum_amount_3ds );
      return ( $request3DSSetting && $amount >= $minAmt3DSSetting );
  }

  /**
	 * Completes a zero amount order.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	public function complete_zero_pay_order( $order ) {
		WC()->cart->empty_cart();
		$order->payment_complete();

		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Generates a payment token from a given payment source
	 *
	 * @param string  $payment_source Payment source Id.
	 * @param float  $amount
	 * @param string  $currency
 	 * @throws Exception If payment token creation fails
	 * @return string
	 */
  public function generate_payment_token( $payment_source, $amount, $currency ) {
    $data = [
      'CreateSavedPaymentSourceToken' => [
        'paymentSourceId' => $payment_source,
        'amount' => array(
          'minorCurrencyUnits' => April_Helper::convert_to_cents( $amount ),
          'currency' => $currency
        )
      ]
    ];

    try {
      $resp = April_API::request( $data, 'tokens', 'POST' );
      if ($resp['response']['code'] != 200) {
        $error_msg = $this->get_error_message( $resp, 'Unable to generate a payment token from provided source' );
        throw new Exception( $error_msg );
      }
      $resp_data = json_decode( $resp['body'], true );
      return $resp_data['SavedPaymentSourceToken']['paymentTokenId'];
    } catch( Exception $e ) {
      $error_msg = $e->getMessage();
      throw new Exception( esc_html( $error_msg ) );
    }
  }

  /**
	 * Retrive April source ID from order
	 *
	 * @param object $order
	 * @return string
	 */
	protected function prepare_order_source( $order = null ) {
		$source_id   = false;

		if ( $order ) {
			$source_id = $order->get_meta( '_april_source_id', true );
		}

		return $source_id;
	}

	/**
	 * Save source to order.
	 *
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $source Source information.
	 */
	public function save_source_to_order( $order, $source ) {
		$order->update_meta_data( '_april_source_id', $source );

		if ( is_callable( [ $order, 'save' ] ) ) {
			$order->save();
		}

		$this->update_source_on_subscription_order( $order, $source );
	}

}
