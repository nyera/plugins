<?php
/**
 * إعدادات ضريبة المتجر (يمامة ← إعدادات المتجر ← الضرائب).
 * تُزامَن مع WooCommerce لتعمل مع كل أنواع المتاجر والثيمات.
 *
 * @package Yamamah_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param mixed $mode Raw mode.
 * @return string exclusive|inclusive
 */
function yamamah_sanitize_booking_tax_mode( $mode ) {
	$mode = sanitize_key( (string) $mode );
	return in_array( $mode, array( 'exclusive', 'inclusive' ), true ) ? $mode : 'exclusive';
}

/**
 * وضع عرض الضريبة: exclusive = غير متضمنة | inclusive = متضمنة.
 * المصدر الأساسي: خيار المتجر yamamah_tax_mode.
 *
 * @return string
 */
function yamamah_get_booking_tax_mode() {
	$mode = get_option( 'yamamah_tax_mode', '' );
	if ( ! in_array( (string) $mode, array( 'exclusive', 'inclusive' ), true ) ) {
		$stored = get_option( 'yamamah_rental_settings', array() );
		$mode   = is_array( $stored ) && isset( $stored['booking_tax_mode'] ) ? $stored['booking_tax_mode'] : 'exclusive';
	}
	if ( ! in_array( (string) $mode, array( 'exclusive', 'inclusive' ), true ) && function_exists( 'wc_prices_include_tax' ) ) {
		$mode = wc_prices_include_tax() ? 'inclusive' : 'exclusive';
	}
	return yamamah_sanitize_booking_tax_mode( $mode );
}

/**
 * هل ضريبة يمامة مفعّلة؟
 *
 * @return bool
 */
function yamamah_is_store_tax_enabled() {
	return 'yes' === get_option( 'yamamah_tax_enabled', 'yes' );
}

/**
 * نسبة ضريبة المتجر %.
 *
 * @return float
 */
function yamamah_get_store_tax_rate() {
	return max( 0, (float) get_option( 'yamamah_tax_rate', 15 ) );
}

/**
 * حفظ وضع العرض ومواءمة خيار الإيجار القديم للتوافق.
 *
 * @param string $mode exclusive|inclusive.
 * @return string
 */
function yamamah_set_booking_tax_mode( $mode ) {
	$mode = yamamah_sanitize_booking_tax_mode( $mode );
	update_option( 'yamamah_tax_mode', $mode );

	$stored = get_option( 'yamamah_rental_settings', array() );
	if ( ! is_array( $stored ) ) {
		$stored = array();
	}
	$stored['booking_tax_mode'] = $mode;
	update_option( 'yamamah_rental_settings', $stored, false );

	/**
	 * @param string $mode exclusive|inclusive.
	 */
	do_action( 'yamamah_booking_tax_mode_saved', $mode );

	return $mode;
}

/**
 * إدراج/تحديث نسبة الضريبة القياسية في جدول WooCommerce.
 *
 * @param float  $percent النسبة.
 * @param string $name    اسم الضريبة.
 * @return void
 */
function yamamah_upsert_woocommerce_standard_tax_rate( $percent, $name = 'ضريبة القيمة المضافة' ) {
	global $wpdb;

	if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
		return;
	}

	$percent = max( 0, (float) $percent );
	$country = (string) WC()->countries->get_base_country();
	if ( '' === $country ) {
		$country = 'SA';
	}

	$table = $wpdb->prefix . 'woocommerce_tax_rates';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$existing_id = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT tax_rate_id FROM {$table} WHERE tax_rate_country = %s AND tax_rate_class = '' ORDER BY tax_rate_priority ASC, tax_rate_id ASC LIMIT 1",
			$country
		)
	);

	$row = array(
		'tax_rate_country'  => $country,
		'tax_rate_state'    => '',
		'tax_rate'          => function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $percent, 4 ) : (string) $percent,
		'tax_rate_name'     => sanitize_text_field( $name ),
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 0,
		'tax_rate_order'    => 0,
		'tax_rate_class'    => '',
	);

	if ( $existing_id > 0 ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, $row, array( 'tax_rate_id' => $existing_id ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $row );
	}

	if ( class_exists( 'WC_Cache_Helper' ) && is_callable( array( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) ) {
		WC_Cache_Helper::invalidate_cache_group( 'taxes' );
	}
	delete_transient( 'wc_tax_rates_' . md5( wp_json_encode( array( $country, '', '', '', '' ) ) ) );
}

