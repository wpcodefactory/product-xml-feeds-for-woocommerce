<?php
/**
 * Product XML Feeds for WooCommerce - Products Shortcodes
 *
 * @version 1.5.4
 * @since   1.0.0
 * @author  WPWhale
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Alg_Products_Shortcodes' ) ) :

class Alg_Products_Shortcodes extends Alg_Shortcodes {

	/**
	 * Constructor.
	 *
	 * @version 1.5.1
	 * @since   1.0.0
	 * @todo    [dev] recheck shortcodes and atts
	 */
	function __construct() {

		$this->the_shortcodes = array(
			'alg_product_available_variations',
			'alg_product_average_rating',
			'alg_product_categories',
			'alg_product_categories_names',
			'alg_product_categories_urls',
			'alg_product_custom_field',
			'alg_product_description',
			'alg_product_dimensions',
			'alg_product_excerpt',
			'alg_product_formatted_name',
			'alg_product_function',
			'alg_product_gallery_image_url',
			'alg_product_height',
			'alg_product_id',
			'alg_product_image_url',
			'alg_product_length',
			'alg_product_list_attribute',
			'alg_product_list_attributes',
			'alg_product_meta',
			'alg_product_name',
			'alg_product_price',
			'alg_product_price_excluding_tax',
			'alg_product_price_including_tax',
			'alg_product_regular_price',
			'alg_product_sale_price',
			'alg_product_shipping_class',
			'alg_product_short_description',
			'alg_product_sku',
			'alg_product_stock_availability',
			'alg_product_stock_quantity',
			'alg_product_tags',
			'alg_product_tax_class',
			'alg_product_terms',
			'alg_product_time_since_last_sale',
			'alg_product_title',
			'alg_product_total_sales',
			'alg_product_type',
			'alg_product_url',
			'alg_product_variation_data',
			'alg_product_variation_meta',
			'alg_product_weight',
			'alg_product_width',
			'alg_product_you_save',
			'alg_product_you_save_percent',
		);

		$this->the_atts = array(
			'add_links'             => 'yes',
			'apply_filters'         => 'no',
			'days_to_cover'         => 90,
			'hide_currency'         => 'yes',
			'hide_if_no_sales'      => 'no',
			'hide_if_zero'          => 'no',
			'image_size'            => 'shop_thumbnail',
			'length'                => 0,
			'multiply_by'           => '',
			'name'                  => '',
			'offset'                => '',
			'order_status'          => 'wc-completed',
			'precision'             => 2,
			'product_id'            => 0,
			'reverse'               => 'no',
			'round'                 => 'no',
			'sep'                   => ', ',
			'show_always'           => 'yes',
			'taxonomy'              => '',
			'to_unit'               => '',
			'use_parent_id'         => 'no',
		);

		$this->is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );

		parent::__construct();
	}

	/**
	 * Inits shortcode atts and properties.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 * @param   array $atts Shortcode atts.
	 * @return  array The (modified) shortcode atts.
	 */
	function init_atts( $atts ) {

		// Atts
		if ( 0 == $atts['product_id'] ) {
			global $alg_product_id_for_shortcode;
			$atts['product_id'] = ( 0 != $alg_product_id_for_shortcode ? $alg_product_id_for_shortcode : get_the_ID() );
			if ( 0 == $atts['product_id'] ) {
				return false;
			}
		}

		// Checking post type
		$the_post_type = get_post_type( $atts['product_id'] );
		if ( 'product' !== $the_post_type && 'product_variation' !== $the_post_type ) {
			return false;
		}

		// Class properties
		$this->the_product = wc_get_product( $atts['product_id'] );
		if ( ! $this->the_product ) {
			return false;
		}

		// Maybe get parent product and ID
		if ( 'yes' === $atts['use_parent_id'] && 'product_variation' === $the_post_type ) {
			$atts['product_id'] = $this->the_product->get_parent_id();
			$this->the_product  = wc_get_product( $atts['product_id'] );
			if ( ! $this->the_product ) {
				return false;
			}
		}

		return $atts;
	}

	/**
	 * get_product_id.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_id( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->id : $_product->get_id() );
	}

	/**
	 * get_product_or_variation_parent_id.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_or_variation_parent_id( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->id : ( $_product->is_type( 'variation' ) ? $_product->get_parent_id() : $_product->get_id() ) );
	}

	/**
	 * get_product_short_description
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_short_description( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->post->post_excerpt : $_product->get_short_description() );
	}

	/**
	 * get_product_price_including_tax.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_price_including_tax( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->get_price_including_tax() : wc_get_price_including_tax( $_product ) );
	}

	/**
	 * get_product_price_excluding_tax.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_price_excluding_tax( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->get_price_excluding_tax() : wc_get_price_excluding_tax( $_product ) );
	}

	/**
	 * get_product_tags.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_tags( $_product, $sep ) {
		return ( $this->is_wc_version_below_3 ? $_product->get_tags( $sep ) : wc_get_product_tag_list( $_product->get_id(), $sep ) );
	}

	/**
	 * get_product_categories.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_categories( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->get_categories() : wc_get_product_category_list( $_product->get_id() ) );
	}

	/**
	 * get_product_dimensions.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function get_product_dimensions( $_product ) {
		return ( $this->is_wc_version_below_3 ? $_product->get_dimensions() : wc_format_dimensions( $_product->get_dimensions( false ) ) );
	}

	/**
	 * list_product_attributes.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function list_product_attributes( $_product ) {
		if ( $this->is_wc_version_below_3 ) {
			$_product->list_attributes();
		} else {
			wc_display_product_attributes( $_product );
		}
	}

	/**
	 * get_matching_product_variation.
	 *
	 * @version 1.5.1
	 * @since   1.5.1
	 */
	function get_matching_product_variation( $atts, $product ) {
		if ( ! isset( $atts['match_attributes'] ) || ! $product->is_type( 'variable' ) ) {
			return false;
		}
		$is_global_attribute = ( ! isset( $atts['is_global_attribute'] ) || 'yes' === $atts['is_global_attribute'] );
		$match_attributes    = array();
		$attributes_data     = array_map( 'trim', explode( ',', $atts['match_attributes'] ) );
		foreach ( $attributes_data as $attribute_data ) {
			$attribute_data = array_map( 'trim', explode( '=', $attribute_data ) );
			if ( 2 != count( $attribute_data ) ) {
				return false;
			}
			$key   = 'attribute_' . ( $is_global_attribute ? 'pa_' : '' ) . $attribute_data[0];
			$value = $attribute_data[1];
			if ( '%current%' === $value ) {
				$value = $this->the_product->get_attribute( $attribute_data[0] );
				if ( $is_global_attribute ) {
					$value = sanitize_title( $value );
				}
			}
			$match_attributes[ $key ] = $value;
		}
		if ( empty( $match_attributes ) ) {
			return false;
		}
		$data_store = WC_Data_Store::load( 'product' );
		return $data_store->find_matching_product_variation( $product, $match_attributes ); // matching variation ID or 0
	}

	/**
	 * alg_product_variation_meta.
	 *
	 * @version 1.5.1
	 * @since   1.5.1
	 */
	function alg_product_variation_meta( $atts ) {
		$product = ( isset( $atts['sibling'] ) && 'yes' === $atts['sibling'] && ( $parent_product_id = $this->the_product->get_parent_id() ) ?
			wc_get_product( $parent_product_id ) : $this->the_product );
		return ( isset( $atts['key'] ) && ( $variation_id = $this->get_matching_product_variation( $atts, $product ) ) ? get_post_meta( $variation_id, $atts['key'], true ) : '' );
	}

	/**
	 * alg_product_variation_data.
	 *
	 * @version 1.5.1
	 * @since   1.5.1
	 */
	function alg_product_variation_data( $atts ) {
		$product = ( isset( $atts['sibling'] ) && 'yes' === $atts['sibling'] && ( $parent_product_id = $this->the_product->get_parent_id() ) ?
			wc_get_product( $parent_product_id ) : $this->the_product );
		return ( isset( $atts['key'] ) && ( $variation_id = $this->get_matching_product_variation( $atts, $product ) ) &&
			( $variation = $product->get_available_variation( $variation_id ) ) && isset( $variation[ $atts['key'] ] ) ? $variation[ $atts['key'] ] : '' );
	}

	/**
	 * alg_product_id.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function alg_product_id( $atts ) {
		return $this->get_product_id( $this->the_product );
	}

	/**
	 * alg_product_function.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function alg_product_function( $atts ) {
		if ( ! isset( $atts['function'] ) ) {
			return '';
		}
		$function_name = $atts['function'];
		return ( is_callable( array( $this->the_product, $function_name ) ) ? $this->the_product->$function_name() : '' );
	}

	/**
	 * alg_product_type.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_type( $atts ) {
		return $this->the_product->get_type();
	}

	/**
	 * alg_product_length.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_length( $atts ) {
		$return = ( '' != $atts['to_unit'] ) ? wc_get_dimension( $this->the_product->get_length(), $atts['to_unit'] ) : $this->the_product->get_length();
		return ( 'yes' === $atts['round'] ) ? round( $return, $atts['precision'] ) : $return;
	}

	/**
	 * alg_product_width.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_width( $atts ) {
		$return = ( '' != $atts['to_unit'] ) ? wc_get_dimension( $this->the_product->get_width(), $atts['to_unit'] ) : $this->the_product->get_width();
		return ( 'yes' === $atts['round'] ) ? round( $return, $atts['precision'] ) : $return;
	}

	/**
	 * alg_product_height.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_height( $atts ) {
		$return = ( '' != $atts['to_unit'] ) ? wc_get_dimension( $this->the_product->get_height(), $atts['to_unit'] ) : $this->the_product->get_height();
		return ( 'yes' === $atts['round'] ) ? round( $return, $atts['precision'] ) : $return;
	}

	/**
	 * alg_product_time_since_last_sale.
	 *
	 * @version 1.3.1
	 * @since   1.0.0
	 * @todo    [dev] use `wc_get_orders()` (instead of `WP_Query`)
	 * @todo    [dev] recheck if maybe we need to use `time()` instead of `current_time( 'timestamp' )`
	 */
	function alg_product_time_since_last_sale( $atts ) {
		$offset        = 0;
		$block_size    = 512;
		$result        = '';
		while( true ) {
			// Create args for new query
			$args = array(
				'post_type'      => 'shop_order',
				'post_status'    => $atts['order_status'],
				'posts_per_page' => $block_size,
				'offset'         => $offset,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'date_query'     => array( 'after' => '-' . $atts['days_to_cover'] . ' days' ),
				'fields'         => 'ids',
			);
			// Run new query
			$loop = new WP_Query( $args );
			if ( ! $loop->have_posts() ) {
				break;
			}
			// Analyze the results, i.e. orders
			foreach ( $loop->posts as $order_id ) {
				$order = wc_get_order( $order_id );
				$items = $order->get_items();
				foreach ( $items as $item ) {
					// Run through all order's items
					if ( $item['product_id'] == $atts['product_id'] ) {
						// Found sale!
						$result = sprintf( __( '%s ago', 'product-xml-feeds-for-woocommerce' ), human_time_diff( get_the_time( 'U', $order_id ), current_time( 'timestamp' ) ) );
						break;
					}
				}
				if ( '' != $result ) {
					break;
				}
			}
			if ( '' != $result ) {
				break;
			}
			$offset += $block_size;
		}
		// No sales found
		return ( '' != $result ? $result : ( 'yes' === $atts['hide_if_no_sales'] ? '' : __( 'No sales yet.', 'product-xml-feeds-for-woocommerce' ) ) );
	}

	/**
	 * alg_product_available_variations.
	 *
	 * @version 1.4.3
	 * @since   1.0.0
	 */
	function alg_product_available_variations( $atts ) {
		if ( $this->the_product->is_type( 'variable' ) ) {
			$sep2 = ( isset( $atts['sep2'] ) ? $atts['sep2'] : ': ' );
			$sep3 = ( isset( $atts['sep3'] ) ? $atts['sep3'] : ' | ' );
			$variations = array();
			foreach ( $this->the_product->get_available_variations() as $variation ) {
				$attributes = array();
				foreach ( $variation['attributes'] as $attribute_slug => $attribute_name ) {
					if ( '' == $attribute_name ) {
						$attribute_name = __( 'Any', 'product-xml-feeds-for-woocommerce' );
					}
					$attributes[] = $attribute_name;
				}
				if ( ! empty( $attributes ) ) {
					$variations[] = implode( $atts['sep'], $attributes ) . $sep2 . $variation['price_html'];
				}
			}
			return ( ! empty( $variations ) ? implode( $sep3, $variations ) : '' );
		} else {
			return '';
		}
	}

	/**
	 * alg_product_price_excluding_tax.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_price_excluding_tax( $atts ) {
		return $this->get_product_price_including_or_excluding_tax( $atts, 'excluding' );
	}

	/**
	 * alg_product_price_including_tax.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_price_including_tax( $atts ) {
		return $this->get_product_price_including_or_excluding_tax( $atts, 'including' );
	}

	/**
	 * get_product_price_including_or_excluding_tax.
	 *
	 * @version 1.5.0
	 * @since   1.0.0
	 */
	function get_product_price_including_or_excluding_tax( $atts, $including_or_excluding ) {
		if ( $this->the_product->is_type( 'variable' ) && ( ! isset( $atts['variable_price_type'] ) || 'range' === $atts['variable_price_type'] ) ) {
			// Variable
			$prices = $this->the_product->get_variation_prices( false );
			$min_product_id = key( $prices['price'] );
			end( $prices['price'] );
			$max_product_id = key( $prices['price'] );
			if ( 0 != $min_product_id && 0 != $max_product_id ) {
				$min_variation_product = wc_get_product( $min_product_id );
				$max_variation_product = wc_get_product( $max_product_id );
				if ( 'including' === $including_or_excluding ) {
					$min = $this->get_product_price_including_tax( $min_variation_product );
					$max = $this->get_product_price_including_tax( $max_variation_product );
				} else { // 'excluding'
					$min = $this->get_product_price_excluding_tax( $min_variation_product );
					$max = $this->get_product_price_excluding_tax( $max_variation_product );
				}
				if ( 0 != $atts['multiply_by'] && is_numeric( $atts['multiply_by'] ) ) {
					$min = $min * $atts['multiply_by'];
					$max = $max * $atts['multiply_by'];
				}
				if ( 'yes' !== $atts['hide_currency'] ) {
					$min = wc_price( $min );
					$max = wc_price( $max );
				}
				return ( $min != $max ) ? sprintf( '%s-%s', $min, $max ) : $min;
			}
		} else {
			 // Simple etc.
			if ( 'including' === $including_or_excluding ) {
				$the_price = $this->get_product_price_including_tax( $this->the_product );
			} else { // 'excluding'
				$the_price = $this->get_product_price_excluding_tax( $this->the_product );
			}
			if ( 0 != $atts['multiply_by'] && is_numeric( $atts['multiply_by'] ) ) {
				$the_price = $the_price * $atts['multiply_by'];
			}
			return ( 'yes' === $atts['hide_currency'] ) ? $the_price : wc_price( $the_price );
		}
	}

	/**
	 * alg_product_regular_price.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_regular_price( $atts ) {
		if ( $this->the_product->is_on_sale() || 'yes' === $atts['show_always'] ) {
			$the_price = $this->the_product->get_regular_price();
			return ( 'yes' === $atts['hide_currency'] ) ? $the_price : wc_price( $the_price );
		}
		return '';
	}

	/**
	 * alg_product_sale_price.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_sale_price( $atts ) {
		if ( $this->the_product->is_on_sale() ) {
			$the_price = $this->the_product->get_sale_price();
			return ( 'yes' === $atts['hide_currency'] ) ? $the_price : wc_price( $the_price );
		}
		return '';
	}

	/**
	 * alg_product_tax_class.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_tax_class( $atts ) {
		return $this->the_product->get_tax_class();
	}

	/**
	 * alg_product_list_attributes.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function alg_product_list_attributes( $atts ) {
		if ( $this->the_product->has_attributes() ) {
			ob_start();
			$this->list_product_attributes( $this->the_product );
			return str_replace( array( '</th>', '</tr>' ), array( ':', '|' ), ob_get_clean() );
		}
		return '';
	}

	/**
	 * alg_product_list_attribute.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @todo    [dev] (maybe) need to check if `'' != $atts['name']` (same in `alg_product_custom_field()`)
	 */
	function alg_product_list_attribute( $atts ) {
		return $this->the_product->get_attribute( $atts['name'] );
	}

	/**
	 * alg_product_stock_quantity.
	 *
	 * @version 1.4.8
	 * @since   1.0.0
	 */
	function alg_product_stock_quantity( $atts ) {
		return ( '' != ( $stock_quantity = $this->the_product->get_stock_quantity() ) ? $stock_quantity : '' );
	}

	/**
	 * alg_product_average_rating.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_average_rating( $atts ) {
		return $this->the_product->get_average_rating();
	}

	/**
	 * alg_product_categories.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 * @todo    [dev] do we need this shortcode (maybe `alg_product_categories_names` instead)?
	 */
	function alg_product_categories( $atts ) {
		return $this->get_product_categories( $this->the_product );
	}

	/**
	 * alg_product_formatted_name.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_formatted_name( $atts ) {
		return $this->the_product->get_formatted_name();
	}

	/**
	 * alg_product_name.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	function alg_product_name( $atts ) {
		return $this->the_product->get_name();
	}

	/**
	 * alg_product_stock_availability.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_stock_availability( $atts ) {
		$stock_availability_array = $this->the_product->get_availability();
		return ( isset( $stock_availability_array['availability'] ) ) ? $stock_availability_array['availability'] : '';
	}

	/**
	 * alg_product_dimensions.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function alg_product_dimensions( $atts ) {
		return ( $this->the_product->has_dimensions() ? $this->get_product_dimensions( $this->the_product ) : '' );
	}

	/**
	 * alg_product_shipping_class.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_shipping_class( $atts ) {
		$the_product_shipping_class = $this->the_product->get_shipping_class();
		if ( '' != $the_product_shipping_class ) {
			foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
				if ( $the_product_shipping_class === $shipping_class->slug ) {
					return $shipping_class->name;
				}
			}
		}
		return '';
	}

	/**
	 * alg_product_total_sales.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function alg_product_total_sales( $atts ) {
		$product_custom_fields = get_post_custom( $this->get_product_or_variation_parent_id( $this->the_product ) );
		$total_sales = ( isset( $product_custom_fields['total_sales'][0] ) ) ? $product_custom_fields['total_sales'][0] : '';
		if ( 0 != $atts['offset'] ) {
			$total_sales += $atts['offset'];
		}
		return ( 0 == $total_sales && 'yes' === $atts['hide_if_zero'] ) ? '' : $total_sales;
	}

	/**
	 * alg_product_tags.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 * @return  string
	 * @todo    [dev] do we need add_links attribute
	 */
	function alg_product_tags( $atts ) {

		if ( 'yes' === $atts['add_links'] ) {
			return $this->get_product_tags( $this->the_product, $atts['sep'] );
		}

		$product_tags = get_the_terms( $atts['product_id'], 'product_tag' );
		$product_tags_names = array();
		foreach ( $product_tags as $product_tag ) {
			$product_tags_names[] = $product_tag->name;
		}
		return implode( $atts['sep'], $product_tags_names );
	}

	/**
	 * alg_product_you_save.
	 *
	 * @return  string
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_you_save( $atts ) {
		if ( $this->the_product->is_on_sale() ) {
			if ( $this->the_product->is_type( 'variable' ) ) {
				$you_save = ( $this->the_product->get_variation_regular_price( 'max' ) - $this->the_product->get_variation_sale_price( 'max' ) );
			} else {
				$you_save = ( $this->the_product->get_regular_price() - $this->the_product->get_sale_price() );
			}
			return ( 'yes' === $atts['hide_currency'] ) ? $you_save : wc_price( $you_save );
		} else {
			return ( 'yes' === $atts['hide_if_zero'] ) ? '' : 0;
		}
	}

	/**
	 * alg_product_you_save_percent.
	 *
	 * @return  string
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_you_save_percent( $atts ) {
		if ( $this->the_product->is_on_sale() ) {
			if ( $this->the_product->is_type( 'variable' ) ) {
				$you_save      = ( $this->the_product->get_variation_regular_price( 'max' ) - $this->the_product->get_variation_sale_price( 'max' ) );
				$regular_price = $this->the_product->get_variation_regular_price( 'max' );
			} else {
				$you_save      = ( $this->the_product->get_regular_price() - $this->the_product->get_sale_price() );
				$regular_price = $this->the_product->get_regular_price();
			}
			if ( 0 != $regular_price ) {
				$you_save_percent = intval( $you_save / $regular_price * 100 );
				return ( 'yes' === $atts['reverse'] ) ? ( 100 - $you_save_percent ) : $you_save_percent;
			} else {
				return '';
			}
		} else {
			return ( 'yes' === $atts['hide_if_zero'] ) ? '' : ( ( 'yes' === $atts['reverse'] ) ? 100 : 0 );
		}
	}

	/**
	 * Get product meta.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_meta( $atts ) {
		return get_post_meta( $atts['product_id'], $atts['name'], true );
	}

	/**
	 * Get product custom field.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_custom_field( $atts ) {
		$product_custom_fields = get_post_custom( $atts['product_id'] );
		return ( isset( $product_custom_fields[ $atts['name'] ][0] ) ) ? $product_custom_fields[ $atts['name'] ][0] : '';
	}

	/**
	 * Returns product (modified) price.
	 *
	 * @version 1.5.0
	 * @since   1.0.0
	 * @todo    [dev] variable products: not range
	 * @return  string The product (modified) price
	 */
	function alg_product_price( $atts ) {
		if ( $this->the_product->is_type( 'variable' ) && ( ! isset( $atts['variable_price_type'] ) || 'range' === $atts['variable_price_type'] ) ) {
			// Variable
			$min = $this->the_product->get_variation_price( 'min', false );
			$max = $this->the_product->get_variation_price( 'max', false );
			if ( '' !== $atts['multiply_by'] && is_numeric( $atts['multiply_by'] ) ) {
				$min = $min * $atts['multiply_by'];
				$max = $max * $atts['multiply_by'];
			}
			if ( 'yes' !== $atts['hide_currency'] ) {
				$min = wc_price( $min );
				$max = wc_price( $max );
			}
			return ( $min != $max ) ? sprintf( '%s-%s', $min, $max ) : $min;
		} else {
			// Simple etc.
			$price = $this->the_product->get_price();
			if ( '' !== $atts['multiply_by'] && is_numeric( $atts['multiply_by'] ) ) {
				$price = $price * $atts['multiply_by'];
			}
			return ( 'yes' === $atts['hide_currency'] ? $price : wc_price( $price ) );
		}
	}

	/**
	 * Get product description.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 * @return  string
	 */
	function alg_product_description( $atts ) {
		return $this->the_product->get_description();
	}

	/**
	 * Get product short description.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_short_description( $atts ) {
		$short_description = $this->get_product_short_description( $this->the_product );
		if ( 'yes' === $atts['apply_filters'] ) {
			apply_filters( 'woocommerce_short_description', $short_description );
		}
		if ( 0 != $atts['length'] ) {
			$excerpt_more = apply_filters( 'excerpt_more', ' ' . '[&hellip;]' );
			$short_description = wp_trim_words( $short_description, $atts['length'], $excerpt_more );
		}
		return $short_description;
	}

	/**
	 * custom_excerpt_length - for alg_product_excerpt().
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function custom_excerpt_length( $length ) {
		return $this->product_excerpt_length;
	}

	/**
	 * Get product excerpt.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_excerpt( $atts ) {
		$the_excerpt = $this->alg_product_short_description( $atts );
		if ( '' === $the_excerpt ) {
			if ( 0 != $atts['length'] ) {
				$this->product_excerpt_length = $atts['length'];
				add_filter(    'excerpt_length', array( $this, 'custom_excerpt_length' ), PHP_INT_MAX );
				$the_excerpt = get_the_excerpt( $atts['product_id'] );
				remove_filter( 'excerpt_length', array( $this, 'custom_excerpt_length' ), PHP_INT_MAX );
			} else {
				$the_excerpt = get_the_excerpt( $atts['product_id'] );
			}
		}
		return $the_excerpt;
	}

	/**
	 * Get SKU (Stock-keeping unit) - product unique ID.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_sku( $atts ) {
		return $this->the_product->get_sku();
	}

	/**
	 * Get the title of the product.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function alg_product_title( $atts ) {
		return $this->the_product->get_title();
	}

	/**
	 * Get the product's weight.
	 *
	 * @return  string
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_weight( $atts ) {
		return ( $this->the_product->has_weight() ) ? $this->the_product->get_weight() : '';
	}

	/**
	 * alg_product_image_url.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 * @todo    [dev] placeholder
	 */
	function alg_product_image_url( $atts ) {
		$product_id = $this->get_product_id( $this->the_product );
		$image_size = $atts['image_size'];
		if ( has_post_thumbnail( $product_id ) ) {
			$image_url = get_the_post_thumbnail_url( $product_id, $image_size );
		} elseif ( ( $parent_id = wp_get_post_parent_id( $product_id ) ) && has_post_thumbnail( $parent_id ) ) {
			$image_url = get_the_post_thumbnail_url( $parent_id, $image_size );
		} else {
			$image_url = '';
		}
		return $image_url;
	}

	/**
	 * alg_product_gallery_image_url.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function alg_product_gallery_image_url( $atts ) {
		if ( ! isset( $atts['image_nr'] ) ) {
			$atts['image_nr'] = 1;
		}
		$attachment_ids = ( $this->is_wc_version_below_3 ? $this->the_product->get_gallery_attachment_ids() : $this->the_product->get_gallery_image_ids() );
		if ( $attachment_ids && isset( $attachment_ids[ ( $atts['image_nr'] - 1 ) ] ) ) {
			$props = wc_get_product_attachment_props( $attachment_ids[ ( $atts['image_nr'] - 1 ) ] );
			if ( isset( $props['url'] ) ) {
				return $props['url'];
			}
		}
		return '';
	}

	/**
	 * alg_product_url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_product_url( $atts ) {
		return $this->the_product->get_permalink();
	}

	/**
	 * alg_product_categories_names.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function alg_product_categories_names( $atts ) {
		$product_cats = get_the_terms( $this->get_product_or_variation_parent_id( $this->the_product ), 'product_cat' );
		$cats = array();
		if ( ! empty( $product_cats ) && is_array( $product_cats ) ) {
			foreach ( $product_cats as $product_cat ) {
				$cats[] = $product_cat->name;
			}
		}
		return implode( $atts['sep'], $cats );
	}

	/**
	 * alg_product_categories_urls.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function alg_product_categories_urls( $atts ) {
		$product_cats = get_the_terms( $this->get_product_or_variation_parent_id( $this->the_product ), 'product_cat' );
		$cats = array();
		if ( ! empty( $product_cats ) && is_array( $product_cats ) ) {
			foreach ( $product_cats as $product_cat ) {
				$cats[] = get_term_link( $product_cat );
			}
		}
		return implode( $atts['sep'], $cats );
	}

	/**
	 * sort_terms_by_parent_id.
	 *
	 * @version 1.5.3
	 * @since   1.5.3
	 */
	function sort_terms_by_parent_id( $a, $b ) {
		return ( $a->parent == $b->parent ? 0 : ( $a->parent < $b->parent ? -1 : 1 ) );
	}

	/**
	 * sort_terms_by_term_id.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	function sort_terms_by_term_id( $a, $b ) {
		return ( $a->term_id == $b->term_id ? 0 : ( $a->term_id < $b->term_id ? -1 : 1 ) );
	}

	/**
	 * get_terms_by_hierarchy.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	function get_terms_by_hierarchy( $product_terms, $atts ) {
		$walker = new Alg_WC_PXF_Walker_Terms();
		$output = $walker->walk( $product_terms, 0, $atts );
		$output = substr( $output, 0, strlen( $output ) - strlen( $atts['sep'] ) );
		return $output;
	}

	/**
	 * alg_product_terms.
	 *
	 * @version 1.5.4
	 * @since   1.2.2
	 * @todo    [dev] (maybe) when `orderby` is set `hierarchy` (or maybe `parent_id`): check if taxonomy is hierarchical first
	 * @todo    [dev] (maybe) `orderby`: add `count` value (i.e. `$product_term->count`)
	 */
	function alg_product_terms( $atts ) {
		if ( '' === $atts['taxonomy'] ) {
			return '';
		}
		$product_terms = get_the_terms( $this->get_product_or_variation_parent_id( $this->the_product ), $atts['taxonomy'] );
		if ( $product_terms && ! is_wp_error( $product_terms ) ) {
			if ( isset( $atts['parent'] ) ) {
				$product_terms = array_filter( $product_terms, array( new Alg_WC_PXF_Filter_Terms_Parent( $atts['parent'] ), 'is_equal' ) );
			}
			if ( ! isset( $atts['orderby'] ) ) {
				$atts['orderby'] = 'name';
			}
			switch ( $atts['orderby'] ) {
				case 'parent_id':
					usort( $product_terms, array( $this, 'sort_terms_by_parent_id' ) );
					return implode( $atts['sep'], wp_list_pluck( $product_terms, 'name' ) );
				case 'term_id':
					usort( $product_terms, array( $this, 'sort_terms_by_term_id' ) );
					return implode( $atts['sep'], wp_list_pluck( $product_terms, 'name' ) );
				case 'hierarchy':
					return $this->get_terms_by_hierarchy( $product_terms, $atts );
				default: // 'name'
					return implode( $atts['sep'], wp_list_pluck( $product_terms, 'name' ) );
			}
		}
		return '';
	}

}

