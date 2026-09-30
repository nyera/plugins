<?php
/**
 * Plugin Name: Yamama Subscriptions
 * Description: Custom subscriptions system for Yamama.
 * Version: 1.0.0
 * Author: Yamama
 */

defined( 'ABSPATH' ) || exit;

define( 'YAAMAMA_SUBSCRIPTIONS_VERSION', '1.2.0' );

// Factory Bridge: connects subscriptions to the provisioning engine
require_once __DIR__ . '/includes/factory-bridge.php';

function yaamama_subscriptions_tables() {
	global $wpdb;
	return array(
		'plans'         => $wpdb->prefix . 'yaamama_plans',
		'plan_prices'   => $wpdb->prefix . 'yaamama_plan_prices',
		'product_plans' => $wpdb->prefix . 'yaamama_product_plans',
		'subs'          => $wpdb->prefix . 'yaamama_subscriptions',
		'events'        => $wpdb->prefix . 'yaamama_subscription_events',
	);
}

function yaamama_subscriptions_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$tables  = yaamama_subscriptions_tables();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$tables['plans']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		description TEXT NULL,
		features LONGTEXT NULL,
		is_free TINYINT(1) NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id)
	) {$charset};

	CREATE TABLE {$tables['plan_prices']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		plan_id BIGINT UNSIGNED NOT NULL,
		period VARCHAR(10) NOT NULL,
		price DECIMAL(12,2) NOT NULL DEFAULT 0,
		trial_days INT NOT NULL DEFAULT 0,
		paymob_plan_id VARCHAR(100) NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY plan_id (plan_id)
	) {$charset};

	CREATE TABLE {$tables['product_plans']} (
		product_id BIGINT UNSIGNED NOT NULL,
		plan_id BIGINT UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (product_id, plan_id),
		KEY plan_id (plan_id)
	) {$charset};

	CREATE TABLE {$tables['subs']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		product_id BIGINT UNSIGNED NOT NULL,
		plan_price_id BIGINT UNSIGNED NOT NULL,
		store_name VARCHAR(255) NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		starts_at DATETIME NULL,
		ends_at DATETIME NULL,
		next_billing_at DATETIME NULL,
		paymob_subscription_id VARCHAR(100) NULL,
		paymob_order_id VARCHAR(100) NULL,
		paymob_txn_id VARCHAR(100) NULL,
		paymob_customer_id VARCHAR(100) NULL,
		paymob_card_token VARCHAR(255) NULL,
		initial_order_id BIGINT UNSIGNED NULL,
		last_order_id BIGINT UNSIGNED NULL,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY product_id (product_id),
		KEY plan_price_id (plan_price_id)
	) {$charset};

	CREATE TABLE {$tables['events']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		subscription_id BIGINT UNSIGNED NOT NULL,
		event_type VARCHAR(50) NOT NULL,
		payload LONGTEXT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY subscription_id (subscription_id)
	) {$charset};";

	dbDelta( $sql );
	update_option( 'yaamama_subscriptions_version', YAAMAMA_SUBSCRIPTIONS_VERSION );
	if ( ! wp_next_scheduled( 'yaamama_process_renewals' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'yaamama_process_renewals' );
	}
}
register_activation_hook( __FILE__, 'yaamama_subscriptions_install' );

function yaamama_subscriptions_maybe_upgrade() {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();

	$suppress = $wpdb->suppress_errors();

	$subs_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tables['subs']}'" );
	if ( $subs_exists ) {
		$col = $wpdb->get_var( "SHOW COLUMNS FROM {$tables['subs']} LIKE 'store_name'" );
		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$tables['subs']} ADD COLUMN store_name VARCHAR(255) NULL AFTER plan_price_id" );
		}
	}

	$plans_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tables['plans']}'" );
	if ( $plans_exists ) {
		$col_features = $wpdb->get_var( "SHOW COLUMNS FROM {$tables['plans']} LIKE 'features'" );
		if ( empty( $col_features ) ) {
			$wpdb->query( "ALTER TABLE {$tables['plans']} ADD COLUMN features LONGTEXT NULL AFTER description" );
		}
		$col_free = $wpdb->get_var( "SHOW COLUMNS FROM {$tables['plans']} LIKE 'is_free'" );
		if ( empty( $col_free ) ) {
			$wpdb->query( "ALTER TABLE {$tables['plans']} ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0 AFTER features" );
		}
	}

	$wpdb->suppress_errors( $suppress );
	update_option( 'yaamama_subscriptions_version', YAAMAMA_SUBSCRIPTIONS_VERSION );
}
add_action( 'init', 'yaamama_subscriptions_maybe_upgrade' );

function yaamama_subscriptions_deactivate() {
	$timestamp = wp_next_scheduled( 'yaamama_process_renewals' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'yaamama_process_renewals' );
	}
}
register_deactivation_hook( __FILE__, 'yaamama_subscriptions_deactivate' );

function yaamama_subscriptions_maybe_schedule_renewals() {
	if ( ! wp_next_scheduled( 'yaamama_process_renewals' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'yaamama_process_renewals' );
	}
}
add_action( 'init', 'yaamama_subscriptions_maybe_schedule_renewals' );

function yaamama_subscriptions_settings() {
	$settings = get_option( 'yaamama_subscriptions_settings', array() );

	if ( isset( $settings['paymob_gateway_id'] ) && ! isset( $settings['payment_gateway_id'] ) ) {
		$settings['payment_gateway_id'] = $settings['paymob_gateway_id'];
		unset( $settings['paymob_gateway_id'] );
		update_option( 'yaamama_subscriptions_settings', $settings );
	}

	if ( ! isset( $settings['payment_gateway_id'] ) ) {
		$settings['payment_gateway_id'] = '';
	}

	$available = function_exists( 'WC' ) ? WC()->payment_gateways()->get_available_payment_gateways() : array();
	$enabled   = yaamama_subscriptions_get_payment_gateways();
	$check     = $available ? array_keys( $available ) : array_keys( $enabled );

	if ( $check && ( empty( $settings['payment_gateway_id'] ) || ! in_array( $settings['payment_gateway_id'], $check, true ) ) ) {
		$settings['payment_gateway_id'] = (string) $check[0];
		update_option( 'yaamama_subscriptions_settings', $settings );
	}

	return $settings;
}

function yaamama_subscriptions_get_payment_gateways() {
	if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
		return array();
	}

	$gateways = WC_Payment_Gateways::instance()->payment_gateways();
	$options  = array();

	foreach ( $gateways as $gateway ) {
		if ( 'yes' !== $gateway->enabled ) {
			continue;
		}
		$options[ $gateway->id ] = $gateway->get_method_title();
	}

	return $options;
}

function yaamama_subscriptions_get_paymob_gateways() { return yaamama_subscriptions_get_payment_gateways(); }

function yaamama_subscriptions_get_plans_for_product( $product_id ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();

	$plans = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.* FROM {$tables['plans']} p
				INNER JOIN {$tables['product_plans']} pp ON p.id = pp.plan_id
				WHERE pp.product_id = %d AND p.status = 'active'
				ORDER BY p.id ASC",
			$product_id
		),
		ARRAY_A
	);

	foreach ( $plans as &$plan ) {
		$plan['prices'] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tables['plan_prices']}
					WHERE plan_id = %d AND status = 'active'
					ORDER BY period ASC",
				$plan['id']
			),
			ARRAY_A
		);
	}

	return $plans;
}

function yaamama_subscriptions_get_price( $price_id ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$tables['plan_prices']} WHERE id = %d", $price_id ),
		ARRAY_A
	);
}

function yaamama_subscriptions_get_plan( $plan_id ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$tables['plans']} WHERE id = %d", $plan_id ),
		ARRAY_A
	);
}

function yaamama_subscriptions_get_plan_prices_map() {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$rows = $wpdb->get_results( "SELECT * FROM {$tables['plan_prices']}", ARRAY_A );
	$map = array();
	foreach ( $rows as $row ) {
		$plan_id = (int) $row['plan_id'];
		$period  = $row['period'];
		if ( ! isset( $map[ $plan_id ] ) ) {
			$map[ $plan_id ] = array();
		}
		$map[ $plan_id ][ $period ] = $row;
	}

	return $map;
}

function yaamama_subscriptions_upsert_plan_price( $plan_id, $period, $price, $trial_days, $status ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$tables['plan_prices']} WHERE plan_id = %d AND period = %s",
			$plan_id,
			$period
		),
		ARRAY_A
	);

	$data = array(
		'plan_id'    => (int) $plan_id,
		'period'     => $period,
		'price'      => (float) $price,
		'trial_days' => (int) $trial_days,
		'status'     => $status,
		'updated_at' => current_time( 'mysql' ),
	);

	if ( $existing ) {
		$wpdb->update( $tables['plan_prices'], $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}

	$data['created_at'] = current_time( 'mysql' );
	$wpdb->insert( $tables['plan_prices'], $data );
	return (int) $wpdb->insert_id;
}

function yaamama_subscriptions_create_subscription( $args ) {
	global $wpdb;
	$tables   = yaamama_subscriptions_tables();
	$defaults = array(
		'user_id'         => 0,
		'product_id'      => 0,
		'plan_price_id'   => 0,
		'store_name'      => null,
		'status'          => 'pending',
		'starts_at'       => current_time( 'mysql' ),
		'ends_at'         => null,
		'next_billing_at' => null,
		'created_at'      => current_time( 'mysql' ),
		'updated_at'      => current_time( 'mysql' ),
	);
	$data = wp_parse_args( $args, $defaults );

	$wpdb->insert( $tables['subs'], $data );
	return (int) $wpdb->insert_id;
}

function yaamama_subscriptions_add_event( $subscription_id, $event_type, $payload = null ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();

	$wpdb->insert(
		$tables['events'],
		array(
			'subscription_id' => $subscription_id,
			'event_type'      => $event_type,
			'payload'         => $payload ? wp_json_encode( $payload ) : null,
			'created_at'      => current_time( 'mysql' ),
		)
	);
}

function yaamama_subscriptions_admin_menu() {
	add_menu_page(
		'Yamama Subscriptions',
		'Yamama Subs',
		'manage_options',
		'yaamama-subs',
		'yaamama_subscriptions_render_plans',
		'dashicons-update'
	);
	add_submenu_page(
		'yaamama-subs',
		'Plans',
		'Plans',
		'manage_options',
		'yaamama-subs',
		'yaamama_subscriptions_render_plans'
	);
	add_submenu_page(
		'yaamama-subs',
		'Subscriptions',
		'Subscriptions',
		'manage_options',
		'yaamama-subs-subs',
		'yaamama_subscriptions_render_subscriptions'
	);
	add_submenu_page(
		'yaamama-subs',
		'Settings',
		'Settings',
		'manage_options',
		'yaamama-subs-settings',
		'yaamama_subscriptions_render_settings'
	);
}
add_action( 'admin_menu', 'yaamama_subscriptions_admin_menu' );
add_action( 'network_admin_menu', 'yaamama_subscriptions_admin_menu' );

