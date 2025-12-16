<?php
/*
Plugin Name: Product XML Feeds for WooCommerce
Plugin URI: https://wpfactory.com/item/product-xml-feeds-woocommerce/
Description: Create your own XML files using tens of preconfigured shortcodes for you on your WooCommerce store.
Version: 3.0.0
Author: WPFactory
Author URI: https://wpfactory.com
Requires at least: 4.4
Text Domain: product-xml-feeds-for-woocommerce
Domain Path: /langs
WC tested up to: 10.4
Requires Plugins: woocommerce
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

defined( 'ABSPATH' ) || exit;

if ( 'product-xml-feeds-for-woocommerce.php' === basename( __FILE__ ) ) {
	/**
	 * Check if Pro plugin version is activated.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 */
	$plugin = 'product-xml-feeds-for-woocommerce-pro/product-xml-feeds-for-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		(
			is_multisite() &&
			array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) )
		)
	) {
		defined( 'ALG_WC_PRODUCT_XML_FEEDS_FILE_FREE' ) || define( 'ALG_WC_PRODUCT_XML_FEEDS_FILE_FREE', __FILE__ );
		return;
	}
}

defined( 'ALG_WC_PRODUCT_XML_FEEDS_VERSION' ) || define( 'ALG_WC_PRODUCT_XML_FEEDS_VERSION', '3.0.0' );

defined( 'ALG_WC_PRODUCT_XML_FEEDS_FILE' ) || define( 'ALG_WC_PRODUCT_XML_FEEDS_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-alg-wc-product-xml-feeds.php';

if ( ! function_exists( 'alg_wc_product_xml_feeds' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Product_XML_Feeds to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 *
	 * @return  Alg_WC_Product_XML_Feeds
	 */
	function alg_wc_product_xml_feeds() {
		return Alg_WC_Product_XML_Feeds::instance();
	}
}

/**
 * alg_wc_product_xml_feeds.
 *
 * @version 2.9.0
 */
add_action( 'plugins_loaded', 'alg_wc_product_xml_feeds' );
