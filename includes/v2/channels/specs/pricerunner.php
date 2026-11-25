<?php
/**
 * Channel spec: PriceRunner (Klarna) CSV, research/platforms.md 5d.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'pricerunner',
	'label'           => __( 'PriceRunner', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Price comparison', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array( 'SE', 'DK', 'GB' ),
	'formats'         => array( 'csv' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(),
	'csv'             => array( 'separator' => ',' ),
	'variation_mode'  => 'all',
	'google_category' => false,
	'notes'           => __( 'CSV file for PriceRunner. Prices include VAT; keep the file link the same so products stay listed.', 'product-xml-feeds-for-woocommerce' ),
	'availability'    => array( 'in_stock' => 'In stock', 'out_of_stock' => 'Out of stock', 'backorder' => 'Backorder', 'preorder' => 'Preorder' ),
	'condition'       => array( 'new' => 'New', 'refurbished' => 'Refurbished', 'used' => 'Used' ),
	'gender'          => array(),
	'age_group'       => array(),
	'ai'              => array( 'title' => 'ProductName', 'description' => 'Description', 'structured' => false ),
	'fields'          => array(
		'SKU'                    => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id' ),
		'ProductName'            => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 150 ) ),
		'Price'                  => call_user_func( $f, __( 'Price with VAT', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'price_incl_tax', array( 'unit' => 'decimal' ) ),
		'SalePrice'              => call_user_func( $f, __( 'Sale price with VAT', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price_incl_tax', array( 'unit' => 'decimal' ) ),
		'SalePriceEffectiveDate' => call_user_func( $f, __( 'Sale dates', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price_effective_date' ),
		'Currency'               => call_user_func( $f, __( 'Currency', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'currency' ),
		'ShippingCost'           => call_user_func( $f, __( 'Shipping cost', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'StockStatus'            => call_user_func( $f, __( 'Stock status', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability', array( 'allowed' => array( 'In stock', 'Out of stock', 'Backorder', 'Preorder' ) ) ),
		'DeliveryTime'           => call_user_func( $f, __( 'Delivery time', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '' ),
		'Manufacturer'           => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'brand' ),
		'EAN'                    => call_user_func( $f, __( 'EAN or GTIN', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'gtin' ),
		'ManufacturerSKU'        => call_user_func( $f, __( 'Manufacturer part number', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'mpn' ),
		'ProductUrl'             => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link' ),
		'ImageUrl'               => call_user_func( $f, __( 'Main image', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'image_link' ),
		'Category'               => call_user_func( $f, __( 'Category path', 'product-xml-feeds-for-woocommerce' ), true, 'category', 'product_type' ),
		'Description'            => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'description', array( 'max_words' => 30 ) ),
		'Condition'              => call_user_func( $f, __( 'Condition', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'condition', array( 'allowed' => array( 'New', 'Used', 'Refurbished' ) ) ),
	),
);