function yaamama_subscriptions_default_features() {
	return array(
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
}

function yaamama_subscriptions_feature_labels() {
	return array(
		'store_type'         => array( 'label' => 'نوع المتجر',       'type' => 'select', 'options' => array( 'retail' => 'تجزئة', 'booking' => 'حجوزات', 'rental' => 'تأجير' ) ),
		'max_products'       => array( 'label' => 'عدد المنتجات',     'type' => 'number', 'hint' => '-1 = غير محدود' ),
		'max_admins'         => array( 'label' => 'عدد المستخدمين',   'type' => 'number', 'hint' => '-1 = غير محدود' ),
		'electronic_payment' => array( 'label' => 'الدفع الإلكتروني', 'type' => 'checkbox' ),
		'coupons'            => array( 'label' => 'الكوبونات',         'type' => 'checkbox' ),
		'loyalty_points'     => array( 'label' => 'نقاط الولاء',      'type' => 'checkbox' ),
		'shipping'           => array( 'label' => 'الشحن',            'type' => 'checkbox' ),
		'analytics_advanced' => array( 'label' => 'تحليلات متقدمة',   'type' => 'checkbox' ),
		'code_snippets'      => array( 'label' => 'أجزاء برمجية',     'type' => 'checkbox' ),
		'custom_domain'      => array( 'label' => 'نطاق مخصص',       'type' => 'checkbox' ),
		'sms'                => array( 'label' => 'رسائل SMS',        'type' => 'checkbox' ),
		'whatsapp'           => array( 'label' => 'رسائل واتساب',     'type' => 'checkbox' ),
		'support_priority'   => array( 'label' => 'أولوية الدعم',     'type' => 'select', 'options' => array( 'standard' => 'عادي', 'priority' => 'أولوية', 'vip' => 'VIP' ) ),
	);
}

function yaamama_subscriptions_parse_plan_features( $plan ) {
	$defaults = yaamama_subscriptions_default_features();
	if ( empty( $plan['features'] ) ) {
		return $defaults;
	}
	$decoded = is_string( $plan['features'] ) ? json_decode( $plan['features'], true ) : $plan['features'];
	if ( ! is_array( $decoded ) ) {
		return $defaults;
	}
	return array_merge( $defaults, $decoded );
}

function yaamama_subscriptions_render_features_form( $features = array() ) {
	$defaults = yaamama_subscriptions_default_features();
	$features = array_merge( $defaults, $features );
	$labels   = yaamama_subscriptions_feature_labels();
	?>
	<table class="form-table" style="max-width:700px;">
		<?php foreach ( $labels as $key => $field ) : ?>
		<tr>
			<th style="padding:8px 10px;"><label><?php echo esc_html( $field['label'] ); ?></label></th>
			<td style="padding:8px 10px;">
				<?php if ( 'checkbox' === $field['type'] ) : ?>
					<input type="hidden" name="features[<?php echo esc_attr( $key ); ?>]" value="0">
					<input type="checkbox" name="features[<?php echo esc_attr( $key ); ?>]" value="1"
						<?php checked( ! empty( $features[ $key ] ) ); ?>>
				<?php elseif ( 'number' === $field['type'] ) : ?>
					<input type="number" name="features[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $features[ $key ] ); ?>" style="width:120px;">
					<?php if ( ! empty( $field['hint'] ) ) : ?>
						<span class="description"><?php echo esc_html( $field['hint'] ); ?></span>
					<?php endif; ?>
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select name="features[<?php echo esc_attr( $key ); ?>]">
						<?php foreach ( $field['options'] as $val => $lbl ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $features[ $key ], $val ); ?>>
								<?php echo esc_html( $lbl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

function yaamama_subscriptions_render_plans() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$tables   = yaamama_subscriptions_tables();
	$plans    = $wpdb->get_results( "SELECT * FROM {$tables['plans']} ORDER BY id DESC", ARRAY_A );
	$prices   = yaamama_subscriptions_get_plan_prices_map();
	$edit_id  = isset( $_GET['edit_plan'] ) ? (int) $_GET['edit_plan'] : 0;
	$edit_plan = $edit_id ? yaamama_subscriptions_get_plan( $edit_id ) : null;
	$edit_prices = $edit_id && isset( $prices[ $edit_id ] ) ? $prices[ $edit_id ] : array();
	$edit_features = $edit_plan ? yaamama_subscriptions_parse_plan_features( $edit_plan ) : array();
	$products = get_posts( array( 'post_type' => 'product', 'numberposts' => -1 ) );
	$active_plans = array_filter(
		$plans,
		static function( $plan ) {
			return isset( $plan['status'] ) && 'active' === $plan['status'];
		}
	);
	?>
	<div class="wrap">
		<h1>خطط الاشتراكات</h1>

		<h2>إضافة باقة</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'yaamama_add_plan', 'yaamama_add_plan_nonce' ); ?>
			<input type="hidden" name="action" value="yaamama_add_plan">
			<table class="form-table" style="max-width:700px;">
				<tr>
					<th style="padding:8px 10px;"><label>اسم الباقة</label></th>
					<td style="padding:8px 10px;"><input type="text" name="name" class="regular-text" required></td>
				</tr>
				<tr>
					<th style="padding:8px 10px;"><label>الوصف</label></th>
					<td style="padding:8px 10px;"><textarea name="description" class="large-text" rows="3" placeholder="اكتب كل ميزة في سطر"></textarea></td>
				</tr>
				<tr>
					<th style="padding:8px 10px;"><label>باقة مجانية</label></th>
					<td style="padding:8px 10px;">
						<input type="hidden" name="is_free" value="0">
						<input type="checkbox" name="is_free" value="1" id="add_is_free">
						<label for="add_is_free">بدون رسوم (مجانية)</label>
					</td>
				</tr>
				<tr class="price-row">
					<th style="padding:8px 10px;"><label>السعر الشهري</label></th>
					<td style="padding:8px 10px;"><input type="number" step="0.01" min="0" name="price_month" style="width:150px;"></td>
				</tr>
				<tr class="price-row">
					<th style="padding:8px 10px;"><label>السعر السنوي</label></th>
					<td style="padding:8px 10px;"><input type="number" step="0.01" min="0" name="price_year" style="width:150px;"></td>
				</tr>
				<tr class="price-row">
					<th style="padding:8px 10px;"><label>أيام تجريبية (شهري)</label></th>
					<td style="padding:8px 10px;"><input type="number" min="0" name="trial_days_month" value="0" style="width:100px;"></td>
				</tr>
				<tr class="price-row">
					<th style="padding:8px 10px;"><label>أيام تجريبية (سنوي)</label></th>
					<td style="padding:8px 10px;"><input type="number" min="0" name="trial_days_year" value="0" style="width:100px;"></td>
				</tr>
			</table>
			<h3>ميزات الباقة</h3>
			<?php yaamama_subscriptions_render_features_form(); ?>
			<?php submit_button( 'إضافة باقة' ); ?>
		</form>

		<?php if ( $edit_plan ) : ?>
			<hr>
			<h2>تعديل باقة: <?php echo esc_html( $edit_plan['name'] ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'yaamama_update_plan', 'yaamama_update_plan_nonce' ); ?>
				<input type="hidden" name="action" value="yaamama_update_plan">
				<input type="hidden" name="plan_id" value="<?php echo esc_attr( $edit_plan['id'] ); ?>">
				<table class="form-table" style="max-width:700px;">
					<tr>
						<th style="padding:8px 10px;"><label>اسم الباقة</label></th>
						<td style="padding:8px 10px;"><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr( $edit_plan['name'] ); ?>"></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label>الوصف</label></th>
						<td style="padding:8px 10px;"><textarea name="description" class="large-text" rows="3"><?php echo esc_textarea( $edit_plan['description'] ); ?></textarea></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;"><label>باقة مجانية</label></th>
						<td style="padding:8px 10px;">
							<input type="hidden" name="is_free" value="0">
							<input type="checkbox" name="is_free" value="1" id="edit_is_free"
								<?php checked( ! empty( $edit_plan['is_free'] ) ); ?>>
							<label for="edit_is_free">بدون رسوم (مجانية)</label>
						</td>
					</tr>
					<tr class="price-row">
						<th style="padding:8px 10px;"><label>السعر الشهري</label></th>
						<td style="padding:8px 10px;"><input type="number" step="0.01" min="0" name="price_month" style="width:150px;"
							value="<?php echo esc_attr( $edit_prices['month']['price'] ?? 0 ); ?>"></td>
					</tr>
					<tr class="price-row">
						<th style="padding:8px 10px;"><label>السعر السنوي</label></th>
						<td style="padding:8px 10px;"><input type="number" step="0.01" min="0" name="price_year" style="width:150px;"
							value="<?php echo esc_attr( $edit_prices['year']['price'] ?? 0 ); ?>"></td>
					</tr>
					<tr class="price-row">
						<th style="padding:8px 10px;"><label>أيام تجريبية (شهري)</label></th>
						<td style="padding:8px 10px;"><input type="number" min="0" name="trial_days_month" style="width:100px;"
							value="<?php echo esc_attr( $edit_prices['month']['trial_days'] ?? 0 ); ?>"></td>
					</tr>
					<tr class="price-row">
						<th style="padding:8px 10px;"><label>أيام تجريبية (سنوي)</label></th>
						<td style="padding:8px 10px;"><input type="number" min="0" name="trial_days_year" style="width:100px;"
							value="<?php echo esc_attr( $edit_prices['year']['trial_days'] ?? 0 ); ?>"></td>
					</tr>
				</table>
				<h3>ميزات الباقة</h3>
				<?php yaamama_subscriptions_render_features_form( $edit_features ); ?>
				<?php submit_button( 'حفظ التعديل' ); ?>
			</form>
		<?php endif; ?>

		<hr>
		<h2>ربط باقة بمنتج</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'yaamama_link_product', 'yaamama_link_product_nonce' ); ?>
			<input type="hidden" name="action" value="yaamama_link_product">
			<select name="plan_id" required>
				<option value="">اختر الباقة</option>
				<?php foreach ( $active_plans as $plan ) : ?>
					<option value="<?php echo esc_attr( $plan['id'] ); ?>"><?php echo esc_html( $plan['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="product_id" required>
				<option value="">اختر المنتج</option>
				<?php foreach ( $products as $product ) : ?>
					<option value="<?php echo esc_attr( $product->ID ); ?>"><?php echo esc_html( $product->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( 'ربط' ); ?>
		</form>

		<hr>
		<h2>الباقات الحالية</h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th style="width:40px;">ID</th>
					<th>الاسم</th>
					<th>النوع</th>
					<th>الحالة</th>
					<th>شهري</th>
					<th>سنوي</th>
					<th>منتجات</th>
					<th>مستخدمين</th>
					<th>الميزات</th>
					<th>إجراءات</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $plans as $plan ) : ?>
					<?php
					$plan_prices  = $prices[ (int) $plan['id'] ] ?? array();
					$month_price  = $plan_prices['month']['price'] ?? '-';
					$year_price   = $plan_prices['year']['price'] ?? '-';
					$is_active    = ( 'active' === $plan['status'] );
					$pf           = yaamama_subscriptions_parse_plan_features( $plan );
					$is_plan_free = ! empty( $plan['is_free'] );
					$badge_labels = yaamama_subscriptions_feature_labels();
					$enabled      = array();
					foreach ( $badge_labels as $fk => $fv ) {
						if ( 'checkbox' === $fv['type'] && ! empty( $pf[ $fk ] ) ) {
							$enabled[] = $fv['label'];
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $plan['id'] ); ?></td>
						<td>
							<strong><?php echo esc_html( $plan['name'] ); ?></strong>
							<?php if ( $is_plan_free ) : ?><span style="color:#16a34a;font-size:11px;">⬤ مجانية</span><?php endif; ?>
						</td>
						<td><?php echo esc_html( $pf['store_type'] ); ?></td>
						<td><?php echo $is_active ? '<span style="color:green;">فعّال</span>' : '<span style="color:#999;">معطّل</span>'; ?></td>
						<td><?php echo $is_plan_free ? 'مجاني' : esc_html( $month_price ); ?></td>
						<td><?php echo $is_plan_free ? 'مجاني' : esc_html( $year_price ); ?></td>
						<td><?php echo (int) $pf['max_products'] === -1 ? '∞' : esc_html( $pf['max_products'] ); ?></td>
						<td><?php echo (int) $pf['max_admins'] === -1 ? '∞' : esc_html( $pf['max_admins'] ); ?></td>
						<td><small><?php echo esc_html( implode( '، ', $enabled ) ?: 'لا شيء' ); ?></small></td>
						<td>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=yaamama-subs&edit_plan=' . (int) $plan['id'] ) ); ?>">تعديل</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<?php wp_nonce_field( 'yaamama_toggle_plan', 'yaamama_toggle_plan_nonce' ); ?>
								<input type="hidden" name="action" value="yaamama_toggle_plan">
								<input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan['id'] ); ?>">
								<input type="hidden" name="new_status" value="<?php echo esc_attr( $is_active ? 'inactive' : 'active' ); ?>">
								<?php submit_button( $is_active ? 'تعطيل' : 'تفعيل', 'secondary', 'submit', false ); ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<script>
	document.querySelectorAll('#add_is_free, #edit_is_free').forEach(function(cb){
		function toggle(){
			var form = cb.closest('form');
			form.querySelectorAll('.price-row').forEach(function(r){ r.style.display = cb.checked ? 'none' : ''; });
		}
		cb.addEventListener('change', toggle);
		toggle();
	});
	</script>
	<?php
}

function yaamama_subscriptions_render_subscriptions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$subs   = $wpdb->get_results( "SELECT * FROM {$tables['subs']} ORDER BY id DESC LIMIT 200", ARRAY_A );
	?>
	<div class="wrap">
		<h1>Subscriptions</h1>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>ID</th>
					<th>User</th>
					<th>Product</th>
					<th>Plan Price</th>
					<th>Status</th>
					<th>Next Billing</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $subs as $sub ) : ?>
					<tr>
						<td><?php echo esc_html( $sub['id'] ); ?></td>
						<td><?php echo esc_html( $sub['user_id'] ); ?></td>
						<td><?php echo esc_html( $sub['product_id'] ); ?></td>
						<td><?php echo esc_html( $sub['plan_price_id'] ); ?></td>
						<td><?php echo esc_html( $sub['status'] ); ?></td>
						<td><?php echo esc_html( $sub['next_billing_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function yaamama_subscriptions_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = yaamama_subscriptions_settings();
	$gateways = yaamama_subscriptions_get_payment_gateways();
	?>
	<div class="wrap">
		<h1>Payment Settings</h1>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'yaamama_save_settings', 'yaamama_save_settings_nonce' ); ?>
			<input type="hidden" name="action" value="yaamama_save_settings">
			<table class="form-table">
				<tr>
					<th><label>بوابة الدفع</label></th>
					<td>
						<?php if ( $gateways ) : ?>
						<select name="payment_gateway_id">
							<?php foreach ( $gateways as $gateway_id => $gateway_label ) : ?>
								<option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( $settings['payment_gateway_id'], $gateway_id ); ?>>
									<?php echo esc_html( $gateway_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<?php else : ?>
							<p>لم يتم العثور على بوابات دفع مفعّلة. يرجى تفعيل بوابة دفع في WooCommerce أولاً.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( 'حفظ' ); ?>
		</form>
	</div>
	<?php
}

function yaamama_subscriptions_collect_features_from_post() {
	$defaults = yaamama_subscriptions_default_features();
	$raw      = isset( $_POST['features'] ) ? (array) $_POST['features'] : array();
	$features = array();
	foreach ( $defaults as $key => $default ) {
		if ( ! isset( $raw[ $key ] ) ) {
			$features[ $key ] = $default;
			continue;
		}
		if ( is_bool( $default ) ) {
			$features[ $key ] = (bool) intval( $raw[ $key ] );
		} elseif ( is_int( $default ) ) {
			$features[ $key ] = (int) $raw[ $key ];
		} else {
			$features[ $key ] = sanitize_text_field( $raw[ $key ] );
		}
	}
	return $features;
}

function yaamama_subscriptions_handle_add_plan() {
	check_admin_referer( 'yaamama_add_plan', 'yaamama_add_plan_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	global $wpdb;
	$tables      = yaamama_subscriptions_tables();
	$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	$is_free     = ! empty( $_POST['is_free'] );
	$price_month = isset( $_POST['price_month'] ) ? (float) wp_unslash( $_POST['price_month'] ) : 0;
	$price_year  = isset( $_POST['price_year'] ) ? (float) wp_unslash( $_POST['price_year'] ) : 0;
	$features    = yaamama_subscriptions_collect_features_from_post();

	if ( ! $is_free && ( $price_month <= 0 || $price_year <= 0 ) ) {
		wp_die( 'يرجى إدخال السعر الشهري والسنوي.' );
	}
	if ( '' === $name ) {
		wp_die( 'يرجى إدخال اسم الباقة.' );
	}

	$wpdb->insert(
		$tables['plans'],
		array(
			'name'        => $name,
			'description' => $description,
			'features'    => wp_json_encode( $features ),
			'is_free'     => $is_free ? 1 : 0,
			'status'      => 'active',
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		)
	);
	$plan_id = (int) $wpdb->insert_id;

	if ( $plan_id ) {
		if ( $is_free ) {
			$price_month = 0;
			$price_year  = 0;
		}
		$trial_days_month = isset( $_POST['trial_days_month'] ) ? (int) wp_unslash( $_POST['trial_days_month'] ) : 0;
		$trial_days_year  = isset( $_POST['trial_days_year'] ) ? (int) wp_unslash( $_POST['trial_days_year'] ) : 0;
		$wpdb->insert(
			$tables['plan_prices'],
			array(
				'plan_id'    => $plan_id,
				'period'     => 'month',
				'price'      => $price_month,
				'trial_days' => $trial_days_month,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
		$wpdb->insert(
			$tables['plan_prices'],
			array(
				'plan_id'    => $plan_id,
				'period'     => 'year',
				'price'      => $price_year,
				'trial_days' => $trial_days_year,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}
	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs' ) );
	exit;
}
add_action( 'admin_post_yaamama_add_plan', 'yaamama_subscriptions_handle_add_plan' );

function yaamama_subscriptions_handle_update_plan() {
	check_admin_referer( 'yaamama_update_plan', 'yaamama_update_plan_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$plan_id = (int) ( $_POST['plan_id'] ?? 0 );
	if ( ! $plan_id ) {
		wp_die( 'Invalid plan.' );
	}

	$plan = yaamama_subscriptions_get_plan( $plan_id );
	if ( ! $plan ) {
		wp_die( 'Plan not found.' );
	}

	$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
	$is_free     = ! empty( $_POST['is_free'] );
	$price_month = isset( $_POST['price_month'] ) ? (float) wp_unslash( $_POST['price_month'] ) : 0;
	$price_year  = isset( $_POST['price_year'] ) ? (float) wp_unslash( $_POST['price_year'] ) : 0;
	$features    = yaamama_subscriptions_collect_features_from_post();

	if ( ! $is_free && ( $price_month <= 0 || $price_year <= 0 ) ) {
		wp_die( 'يرجى إدخال السعر الشهري والسنوي.' );
	}
	if ( '' === $name ) {
		wp_die( 'يرجى إدخال اسم الباقة.' );
	}
	$trial_days_month = isset( $_POST['trial_days_month'] ) ? (int) wp_unslash( $_POST['trial_days_month'] ) : 0;
	$trial_days_year  = isset( $_POST['trial_days_year'] ) ? (int) wp_unslash( $_POST['trial_days_year'] ) : 0;

	$wpdb->update(
		$tables['plans'],
		array(
			'name'        => $name,
			'description' => $description,
			'features'    => wp_json_encode( $features ),
			'is_free'     => $is_free ? 1 : 0,
			'updated_at'  => current_time( 'mysql' ),
		),
		array( 'id' => $plan_id )
	);

	if ( $is_free ) {
		$price_month = 0;
		$price_year  = 0;
	}
	$status = $plan['status'] ?? 'active';
	yaamama_subscriptions_upsert_plan_price( $plan_id, 'month', $price_month, $trial_days_month, $status );
	yaamama_subscriptions_upsert_plan_price( $plan_id, 'year', $price_year, $trial_days_year, $status );

	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs&edit_plan=' . $plan_id ) );
	exit;
}
add_action( 'admin_post_yaamama_update_plan', 'yaamama_subscriptions_handle_update_plan' );

function yaamama_subscriptions_handle_toggle_plan() {
	check_admin_referer( 'yaamama_toggle_plan', 'yaamama_toggle_plan_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$plan_id = (int) ( $_POST['plan_id'] ?? 0 );
	$new_status = sanitize_text_field( wp_unslash( $_POST['new_status'] ?? '' ) );
	if ( ! $plan_id || ! in_array( $new_status, array( 'active', 'inactive' ), true ) ) {
		wp_die( 'Invalid request.' );
	}

	$wpdb->update(
		$tables['plans'],
		array(
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $plan_id )
	);

	$wpdb->update(
		$tables['plan_prices'],
		array(
			'status'     => $new_status,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'plan_id' => $plan_id )
	);

	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs' ) );
	exit;
}
add_action( 'admin_post_yaamama_toggle_plan', 'yaamama_subscriptions_handle_toggle_plan' );

function yaamama_subscriptions_handle_add_price() {
	check_admin_referer( 'yaamama_add_price', 'yaamama_add_price_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$plan_id = (int) ( $_POST['plan_id'] ?? 0 );
	if ( ! $plan_id ) {
		wp_die( 'Invalid plan.' );
	}
	$wpdb->insert(
		$tables['plan_prices'],
		array(
			'plan_id'    => $plan_id,
			'period'     => sanitize_text_field( wp_unslash( $_POST['period'] ?? 'month' ) ),
			'price'      => isset( $_POST['price'] ) ? (float) wp_unslash( $_POST['price'] ) : 0,
			'trial_days' => isset( $_POST['trial_days'] ) ? (int) wp_unslash( $_POST['trial_days'] ) : 0,
			'status'     => 'active',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		)
	);
	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs' ) );
	exit;
}
add_action( 'admin_post_yaamama_add_price', 'yaamama_subscriptions_handle_add_price' );

function yaamama_subscriptions_handle_link_product() {
	check_admin_referer( 'yaamama_link_product', 'yaamama_link_product_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$plan_id    = (int) ( $_POST['plan_id'] ?? 0 );
	$product_id = (int) ( $_POST['product_id'] ?? 0 );
	if ( ! $plan_id || ! $product_id ) {
		wp_die( 'Invalid request.' );
	}
	$plan = yaamama_subscriptions_get_plan( $plan_id );
	if ( ! $plan || 'active' !== $plan['status'] ) {
		wp_die( 'الباقة غير متاحة.' );
	}
	$product = get_post( $product_id );
	if ( ! $product || 'product' !== $product->post_type ) {
		wp_die( 'المنتج غير صالح.' );
	}
	$wpdb->replace(
		$tables['product_plans'],
		array(
			'product_id' => $product_id,
			'plan_id'    => $plan_id,
			'created_at' => current_time( 'mysql' ),
		)
	);
	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs' ) );
	exit;
}
add_action( 'admin_post_yaamama_link_product', 'yaamama_subscriptions_handle_link_product' );

function yaamama_subscriptions_handle_save_settings() {
	check_admin_referer( 'yaamama_save_settings', 'yaamama_save_settings_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	$gateway_id = sanitize_text_field( wp_unslash( $_POST['payment_gateway_id'] ?? '' ) );
	$gateways   = yaamama_subscriptions_get_payment_gateways();
	if ( $gateways && ! isset( $gateways[ $gateway_id ] ) ) {
		$gateway_id = (string) array_key_first( $gateways );
	}
	$settings = array(
		'payment_gateway_id' => $gateway_id,
	);
	update_option( 'yaamama_subscriptions_settings', $settings );
	wp_safe_redirect( admin_url( 'admin.php?page=yaamama-subs-settings' ) );
	exit;
}
add_action( 'admin_post_yaamama_save_settings', 'yaamama_subscriptions_handle_save_settings' );

function yaamama_subscriptions_process_subscribe() {
	if ( ! isset( $_POST['yaamama_subscribe_nonce'] ) || ! wp_verify_nonce( $_POST['yaamama_subscribe_nonce'], 'yaamama_subscribe' ) ) {
		wp_die( 'Invalid request' );
	}

	if ( ! is_user_logged_in() ) {
		$referer = wp_get_referer() ?: home_url( '/' );
		wp_safe_redirect( home_url( '/login?redirect_to=' . rawurlencode( $referer ) ) );
		exit;
	}

	$user_id    = get_current_user_id();
	$product_id = (int) ( $_POST['product_id'] ?? 0 );
	$price_id   = (int) ( $_POST['plan_price_id'] ?? 0 );
	$price      = yaamama_subscriptions_get_price( $price_id );

	if ( ! $price || 'active' !== $price['status'] ) {
		wp_die( 'Invalid plan price' );
	}
	$plan = yaamama_subscriptions_get_plan( (int) $price['plan_id'] );
	if ( ! $plan || 'active' !== $plan['status'] ) {
		wp_die( 'Plan is inactive.' );
	}
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$link = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM {$tables['product_plans']} WHERE product_id = %d AND plan_id = %d",
			$product_id,
			(int) $price['plan_id']
		)
	);
	if ( ! $link ) {
		wp_die( 'Plan is not linked to this product.' );
	}

	$starts_at   = current_time( 'mysql' );
	$is_free     = ! empty( $plan['is_free'] );

	$subscription_id = yaamama_subscriptions_create_subscription(
		array(
			'user_id'         => $user_id,
			'product_id'      => $product_id,
			'plan_price_id'   => $price_id,
			'store_name'      => null,
			'status'          => 'pending',
			'starts_at'       => $starts_at,
			'next_billing_at' => null,
		)
	);

	if ( $is_free ) {
		$wpdb->update(
			$tables['subs'],
			array(
				'status'     => 'active',
				'starts_at'  => $starts_at,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $subscription_id )
		);
		yaamama_subscriptions_add_event( $subscription_id, 'payment_success', array( 'type' => 'free_plan' ) );

		$redirect = wp_get_referer() ?: home_url( '/my-account/' );
		wp_safe_redirect( $redirect );
		exit;
	}

	$order = yaamama_subscriptions_create_order( $subscription_id, $product_id, $price, false, $user_id );
	if ( is_wp_error( $order ) ) {
		yaamama_subscriptions_add_event( $subscription_id, 'order_error', $order->get_error_message() );
		wp_die( $order->get_error_message() );
	}
	$wpdb->update(
		$tables['subs'],
		array(
			'status'     => 'pending_payment',
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $subscription_id )
	);
	yaamama_subscriptions_add_event( $subscription_id, 'order_created', array( 'order_id' => $order->get_id() ) );

	wp_safe_redirect( $order->get_checkout_payment_url() );
	exit;
}
add_action( 'admin_post_nopriv_yaamama_subscribe', 'yaamama_subscriptions_process_subscribe' );
add_action( 'admin_post_yaamama_subscribe', 'yaamama_subscriptions_process_subscribe' );

function yaamama_subscriptions_fix_stale_gateway_on_pay() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! function_exists( 'WC' ) ) return;
	if ( ! is_wc_endpoint_url( 'order-pay' ) ) return;
	global $wp;
	$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
	if ( ! $order_id ) return;

	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->needs_payment() ) return;

	$available = WC()->payment_gateways()->get_available_payment_gateways();
	$current_gw = $order->get_payment_method();

	if ( $current_gw && ! isset( $available[ $current_gw ] ) && $available ) {
		$new_gw_id = (string) array_key_first( $available );
		$order->set_payment_method( $available[ $new_gw_id ] );
		$order->save();
	}
}
add_action( 'template_redirect', 'yaamama_subscriptions_fix_stale_gateway_on_pay' );

function yaamama_moyasar_pay_page_bridge() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) ) return;
	if ( ! is_wc_endpoint_url( 'order-pay' ) ) return;
	?>
	<script>
	(function($){
		var form = document.getElementById('order_review');
		if (!form) return;

		var selectedGw = form.querySelector('input[name="payment_method"]:checked');
		if (!selectedGw || selectedGw.value !== 'moyasar-credit-card') return;

		if (typeof moyasar_credit_card === 'undefined') return;

		var pubKey = moyasar_credit_card.mysrCCPublishableKey;
		var baseUrl = moyasar_credit_card.mysrCCMoyasarBaseUrl;
		if (!pubKey || !baseUrl) return;

		var processing = false;

		form.addEventListener('submit', function(e) {
			if (form.querySelector('input[name="mysr_token"]')) return;
			if (processing) { e.preventDefault(); return; }

			e.preventDefault();
			processing = true;

			var nameEl   = document.getElementById('moyasar-card-name');
			var numberEl = document.getElementById('moyasar-card-number');
			var expiryEl = document.getElementById('moyasar-card-expiry');
			var cvcEl    = document.getElementById('moyasar-card-cvc');

			if (!nameEl || !numberEl || !expiryEl || !cvcEl) {
				processing = false;
				return;
			}

			var name   = nameEl.value.trim();
			var number = numberEl.value.replace(/\s/g, '');
			var expiry = expiryEl.value.replace(/\s/g, '');
			var cvc    = cvcEl.value.trim();

			if (!name || !number || !cvc || !expiry) {
				alert('يرجى تعبئة جميع حقول البطاقة');
				processing = false;
				return;
			}

			var parts = expiry.split('/');
			var month = parts[0] || '';
			var year  = parts[1] || '';
			if (year.length === 2) year = '20' + year;

			var btn = form.querySelector('#place_order, button[type="submit"]');
			var origText = btn ? btn.innerHTML : '';
			if (btn) { btn.innerHTML = 'جاري المعالجة...'; btn.disabled = true; }

			fetch(baseUrl + '/v1/tokens', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					name: name, number: number, month: month, year: year, cvc: cvc,
					callback_url: window.location.href + '/payment',
					publishable_api_key: pubKey,
					save_only: true
				})
			})
			.then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
			.then(function(res) {
				if (!res.ok) {
					var msg = 'فشل في معالجة البطاقة';
					if (res.data && res.data.errors) {
						var errs = [];
						Object.keys(res.data.errors).forEach(function(k) {
							res.data.errors[k].forEach(function(e) { errs.push(k + ': ' + e); });
						});
						if (errs.length) msg = errs.join('\n');
					} else if (res.data && res.data.message) {
						msg = res.data.message;
					}
					throw new Error(msg);
				}

				var token = res.data.id;
				var hi = document.createElement('input');
				hi.type = 'hidden'; hi.name = 'mysr_token'; hi.value = token;
				form.appendChild(hi);

				var hf = document.createElement('input');
				hf.type = 'hidden'; hf.name = 'mysr_form'; hf.value = 'classic';
				form.appendChild(hf);

				nameEl.value = '';
				numberEl.value = '';
				expiryEl.value = '';
				cvcEl.value = '';

				var ph = document.getElementById('billing-phone');
				var phH = document.getElementById('billing-phone-hidden');
				if (ph && phH) phH.value = ph.value;

				form.submit();
			})
			.catch(function(err) {
				if (btn) { btn.innerHTML = origText; btn.disabled = false; }
				processing = false;
				alert(err.message || 'حدث خطأ في معالجة الدفع');
			});
		});

	})(jQuery);
	</script>
	<?php
}
add_action( 'wp_footer', 'yaamama_moyasar_pay_page_bridge', 999 );


function yaamama_subscriptions_create_order( $subscription_id, $product_id, $price_row, $is_renewal, $customer_id = 0 ) {
	if ( ! function_exists( 'wc_create_order' ) ) {
		return new WP_Error( 'woocommerce_missing', 'WooCommerce is not active.' );
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return new WP_Error( 'product_missing', 'Product not found.' );
	}

	$settings   = yaamama_subscriptions_settings();
	$gateway_id = $settings['payment_gateway_id'] ?? '';
	$gateways   = WC_Payment_Gateways::instance()->payment_gateways();
	$available  = WC_Payment_Gateways::instance()->get_available_payment_gateways();

	if ( $available && ! isset( $available[ $gateway_id ] ) ) {
		$gateway_id = (string) array_key_first( $available );
	} elseif ( empty( $gateway_id ) && $gateways ) {
		$enabled = yaamama_subscriptions_get_payment_gateways();
		if ( $enabled ) {
			$gateway_id = (string) array_key_first( $enabled );
		}
	}

	$order = wc_create_order(
		array(
			'customer_id' => (int) $customer_id,
		)
	);
	if ( $customer_id ) {
		$user = get_user_by( 'id', $customer_id );
		if ( $user ) {
			$order->set_billing_email( $user->user_email );
			$order->set_billing_first_name( $user->first_name ?: $user->display_name );
			$order->set_billing_last_name( $user->last_name ?: '' );
		}
	}

	$order->add_product(
		$product,
		1,
		array(
			'subtotal' => (float) $price_row['price'],
			'total'    => (float) $price_row['price'],
		)
	);
	$order->set_total( (float) $price_row['price'] );

	$gw_source = $available ?: $gateways;
	if ( $gateway_id && isset( $gw_source[ $gateway_id ] ) ) {
		$order->set_payment_method( $gw_source[ $gateway_id ] );
	}

	$order->update_meta_data( 'yaamama_subscription_id', (int) $subscription_id );
	$order->update_meta_data( 'yaamama_plan_price_id', (int) $price_row['id'] );
	$order->update_meta_data( 'yaamama_is_renewal', $is_renewal ? '1' : '0' );
	$order->save();

	global $wpdb;
	$tables  = yaamama_subscriptions_tables();
	$updates = array(
		'last_order_id' => $order->get_id(),
		'updated_at'    => current_time( 'mysql' ),
	);
	$sub_row = $wpdb->get_row(
		$wpdb->prepare( "SELECT initial_order_id FROM {$tables['subs']} WHERE id = %d", $subscription_id ),
		ARRAY_A
	);
	if ( empty( $sub_row['initial_order_id'] ) ) {
		$updates['initial_order_id'] = $order->get_id();
	}

	$wpdb->update(
		$tables['subs'],
		$updates,
		array( 'id' => $subscription_id )
	);

	return $order;
}

function yaamama_subscriptions_create_switch_order( $subscription_id, $product_id, $old_price_id, $new_price_row, $difference, $customer_id = 0 ) {
	$price_row = $new_price_row;
	$price_row['price'] = $difference;

	$order = yaamama_subscriptions_create_order( $subscription_id, $product_id, $price_row, false, $customer_id );
	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$order->update_meta_data( 'yaamama_is_switch', '1' );
	$order->update_meta_data( 'yaamama_switch_plan_price_id', (int) $new_price_row['id'] );
	$order->update_meta_data( 'yaamama_switch_from_plan_price_id', (int) $old_price_id );
	$order->save();

	return $order;
}

function yaamama_subscriptions_handle_order_status( $order_id, $old_status, $new_status, $order ) {
	if ( ! $order instanceof WC_Order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order ) {
		return;
	}

	$subscription_id = (int) $order->get_meta( 'yaamama_subscription_id' );
	if ( ! $subscription_id ) {
		return;
	}

	global $wpdb;
	$tables  = yaamama_subscriptions_tables();
	$sub_row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$tables['subs']} WHERE id = %d", $subscription_id ),
		ARRAY_A
	);
	if ( ! $sub_row ) {
		return;
	}

	$is_switch = $order->get_meta( 'yaamama_is_switch' ) === '1';
	$new_plan_price_id = $is_switch ? (int) $order->get_meta( 'yaamama_switch_plan_price_id' ) : 0;
	$price_row = $is_switch && $new_plan_price_id
		? yaamama_subscriptions_get_price( $new_plan_price_id )
		: yaamama_subscriptions_get_price( $sub_row['plan_price_id'] );
	if ( ! $price_row ) {
		return;
	}

	$is_paid = in_array( $new_status, array( 'processing', 'completed', 'on-hold' ), true );
	if ( $is_paid ) {
		$trial_days   = (int) $price_row['trial_days'];
		$next_billing = ( $trial_days && ! $order->get_meta( 'yaamama_is_renewal' ) && ! $is_switch )
			? gmdate( 'Y-m-d H:i:s', strtotime( '+' . $trial_days . ' days' ) )
			: yaamama_subscriptions_get_next_billing( current_time( 'mysql' ), $price_row['period'] );

		$wpdb->update(
			$tables['subs'],
			array(
				'status'          => 'active',
				'plan_price_id'   => $is_switch && $new_plan_price_id ? $new_plan_price_id : $sub_row['plan_price_id'],
				'next_billing_at' => $next_billing,
				'last_order_id'   => $order->get_id(),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $subscription_id )
		);
		$event_type = $is_switch ? 'plan_switch_paid' : 'payment_success';
		yaamama_subscriptions_add_event( $subscription_id, $event_type, array( 'order_id' => $order->get_id() ) );
		return;
	}

	if ( in_array( $new_status, array( 'failed', 'cancelled' ), true ) ) {
		if ( $is_switch ) {
			$wpdb->update(
				$tables['subs'],
				array(
					'status'     => 'active',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $subscription_id )
			);
			yaamama_subscriptions_add_event( $subscription_id, 'plan_switch_failed', array( 'order_id' => $order->get_id() ) );
			return;
		}

		$is_renewal = $order->get_meta( 'yaamama_is_renewal' ) === '1';
		$wpdb->update(
			$tables['subs'],
			array(
				'status'     => $is_renewal ? 'past_due' : 'failed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $subscription_id )
		);
		yaamama_subscriptions_add_event( $subscription_id, 'payment_failed', array( 'order_id' => $order->get_id() ) );
	}
}
add_action( 'woocommerce_order_status_changed', 'yaamama_subscriptions_handle_order_status', 10, 4 );

