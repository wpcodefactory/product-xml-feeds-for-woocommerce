<?php
/**
 * Product XML Feeds for WooCommerce - Main Class
 *
 * @version 2.9.6
 * @since   1.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds' ) ) :

final class Alg_WC_Product_XML_Feeds {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = ALG_WC_PRODUCT_XML_FEEDS_VERSION;

	/**
	 * core.
	 *
	 * @version 2.9.2
	 * @since   2.9.2
	 */
	public $core;

	/**
	 * @var   Alg_WC_Product_XML_Feeds The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

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
	 * @version 2.9.6
	 * @since   1.0.0
	 *
	 * @access  public
	 */
	function __construct() {

		// Check for active WooCommerce plugin
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		// Load libs
		if ( is_admin() ) {
			require_once plugin_dir_path( ALG_WC_PRODUCT_XML_FEEDS_FILE ) . 'vendor/autoload.php';
		}

		// Set up localisation
		add_action( 'init', array( $this, 'localize' ) );

		// Declare compatibility with custom order tables for WooCommerce
		add_action( 'before_woocommerce_init', array( $this, 'wc_declare_compatibility' ) );

		// Pro
		if ( 'product-xml-feeds-for-woocommerce-pro.php' === basename( ALG_WC_PRODUCT_XML_FEEDS_FILE ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'pro/class-alg-wc-product-xml-feeds-pro.php';
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
	 * @version 2.9.6
	 * @since   2.9.1
	 */
	function localize() {
		load_plugin_textdomain(
			'product-xml-feeds-for-woocommerce',
			false,
			dirname( plugin_basename( ALG_WC_PRODUCT_XML_FEEDS_FILE ) ) . '/langs/'
		);
	}

	/**
	 * wc_declare_compatibility.
	 *
	 * @version 2.9.6
	 * @since   2.7.6
	 *
	 * @see     https://developer.woocommerce.com/docs/features/high-performance-order-storage/recipe-book/
	 */
	function wc_declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			$files = (
				defined( 'ALG_WC_PRODUCT_XML_FEEDS_FILE_FREE' ) ?
				array( ALG_WC_PRODUCT_XML_FEEDS_FILE, ALG_WC_PRODUCT_XML_FEEDS_FILE_FREE ) :
				array( ALG_WC_PRODUCT_XML_FEEDS_FILE )
			);
			foreach ( $files as $file ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					$file,
					true
				);
			}
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 *
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();

		$custom_links[] = '<a' .
			' href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_product_xml_feeds' ) . '"' .
		'>' .
			__( 'Settings', 'product-xml-feeds-for-woocommerce' ) .
		'</a>';

		if ( 'product-xml-feeds-for-woocommerce.php' === basename( ALG_WC_PRODUCT_XML_FEEDS_FILE ) ) {
			$custom_links[] = '<a' .
				' target="_blank"' .
				' style="font-weight: bold; color: green;"' .
				' href="https://wpfactory.com/item/product-xml-feeds-woocommerce/"' .
			'>' .
				__( 'Go Pro', 'product-xml-feeds-for-woocommerce' ) .
			'</a>';
		}

		return array_merge( $custom_links, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 */
	function includes() {

		// Shortcodes
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-alg-shortcodes.php';
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-alg-general-shortcodes.php';
		require_once plugin_dir_path( __FILE__ ) . 'shortcodes/class-alg-products-shortcodes.php';

		// Core
		$this->core = require_once plugin_dir_path( __FILE__ ) . 'class-alg-wc-product-xml-feeds-core.php';

	}

	/**
	 * admin.
	 *
	 * @version 2.9.6
	 * @since   1.4.0
	 */
	function admin() {

		// Action links
		add_filter(
			'plugin_action_links_' . plugin_basename( ALG_WC_PRODUCT_XML_FEEDS_FILE ),
			array( $this, 'action_links' )
		);

		// "Recommendations" page
		add_action( 'init', array( $this, 'add_cross_selling_library' ) );

		// WC Settings tab as WPFactory submenu item
		add_action( 'init', array( $this, 'move_wc_settings_tab_to_wpfactory_menu' ) );

		// Settings
		add_filter(
			'woocommerce_get_settings_pages',
			array( $this, 'add_woocommerce_settings_tab' )
		);

		// Version updated
		if ( get_option( 'alg_product_xml_feeds_version', '' ) !== $this->version ) {
			add_action( 'admin_init', array( $this, 'version_updated' ) );
		}

	}

	/**
	 * add_cross_selling_library.
	 *
	 * @version 2.9.6
	 * @since   2.9.0
	 */
	function add_cross_selling_library() {

		if ( ! class_exists( '\WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling' ) ) {
			return;
		}

		$cross_selling = new \WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling();
		$cross_selling->setup( array( 'plugin_file_path' => ALG_WC_PRODUCT_XML_FEEDS_FILE ) );
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

		// Update option
		update_option( 'alg_product_xml_feeds_version', $this->version );

		// Generate a security key for XML feed access if not already set
		if ( '' === get_option( 'alg_products_xml_feeds_security_key', '' ) ) {
			update_option(
				'alg_products_xml_feeds_security_key',
				wp_generate_password( 24, false )
			);
		}

	}

	/**
	 * Add Product XML Feeds settings tab to WooCommerce settings.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once plugin_dir_path( __FILE__ ) . 'settings/class-alg-wc-settings-product-xml-feeds.php';
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 *
	 * @return  string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( ALG_WC_PRODUCT_XML_FEEDS_FILE ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @version 2.9.6
	 * @since   1.0.0
	 *
	 * @return  string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( ALG_WC_PRODUCT_XML_FEEDS_FILE ) );
	}

}

endif;
