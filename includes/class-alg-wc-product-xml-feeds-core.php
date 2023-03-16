<?php
/**
 * Product XML Feeds for WooCommerce - Core Class
 *
 * @version 1.7.2
 * @since   1.0.0
 * @author  WPWhale
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds_Core' ) ) :

class Alg_WC_Product_XML_Feeds_Core {

	/**
	 * Constructor.
	 *
	 * @version 1.7.0
	 * @since   1.0.0
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_wc_product_xml_feeds_enabled', 'yes' ) ) {
			add_action( 'init',           array( $this, 'schedule_the_events' ) );
			add_action( 'admin_init',     array( $this, 'schedule_the_events' ) );
			add_action( 'admin_init',     array( $this, 'alg_create_products_xml' ) );
			add_filter( 'cron_schedules', array( $this, 'cron_add_custom_intervals' ) );
			$total_number = apply_filters( 'alg_wc_product_xml_feeds_values', 1, 'total_number' );
			for ( $i = 1; $i <= $total_number; $i++ ) {
				add_action( 'alg_create_products_xml_hook_' . $i, array( $this, 'create_products_xml_cron' ), PHP_INT_MAX, 2 );
			}
		}
	}

	/**
	 * On an early action hook, check if the hook is scheduled - if not, schedule it.
	 *
	 * @version 1.7.0
	 * @since   1.0.0
	 */
	function schedule_the_events() {
		$update_intervals  = array(
			'minutely',
			'hourly',
			'twicedaily',
			'daily',
			'weekly',
		);
		$total_number = apply_filters( 'alg_wc_product_xml_feeds_values', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$event_hook = 'alg_create_products_xml_hook_' . $i;
			if ( 'yes' === get_option( 'alg_products_xml_enabled_' . $i, 'yes' ) ) {
				$selected_interval = apply_filters( 'alg_wc_product_xml_feeds_values', 'weekly', 'update_interval', $i );
				foreach ( $update_intervals as $interval ) {
					$event_timestamp = wp_next_scheduled( $event_hook, array( $interval, $i ) );
					if ( $selected_interval === $interval ) {
						update_option( 'alg_create_products_xml_cron_time_' . $i, $event_timestamp );
					}
					if ( ! $event_timestamp && $selected_interval === $interval ) {
						wp_schedule_event( time(), $selected_interval, $event_hook, array( $selected_interval, $i ) );
					} elseif ( $event_timestamp && $selected_interval !== $interval ) {
						wp_unschedule_event( $event_timestamp, $event_hook, array( $interval, $i ) );
					}
				}
			} else { // unschedule all events
				update_option( 'alg_create_products_xml_cron_time_' . $i, '' );
				foreach ( $update_intervals as $interval ) {
					$event_timestamp = wp_next_scheduled( $event_hook, array( $interval, $i ) );
					if ( $event_timestamp ) {
						wp_unschedule_event( $event_timestamp, $event_hook, array( $interval, $i ) );
					}
				}
			}
		}
	}

	/**
	 * cron_add_custom_intervals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function cron_add_custom_intervals( $schedules ) {
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __( 'Once Weekly', 'product-xml-feeds-for-woocommerce' )
		);
		$schedules['minutely'] = array(
			'interval' => 60,
			'display' => __( 'Once a Minute', 'product-xml-feeds-for-woocommerce' )
		);
		return $schedules;
	}

	/**
	 * admin_notice__success.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function admin_notice__success() {
		echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Products XML file created successfully.', 'product-xml-feeds-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * admin_notice__error.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function admin_notice__error() {
		echo '<div class="notice notice-error"><p>' . __( 'An error has occurred while creating products XML file.', 'product-xml-feeds-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * alg_create_products_xml.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 * @todo    [dev] with wp_safe_redirect there is no notice displayed
	 */
	function alg_create_products_xml() {
		if ( isset( $_GET['alg_create_products_xml'] ) ) {
			$file_num = $_GET['alg_create_products_xml'];
			$result = $this->create_products_xml( $file_num );
			add_action( 'admin_notices', array( $this, ( ( false !== $result ) ? 'admin_notice__success' : 'admin_notice__error' ) ) );
			if ( false !== $result ) {
				update_option( 'alg_products_time_file_created_' . $file_num, current_time( 'timestamp' ) );
			}
			wp_safe_redirect( remove_query_arg( 'alg_create_products_xml' ) );
			exit;
		}
	}

	/**
	 * create_products_xml_cron.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function create_products_xml_cron( $interval, $file_num ) {
		$result = $this->create_products_xml( $file_num );
		if ( false !== $result ) {
			update_option( 'alg_products_time_file_created_' . $file_num, current_time( 'timestamp' ) );
		}
		die();
	}

	/**
	 * get_default_template.
	 *
	 * @version 1.5.1
	 * @since   1.4.7
	 */
	function get_default_template( $part ) {
		switch ( $part ) {
			case 'header':
				return '<?xml version = "1.0" encoding = "utf-8" ?>' . PHP_EOL .
					'<root>' . PHP_EOL .
						'<time>[alg_current_datetime]</time>' . PHP_EOL .
						'<title><![CDATA[ Products Feed ]]></title>' . PHP_EOL;
			case 'item':
				return '<item>' . PHP_EOL .
					"\t" . '<name>[alg_product_name cdata="yes"]</name>' . PHP_EOL .
					"\t" . '<short_description>[alg_product_short_description cdata="yes"]</short_description>' . PHP_EOL .
					"\t" . '<link>[alg_product_url]</link>' . PHP_EOL .
					"\t" . '<price>[alg_product_price]</price>' . PHP_EOL .
					"\t" . '<currency>[alg_shop_currency]</currency>' . PHP_EOL .
					"\t" . '<image_url>[alg_product_image_url image_size="full"]</image_url>' . PHP_EOL .
					"\t" . '[alg_product_gallery_image_url image_nr="1" before="<image_url_1>" after="</image_url_1>"]' . PHP_EOL .
					"\t" . '<sku>[alg_product_sku]</sku>' . PHP_EOL .
					"\t" . '<stock_quantity>[alg_product_stock_quantity]</stock_quantity>' . PHP_EOL .
					"\t" . '<categories>[alg_product_categories]</categories>' . PHP_EOL .
					"\t" . '<tags>[alg_product_tags]</tags>' . PHP_EOL .
					"\t" . '<total_sales>[alg_product_meta name="total_sales"]</total_sales>' . PHP_EOL .
				'</item>' . PHP_EOL;
			case 'footer':
				return '</root>';
		}
	}

	/**
	 * create_products_xml.
	 *
	 * @version 1.7.2
	 * @since   1.0.0
	 * @todo    [fix] `$query_post_type`: fix filtering by product/category/tag/custom taxonomy when *including variations* for `products_and_variations`
	 * @todo    [dev] check attribute: for global attributes we can use custom taxonomy filtering instead - check if we can do the same for local attributes?
	 * @todo    [dev] recheck `WC()->query->get_meta_query();`
	 * @todo    [dev] use `wc_get_products()` (instead of `WP_Query`)
	 * @todo    [dev] str_replace( '&', '&amp;', html_entity_decode( ... ) )
	 * @todo    [feature] condition: `is_visible`
	 * @todo    [feature] condition: `is_purchasable`
	 * @todo    [feature] condition: `is_X` (https://docs.woocommerce.com/wc-apidocs/class-WC_Product.html)
	 * @todo    [feature] condition: custom `meta_query`
	 * @todo    [feature] condition: stock <>= X
	 */
	function create_products_xml( $file_num ) {
		// Memory limit
		if ( 0 != ( $php_memory_limit = get_option( 'alg_products_xml_php_memory_limit', 0 ) ) ) {
			ini_set( 'memory_limit', $php_memory_limit . 'M' );
		}
		// Time limit (will be used in loop)
		$php_time_limit = get_option( 'alg_products_xml_php_time_limit', -1 );
		// Switch language (WPML)
		$current_lang = '';
		if ( '' != ( $xml_lang = get_option( 'alg_products_xml_lang_' . $file_num, '' ) ) ) {
			global $sitepress;
			if ( $sitepress && is_object( $sitepress ) ) {
				$current_lang = apply_filters( 'wpml_current_language', NULL );
				if ( $current_lang == $xml_lang ) {
					$current_lang = '';
				} else {
					if ( method_exists( $sitepress, 'switch_lang' ) ) {
						$sitepress->switch_lang( $xml_lang );
					} else {
						$current_lang = '';
					}
				}
			}
		}
		// Get options
		$xml_header_template        = get_option( 'alg_products_xml_header_'          . $file_num, $this->get_default_template( 'header' ) );
		$xml_footer_template        = get_option( 'alg_products_xml_footer_'          . $file_num, $this->get_default_template( 'footer' ) );
		$xml_item_template          = get_option( 'alg_products_xml_item_'            . $file_num, $this->get_default_template( 'item' ) );
		$sorting_orderby            = get_option( 'alg_products_xml_orderby_'         . $file_num, 'date' );
		$sorting_order              = get_option( 'alg_products_xml_order_'           . $file_num, 'DESC' );
		$products_in_ids            = get_option( 'alg_products_xml_products_incl_'   . $file_num, '' );
		$products_ex_ids            = get_option( 'alg_products_xml_products_excl_'   . $file_num, '' );
		$products_cats_in_ids       = get_option( 'alg_products_xml_cats_incl_'       . $file_num, '' );
		$products_cats_ex_ids       = get_option( 'alg_products_xml_cats_excl_'       . $file_num, '' );
		$products_tags_in_ids       = get_option( 'alg_products_xml_tags_incl_'       . $file_num, '' );
		$products_tags_ex_ids       = get_option( 'alg_products_xml_tags_excl_'       . $file_num, '' );
		$products_scope             = get_option( 'alg_products_xml_scope_'           . $file_num, 'all' );
		$products_variable          = get_option( 'alg_products_xml_variable_'        . $file_num, 'variable_only' );
		$offset                     = get_option( 'alg_products_xml_offset_'          . $file_num, 0 );
		$total_products             = get_option( 'alg_products_xml_total_products_'  . $file_num, 0 );
		$query_post_type            = 'products_only';
		$products_stock_status      = apply_filters( 'alg_wc_product_xml_feeds_values', array(), 'stock_status', $file_num );
		$min_price                  = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'min_price', $file_num );
		$max_price                  = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'max_price', $file_num );
		$catalog_visibility         = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'catalog_visibility', $file_num );
		$custom_taxonomy_in         = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'custom_taxonomy_in', $file_num );
		$custom_taxonomy_in_slugs   = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'custom_taxonomy_in_slugs', $file_num );
		$attribute_in               = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'attribute_in', $file_num );
		$attribute_in_values        = apply_filters( 'alg_wc_product_xml_feeds_values', '', 'attribute_in_values', $file_num );

		// Handle "raw" input
		if ( 'no' === get_option( 'alg_products_xml_raw_input', 'yes' ) ) {
			$xml_header_template    = str_replace( array( '{', '}' ), array( '<', '>' ), $xml_header_template );
			$xml_footer_template    = str_replace( array( '{', '}' ), array( '<', '>' ), $xml_footer_template );
			$xml_item_template      = str_replace( array( '{', '}' ), array( '<', '>' ), $xml_item_template );
		}

		// Get products and feed
		$xml_items       = '';
		$block_size      = get_option( 'alg_products_xml_query_block_size', 512 );
		$_total_products = 0;
		while ( true ) {
			// Time limit
			if ( -1 != $php_time_limit ) {
				set_time_limit( $php_time_limit );
			}
			// Args
			$args = array(
				'post_type'      => ( 'variable_only' === $products_variable || 'products_only' === $query_post_type ? 'product' : array( 'product', 'product_variation' ) ),
				'post_status'    => 'publish',
				'posts_per_page' => $block_size,
				'orderby'        => $sorting_orderby,
				'order'          => $sorting_order,
				'offset'         => $offset,
			);
			if ( 'all' != $products_scope ) {
				$args['meta_query'] = WC()->query->get_meta_query();
				switch ( $products_scope ) {
					case 'sale_only':
						$args['post__in']     = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
						break;
					case 'not_sale_only':
						$args['post__not_in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
						break;
					case 'featured_only':
						$args['post__in']     = array_merge( array( 0 ), wc_get_featured_product_ids() );
						break;
					case 'not_featured_only':
						$args['post__not_in'] = array_merge( array( 0 ), wc_get_featured_product_ids() );
						break;
				}
			}
			if ( ! empty( $products_stock_status ) ) {
				if ( ! isset( $args['meta_query'] ) ) {
					$args['meta_query'] = array();
				} else {
					$args['meta_query']['relation'] = 'AND';
				}
				$args['meta_query'][] = array(
					'key'     => '_stock_status',
					'value'   => $products_stock_status,
					'compare' => 'IN',
				);
			}
			if ( ! empty( $products_in_ids ) ) {
				$args['post__in'] = $products_in_ids;
			}
			if ( ! empty( $products_ex_ids ) ) {
				$args['post__not_in'] = $products_ex_ids;
			}
			if ( ! empty( $products_cats_in_ids ) ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}
				$args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $products_cats_in_ids,
					'operator' => 'IN',
				);
			}
			if ( ! empty( $products_cats_ex_ids ) ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}
				$args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $products_cats_ex_ids,
					'operator' => 'NOT IN',
				);
			}
			if ( ! empty( $products_tags_in_ids ) ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}
				$args['tax_query'][] = array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => $products_tags_in_ids,
					'operator' => 'IN',
				);
			}
			if ( ! empty( $products_tags_ex_ids ) ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}
				$args['tax_query'][] = array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => $products_tags_ex_ids,
					'operator' => 'NOT IN',
				);
			}
			if ( ! empty( $custom_taxonomy_in ) && ! empty( $custom_taxonomy_in_slugs ) ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = array();
				}
				$args['tax_query'][] = array(
					'taxonomy' => $custom_taxonomy_in,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', $custom_taxonomy_in_slugs ) ),
					'operator' => 'IN',
				);
			}
			$loop = new WP_Query( $args );
			if ( ! $loop->have_posts() ) {
				break;
			}
			while ( $loop->have_posts() ) {
				$loop->the_post();
				// Additional product filtering
				$do_add = true;
				if (
					( 'variations_only' === $products_variable ) ||
					( '' !== $min_price || '' !== $max_price ) ||
					( ! empty( $catalog_visibility ) ) ||
					( '' != $attribute_in && '' != $attribute_in_values )
				) {
					$_product = wc_get_product( get_the_ID() );
					if ( '' !== $min_price || '' !== $max_price ) {
						$_price = $_product->get_price();
					}
					// Filter
					if (
						( 'variations_only' === $products_variable && $_product->is_type( 'variable' ) ) ||
						( ( '' !== $min_price && $_price < $min_price ) || ( '' !== $max_price && $_price > $max_price ) ) ||
						( ! empty( $catalog_visibility ) && ! in_array( $_product->get_catalog_visibility(), $catalog_visibility ) ) ||
						( '' != $attribute_in && '' != $attribute_in_values && ! in_array( $_product->get_attribute( $attribute_in ), $attribute_in_values ) )
					) {
						$do_add = false;
					}
				}
				if ( $do_add ) {
					// Add product to XML feed
					$xml_items .= str_replace( '&', '&amp;', html_entity_decode( do_shortcode( $xml_item_template ) ) );
					$_total_products++;
				}
				// Variations
				if ( 'variable_only' != $products_variable && 'products_only' === $query_post_type ) {
					$_product = wc_get_product( get_the_ID() );
					if ( $_product->is_type( 'variable' ) ) {
						global $post;
						foreach ( $_product->get_children() as $child_id ) {
							$post = get_post( $child_id );
							setup_postdata( $post );
							// Check variation's stock status
							if (
								! empty( $products_stock_status ) &&
								'yes' === get_post_meta( $child_id, '_manage_stock', true ) &&
								! in_array( get_post_meta( $child_id, '_stock_status', true ), $products_stock_status )
							) {
								continue;
							}
							// Check attribute
							if (
								'' != $attribute_in && '' != $attribute_in_values && ( $variation_product = wc_get_product( $child_id ) ) &&
								! in_array( $variation_product->get_attribute( $attribute_in ), $attribute_in_values )
							) {
								continue;
							}
							// Add variation product to XML feed
							$xml_items .= str_replace( '&', '&amp;', html_entity_decode( do_shortcode( $xml_item_template ) ) );
							$_total_products++;
						}
						wp_reset_postdata();
					}
				}
				if ( 0 != $total_products && $_total_products >= $total_products ) {
					break;
				}
			}
			$offset += $block_size;
			if ( 0 != $total_products && $_total_products >= $total_products ) {
				break;
			}
		}
		wp_reset_postdata();

		// Switch back language (WPML)
		if ( '' != $current_lang ) {
			$sitepress->switch_lang( $current_lang );
		}
		// Create XML feed file
		return file_put_contents(
			ABSPATH . get_option( 'alg_products_xml_file_path_' . $file_num, ( ( 1 == $file_num ) ? 'products.xml' : 'products_' . $file_num . '.xml' ) ),
			do_shortcode( $xml_header_template ) . $xml_items . do_shortcode( $xml_footer_template )
		);
	}

}

endif;

return new Alg_WC_Product_XML_Feeds_Core();
