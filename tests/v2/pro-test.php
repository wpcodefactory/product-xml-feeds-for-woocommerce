<?php
/**
 * Product XML Feeds for WooCommerce - Pro tests for channel feeds: rules engine and EU pack.
 *
 * Run on a test site with the Pro plugin active:
 *     wp eval-file wp-content/plugins/product-xml-feeds-for-woocommerce-pro/tests/v2/pro-test.php
 * Prints one PASS or FAIL line per check and a summary. Creates two draft test products and deletes them at the end.
 * Where the real v2 model and mapper classes are missing, tests/v2/pro-stubs.php (loaded by a mu-plugin on the test
 * site only) stands in for them.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['alg_pxf_t'] = array( 'pass' => 0, 'fail' => 0 );

/**
 * alg_pxf_t: one check.
 *
 * @param   string $name   Check name.
 * @param   bool   $ok     Result.
 * @param   mixed  $detail Shown on failure.
 */
function alg_pxf_t( $name, $ok, $detail = '' ) {
	if ( $ok ) {
		$GLOBALS['alg_pxf_t']['pass']++;
		echo 'PASS ' . $name . "\n";
	} else {
		$GLOBALS['alg_pxf_t']['fail']++;
		echo 'FAIL ' . $name . ( '' !== $detail ? ' :: ' . ( is_scalar( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) . "\n";
	}
}

/**
 * alg_pxf_day: Y-m-d in the site time zone, $days from today.
 *
 * @param   int $days Days.
 * @return  string
 */
function alg_pxf_day( $days ) {
	return Alg_WC_PXF_Pro_EU::shift_day( Alg_WC_PXF_Pro_EU::today(), $days );
}

/**
 * alg_pxf_history: builds a history from ranges array( from_offset, to_offset, price ).
 *
 * @param   array $ranges Ranges.
 * @return  array
 */
function alg_pxf_history( array $ranges ) {
	$history = array();
	foreach ( $ranges as $range ) {
		for ( $d = $range[0]; $d <= $range[1]; $d++ ) {
			$history[ alg_pxf_day( $d ) ] = (string) $range[2];
		}
	}
	ksort( $history );
	return $history;
}

// ---------------------------------------------------------------------------------------------------------------
// Set-up.
// ---------------------------------------------------------------------------------------------------------------

alg_pxf_t( 'Pro v2 classes loaded', class_exists( 'Alg_WC_PXF_Pro_Rules' ) && class_exists( 'Alg_WC_PXF_Pro_EU' ) );
alg_pxf_t( 'is_pro', class_exists( 'Alg_WC_PXF' ) && Alg_WC_PXF::is_pro() );
alg_pxf_t( 'rules hooked on alg_wc_pxf_item at 20', 20 === has_filter( 'alg_wc_pxf_item', array( 'Alg_WC_PXF_Pro_Rules', 'filter_item' ) ) );
alg_pxf_t( 'EU hooked on alg_wc_pxf_item at 5', 5 === has_filter( 'alg_wc_pxf_item', array( 'Alg_WC_PXF_Pro_EU', 'filter_item' ) ) );
alg_pxf_t( 'EU hooked on alg_wc_pxf_model', 10 === has_filter( 'alg_wc_pxf_model', array( 'Alg_WC_PXF_Pro_EU', 'filter_model' ) ) );
alg_pxf_t( 'EU hooked on woocommerce_update_product', false !== has_action( 'woocommerce_update_product', array( 'Alg_WC_PXF_Pro_EU', 'on_save' ) ) );

$google = array( 'id' => 'google' );
$idealo = array( 'id' => 'idealo' );

// ---------------------------------------------------------------------------------------------------------------
// Rules on crafted items.
// ---------------------------------------------------------------------------------------------------------------

$item  = array(
	'id'                    => '101',
	'title'                 => 'Blue Mug',
	'brand'                 => 'Northwind',
	'price'                 => '12.00 EUR',
	'sale_price'            => '',
	'availability'          => 'out_of_stock',
	'shipping'              => array( 'country' => 'DE', 'price' => '4.95 EUR' ),
	'additional_image_link' => array( 'https://example.test/a.jpg' ),
);
$model = array( 'stock_quantity' => 2, 'brand' => 'Northwind', 'gtin' => '' );
$r     = 'Alg_WC_PXF_Pro_Rules';

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'set', 'field' => 'custom_label_0', 'value' => 'summer' ) ) ) ) );
alg_pxf_t( 'rules: set (no condition)', isset( $out['custom_label_0'] ) && 'summer' === $out['custom_label_0'], $out );

