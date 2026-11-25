<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: feed list.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$feeds    = Alg_WC_PXF_Admin::rest( 'GET', '/feeds' );
$channels = Alg_WC_PXF_Admin::channels();
$feeds    = is_array( $feeds ) ? $feeds : array();
$v2       = array_filter( $feeds, function ( $f ) { return empty( $f['type'] ) || 'classic' !== $f['type']; } );
$classic  = array_filter( $feeds, function ( $f ) { return ! empty( $f['type'] ) && 'classic' === $f['type']; } );

Alg_WC_PXF_Admin_UI::page_head(
	esc_html( get_bloginfo( 'name' ) ),
	__( 'Product feeds', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Each feed is one file a sales channel reads. It updates on its own; the link stays the same.', 'product-xml-feeds-for-woocommerce' ),
	$v2 ? '<a class="btn btn-primary" href="' . esc_url( Alg_WC_PXF_Admin::url( 'new' ) ) . '">' . esc_html__( 'Add feed', 'product-xml-feeds-for-woocommerce' ) . '</a>' : ''
);

foreach ( $v2 as $feed ) {
	if ( ! isset( $feed['status'] ) || 'failed' !== $feed['status'] ) {
		continue;
	}
	// The list has no error text: read it from the feed's run state.
	$one   = Alg_WC_PXF_Admin::rest( 'GET', '/feeds/' . (int) $feed['id'] );
	$error = ( is_array( $one ) && ! empty( $one['state']['error'] ) ) ? (string) $one['state']['error'] : '';
	$error = '' !== $error ? $error : __( 'The last update stopped.', 'product-xml-feeds-for-woocommerce' );
	Alg_WC_PXF_Admin_UI::banner(
		/* translators: 1: feed name, 2: reason. */
		sprintf( __( '<b>%1$s was not updated.</b> %2$s The last good file is still online.', 'product-xml-feeds-for-woocommerce' ), esc_html( $feed['name'] ), esc_html( $error ) ),
		'warn'
	);
}

if ( is_wp_error( Alg_WC_PXF_Admin::rest( 'GET', '/channels' ) ) ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'The feed engine is not loaded, so feeds cannot be listed right now.', 'product-xml-feeds-for-woocommerce' ), 'bad' );
}

if ( ! $v2 ) :
	$suggest = array( 'google', 'meta', 'idealo' );
	?>
	<section class="card" style="margin-bottom:20px"><div class="empty">
		<h2><?php esc_html_e( 'Where do you sell?', 'product-xml-feeds-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Pick a channel and get a feed it accepts. Required fields are filled from your products, and you see what is missing before you send it.', 'product-xml-feeds-for-woocommerce' ); ?></p>
		<div class="head-actions" style="justify-content:center">
			<?php
			$first = true;
			foreach ( $suggest as $id ) {
				if ( ! isset( $channels[ $id ] ) ) {
					continue;
				}
				printf( '<a class="btn %s" href="%s">%s</a>', $first ? 'btn-primary' : 'btn-secondary', esc_url( Alg_WC_PXF_Admin::url( 'new', array( 'channel' => $id ) ) ), esc_html( $channels[ $id ]['label'] ) );
				$first = false;
			}
			printf( '<a class="btn %s" href="%s">%s</a>', $first ? 'btn-primary' : 'btn-text', esc_url( Alg_WC_PXF_Admin::url( 'new' ) ), esc_html__( 'All channels', 'product-xml-feeds-for-woocommerce' ) );
			?>
		</div>
	</div></section>
	<?php
endif;

