<?php
/**
 * Factory Bridge - connects Yamama Subscriptions to the Provisioning Factory
 * 
 * Triggers store creation when a subscription payment succeeds,
 * receives webhook callbacks, and shows provisioning status to customers.
 * 
 * @version 2.0.0 - Added plan_features support
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// Settings
// =========================================================================

function yaamama_factory_settings() {
	$defaults = array(
		'factory_url'    => 'http://164.90.227.203',
		'api_key'        => 'dev-secret-key-change-in-production',
		'webhook_secret' => '',
	);
	$settings = get_option( 'yaamama_factory_settings', $defaults );
	$settings = wp_parse_args( $settings, $defaults );

	if ( strpos( $settings['factory_url'], ':3000' ) !== false ) {
		$settings['factory_url'] = str_replace( ':3000', '', $settings['factory_url'] );
		update_option( 'yaamama_factory_settings', $settings );
	}

	return $settings;
}

// =========================================================================
// Plan Features - Get features based on plan name
// =========================================================================

/**
 * Get plan features by plan name.
 * Reads from DB first (dynamic features); falls back to hardcoded legacy map.
 */
function yaamama_get_plan_features( $plan_name ) {
	$defaults = function_exists( 'yaamama_subscriptions_default_features' )
		? yaamama_subscriptions_default_features()
		: array(
			'store_type'         => 'retail',
			'max_products'       => 50,
			'max_admins'         => 2,
			'electronic_payment' => false,
			'coupons'            => false,
			'loyalty_points'     => false,
			'shipping'           => false,
			'analytics_advanced' => false,
			'code_snippets'      => false,
			'custom_domain'      => false,
			'sms'                => false,
			'whatsapp'           => false,
			'support_priority'   => 'standard',
		);

	// --- Try database first ---
	if ( function_exists( 'yaamama_subscriptions_tables' ) ) {
		global $wpdb;
		$tables = yaamama_subscriptions_tables();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT features FROM {$tables['plans']} WHERE name = %s AND status = 'active' LIMIT 1",
				$plan_name
			),
			ARRAY_A
		);
		if ( $row && ! empty( $row['features'] ) ) {
			$db_features = json_decode( $row['features'], true );
			if ( is_array( $db_features ) ) {
				return array_merge( $defaults, $db_features );
			}
		}
	}

	// --- Legacy hardcoded fallback ---
	$legacy = array(
		'basic' => array(
			'store_type' => 'retail', 'max_products' => 50, 'max_admins' => 2,
			'electronic_payment' => false, 'coupons' => false, 'loyalty_points' => false,
			'shipping' => false, 'analytics_advanced' => false, 'code_snippets' => false,
			'custom_domain' => false, 'sms' => false, 'whatsapp' => false, 'support_priority' => 'standard',
		),
		'professional' => array(
			'store_type' => 'retail', 'max_products' => 500, 'max_admins' => 5,
			'electronic_payment' => true, 'coupons' => true, 'loyalty_points' => true,
			'shipping' => true, 'analytics_advanced' => false, 'code_snippets' => false,
			'custom_domain' => true, 'sms' => true, 'whatsapp' => false, 'support_priority' => 'priority',
		),
		'enterprise' => array(
			'store_type' => 'retail', 'max_products' => -1, 'max_admins' => -1,
			'electronic_payment' => true, 'coupons' => true, 'loyalty_points' => true,
			'shipping' => true, 'analytics_advanced' => true, 'code_snippets' => true,
			'custom_domain' => true, 'sms' => true, 'whatsapp' => true, 'support_priority' => 'vip',
		),
	);

	$legacy = apply_filters( 'yaamama_plans_features', $legacy );
	$plan_key = strtolower( sanitize_title( $plan_name ) );

	if ( isset( $legacy[ $plan_key ] ) ) {
		return array_merge( $defaults, $legacy[ $plan_key ] );
	}

	foreach ( $legacy as $key => $features ) {
		if ( stripos( $plan_name, $key ) !== false || stripos( $plan_key, $key ) !== false ) {
			return array_merge( $defaults, $features );
		}
	}

	return $defaults;
}

/**
 * Get store type from theme ID
 */
