<?php
/**
 * Channel spec: idealo CSV, research/platforms.md 5c (idealo CSV importer, change log 2026-09-28).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'idealo',
	'label'           => __( 'idealo', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Price comparison', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array( 'DE', 'AT', 'FR', 'IT', 'ES', 'GB' ),
	'formats'         => array( 'csv' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(),
	'csv'             => array( 'separator' => ',' ),
	'variation_mode'  => 'all',
	'google_category' => false,
	'notes'           => __( 'CSV file for idealo. Prices include VAT; set at least one delivery cost column in the field mapping.', 'product-xml-feeds-for-woocommerce' ),
	// Delivery time text shown on idealo. Change the words in the field mapping to your own dispatch times.
	'availability'    => array( 'in_stock' => '1-3 working days', 'out_of_stock' => 'not in stock', 'backorder' => '4-6 working days', 'preorder' => 'pre-order' ),
	'condition'       => array( 'new' => 'NEW', 'refurbished' => 'REFURBISHED', 'used' => 'USED' ),
	'gender'          => array(),
	'age_group'       => array(),
	'ai'              => array( 'title' => 'title', 'description' => 'description', 'structured' => false ),
	'default_filters' => array( 'stock' => array( 'instock', 'onbackorder' ) ),
	'fields'          => array(
		'sku'                   => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id' ),
		'title'                 => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 255 ) ),
		'url'                   => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link' ),
		'price'                 => call_user_func( $f, __( 'Price with VAT', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'active_price_incl_tax', array( 'unit' => 'decimal' ) ),
		'delivery'              => call_user_func( $f, __( 'Delivery time', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability' ),
		'packagingUnit'         => call_user_func( $f, __( 'Pack size', 'product-xml-feeds-for-woocommerce' ), true, 'basic', array( 'type' => 'static', 'value' => '1' ), array( 'unit' => 'integer' ) ),
		'deliveryCosts_dhl'     => call_user_func( $f, __( 'Delivery cost DHL', 'product-xml-feeds-for-woocommerce' ), true, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'deliveryCosts_dpd'     => call_user_func( $f, __( 'Delivery cost DPD', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'deliveryCosts_gls'     => call_user_func( $f, __( 'Delivery cost GLS', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'deliveryCosts_hermes'  => call_user_func( $f, __( 'Delivery cost Hermes', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'deliveryCosts_ups'     => call_user_func( $f, __( 'Delivery cost UPS', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'paymentCosts_paypal'   => call_user_func( $f, __( 'Payment cost PayPal', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'decimal' ) ),
		'brand'                 => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'brand' ),
		'eans'                  => call_user_func( $f, __( 'EAN', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'gtin' ),
		'hans'                  => call_user_func( $f, __( 'Manufacturer part number', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'identifiers', 'mpn' ),
		'categoryPath'          => call_user_func( $f, __( 'Category path', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'category', 'product_type' ),
		'description'           => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'basic', 'description', array( 'max' => 1000 ) ),
		'imageUrls'             => call_user_func( $f, __( 'Images', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'basic', 'images', array( 'list' => true, 'list_sep' => ';' ) ),
		'size'                  => call_user_func( $f, __( 'Size', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'variants', 'size' ),
		'colour'                => call_user_func( $f, __( 'Color', 'product-xml-feeds-for-woocommerce' ), 'recommended', 'variants', 'color' ),
		'basePrice'             => call_user_func( $f, __( 'Base price (per kg, l or m)', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'price', '', array( 'when' => 'unit_pricing' ) ),
		'formerPrice'           => call_user_func( $f, __( 'Former price', 'product-xml-feeds-for-woocommerce' ), false, 'price', '', array( 'unit' => 'decimal' ) ),
		'conditionType'         => call_user_func( $f, __( 'Condition', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'condition', array( 'allowed' => array( 'NEW', 'AS_NEW', 'REFURBISHED', 'USED' ) ) ),
		'EEC_efficiencyClass'   => call_user_func( $f, __( 'Energy class', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', '', array( 'when' => 'energy_label' ) ),
		'eec_labelUrl'          => call_user_func( $f, __( 'Energy label link', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', '', array( 'when' => 'energy_label' ) ),
		'eec_dataSheetUrl'      => call_user_func( $f, __( 'Product data sheet link', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'extra', '', array( 'when' => 'energy_label' ) ),
		'deliveryComment'       => call_user_func( $f, __( 'Delivery note', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'max' => 150 ) ),
	),
);
