<?php
/**
 * Product XML Feeds for WooCommerce - v2 feed writer (base): appends to "<file>.tmp" per batch, publishes with rename().
 *
 * Life of a file: begin() truncates the .tmp file and writes the header; each later batch calls resume( $bytes ) to cut off
 * anything a crashed batch left behind, then item() per product; end() writes the footer, gzips when the live path ends
 * in ".gz", and renames over the live file. Until end() succeeds the live file stays as it was (last good file).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Writer' ) ) :

abstract class Alg_WC_PXF_Writer {

	/**
	 * Format (xml, csv, tsv, txt, jsonl).
	 *
	 * @var string
	 */
	protected $format;

	/**
	 * Channel spec.
	 *
	 * @var array
	 */
	protected $channel;

	/**
	 * Live path (what feed readers fetch).
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Whether the live file is gzipped.
	 *
	 * @var bool
	 */
	protected $gzip;

	/**
	 * Path of the uncompressed temporary file.
	 *
	 * @var string
	 */
	protected $tmp;

	/**
	 * Output encoding.
	 *
	 * @var string
	 */
	protected $encoding = 'UTF-8';

	/**
	 * Open handle.
	 *
	 * @var resource|null
	 */
	protected $handle = null;

	/**
	 * Header and footer text set by the engine (channels with 'raw' => true, such as custom).
	 *
	 * @var array
	 */
	protected $parts = array();

	/**
	 * open.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $format  xml, csv, tsv, txt, jsonl.
	 * @param   array  $channel Channel spec.
	 * @param   string $path    Live file path (ending in .gz for gzip).
	 * @return  Alg_WC_PXF_Writer|WP_Error
	 */
	static function open( $format, $channel, $path ) {
		$format  = strtolower( (string) $format );
		$classes = array(
			'xml'   => 'Alg_WC_PXF_Writer_XML',
			'csv'   => 'Alg_WC_PXF_Writer_CSV',
			'tsv'   => 'Alg_WC_PXF_Writer_CSV',
			'txt'   => 'Alg_WC_PXF_Writer_CSV',
			'jsonl' => 'Alg_WC_PXF_Writer_JSONL',
		);
		$class   = apply_filters( 'alg_wc_pxf_writer_class', isset( $classes[ $format ] ) ? $classes[ $format ] : '', $format, $channel );
		if ( '' === $class || ! class_exists( $class ) || ! is_subclass_of( $class, __CLASS__ ) ) {
			/* translators: %s: File format. */
			return new WP_Error( 'alg_pxf_bad_format', sprintf( __( 'The file format "%s" is not supported.', 'product-xml-feeds-for-woocommerce' ), $format ) );
		}
		return new $class( $format, is_array( $channel ) ? $channel : array(), $path );
	}

	/**
	 * Constructor.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $format  Format.
	 * @param   array  $channel Channel spec.
	 * @param   string $path    Live path.
	 */
	function __construct( $format, array $channel, $path ) {
		$this->format  = $format;
		$this->channel = $channel;
		$this->path    = $path;
		$this->gzip    = '.gz' === substr( $path, -3 );
		$this->tmp     = ( $this->gzip ? substr( $path, 0, -3 ) : $path ) . '.tmp';
		if ( ! empty( $channel['encoding'] ) && is_string( $channel['encoding'] ) ) {
			$this->encoding = strtoupper( $channel['encoding'] );
		}
		if ( 'UTF-8' !== $this->encoding && ! function_exists( 'mb_convert_encoding' ) ) {
			$this->encoding = 'UTF-8';
		}
	}

	/**
	 * Destructor.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	function __destruct() {
		if ( $this->handle ) {
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->handle = null;
		}
	}

	/**
	 * Header text (UTF-8).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	abstract protected function header();

	/**
	 * One item as text (UTF-8).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $item Field => value.
	 * @return  string
	 */
	abstract protected function render_item( array $item );

	/**
	 * Footer text (UTF-8).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	abstract protected function footer();

	/**
	 * tmp_path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	function tmp_path() {
		return $this->tmp;
	}

	/**
	 * set_parts: header and/or footer written as is instead of the format's own (custom templates).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $parts 'header' and/or 'footer' => string.
	 */
	function set_parts( array $parts ) {
		foreach ( array( 'header', 'footer' ) as $key ) {
			if ( isset( $parts[ $key ] ) && is_scalar( $parts[ $key ] ) ) {
				$this->parts[ $key ] = (string) $parts[ $key ];
			}
		}
	}

	/**
	 * field_def: the channel definition of one field.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $field Field.
	 * @return  array
	 */
	protected function field_def( $field ) {
		return ( isset( $this->channel['fields'][ $field ] ) && is_array( $this->channel['fields'][ $field ] ) ) ? $this->channel['fields'][ $field ] : array();
	}

	/**
	 * begin: starts a new temporary file with the header.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @throws  RuntimeException When the file cannot be written.
	 */
	function begin() {
		$this->close();
		$this->handle = @fopen( $this->tmp, 'wb' ); // phpcs:ignore
		if ( ! $this->handle ) {
			throw new RuntimeException( __( 'The feed file could not be created. Check that the uploads folder is writable.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$this->write( isset( $this->parts['header'] ) ? $this->parts['header'] : $this->header() );
	}

	/**
	 * resume: reopens the temporary file and cuts it back to the bytes the last finished batch wrote.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $bytes Size after the last finished batch.
	 * @return  bool False when the file is missing or shorter than expected.
	 */
	function resume( $bytes ) {
		$this->close();
		clearstatcache( true, $this->tmp );
		if ( ! file_exists( $this->tmp ) ) {
			return false;
		}
		$size = (int) filesize( $this->tmp );
		if ( $size < (int) $bytes ) {
			return false;
		}
		$this->handle = @fopen( $this->tmp, 'r+b' ); // phpcs:ignore
		if ( ! $this->handle ) {
			return false;
		}
		if ( $size > (int) $bytes ) {
			ftruncate( $this->handle, (int) $bytes );
		}
		fseek( $this->handle, 0, SEEK_END );
		return true;
	}

	/**
	 * item.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $item Field => value (string) or field => array (sub-attributes, lists).
	 */
	function item( array $item ) {
		if ( isset( $item['_raw'] ) ) {
			// Custom template output: not escaped, only invalid UTF-8 and XML-invalid control characters removed.
			$this->write( self::clean( $item['_raw'] ) );
			return;
		}
		foreach ( array_keys( $item ) as $key ) {
			if ( '_' === substr( (string) $key, 0, 1 ) ) {
				unset( $item[ $key ] ); // Internal keys such as _exclude.
			}
		}
		$this->write( $this->render_item( $item ) );
	}

	/**
	 * close: closes the handle, returns the temporary file size.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  int
	 */
	function close() {
		if ( $this->handle ) {
			fflush( $this->handle );
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->handle = null;
		}
		clearstatcache( true, $this->tmp );
		return file_exists( $this->tmp ) ? (int) filesize( $this->tmp ) : 0;
	}

	/**
	 * end: footer, gzip when asked, then rename() over the live file.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool False when the live file could not be replaced (it is then left as it was).
	 */
	function end() {
		if ( ! $this->handle && ! $this->resume( file_exists( $this->tmp ) ? filesize( $this->tmp ) : 0 ) ) {
			return false;
		}
		$this->write( isset( $this->parts['footer'] ) ? $this->parts['footer'] : $this->footer() );
		$this->close();
		$source = $this->tmp;
		if ( $this->gzip ) {
			$source = $this->path . '.tmp';
			if ( ! self::gzip_file( $this->tmp, $source ) ) {
				@unlink( $source ); // phpcs:ignore
				return false;
			}
			@unlink( $this->tmp ); // phpcs:ignore
		}
		if ( ! @rename( $source, $this->path ) ) { // phpcs:ignore
			return false;
		}
		return true;
	}

	/**
	 * abort: removes the temporary files, the live file stays.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	function abort() {
		$this->close();
		self::discard( $this->path );
	}

	/**
	 * discard: removes the temporary files that belong to a live path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $path Live path.
	 */
	static function discard( $path ) {
		$raw = '.gz' === substr( $path, -3 ) ? substr( $path, 0, -3 ) : $path;
		foreach ( array( $raw . '.tmp', $path . '.tmp' ) as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file ); // phpcs:ignore
			}
		}
	}

	/**
	 * gzip_file: streams $from into a gzip file $to in 1 MB chunks.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $from Source.
	 * @param   string $to   Target.
	 * @return  bool
	 */
	static function gzip_file( $from, $to ) {
		if ( ! function_exists( 'gzopen' ) ) {
			return false;
		}
		$in = @fopen( $from, 'rb' ); // phpcs:ignore
		if ( ! $in ) {
			return false;
		}
		$out = @gzopen( $to, 'wb6' ); // phpcs:ignore
		if ( ! $out ) {
			fclose( $in ); // phpcs:ignore
			return false;
		}
		$ok = true;
		while ( ! feof( $in ) ) {
			$chunk = fread( $in, 1048576 ); // phpcs:ignore
			if ( false === $chunk ) {
				$ok = false;
				break;
			}
			if ( '' !== $chunk && false === gzwrite( $out, $chunk ) ) {
				$ok = false;
				break;
			}
		}
		fclose( $in ); // phpcs:ignore
		return gzclose( $out ) && $ok;
	}

	/**
	 * write: converts the encoding when the channel asks for it, then appends.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text UTF-8 text.
	 * @throws  RuntimeException When the disk refuses the write.
	 */
	protected function write( $text ) {
		if ( '' === $text || null === $text ) {
			return;
		}
		if ( ! $this->handle ) {
			throw new RuntimeException( __( 'The feed file is not open.', 'product-xml-feeds-for-woocommerce' ) );
		}
		$text   = $this->encode( $text );
		$length = strlen( $text );
		$done   = fwrite( $this->handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( false === $done || $done < $length ) {
			throw new RuntimeException( __( 'The feed file could not be written. Check the free disk space.', 'product-xml-feeds-for-woocommerce' ) );
		}
	}

	/**
	 * encode: UTF-8 to the channel encoding.
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
		$converted = @mb_convert_encoding( $text, $this->encoding, 'UTF-8' ); // phpcs:ignore
		return false === $converted ? $text : $converted;
	}

	/**
	 * clean: one cleaner for every value: invalid UTF-8 scrubbed, XML-invalid control characters removed, emoji kept.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  string
	 */
	static function clean( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '//u', $value ) ) {
			if ( function_exists( 'mb_scrub' ) ) {
				$value = mb_scrub( $value, 'UTF-8' );
			} elseif ( function_exists( 'iconv' ) ) {
				$value = (string) @iconv( 'UTF-8', 'UTF-8//IGNORE', $value ); // phpcs:ignore
			} else {
				$value = wp_check_invalid_utf8( $value, true );
			}
		}
		$value = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{7E}\x{85}\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );
		return null === $value ? '' : $value;
	}

	/**
	 * xml_text: a clean value escaped for XML text and attributes (& < > " escaped, no CDATA needed).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  string
	 */
	static function xml_text( $value ) {
		return htmlspecialchars( self::clean( $value ), ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8', true );
	}

	/**
	 * is_list: numeric keys 0..n-1.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $value Value.
	 * @return  bool
	 */
	static function is_list( array $value ) {
		return array_values( $value ) === $value;
	}

	/**
	 * placeholders: {site_name}, {home_url}, {site_description}, {date} (ISO 8601 UTC), {datetime} (site time, Y-m-d H:i).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  string
	 */
	static function placeholders( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, '{' ) ) {
			return $text;
		}
		return strtr( $text, array(
			'{site_name}'        => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{home_url}'         => home_url( '/' ),
			'{site_description}' => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
			'{date}'             => gmdate( 'c' ),
			'{datetime}'         => current_time( 'Y-m-d H:i' ),
		) );
	}

}

endif;
