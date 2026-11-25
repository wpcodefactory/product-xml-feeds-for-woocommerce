<?php
/**
 * Product XML Feeds for WooCommerce - Classic settings screen clean-up (4.0).
 *
 * The classic screens (WooCommerce > Settings > Product XML Feeds) keep every option, key and saved value. This only
 * changes what is shown:
 * - Free: Pro controls that free cannot use (disabled or read-only fields, "Get Pro" notes) are not printed
 *   (WordPress.org guideline 5). WooCommerce saves only the fields it shows, so stored values stay as they are.
 * - Free and Pro: "Remove XML plugin branding" is gone, because feeds have no credit comment since 3.1.4.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'alg_wc_pxf_classic_settings' ) ) {
	/**
	 * alg_wc_pxf_classic_settings.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array  $settings Settings of the current section.
	 * @param   string $section  Section id.
	 * @return  array
	 */
	function alg_wc_pxf_classic_settings( $settings, $section = '' ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		$is_pro = Alg_WC_PXF::is_pro();
		$out    = array();
		foreach ( $settings as $field ) {
			$id = ( isset( $field['id'] ) ? (string) $field['id'] : '' );

			// Since 3.1.4 feeds have no credit comment: the branding switch does nothing.
			if ( 0 === strpos( $id, 'alg_products_xml_enabled_branding_' ) ) {
				continue;
			}

			if ( ! $is_pro ) {
				$attrs = ( isset( $field['custom_attributes'] ) && is_array( $field['custom_attributes'] ) ? $field['custom_attributes'] : array() );

				// The update period is fixed to weekly in free: show the "Create now" button and the next update as text.
				if ( 0 === strpos( $id, 'alg_create_products_xml_period_' ) ) {
					$desc = alg_wc_pxf_classic_strip_pro_note( isset( $field['desc'] ) ? $field['desc'] : '' );
					$out[] = array(
						'type' => 'info',
						'id'   => $id . '_info',
						'text' => '<strong>' . esc_html__( 'Update period', 'product-xml-feeds-for-woocommerce' ) . ':</strong> ' .
							esc_html__( 'once a week.', 'product-xml-feeds-for-woocommerce' ) . ' ' . $desc,
					);
					continue;
				}

				// Locked Pro controls and the fixed "Total XML files (feeds)" number.
				if ( isset( $attrs['disabled'] ) || isset( $attrs['readonly'] ) || 'alg_products_xml_total_files' === $id ) {
					continue;
				}

				if ( isset( $field['desc'] ) && is_string( $field['desc'] ) ) {
					$field['desc'] = alg_wc_pxf_classic_strip_pro_note( $field['desc'] );
				}
			}
			$out[] = $field;
		}
		return alg_wc_pxf_classic_drop_empty_sections( $out );
	}
}

if ( ! function_exists( 'alg_wc_pxf_classic_strip_pro_note' ) ) {
	/**
	 * Removes the English-only "Get ... Pro to ..." notes from a description.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $desc Description HTML.
	 * @return  string
	 */
	function alg_wc_pxf_classic_strip_pro_note( $desc ) {
		return (string) preg_replace( '#<p style="padding: 10px; background-color: white;">Get <a [^>]*>Product XML Feeds for WooCommerce Pro</a>[^<]*</p>#', '', (string) $desc );
	}
}

if ( ! function_exists( 'alg_wc_pxf_classic_drop_empty_sections' ) ) {
	/**
	 * Drops a title and its sectionend when nothing is left between them.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $settings Settings.
	 * @return  array
	 */
	function alg_wc_pxf_classic_drop_empty_sections( $settings ) {
		$out   = array();
		$count = count( $settings );
		for ( $i = 0; $i < $count; $i++ ) {
			$type = ( isset( $settings[ $i ]['type'] ) ? $settings[ $i ]['type'] : '' );
			$next = ( isset( $settings[ $i + 1 ]['type'] ) ? $settings[ $i + 1 ]['type'] : '' );
			if ( 'title' === $type && 'sectionend' === $next && ! Alg_WC_PXF::is_pro() ) {
				$i++;
				continue;
			}
			$out[] = $settings[ $i ];
		}
		return $out;
	}
}

if ( ! function_exists( 'alg_wc_pxf_classic_register' ) ) {
	/**
	 * Hooks the clean-up after the classic sections added their own settings filters (they use PHP_INT_MAX too, so the
	 * clean-up is added later to run after them). General is section '', feeds are 'feed_N'.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   array $pages WooCommerce settings pages.
	 * @return  array
	 */
	function alg_wc_pxf_classic_register( $pages ) {
		static $done = false;
		if ( ! $done ) {
			$done  = true;
			$total = (int) apply_filters( 'alg_wc_product_xml_feeds_values', 1, 'total_number' );
			add_filter( 'woocommerce_get_settings_alg_wc_product_xml_feeds_', 'alg_wc_pxf_classic_settings', PHP_INT_MAX );
			for ( $i = 1; $i <= max( 1, $total ); $i++ ) {
				add_filter( 'woocommerce_get_settings_alg_wc_product_xml_feeds_feed_' . $i, 'alg_wc_pxf_classic_settings', PHP_INT_MAX );
			}
		}
		return $pages;
	}
}

add_filter( 'woocommerce_get_settings_pages', 'alg_wc_pxf_classic_register', PHP_INT_MAX );
