<?php
/**
 * Channel spec: custom template (the classic header, item and footer templates with the 3.1 shortcodes).
 *
 * Items are array( '_raw' => string ): the item template rendered for one product exactly as the classic generator
 * does. Header and footer: Alg_WC_PXF_Mapper::custom_part( 'header' | 'footer', $config ).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'              => 'custom',
	'label'           => __( 'Custom template', 'product-xml-feeds-for-woocommerce' ),
	'group'           => __( 'Other', 'product-xml-feeds-for-woocommerce' ),
	'countries'       => array(),
	'formats'         => array( 'xml', 'txt', 'csv' ),
	'encoding'        => 'UTF-8',
	'xml'             => array(),
	'variation_mode'  => 'parent',
	'google_category' => false,
	'raw'             => true,
	'notes'           => __( 'Write your own header, item and footer with the same shortcodes as the classic feeds, for any site that is not in the list.', 'product-xml-feeds-for-woocommerce' ),
	'availability'    => array(),
	'fields'          => array(),
);