function yaamama_get_store_type_from_theme( $theme_id ) {
	$booking_themes = array( 'my-clinic-theme', 'beauty-time-theme', 'beauty-care-theme' );
	$rental_themes  = array( 'my-car-theme' );

	// Allow filtering theme types
	$booking_themes = apply_filters( 'yaamama_booking_themes', $booking_themes );
	$rental_themes  = apply_filters( 'yaamama_rental_themes', $rental_themes );

	if ( in_array( $theme_id, $booking_themes, true ) ) {
		return 'booking';
	}
	if ( in_array( $theme_id, $rental_themes, true ) ) {
		return 'rental';
	}
	return 'retail';
}

/**
 * Sync features when subscription plan changes
 */
function yaamama_factory_sync_features( $subscription_id, $new_plan_price_id ) {
	$store_id = get_option( "yaamama_store_for_sub_{$subscription_id}", '' );
	if ( ! $store_id ) {
		return false;
	}

	$price_row = yaamama_subscriptions_get_price( $new_plan_price_id );
	$plan = $price_row ? yaamama_subscriptions_get_plan( (int) $price_row['plan_id'] ) : null;
	$plan_name = $plan ? $plan['name'] : 'basic';

	$features = yaamama_get_plan_features( $plan_name );

	$defaults = function_exists( 'yaamama_subscriptions_default_features' )
		? yaamama_subscriptions_default_features()
		: array();
	$features = array_merge( $defaults, $features );

	$sub = null;
	if ( function_exists( 'yaamama_subscriptions_tables' ) ) {
		global $wpdb;
		$tables = yaamama_subscriptions_tables();
		$sub = $wpdb->get_row( $wpdb->prepare(
			"SELECT product_id FROM {$tables['subs']} WHERE id = %d", $subscription_id
		), ARRAY_A );
	}

	if ( $sub ) {
		$product = wc_get_product( $sub['product_id'] );
		$theme_id = $product ? $product->get_meta( '_yamama_theme_id' ) : '';
		if ( $theme_id ) {
			$features['store_type'] = yaamama_get_store_type_from_theme( $theme_id );
		}
	}

	$result = yaamama_factory_api_call( 'POST', "/stores/{$store_id}/sync-features", array(
		'plan_id'    => $plan_name,
		'features'   => $features,
		'store_type' => $features['store_type'] ?? 'retail',
	) );

	if ( is_wp_error( $result ) ) {
		error_log( "[Yamama Factory] Failed to sync features for store {$store_id}: " . $result->get_error_message() );
		return false;
	}

	yaamama_subscriptions_add_event( $subscription_id, 'features_synced', array(
		'store_id'   => $store_id,
		'plan_id'    => $plan_name,
		'store_type' => $features['store_type'] ?? 'retail',
	) );

	return true;
}

// =========================================================================
// Admin Settings Page
// =========================================================================

function yaamama_factory_settings_page() {
	add_submenu_page(
		'yaamama-subs',
		'Factory Settings',
		'Factory (المصنع)',
		'manage_options',
		'yaamama-factory-settings',
		'yaamama_factory_render_settings'
	);
}
add_action( 'admin_menu', 'yaamama_factory_settings_page', 20 );
add_action( 'network_admin_menu', 'yaamama_factory_settings_page', 20 );

