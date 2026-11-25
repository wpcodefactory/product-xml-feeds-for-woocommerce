<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: settings for all feeds.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$s       = Alg_WC_PXF_Admin::settings();
$uploads = wp_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'product-feeds/';
$ok      = wp_is_writable( file_exists( $dir ) ? $dir : $uploads['basedir'] );

Alg_WC_PXF_Admin_UI::page_head(
	esc_html__( 'Settings', 'product-xml-feeds-for-woocommerce' ),
	__( 'Settings for all feeds', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Most stores never change these.', 'product-xml-feeds-for-woocommerce' )
);
?>
<form class="stack" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'alg_wc_pxf_save_settings' ); ?>
	<input type="hidden" name="action" value="alg_wc_pxf_save_settings">
	<section class="card">
		<h2 class="section-label"><?php esc_html_e( 'Updates', 'product-xml-feeds-for-woocommerce' ); ?></h2>
		<?php
		Alg_WC_PXF_Admin_UI::setting( __( 'Products per batch', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Lower it if feeds stop on a slow server.', 'product-xml-feeds-for-woocommerce' ), '<input id="pxf-batch" class="input" type="number" min="10" max="500" step="10" name="settings[batch_size]" value="' . esc_attr( $s['batch_size'] ) . '">', 'pxf-batch' );
		Alg_WC_PXF_Admin_UI::setting( __( 'Update with a server cron job instead (advanced)', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'For sites with WordPress cron turned off. Ask your host to run wp-cron.php every 5 minutes.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'settings[server_cron]', ! empty( $s['server_cron'] ), __( 'Update with a server cron job instead', 'product-xml-feeds-for-woocommerce' ) ) );
		Alg_WC_PXF_Admin_UI::setting( __( 'Feed files folder', 'product-xml-feeds-for-woocommerce' ), '<span class="code">' . esc_html( str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $dir ) ) ) . '</span>', Alg_WC_PXF_Admin_UI::chip( $ok ? __( 'Writable', 'product-xml-feeds-for-woocommerce' ) : __( 'Not writable', 'product-xml-feeds-for-woocommerce' ), $ok ? 'ok' : 'bad' ) );
		?>
	</section>
	<section class="card">
		<h2 class="section-label"><?php esc_html_e( 'Other screens', 'product-xml-feeds-for-woocommerce' ); ?></h2>
		<div class="row"><div><p class="row-title"><?php esc_html_e( 'Classic feeds', 'product-xml-feeds-for-woocommerce' ); ?></p><div class="row-meta"><?php esc_html_e( 'Feeds made with your own XML template before 4.0. They keep working as they are.', 'product-xml-feeds-for-woocommerce' ); ?></div></div><a class="btn btn-secondary btn-sm" href="<?php echo esc_url( Alg_WC_PXF_Admin::classic_url() ); ?>"><?php esc_html_e( 'Open classic feeds', 'product-xml-feeds-for-woocommerce' ); ?></a></div>
	</section>
	<section class="card danger">
		<h2 class="section-label"><?php esc_html_e( 'Careful here', 'product-xml-feeds-for-woocommerce' ); ?></h2>
		<?php Alg_WC_PXF_Admin_UI::setting( __( 'Remove feeds and files when the plugin is deleted', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Feed settings and files in product-feeds are deleted. Classic feeds are not touched.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'settings[remove_on_uninstall]', ! empty( $s['remove_on_uninstall'] ), __( 'Remove feeds and files when the plugin is deleted', 'product-xml-feeds-for-woocommerce' ) ) ); ?>
	</section>
	<div class="savebar"><button class="btn btn-primary" type="submit"><?php esc_html_e( 'Save changes', 'product-xml-feeds-for-woocommerce' ); ?></button></div>
</form>
