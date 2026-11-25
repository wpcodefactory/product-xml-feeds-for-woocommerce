<?php
/**
 * Product XML Feeds for WooCommerce - v2 feed records (custom post type, config, run state, file paths).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Feeds' ) ) :

class Alg_WC_PXF_Feeds {

	/**
	 * Post type, meta keys, folder.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const POST_TYPE   = 'alg_pxf_feed';
	const META_CONFIG = '_alg_pxf_config';
	const META_STATE  = '_alg_pxf_state';
	const FOLDER      = 'product-feeds';

	/**
	 * Supported file formats.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const FORMATS = array( 'xml', 'csv', 'tsv', 'txt', 'jsonl' );

	/**
	 * Variation modes ('' = channel default).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const VARIATION_MODES = array( '', 'all', 'parent', 'default', 'cheapest' );

	/**
	 * Every schedule value a feed may store (the scheduler decides what runs in free).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const SCHEDULES = array( 'daily', 'manual', 'hourly', 'twicedaily', '15min' );

	/**
	 * Mapping source types.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const MAPPING_TYPES = array( 'field', 'attribute', 'meta', 'taxonomy', 'static', 'placeholder' );

	/**
	 * init.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	/**
	 * register_post_type.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function register_post_type() {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}
		$cap  = 'manage_woocommerce';
		$caps = array();
		foreach ( array(
			'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts',
			'read_private_posts', 'read', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts',
			'edit_private_posts', 'edit_published_posts', 'create_posts',
		) as $key ) {
			$caps[ $key ] = $cap;
		}
		register_post_type( self::POST_TYPE, array(
			'labels'              => array(
				'name'          => __( 'Product feeds', 'product-xml-feeds-for-woocommerce' ),
				'singular_name' => __( 'Product feed', 'product-xml-feeds-for-woocommerce' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => true,
			'supports'            => array( 'title' ),
			'map_meta_cap'        => false,
			'capabilities'        => $caps,
		) );
	}

	/**
	 * defaults.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function defaults() {
		$country = 'US';
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$base = WC()->countries->get_base_country();
			if ( $base ) {
				$country = $base;
			}
		}
		return array(
			'channel'             => 'google',
			'format'              => '',
			'country'             => $country,
			'language'            => '',
			'currency'            => '',
			'file_slug'           => 'google-shopping',
			'gzip'                => false,
			'variation_mode'      => '',
			'filters'             => self::default_filters(),
			'mapping'             => array(),
			'schedule'            => 'daily',
			'rebuild_on_change'   => false,
			'leave_out_errors'    => false,
			'use_ai_titles'       => false,
			'use_ai_descriptions' => false,
			'custom_template'     => array(),
			'rules'               => array(),
			'eu'                  => array(),
		);
	}

	/**
	 * default_filters.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function default_filters() {
		return array(
			'stock'        => array(),
			'include_cats' => array(),
			'exclude_cats' => array(),
			'include_tags' => array(),
			'exclude_tags' => array(),
			'exclude_ids'  => array(),
			'min_price'    => '',
			'max_price'    => '',
			'on_sale'      => '',
		);
	}

	/**
	 * default_state.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function default_state() {
		return array(
			'status'     => 'idle',
			'run_id'     => '',
			'cursor'     => array( 'last_date' => '', 'last_id' => 0 ),
			'done'       => 0,
			'total'      => 0,
			'written'    => 0,
			'left_out'   => 0,
			'excluded'   => 0,
			'issues'     => array(),
			'started'    => 0,
			'finished'   => 0,
			'last_good'  => 0,
			'error'      => '',
			// Engine bookkeeping (additive keys).
			'source'     => '',
			'batches'    => 0,
			'batch_size' => 0,
			'bytes'      => 0,
			'last_batch' => 0,
			'attempts'   => 0,
		);
	}

	/**
	 * exists.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  bool
	 */
	static function exists( $feed_id ) {
		$feed_id = absint( $feed_id );
		return $feed_id > 0 && self::POST_TYPE === get_post_type( $feed_id );
	}

	/**
	 * all.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  int[]
	 */
	static function all() {
		return array_map( 'intval', get_posts( array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		) ) );
	}

	/**
	 * get_config.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  array
	 */
	static function get_config( $feed_id ) {
		$stored = get_post_meta( absint( $feed_id ), self::META_CONFIG, true );
		return self::merge_defaults( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * save_config: merges onto the stored config and the defaults, so partial arrays are fine.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $config  Config (full or partial).
	 * @return  array The saved config.
	 */
	static function save_config( $feed_id, array $config ) {
		$feed_id = absint( $feed_id );
		$current = self::get_config( $feed_id );
		if ( isset( $config['filters'] ) && is_array( $config['filters'] ) ) {
			$config['filters'] = array_merge( $current['filters'], $config['filters'] );
		}
		$config = self::sanitize_config( array_merge( $current, $config ), $feed_id );
		update_post_meta( $feed_id, self::META_CONFIG, $config );
		do_action( 'alg_wc_pxf_feed_saved', $feed_id, $config );
		return $config;
	}

	/**
	 * merge_defaults.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $config Stored config.
	 * @return  array
	 */
	static function merge_defaults( array $config ) {
		$defaults = self::defaults();
		$merged   = array_merge( $defaults, $config );
		$merged['filters'] = array_merge( $defaults['filters'], ( isset( $config['filters'] ) && is_array( $config['filters'] ) ? $config['filters'] : array() ) );
		foreach ( array( 'mapping', 'custom_template', 'rules', 'eu' ) as $key ) {
			if ( ! is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = array();
			}
		}
		return $merged;
	}

	/**
	 * sanitize_config.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $c       Config.
	 * @param   int   $feed_id Feed id (for the unique file name).
	 * @return  array
	 */
	static function sanitize_config( array $c, $feed_id = 0 ) {
		$c = self::merge_defaults( $c );
		$d = self::defaults();

		$c['channel'] = sanitize_key( (string) $c['channel'] );
		if ( '' === $c['channel'] ) {
			$c['channel'] = $d['channel'];
		}
		$c['format'] = in_array( $c['format'], self::FORMATS, true ) ? $c['format'] : '';

		$country      = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $c['country'] ) );
		$c['country'] = 2 === strlen( $country ) ? $country : $d['country'];

		$c['language'] = substr( preg_replace( '/[^A-Za-z_-]/', '', (string) $c['language'] ), 0, 20 );
		$c['currency'] = substr( strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $c['currency'] ) ), 0, 3 );

		$slug = sanitize_title( (string) $c['file_slug'] );
		if ( '' === $slug ) {
			$slug = self::default_slug( $c['channel'] );
		}
		$c['file_slug'] = self::unique_slug( $slug, $feed_id );

		foreach ( array( 'gzip', 'rebuild_on_change', 'leave_out_errors', 'use_ai_titles', 'use_ai_descriptions' ) as $key ) {
			$c[ $key ] = self::to_bool( $c[ $key ] );
		}

		$c['variation_mode'] = in_array( $c['variation_mode'], self::VARIATION_MODES, true ) ? $c['variation_mode'] : '';
		$c['schedule']       = in_array( $c['schedule'], self::SCHEDULES, true ) ? $c['schedule'] : 'daily';

		// Filters.
		$f   = is_array( $c['filters'] ) ? $c['filters'] : array();
		$out = self::default_filters();
		$out['stock'] = isset( $f['stock'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) $f['stock'] ) ) ) : array();
		foreach ( array( 'include_cats', 'exclude_cats', 'include_tags', 'exclude_tags', 'exclude_ids' ) as $key ) {
			$out[ $key ] = isset( $f[ $key ] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) $f[ $key ] ) ) ) ) : array();
		}
		foreach ( array( 'min_price', 'max_price' ) as $key ) {
			$value       = isset( $f[ $key ] ) ? trim( (string) $f[ $key ] ) : '';
			$out[ $key ] = ( '' !== $value && is_numeric( str_replace( ',', '.', $value ) ) ) ? (string) (float) str_replace( ',', '.', $value ) : '';
		}
		$out['on_sale'] = ( isset( $f['on_sale'] ) && in_array( $f['on_sale'], array( 'yes', 'no' ), true ) ) ? $f['on_sale'] : '';
		$c['filters']   = $out;

		// Mapping.
		$mapping = array();
		foreach ( (array) $c['mapping'] as $field => $rule ) {
			$field = sanitize_key( $field );
			if ( '' === $field || ! is_array( $rule ) ) {
				continue;
			}
			$type = ( isset( $rule['type'] ) && in_array( $rule['type'], self::MAPPING_TYPES, true ) ) ? $rule['type'] : 'field';
			$mapping[ $field ] = array(
				'type'   => $type,
				'key'    => isset( $rule['key'] ) ? sanitize_text_field( (string) $rule['key'] ) : '',
				'value'  => isset( $rule['value'] ) ? self::clean_line( $rule['value'], 5000 ) : '',
				'prefix' => isset( $rule['prefix'] ) ? self::clean_line( $rule['prefix'], 500 ) : '',
				'suffix' => isset( $rule['suffix'] ) ? self::clean_line( $rule['suffix'], 500 ) : '',
			);
		}
		$c['mapping'] = $mapping;

		// Custom template (channel 'custom'): raw text with shortcodes, like the classic templates.
		$template = array();
		foreach ( array( 'header', 'item', 'footer' ) as $key ) {
			if ( isset( $c['custom_template'][ $key ] ) && is_scalar( $c['custom_template'][ $key ] ) ) {
				$template[ $key ] = wp_check_invalid_utf8( (string) $c['custom_template'][ $key ], true );
			}
		}
		$c['custom_template'] = $template;

		return apply_filters( 'alg_wc_pxf_sanitize_config', $c, $feed_id );
	}

	/**
	 * clean_line: plain text that keeps leading and trailing spaces (prefixes, suffixes).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @param   int   $max   Max length.
	 * @return  string
	 */
	static function clean_line( $value, $max = 500 ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = wp_check_invalid_utf8( (string) $value, true );
		$value = wp_strip_all_tags( $value, false );
		$value = str_replace( array( "\r", "\n", "\t" ), ' ', $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * to_bool.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Value.
	 * @return  bool
	 */
	static function to_bool( $value ) {
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'yes', 'true', 'on' ), true );
		}
		return (bool) $value;
	}

	/**
	 * default_slug.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $channel_id Channel id.
	 * @return  string
	 */
	static function default_slug( $channel_id ) {
		$channel = self::channel( $channel_id );
		$slug    = sanitize_title( $channel && ! empty( $channel['label'] ) ? $channel['label'] : $channel_id );
		return '' !== $slug ? $slug : 'product-feed';
	}

	/**
	 * unique_slug: no two v2 feeds share a file name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $slug    Wanted slug.
	 * @param   int    $feed_id This feed (skipped).
	 * @return  string
	 */
	static function unique_slug( $slug, $feed_id = 0 ) {
		$taken = array();
		foreach ( self::all() as $id ) {
			if ( (int) $id === (int) $feed_id ) {
				continue;
			}
			$stored = get_post_meta( $id, self::META_CONFIG, true );
			if ( is_array( $stored ) && ! empty( $stored['file_slug'] ) ) {
				$taken[ $stored['file_slug'] ] = true;
			}
		}
		$base = $slug;
		$i    = 2;
		while ( isset( $taken[ $slug ] ) || 'index' === $slug ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		return $slug;
	}

	/**
	 * channel.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $channel_id Channel id.
	 * @return  array|null
	 */
	static function channel( $channel_id ) {
		if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) {
			return null;
		}
		$channel = Alg_WC_PXF_Channels::get( $channel_id );
		return is_array( $channel ) ? $channel : null;
	}

	/**
	 * create.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $args name, channel, country, language, currency, plus any config key.
	 * @return  int|WP_Error Feed id.
	 */
	static function create( array $args ) {
		$channel_id = isset( $args['channel'] ) ? sanitize_key( $args['channel'] ) : 'google';
		$channel    = self::channel( $channel_id );
		$name       = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		if ( '' === $name ) {
			$name = ( $channel && ! empty( $channel['label'] ) ) ? $channel['label'] : __( 'Product feed', 'product-xml-feeds-for-woocommerce' );
		}
		$feed_id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => $name,
		), true );
		if ( is_wp_error( $feed_id ) ) {
			return $feed_id;
		}
		$config            = array_intersect_key( $args, self::defaults() );
		$config['channel'] = $channel_id;
		if ( ! isset( $config['filters'] ) && $channel && ! empty( $channel['default_filters'] ) && is_array( $channel['default_filters'] ) ) {
			$config['filters'] = $channel['default_filters']; // The channel's suggested filters for a new feed.
		}
		if ( empty( $config['file_slug'] ) ) {
			$config['file_slug'] = self::default_slug( $channel_id );
		}
		update_post_meta( $feed_id, self::META_STATE, self::default_state() );
		self::save_config( $feed_id, $config );
		do_action( 'alg_wc_pxf_feed_created', $feed_id );
		return (int) $feed_id;
	}

	/**
	 * delete: the feed file is renamed to "<file>.deleted", never removed. Classic feeds are never touched.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  bool
	 */
	static function delete( $feed_id ) {
		$feed_id = absint( $feed_id );
		if ( ! self::exists( $feed_id ) ) {
			return false;
		}
		$config = self::get_config( $feed_id );
		$folder = self::dir();
		foreach ( self::FORMATS as $format ) {
			foreach ( array( '', '.gz' ) as $gz ) {
				$file = $folder . '/' . $config['file_slug'] . '.' . $format . $gz;
				if ( file_exists( $file ) ) {
					@rename( $file, $file . '.deleted' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				if ( file_exists( $file . '.tmp' ) ) {
					@unlink( $file . '.tmp' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
		do_action( 'alg_wc_pxf_feed_deleted', $feed_id );
		delete_transient( 'alg_pxf_issues_' . $feed_id );
		delete_transient( 'alg_pxf_issues_run_' . $feed_id );
		return (bool) wp_delete_post( $feed_id, true );
	}

	/**
	 * get_state.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int  $feed_id Feed id.
	 * @param   bool $fresh   Read from the database, not the object cache (other requests may have changed it).
	 * @return  array
	 */
	static function get_state( $feed_id, $fresh = false ) {
		$feed_id = absint( $feed_id );
		if ( $fresh ) {
			global $wpdb;
			$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1", $feed_id, self::META_STATE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$state = null === $raw ? array() : maybe_unserialize( $raw );
			wp_cache_delete( $feed_id, 'post_meta' );
		} else {
			$state = get_post_meta( $feed_id, self::META_STATE, true );
		}
		$state = array_merge( self::default_state(), is_array( $state ) ? $state : array() );
		if ( ! is_array( $state['cursor'] ) ) {
			$state['cursor'] = array( 'last_date' => '', 'last_id' => 0 );
		}
		if ( ! is_array( $state['issues'] ) ) {
			$state['issues'] = array();
		}
		return $state;
	}

	/**
	 * save_state.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int   $feed_id Feed id.
	 * @param   array $state   State.
	 */
	static function save_state( $feed_id, array $state ) {
		update_post_meta( absint( $feed_id ), self::META_STATE, array_merge( self::default_state(), $state ) );
	}

	/**
	 * dir: uploads/product-feeds (no trailing slash).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function dir() {
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . '/' . self::FOLDER;
	}

	/**
	 * base_url.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  string
	 */
	static function base_url() {
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( $uploads['baseurl'] ) . '/' . self::FOLDER;
	}

	/**
	 * ensure_dir: creates the folder and an index.php in it.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  bool
	 */
	static function ensure_dir() {
		$dir = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return is_dir( $dir ) && wp_is_writable( $dir );
	}

	/**
	 * format: the feed's format, or the channel's default, or xml.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array      $config  Config.
	 * @param   array|null $channel Channel spec.
	 * @return  string
	 */
	static function format( array $config, $channel = null ) {
		$formats = ( is_array( $channel ) && ! empty( $channel['formats'] ) ) ? array_values( array_intersect( (array) $channel['formats'], self::FORMATS ) ) : array();
		if ( '' !== $config['format'] && in_array( $config['format'], self::FORMATS, true ) && ( empty( $formats ) || in_array( $config['format'], $formats, true ) ) ) {
			return $config['format'];
		}
		return ! empty( $formats ) ? $formats[0] : 'xml';
	}

	/**
	 * file_name.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  string
	 */
	static function file_name( $feed_id ) {
		$config = self::get_config( $feed_id );
		$format = self::format( $config, self::channel( $config['channel'] ) );
		return $config['file_slug'] . '.' . $format . ( $config['gzip'] ? '.gz' : '' );
	}

	/**
	 * file_path.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  string
	 */
	static function file_path( $feed_id ) {
		return self::dir() . '/' . self::file_name( $feed_id );
	}

	/**
	 * file_url.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $feed_id Feed id.
	 * @return  string
	 */
	static function file_url( $feed_id ) {
		return self::base_url() . '/' . rawurlencode( self::file_name( $feed_id ) );
	}

	/**
	 * to_array: the feed as the REST routes return it.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int  $feed_id Feed id.
	 * @param   bool $full    Add config and state.
	 * @return  array
	 */
	static function to_array( $feed_id, $full = false ) {
		$feed_id = absint( $feed_id );
		$config  = self::get_config( $feed_id );
		$state   = self::get_state( $feed_id );
		$channel = self::channel( $config['channel'] );
		$path    = self::file_path( $feed_id );
		$out     = array(
			'id'            => $feed_id,
			'type'          => 'v2',
			'name'          => (string) get_post_field( 'post_title', $feed_id, 'raw' ),
			'channel'       => $config['channel'],
			'channel_label' => $channel && isset( $channel['label'] ) ? $channel['label'] : $config['channel'],
			'format'        => self::format( $config, $channel ),
			'gzip'          => $config['gzip'],
			'schedule'      => $config['schedule'],
			'url'           => self::file_url( $feed_id ),
			'file_exists'   => file_exists( $path ),
			'file_size'     => file_exists( $path ) ? (int) filesize( $path ) : 0,
			'status'        => $state['status'],
			'last_good'     => (int) $state['last_good'],
			'written'       => (int) $state['written'],
			'issues_total'  => (int) array_sum( array_map( 'intval', $state['issues'] ) ),
		);
		if ( $full ) {
			$out['config'] = $config;
			$out['state']  = $state;
		}
		return $out;
	}

}

endif;