function yaamama_factory_render_settings() {
	if ( ! current_user_can( 'manage_options' ) && ! is_super_admin() ) return;
	$settings = yaamama_factory_settings();
	?>
	<div class="wrap">
		<h1>إعدادات المصنع (Factory)</h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'yaamama_save_factory_settings', 'yaamama_factory_nonce' ); ?>
			<input type="hidden" name="action" value="yaamama_save_factory_settings">
			<table class="form-table">
				<tr>
					<th><label>Factory URL</label></th>
					<td><input type="url" name="factory_url" value="<?php echo esc_attr( $settings['factory_url'] ); ?>" class="regular-text" placeholder="http://localhost:3000"></td>
				</tr>
				<tr>
					<th><label>API Key</label></th>
					<td><input type="text" name="api_key" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label>Webhook Secret</label></th>
					<td>
						<input type="text" name="webhook_secret" value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>" class="regular-text">
						<p class="description">اتركه فارغًا إذا لم تضبطه في المصنع</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'حفظ' ); ?>
		</form>

		<hr>
		<h2>اختبار الاتصال</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'yaamama_test_factory', 'yaamama_test_factory_nonce' ); ?>
			<input type="hidden" name="action" value="yaamama_test_factory">
			<?php submit_button( 'فحص الاتصال بالمصنع', 'secondary' ); ?>
		</form>

		<hr>
		<h2>خريطة الميزات</h2>
		<p>الميزات المتاحة لكل خطة (يتم قراءتها من قاعدة البيانات):</p>
		<?php
		global $wpdb;
		$_tables = yaamama_subscriptions_tables();
		$_all_plans = $wpdb->get_results( "SELECT name, features, is_free FROM {$_tables['plans']} WHERE status = 'active' ORDER BY id ASC", ARRAY_A );
		if ( $_all_plans ) :
			$_feature_labels = function_exists( 'yaamama_subscriptions_feature_labels' ) ? yaamama_subscriptions_feature_labels() : array();
		?>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th>الميزة</th>
					<?php foreach ( $_all_plans as $_ap ) : ?>
						<th><?php echo esc_html( $_ap['name'] ); ?><?php echo ! empty( $_ap['is_free'] ) ? ' (مجانية)' : ''; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				$_plans_data = array();
				foreach ( $_all_plans as $_ap ) {
					$_plans_data[] = function_exists( 'yaamama_subscriptions_parse_plan_features' )
						? yaamama_subscriptions_parse_plan_features( $_ap )
						: ( ! empty( $_ap['features'] ) ? json_decode( $_ap['features'], true ) : array() );
				}
				foreach ( $_feature_labels as $fk => $fv ) :
				?>
				<tr>
					<td><?php echo esc_html( $fv['label'] ); ?></td>
					<?php foreach ( $_plans_data as $_pd ) :
						$val = $_pd[ $fk ] ?? false;
					?>
					<td>
						<?php if ( is_bool( $val ) ) : ?>
							<?php echo $val ? '✅' : '❌'; ?>
						<?php elseif ( is_int( $val ) || is_numeric( $val ) ) : ?>
							<?php echo (int) $val === -1 ? '∞' : esc_html( $val ); ?>
						<?php else : ?>
							<?php echo esc_html( $val ); ?>
						<?php endif; ?>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
			<p>لا توجد باقات نشطة. أنشئ باقات من صفحة <a href="<?php echo esc_url( admin_url( 'admin.php?page=yaamama-subs' ) ); ?>">خطط الاشتراكات</a>.</p>
		<?php endif; ?>
	</div>
	<?php
}

function yaamama_factory_handle_save_settings() {
	check_admin_referer( 'yaamama_save_factory_settings', 'yaamama_factory_nonce' );
	if ( ! current_user_can( 'manage_options' ) && ! is_super_admin() ) wp_die( 'Unauthorized' );

	$settings = array(
		'factory_url'    => esc_url_raw( wp_unslash( $_POST['factory_url'] ?? '' ) ),
		'api_key'        => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
		'webhook_secret' => sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ),
	);
	update_option( 'yaamama_factory_settings', $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-factory-settings&saved=1' ) );
	exit;
}
add_action( 'admin_post_yaamama_save_factory_settings', 'yaamama_factory_handle_save_settings' );

function yaamama_factory_handle_test() {
	check_admin_referer( 'yaamama_test_factory', 'yaamama_test_factory_nonce' );
	if ( ! current_user_can( 'manage_options' ) && ! is_super_admin() ) wp_die( 'Unauthorized' );

	$result = yaamama_factory_api_call( 'GET', '/health' );
	if ( is_wp_error( $result ) ) {
		wp_die( 'فشل الاتصال: ' . $result->get_error_message() );
	}
	wp_die( 'الاتصال ناجح! الحالة: ' . wp_json_encode( $result ), 'نجاح', array( 'back_link' => true ) );
}
add_action( 'admin_post_yaamama_test_factory', 'yaamama_factory_handle_test' );

// =========================================================================
// API Helper
// =========================================================================

function yaamama_factory_api_call( $method, $endpoint, $body = null, $timeout = 15 ) {
	$settings = yaamama_factory_settings();
	$url      = rtrim( $settings['factory_url'], '/' ) . $endpoint;

	$args = array(
		'method'  => $method,
		'timeout' => (int) $timeout,
		'headers' => array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $settings['api_key'],
		),
	);

	if ( $body ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );


	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 400 ) {
		$error_msg = $data['error'] ?? "HTTP $code";
		return new WP_Error( 'factory_error', $error_msg );
	}

	return $data;
}
// =========================================================================
// Trigger provisioning when first payment succeeds
// =========================================================================

