<?php
/**
 * Product XML Feeds for WooCommerce - v2 module: AI (WPFactory AI credits).
 *
 * Feed titles, feed descriptions, missing attributes and Google category suggestions, paid with WPFactory AI credits
 * through the bundled wpfactory/wpfactory-ai library. Nothing is sent until an administrator connects the site on the
 * AI Credits screen and starts a job. AI values are feed-only and go into feeds only after approval.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

// WPFactory AI library (the newest bundled copy across plugins loads, once, at the end of plugins_loaded).
$alg_wc_pxf_ai_lib = dirname( __DIR__, 3 ) . '/vendor/wpfactory/wpfactory-ai/wpfactory-ai.php';
if ( file_exists( $alg_wc_pxf_ai_lib ) ) {
	require_once $alg_wc_pxf_ai_lib;
}
unset( $alg_wc_pxf_ai_lib );

require_once __DIR__ . '/class-alg-wc-pxf-ai.php';
require_once __DIR__ . '/class-alg-wc-pxf-ai-rest.php';
require_once __DIR__ . '/class-alg-wc-pxf-ai-abilities.php';

Alg_WC_PXF_AI::init();
Alg_WC_PXF_AI_REST::init();
Alg_WC_PXF_AI_Abilities::init();
