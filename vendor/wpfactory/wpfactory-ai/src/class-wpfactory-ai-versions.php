<?php
/**
 * Picks the newest bundled copy of the WPFactory AI library.
 *
 * Kept tiny on purpose: whichever plugin defines this class first, every copy uses it, so its behavior
 * must never change between library versions.
 *
 * @package WPFactory\AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_AI_Versions', false ) ) {

	/**
	 * Version registry.
	 */
	final class WPFactory_AI_Versions {

		/**
		 * Bootstrap file of each registered copy, by version.
		 *
		 * @var array<string,string>
		 */
		private static $files = array();

		/**
		 * The version that loaded, or '' before loading.
		 *
		 * @var string
		 */
		private static $loaded = '';

		/**
		 * Registers one bundled copy.
		 *
		 * @param string $version Library version.
		 * @param string $file    That copy's bootstrap file.
		 */
		public static function register( $version, $file ) {
			self::$files[ (string) $version ] = (string) $file;
			if ( 1 === count( self::$files ) ) {
				// After every plugin (and every Pro add-on loading on plugins_loaded) has registered its copy.
				add_action( 'plugins_loaded', array( __CLASS__, 'load' ), PHP_INT_MAX );
			}
		}

		/**
		 * Loads the newest registered copy. Safe to call more than once.
		 */
		public static function load() {
			if ( '' !== self::$loaded || ! self::$files ) {
				return;
			}
			uksort( self::$files, 'version_compare' );
			end( self::$files );
			self::$loaded = (string) key( self::$files );
			require_once self::$files[ self::$loaded ];
		}

		/**
		 * The version that loaded.
		 *
		 * @return string
		 */
		public static function loaded() {
			return self::$loaded;
		}

		/**
		 * Every registered copy.
		 *
		 * @return array<string,string>
		 */
		public static function registered() {
			return self::$files;
		}
	}
}
