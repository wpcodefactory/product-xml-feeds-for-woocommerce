<?php
/**
 * Product XML Feeds for WooCommerce - v2 channels registry.
 *
 * Each channel is one spec file in specs/ that returns the spec array described in v4/CONVENTIONS.md section 3.2.
 * Extra spec keys used by this module (all optional, documented here for the engine, writer and admin modules):
 *
 * - 'condition', 'gender', 'age_group': value maps canonical => channel value (like 'availability').
 * - 'ai': array( 'title' => field, 'description' => field, 'structured' => bool ). Which channel fields AI values go
 *   into; 'structured' (Google only) writes structured_title / structured_description instead of title / description.
 * - 'split': array( 'by' => 'color', 'nest' => 'size' ) (Skroutz): Alg_WC_PXF_Mapper::items() turns one variable
 *   product into one item per color with its sizes nested in 'variations'.
 * - 'raw' => true (custom): items are array( '_raw' => string ) written as is; header and footer come from
 *   Alg_WC_PXF_Mapper::custom_part().
 * - 'default_filters': suggested feed filters for a new feed of this channel (same shape as config 'filters').
 * - xml 'head_position': 'wrap' (default, head elements inside the wrap element, as RSS channel) or 'root'
 *   (head elements directly in the root element, before the wrap, as Skroutz created_at).
 * - xml 'cdata': array of fields whose text the channel's own examples wrap in CDATA (optional for writers).
 * - csv 'separator' (',' or "\t") and per field 'list_sep' for list values inside one cell.
 *
 * Field definition keys: 'label', 'required' (true|false|'recommended'|'conditional'), 'when' (no_gtin, variation,
 * apparel, sale, preorder, energy_label, country:XX), 'group', 'max', 'max_words', 'allowed', 'unit' ('currency' =
 * "12.00 EUR", 'decimal' = "12.00", 'integer'), 'list' (repeated element / array), 'list_max', 'list_sep', 'children'
 * (sub-attributes: child => field definition), 'json' ('bool' = write true/false as JSON booleans), 'list_item'
 * (element name of each entry of a list of sub-attribute arrays), 'source' (array( 'type' => 'field|attribute|meta|
 * taxonomy|static|placeholder', 'key' => '', 'value' => '' )).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) :

class Alg_WC_PXF_Channels {

	/**
	 * Channel ids in picker order. Each has specs/<id>.php.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const IDS = array( 'google', 'meta', 'pinterest', 'microsoft', 'tiktok', 'openai', 'idealo', 'pricerunner', 'skroutz', 'custom' );

	/**
	 * Loaded specs, per locale (labels are translated).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @var array
	 */
	protected static $specs = array();

	/**
	 * all.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array id => spec.
	 */
	static function all() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( ! isset( self::$specs[ $locale ] ) ) {
			$specs = array();
			foreach ( self::IDS as $id ) {
				$file = __DIR__ . '/specs/' . $id . '.php';
				if ( file_exists( $file ) ) {
					$spec = include $file;
					if ( is_array( $spec ) ) {
						$specs[ $id ] = self::normalize( $spec, $id );
					}
				}
			}
			/**
			 * Filters the channel specs (add, change or remove channels).
			 *
			 * @since 4.0.0
			 *
			 * @param array $specs id => spec.
			 */
			$specs = apply_filters( 'alg_wc_pxf_channels', $specs );
			foreach ( $specs as $id => $spec ) {
				$specs[ $id ] = self::normalize( $spec, $id );
			}
			self::$specs[ $locale ] = $specs;
		}
		return self::$specs[ $locale ];
	}

	/**
	 * get.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $id Channel id.
	 * @return  array|null
	 */
	static function get( $id ) {
		$all = self::all();
		return ( is_string( $id ) && isset( $all[ $id ] ) ? $all[ $id ] : null );
	}

	/**
	 * reset (drops the cache, for tests and after the filter changes).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function reset() {
		self::$specs = array();
	}

	/**
	 * normalize: every top level key present.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $spec Spec.
	 * @param   string $id   Channel id.
	 * @return  array
	 */
	static function normalize( $spec, $id ) {
		$spec = array_merge(
			array(
				'id'              => $id,
				'label'           => $id,
				'group'           => '',
				'countries'       => array(),
				'formats'         => array( 'xml' ),
				'encoding'        => 'UTF-8',
				'xml'             => array(),
				'csv'             => array( 'separator' => ',' ),
				'variation_mode'  => 'all',
				'google_category' => false,
				'notes'           => '',
				'availability'    => array(),
				'condition'       => array(),
				'gender'          => array(),
				'age_group'       => array(),
				'ai'              => array( 'title' => 'title', 'description' => 'description', 'structured' => false ),
				'split'           => array(),
				'raw'             => false,
				'default_filters' => array(),
				'fields'          => array(),
			),
			$spec
		);
		foreach ( $spec['fields'] as $field => $def ) {
			$spec['fields'][ $field ] = self::normalize_field( $def, $field );
		}
		return $spec;
	}

	/**
	 * normalize_field.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $def   Field definition.
	 * @param   string $field Field name.
	 * @return  array
	 */
	static function normalize_field( $def, $field ) {
		$def = array_merge(
			array(
				'label'    => $field,
				'required' => false,
				'group'    => 'extra',
				'source'   => array(),
			),
			(array) $def
		);
		if ( ! empty( $def['children'] ) ) {
			foreach ( $def['children'] as $child => $child_def ) {
				$def['children'][ $child ] = self::normalize_field( $child_def, $child );
			}
		}
		return $def;
	}

	/**
	 * required_fields: names of the fields with 'required' => true.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $spec Channel spec.
	 * @return  array
	 */
	static function required_fields( $spec ) {
		$out = array();
		foreach ( $spec['fields'] as $field => $def ) {
			if ( true === $def['required'] ) {
				$out[] = $field;
			}
		}
		return $out;
	}

	/**
	 * field: shorthand used by the spec files.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string       $label    Label.
	 * @param   bool|string  $required Required state.
	 * @param   string       $group    Group.
	 * @param   string|array $source   Model key (type field) or a source array; '' = no default source.
	 * @param   array        $extra    Other definition keys.
	 * @return  array
	 */
	static function field( $label, $required, $group, $source, $extra = array() ) {
		if ( is_string( $source ) ) {
			$source = ( '' === $source ? array() : array( 'type' => 'field', 'key' => $source ) );
		}
		return array_merge(
			array(
				'label'    => $label,
				'required' => $required,
				'group'    => $group,
				'source'   => $source,
			),
			$extra
		);
	}

}

endif;