function yaamama_subscriptions_get_next_billing( $from, $period ) {
	$timestamp = strtotime( $from );
	if ( ! $timestamp ) {
		$timestamp = time();
	}

	if ( 'year' === $period ) {
		return gmdate( 'Y-m-d H:i:s', strtotime( '+1 year', $timestamp ) );
	}

	return gmdate( 'Y-m-d H:i:s', strtotime( '+1 month', $timestamp ) );
}

function yaamama_subscriptions_process_renewals() {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$now    = current_time( 'mysql' );

	$subs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$tables['subs']}
				WHERE status IN ('active','past_due')
				AND next_billing_at IS NOT NULL
				AND next_billing_at <= %s",
			$now
		),
		ARRAY_A
	);

	foreach ( $subs as $sub ) {
		$price_row = yaamama_subscriptions_get_price( $sub['plan_price_id'] );
		if ( ! $price_row ) {
			yaamama_subscriptions_add_event( $sub['id'], 'renewal_skipped', 'price_not_found' );
			continue;
		}

		if ( ! empty( $sub['last_order_id'] ) ) {
			$last_order = wc_get_order( (int) $sub['last_order_id'] );
			if ( $last_order && in_array( $last_order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				$created = $last_order->get_date_created();
				if ( $created && $created->getTimestamp() >= strtotime( $sub['next_billing_at'] ) ) {
					continue;
				}
			}
		}

		$order = yaamama_subscriptions_create_order( $sub['id'], $sub['product_id'], $price_row, true, (int) $sub['user_id'] );
		if ( is_wp_error( $order ) ) {
			$wpdb->update(
				$tables['subs'],
				array(
					'status'     => 'past_due',
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $sub['id'] )
			);
			yaamama_subscriptions_add_event( $sub['id'], 'renewal_failed', $order->get_error_message() );
			continue;
		}

		$wpdb->update(
			$tables['subs'],
			array(
				'status'     => 'pending_payment',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $sub['id'] )
		);
		yaamama_subscriptions_add_event( $sub['id'], 'renewal_order_created', array( 'order_id' => $order->get_id() ) );

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$emails = WC()->mailer()->get_emails();
			if ( ! empty( $emails['WC_Email_Customer_Invoice'] ) ) {
				$emails['WC_Email_Customer_Invoice']->trigger( $order->get_id() );
			}
		}
	}
}
add_action( 'yaamama_process_renewals', 'yaamama_subscriptions_process_renewals' );