$cond = array(
	array(
		'if'   => array( array( 'field' => 'availability', 'op' => 'equals', 'value' => 'out_of_stock' ) ),
		'then' => array( array( 'action' => 'set', 'field' => 'shipping_label', 'value' => 'backorder' ) ),
		'else' => array( array( 'action' => 'set', 'field' => 'shipping_label', 'value' => 'regular' ) ),
	),
);
$out = $r::apply( $item, $cond );
alg_pxf_t( 'rules: conditional set (match)', 'backorder' === $out['shipping_label'], $out );
$out = $r::apply( array_merge( $item, array( 'availability' => 'in_stock' ) ), $cond );
alg_pxf_t( 'rules: conditional set (else branch)', 'regular' === $out['shipping_label'], $out );

$out = $r::apply(
	$item,
	array(
		array(
			'if'   => array( array( 'field' => 'title', 'op' => 'contains', 'value' => 'mug' ) ),
			'then' => array( array( 'action' => 'exclude' ), array( 'action' => 'set', 'field' => 'title', 'value' => 'not reached' ) ),
		),
	)
);
alg_pxf_t( 'rules: exclude sets _exclude and stops', ! empty( $out['_exclude'] ) && 'Blue Mug' === $out['title'], $out );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'multiply', 'field' => 'price', 'value' => '1.2' ) ) ) ) );
alg_pxf_t( 'rules: multiply price keeps currency (12.00 EUR x 1.2)', '14.40 EUR' === $out['price'], $out['price'] );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'add', 'field' => 'price', 'value' => '10%' ) ) ) ) );
alg_pxf_t( 'rules: add 10%', '13.20 EUR' === $out['price'], $out['price'] );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'add', 'field' => 'price', 'value' => '-2' ) ) ) ) );
alg_pxf_t( 'rules: add -2', '10.00 EUR' === $out['price'], $out['price'] );

$out = $r::apply( array( 'price' => '13.20 EUR' ), array( array( 'then' => array( array( 'action' => 'round', 'field' => 'price', 'value' => '.99' ) ) ) ) );
alg_pxf_t( 'rules: round up to .99', '13.99 EUR' === $out['price'], $out['price'] );

$out = $r::apply( array( 'price' => '13.47 EUR' ), array( array( 'then' => array( array( 'action' => 'round', 'field' => 'price', 'value' => '0' ) ) ) ) );
alg_pxf_t( 'rules: round to 0 decimals keeps 2 for a currency', '13.00 EUR' === $out['price'], $out['price'] );

$out = $r::apply( array( 'weight' => '1.234 kg' ), array( array( 'then' => array( array( 'action' => 'round', 'field' => 'weight', 'value' => '1' ) ) ) ) );
alg_pxf_t( 'rules: round weight to 1 decimal', '1.2 kg' === $out['weight'], $out['weight'] );

