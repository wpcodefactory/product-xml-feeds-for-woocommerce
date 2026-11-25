<?php
/**
 * Product XML Feeds for WooCommerce - v2 loader (channel feeds: presets, background engine, new admin).
 *
 * Classic feeds (options alg_products_xml_*_N, the 3.1.x generator) are not touched by anything in includes/v2.
 * Each module has its own load.php and is loaded only when present.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF' ) ) :

final class Alg_WC_PXF {

	/**
	 * Modules, in load order.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const MODULES = array( 'classic', 'channels', 'engine', 'validator', 'ai', 'admin' );

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		foreach ( self::MODULES as $module ) {
			$file = __DIR__ . '/' . $module . '/load.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
		do_action( 'alg_wc_pxf_loaded' );
	}

	/**
	 * is_pro.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function is_pro() {
		return (bool) apply_filters( 'alg_wc_pxf_is_pro', class_exists( 'Alg_WC_Product_XML_Feeds_Pro' ) );
	}

	/**
	 * dir.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string Path of includes/v2 with a trailing slash.
	 */
	static function dir() {
		return trailingslashit( __DIR__ );
	}

	/**
	 * url.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $path Path relative to the plugin root.
	 * @return  string
	 */
	static function url( $path = '' ) {
		return plugins_url( ltrim( $path, '/' ), ALG_WC_PRODUCT_XML_FEEDS_FILE );
	}

	/**
	 * asset_version.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $path Path relative to the plugin root.
	 * @return  string
	 */
	static function asset_version( $path ) {
		$file = plugin_dir_path( ALG_WC_PRODUCT_XML_FEEDS_FILE ) . ltrim( $path, '/' );
		return ALG_WC_PRODUCT_XML_FEEDS_VERSION . ( file_exists( $file ) ? '.' . filemtime( $file ) : '' );
	}

}

endif;
