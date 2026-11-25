<?php
/**
 * Product XML Feeds for WooCommerce - v2 REST routes (namespace alg-pxf/v1). Every route needs manage_woocommerce.
 *
 * The /ai/* routes belong to the AI module.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_REST' ) ) :

class Alg_WC_PXF_REST {

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
	 * can.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function can() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * routes.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function routes() {
		$can = array( __CLASS__, 'can' );
		$id  = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		register_rest_route( self::NS, '/channels', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_channels' ),
			'permission_callback' => $can,
		) );
		register_rest_route( self::NS, '/feeds', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_feeds' ),
				'permission_callback' => $can,
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_feed' ),
				'permission_callback' => $can,
				'args'                => array(
					'name'     => array( 'type' => 'string' ),
					'channel'  => array( 'type' => 'string' ),
					'country'  => array( 'type' => 'string' ),
					'language' => array( 'type' => 'string' ),
					'currency' => array( 'type' => 'string' ),
				),
			),
		) );
		register_rest_route( self::NS, '/feeds/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_feed' ),
				'permission_callback' => $can,
				'args'                => $id,
			),
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => array( __CLASS__, 'update_feed' ),
				'permission_callback' => $can,
				'args'                => $id,
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_feed' ),
				'permission_callback' => $can,
				'args'                => $id,
			),
		) );
		register_rest_route( self::NS, '/feeds/(?P<id>\d+)/run', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'run_feed' ),
			'permission_callback' => $can,
			'args'                => array_merge( $id, array( 'restart' => array( 'type' => 'boolean', 'default' => false ) ) ),
		) );
		register_rest_route( self::NS, '/feeds/(?P<id>\d+)/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'feed_status' ),
			'permission_callback' => $can,
			'args'                => $id,
		) );
		register_rest_route( self::NS, '/feeds/(?P<id>\d+)/preview', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'feed_preview' ),
			'permission_callback' => $can,
			'args'                => array_merge( $id, array(
				'product' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			) ),
		) );
		register_rest_route( self::NS, '/feeds/(?P<id>\d+)/issues', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'feed_issues' ),
			'permission_callback' => $can,
			'args'                => $id,
		) );
		register_rest_route( self::NS, '/classic/(?P<n>\d+)/copy', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'copy_classic' ),
			'permission_callback' => $can,
			'args'                => array(
				'n' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		) );
		register_rest_route( self::NS, '/taxonomy', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'taxonomy' ),
			'permission_callback' => $can,
			'args'                => array(
				'q'     => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				'limit' => array( 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
			),
		) );
		register_rest_route( self::NS, '/category-map', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_category_map' ),
				'permission_callback' => $can,
			),
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => array( __CLASS__, 'update_category_map' ),
				'permission_callback' => $can,
			),
		) );
	}

	/**
	 * error.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $code    Code.
	 * @param   string $message Message.
	 * @param   int    $status  HTTP status.
	 * @return  WP_Error
	 */
	protected static function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * no_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  WP_Error
	 */
	protected static function no_feed() {
		return self::error( 'alg_pxf_no_feed', __( 'This feed does not exist.', 'product-xml-feeds-for-woocommerce' ), 404 );
	}

	/**
	 * get_channels.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function get_channels() {
		$out = array();
		if ( class_exists( 'Alg_WC_PXF_Channels' ) ) {
			foreach ( (array) Alg_WC_PXF_Channels::all() as $channel_id => $channel ) {
				if ( ! is_array( $channel ) ) {
					continue;
				}
				$out[] = array(
					'id'        => isset( $channel['id'] ) ? $channel['id'] : $channel_id,
					'label'     => isset( $channel['label'] ) ? $channel['label'] : $channel_id,
					'group'     => isset( $channel['group'] ) ? $channel['group'] : '',
					'notes'     => isset( $channel['notes'] ) ? $channel['notes'] : '',
					'formats'   => isset( $channel['formats'] ) ? array_values( (array) $channel['formats'] ) : array( 'xml' ),
					'countries' => isset( $channel['countries'] ) ? array_values( (array) $channel['countries'] ) : array(),
				);
			}
		}
		return $out;
	}

	/**
	 * get_feeds: v2 feeds, then classic feeds (read only).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function get_feeds() {
		$out = array();
		foreach ( Alg_WC_PXF_Feeds::all() as $feed_id ) {
			$out[] = Alg_WC_PXF_Feeds::to_array( $feed_id );
		}
		return array_merge( $out, self::classic_feeds() );
	}

	/**
	 * classic_feeds: reads (never writes) the 3.1 options. Pro: feeds 1 to total; free: feed 1.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function classic_feeds() {
		$total = Alg_WC_PXF::is_pro() ? (int) get_option( 'alg_products_xml_total_files', 1 ) : 1;
		$total = max( 1, min( 500, $total ) );
		$out   = array();
		for ( $n = 1; $n <= $total; $n++ ) {
			$path  = get_option( 'alg_products_xml_file_path_' . $n, ( 1 === $n ? 'products.xml' : 'products_' . $n . '.xml' ) );
			$base  = 'yes' === get_option( 'alg_products_xml_use_home_url_' . $n, 'no' ) ? home_url() : site_url();
			$time  = (int) get_option( 'alg_products_time_file_created_' . $n, 0 );
			/* translators: %d: Feed number. */
			$title = get_option( 'alg_products_xml_feed_title_' . $n, sprintf( __( 'XML Feed #%d', 'product-xml-feeds-for-woocommerce' ), $n ) );
			$out[] = array(
				'id'           => 'classic-' . $n,
				'type'         => 'classic',
				'n'            => $n,
				'name'         => (string) $title,
				'title'        => (string) $title,
				'enabled'      => 'yes' === get_option( 'alg_products_xml_enabled_' . $n, 'yes' ),
				'url'          => $base . '/' . ltrim( (string) $path, '/' ),
				'last_created' => $time,
				'last_created_text' => $time ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '',
				'edit_url'     => admin_url( 'admin.php?page=wc-settings&tab=alg_wc_product_xml_feeds&section=feed_' . $n ),
			);
		}
		return $out;
	}

	/**
	 * valid_channel.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $channel_id Channel id.
	 * @return  bool
	 */
	protected static function valid_channel( $channel_id ) {
		return null !== Alg_WC_PXF_Feeds::channel( $channel_id );
	}

	/**
	 * create_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function create_feed( $request ) {
		$channel_id = sanitize_key( (string) ( $request['channel'] ? $request['channel'] : 'google' ) );
		if ( ! self::valid_channel( $channel_id ) ) {
			return self::error( 'alg_pxf_bad_channel', __( 'This channel is not available.', 'product-xml-feeds-for-woocommerce' ), 400 );
		}
		$args = array( 'channel' => $channel_id );
		foreach ( array( 'name', 'country', 'language', 'currency' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$args[ $key ] = sanitize_text_field( (string) $request[ $key ] );
			}
		}
		$feed_id = Alg_WC_PXF_Feeds::create( $args );
		if ( is_wp_error( $feed_id ) ) {
			return self::error( 'alg_pxf_create_failed', $feed_id->get_error_message(), 500 );
		}
		return Alg_WC_PXF_Feeds::to_array( $feed_id, true );
	}

	/**
	 * copy_classic: a new "Custom template" feed from a classic feed (the classic feed is not touched).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function copy_classic( $request ) {
		$result = Alg_WC_PXF_Classic_Copy::copy( absint( $request['n'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$out            = Alg_WC_PXF_Feeds::to_array( $result['id'], true );
		$out['skipped'] = $result['skipped'];
		return $out;
	}

	/**
	 * get_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function get_feed( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$out              = Alg_WC_PXF_Feeds::to_array( $feed_id, true );
		$out['schedules'] = Alg_WC_PXF_Scheduler::labels();
		return $out;
	}

	/**
	 * update_feed: partial config ("config" object or config keys at the top level) and "name".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function update_feed( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}
		$params = is_array( $params ) ? $params : array();
		$config = ( isset( $params['config'] ) && is_array( $params['config'] ) ) ? $params['config'] : array_intersect_key( $params, Alg_WC_PXF_Feeds::defaults() );
		if ( isset( $config['channel'] ) && ! self::valid_channel( sanitize_key( (string) $config['channel'] ) ) ) {
			return self::error( 'alg_pxf_bad_channel', __( 'This channel is not available.', 'product-xml-feeds-for-woocommerce' ), 400 );
		}
		if ( isset( $params['name'] ) ) {
			$name = sanitize_text_field( (string) $params['name'] );
			if ( '' !== $name ) {
				wp_update_post( array( 'ID' => $feed_id, 'post_title' => $name ) );
			}
		}
		if ( ! empty( $config ) ) {
			Alg_WC_PXF_Feeds::save_config( $feed_id, $config );
		}
		return self::get_feed( $request );
	}

	/**
	 * delete_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function delete_feed( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		return array(
			'id'      => $feed_id,
			'deleted' => Alg_WC_PXF_Feeds::delete( $feed_id ),
		);
	}

	/**
	 * run_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function run_feed( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$state = Alg_WC_PXF_Engine::run( $feed_id, 'manual', (bool) $request['restart'] );
		return is_wp_error( $state ) ? $state : self::status_array( $feed_id, $state );
	}

	/**
	 * feed_status: runs one batch here when no batch ran for a minute (WP-Cron off).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function feed_status( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$state = Alg_WC_PXF_Feeds::get_state( $feed_id, true );
		if ( Alg_WC_PXF_Engine::needs_tick( $state ) ) {
			$state = Alg_WC_PXF_Engine::tick( $feed_id, 10 );
		}
		return self::status_array( $feed_id, $state );
	}

	/**
	 * status_array.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $state   State.
	 * @return  array
	 */
	protected static function status_array( $feed_id, array $state ) {
		$state['feed_id']  = $feed_id;
		$state['url']      = Alg_WC_PXF_Feeds::file_url( $feed_id );
		$state['percent']  = $state['total'] > 0 ? min( 100, (int) floor( 100 * $state['done'] / $state['total'] ) ) : ( 'done' === $state['status'] ? 100 : 0 );
		unset( $state['bytes'], $state['attempts'] );
		return $state;
	}

	/**
	 * feed_preview: model, item and issues for one product, built as a guest.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function feed_preview( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$product = wc_get_product( absint( $request['product'] ) );
		if ( ! $product ) {
			return self::error( 'alg_pxf_no_product', __( 'This product does not exist.', 'product-xml-feeds-for-woocommerce' ), 404 );
		}
		$config  = Alg_WC_PXF_Feeds::get_config( $feed_id );
		$channel = Alg_WC_PXF_Feeds::channel( $config['channel'] );
		if ( ! $channel || ! class_exists( 'Alg_WC_PXF_Product_Data' ) || ! class_exists( 'Alg_WC_PXF_Mapper' ) ) {
			return self::error( 'alg_pxf_unavailable', __( 'The preview is not available: a part of the plugin is missing.', 'product-xml-feeds-for-woocommerce' ), 503 );
		}
		$user = get_current_user_id();
		wp_set_current_user( 0 );
		try {
			$model  = Alg_WC_PXF_Product_Data::model( $product, $config );
			$items  = is_callable( array( 'Alg_WC_PXF_Mapper', 'items' ) ) ? (array) Alg_WC_PXF_Mapper::items( $model, $channel, $config ) : array( Alg_WC_PXF_Mapper::item( $model, $channel, $config ) );
			$items  = array_values( array_filter( $items, 'is_array' ) );
			$item   = isset( $items[0] ) ? $items[0] : array();
			$issues = array();
			if ( class_exists( 'Alg_WC_PXF_Validator' ) ) {
				foreach ( $items as $one ) {
					if ( ! isset( $one['_raw'] ) ) {
						$issues = array_merge( $issues, (array) Alg_WC_PXF_Validator::check( $one, $model, $channel ) );
					}
				}
			}
		} catch ( Throwable $e ) {
			wp_set_current_user( $user );
			/* translators: %s: Error message. */
			return self::error( 'alg_pxf_preview_failed', sprintf( __( 'This product could not be read: %s', 'product-xml-feeds-for-woocommerce' ), $e->getMessage() ), 500 );
		}
		wp_set_current_user( $user );
		$variations = array();
		if ( $product->is_type( 'variable' ) ) {
			$variations = array_map( 'intval', $product->get_children() );
		}
		return array(
			'product_id' => (int) $product->get_id(),
			'variations' => $variations,
			'model'      => $model,
			'item'       => $item,
			'items'      => $items,
			'issues'     => $issues,
		);
	}

	/**
	 * feed_issues: counts and top reasons; the per-product list in Pro.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function feed_issues( $request ) {
		$feed_id = absint( $request['id'] );
		if ( ! Alg_WC_PXF_Feeds::exists( $feed_id ) ) {
			return self::no_feed();
		}
		$state  = Alg_WC_PXF_Feeds::get_state( $feed_id );
		$counts = array_map( 'intval', (array) $state['issues'] );
		arsort( $counts );
		$stored = get_transient( 'alg_pxf_issues_' . $feed_id );
		if ( ! is_array( $stored ) ) {
			$stored = get_transient( 'alg_pxf_issues_run_' . $feed_id );
		}
		$stored = is_array( $stored ) ? $stored : array();
		$first  = array();
		foreach ( $stored as $issue ) {
			if ( isset( $issue['code'] ) && ! isset( $first[ $issue['code'] ] ) ) {
				$first[ $issue['code'] ] = $issue;
			}
		}
		$top = array();
		foreach ( array_slice( $counts, 0, 10, true ) as $code => $count ) {
			$top[] = array(
				'code'     => $code,
				'count'    => $count,
				'field'    => isset( $first[ $code ]['field'] ) ? $first[ $code ]['field'] : '',
				'severity' => isset( $first[ $code ]['severity'] ) ? $first[ $code ]['severity'] : '',
				'message'  => isset( $first[ $code ]['message'] ) ? $first[ $code ]['message'] : '',
				'fix'      => isset( $first[ $code ]['fix'] ) ? $first[ $code ]['fix'] : '',
			);
		}
		$out = array(
			'feed_id'  => $feed_id,
			'total'    => (int) array_sum( $counts ),
			'counts'   => $counts,
			'top'      => $top,
			'written'  => (int) $state['written'],
			'left_out' => (int) $state['left_out'],
		);
		if ( Alg_WC_PXF::is_pro() ) {
			$out['products'] = array_values( $stored );
		}
		return $out;
	}

	/**
	 * taxonomy: Google category search.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array
	 */
	static function taxonomy( $request ) {
		if ( ! class_exists( 'Alg_WC_PXF_Google_Taxonomy' ) ) {
			return array();
		}
		$limit = max( 1, min( 50, absint( $request['limit'] ) ) );
		return array_values( (array) Alg_WC_PXF_Google_Taxonomy::search( (string) $request['q'], $limit ) );
	}

	/**
	 * get_category_map: product categories with product counts and their Google category.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function get_category_map() {
		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
		) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$has_taxonomy = class_exists( 'Alg_WC_PXF_Google_Taxonomy' );
		$out          = array();
		foreach ( $terms as $term ) {
			$google = (int) get_term_meta( $term->term_id, '_alg_pxf_google_category', true );
			$out[]  = array(
				'id'          => (int) $term->term_id,
				'name'        => wp_specialchars_decode( $term->name, ENT_QUOTES ),
				'parent'      => (int) $term->parent,
				'count'       => (int) $term->count,
				'google_id'   => $google,
				'google_path' => ( $google && $has_taxonomy ) ? (string) Alg_WC_PXF_Google_Taxonomy::path( $google ) : '',
			);
		}
		return $out;
	}

	/**
	 * update_category_map: body "map" => { term_id: google_id } (0 or empty removes the mapping).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WP_REST_Request $request Request.
	 * @return  array|WP_Error
	 */
	static function update_category_map( $request ) {
		$map = $request['map'];
		if ( null !== $map && ! is_array( $map ) ) {
			return self::error( 'alg_pxf_bad_map', __( 'The category map must be a list of category and Google category ids.', 'product-xml-feeds-for-woocommerce' ), 400 );
		}
		foreach ( (array) $map as $term_id => $google_id ) {
			$term = get_term( absint( $term_id ), 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$google_id = absint( $google_id );
			if ( $google_id > 0 ) {
				update_term_meta( $term->term_id, '_alg_pxf_google_category', $google_id );
			} else {
				delete_term_meta( $term->term_id, '_alg_pxf_google_category' );
			}
		}
		return self::get_category_map();
	}

}

endif;
