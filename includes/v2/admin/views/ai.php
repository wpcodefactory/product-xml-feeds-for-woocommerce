<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: AI review (estimate, run, before and after, approve, edit, undo).
 *
 * Talks to the /ai/* routes of the AI module; the page is rendered from /ai/results and enhanced by admin.js.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$jobs = array(
	'titles'       => __( 'Titles', 'product-xml-feeds-for-woocommerce' ),
	'descriptions' => __( 'Descriptions', 'product-xml-feeds-for-woocommerce' ),
	'attributes'   => __( 'Color, size, gender, age group', 'product-xml-feeds-for-woocommerce' ),
	'categories'   => __( 'Google categories', 'product-xml-feeds-for-woocommerce' ),
);
$job     = isset( $_GET['job'] ) ? sanitize_key( wp_unslash( $_GET['job'] ) ) : 'titles'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$job     = isset( $jobs[ $job ] ) ? $job : 'titles';
$feed_id = isset( $_GET['feed'] ) ? absint( $_GET['feed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$res     = Alg_WC_PXF_Admin::rest( 'GET', '/ai/results', array( 'job' => $job, 'feed' => $feed_id ) );
$ready   = ! is_wp_error( $res );
$res     = is_array( $res ) ? $res : array();
$items   = isset( $res['items'] ) ? (array) $res['items'] : array();
$balance = isset( $res['balance'] ) ? $res['balance'] : null;

Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'AI review', 'product-xml-feeds-for-woocommerce' ),
	__( 'Better product data for your feeds', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Nothing goes into a feed until you approve it. Your product pages never change; Undo removes the feed value.', 'product-xml-feeds-for-woocommerce' ),
	null === $balance ? '' : '<span class="pill" data-pxf-balance>' . esc_html( sprintf( /* translators: %s: credits. */ _n( '%s credit', '%s credits', (int) $balance, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( (int) $balance ) ) ) . '</span>'
);
if ( ! $ready ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'AI tools are not available right now.', 'product-xml-feeds-for-woocommerce' ), 'warn' );
	return;
}
$approved = 0;
foreach ( $items as $i ) {
	$approved += ( isset( $i['status'] ) && 'approved' === $i['status'] ) ? 1 : 0;
}
?>
<div class="estimate" style="margin-bottom:20px" id="pxf-ai-run" data-feed="<?php echo esc_attr( $feed_id ); ?>">
	<div><b data-pxf-estimate><?php esc_html_e( 'Choose what to improve to see the cost.', 'product-xml-feeds-for-woocommerce' ); ?></b><div class="row-meta" data-pxf-estimate-meta><?php esc_html_e( 'Runs in the background; you can leave this page.', 'product-xml-feeds-for-woocommerce' ); ?></div></div>
	<div class="head-actions">
		<label class="sr-only" for="pxf-ai-job"><?php esc_html_e( 'What to improve', 'product-xml-feeds-for-woocommerce' ); ?></label>
		<select id="pxf-ai-job" class="select" style="width:auto" data-pxf-ai-job>
			<?php foreach ( $jobs as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"<?php selected( $job, $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn btn-primary" type="button" data-pxf-ai-start><?php esc_html_e( 'Run', 'product-xml-feeds-for-woocommerce' ); ?></button>
	</div>
</div>
<section class="card" id="pxf-ai-results">
	<div class="card-head"><h2 class="card-title"><?php echo esc_html( sprintf( /* translators: 1: results, 2: approved. */ __( 'Results: %1$s, %2$s approved', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( count( $items ) ), number_format_i18n( $approved ) ) ); ?></h2>
		<?php if ( $items ) : ?><div class="row-actions"><button class="btn btn-secondary btn-sm" type="button" data-pxf-ai-approve-all><?php esc_html_e( 'Approve all', 'product-xml-feeds-for-woocommerce' ); ?></button></div><?php endif; ?></div>
	<?php if ( ! $items ) : ?>
		<div class="empty"><p><?php esc_html_e( 'No suggestions yet. Run a job above; results show here for you to approve.', 'product-xml-feeds-for-woocommerce' ); ?></p></div>
	<?php else : ?>
		<div class="ba ba-head" aria-hidden="true"><span><?php esc_html_e( 'Now', 'product-xml-feeds-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Suggested', 'product-xml-feeds-for-woocommerce' ); ?></span><span></span></div>
		<?php
		foreach ( $items as $i ) :
			$iid = isset( $i['id'] ) ? (string) $i['id'] : '';
			$ok  = isset( $i['status'] ) && 'approved' === $i['status'];
			?>
			<div class="ba" data-pxf-ai-item="<?php echo esc_attr( $iid ); ?>">
				<div><div class="row-meta"><?php echo esc_html( isset( $i['product_name'] ) ? $i['product_name'] : '' ); ?></div><div class="before"><?php echo esc_html( isset( $i['before'] ) ? $i['before'] : '' ); ?></div></div>
				<div><div class="after" data-pxf-after><?php echo esc_html( isset( $i['after'] ) ? $i['after'] : '' ); ?></div>
					<label class="sr-only" for="pxf-ai-e-<?php echo esc_attr( $iid ); ?>"><?php esc_html_e( 'Edit the suggestion', 'product-xml-feeds-for-woocommerce' ); ?></label>
					<textarea id="pxf-ai-e-<?php echo esc_attr( $iid ); ?>" hidden data-pxf-edit-box><?php echo esc_textarea( isset( $i['after'] ) ? $i['after'] : '' ); ?></textarea>
					<?php echo $ok ? Alg_WC_PXF_Admin_UI::chip( __( 'Approved', 'product-xml-feeds-for-woocommerce' ), 'ok', ' data-pxf-ai-chip' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="row-actions">
					<button class="btn btn-secondary btn-sm" type="button" data-pxf-ai-approve<?php echo $ok ? ' hidden' : ''; ?>><?php esc_html_e( 'Approve', 'product-xml-feeds-for-woocommerce' ); ?></button>
					<button class="btn btn-text btn-sm" type="button" data-pxf-ai-edit<?php echo $ok ? ' hidden' : ''; ?>><?php esc_html_e( 'Edit', 'product-xml-feeds-for-woocommerce' ); ?></button>
					<button class="btn btn-text btn-sm" type="button" data-pxf-ai-undo<?php echo $ok ? '' : ' hidden'; ?>><?php esc_html_e( 'Undo', 'product-xml-feeds-for-woocommerce' ); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
		<div class="card-foot"><span class="small muted"><?php esc_html_e( 'Failed products are refunded and can be run again.', 'product-xml-feeds-for-woocommerce' ); ?></span></div>
	<?php endif; ?>
</section>
<noscript><p class="hint"><?php esc_html_e( 'AI review needs JavaScript.', 'product-xml-feeds-for-woocommerce' ); ?></p></noscript>
