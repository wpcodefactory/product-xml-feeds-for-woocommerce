<?php
/*
Plugin Name: Product XML Feeds for WooCommerce
Plugin URI: https://wpfactory.com/item/product-xml-feeds-woocommerce/
Description: Create your own XML files using tens of preconfigured shortcodes for you on your WooCommerce store.
Version: 2.9.5
Author: WPFactory
Author URI: https://wpfactory.com
Text Domain: product-xml-feeds-for-woocommerce
Domain Path: /langs
WC tested up to: 9.9
Requires Plugins: woocommerce
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

defined( 'ABSPATH' ) || exit;

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array(
		$plugin,
		apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) )
	) &&
	! (
		is_multisite() &&
		array_key_exists(
			$plugin,
			get_site_option( 'active_sitewide_plugins', array() )
		)
	)
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

defined( 'ALG_WC_PRODUCT_XML_FEEDS_VERSION' ) || define( 'ALG_WC_PRODUCT_XML_FEEDS_VERSION', '2.9.5' );

defined( 'ALG_WC_PRODUCT_XML_FEEDS_FILE' ) || define( 'ALG_WC_PRODUCT_XML_FEEDS_FILE', __FILE__ );

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds' ) ) :

/**
 * Main Alg_WC_Product_XML_Feeds Class
 *
 * @version 2.9.5
 * @since   1.0.0
 *
 * @class   Alg_WC_Product_XML_Feeds
 */
final class Alg_WC_Product_XML_Feeds {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = ALG_WC_PRODUCT_XML_FEEDS_VERSION;

	/**
	 * @var   Alg_WC_Product_XML_Feeds The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * core.
	 *
	 * @version 2.9.2
	 * @since   2.9.2
	 */
	public $core;

	/**
	 * Main Alg_WC_Product_XML_Feeds Instance.
	 *
	 * Ensures only one instance of Alg_WC_Product_XML_Feeds is loaded or can be loaded.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 *
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
	 * @version 2.9.1
	 * @since   1.0.0
	 *
	 * @access  public
	 */
	function __construct() {

		// Load libs
		if ( is_admin() ) {
			require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
		}

		// Set up localisation
		add_action( 'init', array( $this, 'localize' ) );

		// Declare compatibility with custom order tables for WooCommerce
		add_action( 'before_woocommerce_init', array( $this, 'wc_declare_compatibility' ) );

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
	 * localize.
	 *
	 * @version 2.9.1
	 * @since   2.9.1
	 */
	function localize() {
		load_plugin_textdomain(
			'product-xml-feeds-for-woocommerce',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/langs/'
		);
	}

	/**
	 * wc_declare_compatibility.
	 *
	 * @version 2.9.0
	 * @since   2.7.6
	 *
	 * @see     https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
	 */
	function wc_declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 2.9.0
	 * @since   1.0.0
	 *
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();

		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_product_xml_feeds' ) . '">' .
			__( 'Settings', 'product-xml-feeds-for-woocommerce' ) .
		'</a>';

		if ( 'product-xml-feeds-for-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a href="https://wpfactory.com/item/product-xml-feeds-woocommerce/">' .
				__( 'Unlock All', 'product-xml-feeds-for-woocommerce' ) .
			'</a>';
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
	 * @version 2.9.3
	 * @since   1.4.0
	 */
	function admin() {

		// Action links
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );

		// "Recommendations" page
		add_action( 'init', array( $this, 'add_cross_selling_library' ) );

		// WC Settings tab as WPFactory submenu item
		add_action( 'init', array( $this, 'move_wc_settings_tab_to_wpfactory_menu' ) );

		// Settings
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );

		// Version updated
		if ( get_option( 'alg_product_xml_feeds_version', '' ) !== $this->version ) {
			add_action( 'admin_init', array( $this, 'version_updated' ) );
		}

	}

	/**
	 * add_cross_selling_library.
	 *
	 * @version 2.9.0
	 * @since   2.9.0
	 */
	function add_cross_selling_library() {

		if ( ! class_exists( '\WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling' ) ) {
			return;
		}

		$cross_selling = new \WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling();
		$cross_selling->setup( array( 'plugin_file_path' => __FILE__ ) );
		$cross_selling->init();

	}

	/**
	 * move_wc_settings_tab_to_wpfactory_menu.
	 *
	 * @version 2.9.5
	 * @since   2.9.0
	 */
	function move_wc_settings_tab_to_wpfactory_menu() {

		if ( ! class_exists( '\WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu' ) ) {
			return;
		}

		$wpfactory_admin_menu = \WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu::get_instance();

		if ( ! method_exists( $wpfactory_admin_menu, 'move_wc_settings_tab_to_wpfactory_menu' ) ) {
			return;
		}

		$wpfactory_admin_menu->move_wc_settings_tab_to_wpfactory_menu( array(
			'wc_settings_tab_id' => 'alg_wc_product_xml_feeds',
			'menu_title'         => __( 'Product XML Feeds', 'product-xml-feeds-for-woocommerce' ),
			'page_title'         => __( 'Product XML Feed Manager for WooCommerce', 'product-xml-feeds-for-woocommerce' ),
			'plugin_icon'        => array(
				'get_url_method'    => 'wporg_plugins_api',
				'wporg_plugin_slug' => 'product-xml-feeds-for-woocommerce',
			),
		) );

	}

	/**
	 * version_updated.
	 *
	 * @version 2.9.3
	 * @since   1.3.0
	 */
	function version_updated() {
		update_option( 'alg_product_xml_feeds_version', $this->version );

		// Generate a security key for XML feed access if not already set
		if ( '' === get_option( 'alg_products_xml_feeds_security_key', '' ) ) {
			update_option( 'alg_products_xml_feeds_security_key', wp_generate_password( 24, false ) );
		}
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

/**
 * alg_wc_product_xml_feeds.
 *
 * @version 2.9.0
 */
add_action( 'plugins_loaded', 'alg_wc_product_xml_feeds' );
