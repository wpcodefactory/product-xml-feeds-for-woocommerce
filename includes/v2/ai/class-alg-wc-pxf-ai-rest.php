<?php
/**
 * Product XML Feeds for WooCommerce - v2 AI REST routes.
 *
 * alg-pxf/v1/ai/estimate, /ai/run, /ai/results, /ai/approve, /ai/undo. Every route needs `manage_woocommerce` and the
 * `wp_rest` nonce (X-WP-Nonce) from the admin.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_AI_REST' ) ) :

class Alg_WC_PXF_AI_REST {

	/**
	 * Namespace.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const NS = 'alg-pxf/v1';

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Permission: shop managers and administrators.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function can() {
		return current_user_can( 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
	}

	/**
	 * Registers the routes.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function routes() {
		$job  = array(
			'type'              => 'string',
			'required'          => true,
			'enum'              => array_keys( Alg_WC_PXF_AI::jobs() ),
			'sanitize_callback' => 'sanitize_key',
		);
		$ids  = array(
			'type'    => 'array',
			'items'   => array( 'type' => 'integer' ),
			'default' => array(),
		);
		$perm = array( __CLASS__, 'can' );
		register_rest_route(
			self::NS,
			'/ai/estimate',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'estimate' ),
				'permission_callback' => $perm,
				'args'                => array(
					'job'   => $job,
					'ids'   => $ids,
					'count' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'photo' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/run',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'run' ),
					'permission_callback' => $perm,
					'args'                => array(
						'job'   => array_merge( $job, array( 'required' => false ) ),
						'ids'   => $ids,
						'photo' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'stop'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'retry' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'progress' ),
					'permission_callback' => $perm,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/results',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'results' ),
				'permission_callback' => $perm,
				'args'                => array(
					'job'      => $job,
					'status'   => array(
						'type'    => 'string',
						'enum'    => array( '', 'suggested', 'approved' ),
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/approve',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'approve' ),
				'permission_callback' => $perm,
				'args'                => array(
					'job'    => $job,
					'ids'    => $ids,
					'all'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'values' => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/ai/undo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'undo' ),
				'permission_callback' => $perm,
				'args'                => array(
					'job' => $job,
					'ids' => $ids,
				),
			)
		);
	}

	/**
	 * GET|POST /ai/estimate.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function estimate( $request ) {
		$ids = array_filter( array_map( 'absint', (array) $request['ids'] ) );
		return Alg_WC_PXF_AI::estimate( (string) $request['job'], $ids ? $ids : (int) $request['count'], (bool) $request['photo'] );
	}

	/**
	 * POST /ai/run: start (job, ids, photo), stop (stop=1) or retry the failed items of the last run (retry=1).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function run( $request ) {
		if ( $request['stop'] ) {
			return array( 'run' => Alg_WC_PXF_AI::stop() );
		}
		$job = (string) $request['job'];
		$ids = array_map( 'absint', (array) $request['ids'] );
		if ( $request['retry'] ) {
			$last = Alg_WC_PXF_AI::progress();
			if ( ! $last || empty( $last['failed'] ) ) {
				return new WP_Error( 'alg_pxf_ai_nothing', __( 'Nothing to retry.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
			}
			$job = $last['job'];
			$ids = $last['failed'];
		}
		if ( '' === $job ) {
			return new WP_Error( 'alg_pxf_ai_job', __( 'Unknown AI job.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$run = Alg_WC_PXF_AI::run( $job, $ids, array( 'photo' => (bool) $request['photo'] ) );
		return is_wp_error( $run ) ? $run : array( 'run' => $run );
	}

	/**
	 * GET /ai/run: progress of the current (or last) run.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function progress() {
		return array( 'run' => Alg_WC_PXF_AI::public_run( Alg_WC_PXF_AI::progress() ) );
	}

	/**
	 * GET /ai/results.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array
	 */
	static function results( $request ) {
		return Alg_WC_PXF_AI::results( (string) $request['job'], (string) $request['status'], (int) $request['page'], (int) $request['per_page'] );
	}

	/**
	 * POST /ai/approve: ids, all, values (id => edited value).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array
	 */
	static function approve( $request ) {
		$values = array();
		foreach ( (array) $request['values'] as $id => $value ) {
			$values[ absint( $id ) ] = $value;
		}
		$ids = array_merge( array_map( 'absint', (array) $request['ids'] ), array_keys( $values ) );
		$n   = Alg_WC_PXF_AI::approve( (string) $request['job'], $ids, $values, (bool) $request['all'] );
		return array(
			'approved' => $n,
			/* translators: %s: number of items. */
			'message'  => sprintf( _n( '%s suggestion approved. It goes into feeds that use AI values from their next build.', '%s suggestions approved. They go into feeds that use AI values from their next build.', $n, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $n ) ),
		);
	}

	/**
	 * POST /ai/undo: ids.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array
	 */
	static function undo( $request ) {
		$n = Alg_WC_PXF_AI::undo( (string) $request['job'], array_map( 'absint', (array) $request['ids'] ) );
		return array(
			'removed' => $n,
			/* translators: %s: number of items. */
			'message' => sprintf( _n( '%s AI value removed. Your product data was not changed.', '%s AI values removed. Your product data was not changed.', $n, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $n ) ),
		);
	}

}

endif;
