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
$res     = Alg_WC_PXF_Admin::rest( 'GET', '/ai/results', array( 'job' => $job, 'per_page' => 100 ) );
$ready   = ! is_wp_error( $res ) && class_exists( 'Alg_WC_PXF_AI' );
$res     = is_array( $res ) ? $res : array();
$items   = isset( $res['items'] ) ? (array) $res['items'] : array();
$run     = ( isset( $res['run'] ) && is_array( $res['run'] ) ) ? $res['run'] : array();
$busy    = $run && in_array( isset( $run['status'] ) ? $run['status'] : '', array( 'queued', 'running' ), true );

Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'AI review', 'product-xml-feeds-for-woocommerce' ),
	__( 'Better product data for your feeds', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Nothing goes into a feed until you approve it. Your product pages never change; Undo removes the feed value.', 'product-xml-feeds-for-woocommerce' ),
	'<span class="pill" data-pxf-balance hidden></span>'
);
if ( ! $ready ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'AI tools are not available right now.', 'product-xml-feeds-for-woocommerce' ), 'warn' );
	return;
}
// The last run of this job ended with an error: say why (the run itself said nothing was charged where that is true).
if ( $run && ! $busy && isset( $run['job'] ) && $job === $run['job'] && ! empty( $run['status_text'] ) && ( 'failed' === $run['status'] || ! empty( $run['failed'] ) ) ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html( $run['status_text'] ), 'failed' === $run['status'] ? 'bad' : 'warn' );
}
$ids      = Alg_WC_PXF_Admin::ai_candidates( $job, $feed_id );
$editable = in_array( $job, array( 'titles', 'descriptions' ), true );
$approved = 0;
foreach ( $items as $i ) {
	$approved += ( isset( $i['status'] ) && 'approved' === $i['status'] ) ? 1 : 0;
}