$expected = new DateTime( 'now', wp_timezone() );
$expected->modify( '+5 days' );
$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'set', 'field' => 'availability_date', 'value' => '{today+5d}' ) ) ) ) );
alg_pxf_t( 'rules: date token {today+5d}', $expected->format( 'Y-m-d' ) === $out['availability_date'], $out['availability_date'] );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'set', 'field' => 'expiration_date', 'value' => '{now+2h}' ) ) ) ) );
alg_pxf_t( 'rules: date token {now+2h} is ISO 8601', (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}[+-]\d{4}$/', $out['expiration_date'] ), $out['expiration_date'] );

$out = $r::apply( array( 'availability_date' => '2026-12-30' ), array( array( 'then' => array( array( 'action' => 'add', 'field' => 'availability_date', 'value' => '3d' ) ) ) ) );
alg_pxf_t( 'rules: add 3d to a date', '2027-01-02' === $out['availability_date'], $out['availability_date'] );

$out = $r::apply(
	$item,
	array(
		array(
			'if'   => array( array( 'field' => 'price', 'op' => 'gt', 'value' => '10' ) ),
			'then' => array( array( 'action' => 'set', 'field' => 'custom_label_1', 'value' => 'over 10' ) ),
		),
		array(
			'if'   => array( array( 'field' => 'price', 'op' => 'lt', 'value' => '10' ) ),
			'then' => array( array( 'action' => 'set', 'field' => 'custom_label_2', 'value' => 'under 10' ) ),
		),
	)
);
alg_pxf_t( 'rules: gt and lt on a price with currency', 'over 10' === $out['custom_label_1'] && ! isset( $out['custom_label_2'] ), $out );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'set', 'field' => 'title', 'value' => '{brand} {title}' ) ) ) ) );
alg_pxf_t( 'rules: combine fields with tokens', 'Northwind Blue Mug' === $out['title'], $out['title'] );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'replace', 'field' => 'title', 'find' => 'Blue', 'value' => 'Red' ) ) ) ) );
alg_pxf_t( 'rules: replace', 'Red Mug' === $out['title'], $out['title'] );

$out = $r::apply(
	$item,
	array(
		array(
			'then' => array(
				array( 'action' => 'append', 'field' => 'title', 'value' => ' - 330 ml' ),
				array( 'action' => 'prepend', 'field' => 'title', 'value' => 'New: ' ),
				array( 'action' => 'append', 'field' => 'additional_image_link', 'value' => 'https://example.test/b.jpg' ),
			),
		),
	)
);
alg_pxf_t( 'rules: append and prepend text', 'New: Blue Mug - 330 ml' === $out['title'], $out['title'] );
alg_pxf_t( 'rules: append to a list adds an entry', 2 === count( $out['additional_image_link'] ), $out['additional_image_link'] );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'set', 'field' => 'shipping.price', 'value' => '0.00 EUR' ) ) ) ) );
alg_pxf_t( 'rules: dot path into a sub-attribute', '0.00 EUR' === $out['shipping']['price'] && 'DE' === $out['shipping']['country'], $out['shipping'] );

$out = $r::apply(
	$item,
	array(
		array(
			'match' => 'any',
			'if'    => array(
				array( 'field' => 'brand', 'op' => 'equals', 'value' => 'Other' ),
				array( 'field' => 'brand', 'op' => 'equals', 'value' => 'northwind' ),
			),
			'then'  => array( array( 'action' => 'set', 'field' => 'custom_label_3', 'value' => 'house brand' ) ),
		),
	)
);
alg_pxf_t( 'rules: match any (case-insensitive equals)', isset( $out['custom_label_3'] ), $out );

$out = $r::apply(
	$item,
	array(
		array(
			'if'   => array( array( 'field' => 'model.stock_quantity', 'op' => 'lt', 'value' => '3' ), array( 'field' => 'model.gtin', 'op' => 'empty' ) ),
			'then' => array( array( 'action' => 'set', 'field' => 'custom_label_4', 'value' => 'low stock' ) ),
			'stop' => true,
		),
		array( 'then' => array( array( 'action' => 'clear', 'field' => 'brand' ) ) ),
	),
	$model
);
alg_pxf_t( 'rules: model.* conditions and stop', 'low stock' === $out['custom_label_4'] && 'Northwind' === $out['brand'], $out );

$out = $r::apply( $item, array( array( 'then' => array( array( 'action' => 'clear', 'field' => 'brand' ) ) ) ) );
alg_pxf_t( 'rules: clear', '' === $out['brand'], $out );

$rules = array( array( 'then' => array( array( 'action' => 'set', 'field' => 'custom_label_0', 'value' => 'via filter' ) ) ) );
$out   = apply_filters( 'alg_wc_pxf_item', $item, $model, $google, array( 'rules' => $rules ) );
alg_pxf_t( 'rules: run through the alg_wc_pxf_item filter', isset( $out['custom_label_0'] ) && 'via filter' === $out['custom_label_0'], $out );

