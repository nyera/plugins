<?php
/**
 * Bridges REST → existing wp_ajax_yamamah_* handlers for full feature parity.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ydash_REST_Proxy {

	public static function register_routes() {
		register_rest_route(
			'ydash/v1',
			'/bridge/(?P<action>[a-z0-9_]+)',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'bridge' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
				'args'                => array(
					'action' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		// Clean aliases for common reads (still use Yamamah_API).
		register_rest_route(
			'ydash/v1',
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'dashboard' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
		register_rest_route(
			'ydash/v1',
			'/orders',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'orders' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
		register_rest_route(
			'ydash/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'products' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
		register_rest_route(
			'ydash/v1',
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'customers' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
		register_rest_route(
			'ydash/v1',
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'notifications' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
		register_rest_route(
			'ydash/v1',
			'/actions',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_actions' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
	}

	public static function list_actions() {
		global $wp_filter;
		$actions = array();
		if ( isset( $wp_filter['wp_ajax_yamamah_get_dashboard_data'] ) ) {
			// Collect from known prefix by scanning registered ajax hooks is heavy;
			// expose static list of bridgeable action names (without yamamah_ prefix in path).
		}
		$names = self::known_actions();
		return rest_ensure_response( array( 'actions' => $names ) );
	}

	public static function known_actions() {
		return array(
			'get_dashboard_data', 'get_orders', 'get_products', 'get_customers', 'get_customer',
			'save_customer', 'delete_customer',
			'submit_support', 'toggle_dark_mode', 'save_settings', 'get_store_settings',
			'save_contact_page_settings', 'save_blog_page_settings', 'save_blog_post',
			'delete_blog_post', 'save_blog_category', 'delete_blog_category',
			'sync_theme_pages', 'test_contact_email', 'save_email_settings',
			'send_test_email', 'check_email_dns', 'save_tracking_settings',
			'verify_tracking', 'create_api_key', 'revoke_api_key',
			'toggle_email_notification', 'send_cart_reminder', 'bulk_cart_reminder',
			'save_abandoned_settings', 'save_abandon_minutes', 'delete_abandoned_cart',
			'save_home_content', 'restore_home_content', 'get_appearance_settings', 'toggle_payment_method',
			'get_payment_methods', 'get_payment_settings',
			'save_payment_settings', 'save_tap_keys', 'attach_domain', 'verify_domain',
			'poll_domain_job', 'remove_domain', 'save_shipping_settings',
			'reregister_shipping', 'fetch_label',
			'get_shipping_orders', 'get_shipping_order', 'get_shipping_settings',
			'get_shipping_carriers', 'get_shipping_quote', 'save_shipping_pending',
			'create_shipping_order', 'get_shipping_wallet', 'get_shipping_pricing',
			'request_shipping_withdraw',
			'update_order_status',
			'update_order_billing', 'update_order_address', 'add_order_note',
			'refund_order', 'apply_order_coupon', 'remove_order_coupon',
			'toggle_loyalty', 'save_loyalty_settings', 'adjust_loyalty_points',
			'create_coupon', 'delete_coupon', 'bulk_products', 'delete_product',
			'get_providers', 'get_provider', 'save_provider', 'delete_provider', 'get_product', 'save_product',
			'provider_me', 'provider_update_me', 'provider_my_bookings', 'provider_working_hours',
			'get_attribute_terms', 'add_term', 'delete_term', 'update_term',
			'save_attribute', 'update_attribute', 'delete_attribute',
			'add_attr_value', 'delete_attr_value', 'save_analytics_settings',
			'get_chart_data', 'get_analytics_revenue', 'get_analytics_products', 'get_analytics_settings',
			'save_admin', 'delete_admin', 'get_admin', 'get_admins',
			'save_site_colors', 'save_review', 'delete_review', 'save_logo',
			'delete_media', 'upload_media', 'cleanup_media', 'save_policy', 'update_profile',
			'get_notifications', 'mark_notification_read', 'mark_all_notifications_read',
			'save_sms_settings', 'save_sms_gateway', 'test_sms_gateway',
			'upload_tap_document', 'remove_tap_document', 'get_tap_documents',
			'download_products_template', 'export_products_csv', 'import_products_csv',
			'get_import_status',
		);
	}

	public static function dashboard( WP_REST_Request $request ) {
		if ( function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider() ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		if ( function_exists( 'yamamah_current_user_can' ) && ! yamamah_current_user_can( 'dashboard-view' ) ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		$period = sanitize_text_field( (string) $request->get_param( 'period' ) ) ?: '30day';
		$api    = new Yamamah_API();
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'stats'          => $api->get_dashboard_stats( $period ),
					'recent_orders'  => $api->get_recent_orders(),
					'top_products'   => $api->get_top_products(),
					'stock_alerts'   => $api->get_stock_alerts(),
					'order_overview' => $api->get_order_overview(),
				),
			)
		);
	}

	public static function orders( WP_REST_Request $request ) {
		if ( function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider() ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		if ( function_exists( 'yamamah_current_user_can' ) && ! yamamah_current_user_can( 'orders-view' ) ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		$page   = absint( $request->get_param( 'page_num' ) ) ?: 1;
		$status = sanitize_text_field( (string) $request->get_param( 'status' ) ) ?: 'any';
		$api    = new Yamamah_API();
		return rest_ensure_response( array( 'success' => true, 'data' => $api->get_orders( $page, 20, $status ) ) );
	}

	public static function products( WP_REST_Request $request ) {
		if ( function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider() ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		if ( function_exists( 'yamamah_current_user_can' ) && ! yamamah_current_user_can( 'products-view' ) ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		$page = absint( $request->get_param( 'page_num' ) ) ?: 1;
		$api  = new Yamamah_API();
		return rest_ensure_response( array( 'success' => true, 'data' => $api->get_products( $page, 20 ) ) );
	}

	public static function customers() {
		if ( function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider() ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		if ( function_exists( 'yamamah_current_user_can' ) && ! yamamah_current_user_can( 'customers-view' ) ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح', array( 'status' => 403 ) );
		}
		$api = new Yamamah_API();
		return rest_ensure_response( array( 'success' => true, 'data' => $api->get_customers() ) );
	}

	public static function notifications() {
		$notifs = yamamah_get_notifications_option();
		if ( function_exists( 'yamamah_prepare_notifications_for_client' ) ) {
			$notifs = yamamah_prepare_notifications_for_client( $notifs );
		}
		$unread = 0;
		foreach ( $notifs as $n ) {
			if ( empty( $n['read'] ) ) {
				$unread++;
			}
		}
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'notifications' => array_slice( $notifs, 0, 20 ),
					'unread'        => $unread,
				),
			)
		);
	}

	public static function bridge( WP_REST_Request $request ) {
		$short  = sanitize_key( $request['action'] );
		$action = 'yamamah_' . $short;

		if ( ! in_array( $short, self::known_actions(), true ) ) {
			return new WP_Error( 'ydash_unknown_action', 'Unknown action: ' . $short, array( 'status' => 404 ) );
		}

		if ( function_exists( 'yamamah_user_can_ajax' ) && ! yamamah_user_can_ajax( $action ) ) {
			return new WP_Error( 'ydash_rbac', 'غير مصرح لهذا الإجراء', array( 'status' => 403 ) );
		}

		$hook = 'wp_ajax_' . $action;
		if ( ! has_action( $hook ) ) {
			return new WP_Error( 'ydash_no_handler', 'Handler missing: ' . $action, array( 'status' => 500 ) );
		}

		$params = $request->get_params();
		unset( $params['action'] );
		$body = $request->get_json_params();
		if ( is_array( $body ) ) {
			$params = array_merge( $params, $body );
		}

		// Authenticated REST user already set — mint valid AJAX nonce.
		$nonce = wp_create_nonce( 'yamamah_nonce' );
		$params['nonce']  = $nonce;
		$params['action'] = $action;

		foreach ( $params as $key => $value ) {
			$_REQUEST[ $key ] = $value;
			$_POST[ $key ]    = $value;
			$_GET[ $key ]     = $value;
		}

		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		$result = null;
		$handler = function ( $message = '', $title = '', $args = array() ) use ( &$result ) {
			// wp_send_json already echoed JSON into the buffer.
			throw new Ydash_Ajax_Exit();
		};
		add_filter( 'wp_die_ajax_handler', function () use ( $handler ) {
			return $handler;
		} );
		add_filter( 'wp_die_handler', function () use ( $handler ) {
			return $handler;
		} );

		ob_start();
		try {
			do_action( $hook );
			$raw = ob_get_clean();
		} catch ( Ydash_Ajax_Exit $e ) {
			$raw = ob_get_clean();
		} catch ( Exception $e ) {
			ob_end_clean();
			return new WP_Error( 'ydash_bridge_error', $e->getMessage(), array( 'status' => 500 ) );
		}

		$decoded = json_decode( $raw, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return rest_ensure_response( $decoded );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'raw'     => $raw,
			)
		);
	}
}

class Ydash_Ajax_Exit extends Exception {}
