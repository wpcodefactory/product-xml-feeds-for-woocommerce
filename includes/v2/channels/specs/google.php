<?php
/**
 * Channel spec: Google Shopping (Merchant Center product data specification, research/platforms.md section 1).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'google',
	'label'           => __( 'Google Shopping', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Search and shopping', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array(),
	'formats'         => array( 'xml', 'tsv' ),
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
	'csv'             => array( 'separator' => "\t" ),
	'variation_mode'  => 'all',
	'google_category' => true,
	'notes'           => __( 'Google Merchant Center feed for Shopping ads and free listings. Microsoft, Pinterest and Meta also read this file.', 'product-xml-feeds-for-woocommerce' ),
	'availability'    => array( 'in_stock' => 'in_stock', 'out_of_stock' => 'out_of_stock', 'backorder' => 'backorder', 'preorder' => 'preorder' ),
	'condition'       => array( 'new' => 'new', 'refurbished' => 'refurbished', 'used' => 'used' ),
	'gender'          => array( 'male' => 'male', 'female' => 'female', 'unisex' => 'unisex' ),
	'age_group'       => array( 'newborn' => 'newborn', 'infant' => 'infant', 'toddler' => 'toddler', 'kids' => 'kids', 'adult' => 'adult' ),
	'ai'              => array( 'title' => 'title', 'description' => 'description', 'structured' => true ),
	'fields'          => array(
		'id'                        => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id', array( 'max' => 50 ) ),
		'title'                     => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 150 ) ),
		'structured_title'          => call_user_func( $f, __( 'AI title', 'product-xml-feeds-for-woocommerce' ), false, 'basic', '', array(
			'children' => array(
				'digital_source_type' => call_user_func( $f, __( 'Source type', 'product-xml-feeds-for-woocommerce' ), true, 'basic', '', array( 'allowed' => array( 'default', 'trained_algorithmic_media' ) ) ),
				'content'             => call_user_func( $f, __( 'Text', 'product-xml-feeds-for-woocommerce' ), true, 'basic', '', array( 'max' => 150 ) ),
			),
		) ),
		'description'               => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'description', array( 'max' => 5000 ) ),
		'structured_description'    => call_user_func( $f, __( 'AI description', 'product-xml-feeds-for-woocommerce' ), false, 'basic', '', array(
			'children' => array(
				'digital_source_type' => call_user_func( $f, __( 'Source type', 'product-xml-feeds-for-woocommerce' ), true, 'basic', '', array( 'allowed' => array( 'default', 'trained_algorithmic_media' ) ) ),
				'content'             => call_user_func( $f, __( 'Text', 'product-xml-feeds-for-woocommerce' ), true, 'basic', '', array( 'max' => 5000 ) ),
			),
		) ),
		'link'                      => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link', array( 'max' => 2000 ) ),
		'image_link'                => call_user_func( $f, __( 'Main image', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'image_link', array( 'max' => 2000 ) ),
		'additional_image_link'     => call_user_func( $f, __( 'More images', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'additional_image_links', array( 'list' => true, 'list_max' => 10, 'list_sep' => ',' ) ),
		'availability'              => call_user_func( $f, __( 'Stock status', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability', array( 'allowed' => array( 'in_stock', 'out_of_stock', 'preorder', 'backorder' ) ) ),
		'availability_date'         => call_user_func( $f, __( 'Available from', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'price', '', array( 'when' => 'preorder' ) ),
		'price'                     => call_user_func( $f, __( 'Price', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'price', array( 'unit' => 'currency' ) ),
		'sale_price'                => call_user_func( $f, __( 'Sale price', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price', array( 'unit' => 'currency' ) ),
		'sale_price_effective_date' => call_user_func( $f, __( 'Sale dates', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price_effective_date', array( 'max' => 51 ) ),
		'condition'                 => call_user_func( $f, __( 'Condition', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'condition', array( 'allowed' => array( 'new', 'refurbished', 'used' ) ) ),
		'brand'                     => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'brand', array( 'max' => 70 ) ),
		'gtin'                      => call_user_func( $f, __( 'GTIN (EAN, UPC, ISBN)', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'gtin', array( 'max' => 14 ) ),
		'mpn'                       => call_user_func( $f, __( 'Manufacturer part number (MPN)', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'identifiers', 'mpn', array( 'when' => 'no_gtin', 'max' => 70 ) ),
		'identifier_exists'         => call_user_func( $f, __( 'Has a product code', 'product-xml-feeds-for-woocommerce' ), false, 'identifiers', 'identifier_exists', array( 'allowed' => array( 'yes', 'no' ) ) ),
		'item_group_id'             => call_user_func( $f, __( 'Variation group ID', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'item_group_id', array( 'when' => 'variation', 'max' => 50 ) ),
		'item_group_title'          => call_user_func( $f, __( 'Variation group title', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'parent_title', array( 'max' => 150 ) ),
		'color'                     => call_user_func( $f, __( 'Color', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'color', array( 'when' => 'apparel', 'max' => 100 ) ),
		'size'                      => call_user_func( $f, __( 'Size', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'size', array( 'when' => 'apparel', 'max' => 100 ) ),
		'gender'                    => call_user_func( $f, __( 'Gender', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'gender', array( 'when' => 'apparel', 'allowed' => array( 'male', 'female', 'unisex' ) ) ),
		'age_group'                 => call_user_func( $f, __( 'Age group', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'age_group', array( 'when' => 'apparel', 'allowed' => array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ) ) ),
		'material'                  => call_user_func( $f, __( 'Material', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'material', array( 'max' => 200 ) ),
		'pattern'                   => call_user_func( $f, __( 'Pattern', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'pattern', array( 'max' => 100 ) ),
		'google_product_category'   => call_user_func( $f, __( 'Google category', 'product-xml-feeds-for-woocommerce' ), false, 'category', 'google_product_category' ),
		'product_type'              => call_user_func( $f, __( 'Shop category path', 'product-xml-feeds-for-woocommerce' ), false, 'category', 'product_type', array( 'max' => 750 ) ),
		'shipping'                  => call_user_func( $f, __( 'Shipping', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'shipping', '', array(
			'when'     => 'no_account_shipping',
			'children' => array(
				'country' => call_user_func( $f, __( 'Country', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '' ),
				'service' => call_user_func( $f, __( 'Service', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '' ),
				'price'   => call_user_func( $f, __( 'Price', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '', array( 'unit' => 'currency' ) ),
			),
		) ),
		'shipping_weight'           => call_user_func( $f, __( 'Shipping weight', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', 'shipping_weight' ),
		'unit_pricing_measure'      => call_user_func( $f, __( 'Unit price measure', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', 'unit_pricing_measure', array( 'when' => 'unit_pricing' ) ),
		'unit_pricing_base_measure' => call_user_func( $f, __( 'Unit price base', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', '', array( 'when' => 'unit_pricing' ) ),
		'certification'             => call_user_func( $f, __( 'Energy label (EPREL)', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', '', array(
			'when'     => 'energy_label',
			'children' => array(
				'certification_authority' => call_user_func( $f, __( 'Authority', 'product-xml-feeds-for-woocommerce' ), true, 'extra', array( 'type' => 'static', 'value' => 'EC' ) ),
				'certification_name'      => call_user_func( $f, __( 'Name', 'product-xml-feeds-for-woocommerce' ), true, 'extra', array( 'type' => 'static', 'value' => 'EPREL' ) ),
				'certification_code'      => call_user_func( $f, __( 'Code', 'product-xml-feeds-for-woocommerce' ), true, 'extra', '' ),
			),
		) ),
		'custom_label_0'            => call_user_func( $f, __( 'Custom label 0', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_1'            => call_user_func( $f, __( 'Custom label 1', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_2'            => call_user_func( $f, __( 'Custom label 2', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_3'            => call_user_func( $f, __( 'Custom label 3', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
		'custom_label_4'            => call_user_func( $f, __( 'Custom label 4', 'product-xml-feeds-for-woocommerce' ), false, 'extra', '', array( 'max' => 100 ) ),
	),
);