function yaamama_subscriptions_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p>يرجى تسجيل الدخول لعرض اشتراكاتك.</p>';
	}

	global $wpdb;
	$tables  = yaamama_subscriptions_tables();
	$user_id = get_current_user_id();
	$subs    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$tables['subs']} WHERE user_id = %d AND status != 'cancelled' ORDER BY id DESC",
			$user_id
		),
		ARRAY_A
	);
	$assets_uri = get_template_directory_uri() . '/yaamama-front-platform/assets';

	ob_start();
	?>
	<div class="stores-grid y-u-grid y-u-grid-3 y-u-gap-24">
		<?php if ( empty( $subs ) ) : ?>
			<p>لا توجد اشتراكات حالياً.</p>
		<?php else : ?>
			<?php foreach ( $subs as $sub ) : ?>
				<?php
				$product     = get_post( $sub['product_id'] );
				$store_name  = ! empty( $sub['store_name'] ) ? $sub['store_name'] : '';
				$store_name_en = '';
				if ( ! empty( $sub['last_order_id'] ) ) {
					$_order = wc_get_order( (int) $sub['last_order_id'] );
					if ( $_order ) {
						if ( $store_name === '' ) {
							$store_name = (string) $_order->get_meta( 'yaamama_store_name' );
						}
						$store_name_en = (string) $_order->get_meta( 'yaamama_store_name_en' );
					}
				}
				if ( $store_name === '' ) {
					$store_name = $product ? $product->post_title : '';
				}
				$price_row   = yaamama_subscriptions_get_price( $sub['plan_price_id'] );
				$plan        = $price_row ? yaamama_subscriptions_get_plan( $price_row['plan_id'] ) : null;
				$pay_url     = '';
				if ( 'pending_payment' === $sub['status'] && ! empty( $sub['last_order_id'] ) ) {
					$order = wc_get_order( (int) $sub['last_order_id'] );
					if ( $order ) {
						$current_gw = $order->get_payment_method();
						$enabled_gw = yaamama_subscriptions_get_payment_gateways();
						if ( $current_gw && $enabled_gw && ! isset( $enabled_gw[ $current_gw ] ) ) {
							$sub_settings = yaamama_subscriptions_settings();
							$new_gw_id = $sub_settings['payment_gateway_id'] ?? '';
							$all_gw = WC_Payment_Gateways::instance()->payment_gateways();
							if ( $new_gw_id && isset( $all_gw[ $new_gw_id ] ) ) {
								$order->set_payment_method( $all_gw[ $new_gw_id ] );
								$order->save();
							}
						}
						$pay_url = $order->get_checkout_payment_url();
					}
				}
				$start_date = ! empty( $sub['starts_at'] ) ? date_i18n( 'j F Y', strtotime( $sub['starts_at'] ) ) : '';

				$store_id     = get_option( "yaamama_store_for_sub_{$sub['id']}", '' );
				$store_url    = get_option( "yaamama_store_url_for_sub_{$sub['id']}", '' );
				$store_status = get_option( "yaamama_store_status_for_sub_{$sub['id']}", '' );
				$job_id       = get_option( "yaamama_job_for_sub_{$sub['id']}", '' );

			if ( $store_id && ( ! $store_url || ! $store_status ) ) {
				if ( function_exists( 'yaamama_factory_api_call' ) ) {
					$_store_data = yaamama_factory_api_call( 'GET', "/stores/{$store_id}" );
					if ( ! is_wp_error( $_store_data ) ) {
						$api_status = $_store_data['status'] ?? '';
						if ( ! empty( $_store_data['store_url'] ) ) {
							$store_url = $_store_data['store_url'];
							$store_status = $api_status ?: 'active';
							update_option( "yaamama_store_url_for_sub_{$sub['id']}", $store_url );
							update_option( "yaamama_store_status_for_sub_{$sub['id']}", $store_status );
						} elseif ( in_array( $api_status, array( 'pending', 'provisioning' ), true ) ) {
							$store_status = 'provisioning';
							update_option( "yaamama_store_status_for_sub_{$sub['id']}", $store_status );
						} elseif ( $api_status === 'failed' ) {
							$store_status = 'failed';
							update_option( "yaamama_store_status_for_sub_{$sub['id']}", $store_status );
						}
					}
				}
				if ( ! $store_url && ! in_array( $store_status, array( 'provisioning', 'pending', 'failed' ), true ) ) {
					$store_url    = 'https://' . $store_id . '.staging.yaamama.store';
					$store_status = $store_status ?: 'active';
					update_option( "yaamama_store_url_for_sub_{$sub['id']}", $store_url );
					update_option( "yaamama_store_status_for_sub_{$sub['id']}", $store_status );
				}
			}

				$is_suspended = ( $sub['status'] === 'cancelled' && $store_id );
				$local_store_status = get_option( "yaamama_store_status_for_sub_{$sub['id']}", '' );
				if ( $local_store_status === 'suspended' ) {
					$is_suspended = true;
				}

				$status_map = array(
					'active'          => array( 'label' => 'جاهز', 'class' => 'ready' ),
					'pending_payment' => array( 'label' => 'بانتظار الدفع', 'class' => 'preparing' ),
					'pending'         => array( 'label' => 'قيد التفعيل', 'class' => 'preparing' ),
					'past_due'        => array( 'label' => 'متأخر', 'class' => 'preparing' ),
					'failed'          => array( 'label' => 'فشل', 'class' => 'preparing' ),
					'cancelled'       => array( 'label' => 'ملغي', 'class' => 'preparing' ),
				);
				if ( $is_suspended ) {
					$status_data = array( 'label' => 'معلّق', 'class' => 'suspended' );
				} else {
					$status_data = $status_map[ $sub['status'] ] ?? array( 'label' => $sub['status'], 'class' => 'preparing' );
				}
				$status_label = $status_data['label'];
				$status_class = $status_data['class'];
				$plan_prices = $price_row
					? $wpdb->get_results(
						$wpdb->prepare( "SELECT * FROM {$tables['plan_prices']} WHERE plan_id = %d AND status = 'active'", $price_row['plan_id'] ),
						ARRAY_A
					)
					: array();
				?>
				<div class="store-card y-u-rounded-m y-u-p-24 y-u-relative y-u-flex y-u-flex-col">
					<div class="status-badge <?php echo esc_attr( $status_class ); ?> y-u-absolute y-u-top-24 y-u-left-24 y-u-p-12 y-u-py-4 y-u-rounded-m y-u-text-xs y-u-font-bold">
						<span class="status-dot"></span>
						<?php echo esc_html( $status_label ); ?>
					</div>

					<div class="y-u-flex y-u-justify-center y-u-m-b-24 y-u-m-t-24">
						<div class="image-wrapper y-u-rounded-f y-u-p-4" style="border: 2px solid var(--y-color-primary);">
							<img src="<?php echo esc_url( $assets_uri . '/product.png' ); ?>" alt="Store" class="y-u-rounded-f"
								style="width: 120px; height: 120px; object-fit: cover;">
						</div>
					</div>

					<div class="y-u-text-center y-u-m-b-16">
						<h3 class="y-u-text-l y-u-font-bold primary-color y-u-m-b-4"><?php echo esc_html( $store_name ?: 'متجري' ); ?></h3>
						<?php if ( $store_name_en !== '' ) : ?>
							<p class="y-u-text-s y-u-m-0" style="color:#6b7280;direction:ltr;"><?php echo esc_html( $store_name_en ); ?></p>
						<?php endif; ?>
						<p class="y-u-text-muted y-u-text-s">الباقة: <?php echo esc_html( $plan['name'] ?? '-' ); ?></p>
					</div>

					<div class="y-u-flex y-u-justify-between y-u-items-center y-u-m-b-16">
						<span class="package-badge y-u-p-12 y-u-py-4 y-u-rounded-m y-u-text-xs y-u-font-bold">باقة اليمامة</span>
						<?php if ( $start_date ) : ?>
							<p class="y-u-text-s y-u-font-medium y-u-m-0">بدأ في : <?php echo esc_html( $start_date ); ?></p>
						<?php endif; ?>
					</div>

					<div class="divider y-u-m-b-24"></div>

				<?php if ( $is_suspended ) : ?>
					<div class="yaamama-store-suspended y-u-m-b-16 y-u-text-center y-u-p-24 y-u-rounded-s" style="background:linear-gradient(135deg,#fef3c7 0%,#fffbeb 100%);border:2px solid #f59e0b;">
						<div style="font-size:36px;margin-bottom:8px;">⏸️</div>
						<p class="y-u-text-s y-u-font-bold y-u-m-0" style="color:#d97706;margin-bottom:8px!important;">تم تعليق متجرك</p>
						<p class="y-u-text-xs y-u-m-0" style="color:#92400e;margin-bottom:16px!important;">تم إيقاف المتجر بسبب إلغاء الاشتراك. يمكنك تجديد اشتراكك لإعادة تفعيله.</p>
						<?php
						$reactivate_product_url = '';
						if ( ! empty( $sub['product_id'] ) ) {
							$reactivate_product_url = get_permalink( $sub['product_id'] );
						}
						if ( $reactivate_product_url ) : ?>
							<a class="btn main-button" href="<?php echo esc_url( $reactivate_product_url ); ?>"
								style="display:inline-block;background:#d97706;border-color:#d97706;padding:12px 32px;border-radius:8px;color:#fff;text-decoration:none;font-weight:bold;font-size:14px;">
								تجديد الاشتراك
							</a>
						<?php endif; ?>
					</div>
				<?php elseif ( $store_url && $store_status !== 'failed' && $store_status !== 'provisioning' ) : ?>
					<div class="yaamama-store-ready y-u-m-b-16 y-u-text-center y-u-p-24 y-u-rounded-s" style="background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border:2px solid #86efac;">
						<div style="font-size:36px;margin-bottom:8px;">🎉</div>
						<p class="y-u-text-s y-u-font-bold y-u-m-0" style="color:#059669;margin-bottom:12px!important;">متجرك جاهز ويعمل!</p>
						<div style="display:flex;gap:8px;margin-top:12px;">
							<a class="btn main-button" href="<?php echo esc_url( $store_url ); ?>" target="_blank"
								style="flex:1;display:block;text-align:center;background:#059669;border-color:#059669;padding:12px 8px;border-radius:8px;color:#fff;text-decoration:none;font-weight:bold;font-size:14px;">
								زيارة المتجر
							</a>
							<button type="button"
								class="yaamama-auto-login-btn"
								data-subscription-id="<?php echo esc_attr( $sub['id'] ); ?>"
								style="flex:1;display:block;text-align:center;background:#1e40af;border:none;padding:12px 8px;border-radius:8px;color:#fff;cursor:pointer;font-weight:bold;font-size:14px;">
								لوحة تحكم المتجر
							</button>
						</div>
					</div>
				<?php elseif ( $job_id && ( ! $store_url || $store_status === 'provisioning' ) && $store_status !== 'failed' ) : ?>
					<div class="yaamama-provision-status y-u-m-b-16 y-u-p-20 y-u-rounded-s" style="background:#f0f9ff;border:1px solid #bae6fd;" data-subscription-id="<?php echo esc_attr( $sub['id'] ); ?>">
						<div class="provision-header" style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
							<div class="provision-spinner" style="flex-shrink:0;width:28px;height:28px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:yaamama-spin 1s linear infinite;"></div>
							<div>
								<p class="y-u-text-s y-u-font-bold y-u-m-0" style="color:#1e40af;">جاري تجهيز متجرك</p>
								<p class="provision-step-label y-u-text-xs y-u-text-muted y-u-m-0" style="margin-top:2px;"></p>
							</div>
						</div>
						<div class="provision-bar-track" style="width:100%;height:8px;background:#e0e7ff;border-radius:4px;overflow:hidden;margin-bottom:4px;">
							<div class="provision-bar-fill" style="width:0%;height:100%;background:linear-gradient(90deg,#3b82f6,#6366f1);border-radius:4px;transition:width .6s ease;"></div>
						</div>
						<p class="provision-percent y-u-text-xs y-u-text-muted y-u-m-0" style="text-align:left;direction:ltr;">0%</p>
						<div class="provision-steps-list" style="margin-top:12px;display:none;"></div>
					</div>
				<?php elseif ( $store_status === 'failed' ) : ?>
					<div class="y-u-m-b-16 y-u-text-center y-u-p-16 y-u-rounded-s" style="background:#fef2f2;border:1px solid #fecaca;">
						<p class="y-u-text-s y-u-font-bold y-u-m-0" style="color:#dc2626;">فشل إنشاء المتجر</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
							<?php wp_nonce_field( 'yaamama_retry_provision', 'yaamama_retry_nonce' ); ?>
							<input type="hidden" name="action" value="yaamama_retry_provision">
							<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
							<button type="submit" class="btn black-outline-button y-u-p-8 y-u-px-16 y-u-text-xs">إعادة المحاولة</button>
						</form>
					</div>
				<?php elseif ( in_array( $sub['status'], array( 'active', 'pending' ), true ) && ! $store_url ) : ?>
					<div class="y-u-m-b-16 y-u-text-center y-u-p-16 y-u-rounded-s" style="background:#fffbeb;border:1px solid #fde68a;">
						<p class="y-u-text-s y-u-font-bold y-u-m-0" style="color:#d97706;">جاري تجهيز المتجر...</p>
						<p class="y-u-text-xs y-u-text-muted y-u-m-0" style="margin-top:4px;">سيكون متجرك جاهزًا قريبًا</p>
					</div>
				<?php endif; ?>

				<?php if ( ! $is_suspended ) : ?>
					<div class="store-actions y-u-flex y-u-flex-col y-u-gap-12">
						<?php if ( $pay_url ) : ?>
							<a class="btn main-button y-u-w-full" href="<?php echo esc_url( $pay_url ); ?>">إتمام الدفع</a>
						<?php endif; ?>

					<?php $has_custom_name = ! empty( $sub['store_name'] ); ?>
				<?php if ( $has_custom_name ) : ?>
						<div class="yaamama-rename-wrap">
							<button type="button" class="yaamama-rename-toggle btn black-outline-button y-u-p-8 y-u-px-16 y-u-text-xs y-u-w-full" style="display:flex;align-items:center;justify-content:center;gap:6px;">
								<i class="fa-solid fa-pen" style="font-size:11px;"></i> تعديل اسم المتجر
							</button>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yaamama-rename-form" style="display:none;margin-top:8px;">
								<?php wp_nonce_field( 'yaamama_rename_store', 'yaamama_rename_store_nonce' ); ?>
								<input type="hidden" name="action" value="yaamama_rename_store">
								<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
								<div style="display:flex;gap:8px;">
									<input type="text" name="store_name" class="y-u-p-8 y-u-rounded-s y-u-border" style="flex:1;font-size:13px;" value="<?php echo esc_attr( $store_name ); ?>" placeholder="اسم المتجر الجديد" required>
									<button type="submit" class="btn main-button y-u-p-8 y-u-px-12 y-u-text-xs" style="white-space:nowrap;">حفظ</button>
								</div>
							</form>
						</div>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="y-u-flex y-u-flex-col y-u-gap-8" style="background:#fffbeb;padding:12px;border-radius:8px;border:1px solid #fde68a;">
							<?php wp_nonce_field( 'yaamama_rename_store', 'yaamama_rename_store_nonce' ); ?>
							<input type="hidden" name="action" value="yaamama_rename_store">
							<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
							<label class="y-u-text-xs y-u-font-bold" style="color:#d97706;">أدخل اسم متجرك</label>
							<input type="text" name="store_name" class="y-u-w-full y-u-p-10 y-u-rounded-s y-u-border" placeholder="اسم المتجر" required>
							<button type="submit" class="btn main-button y-u-p-8 y-u-px-16 y-u-text-xs">حفظ اسم المتجر</button>
						</form>
					<?php endif; ?>

					<?php if ( $plan_prices ) : ?>
						<div class="store-actions-row">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="y-u-flex y-u-flex-col y-u-gap-12">
								<?php wp_nonce_field( 'yaamama_switch_subscription', 'yaamama_switch_subscription_nonce' ); ?>
								<input type="hidden" name="action" value="yaamama_switch_subscription">
								<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
								<select name="plan_price_id" class="y-u-w-full y-u-p-8 y-u-rounded-s y-u-border">
									<?php foreach ( $plan_prices as $pprice ) : ?>
										<option value="<?php echo esc_attr( $pprice['id'] ); ?>" <?php selected( $pprice['id'], $sub['plan_price_id'] ); ?>>
											<?php echo esc_html( $pprice['period'] ); ?> - <?php echo esc_html( number_format( (float) $pprice['price'], 2 ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="btn main-button y-u-p-8 y-u-px-16 y-u-text-xs">تغيير الخطة</button>
							</form>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yaamama-cancel-form">
								<?php wp_nonce_field( 'yaamama_cancel_subscription', 'yaamama_cancel_subscription_nonce' ); ?>
								<input type="hidden" name="action" value="yaamama_cancel_subscription">
								<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
								<button type="submit" class="btn y-u-w-full" style="background-color:#ef4444;color:#ffffff;border:1px solid #ef4444;">إلغاء الاشتراك</button>
							</form>
						</div>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="yaamama-cancel-form">
							<?php wp_nonce_field( 'yaamama_cancel_subscription', 'yaamama_cancel_subscription_nonce' ); ?>
							<input type="hidden" name="action" value="yaamama_cancel_subscription">
							<input type="hidden" name="subscription_id" value="<?php echo esc_attr( $sub['id'] ); ?>">
							<button type="submit" class="btn y-u-w-full" style="background-color:#ef4444;color:#ffffff;border:1px solid #ef4444;">إلغاء الاشتراك</button>
						</form>
					<?php endif; ?>
					</div>
				<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
	?>
	<style>
		@keyframes yaamama-spin { to { transform: rotate(360deg); } }
		.provision-steps-list { font-size: 13px; }
		.provision-step-item {
			display: flex; align-items: center; gap: 8px; padding: 4px 0;
			color: #9ca3af; transition: color .3s;
		}
		.provision-step-item.completed { color: #059669; }
		.provision-step-item.in_progress { color: #1e40af; font-weight: 600; }
		.provision-step-icon {
			flex-shrink: 0; width: 20px; height: 20px; display: flex;
			align-items: center; justify-content: center; border-radius: 50%;
			font-size: 11px; border: 2px solid #e5e7eb; background: #fff;
		}
		.provision-step-item.completed .provision-step-icon {
			background: #059669; border-color: #059669; color: #fff;
		}
		.provision-step-item.in_progress .provision-step-icon {
			border-color: #3b82f6; animation: yaamama-spin 1s linear infinite;
		}
		.yaamama-store-ready-dynamic {
			background: linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);
			border: 2px solid #86efac; border-radius: 8px; padding: 24px;
			text-align: center; animation: yaamama-fadeIn .5s ease;
		}
		@keyframes yaamama-fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
	</style>
	<script>
	(function(){
		var boxes = document.querySelectorAll('.yaamama-provision-status');
		if (!boxes.length) return;
		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

		function buildStepIcon(status) {
			if (status === 'completed') return '✓';
			if (status === 'in_progress') return '◌';
			return '';
		}

		function renderSteps(box, steps) {
			var list = box.querySelector('.provision-steps-list');
			if (!list || !steps || !steps.length) return;
			list.style.display = 'block';
			var html = '';
			for (var i = 0; i < steps.length; i++) {
				var s = steps[i];
				html += '<div class="provision-step-item ' + s.status + '">' +
					'<span class="provision-step-icon">' + buildStepIcon(s.status) + '</span>' +
					'<span>' + s.label + '</span></div>';
			}
			list.innerHTML = html;
		}

		function showReady(box, storeUrl) {
			var card = box.closest('.store-card');
			var badge = card ? card.querySelector('.status-badge') : null;
			if (badge) {
				badge.className = badge.className.replace('preparing', 'ready');
				badge.innerHTML = '<span class="status-dot"></span> جاهز';
			}
			var subId = box.getAttribute('data-subscription-id');
			box.className = 'yaamama-store-ready-dynamic y-u-m-b-16';
			box.style.cssText = 'text-align:center;padding:24px;border-radius:8px;background:linear-gradient(135deg,#f0fdf4 0%,#ecfdf5 100%);border:2px solid #86efac;';
			box.innerHTML = '<div style="font-size:36px;margin-bottom:8px;">🎉</div>' +
				'<p style="color:#059669;font-weight:bold;margin:0 0 12px;">متجرك جاهز ويعمل!</p>' +
				(storeUrl ? '<div style="display:flex;gap:8px;margin-top:12px;">' +
				'<a href="' + storeUrl + '" target="_blank" style="flex:1;display:block;text-align:center;background:#059669;padding:12px 8px;border-radius:8px;color:#fff;text-decoration:none;font-weight:bold;font-size:14px;">زيارة المتجر</a>' +
				'<button type="button" class="yaamama-auto-login-btn" data-subscription-id="' + subId + '" style="flex:1;display:block;text-align:center;background:#1e40af;border:none;padding:12px 8px;border-radius:8px;color:#fff;cursor:pointer;font-weight:bold;font-size:14px;">لوحة تحكم المتجر</button>' +
				'</div>' : '');
		}

		function showFailed(box) {
			box.style.background = '#fef2f2';
			box.style.borderColor = '#fecaca';
			box.innerHTML = '<p style="color:#dc2626;font-weight:bold;margin:0;">فشل إنشاء المتجر</p>' +
				'<p style="color:#6b7280;font-size:12px;margin:8px 0 0;">يرجى إعادة تحميل الصفحة للمحاولة مرة أخرى.</p>';
		}

		boxes.forEach(function(box) {
			var subId = box.getAttribute('data-subscription-id');
			if (!subId) return;

			var poll = function() {
				var data = new FormData();
				data.append('action', 'yaamama_check_provision_status');
				data.append('subscription_id', subId);

				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					if (!res.success || !res.data) { setTimeout(poll, 5000); return; }
					var d = res.data;

					if (d.status === 'completed' || d.status === 'active') {
						var bar = box.querySelector('.provision-bar-fill');
						var pct = box.querySelector('.provision-percent');
						if (bar) bar.style.width = '100%';
						if (pct) pct.textContent = '100%';
						setTimeout(function() { showReady(box, d.store_url); }, 800);
						return;
					}

					if (d.status === 'failed') {
						showFailed(box);
						return;
					}

					var progress = d.progress || 0;
					var bar = box.querySelector('.provision-bar-fill');
					var pct = box.querySelector('.provision-percent');
					var label = box.querySelector('.provision-step-label');
					if (bar) bar.style.width = progress + '%';
					if (pct) pct.textContent = progress + '%';
					if (label && d.step_label) label.textContent = d.step_label + '...';
					if (d.steps) renderSteps(box, d.steps);

					setTimeout(poll, 4000);
				})
				.catch(function() { setTimeout(poll, 8000); });
			};

			setTimeout(poll, 2000);
		});
	})();

	// Auto-login button handler
	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.yaamama-auto-login-btn');
		if (!btn) return;
		e.preventDefault();

		var subId = btn.getAttribute('data-subscription-id');
		if (!subId) return;

		var origText = btn.textContent;
		btn.textContent = 'جاري فتح لوحة التحكم...';
		btn.disabled = true;

		var formData = new FormData();
		formData.append('action', 'yaamama_store_auto_login');
		formData.append('subscription_id', subId);

		fetch('<?php echo esc_url( admin_url("admin-ajax.php") ); ?>', {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(r) { return r.text(); })
		.then(function(text) {
			var resp;
			try { resp = JSON.parse(text); } catch (e) {
				alert('حدث خطأ في الاتصال. تأكد من أن الموقع يعمل أو جرّب لاحقاً.');
				btn.textContent = origText;
				btn.disabled = false;
				return;
			}
			if (resp.success && resp.data && resp.data.login_url) {
				window.open(resp.data.login_url, '_blank');
			} else {
				alert(resp.data || 'فشل فتح لوحة التحكم');
			}
			btn.textContent = origText;
			btn.disabled = false;
		})
		.catch(function(err) {
			alert('حدث خطأ في الاتصال. تحقق من اتصالك بالإنترنت أو جرّب لاحقاً.');
			btn.textContent = origText;
			btn.disabled = false;
		});
	});
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'yaamama_subscriptions', 'yaamama_subscriptions_shortcode' );

