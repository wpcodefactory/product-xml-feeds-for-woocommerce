<?php
/**
 * Product XML Feeds for WooCommerce - v2 JSON Lines writer (one JSON object per line, always UTF-8).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Writer_JSONL' ) ) :

class Alg_WC_PXF_Writer_JSONL extends Alg_WC_PXF_Writer {

	/**
	 * Constructor: JSON is always UTF-8.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $format  Format.
	 * @param   array  $channel Channel spec.
	 * @param   string $path    Live path.
	 */
	function __construct( $format, array $channel, $path ) {
		parent::__construct( $format, $channel, $path );
		$this->encoding = 'UTF-8';
	}

	/**
	 * header.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	protected function header() {
		return '';
	}

	/**
	 * render_item.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $item Item.
	 * @return  string
	 */
	protected function render_item( array $item ) {
		$item = self::clean_deep( $item );
		foreach ( $item as $field => $value ) {
			$def = $this->field_def( $field );
			if ( isset( $def['json'] ) && 'bool' === $def['json'] && is_string( $value ) ) {
				$item[ $field ] = in_array( strtolower( $value ), array( 'true', 'yes', '1' ), true );
			}
		}
		$json = wp_json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $json ? '' : $json . "\n";
	}

	/**
	 * footer.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	protected function footer() {
		return '';
	}

	/**
	 * clean_deep.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  mixed
	 */
	static function clean_deep( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $one ) {
				$out[ is_int( $key ) ? $key : self::clean( $key ) ] = self::clean_deep( $one );
			}
			return $out;
		}
		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || null === $value ) {
			return $value;
		}
		return self::clean( $value );
	}

}

endif;