function yaamama_factory_on_payment_success( $order_id, $old_status, $new_status, $order ) {
	$allowed = array( 'processing', 'completed', 'on-hold' );
	if ( ! in_array( $new_status, $allowed, true ) ) {
		error_log( "[Yamama Factory] Skipped order #{$order_id}: status '{$new_status}' not in allowed list." );
		return;
	}

	$subscription_id = (int) $order->get_meta( 'yaamama_subscription_id' );
	if ( ! $subscription_id ) {
		error_log( "[Yamama Factory] Skipped order #{$order_id}: no yaamama_subscription_id meta." );
		return;
	}

	$is_renewal = $order->get_meta( 'yaamama_is_renewal' ) === '1';
	$is_switch  = $order->get_meta( 'yaamama_is_switch' ) === '1';
	
	// Handle plan switch - sync features
	if ( $is_switch ) {
		$new_plan_price_id = (int) $order->get_meta( 'yaamama_switch_plan_price_id' );
		if ( $new_plan_price_id ) {
			yaamama_factory_sync_features( $subscription_id, $new_plan_price_id );
		}
		return;
	}
	
	if ( $is_renewal ) {
		error_log( "[Yamama Factory] Skipped order #{$order_id}: is renewal." );
		return;
	}

	$existing_store_id = get_option( "yaamama_store_for_sub_{$subscription_id}" );
	if ( $existing_store_id ) {
		error_log( "[Yamama Factory] Skipped sub #{$subscription_id}: already has store {$existing_store_id}." );
		return;
	}

	yaamama_factory_provision_subscription( $subscription_id, $order );
}
add_action( 'woocommerce_order_status_changed', 'yaamama_factory_on_payment_success', 20, 4 );

/**
 * Core provisioning logic — used by both the auto-trigger and the cron fallback.
 * NOW WITH plan_features SUPPORT
 */
function yaamama_factory_provision_subscription( $subscription_id, $order = null ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();

	$sub = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$tables['subs']} WHERE id = %d", $subscription_id
	), ARRAY_A );

	if ( ! $sub ) {
		error_log( "[Yamama Factory] Provision aborted: sub #{$subscription_id} not found." );
		return false;
	}

	$existing = get_option( "yaamama_store_for_sub_{$subscription_id}" );
	if ( $existing ) {
		return false;
	}

	$price_row = yaamama_subscriptions_get_price( $sub['plan_price_id'] );
	$plan      = $price_row ? yaamama_subscriptions_get_plan( (int) $price_row['plan_id'] ) : null;
	$plan_name = $plan ? $plan['name'] : 'basic';

	$product  = wc_get_product( $sub['product_id'] );
	$theme_id = $product ? $product->get_meta( '_yamama_theme_id' ) : '';
	if ( ! $theme_id ) {
		$theme_id = $product ? $product->get_sku() : '';
	}
	if ( ! $theme_id ) {
		$theme_id = $product ? sanitize_title( $product->get_name() ) : 'theme-default';
	}

	$store_name = $sub['store_name'] ?? '';
	if ( ! $store_name && $order ) {
		$store_name = $order->get_meta( 'yaamama_store_name' );
	}
	if ( ! $store_name && ! empty( $sub['last_order_id'] ) ) {
		$fallback_order = wc_get_order( (int) $sub['last_order_id'] );
		if ( $fallback_order ) {
			$store_name = $fallback_order->get_meta( 'yaamama_store_name' );
		}
	}

	$user           = get_userdata( $sub['user_id'] );
	$customer_email = $user ? $user->user_email : '';
	$customer_name  = $user ? $user->display_name : '';
	if ( ! $customer_email && $order ) {
		$customer_email = $order->get_billing_email();
	}
	if ( ! $customer_name && $order ) {
		$customer_name = $order->get_billing_first_name();
	}

	// === NEW: Get plan features and store type ===
	$plan_features = yaamama_get_plan_features( $plan_name );
	$store_type = yaamama_get_store_type_from_theme( $theme_id );
	$plan_features['store_type'] = $store_type;

	error_log( "[Yamama Factory] Provisioning sub #{$subscription_id}: theme={$theme_id}, plan={$plan_name}, store_type={$store_type}" );

	$result = yaamama_factory_api_call( 'POST', '/provision', array(
		'template_id'    => $theme_id,
		'plan_id'        => $plan_name,
		'theme_id'       => $theme_id,
		'store_name'     => $store_name,
		'customer_email' => $customer_email,
		'customer_name'  => $customer_name,
		// NEW: Send plan features
		'plan_features'  => $plan_features,
		'store_type'     => $store_type,
	) );


	if ( is_wp_error( $result ) ) {
		update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'provision_error' );
		update_option( "yaamama_provision_error_for_sub_{$subscription_id}", $result->get_error_message() );
		update_option( "yaamama_provision_error_time_for_sub_{$subscription_id}", time() );
		yaamama_subscriptions_add_event( $subscription_id, 'provision_failed', array(
			'error' => $result->get_error_message(),
		) );
		error_log( "[Yamama Factory] Provisioning FAILED for sub #{$subscription_id}: " . $result->get_error_message() );
		return false;
	}

	$store_id = $result['store_id'] ?? '';
	$job_id   = $result['job_id'] ?? '';

	update_option( "yaamama_store_for_sub_{$subscription_id}", $store_id );
	update_option( "yaamama_job_for_sub_{$subscription_id}", $job_id );
	update_option( "yaamama_store_plan_for_sub_{$subscription_id}", $plan_name );

	$wpdb->update(
		$tables['subs'],
		array( 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $subscription_id )
	);

	yaamama_subscriptions_add_event( $subscription_id, 'provision_started', array(
		'store_id'      => $store_id,
		'job_id'        => $job_id,
		'template_id'   => $theme_id,
		'plan_id'       => $plan_name,
		'store_type'    => $store_type,
		'plan_features' => $plan_features,
	) );

	error_log( "[Yamama Factory] Provisioning SUCCESS for sub #{$subscription_id}: store={$store_id}, job={$job_id}" );
	return true;
}