add_filter( 'alg_wc_pxf_is_pro', '__return_false', 99 );
$out = apply_filters( 'alg_wc_pxf_item', $item, $model, $google, array( 'rules' => $rules ) );
remove_filter( 'alg_wc_pxf_is_pro', '__return_false', 99 );
alg_pxf_t( 'rules: do nothing when not Pro', ! isset( $out['custom_label_0'] ), $out );

$clean = $r::sanitize(
	array(
		array(
			'if'   => array( array( 'field' => 'title<script>', 'op' => 'bad_op', 'value' => 'x' ), array( 'field' => 'title', 'op' => 'contains', 'value' => '<b>x</b>' ) ),
			'then' => array( array( 'action' => 'drop_table', 'field' => 'x' ), array( 'action' => 'exclude' ) ),
		),
	)
);
alg_pxf_t( 'rules: sanitize drops unknown ops and actions', 1 === count( $clean[0]['if'] ) && 1 === count( $clean[0]['then'] ) && 'x' === $clean[0]['if'][0]['value'], $clean );

// ---------------------------------------------------------------------------------------------------------------
// Omnibus: price history and the 30-day lowest price.
// ---------------------------------------------------------------------------------------------------------------

$eu_class = 'Alg_WC_PXF_Pro_EU';
$p        = new WC_Product_Simple();
$p->set_name( 'PXF Pro test mug' );
$p->set_status( 'draft' );
$p->set_regular_price( '20' );
$p->set_sale_price( '15' );
$p->save();
$pid = $p->get_id();
$p   = wc_get_product( $pid );

if ( function_exists( 'alg_wc_omnibus_get_reference_price' ) ) {
	echo "NOTE WPFactory Omnibus plugin is active: its reference price is read first.\n";
}
add_filter( 'alg_wc_pxf_use_omnibus_plugin', '__return_false' ); // Test our own history.

$omni  = array( 'eu' => array( 'omnibus' => true ) );
$gitem = array( 'id' => (string) $pid, 'price' => '20.00 EUR', 'sale_price' => '15.00 EUR', 'sale_price_effective_date' => '' );
$iitem = array( 'sku' => (string) $pid, 'price' => '15.00' );
$to_display = function ( $price ) use ( $p ) {
	return number_format( (float) wc_get_price_to_display( $p, array( 'price' => $price ) ), 2, '.', '' );
};

// A: 20 before the sale, sale at 15 for the last 3 days.
delete_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META );
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -12, -4, 20 ), array( -3, -1, 15 ) ) ) );
alg_pxf_t( 'omnibus: lowest before the sale run (20)', 20.0 === $eu_class::lowest_price( $p ), $eu_class::lowest_price( $p ) );
$m = apply_filters( 'alg_wc_pxf_model', array( 'id' => (string) $pid, 'price' => '20.00', 'sale_price' => '15.00', 'lowest_price_30d' => '', 'unit_pricing_measure' => '' ), $p, $omni );
alg_pxf_t( 'omnibus: model lowest_price_30d filled', $to_display( 20 ) === $m['lowest_price_30d'], $m['lowest_price_30d'] );
$h = $eu_class::get_history( $pid );
alg_pxf_t( 'omnibus: first use records today', isset( $h[ alg_pxf_day( 0 ) ] ) && 15.0 === (float) $h[ alg_pxf_day( 0 ) ], $h );
alg_pxf_t( 'omnibus: history switched on for product saves', 'yes' === get_option( Alg_WC_PXF_Pro_EU::HISTORY_OPTION ) );
$out = apply_filters( 'alg_wc_pxf_item', $gitem, $m, $google, $omni );
alg_pxf_t( 'omnibus: real price cut keeps the Google sale', '15.00 EUR' === $out['sale_price'] && '20.00 EUR' === $out['price'], $out );
$out = apply_filters( 'alg_wc_pxf_item', $iitem, array_merge( $m, array( 'lowest_price_30d' => '20.00' ) ), $idealo, $omni );
alg_pxf_t( 'omnibus: idealo formerPrice = 30-day lowest', isset( $out['formerPrice'] ) && '20.00' === $out['formerPrice'], $out );

