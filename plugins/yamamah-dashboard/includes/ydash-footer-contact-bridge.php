<?php
/**
 * جسر الفوتر — بيانات التواصل المعروضة في التذييل (بريد، هاتف، عنوان).
 *
 * الفلاتر:
 * - ydash_footer_contact_manifest: array<string,string> مفتاح الحقل => تسمية العرض في الداشبورد.
 * - ydash_get_footer_contact_settings: array<string,string> القيم الحالية (email, phone, address).
 *
 * الإجراءات:
 * - ydash_save_footer_contact_settings( array $sanitized ): يحفظ الثيم أو الإضافة الافتراضية إلى الخيارات.
 *
 * @package Yamamah_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تسميات الحقول الافتراضية في صفحة تواصل معنا (قسم الفوتر).
 *
 * @param array<string,string> $manifest ما أرجعه فلاتر سابقة.
 * @return array<string,string>
 */
function yamamah_ydash_footer_contact_manifest_default( $manifest ) {
	$defaults = array(
		'email'   => __( 'البريد الإلكتروني (يظهر في الفوتر)', 'yamamah-dashboard' ),
		'phone'   => __( 'رقم الجوال أو الهاتف (يظهر في الفوتر)', 'yamamah-dashboard' ),
		'address' => __( 'الموقع أو العنوان (يظهر في الفوتر)', 'yamamah-dashboard' ),
	);
	return array_merge( $defaults, is_array( $manifest ) ? $manifest : array() );
}
add_filter( 'ydash_footer_contact_manifest', 'yamamah_ydash_footer_contact_manifest_default', 10 );

/**
 * قيم افتراضية من خيارات المتجر المشتركة.
 *
 * @param array<string,string> $settings ما مرّ عبر فلاتر سابقة.
 * @return array<string,string>
 */
function yamamah_ydash_get_footer_contact_settings_default( $settings ) {
	$base = array(
		'email'   => (string) get_option( 'yamamah_store_email', '' ),
		'phone'   => (string) get_option( 'yamamah_store_phone', '' ),
		'address' => (string) get_option( 'yamamah_store_address', '' ),
	);
	if ( ! is_array( $settings ) ) {
		return $base;
	}
	return array_merge( $base, $settings );
}
add_filter( 'ydash_get_footer_contact_settings', 'yamamah_ydash_get_footer_contact_settings_default', 10 );

/**
 * حفظ افتراضي إلى yamamah_store_* (يمكن للثيم تجاوزه بأولوية أعلى على نفس الـ action).
 *
 * @param array<string,string> $data مفاتيح email / phone / address.
 */
function yamamah_ydash_save_footer_contact_settings_default( $data ) {
	if ( ! is_array( $data ) ) {
		return;
	}
	if ( array_key_exists( 'email', $data ) ) {
		update_option( 'yamamah_store_email', sanitize_email( (string) $data['email'] ) );
	}
	if ( array_key_exists( 'phone', $data ) ) {
		update_option( 'yamamah_store_phone', sanitize_text_field( (string) $data['phone'] ) );
	}
	if ( array_key_exists( 'address', $data ) ) {
		update_option( 'yamamah_store_address', sanitize_text_field( (string) $data['address'] ) );
	}
}
add_action( 'ydash_save_footer_contact_settings', 'yamamah_ydash_save_footer_contact_settings_default', 10 );