function yaamama_pricing_shortcode( $atts ) {
	global $wpdb;
	$tables = yaamama_subscriptions_tables();

	$plans = $wpdb->get_results(
		"SELECT * FROM {$tables['plans']} WHERE status = 'active' ORDER BY id ASC",
		ARRAY_A
	);
	$prices_map = yaamama_subscriptions_get_plan_prices_map();

	$type_labels = array(
		'retail'  => array( 'label' => 'متاجر',  'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' ),
		'booking' => array( 'label' => 'حجوزات', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' ),
		'rental'  => array( 'label' => 'تأجير',  'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>' ),
	);

	$grouped = array();
	foreach ( $plans as $plan ) {
		$features = yaamama_subscriptions_parse_plan_features( $plan );
		$type = $features['store_type'] ?? 'retail';
		if ( ! isset( $grouped[ $type ] ) ) {
			$grouped[ $type ] = array();
		}
		$plan['_features'] = $features;
		$plan['_prices']   = $prices_map[ (int) $plan['id'] ] ?? array();
		$grouped[ $type ][] = $plan;
	}

	if ( empty( $grouped ) ) {
		return '<p style="text-align:center;padding:40px;">لا توجد باقات متاحة حالياً.</p>';
	}

	$active_types = array_keys( $grouped );
	$first_type   = $active_types[0];

	$tier_icons = array(
		0 => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
		1 => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
		2 => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
	);

	$check_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';

	ob_start();
	?>
	<div class="yp-pricing-section">
		<?php if ( count( $active_types ) > 1 ) : ?>
		<div class="yp-tabs">
			<?php foreach ( $active_types as $i => $type ) :
				$info = $type_labels[ $type ] ?? array( 'label' => $type, 'icon' => '' );
			?>
			<button class="yp-tab<?php echo $type === $first_type ? ' active' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>">
				<?php echo $info['icon']; ?>
				<span><?php echo esc_html( $info['label'] ); ?></span>
			</button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php foreach ( $grouped as $type => $type_plans ) : ?>
		<div class="yp-plans-group<?php echo $type === $first_type ? ' active' : ''; ?>" data-group="<?php echo esc_attr( $type ); ?>">
			<div class="yp-plans-track">
				<?php
				$highlight_idx = count( $type_plans ) > 1 ? 1 : 0;
				foreach ( $type_plans as $idx => $plan ) :
					$is_highlight = ( $idx === $highlight_idx );
					$month_price  = $plan['_prices']['month'] ?? null;
					$price_val    = $month_price ? number_format( (float) $month_price['price'], 0 ) : '0';
					$trial_days   = $month_price ? (int) $month_price['trial_days'] : 0;
					$is_free      = ! empty( $plan['is_free'] );
					$features     = $plan['_features'];
					$desc_lines   = array_filter( array_map( 'trim', explode( "\n", $plan['description'] ?? '' ) ) );
					$icon         = $tier_icons[ $idx ] ?? $tier_icons[0];

					$feature_labels = yaamama_subscriptions_feature_labels();
					$feature_list = array();
					if ( ! empty( $desc_lines ) ) {
						$feature_list = $desc_lines;
					} else {
						if ( (int) $features['max_products'] === -1 ) {
							$feature_list[] = 'منتجات غير محدودة';
						} else {
							$feature_list[] = 'حتى ' . $features['max_products'] . ' منتج';
						}
						foreach ( $feature_labels as $fk => $fv ) {
							if ( $fv['type'] === 'checkbox' && ! empty( $features[ $fk ] ) ) {
								$feature_list[] = $fv['label'];
							}
						}
						if ( isset( $features['support_priority'] ) ) {
							$sp_map = array( 'standard' => 'دعم فني على مدار الساعة', 'priority' => 'دعم أولوية', 'vip' => 'دعم VIP مخصص' );
							$feature_list[] = $sp_map[ $features['support_priority'] ] ?? 'دعم فني';
						}
					}
				?>
				<div class="yp-card<?php echo $is_highlight ? ' yp-card--highlight' : ''; ?>">
					<?php if ( $is_highlight ) : ?>
					<div class="yp-badge-recommend">
						<?php echo $tier_icons[1]; ?>
						<span>اختيارنا</span>
					</div>
					<?php endif; ?>
					<div class="yp-card-header">
						<div class="yp-tier-icon"><?php echo $icon; ?></div>
						<h3 class="yp-plan-name"><?php echo esc_html( $plan['name'] ); ?></h3>
					</div>
					<?php if ( ! empty( $desc_lines ) ) : ?>
					<p class="yp-plan-desc"><?php echo esc_html( $desc_lines[0] ); ?></p>
					<?php endif; ?>
					<div class="yp-price">
						<?php if ( $is_free ) : ?>
						<span class="yp-price-amount">مجاني</span>
						<?php else : ?>
						<span class="yp-price-amount"><?php echo esc_html( $price_val ); ?></span>
						<span class="yp-price-currency">ر.س</span>
						<span class="yp-price-period">/ شهرياً</span>
						<?php endif; ?>
					</div>
					<ul class="yp-features">
						<?php foreach ( $feature_list as $fi => $feat ) :
							if ( $fi === 0 && ! empty( $desc_lines ) ) continue;
						?>
						<li><?php echo $check_svg; ?><span><?php echo esc_html( $feat ); ?></span></li>
						<?php endforeach; ?>
					</ul>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'yaamama_subscribe', 'yaamama_subscribe_nonce' ); ?>
						<input type="hidden" name="action" value="yaamama_subscribe">
						<input type="hidden" name="product_id" value="0">
						<?php if ( $month_price ) : ?>
						<input type="hidden" name="plan_price_id" value="<?php echo esc_attr( $month_price['id'] ); ?>">
						<?php endif; ?>
						<button type="submit" class="yp-cta<?php echo $is_highlight ? ' yp-cta--highlight' : ''; ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
							<span>ابدأ التجربة المجانية</span>
						</button>
					</form>
					<p class="yp-trial-info">
						<?php if ( $trial_days > 0 ) : ?>
						تجربة مجانية <?php echo esc_html( $trial_days ); ?> يوم • بدون بطاقة • تلغي متى شئت
						<?php else : ?>
						بدون بطاقة • تلغي متى شئت
						<?php endif; ?>
					</p>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="yp-dots">
				<?php foreach ( $type_plans as $di => $dp ) : ?>
				<span class="yp-dot<?php echo $di === 0 ? ' active' : ''; ?>" data-index="<?php echo $di; ?>"></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	yaamama_pricing_inline_styles();
	yaamama_pricing_inline_scripts();
	return ob_get_clean();
}
add_shortcode( 'yaamama_pricing', 'yaamama_pricing_shortcode' );