if ( $v2 || $classic ) :
	?>
	<section class="card" aria-label="<?php esc_attr_e( 'Feeds', 'product-xml-feeds-for-woocommerce' ); ?>">
	<?php
	foreach ( $v2 as $feed ) :
		$id        = (int) $feed['id'];
		$status    = isset( $feed['status'] ) ? $feed['status'] : 'idle';
		$last_good = isset( $feed['last_good'] ) ? (int) $feed['last_good'] : 0;
		$written   = isset( $feed['written'] ) ? (int) $feed['written'] : ( isset( $feed['products'] ) ? (int) $feed['products'] : 0 );
		$issues    = isset( $feed['issues_total'] ) ? (int) $feed['issues_total'] : 0;
		$chip      = Alg_WC_PXF_Admin_UI::status( $status, $last_good );
		$channel   = isset( $channels[ $feed['channel'] ] ) ? $channels[ $feed['channel'] ]['label'] : $feed['channel'];
		$busy      = in_array( $status, array( 'running', 'queued' ), true );
		$meta      = array( $channel );
		/* translators: %s: number of products. */
		$meta[] = sprintf( _n( '%s product', '%s products', $written, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $written ) );
		if ( $issues ) {
			/* translators: %s: number of problems. */
			$meta[] = sprintf( _n( '%s problem to fix', '%s problems to fix', $issues, 'product-xml-feeds-for-woocommerce' ), number_format_i18n( $issues ) );
		}
		if ( $last_good ) {
			/* translators: %s: date. */
			$meta[] = sprintf( __( 'Updated %s', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::date( $last_good ) );
		}
		?>
		<div class="feed" data-pxf-feed="<?php echo esc_attr( $id ); ?>" data-status="<?php echo esc_attr( $status ); ?>">
			<div>
				<p class="feed-name"><a href="<?php echo esc_url( Alg_WC_PXF_Admin::url( 'edit', array( 'feed' => $id ) ) ); ?>"><?php echo esc_html( $feed['name'] ); ?></a> <?php echo Alg_WC_PXF_Admin_UI::chip( $chip[1], $chip[0], ' data-pxf-chip' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<div class="feed-meta" data-pxf-meta><?php echo esc_html( implode( ' · ', $meta ) ); ?></div>
				<div class="progress" data-pxf-progress aria-hidden="true"<?php echo $busy ? '' : ' hidden'; ?>><i style="width:0"></i></div>
			</div>
			<?php echo Alg_WC_PXF_Admin_UI::url_field( 'pxf-url-' . $id, isset( $feed['url'] ) ? $feed['url'] : '', sprintf( /* translators: %s: feed name. */ __( 'Feed link for %s', 'product-xml-feeds-for-woocommerce' ), $feed['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form class="row-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'alg_wc_pxf_run_feed' ); ?>
				<input type="hidden" name="action" value="alg_wc_pxf_run_feed"><input type="hidden" name="feed" value="<?php echo esc_attr( $id ); ?>">
				<button class="btn btn-secondary btn-sm" type="submit" data-pxf-run="<?php echo esc_attr( $id ); ?>"<?php disabled( $busy ); ?>><?php echo $busy ? esc_html( $chip[1] ) : esc_html__( 'Update now', 'product-xml-feeds-for-woocommerce' ); ?></button>
				<a class="btn btn-text btn-sm" href="<?php echo esc_url( Alg_WC_PXF_Admin::url( 'edit', array( 'feed' => $id ) ) ); ?>"><?php esc_html_e( 'Edit', 'product-xml-feeds-for-woocommerce' ); ?><span class="sr-only"> <?php echo esc_html( $feed['name'] ); ?></span></a>
			</form>
		</div>
		<?php
	endforeach;
	foreach ( $classic as $feed ) :
		$n     = isset( $feed['n'] ) ? (int) $feed['n'] : 0;
		$title = ! empty( $feed['title'] ) ? $feed['title'] : sprintf( /* translators: %d: feed number. */ __( 'XML feed #%d', 'product-xml-feeds-for-woocommerce' ), $n );
		?>
		<div class="feed classic">
			<div>
				<p class="feed-name"><?php echo esc_html( $title ); ?> <?php echo Alg_WC_PXF_Admin_UI::chip( __( 'Classic feed', 'product-xml-feeds-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<div class="feed-meta"><?php
					echo esc_html__( 'Your own template', 'product-xml-feeds-for-woocommerce' );
				if ( ! empty( $feed['last_created'] ) ) {
					/* translators: %s: date. */
					echo ' · ' . esc_html( sprintf( __( 'created %s', 'product-xml-feeds-for-woocommerce' ), is_numeric( $feed['last_created'] ) ? Alg_WC_PXF_Admin_UI::date( $feed['last_created'] ) : $feed['last_created'] ) );
				}
				?></div>
			</div>
			<?php echo Alg_WC_PXF_Admin_UI::url_field( 'pxf-curl-' . $n, isset( $feed['url'] ) ? $feed['url'] : '', sprintf( /* translators: %s: feed name. */ __( 'Feed link for %s', 'product-xml-feeds-for-woocommerce' ), $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form class="row-actions" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'alg_wc_pxf_copy_classic' ); ?>
				<input type="hidden" name="action" value="alg_wc_pxf_copy_classic"><input type="hidden" name="classic" value="<?php echo esc_attr( $n ); ?>">
				<a class="btn btn-secondary btn-sm" href="<?php echo esc_url( Alg_WC_PXF_Admin::classic_url( $n ) ); ?>"><?php esc_html_e( 'Open', 'product-xml-feeds-for-woocommerce' ); ?><span class="sr-only"> <?php echo esc_html( $title ); ?></span></a>
				<button class="btn btn-text btn-sm" type="submit" title="<?php esc_attr_e( 'Makes a new feed with this template and its own link. This classic feed stays as it is.', 'product-xml-feeds-for-woocommerce' ); ?>"><?php esc_html_e( 'Copy to a new feed', 'product-xml-feeds-for-woocommerce' ); ?><span class="sr-only"> <?php echo esc_html( $title ); ?></span></button>
			</form>
		</div>
		<?php
	endforeach;
	?>
	</section>
	<?php
endif;
