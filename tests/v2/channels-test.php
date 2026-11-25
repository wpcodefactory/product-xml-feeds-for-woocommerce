<?php
/**
 * Tests for the v2 channels module (models, mapper, channels, Google taxonomy, sample files).
 *
 * Run on a test site: wp eval-file wp-content/plugins/product-xml-feeds-for-woocommerce-pro/tests/v2/channels-test.php
 * Prints one PASS or FAIL line per check and a total. Every change it makes to products is undone at the end.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$pxf_pass = 0;
$pxf_fail = 0;
$pxf_t    = function ( $name, $ok, $detail = '' ) use ( &$pxf_pass, &$pxf_fail ) {
	if ( $ok ) {
		$pxf_pass++;
		echo 'PASS ' . $name . "\n";
	} else {
		$pxf_fail++;
		echo 'FAIL ' . $name . ( '' !== $detail ? ' :: ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) : '' ) . "\n";
	}
};

if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
	echo "FAIL module channels not loaded\n";
	return;
}

$pxf_id = function ( $sku ) {
	return (int) wc_get_product_id_by_sku( $sku );
};
$pxf_model = function ( $id, $config = array() ) {
	return Alg_WC_PXF_Product_Data::model( wc_get_product( $id ), $config );
};
$pxf_cleanup = array();

// 1. Models of the edge products.
$keys = Alg_WC_PXF_Product_Data::keys();
foreach ( array( 'EDGE-AMP', 'EDGE-SALE', 'EDGE-OOS', 'EDGE-VAR', 'EDGE-RTL', 'EDGE-NOPRICE', 'woo-hoodie' ) as $sku ) {
	$id = $pxf_id( $sku );
	if ( ! $id ) {
		$pxf_t( "model $sku exists", false, 'product not found' );
		continue;
	}
	$m       = $pxf_model( $id );
	$missing = array_diff( $keys, array_keys( $m ) );
	$pxf_t( "model $sku has every key", empty( $missing ), $missing );
}

$m = $pxf_model( $pxf_id( 'EDGE-AMP' ) );
$pxf_t( 'EDGE-AMP title keeps & < > and quotes', 'Salt & Pepper Grinder <Set of 2> "Chef" edition' === $m['title'], $m['title'] );
$pxf_t( 'EDGE-AMP description plain text with entities decoded', 'Uses <ceramic> burrs. 5 > 3. Tom & Jerry approved.' === $m['description'], $m['description'] );
$pxf_t( 'EDGE-AMP GTIN from core field', '4006381333931' === $m['gtin'], $m['gtin'] );

$m = $pxf_model( $pxf_id( 'EDGE-SALE' ) );
$pxf_t( 'EDGE-SALE GTIN 4006381333948', '4006381333948' === $m['gtin'], $m['gtin'] );
$pxf_t( 'EDGE-SALE price is the regular price, sale price set', '59.00' === $m['price'] && '29.50' === $m['sale_price'], array( $m['price'], $m['sale_price'] ) );
$pxf_t( 'EDGE-SALE identifier_exists yes (has GTIN)', 'yes' === $m['identifier_exists'] );

// Sale dates as ISO 8601 interval.
$sale_product = wc_get_product( $pxf_id( 'EDGE-SALE' ) );
$old_from     = $sale_product->get_date_on_sale_from( 'edit' );
$old_to       = $sale_product->get_date_on_sale_to( 'edit' );
$sale_product->set_date_on_sale_from( gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) );
$sale_product->set_date_on_sale_to( gmdate( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ) );
$m = Alg_WC_PXF_Product_Data::model( $sale_product, array() );
$pxf_t( 'sale_price_effective_date is an ISO 8601 interval', (bool) preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}[+\-]\d{4}\/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}[+\-]\d{4}$/', $m['sale_price_effective_date'] ) && strlen( $m['sale_price_effective_date'] ) <= 51, $m['sale_price_effective_date'] );
$sale_product->set_date_on_sale_from( $old_from );
$sale_product->set_date_on_sale_to( $old_to );

$m = $pxf_model( $pxf_id( 'EDGE-OOS' ) );
$pxf_t( 'EDGE-OOS availability out_of_stock', 'out_of_stock' === $m['availability'], $m['availability'] );

$var_id = $pxf_id( 'EDGE-VAR' );
$m      = $pxf_model( $var_id );
$pxf_t( 'EDGE-VAR parent model type variable, price from variations', 'variable' === $m['type'] && '' !== $m['price'], array( $m['type'], $m['price'] ) );
$children = wc_get_product( $var_id )->get_children();
$ok       = ! empty( $children );
foreach ( $children as $child_id ) {
	$c = $pxf_model( $child_id );
	if ( (string) $var_id !== $c['item_group_id'] || '' === $c['color'] || '' === $c['size'] || (string) $child_id !== $c['id'] || false === strpos( $c['title'], $c['color'] ) ) {
		$ok = false;
		$pxf_t( 'EDGE-VAR variation ' . $child_id, false, array( $c['item_group_id'], $c['color'], $c['size'], $c['title'] ) );
	}
}
$pxf_t( 'EDGE-VAR every variation has item_group_id, color, size and a title with its options', $ok );
$c = $pxf_model( $children[0] );
$pxf_t( 'EDGE-VAR first variation is S / Navy', 'S' === $c['size'] && 'Navy' === $c['color'], array( $c['size'], $c['color'] ) );

$m = $pxf_model( $pxf_id( 'EDGE-RTL' ) );
$pxf_t( 'EDGE-RTL Hebrew title kept', 'מגבת חוף כחולה' === $m['title'], $m['title'] );

$m = $pxf_model( $pxf_id( 'EDGE-NOPRICE' ) );
$pxf_t( 'EDGE-NOPRICE price and image empty', '' === $m['price'] && '' === $m['image_link'], array( $m['price'], $m['image_link'] ) );

$hoodie = wc_get_product( $pxf_id( 'woo-hoodie' ) );
$hc     = $hoodie->get_children();
$c      = $pxf_model( $hc[0] );
$pxf_t( 'woo-hoodie variation color from global attribute pa_color', 'Red' === $c['color'] && (string) $hoodie->get_id() === $c['item_group_id'], array( $c['color'], $c['item_group_id'] ) );
$pxf_t( 'woo-hoodie variation weight with unit', (bool) preg_match( '/^[\d.]+ (kg|g|lb|oz)$/', $c['weight'] ), $c['weight'] );

// Attribute names in other languages.
$pxf_t( 'attribute kind: Farbe, Χρώμα, Couleur = color', 'color' === Alg_WC_PXF_Product_Data::kind( 'Farbe' ) && 'color' === Alg_WC_PXF_Product_Data::kind( 'Χρώμα' ) && 'color' === Alg_WC_PXF_Product_Data::kind( 'pa_couleur' ) );
$pxf_t( 'attribute kind: Größe, Μέγεθος, Taille = size', 'size' === Alg_WC_PXF_Product_Data::kind( 'Größe' ) && 'size' === Alg_WC_PXF_Product_Data::kind( 'Μεγεθος' ) && 'size' === Alg_WC_PXF_Product_Data::kind( 'taille' ) );
$pxf_t( 'attribute kind: Material, Muster, Geschlecht, Altersgruppe', 'material' === Alg_WC_PXF_Product_Data::kind( 'Matière' ) && 'pattern' === Alg_WC_PXF_Product_Data::kind( 'Muster' ) && 'gender' === Alg_WC_PXF_Product_Data::kind( 'Geschlecht' ) && 'age_group' === Alg_WC_PXF_Product_Data::kind( 'Altersgruppe' ) );
$pxf_t( 'gender words: Damen = female, Ανδρικό = male', 'female' === Alg_WC_PXF_Product_Data::canonical_word( 'Damen', Alg_WC_PXF_Product_Data::GENDER_WORDS ) && 'male' === Alg_WC_PXF_Product_Data::canonical_word( 'Ανδρικό', Alg_WC_PXF_Product_Data::GENDER_WORDS ) );

// 2. Brand from core Brands.
$normal_id = $pxf_id( 'woo-beanie' );
$brand     = term_exists( 'Northwind Test Brand', 'product_brand' );
$new_brand = false;
if ( ! $brand ) {
	$brand     = wp_insert_term( 'Northwind Test Brand', 'product_brand' );
	$new_brand = true;
}
$brand_id = (int) ( is_array( $brand ) ? $brand['term_id'] : $brand );
wp_set_object_terms( $normal_id, array( $brand_id ), 'product_brand', true );
$m = $pxf_model( $normal_id );
$pxf_t( 'brand from core Brands taxonomy', 'Northwind Test Brand' === $m['brand'], $m['brand'] );

// Test GTIN on the normal product (valid EAN-13, unused), removed at the end.
$normal      = wc_get_product( $normal_id );
$old_gtin    = $normal->get_global_unique_id( 'edit' );
$gtin_digits = '400638133397';
$sum         = 0;
for ( $i = 0; $i < 12; $i++ ) {
	$sum += (int) $gtin_digits[ $i ] * ( 0 === $i % 2 ? 1 : 3 );
}
$test_gtin = $gtin_digits . ( ( 10 - $sum % 10 ) % 10 );
update_post_meta( $normal_id, '_global_unique_id', $test_gtin );
wc_delete_product_transients( $normal_id );
clean_post_cache( $normal_id );
$m = $pxf_model( $normal_id );
$pxf_t( 'normal product has brand, GTIN, image, price', '' !== $m['brand'] && $test_gtin === $m['gtin'] && '' !== $m['image_link'] && '' !== $m['price'], array( $m['brand'], $m['gtin'], $m['image_link'], $m['price'] ) );

// _alg_ean fallback.
update_post_meta( $pxf_id( 'EDGE-OOS' ), '_alg_ean', '4006381333962' );
$m = $pxf_model( $pxf_id( 'EDGE-OOS' ) );
$pxf_t( 'GTIN falls back to the EAN plugin meta _alg_ean', '4006381333962' === $m['gtin'], $m['gtin'] );
delete_post_meta( $pxf_id( 'EDGE-OOS' ), '_alg_ean' );

// 3. Every channel: required fields for a normal product (merchant-only values given through the mapping).
$merchant_mapping = array(
	'idealo'      => array( 'deliveryCosts_dhl' => array( 'type' => 'static', 'value' => '4.90' ) ),
	'pricerunner' => array(
		'ShippingCost' => array( 'type' => 'static', 'value' => '4.90' ),
		'DeliveryTime' => array( 'type' => 'static', 'value' => '1-3 days' ),
	),
	'skroutz'     => array(
		'mpn'      => array( 'type' => 'field', 'key' => 'sku' ),
		'quantity' => array( 'type' => 'static', 'value' => '5' ),
	),
);
$channels = Alg_WC_PXF_Channels::all();
$pxf_t( 'Alg_WC_PXF_Channels::all() has the 10 channels', array( 'google', 'meta', 'pinterest', 'microsoft', 'tiktok', 'openai', 'idealo', 'pricerunner', 'skroutz', 'custom' ) === array_keys( $channels ) );
$pxf_t( 'Alg_WC_PXF_Channels::get() unknown id gives null', null === Alg_WC_PXF_Channels::get( 'nope' ) && is_array( Alg_WC_PXF_Channels::get( 'google' ) ) );
foreach ( $channels as $cid => $spec ) {
	$config = array( 'channel' => $cid, 'country' => ( 'openai' === $cid ? 'US' : 'DE' ), 'mapping' => ( isset( $merchant_mapping[ $cid ] ) ? $merchant_mapping[ $cid ] : array() ) );
	$m      = Alg_WC_PXF_Product_Data::model( wc_get_product( $normal_id ), $config );
	$item   = Alg_WC_PXF_Mapper::item( $m, $spec, $config );
	if ( ! empty( $spec['raw'] ) ) {
		$pxf_t( "channel $cid item is rendered text", isset( $item['_raw'] ) && false !== strpos( $item['_raw'], 'woo-beanie' ), $item );
		continue;
	}
	$missing = array_diff( Alg_WC_PXF_Channels::required_fields( $spec ), array_keys( $item ) );
	$pxf_t( "channel $cid has every required field for a normal product", empty( $missing ), $missing );
	$bad = array();
	foreach ( $item as $field => $value ) {
		if ( isset( $spec['fields'][ $field ]['allowed'] ) && is_string( $value ) && ! in_array( $value, $spec['fields'][ $field ]['allowed'], true ) ) {
			$bad[ $field ] = $value;
		}
	}
	$pxf_t( "channel $cid values are in its allowed lists", empty( $bad ), $bad );
	$long = array();
	foreach ( $item as $field => $value ) {
		if ( isset( $spec['fields'][ $field ]['max'] ) && is_string( $value ) && mb_strlen( $value ) > $spec['fields'][ $field ]['max'] ) {
			$long[] = $field;
		}
	}
	$pxf_t( "channel $cid values within max lengths", empty( $long ), $long );
}

// 4. Google availability values, price unit, title cut.
$google = Alg_WC_PXF_Channels::get( 'google' );
$base   = $pxf_model( $normal_id );
$ok     = true;
foreach ( array( 'in_stock', 'out_of_stock', 'backorder', 'preorder' ) as $canonical ) {
	$item = Alg_WC_PXF_Mapper::item( array_merge( $base, array( 'availability' => $canonical ) ), $google, array() );
	if ( ! in_array( $item['availability'], array( 'in_stock', 'out_of_stock', 'preorder', 'backorder' ), true ) ) {
		$ok = false;
	}
}
$pxf_t( 'Google availability values are allowed values', $ok );
foreach ( array( 'meta', 'pinterest', 'tiktok', 'openai' ) as $cid ) {
	$spec = Alg_WC_PXF_Channels::get( $cid );
	$item = Alg_WC_PXF_Mapper::item( array_merge( $base, array( 'availability' => 'preorder' ) ), $spec, array() );
	$pxf_t( "$cid preorder word is its own (" . $item['availability'] . ')', in_array( $item['availability'], $spec['fields']['availability']['allowed'], true ) );
}
$item = Alg_WC_PXF_Mapper::item( $base, $google, array() );
$pxf_t( 'price with currency unit "12.00 EUR"', (bool) preg_match( '/^\d+\.\d{2} [A-Z]{3}$/', $item['price'] ), $item['price'] );
$sale_item = Alg_WC_PXF_Mapper::item( $pxf_model( $pxf_id( 'EDGE-SALE' ) ), $google, array() );
$pxf_t( 'sale price with currency unit', '29.50 EUR' === $sale_item['sale_price'] && '59.00 EUR' === $sale_item['price'], array( $sale_item['price'], $sale_item['sale_price'] ) );

$long_title = trim( str_repeat( 'Merino wool crew sweater ', 9 ) ); // 224 characters.
$item       = Alg_WC_PXF_Mapper::item( array_merge( $base, array( 'title' => $long_title ) ), $google, array() );
$cut        = $item['title'];
$pxf_t( 'title cut at 150 on a word', mb_strlen( $cut ) <= 150 && 0 === strpos( $long_title, $cut ) && ' ' === mb_substr( $long_title, mb_strlen( $cut ), 1 ) && mb_strlen( $cut ) > 130, array( mb_strlen( $cut ), $cut ) );

// Mapping overrides, prefix and suffix, value maps, placeholder.
$config = array(
	'mapping' => array(
		'title'          => array( 'prefix' => 'NEW ', 'suffix' => ' (DE)' ),
		'custom_label_0' => array( 'type' => 'placeholder', 'value' => '[alg_product_sku]' ),
		'custom_label_1' => array( 'type' => 'taxonomy', 'key' => 'product_cat' ),
		'custom_label_2' => array( 'type' => 'meta', 'key' => '_sku' ),
		'custom_label_3' => array( 'type' => 'static', 'value' => 'winter' ),
		'mpn'            => array( 'type' => 'field', 'key' => 'sku' ),
		'availability'   => array( 'map' => array( 'in_stock' => 'in_stock' ) ),
	),
);
$item = Alg_WC_PXF_Mapper::item( $base, $google, $config );
$pxf_t( 'mapping: prefix and suffix', 'NEW Beanie (DE)' === $item['title'], $item['title'] );
$pxf_t( 'mapping: placeholder renders a classic shortcode', 'woo-beanie' === $item['custom_label_0'], $item['custom_label_0'] );
$pxf_t( 'mapping: taxonomy, meta, static, field', '' !== $item['custom_label_1'] && 'woo-beanie' === $item['custom_label_2'] && 'winter' === $item['custom_label_3'] && 'woo-beanie' === $item['mpn'], array( $item['custom_label_1'], $item['custom_label_2'], $item['custom_label_3'], $item['mpn'] ) );
$item = Alg_WC_PXF_Mapper::item( $base, Alg_WC_PXF_Channels::get( 'idealo' ), array( 'mapping' => array( 'delivery' => array( 'map' => array( 'in_stock' => '24 Stunden' ) ) ) ) );
$pxf_t( 'mapping: availability value map', '24 Stunden' === $item['delivery'], $item['delivery'] );

// 5. OpenAI field names.
$item = Alg_WC_PXF_Mapper::item( $pxf_model( $normal_id, array( 'country' => 'US' ) ), Alg_WC_PXF_Channels::get( 'openai' ), array( 'country' => 'US' ) );
$pxf_t( 'openai item uses its own field names', isset( $item['item_id'], $item['url'], $item['image_url'], $item['seller_name'] ) && ! isset( $item['id'] ) && ! isset( $item['link'] ) && ! isset( $item['image_link'] ), array_keys( $item ) );
$pxf_t( 'openai target_countries ["US"], store_country US, checkout off', array( 'US' ) === $item['target_countries'] && 'US' === $item['store_country'] && 'false' === $item['is_eligible_checkout'] );
$vitem = Alg_WC_PXF_Mapper::item( $pxf_model( $children[0] ), Alg_WC_PXF_Channels::get( 'openai' ), array( 'country' => 'US' ) );
$pxf_t( 'openai variation: group_id, listing_has_variations, variant_dict', (string) $var_id === $vitem['group_id'] && 'true' === $vitem['listing_has_variations'] && array( 'Size' => 'S', 'Color' => 'Navy' ) === $vitem['variant_dict'], $vitem );
$item = Alg_WC_PXF_Mapper::item( array_merge( $base, array( 'availability' => 'preorder' ) ), Alg_WC_PXF_Channels::get( 'openai' ), array() );
$pxf_t( 'openai preorder is pre_order', 'pre_order' === $item['availability'], $item['availability'] );

// 6. AI values (approved only) and the Google structured_title rule.
update_post_meta( $normal_id, '_alg_pxf_ai_title', 'Soft Knit Beanie in Warm Wool' );
update_post_meta( $normal_id, '_alg_pxf_ai_title_status', 'suggested' );
update_post_meta( $normal_id, '_alg_pxf_ai_description', '<p>A warm beanie.</p>' );
update_post_meta( $normal_id, '_alg_pxf_ai_description_status', 'approved' );
$m = $pxf_model( $normal_id );
$pxf_t( 'AI title not used while only suggested', '' === $m['ai_title'], $m['ai_title'] );
$pxf_t( 'approved AI description in the model as plain text', 'A warm beanie.' === $m['ai_description'], $m['ai_description'] );
update_post_meta( $normal_id, '_alg_pxf_ai_title_status', 'approved' );
$m    = $pxf_model( $normal_id );
$on   = array( 'use_ai_titles' => true, 'use_ai_descriptions' => true );
$item = Alg_WC_PXF_Mapper::item( $m, $google, $on );
$pxf_t( 'Google: AI title goes to structured_title, plain title left out', ! isset( $item['title'] ) && isset( $item['structured_title'] ) && array( 'digital_source_type' => 'trained_algorithmic_media', 'content' => 'Soft Knit Beanie in Warm Wool' ) === $item['structured_title'], $item );
$pxf_t( 'Google: AI description goes to structured_description', ! isset( $item['description'] ) && 'A warm beanie.' === $item['structured_description']['content'] );
$item = Alg_WC_PXF_Mapper::item( $m, $google, array() );
$pxf_t( 'Google: AI title not used when the feed does not ask', 'Beanie' === $item['title'] && ! isset( $item['structured_title'] ) );
$item = Alg_WC_PXF_Mapper::item( $m, Alg_WC_PXF_Channels::get( 'meta' ), $on );
$pxf_t( 'Meta: AI title used as plain title', 'Soft Knit Beanie in Warm Wool' === $item['title'] && ! isset( $item['structured_title'] ), $item['title'] );
$item = Alg_WC_PXF_Mapper::item( $m, Alg_WC_PXF_Channels::get( 'skroutz' ), $on );
$pxf_t( 'Skroutz: AI title used as name', 'Soft Knit Beanie in Warm Wool' === $item['name'], $item['name'] );

// 7. Skroutz: one product per color with nested sizes.
$skroutz = Alg_WC_PXF_Channels::get( 'skroutz' );
$items   = Alg_WC_PXF_Mapper::items( $pxf_model( $var_id ), $skroutz, array() );
$colors  = array();
$ok      = 3 === count( $items );
foreach ( $items as $it ) {
	$colors[] = isset( $it['color'] ) ? $it['color'] : '';
	if ( empty( $it['variations'] ) || 4 !== count( $it['variations'] ) || 0 !== strpos( $it['id'], $var_id . '-' ) || isset( $it['variations'][0]['color'] ) ) {
		$ok = false;
	}
}
$pxf_t( 'Skroutz: EDGE-VAR becomes 3 color products with 4 sizes each', $ok && array( 'Navy', 'Olive', 'Sand' ) === $colors, array( count( $items ), $colors ) );
$pxf_t( 'Skroutz: variation has variationid, size, availability, quantity', isset( $items[0]['variations'][0]['variationid'], $items[0]['variations'][0]['size'], $items[0]['variations'][0]['availability'] ), isset( $items[0]['variations'][0] ) ? $items[0]['variations'][0] : '' );
$pxf_t( 'Skroutz: name has the color, never the size', isset( $items[0]['name'] ) && 'Merino Crew Sweater Navy' === $items[0]['name'], isset( $items[0]['name'] ) ? $items[0]['name'] : '' );
$pxf_t( 'Skroutz: sizes list on the color product', isset( $items[0]['size'] ) && 'S, M, L, XL' === $items[0]['size'], isset( $items[0]['size'] ) ? $items[0]['size'] : '' );
$hitems = Alg_WC_PXF_Mapper::items( $pxf_model( $hoodie->get_id() ), $skroutz, array() );
$pxf_t( 'Skroutz: woo-hoodie color ids use the color term ID', ! empty( $hitems ) && (bool) preg_match( '/^' . $hoodie->get_id() . '-\d+$/', $hitems[0]['id'] ), wp_list_pluck( $hitems, 'id' ) );

// 8. Google taxonomy and the category map.
$pxf_t( 'taxonomy file loaded (5000+ categories)', count( Alg_WC_PXF_Google_Taxonomy::all() ) > 5000 );
$pxf_t( 'taxonomy get(212) and path(212)', 'Shirts & Tops' === Alg_WC_PXF_Google_Taxonomy::get( 212 )['name'] && 'Apparel & Accessories > Clothing > Shirts & Tops' === Alg_WC_PXF_Google_Taxonomy::path( 212 ) );
$found = wp_list_pluck( Alg_WC_PXF_Google_Taxonomy::search( 'shirts', 10 ), 'id' );
$pxf_t( 'taxonomy search "shirts" finds 212', in_array( 212, $found, true ), $found );
$short = Alg_WC_PXF_Google_Taxonomy::shortlist( 'Hoodies', 40 );
$pxf_t( 'shortlist("Hoodies") has Shirts & Tops (212) among at most 40', isset( $short[212] ) && count( $short ) <= 40, array_slice( $short, 0, 5, true ) );
$short = Alg_WC_PXF_Google_Taxonomy::shortlist( 'Hats', 40 );
$pxf_t( 'shortlist("Hats") puts Hats (173) first', 173 === (int) key( $short ), array_slice( $short, 0, 3, true ) );
$pxf_t( 'shortlist of an unknown word falls back to top level', count( Alg_WC_PXF_Google_Taxonomy::shortlist( 'qqqzzz', 40 ) ) > 10 );
$cats = wc_get_product( $normal_id )->get_category_ids();
$cat  = (int) $cats[0];
$old  = get_term_meta( $cat, '_alg_pxf_google_category', true );
Alg_WC_PXF_Google_Taxonomy::set_category( $cat, 173 );
$m = $pxf_model( $normal_id );
$pxf_t( 'category map gives google_product_category', '173' === $m['google_product_category'], $m['google_product_category'] );
$parent_term = get_term( $cat, 'product_cat' );
if ( $parent_term && $parent_term->parent ) {
	Alg_WC_PXF_Google_Taxonomy::set_category( $cat, 0 );
	Alg_WC_PXF_Google_Taxonomy::set_category( $parent_term->parent, 166 );
	$m = $pxf_model( $normal_id );
	$pxf_t( 'unmapped category uses its parent map', '166' === $m['google_product_category'], $m['google_product_category'] );
	delete_term_meta( $parent_term->parent, '_alg_pxf_google_category' );
}
if ( '' === (string) $old ) {
	delete_term_meta( $cat, '_alg_pxf_google_category' );
} else {
	update_term_meta( $cat, '_alg_pxf_google_category', $old );
}

// 9. Custom channel: same bytes as the classic generator for the same template and product.
$custom = Alg_WC_PXF_Channels::get( 'custom' );
foreach ( array( 'EDGE-AMP', 'woo-hoodie', 'woo-beanie' ) as $sku ) {
	$pid     = $pxf_id( $sku );
	$file    = 'pxf-v2-channels-test-77.xml';
	$filters = array(
		'pre_option_alg_products_xml_products_incl_77' => function () use ( $pid ) {
			return array( $pid );
		},
		'pre_option_alg_products_xml_file_path_77'     => function () use ( $file ) {
			return $file;
		},
		'pre_option_alg_products_xml_header_77'        => function () {
			return '<root>' . PHP_EOL;
		},
		'pre_option_alg_products_xml_footer_77'        => function () {
			return '</root>';
		},
	);
	foreach ( $filters as $hook => $cb ) {
		add_filter( $hook, $cb );
	}
	alg_wc_product_xml_feeds()->core->create_products_xml( 77 );
	foreach ( $filters as $hook => $cb ) {
		remove_filter( $hook, $cb );
	}
	$classic = file_exists( ABSPATH . $file ) ? file_get_contents( ABSPATH . $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( file_exists( ABSPATH . $file ) ) {
		wp_delete_file( ABSPATH . $file );
	}
	$cconfig = array( 'channel' => 'custom', 'custom_template' => array( 'header' => '<root>' . PHP_EOL, 'footer' => '</root>' ) );
	$v2      = Alg_WC_PXF_Mapper::custom_part( 'header', $cconfig );
	foreach ( Alg_WC_PXF_Mapper::items( $pxf_model( $pid, $cconfig ), $custom, $cconfig ) as $it ) {
		$v2 .= $it['_raw'];
	}
	$v2 .= Alg_WC_PXF_Mapper::custom_part( 'footer', $cconfig );
	$pxf_t( "custom channel renders $sku exactly like the classic generator", '' !== $classic && $classic === $v2, '' === $classic ? 'no classic file' : substr( $classic, 0, 300 ) . ' <> ' . substr( $v2, 0, 300 ) );
}

// 10. Sample files per channel, XML well-formed, JSONL and CSV readable.
$samples_dir = __DIR__ . '/samples';
wp_mkdir_p( $samples_dir );
$sample_ids = array( $normal_id, $pxf_id( 'EDGE-AMP' ), $pxf_id( 'EDGE-SALE' ), $pxf_id( 'EDGE-RTL' ), $pxf_id( 'EDGE-OOS' ), $var_id, $hoodie->get_id() );

$pxf_xml_text = function ( $value ) {
	$value = wp_check_invalid_utf8( (string) $value, true );
	$value = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );
	return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
};
$pxf_xml_field = function ( $name, $value, $def, $prefix, $indent ) use ( &$pxf_xml_field, $pxf_xml_text ) {
	$tag = $prefix . $name;
	if ( ! is_array( $value ) ) {
		return $indent . '<' . $tag . '>' . $pxf_xml_text( $value ) . '</' . $tag . '>' . "\n";
	}
	if ( Alg_WC_PXF_Mapper::is_assoc( $value ) ) {
		$out = $indent . '<' . $tag . '>' . "\n";
		foreach ( $value as $child => $child_value ) {
			$out .= $pxf_xml_field( $child, $child_value, array(), $prefix, $indent . "\t" );
		}
		return $out . $indent . '</' . $tag . '>' . "\n";
	}
	if ( ! empty( $def['list_item'] ) ) {
		$out = $indent . '<' . $tag . '>' . "\n";
		foreach ( $value as $row ) {
			$out .= $pxf_xml_field( $def['list_item'], $row, array(), $prefix, $indent . "\t" );
		}
		return $out . $indent . '</' . $tag . '>' . "\n";
	}
	$out = '';
	foreach ( $value as $one ) {
		$out .= $pxf_xml_field( $name, $one, array(), $prefix, $indent );
	}
	return $out;
};
$pxf_head = function ( $text ) {
	return strtr(
		$text,
		array(
			'{site_name}'        => get_bloginfo( 'name' ),
			'{home_url}'         => home_url( '/' ),
			'{site_description}' => get_bloginfo( 'description' ),
			'{datetime}'         => wp_date( 'Y-m-d H:i' ),
		)
	);
};

foreach ( $channels as $cid => $spec ) {
	$format = $spec['formats'][0];
	$config = array( 'channel' => $cid, 'country' => ( 'openai' === $cid ? 'US' : 'DE' ), 'mapping' => ( isset( $merchant_mapping[ $cid ] ) ? $merchant_mapping[ $cid ] : array() ) );
	$items  = array();
	foreach ( $sample_ids as $sid ) {
		$product = wc_get_product( $sid );
		$list    = array( $product );
		if ( $product->is_type( 'variable' ) && 'all' === $spec['variation_mode'] ) {
			$list = array_slice( array_map( 'wc_get_product', $product->get_children() ), 0, 3 );
		}
		foreach ( $list as $p ) {
			foreach ( Alg_WC_PXF_Mapper::items( Alg_WC_PXF_Product_Data::model( $p, $config ), $spec, $config ) as $it ) {
				$items[] = $it;
			}
		}
	}
	$path = $samples_dir . '/' . $cid . '.' . $format;
	if ( ! empty( $spec['raw'] ) ) {
		$body = Alg_WC_PXF_Mapper::custom_part( 'header', $config );
		foreach ( $items as $it ) {
			$body .= $it['_raw'];
		}
		$body .= Alg_WC_PXF_Mapper::custom_part( 'footer', $config );
	} elseif ( 'xml' === $format ) {
		$x      = $spec['xml'];
		$prefix = isset( $x['prefix'] ) ? $x['prefix'] : '';
		$attrs  = '';
		foreach ( (array) $x['root_attrs'] as $k => $v ) {
			$attrs .= ' ' . $k . '="' . $pxf_xml_text( $v ) . '"';
		}
		$head = '';
		foreach ( (array) $x['head'] as $k => $v ) {
			$head .= "\t<" . $k . '>' . $pxf_xml_text( $pxf_head( $v ) ) . '</' . $k . ">\n";
		}
		$body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<' . $x['root'] . $attrs . ">\n";
		if ( 'root' === $x['head_position'] ) {
			$body .= $head;
		}
		$body .= '<' . $x['wrap'] . ">\n";
		if ( 'root' !== $x['head_position'] ) {
			$body .= $head;
		}
		foreach ( $items as $it ) {
			$body .= "\t<" . $x['item'] . ">\n";
			foreach ( $it as $field => $value ) {
				$def   = isset( $spec['fields'][ $field ] ) ? $spec['fields'][ $field ] : array();
				$body .= $pxf_xml_field( $field, $value, $def, $prefix, "\t\t" );
			}
			$body .= "\t</" . $x['item'] . ">\n";
		}
		$body .= '</' . $x['wrap'] . ">\n</" . $x['root'] . ">\n";
	} elseif ( 'jsonl' === $format ) {
		$body = '';
		foreach ( $items as $it ) {
			foreach ( $it as $field => $value ) {
				if ( isset( $spec['fields'][ $field ]['json'] ) && 'bool' === $spec['fields'][ $field ]['json'] ) {
					$it[ $field ] = ( 'true' === $value );
				}
			}
			$body .= wp_json_encode( $it, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		}
	} else {
		$sep    = ( 'tsv' === $format ? "\t" : $spec['csv']['separator'] );
		$fields = array();
		foreach ( $spec['fields'] as $field => $def ) {
			if ( empty( $def['children'] ) ) {
				$fields[] = $field;
			}
		}
		$fh = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $fh, $fields, $sep, '"', '' );
		foreach ( $items as $it ) {
			$row = array();
			foreach ( $fields as $field ) {
				$value = isset( $it[ $field ] ) ? $it[ $field ] : '';
				if ( is_array( $value ) ) {
					$value = Alg_WC_PXF_Mapper::is_assoc( $value ) ? wp_json_encode( $value ) : implode( isset( $spec['fields'][ $field ]['list_sep'] ) ? $spec['fields'][ $field ]['list_sep'] : ',', $value );
				}
				$row[] = $value;
			}
			fputcsv( $fh, $row, $sep, '"', '' );
		}
		rewind( $fh );
		$body = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}
	file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( 'xml' === $format ) {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$ok  = $dom->loadXML( $body );
		$err = libxml_get_errors();
		libxml_clear_errors();
		$pxf_t( "sample $cid.$format is well-formed XML (" . count( $items ) . ' items)', $ok && empty( $err ), empty( $err ) ? '' : trim( $err[0]->message ) . ' line ' . $err[0]->line );
	} elseif ( 'jsonl' === $format ) {
		$ok = true;
		foreach ( array_filter( explode( "\n", $body ) ) as $line ) {
			if ( ! is_array( json_decode( $line, true ) ) ) {
				$ok = false;
			}
		}
		$pxf_t( "sample $cid.$format every line is a JSON object (" . count( $items ) . ' items)', $ok );
	} else {
		$fh    = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$sep   = ( 'tsv' === $format ? "\t" : $spec['csv']['separator'] );
		$width = null;
		$ok    = true;
		$rows  = 0;
		while ( false !== ( $row = fgetcsv( $fh, 0, $sep, '"', '' ) ) ) {
			$width = ( null === $width ? count( $row ) : $width );
			$ok    = $ok && count( $row ) === $width;
			$rows++;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$pxf_t( "sample $cid.$format rows have the same columns (" . ( $rows - 1 ) . ' items)', $ok && $rows === count( $items ) + 1 );
	}
}

// Undo every change.
delete_post_meta( $normal_id, '_alg_pxf_ai_title' );
delete_post_meta( $normal_id, '_alg_pxf_ai_title_status' );
delete_post_meta( $normal_id, '_alg_pxf_ai_description' );
delete_post_meta( $normal_id, '_alg_pxf_ai_description_status' );
if ( '' === (string) $old_gtin ) {
	delete_post_meta( $normal_id, '_global_unique_id' );
} else {
	update_post_meta( $normal_id, '_global_unique_id', $old_gtin );
}
wp_remove_object_terms( $normal_id, array( $brand_id ), 'product_brand' );
if ( $new_brand ) {
	wp_delete_term( $brand_id, 'product_brand' );
}
wc_delete_product_transients( $normal_id );
clean_post_cache( $normal_id );

echo "\n" . ( 0 === $pxf_fail ? 'ALL PASS' : 'FAILURES' ) . ": $pxf_pass passed, $pxf_fail failed\n";
