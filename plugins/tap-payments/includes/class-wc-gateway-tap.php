<?php
/**
 * صنف WC_Gateway_Tap: بوابة دفع WooCommerce تستخدم Tap Payments بنموذج Sub-Merchant.
 *
 * المتجر يستخدم مفتاح المنصة + merchant_id الخاص به لإنشاء Charge عند Tap،
 * ثم يعيد توجيه العميل إلى صفحة Tap للدفع، ثم يتلقى تأكيداً عبر post.url + redirect.url.
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

class WC_Gateway_Tap extends WC_Payment_Gateway {

	const TAP_API_BASE = 'https://api.tap.company';

	public function __construct() {
		$this->id                 = 'tap';
		$this->method_title       = 'Tap Payments';
		$this->method_description = 'بوابة Tap (مدى/فيزا/ماستركارد/Apple Pay/STC Pay) — تُربط تلقائياً بحساب التاجر بعد إكمال onboarding من لوحة التحكم.';
		$this->has_fields         = false;
		$this->icon               = apply_filters( 'yamama_tap_icon', '' );
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', 'الدفع الإلكتروني (Tap)' );
		$this->description = $this->get_option( 'description', 'ادفع بأمان عبر مدى أو الفيزا أو ماستركارد أو Apple Pay أو STC Pay.' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'render_receipt' ) );
		add_action( 'woocommerce_api_wc_gateway_tap_redirect', array( $this, 'handle_redirect_return' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => 'تفعيل/تعطيل',
				'type'    => 'checkbox',
				'label'   => 'تفعيل بوابة Tap',
				'default' => 'no',
			),
			'title' => array(
				'title'       => 'العنوان',
				'type'        => 'text',
				'description' => 'العنوان الذي يراه العميل في صفحة الدفع.',
				'default'     => 'الدفع الإلكتروني (Tap)',
			),
			'description' => array(
				'title'       => 'الوصف',
				'type'        => 'textarea',
				'description' => 'الوصف الذي يظهر تحت العنوان في صفحة الدفع.',
				'default'     => 'ادفع بأمان عبر مدى أو الفيزا أو ماستركارد أو Apple Pay أو STC Pay.',
			),
			'info' => array(
				'title'       => 'معلومات الربط',
				'type'        => 'title',
				'description' => $this->build_status_description(),
			),
		);
	}

	private function build_status_description() {
		$s = yamama_tap_get_settings();
		if ( empty( $s['merchant_id'] ) ) {
			return '<strong style="color:#b32d2e;">⚠ لم يكتمل الربط بعد.</strong> اذهب إلى لوحة التحكم → إعدادات الدفع واضغط "فعّل بوابة الدفع" لإكمال onboarding مع Tap.';
		}
		$mode = $s['livemode'] ? 'مباشر (Live)' : 'تجريبي (Test)';
		return sprintf(
			'<strong style="color:#0a7d3b;">✓ مرتبط</strong><br>Merchant ID: <code>%s</code><br>الوضع: %s',
			esc_html( $s['merchant_id'] ),
			esc_html( $mode )
		);
	}

	/**
	 * تعطّل البوابة تلقائياً إن لم يكتمل الربط.
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}
		$s = yamama_tap_get_settings();
		if ( empty( $s['merchant_id'] ) || empty( $s['secret_key'] ) ) {
			return false;
		}
		return parent::is_available();
	}

	/**
	 * يحوّل العميل إلى صفحة Receipt لإنشاء الـ charge ثم إعادة التوجيه إلى Tap.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( 'الطلب غير موجود', 'error' );
			return array( 'result' => 'fail' );
		}
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * يُستدعى على صفحة /checkout/order-pay/. ينشئ Charge عند Tap ويعيد توجيه العميل.
	 */
	public function render_receipt( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo '<p>الطلب غير موجود.</p>';
			return;
		}

		$charge = $this->create_charge( $order );
		if ( is_wp_error( $charge ) ) {
			echo '<p style="color:#b32d2e;">' . esc_html( 'تعذّر إنشاء عملية الدفع: ' . $charge->get_error_message() ) . '</p>';
			echo '<p><a href="' . esc_url( wc_get_checkout_url() ) . '">رجوع إلى صفحة الدفع</a></p>';
			return;
		}

		$redirect = $charge['transaction']['url'] ?? '';
		if ( ! $redirect ) {
			echo '<p style="color:#b32d2e;">لم يصل رابط الدفع من Tap.</p>';
			return;
		}

		$order->update_meta_data( '_tap_charge_id', $charge['id'] ?? '' );
		$order->save();

		echo '<p>سيتم تحويلك إلى صفحة الدفع الآمنة...</p>';
		echo '<script>setTimeout(function(){window.location.href=' . wp_json_encode( $redirect ) . ';},800);</script>';
	}

	/**
	 * إنشاء Charge عند Tap.
	 */
	private function create_charge( $order ) {
		$s = yamama_tap_get_settings();

		if ( empty( $s['secret_key'] ) || empty( $s['merchant_id'] ) ) {
			return new WP_Error( 'tap_not_configured', 'إعدادات Tap غير مكتملة في المتجر.' );
		}

		$amount  = (float) $order->get_total();
		$currency = $order->get_currency();

		$body = array(
			'amount'             => $amount,
			'currency'           => $currency,
			'customer_initiated' => true,
			'threeDSecure'       => true,
			'save_card'          => false,
			'description'        => sprintf( 'Order #%s', $order->get_order_number() ),
			'metadata'           => array(
				'wc_order_id'      => (string) $order->get_id(),
				'yamama_store_id'  => (string) $s['store_id'],
			),
			'reference' => array(
				'transaction' => 'wc_' . $order->get_id() . '_' . time(),
				'order'       => (string) $order->get_id(),
			),
			'receipt' => array(
				'email' => true,
				'sms'   => true,
			),
			'customer' => array(
				'first_name' => $order->get_billing_first_name() ?: 'Customer',
				'last_name'  => $order->get_billing_last_name() ?: '',
				'email'      => $order->get_billing_email(),
			),
			'merchant' => array(
				'id' => $s['merchant_id'],
			),
			'source' => array(
				'id' => 'src_all',
			),
			'post' => array(
				'url' => rest_url( 'yaamama-tap/v1/charge-webhook' ),
			),
			'redirect' => array(
				'url' => add_query_arg( array(
					'wc-api'   => 'wc_gateway_tap_redirect',
					'order_id' => $order->get_id(),
				), home_url( '/' ) ),
			),
		);

		if ( ! empty( $s['technology_id'] ) ) {
			$body['payment_provider'] = array(
				'technology' => array( 'id' => $s['technology_id'] ),
			);
		}
		if ( ! empty( $s['platform_id'] ) ) {
			$body['platform'] = array( 'id' => $s['platform_id'] );
		}

		$phone = $order->get_billing_phone();
		if ( $phone ) {
			$body['customer']['phone'] = array(
				'country_code' => '966',
				'number'       => preg_replace( '/\D+/', '', $phone ),
			);
		}

		$response = wp_remote_post( self::TAP_API_BASE . '/v2/charges/', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $s['secret_key'],
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$msg = is_array( $data ) && isset( $data['errors'][0]['description'] )
				? $data['errors'][0]['description']
				: ( $data['message'] ?? "HTTP {$code}" );
			error_log( "[Yamama Tap] charge create failed HTTP {$code}: {$msg}" );
			return new WP_Error( 'tap_charge_failed', $msg );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * المعالج المستدعى عند عودة العميل من Tap.
	 */
	public function handle_redirect_return() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$tap_id   = isset( $_GET['tap_id'] ) ? sanitize_text_field( wp_unslash( $_GET['tap_id'] ) ) : '';

		if ( ! $order_id ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		if ( ! $tap_id ) {
			$tap_id = $order->get_meta( '_tap_charge_id' );
		}

		$status = $this->verify_charge( $tap_id );
		if ( in_array( $status, array( 'CAPTURED', 'AUTHORIZED' ), true ) ) {
			$order->payment_complete( $tap_id );
			WC()->cart && WC()->cart->empty_cart();
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
		if ( 'FAILED' === $status || 'DECLINED' === $status ) {
			$order->update_status( 'failed', 'Tap charge ' . $status );
			wc_add_notice( 'فشل الدفع، يرجى المحاولة مرة أخرى.', 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
		wc_add_notice( 'لم يتم تأكيد الدفع بعد.', 'notice' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * يستعلم Tap عن حالة charge.
	 */
	public function verify_charge( $charge_id ) {
		if ( empty( $charge_id ) ) {
			return 'UNKNOWN';
		}
		$s = yamama_tap_get_settings();
		$response = wp_remote_get( self::TAP_API_BASE . '/v2/charges/' . rawurlencode( $charge_id ), array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $s['secret_key'],
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return 'UNKNOWN';
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && ! empty( $data['status'] ) ? $data['status'] : 'UNKNOWN';
	}
}
