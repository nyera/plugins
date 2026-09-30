<?php
/**
 * Disable Jetpack Force 2FA + SSO login suppression on main site.
 *
 * Must run as MU plugin so it loads before Jetpack SSO.
 */

defined( 'ABSPATH' ) || exit;

function yaamama_is_main_site() {
	return is_multisite() ? is_main_site() : true;
}

function yaamama_disable_jetpack_force_2fa_on_main_site( $force_2fa ) {
	if ( yaamama_is_main_site() ) {
		return false;
	}

	return $force_2fa;
}
add_filter( 'jetpack_force_2fa', 'yaamama_disable_jetpack_force_2fa_on_main_site', 9999 );

function yaamama_allow_wp_login_on_main_site( $remove_login_form ) {
	if ( yaamama_is_main_site() ) {
		return false;
	}

	return $remove_login_form;
}
add_filter( 'jetpack_remove_login_form', 'yaamama_allow_wp_login_on_main_site', 9999 );

function yaamama_disable_jetpack_sso_bypass_on_main_site( $bypass ) {
	if ( yaamama_is_main_site() ) {
		return false;
	}

	return $bypass;
}
add_filter( 'jetpack_sso_bypass_login_forward_wpcom', 'yaamama_disable_jetpack_sso_bypass_on_main_site', 9999 );

function yaamama_disable_jetpack_sso_two_step_on_main_site( $required ) {
	if ( yaamama_is_main_site() ) {
		return false;
	}

	return $required;
}
add_filter( 'jetpack_sso_require_two_step', 'yaamama_disable_jetpack_sso_two_step_on_main_site', 9999 );

function yaamama_log_wp_login_errors( $user, $username, $password ) {
	if ( yaamama_is_main_site() && is_wp_error( $user ) ) {
		error_log(
			sprintf(
				'[yaamama-login] user=%s codes=%s messages=%s',
				(string) $username,
				implode( ',', $user->get_error_codes() ),
				implode( ' | ', $user->get_error_messages() )
			)
		);
	}

	return $user;
}
add_filter( 'authenticate', 'yaamama_log_wp_login_errors', 1000, 3 );

function yaamama_log_wp_login_failed( $username, $error = null ) {
	if ( yaamama_is_main_site() ) {
		$details = '';
		if ( $error instanceof WP_Error ) {
			$details = implode( ' | ', $error->get_error_messages() );
		}
		error_log( sprintf( '[yaamama-login] failed user=%s %s', (string) $username, $details ) );
	}
}
add_action( 'wp_login_failed', 'yaamama_log_wp_login_failed', 10, 2 );
