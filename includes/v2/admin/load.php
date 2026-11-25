<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin module loader.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_admin() ) {
	return;
}

require_once __DIR__ . '/class-alg-wc-pxf-admin-ui.php';
require_once __DIR__ . '/class-alg-wc-pxf-admin.php';

Alg_WC_PXF_Admin::init();
