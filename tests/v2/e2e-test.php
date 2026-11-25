<?php
/**
 * End to end through REST, as the admin screens do it: create a feed per main channel, run it to the end, read the file.
 * Run: wp eval-file tests/v2/e2e-test.php   (test only; not shipped)
 */
defined( 'ABSPATH' ) || exit;
wp_set_current_user( 1 );
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

list( $s, $channels ) = $req( 'GET', '/channels' );
$ok( 200 === $s && count( $channels ) >= 10, 'GET /channels lists ' . count( (array) $channels ) . ' channels' );

foreach ( array( 'google' => 'xml', 'meta' => 'xml', 'openai' => 'jsonl', 'idealo' => 'csv', 'skroutz' => 'xml' ) as $ch => $fmt ) {
	list( $s, $feed ) = $req( 'POST', '/feeds', array( 'name' => 'E2E ' . $ch, 'channel' => $ch, 'country' => 'DE' ) );
	$id = isset( $feed['id'] ) ? (int) $feed['id'] : 0;
	$ok( in_array( $s, array( 200, 201 ), true ) && $id > 0, "POST /feeds $ch (id $id)" );
	if ( ! $id ) {
		continue;
	}
	$req( 'POST', "/feeds/$id/run" );
	$state = array();
	for ( $i = 0; $i < 40; $i++ ) {
		Alg_WC_PXF_Engine::tick( $id );
		list( , $state ) = $req( 'GET', "/feeds/$id/status" );
		$st = isset( $state['status'] ) ? $state['status'] : ( isset( $state['state']['status'] ) ? $state['state']['status'] : '' );
		if ( in_array( $st, array( 'done', 'failed' ), true ) ) {
			break;
		}
	}
	$flat = wp_json_encode( $state );
	$ok( false !== strpos( $flat, '"done"' ), "$ch run finished" );
	list( , $f ) = $req( 'GET', "/feeds/$id" );
	$url  = '';
	array_walk_recursive( $f, function ( $v, $k ) use ( &$url ) { if ( 'url' === $k && is_string( $v ) && '' === $url ) { $url = $v; } } );
	$path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $url );
	$body = ( $url && file_exists( $path ) ) ? file_get_contents( $path ) : '';
	$ok( strlen( $body ) > 1000, "$ch file written (" . strlen( $body ) . ' bytes) at ' . basename( $path ) );
	if ( 'xml' === $fmt && $body ) {
		libxml_use_internal_errors( true );
		$ok( false !== simplexml_load_string( $body ), "$ch file is valid XML" );
	}
	if ( 'jsonl' === $fmt && $body ) {
		$lines = array_filter( explode( "\n", $body ) );
		$bad   = 0;
		foreach ( $lines as $l ) {
			if ( null === json_decode( $l, true ) ) {
				$bad++;
			}
		}
		$ok( 0 === $bad && count( $lines ) > 100, "$ch JSONL: " . count( $lines ) . ' lines, ' . $bad . ' bad' );
	}
	if ( 'csv' === $fmt && $body ) {
		$rows = array_map( 'str_getcsv', array_filter( explode( "\n", $body ) ) );
		$ok( count( $rows ) > 100 && count( $rows[0] ) > 5, "$ch CSV: " . count( $rows ) . ' rows, ' . count( $rows[0] ) . ' columns' );
	}
	list( $s, $issues ) = $req( 'GET', "/feeds/$id/issues" );
	$ok( 200 === $s, "$ch issues summary: " . substr( wp_json_encode( $issues ), 0, 160 ) );
	list( $s, $pv ) = $req( 'GET', "/feeds/$id/preview", array( 'product' => wc_get_product_id_by_sku( 'EDGE-SALE' ) ) );
	$ok( 200 === $s && ! empty( $pv ), "$ch preview of EDGE-SALE" );
	$req( 'DELETE', "/feeds/$id" );
}

// Classic feed still listed and untouched.
list( $s, $feeds ) = $req( 'GET', '/feeds' );
$ok( false !== strpos( wp_json_encode( $feeds ), 'classic' ), 'GET /feeds lists the classic feed' );

// Logged out: 401.
wp_set_current_user( 0 );
list( $s ) = $req( 'GET', '/feeds' );
$ok( 401 === $s, 'logged-out GET /feeds is 401' );

echo "SUMMARY: $pass passed, $fail failed\n";
