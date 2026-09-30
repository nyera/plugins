<?php
/**
 * Plugin Name: Yamama — إعداد متجر الحجوزات (مرة واحدة)
 * Description: يضبط yamama_store_type إلى booking وميزات الباقة المناسبة لمتجر حجوزات (بدون شحن وبدون سلات متروكة). احذف هذا الملف بعد أول تشغيل ناجح.
 *
 * @package Yamama
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	function () {
		if ( get_option( '_yamama_booking_setup_done' ) ) {
			return;
		}

		update_option( 'yamama_store_type', 'booking' );

		$features = array(
			'max_products'       => -1,
			'max_admins'         => -1,
			'coupons'            => true,
			'loyalty_points'     => true,
			'shipping'           => false,
			'electronic_payment' => true,
			'custom_domain'      => true,
			'abandoned_carts'    => false,
			'api_keys'           => true,
			'sms'                => true,
			'analytics_advanced' => true,
			'code_snippets'      => true,
			'email_custom_smtp'  => true,
		);

		update_option( 'yamama_plan_features', wp_json_encode( $features ), false );

		update_option( 'yamama_max_products', '-1' );
		update_option( 'yamama_max_admins', '-1' );
		update_option( 'yamama_coupons_enabled', 'yes' );
		update_option( 'yamama_loyalty_enabled', 'yes' );
		update_option( 'yamama_shipping_enabled', 'no' );
		update_option( 'yamama_electronic_payment', 'yes' );
		update_option( 'yamama_custom_domain_enabled', 'yes' );
		update_option( 'yamama_abandoned_carts', 'no' );
		update_option( 'yamama_api_keys', 'yes' );
		update_option( 'yamama_analytics_advanced', 'yes' );
		update_option( 'yamama_code_snippets', 'yes' );
		update_option( 'yamama_sms_enabled', 'yes' );
		update_option( 'yamama_sms_monthly_limit', '-1' );

		// WooCommerce: لا شحن لمتاجر الحجوزات.
		update_option( 'woocommerce_calc_shipping', 'no' );
		update_option( 'woocommerce_enable_shipping_calc', 'no' );

		update_option( '_yamama_booking_setup_done', '1' );

		if ( function_exists( 'error_log' ) ) {
			error_log( '[Yamama] Booking store setup: yamama_store_type=booking, plan features applied.' );
		}
	},
	1
);