/**
 * مزامنة إعدادات ضريبة يمامة بالكامل مع WooCommerce.
 *
 * @param string|null $enabled yes|no أو null لقراءة الخيار الحالي.
 * @param float|null  $rate    النسبة أو null.
 * @param string|null $mode    exclusive|inclusive أو null.
 * @return void
 */
function yamamah_sync_store_tax_to_woocommerce( $enabled = null, $rate = null, $mode = null ) {
	if ( null === $enabled ) {
		$enabled = get_option( 'yamamah_tax_enabled', 'yes' );
	}
	$enabled = ( 'yes' === $enabled ) ? 'yes' : 'no';
	update_option( 'yamamah_tax_enabled', $enabled );

	if ( null === $rate ) {
		$rate = yamamah_get_store_tax_rate();
	}
	$rate = max( 0, (float) $rate );
	update_option( 'yamamah_tax_rate', $rate );

	if ( null === $mode ) {
		$mode = yamamah_get_booking_tax_mode();
	}
	$mode = yamamah_sanitize_booking_tax_mode( $mode );
	yamamah_set_booking_tax_mode( $mode );

	// تفعيل/تعطيل حساب الضريبة في WooCommerce.
	update_option( 'woocommerce_calc_taxes', $enabled );
	update_option( 'woocommerce_prices_include_tax', 'inclusive' === $mode ? 'yes' : 'no' );
	update_option( 'woocommerce_tax_display_shop', 'inclusive' === $mode ? 'incl' : 'excl' );
	update_option( 'woocommerce_tax_display_cart', 'inclusive' === $mode ? 'incl' : 'excl' );
	update_option( 'woocommerce_tax_based_on', 'base' );
	update_option( 'woocommerce_default_customer_address', 'base' );

	// ضمان وجود دولة أساسية لمطابقة نسب الضريبة.
	$default_country = (string) get_option( 'woocommerce_default_country', '' );
	if ( '' === $default_country ) {
		update_option( 'woocommerce_default_country', 'SA' );
	}

	if ( 'yes' === $enabled ) {
		yamamah_upsert_woocommerce_standard_tax_rate( $rate, 'ضريبة القيمة المضافة' );
	}

	// لا نُعيد حساب السلة هنا لتجنّب حلقة مع woocommerce_before_calculate_totals.
}

/**
 * نسبة الضريبة للواجهة (من WC إن وُجدت، وإلا من إعداد يمامة).
 *
 * @return float
 */
function yamamah_get_effective_tax_rate_percent() {
	if ( ! yamamah_is_store_tax_enabled() ) {
		return 0.0;
	}
	if ( function_exists( 'wc_tax_enabled' ) && wc_tax_enabled() && class_exists( 'WC_Tax' ) && function_exists( 'WC' ) && WC()->countries ) {
		$rates = WC_Tax::find_rates(
			array(
				'country'   => WC()->countries->get_base_country(),
				'state'     => WC()->countries->get_base_state(),
				'postcode'  => WC()->countries->get_base_postcode(),
				'city'      => WC()->countries->get_base_city(),
				'tax_class' => '',
			)
		);
		$sum = 0.0;
		foreach ( $rates as $rate ) {
			$sum += isset( $rate['rate'] ) ? (float) $rate['rate'] : 0.0;
		}
		if ( $sum > 0 ) {
			return $sum;
		}
	}
	return yamamah_get_store_tax_rate();
}

/**
 * هل يجب تطبيق ضريبة المتجر على الواجهة؟
 *
 * @return bool
 */
function yamamah_is_store_tax_active() {
	return yamamah_is_store_tax_enabled() && yamamah_get_effective_tax_rate_percent() > 0;
}

/**
 * هل يُعرض بند الضريبة في السلة/الدفع؟
 *
 * @return bool
 */
function yamamah_should_display_cart_tax() {
	if ( yamamah_is_store_tax_active() ) {
		return true;
	}
	return function_exists( 'wc_tax_enabled' ) && wc_tax_enabled();
}

/**
 * إصلاح تلقائي: مواءمة WooCommerce مع تفعيل/نسبة/وضع ضريبة يمامة.
 *
 * @return void
 */
