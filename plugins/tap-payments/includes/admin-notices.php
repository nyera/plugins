<?php
/**
 * إشعار في لوحة WP يطلب من التاجر إكمال onboarding عند Tap.
 * يظهر فقط لمن يملك صلاحية manage_woocommerce وعند غياب merchant_id.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_notices', 'yamama_tap_render_onboarding_notice' );

function yamama_tap_render_onboarding_notice() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( get_option( 'yamama_tap_dismissed_notice' ) ) {
		return;
	}
	$s = yamama_tap_get_settings();
	if ( ! empty( $s['merchant_id'] ) ) {
		return;
	}

	$ydash_url = admin_url( 'admin.php?page=yamama-dashboard&tab=payment-settings' );
	?>
	<div class="notice notice-warning is-dismissible" style="border-right-color:#10C07A;">
		<p>
			<strong>بوابة الدفع لم تُفعَّل بعد:</strong>
			لكي يستطيع عملاؤك الدفع إلكترونياً عبر مدى/الفيزا، يجب إكمال خطوات الربط مع Tap من لوحة ياماما.
			<a class="button button-primary" style="margin-right:8px;" href="<?php echo esc_url( $ydash_url ); ?>">فعّل بوابة الدفع</a>
		</p>
	</div>
	<?php
}

/**
 * زر/Endpoint لتجاهل الإشعار (اختياري).
 */
add_action( 'admin_init', 'yamama_tap_maybe_dismiss_notice' );
function yamama_tap_maybe_dismiss_notice() {
	if ( isset( $_GET['yamama_tap_dismiss'] ) && current_user_can( 'manage_woocommerce' ) ) {
		check_admin_referer( 'yamama_tap_dismiss' );
		update_option( 'yamama_tap_dismissed_notice', 1 );
		wp_safe_redirect( remove_query_arg( array( 'yamama_tap_dismiss', '_wpnonce' ) ) );
		exit;
	}
}
