<?php
/**
 * Validator and AI tests. Run on a QA site with the stubs mu-plugin:
 *   wp eval-file wp-content/plugins/product-xml-feeds-for-woocommerce-pro/tests/v2/validator-ai-test.php
 * Every AI call goes through the `alg_wc_pxf_ai_complete` stub filter: nothing is sent anywhere, nothing is paid.
 *
 * @package Alg_WC_PXF_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'alg_wc_pxf_test_model' ) ) {
	require_once __DIR__ . '/validator-ai-stubs.php';
}

$GLOBALS['pxf_pass'] = 0;
$GLOBALS['pxf_fail'] = 0;

/**
 * Prints PASS or FAIL.
 *
 * @param string $name Test.
 * @param bool   $ok   Result.
 * @param mixed  $info Shown on failure.
 */
function pxf_t( $name, $ok, $info = '' ) {
	if ( $ok ) {
		++$GLOBALS['pxf_pass'];
		echo 'PASS ' . $name . "\n";
	} else {
		++$GLOBALS['pxf_fail'];
		echo 'FAIL ' . $name . ( '' !== $info ? ' :: ' . ( is_scalar( $info ) ? $info : wp_json_encode( $info ) ) : '' ) . "\n";
	}
}

/**
 * Codes of issues.
 *
 * @param array $issues Issues.
 * @return array
 */
function pxf_codes( array $issues ) {
	return array_column( $issues, 'code' );
}

// Never reach the real AI server from this test.
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		return false !== strpos( (string) $url, 'ai.wpfactory.com' ) ? new WP_Error( 'blocked', 'Blocked in tests' ) : $pre;
	},
	1,
	3
);

$v       = 'Alg_WC_PXF_Validator';
$channel = Alg_WC_PXF_Channels::get( 'google' );

/* ------------------------------------------------------------------ validator */

pxf_t( 'validator class loaded', class_exists( $v ) );

$m      = alg_wc_pxf_test_model();
// A clothing product: Google also needs size, gender and age group (real channel spec).
$issues = $v::check( alg_wc_pxf_test_item( $m, array( 'size' => 'M', 'gender' => 'unisex', 'age_group' => 'adult' ) ), $m, $channel );
pxf_t( 'clean item has no errors', ! array_filter( $issues, fn( $i ) => 'error' === $i['severity'] ), $issues );

