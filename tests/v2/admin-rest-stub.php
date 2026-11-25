<?php
/**
 * TEST ONLY: alg-pxf/v1 REST stub with sample data in the CONVENTIONS 3.6 shapes, for building the admin before the
 * engine, channels, validator and AI modules land. Never required by plugin code: a mu-plugin on a QA site loads it,
 * and it registers nothing when the real routes or classes exist.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Admin_REST_Stub' ) ) :

class Alg_WC_PXF_Admin_REST_Stub {

	const OPTION = 'alg_pxf_stub_state';

	static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ), 100 );
		if ( 'yes' === get_option( 'alg_pxf_stub_free' ) ) {
			add_filter( 'alg_wc_pxf_is_pro', '__return_false' );
		}
	}

	static function channels() {
		return array(
			'google'      => array( 'id' => 'google', 'label' => 'Google Shopping', 'group' => 'Search and shopping', 'notes' => 'Free listings and Shopping ads. Also accepted by Microsoft and Pinterest.', 'formats' => array( 'xml', 'tsv' ), 'countries' => array() ),
			'microsoft'   => array( 'id' => 'microsoft', 'label' => 'Microsoft Shopping', 'group' => 'Search and shopping', 'notes' => 'Bing and Microsoft Merchant Center.', 'formats' => array( 'xml' ), 'countries' => array() ),
			'openai'      => array( 'id' => 'openai', 'label' => 'ChatGPT shopping', 'group' => 'Search and shopping', 'notes' => 'OpenAI product feed, JSON lines.', 'formats' => array( 'jsonl' ), 'countries' => array( 'US' ) ),
			'meta'        => array( 'id' => 'meta', 'label' => 'Facebook and Instagram', 'group' => 'Social', 'notes' => 'Meta catalog for shops and ads.', 'formats' => array( 'xml', 'csv' ), 'countries' => array() ),
			'pinterest'   => array( 'id' => 'pinterest', 'label' => 'Pinterest', 'group' => 'Social', 'notes' => 'Product pins and shopping ads.', 'formats' => array( 'xml' ), 'countries' => array() ),
			'tiktok'      => array( 'id' => 'tiktok', 'label' => 'TikTok', 'group' => 'Social', 'notes' => 'TikTok catalog file.', 'formats' => array( 'csv' ), 'countries' => array() ),
			'idealo'      => array( 'id' => 'idealo', 'label' => 'idealo', 'group' => 'Price comparison', 'notes' => 'Price comparison in Germany and Austria. CSV file.', 'formats' => array( 'csv' ), 'countries' => array( 'DE', 'AT' ) ),
			'pricerunner' => array( 'id' => 'pricerunner', 'label' => 'PriceRunner', 'group' => 'Price comparison', 'notes' => 'UK, Sweden, Denmark. CSV file.', 'formats' => array( 'csv' ), 'countries' => array( 'GB', 'SE', 'DK' ) ),
			'skroutz'     => array( 'id' => 'skroutz', 'label' => 'Skroutz', 'group' => 'Price comparison', 'notes' => 'Greece and Cyprus. Colors and sizes grouped.', 'formats' => array( 'xml' ), 'countries' => array( 'GR', 'CY' ) ),
			'custom'      => array( 'id' => 'custom', 'label' => 'Custom XML', 'group' => 'Other', 'notes' => 'Your own template with placeholders, for any other site.', 'formats' => array( 'xml' ), 'countries' => array() ),
		);
	}

	static function fields() {
		$f = function ( $label, $req, $group, $key = '' ) {
			return array( 'label' => $label, 'required' => $req, 'group' => $group, 'source' => $key ? array( 'type' => 'field', 'key' => $key ) : array() );
		};
		return array(
			'id' => $f( 'ID', true, 'basic', 'id' ), 'title' => $f( 'Title', true, 'basic', 'title' ), 'description' => $f( 'Description', true, 'basic', 'description' ),
			'link' => $f( 'Product link', true, 'basic', 'link' ), 'image_link' => $f( 'Image', true, 'basic', 'image_link' ),
			'price' => $f( 'Price', true, 'price', 'price' ), 'sale_price' => $f( 'Sale price', false, 'price', 'sale_price' ), 'availability' => $f( 'Availability', true, 'price', 'availability' ),
			'google_product_category' => $f( 'Google category', 'recommended', 'category', 'google_product_category' ), 'product_type' => $f( 'Product type', false, 'category', 'product_type' ),
			'gtin' => $f( 'GTIN', 'conditional', 'identifiers', 'gtin' ), 'mpn' => $f( 'MPN', 'conditional', 'identifiers', 'mpn' ), 'brand' => $f( 'Brand', true, 'identifiers' ),
			'condition' => $f( 'Condition', true, 'details', 'condition' ), 'color' => $f( 'Color', 'recommended', 'details', 'color' ), 'size' => $f( 'Size', 'recommended', 'details', 'size' ),
		);
	}

	static function state() {
		$s = get_option( self::OPTION );
		if ( ! is_array( $s ) ) {
			$now = time();
			$s   = array(
				'next'  => 103,
				'feeds' => array(
					101 => array( 'name' => 'Google Shopping (Germany)', 'config' => array( 'channel' => 'google', 'country' => 'DE', 'file_slug' => 'google-shopping', 'schedule' => 'daily', 'filters' => array( 'stock' => array( 'instock', 'onbackorder' ) ), 'mapping' => array( 'brand' => array( 'type' => 'static', 'key' => '', 'value' => 'Northwind', 'prefix' => '', 'suffix' => '' ) ) ), 'state' => array( 'status' => 'done', 'done' => 333, 'total' => 333, 'written' => 331, 'left_out' => 2, 'last_good' => $now - 3600, 'issues' => array( 'missing_category' => 313, 'title_too_long' => 1 ) ) ),
					102 => array( 'name' => 'idealo.de', 'config' => array( 'channel' => 'idealo', 'country' => 'DE', 'file_slug' => 'idealo-de', 'format' => 'csv', 'schedule' => 'daily' ), 'state' => array( 'status' => 'failed', 'written' => 318, 'left_out' => 0, 'last_good' => $now - 90000, 'error' => 'The uploads folder is not writable. Ask your host to allow writing to wp-content/uploads, then press Update now.' ) ),
				),
				'ai'    => array(
					array( 'id' => 'a1', 'product_id' => 19, 'product_name' => 'Hoodie with Zipper', 'field' => 'title', 'before' => 'Hoodie with Zipper', 'after' => 'Northwind Hoodie with Zipper, Cotton, Grey', 'status' => 'approved' ),
					array( 'id' => 'a2', 'product_id' => 63, 'product_name' => 'Outdoor Cushion Cover', 'field' => 'title', 'before' => 'Long Title Extra Long Outdoor Cushion Cover Waterproof Extra Long Outdoor Cushion Cover Waterproof', 'after' => 'Northwind Outdoor Cushion Cover, Waterproof, 45 x 45 cm', 'status' => 'suggested' ),
					array( 'id' => 'a3', 'product_id' => 21, 'product_name' => 'Polo', 'field' => 'title', 'before' => 'Polo', 'after' => 'Northwind Polo Shirt, Cotton Pique, Blue', 'status' => 'suggested' ),
					array( 'id' => 'a4', 'product_id' => 14, 'product_name' => 'Beanie', 'field' => 'title', 'before' => 'Beanie', 'after' => 'Northwind Knit Beanie, Wool Blend, Red', 'status' => 'suggested' ),
				),
				'cats'  => array(),
			);
		}
		return $s;
	}

	static function save( $s ) {
		update_option( self::OPTION, $s, false );
	}

	static function feed_out( $id, $f ) {
		$uploads = wp_upload_dir();
		$ext     = ! empty( $f['config']['format'] ) ? $f['config']['format'] : 'xml';
		$st      = $f['state'];
		return array(
			'id' => (int) $id, 'type' => 'feed', 'name' => $f['name'], 'channel' => $f['config']['channel'],
			'url' => $uploads['baseurl'] . '/product-feeds/' . $f['config']['file_slug'] . '.' . $ext,
			'status' => $st['status'], 'last_good' => isset( $st['last_good'] ) ? $st['last_good'] : 0,
			'written' => isset( $st['written'] ) ? $st['written'] : 0, 'issues' => isset( $st['issues'] ) ? array_sum( $st['issues'] ) : 0,
			'error' => isset( $st['error'] ) ? $st['error'] : '', 'config' => $f['config'], 'state' => $st,
		);
	}

	static function routes() {
		$routes = rest_get_server()->get_routes();
		if ( isset( $routes['/alg-pxf/v1/feeds'] ) ) {
			return;
		}
		$perm = function () {
			return current_user_can( 'manage_woocommerce' );
		};
		$reg  = function ( $route, $methods, $cb ) use ( $perm ) {
			register_rest_route( 'alg-pxf/v1', $route, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
		};
		$reg( '/channels', 'GET', function () {
			return array_values( Alg_WC_PXF_Admin_REST_Stub::channels() );
		} );
		$reg( '/feeds', 'GET', function () {
			$s   = Alg_WC_PXF_Admin_REST_Stub::state();
			$out = array();
			foreach ( $s['feeds'] as $id => $f ) {
				$out[] = Alg_WC_PXF_Admin_REST_Stub::feed_out( $id, $f );
			}
			$out[] = array( 'type' => 'classic', 'n' => 1, 'title' => 'Products XML', 'url' => home_url( '/products.xml' ), 'last_created' => time() - 40000 );
			return $out;
		} );
		$reg( '/feeds', 'POST', function ( $r ) {
			$s  = Alg_WC_PXF_Admin_REST_Stub::state();
			$id = $s['next']++;
			$s['feeds'][ $id ] = array( 'name' => sanitize_text_field( $r['name'] ), 'config' => array( 'channel' => sanitize_key( $r['channel'] ), 'country' => sanitize_text_field( $r['country'] ), 'file_slug' => sanitize_title( $r['name'] ), 'schedule' => 'daily' ), 'state' => array( 'status' => 'queued', 'done' => 0, 'total' => 335 ) );
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return Alg_WC_PXF_Admin_REST_Stub::feed_out( $id, $s['feeds'][ $id ] );
		} );
		$reg( '/feeds/(?P<id>\d+)', 'GET', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			return isset( $s['feeds'][ $r['id'] ] ) ? Alg_WC_PXF_Admin_REST_Stub::feed_out( $r['id'], $s['feeds'][ $r['id'] ] ) : new WP_Error( 'not_found', 'Feed not found', array( 'status' => 404 ) );
		} );
		$reg( '/feeds/(?P<id>\d+)', 'PUT', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			if ( ! isset( $s['feeds'][ $r['id'] ] ) ) {
				return new WP_Error( 'not_found', 'Feed not found', array( 'status' => 404 ) );
			}
			$cfg = (array) $r['config'];
			if ( isset( $cfg['mapping'] ) ) {
				$s['feeds'][ $r['id'] ]['config']['mapping'] = $cfg['mapping'];
				unset( $cfg['mapping'] );
			}
			$s['feeds'][ $r['id'] ]['config'] = array_replace_recursive( $s['feeds'][ $r['id'] ]['config'], $cfg );
			if ( isset( $cfg['filters'] ) ) {
				$s['feeds'][ $r['id'] ]['config']['filters'] = array_merge( isset( $s['feeds'][ $r['id'] ]['config']['filters'] ) ? $s['feeds'][ $r['id'] ]['config']['filters'] : array(), $cfg['filters'] );
			}
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return Alg_WC_PXF_Admin_REST_Stub::feed_out( $r['id'], $s['feeds'][ $r['id'] ] );
		} );
		$reg( '/feeds/(?P<id>\d+)', 'DELETE', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			unset( $s['feeds'][ $r['id'] ] );
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return array( 'deleted' => true );
		} );
		$reg( '/feeds/(?P<id>\d+)/run', 'POST', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			$s['feeds'][ $r['id'] ]['state'] = array_merge( $s['feeds'][ $r['id'] ]['state'], array( 'status' => 'running', 'done' => 0, 'total' => 333, 'started' => time() ) );
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return $s['feeds'][ $r['id'] ]['state'];
		} );
		$reg( '/feeds/(?P<id>\d+)/status', 'GET', function ( $r ) {
			$s  = Alg_WC_PXF_Admin_REST_Stub::state();
			$st = &$s['feeds'][ $r['id'] ]['state'];
			if ( in_array( $st['status'], array( 'running', 'queued' ), true ) ) {
				$st['status'] = 'running';
				$st['done']   = min( 333, ( isset( $st['done'] ) ? $st['done'] : 0 ) + 120 );
				if ( 333 === $st['done'] ) {
					$st = array_merge( $st, array( 'status' => 'done', 'written' => 331, 'left_out' => 2, 'last_good' => time(), 'error' => '' ) );
				}
			}
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return $st;
		} );
		$reg( '/feeds/(?P<id>\d+)/preview', 'GET', function ( $r ) {
			$p = wc_get_product( absint( $r['product'] ) );
			if ( ! $p ) {
				return new WP_Error( 'not_found', 'Product not found', array( 'status' => 404 ) );
			}
			$item   = array(
				'id' => (string) $p->get_id(), 'title' => wp_strip_all_tags( $p->get_name() ), 'description' => wp_trim_words( wp_strip_all_tags( $p->get_description() ), 14 ),
				'link' => $p->get_permalink(), 'image_link' => (string) wp_get_attachment_url( $p->get_image_id() ),
				'price' => $p->get_regular_price() ? wc_format_decimal( $p->get_regular_price(), 2 ) . ' EUR' : '', 'availability' => $p->is_in_stock() ? 'in_stock' : 'out_of_stock',
				'brand' => 'Northwind', 'gtin' => (string) $p->get_global_unique_id(), 'condition' => 'new', 'google_product_category' => '',
			);
			$issues = array( array( 'code' => 'missing_category', 'field' => 'google_product_category', 'severity' => 'warning', 'message' => 'No Google category. Match the category on the Categories screen.', 'fix' => 'ai_category' ) );
			if ( '' === $item['image_link'] ) {
				$issues[] = array( 'code' => 'missing_image', 'field' => 'image_link', 'severity' => 'error', 'message' => 'No image. Add a main image to the product.', 'fix' => 'product' );
			}
			if ( '' === $item['price'] ) {
				$issues[] = array( 'code' => 'missing_price', 'field' => 'price', 'severity' => 'error', 'message' => 'No price.', 'fix' => 'product' );
			}
			return array( 'model' => $item, 'item' => $item, 'issues' => $issues );
		} );
		$reg( '/feeds/(?P<id>\d+)/issues', 'GET', function () {
			$out = array(
				'totals'  => array( 'ready' => 327, 'warnings' => 313, 'errors' => 4, 'left_out' => 2 ),
				'summary' => array(
					array( 'code' => 'title_too_long', 'field' => 'title', 'severity' => 'error', 'message' => 'Title longer than 150 characters', 'fix' => 'ai_title', 'count' => 1 ),
					array( 'code' => 'gtin_check_digit', 'field' => 'gtin', 'severity' => 'error', 'message' => 'GTIN check digit is wrong', 'fix' => 'product', 'count' => 2 ),
					array( 'code' => 'missing_image', 'field' => 'image_link', 'severity' => 'error', 'message' => 'No image', 'fix' => 'product', 'count' => 1 ),
					array( 'code' => 'missing_category', 'field' => 'google_product_category', 'severity' => 'warning', 'message' => 'No Google category', 'fix' => 'ai_category', 'count' => 313 ),
					array( 'code' => 'missing_color_size', 'field' => 'color', 'severity' => 'warning', 'message' => 'Clothing without color or size', 'fix' => 'ai_attributes', 'count' => 42 ),
				),
			);
			if ( Alg_WC_PXF::is_pro() ) {
				$out['products'] = array(
					array( 'product_id' => 63, 'name' => 'Long Title Extra Long Outdoor Cushion Cover', 'issues' => array( array( 'message' => 'Title longer than 150 characters' ) ) ),
					array( 'product_id' => 64, 'name' => 'No Image No Price Sample', 'issues' => array( array( 'message' => 'No image' ), array( 'message' => 'No price' ) ) ),
				);
			}
			return $out;
		} );
		$reg( '/taxonomy', 'GET', function ( $r ) {
			$all = array( 212 => 'Apparel & Accessories > Clothing > Shirts & Tops', 5322 => 'Apparel & Accessories > Clothing > Activewear', 173 => 'Apparel & Accessories > Clothing Accessories > Hats', 169 => 'Apparel & Accessories > Clothing Accessories > Belts', 178 => 'Apparel & Accessories > Clothing Accessories > Sunglasses', 2169 => 'Home & Garden > Kitchen & Dining > Tableware > Drinkware > Mugs', 1604 => 'Apparel & Accessories > Clothing' );
			$out = array();
			foreach ( $all as $id => $path ) {
				if ( false !== stripos( $path, (string) $r['q'] ) ) {
					$out[] = array( 'id' => $id, 'path' => $path );
				}
			}
			return $out;
		} );
		$reg( '/category-map', 'GET', function () {
			$s   = Alg_WC_PXF_Admin_REST_Stub::state();
			$out = array();
			foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) {
				$gid   = isset( $s['cats'][ $t->term_id ] ) ? (int) $s['cats'][ $t->term_id ] : 0;
				$out[] = array( 'term_id' => $t->term_id, 'name' => $t->name, 'parent' => $t->parent, 'count' => $t->count, 'google_id' => $gid, 'google_path' => $gid ? 'Google category ' . $gid : '' );
			}
			return $out;
		} );
		$reg( '/category-map', 'PUT', function ( $r ) {
			$s         = Alg_WC_PXF_Admin_REST_Stub::state();
			$s['cats'] = array_map( 'absint', (array) $r['map'] );
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return array( 'saved' => true );
		} );
		$reg( '/ai/estimate', 'POST', function () {
			return array( 'connected' => true, 'count' => 42, 'credits' => 5, 'balance' => 412 );
		} );
		$reg( '/ai/run', 'POST', function () {
			return array( 'job_id' => 'j1', 'status' => 'queued' );
		} );
		$reg( '/ai/results', 'GET', function () {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			return array( 'items' => $s['ai'], 'balance' => 412 );
		} );
		$reg( '/ai/approve', 'POST', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			foreach ( $s['ai'] as &$i ) {
				if ( ! empty( $r['all'] ) || in_array( $i['id'], (array) $r['ids'], true ) ) {
					$i['status'] = 'approved';
					if ( isset( $r['values'][ $i['id'] ] ) ) {
						$i['after'] = sanitize_text_field( $r['values'][ $i['id'] ] );
					}
				}
			}
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return array( 'ok' => true );
		} );
		$reg( '/ai/undo', 'POST', function ( $r ) {
			$s = Alg_WC_PXF_Admin_REST_Stub::state();
			foreach ( $s['ai'] as &$i ) {
				if ( in_array( $i['id'], (array) $r['ids'], true ) ) {
					$i['status'] = 'suggested';
				}
			}
			Alg_WC_PXF_Admin_REST_Stub::save( $s );
			return array( 'ok' => true );
		} );
	}

}

endif;

Alg_WC_PXF_Admin_REST_Stub::init();

// Channel field specs (PHP contract 3.2) only when the channels module is missing.
add_action( 'alg_wc_pxf_loaded', function () {
	if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
		require_once __DIR__ . '/admin-channels-stub.php';
	}
} );
