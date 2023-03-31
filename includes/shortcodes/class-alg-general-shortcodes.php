<?php
/**
 * Product XML Feeds for WooCommerce - General Shortcodes
 *
 * The Product XML Feeds for WooCommerce General Shortcodes class.
 *
 * @version 1.4.5
 * @since   1.0.0
 * @author  WPFactory
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Alg_Products_Shortcodes' ) ) :

class Alg_General_Shortcodes extends Alg_Shortcodes {

	/**
	 * Constructor.
	 *
	 * @version 1.4.5
	 * @since   1.0.0
	 */
	function __construct() {

		$this->the_shortcodes = array(
			'alg_shop_currency',
			'alg_current_datetime',
			'alg_format_date',
			'alg_format_number',
			'alg_to_timestamp',
		);

		$this->the_atts = array(
			'datetime_format' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
		);

		parent::__construct();
	}

	/**
	 * Inits shortcode atts and properties.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function init_atts( $atts ) {
		return $atts;
	}

	/**
	 * alg_shop_currency.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function alg_shop_currency( $atts ) {
		return get_woocommerce_currency();
	}

	/**
	 * alg_current_datetime.
	 *
	 * @version 1.1.0
	 * @since   1.1.0
	 */
	function alg_current_datetime( $atts ) {
		return date_i18n( $atts['datetime_format'], current_time( 'timestamp' ) );
	}

	/**
	 * alg_format_date.
	 *
	 * @version 1.4.5
	 * @since   1.4.5
	 */
	function alg_format_date( $atts, $content ) {
		$atts = shortcode_atts( array(
			'format'       => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			'is_timestamp' => 'no',
			'do_localize'  => 'no',
		), $atts, 'alg_format_date' );
		$content   = do_shortcode( $content );
		$timestamp = ( 'no' === $atts['is_timestamp'] ? strtotime( $content ) : $content );
		$function  = ( 'no' === $atts['do_localize']  ? 'date' : 'date_i18n' );
		return $function( $atts['format'], $timestamp );
	}

	/**
	 * alg_format_number.
	 *
	 * @version 1.4.5
	 * @since   1.4.5
	 */
	function alg_format_number( $atts, $content ) {
		$atts = shortcode_atts( array(
			'decimals'      => 0,
			'dec_point'     => '.',
			'thousands_sep' => ',',
		), $atts, 'alg_format_number' );
		return number_format( do_shortcode( $content ), $atts['decimals'], $atts['dec_point'], $atts['thousands_sep'] );
	}

	/**
	 * alg_to_timestamp.
	 *
	 * @version 1.4.5
	 * @since   1.4.5
	 */
	function alg_to_timestamp( $atts, $content ) {
		return strtotime( do_shortcode( $content ) );
	}

}

endif;

return new Alg_General_Shortcodes();
