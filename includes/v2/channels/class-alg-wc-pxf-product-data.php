<?php
/**
 * Product XML Feeds for WooCommerce - v2 canonical product model.
 *
 * One plain array per product or variation that every channel is mapped from (v4/CONVENTIONS.md section 3.2).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Product_Data' ) ) :

class Alg_WC_PXF_Product_Data {

	/**
	 * Attribute names and slugs per model key, in several languages (compared lower case, accents removed).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const ATTRIBUTE_NAMES = array(
		'color'     => array( 'color', 'colour', 'colors', 'colours', 'farbe', 'farben', 'couleur', 'couleurs', 'colore', 'colori', 'kleur', 'cor', 'kolor', 'barva', 'farve', 'farg', 'farge', 'vari', 'renk', 'χρώμα', 'χρωμα', 'χρώματα', 'цвет', 'цвят' ),
		'size'      => array( 'size', 'sizes', 'größe', 'grosse', 'groesse', 'größen', 'konfektionsgröße', 'taille', 'tailles', 'pointure', 'talla', 'tallas', 'taglia', 'taglie', 'maat', 'tamanho', 'rozmiar', 'velikost', 'storrelse', 'storlek', 'koko', 'beden', 'μέγεθος', 'μεγεθος', 'νούμερο', 'νουμερο', 'размер' ),
		'material'  => array( 'material', 'materials', 'materiał', 'matiere', 'matière', 'materiale', 'materiaal', 'stoff', 'tissu', 'tejido', 'fabric', 'composition', 'zusammensetzung', 'υλικό', 'υλικο', 'ύφασμα', 'υφασμα', 'материал' ),
		'pattern'   => array( 'pattern', 'muster', 'motif', 'motivo', 'patroon', 'print', 'stampa', 'estampado', 'wzor', 'wzór', 'σχέδιο', 'σχεδιο', 'μοτίβο', 'μοτιβο', 'узор' ),
		'gender'    => array( 'gender', 'sex', 'geschlecht', 'genre', 'sexe', 'genero', 'género', 'sesso', 'geslacht', 'plec', 'płeć', 'φύλο', 'φυλο', 'пол' ),
		'age_group' => array( 'age group', 'agegroup', 'age', 'altersgruppe', 'alter', 'tranche d age', 'tranche d\'age', 'tranche d\'âge', 'age cible', 'grupo de edad', 'edad', 'fascia d eta', 'fascia d\'eta', 'eta', 'età', 'leeftijd', 'leeftijdsgroep', 'ηλικία', 'ηλικια', 'ηλικιακή ομάδα', 'ηλικιακη ομαδα', 'возраст' ),
		'brand'     => array( 'brand', 'brands', 'marke', 'marque', 'marca', 'merk', 'marka', 'μάρκα', 'μαρκα', 'κατασκευαστής', 'κατασκευαστης', 'manufacturer', 'hersteller', 'fabricant', 'бренд' ),
	);

	/**
	 * Value words per canonical gender.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const GENDER_WORDS = array(
		'male'   => array( 'male', 'man', 'men', 'mens', 'men\'s', 'herren', 'herr', 'manner', 'männer', 'mannlich', 'männlich', 'homme', 'hommes', 'masculin', 'uomo', 'hombre', 'masculino', 'heren', 'man', 'meski', 'męski', 'ανδρας', 'άνδρας', 'ανδρικο', 'ανδρικό', 'ανδρικα', 'ανδρικά', 'αντρικο', 'αντρικό' ),
		'female' => array( 'female', 'woman', 'women', 'womens', 'women\'s', 'damen', 'dame', 'frauen', 'weiblich', 'femme', 'femmes', 'feminin', 'féminin', 'donna', 'mujer', 'feminino', 'dames', 'vrouw', 'damski', 'γυναικα', 'γυναίκα', 'γυναικειο', 'γυναικείο', 'γυναικεια', 'γυναικεία' ),
		'unisex' => array( 'unisex', 'unisexe', 'uni', 'both', 'all', 'beide', 'mixte', 'unisexo', 'unisex', 'για ολους', 'για όλους' ),
	);

	/**
	 * Value words per canonical age group (Meta adds teen and all ages).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	const AGE_WORDS = array(
		'newborn'  => array( 'newborn', 'newborns', 'neugeborene', 'neugeboren', 'nouveau-ne', 'nouveau ne', 'recien nacido', 'neonato', 'pasgeborene', 'νεογέννητο', 'νεογεννητο' ),
		'infant'   => array( 'infant', 'infants', 'baby', 'babies', 'saugling', 'säugling', 'bebe', 'bébé', 'bebé', 'neonati', 'βρέφος', 'βρεφος', 'βρεφικό', 'βρεφικο', 'μωρό', 'μωρο' ),
		'toddler'  => array( 'toddler', 'toddlers', 'kleinkind', 'kleinkinder', 'tout-petit', 'tout petit', 'nino pequeno', 'peuter', 'νήπιο', 'νηπιο' ),
		'kids'     => array( 'kids', 'kid', 'child', 'children', 'kinder', 'kind', 'enfant', 'enfants', 'nino', 'ninos', 'niño', 'niños', 'bambino', 'bambini', 'kinderen', 'junior', 'παιδί', 'παιδι', 'παιδικό', 'παιδικο', 'παιδικά', 'παιδικα' ),
		'teen'     => array( 'teen', 'teens', 'teenager', 'jugendliche', 'ado', 'adolescent', 'adolescente', 'έφηβος', 'εφηβος', 'εφηβικό', 'εφηβικο' ),
		'adult'    => array( 'adult', 'adults', 'erwachsene', 'erwachsener', 'adulte', 'adultes', 'adulto', 'adultos', 'adulti', 'volwassene', 'volwassenen', 'ενήλικες', 'ενηλικες', 'ενήλικας', 'ενηλικας' ),
		'all_ages' => array( 'all ages', 'alle', 'tous ages', 'todas las edades', 'όλες οι ηλικίες' ),
	);

	/**
	 * model.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product or variation.
	 * @param   array      $config  Feed config (Alg_WC_PXF_Feeds::get_config()).
	 * @return  array Canonical model; every key present, '' when empty.
	 */
	static function model( $product, $config = array() ) {
		$config       = (array) $config;
		$is_variation = $product->is_type( 'variation' );
		$parent       = ( $is_variation ? wc_get_product( $product->get_parent_id() ) : null );
		$main         = ( $parent ? $parent : $product );
		$mapping      = ( isset( $config['mapping'] ) && is_array( $config['mapping'] ) ? $config['mapping'] : array() );

		// Attributes (variation values over the parent's other attributes).
		$attributes         = self::attributes( $main );
		$variant_attributes = ( $is_variation ? self::variation_attributes( $product, $main ) : array() );
		$attributes         = array_merge( $attributes, $variant_attributes );

		// Title.
		$title        = self::plain_title( $product->get_name() );
		$parent_title = ( $parent ? self::plain_title( $parent->get_name() ) : '' );
		if ( $is_variation && ! empty( $variant_attributes ) ) {
			$missing = array();
			foreach ( $variant_attributes as $value ) {
				if ( '' !== $value && false === mb_stripos( $title, $value ) ) {
					$missing[] = $value;
				}
			}
			if ( ! empty( $missing ) ) {
				$title .= ( $title === $parent_title || false === strpos( $title, ' - ' ) ? ' - ' : ', ' ) . implode( ', ', $missing );
			}
		}

		// Descriptions.
		$description = ( $is_variation ? $product->get_description() : '' );
		if ( '' === trim( (string) $description ) ) {
			$description = $main->get_description();
		}
		$short_description = $main->get_short_description();
		if ( '' === trim( wp_strip_all_tags( (string) $description ) ) ) {
			$description = $short_description;
		}
		$description       = self::plain_text( $description, 5000 );
		$short_description = self::plain_text( $short_description, 5000 );

		// Images.
		$image_id   = $product->get_image_id();
		if ( ! $image_id && $parent ) {
			$image_id = $parent->get_image_id();
		}
		$image_link = ( $image_id ? (string) wp_get_attachment_url( $image_id ) : '' );
		$gallery    = $product->get_gallery_image_ids();
		if ( empty( $gallery ) && $parent ) {
			$gallery = $parent->get_gallery_image_ids();
		}
		$additional = array();
		foreach ( (array) $gallery as $gallery_id ) {
			$url = wp_get_attachment_url( $gallery_id );
			if ( $url && $url !== $image_link && ! in_array( $url, $additional, true ) ) {
				$additional[] = $url;
			}
			if ( count( $additional ) >= 10 ) {
				break;
			}
		}

		// Prices.
		$prices = self::prices( $product );

		// Stock.
		$status       = $product->get_stock_status();
		$availability = self::availability( $status );
		$stock_qty    = ( $product->managing_stock() ? (int) $product->get_stock_quantity() : '' );

		// Condition.
		$condition = self::meta( $product, $parent, '_alg_pxf_condition' );
		$condition = self::canonical_condition( $condition );

		// Identifiers.
		$gtin = '';
		if ( is_callable( array( $product, 'get_global_unique_id' ) ) ) {
			$gtin = (string) $product->get_global_unique_id();
		}
		if ( '' === $gtin ) {
			$gtin = (string) get_post_meta( $product->get_id(), '_alg_ean', true );
		}
		if ( '' === $gtin ) {
			$gtin = self::mapped( 'gtin', $mapping, $product, $parent );
		}
		$gtin = preg_replace( '/[\s\-]+/', '', $gtin );
		$mpn  = self::mapped( 'mpn', $mapping, $product, $parent );

		$brand = self::brand( $main, $attributes );
		if ( '' === $brand ) {
			$brand = self::mapped( 'brand', $mapping, $product, $parent );
		}

		// Categories.
		$product_type = '';
		$deepest      = Alg_WC_PXF_Google_Taxonomy::categories_deepest_first( $main->get_id() );
		if ( ! empty( $deepest ) ) {
			$names = array();
			foreach ( array_reverse( get_ancestors( $deepest[0]->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$names[] = html_entity_decode( $ancestor->name, ENT_QUOTES, 'UTF-8' );
				}
			}
			$names[]      = html_entity_decode( $deepest[0]->name, ENT_QUOTES, 'UTF-8' );
			$product_type = implode( ' > ', $names );
		}

		// Apparel attributes: attribute names first, then approved AI values.
		$detected = self::detect( $attributes );
		$ai_attrs = self::ai_attributes( $product, $parent );
		foreach ( array( 'color', 'size', 'material', 'pattern', 'gender', 'age_group' ) as $key ) {
			if ( '' === $detected[ $key ] && isset( $ai_attrs[ $key ] ) && is_scalar( $ai_attrs[ $key ] ) ) {
				$detected[ $key ] = trim( (string) $ai_attrs[ $key ] );
			}
		}
		$detected['gender']    = self::canonical_word( $detected['gender'], self::GENDER_WORDS );
		$detected['age_group'] = self::canonical_word( $detected['age_group'], self::AGE_WORDS );

		// Weight.
		$weight = self::weight( $product );

		// AI values (approved only).
		$ai_title       = self::ai_value( $product, $parent, 'title' );
		$ai_description = self::ai_value( $product, $parent, 'description' );
		if ( $is_variation && '' !== $ai_title && '' === self::ai_value( $product, null, 'title' ) && ! empty( $variant_attributes ) ) {
			$ai_title .= ' - ' . implode( ', ', array_filter( $variant_attributes, 'strlen' ) );
		}
		if ( '' !== $ai_description ) {
			$ai_description = self::plain_text( $ai_description, 5000 );
		}

		$model = array(
			'id'                        => (string) $product->get_id(),
			'item_group_id'             => ( $is_variation ? (string) $product->get_parent_id() : '' ),
			'title'                     => $title,
			'description'               => $description,
			'short_description'         => $short_description,
			'link'                      => (string) $product->get_permalink(),
			'image_link'                => $image_link,
			'additional_image_links'    => $additional,
			'price'                     => $prices['price'],
			'sale_price'                => $prices['sale_price'],
			'sale_price_effective_date' => $prices['sale_price_effective_date'],
			'currency'                  => ( ! empty( $config['currency'] ) ? strtoupper( (string) $config['currency'] ) : get_woocommerce_currency() ),
			'availability'              => $availability,
			'stock_quantity'            => $stock_qty,
			'condition'                 => $condition,
			'gtin'                      => $gtin,
			'mpn'                       => $mpn,
			'brand'                     => $brand,
			'identifier_exists'         => ( '' !== $gtin || ( '' !== $brand && '' !== $mpn ) ? 'yes' : 'no' ),
			'google_product_category'   => Alg_WC_PXF_Google_Taxonomy::for_product( $product ),
			'product_type'              => $product_type,
			'color'                     => $detected['color'],
			'size'                      => $detected['size'],
			'material'                  => $detected['material'],
			'pattern'                   => $detected['pattern'],
			'gender'                    => $detected['gender'],
			'age_group'                 => $detected['age_group'],
			'weight'                    => $weight,
			'shipping_weight'           => $weight,
			'attributes'                => $attributes,
			'ai_title'                  => $ai_title,
			'ai_description'            => $ai_description,
			'type'                      => $product->get_type(),
			'parent_title'              => $parent_title,
			'lowest_price_30d'          => '',
			'unit_pricing_measure'      => '',
			// Extra keys used by the channel specs.
			'sku'                       => (string) $product->get_sku(),
			'variant_attributes'        => $variant_attributes,
			'vat'                       => self::vat_rate( $product ),
		);

		/**
		 * Filters the canonical product model (Pro and other plugins fill or change values here).
		 *
		 * @since 4.0.0
		 *
		 * @param array      $model   Model.
		 * @param WC_Product $product Product or variation.
		 * @param array      $config  Feed config.
		 */
		return apply_filters( 'alg_wc_pxf_model', $model, $product, $config );
	}

	/**
	 * keys: every model key (for admin pickers and tests).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @return  array
	 */
	static function keys() {
		return array(
			'id', 'item_group_id', 'title', 'description', 'short_description', 'link', 'image_link', 'additional_image_links',
			'price', 'sale_price', 'sale_price_effective_date', 'currency', 'availability', 'stock_quantity', 'condition', 'gtin',
			'mpn', 'brand', 'identifier_exists', 'google_product_category', 'product_type', 'color', 'size', 'material', 'pattern',
			'gender', 'age_group', 'weight', 'shipping_weight', 'attributes', 'ai_title', 'ai_description', 'type', 'parent_title',
			'lowest_price_30d', 'unit_pricing_measure', 'sku', 'variant_attributes', 'vat',
		);
	}

	/**
	 * plain_title: entities decoded, real HTML tags removed, text such as "<Set of 2>" kept.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Title.
	 * @return  string
	 */
	static function plain_title( $text ) {
		$text = (string) $text;
		$text = preg_replace( '#</?(?:a|b|i|u|em|strong|span|br|p|div|sup|sub|small|big|font|h[1-6]|mark|del|ins|s|strike)\b[^>]*>#i', ' ', $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return self::squash( $text );
	}

	/**
	 * plain_text: HTML and shortcodes removed, entities decoded, white space collapsed, cut on a word at $max.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @param   int    $max  Max characters (0 = no cut).
	 * @return  string
	 */
	static function plain_text( $text, $max = 0 ) {
		$text = strip_shortcodes( (string) $text );
		$text = preg_replace( '/\[\/?[a-zA-Z0-9_\-]+[^\]]*\]/', ' ', $text ); // Shortcodes that are not registered.
		$text = preg_replace( '#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = self::squash( $text );
		return ( $max > 0 ? self::cut( $text, $max ) : $text );
	}

	/**
	 * squash: one space for any run of white space (incl. no-break spaces), trimmed.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  string
	 */
	static function squash( $text ) {
		$text = preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * cut: at most $max characters, cut after a whole word (a word longer than $max is the only hard cut).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @param   int    $max  Max characters.
	 * @return  string
	 */
	static function cut( $text, $max ) {
		$text = (string) $text;
		$max  = (int) $max;
		if ( $max <= 0 || mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut  = mb_substr( $text, 0, $max );
		$next = mb_substr( $text, $max, 1 );
		if ( '' !== $next && ! preg_match( '/\s/u', $next ) ) {
			$space = max( (int) mb_strrpos( $cut, ' ' ), 0 );
			if ( $space > 0 ) {
				$cut = mb_substr( $cut, 0, $space );
			}
		}
		return rtrim( $cut, " \t,;:-/|(" );
	}

	/**
	 * prices: regular and sale price as shown in the shop (tax per shop display setting), 2 decimals.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  array price, sale_price, sale_price_effective_date.
	 */
	static function prices( $product ) {
		$out = array( 'price' => '', 'sale_price' => '', 'sale_price_effective_date' => '' );
		if ( $product->is_type( 'variable' ) ) {
			$regular = $product->get_variation_regular_price( 'min', true );
			$sale    = $product->get_variation_sale_price( 'min', true );
			if ( '' !== (string) $regular && null !== $regular ) {
				$out['price'] = self::decimal( $regular );
				if ( '' !== (string) $sale && (float) $sale < (float) $regular ) {
					$out['sale_price'] = self::decimal( $sale );
				}
			}
			return $out;
		}
		$regular = $product->get_regular_price();
		$sale    = $product->get_sale_price();
		if ( '' === (string) $regular ) {
			$regular = $product->get_price();
		}
		if ( '' === (string) $regular ) {
			return $out;
		}
		$out['price'] = self::decimal( wc_get_price_to_display( $product, array( 'price' => $regular ) ) );
		if ( '' !== (string) $sale && (float) $sale < (float) $regular ) {
			$from = $product->get_date_on_sale_from();
			$to   = $product->get_date_on_sale_to();
			$now  = time();
			if ( $to && $to->getTimestamp() < $now ) {
				return $out; // Sale is over.
			}
			$out['sale_price'] = self::decimal( wc_get_price_to_display( $product, array( 'price' => $sale ) ) );
			if ( $from || $to ) {
				$start = ( $from ? $from : new WC_DateTime( '@' . $now ) );
				if ( ! $from ) {
					$start->setTimezone( new DateTimeZone( wc_timezone_string() ) );
				}
				$end = ( $to ? $to : new WC_DateTime( '@' . ( $start->getTimestamp() + YEAR_IN_SECONDS ) ) );
				if ( ! $to ) {
					$end->setTimezone( new DateTimeZone( wc_timezone_string() ) );
				}
				$out['sale_price_effective_date'] = $start->format( 'Y-m-d\TH:iO' ) . '/' . $end->format( 'Y-m-d\TH:iO' );
			}
		}
		return $out;
	}

	/**
	 * decimal: "12.00".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   mixed $value Number.
	 * @return  string
	 */
	static function decimal( $value ) {
		return ( '' === (string) $value || ! is_numeric( $value ) ? '' : number_format( (float) $value, 2, '.', '' ) );
	}

	/**
	 * availability: canonical value from the WooCommerce stock status.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $status Stock status.
	 * @return  string in_stock, out_of_stock, backorder or preorder.
	 */
	static function availability( $status ) {
		switch ( $status ) {
			case 'outofstock':
				return 'out_of_stock';
			case 'onbackorder':
				return 'backorder';
			case 'preorder':
			case 'pre-order':
				return 'preorder';
			default:
				return 'in_stock';
		}
	}

	/**
	 * canonical_condition.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $value Stored value.
	 * @return  string new, refurbished or used.
	 */
	static function canonical_condition( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( 'refurbished', 'renewed', 'refurb', 'generalüberholt', 'reconditionne', 'reconditionné', 'ανακατασκευασμένο' ), true ) ) {
			return 'refurbished';
		}
		if ( in_array( $value, array( 'used', 'second hand', 'secondhand', 'gebraucht', 'occasion', 'usato', 'usado', 'μεταχειρισμένο', 'μεταχειρισμενο' ), true ) || 0 === strpos( $value, 'used' ) ) {
			return 'used';
		}
		return 'new';
	}

	/**
	 * meta: post meta of the product, then of the parent.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product      $product Product.
	 * @param   WC_Product|null $parent  Parent.
	 * @param   string          $key     Meta key.
	 * @return  string
	 */
	static function meta( $product, $parent, $key ) {
		$value = get_post_meta( $product->get_id(), $key, true );
		if ( ( '' === $value || null === $value || false === $value ) && $parent ) {
			$value = get_post_meta( $parent->get_id(), $key, true );
		}
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
		}
		return trim( (string) $value );
	}

	/**
	 * mapped: value of a feed mapping of type meta, attribute or taxonomy (used for gtin, mpn, brand in the model).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string          $field   Model field.
	 * @param   array           $mapping Feed mapping.
	 * @param   WC_Product      $product Product.
	 * @param   WC_Product|null $parent  Parent.
	 * @return  string
	 */
	static function mapped( $field, $mapping, $product, $parent ) {
		if ( empty( $mapping[ $field ]['type'] ) || empty( $mapping[ $field ]['key'] ) ) {
			return '';
		}
		$key = (string) $mapping[ $field ]['key'];
		switch ( $mapping[ $field ]['type'] ) {
			case 'meta':
				return self::meta( $product, $parent, $key );
			case 'attribute':
				return self::attribute_value( $product, $parent, $key );
			case 'taxonomy':
				return self::term_names( ( $parent ? $parent->get_id() : $product->get_id() ), $key );
		}
		return '';
	}

	/**
	 * attribute_value: one attribute by name or slug, variation first, then parent.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product      $product Product.
	 * @param   WC_Product|null $parent  Parent.
	 * @param   string          $key     Attribute name or slug (pa_color, color, Color).
	 * @return  string
	 */
	static function attribute_value( $product, $parent, $key ) {
		$candidates = array_unique( array( $key, sanitize_title( $key ), ( 0 === strpos( $key, 'pa_' ) ? $key : 'pa_' . sanitize_title( $key ) ) ) );
		foreach ( array( $product, $parent ) as $object ) {
			if ( ! $object ) {
				continue;
			}
			foreach ( $candidates as $candidate ) {
				$value = $object->get_attribute( $candidate );
				if ( '' !== (string) $value ) {
					return html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
				}
			}
		}
		return '';
	}

	/**
	 * term_names: term names of a taxonomy, comma separated.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int    $post_id  Post ID.
	 * @param   string $taxonomy Taxonomy.
	 * @return  string
	 */
	static function term_names( $post_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return html_entity_decode( implode( ', ', wp_list_pluck( $terms, 'name' ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * attributes: label => value (term names or custom values joined with ", ").
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product (not a variation).
	 * @return  array
	 */
	static function attributes( $product ) {
		$out = array();
		foreach ( $product->get_attributes() as $key => $attribute ) {
			if ( ! is_a( $attribute, 'WC_Product_Attribute' ) ) {
				continue;
			}
			$label = wc_attribute_label( $attribute->get_name(), $product );
			if ( $attribute->is_taxonomy() ) {
				$values = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) );
			} else {
				$values = $attribute->get_options();
			}
			$values = array_filter( array_map( 'trim', array_map( 'strval', (array) $values ) ), 'strlen' );
			$out[ html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ] = html_entity_decode( implode( ', ', $values ), ENT_QUOTES, 'UTF-8' );
		}
		return $out;
	}

	/**
	 * variation_attributes: label => value of the options that make the variation ("Any" options left out).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product_Variation $variation Variation.
	 * @param   WC_Product           $parent    Parent.
	 * @return  array
	 */
	static function variation_attributes( $variation, $parent ) {
		$out = array();
		foreach ( $variation->get_attributes() as $key => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$name = $key;
			if ( taxonomy_exists( $key ) ) {
				$term  = get_term_by( 'slug', $value, $key );
				$value = ( $term && ! is_wp_error( $term ) ? $term->name : $value );
			} else {
				$parent_attributes = $parent->get_attributes();
				if ( isset( $parent_attributes[ $key ] ) && is_a( $parent_attributes[ $key ], 'WC_Product_Attribute' ) ) {
					$name = $parent_attributes[ $key ]->get_name();
					foreach ( $parent_attributes[ $key ]->get_options() as $option ) {
						if ( sanitize_title( $option ) === sanitize_title( $value ) ) {
							$value = $option;
							break;
						}
					}
				}
			}
			$label         = html_entity_decode( wc_attribute_label( $name, $parent ), ENT_QUOTES, 'UTF-8' );
			$out[ $label ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		}
		return $out;
	}

	/**
	 * kind: which model key an attribute name or slug stands for ('' when none).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name Attribute label, name or slug.
	 * @return  string color, size, material, pattern, gender, age_group, brand or ''.
	 */
	static function kind( $name ) {
		$forms = self::forms( preg_replace( '/^(attribute_)?pa_/', '', (string) $name ) );
		foreach ( self::ATTRIBUTE_NAMES as $kind => $names ) {
			foreach ( $names as $candidate ) {
				if ( array_intersect( $forms, self::forms( $candidate ) ) ) {
					return $kind;
				}
			}
		}
		return '';
	}

	/**
	 * forms: lower case forms of a word for matching (as is, accents removed, - and _ as spaces).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  array
	 */
	static function forms( $text ) {
		$lower = mb_strtolower( trim( (string) $text ), 'UTF-8' );
		$space = trim( preg_replace( '/[\s_\-]+/u', ' ', $lower ) );
		return array_values( array_unique( array( $lower, $space, remove_accents( $lower ), remove_accents( $space ), self::strip_greek_accents( $space ) ) ) );
	}

	/**
	 * strip_greek_accents.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @return  string
	 */
	static function strip_greek_accents( $text ) {
		return strtr( $text, array( 'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ϋ' => 'υ', 'ΰ' => 'υ' ) );
	}

	/**
	 * detect: color, size, material, pattern, gender, age_group (and brand) from attributes.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $attributes label => value.
	 * @return  array
	 */
	static function detect( $attributes ) {
		$out = array_fill_keys( array( 'color', 'size', 'material', 'pattern', 'gender', 'age_group', 'brand' ), '' );
		foreach ( $attributes as $label => $value ) {
			$kind = self::kind( $label );
			if ( '' !== $kind && '' === $out[ $kind ] ) {
				$out[ $kind ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * canonical_word: canonical key when a value matches a word list, else the value as is.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $value Value.
	 * @param   array  $words canonical => words.
	 * @return  string
	 */
	static function canonical_word( $value, $words ) {
		if ( '' === (string) $value ) {
			return '';
		}
		$forms = self::forms( $value );
		foreach ( $words as $canonical => $list ) {
			foreach ( $list as $word ) {
				if ( array_intersect( $forms, self::forms( $word ) ) ) {
					return $canonical;
				}
			}
		}
		return (string) $value;
	}

	/**
	 * brand: core Brands taxonomy product_brand, then an attribute named brand.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $main       Product (parent for variations).
	 * @param   array      $attributes label => value.
	 * @return  string
	 */
	static function brand( $main, $attributes ) {
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $main->get_id(), 'product_brand' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				// Deepest brand first (a sub brand is more specific).
				usort(
					$terms,
					function ( $a, $b ) {
						return (int) $b->parent - (int) $a->parent;
					}
				);
				return html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
			}
		}
		$detected = self::detect( $attributes );
		return $detected['brand'];
	}

	/**
	 * weight: "1.5 kg" (units kg, g, lb, oz), '' when empty.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  string
	 */
	static function weight( $product ) {
		$weight = $product->get_weight();
		if ( '' === (string) $weight || ! is_numeric( $weight ) ) {
			return '';
		}
		$unit = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
		$unit = ( 'lbs' === $unit ? 'lb' : $unit );
		return wc_format_decimal( $weight ) . ' ' . $unit;
	}

	/**
	 * vat_rate: VAT percent of the product's tax class at the shop base address ('' when taxes are off, '0' when none).
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product $product Product.
	 * @return  string
	 */
	static function vat_rate( $product ) {
		if ( ! wc_tax_enabled() || ! $product->is_taxable() ) {
			return ( wc_tax_enabled() ? '0' : '' );
		}
		$rates = WC_Tax::get_base_tax_rates( $product->get_tax_class( 'unfiltered' ) );
		$total = 0;
		foreach ( (array) $rates as $rate ) {
			$total += (float) $rate['rate'];
		}
		return wc_format_decimal( $total, 2, true );
	}

	/**
	 * ai_value: approved AI title or description ('' unless the status meta is 'approved').
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product      $product Product.
	 * @param   WC_Product|null $parent  Parent (checked when the product has no approved value).
	 * @param   string          $what    title or description.
	 * @return  string
	 */
	static function ai_value( $product, $parent, $what ) {
		foreach ( array( $product, $parent ) as $object ) {
			if ( ! $object ) {
				continue;
			}
			if ( 'approved' === get_post_meta( $object->get_id(), '_alg_pxf_ai_' . $what . '_status', true ) ) {
				$value = trim( (string) get_post_meta( $object->get_id(), '_alg_pxf_ai_' . $what, true ) );
				if ( '' !== $value ) {
					return ( 'title' === $what ? self::plain_title( $value ) : $value );
				}
			}
		}
		return '';
	}

	/**
	 * ai_attributes: approved AI attributes (post meta _alg_pxf_ai_attrs, JSON) of the product, then the parent.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   WC_Product      $product Product.
	 * @param   WC_Product|null $parent  Parent.
	 * @return  array
	 */
	static function ai_attributes( $product, $parent ) {
		$out = array();
		foreach ( array( $parent, $product ) as $object ) {
			if ( ! $object || 'approved' !== get_post_meta( $object->get_id(), '_alg_pxf_ai_attrs_status', true ) ) {
				continue;
			}
			$raw  = get_post_meta( $object->get_id(), '_alg_pxf_ai_attrs', true );
			$data = ( is_array( $raw ) ? $raw : json_decode( (string) $raw, true ) );
			if ( is_array( $data ) ) {
				$out = array_merge( $out, array_filter( $data, 'is_scalar' ) );
			}
		}
		return $out;
	}

}

endif;
