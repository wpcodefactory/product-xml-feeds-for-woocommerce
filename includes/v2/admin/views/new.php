<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: new feed (1 channel, 2 name and country).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$channels  = Alg_WC_PXF_Admin::channels();
$country   = WC()->countries ? WC()->countries->get_base_country() : 'US';
$countries = WC()->countries ? WC()->countries->get_countries() : array();
$pick      = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$currency  = get_woocommerce_currency();
$lang      = get_locale();

// Suggested: channels made for the store country, then Google and Meta.
$suggested = array();
foreach ( $channels as $id => $c ) {
	if ( ! empty( $c['countries'] ) && in_array( $country, (array) $c['countries'], true ) ) {
		$suggested[] = $id;
	}
}
$suggested = array_slice( array_unique( array_merge( array_intersect( array( 'google', 'meta' ), array_keys( $channels ) ), $suggested ) ), 0, 3 );
if ( ! $pick || ! isset( $channels[ $pick ] ) ) {
	$pick = $suggested ? $suggested[0] : (string) key( $channels );
}
$groups = array();
foreach ( $channels as $id => $c ) {
	if ( in_array( $id, $suggested, true ) ) {
		continue;
	}
	$groups[ isset( $c['group'] ) ? $c['group'] : __( 'Other', 'product-xml-feeds-for-woocommerce' ) ][] = $id;
}

$choice = function ( $id ) use ( $channels, $pick ) {
	$c = $channels[ $id ];
	echo '<label class="choice"><input type="radio" name="channel" value="' . esc_attr( $id ) . '" data-label="' . esc_attr( $c['label'] ) . '"' . checked( $pick, $id, false ) . ' required>';
	echo '<span><b>' . esc_html( $c['label'] ) . '</b>';
	if ( ! empty( $c['notes'] ) ) {
		echo '<span class="row-meta">' . esc_html( $c['notes'] ) . '</span>';
	}
	if ( 'openai' === $id ) {
		echo Alg_WC_PXF_Admin_UI::chip( __( 'US only, application required', 'product-xml-feeds-for-woocommerce' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</span></label>';
};

Alg_WC_PXF_Admin_UI::page_head(
	'<a href="' . esc_url( Alg_WC_PXF_Admin::url() ) . '">' . esc_html__( 'Feeds', 'product-xml-feeds-for-woocommerce' ) . '</a> / ' . esc_html__( 'New feed', 'product-xml-feeds-for-woocommerce' ),
	__( 'Where do you sell?', 'product-xml-feeds-for-woocommerce' ),
	esc_html__( 'Pick one channel. You can add more feeds later, one per channel or country.', 'product-xml-feeds-for-woocommerce' )
);

if ( ! $channels ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'The channel list is not available right now.', 'product-xml-feeds-for-woocommerce' ), 'bad' );
	return;
}
$default_name = isset( $channels[ $pick ] ) ? $channels[ $pick ]['label'] . ( isset( $countries[ $country ] ) ? ' (' . $countries[ $country ] . ')' : '' ) : '';
?>
<form id="pxf-new" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'alg_wc_pxf_create_feed' ); ?>
	<input type="hidden" name="action" value="alg_wc_pxf_create_feed">
	<div class="steps" aria-hidden="true"><span class="on"><span class="n">1</span><?php esc_html_e( 'Channel', 'product-xml-feeds-for-woocommerce' ); ?></span><span><span class="n">2</span><?php esc_html_e( 'Name and country', 'product-xml-feeds-for-woocommerce' ); ?></span></div>
	<div class="grid">
		<fieldset class="card"><legend class="sr-only"><?php esc_html_e( 'Channel', 'product-xml-feeds-for-woocommerce' ); ?></legend><div class="card-body">
			<?php if ( $suggested ) : ?>
				<h2 class="group-label"><?php
					/* translators: %s: country name. */
					echo esc_html( sprintf( __( 'Suggested for %s', 'product-xml-feeds-for-woocommerce' ), isset( $countries[ $country ] ) ? $countries[ $country ] : $country ) );
				?></h2>
				<div class="choices"><?php array_map( $choice, $suggested ); ?></div>
			<?php endif; ?>
			<?php foreach ( $groups as $group => $ids ) : ?>
				<h2 class="group-label"><?php echo esc_html( $group ); ?></h2>
				<div class="choices"><?php array_map( $choice, $ids ); ?></div>
			<?php endforeach; ?>
		</div></fieldset>

		<section class="card">
			<div class="card-head"><h2 class="card-title"><?php esc_html_e( 'Name and country', 'product-xml-feeds-for-woocommerce' ); ?></h2></div>
			<div class="card-body">
				<div class="field"><label class="label" for="pxf-name"><?php esc_html_e( 'Feed name', 'product-xml-feeds-for-woocommerce' ); ?></label><input id="pxf-name" name="name" class="input" type="text" required value="<?php echo esc_attr( $default_name ); ?>"><p class="hint"><?php esc_html_e( 'Only you see this name.', 'product-xml-feeds-for-woocommerce' ); ?></p></div>
				<div class="field"><label class="label" for="pxf-country"><?php esc_html_e( 'Country you sell to', 'product-xml-feeds-for-woocommerce' ); ?></label>
					<select id="pxf-country" name="country" class="select">
						<?php foreach ( $countries as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"<?php selected( $country, $code ); ?>><?php echo esc_html( html_entity_decode( $name ) ); ?></option>
						<?php endforeach; ?>
					</select></div>
				<div class="fields-2">
					<div class="field"><label class="label" for="pxf-lang"><?php esc_html_e( 'Language', 'product-xml-feeds-for-woocommerce' ); ?></label>
						<select id="pxf-lang" name="language" class="select">
							<option value=""><?php echo esc_html( sprintf( /* translators: %s: locale. */ __( 'Site language (%s)', 'product-xml-feeds-for-woocommerce' ), $lang ) ); ?></option>
							<?php foreach ( array( 'en', 'de', 'fr', 'es', 'it', 'nl', 'pl', 'sv', 'el' ) as $code ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( strtoupper( $code ) ); ?></option>
							<?php endforeach; ?>
						</select></div>
					<div class="field"><label class="label" for="pxf-cur"><?php esc_html_e( 'Currency', 'product-xml-feeds-for-woocommerce' ); ?></label>
						<select id="pxf-cur" name="currency" class="select">
							<option value=""><?php echo esc_html( sprintf( /* translators: %s: currency code. */ __( '%s (shop currency)', 'product-xml-feeds-for-woocommerce' ), $currency ) ); ?></option>
						</select></div>
				</div>
			</div>
			<div class="card-foot"><a class="btn btn-text" href="<?php echo esc_url( Alg_WC_PXF_Admin::url() ); ?>"><?php esc_html_e( 'Cancel', 'product-xml-feeds-for-woocommerce' ); ?></a><button class="btn btn-primary" type="submit"><?php esc_html_e( 'Create feed', 'product-xml-feeds-for-woocommerce' ); ?></button></div>
		</section>
	</div>
</form>
