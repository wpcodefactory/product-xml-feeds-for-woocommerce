<?php
/**
 * Product XML Feeds for WooCommerce - v2 mapper: canonical model to channel item.
 *
 * Item shape (for the writers): field => string; field => list of strings (repeated element, or one cell joined
 * with the field's 'list_sep'); field => array( child => string ) (sub-attributes, nested elements); field => list
 * of child arrays (each written as the field's 'list_item' element, e.g. Skroutz variations > variation);
 * a field with 'json' => 'bool' holds 'true' or 'false'. Empty fields are left out.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Mapper' ) ) :

class Alg_WC_PXF_Mapper {

	/**
	 * item.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $model   Canonical model (Alg_WC_PXF_Product_Data::model()).
	 * @param   array $channel Channel spec.
	 * @param   array $config  Feed config.
	 * @return  array field => value.
	 */
	static function item( array $model, array $channel, array $config ) {
		$channel = Alg_WC_PXF_Channels::normalize( $channel, isset( $channel['id'] ) ? $channel['id'] : '' );
		$mapping = ( isset( $config['mapping'] ) && is_array( $config['mapping'] ) ? $config['mapping'] : array() );
		$item    = array();

		if ( ! empty( $channel['raw'] ) ) {
			$item = array( '_raw' => self::custom_item( (int) $model['id'], $config ) );
		} else {
			foreach ( $channel['fields'] as $field => $def ) {
				$map   = ( isset( $mapping[ $field ] ) && is_array( $mapping[ $field ] ) ? $mapping[ $field ] : array() );
				$value = self::field_value( $field, $def, $map, $model, $channel, $config );
				if ( self::is_empty( $value ) ) {
					continue;
				}
				$item[ $field ] = $value;
			}
			$item = self::apply_ai( $item, $model, $channel, $config, $mapping );
		}

		/**
		 * Filters one channel item.
		 *
		 * @since 4.0.0
		 *
		 * @param array $item    field => value.
		 * @param array $model   Canonical model.
		 * @param array $channel Channel spec.
		 * @param array $config  Feed config.
		 */
		return apply_filters( 'alg_wc_pxf_item', $item, $model, $channel, $config );
	}

	/**
	 * items: the items of one model. One item for every channel, except channels with a 'split' (Skroutz), where a
	 * variable product becomes one item per color with its sizes in 'variations'. Engines should call this.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $model   Canonical model.
	 * @param   array $channel Channel spec.
	 * @param   array $config  Feed config.
	 * @return  array List of items.
	 */
	static function items( array $model, array $channel, array $config ) {
		if ( empty( $channel['split'] ) || 'variable' !== $model['type'] ) {
			return array( self::item( $model, $channel, $config ) );
		}
		$product = wc_get_product( (int) $model['id'] );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return array( self::item( $model, $channel, $config ) );
		}
		$by   = ( isset( $channel['split']['by'] ) ? $channel['split']['by'] : 'color' );
		$nest = ( isset( $channel['split']['nest'] ) ? $channel['split']['nest'] : 'size' );

		// Which variation attributes are the color and the size.
		$by_key   = '';
		$nest_key = '';
		foreach ( $product->get_variation_attributes() as $attribute_name => $options ) {
			$kind = Alg_WC_PXF_Product_Data::kind( wc_attribute_label( $attribute_name, $product ) );
			if ( '' === $kind ) {
				$kind = Alg_WC_PXF_Product_Data::kind( $attribute_name );
			}
			if ( $kind === $by && '' === $by_key ) {
				$by_key = $attribute_name;
			} elseif ( $kind === $nest && '' === $nest_key ) {
				$nest_key = $attribute_name;
			}
		}

		// Child models, grouped by color.
		$groups = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child || ! $child->is_type( 'variation' ) || 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			if ( ! empty( $config['filters']['stock'] ) && ! in_array( $child->get_stock_status(), (array) $config['filters']['stock'], true ) ) {
				continue;
			}
			$child_model = Alg_WC_PXF_Product_Data::model( $child, $config );
			$group       = '';
			if ( '' !== $by_key ) {
				$group = (string) $child->get_attribute( $by_key );
				if ( '' === $group ) {
					$group = (string) $child_model[ $by ];
				}
			}
			if ( '' === $by_key && '' === $nest_key ) {
				$group = 'v' . $child_id; // Neither color nor size: each variation is its own product.
			}
			$groups[ $group ][] = $child_model;
		}
		if ( empty( $groups ) ) {
			return array( self::item( $model, $channel, $config ) );
		}

		$items = array();
		foreach ( $groups as $group => $children ) {
			if ( 0 === strpos( (string) $group, 'v' ) && '' === $by_key && '' === $nest_key ) {
				$items[] = self::item( $children[0], $channel, $config );
				continue;
			}
			$items[] = self::item( self::group_model( $model, $children, (string) $group, $by_key, $nest, $product ), $channel, $config );
		}
		return $items;
	}

	/**
	 * group_model: model of one color of a variable product (Skroutz), with the sizes in 'variations'.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array      $parent   Parent model.
	 * @param   array      $children Child models of this color.
	 * @param   string     $color    Color value ('' when the product has no color attribute).
	 * @param   string     $by_key   Color attribute name.
	 * @param   string     $nest     Model key of the nested option (size).
	 * @param   WC_Product $product  Variable product.
	 * @return  array
	 */
	static function group_model( $parent, $children, $color, $by_key, $nest, $product ) {
		$model = $parent;
		$first = $children[0];
		$term  = null;
		if ( '' !== $color ) {
			$suffix = sanitize_title( $color );
			if ( taxonomy_exists( $by_key ) ) {
				$term = get_term_by( 'name', $color, $by_key );
				if ( ! $term ) {
					$term = get_term_by( 'slug', sanitize_title( $color ), $by_key );
				}
				if ( $term && ! is_wp_error( $term ) ) {
					$suffix = (string) $term->term_id;
				}
			}
			$model['id']    = $parent['id'] . '-' . $suffix;
			$model['title'] = trim( $parent['title'] . ' ' . $color );
			$model['color'] = $color;
			$term_slug      = sanitize_title( $color );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_slug = $term->slug;
			}
			$model['link'] = add_query_arg( 'attribute_' . sanitize_title( $by_key ), rawurlencode( $term_slug ), $parent['link'] );
		}
		$model['item_group_id'] = $parent['id'];
		$model['image_link']    = ( '' !== $first['image_link'] ? $first['image_link'] : $parent['image_link'] );

		$sizes      = array();
		$variations = array();
		$quantity   = 0;
		$known_qty  = true;
		$lowest     = null;
		$best       = 'out_of_stock';
		$rank       = array( 'in_stock' => 4, 'backorder' => 3, 'preorder' => 2, 'out_of_stock' => 1 );
		foreach ( $children as $child ) {
			$size = (string) $child[ $nest ];
			if ( '' !== $size && 'out_of_stock' !== $child['availability'] && ! in_array( $size, $sizes, true ) ) {
				$sizes[] = $size;
			}
			$qty = self::virtual( 'quantity', $child, array() );
			if ( '' === $qty ) {
				$known_qty = false;
			} else {
				$quantity += (int) $qty;
			}
			$price = self::virtual( 'active_price_incl_tax', $child, array() );
			if ( '' !== $price && ( null === $lowest || (float) $price < (float) $lowest ) ) {
				$lowest = $price;
			}
			if ( $rank[ $child['availability'] ] > $rank[ $best ] ) {
				$best = $child['availability'];
			}
			if ( '' !== $size ) {
				$variations[] = $child;
			}
		}
		$model['size']           = implode( ', ', $sizes );
		$model['availability']   = $best;
		$model['stock_quantity'] = ( $known_qty ? $quantity : '' );
		$model['_quantity']      = ( $known_qty ? (string) $quantity : '' );
		$model['_active_price']  = ( null === $lowest ? '' : $lowest );
		$model['_variations']    = $variations;
		$model['gtin']           = ( 1 === count( $children ) ? $first['gtin'] : $parent['gtin'] );
		$model['mpn']            = ( 1 === count( $children ) ? $first['mpn'] : $parent['mpn'] );
		$model['type']           = 'variation_group';
		return $model;
	}

	/**
	 * field_value: one field (with children or a list of children).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $field   Field name.
	 * @param   array  $def     Field definition.
	 * @param   array  $map     Feed mapping entry for this field.
	 * @param   array  $model   Model.
	 * @param   array  $channel Channel spec.
	 * @param   array  $config  Feed config.
	 * @return  mixed
	 */
	static function field_value( $field, $def, $map, $model, $channel, $config ) {
		// List of sub-attribute arrays (Skroutz variations).
		if ( ! empty( $def['children'] ) && ! empty( $def['list'] ) ) {
			$rows = ( isset( $model[ '_' . $field ] ) ? (array) $model[ '_' . $field ] : array() );
			$out  = array();
			foreach ( $rows as $row_model ) {
				$row = array();
				foreach ( $def['children'] as $child => $child_def ) {
					$child_map = ( isset( $map['children'][ $child ] ) && is_array( $map['children'][ $child ] ) ? $map['children'][ $child ] : array() );
					$value     = self::field_value( $child, $child_def, $child_map, $row_model, $channel, $config );
					if ( ! self::is_empty( $value ) ) {
						$row[ $child ] = $value;
					}
				}
				if ( ! empty( $row ) ) {
					$out[] = $row;
				}
			}
			return $out;
		}
		// Sub-attributes (Google shipping, certification).
		if ( ! empty( $def['children'] ) ) {
			$out = array();
			foreach ( $def['children'] as $child => $child_def ) {
				$child_map = ( isset( $map['children'][ $child ] ) && is_array( $map['children'][ $child ] ) ? $map['children'][ $child ] : array() );
				$value     = self::field_value( $child, $child_def, $child_map, $model, $channel, $config );
				if ( ! self::is_empty( $value ) ) {
					$out[ $child ] = $value;
				}
			}
			// Only static defaults filled: nothing real to send.
			$real = false;
			foreach ( $def['children'] as $child => $child_def ) {
				if ( isset( $out[ $child ] ) && ( empty( $child_def['source']['type'] ) || 'static' !== $child_def['source']['type'] || ! empty( $map['children'][ $child ] ) ) ) {
					$real = true;
				}
			}
			return ( $real ? $out : array() );
		}

		$source = ( ! empty( $map['type'] ) ? $map : $def['source'] );
		$value  = self::resolve( $source, $model, $config, $field );

		// Value maps: the channel's words, then the feed's own map.
		$vocab_key = self::vocab_key( $def, $source );
		if ( '' !== $vocab_key && is_string( $value ) && '' !== $value ) {
			if ( ! empty( $map['map'] ) && is_array( $map['map'] ) && isset( $map['map'][ $value ] ) ) {
				$value = (string) $map['map'][ $value ];
			} elseif ( ! empty( $channel[ $vocab_key ] ) && array_key_exists( $value, $channel[ $vocab_key ] ) ) {
				$value = (string) $channel[ $vocab_key ][ $value ];
			}
		} elseif ( ! empty( $map['map'] ) && is_array( $map['map'] ) && is_string( $value ) && isset( $map['map'][ $value ] ) ) {
			$value = (string) $map['map'][ $value ];
		}

		// Units.
		$unit = ( isset( $def['unit'] ) ? $def['unit'] : '' );
		if ( is_string( $value ) && '' !== $value ) {
			if ( 'currency' === $unit && is_numeric( $value ) ) {
				$currency = ( '' !== $model['currency'] ? $model['currency'] : get_woocommerce_currency() );
				$value    = Alg_WC_PXF_Product_Data::decimal( $value ) . ' ' . $currency;
			} elseif ( 'decimal' === $unit && is_numeric( $value ) ) {
				$value = Alg_WC_PXF_Product_Data::decimal( $value );
			} elseif ( 'integer' === $unit && is_numeric( $value ) ) {
				$value = (string) (int) round( (float) $value );
			}
		} elseif ( is_int( $value ) || is_float( $value ) ) {
			$value = ( 'integer' === $unit ? (string) (int) $value : (string) $value );
		}

		// Lists.
		if ( is_array( $value ) ) {
			if ( empty( $def['list'] ) && 'variant_dict' !== $field && ! self::is_assoc( $value ) ) {
				$value = implode( ', ', array_filter( array_map( 'strval', $value ), 'strlen' ) );
			} elseif ( ! empty( $def['list_max'] ) && ! self::is_assoc( $value ) ) {
				$value = array_slice( array_values( $value ), 0, (int) $def['list_max'] );
			}
		}

		// Prefix, suffix, lengths.
		if ( is_string( $value ) && '' !== $value ) {
			$prefix = ( isset( $map['prefix'] ) ? (string) $map['prefix'] : '' );
			$suffix = ( isset( $map['suffix'] ) ? (string) $map['suffix'] : '' );
			$value  = $prefix . $value . $suffix;
			if ( ! empty( $def['max_words'] ) ) {
				$words = preg_split( '/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY );
				if ( count( $words ) > (int) $def['max_words'] ) {
					$value = implode( ' ', array_slice( $words, 0, (int) $def['max_words'] ) );
				}
			}
			if ( ! empty( $def['max'] ) ) {
				$value = Alg_WC_PXF_Product_Data::cut( $value, (int) $def['max'] );
			}
		}
		return $value;
	}

	/**
	 * vocab_key: which channel value map applies to a field ('' for none).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $def    Field definition.
	 * @param   array $source Source used.
	 * @return  string
	 */
	static function vocab_key( $def, $source ) {
		if ( isset( $source['type'], $source['key'] ) && 'field' === $source['type'] && in_array( $source['key'], array( 'availability', 'condition', 'gender', 'age_group' ), true ) ) {
			return $source['key'];
		}
		return '';
	}

	/**
	 * resolve: value of a source for the model.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $source array( 'type' => field|attribute|meta|taxonomy|static|placeholder, 'key', 'value' ).
	 * @param   array  $model  Model.
	 * @param   array  $config Feed config.
	 * @param   string $field  Field name (for filters).
	 * @return  mixed
	 */
	static function resolve( $source, $model, $config, $field = '' ) {
		if ( empty( $source['type'] ) ) {
			return '';
		}
		$key   = ( isset( $source['key'] ) ? (string) $source['key'] : '' );
		$value = '';
		switch ( $source['type'] ) {
			case 'field':
				$value = ( array_key_exists( $key, $model ) ? $model[ $key ] : self::virtual( $key, $model, $config ) );
				break;
			case 'static':
				$value = ( isset( $source['value'] ) ? (string) $source['value'] : '' );
				break;
			case 'attribute':
			case 'meta':
			case 'taxonomy':
				$product = self::product( $model );
				if ( $product ) {
					$parent = ( $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null );
					$value  = Alg_WC_PXF_Product_Data::mapped( 'x', array( 'x' => array( 'type' => $source['type'], 'key' => $key ) ), $product, $parent );
				}
				break;
			case 'placeholder':
				$id = self::real_id( $model );
				if ( $id ) {
					$value = trim( html_entity_decode( self::render( isset( $source['value'] ) ? (string) $source['value'] : $key, $id ), ENT_QUOTES, 'UTF-8' ) );
				}
				break;
		}
		/**
		 * Filters a resolved source value before units, maps and lengths.
		 *
		 * @since 4.0.0
		 */
		return apply_filters( 'alg_wc_pxf_source_value', $value, $source, $model, $config, $field );
	}

	/**
	 * virtual: computed values the specs use besides the model keys.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $key    Key.
	 * @param   array  $model  Model.
	 * @param   array  $config Feed config.
	 * @return  mixed
	 */
	static function virtual( $key, $model, $config ) {
		switch ( $key ) {
			case 'site_name':
				return html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
			case 'country':
				return self::country( $config );
			case 'countries':
				return array( self::country( $config ) );
			case 'images':
				return array_values( array_filter( array_merge( array( $model['image_link'] ), (array) $model['additional_image_links'] ), 'strlen' ) );
			case 'is_variation':
				return ( '' !== $model['item_group_id'] ? 'true' : '' );
			case 'weight_value':
			case 'weight_unit':
				$parts = explode( ' ', (string) $model['weight'], 2 );
				return ( 2 === count( $parts ) ? $parts[ 'weight_value' === $key ? 0 : 1 ] : '' );
			case 'weight_grams':
				$parts = explode( ' ', (string) $model['weight'], 2 );
				if ( 2 !== count( $parts ) ) {
					return '';
				}
				$from = ( 'lb' === $parts[1] ? 'lbs' : $parts[1] );
				return (string) round( wc_get_weight( (float) $parts[0], 'g', $from ) );
			case 'quantity':
				if ( isset( $model['_quantity'] ) ) {
					return $model['_quantity'];
				}
				if ( '' !== $model['stock_quantity'] ) {
					return (string) max( 0, (int) $model['stock_quantity'] );
				}
				return ( 'out_of_stock' === $model['availability'] ? '0' : '' );
			case 'active_price':
				return self::active( $model['price'], $model['sale_price'], $model['sale_price_effective_date'] );
			case 'price_incl_tax':
			case 'sale_price_incl_tax':
			case 'active_price_incl_tax':
				if ( 'active_price_incl_tax' === $key && isset( $model['_active_price'] ) ) {
					return $model['_active_price'];
				}
				$product = self::product( $model );
				if ( ! $product || $product->is_type( 'variable' ) ) {
					$price = self::active( $model['price'], $model['sale_price'], $model['sale_price_effective_date'] );
					return ( 'sale_price_incl_tax' === $key ? $model['sale_price'] : ( 'price_incl_tax' === $key ? $model['price'] : $price ) );
				}
				$regular = $product->get_regular_price();
				$regular = ( '' === (string) $regular ? $product->get_price() : $regular );
				$sale    = ( '' !== $model['sale_price'] ? $product->get_sale_price() : '' );
				if ( 'price_incl_tax' === $key ) {
					$use = $regular;
				} elseif ( 'sale_price_incl_tax' === $key ) {
					$use = $sale;
				} else {
					$sale_now = ( '' !== $sale && '0' === self::active( '1', '0', $model['sale_price_effective_date'] ) );
					$use      = ( $sale_now ? $sale : $regular );
				}
				if ( '' === (string) $use ) {
					return '';
				}
				return Alg_WC_PXF_Product_Data::decimal( wc_get_price_including_tax( $product, array( 'price' => $use ) ) );
		}
		return '';
	}

	/**
	 * active: the sale price when it applies now, else the price.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $price Price.
	 * @param   string $sale  Sale price.
	 * @param   string $dates ISO 8601 interval or ''.
	 * @return  string
	 */
	static function active( $price, $sale, $dates ) {
		if ( '' === (string) $sale ) {
			return $price;
		}
		if ( '' !== (string) $dates ) {
			$parts = explode( '/', $dates );
			$from  = strtotime( $parts[0] );
			$to    = ( isset( $parts[1] ) ? strtotime( $parts[1] ) : false );
			$now   = time();
			if ( ( $from && $now < $from ) || ( $to && $now > $to ) ) {
				return $price;
			}
		}
		return $sale;
	}

	/**
	 * country: feed target country, else the shop base country.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $config Feed config.
	 * @return  string
	 */
	static function country( $config ) {
		if ( ! empty( $config['country'] ) ) {
			return strtoupper( (string) $config['country'] );
		}
		return ( function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : '' );
	}

	/**
	 * apply_ai: approved AI title and description when the feed asks for them.
	 *
	 * Google: structured_title / structured_description (digital_source_type trained_algorithmic_media) and no plain
	 * title / description. Other channels: the AI text goes into their title / description field. A field the merchant
	 * mapped to another source keeps that source.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $item    Item.
	 * @param   array $model   Model.
	 * @param   array $channel Channel spec.
	 * @param   array $config  Feed config.
	 * @param   array $mapping Feed mapping.
	 * @return  array
	 */
	static function apply_ai( $item, $model, $channel, $config, $mapping ) {
		$ai = array_merge( array( 'title' => 'title', 'description' => 'description', 'structured' => false ), (array) $channel['ai'] );
		foreach ( array( 'title' => 'use_ai_titles', 'description' => 'use_ai_descriptions' ) as $what => $option ) {
			$text  = (string) $model[ 'ai_' . $what ];
			$field = (string) $ai[ $what ];
			if ( empty( $config[ $option ] ) || '' === $text || '' === $field || ! empty( $mapping[ $field ]['type'] ) ) {
				continue;
			}
			$structured = 'structured_' . $what;
			if ( $ai['structured'] && isset( $channel['fields'][ $structured ] ) ) {
				$max = ( isset( $channel['fields'][ $structured ]['children']['content']['max'] ) ? (int) $channel['fields'][ $structured ]['children']['content']['max'] : 0 );
				$item[ $structured ] = array(
					'digital_source_type' => 'trained_algorithmic_media',
					'content'             => Alg_WC_PXF_Product_Data::cut( $text, $max ),
				);
				unset( $item[ $field ] );
				$item = self::move_after( $item, $structured, ( 'title' === $what ? 'id' : ( isset( $item['structured_title'] ) ? 'structured_title' : 'id' ) ) );
			} elseif ( isset( $channel['fields'][ $field ] ) ) {
				$max            = ( isset( $channel['fields'][ $field ]['max'] ) ? (int) $channel['fields'][ $field ]['max'] : 0 );
				$item[ $field ] = Alg_WC_PXF_Product_Data::cut( $text, $max );
			}
		}
		return $item;
	}

	/**
	 * move_after: keeps a readable field order in the file.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $item  Item.
	 * @param   string $key   Key to move.
	 * @param   string $after Key to place it after.
	 * @return  array
	 */
	static function move_after( $item, $key, $after ) {
		if ( ! isset( $item[ $key ] ) || ! isset( $item[ $after ] ) ) {
			return $item;
		}
		$value = $item[ $key ];
		unset( $item[ $key ] );
		$out = array();
		foreach ( $item as $k => $v ) {
			$out[ $k ] = $v;
			if ( $k === $after ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * custom_item: the classic item template rendered for one product (channel custom).
	 *
	 * Same as the 3.1 generator: global post set up for the product, do_shortcode() over the whole template (every
	 * shortcode, also other plugins'), then html_entity_decode() and "&" written as "&amp;".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $product_id Product or variation ID.
	 * @param   array $config     Feed config ('custom_template' => array( 'header', 'item', 'footer' )).
	 * @return  string
	 */
	static function custom_item( $product_id, $config ) {
		$template = self::custom_template( 'item', $config );
		return str_replace( '&', '&amp;', html_entity_decode( self::render( $template, $product_id ) ) );
	}

	/**
	 * custom_part: the classic header or footer template rendered (channel custom).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $part   header or footer.
	 * @param   array  $config Feed config.
	 * @return  string
	 */
	static function custom_part( $part, $config ) {
		$template = self::custom_template( $part, $config );
		$was      = self::generating( true );
		$out      = do_shortcode( $template );
		self::generating( $was );
		return $out;
	}

	/**
	 * custom_template: the feed's template part, else the classic default; "raw input" setting honoured.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $part   header, item or footer.
	 * @param   array  $config Feed config.
	 * @return  string
	 */
	static function custom_template( $part, $config ) {
		$template = ( isset( $config['custom_template'][ $part ] ) && is_string( $config['custom_template'][ $part ] ) ? $config['custom_template'][ $part ] : null );
		if ( null === $template ) {
			$core     = ( function_exists( 'alg_wc_product_xml_feeds' ) && isset( alg_wc_product_xml_feeds()->core ) ? alg_wc_product_xml_feeds()->core : null );
			$template = ( $core && is_callable( array( $core, 'get_default_template' ) ) ? (string) $core->get_default_template( $part ) : '' );
		}
		if ( 'no' === get_option( 'alg_products_xml_raw_input', 'yes' ) ) {
			$template = str_replace( array( '{', '}' ), array( '<', '>' ), $template );
		}
		return $template;
	}

	/**
	 * render: do_shortcode() of a template with the global post set to one product, as the classic generator does.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $template   Template with shortcodes.
	 * @param   int    $product_id Product or variation ID.
	 * @return  string
	 */
	static function render( $template, $product_id ) {
		global $post;
		$old_post = $post;
		$was      = self::generating( true );
		$post     = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Same setup as the classic generator loop.
		setup_postdata( $post );
		$out = do_shortcode( (string) $template );
		$post = $old_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $old_post ) {
			setup_postdata( $old_post );
		}
		self::generating( $was );
		return (string) $out;
	}

	/**
	 * generating: sets the classic "feed is being generated" flag; returns the previous value.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   bool $value New value.
	 * @return  bool
	 */
	static function generating( $value ) {
		if ( ! class_exists( 'Alg_WC_Product_XML_Feeds_Core' ) || ! property_exists( 'Alg_WC_Product_XML_Feeds_Core', 'generating' ) ) {
			return false;
		}
		$was                                         = Alg_WC_Product_XML_Feeds_Core::$generating;
		Alg_WC_Product_XML_Feeds_Core::$generating = (bool) $value;
		return $was;
	}

	/**
	 * product: WC_Product of a model (the parent for a color group).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $model Model.
	 * @return  WC_Product|null
	 */
	static function product( $model ) {
		$id = self::real_id( $model );
		$p  = ( $id ? wc_get_product( $id ) : null );
		return ( $p ? $p : null );
	}

	/**
	 * real_id: post ID of a model ("65-12" color group ids give the parent ID).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $model Model.
	 * @return  int
	 */
	static function real_id( $model ) {
		if ( isset( $model['type'] ) && 'variation_group' === $model['type'] ) {
			return (int) $model['item_group_id'];
		}
		return (int) $model['id'];
	}

	/**
	 * is_empty.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  bool
	 */
	static function is_empty( $value ) {
		return ( null === $value || '' === $value || ( is_array( $value ) && empty( $value ) ) );
	}

	/**
	 * is_assoc.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $value Array.
	 * @return  bool
	 */
	static function is_assoc( $value ) {
		return is_array( $value ) && array() !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

}

endif;
