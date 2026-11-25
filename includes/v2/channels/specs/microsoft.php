<?php
/**
 * Channel spec: Microsoft Merchant Center (Bing Shopping, Copilot). Google attribute set, research/platforms.md 4a.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$f    = array( 'Alg_WC_PXF_Channels', 'field' );
$spec = include __DIR__ . '/google.php';

$spec['id']      = 'microsoft';
$spec['label']   = __( 'Microsoft Shopping (Bing)', 'product-xml-feeds-for-woocommerce' );
$spec['notes']   = __( 'Microsoft Merchant Center feed. Tip: Microsoft can also import your Google Merchant Center products on a schedule.', 'product-xml-feeds-for-woocommerce' );
$spec['formats'] = array( 'xml', 'tsv' );
$spec['ai']      = array( 'title' => 'title', 'description' => 'description', 'structured' => false );

unset( $spec['fields']['structured_title'], $spec['fields']['structured_description'] );

$spec['fields']['shipping']['when'] = 'country:AT,DE';
$spec['fields']['seller_name']      = call_user_func( $f, __( 'Shop name', 'product-xml-feeds-for-woocommerce' ), false, 'basic', 'site_name' );

return $spec;
