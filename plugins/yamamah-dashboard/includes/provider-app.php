<?php
/**
 * مزود الخدمة كتطبيق: دور WP، ربط CPT↔مستخدم، وواجهات bridge للمزود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'yamamah_register_provider_role', 5 );
function yamamah_register_provider_role() {
	if ( ! get_role( 'yamamah_provider' ) ) {
		add_role(
			'yamamah_provider',
			'مزود خدمة',
			array(
				'read' => true,
			)
		);
	}
}

/**
 * إنشاء أو تحديث حساب دخول للمزود.
 *
 * @param int    $provider_post_id
 * @param string $email
 * @param string $name
 * @param string $password كلمة مرور جديدة (اختياري عند التحديث)
 * @return int|WP_Error user id
 */
function yamamah_sync_provider_app_user( $provider_post_id, $email, $name, $password = '' ) {
	$provider_post_id = absint( $provider_post_id );
	$email            = sanitize_email( $email );
	$name             = sanitize_text_field( $name );
	$password         = (string) $password;

	if ( ! $provider_post_id || ! $email ) {
		return new WP_Error( 'yamamah_provider_email', 'بريد مزود الخدمة مطلوب لإنشاء حساب التطبيق' );
	}

	$existing_uid = (int) get_post_meta( $provider_post_id, '_provider_user_id', true );
	$user         = $existing_uid ? get_userdata( $existing_uid ) : false;

	if ( ! $user ) {
		$by_email = get_user_by( 'email', $email );
		if ( $by_email ) {
			if ( user_can( $by_email, 'manage_woocommerce' ) || in_array( 'administrator', (array) $by_email->roles, true ) ) {
				return new WP_Error( 'yamamah_provider_email_taken', 'هذا البريد مستخدم لحساب إداري' );
			}
			$linked = (int) get_user_meta( $by_email->ID, '_linked_service_provider_id', true );
			if ( $linked && $linked !== $provider_post_id ) {
				return new WP_Error( 'yamamah_provider_email_taken', 'هذا البريد مرتبط بمزود خدمة آخر' );
			}
			$user = $by_email;
		}
	}

	if ( $user ) {
		$update = array(
			'ID'           => $user->ID,
			'display_name' => $name ?: $user->display_name,
			'user_email'   => $email,
		);
		if ( $password !== '' ) {
			// wp_set_password يحدّث الهاش مباشرة ويتجنب مشاكل wp_update_user مع بعض الإضافات.
			wp_set_password( $password, (int) $user->ID );
		}
		$result = wp_update_user( $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$user_id = (int) $user->ID;
	} else {
		if ( $password === '' ) {
			return new WP_Error( 'yamamah_provider_password', 'كلمة مرور حساب التطبيق مطلوبة' );
		}
		$login = sanitize_user( current( explode( '@', $email ) ), true );
		if ( $login === '' ) {
			$login = 'provider' . $provider_post_id;
		}
		$base = $login;
		$i    = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			$i++;
		}
		$user_id = wp_create_user( $login, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name ?: $login,
				'nickname'     => $name ?: $login,
			)
		);
		// تأكيد كلمة المرور بعد الإنشاء (بعض البيئات تعيد ضبطها).
		wp_set_password( $password, (int) $user_id );
	}

	$user_obj = new WP_User( $user_id );
	$user_obj->set_role( 'yamamah_provider' );
	// أزل manage_woocommerce إن وُجدت بالخطأ حتى لا يُعامل كتاجر كامل.
	$user_obj->remove_cap( 'manage_woocommerce' );
	update_user_meta( $user_id, '_linked_service_provider_id', $provider_post_id );
	update_user_meta( $user_id, 'yamamah_role', 'yamamah_provider' );
	update_user_meta( $user_id, 'yamamah_permissions', array() );
	update_post_meta( $provider_post_id, '_provider_user_id', $user_id );

	return $user_id;
}

function yamamah_provider_payload( $provider_post_id ) {
	$post = get_post( $provider_post_id );
	if ( ! $post || $post->post_type !== 'service_provider' ) {
		return null;
	}
	$uid    = (int) get_post_meta( $provider_post_id, '_provider_user_id', true );
	$img_id = (int) get_post_thumbnail_id( $provider_post_id );
	return array(
		'id'               => (int) $provider_post_id,
		'name'             => $post->post_title,
		'specialty'        => (string) get_post_meta( $provider_post_id, '_provider_specialty', true ),
		'phone'            => (string) get_post_meta( $provider_post_id, '_provider_phone', true ),
		'email'            => (string) get_post_meta( $provider_post_id, '_provider_email', true ),
		'description'      => $post->post_content,
		'status'           => $post->post_status,
		'consultation_fee' => (string) get_post_meta( $provider_post_id, '_lumiere_consultation_fee', true ),
		'image_id'         => $img_id,
		'image_url'        => $img_id ? (string) wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '',
		'has_app_account'  => $uid > 0,
		'provider_user_id' => $uid ?: null,
	);
}