// =========================================================================
// Webhook endpoint: Factory notifies us when provisioning completes/fails
// =========================================================================

function yaamama_factory_register_webhook() {
	register_rest_route( 'yamama/v1', '/store-callback', array(
		'methods'             => 'POST',
		'callback'            => 'yaamama_factory_handle_webhook',
		'permission_callback' => 'yaamama_factory_verify_webhook',
	) );
}
add_action( 'rest_api_init', 'yaamama_factory_register_webhook' );

function yaamama_factory_verify_webhook( $request ) {
	$settings = yaamama_factory_settings();
	if ( empty( $settings['webhook_secret'] ) ) {
		return true;
	}

	$signature = $request->get_header( 'X-Yamama-Signature' );
	if ( ! $signature ) return false;

	$body    = $request->get_body();
	$expected = hash_hmac( 'sha256', $body, $settings['webhook_secret'] );

	return hash_equals( $expected, $signature );
}

function yaamama_factory_handle_webhook( $request ) {
	$data     = $request->get_json_params();
	$event    = $data['event'] ?? '';
	$store_id = $data['store_id'] ?? '';
	$job_id   = $data['job_id'] ?? '';

	if ( ! $store_id ) {
		return new WP_REST_Response( array( 'error' => 'missing store_id' ), 400 );
	}

	global $wpdb;
	$subscription_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_value = %s AND option_name LIKE 'yaamama_store_for_sub_%%'",
		$store_id
	) );

	if ( $subscription_id ) {
		$subscription_id = (int) str_replace( 'yaamama_store_for_sub_', '', $subscription_id );
	}

	if ( 'provision.completed' === $event ) {
		$store_url = $data['store_url'] ?? '';

		if ( $subscription_id ) {
			update_option( "yaamama_store_url_for_sub_{$subscription_id}", $store_url );
			update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'active' );
			yaamama_subscriptions_add_event( $subscription_id, 'provision_completed', array(
				'store_id'  => $store_id,
				'store_url' => $store_url,
			) );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	if ( 'provision.failed' === $event ) {
		$error = $data['error'] ?? 'unknown';

		if ( $subscription_id ) {
			update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'failed' );
			yaamama_subscriptions_add_event( $subscription_id, 'provision_failed', array(
				'store_id' => $store_id,
				'error'    => $error,
			) );
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	return new WP_REST_Response( array( 'received' => true, 'event' => $event ), 200 );
}

// =========================================================================
// Poll provisioning status (AJAX for the customer dashboard)
// =========================================================================

function yaamama_factory_step_labels() {
	return array(
		'reserve_slot'      => 'حجز مساحة',
		'move_files'        => 'نقل الملفات',
		'setup_database'    => 'إعداد قاعدة البيانات',
		'update_wp_config'  => 'تحديث الإعدادات',
		'update_site_url'   => 'ضبط رابط المتجر',
		'create_vhost'      => 'إعداد الخادم',
		'issue_ssl'         => 'إصدار شهادة الأمان',
		'apply_theme'       => 'تطبيق القالب',
		'setup_admin'       => 'إعداد حساب المدير',
		'inject_store_meta'      => 'ربط المتجر',
		'configure_woocommerce'  => 'ضبط إعدادات المتجر',
		'health_check'           => 'فحص المتجر',
		'mark_active'       => 'تفعيل المتجر',
	);
}

function yaamama_factory_ajax_check_status() {
	if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );

	$subscription_id = (int) ( $_REQUEST['subscription_id'] ?? 0 );
	if ( ! $subscription_id ) wp_send_json_error( 'Invalid subscription' );

	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$sub    = $wpdb->get_row( $wpdb->prepare(
		"SELECT user_id FROM {$tables['subs']} WHERE id = %d", $subscription_id
	), ARRAY_A );

	if ( ! $sub || (int) $sub['user_id'] !== get_current_user_id() ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$store_id = get_option( "yaamama_store_for_sub_{$subscription_id}", '' );
	$job_id   = get_option( "yaamama_job_for_sub_{$subscription_id}", '' );


	if ( ! $job_id ) {
		$provision_status = get_option( "yaamama_store_status_for_sub_{$subscription_id}", '' );
		if ( $provision_status === 'provision_error' ) {
			$error_msg = get_option( "yaamama_provision_error_for_sub_{$subscription_id}", '' );
			wp_send_json_success( array(
				'status'  => 'failed',
				'message' => $error_msg ?: 'فشل في إنشاء المتجر',
				'can_retry' => true,
			) );
		}
		wp_send_json_success( array( 'status' => 'not_started' ) );
	}

	$cached_status = get_option( "yaamama_store_status_for_sub_{$subscription_id}", '' );
	$cached_url    = get_option( "yaamama_store_url_for_sub_{$subscription_id}", '' );

	if ( $cached_status === 'active' && $cached_url ) {
		wp_send_json_success( array(
			'status'    => 'active',
			'store_id'  => $store_id,
			'store_url' => $cached_url,
			'progress'  => 100,
		) );
	}

	$result = yaamama_factory_api_call( 'GET', "/jobs/{$job_id}" );
	if ( is_wp_error( $result ) ) {
		wp_send_json_success( array(
			'status'  => $cached_status ?: 'unknown',
			'message' => 'لم نتمكن من الاتصال بالمصنع',
		) );
	}

	$job_status   = $result['status'] ?? 'unknown';
	$current_step = $result['current_step'] ?? null;
	$api_steps    = $result['steps'] ?? array();
	$store_url    = '';

	if ( 'completed' === $job_status ) {
		$store_result = yaamama_factory_api_call( 'GET', "/stores/{$store_id}" );
		if ( ! is_wp_error( $store_result ) ) {
			$store_url = $store_result['store_url'] ?? '';
			update_option( "yaamama_store_url_for_sub_{$subscription_id}", $store_url );
			update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'active' );
		}
	}

	$step_labels  = yaamama_factory_step_labels();
	$step_order   = array_keys( $step_labels );
	$total_steps  = count( $step_order );
	$completed    = 0;
	$steps_info   = array();

	if ( ! empty( $api_steps ) ) {
		foreach ( $api_steps as $s ) {
			$name   = $s['step'] ?? $s['name'] ?? $s['step_name'] ?? '';
			$status = $s['status'] ?? 'pending';
			if ( 'completed' === $status ) {
				$completed++;
			}
			$steps_info[] = array(
				'name'   => $name,
				'label'  => $step_labels[ $name ] ?? $name,
				'status' => $status,
			);
		}
	} elseif ( $current_step ) {
		$current_index = array_search( $current_step, $step_order, true );
		if ( false === $current_index ) {
			$current_index = -1;
		}
		foreach ( $step_order as $i => $step_name ) {
			if ( $i < $current_index ) {
				$status = 'completed';
				$completed++;
			} elseif ( $i === $current_index ) {
				$status = 'in_progress';
			} else {
				$status = 'pending';
			}
			$steps_info[] = array(
				'name'   => $step_name,
				'label'  => $step_labels[ $step_name ],
				'status' => $status,
			);
		}
	}

	$progress = $total_steps > 0 ? round( ( $completed / $total_steps ) * 100 ) : 0;
	if ( 'completed' === $job_status ) {
		$progress = 100;
	}

	wp_send_json_success( array(
		'status'       => $job_status,
		'store_id'     => $store_id,
		'store_url'    => $store_url,
		'current_step' => $current_step,
		'step_label'   => $step_labels[ $current_step ] ?? $current_step,
		'progress'     => $progress,
		'steps'        => $steps_info,
	) );
}
add_action( 'wp_ajax_yaamama_check_provision_status', 'yaamama_factory_ajax_check_status' );

