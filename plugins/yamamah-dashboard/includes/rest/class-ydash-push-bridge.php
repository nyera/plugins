<?php
/**
 * Push store events to Merchant Gateway (HMAC signed).
 *
 * Single source of truth for user-facing alerts: yamamah_notification_added.
 * Direct WooCommerce hooks are only used for status changes that do NOT
 * already create an in-dashboard notification (avoids duplicate gateway events).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ydash_Push_Bridge {

	/** Statuses that already emit via yamamah_add_notification(). */
	const NOTIFIED_STATUSES = array( 'cancelled', 'refunded' );

	public static function init() {
		add_action( 'yamamah_notification_added', array( __CLASS__, 'on_notification' ), 10, 1 );
		// New orders are covered by the notification hook (woocommerce_new_order → yamamah_add_notification).
		// Only push status changes that are not already notification-backed.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status' ), 20, 3 );
	}

	public static function on_notification( $notification ) {
		$order_id = 0;
		if ( ! empty( $notification['url'] ) && preg_match( '/order_id=(\d+)/', $notification['url'], $m ) ) {
			$order_id = (int) $m[1];
		}

		$type = 'notification';
		if ( ( $notification['type'] ?? '' ) === 'order' ) {
			$type = 'order.new';
		} elseif ( in_array( $notification['type'] ?? '', array( 'cancel', 'refund' ), true ) ) {
			$type = 'order.status';
		}

		$event = array(
			'type'     => $type,
			'order_id' => $order_id ?: ( ! empty( $notification['order_id'] ) ? (int) $notification['order_id'] : null ),
			'title'    => $notification['title'] ?? '',
			'body'     => $notification['message'] ?? '',
			'data'     => $notification,
		);

		if ( ! empty( $notification['provider_id'] ) ) {
			$event['provider_id'] = (int) $notification['provider_id'];
		}

		if ( $event['order_id'] && function_exists( 'yamamah_booking_notification_recipient_user_ids' ) ) {
			$recipients = yamamah_booking_notification_recipient_user_ids( (int) $event['order_id'] );
			if ( ! empty( $recipients ) ) {
				$event['recipient_user_ids'] = $recipients;
			}
			if ( empty( $event['provider_id'] ) && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( (int) $event['order_id'] );
				if ( $order ) {
					$provider_id = (int) $order->get_meta( '_lumiere_booking_expert_provider_id', true );
					if ( $provider_id ) {
						$event['provider_id'] = $provider_id;
					}
				}
			}
		} else {
			// Stock / new-customer / etc.: merchants & staff only — never broadcast to providers.
			$staff = array();
			if ( function_exists( 'yamamah_staff_notification_recipient_user_ids' ) ) {
				$staff = yamamah_staff_notification_recipient_user_ids();
			} elseif ( function_exists( 'yamamah_booking_notification_recipient_user_ids' ) ) {
				$staff = yamamah_booking_notification_recipient_user_ids( 0 );
			}
			if ( ! empty( $staff ) ) {
				$event['recipient_user_ids'] = $staff;
			}
		}

		self::dispatch( $event );
	}

	public static function on_order_status( $order_id, $old_status, $new_status ) {
		// cancelled / refunded already dispatched via yamamah_notification_added.
		if ( in_array( $new_status, self::NOTIFIED_STATUSES, true ) ) {
			return;
		}

		$event = array(
			'type'     => 'order.status',
			'order_id' => (int) $order_id,
			'title'    => 'تحديث طلب #' . $order_id,
			'body'     => $old_status . ' → ' . $new_status,
			'data'     => array(
				'old_status' => $old_status,
				'new_status' => $new_status,
			),
		);
		if ( function_exists( 'yamamah_booking_notification_recipient_user_ids' ) ) {
			$recipients = yamamah_booking_notification_recipient_user_ids( (int) $order_id );
			if ( ! empty( $recipients ) ) {
				$event['recipient_user_ids'] = $recipients;
			}
		}
		self::dispatch( $event );
	}

	public static function dispatch( array $event ) {
		$gateway  = get_option( 'yamama_merchant_gateway_url', '' );
		$token    = get_option( 'yamama_api_token', '' );
		$store_id = get_option( 'yamama_store_id', '' );

		if ( $gateway === '' || $token === '' || $store_id === '' ) {
			return;
		}

		$payload = array_merge(
			array(
				'store_id'  => $store_id,
				'timestamp' => time(),
			),
			$event
		);

		$body = wp_json_encode( $payload );
		$sig  = hash_hmac( 'sha256', $body, $token );

		$url = trailingslashit( $gateway ) . 'api/v1/events';

		wp_remote_post(
			$url,
			array(
				'timeout'  => 8,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'       => 'application/json',
					'X-Yamama-Store-Id'  => $store_id,
					'X-Yamama-Signature' => $sig,
					'X-Yamama-Api-Token' => $token,
				),
				'body'     => $body,
			)
		);
	}
}
