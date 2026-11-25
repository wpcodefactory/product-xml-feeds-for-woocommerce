<?php
/**
 * The site's connection to WPFactory AI, stored once and shared by every WPFactory plugin on the site.
 * The token is encrypted with a key made from this site's salts. A copied database (a staging clone at
 * another address) does not reuse the connection: the site has to connect again.
 *
 * @package WPFactory\AI
 */

namespace WPFactory\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Connection storage.
 */
final class Connection {

	const OPTION = 'wpfactory_ai_connection';

	/**
	 * What the screens may show. Never the token.
	 *
	 * @return array{connected:bool, email:string, site_id:int, connected_at:int, pending_email:string, needs_reconnect:bool, address_changed:bool}
	 */
	public static function info() {
		$c       = self::raw();
		$changed = ! empty( $c['token'] ) && ! self::same_address( $c );
		return array(
			'connected'       => '' !== self::token(),
			'email'           => isset( $c['email'] ) ? (string) $c['email'] : '',
			'site_id'         => isset( $c['site_id'] ) ? (int) $c['site_id'] : 0,
			'connected_at'    => isset( $c['connected_at'] ) ? (int) $c['connected_at'] : 0,
			'pending_email'   => isset( $c['pending_email'] ) ? (string) $c['pending_email'] : '',
			'needs_reconnect' => ! empty( $c['broken'] ) || $changed,
			'address_changed' => $changed,
		);
	}

	/**
	 * The site token, or '' when not connected (or the token cannot be read here).
	 *
	 * @return string
	 */
	public static function token() {
		$c = self::raw();
		if ( empty( $c['token'] ) || ! self::same_address( $c ) ) {
			return '';
		}
		return self::open( (string) $c['token'] );
	}

	/**
	 * Saves a new connection.
	 *
	 * @param string $token   Site token from the server.
	 * @param string $email   Account email.
	 * @param int    $site_id Site id on the server.
	 */
	public static function save( $token, $email, $site_id ) {
		update_option(
			self::OPTION,
			array(
				'token'        => self::seal( (string) $token ),
				'email'        => (string) $email,
				'site_id'      => (int) $site_id,
				'site_url'     => home_url(),
				'connected_at' => time(),
			),
			false
		);
	}

	/**
	 * Remembers the email a code was sent to, for the code form.
	 *
	 * @param string $email Email address.
	 */
	public static function remember_email( $email ) {
		$c                  = self::raw();
		$c['pending_email'] = (string) $email;
		update_option( self::OPTION, $c, false );
	}

	/**
	 * The server no longer accepts the token: keep the email, drop the token, ask to connect again.
	 */
	public static function mark_broken() {
		$c = self::raw();
		unset( $c['token'] );
		$c['broken'] = true;
		update_option( self::OPTION, $c, false );
	}

	/**
	 * Forgets the connection.
	 */
	public static function forget() {
		delete_option( self::OPTION );
	}

	/**
	 * Stored option.
	 *
	 * @return array<string,mixed>
	 */
	private static function raw() {
		$c = get_option( self::OPTION, array() );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * Is the site still at the address it connected from?
	 *
	 * @param array<string,mixed> $c Stored option.
	 * @return bool
	 */
	private static function same_address( array $c ) {
		return empty( $c['site_url'] ) || untrailingslashit( (string) $c['site_url'] ) === untrailingslashit( home_url() );
	}

	/**
	 * Encryption key from this site's salts.
	 *
	 * @return string
	 */
	private static function key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|wpfactory-ai|token', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypts the token for storage.
	 *
	 * @param string $plain Token.
	 * @return string
	 */
	private static function seal( $plain ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'enc1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext stored as text.
	}

	/**
	 * Decrypts the stored token. '' when the salts changed.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	private static function open( $stored ) {
		if ( 0 !== strpos( $stored, 'enc1:' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 5 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reads what seal() wrote.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		return false === $plain ? '' : $plain;
	}
}
