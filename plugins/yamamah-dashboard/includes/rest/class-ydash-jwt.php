<?php
/**
 * Minimal HS256 JWT for ydash mobile / gateway auth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ydash_JWT {

	public static function secret() {
		$secret = get_option( 'ydash_jwt_secret', '' );
		if ( $secret === '' ) {
			$secret = wp_generate_password( 64, true, true );
			update_option( 'ydash_jwt_secret', $secret, false );
		}
		return $secret;
	}

	public static function encode( array $payload, $ttl_seconds = 3600 ) {
		$header = array( 'typ' => 'JWT', 'alg' => 'HS256' );
		$now    = time();
		$payload['iat'] = $now;
		$payload['exp'] = $now + (int) $ttl_seconds;
		$segments = array(
			self::b64( wp_json_encode( $header ) ),
			self::b64( wp_json_encode( $payload ) ),
		);
		$signing = implode( '.', $segments );
		$sig     = hash_hmac( 'sha256', $signing, self::secret(), true );
		$segments[] = self::b64( $sig );
		return implode( '.', $segments );
	}

	public static function decode( $token ) {
		$parts = explode( '.', (string) $token );
		if ( count( $parts ) !== 3 ) {
			return new WP_Error( 'ydash_jwt_malformed', 'Invalid token', array( 'status' => 401 ) );
		}
		list( $h64, $p64, $s64 ) = $parts;
		$signing = $h64 . '.' . $p64;
		$expect  = self::b64( hash_hmac( 'sha256', $signing, self::secret(), true ) );
		if ( ! hash_equals( $expect, $s64 ) ) {
			return new WP_Error( 'ydash_jwt_signature', 'Invalid signature', array( 'status' => 401 ) );
		}
		$payload = json_decode( self::ub64( $p64 ), true );
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'ydash_jwt_payload', 'Invalid payload', array( 'status' => 401 ) );
		}
		if ( empty( $payload['exp'] ) || (int) $payload['exp'] < time() ) {
			return new WP_Error( 'ydash_jwt_expired', 'Token expired', array( 'status' => 401 ) );
		}
		return $payload;
	}

	private static function b64( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function ub64( $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