function yaamama_pricing_inline_styles() { ?>
<style>
.yp-pricing-section {
	max-width: 1100px;
	margin: 0 auto;
	padding: 40px 16px;
	font-family: 'Tajawal', 'Inter', sans-serif;
	direction: rtl;
}
.yp-tabs {
	display: flex;
	justify-content: center;
	gap: 4px;
	margin-bottom: 40px;
	background: #f3f4f6;
	border-radius: 12px;
	padding: 4px;
	width: fit-content;
	margin-left: auto;
	margin-right: auto;
}
.yp-tab {
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 10px 24px;
	border: none;
	background: transparent;
	border-radius: 10px;
	cursor: pointer;
	font-size: 14px;
	font-weight: 600;
	color: #6b7280;
	transition: all 0.25s ease;
	font-family: inherit;
}
.yp-tab:hover { color: #374151; }
.yp-tab.active {
	background: #fff;
	color: #111827;
	box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.yp-plans-group { display: none; }
.yp-plans-group.active { display: block; }
.yp-plans-track {
	display: flex;
	gap: 24px;
	justify-content: center;
	align-items: stretch;
}
.yp-card {
	background: #fff;
	border: 2px solid #e5e7eb;
	border-radius: 16px;
	padding: 32px 28px;
	flex: 1;
	max-width: 340px;
	display: flex;
	flex-direction: column;
	position: relative;
	transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.yp-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 12px 40px rgba(0,0,0,0.08);
}
.yp-card--highlight {
	border-color: #16a34a;
	box-shadow: 0 8px 30px rgba(22,163,74,0.12);
}
.yp-card--highlight:hover {
	box-shadow: 0 12px 40px rgba(22,163,74,0.18);
}
.yp-badge-recommend {
	position: absolute;
	top: -1px;
	left: 50%;
	transform: translateX(-50%) translateY(-50%);
	background: #16a34a;
	color: #fff;
	padding: 4px 16px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 700;
	display: flex;
	align-items: center;
	gap: 4px;
	white-space: nowrap;
}
.yp-badge-recommend svg { stroke: #fff; width: 13px; height: 13px; }
.yp-card-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 8px;
}
.yp-tier-icon {
	width: 36px;
	height: 36px;
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 10px;
	background: #f0fdf4;
	color: #16a34a;
	flex-shrink: 0;
}
.yp-card--highlight .yp-tier-icon { background: #dcfce7; }
.yp-plan-name {
	font-size: 1.15rem;
	font-weight: 700;
	color: #111827;
	margin: 0;
}
.yp-plan-desc {
	font-size: 0.82rem;
	color: #6b7280;
	margin: 0 0 16px;
	line-height: 1.5;
}
.yp-price {
	display: flex;
	align-items: baseline;
	gap: 4px;
	margin-bottom: 24px;
	direction: ltr;
	justify-content: flex-end;
}
.yp-price-amount {
	font-size: 2.4rem;
	font-weight: 800;
	color: #111827;
	line-height: 1;
}
.yp-price-currency {
	font-size: 1rem;
	font-weight: 600;
	color: #6b7280;
}
.yp-price-period {
	font-size: 0.85rem;
	color: #9ca3af;
}
.yp-features {
	list-style: none;
	padding: 0;
	margin: 0 0 28px;
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.yp-features li {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 0.88rem;
	color: #374151;
	line-height: 1.4;
}
.yp-features li svg { flex-shrink: 0; }
.yp-cta {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	width: 100%;
	padding: 14px 20px;
	border: 2px solid #16a34a;
	border-radius: 12px;
	background: #fff;
	color: #16a34a;
	font-size: 0.95rem;
	font-weight: 700;
	cursor: pointer;
	transition: all 0.2s ease;
	font-family: inherit;
}
.yp-cta:hover {
	background: #f0fdf4;
}
.yp-cta--highlight {
	background: #16a34a;
	color: #fff;
	border-color: #16a34a;
}
.yp-cta--highlight:hover {
	background: #15803d;
	border-color: #15803d;
}
.yp-cta svg { transition: transform 0.2s; }
.yp-cta:hover svg { transform: translateX(-3px); }
.yp-trial-info {
	text-align: center;
	font-size: 0.75rem;
	color: #9ca3af;
	margin: 12px 0 0;
}
.yp-dots {
	display: none;
	justify-content: center;
	gap: 8px;
	margin-top: 24px;
}
.yp-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: #d1d5db;
	transition: all 0.25s;
	cursor: pointer;
}
.yp-dot.active {
	background: #16a34a;
	width: 24px;
	border-radius: 4px;
}

@media (max-width: 768px) {
	.yp-pricing-section { padding: 24px 0; }
	.yp-tabs { margin-bottom: 24px; padding: 3px; }
	.yp-tab { padding: 8px 16px; font-size: 13px; }
	.yp-plans-track {
		flex-direction: row;
		overflow-x: auto;
		scroll-snap-type: x mandatory;
		-webkit-overflow-scrolling: touch;
		gap: 16px;
		padding: 0 24px 8px;
		justify-content: flex-start;
		scrollbar-width: none;
	}
	.yp-plans-track::-webkit-scrollbar { display: none; }
	.yp-card {
		min-width: 280px;
		max-width: 300px;
		flex-shrink: 0;
		scroll-snap-align: center;
	}
	.yp-dots { display: flex; }
	.yp-price-amount { font-size: 2rem; }
}
</style>
<?php }

function yaamama_pricing_inline_scripts() { ?>
<script>
(function(){
	var section = document.querySelector('.yp-pricing-section');
	if (!section) return;

	var tabs = section.querySelectorAll('.yp-tab');
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var type = this.getAttribute('data-type');
			tabs.forEach(function(t){ t.classList.remove('active'); });
			this.classList.add('active');
			section.querySelectorAll('.yp-plans-group').forEach(function(g){
				g.classList.remove('active');
			});
			var target = section.querySelector('.yp-plans-group[data-group="' + type + '"]');
			if (target) {
				target.classList.add('active');
				initTrackScroll(target);
			}
		});
	});

	function initTrackScroll(group) {
		var track = group.querySelector('.yp-plans-track');
		var dots  = group.querySelectorAll('.yp-dot');
		if (!track || !dots.length) return;

		track.scrollLeft = 0;
		updateDots(track, dots);

		track.addEventListener('scroll', function() {
			updateDots(track, dots);
		});

		dots.forEach(function(dot) {
			dot.addEventListener('click', function() {
				var idx = parseInt(this.getAttribute('data-index'));
				var cards = track.querySelectorAll('.yp-card');
				if (cards[idx]) {
					cards[idx].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
				}
			});
		});
	}

	function updateDots(track, dots) {
		var cards = track.querySelectorAll('.yp-card');
		if (!cards.length) return;
		var trackRect = track.getBoundingClientRect();
		var center = trackRect.left + trackRect.width / 2;
		var closest = 0;
		var minDist = Infinity;
		cards.forEach(function(card, i) {
			var rect = card.getBoundingClientRect();
			var cardCenter = rect.left + rect.width / 2;
			var dist = Math.abs(cardCenter - center);
			if (dist < minDist) { minDist = dist; closest = i; }
		});
		dots.forEach(function(d, i) {
			d.classList.toggle('active', i === closest);
		});
	}

	section.querySelectorAll('.yp-plans-group.active').forEach(function(g) {
		initTrackScroll(g);
	});
})();
</script>
<?php }

