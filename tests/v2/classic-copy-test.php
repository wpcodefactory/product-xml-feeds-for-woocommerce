<?php
/**
 * Copy a classic feed to a new feed, and the one-menu-item admin (option B).
 * Run: wp eval-file tests/v2/classic-copy-test.php   (test only; not shipped)
 *
 * Proves: the copy is a Custom template feed with the classic templates and the filters that map; classic options,
 * file and schedule are byte-for-byte untouched; skipped settings are reported; the new file builds and matches the
 * classic template rendering; bad input and non-admins are refused; the classic screen highlights Product Feeds.
 */
defined( 'ABSPATH' ) || exit;
$pass = 0;
$fail = 0;
$ok   = function ( $cond, $label ) use ( &$pass, &$fail ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	$cond ? $pass++ : $fail++;
};
$req = function ( $method, $route, $params = array() ) {
	$r = new WP_REST_Request( $method, '/alg-pxf/v1' . $route );
	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}
	$res = rest_do_request( $r );
	return array( $res->get_status(), $res->get_data() );
};
$snapshot = function () {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'alg\\_product%' AND option_name NOT LIKE '%transient%' ORDER BY option_name", ARRAY_A ); // phpcs:ignore
	return md5( wp_json_encode( $rows ) );
};
// Every scheduled event except the new feeds' own schedule.
$cron = function () {
	$out = array();
	foreach ( (array) _get_cron_array() as $time => $hooks ) {
		foreach ( (array) $hooks as $hook => $events ) {
			if ( Alg_WC_PXF_Scheduler::HOOK !== $hook ) {
				$out[ $time ][ $hook ] = $events;
			}
		}
	}
	return wp_json_encode( $out );
};

// Classic feed 1 as a merchant set it up: own template, filters, and two settings with no match in new feeds.
$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'fields' => 'ids', 'number' => 2 ) );
$prods   = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 2 ) );
$set     = array(
	'alg_products_xml_feed_title_1'    => 'My shop feed',
	'alg_products_xml_header_1'        => '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL . '<shop>' . PHP_EOL,
	'alg_products_xml_item_1'          => '<offer><id>[alg_product_id]</id><name>[alg_product_name cdata="yes"]</name><price>[alg_product_price]</price></offer>' . PHP_EOL,
	'alg_products_xml_footer_1'        => '</shop>',
	'alg_products_xml_cats_excl_1'     => array_map( 'strval', array( (int) $cats[0] ) ),
	'alg_products_xml_products_excl_1' => array_map( 'strval', $prods ),
	'alg_products_xml_scope_1'         => 'sale_only',
	'alg_products_xml_offset_1'        => '3',
	'alg_products_xml_tags_if_empty_1' => 'price',
);
$before_opts = array();
foreach ( $set as $k => $v ) {
	$before_opts[ $k ] = get_option( $k, '__none__' );
	update_option( $k, $v );
}
$cron_before = $cron();
$opts_before = $snapshot();
$path        = ABSPATH . get_option( 'alg_products_xml_file_path_1', 'products.xml' );
$file_before = file_exists( $path ) ? md5_file( $path ) . filemtime( $path ) : 'none';

// Not allowed for a logged-out visitor.
wp_set_current_user( 0 );
list( $s ) = $req( 'POST', '/classic/1/copy' );
$ok( in_array( $s, array( 401, 403 ), true ), "logged-out copy refused ($s)" );
wp_set_current_user( 1 );

list( $s ) = $req( 'POST', '/classic/999/copy' );
$ok( 404 === $s, "copy of a feed that does not exist: 404 ($s)" );

list( $s, $feed ) = $req( 'POST', '/classic/1/copy' );
$id = isset( $feed['id'] ) ? (int) $feed['id'] : 0;
$ok( 200 === $s && $id > 0, "POST /classic/1/copy creates feed $id" );
$cfg = Alg_WC_PXF_Feeds::get_config( $id );
$ok( 'custom' === $cfg['channel'], 'new feed uses the Custom template channel' );
$ok( 'My shop feed (copy)' === get_the_title( $id ), 'name is "My shop feed (copy)"' );
$ok( $cfg['custom_template']['header'] === $set['alg_products_xml_header_1'] && $cfg['custom_template']['item'] === $set['alg_products_xml_item_1'] && $cfg['custom_template']['footer'] === $set['alg_products_xml_footer_1'], 'header, item and footer copied exactly' );
$ok( array( (int) $cats[0] ) === $cfg['filters']['exclude_cats'], 'excluded categories copied' );
$ok( array_map( 'intval', $prods ) === $cfg['filters']['exclude_ids'], 'excluded products copied' );
$ok( 'yes' === $cfg['filters']['on_sale'], 'on sale only copied' );
$ok( 'parent' === $cfg['variation_mode'], 'variable products only maps to main product' );
$ok( 0 === strpos( $cfg['file_slug'], 'products-copy' ), 'own file name: ' . $cfg['file_slug'] );
$skipped = isset( $feed['skipped'] ) ? $feed['skipped'] : array();
sort( $skipped );
$ok( array( 'offset', 'tags_if_empty' ) === $skipped, 'skipped settings reported: ' . implode( ',', $skipped ) );
$ok( 1 === (int) get_post_meta( $id, Alg_WC_PXF_Classic_Copy::META_FROM, true ), 'copied-from note stored on the new feed' );

