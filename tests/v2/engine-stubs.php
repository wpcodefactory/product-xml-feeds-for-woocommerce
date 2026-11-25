<?php
/**
 * Product XML Feeds for WooCommerce - TEST ONLY stubs for the engine tests.
 *
 * A minimal Google-like channel, a product model from WC_Product getters, a mapper that copies fields and a tiny
 * validator. Loaded by a mu-plugin on a test site only when the real classes are missing. Never required by plugin code.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
	class Alg_WC_PXF_Channels {
		static function all() {
			return array( 'google' => self::google(), 'custom' => self::custom() );
		}
		static function get( $id ) {
			$all = self::all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}
		static function custom() {
			return array(
				'id'             => 'custom',
				'label'          => 'Custom template',
				'group'          => 'Other',
				'countries'      => array(),
				'formats'        => array( 'xml', 'txt', 'csv' ),
				'encoding'       => 'UTF-8',
				'xml'            => array(),
				'variation_mode' => 'parent',
				'raw'            => true,
				'notes'          => 'Test stub.',
				'availability'   => array(),
				'fields'         => array(),
			);
		}
		static function google() {
			return array(
				'id'             => 'google',
				'label'          => 'Google Shopping',
				'group'          => 'Search and shopping',
				'countries'      => array(),
				'formats'        => array( 'xml', 'tsv', 'csv', 'txt', 'jsonl' ),
				'encoding'       => 'UTF-8',
				'xml'            => array(
					'root'       => 'rss',
					'root_attrs' => array( 'version' => '2.0', 'xmlns:g' => 'http://base.google.com/ns/1.0' ),
					'wrap'       => 'channel',
					'item'       => 'item',
					'prefix'     => 'g:',
					'head'       => array( 'title' => '{site_name}', 'link' => '{home_url}', 'description' => '{site_description}' ),
				),
				'variation_mode' => 'all',
				'google_category'=> true,
				'notes'          => 'Test stub.',
				'availability'   => array( 'in_stock' => 'in_stock', 'out_of_stock' => 'out_of_stock', 'backorder' => 'backorder', 'preorder' => 'preorder' ),
				'fields'         => array(
					'id'                    => array( 'label' => 'Product ID', 'required' => true ),
					'item_group_id'         => array( 'label' => 'Item group ID', 'required' => false ),
					'title'                 => array( 'label' => 'Title', 'required' => true, 'max' => 150 ),
					'description'           => array( 'label' => 'Description', 'required' => true, 'max' => 5000 ),
					'link'                  => array( 'label' => 'Link', 'required' => true ),
					'image_link'            => array( 'label' => 'Image link', 'required' => true ),
					'additional_image_link' => array( 'label' => 'Additional image links', 'required' => false ),
					'availability'          => array( 'label' => 'Availability', 'required' => true ),
					'price'                 => array( 'label' => 'Price', 'required' => true, 'unit' => 'currency' ),
					'sale_price'            => array( 'label' => 'Sale price', 'required' => false, 'unit' => 'currency' ),
					'brand'                 => array( 'label' => 'Brand', 'required' => 'recommended' ),
					'gtin'                  => array( 'label' => 'GTIN', 'required' => 'recommended' ),
					'shipping'              => array( 'label' => 'Shipping', 'required' => false, 'children' => array( 'country', 'service', 'price' ) ),
				),
			);
		}
	}
}

if ( ! class_exists( 'Alg_WC_PXF_Product_Data' ) ) {
	class Alg_WC_PXF_Product_Data {
		static function model( WC_Product $product, array $config ) {
			$text   = function ( $html ) {
				return trim( html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) $html ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			};
			$images = array();
			foreach ( array_slice( (array) $product->get_gallery_image_ids(), 0, 10 ) as $image_id ) {
				$url = wp_get_attachment_url( $image_id );
				if ( $url ) {
					$images[] = $url;
				}
			}
			$status = $product->get_stock_status();
			$map    = array( 'instock' => 'in_stock', 'outofstock' => 'out_of_stock', 'onbackorder' => 'backorder' );
			$image  = $product->get_image_id() ? wp_get_attachment_url( $product->get_image_id() ) : '';
			$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : null;
			return array(
				'id'                     => (string) $product->get_id(),
				'item_group_id'          => $product->get_parent_id() ? (string) $product->get_parent_id() : '',
				'title'                  => wp_strip_all_tags( $product->get_name() ),
				'description'            => $text( $product->get_description() ? $product->get_description() : ( $parent ? $parent->get_description() : '' ) ),
				'short_description'      => $text( $product->get_short_description() ),
				'link'                   => (string) $product->get_permalink(),
				'image_link'             => $image ? $image : '',
				'additional_image_links' => $images,
				'price'                  => '' !== (string) $product->get_regular_price() ? wc_format_decimal( $product->get_regular_price(), 2 ) : '',
				'sale_price'             => $product->is_on_sale() ? wc_format_decimal( $product->get_sale_price(), 2 ) : '',
				'sale_price_effective_date' => '',
				'currency'               => '' !== $config['currency'] ? $config['currency'] : get_woocommerce_currency(),
				'availability'           => isset( $map[ $status ] ) ? $map[ $status ] : 'in_stock',
				'stock_quantity'         => null === $product->get_stock_quantity() ? '' : (int) $product->get_stock_quantity(),
				'condition'              => 'new',
				'gtin'                   => method_exists( $product, 'get_global_unique_id' ) ? (string) $product->get_global_unique_id() : '',
				'mpn'                    => '',
				'brand'                  => '',
				'identifier_exists'      => 'no',
				'google_product_category'=> '',
				'product_type'           => '',
				'color'                  => '',
				'size'                   => '',
				'material'               => '',
				'pattern'                => '',
				'gender'                 => '',
				'age_group'              => '',
				'weight'                 => '',
				'shipping_weight'        => '',
				'attributes'             => array(),
				'ai_title'               => '',
				'ai_description'         => '',
				'type'                   => $product->get_type(),
				'parent_title'           => $parent ? $parent->get_name() : '',
			);
		}
	}
}

if ( ! class_exists( 'Alg_WC_PXF_Mapper' ) ) {
	class Alg_WC_PXF_Mapper {
		static function items( array $model, array $channel, array $config ) {
			return array( self::item( $model, $channel, $config ) );
		}
		static function custom_part( $part, $config ) {
			return 'header' === $part ? '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<products>' . "\n" : '</products>' . "\n";
		}
		static function item( array $model, array $channel, array $config ) {
			if ( ! empty( $channel['raw'] ) ) {
				$raw = '<product><id>' . $model['id'] . '</id><name>' . htmlspecialchars( $model['title'], ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8' ) . '</name></product>' . "\n";
				return apply_filters( 'alg_wc_pxf_item', array( '_raw' => $raw ), $model, $channel, $config );
			}
			$item = array();
			foreach ( $channel['fields'] as $field => $spec ) {
				switch ( $field ) {
					case 'additional_image_link':
						$value = $model['additional_image_links'];
						break;
					case 'price':
					case 'sale_price':
						$value = '' !== $model[ $field ] ? $model[ $field ] . ' ' . $model['currency'] : '';
						break;
					case 'availability':
						$value = isset( $channel['availability'][ $model['availability'] ] ) ? $channel['availability'][ $model['availability'] ] : $model['availability'];
						break;
					case 'shipping':
						$value = array( 'country' => $config['country'], 'service' => 'Standard', 'price' => '4.95 ' . $model['currency'] );
						break;
					default:
						$value = isset( $model[ $field ] ) ? $model[ $field ] : '';
				}
				$item[ $field ] = $value;
			}
			return apply_filters( 'alg_wc_pxf_item', $item, $model, $channel, $config );
		}
	}
}

if ( ! class_exists( 'Alg_WC_PXF_Validator' ) ) {
	class Alg_WC_PXF_Validator {
		static function check( array $item, array $model, array $channel ) {
			$issues = array();
			if ( '' === $model['image_link'] ) {
				$issues[] = array( 'code' => 'missing_image', 'field' => 'image_link', 'severity' => 'error', 'message' => 'This product has no image.', 'fix' => 'product' );
			}
			if ( '' === $model['brand'] ) {
				$issues[] = array( 'code' => 'missing_brand', 'field' => 'brand', 'severity' => 'warning', 'message' => 'This product has no brand.', 'fix' => 'ai_attributes' );
			}
			return $issues;
		}
	}
}