function yamamah_maybe_resync_store_tax_to_woocommerce() {
	if ( ! function_exists( 'WC' ) ) {
		return;
	}

	$enabled = yamamah_is_store_tax_enabled();
	$wc_on   = get_option( 'woocommerce_calc_taxes' ) === 'yes';

	if ( ! $enabled ) {
		if ( $wc_on ) {
			update_option( 'woocommerce_calc_taxes', 'no' );
		}
		return;
	}

	$needs = ! $wc_on;
	$mode  = yamamah_get_booking_tax_mode();
	$want  = 'inclusive' === $mode ? 'yes' : 'no';
	if ( get_option( 'woocommerce_prices_include_tax' ) !== $want ) {
		$needs = true;
	}
	$want_display = 'inclusive' === $mode ? 'incl' : 'excl';
	if ( get_option( 'woocommerce_tax_display_cart' ) !== $want_display ) {
		$needs = true;
	}

	$store_rate = yamamah_get_store_tax_rate();
	if ( ! $needs && class_exists( 'WC_Tax' ) && WC()->countries && $store_rate > 0 ) {
		$rates = WC_Tax::find_rates(
			array(
				'country'   => WC()->countries->get_base_country(),
				'state'     => WC()->countries->get_base_state(),
				'postcode'  => WC()->countries->get_base_postcode(),
				'city'      => WC()->countries->get_base_city(),
				'tax_class' => '',
			)
		);
		$sum = 0.0;
		foreach ( $rates as $rate ) {
			$sum += isset( $rate['rate'] ) ? (float) $rate['rate'] : 0.0;
		}
		if ( $sum <= 0 || abs( $sum - $store_rate ) > 0.001 ) {
			$needs = true;
		}
	}

	if ( $needs ) {
		yamamah_sync_store_tax_to_woocommerce();
	}
}
add_action( 'init', 'yamamah_maybe_resync_store_tax_to_woocommerce', 30 );

/**
 * Alias للتوافق مع استدعاءات الواجهة.
 *
 * @return bool
 */
function yamamah_is_tax_active_for_storefront() {
	return yamamah_is_store_tax_active();
}

/**
 * ضمان أن المنتجات خاضعة للضريبة عند تفعيل ضريبة يمامة.
 *
 * @param string     $status  Tax status.
 * @param WC_Product $product Product.
 * @return string
 */
function yamamah_force_taxable_products_when_store_tax_on( $status, $product ) {
	if ( yamamah_is_store_tax_enabled() && 'none' === $status ) {
		return 'taxable';
	}
	return $status;
}
add_filter( 'woocommerce_product_get_tax_status', 'yamamah_force_taxable_products_when_store_tax_on', 20, 2 );
add_filter( 'woocommerce_product_variation_get_tax_status', 'yamamah_force_taxable_products_when_store_tax_on', 20, 2 );

/**
 * قبل حساب السلة: ضمان مزامنة الضريبة إن كانت مفعّلة في يمامة.
 *
 * @return void
 */
function yamamah_ensure_store_tax_before_cart_totals() {
	static $syncing = false;
	if ( $syncing || ! yamamah_is_store_tax_enabled() ) {
		return;
	}
	if ( get_option( 'woocommerce_calc_taxes' ) === 'yes' && yamamah_get_effective_tax_rate_percent() > 0 ) {
		return;
	}
	$syncing = true;
	yamamah_sync_store_tax_to_woocommerce();
	$syncing = false;
}
add_action( 'woocommerce_before_calculate_totals', 'yamamah_ensure_store_tax_before_cart_totals', 1 );

/**
 * تحميل دائري موحّد عند الدفع (بدل خلفية blockUI البيضاء) لكل الثيمات.
 *
 * @return void
 */
function yamamah_enqueue_checkout_loading_styles() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
		return;
	}
	wp_enqueue_style(
		'yamamah-checkout-loading',
		YAMAMAH_PLUGIN_URL . 'assets/css/checkout-loading.css',
		array(),
		defined( 'YAMAMAH_VERSION' ) ? YAMAMAH_VERSION : '1.0'
	);
}
add_action( 'wp_enqueue_scripts', 'yamamah_enqueue_checkout_loading_styles', 40 );
