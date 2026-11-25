<?php
/**
 * Product XML Feeds for WooCommerce - v2 module "engine": feed records, background runs, writers, schedules, REST.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-alg-wc-pxf-feeds.php';
require_once __DIR__ . '/class-alg-wc-pxf-writer.php';
require_once __DIR__ . '/class-alg-wc-pxf-writer-xml.php';
require_once __DIR__ . '/class-alg-wc-pxf-writer-csv.php';
require_once __DIR__ . '/class-alg-wc-pxf-writer-jsonl.php';
require_once __DIR__ . '/class-alg-wc-pxf-engine.php';
require_once __DIR__ . '/class-alg-wc-pxf-scheduler.php';
require_once __DIR__ . '/class-alg-wc-pxf-classic-copy.php';
require_once __DIR__ . '/class-alg-wc-pxf-rest.php';

Alg_WC_PXF_Feeds::init();
Alg_WC_PXF_Engine::init();
Alg_WC_PXF_Scheduler::init();
Alg_WC_PXF_REST::init();
