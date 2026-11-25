<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: Pro features (free only; no locked controls elsewhere).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'Pro features', 'product-xml-feeds-for-woocommerce' ),
	__( 'More from your feeds with Pro', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Every channel, feed and field is free. Pro adds speed and control for busy stores.', 'product-xml-feeds-for-woocommerce' )
);
$items = array(
	array( __( 'Updates every hour or every 15 minutes', 'product-xml-feeds-for-woocommerce' ), __( 'Prices and stock reach the channel faster.', 'product-xml-feeds-for-woocommerce' ) ),
	array( __( 'Update when a product changes', 'product-xml-feeds-for-woocommerce' ), __( 'A sale or a sold-out product shows in the feed within minutes.', 'product-xml-feeds-for-woocommerce' ) ),
	array( __( 'Leave out products with errors', 'product-xml-feeds-for-woocommerce' ), __( 'The channel never rejects the whole feed for one bad product.', 'product-xml-feeds-for-woocommerce' ) ),
	array( __( 'List of products with problems', 'product-xml-feeds-for-woocommerce' ), __( 'See every product and the exact fix, not only the counts.', 'product-xml-feeds-for-woocommerce' ) ),
);
?>
<section class="card">
	<ul class="pro-list">
		<?php foreach ( $items as $i ) : ?>
			<li><p class="row-title"><?php echo esc_html( $i[0] ); ?></p><div class="row-meta"><?php echo esc_html( $i[1] ); ?></div></li>
		<?php endforeach; ?>
	</ul>
	<div class="card-foot"><span class="small muted"><?php esc_html_e( 'Everything you set up now keeps working with or without Pro.', 'product-xml-feeds-for-woocommerce' ); ?></span><a class="btn btn-primary" href="<?php echo esc_url( Alg_WC_PXF_Admin::pro_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Pro features', 'product-xml-feeds-for-woocommerce' ); ?></a></div>
</section>