// =========================================================================
// Cron fallback: auto-provision subscriptions that were missed
// =========================================================================

function yaamama_factory_schedule_cron() {
	if ( ! wp_next_scheduled( 'yaamama_factory_cron_provision' ) ) {
		wp_schedule_event( time(), 'every_five_minutes', 'yaamama_factory_cron_provision' );
	}
}
add_action( 'wp', 'yaamama_factory_schedule_cron' );

function yaamama_factory_cron_interval( $schedules ) {
	$schedules['every_five_minutes'] = array( 'interval' => 300, 'display' => 'Every 5 Minutes' );
	return $schedules;
}
add_filter( 'cron_schedules', 'yaamama_factory_cron_interval' );

function yaamama_factory_cron_provision_handler() {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$subs = $wpdb->get_results( "SELECT s.id FROM {$tables['subs']} s WHERE s.status = 'active' AND s.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY s.id DESC LIMIT 10", ARRAY_A );
	if ( ! $subs ) return;
	foreach ( $subs as $row ) {
		$sub_id = (int) $row['id'];
		if ( get_option( "yaamama_store_for_sub_{$sub_id}" ) ) continue;
		if ( get_option( "yaamama_job_for_sub_{$sub_id}" ) ) continue;

		$error_status = get_option( "yaamama_store_status_for_sub_{$sub_id}", '' );
		$error_time   = (int) get_option( "yaamama_provision_error_time_for_sub_{$sub_id}", 0 );
		if ( $error_status === 'provision_error' && $error_time > 0 ) {
			$minutes_since_error = ( time() - $error_time ) / 60;
			if ( $minutes_since_error < 30 ) {
				continue;
			}
			delete_option( "yaamama_store_status_for_sub_{$sub_id}" );
			delete_option( "yaamama_provision_error_for_sub_{$sub_id}" );
			delete_option( "yaamama_provision_error_time_for_sub_{$sub_id}" );
		}

		yaamama_factory_provision_subscription( $sub_id );
		usleep( 500000 );
	}
}
add_action( 'yaamama_factory_cron_provision', 'yaamama_factory_cron_provision_handler' );

