<?php
/**
 * Product XML Feeds for WooCommerce - Shortcodes
 *
 * @version 2.9.4
 * @since   1.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_Shortcodes' ) ) :

class Alg_Shortcodes {

	/**
	 * the_shortcodes.
	 *
	 * @version 2.9.2
	 * @since   2.9.2
	 */
	public $the_shortcodes;

	/**
	 * the_atts.
	 *
	 * @version 2.9.2
	 * @since   2.9.2
	 */
	public $the_atts;

	/**
	 * currency_exchange_rates.
	 *
	 * @version 2.9.2
	 * @since   2.9.2
	 */
	public $currency_exchange_rates;

	/**
	 * Constructor.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public function __construct() {
		foreach ( $this->the_shortcodes as $the_shortcode ) {
			add_shortcode( $the_shortcode, array( $this, 'alg_shortcode' ) );
		}
		add_filter( 'alg_shortcodes_list', array( $this, 'add_shortcodes_to_the_list' ) );
	}

	/**
	 * add_extra_atts.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_extra_atts( $atts ) {
		if ( ! isset( $this->the_atts ) ) {
			$this->the_atts = array();
		}

		return array_merge( $this->the_atts, $atts );
	}

	/**
	 * init_atts.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function init_atts( $atts ) {
		return $atts;
	}

	/**
	 * add_shortcodes_to_the_list.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_shortcodes_to_the_list( $shortcodes_list ) {
		foreach ( $this->the_shortcodes as $the_shortcode ) {
			$shortcodes_list[] = $the_shortcode;
		}

		return $shortcodes_list;
	}

	/**
	 * alg_shortcode.
	 *
	 * @version 2.9.4
	 * @since   1.0.0
	 * @todo    [dev] maybe add `esc_html` attribute? (or alternatively add example with `custom_function="esc_html"` to the site)
	 * @todo    [dev] recheck global atts (before, after etc.)
	 * @todo    [dev] maybe add `multiply` global attribute
	 * @todo    [dev] Currency conversion: maybe add `exchange_rate` global attribute (i.e. instead of getting rates from ECB)
	 * @todo    [dev] Currency conversion: handle price ranges (i.e. variable products)
	 * @todo    [dev] (maybe) `strip_shortcodes`: `yes-force`: another optional regular expression
	 */
	function alg_shortcode( $atts, $content, $shortcode ) {

		// Init
		if ( empty( $atts ) ) {
			$atts = array();
		}

		// Add child class specific atts
		$atts = $this->add_extra_atts( $atts );

		// Merge atts with global defaults
		$global_defaults = array(
			'before'                     => '',
			'after'                      => '',
			'find'                       => '',
			'replace'                    => '',
			'find_replace_sep'           => '',
			'on_empty'                   => '',
			'on_empty_apply_shortcodes'  => 'no',
			'convert_currency_from'      => '',
			'convert_currency_to'        => '',
			'convert_currency_precision' => 2,
			'exchange_rate'              => 0,
			'custom_function'            => '',
			'strip_tags'                 => 'yes',
			'strip_shortcodes'           => 'no',
			'cdata'                      => 'no',
			'append_currency'            => 'no',
		);

		$atts = array_merge( $global_defaults, $atts );

		// Check for required atts
		if ( false === ( $atts = $this->init_atts( $atts ) ) ) {
			return '';
		}

		// Run the shortcode function
		$shortcode_function = $shortcode;
		if ( '' !== ( $result = $this->$shortcode_function( $atts, $content ) ) ) {
			// Strip tags
			if ( 'yes' === $atts['strip_tags'] ) {
				$result = strip_tags( $result );
			}

			// Strip shortcodes
			if ( 'no' != $atts['strip_shortcodes'] ) {
				$result = ( 'yes' === $atts['strip_shortcodes'] ?
					strip_shortcodes( $result ) :
					preg_replace( "(\[(?:\[??[^\[]*?\]))", '', $result ) // `yes-force`
				);
			}

			// Currency conversion
			if (
				! empty( $atts['convert_currency_from'] ) &&
				! empty( $atts['convert_currency_to'] ) &&
				is_numeric( $result ) &&
				! empty( $atts['exchange_rate'] )
			) {
				$result = round(
					$result * $atts['exchange_rate'],
					$atts['convert_currency_precision']
				);
			} else if (
				! empty( $atts['convert_currency_from'] ) &&
				! empty( $atts['convert_currency_to'] ) &&
				is_numeric( $result ) &&
				false != ( $exchange_rate = $this->get_currency_exchange_rate_ecb(
					strtoupper( $atts['convert_currency_from'] ),
					strtoupper( $atts['convert_currency_to'] )
				) )
			) {
				$result = round(
					$result * $exchange_rate,
					$atts['convert_currency_precision']
				);
			}

			// Find/Replace
			if ( '' !== $atts['find'] ) {
				$atts['find']    = html_entity_decode( $atts['find'] );
				$atts['replace'] = html_entity_decode( $atts['replace'] );

				if ( '' !== $atts['find_replace_sep'] ) {
					$result = str_replace(
						explode( $atts['find_replace_sep'], $atts['find'] ),
						explode( $atts['find_replace_sep'], $atts['replace'] ),
						$result
					);
				} else {
					$result = str_replace( $atts['find'], $atts['replace'], $result );
				}
			}

			// Custom function
			if ( '' !== ( $custom_function = sanitize_text_field( $atts['custom_function'] ) ) ) {

				// Only functions listed here will be allowed for executions
				$allowed = apply_filters( 'alg_allowed_custom_functions', array() );
				if (
					in_array( $custom_function, $allowed ) &&
					function_exists( $custom_function )
				) {
					$result = call_user_func( $custom_function, $result );
				}
			}

			// on_zero_apply_shortcodes
			if (
				isset( $atts['on_zero_apply_shortcodes'] ) &&
				'yes' === strtolower( $atts['on_zero_apply_shortcodes'] )
			) {
				if ( isset( $atts['on_zero'] ) && '' !== $atts['on_zero'] ) {
					if ( 0 == $result ) {
						$result = do_shortcode(
							str_replace( array( '{', '}' ), array( '[', ']' ), $atts['on_zero'] )
						);
					}
				}
			}

			if ( ! empty( $atts['before'] ) ) {
				$atts['before'] = str_replace(
					array( '#algequal;', '#algquotstart;', '#algquotend;' ),
					array( '=', '"', '"' ),
					$atts['before']
				);
			}

			if ( ! empty( $atts['after'] ) ) {
				$atts['after'] = str_replace(
					array( '#algequal;', '#algquotstart;', '#algquotend;' ),
					array( '=', '"', '"' ),
					$atts['after']
				);
			}

			// append currency
			if (
				isset( $atts['append_currency'] ) &&
				'yes' == strtolower( $atts['append_currency'] )
			) {
				$result = $result . ' ' .  get_woocommerce_currency();
			}

			$result = wp_kses_post( $result );
			// CDATA
			if ( 'yes' === $atts['cdata'] ) {
				$result = '<![CDATA[' .  wp_kses_post( $result ) . ']]>';
			}

			// Before/After
			return wp_kses_post( $atts['before'] ) . $result . wp_kses_post( $atts['after'] );
		} else {
			$on_empty = isset( $atts['on_empty'] ) ? wp_kses_post( $atts['on_empty'] ) : '';

			if ( 'yes' === strtolower( $atts['on_empty_apply_shortcodes'] ) ) {
				return do_shortcode(
					str_replace( array( '{', '}' ), array( '[', ']' ), $on_empty )
				);
			}

			return $on_empty;
		}
	}

	/**
	 * get_currency_exchange_rate_ecb.
	 *
	 * @version 1.4.3
	 * @since   1.4.3
	 *
	 * @todo    (dev) maybe add more exchange rate servers
	 */
	function get_currency_exchange_rate_ecb( $currency_from, $currency_to ) {
		if ( ! empty( $this->currency_exchange_rates[ $currency_from ][ $currency_to ] ) ) {
			return $this->currency_exchange_rates[ $currency_from ][ $currency_to ];
		}

		$final_rate = false;
		if ( function_exists( 'simplexml_load_file' ) ) {
			$xml = @simplexml_load_file( 'http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml' );
			if ( isset( $xml->Cube->Cube->Cube ) ) {
				if ( 'EUR' === $currency_from ) {
					$eur_currency_from_rate = 1;
				}
				if ( 'EUR' === $currency_to ) {
					$eur_currency_to_rate = 1;
				}

				foreach ( $xml->Cube->Cube->Cube as $currency_rate ) {
					$currency_rate = $currency_rate->attributes();
					if (
						! isset( $eur_currency_from_rate ) &&
						$currency_from == $currency_rate->currency
					) {
						$eur_currency_from_rate = ( float ) $currency_rate->rate;
					}
					if (
						! isset( $eur_currency_to_rate ) &&
						$currency_to == $currency_rate->currency
					) {
						$eur_currency_to_rate = ( float ) $currency_rate->rate;
					}
				}

				$final_rate = ( ! empty( $eur_currency_from_rate ) && isset( $eur_currency_to_rate )
					? ( $eur_currency_to_rate / $eur_currency_from_rate )
					: false
				);
			}
		}
		$this->currency_exchange_rates[ $currency_from ][ $currency_to ] = $final_rate;

		return $final_rate;
	}

}

endif;
