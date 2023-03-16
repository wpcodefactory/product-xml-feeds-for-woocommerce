<?php
/**
 * Product XML Feeds for WooCommerce - Settings
 *
 * @version 1.7.2
 * @since   1.0.0
 * @author  WPWhale
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Settings_Product_XML_Feeds' ) ) :

class Alg_WC_Settings_Product_XML_Feeds extends WC_Settings_Page {

	/**
	 * Constructor.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function __construct() {
		$this->id    = 'alg_wc_product_xml_feeds';
		$this->label = __( 'Product XML Feeds', 'product-xml-feeds-for-woocommerce' );
		parent::__construct();
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_unsanitize_option' ), PHP_INT_MAX, 3 );
	}

	/**
	 * maybe_unsanitize_option.
	 *
	 * @version 1.7.2
	 * @since   1.4.0
	 */
	function maybe_unsanitize_option( $value, $option, $raw_value ) {
		return ( ! empty( $option['alg_wc_pxf_raw'] ) && 'yes' === get_option( 'alg_products_xml_raw_input', 'yes' ) ? $raw_value : $value );
	}

	/**
	 * get_settings.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function get_settings() {
		global $current_section;
		return array_merge( apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ), array(
			array(
				'title'     => __( 'Reset Settings', 'product-xml-feeds-for-woocommerce' ),
				'type'      => 'title',
				'id'        => $this->id . '_' . $current_section . '_reset_options',
			),
			array(
				'title'     => __( 'Reset section settings', 'product-xml-feeds-for-woocommerce' ),
				'desc'      => '<strong>' . __( 'Reset', 'product-xml-feeds-for-woocommerce' ) . '</strong>',
				'id'        => $this->id . '_' . $current_section . '_reset',
				'default'   => 'no',
				'type'      => 'checkbox',
			),
			array(
				'type'      => 'sectionend',
				'id'        => $this->id . '_' . $current_section . '_reset_options',
			),
		) );
	}

	/**
	 * maybe_reset_settings.
	 *
	 * @version 1.4.2
	 * @since   1.3.0
	 */
	function maybe_reset_settings() {
		global $current_section;
		if ( 'yes' === get_option( $this->id . '_' . $current_section . '_reset', 'no' ) ) {
			foreach ( $this->get_settings() as $value ) {
				if ( isset( $value['id'] ) ) {
					$id = explode( '[', $value['id'] );
					delete_option( $id[0] );
				}
			}
			add_action( 'admin_notices', array( $this, 'admin_notice_settings_reset' ) );
		}
	}

	/**
	 * admin_notice_settings_reset.
	 *
	 * @version 1.4.2
	 * @since   1.4.2
	 */
	function admin_notice_settings_reset() {
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
			__( 'Your settings have been reset.', 'product-xml-feeds-for-woocommerce' ) . '</strong></p></div>';
	}

	/**
	 * Save settings.
	 *
	 * @version 1.4.0
	 * @since   1.3.0
	 * @todo    [fix] with wp_safe_redirect there are no admin notices displayed
	 */
	function save() {
		parent::save();
		$this->maybe_reset_settings();
		if ( isset( $_GET['tab'] ) && 'alg_wc_product_xml_feeds' === $_GET['tab'] ) {
			wp_safe_redirect( add_query_arg( '', '' ) );
			exit;
		}
	}

}

endif;

return new Alg_WC_Settings_Product_XML_Feeds();
