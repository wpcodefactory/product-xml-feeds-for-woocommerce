<?php
/**
 * WPFactory AI library loader.
 *
 * Every plugin that bundles this library includes this file (Composer "files" autoload, or a plain
 * require from the plugin's main file). Each copy registers its version; once all plugins are loaded,
 * the newest copy loads, once. Use wpfactory_ai() on init or later.
 *
 * @package WPFactory\AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_AI_Versions', false ) ) {
	require_once __DIR__ . '/src/class-wpfactory-ai-versions.php';
}
if ( ! function_exists( 'wpfactory_ai' ) ) {
	require_once __DIR__ . '/src/functions.php';
}

WPFactory_AI_Versions::register( '1.7.0', __DIR__ . '/src/bootstrap.php' );
