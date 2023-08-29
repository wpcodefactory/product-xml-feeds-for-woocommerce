<?php
/*
Plugin Name: Product XML Feeds for WooCommerce
Plugin URI: https://wpfactory.com/item/product-xml-feeds-woocommerce/
Description: Create your own XML files using tens of preconfigured shortcodes for you on your WooCommerce store
Version: 2.7.7
Author: WPFactory
Author URI: https://wpfactory.com
Text Domain: product-xml-feeds-for-woocommerce
Domain Path: /langs
Copyright: © 2023 WPFactory
WC tested up to: 8.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'product-xml-feeds-for-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return
	$plugin = 'product-xml-feeds-for-woocommerce-pro/product-xml-feeds-for-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds' ) ) :

/**
 * Main Alg_WC_Product_XML_Feeds Class
 *
 * @class   Alg_WC_Product_XML_Feeds
 * @version 1.7.1
 * @since   1.0.0
 */
final class Alg_WC_Product_XML_Feeds {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = '2.7.5';

	/**
	 * @var   Alg_WC_Product_XML_Feeds The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_WC_Product_XML_Feeds Instance
	 *
	 * Ensures only one instance of Alg_WC_Product_XML_Feeds is loaded or can be loaded.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @static
	 * @return  Alg_WC_Product_XML_Feeds - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_WC_Product_XML_Feeds Constructor.
	 *
	 * @version 1.6.0
	 * @since   1.0.0
	 * @access  public
	 */
	function __construct() {

		// Set up localisation
		load_plugin_textdomain( 'product-xml-feeds-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

		// Pro
		if ( 'product-xml-feeds-for-woocommerce-pro.php' === basename( __FILE__ ) ) {
			require_once( 'includes/pro/class-alg-wc-product-xml-feeds-pro.php' );
		}

		// Include required files
		$this->includes();

		// Admin
		if ( is_admin() ) {
			$this->admin();
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_product_xml_feeds' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'product-xml-feeds-for-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a href="https://wpfactory.com/item/product-xml-feeds-woocommerce/">' . __( 'Unlock All', 'product-xml-feeds-for-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function includes() {
		// Shortcodes
		require_once( 'includes/shortcodes/class-alg-shortcodes.php' );
		require_once( 'includes/shortcodes/class-alg-general-shortcodes.php' );
		require_once( 'includes/shortcodes/class-alg-products-shortcodes.php' );
		// Core
		$this->core = require_once( 'includes/class-alg-wc-product-xml-feeds-core.php' );
	}

	/**
	 * admin.
	 *
	 * @version 1.7.1
	 * @since   1.4.0
	 */
	function admin() {
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		// Settings
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
		require_once( 'includes/settings/class-alg-wc-product-xml-feeds-settings-section.php' );
		$this->settings = array();
		$this->settings['general'] = require_once( 'includes/settings/class-alg-wc-product-xml-feeds-settings-general.php' );
		require_once( 'includes/settings/class-alg-wc-product-xml-feeds-settings-feed.php' );
		$total_number = apply_filters( 'alg_wc_product_xml_feeds_values', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$this->settings[ 'feed_' . $i ] = new Alg_WC_Product_XML_Feeds_Settings_Feed( $i );
		}
		// Version updated
		if ( get_option( 'alg_product_xml_feeds_version', '' ) !== $this->version ) {
			add_action( 'admin_init', array( $this, 'version_updated' ) );
		}
	}

	/**
	 * version_updated.
	 *
	 * @version 1.4.2
	 * @since   1.3.0
	 */
	function version_updated() {
		update_option( 'alg_product_xml_feeds_version', $this->version );
	}

	/**
	 * Add Product XML Feeds settings tab to WooCommerce settings.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-alg-wc-settings-product-xml-feeds.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

}

endif;

if ( ! function_exists( 'alg_wc_product_xml_feeds' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Product_XML_Feeds to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Product_XML_Feeds
	 */
	function alg_wc_product_xml_feeds() {
		return Alg_WC_Product_XML_Feeds::instance();
	}
}

alg_wc_product_xml_feeds();

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
