<?php
/**
 * Channel spec: OpenAI product feed (ChatGPT shopping), research/platforms.md 2a.
 *
 * JSONL with OpenAI's own field names. Empty fields are left out (no placeholders). Checkout stays off.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f = array( 'Alg_WC_PXF_Channels', 'field' );

return array(
	'id'              => 'openai',
	'label'           => __( 'ChatGPT shopping (OpenAI)', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'AI shopping', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array( 'US' ),
	'formats'         => array( 'jsonl', 'csv', 'tsv' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(),
	'csv'             => array( 'separator' => ',' ),
	'variation_mode'  => 'all',
	'google_category' => false,
	'notes'           => __( 'US only, application required: OpenAI must approve your business first, and a listing is not guaranteed.', 'product-xml-feeds-for-woocommerce' ),
	'availability'    => array( 'in_stock' => 'in_stock', 'out_of_stock' => 'out_of_stock', 'backorder' => 'backorder', 'preorder' => 'pre_order' ),
	'condition'       => array( 'new' => 'new', 'refurbished' => 'refurbished', 'used' => 'used' ),
	'gender'          => array( 'male' => 'male', 'female' => 'female', 'unisex' => 'unisex' ),
	'age_group'       => array( 'newborn' => 'newborn', 'infant' => 'infant', 'toddler' => 'toddler', 'kids' => 'kids', 'adult' => 'adult' ),
	'ai'              => array( 'title' => 'title', 'description' => 'description', 'structured' => false ),
	'fields'          => array(
		'item_id'                => call_user_func( $f, __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'id' ),
		'title'                  => call_user_func( $f, __( 'Title', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'title', array( 'max' => 150 ) ),
		'description'            => call_user_func( $f, __( 'Description', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'description', array( 'max' => 5000 ) ),
		'url'                    => call_user_func( $f, __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'link' ),
		'brand'                  => call_user_func( $f, __( 'Brand', 'product-xml-feeds-for-woocommerce' ), true, 'identifiers', 'brand' ),
		'seller_name'            => call_user_func( $f, __( 'Shop name', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'site_name' ),
		'image_url'              => call_user_func( $f, __( 'Main image', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'image_link' ),
		'availability'           => call_user_func( $f, __( 'Stock status', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'availability', array( 'allowed' => array( 'in_stock', 'out_of_stock', 'pre_order', 'backorder' ) ) ),
		'price'                  => call_user_func( $f, __( 'Price', 'product-xml-feeds-for-woocommerce' ), true, 'price', 'price', array( 'unit' => 'currency' ) ),
		'target_countries'       => call_user_func( $f, __( 'Target countries', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'countries', array( 'list' => true ) ),
		'store_country'          => call_user_func( $f, __( 'Shop country', 'product-xml-feeds-for-woocommerce' ), true, 'basic', 'country' ),
		'sale_price'             => call_user_func( $f, __( 'Sale price', 'product-xml-feeds-for-woocommerce' ), false, 'price', 'sale_price', array( 'unit' => 'currency' ) ),
		'group_id'               => call_user_func( $f, __( 'Variation group ID', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'item_group_id', array( 'when' => 'variation' ) ),
		'listing_has_variations' => call_user_func( $f, __( 'Has variations', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'is_variation', array( 'when' => 'variation', 'json' => 'bool' ) ),
		'variant_dict'           => call_user_func( $f, __( 'Variation options', 'product-xml-feeds-for-woocommerce' ), 'conditional', 'variants', 'variant_attributes', array( 'when' => 'variation' ) ),
		'gtin'                   => call_user_func( $f, __( 'GTIN (EAN, UPC)', 'product-xml-feeds-for-woocommerce' ), false, 'identifiers', 'gtin', array( 'max' => 14 ) ),
		'mpn'                    => call_user_func( $f, __( 'Manufacturer part number (MPN)', 'product-xml-feeds-for-woocommerce' ), false, 'identifiers', 'mpn' ),
		'condition'              => call_user_func( $f, __( 'Condition', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'condition', array( 'allowed' => array( 'new', 'refurbished', 'used' ) ) ),
		'product_category'       => call_user_func( $f, __( 'Category path', 'product-xml-feeds-for-woocommerce' ), false, 'category', 'product_type' ),
		'color'                  => call_user_func( $f, __( 'Color', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'color' ),
		'size'                   => call_user_func( $f, __( 'Size', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'size' ),
		'material'               => call_user_func( $f, __( 'Material', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'material' ),
		'gender'                 => call_user_func( $f, __( 'Gender', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'gender', array( 'allowed' => array( 'male', 'female', 'unisex' ) ) ),
		'age_group'              => call_user_func( $f, __( 'Age group', 'product-xml-feeds-for-woocommerce' ), false, 'variants', 'age_group', array( 'allowed' => array( 'newborn', 'infant', 'toddler', 'kids', 'adult' ) ) ),
		'weight'                 => call_user_func( $f, __( 'Weight', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', 'weight_value' ),
		'item_weight_unit'       => call_user_func( $f, __( 'Weight unit', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', 'weight_unit', array( 'allowed' => array( 'g', 'kg', 'oz', 'lb' ) ) ),
		'additional_image_urls'  => call_user_func( $f, __( 'More images', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'additional_image_links', array( 'list' => true, 'list_sep' => ',' ) ),
		'shipping_price'         => call_user_func( $f, __( 'Shipping price', 'product-xml-feeds-for-woocommerce' ), false, 'shipping', '', array( 'unit' => 'currency' ) ),
		'is_eligible_search'     => call_user_func( $f, __( 'Show in ChatGPT search', 'product-xml-feeds-for-woocommerce' ), false, 'extra', array( 'type' => 'static', 'value' => 'true' ), array( 'json' => 'bool', 'allowed' => array( 'true', 'false' ) ) ),
		'is_eligible_checkout'   => call_user_func( $f, __( 'Checkout in ChatGPT', 'product-xml-feeds-for-woocommerce' ), false, 'extra', array( 'type' => 'static', 'value' => 'false' ), array( 'json' => 'bool', 'allowed' => array( 'true', 'false' ) ) ),
	),
);
