<?php
/**
 * Register all ydash/v1 REST routes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ydash_REST_Router {

	public static function init() {
		require_once YAMAMAH_PLUGIN_DIR . 'includes/rest/class-ydash-jwt.php';
		require_once YAMAMAH_PLUGIN_DIR . 'includes/rest/class-ydash-rest-auth.php';
		require_once YAMAMAH_PLUGIN_DIR . 'includes/rest/class-ydash-rest-proxy.php';
		require_once YAMAMAH_PLUGIN_DIR . 'includes/rest/class-ydash-push-bridge.php';

		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
		Ydash_Push_Bridge::init();
	}

	public static function register() {
		Ydash_REST_Auth::register_routes();
		Ydash_REST_Proxy::register_routes();

		register_rest_route(
			'ydash/v1',
			'/',
			array(
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response(
						array(
							'namespace' => 'ydash/v1',
							'version'   => YAMAMAH_VERSION,
							'store_id'  => get_option( 'yamama_store_id', '' ),
						)
					);
				},
				'permission_callback' => '__return_true',
			)
		);
	}
}