// B: cheaper (14) earlier in the window, so 15 is not a real cut.
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -12, -8, 14 ), array( -7, -4, 20 ), array( -3, -1, 15 ) ) ) );
alg_pxf_t( 'omnibus: lowest includes the earlier 14', 14.0 === $eu_class::lowest_price( $p ), $eu_class::lowest_price( $p ) );
$m   = apply_filters( 'alg_wc_pxf_model', array( 'sale_price' => '15.00' ), $p, $omni );
$out = apply_filters( 'alg_wc_pxf_item', $gitem, $m, $google, $omni );
alg_pxf_t( 'omnibus: sale not lower than lowest is sent as the plain price', '15.00 EUR' === $out['price'] && '' === $out['sale_price'], $out );
$out = apply_filters( 'alg_wc_pxf_item', array_merge( $iitem, array( 'formerPrice' => '20.00' ) ), $m, $idealo, $omni );
alg_pxf_t( 'omnibus: idealo formerPrice cleared when not a real cut', '' === $out['formerPrice'], $out );
$keep = array( 'eu' => array( 'omnibus' => true, 'omnibus_drop_sale' => false ) );
$out  = apply_filters( 'alg_wc_pxf_item', $gitem, $m, $google, $keep );
alg_pxf_t( 'omnibus: drop-sale switch off keeps the sale', '15.00 EUR' === $out['sale_price'], $out );

// C: lowest 18, "was" price option on.
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -12, -8, 18 ), array( -7, -4, 20 ), array( -3, -1, 15 ) ) ) );
$was = array( 'eu' => array( 'omnibus' => true, 'omnibus_was_price' => true ) );
$m   = apply_filters( 'alg_wc_pxf_model', array( 'sale_price' => '15.00' ), $p, $was );
$out = apply_filters( 'alg_wc_pxf_item', $gitem, $m, $google, $was );
alg_pxf_t( 'omnibus: was-price option sends the 30-day lowest as price', $to_display( 18 ) . ' EUR' === $out['price'] && '15.00 EUR' === $out['sale_price'], $out );

// D: unknown (no history before the sale): feed stays as mapped.
delete_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META );
$m   = apply_filters( 'alg_wc_pxf_model', array( 'sale_price' => '15.00' ), $p, $omni );
$out = apply_filters( 'alg_wc_pxf_item', $gitem, $m, $google, $omni );
alg_pxf_t( 'omnibus: unknown lowest leaves the item as mapped', '' === $m['lowest_price_30d'] && '15.00 EUR' === $out['sale_price'], array( $m['lowest_price_30d'], $out ) );

// E: scheduled sale start is used as the start of the cut.
$p->set_date_on_sale_from( strtotime( alg_pxf_day( -2 ) . ' 00:00:00' ) );
$p->save();
$p = wc_get_product( $pid );
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -12, -3, 20 ), array( -2, -1, 15 ) ) ) );
alg_pxf_t( 'omnibus: scheduled sale start', 20.0 === $eu_class::lowest_price( $p ), $eu_class::lowest_price( $p ) );
$p->set_date_on_sale_from( null );

// F: not on sale: lowest of the last 30 days, today included.
$p->set_sale_price( '' );
$p->save();
$p = wc_get_product( $pid );
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -40, -40, 9 ), array( -31, -31, 25 ), array( -20, -10, 18 ), array( -9, -1, 20 ) ) ) );
alg_pxf_t( 'omnibus: not on sale, lowest of 30 days (ignores a price changed before the window)', 18.0 === $eu_class::lowest_price( $p ), $eu_class::lowest_price( $p ) );
update_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META, alg_pxf_history( array( array( -40, -40, 9 ), array( -20, -10, 18 ), array( -9, -1, 20 ) ) ) );
alg_pxf_t( 'omnibus: a price still in effect when the window starts counts', 9.0 === $eu_class::lowest_price( $p ), $eu_class::lowest_price( $p ) );