$m      = alg_wc_pxf_test_model( array( 'brand' => '', 'gtin' => '' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
$codes  = pxf_codes( $issues );
pxf_t( 'missing brand is an error', in_array( 'missing_brand', $codes, true ), $codes );
pxf_t( 'missing gtin is a warning', in_array( 'missing_gtin', $codes, true ) && 'warning' === $issues[ array_search( 'missing_gtin', $codes, true ) ]['severity'], $codes );
pxf_t( 'mpn needed when no gtin (conditional)', in_array( 'missing_mpn', $codes, true ), $codes );
pxf_t( 'missing identifiers warning', in_array( 'missing_identifiers', $codes, true ), $codes );
$brand = $issues[ array_search( 'missing_brand', $codes, true ) ];
pxf_t( 'missing brand fix hint is ai_attributes', 'ai_attributes' === $brand['fix'] && 'brand' === $brand['field'], $brand );
pxf_t( 'message is plain words', false === strpos( $brand['message'], '_' ) && '' !== $brand['message'], $brand['message'] );

$m      = alg_wc_pxf_test_model( array( 'gtin' => '4006381333932' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'bad GTIN check digit', in_array( 'gtin_check_digit', pxf_codes( $issues ), true ), pxf_codes( $issues ) );
pxf_t( 'good GTIN 4006381333931 passes', '' === $v::gtin_problem( '4006381333931' ) );
pxf_t( 'good UPC 036000291452 passes', '' === $v::gtin_problem( '036000291452' ) );
pxf_t( 'GTIN with 10 digits is format', 'format' === $v::gtin_problem( '0306406152' ) );

$m      = alg_wc_pxf_test_model( array( 'gtin' => '2000000000008' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'banned prefix 2 (EAN-13)', in_array( 'gtin_banned_prefix', pxf_codes( $issues ), true ), pxf_codes( $issues ) );
pxf_t( 'banned prefix 04 (UPC 4...)', 'prefix' === $v::gtin_problem( '400000000008' ) );
pxf_t( 'banned prefix 98 (coupon)', 'prefix' === $v::gtin_problem( '9800000000007' ) );
pxf_t( 'GTIN issue fix hint is product', 'product' === $v::fix_for( 'gtin', 'gtin_banned_prefix' ) );

$long   = str_repeat( 'Merino ', 25 ) . 'Beanie';
$m      = alg_wc_pxf_test_model( array( 'title' => $long ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
$codes  = pxf_codes( $issues );
pxf_t( 'title over 150 characters', in_array( 'too_long_title', $codes, true ), $codes );
pxf_t( 'too long title fix is ai_title', 'ai_title' === $issues[ array_search( 'too_long_title', $codes, true ) ]['fix'] );

$m      = alg_wc_pxf_test_model( array( 'title' => 'Merino Wool Beanie SALE - Free Shipping, 20% off' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'promo words in title', in_array( 'title_promo', pxf_codes( $issues ), true ), pxf_codes( $issues ) );
pxf_t( 'promo words found', count( $v::promo_words( 'Merino Wool Beanie SALE - Free Shipping, 20% off' ) ) >= 3, $v::promo_words( 'Merino Wool Beanie SALE - Free Shipping, 20% off' ) );
pxf_t( '"Wholesale" and "100% cotton" are not promo', ! $v::promo_words( 'Wholesale Pack of 100% Cotton Socks' ), $v::promo_words( 'Wholesale Pack of 100% Cotton Socks' ) );

$m      = alg_wc_pxf_test_model( array( 'title' => 'NORTHWIND MERINO WOOL BEANIE' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'all caps title', in_array( 'title_all_caps', pxf_codes( $issues ), true ), pxf_codes( $issues ) );

$m      = alg_wc_pxf_test_model( array( 'price' => '20.00', 'sale_price' => '25.00' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'sale price above price', in_array( 'sale_not_lower', pxf_codes( $issues ), true ), pxf_codes( $issues ) );
$m      = alg_wc_pxf_test_model( array( 'price' => '20.00', 'sale_price' => '20.00' ) );
pxf_t( 'sale price equal to price', in_array( 'sale_not_lower', pxf_codes( $v::check( alg_wc_pxf_test_item( $m ), $m, $channel ) ), true ) );
$m      = alg_wc_pxf_test_model( array( 'price' => '0' ) );
pxf_t( 'price 0', in_array( 'price_zero', pxf_codes( $v::check( alg_wc_pxf_test_item( $m ), $m, $channel ) ), true ) );

$ph     = wc_placeholder_img_src( 'woocommerce_single' );
$m      = alg_wc_pxf_test_model( array( 'image_link' => $ph ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'placeholder image', in_array( 'placeholder_image', pxf_codes( $issues ), true ), array( $ph, pxf_codes( $issues ) ) );
$m      = alg_wc_pxf_test_model( array( 'image_link' => '' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'no image is one error', 1 === count( array_intersect( pxf_codes( $issues ), array( 'missing_image_link', 'missing_image' ) ) ), pxf_codes( $issues ) );

$m      = alg_wc_pxf_test_model();
$issues = $v::check( alg_wc_pxf_test_item( $m, array( 'availability' => 'available' ) ), $m, $channel );
pxf_t( 'value not allowed', in_array( 'invalid_availability', pxf_codes( $issues ), true ), pxf_codes( $issues ) );
$issues = $v::check( alg_wc_pxf_test_item( $m, array( 'title' => null, 'structured_title' => array( 'digital_source_type' => 'trained_algorithmic_media', 'content' => 'Northwind Merino Beanie' ) ) ), $m, $channel );
pxf_t( 'structured_title counts as title', ! in_array( 'missing_title', pxf_codes( $issues ), true ), pxf_codes( $issues ) );

$m      = alg_wc_pxf_test_model( array( 'gtin' => '', 'brand' => '', 'identifier_exists' => 'no' ) );
$issues = $v::check( alg_wc_pxf_test_item( $m ), $m, $channel );
pxf_t( 'identifier_exists=no silences missing identifiers', ! in_array( 'missing_identifiers', pxf_codes( $issues ), true ), pxf_codes( $issues ) );

$sum = $v::summary( array( 101 => array( array( 'code' => 'missing_brand', 'severity' => 'error', 'field' => 'brand', 'fix' => 'ai_attributes' ) ), 102 => array( array( 'code' => 'missing_brand', 'severity' => 'error', 'field' => 'brand', 'fix' => 'ai_attributes' ), array( 'code' => 'title_promo', 'severity' => 'warning', 'field' => 'title', 'fix' => 'ai_title' ) ), 103 => array() ) );
pxf_t( 'summary counts per product', 2 === $sum['errors'] && 1 === $sum['warnings'] && 2 === $sum['products'], $sum );
pxf_t( 'summary top reason', 'missing_brand' === $sum['top'][0]['code'] && false !== strpos( $sum['top'][0]['reason'], '2 products have no Brand' ), $sum['top'][0] );
$sum = $v::summary( array( 'gtin_check_digit' => 12, 'title_all_caps' => 3 ) );
pxf_t( 'summary from counts by code', 12 === $sum['errors'] && 3 === $sum['warnings'] && null === $sum['products'] && 'product' === $sum['by_code']['gtin_check_digit']['fix'], $sum );

/* ------------------------------------------------------------------ AI */

wp_set_current_user( 1 );
pxf_t( 'AI class loaded', class_exists( 'Alg_WC_PXF_AI' ) );
pxf_t( 'library bundled and loaded', function_exists( 'wpfactory_ai' ) && '1.7.0' === WPFactory_AI_Versions::loaded(), function_exists( 'wpfactory_ai' ) ? WPFactory_AI_Versions::loaded() : 'none' );
pxf_t( 'product-feeds registered with the library', array_key_exists( 'product-feeds', (array) wpfactory_ai()->products() ), array_keys( (array) wpfactory_ai()->products() ) );
pxf_t( 'shortcodes registered', shortcode_exists( 'alg_product_ai_title' ) && shortcode_exists( 'alg_product_ai_description' ) );

// Estimate math.
$e = Alg_WC_PXF_AI::estimate( 'titles', 1180 );
pxf_t( 'estimate 1,180 titles = 118 credits', 118 === $e['credits'] && 118 === $e['batches'], $e );
pxf_t( 'estimate message plain', false !== strpos( $e['message'], 'Improve 1,180 feed titles: about 118 credits.' ), $e['message'] );
$e = Alg_WC_PXF_AI::estimate( 'descriptions', 7 );
pxf_t( 'estimate 7 descriptions = 2 credits', 2 === $e['credits'], $e );
$e = Alg_WC_PXF_AI::estimate( 'categories', 25 );
pxf_t( 'estimate 25 categories = 8 credits', 8 === $e['credits'], $e );
$e = Alg_WC_PXF_AI::estimate( 'attributes', 10, true );
pxf_t( 'estimate 10 attributes with photo = 3 credits', 3 === $e['credits'], $e );
pxf_t( 'estimate unknown job is an error', is_wp_error( Alg_WC_PXF_AI::estimate( 'poems', 3 ) ) );

// Test products (example.test data only), removed at the end.
$ids = array();
foreach ( array( 'PXF AI Test Beanie', 'PXF AI Test Scarf', 'PXF AI Test Gloves' ) as $name ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	$p->set_regular_price( '20' );
	$p->set_description( 'Hand knitted from merino wool in a charcoal color. Brand: Northwind. Warm and soft.' );
	$attr = new WC_Product_Attribute();
	$attr->set_name( 'Brand' );
	$attr->set_options( array( 'Northwind' ) );
	$attr->set_visible( true );
	$p->set_attributes( array( $attr ) );
	$ids[] = $p->save();
}
list( $a, $b, $c ) = $ids;

$GLOBALS['pxf_answers'] = array();
$GLOBALS['pxf_bodies']  = array();
add_filter(
	'alg_wc_pxf_ai_complete',
	function ( $pre, $body, $task ) {
		$GLOBALS['pxf_bodies'][] = array( 'body' => $body, 'task' => $task );
		$answer                  = $GLOBALS['pxf_answers'];
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		if ( isset( $answer['raw'] ) ) {
			return $answer['raw'];
		}
		return array(
			'id'          => 'msg_test',
			'type'        => 'message',
			'role'        => 'assistant',
			'model'       => $body['model'],
			'stop_reason' => 'tool_use',
			'content'     => array(
				array(
					'type'  => 'tool_use',
					'id'    => 'toolu_test',
					'name'  => $body['tool_choice']['name'],
					'input' => array( 'items' => $answer ),
				),
			),
		);
	},
	10,
	3
);

$GLOBALS['pxf_answers'] = array(
	array( 'id' => $a, 'title' => 'Northwind Merino Wool Beanie, Charcoal' ),
	array( 'id' => $b, 'title' => 'Northwind Scarf SALE!! Free Shipping' ),
	array( 'id' => $c, 'title' => 'Northwind Merino Gloves $19.99' ),
);
delete_option( Alg_WC_PXF_AI::OPTION_RUN );
$run = Alg_WC_PXF_AI::run( 'titles', $ids, array( 'sync' => true ) );
pxf_t( 'titles run finished', ! is_wp_error( $run ) && 'done' === $run['status'] && 3 === $run['done'], is_wp_error( $run ) ? $run->get_error_message() : $run );
pxf_t( 'one suggestion kept, two dropped by the checks', 1 === $run['suggested'] && isset( $run['dropped'][ $b ], $run['dropped'][ $c ] ), $run );
pxf_t( 'dropped reasons are plain', false !== stripos( $run['dropped'][ $b ], 'promotional' ) && false !== stripos( $run['dropped'][ $c ], 'price' ), $run['dropped'] );
$sent = end( $GLOBALS['pxf_bodies'] );
pxf_t( 'body: Haiku, forced tool, tight max_tokens', 'claude-haiku-5-5' === $sent['body']['model'] && 'tool' === $sent['body']['tool_choice']['type'] && 'save_titles' === $sent['body']['tool_choice']['name'] && 1000 === $sent['body']['max_tokens'] && 'feed-titles' === $sent['task'], $sent );
pxf_t( 'body: tool schema with items', 'array' === $sent['body']['tools'][0]['input_schema']['properties']['items']['type'] && in_array( 'title', $sent['body']['tools'][0]['input_schema']['properties']['items']['items']['required'], true ) );
pxf_t( 'body: product facts sent, no customer data', false !== strpos( $sent['body']['messages'][0]['content'], 'PXF AI Test Beanie' ) && false !== strpos( $sent['body']['messages'][0]['content'], 'Northwind' ) && false === strpos( $sent['body']['messages'][0]['content'], '@' ) );
pxf_t( 'status suggested after the run', 'suggested' === get_post_meta( $a, '_alg_pxf_ai_title_status', true ) && 'Northwind Merino Wool Beanie, Charcoal' === get_post_meta( $a, '_alg_pxf_ai_title', true ) );
pxf_t( 'dropped products get nothing saved', '' === get_post_meta( $b, '_alg_pxf_ai_title', true ) && '' === get_post_meta( $c, '_alg_pxf_ai_title', true ) );
pxf_t( 'product name untouched', 'PXF AI Test Beanie' === wc_get_product( $a )->get_name() );
pxf_t( 'shortcode empty before approval', '' === do_shortcode( '[alg_product_ai_title product_id="' . $a . '"]' ) );

$res = Alg_WC_PXF_AI::results( 'titles', 'suggested' );
pxf_t( 'results list before and after', 1 === count( array_filter( $res['items'], fn( $r ) => $r['id'] === $a && 'PXF AI Test Beanie' === $r['before'] && 'Northwind Merino Wool Beanie, Charcoal' === $r['after'] ) ), $res );

pxf_t( 'approve one', 1 === Alg_WC_PXF_AI::approve( 'titles', array( $a ) ) && 'approved' === get_post_meta( $a, '_alg_pxf_ai_title_status', true ) );
pxf_t( 'shortcode shows the approved title', 'Northwind Merino Wool Beanie, Charcoal' === do_shortcode( '[alg_product_ai_title product_id="' . $a . '"]' ), do_shortcode( '[alg_product_ai_title product_id="' . $a . '"]' ) );
pxf_t( 'shortcode cdata', '<![CDATA[Northwind Merino Wool Beanie, Charcoal]]>' === do_shortcode( '[alg_product_ai_title product_id="' . $a . '" cdata="yes"]' ) );
pxf_t( 'description shortcode empty without a value', '' === do_shortcode( '[alg_product_ai_description product_id="' . $a . '"]' ) );
pxf_t( 'approved() helper', 'Northwind Merino Wool Beanie, Charcoal' === Alg_WC_PXF_AI::approved( $a, 'title' ) );
pxf_t( 'approve with an edit', 1 === Alg_WC_PXF_AI::approve( 'titles', array( $b ), array( $b => 'Northwind Merino Scarf <b>Grey</b>' ) ) && 'Northwind Merino Scarf Grey' === Alg_WC_PXF_AI::approved( $b, 'title' ), Alg_WC_PXF_AI::approved( $b, 'title' ) );

pxf_t( 'undo removes the AI value', 2 === Alg_WC_PXF_AI::undo( 'titles', array( $a, $b ) ) && '' === get_post_meta( $a, '_alg_pxf_ai_title', true ) && '' === get_post_meta( $a, '_alg_pxf_ai_title_status', true ) );
pxf_t( 'shortcode empty after undo', '' === do_shortcode( '[alg_product_ai_title product_id="' . $a . '"]' ) );

// Language kept: a Cyrillic title for an English product is dropped.
$GLOBALS['pxf_answers'] = array( array( 'id' => $a, 'title' => 'Шапка из мериносовой шерсти Northwind' ) );
$run                    = Alg_WC_PXF_AI::run( 'titles', array( $a ), array( 'sync' => true ) );
pxf_t( 'language change dropped', 0 === $run['suggested'] && false !== stripos( (string) $run['dropped'][ $a ], 'language' ), $run );

// Descriptions.
$GLOBALS['pxf_answers'] = array( array( 'id' => $a, 'description' => 'A warm beanie hand knitted from merino wool in a charcoal color by Northwind. The soft yarn keeps the head warm on cold days and the fit stays snug without itching.' ) );
$run                    = Alg_WC_PXF_AI::run( 'descriptions', array( $a ), array( 'sync' => true ) );
pxf_t( 'description saved as suggestion', 1 === $run['suggested'] && 'suggested' === get_post_meta( $a, '_alg_pxf_ai_description_status', true ), $run );
Alg_WC_PXF_AI::approve( 'descriptions', array(), array(), true );
pxf_t( 'approve all, description shortcode', 0 === strpos( do_shortcode( '[alg_product_ai_description product_id="' . $a . '"]' ), 'A warm beanie' ) );
$GLOBALS['pxf_answers'] = array( array( 'id' => $b, 'description' => '<p>Short</p>' ) );
$run                    = Alg_WC_PXF_AI::run( 'descriptions', array( $b ), array( 'sync' => true ) );
pxf_t( 'HTML description dropped', 0 === $run['suggested'] );

// Attributes: invalid gender dropped, color kept, guessed brand dropped.
$GLOBALS['pxf_answers'] = array( array( 'id' => $c, 'color' => 'Charcoal', 'gender' => 'man', 'brand' => 'Acme' ) );
$run                    = Alg_WC_PXF_AI::run( 'attributes', array( $c ), array( 'sync' => true ) );
$attrs                  = json_decode( (string) get_post_meta( $c, '_alg_pxf_ai_attrs', true ), true );
pxf_t( 'attributes saved as JSON, bad values left out', array( 'color' => 'Charcoal' ) === $attrs && 'suggested' === get_post_meta( $c, '_alg_pxf_ai_attrs_status', true ), $attrs );

// Failures: plain message, failed ids kept for Retry, nothing saved.
$GLOBALS['pxf_answers'] = new WP_Error( 'wpfactory_ai_rate_limit_error', 'WPFactory AI is busy. Please try again in a minute. Nothing was charged.' );
$run                    = Alg_WC_PXF_AI::run( 'titles', array( $a, $b ), array( 'sync' => true ) );
pxf_t( 'failed batch keeps ids for retry with a plain error', in_array( $a, $run['failed'], true ) && false !== strpos( $run['error'], 'Nothing was charged' ), $run );
$GLOBALS['pxf_answers'] = array( 'raw' => array( 'content' => array( array( 'type' => 'text', 'text' => 'Sorry' ) ) ) );
$run                    = Alg_WC_PXF_AI::run( 'titles', array( $a ), array( 'sync' => true ) );
pxf_t( 'unreadable answer is a failed batch', in_array( $a, $run['failed'], true ) && false !== strpos( $run['error'], 'could not be read' ), $run );
$GLOBALS['pxf_answers'] = new WP_Error( 'alg_pxf_ai_low_credits', 'Not enough credits for the next 2 products (0 left): buy credits or run fewer.' );
$run                    = Alg_WC_PXF_AI::run( 'titles', $ids, array( 'sync' => true ) );
pxf_t( 'not enough credits stops the run', 'failed' === $run['status'] && 3 === count( $run['failed'] ), $run );

// A server error stops the run at the first batch, in plain words (seen on wpwhale: the server did not know the
// product yet, every batch failed and the run said "Finished: 0 suggestions").
$GLOBALS['pxf_answers'] = new WP_Error( 'alg_pxf_ai_not_ready', 'WPFactory AI does not run Product Feeds tasks yet. Nothing was charged. Please try again later.' );
$GLOBALS['pxf_bodies']  = array();
$many_ids               = array_merge( $ids, array_map( 'intval', get_posts( array( 'post_type' => 'product', 'posts_per_page' => 12, 'fields' => 'ids', 'post__not_in' => $ids ) ) ) );
$run                    = Alg_WC_PXF_AI::run( 'titles', $many_ids, array( 'sync' => true ) );
pxf_t( 'server error stops after one call', 'failed' === $run['status'] && 1 === count( $GLOBALS['pxf_bodies'] ) && count( $run['failed'] ) === count( $many_ids ), array( 'calls' => count( $GLOBALS['pxf_bodies'] ), 'run' => $run ) );
pxf_t( 'stopped run says why', 0 === strpos( $run['status_text'], 'Stopped: WPFactory AI does not run Product Feeds tasks yet' ), $run['status_text'] );
$GLOBALS['pxf_answers'] = array( 'raw' => array( 'content' => array( array( 'type' => 'text', 'text' => 'Sorry' ) ) ) );
$GLOBALS['pxf_bodies']  = array();
$run                    = Alg_WC_PXF_AI::run( 'titles', $many_ids, array( 'sync' => true ) );
pxf_t( 'unreadable answer does not stop the other batches', 'done' === $run['status'] && count( $GLOBALS['pxf_bodies'] ) > 1 && false !== strpos( $run['status_text'], 'could not be done' ), array( 'calls' => count( $GLOBALS['pxf_bodies'] ), 'text' => $run['status_text'] ) );

$pub = Alg_WC_PXF_AI::public_run( array( 'id' => 'x', 'job' => 'titles', 'total' => 2, 'done' => 2, 'queue' => array(), 'user' => 1, 'failed' => array( 1, 2 ), 'suggested' => 0, 'status' => 'done', 'error' => 'Send a known product id (X-WPFactory-Product), for example ai-assistant-mcp.' ) );
pxf_t( 'saved server message shown in plain words', false !== strpos( $pub['status_text'], 'does not run Product Feeds tasks yet' ) && false === strpos( $pub['status_text'], 'X-WPFactory' ), $pub['status_text'] );

// Background run through Action Scheduler.
$GLOBALS['pxf_answers'] = array( array( 'id' => $a, 'title' => 'Northwind Merino Beanie' ) );
delete_option( Alg_WC_PXF_AI::OPTION_RUN );
$many = array_merge( $ids, array_map( 'intval', get_posts( array( 'post_type' => 'product', 'posts_per_page' => 9, 'fields' => 'ids', 'post__not_in' => $ids ) ) ) );
$run  = Alg_WC_PXF_AI::run( 'titles', $many );
pxf_t( 'more than one batch is queued in the background', 'queued' === $run['status'] && function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( Alg_WC_PXF_AI::HOOK, array( $run['id'] ), 'alg-pxf' ), $run );
do_action( Alg_WC_PXF_AI::HOOK, $run['id'] );
$p = Alg_WC_PXF_AI::progress();
pxf_t( 'a batch runs from the hook and queues the next', 10 === $p['done'] && 'running' === $p['status'] && count( $p['queue'] ) === count( $many ) - 10, Alg_WC_PXF_AI::public_run( $p ) );
pxf_t( 'busy while a run is going', is_wp_error( Alg_WC_PXF_AI::run( 'titles', $ids ) ) );
$s = Alg_WC_PXF_AI::stop();
pxf_t( 'stop', 'stopped' === $s['status'] );
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( Alg_WC_PXF_AI::HOOK, array(), 'alg-pxf' );
}

// Categories with a local shortlist (a small taxonomy file stands in for the bundled one).
$tax = wp_upload_dir()['basedir'] . '/pxf-test-taxonomy.txt';
file_put_contents( $tax, "# Google_Product_Taxonomy_Version: test\n166 - Apparel & Accessories\n173 - Apparel & Accessories > Clothing Accessories > Hats\n167 - Apparel & Accessories > Clothing Accessories\n632 - Hardware\n" ); // phpcs:ignore
add_filter( 'alg_wc_pxf_ai_taxonomy_file', fn() => $tax );
$term = wp_insert_term( 'PXF Test Hats', 'product_cat' );
$tid  = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
$facts = Alg_WC_PXF_AI::category_facts( $tid );
pxf_t( 'category shortlist ranks Hats', ! empty( $facts['_ids'] ) && 173 === $facts['_ids'][0], $facts );
update_term_meta( $tid, '_alg_pxf_google_category', 632 );
$GLOBALS['pxf_answers'] = array( array( 'id' => $tid, 'google_category_id' => 173, 'confidence' => 0.9 ) );
$run                    = Alg_WC_PXF_AI::run( 'categories', array( $tid ), array( 'sync' => true ) );
$sent                   = end( $GLOBALS['pxf_bodies'] );
pxf_t( 'category suggestion saved, mapping untouched', 1 === $run['suggested'] && 173 === (int) get_term_meta( $tid, '_alg_pxf_ai_google_category', true ) && 632 === (int) get_term_meta( $tid, '_alg_pxf_google_category', true ), $run );
pxf_t( 'local candidate ids not sent', false === strpos( $sent['body']['messages'][0]['content'], '_ids' ) && false !== strpos( $sent['body']['messages'][0]['content'], '173: Apparel' ) );
Alg_WC_PXF_AI::approve( 'categories', array( $tid ) );
pxf_t( 'approve maps the category', 173 === (int) get_term_meta( $tid, '_alg_pxf_google_category', true ) );
Alg_WC_PXF_AI::undo( 'categories', array( $tid ) );
pxf_t( 'undo puts the earlier mapping back', 632 === (int) get_term_meta( $tid, '_alg_pxf_google_category', true ) && '' === get_term_meta( $tid, '_alg_pxf_ai_google_category', true ) );
$GLOBALS['pxf_answers'] = array( array( 'id' => $tid, 'google_category_id' => 999999, 'confidence' => 0.9 ) );
$run                    = Alg_WC_PXF_AI::run( 'categories', array( $tid ), array( 'sync' => true ) );
pxf_t( 'category outside the shortlist dropped', 0 === $run['suggested'] );
wp_delete_term( $tid, 'product_cat' );
wp_delete_file( $tax );

/* ------------------------------------------------------------------ REST */

do_action( 'rest_api_init' );
wp_set_current_user( 0 );
foreach ( array( array( 'POST', '/alg-pxf/v1/ai/estimate' ), array( 'POST', '/alg-pxf/v1/ai/run' ), array( 'GET', '/alg-pxf/v1/ai/results' ), array( 'POST', '/alg-pxf/v1/ai/approve' ), array( 'POST', '/alg-pxf/v1/ai/undo' ) ) as $route ) {
	$req = new WP_REST_Request( $route[0], $route[1] );
	$req->set_param( 'job', 'titles' );
	$res = rest_do_request( $req );
	pxf_t( 'logged out ' . $route[0] . ' ' . $route[1] . ' is 401', 401 === $res->get_status(), $res->get_status() );
}
wp_set_current_user( 1 );
$req = new WP_REST_Request( 'POST', '/alg-pxf/v1/ai/estimate' );
$req->set_param( 'job', 'titles' );
$req->set_param( 'count', 25 );
$res = rest_do_request( $req );
pxf_t( 'admin estimate 200', 200 === $res->get_status() && 3 === $res->get_data()['credits'], $res->get_data() );
$req = new WP_REST_Request( 'GET', '/alg-pxf/v1/ai/results' );
$req->set_param( 'job', 'attributes' );
$res = rest_do_request( $req );
pxf_t( 'admin results 200', 200 === $res->get_status() && is_array( $res->get_data()['items'] ), $res->get_data() );
$req = new WP_REST_Request( 'POST', '/alg-pxf/v1/ai/undo' );
$req->set_param( 'job', 'attributes' );
$req->set_param( 'ids', array( $c ) );
$res = rest_do_request( $req );
pxf_t( 'admin undo 200', 200 === $res->get_status() && 1 === $res->get_data()['removed'], $res->get_data() );

/* ------------------------------------------------------------------ Agent Control abilities */

$ab = Alg_WC_PXF_AI_Abilities::abilities( array() );
pxf_t( 'three read-only abilities', 3 === count( $ab ) && ! array_filter( $ab, fn( $x ) => 'read' !== $x['effect'] ) );
$why = $ab[2]['patterns'][0];
pxf_t( 'why-missing pattern', (bool) preg_match( $why, 'why is the Wool Beanie missing from the Google feed?', $mm ) && 'Wool Beanie' === $mm['product'] && 'Google' === $mm['feed'], $mm ?? '' );
pxf_t( 'list pattern', (bool) preg_match( $ab[0]['patterns'][0], 'which product feeds do I have?' ) && ! preg_match( $ab[0]['patterns'][0], 'why is the Wool Beanie missing from the Google feed?' ) );
pxf_t( 'not hooked without Agent Control', Alg_WC_PXF_AI_Abilities::active() || false === has_filter( 'acwc_abilities', array( 'Alg_WC_PXF_AI_Abilities', 'abilities' ) ) );
$r = Alg_WC_PXF_AI_Abilities::run( null, 'pxf_list_feeds', array(), 'test' );
pxf_t( 'list feeds report', is_array( $r ) && 'report' === $r['kind'] && '' !== $r['headline'], $r );
pxf_t( 'other abilities pass through', 'x' === Alg_WC_PXF_AI_Abilities::run( 'x', 'sales', array(), 'test' ) );

/* ------------------------------------------------------------------ clean up */

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
delete_option( Alg_WC_PXF_AI::OPTION_RUN );

echo "\n" . $GLOBALS['pxf_pass'] . ' passed, ' . $GLOBALS['pxf_fail'] . " failed\n";
