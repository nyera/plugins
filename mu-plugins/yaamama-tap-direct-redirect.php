<?php
/**
 * Plugin Name: Yaamama Tap Direct Redirect
 * Description: يعطّل أي شاشة "شكراً" وسيطة قبل التحويل إلى صفحة دفع Tap، ويُسرّع تجربة العميل.
 * Version: 1.0.0
 * Author: Yaamama
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * يضمن أن إعدادات بوابة Tap لا تحوي شاشة شكر قبل التحويل (في حال أُضيف خيار مماثل لاحقاً).
 *
 * @param array|false $settings قيمة خيار woocommerce_tap_settings.
 * @return array|false
 */
add_filter(
	'option_woocommerce_tap_settings',
	static function ( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		if ( isset( $settings['display_thank_you'] ) ) {
			$settings['display_thank_you'] = 'no';
		}
		return $settings;
	},
	20
);

/**
 * بعض الثيمات تكتب نصاً ثابتاً قبل تحويل العميل إلى Tap. هذا الفلتر
 * يحوّل قيمة `yamama_tap_skip_intermediate` إلى true افتراضياً ليتمكن قالب الثيم
 * من تخطّي تلك الشاشة عند توفر apple_pay/mada وما شابه.
 */
add_filter(
	'pre_option_yamama_tap_skip_intermediate',
	static function ( $value ) {
		if ( $value === false ) {
			return '1';
		}
		return $value;
	},
	5
);
