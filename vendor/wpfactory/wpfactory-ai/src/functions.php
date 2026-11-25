<?php
/**
 * The one global function. Like the version registry, whichever copy defines it first serves every copy,
 * so it never changes.
 *
 * @package WPFactory\AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wpfactory_ai' ) ) {
	/**
	 * The WPFactory AI client from the newest bundled copy. Call it on init or later.
	 *
	 * @return \WPFactory\AI\Client
	 */
	function wpfactory_ai() {
		WPFactory_AI_Versions::load();
		return \WPFactory\AI\Client::instance();
	}
}
