<?php
/**
 * Product XML Feeds for WooCommerce - General Section Settings
 *
 * @version 1.7.2
 * @since   1.0.0
 * @author  WPWhale
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds_Settings_General' ) ) :

class Alg_WC_Product_XML_Feeds_Settings_General extends Alg_WC_Product_XML_Feeds_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.1.0
	 * @since   1.0.0
	 */
	function __construct() {
		$this->id   = '';
		$this->desc = __( 'General', 'product-xml-feeds-for-woocommerce' );
		parent::__construct();
	}

	/**
	 * get_settings.
	 *
	 * @version 1.7.2
	 * @since   1.0.0
	 * @todo    [dev] better description for "Block size for products query"
	 * @todo    [dev] (maybe) create all files at once (manually and synchronize update)
	 */
	function get_settings() {
		$settings = array(
			array(
				'title'    => __( 'Product XML Feeds Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_wc_product_xml_feeds_options',
			),
			array(
				'title'    => __( 'WooCommerce Product XML Feeds', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable plugin', 'product-xml-feeds-for-woocommerce' ) . '</strong>',
				'desc_tip' => __( 'Product XML Feeds for WooCommerce.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_wc_product_xml_feeds_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Total XML files (feeds)', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_total_files',
				'default'  => 1,
				'type'     => 'number',
				'desc'     => apply_filters( 'alg_wc_product_xml_feeds_settings', sprintf(
					'<p style="padding: 10px; background-color: white;">Get <a href="%s" target="_blank">Product XML Feeds for WooCommerce Pro</a> to add more than one XML feed.</p>',
						'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ), 'save_button' ),
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'step' => '1', 'min' => '1', 'max' => '1' ), 'custom_attributes' ),
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_wc_product_xml_feeds_options',
			),
			array(
				'title'    => __( 'Advanced Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_wc_product_xml_feeds_advanced_options',
			),
			array(
				'title'    => __( 'Block size for products query', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Sets the number of products feed file creation script should process in single loop.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_query_block_size',
				'default'  => 512,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 1 ),
			),
			array(
				'title'    => __( 'PHP memory limit', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Sets PHP memory limit.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Ignored if set to zero.', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => __( 'megabytes', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_php_memory_limit',
				'default'  => 0,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 0 ),
			),
			array(
				'title'    => __( 'PHP time limit', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Sets the number of seconds feed file creation script is allowed to run.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Set to zero for no time limit.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					sprintf( __( 'Ignored if set to -1 (and server\'s default time limit of %s seconds is used).', 'product-xml-feeds-for-woocommerce' ),
						ini_get( 'max_execution_time' ) ),
				'desc'     => '<p>' . sprintf( __( 'Check %s function documentation for more info.', 'product-xml-feeds-for-woocommerce' ),
					'<a target="_blank" href="http://php.net/manual/en/function.set-time-limit.php"><code>set_time_limit()</code></a>' ) . '</p>',
				'id'       => 'alg_products_xml_php_time_limit',
				'default'  => -1,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => -1 ),
			),
			array(
				'title'    => __( '"Raw" input', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Affects "Template Options".', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					sprintf( __( 'If disabled, use %s instead of %s accordingly.', 'product-xml-feeds-for-woocommerce' ),
						'<code>{</code> and <code>}</code>', '<code><</code> and <code>></code>' ),
				'desc'     => __( 'Enable', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_raw_input',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_wc_product_xml_feeds_advanced_options',
			),
		);
		return $settings;
	}

}

endif;

return new Alg_WC_Product_XML_Feeds_Settings_General();
