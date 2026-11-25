<?php
/**
 * Product XML Feeds for WooCommerce - v2 validator.
 *
 * Alg_WC_PXF_Validator::check( $item, $model, $channel ) returns the problems of one feed item, each as
 * array( code, field, severity (error|warning), message (plain words), fix (ai_title|ai_description|ai_attributes|mapping|product) ).
 * Alg_WC_PXF_Validator::summary( $issues ) turns issues (or counts by code) into totals and the top reasons.
 *
 * Rules: required and conditional fields from the channel spec, max lengths, allowed values, price above 0, sale
 * price lower than the price, a main image that is not the WooCommerce placeholder, GTIN length, check digit and
 * Google's banned prefixes, promo words and all caps in titles, and the identifier rules (GTIN, or brand and MPN).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Validator' ) ) :

class Alg_WC_PXF_Validator {

	/**
	 * Field ids that hold the product title, by channel naming.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const TITLE_FIELDS = array( 'title', 'name', 'product_name' );

	/**
	 * Field ids that hold the description.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const DESCRIPTION_FIELDS = array( 'description', 'product_description' );

	/**
	 * Field ids that hold the main image.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const IMAGE_FIELDS = array( 'image_link', 'image', 'image_url', 'main_image' );

	/**
	 * Field ids that hold the GTIN.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const GTIN_FIELDS = array( 'gtin', 'ean', 'upc', 'barcode' );

	/**
	 * Fields AI can fill (feed-only values).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const AI_ATTRIBUTES = array( 'brand', 'color', 'size', 'material', 'pattern', 'gender', 'age_group', 'manufacturer' );

	/**
	 * GTIN prefixes Google rejects (restricted circulation 02, 04, 2; coupons 98, 99), on the 13-digit form.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const BANNED_GTIN_PREFIXES = array( '02', '04', '2', '98', '99' );

	/**
	 * Codes of the last check() call, kept for tests and the preview.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @var array
	 */
	protected static $seen = array();

	/**
	 * check.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $item    Field => value (string or array) as the mapper made it for the channel.
	 * @param   array $model   Canonical product model (Alg_WC_PXF_Product_Data::model()).
	 * @param   array $channel Channel spec (Alg_WC_PXF_Channels::get()).
	 * @return  array Issues.
	 */
	static function check( array $item, array $model, array $channel ) {
		self::$seen = array();
		$issues     = array();
		$fields     = ( isset( $channel['fields'] ) && is_array( $channel['fields'] ) ) ? $channel['fields'] : array();
		$generic    = empty( $fields ); // Custom templates: only the checks that matter everywhere.

		// Required, conditional and recommended fields; max lengths; allowed values.
		foreach ( $fields as $field => $spec ) {
			$spec     = is_array( $spec ) ? $spec : array();
			$value    = self::item_value( $item, $field );
			$empty    = self::is_empty( $value );
			$required = isset( $spec['required'] ) ? $spec['required'] : false;
			$label    = self::label( $field, $spec );
			if ( $empty ) {
				if ( true === $required ) {
					self::add( $issues, 'missing_' . $field, $field, 'error', sprintf( /* translators: %s: field name, for example "Brand". */ __( '%s is missing. This channel needs it.', 'product-xml-feeds-for-woocommerce' ), $label ) );
				} elseif (
					'conditional' === $required &&
					self::condition_holds( isset( $spec['when'] ) ? $spec['when'] : '', $item, $model ) &&
					// A product sent with identifier_exists "no" needs no GTIN or MPN (Google's rule); it gets one warning instead.
					! ( in_array( $field, array( 'gtin', 'mpn' ), true ) && 'no' === self::scalar( self::item_value( $item, 'identifier_exists' ) ) )
				) {
					self::add( $issues, 'missing_' . $field, $field, 'error', sprintf( /* translators: %s: field name, for example "MPN". */ __( '%s is missing. This channel needs it for this product.', 'product-xml-feeds-for-woocommerce' ), $label ) );
				} elseif ( 'recommended' === $required ) {
					self::add( $issues, 'missing_' . $field, $field, 'warning', sprintf( /* translators: %s: field name, for example "Color". */ __( '%s is missing. Adding it helps the product show up in more searches.', 'product-xml-feeds-for-woocommerce' ), $label ) );
				}
				continue;
			}
			if ( ! empty( $spec['max'] ) && is_scalar( $value ) && self::length( (string) $value ) > (int) $spec['max'] ) {
				self::add(
					$issues,
					'too_long_' . $field,
					$field,
					true === $required ? 'error' : 'warning',
					/* translators: 1: field name, 2: number of characters, 3: most characters allowed. */
					sprintf( __( '%1$s has %2$s characters. This channel allows %3$s.', 'product-xml-feeds-for-woocommerce' ), $label, number_format_i18n( self::length( (string) $value ) ), number_format_i18n( (int) $spec['max'] ) )
				);
			}
			if ( ! empty( $spec['allowed'] ) && is_array( $spec['allowed'] ) && is_scalar( $value ) && ! self::is_allowed( (string) $value, $spec['allowed'] ) ) {
				self::add(
					$issues,
					'invalid_' . $field,
					$field,
					'error',
					/* translators: 1: field name, 2: the value, 3: list of accepted values. */
					sprintf( __( '%1$s "%2$s" is not accepted. Use one of: %3$s.', 'product-xml-feeds-for-woocommerce' ), $label, wp_strip_all_tags( (string) $value ), implode( ', ', array_map( 'strval', array_slice( array_values( $spec['allowed'] ), 0, 12 ) ) ) )
				);
			}
		}

		// Prices.
		if ( $generic || isset( $fields['price'] ) ) {
			$price = self::number( isset( $model['price'] ) && '' !== (string) $model['price'] ? $model['price'] : self::item_value( $item, 'price' ) );
			if ( null === $price || $price <= 0 ) {
				if ( ! self::has( 'missing_price' ) ) {
					self::add( $issues, 'price_zero', 'price', 'error', __( 'The product has no price, or the price is 0. Channels do not accept free or unpriced products.', 'product-xml-feeds-for-woocommerce' ) );
				}
			} else {
				$sale = self::number( isset( $model['sale_price'] ) && '' !== (string) $model['sale_price'] ? $model['sale_price'] : self::item_value( $item, 'sale_price' ) );
				if ( null !== $sale && $sale >= $price ) {
					self::add( $issues, 'sale_not_lower', 'sale_price', 'error', __( 'The sale price is not lower than the regular price. Lower the sale price or remove it.', 'product-xml-feeds-for-woocommerce' ) );
				}
			}
		}

		// Main image.
		$image_field = self::first_field( $fields, self::IMAGE_FIELDS );
		if ( $generic || '' !== $image_field ) {
			$image_field = '' !== $image_field ? $image_field : 'image_link';
			$image       = isset( $model['image_link'] ) && '' !== (string) $model['image_link'] ? (string) $model['image_link'] : self::scalar( self::item_value( $item, $image_field ) );
			if ( '' === $image ) {
				if ( ! self::has( 'missing_' . $image_field ) ) {
					self::add( $issues, 'missing_image', $image_field, 'error', __( 'The product has no main image. Add a product image.', 'product-xml-feeds-for-woocommerce' ) );
				}
			} elseif ( self::is_placeholder( $image ) ) {
				self::add( $issues, 'placeholder_image', $image_field, 'error', __( 'The main image is the WooCommerce placeholder. Channels reject placeholder images: add a real product photo.', 'product-xml-feeds-for-woocommerce' ) );
			}
		}

		// GTIN.
		$gtin_field = self::first_field( $fields, self::GTIN_FIELDS );
		if ( $generic || '' !== $gtin_field ) {
			$gtin_field = '' !== $gtin_field ? $gtin_field : 'gtin';
			$gtin       = self::scalar( self::item_value( $item, $gtin_field ) );
			$gtin       = '' !== $gtin ? $gtin : ( isset( $model['gtin'] ) ? (string) $model['gtin'] : '' );
			if ( '' !== $gtin ) {
				$problem = self::gtin_problem( $gtin );
				if ( 'format' === $problem ) {
					self::add( $issues, 'gtin_invalid', $gtin_field, 'error', __( 'The GTIN must have 8, 12, 13 or 14 digits and nothing else (UPC, EAN, JAN, ISBN-13 or ITF-14).', 'product-xml-feeds-for-woocommerce' ) );
				} elseif ( 'check_digit' === $problem ) {
					self::add( $issues, 'gtin_check_digit', $gtin_field, 'error', __( 'The GTIN has a wrong last digit (check digit). Copy it again from the barcode on the package.', 'product-xml-feeds-for-woocommerce' ) );
				} elseif ( 'prefix' === $problem ) {
					self::add( $issues, 'gtin_banned_prefix', $gtin_field, 'error', __( 'The GTIN starts with a number reserved for in-store or coupon codes (02, 04, 2, 98 or 99). Google does not accept it: use the manufacturer\'s GTIN, or leave it empty.', 'product-xml-feeds-for-woocommerce' ) );
				}
			}
		}

		// Identifiers: GTIN, or brand and MPN; otherwise identifier_exists = no.
		if ( isset( $fields['identifier_exists'] ) || ( isset( $fields['mpn'] ) && '' !== $gtin_field ) ) {
			$has_gtin  = '' !== ( isset( $gtin ) ? $gtin : '' ) && ! self::has( 'gtin_invalid' ) && ! self::has( 'gtin_check_digit' ) && ! self::has( 'gtin_banned_prefix' );
			$brand     = self::scalar( self::item_value( $item, 'brand' ) );
			$brand     = '' !== $brand ? $brand : ( isset( $model['brand'] ) ? (string) $model['brand'] : '' );
			$mpn       = self::scalar( self::item_value( $item, 'mpn' ) );
			$mpn       = '' !== $mpn ? $mpn : ( isset( $model['mpn'] ) ? (string) $model['mpn'] : '' );
			$exists    = strtolower( self::scalar( self::item_value( $item, 'identifier_exists' ) ) );
			$exists    = '' !== $exists ? $exists : strtolower( isset( $model['identifier_exists'] ) ? (string) $model['identifier_exists'] : '' );
			$has_ids   = $has_gtin || ( '' !== $brand && '' !== $mpn );
			if ( ! $has_ids && ! in_array( $exists, array( 'no', 'false', 'n' ), true ) ) {
				self::add( $issues, 'missing_identifiers', $gtin_field ? $gtin_field : 'gtin', 'warning', __( 'The product has no GTIN, and no brand with an MPN. Add the GTIN from the barcode; for handmade or own-brand products the feed says it has none.', 'product-xml-feeds-for-woocommerce' ) );
			} elseif ( $has_gtin && in_array( $exists, array( 'no', 'false', 'n' ), true ) ) {
				self::add( $issues, 'identifier_conflict', 'identifier_exists', 'warning', __( 'The product has a GTIN, but the feed says it has no identifiers. Remove the "no identifiers" setting for this product.', 'product-xml-feeds-for-woocommerce' ) );
			}
		}

		// Title rules (promo words, all caps). Checked on what the feed sends: the AI title when one is used.
		$title_field = self::first_field( $fields, self::TITLE_FIELDS );
		if ( '' !== $title_field || $generic ) {
			$title_field = '' !== $title_field ? $title_field : 'title';
			$title       = self::scalar( self::item_value( $item, $title_field ) );
			if ( '' !== $title ) {
				$promo = self::promo_words( $title );
				if ( $promo ) {
					self::add(
						$issues,
						'title_promo',
						$title_field,
						'warning',
						/* translators: %s: the promotional words found, for example "sale, free shipping". */
						sprintf( __( 'The title has promotional words (%s). Channels ask for the product name and its details only: no sale words, prices or shipping offers.', 'product-xml-feeds-for-woocommerce' ), implode( ', ', $promo ) )
					);
				}
				if ( self::is_all_caps( $title ) ) {
					self::add( $issues, 'title_all_caps', $title_field, 'warning', __( 'The title is written in capital letters only. Channels ask for normal capitalization.', 'product-xml-feeds-for-woocommerce' ) );
				}
			}
		}

		/**
		 * Issues of one feed item.
		 *
		 * @param array $issues  Issues.
		 * @param array $item    Item.
		 * @param array $model   Product model.
		 * @param array $channel Channel spec.
		 */
		return (array) apply_filters( 'alg_wc_pxf_validator_issues', $issues, $item, $model, $channel );
	}

	/**
	 * summary.
	 *
	 * Accepts a flat list of issues, a list of issue lists (by product), or counts by code (as the engine keeps
	 * them in the feed state).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $issues Issues.
	 * @param   int   $top    How many top reasons.
	 * @return  array errors, warnings, total, products (with issues, when known), by_code (code => count, severity, field, fix, reason), top (list of the same, most frequent first).
	 */
	static function summary( array $issues, $top = 5 ) {
		$by_code  = array();
		$products = 0;
		$known    = false;
		$add      = function ( $code, $count, $severity = '', $field = '', $fix = '' ) use ( &$by_code ) {
			$code = sanitize_key( $code );
			if ( '' === $code || $count <= 0 ) {
				return;
			}
			if ( ! isset( $by_code[ $code ] ) ) {
				$by_code[ $code ] = array(
					'code'     => $code,
					'count'    => 0,
					'severity' => '' !== $severity ? $severity : self::severity_of( $code ),
					'field'    => '' !== $field ? $field : self::field_of( $code ),
					'fix'      => '' !== $fix ? $fix : self::fix_for( self::field_of( $code ), $code ),
				);
			}
			$by_code[ $code ]['count'] += (int) $count;
		};
		foreach ( $issues as $key => $entry ) {
			if ( is_int( $entry ) || ( is_string( $entry ) && is_numeric( $entry ) ) ) {
				$add( (string) $key, (int) $entry ); // Counts by code.
			} elseif ( is_array( $entry ) && isset( $entry['code'] ) ) {
				$add( (string) $entry['code'], 1, (string) ( $entry['severity'] ?? '' ), (string) ( $entry['field'] ?? '' ), (string) ( $entry['fix'] ?? '' ) );
			} elseif ( is_array( $entry ) ) {
				$known = true;
				if ( $entry ) {
					++$products;
				}
				$codes = array();
				foreach ( $entry as $one ) {
					if ( is_array( $one ) && isset( $one['code'] ) && ! isset( $codes[ $one['code'] ] ) ) {
						$codes[ $one['code'] ] = true;
						$add( (string) $one['code'], 1, (string) ( $one['severity'] ?? '' ), (string) ( $one['field'] ?? '' ), (string) ( $one['fix'] ?? '' ) );
					}
				}
			}
		}
		$errors   = 0;
		$warnings = 0;
		foreach ( $by_code as $code => $row ) {
			$by_code[ $code ]['reason'] = self::reason( $code, $row['count'] );
			if ( 'error' === $row['severity'] ) {
				$errors += $row['count'];
			} else {
				$warnings += $row['count'];
			}
		}
		$list = array_values( $by_code );
		usort(
			$list,
			function ( $a, $b ) {
				if ( $a['severity'] !== $b['severity'] ) {
					return 'error' === $a['severity'] ? -1 : 1;
				}
				return $b['count'] - $a['count'];
			}
		);
		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
			'total'    => $errors + $warnings,
			'products' => $known ? $products : null,
			'by_code'  => $by_code,
			'top'      => array_slice( $list, 0, max( 1, (int) $top ) ),
		);
	}

	/**
	 * reason: one plain line for a code and a count of products ("12 products have no brand").
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $code  Issue code.
	 * @param   int    $count Products.
	 * @return  string
	 */
	static function reason( $code, $count ) {
		$n = number_format_i18n( (int) $count );
		switch ( $code ) {
			case 'price_zero':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has no price or a price of 0', '%s products have no price or a price of 0', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'sale_not_lower':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has a sale price that is not lower than the price', '%s products have a sale price that is not lower than the price', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'missing_image':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has no main image', '%s products have no main image', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'placeholder_image':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product shows the placeholder image', '%s products show the placeholder image', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'gtin_invalid':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has a GTIN in the wrong format', '%s products have a GTIN in the wrong format', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'gtin_check_digit':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has a GTIN with a wrong check digit', '%s products have a GTIN with a wrong check digit', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'gtin_banned_prefix':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has a GTIN Google does not accept', '%s products have a GTIN Google does not accept', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'missing_identifiers':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has no GTIN and no brand with MPN', '%s products have no GTIN and no brand with MPN', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'identifier_conflict':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s product has a GTIN but is marked as having none', '%s products have a GTIN but are marked as having none', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'title_promo':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s title has promotional words', '%s titles have promotional words', $count, 'product-xml-feeds-for-woocommerce' ), $n );
			case 'title_all_caps':
				/* translators: %s: number of products. */
				return sprintf( _n( '%s title is in capital letters only', '%s titles are in capital letters only', $count, 'product-xml-feeds-for-woocommerce' ), $n );
		}
		$field = self::field_of( $code );
		$label = self::label( $field );
		if ( 0 === strpos( $code, 'missing_' ) ) {
			/* translators: 1: number of products, 2: field name, for example "Brand". */
			return sprintf( _n( '%1$s product has no %2$s', '%1$s products have no %2$s', $count, 'product-xml-feeds-for-woocommerce' ), $n, $label );
		}
		if ( 0 === strpos( $code, 'too_long_' ) ) {
			/* translators: 1: number of products, 2: field name, for example "Title". */
			return sprintf( _n( '%1$s product has a %2$s that is too long', '%1$s products have a %2$s that is too long', $count, 'product-xml-feeds-for-woocommerce' ), $n, $label );
		}
		if ( 0 === strpos( $code, 'invalid_' ) ) {
			/* translators: 1: number of products, 2: field name, for example "Availability". */
			return sprintf( _n( '%1$s product has a %2$s value the channel does not accept', '%1$s products have a %2$s value the channel does not accept', $count, 'product-xml-feeds-for-woocommerce' ), $n, $label );
		}
		/* translators: 1: number of products, 2: issue code. */
		return sprintf( _n( '%1$s product: %2$s', '%1$s products: %2$s', $count, 'product-xml-feeds-for-woocommerce' ), $n, str_replace( '_', ' ', $code ) );
	}

	/**
	 * GTIN problem: '' (fine), 'format', 'check_digit' or 'prefix'.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $gtin GTIN as entered (spaces and dashes allowed).
	 * @return  string
	 */
	static function gtin_problem( $gtin ) {
		$digits = preg_replace( '/[\s\-]+/', '', (string) $gtin );
		if ( ! preg_match( '/^\d+$/', $digits ) || ! in_array( strlen( $digits ), array( 8, 12, 13, 14 ), true ) || '' === trim( $digits, '0' ) ) {
			return 'format';
		}
		if ( ! self::gtin_check_digit_ok( $digits ) ) {
			return 'check_digit';
		}
		// 13-digit form for the prefix rules: UPC-12 gets a leading 0, GTIN-14 drops its indicator digit.
		$len = strlen( $digits );
		if ( 12 === $len ) {
			$form = '0' . $digits;
		} elseif ( 14 === $len ) {
			$form = substr( $digits, 1 );
		} else {
			$form = $digits; // EAN-13, and EAN-8 as is (RCN-8 starts with 2).
		}
		foreach ( self::BANNED_GTIN_PREFIXES as $prefix ) {
			if ( 0 === strpos( $form, $prefix ) ) {
				return 'prefix';
			}
		}
		return '';
	}

	/**
	 * GS1 check digit.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $digits Digits only.
	 * @return  bool
	 */
	static function gtin_check_digit_ok( $digits ) {
		$body = substr( $digits, 0, -1 );
		$sum  = 0;
		$len  = strlen( $body );
		for ( $i = 0; $i < $len; $i++ ) {
			// From the right: weights 3, 1, 3, 1...
			$sum += (int) $body[ $len - 1 - $i ] * ( 0 === $i % 2 ? 3 : 1 );
		}
		return ( ( 10 - ( $sum % 10 ) ) % 10 ) === (int) substr( $digits, -1 );
	}

	/**
	 * Promotional words in a title.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $title Title.
	 * @return  string[] The words found.
	 */
	static function promo_words( $title ) {
		$patterns = array(
			'/\bfree\s+(?:shipping|delivery|gift|returns?)\b/iu',
			'/\b(?:on\s+)?sale\b/iu',
			'/\bbest\s*(?:seller|price|deal|buy)?\b/iu',
			'/\bcheap(?:est)?\b/iu',
			'/\bdiscount(?:ed)?\b/iu',
			'/\bclearance\b/iu',
			'/\b(?:hot\s+)?deals?\b/iu',
			'/\blowest\s+price\b/iu',
			'/\bbuy\s+now\b/iu',
			'/\blimited\s+(?:time\s+)?offer\b/iu',
			'/\bspecial\s+offer\b/iu',
			'/\b\d+(?:[.,]\d+)?\s*%\s*off\b/iu',
			'/\bsave\s+(?:up\s+to\s+)?[\d$\x{20AC}\x{00A3}]/iu',
			'/\bbargain\b/iu',
			'/!{2,}/u',
		);
		/**
		 * Patterns of promotional words checked in titles (regular expressions).
		 *
		 * @param array $patterns Patterns.
		 */
		$patterns = (array) apply_filters( 'alg_wc_pxf_promo_patterns', $patterns );
		$found    = array();
		foreach ( $patterns as $pattern ) {
			if ( @preg_match_all( $pattern, $title, $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- filtered patterns may be broken.
				foreach ( $m[0] as $hit ) {
					$found[ strtolower( trim( $hit ) ) ] = true;
				}
			}
		}
		return array_keys( $found );
	}

	/**
	 * Whether a title is all capitals (at least 8 letters, in a script with capitals).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $title Title.
	 * @return  bool
	 */
	static function is_all_caps( $title ) {
		$letters = preg_replace( '/[^\p{L}]+/u', '', (string) $title );
		if ( null === $letters || self::length( $letters ) < 8 ) {
			return false;
		}
		$upper = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letters, 'UTF-8' ) : strtoupper( $letters );
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $letters, 'UTF-8' ) : strtolower( $letters );
		return $letters === $upper && $upper !== $lower;
	}

	/**
	 * Whether an image URL is the WooCommerce placeholder.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $url Image URL.
	 * @return  bool
	 */
	static function is_placeholder( $url ) {
		$url = (string) $url;
		if ( false !== strpos( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ), 'woocommerce-placeholder' ) || false !== strpos( $url, 'assets/images/placeholder' ) ) {
			return true;
		}
		$known = array();
		if ( function_exists( 'wc_placeholder_img_src' ) ) {
			foreach ( array( 'woocommerce_thumbnail', 'woocommerce_single', 'full' ) as $size ) {
				$known[] = (string) wc_placeholder_img_src( $size );
			}
		}
		$id = (int) get_option( 'woocommerce_placeholder_image', 0 );
		if ( $id > 0 ) {
			$known[] = (string) wp_get_attachment_url( $id );
		}
		$strip = function ( $u ) {
			return preg_replace( '#^https?:#i', '', strtok( (string) $u, '?' ) );
		};
		$url   = $strip( $url );
		foreach ( array_filter( $known ) as $one ) {
			if ( $strip( $one ) === $url ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fix hint for a field.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $field Field id.
	 * @param   string $code  Issue code.
	 * @return  string ai_title, ai_description, ai_attributes, mapping or product.
	 */
	static function fix_for( $field, $code = '' ) {
		if ( in_array( $code, array( 'title_promo', 'title_all_caps' ), true ) || in_array( $field, self::TITLE_FIELDS, true ) || 'structured_title' === $field ) {
			return 'ai_title';
		}
		if ( in_array( $field, self::DESCRIPTION_FIELDS, true ) || 'structured_description' === $field ) {
			return 'ai_description';
		}
		if ( in_array( $field, self::AI_ATTRIBUTES, true ) ) {
			return 'ai_attributes';
		}
		if ( in_array( $field, array_merge( array( 'price', 'sale_price', 'link', 'mpn', 'availability', 'identifier_exists' ), self::IMAGE_FIELDS, self::GTIN_FIELDS ), true ) ) {
			return 'product';
		}
		return 'mapping';
	}

	/**
	 * Plain field name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $field Field id.
	 * @param   array  $spec  Field spec.
	 * @return  string
	 */
	static function label( $field, $spec = array() ) {
		if ( ! empty( $spec['label'] ) ) {
			return (string) $spec['label'];
		}
		$labels = array(
			'id'                      => __( 'Product ID', 'product-xml-feeds-for-woocommerce' ),
			'title'                   => __( 'Title', 'product-xml-feeds-for-woocommerce' ),
			'description'             => __( 'Description', 'product-xml-feeds-for-woocommerce' ),
			'link'                    => __( 'Product link', 'product-xml-feeds-for-woocommerce' ),
			'image_link'              => __( 'Main image', 'product-xml-feeds-for-woocommerce' ),
			'image'                   => __( 'Main image', 'product-xml-feeds-for-woocommerce' ),
			'price'                   => __( 'Price', 'product-xml-feeds-for-woocommerce' ),
			'sale_price'              => __( 'Sale price', 'product-xml-feeds-for-woocommerce' ),
			'availability'            => __( 'Availability', 'product-xml-feeds-for-woocommerce' ),
			'brand'                   => __( 'Brand', 'product-xml-feeds-for-woocommerce' ),
			'gtin'                    => __( 'GTIN', 'product-xml-feeds-for-woocommerce' ),
			'mpn'                     => __( 'MPN', 'product-xml-feeds-for-woocommerce' ),
			'color'                   => __( 'Color', 'product-xml-feeds-for-woocommerce' ),
			'size'                    => __( 'Size', 'product-xml-feeds-for-woocommerce' ),
			'material'                => __( 'Material', 'product-xml-feeds-for-woocommerce' ),
			'pattern'                 => __( 'Pattern', 'product-xml-feeds-for-woocommerce' ),
			'gender'                  => __( 'Gender', 'product-xml-feeds-for-woocommerce' ),
			'age_group'               => __( 'Age group', 'product-xml-feeds-for-woocommerce' ),
			'condition'               => __( 'Condition', 'product-xml-feeds-for-woocommerce' ),
			'google_product_category' => __( 'Google category', 'product-xml-feeds-for-woocommerce' ),
			'product_type'            => __( 'Product type', 'product-xml-feeds-for-woocommerce' ),
			'identifier_exists'       => __( 'Identifier exists', 'product-xml-feeds-for-woocommerce' ),
			'item_group_id'           => __( 'Variant group', 'product-xml-feeds-for-woocommerce' ),
		);
		return isset( $labels[ $field ] ) ? $labels[ $field ] : ucfirst( str_replace( '_', ' ', (string) $field ) );
	}

	/**
	 * The field an issue code is about.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $code Code.
	 * @return  string
	 */
	static function field_of( $code ) {
		$fixed = array(
			'price_zero'          => 'price',
			'sale_not_lower'      => 'sale_price',
			'missing_image'       => 'image_link',
			'placeholder_image'   => 'image_link',
			'gtin_invalid'        => 'gtin',
			'gtin_check_digit'    => 'gtin',
			'gtin_banned_prefix'  => 'gtin',
			'missing_identifiers' => 'gtin',
			'identifier_conflict' => 'identifier_exists',
			'title_promo'         => 'title',
			'title_all_caps'      => 'title',
		);
		if ( isset( $fixed[ $code ] ) ) {
			return $fixed[ $code ];
		}
		return (string) preg_replace( '/^(?:missing_|too_long_|invalid_)/', '', (string) $code );
	}

	/**
	 * Default severity of a code (for counts without details).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $code Code.
	 * @return  string
	 */
	static function severity_of( $code ) {
		return in_array( $code, array( 'missing_identifiers', 'identifier_conflict', 'title_promo', 'title_all_caps' ), true ) || 0 === strpos( $code, 'too_long_' ) ? 'warning' : 'error';
	}

	/**
	 * Whether a conditional requirement applies.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string|array $when  Condition(s), all must hold: no_gtin, no_mpn, no_brand, no_identifiers, has_gtin, on_sale, variant, apparel.
	 * @param   array        $item  Item.
	 * @param   array        $model Model.
	 * @return  bool
	 */
	static function condition_holds( $when, array $item, array $model ) {
		$get = function ( $key ) use ( $item, $model ) {
			$v = self::scalar( self::item_value( $item, $key ) );
			return '' !== $v ? $v : ( isset( $model[ $key ] ) && is_scalar( $model[ $key ] ) ? (string) $model[ $key ] : '' );
		};
		$all = array_filter( (array) $when );
		if ( ! $all ) {
			return false;
		}
		foreach ( $all as $cond ) {
			switch ( $cond ) {
				case 'no_gtin':
					$ok = '' === $get( 'gtin' );
					break;
				case 'has_gtin':
					$ok = '' !== $get( 'gtin' );
					break;
				case 'no_mpn':
					$ok = '' === $get( 'mpn' );
					break;
				case 'no_brand':
					$ok = '' === $get( 'brand' );
					break;
				case 'no_identifiers':
					$ok = '' === $get( 'gtin' ) && ( '' === $get( 'brand' ) || '' === $get( 'mpn' ) );
					break;
				case 'on_sale':
					$ok = '' !== $get( 'sale_price' );
					break;
				case 'variant':
					$ok = '' !== $get( 'item_group_id' );
					break;
				case 'apparel':
					$cat = $get( 'google_product_category' );
					$ok  = in_array( $cat, array( '166', '1604', '187', '178' ), true ) || (bool) preg_match( '/^(?:apparel|clothing|shoes)/i', $cat ) || (bool) preg_match( '/\b(?:apparel|clothing|clothes|shoes|fashion)\b/i', $get( 'product_type' ) );
					break;
				default:
					/**
					 * Whether a channel's custom condition holds for an item.
					 *
					 * @param bool   $holds Default false.
					 * @param string $cond  Condition.
					 * @param array  $item  Item.
					 * @param array  $model Model.
					 */
					$ok = (bool) apply_filters( 'alg_wc_pxf_validator_condition', false, $cond, $item, $model );
			}
			if ( ! $ok ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A field's value in the item; the title and description also come from their structured forms.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $item  Item.
	 * @param   string $field Field.
	 * @return  mixed
	 */
	protected static function item_value( array $item, $field ) {
		if ( isset( $item[ $field ] ) && ! self::is_empty( $item[ $field ] ) ) {
			return $item[ $field ];
		}
		if ( in_array( $field, array( 'title', 'description' ), true ) && isset( $item[ 'structured_' . $field ] ) ) {
			$s = $item[ 'structured_' . $field ];
			return is_array( $s ) ? ( isset( $s['content'] ) ? $s['content'] : '' ) : $s;
		}
		return isset( $item[ $field ] ) ? $item[ $field ] : '';
	}

	/**
	 * Adds one issue (once per code).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $issues   Issues.
	 * @param   string $code     Code.
	 * @param   string $field    Field.
	 * @param   string $severity error|warning.
	 * @param   string $message  Plain message.
	 */
	protected static function add( array &$issues, $code, $field, $severity, $message ) {
		if ( isset( self::$seen[ $code ] ) ) {
			return;
		}
		self::$seen[ $code ] = true;
		$issues[]            = array(
			'code'     => $code,
			'field'    => $field,
			'severity' => $severity,
			'message'  => $message,
			'fix'      => self::fix_for( $field, $code ),
		);
	}

	/**
	 * Whether a code was already added in this check.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $code Code.
	 * @return  bool
	 */
	protected static function has( $code ) {
		return isset( self::$seen[ $code ] );
	}

	/**
	 * First of some field ids the channel declares.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $fields Channel fields.
	 * @param   array $ids    Candidates.
	 * @return  string '' when none.
	 */
	protected static function first_field( array $fields, array $ids ) {
		foreach ( $ids as $id ) {
			if ( isset( $fields[ $id ] ) ) {
				return $id;
			}
		}
		return '';
	}

	/**
	 * Whether a value is empty (sub-attributes: every part empty).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  bool
	 */
	protected static function is_empty( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( ! self::is_empty( $v ) ) {
					return false;
				}
			}
			return true;
		}
		return '' === trim( (string) $value );
	}

	/**
	 * A value as a plain string ('' for lists).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  string
	 */
	protected static function scalar( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Number from a price ("12.00 EUR", "12,00").
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  float|null
	 */
	protected static function number( $value ) {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return null;
		}
		if ( ! preg_match( '/-?\d+(?:[.,]\d+)?/', (string) $value, $m ) ) {
			return null;
		}
		return (float) str_replace( ',', '.', $m[0] );
	}

	/**
	 * Characters in a string.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  int
	 */
	protected static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Whether a value is one of the allowed ones (case, spaces and underscores ignored).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $value   Value.
	 * @param   array  $allowed Allowed values (list, or value => label).
	 * @return  bool
	 */
	protected static function is_allowed( $value, array $allowed ) {
		$norm = function ( $v ) {
			return preg_replace( '/[\s_\-]+/', '_', strtolower( trim( (string) $v ) ) );
		};
		$list = array_map( $norm, array_merge( array_values( $allowed ), array_filter( array_keys( $allowed ), 'is_string' ) ) );
		return in_array( $norm( $value ), $list, true );
	}

}

endif;
