<?php
/**
 * Product XML Feeds for WooCommerce - v2 Google product taxonomy.
 *
 * Bundled file assets/v2/data/google-product-taxonomy-en-US.txt (from
 * https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt), lines "ID - A > B > C".
 * Category map: term meta _alg_pxf_google_category (Google ID) on product_cat terms.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Google_Taxonomy' ) ) :

class Alg_WC_PXF_Google_Taxonomy {

	/**
	 * Term meta key of the category map.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const META_KEY = '_alg_pxf_google_category';

	/**
	 * Loaded taxonomy: id => path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @var array|null
	 */
	protected static $paths = null;

	/**
	 * Words left out of keyword matching.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const STOP_WORDS = array( 'and', 'the', 'for', 'with', 'of', 'in', 'on', 'a', 'an', 'to', 'by', 'or', 'other', 'others', 'accessories', 'misc', 'new', 'sale', 'all' );

	/**
	 * Common shop words that are not Google category words (stemmed word => taxonomy words).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const SYNONYMS = array(
		'hoodie'     => array( 'shirt', 'top', 'activewear' ),
		'hoody'      => array( 'shirt', 'top', 'activewear' ),
		'beany'      => array( 'hat' ),
		'blous'      => array( 'shirt', 'top' ),
		'purs'       => array( 'handbag', 'wallet' ),
		'sweatshirt' => array( 'shirt', 'top', 'activewear' ),
		'sweater'    => array( 'shirt', 'top' ),
		'jumper'     => array( 'shirt', 'top' ),
		'pullover'   => array( 'shirt', 'top' ),
		'tee'        => array( 'shirt', 'top' ),
		'tshirt'     => array( 'shirt', 'top' ),
		'polo'       => array( 'shirt', 'top' ),
		'blouse'     => array( 'shirt', 'top' ),
		'jean'       => array( 'pant' ),
		'trouser'    => array( 'pant' ),
		'legging'    => array( 'pant' ),
		'jacket'     => array( 'coat', 'jacket', 'outerwear' ),
		'parka'      => array( 'coat', 'jacket', 'outerwear' ),
		'sneaker'    => array( 'shoe', 'athletic' ),
		'trainer'    => array( 'shoe', 'athletic' ),
		'boot'       => array( 'shoe', 'boot' ),
		'beanie'     => array( 'hat' ),
		'cap'        => array( 'hat' ),
		'bag'        => array( 'handbag', 'bag' ),
		'purse'      => array( 'handbag', 'wallet' ),
		'phone'      => array( 'mobile', 'phone' ),
		'laptop'     => array( 'laptop', 'computer' ),
	);

	/**
	 * file.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function file() {
		return dirname( __DIR__, 3 ) . '/assets/v2/data/google-product-taxonomy-en-US.txt';
	}

	/**
	 * all: id => full path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function all() {
		if ( null === self::$paths ) {
			self::$paths = array();
			$file        = self::file();
			$lines       = ( is_readable( $file ) ? file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array() );
			foreach ( (array) $lines as $line ) {
				if ( '#' === substr( $line, 0, 1 ) ) {
					continue;
				}
				$parts = explode( ' - ', $line, 2 );
				if ( 2 === count( $parts ) && ctype_digit( trim( $parts[0] ) ) ) {
					self::$paths[ (int) $parts[0] ] = trim( $parts[1] );
				}
			}
		}
		return self::$paths;
	}

	/**
	 * get.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int|string $id Google category ID.
	 * @return  array|null array( 'id' => int, 'name' => leaf name, 'path' => full path ).
	 */
	static function get( $id ) {
		$all = self::all();
		$id  = (int) $id;
		if ( ! isset( $all[ $id ] ) ) {
			return null;
		}
		$names = explode( ' > ', $all[ $id ] );
		return array(
			'id'   => $id,
			'name' => end( $names ),
			'path' => $all[ $id ],
		);
	}

	/**
	 * path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int|string $id Google category ID.
	 * @return  string '' when unknown.
	 */
	static function path( $id ) {
		$all = self::all();
		return ( isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : '' );
	}

	/**
	 * search: an ID, or words that must all appear in the path; leaf matches first.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $q     Query.
	 * @param   int    $limit Max results.
	 * @return  array List of array( 'id', 'name', 'path' ).
	 */
	static function search( $q, $limit = 20 ) {
		$q     = trim( (string) $q );
		$limit = max( 1, (int) $limit );
		if ( '' === $q ) {
			return array();
		}
		if ( ctype_digit( $q ) ) {
			$one = self::get( $q );
			return ( $one ? array( $one ) : array() );
		}
		$words = self::words( $q, false );
		if ( empty( $words ) ) {
			return array();
		}
		$scored = array();
		foreach ( self::all() as $id => $path ) {
			$lower = strtolower( $path );
			$ok    = true;
			foreach ( $words as $word ) {
				if ( false === strpos( $lower, $word ) ) {
					$ok = false;
					break;
				}
			}
			if ( ! $ok ) {
				continue;
			}
			$names = explode( ' > ', $lower );
			$leaf  = end( $names );
			$score = 0;
			foreach ( $words as $word ) {
				if ( false !== strpos( $leaf, $word ) ) {
					$score += 10;
				}
			}
			if ( $leaf === strtolower( $q ) ) {
				$score += 50;
			}
			$score -= count( $names );
			$scored[ $id ] = $score;
		}
		arsort( $scored );
		$out = array();
		foreach ( array_slice( $scored, 0, $limit, true ) as $id => $score ) {
			$out[] = self::get( $id );
		}
		return $out;
	}

	/**
	 * shortlist: local keyword shortlist of likely Google categories for a shop category name (for the AI worker).
	 *
	 * Scores every taxonomy entry by the words of the name (leaf words count most, simple plural forms match);
	 * when nothing matches, returns the top level categories so there is always something to choose from.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $term_name Shop category name (or a "A > B" path).
	 * @param   int    $limit     Max entries.
	 * @return  array id => path, best first.
	 */
	static function shortlist( $term_name, $limit = 40 ) {
		$limit = max( 1, (int) $limit );
		$words = self::words( $term_name, true );
		foreach ( $words as $word ) {
			if ( isset( self::SYNONYMS[ $word ] ) ) {
				$words = array_merge( $words, self::SYNONYMS[ $word ] );
			}
		}
		$words = array_values( array_unique( $words ) );
		$all   = self::all();
		$out   = array();
		if ( ! empty( $words ) ) {
			$scored = array();
			foreach ( $all as $id => $path ) {
				$names = explode( ' > ', strtolower( $path ) );
				$leaf  = self::words( end( $names ), true );
				$rest  = self::words( implode( ' ', array_slice( $names, 0, -1 ) ), true );
				$score = 0;
				foreach ( $words as $word ) {
					if ( in_array( $word, $leaf, true ) ) {
						$score += 6;
					} elseif ( in_array( $word, $rest, true ) ) {
						$score += 2;
					}
				}
				if ( $score > 0 ) {
					// Shorter paths first among equal scores; exact leaf match first of all.
					$scored[ $id ] = $score * 100 + ( $leaf === $words ? 500 : 0 ) - count( $names );
				}
			}
			arsort( $scored );
			foreach ( array_slice( $scored, 0, $limit, true ) as $id => $score ) {
				$out[ $id ] = $all[ $id ];
			}
		}
		if ( empty( $out ) ) {
			foreach ( $all as $id => $path ) {
				if ( false === strpos( $path, ' > ' ) ) {
					$out[ $id ] = $path;
				}
			}
			$out = array_slice( $out, 0, $limit, true );
		}
		return $out;
	}

	/**
	 * words: lower case words of a text, stop words left out; with $stem, plural endings removed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @param   bool   $stem Remove plural endings.
	 * @return  array
	 */
	static function words( $text, $stem = false ) {
		$text  = strtolower( remove_accents( wp_strip_all_tags( (string) $text ) ) );
		$parts = preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$out   = array();
		foreach ( (array) $parts as $word ) {
			if ( in_array( $word, self::STOP_WORDS, true ) || strlen( $word ) < 2 ) {
				continue;
			}
			if ( $stem ) {
				$word = self::stem( $word );
			}
			$out[] = $word;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * stem: very small English plural stemmer (shirts, dresses, accessories, knives).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $word Word.
	 * @return  string
	 */
	static function stem( $word ) {
		if ( strlen( $word ) > 4 && 'ies' === substr( $word, -3 ) ) {
			return substr( $word, 0, -3 ) . 'y';
		}
		if ( strlen( $word ) > 4 && 'ves' === substr( $word, -3 ) ) {
			return substr( $word, 0, -3 ) . 'f';
		}
		if ( strlen( $word ) > 4 && preg_match( '/(ses|xes|zes|ches|shes)$/', $word ) ) {
			return preg_replace( '/es$/', '', $word );
		}
		if ( strlen( $word ) > 3 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
			return substr( $word, 0, -1 );
		}
		return $word;
	}

	/**
	 * get_category: mapped Google ID of one product_cat term ('' when none).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $term_id product_cat term ID.
	 * @return  string
	 */
	static function get_category( $term_id ) {
		$value = get_term_meta( (int) $term_id, self::META_KEY, true );
		return ( '' !== $value && null !== $value && ctype_digit( (string) $value ) ? (string) $value : '' );
	}

	/**
	 * set_category: map a product_cat term to a Google ID ('' or 0 removes the map).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int        $term_id   product_cat term ID.
	 * @param   int|string $google_id Google category ID.
	 * @return  bool
	 */
	static function set_category( $term_id, $google_id ) {
		$google_id = (int) $google_id;
		if ( $google_id <= 0 ) {
			return (bool) delete_term_meta( (int) $term_id, self::META_KEY );
		}
		if ( null === self::get( $google_id ) ) {
			return false;
		}
		return false !== update_term_meta( (int) $term_id, self::META_KEY, (string) $google_id );
	}

	/**
	 * for_product: Google ID from the category map; the deepest mapped category of the product, then its parents.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product or variation.
	 * @return  string '' when no category is mapped.
	 */
	static function for_product( $product ) {
		$id = ( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
		foreach ( self::categories_deepest_first( $id ) as $term ) {
			$check = $term->term_id;
			while ( $check ) {
				$google = self::get_category( $check );
				if ( '' !== $google ) {
					return $google;
				}
				$parent = get_term( $check, 'product_cat' );
				$check  = ( $parent && ! is_wp_error( $parent ) ? (int) $parent->parent : 0 );
			}
		}
		return '';
	}

	/**
	 * categories_deepest_first: product_cat terms of a product, deepest first (ties: lowest term ID).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $product_id Product ID (not a variation).
	 * @return  WP_Term[]
	 */
	static function categories_deepest_first( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}
		$depth = array();
		foreach ( $terms as $term ) {
			$depth[ $term->term_id ] = count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
		}
		usort(
			$terms,
			function ( $a, $b ) use ( $depth ) {
				if ( $depth[ $a->term_id ] === $depth[ $b->term_id ] ) {
					return $a->term_id - $b->term_id;
				}
				return $depth[ $b->term_id ] - $depth[ $a->term_id ];
			}
		);
		return $terms;
	}

}

endif;