endif;

if ( ! class_exists( 'Alg_WC_PXF_Filter_Terms_Parent' ) ) :

/**
 * Alg_WC_PXF_Filter_Terms_Parent.
 *
 * @version 1.5.4
 * @since   1.5.4
 */
class Alg_WC_PXF_Filter_Terms_Parent {

	/**
	 * parent_id.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	private $parent_id;

	/**
	 * __construct.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	function __construct( $parent_id ) {
		$this->parent_id = $parent_id;
	}

	/**
	 * is_equal.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	function is_equal( $term ) {
		return ( $term->parent == $this->parent_id );
	}
}

endif;

if ( ! class_exists( 'Alg_WC_PXF_Walker_Terms' ) ) :

/**
 * Alg_WC_PXF_Walker_Terms.
 *
 * @version 1.5.4
 * @since   1.5.4
 */
class Alg_WC_PXF_Walker_Terms extends Walker {

	/**
	 * db_fields.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	public $db_fields = array(
		'parent' => 'parent',
		'id'     => 'term_id',
	);

	/**
	 * start_el.
	 *
	 * @version 1.5.4
	 * @since   1.5.4
	 */
	function start_el( &$output, $term, $depth = 0, $args = array(), $id = 0 ) {
		$output .= $term->name . $args['sep'];
	}

}

endif;

return new Alg_Products_Shortcodes();
