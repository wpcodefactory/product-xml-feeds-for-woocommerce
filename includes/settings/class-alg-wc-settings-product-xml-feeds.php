<?php
/**
 * Product XML Feeds for WooCommerce - Settings
 *
 * @version 3.0.0
 * @since   1.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_Settings_Product_XML_Feeds' ) ) :

/**
 * WC_Settings_Page.
 *
 * @version 3.0.0
 * @since   3.0.0
 */
if ( ! class_exists( 'WC_Settings_Page' ) ) {
	include_once WC()->plugin_path() . '/includes/admin/settings/class-wc-settings-page.php';
}

class Alg_WC_Settings_Product_XML_Feeds extends WC_Settings_Page {

	/**
	 * Constructor.
	 *
	 * @version 2.9.2
	 * @since   1.0.0
	 */
	function __construct() {

		$this->id    = 'alg_wc_product_xml_feeds';
		$this->label = __( 'Product XML Feeds', 'product-xml-feeds-for-woocommerce' );
		parent::__construct();

		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_unsanitize_option' ), PHP_INT_MAX, 3 );

		// Sections
		require_once( 'class-alg-wc-product-xml-feeds-settings-section.php' );
		require_once( 'class-alg-wc-product-xml-feeds-settings-general.php' );
		require_once( 'class-alg-wc-product-xml-feeds-settings-feed.php' );
		$total_number = apply_filters( 'alg_wc_product_xml_feeds_values', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			new Alg_WC_Product_XML_Feeds_Settings_Feed( $i );
		}

	}

	/**
	 * maybe_unsanitize_option.
	 *
	 * @version 1.7.2
	 * @since   1.4.0
	 */
	function maybe_unsanitize_option( $value, $option, $raw_value ) {
		return (
			(
				! empty( $option['alg_wc_pxf_raw'] ) &&
				'yes' === get_option( 'alg_products_xml_raw_input', 'yes' )
			) ?
			$raw_value :
			$value
		);
	}

	/**
	 * get_settings.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function get_settings() {
		global $current_section;
		return array_merge(
			apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ),
			array(
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
			)
		);
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
	 * @version 2.9.2
	 * @since   1.4.2
	 */
	function admin_notice_settings_reset() {
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' .
			esc_html__( 'Your settings have been reset.', 'product-xml-feeds-for-woocommerce' ) .
		'</strong></p></div>';
	}

	/**
	 * Save settings.
	 *
	 * @version 1.4.0
	 * @since   1.3.0
	 *
	 * @todo    (fix) with the `wp_safe_redirect()` there are no admin notices displayed
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
