<?php
/**
 * Channel spec: TikTok Ads catalog file (Video Shopping Ads), research/platforms.md 4c.
 *
 * Not TikTok Shop: the marketplace needs its own connector, not a feed.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'tiktok',
	'label'           => __( 'TikTok catalog', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Social', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array(),
	'formats'         => array( 'csv', 'xml' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(
		'root'          => 'rss',
		'root_attrs'    => array( 'version' => '2.0', 'xmlns:g' => 'http://base.google.com/ns/1.0' ),
		'wrap'          => 'channel',
		'item'          => 'item',
		'prefix'        => 'g:',
		'head'          => array( 'title' => '{site_name}', 'link' => '{home_url}', 'description' => '{site_description}' ),
		'head_position' => 'wrap',
	),
	'csv'             => array( 'separator' => ',' ),
	'variation_mode'  => 'all',
	'google_category' => true,
	'notes'           => __( 'Catalog file for TikTok video shopping ads. For TikTok Shop use the official TikTok for WooCommerce app.', 'product-xml-feeds-for-woocommerce' ),
	'availability'    => array( 'in_stock' => 'in stock', 'out_of_stock' => 'out of stock', 'backorder' => 'available for order', 'preorder' => 'preorder' ),
	'condition'       => array( 'new' => 'new', 'refurbished' => 'refurbished', 'used' => 'used' ),
	'gender'          => array( 'male' => 'male', 'female' => 'female', 'unisex' => 'unisex' ),
	'age_group'       => array( 'newborn' => 'newborn', 'infant' => 'infant', 'toddler' => 'toddler', 'kids' => 'kids', 'adult' => 'adult' ),
	'ai'              => array( 'title' => 'title', 'description' => 'description', 'structured' => false ),
	'fields'          => array(
		'sku_id'                    => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id' ),
		'title'                     => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 150 ) ),
		'description'               => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'description', array( 'max' => 10000 ) ),
		'availability'              => call_user_func( $f, __( 'Stock status', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability', array( 'allowed' => array( 'in stock', 'available for order', 'preorder', 'out of stock', 'discontinued' ) ) ),
		'condition'                 => call_user_func( $f, __( 'Condition', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'condition', array( 'allowed' => array( 'new', 'refurbished', 'used' ) ) ),
		'price'                     => call_user_func( $f, __( 'Price', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'price', array( 'unit' => 'currency' ) ),
		'link'                      => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link' ),
		'image_link'                => call_user_func( $f, __( 'Main image (JPG or PNG)', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'image_link' ),
		'brand'                     => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'brand' ),
		'item_group_id'             => call_user_func( $f, __( 'Variation group ID', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'item_group_id', array( 'when' => 'variation' ) ),
		'gtin'                      => call_user_func( $f, __( 'GTIN (EAN, UPC)', 'product-xml-feeds-for-woocommerce' ), false, 'identifiers', 'gtin', array( 'max' => 14 ) ),
		'mpn'                       => call_user_func( $f, __( 'Manufacturer part number (MPN)', 'product-xml-feeds-for-woocommerce' ), false, 'identifiers', 'mpn' ),
		'google_product_category'   => call_user_func( $f, __( 'Google category', 'product-xml-feeds-for-woocommerce' ), false, 'category', 'google_product_category' ),
		'additional_image_link'     => call_user_func( $f, __( 'More images', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'additional_image_links', array( 'list' => true, 'list_max' => 10, 'list_sep' => ',' ) ),
		'video_link'                => call_user_func( $f, __( 'Video link', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '' ),
		'sale_price'                => call_user_func( $f, __( 'Sale price', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price', array( 'unit' => 'currency' ) ),
		'sale_price_effective_date' => call_user_func( $f, __( 'Sale dates', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price_effective_date' ),
		'age_group'                 => call_user_func( $f, __( 'Age group', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'age_group', array( 'allowed' => array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ) ) ),
		'gender'                    => call_user_func( $f, __( 'Gender', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'gender', array( 'allowed' => array( 'male', 'female', 'unisex' ) ) ),
		'color'                     => call_user_func( $f, __( 'Color', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'color' ),
		'size'                      => call_user_func( $f, __( 'Size', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'size' ),
		'material'                  => call_user_func( $f, __( 'Material', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'material' ),
		'pattern'                   => call_user_func( $f, __( 'Pattern', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'pattern' ),
		'shipping'                  => call_user_func( $f, __( 'Shipping', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array(
			'children' => array(
				'country' => call_user_func( $f, __( 'Country', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '' ),
				'service' => call_user_func( $f, __( 'Service', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '' ),
				'price'   => call_user_func( $f, __( 'Price', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '', array( 'unit' => 'currency' ) ),
			),
		) ),
		'custom_label_0'            => call_user_func( $f, __( 'Custom label 0', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_1'            => call_user_func( $f, __( 'Custom label 1', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_2'            => call_user_func( $f, __( 'Custom label 2', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_3'            => call_user_func( $f, __( 'Custom label 3', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_4'            => call_user_func( $f, __( 'Custom label 4', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
	),
);
