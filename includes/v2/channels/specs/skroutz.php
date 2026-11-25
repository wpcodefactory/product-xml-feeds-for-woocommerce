<?php
/**
 * Channel spec: Skroutz XML (developer.skroutz.gr/products/xml_feed), research/platforms.md 5a.
 *
 * Own structure: <mywebstore><created_at/><products><product>. Each color of a variable product is its own product
 * (id "<parent ID>-<color term ID>"); its sizes are nested in <variations><variation>. See Alg_WC_PXF_Mapper::items().
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'skroutz',
	'label'           => __( 'Skroutz', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Price comparison', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array( 'GR', 'CY' ),
	'formats'         => array( 'xml' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(
		'root'          => 'mywebstore',
		'root_attrs'    => array(),
		'wrap'          => 'products',
		'item'          => 'product',
		'prefix'        => '',
		'head'          => array( 'created_at' => '{datetime}' ),
		'head_position' => 'root',
		'cdata'         => array( 'name', 'description', 'category', 'manufacturer', 'link', 'image' ),
	),
	'csv'             => array( 'separator' => ',' ),
	'variation_mode'  => 'parent',
	'split'           => array( 'by' => 'color', 'nest' => 'size' ),
	'google_category' => false,
	'notes'           => __( 'XML file for Skroutz. Each color is its own product with its sizes inside; prices include VAT.', 'product-xml-feeds-for-woocommerce' ),
	// Skroutz has no word for out of stock: such products should be left out (see default_filters).
	'availability'    => array( 'in_stock' => 'In stock', 'out_of_stock' => '', 'backorder' => 'Available from 4 to 6 days', 'preorder' => 'Available up to 12 days' ),
	'condition'       => array(),
	'gender'          => array(),
	'age_group'       => array(),
	'ai'              => array( 'title' => 'name', 'description' => 'description', 'structured' => false ),
	'default_filters' => array( 'stock' => array( 'instock', 'onbackorder' ) ),
	'fields'          => array(
		'id'                  => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id', array( 'max' => 200 ) ),
		'name'                => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 300 ) ),
		'link'                => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link', array( 'max' => 1000 ) ),
		'image'               => call_user_func( $f, __( 'Main image', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'image_link', array( 'max' => 400 ) ),
		'additional_imageurl' => call_user_func( $f, __( 'More images', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'basic', 'additional_image_links', array( 'when' => 'apparel', 'list' => true, 'list_max' => 14, 'max' => 400 ) ),
		'category'            => call_user_func( $f, __( 'Category path', 'product-xml-feeds-for-woocommerce' ), true, 'category', 'product_type', array( 'max' => 250 ) ),
		'price'               => call_user_func( $f, __( 'Price with VAT', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'active_price_incl_tax', array( 'unit' => 'decimal' ) ),
		'vat'                 => call_user_func( $f, __( 'VAT rate', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'vat' ),
		'availability'        => call_user_func( $f, __( 'Delivery time', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability', array( 'max' => 60, 'allowed' => array( 'In stock', 'Available from 1 to 3 days', 'Available from 4 to 6 days', 'Available up to 12 days' ) ) ),
		'manufacturer'        => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'brand', array( 'max' => 100 ) ),
		'mpn'                 => call_user_func( $f, __( 'Manufacturer part number (MPN)', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'mpn', array( 'max' => 80 ) ),
		'ean'                 => call_user_func( $f, __( 'EAN', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'gtin', array( 'max' => 13 ) ),
		'description'         => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'description', array( 'max' => 10000 ) ),
		'quantity'            => call_user_func( $f, __( 'Quantity', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'quantity', array( 'unit' => 'integer' ) ),
		'size'                => call_user_func( $f, __( 'Sizes', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'size', array( 'when' => 'apparel', 'max' => 500 ) ),
		'color'               => call_user_func( $f, __( 'Color', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'color', array( 'when' => 'apparel', 'max' => 100 ) ),
		'weight'              => call_user_func( $f, __( 'Weight (grams)', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'shipping', 'weight_grams', array( 'when' => 'weight_shipping' ) ),
		'shipping'            => call_user_func( $f, __( 'Shipping cost', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'variations'          => call_user_func( $f, __( 'Sizes in stock', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'variants', '', array(
			'list'      => true,
			'list_item' => 'variation',
			'children'  => array(
				'variationid'  => call_user_func( $f, __( 'Variation ID', 'product-xml-feeds-for-woocommerce' ), true, 'variants', 'id', array( 'max' => 200 ) ),
				'availability' => call_user_func( $f, __( 'Delivery time', 'product-xml-feeds-for-woocommerce' ), true, 'variants', 'availability' ),
				'size'         => call_user_func( $f, __( 'Size', 'product-xml-feeds-for-woocommerce' ), true, 'variants', 'size', array( 'max' => 64 ) ),
				'quantity'     => call_user_func( $f, __( 'Quantity', 'product-xml-feeds-for-woocommerce' ), true, 'variants', 'quantity', array( 'unit' => 'integer' ) ),
				'price'        => call_user_func( $f, __( 'Price with VAT', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'active_price_incl_tax', array( 'unit' => 'decimal' ) ),
				'link'         => call_user_func( $f, __( 'Link', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'link' ),
				'mpn'          => call_user_func( $f, __( 'MPN', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'mpn' ),
				'ean'          => call_user_func( $f, __( 'EAN', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'gtin' ),
			),
		) ),
	),
);