// =========================================================================
// Retry provisioning
// =========================================================================

function yaamama_factory_retry_provision() {
	if ( ! isset( $_POST['yaamama_retry_nonce'] ) || ! wp_verify_nonce( $_POST['yaamama_retry_nonce'], 'yaamama_retry_provision' ) ) wp_die( 'Invalid request' );
	if ( ! is_user_logged_in() ) { wp_safe_redirect( home_url( '/login' ) ); exit; }
	$subscription_id = (int) ( $_POST['subscription_id'] ?? 0 );
	$store_id = get_option( "yaamama_store_for_sub_{$subscription_id}", '' );
	if ( ! $store_id ) wp_die( 'No store' );
	$result = yaamama_factory_api_call( 'POST', "/provision/{$store_id}/retry" );
	if ( is_wp_error( $result ) ) wp_die( $result->get_error_message() );
	$new_job_id = $result['job_id'] ?? '';
	if ( $new_job_id ) {
		update_option( "yaamama_job_for_sub_{$subscription_id}", $new_job_id );
		update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'provisioning' );
	}
	wp_safe_redirect( wp_get_referer() ?: home_url( '/my-account#page-2' ) );
	exit;
}
add_action( 'admin_post_yaamama_retry_provision', 'yaamama_factory_retry_provision' );

// =========================================================================
// WooCommerce Product: Theme ID meta field
// =========================================================================

function yaamama_product_theme_id_field() {
	woocommerce_wp_text_input( array( 'id' => '_yamama_theme_id', 'label' => 'Yamama Theme ID', 'placeholder' => 'mallati-theme' ) );
}
add_action( 'woocommerce_product_options_general_product_data', 'yaamama_product_theme_id_field' );

function yaamama_product_theme_id_save( $post_id ) {
	if ( isset( $_POST['_yamama_theme_id'] ) ) update_post_meta( $post_id, '_yamama_theme_id', sanitize_text_field( $_POST['_yamama_theme_id'] ) );
}
add_action( 'woocommerce_process_product_meta', 'yaamama_product_theme_id_save' );

