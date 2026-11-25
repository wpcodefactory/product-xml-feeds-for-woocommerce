<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: WooCommerce categories to Google categories.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$rows = Alg_WC_PXF_Admin::rest( 'GET', '/category-map' );
$rows = is_array( $rows ) ? $rows : array();
Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'Categories', 'product-xml-feeds-for-woocommerce' ),
	__( 'Match your categories to Google\'s', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Google, Meta and Pinterest use Google\'s category list. Set it once per category; every feed uses it.', 'product-xml-feeds-for-woocommerce' ),
	'<a class="btn btn-secondary" href="' . esc_url( Alg_WC_PXF_Admin::url( 'ai', array( 'job' => 'categories' ) ) ) . '">' . esc_html__( 'Suggest with AI', 'product-xml-feeds-for-woocommerce' ) . '</a>'
);
$matched = 0;
foreach ( $rows as $r ) {
	$matched += empty( $r['google_id'] ) ? 0 : 1;
}
?>
<form id="pxf-cats" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'alg_wc_pxf_save_categories' ); ?>
	<input type="hidden" name="action" value="alg_wc_pxf_save_categories">
	<section class="card">
		<div class="cat map-head" aria-hidden="true"><span><?php esc_html_e( 'Your category', 'product-xml-feeds-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Products', 'product-xml-feeds-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Google category', 'product-xml-feeds-for-woocommerce' ); ?></span></div>
		<?php if ( ! $rows ) : ?>
			<div class="empty"><p><?php esc_html_e( 'No product categories yet.', 'product-xml-feeds-for-woocommerce' ); ?></p></div>
		<?php endif; ?>
		<?php
		foreach ( $rows as $r ) :
			$tid = (int) $r['id'];
			$cnt = isset( $r['count'] ) ? (int) $r['count'] : 0;
			?>
			<div class="cat">
				<div class="row-title<?php echo empty( $r['parent'] ) ? '' : ' indent'; ?>"><?php echo esc_html( $r['name'] ); ?></div>
				<div class="n"><?php echo esc_html( sprintf( /* translators: %s: number of products. */ _n( '%s product', '%s products', $cnt, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $cnt ) ) ); ?></div>
				<div class="typeahead" data-pxf-typeahead>
					<label class="sr-only" for="pxf-cat-<?php echo esc_attr( $tid ); ?>"><?php echo esc_html( sprintf( /* translators: %s: category name. */ __( 'Google category for %s', 'product-xml-feeds-for-woocommerce' ), $r['name'] ) ); ?></label>
					<input id="pxf-cat-<?php echo esc_attr( $tid ); ?>" class="input" type="text" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pxf-cat-<?php echo esc_attr( $tid ); ?>-list" placeholder="<?php esc_attr_e( 'Type to search Google categories', 'product-xml-feeds-for-woocommerce' ); ?>" value="<?php echo esc_attr( isset( $r['google_path'] ) ? $r['google_path'] : '' ); ?>" data-pxf-ta-input>
					<input type="hidden" name="map[<?php echo esc_attr( $tid ); ?>]" value="<?php echo esc_attr( isset( $r['google_id'] ) ? (int) $r['google_id'] : 0 ); ?>" data-pxf-ta-id>
					<ul id="pxf-cat-<?php echo esc_attr( $tid ); ?>-list" role="listbox" hidden></ul>
				</div>
			</div>
		<?php endforeach; ?>
		<div class="card-foot"><span class="small muted"><?php echo esc_html( sprintf( /* translators: 1: matched, 2: total. */ __( '%1$s of %2$s categories matched', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $matched ), number_format_i18n( count( $rows ) ) ) ); ?></span><button class="btn btn-primary" type="submit"><?php esc_html_e( 'Save', 'product-xml-feeds-for-woocommerce' ); ?></button></div>
	</section>
</form>