add_action( 'wp_ajax_yamamah_provider_me', 'yamamah_ajax_provider_me' );
function yamamah_ajax_provider_me() {
	check_ajax_referer( 'yamamah_nonce', 'nonce' );
	$user = wp_get_current_user();
	if ( ! yamamah_user_is_provider( $user ) ) {
		wp_send_json_error( 'غير مصرح' );
	}
	$pid = yamamah_user_provider_id( $user );
	$data = yamamah_provider_payload( $pid );
	if ( ! $data ) {
		wp_send_json_error( 'مزود الخدمة غير موجود' );
	}
	wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_provider_update_me', 'yamamah_ajax_provider_update_me' );
function yamamah_ajax_provider_update_me() {
	check_ajax_referer( 'yamamah_nonce', 'nonce' );
	$user = wp_get_current_user();
	if ( ! yamamah_user_is_provider( $user ) ) {
		wp_send_json_error( 'غير مصرح' );
	}
	$pid = yamamah_user_provider_id( $user );
	$post = get_post( $pid );
	if ( ! $post || $post->post_type !== 'service_provider' ) {
		wp_send_json_error( 'مزود الخدمة غير موجود' );
	}

	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	if ( $name !== '' ) {
		wp_update_post(
			array(
				'ID'         => $pid,
				'post_title' => $name,
			)
		);
		wp_update_user(
			array(
				'ID'           => $user->ID,
				'display_name' => $name,
			)
		);
	}

	if ( isset( $_POST['phone'] ) ) {
		update_post_meta( $pid, '_provider_phone', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );
	}
	if ( isset( $_POST['specialty'] ) ) {
		update_post_meta( $pid, '_provider_specialty', sanitize_text_field( wp_unslash( $_POST['specialty'] ) ) );
	}
	if ( isset( $_POST['description'] ) ) {
		wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => wp_kses_post( wp_unslash( $_POST['description'] ) ),
			)
		);
	}
	if ( isset( $_POST['consultation_fee'] ) ) {
		$fee = sanitize_text_field( wp_unslash( $_POST['consultation_fee'] ) );
		update_post_meta( $pid, '_lumiere_consultation_fee', $fee );
	}
	if ( ! empty( $_POST['password'] ) ) {
		wp_set_password( (string) wp_unslash( $_POST['password'] ), $user->ID );
	}

	wp_send_json_success( yamamah_provider_payload( $pid ) );
}

add_action( 'wp_ajax_yamamah_provider_my_bookings', 'yamamah_ajax_provider_my_bookings' );
function yamamah_ajax_provider_my_bookings() {
	check_ajax_referer( 'yamamah_nonce', 'nonce' );
	$user = wp_get_current_user();
	if ( ! yamamah_user_is_provider( $user ) ) {
		wp_send_json_error( 'غير مصرح' );
	}
	$pid = yamamah_user_provider_id( $user );
	if ( ! $pid || ! function_exists( 'wc_get_orders' ) ) {
		wp_send_json_success( array( 'orders' => array() ) );
	}

	$orders = wc_get_orders(
		array(
			'limit'      => 50,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_key'   => '_lumiere_booking_expert_provider_id',
			'meta_value' => (string) $pid,
			'return'     => 'objects',
		)
	);

	// Fallback: scan recent orders for line-item meta (HPOS / older carts).
	if ( empty( $orders ) ) {
		$recent = wc_get_orders(
			array(
				'limit'   => 80,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);
		$orders = array();
		foreach ( $recent as $order ) {
			$match = (int) $order->get_meta( '_lumiere_booking_expert_provider_id', true ) === $pid;
			if ( ! $match ) {
				foreach ( $order->get_items() as $item ) {
					if ( (int) $item->get_meta( '_lumiere_booking_expert_provider_id', true ) === $pid ) {
						$match = true;
						break;
					}
				}
			}
			if ( $match ) {
				$orders[] = $order;
			}
			if ( count( $orders ) >= 50 ) {
				break;
			}
		}
	}

	$rows = array();
	foreach ( $orders as $order ) {
		$rows[] = array(
			'id'           => $order->get_id(),
			'number'       => $order->get_order_number(),
			'status'       => $order->get_status(),
			'status_label' => function_exists( 'yamamah_order_status_label' ) ? yamamah_order_status_label( $order->get_status() ) : $order->get_status(),
			'total'        => $order->get_total(),
			'currency'     => $order->get_currency(),
			'date'         => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '',
			'customer'     => trim( $order->get_formatted_billing_full_name() ),
			'phone'        => $order->get_billing_phone(),
		);
	}

	wp_send_json_success( array( 'orders' => $rows, 'provider_id' => $pid ) );
}

add_action( 'wp_ajax_yamamah_provider_working_hours', 'yamamah_ajax_provider_working_hours' );
function yamamah_ajax_provider_working_hours() {
	check_ajax_referer( 'yamamah_nonce', 'nonce' );
	$user = wp_get_current_user();
	if ( ! yamamah_user_is_provider( $user ) ) {
		wp_send_json_error( 'غير مصرح' );
	}

	$settings = array();
	if ( function_exists( 'lumiere_get_yamamah_booking_settings' ) ) {
		$settings = lumiere_get_yamamah_booking_settings();
	} else {
		$settings = get_option( 'yamamah_rental_settings', array() );
	}
	$hours = is_array( $settings ) && isset( $settings['working_hours'] ) && is_array( $settings['working_hours'] )
		? $settings['working_hours']
		: array();

	wp_send_json_success(
		array(
			'working_hours' => $hours,
			'source'        => 'store',
		)
	);
}