function yaamama_product_theme_id_validate_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->id ) return;

	global $post;
	if ( ! $post ) return;

	$theme_id = get_post_meta( $post->ID, '_yamama_theme_id', true );
	if ( ! empty( $theme_id ) ) return;

	if ( ! function_exists( 'yaamama_subscriptions_tables' ) ) return;
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$linked = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$tables['product_plans']} WHERE product_id = %d",
		$post->ID
	) );

	if ( $linked > 0 ) {
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>تحذير:</strong> هذا المنتج مرتبط بباقات اشتراك لكن <code>_yamama_theme_id</code> غير محدد. ';
		echo 'المتاجر المُنشأة من هذا المنتج قد تستخدم قالب افتراضي بدلاً من القالب المطلوب.';
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'yaamama_product_theme_id_validate_notice' );

// =========================================================================
// Auto-Login
// =========================================================================

function yaamama_factory_ajax_auto_login() {
	if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );
	$subscription_id = (int) ( $_REQUEST['subscription_id'] ?? 0 );
	if ( ! $subscription_id ) wp_send_json_error( 'Invalid subscription' );
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$sub = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM {$tables['subs']} WHERE id = %d", $subscription_id ), ARRAY_A );
	if ( ! $sub || (int) $sub['user_id'] !== get_current_user_id() ) wp_send_json_error( 'Unauthorized' );
	$store_id = get_option( "yaamama_store_for_sub_{$subscription_id}", '' );
	if ( ! $store_id ) wp_send_json_error( 'No store' );
	$result = yaamama_factory_api_call( 'POST', "/stores/{$store_id}/auto-login-token", null, 30 );
	if ( is_wp_error( $result ) ) {
		error_log( '[Yamama Factory] Auto-login failed for store ' . $store_id . ': ' . $result->get_error_message() );
		wp_send_json_error( $result->get_error_message() );
	}
	$login_url = $result['login_url'] ?? '';
	if ( ! $login_url ) wp_send_json_error( 'No login URL' );
	wp_send_json_success( array( 'login_url' => $login_url ) );
}
add_action( 'wp_ajax_yaamama_store_auto_login', 'yaamama_factory_ajax_auto_login' );

// =========================================================================
// Thank-you page
// =========================================================================

function yaamama_factory_thankyou_notice( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) return;
	$subscription_id = (int) $order->get_meta( 'yaamama_subscription_id' );
	if ( ! $subscription_id ) return;
	if ( $order->get_meta( 'yaamama_is_renewal' ) === '1' || $order->get_meta( 'yaamama_is_switch' ) === '1' ) return;
	$store_status = get_option( "yaamama_store_status_for_sub_{$subscription_id}", '' );
	$store_url = get_option( "yaamama_store_url_for_sub_{$subscription_id}", '' );
	$job_id = get_option( "yaamama_job_for_sub_{$subscription_id}", '' );
	echo '<div style="background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:24px;margin:24px 0;text-align:center;direction:rtl;">';
	if ( $store_status === 'active' && $store_url ) {
		echo '<div style="font-size:48px;margin-bottom:12px;">🎉</div><h3 style="color:#059669;">متجرك جاهز!</h3>';
		echo '<a href="' . esc_url( $store_url ) . '" target="_blank" style="background:#059669;color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:bold;">الذهاب إلى المتجر</a>';
	} elseif ( $job_id ) {
		echo '<div style="width:40px;height:40px;border:4px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:yaamama-spin 1s linear infinite;margin:0 auto 12px;"></div>';
		echo '<h3 style="color:#1e40af;">جاري تجهيز متجرك...</h3><style>@keyframes yaamama-spin{to{transform:rotate(360deg)}}</style>';
	} else {
		echo '<h3 style="color:#059669;">تم الدفع بنجاح!</h3>';
	}
	echo '</div>';
}
add_action( 'woocommerce_thankyou', 'yaamama_factory_thankyou_notice', 5 );

/**
 * Redirect subscription orders to custom thank-you page so progress bar works.
 */
function yaamama_factory_thankyou_return_url( $return_url, $order ) {
	if ( ! $order || ! ( $order instanceof \WC_Order ) ) return $return_url;
	$sub_id = (int) $order->get_meta( 'yaamama_subscription_id' );
	if ( ! $sub_id ) return $return_url;
	$thank_you_page = get_page_by_path( 'thank-you' );
	if ( ! $thank_you_page ) return $return_url;
	return home_url( '/thank-you/?order_id=' . $order->get_id() );
}
add_filter( 'woocommerce_get_return_url', 'yaamama_factory_thankyou_return_url', 10, 2 );
