<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin: "Product Feeds" screen (admin.php?page=alg-pxf&view=...).
 *
 * Data comes from the alg-pxf/v1 REST routes (module engine), dispatched internally for the first render, and from
 * wp.apiFetch in assets/v2/admin.js after that. Forms also post to admin-post.php so the screens work without JS.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Admin' ) ) :

class Alg_WC_PXF_Admin {

	/**
	 * Page slug.
	 */
	const SLUG = 'alg-pxf';

	/**
	 * Settings option (admin owned).
	 */
	const OPTION = 'alg_pxf_settings';

	/**
	 * Views.
	 */
	const VIEWS = array( 'feeds', 'new', 'edit', 'products', 'categories', 'ai', 'settings', 'pro' );

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	static $hook = '';

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'hide_notices' ), 1000 );
		foreach ( array( 'create_feed', 'save_feed', 'run_feed', 'save_categories', 'save_settings' ) as $action ) {
			add_action( 'admin_post_alg_wc_pxf_' . $action, array( __CLASS__, 'post_' . $action ) );
		}
	}

	/**
	 * menu: one item under the WPFactory menu (WooCommerce menu when the WPFactory menu is missing).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function menu() {
		global $admin_page_hooks;
		$parent     = isset( $admin_page_hooks['wpfactory'] ) ? 'wpfactory' : 'woocommerce';
		self::$hook = add_submenu_page(
			$parent,
			__( 'Product Feeds', 'product-xml-feeds-for-woocommerce' ),
			__( 'Product Feeds', 'product-xml-feeds-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * is_screen.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function is_screen() {
		return isset( $_GET['page'] ) && self::SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * view: current view from the view param.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function view() {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'feeds'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $view, self::VIEWS, true ) || ( 'pro' === $view && Alg_WC_PXF::is_pro() ) ) {
			$view = 'feeds';
		}
		return $view;
	}

	/**
	 * url.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $view View.
	 * @param   array  $args More query args.
	 * @return  string
	 */
	static function url( $view = 'feeds', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG, 'view' => $view ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * classic_url: the classic WooCommerce settings tab (kept as it is).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $n Classic feed number (0 = first screen).
	 * @return  string
	 */
	static function classic_url( $n = 0 ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=alg_wc_product_xml_feeds' );
		return $n ? add_query_arg( 'section', 'feed_' . absint( $n ), $url ) : $url;
	}

	/**
	 * pro_url.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function pro_url() {
		return 'https://wpfactory.com/item/product-xml-feeds-woocommerce/';
	}

	/**
	 * body_class.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $classes Classes.
	 * @return  string
	 */
	static function body_class( $classes ) {
		return self::is_screen() ? $classes . ' pxf-screen' : $classes;
	}

	/**
	 * hide_notices: other plugins' notices stay off this screen.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function hide_notices() {
		if ( self::is_screen() ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	/**
	 * enqueue.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $hook Hook suffix.
	 */
	static function enqueue( $hook ) {
		if ( ! self::is_screen() ) {
			return;
		}
		wp_enqueue_style( 'alg-wc-pxf-admin', Alg_WC_PXF::url( 'assets/v2/admin.css' ), array(), Alg_WC_PXF::asset_version( 'assets/v2/admin.css' ) );
		wp_enqueue_script( 'alg-wc-pxf-admin', Alg_WC_PXF::url( 'assets/v2/admin.js' ), array( 'wp-api-fetch' ), Alg_WC_PXF::asset_version( 'assets/v2/admin.js' ), true );
		wp_localize_script( 'alg-wc-pxf-admin', 'algWcPxfAdmin', array(
			'ns'      => 'alg-pxf/v1',
			'view'    => self::view(),
			'isPro'   => Alg_WC_PXF::is_pro(),
			'editUrl' => self::url( 'edit', array( 'feed' => '__ID__' ) ),
			'aiUrl'   => self::url( 'ai' ),
			'i18n'    => array(
				'on'          => __( 'On', 'product-xml-feeds-for-woocommerce' ),
				'off'         => __( 'Off', 'product-xml-feeds-for-woocommerce' ),
				'copied'      => __( 'Link copied.', 'product-xml-feeds-for-woocommerce' ),
				'copyFailed'  => __( 'Select the link and copy it by hand.', 'product-xml-feeds-for-woocommerce' ),
				'saved'       => __( 'Saved.', 'product-xml-feeds-for-woocommerce' ),
				'error'       => __( 'Something went wrong. Nothing was changed.', 'product-xml-feeds-for-woocommerce' ),
				'updating'    => __( 'Updating', 'product-xml-feeds-for-woocommerce' ),
				'waiting'     => __( 'Waiting', 'product-xml-feeds-for-woocommerce' ),
				'upToDate'    => __( 'Up to date', 'product-xml-feeds-for-woocommerce' ),
				'notUpdated'  => __( 'Not updated', 'product-xml-feeds-for-woocommerce' ),
				'never'       => __( 'Never updated', 'product-xml-feeds-for-woocommerce' ),
				'updateNow'   => __( 'Update now', 'product-xml-feeds-for-woocommerce' ),
				/* translators: 1: products done, 2: products total. */
				'progress'    => __( '%1$s of %2$s products done', 'product-xml-feeds-for-woocommerce' ),
				/* translators: 1: products written, 2: products left out. */
				'doneMsg'     => __( 'Feed updated: %1$s products, %2$s left out.', 'product-xml-feeds-for-woocommerce' ),
				'started'     => __( 'Update started. You can leave this page.', 'product-xml-feeds-for-woocommerce' ),
				/* translators: 1: filled required fields, 2: required fields. */
				'meter'       => __( '%1$s of %2$s filled', 'product-xml-feeds-for-woocommerce' ),
				'empty'       => __( 'empty', 'product-xml-feeds-for-woocommerce' ),
				'noIssues'    => __( 'No problems for this product.', 'product-xml-feeds-for-woocommerce' ),
				'error_'      => __( 'Error', 'product-xml-feeds-for-woocommerce' ),
				'warning'     => __( 'Warning', 'product-xml-feeds-for-woocommerce' ),
				'noMatch'     => __( 'No Google category found. Try a shorter word.', 'product-xml-feeds-for-woocommerce' ),
				'approved'    => __( 'Approved', 'product-xml-feeds-for-woocommerce' ),
				'undone'      => __( 'Removed from the feed.', 'product-xml-feeds-for-woocommerce' ),
				'aiStarted'   => __( 'AI job started. Results appear here as they come in.', 'product-xml-feeds-for-woocommerce' ),
				/* translators: 1: number of products, 2: credits. */
				'estimate'    => __( 'Improve %1$s products: about %2$s credits.', 'product-xml-feeds-for-woocommerce' ),
				/* translators: %s: credits. */
				'balance'     => __( 'You have %s credits.', 'product-xml-feeds-for-woocommerce' ),
				'notConnected'=> __( 'Turn on AI on the AI Credits screen first.', 'product-xml-feeds-for-woocommerce' ),
				'short'       => __( 'Not enough credits: buy credits or run fewer products.', 'product-xml-feeds-for-woocommerce' ),
				'confirmDel'  => __( 'Delete this feed? The file stops working for the channel. Classic feeds are not touched.', 'product-xml-feeds-for-woocommerce' ),
			),
		) );
	}

	/**
	 * rest: dispatch an alg-pxf/v1 route internally (same permission checks as over HTTP).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $method GET|POST|PUT|DELETE.
	 * @param   string $route  Route after the namespace, for example "/feeds".
	 * @param   array  $params Query (GET) or JSON body params.
	 * @return  mixed|WP_Error
	 */
	static function rest( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, '/alg-pxf/v1' . $route );
		if ( 'GET' === $method ) {
			$request->set_query_params( $params );
		} elseif ( $params ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error();
		}
		return $response->get_data();
	}

	/**
	 * channels: id => spec from the REST list.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function channels() {
		$list = self::rest( 'GET', '/channels' );
		$out  = array();
		if ( is_array( $list ) ) {
			foreach ( $list as $key => $channel ) {
				$id         = isset( $channel['id'] ) ? $channel['id'] : $key;
				$out[ $id ] = $channel;
			}
		}
		return $out;
	}

	/**
	 * channel_fields: field specs of a channel (PHP contract 3.2), empty when the channels module is missing.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $channel Channel id.
	 * @return  array
	 */
	static function channel_fields( $channel ) {
		if ( class_exists( 'Alg_WC_PXF_Channels' ) ) {
			$spec = Alg_WC_PXF_Channels::get( $channel );
			if ( $spec && ! empty( $spec['fields'] ) ) {
				return $spec['fields'];
			}
		}
		return array();
	}

	/**
	 * settings: admin settings with defaults.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), array(
			'batch_size'          => 100,
			'server_cron'         => false,
			'remove_on_uninstall' => false,
		) );
	}

	/**
	 * render.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage feeds.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$view = self::view();
		$ui   = 'Alg_WC_PXF_Admin_UI';
		Alg_WC_PXF_Admin_UI::open( $view );
		self::flash();
		include __DIR__ . '/views/' . $view . '.php';
		Alg_WC_PXF_Admin_UI::close();
	}

	/**
	 * flash: one-line result after a no-JS form post.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function flash() {
		$msg = isset( $_GET['pxf_msg'] ) ? sanitize_key( wp_unslash( $_GET['pxf_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map = array(
			'saved'   => array( 'ok', __( 'Saved.', 'product-xml-feeds-for-woocommerce' ) ),
			'created' => array( 'ok', __( 'Feed created. The first file is being built now.', 'product-xml-feeds-for-woocommerce' ) ),
			'started' => array( 'ok', __( 'Update started. You can leave this page.', 'product-xml-feeds-for-woocommerce' ) ),
			'deleted' => array( 'ok', __( 'Feed deleted.', 'product-xml-feeds-for-woocommerce' ) ),
			'error'   => array( 'bad', __( 'Something went wrong. Nothing was changed.', 'product-xml-feeds-for-woocommerce' ) ),
		);
		if ( isset( $map[ $msg ] ) ) {
			Alg_WC_PXF_Admin_UI::banner( esc_html( $map[ $msg ][1] ), $map[ $msg ][0] );
		}
	}

	/**
	 * check_post: capability and nonce for admin-post handlers.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $action Nonce action.
	 */
	static function check_post( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage feeds.', 'product-xml-feeds-for-woocommerce' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * back: redirect after a form post.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $view View.
	 * @param   array  $args Args.
	 * @param   mixed  $result REST result.
	 * @param   string $ok   Message key when the result is fine.
	 */
	static function back( $view, $args, $result, $ok = 'saved' ) {
		$args['pxf_msg'] = is_wp_error( $result ) ? 'error' : $ok;
		wp_safe_redirect( self::url( $view, $args ) );
		exit;
	}

	/**
	 * post_create_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function post_create_feed() {
		self::check_post( 'alg_wc_pxf_create_feed' );
		$p      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value sanitized below.
		$result = self::rest( 'POST', '/feeds', array(
			'name'     => isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '',
			'channel'  => isset( $p['channel'] ) ? sanitize_key( $p['channel'] ) : '',
			'country'  => isset( $p['country'] ) ? strtoupper( substr( sanitize_key( $p['country'] ), 0, 2 ) ) : '',
			'language' => isset( $p['language'] ) ? sanitize_text_field( $p['language'] ) : '',
			'currency' => isset( $p['currency'] ) ? strtoupper( substr( sanitize_key( $p['currency'] ), 0, 3 ) ) : '',
		) );
		if ( ! is_wp_error( $result ) && ! empty( $result['id'] ) ) {
			self::back( 'edit', array( 'feed' => (int) $result['id'] ), $result, 'created' );
		}
		self::back( 'new', array(), is_wp_error( $result ) ? $result : new WP_Error( 'pxf' ) );
	}

	/**
	 * post_save_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function post_save_feed() {
		self::check_post( 'alg_wc_pxf_save_feed' );
		$id = isset( $_POST['feed'] ) ? absint( $_POST['feed'] ) : 0;
		if ( isset( $_POST['pxf_delete'] ) ) {
			$result = self::rest( 'DELETE', '/feeds/' . $id );
			self::back( 'feeds', array(), $result, 'deleted' );
		}
		$p      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_config().
		$body   = array( 'config' => self::sanitize_config( isset( $p['config'] ) ? (array) $p['config'] : array() ) );
		if ( isset( $p['name'] ) ) {
			$body['name'] = sanitize_text_field( $p['name'] );
		}
		$result = self::rest( 'PUT', '/feeds/' . $id, $body );
		$tab    = isset( $p['tab'] ) ? sanitize_key( $p['tab'] ) : 'fields';
		self::back( 'edit', array( 'feed' => $id, 'tab' => $tab ), $result );
	}

	/**
	 * post_run_feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function post_run_feed() {
		self::check_post( 'alg_wc_pxf_run_feed' );
		$id     = isset( $_POST['feed'] ) ? absint( $_POST['feed'] ) : 0;
		$result = self::rest( 'POST', '/feeds/' . $id . '/run' );
		$back   = isset( $_POST['back'] ) && 'edit' === $_POST['back'] ? 'edit' : 'feeds';
		self::back( $back, 'edit' === $back ? array( 'feed' => $id ) : array(), $result, 'started' );
	}

	/**
	 * post_save_categories.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function post_save_categories() {
		self::check_post( 'alg_wc_pxf_save_categories' );
		$map = array();
		if ( isset( $_POST['map'] ) && is_array( $_POST['map'] ) ) {
			foreach ( wp_unslash( $_POST['map'] ) as $term_id => $google_id ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to int.
				$map[ absint( $term_id ) ] = absint( $google_id );
			}
		}
		$result = self::rest( 'PUT', '/category-map', array( 'map' => $map ) );
		self::back( 'categories', array(), $result );
	}

	/**
	 * post_save_settings.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function post_save_settings() {
		self::check_post( 'alg_wc_pxf_save_settings' );
		$p = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		update_option( self::OPTION, array(
			'batch_size'          => max( 10, min( 500, absint( isset( $p['batch_size'] ) ? $p['batch_size'] : 100 ) ) ),
			'server_cron'         => ! empty( $p['server_cron'] ),
			'remove_on_uninstall' => ! empty( $p['remove_on_uninstall'] ),
		), false );
		self::back( 'settings', array(), true );
	}

	/**
	 * sanitize_config: posted config (partial) to the contract shape (3.1). Pro-only keys are dropped in free.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $in Posted config.
	 * @return  array
	 */
	static function sanitize_config( array $in ) {
		$out = array();
		foreach ( array( 'country', 'currency' ) as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $in[ $key ] ) );
			}
		}
		if ( isset( $in['language'] ) ) {
			$out['language'] = preg_replace( '/[^A-Za-z_\-]/', '', (string) $in['language'] );
		}
		if ( isset( $in['format'] ) ) {
			$out['format'] = sanitize_key( $in['format'] );
		}
		if ( isset( $in['file_slug'] ) ) {
			$out['file_slug'] = sanitize_title( $in['file_slug'] );
		}
		if ( isset( $in['variation_mode'] ) && in_array( $in['variation_mode'], array( '', 'all', 'parent', 'default', 'cheapest' ), true ) ) {
			$out['variation_mode'] = $in['variation_mode'];
		}
		$schedules = array( 'daily', 'manual' );
		if ( Alg_WC_PXF::is_pro() ) {
			$schedules = array_merge( $schedules, array( 'hourly', '15min', 'twicedaily' ) );
		}
		if ( isset( $in['schedule'] ) && in_array( $in['schedule'], $schedules, true ) ) {
			$out['schedule'] = $in['schedule'];
		}
		$bools = array( 'gzip', 'use_ai_titles', 'use_ai_descriptions' );
		if ( Alg_WC_PXF::is_pro() ) {
			$bools = array_merge( $bools, array( 'rebuild_on_change', 'leave_out_errors' ) );
		}
		foreach ( $bools as $key ) {
			if ( isset( $in[ $key ] ) ) {
				$out[ $key ] = ! empty( $in[ $key ] ) && '0' !== $in[ $key ];
			}
		}
		if ( isset( $in['filters'] ) && is_array( $in['filters'] ) ) {
			$f       = $in['filters'];
			$filters = array();
			if ( isset( $f['stock'] ) ) {
				$filters['stock'] = array_values( array_intersect( (array) $f['stock'], array( 'instock', 'onbackorder', 'outofstock' ) ) );
			}
			foreach ( array( 'include_cats', 'exclude_cats', 'include_tags', 'exclude_tags', 'exclude_ids' ) as $key ) {
				if ( isset( $f[ $key ] ) ) {
					$list            = is_array( $f[ $key ] ) ? $f[ $key ] : preg_split( '/[\s,]+/', (string) $f[ $key ] );
					$filters[ $key ] = array_values( array_filter( array_map( 'absint', $list ) ) );
				}
			}
			foreach ( array( 'min_price', 'max_price' ) as $key ) {
				if ( isset( $f[ $key ] ) ) {
					$filters[ $key ] = '' === trim( (string) $f[ $key ] ) ? '' : wc_format_decimal( $f[ $key ] );
				}
			}
			if ( isset( $f['on_sale'] ) && in_array( $f['on_sale'], array( '', 'yes', 'no' ), true ) ) {
				$filters['on_sale'] = $f['on_sale'];
			}
			$out['filters'] = $filters;
		}
		if ( isset( $in['mapping'] ) && is_array( $in['mapping'] ) ) {
			$out['mapping'] = array();
			foreach ( $in['mapping'] as $field => $row ) {
				$row    = (array) $row;
				$source = isset( $row['source'] ) ? (string) $row['source'] : '';
				if ( '' === $source ) {
					continue;
				}
				list( $type, $key ) = array_pad( explode( ':', $source, 2 ), 2, '' );
				if ( ! in_array( $type, array( 'field', 'attribute', 'meta', 'taxonomy', 'static', 'placeholder' ), true ) ) {
					continue;
				}
				if ( 'meta' === $type ) {
					$key = isset( $row['key'] ) ? sanitize_text_field( $row['key'] ) : '';
				}
				$out['mapping'][ sanitize_key( $field ) ] = array(
					'type'   => $type,
					'key'    => sanitize_text_field( $key ),
					'value'  => isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '',
					'prefix' => isset( $row['prefix'] ) ? sanitize_text_field( $row['prefix'] ) : '',
					'suffix' => isset( $row['suffix'] ) ? sanitize_text_field( $row['suffix'] ) : '',
				);
			}
		}
		return $out;
	}

}

endif;
