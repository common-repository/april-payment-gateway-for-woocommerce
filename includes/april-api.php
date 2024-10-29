<?php
/**
 * April_API class.
 *
 */
class April_API {

	/**
	 * April API Endpoint
	 */
	const APRIL_API_ENDPOINT_LIVE     = 'https://api.au.meetapril.io/';
    const APRIL_API_ENDPOINT_SANDBOX  = 'https://api.sandbox.au.meetapril.io/';

	/**
	 * Secret API Key.
	 *
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Set secret API Key.
	 *
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get April environment [live, sandbox, tst, dev]
	 *
	 * @return string
	 */
	private static function get_april_env() {
		$pkExp = explode( '_', self::$secret_key );
		$env = $pkExp[0];
		return $env;
	}


	/**
	 * Get API endpoint url.
	 *
	 * @return string
	 */
	private static function get_api_end_point() {
		$env = self::get_april_env();
		$url = '';

		switch($env) {
			case 'sandbox':
				$url = self::APRIL_API_ENDPOINT_SANDBOX;
				break;
			default:
				$url = self::APRIL_API_ENDPOINT_LIVE;
		}

		return $url;
	}

	/**
	 * Generates the headers to pass to API request.
	 */
	public static function get_headers( $data_string ) {

		$http_headers = array(
			"Content-Type: application/json",
			"Content-Length: " . strlen( $data_string )
		);

		$http_headers['Authorization'] = 'Bearer ' . self::$secret_key;

		return $http_headers;
	}

	/**
	 * April API request
	 *
	 * @param array  $request
	 * @param string $api
	 * @param string $method
	 * @param bool   $with_headers To get the response with headers.
	 * @return stdClass|array
	 * @throws April_Exception
	 */

	public static function request( $request, $api, $method = 'POST' ) {

		$req_string = wp_json_encode( $request );
		$endpoint = self::get_api_end_point() . $api;

		April_Helper::write_log( $method . ' ' . $endpoint );

		$response = wp_safe_remote_post( $endpoint,
			[
				'sslverify' => true,
				'method'  => $method,
				'headers' => self::get_headers( $req_string ),
				'body'    => $req_string,
				'timeout' => 60,
			]
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			throw new Exception( 'April API call failed' );
		}

		return $response;
	}

}
