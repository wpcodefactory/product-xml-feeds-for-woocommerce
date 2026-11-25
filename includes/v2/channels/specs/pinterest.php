<?php
/**
 * Channel spec: Pinterest catalog, research/platforms.md 4b.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$spec = include __DIR__ . '/google.php';

$spec['id']           = 'pinterest';
$spec['label']        = __( 'Pinterest', 'product-xml-feeds-for-woocommerce' );
$spec['group']        = __( 'Social', 'product-xml-feeds-for-woocommerce' );
$spec['formats']      = array( 'xml', 'tsv', 'csv' );
$spec['notes']        = __( 'Pinterest catalog. Pinterest reads the file once a day; images of at least 1000 x 1500 px work best.', 'product-xml-feeds-for-woocommerce' );
// Pinterest lists only these three words. A product on backorder can be bought now, so it is sent as in stock.
$spec['availability'] = array( 'in_stock' => 'in stock', 'out_of_stock' => 'out of stock', 'backorder' => 'in stock', 'preorder' => 'preorder' );
$spec['ai']           = array( 'title' => 'title', 'description' => 'description', 'structured' => false );

unset(
	$spec['fields']['structured_title'],
	$spec['fields']['structured_description'],
	$spec['fields']['item_group_title'],
	$spec['fields']['availability_date'],
	$spec['fields']['identifier_exists'],
	$spec['fields']['unit_pricing_measure'],
	$spec['fields']['unit_pricing_base_measure'],
	$spec['fields']['certification'],
	$spec['fields']['shipping']
);

$spec['fields']['id']['max']               = 127;
$spec['fields']['title']['max']            = 500;
$spec['fields']['description']['max']      = 10000;
$spec['fields']['link']['max']             = 511;
$spec['fields']['availability']['allowed'] = array( 'in stock', 'out of stock', 'preorder' );
$spec['fields']['condition']['required']   = false;
$spec['fields']['brand']['required']       = false;
$spec['fields']['brand']['max']            = 100;
$spec['fields']['gtin']['required']        = false;
$spec['fields']['mpn']['required']         = false;
$spec['fields']['item_group_id']['max']    = 127;
$spec['fields']['color']['required']       = false;
$spec['fields']['size']['required']        = false;
$spec['fields']['gender']['required']      = false;
$spec['fields']['age_group']['required']   = false;

return $spec;
