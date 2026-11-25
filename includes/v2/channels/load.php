<?php
/**
 * Product XML Feeds for WooCommerce - v2 module "channels".
 *
 * Channel specs (one file per channel in specs/), the canonical product model, the mapper from model to channel item
 * and the bundled Google product taxonomy. Nothing here writes classic options or classic feed files.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-alg-wc-pxf-channels.php';
require_once __DIR__ . '/class-alg-wc-pxf-google-taxonomy.php';
require_once __DIR__ . '/class-alg-wc-pxf-product-data.php';
require_once __DIR__ . '/class-alg-wc-pxf-mapper.php';