// Plain text for a before or after value: Google category paths, "color: Red, size: M" for attributes.
$show = function ( $value, $path = '' ) use ( $job ) {
	if ( 'categories' === $job ) {
		if ( '' !== (string) $path ) {
			return (string) $path;
		}
		$gid = absint( $value );
		$out = ( $gid && class_exists( 'Alg_WC_PXF_Google_Taxonomy' ) ) ? (string) Alg_WC_PXF_Google_Taxonomy::path( $gid ) : '';
		return '' !== $out ? $out : __( 'No Google category', 'product-xml-feeds-for-woocommerce' );
	}
	if ( is_array( $value ) ) {
		$pairs = array();
		foreach ( $value as $k => $v ) {
			if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				$pairs[] = str_replace( '_', ' ', (string) $k ) . ': ' . $v;
			}
		}
		return $pairs ? implode( ', ', $pairs ) : __( 'Nothing set', 'product-xml-feeds-for-woocommerce' );
	}
	return (string) $value;
};
?>
<div class="estimate" style="margin-bottom:20px" id="pxf-ai-run" data-feed="<?php echo esc_attr( $feed_id ); ?>" data-ids="<?php echo esc_attr( wp_json_encode( $ids ) ); ?>" data-running="<?php echo $busy ? '1' : '0'; ?>">
	<div><b data-pxf-estimate><?php esc_html_e( 'Choose what to improve to see the cost.', 'product-xml-feeds-for-woocommerce' ); ?></b><div class="row-meta" data-pxf-estimate-meta><?php echo esc_html( $busy && ! empty( $run['status_text'] ) ? $run['status_text'] : __( 'Runs in the background; you can leave this page.', 'product-xml-feeds-for-woocommerce' ) ); ?></div></div>
	<div class="head-actions">
		<label class="sr-only" for="pxf-ai-job"><?php esc_html_e( 'What to improve', 'product-xml-feeds-for-woocommerce' ); ?></label>
		<select id="pxf-ai-job" class="select" style="width:auto" data-pxf-ai-job>
			<?php foreach ( $jobs as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"<?php selected( $job, $k ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn btn-primary" type="button" data-pxf-ai-start<?php disabled( $busy ); ?>><?php esc_html_e( 'Run', 'product-xml-feeds-for-woocommerce' ); ?></button>
	</div>
</div>
<section class="card" id="pxf-ai-results" data-job="<?php echo esc_attr( $job ); ?>">
	<div class="card-head"><h2 class="card-title"><?php echo esc_html( sprintf( /* translators: 1: results, 2: approved. */ __( 'Results: %1$s, %2$s approved', 'product-xml-feeds-for-woocommerce' ), number_format_i18n( count( $items ) ), number_format_i18n( $approved ) ) ); ?></h2>
		<?php if ( $items ) : ?><div class="row-actions"><button class="btn btn-secondary btn-sm" type="button" data-pxf-ai-approve-all><?php esc_html_e( 'Approve all', 'product-xml-feeds-for-woocommerce' ); ?></button></div><?php endif; ?></div>
	<?php if ( ! $items ) : ?>
		<div class="empty"><p><?php esc_html_e( 'No suggestions yet. Run a job above; results show here for you to approve.', 'product-xml-feeds-for-woocommerce' ); ?></p></div>
	<?php else : ?>
		<div class="ba ba-head" aria-hidden="true"><span><?php esc_html_e( 'Now', 'product-xml-feeds-for-woocommerce' ); ?></span><span><?php esc_html_e( 'Suggested', 'product-xml-feeds-for-woocommerce' ); ?></span><span></span></div>
		<?php
		foreach ( $items as $i ) :
			$iid    = isset( $i['id'] ) ? (int) $i['id'] : 0;
			$ok     = isset( $i['status'] ) && 'approved' === $i['status'];
			$before = $show( isset( $i['before'] ) ? $i['before'] : '' );
			$after  = $show( isset( $i['after'] ) ? $i['after'] : '', isset( $i['after_path'] ) ? $i['after_path'] : '' );
			?>
			<div class="ba" data-pxf-ai-item="<?php echo esc_attr( $iid ); ?>">
				<div><div class="row-meta"><?php echo esc_html( isset( $i['name'] ) ? $i['name'] : '' ); ?></div><div class="before"><?php echo esc_html( $before ); ?></div></div>
				<div><div class="after" data-pxf-after><?php echo esc_html( $after ); ?></div>
					<?php if ( $editable ) : ?>
						<label class="sr-only" for="pxf-ai-e-<?php echo esc_attr( $iid ); ?>"><?php esc_html_e( 'Edit the suggestion', 'product-xml-feeds-for-woocommerce' ); ?></label>
						<textarea id="pxf-ai-e-<?php echo esc_attr( $iid ); ?>" hidden data-pxf-edit-box><?php echo esc_textarea( $after ); ?></textarea>
					<?php endif; ?>
					<?php echo $ok ? Alg_WC_PXF_Admin_UI::chip( __( 'Approved', 'product-xml-feeds-for-woocommerce' ), 'ok', ' data-pxf-ai-chip' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="row-actions">
					<button class="btn btn-secondary btn-sm" type="button" data-pxf-ai-approve<?php echo $ok ? ' hidden' : ''; ?>><?php esc_html_e( 'Approve', 'product-xml-feeds-for-woocommerce' ); ?></button>
					<?php if ( $editable ) : ?>
						<button class="btn btn-text btn-sm" type="button" data-pxf-ai-edit<?php echo $ok ? ' hidden' : ''; ?>><?php esc_html_e( 'Edit', 'product-xml-feeds-for-woocommerce' ); ?></button>
					<?php endif; ?>
					<button class="btn btn-text btn-sm" type="button" data-pxf-ai-undo<?php echo $ok ? '' : ' hidden'; ?>><?php esc_html_e( 'Undo', 'product-xml-feeds-for-woocommerce' ); ?></button>
				</div>
			</div>
		<?php endforeach; ?>
		<div class="card-foot"><span class="small muted"><?php esc_html_e( 'Failed products are refunded and can be run again.', 'product-xml-feeds-for-woocommerce' ); ?></span></div>
	<?php endif; ?>
</section>
<noscript><p class="hint"><?php esc_html_e( 'AI review needs JavaScript.', 'product-xml-feeds-for-woocommerce' ); ?></p></noscript>
