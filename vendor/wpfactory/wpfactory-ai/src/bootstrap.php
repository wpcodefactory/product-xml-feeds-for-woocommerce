<?php
/**
 * Loaded once, from the newest bundled copy only. Registers this copy's class loader and starts the client.
 *
 * @package WPFactory\AI
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WPFactory\\AI\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/class-' . strtolower( str_replace( array( '\\', '_' ), '-', substr( $class_name, strlen( $prefix ) ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

\WPFactory\AI\Client::instance()->boot();
