<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: product check (validator results).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$feeds   = Alg_WC_PXF_Admin::rest( 'GET', '/feeds' );
$feeds   = array_values( array_filter( is_array( $feeds ) ? $feeds : array(), function ( $f ) { return empty( $f['type'] ) || 'classic' !== $f['type']; } ) );
$feed_id = isset( $_GET['feed'] ) ? absint( $_GET['feed'] ) : ( $feeds ? (int) $feeds[0]['id'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current = null;
foreach ( $feeds as $f ) {
	if ( (int) $f['id'] === $feed_id ) {
		$current = $f;
	}
}
$picker = '';
if ( $feeds ) {
	$picker = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="head-actions"><input type="hidden" name="page" value="' . esc_attr( Alg_WC_PXF_Admin::SLUG ) . '"><input type="hidden" name="view" value="products">' .
		'<label class="sr-only" for="pxf-pfeed">' . esc_html__( 'Feed', 'product-xml-feeds-for-woocommerce' ) . '</label><select id="pxf-pfeed" class="select" style="width:auto" name="feed" data-pxf-autosubmit>';
	foreach ( $feeds as $f ) {
		$picker .= '<option value="' . esc_attr( $f['id'] ) . '"' . selected( $feed_id, (int) $f['id'], false ) . '>' . esc_html( $f['name'] ) . '</option>';
	}
	$picker .= '</select><noscript><button class="btn btn-secondary btn-sm" type="submit">' . esc_html__( 'Show', 'product-xml-feeds-for-woocommerce' ) . '</button></noscript></form>';
}
Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'Product check', 'product-xml-feeds-for-woocommerce' ),
	/* translators: %s: feed name. */
	$current ? sprintf( __( 'What %s will say', 'product-xml-feeds-for-woocommerce' ), $current['name'] ) : __( 'Product check', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Checked on the last update. Fix errors first: the channel rejects those products.', 'product-xml-feeds-for-woocommerce' ),
	$picker
);
if ( ! $current ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'Add a feed first. Products are checked every time a feed updates.', 'product-xml-feeds-for-woocommerce' ) . ' <a href="' . esc_url( Alg_WC_PXF_Admin::url( 'new' ) ) . '">' . esc_html__( 'Add feed', 'product-xml-feeds-for-woocommerce' ) . '</a>' );
	return;
}
$data    = Alg_WC_PXF_Admin::rest( 'GET', '/feeds/' . $feed_id . '/issues' );
$data    = is_array( $data ) ? $data : array();
$summary = isset( $data['summary'] ) ? (array) $data['summary'] : array();
$totals  = isset( $data['totals'] ) ? (array) $data['totals'] : array();
$tiles   = array(
	'ready'    => array( 'ok', __( 'Ready', 'product-xml-feeds-for-woocommerce' ) ),
	'warnings' => array( 'warn', __( 'With warnings', 'product-xml-feeds-for-woocommerce' ) ),
	'errors'   => array( 'bad', __( 'With errors', 'product-xml-feeds-for-woocommerce' ) ),
	'left_out' => array( '', __( 'Left out', 'product-xml-feeds-for-woocommerce' ) ),
);
echo '<div class="tiles" style="margin-bottom:20px">';
foreach ( $tiles as $k => $t ) {
	echo '<div class="tile ' . esc_attr( $t[0] ) . '"><div class="eyebrow">' . esc_html( $t[1] ) . '</div><b>' . esc_html( number_format_i18n( isset( $totals[ $k ] ) ? (int) $totals[ $k ] : 0 ) ) . '</b></div>';
}
echo '</div>';
$fix_links = array(
	'ai_title'       => Alg_WC_PXF_Admin::url( 'ai', array( 'job' => 'titles', 'feed' => $feed_id ) ),
	'ai_description' => Alg_WC_PXF_Admin::url( 'ai', array( 'job' => 'descriptions', 'feed' => $feed_id ) ),
	'ai_attributes'  => Alg_WC_PXF_Admin::url( 'ai', array( 'job' => 'attributes', 'feed' => $feed_id ) ),
	'ai_category'    => Alg_WC_PXF_Admin::url( 'categories' ),
	'mapping'        => Alg_WC_PXF_Admin::url( 'edit', array( 'feed' => $feed_id ) ),
);
?>
<section class="card" style="margin-bottom:20px">
	<div class="card-head"><h2 class="card-title"><?php esc_html_e( 'Problems by reason', 'product-xml-feeds-for-woocommerce' ); ?></h2></div>
	<?php if ( ! $summary ) : ?>
		<div class="empty"><p><?php esc_html_e( 'No problems found on the last update.', 'product-xml-feeds-for-woocommerce' ); ?></p></div>
	<?php endif; ?>
	<?php
	foreach ( $summary as $row ) :
		$bad = isset( $row['severity'] ) && 'error' === $row['severity'];
		$fix = isset( $row['fix'] ) ? $row['fix'] : 'product';
		$fix = ( isset( $row['field'] ) && 'google_product_category' === $row['field'] ) ? 'ai_category' : $fix;
		$cnt = isset( $row['count'] ) ? (int) $row['count'] : 0;
		?>
		<div class="row"><div><p class="row-title"><?php echo Alg_WC_PXF_Admin_UI::chip( $bad ? __( 'Error', 'product-xml-feeds-for-woocommerce' ) : __( 'Warning', 'product-xml-feeds-for-woocommerce' ), $bad ? 'bad' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( isset( $row['message'] ) ? $row['message'] : $row['code'] ); ?></p>
			<div class="row-meta"><?php echo esc_html( sprintf( /* translators: %s: number of products. */ _n( '%s product', '%s products', $cnt, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $cnt ) ) ); ?></div></div>
			<div class="row-actions">
			<?php
			if ( isset( $fix_links[ $fix ] ) && 0 === strpos( $fix, 'ai_' ) ) {
				printf( '<a class="btn btn-secondary btn-sm" href="%s">%s</a>', esc_url( $fix_links[ $fix ] ), esc_html__( 'Fix with AI', 'product-xml-feeds-for-woocommerce' ) );
			} elseif ( 'mapping' === $fix ) {
				printf( '<a class="btn btn-text btn-sm" href="%s">%s</a>', esc_url( $fix_links['mapping'] ), esc_html__( 'Choose a source', 'product-xml-feeds-for-woocommerce' ) );
			}
			?>
			</div></div>
	<?php endforeach; ?>
</section>
<?php
if ( Alg_WC_PXF::is_pro() ) :
	$list = isset( $data['products'] ) ? (array) $data['products'] : array();
	?>
	<section class="card">
		<div class="card-head"><h2 class="card-title"><?php esc_html_e( 'Products with problems', 'product-xml-feeds-for-woocommerce' ); ?></h2></div>
		<div class="table-wrap"><table class="table"><thead><tr><th><?php esc_html_e( 'Product', 'product-xml-feeds-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Problem', 'product-xml-feeds-for-woocommerce' ); ?></th><th><span class="sr-only"><?php esc_html_e( 'Action', 'product-xml-feeds-for-woocommerce' ); ?></span></th></tr></thead><tbody>
		<?php foreach ( $list as $p ) : ?>
			<tr><td><?php echo esc_html( isset( $p['name'] ) ? $p['name'] : '#' . $p['product_id'] ); ?></td>
				<td><?php echo esc_html( implode( ', ', wp_list_pluck( isset( $p['issues'] ) ? (array) $p['issues'] : array(), 'message' ) ) ); ?></td>
				<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $p['product_id'] ) ); ?>"><?php esc_html_e( 'Edit product', 'product-xml-feeds-for-woocommerce' ); ?></a></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
	</section>
<?php else : ?>
	<p class="hint"><a href="<?php echo esc_url( Alg_WC_PXF_Admin::url( 'pro' ) ); ?>"><?php esc_html_e( 'See every product and its exact fix: Pro features', 'product-xml-feeds-for-woocommerce' ); ?></a></p>
	<?php
endif;
