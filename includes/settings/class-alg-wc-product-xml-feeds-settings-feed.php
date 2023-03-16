<?php
/**
 * Product XML Feeds for WooCommerce - Feed Section Settings
 *
 * @version 1.7.0
 * @since   1.1.0
 * @author  WPWhale
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Product_XML_Feeds_Settings_Feed' ) ) :

class Alg_WC_Product_XML_Feeds_Settings_Feed extends Alg_WC_Product_XML_Feeds_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.4.0
	 * @since   1.1.0
	 */
	function __construct( $feed_num ) {
		$this->id       = 'feed_' . $feed_num;
		$this->desc     = get_option( 'alg_products_xml_feed_title_' . $feed_num, sprintf( __( 'XML Feed #%d', 'product-xml-feeds-for-woocommerce' ), $feed_num ) );
		$this->feed_num = $feed_num;
		parent::__construct();
	}

	/**
	 * get_products.
	 *
	 * @version 1.4.5
	 * @since   1.0.0
	 */
	function get_products() {
		$products_options = array();
		$offset     = 0;
		$block_size = 512;
		while( true ) {
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $block_size,
				'offset'         => $offset,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			);
			$loop = new WP_Query( $args );
			if ( ! $loop->have_posts() ) {
				break;
			}
			foreach ( $loop->posts as $post_id ) {
				$sku = get_post_meta($post_id, '_sku', true);
				$products_options[ $post_id ] = get_the_title( $post_id ) . ' (#' . $post_id . ') (SKU# ' . $sku .')' ;
			}
			$offset += $block_size;
		}
		return $products_options;
	}

	/**
	 * get_settings.
	 *
	 * @version 1.7.0
	 * @since   1.1.0
	 * @todo    [dev] (maybe) move "Sorting" options to a separate subsection
	 * @todo    [feature] Update period: "Manual only"
	 * @todo    [feature] (maybe) `alg_products_xml_orderby_`: `parent`, `menu_order`, `post__in`
	 */
	function get_settings() {

		// Prepare Products Options
		$products_options = $this->get_products();

		// Prepare Categories Options
		$product_cats_options = array();
		$product_cats = get_terms( 'product_cat', 'orderby=name&hide_empty=0' );
		if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ){
			foreach ( $product_cats as $product_cat ) {
				$product_cats_options[ $product_cat->term_id ] = $product_cat->name;
			}
		}

		// Prepare Tags Options
		$product_tags_options = array();
		$product_tags = get_terms( 'product_tag', 'orderby=name&hide_empty=0' );
		if ( ! empty( $product_tags ) && ! is_wp_error( $product_tags ) ){
			foreach ( $product_tags as $product_tag ) {
				$product_tags_options[ $product_tag->term_id ] = $product_tag->name;
			}
		}
		
		// Prepare Type Options
		$product_type_options = array();
		$product_default_options = array();
		$product_types = get_terms( 'product_type', 'orderby=name&hide_empty=0' );
		
		if ( ! empty( $product_types ) && ! is_wp_error( $product_types ) ){
			foreach ( $product_types as $product_type ) {
				/*if(in_array($product_type->slug, array('grouped', 'simple', 'variable'))){*/
					$product_type_options[ $product_type->term_id ] = ucfirst($product_type->name);
					$product_default_options[] = (string) $product_type->term_id;
				/*}*/
			}
		}

		// Prepare WPML languages
		$langs_options = array( '' => __( 'Default (current)', 'product-xml-feeds-for-woocommerce' ) );
		if ( function_exists( 'icl_get_languages' ) ) {
			$langs = icl_get_languages( 'skip_missing=0&orderby=KEY&order=DIR&link_empty_to=str' );
			if ( ! empty( $langs ) ) {
				foreach ( $langs as $lang ) {
					if ( ! empty( $lang['language_code'] ) ) {
						$lang_code = $lang['language_code'];
						$langs_options[ $lang_code ] = ( ! empty( $lang['translated_name'] ) ? $lang['translated_name'] . ' (' . $lang_code . ')' : $lang_code );
					}
				}
			}
		}

		// Settings
		$products_xml_cron_desc = '';
		if ( 'yes' === get_option( 'alg_wc_product_xml_feeds_enabled', 'yes' ) ) {
			$products_xml_cron_desc .= '<a class="button" href="' . add_query_arg( 'alg_create_products_xml', $this->feed_num ) . '" title="' .
				__( 'Don\'t forget to save settings if you\'ve made any changes.', 'product-xml-feeds-for-woocommerce' ) . '">' .
					__( 'Create now', 'product-xml-feeds-for-woocommerce' ) . '</a>';
			if ( '' != get_option( 'alg_create_products_xml_cron_time_' . $this->feed_num, '' ) ) {
				$scheduled_time_diff = get_option( 'alg_create_products_xml_cron_time_' . $this->feed_num, '' ) - time();
				if ( $scheduled_time_diff > 60 ) {
					$products_xml_cron_desc .= ' <em>' . sprintf( __( '%s till next update.', 'product-xml-feeds-for-woocommerce' ), human_time_diff( 0, $scheduled_time_diff ) ) . '</em>';
				} elseif ( $scheduled_time_diff > 0 ) {
					$products_xml_cron_desc .= ' <em>' . sprintf( __( '%s seconds till next update.', 'product-xml-feeds-for-woocommerce' ), $scheduled_time_diff ) . '</em>';
				}
			}
		}
		$products_time_file_created_desc = '';
		if ( '' != get_option( 'alg_products_time_file_created_' . $this->feed_num, '' ) ) {
			$products_time_file_created_desc = sprintf(
				'<em>' . __( 'Recent file was created on %s', 'product-xml-feeds-for-woocommerce' ) . '</em>',
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_option( 'alg_products_time_file_created_' . $this->feed_num, '' ) )
			);
		}
		$default_file_name = ( ( 1 == $this->feed_num ) ? 'products.xml' : 'products_' . $this->feed_num . '.xml' );
		$default_xml_file_name = ( ( 1 == $this->feed_num ) ? 'products.txt' : 'products_' . $this->feed_num . '.txt' );
		$use_home_url = get_option( 'alg_products_xml_use_home_url_' . $this->feed_num, 'no' );

		if( 'yes' == $use_home_url )
		{
			$feed_url = home_url() . '/' . get_option( 'alg_products_xml_file_path_' . $this->feed_num, $default_file_name );
		}
		else
		{
			$feed_url = site_url() . '/' . get_option( 'alg_products_xml_file_path_' . $this->feed_num, $default_file_name );
		}
		
		$home_url = home_url();
		$site_url = site_url();
		$feed_path = ABSPATH;
		if($home_url!=$site_url)
		{
			$compare = strcmp($site_url,$home_url);

			if($compare > 0)
			{
				$extra = str_replace($home_url, '', $site_url);
				$extra = ltrim($extra, '/');
				$extra = rtrim($extra, '/');
				$newPath = str_replace(DIRECTORY_SEPARATOR . $extra, '', ABSPATH);
				$newPath = ltrim($newPath, '/');
				$newPath = ltrim($newPath, '\\');
				$newPath = rtrim($newPath, '\\');
				$feed_path = rtrim($newPath, '/') . DIRECTORY_SEPARATOR;
			}
			else if($compare < 0)
			{
				$extra = str_replace($site_url, '', $home_url);
				$extra = ltrim($extra, '/');
				$extra = rtrim($extra, '/');
				$feed_path = rtrim(ABSPATH, '/') . '/' . $extra . '/';
			}
		}
		if(!is_writable($feed_path)){
			$is_writable = '<br><br><em style="color:red">  Plugin doesn\'t have access to '. $feed_path .' </em>';
		}
		else
		{
			$is_writable = '';
		}

		$settings = array(
			array(
				'title'    => __( 'XML Feed', 'product-xml-feeds-for-woocommerce' ) . ' #' . $this->feed_num,
				'type'     => 'title',
				'desc'     => $products_time_file_created_desc,
				'id'       => 'alg_products_xml_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Enable/Disable', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'XML Feed', 'product-xml-feeds-for-woocommerce' ) . ' #' . $this->feed_num . '</strong>',
				'id'       => 'alg_products_xml_enabled_' . $this->feed_num,
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Admin title', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_feed_title_' . $this->feed_num,
				'default'  => sprintf( __( 'XML Feed #%d', 'product-xml-feeds-for-woocommerce' ), $this->feed_num ),
				'type'     => 'text',
				'css'      => 'width:100%;',
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Template Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_products_xml_template_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'XML header', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'Please visit <a href="%s" target="_blank">Product XML Feeds for WooCommerce page</a> to check all available shortcodes.', 'product-xml-feeds-for-woocommerce' ), 'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ),
				'id'       => 'alg_products_xml_header_' . $this->feed_num,
				'default'  => alg_wc_product_xml_feeds()->core->get_default_template( 'header' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:150px;',
				'alg_wc_pxf_raw' => true,
			),
			array(
				'title'    => __( 'XML item', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'Please visit <a href="%s" target="_blank">Product XML Feeds for WooCommerce page</a> to check all available shortcodes.', 'product-xml-feeds-for-woocommerce' ), 'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ),
				'id'       => 'alg_products_xml_item_' . $this->feed_num,
				'default'  => alg_wc_product_xml_feeds()->core->get_default_template( 'item' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:300px;',
				'alg_wc_pxf_raw' => true,
			),
			array(
				'title'    => __( 'Variation XML item', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'Please visit <a href="%s" target="_blank">Product XML Feeds for WooCommerce page</a> to check all available shortcodes. It will works when "Variable products" option selected to be "Both variable and variations products"', 'product-xml-feeds-for-woocommerce' ), 'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ),
				'id'       => 'alg_products_xml_variation_item_' . $this->feed_num,
				'default'  => alg_wc_product_xml_feeds()->core->get_default_template( 'variation_item' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:300px;',
				'alg_wc_pxf_raw' => true,
			),
			array(
				'title'    => __( 'XML footer', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'Please visit <a href="%s" target="_blank">Product XML Feeds for WooCommerce page</a> to check all available shortcodes.', 'product-xml-feeds-for-woocommerce' ), 'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ),
				'id'       => 'alg_products_xml_footer_' . $this->feed_num,
				'default'  => alg_wc_product_xml_feeds()->core->get_default_template( 'footer' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:150px;',
				'alg_wc_pxf_raw' => true,
			),
			
			array(
				'title'    => __( 'Hide XML tags if empty', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<code>' . __( 'Eg: product, stock etc with comma separated.', 'product-xml-feeds-for-woocommerce' ) . '</code>',
				'id'       => 'alg_products_xml_tags_if_empty_' . $this->feed_num,
				'default'  => '',
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:150px;',
				'alg_wc_pxf_raw' => true,
			),
			
			array(
				'title'    => __( 'Create text feed', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable', 'product-xml-feeds-for-woocommerce' )  .  '</strong>',
				'id'       => 'alg_products_xml_create_text_feed_' . $this->feed_num,
				'default'  => 'no',
				'type'     => 'checkbox',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			array(
				'title'    => __( 'Text item', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'Please visit <a href="%s" target="_blank">Product XML Feeds for WooCommerce page</a> to check all available shortcodes.', 'product-xml-feeds-for-woocommerce' ), 'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ),
				'id'       => 'alg_products_xml_text_item_' . $this->feed_num,
				'default'  => alg_wc_product_xml_feeds()->core->get_default_template( 'text_item' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;min-height:300px;',
				'alg_wc_pxf_raw' => true,
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_template_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'General Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_products_xml_general_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'XML file path and name', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Path on server:', 'product-xml-feeds-for-woocommerce' ) . ' ' . ABSPATH . get_option( 'alg_products_xml_file_path_' . $this->feed_num, $default_file_name ),
				'desc'     => __( 'URL:', 'product-xml-feeds-for-woocommerce' ) . ' ' . '<a target="_blank" href="' . site_url() . '/' . get_option( 'alg_products_xml_file_path_' . $this->feed_num, $default_file_name ) . '">' . site_url() . '/' . get_option( 'alg_products_xml_file_path_' . $this->feed_num, $default_file_name ) . '</a>',
				'id'       => 'alg_products_xml_file_path_' . $this->feed_num,
				'default'  => $default_file_name,
				'type'     => 'text',
				'css'      => 'width:100%;',
			),
			array(
				'title'    => __( 'Text file path and name', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Path on server:', 'product-xml-feeds-for-woocommerce' ) . ' ' . ABSPATH . get_option( 'alg_products_xml_text_file_path_' . $this->feed_num, $default_xml_file_name ),
				'desc'     => __( 'URL:', 'product-xml-feeds-for-woocommerce' ) . ' ' . '<a target="_blank" href="' . site_url() . '/' . get_option( 'alg_products_xml_text_file_path_' . $this->feed_num, $default_xml_file_name ) . '">' . site_url() . '/' . get_option( 'alg_products_xml_text_file_path_' . $this->feed_num, $default_xml_file_name ) . '</a>',
				'id'       => 'alg_products_xml_text_file_path_' . $this->feed_num,
				'default'  => $default_xml_file_name,
				'type'     => 'text',
				'css'      => 'width:100%;',
			),
			array(
				'title'    => __( 'Use Site Address (HOME_URL)', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable', 'product-xml-feeds-for-woocommerce' )  .  '  '  .  $is_writable . '</strong>',
				'id'       => 'alg_products_xml_use_home_url_' . $this->feed_num,
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Update period', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => $products_xml_cron_desc . apply_filters( 'alg_wc_product_xml_feeds_settings', sprintf(
					'<p style="padding: 10px; background-color: white;">Get <a href="%s" target="_blank">Product XML Feeds for WooCommerce Pro</a> to change the update period.</p>',
						'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ) ),
				'id'       => 'alg_create_products_xml_period_' . $this->feed_num,
				'default'  => 'weekly',
				'type'     => 'select',
				'class'    => 'chosen_select',
				'options'  => array(
					'minutely'   => __( 'Update every minute', 'product-xml-feeds-for-woocommerce' ),
					'hourly'     => __( 'Update hourly', 'product-xml-feeds-for-woocommerce' ),
					'twicedaily' => __( 'Update twice daily', 'product-xml-feeds-for-woocommerce' ),
					'daily'      => __( 'Update daily', 'product-xml-feeds-for-woocommerce' ),
					'weekly'     => __( 'Update weekly', 'product-xml-feeds-for-woocommerce' ),
				),
				'desc_tip' => __( 'Possible update periods are: every minute, hourly, twice daily, daily and weekly.', 'product-xml-feeds-for-woocommerce' ),
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_general_options_end_' . $this->feed_num,
			),
			
			array(
				'title'    => __( 'Manual Cron Job Command', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'desc'     => '<strong>wget -qO- '.get_site_url().'/wp-admin/admin-ajax.php?action=generate_xml_external&alg_create_products_xml='.$this->feed_num.' >/dev/null 2>&1</strong>',
				'id'       => 'alg_products_xml_manual_cron_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Turn off WP schedule', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Turn off WP schedule of this plugin only.', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => __( 'Enable', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_turn_off_wp_schedule_' . $this->feed_num,
				'default'  => 'no',
				'type'     => 'checkbox',
			),
			
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_manual_cron_options_end_' . $this->feed_num,
			),
			array(
				'title'    => __( 'General Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_products_xml_general_options_' . $this->feed_num,
			),
		);
		if ( count( $langs_options ) > 1 || '' !== get_option( 'alg_products_xml_lang_' . $this->feed_num, '' ) ) {
			$settings = array_merge( $settings, array(
				array(
					'title'    => __( 'WPML language', 'product-xml-feeds-for-woocommerce' ),
					'id'       => 'alg_products_xml_lang_' . $this->feed_num,
					'default'  => '',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => $langs_options,
				),
			) );
		}
		$settings = array_merge( $settings, array(
			array(
				'title'    => __( 'Sorting: Order by', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_orderby_' . $this->feed_num,
				'default'  => 'date',
				'type'     => 'select',
				'class'    => 'chosen_select',
				'options'  => array(
					'ID'            => __( 'Product ID', 'product-xml-feeds-for-woocommerce' ),
					'title'         => __( 'Title', 'product-xml-feeds-for-woocommerce' ),
					'name'          => __( 'Slug', 'product-xml-feeds-for-woocommerce' ),
					'date'          => __( 'Date', 'product-xml-feeds-for-woocommerce' ),
					'modified'      => __( 'Last modified date', 'product-xml-feeds-for-woocommerce' ),
					'comment_count' => __( 'Number of comments', 'product-xml-feeds-for-woocommerce' ),
					'author'        => __( 'Author', 'product-xml-feeds-for-woocommerce' ),
					'rand'          => __( 'Random', 'product-xml-feeds-for-woocommerce' ),
					'none'          => __( 'None', 'product-xml-feeds-for-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'Sorting: Order', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_order_' . $this->feed_num,
				'default'  => 'DESC',
				'type'     => 'select',
				'class'    => 'chosen_select',
				'options'  => array(
					'ASC'        => __( 'Ascending', 'product-xml-feeds-for-woocommerce' ),
					'DESC'       => __( 'Descending', 'product-xml-feeds-for-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'Remove XML Plugin Branding', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => '<strong>Remove</strong>' . apply_filters( 'alg_wc_product_xml_feeds_settings', sprintf(
					'<p style="padding: 10px; background-color: white;">Get <a href="%s" target="_blank">Product XML Feeds for WooCommerce Pro</a> to remove plugin branding.</p>',
						'https://wpfactory.com/item/product-xml-feeds-woocommerce/' )),
				'id'       => 'alg_products_xml_enabled_branding_' . $this->feed_num,
				'default'  => 'no',
				'type'     => 'checkbox',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_general_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Products Filtering Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_products_xml_filtering_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Products to include', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To include selected products only, enter products here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_products_incl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $products_options,
			),
			array(
				'title'    => __( 'Products to exclude', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To exclude selected products, enter products here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_products_excl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $products_options,
			),
			array(
				'title'    => __( 'Categories to include', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To include products from selected categories only, enter categories here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_cats_incl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_cats_options,
			),
			array(
				'title'    => __( 'Categories to exclude', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To exclude products from selected categories, enter categories here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_cats_excl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_cats_options,
			),
			array(
				'title'    => __( 'Tags to include', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To include products from selected tags only, enter tags here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_tags_incl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_tags_options,
			),
			array(
				'title'    => __( 'Tags to exclude', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'To exclude products from selected tags, enter tags here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_tags_excl_' . $this->feed_num,
				'default'  => '',
				'class'    => 'chosen_select',
				'type'     => 'multiselect',
				'options'  => $product_tags_options,
			),
			array(
				'title'    => __( 'Product types to include', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Product types to include (multiselect): ', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all product types.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_product_type_include_' . $this->feed_num,
				'default'  => $product_default_options,
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'options'  => $product_type_options,
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			array(
				'title'    => __( 'Variable products', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_variable_' . $this->feed_num,
				'default'  => 'variable_only',
				'type'     => 'select',
				'class'    => 'chosen_select',
				'options'  => array(
					'variable_only'   => __( 'Variable product only', 'product-xml-feeds-for-woocommerce' ),
					'variations_only' => __( 'Variation products only', 'product-xml-feeds-for-woocommerce' ),
					'both'            => __( 'Both variable and variations products', 'product-xml-feeds-for-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'Products scope', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_scope_' . $this->feed_num,
				'default'  => 'all',
				'type'     => 'select',
				'class'    => 'chosen_select',
				'options'  => array(
					'all'               => __( 'All products', 'product-xml-feeds-for-woocommerce' ),
					'sale_only'         => __( 'Only products that are on sale', 'product-xml-feeds-for-woocommerce' ),
					'not_sale_only'     => __( 'Only products that are not on sale', 'product-xml-feeds-for-woocommerce' ),
					'featured_only'     => __( 'Only products that are featured', 'product-xml-feeds-for-woocommerce' ),
					'not_featured_only' => __( 'Only products that are not featured', 'product-xml-feeds-for-woocommerce' ),
				),
			),
			array(
				'title'    => __( 'Offset products', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Number of products to pass over.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_offset_' . $this->feed_num,
				'default'  => 0,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 0 ),
			),
			array(
				'title'    => __( 'Total products', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Set to zero to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_total_products_' . $this->feed_num,
				'default'  => 0,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 0 ),
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_filtering_options_' . $this->feed_num,
			),
			array(
				'title'    => __( 'Extra Products Filtering Options', 'product-xml-feeds-for-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_products_xml_extra_filtering_options_' . $this->feed_num,
				'desc'     => apply_filters( 'alg_wc_product_xml_feeds_settings', sprintf(
					'<p style="padding: 10px; background-color: white;">Get <a href="%s" target="_blank">Product XML Feeds for WooCommerce Pro</a> to use extra products filtering options.</p>',
						'https://wpfactory.com/item/product-xml-feeds-woocommerce/' ) ),
			),
			array(
				'title'    => __( 'Stock status', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Possible values (multiselect): In stock, Out of stock, On backorder.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_stock_status_' . $this->feed_num,
				'default'  => array(),
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'options'  => array(
					'instock'       => __( 'In stock', 'product-xml-feeds-for-woocommerce' ),
					'outofstock'    => __( 'Out of stock', 'product-xml-feeds-for-woocommerce' ),
					'onbackorder'   => __( 'On backorder', 'product-xml-feeds-for-woocommerce' ),
				),
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			array(
				'title'    => __( 'Min price', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Ignored if empty.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_min_price_' . $this->feed_num,
				'default'  => '',
				'type'     => 'number',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ), 'price' ),
			),
			array(
				'title'    => __( 'Max price', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'Ignored if empty.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_max_price_' . $this->feed_num,
				'default'  => '',
				'type'     => 'number',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ), 'price' ),
			),
			array(
				'title'    => __( 'Catalog visibility', 'product-xml-feeds-for-woocommerce' ),
				'desc_tip' => __( 'If empty, then all products will be included in the feed.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_catalog_visibility_' . $this->feed_num,
				'default'  => array(),
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'options'  => array(
					'visible' => __( 'Shop and search results (i.e. visible)', 'product-xml-feeds-for-woocommerce' ),
					'catalog' => __( 'Shop only', 'product-xml-feeds-for-woocommerce' ),
					'search'  => __( 'Search results only', 'product-xml-feeds-for-woocommerce' ),
					'hidden'  => __( 'Hidden', 'product-xml-feeds-for-woocommerce' ),
				),
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'disabled' => 'disabled' ) ),
			),
			array(
				'title'    => __( 'Custom taxonomy to include', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'E.g.: %s', 'product-xml-feeds-for-woocommerce' ), '<code>pwb-brand</code>' ),
				'desc_tip' => __( 'To include products from selected taxonomy only, enter taxonomy slug here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_custom_taxonomy_incl_' . $this->feed_num,
				'default'  => '',
				'type'     => 'text',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ) ),
			),
			array(
				'desc'     => __( 'Custom taxonomy slugs (comma separated)', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_custom_taxonomy_incl_slugs_' . $this->feed_num,
				'default'  => '',
				'type'     => 'text',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ) ),
			),
			array(
				'title'    => __( 'Attribute to include', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => sprintf( __( 'E.g.: %s', 'product-xml-feeds-for-woocommerce' ), '<code>color</code>' ),
				'desc_tip' => __( 'To include products from selected attribute only, enter attribute slug here.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_attribute_incl_' . $this->feed_num,
				'default'  => '',
				'type'     => 'text',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ) ),
			),
			array(
				'desc'     => __( 'Attribute values (comma separated)', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					sprintf( __( 'E.g.: %s', 'product-xml-feeds-for-woocommerce' ), '<code>Red,Green</code>' ),
				'id'       => 'alg_products_xml_attribute_incl_values_' . $this->feed_num,
				'default'  => '',
				'type'     => 'text',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ) ),
			),
			array(
				'title'    => __( 'Custom meta filter', 'product-xml-feeds-for-woocommerce' ),
				'desc'     => __( 'Meta key, compare, value (comma separated)', 'product-xml-feeds-for-woocommerce' ) . ' ' . sprintf( __( 'E.g.: %s', 'product-xml-feeds-for-woocommerce' ), '<code>meta_key, = , value</code>' ),
				'desc_tip' => __( 'To include products from selected custom meta value only, enter meta information here as per instruction.', 'product-xml-feeds-for-woocommerce' ) . ' ' .
					__( 'Leave blank to include all products.', 'product-xml-feeds-for-woocommerce' ),
				'id'       => 'alg_products_xml_custom_meta_incl_' . $this->feed_num,
				'default'  => '',
				'type'     => 'text',
				'custom_attributes' => apply_filters( 'alg_wc_product_xml_feeds_settings', array( 'readonly' => 'readonly' ) ),
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_products_xml_extra_filtering_options_' . $this->feed_num,
			),
		) );

		return $settings;
	}

}

endif;
