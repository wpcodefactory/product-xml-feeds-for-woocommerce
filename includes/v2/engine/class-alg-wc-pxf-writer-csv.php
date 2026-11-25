<?php
/**
 * Product XML Feeds for WooCommerce - v2 CSV, TSV and TXT writer.
 *
 * Columns: channel 'columns' when set, else the channel field keys. Separator: tab for tsv; for csv and txt the channel's
 * csv 'separator' (else comma for csv, tab for txt). Lists are joined with the field's 'list_sep' (else a comma),
 * sub-attributes with colons (Google style "DE::Standard:4.95 EUR").
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Writer_CSV' ) ) :

class Alg_WC_PXF_Writer_CSV extends Alg_WC_PXF_Writer {

	/**
	 * Columns cache.
	 *
	 * @var array|null
	 */
	protected $columns = null;

	/**
	 * columns.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	function columns() {
		if ( null === $this->columns ) {
			if ( ! empty( $this->channel['columns'] ) && is_array( $this->channel['columns'] ) ) {
				$columns = array_values( $this->channel['columns'] );
			} elseif ( ! empty( $this->channel['fields'] ) && is_array( $this->channel['fields'] ) ) {
				$columns = array_keys( $this->channel['fields'] );
			} else {
				$columns = array( 'id', 'title', 'description', 'link', 'image_link', 'price', 'availability' );
			}
			$this->columns = array_values( array_map( 'strval', (array) apply_filters( 'alg_wc_pxf_writer_columns', $columns, $this->channel, $this->format ) ) );
		}
		return $this->columns;
	}

	/**
	 * separator.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	function separator() {
		if ( 'tsv' === $this->format ) {
			return "\t";
		}
		$candidates = array(
			isset( $this->channel['csv']['separator'] ) ? $this->channel['csv']['separator'] : null,
			isset( $this->channel['separator'] ) ? $this->channel['separator'] : null,
		);
		foreach ( $candidates as $separator ) {
			if ( is_string( $separator ) && 1 === strlen( $separator ) ) {
				return $separator;
			}
		}
		return 'csv' === $this->format ? ',' : "\t";
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
		return $this->line( $this->columns() );
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
		$row = array();
		foreach ( $this->columns() as $column ) {
			$def   = $this->field_def( $column );
			$sep   = ( isset( $def['list_sep'] ) && is_string( $def['list_sep'] ) && '' !== $def['list_sep'] ) ? $def['list_sep'] : ',';
			$row[] = isset( $item[ $column ] ) ? self::flatten( $item[ $column ], $sep ) : '';
		}
		return $this->line( $row );
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
	 * flatten: list => comma joined, sub-attributes => colon joined.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed  $value    Value.
	 * @param   string $list_sep Separator for list entries.
	 * @return  string
	 */
	static function flatten( $value, $list_sep = ',' ) {
		if ( ! is_array( $value ) ) {
			return self::clean( $value );
		}
		$parts = array();
		if ( self::is_list( $value ) ) {
			foreach ( $value as $one ) {
				$parts[] = self::flatten( $one, $list_sep );
			}
			return implode( $list_sep, array_filter( $parts, 'strlen' ) );
		}
		foreach ( $value as $key => $one ) {
			if ( '@' === substr( (string) $key, 0, 1 ) ) {
				continue;
			}
			$parts[] = is_array( $one ) ? self::flatten( $one ) : self::clean( $one );
		}
		return implode( ':', $parts );
	}

	/**
	 * line: one row, quoted where needed (fputcsv style, same on every PHP version).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $fields Values.
	 * @return  string
	 */
	protected function line( array $fields ) {
		$separator = $this->separator();
		$tabbed    = "\t" === $separator;
		$out       = array();
		foreach ( $fields as $field ) {
			$field = self::clean( $field );
			if ( $tabbed ) {
				// Tab separated: no tabs or line breaks inside values.
				$field = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $field );
				$quote = false !== strpos( $field, '"' );
			} else {
				$quote = '' !== $field && ( strpbrk( $field, $separator . "\"\r\n" ) !== false || ' ' === $field[0] || ' ' === substr( $field, -1 ) );
			}
			$out[] = $quote ? '"' . str_replace( '"', '""', $field ) . '"' : $field;
		}
		return implode( $separator, $out ) . "\n";
	}

}

endif;
