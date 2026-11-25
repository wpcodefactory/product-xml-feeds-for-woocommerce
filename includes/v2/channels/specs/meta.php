<?php
/**
 * Channel spec: Meta (Facebook and Instagram) catalog, research/platforms.md section 3.
 *
 * RSS with Google g: fields, which Meta reads, with Meta's own availability words and limits.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f    = array( 'Alg_WC_PXF_Channels', 'field' );
$spec = include __DIR__ . '/google.php';

$spec['id']              = 'meta';
$spec['label']           = __( 'Facebook and Instagram (Meta)', 'product-xml-feeds-for-woocommerce' );
$spec['group']           = __( 'Social', 'product-xml-feeds-for-woocommerce' );
$spec['formats']         = array( 'xml', 'csv', 'tsv' );
$spec['csv']             = array( 'separator' => ',' );
$spec['google_category'] = true;
$spec['notes']           = __( 'Catalog for Facebook and Instagram shops and Advantage+ catalog ads. Meta can fetch it every hour.', 'product-xml-feeds-for-woocommerce' );
// Meta lists only these three words. A product on backorder can be bought now, so it is sent as in stock.
$spec['availability']    = array( 'in_stock' => 'in stock', 'out_of_stock' => 'out of stock', 'backorder' => 'in stock', 'preorder' => 'preorder' );
$spec['condition']       = array( 'new' => 'new', 'refurbished' => 'refurbished', 'used' => 'used' );
$spec['age_group']       = array( 'newborn' => 'newborn', 'infant' => 'infant', 'toddler' => 'toddler', 'kids' => 'kids', 'adult' => 'adult', 'teen' => 'teen', 'all_ages' => 'all ages' );
$spec['ai']              = array( 'title' => 'title', 'description' => 'description', 'structured' => false );

unset(
	$spec['fields']['structured_title'],
	$spec['fields']['structured_description'],
	$spec['fields']['item_group_title'],
	$spec['fields']['availability_date'],
	$spec['fields']['unit_pricing_measure'],
	$spec['fields']['unit_pricing_base_measure'],
	$spec['fields']['certification']
);

$spec['fields']['id']['max']                         = 100;
$spec['fields']['title']['max']                      = 200;
$spec['fields']['description']['max']                = 9999;
$spec['fields']['availability']['allowed']           = array( 'in stock', 'out of stock', 'preorder' );
$spec['fields']['condition']['allowed']              = array( 'new', 'refurbished', 'used', 'used_like_new', 'used_good', 'used_fair', 'cpo', 'open_box_new' );
$spec['fields']['brand']['required']                 = 'conditional';
$spec['fields']['brand']['when']                     = 'no_gtin';
$spec['fields']['brand']['max']                      = 100;
$spec['fields']['mpn']['max']                        = 100;
$spec['fields']['item_group_id']['required']         = 'recommended';
$spec['fields']['color']['required']                 = 'recommended';
$spec['fields']['color']['max']                      = 200;
$spec['fields']['size']['required']                  = 'recommended';
$spec['fields']['size']['max']                       = 200;
$spec['fields']['gender']['required']                = 'recommended';
$spec['fields']['age_group']['required']             = 'recommended';
$spec['fields']['age_group']['allowed']              = array( 'adult', 'all ages', 'teen', 'kids', 'toddler', 'infant', 'newborn' );
$spec['fields']['additional_image_link']['list_max'] = 50;

$spec['fields']['quantity_to_sell_on_facebook'] = call_user_func( $f, __( 'Quantity for Facebook checkout', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'stock_quantity', array( 'unit' => 'integer' ) );
$spec['fields']['fb_product_category']          = call_user_func( $f, __( 'Facebook category', 'product-xml-feeds-for-woocommerce' ), false, 'category', '' );
$spec['fields']['status']                       = call_user_func( $f, __( 'Status', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'allowed' => array( 'active', 'archived' ) ) );

return $spec;
