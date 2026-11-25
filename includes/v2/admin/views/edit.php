<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin view: edit one feed (Fields, Products and filters, Schedule and file).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

$feed_id = isset( $_GET['feed'] ) ? absint( $_GET['feed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'fields'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$feed    = Alg_WC_PXF_Admin::rest( 'GET', '/feeds/' . $feed_id );
if ( is_wp_error( $feed ) || empty( $feed['id'] ) ) {
	Alg_WC_PXF_Admin_UI::banner( esc_html__( 'This feed was not found. It may have been deleted.', 'product-xml-feeds-for-woocommerce' ) . ' <a href="' . esc_url( Alg_WC_PXF_Admin::url() ) . '">' . esc_html__( 'Back to feeds', 'product-xml-feeds-for-woocommerce' ) . '</a>', 'bad' );
	return;
}
$tabs = array(
	'fields'   => __( 'Fields', 'product-xml-feeds-for-woocommerce' ),
	'filters'  => __( 'Products and filters', 'product-xml-feeds-for-woocommerce' ),
	'schedule' => __( 'Schedule and file', 'product-xml-feeds-for-woocommerce' ),
);
$tab      = isset( $tabs[ $tab ] ) ? $tab : 'fields';
$config   = isset( $feed['config'] ) ? (array) $feed['config'] : array();
$state    = isset( $feed['state'] ) ? (array) $feed['state'] : array();
$filters  = isset( $config['filters'] ) ? (array) $config['filters'] : array();
$mapping  = isset( $config['mapping'] ) ? (array) $config['mapping'] : array();
$channels = Alg_WC_PXF_Admin::channels();
$ch_id    = isset( $config['channel'] ) ? $config['channel'] : '';
$ch_label = isset( $channels[ $ch_id ] ) ? $channels[ $ch_id ]['label'] : $ch_id;
$fields   = Alg_WC_PXF_Admin::channel_fields( $ch_id );
$status   = isset( $state['status'] ) ? $state['status'] : 'idle';
$chip     = Alg_WC_PXF_Admin_UI::status( $status, isset( $state['last_good'] ) ? $state['last_good'] : 0 );
$is_pro   = Alg_WC_PXF::is_pro();

// Preview product: the first published product, or the one asked for.
$products = wc_get_products( array( 'limit' => 30, 'status' => 'publish', 'orderby' => 'ID', 'order' => 'ASC', 'type' => array( 'simple', 'variable', 'external' ) ) );
$pv_id    = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : ( $products ? $products[0]->get_id() : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$preview  = $pv_id ? Alg_WC_PXF_Admin::rest( 'GET', '/feeds/' . $feed_id . '/preview', array( 'product' => $pv_id ) ) : array();
$item     = ( is_array( $preview ) && isset( $preview['item'] ) ) ? (array) $preview['item'] : array();
if ( ! $fields ) {
	foreach ( array_keys( $item ) as $key ) {
		$fields[ $key ] = array( 'label' => $key, 'required' => false, 'group' => 'basic' );
	}
}

// Lead line.
$lead = '';
if ( ! empty( $state['last_good'] ) ) {
	$lead = sprintf(
		/* translators: 1: date, 2: number of products, 3: number left out. */
		__( 'Updated %1$s: %2$s products, %3$s left out.', 'product-xml-feeds-for-woocommerce' ),
		esc_html( Alg_WC_PXF_Admin_UI::date( $state['last_good'] ) ),
		esc_html( number_format_i18n( isset( $state['written'] ) ? (int) $state['written'] : 0 ) ),
		esc_html( number_format_i18n( isset( $state['left_out'] ) ? (int) $state['left_out'] : 0 ) )
	) . ' <a href="' . esc_url( Alg_WC_PXF_Admin::url( 'products', array( 'feed' => $feed_id ) ) ) . '">' . esc_html__( 'See why', 'product-xml-feeds-for-woocommerce' ) . '</a>';
} elseif ( 'failed' === $status && ! empty( $state['error'] ) ) {
	$lead = esc_html( $state['error'] );
} elseif ( in_array( $status, array( 'running', 'queued' ), true ) ) {
	$lead = esc_html__( 'The first file is being built now. You can leave this page.', 'product-xml-feeds-for-woocommerce' );
} else {
	$lead = esc_html__( 'Not built yet. Press Update now to build the first file.', 'product-xml-feeds-for-woocommerce' );
}
$busy    = in_array( $status, array( 'running', 'queued' ), true );
$actions = Alg_WC_PXF_Admin_UI::chip( $chip[1], $chip[0], ' data-pxf-chip' ) .
	'<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . wp_nonce_field( 'alg_wc_pxf_run_feed', '_wpnonce', true, false ) .
	'<input type="hidden" name="action" value="alg_wc_pxf_run_feed"><input type="hidden" name="back" value="edit"><input type="hidden" name="feed" value="' . esc_attr( $feed_id ) . '">' .
	'<button class="btn btn-primary" type="submit" data-pxf-run="' . esc_attr( $feed_id ) . '"' . disabled( $busy, true, false ) . '>' . esc_html( $busy ? $chip[1] : __( 'Update now', 'product-xml-feeds-for-woocommerce' ) ) . '</button></form>';

echo '<div data-pxf-feed="' . esc_attr( $feed_id ) . '" data-status="' . esc_attr( $status ) . '">';
Alg_WC_PXF_Admin_UI::page_head(
	'<a href="' . esc_url( Alg_WC_PXF_Admin::url() ) . '">' . esc_html__( 'Feeds', 'product-xml-feeds-for-woocommerce' ) . '</a> / ' . esc_html( $ch_label ),
	isset( $feed['name'] ) ? $feed['name'] : '',
	$lead,
	$actions
);
echo '<div class="progress" data-pxf-progress aria-hidden="true"' . ( $busy ? '' : ' hidden' ) . ' style="max-width:none;margin:-8px 0 16px"><i style="width:0"></i></div></div>';
?>
<section class="card" style="margin-bottom:20px"><div class="card-body">
	<?php echo Alg_WC_PXF_Admin_UI::url_field( 'pxf-feed-url', isset( $feed['url'] ) ? $feed['url'] : '', __( 'Feed link', 'product-xml-feeds-for-woocommerce' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<p class="hint" style="margin-top:8px"><?php esc_html_e( 'Give this link to the channel once. It stays the same after every update.', 'product-xml-feeds-for-woocommerce' ); ?></p>
</div></section>

<nav class="tabs" aria-label="<?php esc_attr_e( 'Feed settings', 'product-xml-feeds-for-woocommerce' ); ?>">
	<?php foreach ( $tabs as $id => $label ) : ?>
		<a href="<?php echo esc_url( Alg_WC_PXF_Admin::url( 'edit', array( 'feed' => $feed_id, 'tab' => $id ) ) ); ?>" data-pxf-tab="<?php echo esc_attr( $id ); ?>"<?php echo $id === $tab ? ' class="on" aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<form id="pxf-edit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-feed="<?php echo esc_attr( $feed_id ); ?>">
<?php wp_nonce_field( 'alg_wc_pxf_save_feed' ); ?>
<input type="hidden" name="action" value="alg_wc_pxf_save_feed"><input type="hidden" name="feed" value="<?php echo esc_attr( $feed_id ); ?>"><input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

<div data-pxf-pane="fields"<?php echo 'fields' === $tab ? '' : ' hidden'; ?>>
<?php
// Source choices for the field picker.
$model_keys = array(
	'id' => __( 'Product ID', 'product-xml-feeds-for-woocommerce' ), 'title' => __( 'Product name', 'product-xml-feeds-for-woocommerce' ),
	'ai_title' => __( 'AI title (approved)', 'product-xml-feeds-for-woocommerce' ), 'description' => __( 'Description', 'product-xml-feeds-for-woocommerce' ),
	'short_description' => __( 'Short description', 'product-xml-feeds-for-woocommerce' ), 'ai_description' => __( 'AI description (approved)', 'product-xml-feeds-for-woocommerce' ),
	'link' => __( 'Product page link', 'product-xml-feeds-for-woocommerce' ), 'image_link' => __( 'Main image', 'product-xml-feeds-for-woocommerce' ),
	'additional_image_links' => __( 'Gallery images', 'product-xml-feeds-for-woocommerce' ), 'price' => __( 'Regular price', 'product-xml-feeds-for-woocommerce' ),
	'sale_price' => __( 'Sale price', 'product-xml-feeds-for-woocommerce' ), 'availability' => __( 'Stock status', 'product-xml-feeds-for-woocommerce' ),
	'stock_quantity' => __( 'Stock quantity', 'product-xml-feeds-for-woocommerce' ), 'condition' => __( 'Condition', 'product-xml-feeds-for-woocommerce' ),
	'gtin' => __( 'GTIN, UPC, EAN or ISBN (WooCommerce)', 'product-xml-feeds-for-woocommerce' ), 'mpn' => __( 'MPN', 'product-xml-feeds-for-woocommerce' ),
	'brand' => __( 'Brand (WooCommerce Brands)', 'product-xml-feeds-for-woocommerce' ), 'google_product_category' => __( 'Google category (category map)', 'product-xml-feeds-for-woocommerce' ),
	'product_type' => __( 'Category path', 'product-xml-feeds-for-woocommerce' ), 'item_group_id' => __( 'Parent ID (variations)', 'product-xml-feeds-for-woocommerce' ),
	'color' => __( 'Color', 'product-xml-feeds-for-woocommerce' ), 'size' => __( 'Size', 'product-xml-feeds-for-woocommerce' ),
	'material' => __( 'Material', 'product-xml-feeds-for-woocommerce' ), 'gender' => __( 'Gender', 'product-xml-feeds-for-woocommerce' ),
	'age_group' => __( 'Age group', 'product-xml-feeds-for-woocommerce' ), 'weight' => __( 'Weight', 'product-xml-feeds-for-woocommerce' ),
);
$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
$groups     = array(
	'basic' => __( 'Basic', 'product-xml-feeds-for-woocommerce' ), 'price' => __( 'Price and stock', 'product-xml-feeds-for-woocommerce' ),
	'category' => __( 'Category', 'product-xml-feeds-for-woocommerce' ), 'identifiers' => __( 'Identifiers', 'product-xml-feeds-for-woocommerce' ),
	'details' => __( 'Details', 'product-xml-feeds-for-woocommerce' ), 'shipping' => __( 'Shipping', 'product-xml-feeds-for-woocommerce' ),
);
$by_group   = array();
$req_total  = 0;
$req_filled = 0;
foreach ( $fields as $key => $f ) {
	$g = isset( $f['group'] ) ? $f['group'] : 'details';
	$by_group[ $g ][ $key ] = $f;
	if ( true === ( isset( $f['required'] ) ? $f['required'] : false ) ) {
		$req_total++;
		if ( ! empty( $mapping[ $key ] ) || ! empty( $f['source'] ) ) {
			$req_filled++;
		}
	}
}
$pct = $req_total ? round( 100 * $req_filled / $req_total ) : 100;
?>
	<div class="grid">
		<div class="stack">
			<section class="card"><div class="card-body">
				<div class="meter-box">
					<div><div class="eyebrow"><?php esc_html_e( 'Required fields', 'product-xml-feeds-for-woocommerce' ); ?></div><div class="meter-num" data-pxf-meter-num><?php echo esc_html( sprintf( /* translators: 1: filled, 2: total. */ __( '%1$s of %2$s filled', 'product-xml-feeds-for-woocommerce' ), $req_filled, $req_total ) ); ?></div></div>
					<div class="meter<?php echo $pct < 100 ? ' warn' : ''; ?>" data-pxf-meter role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: filled, 2: total. */ __( '%1$s of %2$s required fields filled', 'product-xml-feeds-for-woocommerce' ), $req_filled, $req_total ) ); ?>"><i style="width:<?php echo esc_attr( $pct ); ?>%"></i></div>
					<div class="seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Show fields', 'product-xml-feeds-for-woocommerce' ); ?>"><label><input type="radio" name="pxf_show" value="req"><?php esc_html_e( 'Required', 'product-xml-feeds-for-woocommerce' ); ?></label><label><input type="radio" name="pxf_show" value="all" checked><?php esc_html_e( 'All', 'product-xml-feeds-for-woocommerce' ); ?> <span class="muted"><?php echo esc_html( count( $fields ) ); ?></span></label></div>
				</div>
				<p class="hint" style="margin-top:10px"><?php esc_html_e( 'Filled from your products automatically. Change a source only when your data lives somewhere else.', 'product-xml-feeds-for-woocommerce' ); ?></p>
			</div></section>
			<section class="card" aria-label="<?php esc_attr_e( 'Fields', 'product-xml-feeds-for-woocommerce' ); ?>">
			<?php
			foreach ( $by_group as $g => $list ) :
				echo '<h2 class="section-label">' . esc_html( isset( $groups[ $g ] ) ? $groups[ $g ] : ucfirst( $g ) ) . '</h2>';
				foreach ( $list as $key => $f ) :
					$req     = isset( $f['required'] ) ? $f['required'] : false;
					$cur     = isset( $mapping[ $key ] ) ? (array) $mapping[ $key ] : array();
					$cur_src = $cur ? $cur['type'] . ':' . ( 'meta' === $cur['type'] || 'static' === $cur['type'] ? '' : $cur['key'] ) : '';
					$val     = isset( $item[ $key ] ) ? ( is_array( $item[ $key ] ) ? implode( ', ', array_map( 'strval', array_filter( $item[ $key ], 'is_scalar' ) ) ) : (string) $item[ $key ] ) : '';
					$sid     = 'pxf-src-' . $key;
					$default = ! empty( $f['source']['key'] ) && isset( $model_keys[ $f['source']['key'] ] ) ? $model_keys[ $f['source']['key'] ] : __( 'none', 'product-xml-feeds-for-woocommerce' );
					?>
					<div class="map" data-field="<?php echo esc_attr( $key ); ?>" data-req="<?php echo true === $req ? '1' : '0'; ?>" data-default="<?php echo empty( $f['source'] ) ? '0' : '1'; ?>">
						<div class="fname"><?php echo esc_html( $f['label'] ); ?> <span class="fkey"><?php echo esc_html( $key ); ?></span>
							<?php
							if ( true === $req ) {
								echo Alg_WC_PXF_Admin_UI::chip( __( 'Required', 'product-xml-feeds-for-woocommerce' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} elseif ( 'recommended' === $req || 'conditional' === $req ) {
								echo Alg_WC_PXF_Admin_UI::chip( 'recommended' === $req ? __( 'Recommended', 'product-xml-feeds-for-woocommerce' ) : __( 'Sometimes required', 'product-xml-feeds-for-woocommerce' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						</div>
						<div class="src">
							<label class="sr-only" for="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( sprintf( /* translators: %s: field name. */ __( 'Source for %s', 'product-xml-feeds-for-woocommerce' ), $f['label'] ) ); ?></label>
							<select id="<?php echo esc_attr( $sid ); ?>" class="select" name="config[mapping][<?php echo esc_attr( $key ); ?>][source]" data-pxf-source>
								<option value=""><?php echo esc_html( sprintf( /* translators: %s: default source. */ __( 'Automatic: %s', 'product-xml-feeds-for-woocommerce' ), $default ) ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Product data', 'product-xml-feeds-for-woocommerce' ); ?>">
									<?php foreach ( $model_keys as $mk => $ml ) : ?>
										<option value="field:<?php echo esc_attr( $mk ); ?>"<?php selected( $cur_src, 'field:' . $mk ); ?>><?php echo esc_html( $ml ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php if ( $attributes ) : ?>
								<optgroup label="<?php esc_attr_e( 'Attributes', 'product-xml-feeds-for-woocommerce' ); ?>">
									<?php foreach ( $attributes as $a ) : ?>
										<option value="attribute:pa_<?php echo esc_attr( $a->attribute_name ); ?>"<?php selected( $cur_src, 'attribute:pa_' . $a->attribute_name ); ?>><?php echo esc_html( $a->attribute_label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
								<?php endif; ?>
								<optgroup label="<?php esc_attr_e( 'Other', 'product-xml-feeds-for-woocommerce' ); ?>">
									<option value="meta:"<?php selected( $cur_src, 'meta:' ); ?>><?php esc_html_e( 'Custom field', 'product-xml-feeds-for-woocommerce' ); ?></option>
									<option value="static:"<?php selected( $cur_src, 'static:' ); ?>><?php esc_html_e( 'Fixed value', 'product-xml-feeds-for-woocommerce' ); ?></option>
								</optgroup>
							</select>
							<label class="sr-only" for="<?php echo esc_attr( $sid ); ?>-key"><?php esc_html_e( 'Custom field name', 'product-xml-feeds-for-woocommerce' ); ?></label>
							<input id="<?php echo esc_attr( $sid ); ?>-key" class="input" type="text" placeholder="<?php esc_attr_e( 'Custom field name, for example _ean', 'product-xml-feeds-for-woocommerce' ); ?>" name="config[mapping][<?php echo esc_attr( $key ); ?>][key]" value="<?php echo esc_attr( isset( $cur['key'] ) && 'meta' === $cur['type'] ? $cur['key'] : '' ); ?>" data-pxf-for="meta"<?php echo 'meta:' === $cur_src ? '' : ' hidden'; ?>>
							<label class="sr-only" for="<?php echo esc_attr( $sid ); ?>-val"><?php esc_html_e( 'Fixed value', 'product-xml-feeds-for-woocommerce' ); ?></label>
							<input id="<?php echo esc_attr( $sid ); ?>-val" class="input" type="text" placeholder="<?php esc_attr_e( 'Same value for every product', 'product-xml-feeds-for-woocommerce' ); ?>" name="config[mapping][<?php echo esc_attr( $key ); ?>][value]" value="<?php echo esc_attr( isset( $cur['value'] ) ? $cur['value'] : '' ); ?>" data-pxf-for="static"<?php echo 'static:' === $cur_src ? '' : ' hidden'; ?>>
						</div>
						<div class="val<?php echo '' === $val && true === $req ? ' missing' : ''; ?>" data-pxf-val title="<?php echo esc_attr( $val ); ?>"><?php echo '' === $val ? esc_html__( 'empty', 'product-xml-feeds-for-woocommerce' ) : esc_html( $val ); ?></div>
					</div>
					<?php
				endforeach;
			endforeach;
			?>
			</section>
		</div>
		<section class="card" id="pxf-preview" aria-live="polite">
			<div class="card-head"><h2 class="card-title"><?php esc_html_e( 'Preview', 'product-xml-feeds-for-woocommerce' ); ?></h2>
				<label class="sr-only" for="pxf-pv"><?php esc_html_e( 'Preview product', 'product-xml-feeds-for-woocommerce' ); ?></label>
				<select id="pxf-pv" class="select" style="width:auto;max-width:220px" data-pxf-preview>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p->get_id() ); ?>"<?php selected( $pv_id, $p->get_id() ); ?>><?php echo esc_html( wp_strip_all_tags( $p->get_name() ) ); ?></option>
					<?php endforeach; ?>
				</select></div>
			<div class="card-body">
				<pre class="xml" data-pxf-xml><?php
				foreach ( $item as $k => $v ) {
					echo esc_html( '<' . $k . '>' . ( is_array( $v ) ? wp_json_encode( $v ) : $v ) . '</' . $k . '>' ) . "\n";
				}
				?></pre>
				<ul class="issues" data-pxf-issues>
					<?php
					$pv_issues = ( is_array( $preview ) && ! empty( $preview['issues'] ) ) ? $preview['issues'] : array();
					foreach ( $pv_issues as $issue ) {
						$bad = isset( $issue['severity'] ) && 'error' === $issue['severity'];
						echo '<li>' . Alg_WC_PXF_Admin_UI::chip( $bad ? __( 'Error', 'product-xml-feeds-for-woocommerce' ) : __( 'Warning', 'product-xml-feeds-for-woocommerce' ), $bad ? 'bad' : 'warn' ) . '<span>' . esc_html( $issue['message'] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					if ( ! $pv_issues ) {
						echo '<li class="muted">' . esc_html__( 'No problems for this product.', 'product-xml-feeds-for-woocommerce' ) . '</li>';
					}
					?>
				</ul>
			</div>
		</section>
	</div>
</div>

<div data-pxf-pane="filters"<?php echo 'filters' === $tab ? '' : ' hidden'; ?>>
<section class="card">
	<?php
	$stock  = isset( $filters['stock'] ) ? (array) $filters['stock'] : array();
	$checks = '<input type="hidden" name="config[filters][stock]" value="">';
	foreach ( array( 'instock' => __( 'In stock', 'product-xml-feeds-for-woocommerce' ), 'onbackorder' => __( 'On backorder', 'product-xml-feeds-for-woocommerce' ), 'outofstock' => __( 'Out of stock', 'product-xml-feeds-for-woocommerce' ) ) as $k => $l ) {
		$checks .= '<label><input type="checkbox" name="config[filters][stock][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $stock, true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Stock status', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Leave all empty to send every product.', 'product-xml-feeds-for-woocommerce' ), '<div class="checks">' . $checks . '</div>' );
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	foreach ( array( 'include_cats' => __( 'Only these categories', 'product-xml-feeds-for-woocommerce' ), 'exclude_cats' => __( 'Leave out categories', 'product-xml-feeds-for-woocommerce' ) ) as $k => $l ) {
		$sel  = isset( $filters[ $k ] ) ? array_map( 'intval', (array) $filters[ $k ] ) : array();
		$ctl  = '<input type="hidden" name="config[filters][' . esc_attr( $k ) . ']" value=""><select multiple size="4" class="select" style="height:auto;background-image:none;padding:6px" id="pxf-' . esc_attr( $k ) . '" name="config[filters][' . esc_attr( $k ) . '][]">';
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			$ctl .= '<option value="' . esc_attr( $t->term_id ) . '"' . selected( in_array( (int) $t->term_id, $sel, true ), true, false ) . '>' . esc_html( $t->name ) . '</option>';
		}
		$ctl .= '</select>';
		Alg_WC_PXF_Admin_UI::setting( $l, 'include_cats' === $k ? esc_html__( 'Leave empty for all categories.', 'product-xml-feeds-for-woocommerce' ) : '', $ctl, 'pxf-' . $k );
	}
	Alg_WC_PXF_Admin_UI::setting(
		__( 'Price between', 'product-xml-feeds-for-woocommerce' ),
		'',
		'<label class="sr-only" for="pxf-min">' . esc_html__( 'Lowest price', 'product-xml-feeds-for-woocommerce' ) . '</label><input id="pxf-min" class="input" style="flex:0 1 110px" type="text" inputmode="decimal" placeholder="0.00" name="config[filters][min_price]" value="' . esc_attr( isset( $filters['min_price'] ) ? $filters['min_price'] : '' ) . '"><span class="muted">' . esc_html__( 'and', 'product-xml-feeds-for-woocommerce' ) . '</span>' .
		'<label class="sr-only" for="pxf-max">' . esc_html__( 'Highest price', 'product-xml-feeds-for-woocommerce' ) . '</label><input id="pxf-max" class="input" style="flex:0 1 110px" type="text" inputmode="decimal" placeholder="' . esc_attr__( 'any', 'product-xml-feeds-for-woocommerce' ) . '" name="config[filters][max_price]" value="' . esc_attr( isset( $filters['max_price'] ) ? $filters['max_price'] : '' ) . '">'
	);
	$on_sale = isset( $filters['on_sale'] ) ? $filters['on_sale'] : '';
	$ctl     = '<select id="pxf-sale" class="select" name="config[filters][on_sale]">';
	foreach ( array( '' => __( 'All products', 'product-xml-feeds-for-woocommerce' ), 'yes' => __( 'Only products on sale', 'product-xml-feeds-for-woocommerce' ), 'no' => __( 'Only products not on sale', 'product-xml-feeds-for-woocommerce' ) ) as $k => $l ) {
		$ctl .= '<option value="' . esc_attr( $k ) . '"' . selected( $on_sale, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Sale', 'product-xml-feeds-for-woocommerce' ), '', $ctl . '</select>', 'pxf-sale' );
	Alg_WC_PXF_Admin_UI::setting( __( 'Leave out products', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Product IDs, separated by commas.', 'product-xml-feeds-for-woocommerce' ), '<input id="pxf-xids" class="input" type="text" name="config[filters][exclude_ids]" value="' . esc_attr( implode( ', ', isset( $filters['exclude_ids'] ) ? (array) $filters['exclude_ids'] : array() ) ) . '">', 'pxf-xids' );
	$vm  = isset( $config['variation_mode'] ) ? $config['variation_mode'] : '';
	$ctl = '<select id="pxf-var" class="select" name="config[variation_mode]">';
	foreach ( array( '' => __( 'What the channel wants (recommended)', 'product-xml-feeds-for-woocommerce' ), 'all' => __( 'Every variation', 'product-xml-feeds-for-woocommerce' ), 'parent' => __( 'Main product only', 'product-xml-feeds-for-woocommerce' ), 'default' => __( 'Default variation only', 'product-xml-feeds-for-woocommerce' ), 'cheapest' => __( 'Cheapest variation only', 'product-xml-feeds-for-woocommerce' ) ) as $k => $l ) {
		$ctl .= '<option value="' . esc_attr( $k ) . '"' . selected( $vm, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Variations', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Google wants every variation with its own color and size.', 'product-xml-feeds-for-woocommerce' ), $ctl . '</select>', 'pxf-var' );
	?>
</section>
</div>

<div data-pxf-pane="schedule"<?php echo 'schedule' === $tab ? '' : ' hidden'; ?>>
<section class="card">
	<?php
	$schedules = array( 'daily' => __( 'Every day', 'product-xml-feeds-for-woocommerce' ), 'manual' => __( 'Only when I press Update now', 'product-xml-feeds-for-woocommerce' ) );
	if ( $is_pro ) {
		$schedules += array( 'twicedaily' => __( 'Twice a day', 'product-xml-feeds-for-woocommerce' ), 'hourly' => __( 'Every hour', 'product-xml-feeds-for-woocommerce' ), '15min' => __( 'Every 15 minutes', 'product-xml-feeds-for-woocommerce' ) );
	}
	$cur = isset( $config['schedule'] ) ? $config['schedule'] : 'daily';
	$ctl = '<select id="pxf-sch" class="select" name="config[schedule]">';
	foreach ( $schedules as $k => $l ) {
		$ctl .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Update the feed', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Runs in the background in small batches.', 'product-xml-feeds-for-woocommerce' ), $ctl . '</select>', 'pxf-sch' );
	if ( $is_pro ) {
		Alg_WC_PXF_Admin_UI::setting( __( 'Update when a product changes', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'At most every 10 minutes.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'config[rebuild_on_change]', ! empty( $config['rebuild_on_change'] ), __( 'Update when a product changes', 'product-xml-feeds-for-woocommerce' ) ) );
		Alg_WC_PXF_Admin_UI::setting( __( 'Leave out products with errors', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Keeps the channel from rejecting the whole feed.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'config[leave_out_errors]', ! empty( $config['leave_out_errors'] ), __( 'Leave out products with errors', 'product-xml-feeds-for-woocommerce' ) ) );
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Use approved AI titles', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Only titles you approved on AI review. Your product pages do not change.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'config[use_ai_titles]', ! empty( $config['use_ai_titles'] ), __( 'Use approved AI titles', 'product-xml-feeds-for-woocommerce' ) ) );
	Alg_WC_PXF_Admin_UI::setting( __( 'Use approved AI descriptions', 'product-xml-feeds-for-woocommerce' ), '', Alg_WC_PXF_Admin_UI::toggle( 'config[use_ai_descriptions]', ! empty( $config['use_ai_descriptions'] ), __( 'Use approved AI descriptions', 'product-xml-feeds-for-woocommerce' ) ) );
	$formats = isset( $channels[ $ch_id ]['formats'] ) ? (array) $channels[ $ch_id ]['formats'] : array();
	$format  = ! empty( $config['format'] ) ? $config['format'] : ( $formats ? $formats[0] : 'xml' );
	Alg_WC_PXF_Admin_UI::setting( __( 'File name', 'product-xml-feeds-for-woocommerce' ), '', '<input id="pxf-slug" class="input" type="text" name="config[file_slug]" value="' . esc_attr( isset( $config['file_slug'] ) ? $config['file_slug'] : '' ) . '"><span class="muted">.' . esc_html( $format ) . '</span>', 'pxf-slug' );
	if ( count( $formats ) > 1 ) {
		$ctl = '<select id="pxf-format" class="select" name="config[format]">';
		foreach ( $formats as $f ) {
			$ctl .= '<option value="' . esc_attr( $f ) . '"' . selected( $format, $f, false ) . '>' . esc_html( strtoupper( $f ) ) . '</option>';
		}
		Alg_WC_PXF_Admin_UI::setting( __( 'File type', 'product-xml-feeds-for-woocommerce' ), '', $ctl . '</select>', 'pxf-format' );
	}
	Alg_WC_PXF_Admin_UI::setting( __( 'Compress the file (gzip)', 'product-xml-feeds-for-woocommerce' ), esc_html__( 'Only when the channel asks for it.', 'product-xml-feeds-for-woocommerce' ), Alg_WC_PXF_Admin_UI::toggle( 'config[gzip]', ! empty( $config['gzip'] ), __( 'Compress the file (gzip)', 'product-xml-feeds-for-woocommerce' ) ) );
	?>
</section>
<?php if ( ! $is_pro ) : ?>
	<p class="hint" style="margin-top:12px"><a href="<?php echo esc_url( Alg_WC_PXF_Admin::url( 'pro' ) ); ?>"><?php esc_html_e( 'Faster updates and more: Pro features', 'product-xml-feeds-for-woocommerce' ); ?></a></p>
<?php endif; ?>
</div>

<div class="savebar"><button class="btn btn-danger" type="submit" name="pxf_delete" value="1" data-pxf-delete formnovalidate><?php esc_html_e( 'Delete feed', 'product-xml-feeds-for-woocommerce' ); ?></button><span class="small muted" data-pxf-savestate style="margin-left:auto"></span><button class="btn btn-primary" type="submit"><?php esc_html_e( 'Save', 'product-xml-feeds-for-woocommerce' ); ?></button></div>
</form>
