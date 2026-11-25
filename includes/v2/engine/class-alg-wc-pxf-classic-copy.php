<?php
/**
 * Product XML Feeds for WooCommerce - v2 engine: copy a classic feed into a new "Custom template" feed.
 *
 * Reads the classic options (never writes them): the classic feed, its file, link and schedule stay exactly as they
 * are. The new feed gets the header, item and footer templates plus the filters that have a match in the new feeds,
 * and its own file under uploads/product-feeds. Classic settings with no match are reported back, so the merchant
 * knows what to check.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Classic_Copy' ) ) :

class Alg_WC_PXF_Classic_Copy {

	/**
	 * Feed meta: the classic feed number a feed was copied from.
	 */
	const META_FROM = '_alg_pxf_copied_from';

	/**
	 * total: classic feeds on this site. Pro: 1 to total files; free: feed 1 (same as the classic screens).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  int
	 */
	static function total() {
		$total = Alg_WC_PXF::is_pro() ? (int) get_option( 'alg_products_xml_total_files', 1 ) : 1;
		return max( 1, min( 500, $total ) );
	}

	/**
	 * value: a classic value that only Pro applies, read the way the classic generator reads it.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $key     Value key.
	 * @param   int    $n       Classic feed number.
	 * @param   mixed  $default Default.
	 * @return  mixed
	 */
	static function value( $key, $n, $default ) {
		return apply_filters( 'alg_wc_product_xml_feeds_values', $default, $key, $n );
	}

	/**
	 * ids: IDs from a multiselect value or a comma list.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  int[]
	 */
	static function ids( $value ) {
		$list = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		return array_values( array_unique( array_filter( array_map( 'absint', $list ) ) ) );
	}

	/**
	 * template: a classic template part as stored (the classic default when never saved).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $part header, item or footer.
	 * @param   int    $n    Classic feed number.
	 * @return  string
	 */
	static function template( $part, $n ) {
		$core    = ( function_exists( 'alg_wc_product_xml_feeds' ) && isset( alg_wc_product_xml_feeds()->core ) ? alg_wc_product_xml_feeds()->core : null );
		$default = ( $core && is_callable( array( $core, 'get_default_template' ) ) ? (string) $core->get_default_template( $part ) : '' );
		return (string) get_option( 'alg_products_xml_' . $part . '_' . $n, $default );
	}

	/**
	 * config: the new feed config built from classic feed $n.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $n Classic feed number.
	 * @return  array
	 */
	static function config( $n ) {
		$scope = get_option( 'alg_products_xml_scope_' . $n, 'all' );
		$stock = array_values( array_intersect( (array) self::value( 'stock_status', $n, array() ), array( 'instock', 'onbackorder', 'outofstock' ) ) );
		$path  = (string) get_option( 'alg_products_xml_file_path_' . $n, ( 1 === $n ? 'products.xml' : 'products_' . $n . '.xml' ) );
		$slug  = sanitize_title( pathinfo( $path, PATHINFO_FILENAME ) );
		return array(
			'format'          => 'xml',
			'language'        => (string) get_option( 'alg_products_xml_lang_' . $n, '' ),
			'variation_mode'  => 'variations_only' === get_option( 'alg_products_xml_variable_' . $n, 'variable_only' ) ? 'all' : 'parent',
			'file_slug'       => ( '' !== $slug ? $slug : 'products' ) . '-copy',
			'filters'         => array(
				'stock'        => $stock,
				'include_cats' => self::ids( get_option( 'alg_products_xml_cats_incl_' . $n, '' ) ),
				'exclude_cats' => self::ids( get_option( 'alg_products_xml_cats_excl_' . $n, '' ) ),
				'include_tags' => self::ids( get_option( 'alg_products_xml_tags_incl_' . $n, '' ) ),
				'exclude_tags' => self::ids( get_option( 'alg_products_xml_tags_excl_' . $n, '' ) ),
				'exclude_ids'  => self::ids( get_option( 'alg_products_xml_products_excl_' . $n, '' ) ),
				'min_price'    => (string) self::value( 'min_price', $n, '' ),
				'max_price'    => (string) self::value( 'max_price', $n, '' ),
				'on_sale'      => ( 'sale_only' === $scope ? 'yes' : ( 'not_sale_only' === $scope ? 'no' : '' ) ),
			),
			'custom_template' => array(
				'header' => self::template( 'header', $n ),
				'item'   => self::template( 'item', $n ),
				'footer' => self::template( 'footer', $n ),
			),
		);
	}

	/**
	 * skipped: classic settings of feed $n that are in use and have no match in the new feed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $n Classic feed number.
	 * @return  string[] Keys of labels().
	 */
	static function skipped( $n ) {
		$out    = array();
		$checks = array(
			'products_incl'      => array() !== self::ids( get_option( 'alg_products_xml_products_incl_' . $n, '' ) ),
			'product_types'      => array() !== array_filter( (array) self::value( 'product_type_include', $n, array() ) ),
			'featured'           => in_array( get_option( 'alg_products_xml_scope_' . $n, 'all' ), array( 'featured_only', 'not_featured_only' ), true ),
			'offset'             => absint( get_option( 'alg_products_xml_offset_' . $n, 0 ) ) > 0,
			'total_products'     => absint( get_option( 'alg_products_xml_total_products_' . $n, 0 ) ) > 0,
			'sorting'            => 'date' !== get_option( 'alg_products_xml_orderby_' . $n, 'date' ) || 'DESC' !== get_option( 'alg_products_xml_order_' . $n, 'DESC' ),
			'status'             => array( 'publish' ) !== array_values( (array) get_option( 'alg_products_xml_products_status_' . $n, array( 'publish' ) ) ),
			'variations_both'    => 'both' === get_option( 'alg_products_xml_variable_' . $n, 'variable_only' ),
			'tags_if_empty'      => '' !== trim( (string) get_option( 'alg_products_xml_tags_if_empty_' . $n, '' ) ),
			'text_feed'          => 'yes' === self::value( 'create_text_feed', $n, 'no' ),
			'catalog_visibility' => ! empty( self::value( 'catalog_visibility', $n, '' ) ),
			'custom_taxonomy'    => ! empty( self::value( 'custom_taxonomy_in', $n, '' ) ),
			'attribute'          => ! empty( self::value( 'attribute_in', $n, '' ) ),
			'custom_meta'        => ! empty( self::value( 'custom_meta_incl', $n, '' ) ),
		);
		foreach ( $checks as $key => $in_use ) {
			if ( $in_use ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * labels: plain words for skipped().
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function labels() {
		return array(
			'products_incl'      => __( 'Products to include', 'product-xml-feeds-for-woocommerce' ),
			'product_types'      => __( 'Product types', 'product-xml-feeds-for-woocommerce' ),
			'featured'           => __( 'Featured products only', 'product-xml-feeds-for-woocommerce' ),
			'offset'             => __( 'Offset products', 'product-xml-feeds-for-woocommerce' ),
			'total_products'     => __( 'Total products', 'product-xml-feeds-for-woocommerce' ),
			'sorting'            => __( 'Sorting', 'product-xml-feeds-for-woocommerce' ),
			'status'             => __( 'Product status', 'product-xml-feeds-for-woocommerce' ),
			'variations_both'    => __( 'Variable products with their variations', 'product-xml-feeds-for-woocommerce' ),
			'tags_if_empty'      => __( 'Remove empty tags', 'product-xml-feeds-for-woocommerce' ),
			'text_feed'          => __( 'Text feed', 'product-xml-feeds-for-woocommerce' ),
			'catalog_visibility' => __( 'Catalog visibility', 'product-xml-feeds-for-woocommerce' ),
			'custom_taxonomy'    => __( 'Custom taxonomy', 'product-xml-feeds-for-woocommerce' ),
			'attribute'          => __( 'Attribute filter', 'product-xml-feeds-for-woocommerce' ),
			'custom_meta'        => __( 'Custom meta filter', 'product-xml-feeds-for-woocommerce' ),
		);
	}

	/**
	 * copy: a new "Custom template" feed from classic feed $n. The classic feed is not touched.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $n Classic feed number.
	 * @return  array|WP_Error array( 'id' => new feed id, 'skipped' => keys of labels() ).
	 */
	static function copy( $n ) {
		$n = absint( $n );
		if ( $n < 1 || $n > self::total() ) {
			return new WP_Error( 'alg_pxf_no_classic', __( 'This classic feed does not exist.', 'product-xml-feeds-for-woocommerce' ), array( 'status' => 404 ) );
		}
		/* translators: %d: Feed number. */
		$title   = (string) get_option( 'alg_products_xml_feed_title_' . $n, sprintf( __( 'XML Feed #%d', 'product-xml-feeds-for-woocommerce' ), $n ) );
		$config  = self::config( $n );
		$feed_id = Alg_WC_PXF_Feeds::create( array_merge( $config, array(
			/* translators: %s: classic feed name. */
			'name'    => sprintf( __( '%s (copy)', 'product-xml-feeds-for-woocommerce' ), $title ),
			'channel' => 'custom',
		) ) );
		if ( is_wp_error( $feed_id ) ) {
			return $feed_id;
		}
		update_post_meta( $feed_id, self::META_FROM, $n );
		return array(
			'id'      => (int) $feed_id,
			'skipped' => self::skipped( $n ),
		);
	}

}

endif;