function yaamama_subscriptions_cancel_subscription() {
	if ( ! isset( $_POST['yaamama_cancel_subscription_nonce'] ) || ! wp_verify_nonce( $_POST['yaamama_cancel_subscription_nonce'], 'yaamama_cancel_subscription' ) ) {
		wp_die( 'Invalid request' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/login' ) );
		exit;
	}

	$subscription_id = (int) ( $_POST['subscription_id'] ?? 0 );
	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$sub    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['subs']} WHERE id = %d", $subscription_id ), ARRAY_A );

	if ( ! $sub || (int) $sub['user_id'] !== get_current_user_id() ) {
		wp_die( 'Unauthorized' );
	}

	// Suspend the actual store on the Store Host via Factory API
	$store_id = get_option( "yaamama_store_for_sub_{$subscription_id}", '' );
	if ( $store_id ) {
		$suspend_result = yaamama_factory_api_call( 'POST', "/stores/{$store_id}/suspend" );
		if ( is_wp_error( $suspend_result ) ) {
			error_log( "Yamama: Failed to suspend store {$store_id}: " . $suspend_result->get_error_message() );
		} else {
			update_option( "yaamama_store_status_for_sub_{$subscription_id}", 'suspended' );
		}
	}

	$wpdb->update(
		$tables['subs'],
		array(
			'status'     => 'cancelled',
			'ends_at'    => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $subscription_id )
	);
	yaamama_subscriptions_add_event( $subscription_id, 'cancelled' );
	$referer = wp_get_referer();
	if ( $referer ) {
		$referer = explode( '#', $referer )[0];
		$referer = $referer . '#page-2';
	}
	wp_safe_redirect( $referer ?: home_url( '/my-account#page-2' ) );
	exit;
}
add_action( 'admin_post_yaamama_cancel_subscription', 'yaamama_subscriptions_cancel_subscription' );

