<?php
/**
 * Plugin Name: Yamama Tap Payments
 * Description: بوابة دفع Tap (نموذج Sub-Merchant) للمتاجر التابعة لمنصة ياماما — يستقبل merchant_id من المنصة الأم تلقائياً.
 * Version: 1.0.0
 * Author: Yamama
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: yamama-tap
 */

defined( 'ABSPATH' ) || exit;

define( 'YAMAMA_TAP_VERSION', '1.0.0' );
define( 'YAMAMA_TAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'YAMAMA_TAP_URL', plugin_dir_url( __FILE__ ) );

/**
 * تحقق من تفعيل WooCommerce.
 */
function yamama_tap_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * إعدادات Tap الخاصة بهذا المتجر (مدفوعة من المنصة الأم).
 */
function yamama_tap_get_settings() {
	$defaults = array(
		'merchant_id'    => get_option( 'yamama_tap_merchant_id', '' ),
		'technology_id'  => get_option( 'yamama_tap_technology_id', '' ),
		'platform_id'    => get_option( 'yamama_tap_platform_id', '' ),
		'secret_key'     => get_option( 'yamama_tap_secret_key', '' ),
		'test_mode'      => (int) get_option( 'yamama_tap_test_mode', 1 ),
		'livemode'       => (int) get_option( 'yamama_tap_livemode', 0 ),
		'platform_url'   => get_option( 'yamama_platform_url', '' ),
		'platform_token' => get_option( 'yamama_api_token', '' ),
		'store_id'       => get_option( 'yamama_store_id', '' ),
	);
	return $defaults;
}

/**
 * الإقلاع.
 */
function yamama_tap_init() {
	if ( ! yamama_tap_woocommerce_active() ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>WooCommerce غير مفعّل — Yamama Tap Payments يحتاجه.</p></div>';
		} );
		return;
	}

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once YAMAMA_TAP_PATH . 'includes/class-wc-gateway-tap.php';
	require_once YAMAMA_TAP_PATH . 'includes/class-rest-controller.php';
	require_once YAMAMA_TAP_PATH . 'includes/admin-notices.php';

	add_filter( 'woocommerce_payment_gateways', 'yamama_tap_register_gateway' );
}
add_action( 'plugins_loaded', 'yamama_tap_init', 11 );

function yamama_tap_register_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Tap';
	return $methods;
}

/**
 * تنشيط الإضافة: إنشاء خيارات افتراضية إن لم تكن موجودة.
 */
function yamama_tap_activate() {
	add_option( 'yamama_tap_test_mode', 1 );
	add_option( 'yamama_tap_livemode', 0 );
}
register_activation_hook( __FILE__, 'yamama_tap_activate' );
