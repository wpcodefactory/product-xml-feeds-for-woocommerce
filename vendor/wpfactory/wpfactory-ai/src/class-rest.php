<?php
/**
 * REST routes for the connect screen (administrators only) and the public site check the server uses
 * before the free monthly credits.
 *
 * @package WPFactory\AI
 */

namespace WPFactory\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under wpfactory-ai/v1.
 */
final class Rest {

	const NS = 'wpfactory-ai/v1';

	/**
	 * Registers the routes.
	 */
	public static function register() {
		$admin = array( __CLASS__, 'can_manage' );
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => $admin,
				'args'                => array(
					'product' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'fresh'   => array( 'type' => 'boolean' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/connect/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'start' ),
				'permission_callback' => $admin,
				'args'                => array(
					'email' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/connect/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'verify' ),
				'permission_callback' => $admin,
				'args'                => array(
					'email' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_email',
					),
					'code'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/disconnect',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'disconnect' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NS,
			'/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'usage' ),
				'permission_callback' => $admin,
				'args'                => array(
					'period' => array(
						'type'    => 'string',
						'pattern' => '^(\d{4}-\d{2})?$',
					),
					'scope'  => array(
						'type' => 'string',
						'enum' => array( 'account', 'site' ),
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'checkout' ),
				'permission_callback' => $admin,
				'args'                => array(
					'pack' => array(
						'type'     => 'integer',
						'required' => true,
						'enum'     => array( 5, 10, 25, 50 ),
					),
				),
			)
		);
		// Public on purpose: answers only with an HMAC of the server's one-time challenge, which proves this
		// site holds its token without revealing it.
		register_rest_route(
			self::NS,
			'/site-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'site_check' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'challenge' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-f0-9]{16,128}$',
					),
				),
			)
		);
	}

	/**
	 * Who may connect, disconnect and see usage (filter wpfactory_ai_capability).
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( (string) apply_filters( 'wpfactory_ai_capability', 'manage_options' ) );
	}

	/**
	 * Connection and balance.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response
	 */
	public static function status( $req ) {
		$client  = wpfactory_ai();
		$out     = $client->connection();
		$product = (string) $req['product'];
		if ( '' === $product ) {
			$product = (string) key( $client->products() );
		}
		$out['balance'] = null;
		if ( $out['connected'] && '' !== $product ) {
			$b              = $client->balance( $product, (bool) $req['fresh'] );
			$out['balance'] = is_wp_error( $b ) ? null : $b;
		}
		$out['products']  = $client->products();
		$out['buy_url']   = $client->buy_url();
		$out['admin_url'] = $client->admin_url();
		return rest_ensure_response( $out );
	}

	/**
	 * Sends the code.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function start( $req ) {
		$r = wpfactory_ai()->start_connect( (string) $req['email'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'sent' => true ) );
	}

	/**
	 * Checks the code and connects.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function verify( $req ) {
		$r = wpfactory_ai()->finish_connect( (string) $req['email'], (string) $req['code'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
	}

	/**
	 * Disconnects.
	 *
	 * @return \WP_REST_Response
	 */
	public static function disconnect() {
		wpfactory_ai()->disconnect();
		return rest_ensure_response( array( 'connected' => false ) );
	}

	/**
	 * Usage numbers.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function usage( $req ) {
		$r = wpfactory_ai()->usage( (string) $req['period'], (string) $req['scope'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
	}

	/**
	 * A Stripe payment page for a credit pack.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function checkout( $req ) {
		$url = wpfactory_ai()->checkout( (int) $req['pack'] );
		return is_wp_error( $url ) ? $url : rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * Proof that this site holds its token: HMAC of the challenge, keyed by the token's hash.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function site_check( $req ) {
		$token = Connection::token();
		if ( '' === $token ) {
			return new \WP_Error( 'wpfactory_ai_not_connected', 'Not connected.', array( 'status' => 404 ) );
		}
		$proof = hash_hmac( 'sha256', 'wpfactory-ai-site-check|' . (string) $req['challenge'], hash( 'sha256', $token ) );
		return rest_ensure_response( array( 'proof' => $proof ) );
	}
}
