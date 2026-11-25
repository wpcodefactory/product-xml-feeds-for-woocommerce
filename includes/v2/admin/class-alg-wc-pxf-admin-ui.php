<?php
/**
 * Product XML Feeds for WooCommerce - v2 admin UI helper (wrapper, top bar, page head, cards, chips, toggles, banners).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Admin_UI' ) ) :

class Alg_WC_PXF_Admin_UI {

	/**
	 * open: wrapper and top bar.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $view Current view.
	 */
	static function open( $view ) {
		echo '<div class="pxf-app" data-view="' . esc_attr( $view ) . '">';
		self::topbar( $view );
		echo '<main class="page" id="pxf-main">';
	}

	/**
	 * close.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 */
	static function close() {
		echo '</main><div class="toast-region" aria-live="polite" id="pxf-toasts"></div></div>';
	}

	/**
	 * topbar.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $view Current view.
	 */
	static function topbar( $view ) {
		$nav = array(
			'feeds'      => __( 'Feeds', 'product-xml-feeds-for-woocommerce' ),
			'products'   => __( 'Product check', 'product-xml-feeds-for-woocommerce' ),
			'categories' => __( 'Categories', 'product-xml-feeds-for-woocommerce' ),
			'ai'         => __( 'AI review', 'product-xml-feeds-for-woocommerce' ),
			'settings'   => __( 'Settings', 'product-xml-feeds-for-woocommerce' ),
		);
		if ( ! Alg_WC_PXF::is_pro() ) {
			$nav['pro'] = __( 'Pro features', 'product-xml-feeds-for-woocommerce' );
		}
		$current = in_array( $view, array( 'new', 'edit' ), true ) ? 'feeds' : $view;
		echo '<header class="topbar"><div class="left">';
		echo '<div class="brand"><span class="brand-mark" aria-hidden="true">PF</span>' . esc_html__( 'Product Feeds', 'product-xml-feeds-for-woocommerce' );
		if ( Alg_WC_PXF::is_pro() ) {
			echo ' <span class="edition">' . esc_html__( 'PRO', 'product-xml-feeds-for-woocommerce' ) . '</span>';
		}
		echo '</div><nav class="nav" aria-label="' . esc_attr__( 'Product Feeds', 'product-xml-feeds-for-woocommerce' ) . '">';
		foreach ( $nav as $id => $label ) {
			$on = ( $id === $current );
			printf(
				'<a href="%s"%s>%s</a>',
				esc_url( Alg_WC_PXF_Admin::url( $id ) ),
				$on ? ' class="on" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav></div><div class="top-right">';
		printf( '<a class="btn btn-text btn-sm" href="%s">%s</a>', esc_url( Alg_WC_PXF_Admin::classic_url() ), esc_html__( 'Classic feeds', 'product-xml-feeds-for-woocommerce' ) );
		$pill = apply_filters( 'alg_wc_pxf_admin_ai_pill', '' );
		if ( '' !== $pill ) {
			echo wp_kses_post( $pill );
		}
		echo '</div></header>';
	}

	/**
	 * page_head.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $eyebrow      Eyebrow (HTML allowed: links).
	 * @param   string $title        Title.
	 * @param   string $lead         Lead text (HTML allowed: links).
	 * @param   string $actions_html Actions (already escaped HTML).
	 */
	static function page_head( $eyebrow, $title, $lead = '', $actions_html = '' ) {
		echo '<div class="page-head"><div>';
		if ( '' !== $eyebrow ) {
			echo '<div class="eyebrow">' . wp_kses_post( $eyebrow ) . '</div>';
		}
		echo '<h1>' . esc_html( $title ) . '</h1>';
		if ( '' !== $lead ) {
			echo '<p class="lead" data-pxf-lead>' . wp_kses_post( $lead ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $actions_html ) {
			echo '<div class="head-actions">' . $actions_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}
		echo '</div>';
	}

	/**
	 * chip.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $text Text.
	 * @param   string $kind neutral|ok|warn|bad|info.
	 * @param   string $attr Extra attributes (escaped by the caller).
	 * @return  string
	 */
	static function chip( $text, $kind = 'neutral', $attr = '' ) {
		return '<span class="chip chip-' . esc_attr( $kind ) . '"' . $attr . '>' . esc_html( $text ) . '</span>';
	}

	/**
	 * status: chip kind and text for a run state.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $status    idle|queued|running|done|failed.
	 * @param   int    $last_good Timestamp of the last complete file.
	 * @return  array  array( kind, text )
	 */
	static function status( $status, $last_good = 0 ) {
		switch ( $status ) {
			case 'running':
				return array( 'info', __( 'Updating', 'product-xml-feeds-for-woocommerce' ) );
			case 'queued':
				return array( 'info', __( 'Waiting', 'product-xml-feeds-for-woocommerce' ) );
			case 'failed':
				return array( 'bad', __( 'Not updated', 'product-xml-feeds-for-woocommerce' ) );
		}
		if ( $last_good ) {
			return array( 'ok', __( 'Up to date', 'product-xml-feeds-for-woocommerce' ) );
		}
		return array( 'neutral', __( 'Never updated', 'product-xml-feeds-for-woocommerce' ) );
	}

	/**
	 * toggle: a real checkbox drawn as a switch, with a hidden 0 so "off" is saved too.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $name    Field name.
	 * @param   bool   $checked Checked.
	 * @param   string $label   Accessible label.
	 * @return  string
	 */
	static function toggle( $name, $checked, $label ) {
		return '<label class="toggle"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">' .
			'<input type="checkbox" role="switch" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) .
			' aria-label="' . esc_attr( $label ) . '"><span class="state" aria-hidden="true">' .
			( $checked ? esc_html__( 'On', 'product-xml-feeds-for-woocommerce' ) : esc_html__( 'Off', 'product-xml-feeds-for-woocommerce' ) ) .
			'</span></label>';
	}

	/**
	 * setting row.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $title   Title.
	 * @param   string $help    Help line.
	 * @param   string $control Control HTML (escaped by the caller).
	 * @param   string $for     Id of the control, for the title label.
	 */
	static function setting( $title, $help, $control, $for = '' ) {
		echo '<div class="setting"><div>';
		if ( $for ) {
			echo '<label class="row-title" for="' . esc_attr( $for ) . '">' . esc_html( $title ) . '</label>';
		} else {
			echo '<p class="row-title">' . esc_html( $title ) . '</p>';
		}
		if ( '' !== $help ) {
			echo '<div class="row-meta">' . wp_kses_post( $help ) . '</div>';
		}
		echo '</div><div class="ctl">' . $control . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- control built from escaped parts.
	}

	/**
	 * banner: one-line message.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $html   Message (links allowed).
	 * @param   string $kind   info|ok|warn|bad.
	 * @param   string $action Action HTML (escaped by the caller).
	 */
	static function banner( $html, $kind = 'info', $action = '' ) {
		echo '<div class="banner ' . esc_attr( $kind ) . '" role="status"><span>' . wp_kses_post( $html ) . '</span>' . $action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action built from escaped parts.
	}

	/**
	 * url_field: read-only link with a Copy button.
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   string $id    Input id.
	 * @param   string $url   URL.
	 * @param   string $label Accessible label.
	 * @param   bool   $show_label Show the label.
	 * @return  string
	 */
	static function url_field( $id, $url, $label, $show_label = false ) {
		return '<div class="url"><label class="' . ( $show_label ? 'label' : 'sr-only' ) . '" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>' .
			'<input id="' . esc_attr( $id ) . '" class="input" type="text" readonly value="' . esc_attr( $url ) . '">' .
			'<button class="btn btn-secondary btn-sm" type="button" data-pxf-copy="' . esc_attr( $id ) . '">' . esc_html__( 'Copy', 'product-xml-feeds-for-woocommerce' ) . '</button></div>';
	}

	/**
	 * date: "today 09:14", "25 Nov, 09:14".
	 *
	 * @version 4.0.0
	 * @since   4.0.0
	 *
	 * @param   int $ts Timestamp.
	 * @return  string
	 */
	static function date( $ts ) {
		$ts = (int) $ts;
		if ( ! $ts ) {
			return '';
		}
		if ( wp_date( 'Y-m-d', $ts ) === wp_date( 'Y-m-d' ) ) {
			/* translators: %s: time. */
			return sprintf( __( 'today %s', 'product-xml-feeds-for-woocommerce' ), wp_date( get_option( 'time_format', 'H:i' ), $ts ) );
		}
		return wp_date( 'j M, ' . get_option( 'time_format', 'H:i' ), $ts );
	}

}

endif;
