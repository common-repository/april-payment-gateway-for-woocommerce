<?php
/**
 * April_Helper class.
 *
 */
class April_Helper {

	/**
   * Convert currency amount to cents
   *
   * @return int
   */
  public static function convert_to_cents( $amount ) {
    return round( $amount * 100 );
  }

	/**
	 * Map API error code to message
	 *
	 * @param int  $order_id Reference.
	 *
	 * @return void
	 */
  public static function get_api_error_message($apiResp) {
    $apiErrorCode = $apiResp['errorCode'];
    $apiMessage = array_key_exists( 'message', $apiResp ) ? $apiResp['message'] : '';
    $apiDetail = array_key_exists( 'detail', $apiResp ) ? $apiResp['detail'] : '';
    $apiTracer = array_key_exists( 'tracer', $apiResp ) ? $apiResp['tracer'] : 'Not available';

    $errorList = array(
        'do_not_honor'          => 'Please contact your card issuer',
        'expired_card'          => 'Card expired',
        'fraudulent'            => 'Suspected fraudulent transaction',
        'incorrect_cvc'         => 'Incorrect CVC',
        'insufficient_funds'    => 'Insufficient funds',
        'invalid_cvc'           => 'Invalid CVC',
        'invalid_expiry_month'  => 'Invalid expiry month',
        'invalid_expiry_year'   => 'Invalid expiry year',
        'pickup_card'           => 'Card not allowed',
        'stolen_card'           => 'Card is reported stolen'
    );
    if (array_key_exists($apiErrorCode, $errorList)) {
        return $errorList[$apiErrorCode];
    }
    return $apiMessage . ' [' . $apiErrorCode . '] ' . $apiDetail . ' [Error reference: ' . $apiTracer . ']';
  }

  public static function write_log( $log ) {
    if ( WP_DEBUG ) {
      if (is_array($log) || is_object($log)) {
        error_log(print_r($log, true));
      } else {
        error_log($log);
      }
    }
  }

  public static function sanitize_validate_payment_token( $paymentToken ) {
    $paymentToken = sanitize_text_field( $paymentToken );
    if ( is_string( $paymentToken ) ) {
      $str_arr = explode( '_', $paymentToken );
      if ( count( $str_arr ) > 1 && $str_arr[0] === 'ptkn' ) {
        return $paymentToken;
      }
    }
    return null;
  }

  public static function sanitize_validate_payment_source( $paymentSource ) {
    $paymentSource = sanitize_text_field( $paymentSource );
    if ( is_string( $paymentSource ) ) {
      $str_arr = explode( '_', $paymentSource );
      if ( count( $str_arr ) > 1 && $str_arr[0] === 'psrc' ) {
        return $paymentSource;
      }
    }
    return null;
  }

  public static function sanitize_validate_payment_data_string( $paymentDataString ) {
    $paymentDataString = sanitize_text_field( $paymentDataString );
    if ( is_string( $paymentDataString ) ) {
      try {
        $paymentData = json_decode( $paymentDataString, true );
      }catch( Exception $e ) {
        return null;
      }
      if ( is_array($paymentData) && key_exists( 'paymentMethodType', $paymentData ) ) {
        return $paymentData;
      }
    }
    return null;
  }
}
