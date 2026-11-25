<?php
/**
 * Product XML Feeds for WooCommerce - engine tests (feeds, engine, writers, scheduler, REST).
 *
 * Run on a TEST site: wp eval-file tests/v2/engine-test.php
 * Prints PASS / FAIL lines and a SUMMARY line. Creates feeds named "PXF engine test ..." and removes them at the end.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$pass  = 0;
$fail  = 0;
$check = function ( $ok, $label, $detail = '' ) use ( &$pass, &$fail ) {
	if ( $ok ) {
		$pass++;
		echo 'PASS ' . $label . "\n";
	} else {
		$fail++;
		echo 'FAIL ' . $label . ( '' !== (string) $detail ? ' (' . $detail . ')' : '' ) . "\n";
	}
};

foreach ( array( 'Alg_WC_PXF_Feeds', 'Alg_WC_PXF_Engine', 'Alg_WC_PXF_Writer', 'Alg_WC_PXF_Scheduler', 'Alg_WC_PXF_REST', 'Alg_WC_PXF_Channels', 'Alg_WC_PXF_Product_Data', 'Alg_WC_PXF_Mapper' ) as $class ) {
	$check( class_exists( $class ), 'class ' . $class . ' loaded' );
}
if ( ! class_exists( 'Alg_WC_PXF_Engine' ) || ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
	echo "SUMMARY: stopped, classes missing\n";
	return;
}

$admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $admin->ID );

// Leftovers from an earlier run of this test.
$cleanup = function () {
	foreach ( Alg_WC_PXF_Feeds::all() as $old ) {
		if ( 0 === strpos( (string) get_post_field( 'post_title', $old, 'raw' ), 'PXF engine test' ) ) {
			Alg_WC_PXF_Feeds::delete( $old );
		}
	}
	foreach ( (array) glob( Alg_WC_PXF_Feeds::dir() . '/pxf-*' ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
};
$cleanup();

$run_to_end = function ( $feed_id, $restart = true ) {
	$state = Alg_WC_PXF_Engine::run( $feed_id, 'test', $restart );
	if ( is_wp_error( $state ) ) {
		return array( 'status' => 'wp_error', 'error' => $state->get_error_message(), 'batches' => 0, 'written' => 0, 'batch_size' => 0 );
	}
	for ( $i = 0; $i < 500; $i++ ) {
		$state = Alg_WC_PXF_Engine::tick( $feed_id );
		if ( ! in_array( $state['status'], array( 'queued', 'running' ), true ) ) {
			break;
		}
	}
	return $state;
};

// Expected items for variation_mode "all": published products (not variable, not grouped) plus published variations.
$expected = array();
foreach ( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) ) as $pid ) {
	$p = wc_get_product( $pid );
	if ( $p->is_type( 'grouped' ) ) {
		continue;
	}
	if ( $p->is_type( 'variable' ) ) {
		foreach ( $p->get_children() as $cid ) {
			if ( 'publish' === get_post_status( $cid ) ) {
				$expected[] = (string) $cid;
			}
		}
		continue;
	}
	$expected[] = (string) $pid;
}
sort( $expected );
echo 'INFO expected items (published products and variations): ' . count( $expected ) . "\n";

$compare = function ( array $got, $label ) use ( $expected, $check ) {
	sort( $got );
	$dupes   = count( $got ) - count( array_unique( $got ) );
	$missing = array_diff( $expected, $got );
	$extra   = array_diff( $got, $expected );
	$check( 0 === $dupes, $label . ': no duplicates', $dupes . ' duplicates' );
	$check( empty( $missing ), $label . ': none missing', count( $missing ) . ' missing, e.g. ' . implode( ',', array_slice( $missing, 0, 5 ) ) );
	$check( empty( $extra ), $label . ': nothing extra', count( $extra ) . ' extra' );
};

$edge_skus = array( 'EDGE-AMP', 'EDGE-CDATA', 'EDGE-CTRL', 'EDGE-RTL', 'EDGE-EMOJI' );
$edge_ids  = array();
foreach ( $edge_skus as $sku ) {
	$edge_ids[ $sku ] = (string) wc_get_product_id_by_sku( $sku );
}

// 1. Writer round trip with nasty values (XML, UTF-8 and ISO-8859-7).
Alg_WC_PXF_Feeds::ensure_dir();
$nasty   = "Salt & Pepper <Set of 2> \"Chef\" ]]> it's \x0B ctrl \u{2615} \u{05DE}\u{05D2}\u{05D1}\u{05EA} \xC3\x28 end";
$channel = Alg_WC_PXF_Channels::get( 'google' );
foreach ( array( 'UTF-8', 'ISO-8859-7' ) as $encoding ) {
	$spec             = $channel;
	$spec['encoding'] = $encoding;
	$path             = Alg_WC_PXF_Feeds::dir() . '/pxf-writer-test-' . strtolower( $encoding ) . '.xml';
	$writer           = Alg_WC_PXF_Writer::open( 'xml', $spec, $path );
	$writer->begin();
	$writer->item( array( 'id' => '1', 'title' => $nasty . " \u{0391}\u{03B8}\u{03AE}\u{03BD}\u{03B1}", 'shipping' => array( 'country' => 'DE', 'price' => '4.95 EUR' ), 'additional_image_link' => array( 'https://example.test/a.jpg', 'https://example.test/b.jpg' ) ) );
	$writer->close();
	$writer = Alg_WC_PXF_Writer::open( 'xml', $spec, $path );
	$writer->resume( filesize( $writer->tmp_path() ) );
	$writer->item( array( 'id' => '2', 'title' => 'Second' ) );
	$ok = $writer->end();
	libxml_use_internal_errors( true );
	$doc  = simplexml_load_file( $path );
	$errs = libxml_get_errors();
	libxml_clear_errors();
	$check( $ok && false !== $doc && empty( $errs ), 'writer ' . $encoding . ': well-formed with &, <, ", ]]>, control char, emoji, Hebrew, bad UTF-8', $errs ? trim( $errs[0]->message ) : '' );
	if ( $doc ) {
		$g     = $doc->channel->item[0]->children( 'http://base.google.com/ns/1.0' );
		$title = (string) $g->title;
		$check( false !== strpos( $title, 'Salt & Pepper <Set of 2> "Chef" ]]>' ) && false !== strpos( $title, "\u{2615}" ) && false !== strpos( $title, "\u{05DE}\u{05D2}" ) && false !== strpos( $title, "\u{0391}\u{03B8}" ) && false === strpos( $title, "\x0B" ), 'writer ' . $encoding . ': values come back intact (control char removed)' );
		$check( 2 === count( $doc->channel->item ) && 2 === count( $g->additional_image_link ) && 'DE' === (string) $g->shipping->children( 'http://base.google.com/ns/1.0' )->country, 'writer ' . $encoding . ': resume appends, lists repeat, sub-attributes nest' );
	}
	if ( 'ISO-8859-7' === $encoding ) {
		$head = file_get_contents( $path, false, null, 0, 60 );
		$check( false !== strpos( $head, 'encoding="ISO-8859-7"' ) && false === strpos( file_get_contents( $path ), "\xCE\x91" ), 'writer ISO-8859-7: declared and converted' );
	}
}

// 1b. Writer: head_position root, list_item, list_sep, json bool, raw items with set_parts.
$spec = array(
	'xml'    => array( 'root' => 'mywebstore', 'wrap' => 'products', 'item' => 'product', 'head' => array( 'created_at' => '{datetime}' ), 'head_position' => 'root' ),
	'csv'    => array( 'separator' => ';' ),
	'fields' => array(
		'id'         => array(),
		'certification' => array(),
		'images'     => array( 'list' => true, 'list_sep' => '|' ),
		'variations' => array( 'list' => true, 'list_item' => 'variation' ),
		'eligible'   => array( 'json' => 'bool' ),
	),
);
$item = array( 'id' => '7', 'certification' => array( 'certification_authority' => 'EC', 'certification_name' => 'EPREL', 'certification_code' => '123' ), 'images' => array( 'https://example.test/a.jpg', 'https://example.test/b.jpg' ), 'variations' => array( array( 'size' => 'S' ), array( 'size' => 'M' ) ), 'eligible' => 'true', '_internal' => 'x' );
$path = Alg_WC_PXF_Feeds::dir() . '/pxf-writer-spec.xml';
$w    = Alg_WC_PXF_Writer::open( 'xml', $spec, $path );
$w->begin();
$w->item( $item );
$w->end();
$doc = simplexml_load_file( $path );
$check( $doc && 'created_at' === $doc->children()[0]->getName() && preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d$/', (string) $doc->created_at ) && 2 === count( $doc->products->product->variations->variation ) && 'M' === (string) $doc->products->product->variations->variation[1]->size && ! isset( $doc->products->product->_internal ) && 'EPREL' === (string) $doc->products->product->certification->certification_name, 'writer XML: head_position root, certification sub-attribute, {datetime}, list_item nesting, internal keys dropped' );
$path = Alg_WC_PXF_Feeds::dir() . '/pxf-writer-spec.csv';
$w    = Alg_WC_PXF_Writer::open( 'csv', $spec, $path );
$w->begin();
$w->item( $item );
$w->end();
$lines = file( $path, FILE_IGNORE_NEW_LINES );
$check( 'id;certification;images;variations;eligible' === $lines[0] && 0 === strpos( $lines[1], '7;EC:EPREL:123;https://example.test/a.jpg|https://example.test/b.jpg;' ), 'writer CSV: channel csv separator, field list_sep, sub-attributes joined with colons', isset( $lines[1] ) ? $lines[1] : '' );
$path = Alg_WC_PXF_Feeds::dir() . '/pxf-writer-spec.jsonl';
$w    = Alg_WC_PXF_Writer::open( 'jsonl', $spec, $path );
$w->begin();
$w->item( $item );
$w->end();
$obj = json_decode( trim( file_get_contents( $path ) ), true );
$check( is_array( $obj ) && true === $obj['eligible'] && ! isset( $obj['_internal'] ), 'writer JSONL: json bool fields become true/false' );
$path = Alg_WC_PXF_Feeds::dir() . '/pxf-writer-raw.xml';
$w    = Alg_WC_PXF_Writer::open( 'xml', array(), $path );
$w->set_parts( array( 'header' => "<list>\n" ) );
$w->begin();
$w->item( array( '_raw' => "<one a=\"1\">R&amp;D\x0B</one>\n" ) );
$w->set_parts( array( 'footer' => "</list>\n" ) );
$w->end();
$check( "<list>\n<one a=\"1\">R&amp;D</one>\n</list>\n" === file_get_contents( $path ), 'writer: _raw items (control chars removed) and header/footer parts written as is' );

// 2. Feed record.
$feed = Alg_WC_PXF_Feeds::create( array( 'name' => 'PXF engine test XML', 'channel' => 'google', 'file_slug' => 'pxf-engine-test', 'variation_mode' => 'all', 'schedule' => 'manual' ) );
$check( is_int( $feed ) && $feed > 0 && 'alg_pxf_feed' === get_post_type( $feed ), 'create feed (custom post type alg_pxf_feed)' );
$config = Alg_WC_PXF_Feeds::get_config( $feed );
$check( 'all' === $config['variation_mode'] && false === $config['gzip'] && array() === $config['rules'] && array() === $config['eu'] && isset( $config['filters']['on_sale'] ), 'config defaults merged (rules, eu, filters)' );
$feed2 = Alg_WC_PXF_Feeds::create( array( 'name' => 'PXF engine test slug', 'channel' => 'google', 'file_slug' => 'pxf-engine-test' ) );
$check( 'pxf-engine-test-2' === Alg_WC_PXF_Feeds::get_config( $feed2 )['file_slug'], 'file slug made unique' );
Alg_WC_PXF_Feeds::delete( $feed2 );
$pt = get_post_type_object( 'alg_pxf_feed' );
$check( $pt && ! $pt->public && ! $pt->show_in_rest && 'manage_woocommerce' === $pt->cap->edit_posts && 'manage_woocommerce' === $pt->cap->delete_post, 'post type private, capabilities mapped to manage_woocommerce' );

// 3. Full XML run, timed.
$t0    = microtime( true );
$state = $run_to_end( $feed );
$secs  = microtime( true ) - $t0;
$check( 'done' === $state['status'], 'XML run done through tick()', $state['error'] );
printf( "INFO full run: %d items from %d products in %.2f s, %d batches, next batch size %d, peak memory %.1f MB\n", $state['written'], $state['total'], $secs, $state['batches'], $state['batch_size'], memory_get_peak_usage( true ) / 1048576 );
$path = Alg_WC_PXF_Feeds::file_path( $feed );
$check( file_exists( $path ) && ! file_exists( $path . '.tmp' ), 'XML file published, no .tmp left' );
$check( file_exists( Alg_WC_PXF_Feeds::dir() . '/index.php' ), 'index.php in uploads/product-feeds' );
$check( ! Alg_WC_PXF_Engine::is_locked( $feed ), 'run lock released after done' );
libxml_use_internal_errors( true );
$xml  = simplexml_load_file( $path );
$errs = libxml_get_errors();
libxml_clear_errors();
$check( false !== $xml && empty( $errs ), 'XML well-formed (simplexml) with the edge products', $errs ? trim( $errs[0]->message ) : '' );
$ids    = array();
$titles = array();
if ( $xml ) {
	foreach ( $xml->channel->item as $it ) {
		$g                         = $it->children( 'http://base.google.com/ns/1.0' );
		$ids[]                     = (string) $g->id;
		$titles[ (string) $g->id ] = (string) $g->title;
	}
}
$compare( $ids, 'XML variation_mode all' );
$check( (int) $state['written'] === count( $ids ), 'state written equals items in the file', $state['written'] . ' vs ' . count( $ids ) );
foreach ( $edge_ids as $sku => $eid ) {
	$model = Alg_WC_PXF_Product_Data::model( wc_get_product( $eid ), $config );
	$want  = Alg_WC_PXF_Writer::clean( $model['title'] );
	$check( isset( $titles[ $eid ] ) && $titles[ $eid ] === $want, 'XML ' . $sku . ' title round trip', isset( $titles[ $eid ] ) ? $titles[ $eid ] : 'missing' );
}
$check( is_array( $state['issues'] ) && array_sum( $state['issues'] ) > 0 && is_array( get_transient( 'alg_pxf_issues_' . $feed ) ), 'issues counted by code and stored' );
$good_md5 = md5_file( $path );

// 4. CSV, TSV, JSONL.
$formats = (array) Alg_WC_PXF_Channels::get( 'google' )['formats'];
foreach ( array( 'csv' => ',', 'tsv' => "\t" ) as $format => $sep ) {
	if ( ! in_array( $format, $formats, true ) ) {
		echo 'SKIP ' . strtoupper( $format ) . ": the google channel does not offer this format\n";
		continue;
	}
	$sep = 'tsv' === $format ? "\t" : ( isset( Alg_WC_PXF_Channels::get( 'google' )['csv']['separator'] ) ? Alg_WC_PXF_Channels::get( 'google' )['csv']['separator'] : ',' );
	Alg_WC_PXF_Feeds::save_config( $feed, array( 'format' => $format ) );
	$state = $run_to_end( $feed );
	$file  = Alg_WC_PXF_Feeds::file_path( $feed );
	$check( 'done' === $state['status'] && '.' . $format === substr( $file, -1 - strlen( $format ) ), strtoupper( $format ) . ' run done', $state['error'] );
	$h      = fopen( $file, 'r' );
	$header = fgetcsv( $h, 0, $sep, '"', '' );
	$col    = array_search( 'id', $header, true );
	$tcol   = array_search( 'title', $header, true );
	$rows   = 0;
	$bad    = 0;
	$got    = array();
	$ctitle = array();
	while ( false !== ( $row = fgetcsv( $h, 0, $sep, '"', '' ) ) ) {
		if ( array( null ) === $row ) {
			continue;
		}
		$rows++;
		if ( count( $row ) !== count( $header ) ) {
			$bad++;
		}
		$got[]                 = $row[ $col ];
		$ctitle[ $row[ $col ] ] = $row[ $tcol ];
	}
	fclose( $h );
	$check( false !== $col && 0 === $bad, strtoupper( $format ) . ' parses, every row has ' . count( $header ) . ' columns', $bad . ' bad rows' );
	$compare( $got, strtoupper( $format ) );
	$amp_model = Alg_WC_PXF_Product_Data::model( wc_get_product( $edge_ids['EDGE-AMP'] ), $config );
	$check( isset( $ctitle[ $edge_ids['EDGE-AMP'] ] ) && $ctitle[ $edge_ids['EDGE-AMP'] ] === Alg_WC_PXF_Writer::clean( $amp_model['title'] ), strtoupper( $format ) . ' EDGE-AMP title round trip' );
}
$jsonl_channel = in_array( 'jsonl', $formats, true ) ? 'google' : ( Alg_WC_PXF_Channels::get( 'openai' ) ? 'openai' : '' );
if ( '' === $jsonl_channel ) {
	echo "SKIP JSONL: no channel offers it\n";
} else {
	$jfeed = 'google' === $jsonl_channel ? $feed : Alg_WC_PXF_Feeds::create( array( 'name' => 'PXF engine test JSONL', 'channel' => $jsonl_channel, 'file_slug' => 'pxf-jsonl-test', 'variation_mode' => 'all', 'filters' => array() ) );
	Alg_WC_PXF_Feeds::save_config( $jfeed, array( 'format' => 'jsonl', 'variation_mode' => 'all' ) );
	$state = $run_to_end( $jfeed );
	$file  = Alg_WC_PXF_Feeds::file_path( $jfeed );
	$check( 'done' === $state['status'] && '.jsonl' === substr( $file, -6 ), 'JSONL run done (' . $jsonl_channel . ')', $state['error'] );
	$got = array();
	$bad = 0;
	foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$obj = json_decode( $line, true );
		if ( ! is_array( $obj ) ) {
			$bad++;
			continue;
		}
		$got[] = isset( $obj['id'] ) ? (string) $obj['id'] : ( isset( $obj['item_id'] ) ? (string) $obj['item_id'] : '' );
	}
	$check( 0 === $bad && count( $got ) === (int) $state['written'], 'JSONL every line parses', $bad . ' bad lines' );
	if ( 'google' === $jsonl_channel ) {
		$compare( $got, 'JSONL' );
	} else {
		Alg_WC_PXF_Feeds::delete( $jfeed );
	}
}

// 5. Gzip.
Alg_WC_PXF_Feeds::save_config( $feed, array( 'format' => 'xml', 'gzip' => true ) );
$state = $run_to_end( $feed );
$file  = Alg_WC_PXF_Feeds::file_path( $feed );
$raw   = file_exists( $file ) ? gzdecode( file_get_contents( $file ) ) : false;
$gxml  = $raw ? simplexml_load_string( $raw ) : false;
$check( 'done' === $state['status'] && '.xml.gz' === substr( $file, -7 ) && false !== $gxml && count( $expected ) === count( $gxml->channel->item ), 'gzip output gunzips to valid XML with every item' );
$check( ! file_exists( $file . '.tmp' ) && ! file_exists( substr( $file, 0, -3 ) . '.tmp' ), 'gzip leaves no .tmp files' );
Alg_WC_PXF_Feeds::save_config( $feed, array( 'gzip' => false ) );

// 6. A failed run keeps the last good file.
$path = Alg_WC_PXF_Feeds::file_path( $feed );
$state = $run_to_end( $feed );
$good_md5 = md5_file( $path );
$breaker  = function ( $feed_id, $config, $state ) {
	if ( (int) $state['batches'] >= 1 ) {
		throw new RuntimeException( 'Forced failure for the test' );
	}
};
add_action( 'alg_wc_pxf_batch_start', $breaker, 10, 3 );
$failed_hook = 0;
$on_fail     = function () use ( &$failed_hook ) {
	$failed_hook++;
};
add_action( 'alg_wc_pxf_run_failed', $on_fail );
$state = $run_to_end( $feed );
remove_action( 'alg_wc_pxf_batch_start', $breaker, 10 );
remove_action( 'alg_wc_pxf_run_failed', $on_fail );
$check( 'failed' === $state['status'] && false !== strpos( $state['error'], 'Forced failure' ) && 1 === $failed_hook, 'forced failure ends the run as failed and fires alg_wc_pxf_run_failed', $state['status'] . ' ' . $state['error'] );
$check( md5_file( $path ) === $good_md5 && ! file_exists( $path . '.tmp' ), 'failed run keeps the last good file, .tmp removed' );
$check( ! Alg_WC_PXF_Engine::is_locked( $feed ), 'run lock released after failure' );
Alg_WC_PXF_Feeds::save_config( $feed, array( 'filters' => array( 'include_cats' => array( 999999 ) ) ) );
$state = $run_to_end( $feed );
$check( 'failed' === $state['status'] && md5_file( $path ) === $good_md5, 'a run with 0 products keeps the last good file', $state['error'] );
Alg_WC_PXF_Feeds::save_config( $feed, array( 'filters' => array( 'include_cats' => array() ) ) );

// 7. Stale run id, one run per feed, busy batch, tick fallback.
$done_args = array();
$on_done   = function ( $feed_id, $state, $file ) use ( &$done_args ) {
	$done_args = array( $feed_id, $state['status'], $file );
};
add_action( 'alg_wc_pxf_run_done', $on_done, 10, 3 );
$s1 = Alg_WC_PXF_Engine::run( $feed, 'test', true );
$r1 = $s1['run_id'];
Alg_WC_PXF_Engine::tick( $feed );
$mid = Alg_WC_PXF_Feeds::get_state( $feed, true );
$check( 'running' === $mid['status'] && $mid['done'] > 0 && $mid['done'] < $mid['total'] && $mid['cursor']['last_id'] > 0, 'first batch saved the cursor', $mid['status'] . ' ' . $mid['done'] . '/' . $mid['total'] );
$same = Alg_WC_PXF_Engine::run( $feed, 'test', false );
$check( $same['run_id'] === $r1, 'one run per feed: a second run() returns the running one' );
$s2 = Alg_WC_PXF_Engine::run( $feed, 'test', true );
$r2 = $s2['run_id'];
$check( $r2 !== $r1, 'restart gives a new run id' );
$result = Alg_WC_PXF_Engine::batch( $feed, $r1 );
$after  = Alg_WC_PXF_Feeds::get_state( $feed, true );
$check( 'stale' === $result && $after['run_id'] === $r2 && 0 === (int) $after['batches'] && 0 === (int) $after['done'], 'a stale run id stops without touching the new run', $result );
$lock = Alg_WC_PXF_Engine::batch_lock_name( $feed );
Alg_WC_PXF_Engine::lock_acquire( $lock, 60 );
$check( 'busy' === Alg_WC_PXF_Engine::batch( $feed, $r2 ), 'a second batch at the same time waits (busy)' );
Alg_WC_PXF_Engine::lock_release( $lock );
$state = $run_to_end( $feed, false );
$check( 'done' === $state['status'] && $state['run_id'] === $r2, 'restarted run finishes' );
$xml = simplexml_load_file( $path );
$ids = array();
foreach ( $xml->channel->item as $it ) {
	$ids[] = (string) $it->children( 'http://base.google.com/ns/1.0' )->id;
}
$compare( $ids, 'XML after restart' );
$check( array( $feed, 'done', $path ) === $done_args, 'alg_wc_pxf_run_done fires with feed id, state and file path' );
remove_action( 'alg_wc_pxf_run_done', $on_done, 10 );

// 7b. Items marked _exclude (feed rules) are skipped and counted.
$exclude = function ( $item, $model ) use ( $edge_ids ) {
	if ( isset( $model['id'] ) && $edge_ids['EDGE-AMP'] === (string) $model['id'] ) {
		$item['_exclude'] = true;
	}
	return $item;
};
add_filter( 'alg_wc_pxf_item', $exclude, 10, 2 );
$state = $run_to_end( $feed );
remove_filter( 'alg_wc_pxf_item', $exclude, 10 );
$xml = simplexml_load_file( $path );
$ids = array();
foreach ( $xml->channel->item as $it ) {
	$ids[] = (string) $it->children( 'http://base.google.com/ns/1.0' )->id;
}
$check( 'done' === $state['status'] && 1 === (int) $state['excluded'] && 1 === (int) $state['left_out'] && ! in_array( $edge_ids['EDGE-AMP'], $ids, true ) && count( $expected ) - 1 === count( $ids ), 'an item with _exclude is left out and counted in left_out' );

// 7c. Custom channel: raw items, header and footer from the mapper.
$custom = Alg_WC_PXF_Feeds::create( array( 'name' => 'PXF engine test custom', 'channel' => 'custom', 'file_slug' => 'pxf-custom-test' ) );
$state  = $run_to_end( $custom );
$cpath  = Alg_WC_PXF_Feeds::file_path( $custom );
$cxml   = file_exists( $cpath ) ? simplexml_load_file( $cpath ) : false;
$want   = count( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids', 'type' => array( 'simple', 'external', 'variable' ) ) ) );
$check( 'done' === $state['status'] && $cxml && $want === (int) $state['written'], 'custom channel: raw items between the mapper header and footer, valid XML', $state['error'] );
Alg_WC_PXF_Feeds::delete( $custom );

// 8. Variation modes.
foreach ( array( 'parent', 'default', 'cheapest' ) as $mode ) {
	Alg_WC_PXF_Feeds::save_config( $feed, array( 'variation_mode' => $mode ) );
	$state = $run_to_end( $feed );
	$xml   = simplexml_load_file( $path );
	$count = $xml ? count( $xml->channel->item ) : -1;
	$want  = count( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids', 'type' => array( 'simple', 'external', 'variable' ) ) ) );
	$check( 'done' === $state['status'] && $count === $want, 'variation_mode ' . $mode . ': one item per product', $count . ' vs ' . $want );
}
Alg_WC_PXF_Feeds::save_config( $feed, array( 'variation_mode' => 'all' ) );

// 9. Scheduler.
Alg_WC_PXF_Feeds::save_config( $feed, array( 'schedule' => 'daily' ) );
$event = wp_get_scheduled_event( 'alg_wc_pxf_scheduled', array( $feed ) );
$check( $event && 'daily' === $event->schedule, 'daily schedule creates the WP-Cron event' );
Alg_WC_PXF_Feeds::save_config( $feed, array( 'schedule' => '15min' ) );
$event = wp_get_scheduled_event( 'alg_wc_pxf_scheduled', array( $feed ) );
$check( Alg_WC_PXF::is_pro() && $event && 'alg_pxf_15min' === $event->schedule, 'Pro: 15 minute schedule' );
add_filter( 'alg_wc_pxf_is_pro', '__return_false' );
$schedules = wp_get_schedules();
Alg_WC_PXF_Scheduler::sync( $feed );
$event = wp_get_scheduled_event( 'alg_wc_pxf_scheduled', array( $feed ) );
$check( ! isset( $schedules['alg_pxf_15min'] ) && $event && 'daily' === $event->schedule && '15min' === Alg_WC_PXF_Feeds::get_config( $feed )['schedule'], 'free: 15 minutes runs daily, stored value kept, interval not registered' );
remove_filter( 'alg_wc_pxf_is_pro', '__return_false' );
Alg_WC_PXF_Feeds::save_config( $feed, array( 'schedule' => 'manual', 'rebuild_on_change' => true ) );
$check( false === wp_get_scheduled_event( 'alg_wc_pxf_scheduled', array( $feed ) ), 'manual schedule removes the event' );
do_action( 'woocommerce_update_product', (int) $edge_ids['EDGE-AMP'], wc_get_product( $edge_ids['EDGE-AMP'] ) );
$pending = as_get_scheduled_actions( array( 'hook' => 'alg_wc_pxf_rebuild', 'args' => array( $feed ), 'group' => 'alg-pxf', 'status' => ActionScheduler_Store::STATUS_PENDING ), 'ids' );
$check( 1 === count( $pending ), 'Pro: product change queues one rebuild' );
Alg_WC_PXF_Scheduler::clear( $feed );

// 10. REST: logged out 401, admin 200, contributor 403.
$amp       = (int) $edge_ids['EDGE-AMP'];
$rest_feed = Alg_WC_PXF_Feeds::create( array( 'name' => 'PXF engine test REST delete', 'channel' => 'google' ) );
$routes    = array(
	array( 'GET', '/alg-pxf/v1/channels', array() ),
	array( 'GET', '/alg-pxf/v1/feeds', array() ),
	array( 'POST', '/alg-pxf/v1/feeds', array( 'name' => 'PXF engine test REST', 'channel' => 'google', 'country' => 'DE' ) ),
	array( 'GET', '/alg-pxf/v1/feeds/' . $feed, array() ),
	array( 'PUT', '/alg-pxf/v1/feeds/' . $feed, array( 'config' => array( 'gzip' => false ) ) ),
	array( 'POST', '/alg-pxf/v1/feeds/' . $feed . '/run', array() ),
	array( 'GET', '/alg-pxf/v1/feeds/' . $feed . '/status', array() ),
	array( 'GET', '/alg-pxf/v1/feeds/' . $feed . '/preview', array( 'product' => $amp ) ),
	array( 'GET', '/alg-pxf/v1/feeds/' . $feed . '/issues', array() ),
	array( 'GET', '/alg-pxf/v1/taxonomy', array( 'q' => 'shirt' ) ),
	array( 'GET', '/alg-pxf/v1/category-map', array() ),
	array( 'PUT', '/alg-pxf/v1/category-map', array( 'map' => array() ) ),
	array( 'DELETE', '/alg-pxf/v1/feeds/' . $rest_feed, array() ),
);
$call = function ( $method, $route, $params ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} elseif ( ! empty( $params ) ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	}
	return rest_do_request( $request );
};
foreach ( array( 0 => 401, 'writer' => 403, 'admin' => 200 ) as $who => $want ) {
	$user = $who ? get_user_by( 'login', $who ) : null;
	wp_set_current_user( $user ? $user->ID : 0 );
	$wrong = array();
	$data  = array();
	foreach ( $routes as $r ) {
		$res = $call( $r[0], $r[1], $r[2] );
		if ( $want !== $res->get_status() ) {
			$wrong[] = $r[0] . ' ' . $r[1] . ' = ' . $res->get_status();
		}
		$data[ $r[0] . ' ' . $r[1] ] = $res->get_data();
	}
	$check( empty( $wrong ), 'REST ' . ( $who ? $who : 'logged out' ) . ': every route ' . $want, implode( '; ', $wrong ) );
}
$list    = $data[ 'GET /alg-pxf/v1/feeds' ];
$types   = wp_list_pluck( $list, 'type' );
$classic = array_values( wp_list_filter( $list, array( 'type' => 'classic' ) ) );
$check( in_array( 'v2', $types, true ) && ! empty( $classic ) && 1 === $classic[0]['n'] && false !== strpos( $classic[0]['url'], '.xml' ), 'GET /feeds lists v2 and classic feeds' );
$preview = $data[ 'GET /alg-pxf/v1/feeds/' . $feed . '/preview' ];
$check( isset( $preview['model']['title'], $preview['item']['id'] ) && (string) $amp === $preview['item']['id'], 'preview returns model, item and issues' );
$created = $data[ 'POST /alg-pxf/v1/feeds' ];
$check( isset( $created['id'], $created['config']['country'] ) && 'DE' === $created['config']['country'], 'POST /feeds creates a feed with its config' );
$check( ! Alg_WC_PXF_Feeds::exists( $rest_feed ), 'DELETE /feeds/ID deletes the feed' );
$check( isset( $data[ 'GET /alg-pxf/v1/feeds/' . $feed . '/issues' ]['counts'] ), 'issues route returns counts' );

// 11. Tick fallback from the status route (no batch for over a minute).
$state = Alg_WC_PXF_Engine::run( $feed, 'test', true );
$state['started'] = time() - 120;
Alg_WC_PXF_Feeds::save_state( $feed, $state );
$res   = $call( 'GET', '/alg-pxf/v1/feeds/' . $feed . '/status', array() );
$after = Alg_WC_PXF_Feeds::get_state( $feed, true );
$check( 200 === $res->get_status() && (int) $after['batches'] >= 1, 'status route runs a batch when none ran for a minute' );
$run_to_end( $feed, false );

// 12. Delete keeps the file under a .deleted name.
$path = Alg_WC_PXF_Feeds::file_path( $feed );
Alg_WC_PXF_Feeds::delete( $feed );
$check( ! file_exists( $path ) && file_exists( $path . '.deleted' ), 'delete renames the file to .deleted' );

$cleanup();
echo 'SUMMARY: ' . $pass . ' passed, ' . $fail . ' failed' . "\n";