function yaamama_subscriptions_rename_store() {
	if ( ! isset( $_POST['yaamama_rename_store_nonce'] ) || ! wp_verify_nonce( $_POST['yaamama_rename_store_nonce'], 'yaamama_rename_store' ) ) {
		wp_die( 'Invalid request' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/login' ) );
		exit;
	}

	$subscription_id = (int) ( $_POST['subscription_id'] ?? 0 );
	$store_name      = sanitize_text_field( wp_unslash( $_POST['store_name'] ?? '' ) );
	if ( ! $subscription_id || $store_name === '' ) {
		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$sub    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['subs']} WHERE id = %d", $subscription_id ), ARRAY_A );

	if ( ! $sub || (int) $sub['user_id'] !== get_current_user_id() ) {
		wp_die( 'Unauthorized' );
	}

	$wpdb->update(
		$tables['subs'],
		array(
			'store_name' => $store_name,
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => $subscription_id )
	);
	yaamama_subscriptions_add_event( $subscription_id, 'store_name_updated', array( 'store_name' => $store_name ) );
	$referer = wp_get_referer();
	if ( $referer ) {
		$referer = explode( '#', $referer )[0];
		$referer = $referer . '#page-2';
	}
	wp_safe_redirect( $referer ?: home_url( '/my-account#page-2' ) );
	exit;
}
add_action( 'admin_post_yaamama_rename_store', 'yaamama_subscriptions_rename_store' );

function yaamama_subscriptions_switch_subscription() {
	if ( ! isset( $_POST['yaamama_switch_subscription_nonce'] ) || ! wp_verify_nonce( $_POST['yaamama_switch_subscription_nonce'], 'yaamama_switch_subscription' ) ) {
		wp_die( 'Invalid request' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/login' ) );
		exit;
	}

	$subscription_id = (int) ( $_POST['subscription_id'] ?? 0 );
	$plan_price_id   = (int) ( $_POST['plan_price_id'] ?? 0 );

	global $wpdb;
	$tables = yaamama_subscriptions_tables();
	$sub    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['subs']} WHERE id = %d", $subscription_id ), ARRAY_A );

	if ( ! $sub || (int) $sub['user_id'] !== get_current_user_id() ) {
		wp_die( 'Unauthorized' );
	}
	if ( $plan_price_id && (int) $sub['plan_price_id'] === $plan_price_id ) {
		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}

	$price_row = yaamama_subscriptions_get_price( $plan_price_id );
	if ( ! $price_row ) {
		wp_die( 'Invalid plan price' );
	}

	$current_price_row = yaamama_subscriptions_get_price( $sub['plan_price_id'] );
	$current_price = $current_price_row ? (float) $current_price_row['price'] : 0.0;
	$new_price = (float) $price_row['price'];
	$difference = round( $new_price - $current_price, 2 );
	$requires_full_charge = ! in_array( $sub['status'], array( 'active', 'past_due' ), true );
	$charge_total = $requires_full_charge ? $new_price : $difference;

	if ( $charge_total > 0 ) {
		$order = yaamama_subscriptions_create_switch_order( $subscription_id, (int) $sub['product_id'], (int) $sub['plan_price_id'], $price_row, $charge_total, (int) $sub['user_id'] );
		if ( is_wp_error( $order ) ) {
			wp_die( $order->get_error_message() );
		}

		$wpdb->update(
			$tables['subs'],
			array(
				'status'     => 'pending_payment',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $subscription_id )
		);
		yaamama_subscriptions_add_event(
			$subscription_id,
			'plan_switch_pending',
			array(
				'order_id'      => $order->get_id(),
				'plan_price_id' => $plan_price_id,
				'charge_total'  => $charge_total,
			)
		);
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	$next_billing = yaamama_subscriptions_get_next_billing( current_time( 'mysql' ), $price_row['period'] );
	$wpdb->update(
		$tables['subs'],
		array(
			'plan_price_id'  => $plan_price_id,
			'next_billing_at'=> $next_billing,
			'updated_at'     => current_time( 'mysql' ),
		),
		array( 'id' => $subscription_id )
	);
	yaamama_subscriptions_add_event( $subscription_id, 'plan_switched', array( 'plan_price_id' => $plan_price_id ) );
	wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
	exit;
}
add_action( 'admin_post_yaamama_switch_subscription', 'yaamama_subscriptions_switch_subscription' );