// G: recording on product save, lowest of the day, pruning.
delete_post_meta( $pid, Alg_WC_PXF_Pro_EU::HISTORY_META );
$p->set_sale_price( '14' );
$p->save();
$h = $eu_class::get_history( $pid );
alg_pxf_t( 'omnibus: product save records the price', isset( $h[ alg_pxf_day( 0 ) ] ) && 14.0 === (float) $h[ alg_pxf_day( 0 ) ], $h );
$p->set_sale_price( '16' );
$p->save();
$h = $eu_class::get_history( $pid );
alg_pxf_t( 'omnibus: a day keeps its lowest price', 14.0 === (float) $h[ alg_pxf_day( 0 ) ], $h );
$pruned = $eu_class::prune( alg_pxf_history( array( array( -40, -40, 9 ), array( -35, -35, 11 ), array( -20, -20, 12 ) ) ), alg_pxf_day( 0 ) );
alg_pxf_t( 'omnibus: prune keeps 31 days plus the price in effect before', array( alg_pxf_day( -35 ), alg_pxf_day( -20 ) ) === array_keys( $pruned ), $pruned );
remove_filter( 'alg_wc_pxf_use_omnibus_plugin', '__return_false' );

// ---------------------------------------------------------------------------------------------------------------
// Unit pricing.
// ---------------------------------------------------------------------------------------------------------------

$cases = array(
	array( '500 ml', 'small', '500 ml', '100 ml' ),
	array( '1,5 L', 'small', '1.5 l', '1 l' ),
	array( '250g', 'small', '250 g', '100 g' ),
	array( '2 kg bag', 'small', '2 kg', '1 kg' ),
	array( '12 fl oz', 'small', '12 floz', '1 floz' ),
	array( '500 ml', 'large', '500 ml', '1 l' ),
);
foreach ( $cases as $case ) {
	$parsed = $eu_class::parse_measure( $case[0] );
	$got    = ( $parsed ? array( $eu_class::format_measure( $parsed['value'], $parsed['unit'] ), $eu_class::default_base( $parsed['unit'], $case[1] ) ) : null );
	alg_pxf_t( 'unit pricing: "' . $case[0] . '" (' . $case[1] . ')', array( $case[2], $case[3] ) === $got, $got );
}
alg_pxf_t( 'unit pricing: "Large" is not a measure', null === $eu_class::parse_measure( 'Large' ) );

$u = new WC_Product_Simple();
$u->set_name( 'PXF Pro test olive oil' );
$u->set_status( 'draft' );
$u->set_regular_price( '9.90' );
$attribute = new WC_Product_Attribute();
$attribute->set_name( 'Content' );
$attribute->set_options( array( '500 ml' ) );
$attribute->set_visible( true );
$attribute2 = new WC_Product_Attribute();
$attribute2->set_name( 'EPREL' );
$attribute2->set_options( array( 'https://eprel.ec.europa.eu/screen/product/dishwashers2019/777888' ) );
$u->set_attributes( array( $attribute, $attribute2 ) );
$u->save();
$uid = $u->get_id();
$u   = wc_get_product( $uid );

$unit_cfg = array( 'eu' => array( 'unit_pricing' => true, 'energy_label' => true ) );
$m        = apply_filters( 'alg_wc_pxf_model', array( 'id' => (string) $uid, 'lowest_price_30d' => '', 'unit_pricing_measure' => '' ), $u, $unit_cfg );
alg_pxf_t( 'unit pricing: model from the Content attribute', '500 ml' === $m['unit_pricing_measure'] && '100 ml' === $m['unit_pricing_base_measure'], $m );
$out = apply_filters( 'alg_wc_pxf_item', array( 'id' => (string) $uid, 'price' => '9.90 EUR' ), $m, $google, $unit_cfg );
alg_pxf_t( 'unit pricing: Google item fields', '500 ml' === $out['unit_pricing_measure'] && '100 ml' === $out['unit_pricing_base_measure'], $out );
$out = apply_filters( 'alg_wc_pxf_item', array( 'id' => (string) $uid ), $m, array( 'id' => 'meta' ), $unit_cfg );
alg_pxf_t( 'unit pricing: not added to a channel without the field', ! isset( $out['unit_pricing_measure'] ), $out );
$off = apply_filters( 'alg_wc_pxf_model', array( 'unit_pricing_measure' => '' ), $u, array( 'eu' => array() ) );
alg_pxf_t( 'unit pricing: off by default', '' === $off['unit_pricing_measure'], $off );

