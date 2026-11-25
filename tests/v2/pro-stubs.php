<?php
/**
 * Product XML Feeds for WooCommerce - TEST ONLY stubs for the v2 product model and mapper.
 *
 * Loaded by a mu-plugin on a worker's own test site (at plugins_loaded, priority 99), never by plugin code. Each stub is
 * defined only when the real class is missing, and only does what the Pro tests need: build a small model, map it to
 * a small item, and fire the contract filters 'alg_wc_pxf_model' and 'alg_wc_pxf_item'.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Product_Data' ) ) :

/**
 * Test stub: Alg_WC_PXF_Product_Data.
 */
class Alg_WC_PXF_Product_Data {

	/**
	 * model (stub).
	 *
	 * @param   WC_Product $product Product.
	 * @param   array      $config  Feed config.
	 * @return  array
	 */
	static function model( WC_Product $product, array $config ) {
		$attributes = array();
		foreach ( $product->get_attributes() as $key => $attribute ) {
			$name                = ( is_a( $attribute, 'WC_Product_Attribute' ) ? wc_attribute_label( $attribute->get_name() ) : wc_attribute_label( $key ) );
			$attributes[ $name ] = $product->get_attribute( $key );
		}
		$regular = $product->get_regular_price( 'edit' );
		$sale    = ( $product->is_on_sale( 'edit' ) ? $product->get_sale_price( 'edit' ) : '' );
		$model   = array(
			'id'                        => (string) $product->get_id(),
			'item_group_id'             => ( $product->get_parent_id() ? (string) $product->get_parent_id() : '' ),
			'title'                     => $product->get_name(),
			'link'                      => get_permalink( $product->get_id() ),
			'price'                     => ( '' === $regular ? '' : number_format( (float) wc_get_price_to_display( $product, array( 'price' => $regular ) ), 2, '.', '' ) ),
			'sale_price'                => ( '' === $sale ? '' : number_format( (float) wc_get_price_to_display( $product, array( 'price' => $sale ) ), 2, '.', '' ) ),
			'sale_price_effective_date' => '',
			'currency'                  => get_woocommerce_currency(),
			'availability'              => ( $product->is_in_stock() ? 'in_stock' : 'out_of_stock' ),
			'stock_quantity'            => ( null === $product->get_stock_quantity() ? '' : (int) $product->get_stock_quantity() ),
			'attributes'                => $attributes,
			'type'                      => $product->get_type(),
			'lowest_price_30d'          => '',
			'unit_pricing_measure'      => '',
		);
		return apply_filters( 'alg_wc_pxf_model', $model, $product, $config );
	}

}

endif;

if ( ! class_exists( 'Alg_WC_PXF_Mapper' ) ) :

/**
 * Test stub: Alg_WC_PXF_Mapper.
 */
class Alg_WC_PXF_Mapper {

	/**
	 * item (stub).
	 *
	 * @param   array $model   Model.
	 * @param   array $channel Channel spec.
	 * @param   array $config  Feed config.
	 * @return  array
	 */
	static function item( array $model, array $channel, array $config ) {
		$channel_id = ( isset( $channel['id'] ) ? $channel['id'] : 'google' );
		$currency   = ( isset( $model['currency'] ) ? $model['currency'] : '' );
		if ( 'idealo' === $channel_id ) {
			$item = array(
				'sku'   => $model['id'],
				'title' => $model['title'],
				'url'   => $model['link'],
				'price' => ( '' !== $model['sale_price'] ? $model['sale_price'] : $model['price'] ),
			);
		} else {
			$item = array(
				'id'           => $model['id'],
				'title'        => $model['title'],
				'link'         => $model['link'],
				'availability' => $model['availability'],
				'price'        => ( '' === $model['price'] ? '' : $model['price'] . ' ' . $currency ),
				'sale_price'   => ( '' === $model['sale_price'] ? '' : $model['sale_price'] . ' ' . $currency ),
			);
		}
		return apply_filters( 'alg_wc_pxf_item', $item, $model, $channel, $config );
	}

}

endif;
