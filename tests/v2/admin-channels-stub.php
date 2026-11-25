<?php
/**
 * TEST ONLY: minimal Alg_WC_PXF_Channels (contract 3.2) for the admin REST stub. Loaded by admin-rest-stub.php only
 * when the channels module is missing.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Alg_WC_PXF_Channels' ) ) :

final class Alg_WC_PXF_Channels {

	static function all() {
		return Alg_WC_PXF_Admin_REST_Stub::channels();
	}

	static function get( $id ) {
		$all = Alg_WC_PXF_Admin_REST_Stub::channels();
		if ( ! isset( $all[ $id ] ) ) {
			return null;
		}
		$all[ $id ]['fields'] = Alg_WC_PXF_Admin_REST_Stub::fields();
		return $all[ $id ];
	}

}

endif;