update_post_meta( $uid, '_unit_product', '750' );
update_post_meta( $uid, '_unit', 'ml' );
update_post_meta( $uid, '_unit_base', '1000' );
$m2 = apply_filters( 'alg_wc_pxf_model', array( 'unit_pricing_measure' => '' ), $u, $unit_cfg );
alg_pxf_t( 'unit pricing: Germanized meta first', '750 ml' === $m2['unit_pricing_measure'] && '1000 ml' === $m2['unit_pricing_base_measure'], $m2 );

// ---------------------------------------------------------------------------------------------------------------
// Energy label (EPREL).
// ---------------------------------------------------------------------------------------------------------------

$eprel = array(
	'123456'                                                              => '123456',
	'EC:EPREL:654321'                                                     => '654321',
	'https://eprel.ec.europa.eu/screen/product/dishwashers2019/987654'    => '987654',
	'https://eprel.ec.europa.eu/qr/555123?utm=2'                          => '555123',
	'none'                                                                => '',
);
foreach ( $eprel as $in => $want ) {
	alg_pxf_t( 'eprel: parse "' . $in . '"', $want === $eu_class::parse_eprel( $in ), $eu_class::parse_eprel( $in ) );
}
alg_pxf_t( 'eprel: model from the EPREL attribute link', '777888' === $m['eprel'], $m['eprel'] );
update_post_meta( $uid, 'eprel', '123456' );
$m3  = apply_filters( 'alg_wc_pxf_model', array(), $u, $unit_cfg );
alg_pxf_t( 'eprel: meta first', '123456' === $m3['eprel'], $m3['eprel'] );
$out = apply_filters( 'alg_wc_pxf_item', array( 'id' => (string) $uid ), $m3, $google, $unit_cfg );
$want_cert = array( 'certification_authority' => 'EC', 'certification_name' => 'EPREL', 'certification_code' => '123456' );
alg_pxf_t( 'eprel: Google certification EC:EPREL:123456', isset( $out['certification'] ) && $want_cert === $out['certification'] && 'EC:EPREL:123456' === Alg_WC_PXF_Pro_Rules::to_text( $out['certification'] ), $out );
$out = apply_filters( 'alg_wc_pxf_item', array( 'id' => (string) $uid ), $m3, $idealo, $unit_cfg );
alg_pxf_t( 'eprel: not added to idealo', ! isset( $out['certification'] ), $out );

// ---------------------------------------------------------------------------------------------------------------
// Pipeline: model -> item with rules and the EU pack (real classes, or the test stubs).
// ---------------------------------------------------------------------------------------------------------------

if ( class_exists( 'Alg_WC_PXF_Product_Data' ) && class_exists( 'Alg_WC_PXF_Mapper' ) ) {
	$channel = ( class_exists( 'Alg_WC_PXF_Channels' ) && Alg_WC_PXF_Channels::get( 'google' ) ? Alg_WC_PXF_Channels::get( 'google' ) : $google );
	$config  = array(
		'channel' => 'google',
		'eu'      => array( 'unit_pricing' => true, 'energy_label' => true ),
		'rules'   => array(
			array(
				'if'   => array( array( 'field' => 'unit_pricing_measure', 'op' => 'not_empty' ) ),
				'then' => array( array( 'action' => 'set', 'field' => 'custom_label_0', 'value' => 'unit {unit_pricing_measure}' ) ),
			),
		),
	);
	$model = Alg_WC_PXF_Product_Data::model( $u, $config );
	$item  = Alg_WC_PXF_Mapper::item( $model, $channel, $config );
	alg_pxf_t( 'pipeline: EU fields and a rule on the mapped item', '750 ml' === $item['unit_pricing_measure'] && isset( $item['certification'] ) && 'unit 750 ml' === $item['custom_label_0'], $item );
} else {
	echo "SKIP pipeline: no model and mapper classes (load tests/v2/pro-stubs.php)\n";
}

// ---------------------------------------------------------------------------------------------------------------
// Clean-up.
// ---------------------------------------------------------------------------------------------------------------

wp_delete_post( $pid, true );
wp_delete_post( $uid, true );

$t = $GLOBALS['alg_pxf_t'];
echo "\n" . $t['pass'] . ' passed, ' . $t['fail'] . " failed\n";