// Product types: every type saved (the classic default) is no limit; one type is.
if ( Alg_WC_PXF::is_pro() ) {
	$types = get_terms( array( 'taxonomy' => 'product_type', 'hide_empty' => false, 'fields' => 'ids' ) );
	$was   = get_option( 'alg_products_xml_product_type_include_1', '__none__' );
	update_option( 'alg_products_xml_product_type_include_1', array_map( 'strval', $types ) );
	$ok( ! in_array( 'product_types', Alg_WC_PXF_Classic_Copy::skipped( 1 ), true ), 'all product types saved: not reported as skipped' );
	update_option( 'alg_products_xml_product_type_include_1', array( (string) $types[0] ) );
	$ok( in_array( 'product_types', Alg_WC_PXF_Classic_Copy::skipped( 1 ), true ), 'one product type saved: reported as skipped' );
	'__none__' === $was ? delete_option( 'alg_products_xml_product_type_include_1' ) : update_option( 'alg_products_xml_product_type_include_1', $was );
}

// Classic feed untouched.
$ok( $snapshot() === $opts_before, 'classic options unchanged by the copy' );
$ok( $cron() === $cron_before, 'classic schedules unchanged by the copy' );
$ok( ( file_exists( $path ) ? md5_file( $path ) . filemtime( $path ) : 'none' ) === $file_before, 'classic file unchanged by the copy' );

// The new file builds and its items are the classic template rendered.
$req( 'POST', "/feeds/$id/run" );
for ( $i = 0; $i < 40; $i++ ) {
	Alg_WC_PXF_Engine::tick( $id );
	$st = Alg_WC_PXF_Feeds::get_state( $id, true );
	if ( in_array( $st['status'], array( 'done', 'failed' ), true ) ) {
		break;
	}
}
$ok( 'done' === $st['status'], 'new feed run finished (' . $st['status'] . ')' );
$file = Alg_WC_PXF_Feeds::file_path( $id );
$body = file_exists( $file ) ? file_get_contents( $file ) : '';
libxml_use_internal_errors( true );
$ok( '' !== $body && false !== simplexml_load_string( $body ), 'new file is valid XML (' . strlen( $body ) . ' bytes)' );
$ok( 0 === strpos( $body, '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL . '<shop>' ), 'file starts with the copied header' );
$sale = wc_get_product_ids_on_sale();
$one  = 0;
foreach ( $sale as $pid ) {
	$p = wc_get_product( $pid );
	if ( $p && 'publish' === get_post_status( $p->get_parent_id() ? $p->get_parent_id() : $pid ) && ! $p->get_parent_id() && ! in_array( (int) $pid, array_map( 'intval', $prods ), true ) && ! has_term( (int) $cats[0], 'product_cat', $pid ) ) {
		$one = (int) $pid;
		break;
	}
}
if ( $one ) {
	$ok( false !== strpos( $body, Alg_WC_PXF_Mapper::custom_item( $one, $cfg ) ), "item of product $one is the classic template rendered" );
} else {
	echo "note: no plain on-sale product in this store to compare one item\n";
}
$ok( false === strpos( $body, '<id>' . (int) $prods[0] . '</id>' ), 'excluded product left out of the new file' );

// Option B: the classic screen highlights Product Feeds, and the classic menu item is removed.
if ( ! class_exists( 'Alg_WC_PXF_Admin' ) ) { // The admin module loads in wp-admin only.
	require_once Alg_WC_PXF::dir() . 'admin/class-alg-wc-pxf-admin-ui.php';
	require_once Alg_WC_PXF::dir() . 'admin/class-alg-wc-pxf-admin.php';
}
// The edit screen's Template tab saves without JavaScript too.
$posted = Alg_WC_PXF_Admin::sanitize_config( array( 'custom_template' => array( 'item' => '<x>[alg_product_id]</x>', 'other' => 'no' ) ) );
$ok( array( 'item' => '<x>[alg_product_id]</x>' ) === $posted['custom_template'], 'template form post keeps header, item and footer only' );

$_GET['page'] = 'wc-settings';
$_GET['tab']  = 'alg_wc_product_xml_feeds';
Alg_WC_PXF_Admin::$hook   = 'wpfactory_page_alg-pxf';
Alg_WC_PXF_Admin::$parent = 'wpfactory';
$ok( 'wpfactory' === Alg_WC_PXF_Admin::classic_parent_file( 'woocommerce' ), 'classic screen: parent menu is WPFactory' );
$ok( 'alg-pxf' === Alg_WC_PXF_Admin::classic_submenu_file( null ), 'classic screen: Product Feeds item is highlighted' );
ob_start();
Alg_WC_PXF_Admin::classic_crumb();
$crumb = ob_get_clean();
$ok( false !== strpos( $crumb, 'page=alg-pxf' ) && false !== strpos( $crumb, 'Classic feeds' ), 'classic screen: link back to Product Feeds' );
$_GET['tab'] = 'general';
$ok( 'woocommerce' === Alg_WC_PXF_Admin::classic_parent_file( 'woocommerce' ) && null === Alg_WC_PXF_Admin::classic_submenu_file( null ), 'other WooCommerce settings tabs untouched' );
unset( $_GET['page'], $_GET['tab'] );
global $submenu;
$submenu['wpfactory'] = array( array( 'Product XML Feeds', 'manage_woocommerce', Alg_WC_PXF_Admin::classic_slug() ), array( 'Product Feeds', 'manage_woocommerce', 'alg-pxf' ) ); // phpcs:ignore
Alg_WC_PXF_Admin::hide_classic_item();
$ok( array( 'alg-pxf' ) === array_values( wp_list_pluck( $submenu['wpfactory'], 2 ) ), 'menu keeps one item: Product Feeds' );

// Clean up: test feed and classic options back as they were.
Alg_WC_PXF_Feeds::delete( $id );
wp_delete_post( $id, true );
foreach ( $before_opts as $k => $v ) {
	'__none__' === $v ? delete_option( $k ) : update_option( $k, $v );
}

echo "\nclassic-copy: $pass passed, $fail failed\n";
