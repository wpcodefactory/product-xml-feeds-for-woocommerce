<?php
/**
 * Product XML Feeds for WooCommerce - v2 XML writer.
 *
 * Channel spec 'xml' => array( 'root', 'root_attrs', 'wrap', 'item', 'prefix', 'head' ). Item values: string = one
 * element; list = the element repeated; associative array = element with children ('@name' keys become attributes,
 * '#text' the text). Empty values are left out.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Writer_XML' ) ) :

class Alg_WC_PXF_Writer_XML extends Alg_WC_PXF_Writer {

	/**
	 * spec.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	protected function spec() {
		$xml = ( isset( $this->channel['xml'] ) && is_array( $this->channel['xml'] ) ) ? $this->channel['xml'] : array();
		return array_merge( array(
			'root'       => 'products',
			'root_attrs' => array(),
			'wrap'       => '',
			'item'       => 'product',
			'prefix'     => '',
			'head'          => array(),
			'head_position' => 'wrap',
		), $xml );
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
		$spec  = $this->spec();
		$attrs = '';
		foreach ( (array) $spec['root_attrs'] as $name => $value ) {
			$attrs .= ' ' . self::name( $name ) . '="' . self::xml_text( $value ) . '"';
		}
		$out = '<?xml version="1.0" encoding="' . self::xml_text( $this->encoding ) . '"?>' . "\n";
		$out .= '<' . self::name( $spec['root'] ) . $attrs . '>' . "\n";
		$wrap  = '' !== (string) $spec['wrap'];
		$head  = '';
		$depth = ( $wrap && 'root' !== $spec['head_position'] ) ? 2 : 1;
		foreach ( (array) $spec['head'] as $name => $value ) {
			$value = is_string( $value ) ? self::placeholders( $value ) : $value;
			$head .= $this->node( $name, $value, $depth, '' );
		}
		if ( ! $wrap ) {
			return $out . $head;
		}
		// head_position 'root': head elements in the root before the wrap (Skroutz created_at); 'wrap': inside it (RSS channel).
		$open = "\t<" . self::name( $spec['wrap'] ) . ">\n";
		return $out . ( 'root' === $spec['head_position'] ? $head . $open : $open . $head );
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
		$spec   = $this->spec();
		$depth  = '' !== (string) $spec['wrap'] ? 2 : 1;
		$indent = str_repeat( "\t", $depth );
		$tag    = self::name( $spec['item'] );
		$body   = '';
		foreach ( $item as $name => $value ) {
			$def = $this->field_def( $name );
			if ( ! empty( $def['list_item'] ) && is_array( $value ) && ! empty( $value ) && self::is_list( $value ) ) {
				// A list of entries inside one element, each written as the 'list_item' element (variations > variation).
				$inner = '';
				$pad   = str_repeat( "\t", $depth + 1 );
				foreach ( $value as $entry ) {
					$inner .= $this->node( $def['list_item'], $entry, $depth + 2, (string) $spec['prefix'] );
				}
				if ( '' !== $inner ) {
					$name_tag = self::name( $name, (string) $spec['prefix'] );
					$body    .= $pad . '<' . $name_tag . ">\n" . $inner . $pad . '</' . $name_tag . ">\n";
				}
				continue;
			}
			$body .= $this->node( $name, $value, $depth + 1, (string) $spec['prefix'] );
		}
		return $indent . '<' . $tag . ">\n" . $body . $indent . '</' . $tag . ">\n";
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
		$spec = $this->spec();
		$out  = '';
		if ( '' !== (string) $spec['wrap'] ) {
			$out .= "\t</" . self::name( $spec['wrap'] ) . ">\n";
		}
		return $out . '</' . self::name( $spec['root'] ) . ">\n";
	}

	/**
	 * encode: characters the target charset lacks become numeric references (valid XML), not "?".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text UTF-8 text.
	 * @return  string
	 */
	protected function encode( $text ) {
		if ( 'UTF-8' === $this->encoding || 'UTF8' === $this->encoding ) {
			return $text;
		}
		$previous = mb_substitute_character();
		mb_substitute_character( 'entity' );
		$converted = @mb_convert_encoding( $text, $this->encoding, 'UTF-8' ); // phpcs:ignore
		mb_substitute_character( $previous );
		return false === $converted ? $text : $converted;
	}

	/**
	 * node.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string|int $name   Element name.
	 * @param   mixed      $value  Value.
	 * @param   int        $depth  Indent depth.
	 * @param   string     $prefix Namespace prefix such as "g:".
	 * @return  string
	 */
	protected function node( $name, $value, $depth, $prefix ) {
		$indent = str_repeat( "\t", $depth );
		$tag    = self::name( $name, $prefix );
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return '';
			}
			if ( self::is_list( $value ) ) {
				$out = '';
				foreach ( $value as $one ) {
					$out .= $this->node( $name, $one, $depth, $prefix );
				}
				return $out;
			}
			$attrs    = '';
			$text     = null;
			$children = '';
			foreach ( $value as $key => $child ) {
				$key = (string) $key;
				if ( '#text' === $key ) {
					$text = $child;
				} elseif ( '' !== $key && '@' === $key[0] ) {
					if ( is_scalar( $child ) && '' !== (string) $child ) {
						$attrs .= ' ' . self::name( substr( $key, 1 ) ) . '="' . self::xml_text( $child ) . '"';
					}
				} else {
					$children .= $this->node( $key, $child, $depth + 1, $prefix );
				}
			}
			if ( null !== $text ) {
				return $indent . '<' . $tag . $attrs . '>' . self::xml_text( $text ) . '</' . $tag . ">\n";
			}
			if ( '' === $children ) {
				return '' === $attrs ? '' : $indent . '<' . $tag . $attrs . "/>\n";
			}
			return $indent . '<' . $tag . $attrs . ">\n" . $children . $indent . '</' . $tag . ">\n";
		}
		if ( null === $value || ! is_scalar( $value ) ) {
			return '';
		}
		$text = self::xml_text( $value );
		if ( '' === $text ) {
			return '';
		}
		return $indent . '<' . $tag . '>' . $text . '</' . $tag . ">\n";
	}

	/**
	 * name: a valid XML element or attribute name (the prefix is added only to names without one).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string|int $name   Name.
	 * @param   string     $prefix Prefix such as "g:".
	 * @return  string
	 */
	static function name( $name, $prefix = '' ) {
		$name  = (string) $name;
		$parts = explode( ':', $name, 2 );
		if ( 2 === count( $parts ) ) {
			return self::local_name( $parts[0] ) . ':' . self::local_name( $parts[1] );
		}
		return ( '' !== $prefix ? $prefix : '' ) . self::local_name( $name );
	}

	/**
	 * local_name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name Name without a prefix.
	 * @return  string
	 */
	static function local_name( $name ) {
		$name = preg_replace( '/[^A-Za-z0-9_.-]/', '_', (string) $name );
		if ( '' === $name || ! preg_match( '/^[A-Za-z_]/', $name ) ) {
			$name = '_' . $name;
		}
		return $name;
	}

}

endif;
