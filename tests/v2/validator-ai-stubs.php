<?php
/**
 * TEST ONLY: stand-ins for the channels and engine modules (built by other workers), so the validator and AI tests run
 * on their own. Loaded by a mu-plugin on worker C's QA site only; never required by plugin code. Every class is
 * defined only when the real one is missing.
 *
 * @package Alg_WC_PXF_Tests
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
	/**
	 * Channels stand-in: a Google spec with the fields the validator reads.
	 */
	class Alg_WC_PXF_Channels {

		/**
		 * All specs.
		 *
		 * @return array
		 */
		public static function all() {
			return array( 'google' => self::get( 'google' ) );
		}

		/**
		 * One spec.
		 *
		 * @param string $id Channel id.
		 * @return array|null
		 */
		public static function get( $id ) {
			if ( 'google' !== $id ) {
				return null;
			}
			return array(
				'id'             => 'google',
				'label'          => 'Google Shopping',
				'group'          => 'Search and shopping',
				'countries'      => array(),
				'formats'        => array( 'xml', 'tsv' ),
				'encoding'       => 'UTF-8',
				'variation_mode' => 'all',
				'google_category'=> true,
				'notes'          => 'Test spec.',
				'availability'   => array( 'in_stock' => 'in_stock', 'out_of_stock' => 'out_of_stock', 'backorder' => 'backorder', 'preorder' => 'preorder' ),
				'fields'         => array(
					'id'                => array( 'label' => 'Product ID', 'required' => true, 'max' => 50 ),
					'title'             => array( 'label' => 'Title', 'required' => true, 'max' => 150 ),
					'description'       => array( 'label' => 'Description', 'required' => true, 'max' => 5000 ),
					'link'              => array( 'label' => 'Product link', 'required' => true ),
					'image_link'        => array( 'label' => 'Main image', 'required' => true ),
					'availability'      => array( 'label' => 'Availability', 'required' => true, 'allowed' => array( 'in_stock', 'out_of_stock', 'backorder', 'preorder' ) ),
					'price'             => array( 'label' => 'Price', 'required' => true, 'unit' => 'currency' ),
					'sale_price'        => array( 'label' => 'Sale price', 'required' => false, 'unit' => 'currency' ),
					'brand'             => array( 'label' => 'Brand', 'required' => true, 'max' => 70 ),
					'gtin'              => array( 'label' => 'GTIN', 'required' => 'recommended' ),
					'mpn'               => array( 'label' => 'MPN', 'required' => 'conditional', 'when' => 'no_gtin', 'max' => 70 ),
					'identifier_exists' => array( 'label' => 'Identifier exists', 'required' => false, 'allowed' => array( 'yes', 'no' ) ),
					'condition'         => array( 'label' => 'Condition', 'required' => false, 'allowed' => array( 'new', 'refurbished', 'used' ) ),
					'color'             => array( 'label' => 'Color', 'required' => 'recommended' ),
				),
			);
		}
	}
}

if ( ! function_exists( 'alg_wc_pxf_test_model' ) ) {
	/**
	 * A canonical model with every key (test values).
	 *
	 * @param array $over Overrides.
	 * @return array
	 */
	function alg_wc_pxf_test_model( array $over = array() ) {
		return array_merge(
			array(
				'id'                     => '101',
				'item_group_id'          => '',
				'title'                  => 'Northwind Merino Wool Beanie, Charcoal',
				'description'            => 'A warm beanie knitted from merino wool.',
				'short_description'      => '',
				'link'                   => 'https://shop.example.test/product/beanie/',
				'image_link'             => 'https://shop.example.test/wp-content/uploads/beanie.jpg',
				'additional_image_links' => array(),
				'price'                  => '20.00',
				'sale_price'             => '',
				'sale_price_effective_date' => '',
				'currency'               => 'USD',
				'availability'           => 'in_stock',
				'stock_quantity'         => 5,
				'condition'              => 'new',
				'gtin'                   => '4006381333931',
				'mpn'                    => '',
				'brand'                  => 'Northwind',
				'identifier_exists'      => 'yes',
				'google_product_category'=> '166',
				'product_type'           => 'Clothing > Hats',
				'color'                  => 'Charcoal',
				'size'                   => '',
				'material'               => 'Merino wool',
				'pattern'                => '',
				'gender'                 => '',
				'age_group'              => '',
				'weight'                 => '',
				'shipping_weight'        => '',
				'attributes'             => array(),
				'ai_title'               => '',
				'ai_description'         => '',
				'type'                   => 'simple',
				'parent_title'           => '',
			),
			$over
		);
	}
}

if ( ! function_exists( 'alg_wc_pxf_test_item' ) ) {
	/**
	 * A Google item from a model, roughly as the mapper would make it (test only).
	 *
	 * @param array $model Model.
	 * @param array $over  Overrides (null removes a field).
	 * @return array
	 */
	function alg_wc_pxf_test_item( array $model, array $over = array() ) {
		$item = array(
			'id'                => $model['id'],
			'title'             => $model['title'],
			'description'       => $model['description'],
			'link'              => $model['link'],
			'image_link'        => $model['image_link'],
			'availability'      => $model['availability'],
			'price'             => $model['price'] . ' ' . $model['currency'],
			'sale_price'        => '' !== $model['sale_price'] ? $model['sale_price'] . ' ' . $model['currency'] : '',
			'brand'             => $model['brand'],
			'gtin'              => $model['gtin'],
			'mpn'               => $model['mpn'],
			'identifier_exists' => $model['identifier_exists'],
			'condition'         => $model['condition'],
			'color'             => $model['color'],
		);
		foreach ( $over as $k => $v ) {
			if ( null === $v ) {
				unset( $item[ $k ] );
			} else {
				$item[ $k ] = $v;
			}
		}
		return $item;
	}
}
