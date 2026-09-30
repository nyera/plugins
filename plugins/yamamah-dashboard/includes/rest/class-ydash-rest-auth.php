<?php
/**
 * Auth endpoints for merchant app / gateway.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ydash_REST_Auth {

	const ACCESS_TTL  = 3600;
	const REFRESH_TTL = 2592000; // 30 days

	public static function register_routes() {
		register_rest_route(
			'ydash/v1',
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ydash/v1',
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'refresh' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ydash/v1',
			'/auth/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'me' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);

		register_rest_route(
			'ydash/v1',
			'/auth/logout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'logout' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);

		register_rest_route(
			'ydash/v1',
			'/bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'bootstrap' ),
				'permission_callback' => array( 'Ydash_REST_Auth', 'require_user' ),
			)
		);
	}

	public static function require_user( WP_REST_Request $request ) {
		$user_id = self::authenticate_request( $request );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		return (bool) $user_id;
	}

	public static function authenticate_request( WP_REST_Request $request ) {
		$auth = $request->get_header( 'authorization' );
		if ( ! $auth || ! preg_match( '/Bearer\s+(\S+)/i', $auth, $m ) ) {
			return new WP_Error( 'ydash_no_token', 'Missing bearer token', array( 'status' => 401 ) );
		}
		$payload = Ydash_JWT::decode( $m[1] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( empty( $payload['uid'] ) || ( $payload['typ'] ?? '' ) !== 'access' ) {
			return new WP_Error( 'ydash_token_type', 'Invalid access token', array( 'status' => 401 ) );
		}
		$user = get_userdata( (int) $payload['uid'] );
		if ( ! $user || ! function_exists( 'yamamah_user_can_access_app' ) || ! yamamah_user_can_access_app( $user ) ) {
			return new WP_Error( 'ydash_forbidden', 'Not allowed', array( 'status' => 403 ) );
		}
		wp_set_current_user( $user->ID );
		return (int) $user->ID;
	}

	public static function login( WP_REST_Request $request ) {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );

		if ( ! $email || $password === '' ) {
			return new WP_Error( 'ydash_missing', 'Email and password required', array( 'status' => 400 ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$user = get_user_by( 'login', $email );
		}
		if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			return new WP_Error( 'ydash_bad_creds', 'بيانات الدخول غير صحيحة', array( 'status' => 401 ) );
		}
		if ( ! function_exists( 'yamamah_user_can_access_app' ) || ! yamamah_user_can_access_app( $user ) ) {
			return new WP_Error( 'ydash_forbidden', 'هذا الحساب ليس لديه صلاحية لوحة التحكم', array( 'status' => 403 ) );
		}

		return rest_ensure_response( self::token_pair_for_user( $user ) );
	}

	public static function refresh( WP_REST_Request $request ) {
		$refresh = (string) $request->get_param( 'refresh_token' );
		$payload = Ydash_JWT::decode( $refresh );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( empty( $payload['uid'] ) || ( $payload['typ'] ?? '' ) !== 'refresh' ) {
			return new WP_Error( 'ydash_token_type', 'Invalid refresh token', array( 'status' => 401 ) );
		}
		$jti = $payload['jti'] ?? '';
		$stored = get_user_meta( (int) $payload['uid'], 'ydash_refresh_jti', true );
		if ( ! $jti || ! hash_equals( (string) $stored, (string) $jti ) ) {
			return new WP_Error( 'ydash_refresh_revoked', 'Refresh token revoked', array( 'status' => 401 ) );
		}
		$user = get_userdata( (int) $payload['uid'] );
		if ( ! $user || ! function_exists( 'yamamah_user_can_access_app' ) || ! yamamah_user_can_access_app( $user ) ) {
			return new WP_Error( 'ydash_forbidden', 'Not allowed', array( 'status' => 403 ) );
		}
		return rest_ensure_response( self::token_pair_for_user( $user ) );
	}

	public static function logout( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'ydash_refresh_jti' );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function me( WP_REST_Request $request ) {
		$user = wp_get_current_user();
		return rest_ensure_response( self::user_payload( $user ) );
	}

	public static function bootstrap( WP_REST_Request $request ) {
		$user = wp_get_current_user();
		$store_type = function_exists( 'yamamah_get_store_type' ) ? yamamah_get_store_type() : get_option( 'yamama_store_type', 'retail' );
		$labels     = function_exists( 'yamamah_get_catalog_labels' ) ? yamamah_get_catalog_labels() : array();
		$is_provider = function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider( $user );
		$perms       = $is_provider
			? array()
			: ( function_exists( 'yamamah_user_effective_permissions' ) ? yamamah_user_effective_permissions( $user ) : array() );

		$menu = $is_provider
			? self::menu_for_provider()
			: self::menu_for_store( $store_type, $user );

		$provider_id = function_exists( 'yamamah_user_provider_id' ) ? yamamah_user_provider_id( $user ) : 0;

		return rest_ensure_response(
			array(
				'user'         => self::user_payload( $user ),
				'user_type'    => $is_provider ? 'provider' : 'merchant',
				'provider_id'  => $provider_id ?: null,
				'store'        => array(
					'id'           => get_option( 'yamama_store_id', '' ),
					'name'         => get_option( 'yamamah_store_name', get_bloginfo( 'name' ) ),
					'url'          => home_url( '/' ),
					'store_type'   => $store_type,
					'plan_id'      => get_option( 'yamama_plan_id', '' ),
					'plan_features'=> get_option( 'yamama_plan_features', array() ),
					'currency'     => get_woocommerce_currency(),
					'currency_symbol' => get_woocommerce_currency_symbol(),
				),
				'labels'       => $labels,
				'permissions'  => $perms,
				'menu'         => $menu,
				'gateway_url'  => get_option( 'yamama_merchant_gateway_url', '' ),
			)
		);
	}

	public static function token_pair_for_user( WP_User $user ) {
		$jti = wp_generate_uuid4();
		update_user_meta( $user->ID, 'ydash_refresh_jti', $jti );

		$access = Ydash_JWT::encode(
			array(
				'uid' => $user->ID,
				'typ' => 'access',
				'sid' => get_option( 'yamama_store_id', '' ),
			),
			self::ACCESS_TTL
		);
		$refresh = Ydash_JWT::encode(
			array(
				'uid' => $user->ID,
				'typ' => 'refresh',
				'jti' => $jti,
			),
			self::REFRESH_TTL
		);

		$is_provider = function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider( $user );

		return array(
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => self::ACCESS_TTL,
			'token_type'    => 'Bearer',
			'user'          => self::user_payload( $user ),
			'user_type'     => $is_provider ? 'provider' : 'merchant',
			'provider_id'   => function_exists( 'yamamah_user_provider_id' ) ? ( yamamah_user_provider_id( $user ) ?: null ) : null,
			'store'         => array(
				'id'         => get_option( 'yamama_store_id', '' ),
				'name'       => get_option( 'yamamah_store_name', get_bloginfo( 'name' ) ),
				'url'        => home_url( '/' ),
				'store_type' => function_exists( 'yamamah_get_store_type' ) ? yamamah_get_store_type() : get_option( 'yamama_store_type', 'retail' ),
			),
		);
	}

	public static function user_payload( WP_User $user ) {
		$is_provider = function_exists( 'yamamah_user_is_provider' ) && yamamah_user_is_provider( $user );
		$is_admin    = in_array( 'administrator', (array) $user->roles, true );
		$yam_role    = get_user_meta( $user->ID, 'yamamah_role', true );
		$role        = $yam_role ? (string) $yam_role : ( ! empty( $user->roles ) ? (string) $user->roles[0] : '' );
		$perms       = $is_provider
			? array()
			: ( function_exists( 'yamamah_user_effective_permissions' ) ? yamamah_user_effective_permissions( $user ) : array() );
		$provider_id = function_exists( 'yamamah_user_provider_id' ) ? yamamah_user_provider_id( $user ) : 0;

		return array(
			'id'           => $user->ID,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => array_values( $user->roles ),
			'role'         => $role,
			'is_wp_admin'  => $is_admin,
			'user_type'    => $is_provider ? 'provider' : 'merchant',
			'provider_id'  => $provider_id ?: null,
			'permissions'  => $perms,
		);
	}

	public static function menu_for_provider() {
		return array(
			array( 'id' => 'provider-bookings', 'title' => 'حجوزاتي', 'icon' => 'orders' ),
			array( 'id' => 'provider-hours', 'title' => 'ساعات الدوام', 'icon' => 'calendar' ),
			array( 'id' => 'provider-account', 'title' => 'حسابي / السعر', 'icon' => 'user' ),
			array( 'id' => 'notifications', 'title' => 'الإشعارات', 'icon' => 'bell' ),
		);
	}

	public static function menu_for_store( $store_type, $user = null ) {
		$labels = function_exists( 'yamamah_get_catalog_labels' ) ? yamamah_get_catalog_labels() : array();
		$orders_label   = $labels['orders_label'] ?? ( ( $store_type === 'booking' ) ? 'الحجوزات' : 'الطلبات' );
		$products_label = $labels['section'] ?? 'المنتجات';

		$items = array(
			array( 'id' => 'dashboard', 'title' => 'الرئيسية', 'icon' => 'home' ),
			array( 'id' => 'operations', 'title' => 'مدير العمليات', 'icon' => 'calendar', 'types' => array( 'booking', 'rental' ) ),
			array( 'id' => 'orders', 'title' => $orders_label, 'icon' => 'orders' ),
			array( 'id' => 'products', 'title' => $products_label, 'icon' => 'products' ),
			array( 'id' => 'providers', 'title' => 'مزودي الخدمات', 'icon' => 'users', 'types' => array( 'booking' ) ),
			array( 'id' => 'products-import', 'title' => 'استيراد/تصدير', 'icon' => 'upload', 'types' => array( 'retail' ) ),
			array( 'id' => 'categories', 'title' => 'التصنيفات', 'icon' => 'folder' ),
			array( 'id' => 'customers', 'title' => 'العملاء', 'icon' => 'customers' ),
			array( 'id' => 'coupons', 'title' => 'القسائم', 'icon' => 'ticket', 'feature' => 'coupons' ),
			array( 'id' => 'loyalty', 'title' => 'نقاط الولاء', 'icon' => 'star', 'feature' => 'loyalty_points' ),
			array( 'id' => 'abandoned-carts', 'title' => 'السلات المتروكة', 'icon' => 'cart', 'types' => array( 'retail' ), 'feature' => 'abandoned_carts' ),
			array( 'id' => 'shipping', 'title' => 'الشحن', 'icon' => 'truck', 'types' => array( 'retail' ), 'feature' => 'shipping' ),
			array( 'id' => 'payments', 'title' => 'الدفع', 'icon' => 'card' ),
			array( 'id' => 'analytics', 'title' => 'التحليلات', 'icon' => 'chart' ),
			array( 'id' => 'reports', 'title' => 'التقارير', 'icon' => 'report' ),
			array( 'id' => 'users', 'title' => 'المستخدمين', 'icon' => 'users' ),
			array( 'id' => 'admins', 'title' => 'المدراء', 'icon' => 'shield' ),
			array( 'id' => 'settings', 'title' => 'الإعدادات', 'icon' => 'settings' ),
			array( 'id' => 'appearance', 'title' => 'المظهر', 'icon' => 'palette' ),
			array( 'id' => 'media', 'title' => 'الوسائط', 'icon' => 'image' ),
			array( 'id' => 'support', 'title' => 'الدعم الفني', 'icon' => 'help' ),
			array( 'id' => 'account', 'title' => 'حسابي', 'icon' => 'user' ),
		);

		$is_admin = $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true );

		$out = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['types'] ) && ! in_array( $store_type, $item['types'], true ) ) {
				continue;
			}
			if ( ! empty( $item['feature'] ) && function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( $item['feature'] ) ) {
				continue;
			}
			if ( ! $is_admin && function_exists( 'yamamah_menu_item_required_perm' ) && function_exists( 'yamamah_current_user_can' ) ) {
				$need = yamamah_menu_item_required_perm( $item['id'] );
				if ( $need !== null && ! yamamah_current_user_can( $need ) ) {
					continue;
				}
			}
			unset( $item['types'], $item['feature'] );
			$out[] = $item;
		}
		return $out;
	}
}
