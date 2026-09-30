<?php
/**
 * REST endpoints لإضافة Yamama Tap Payments داخل المتجر.
 *
 *  POST /wp-json/yaamama-tap/v1/sync-merchant      : يستقبل من المنصة الأم merchant_id + keys.
 *  POST /wp-json/yaamama-tap/v1/request-onboarding : يستدعي المنصة لطلب Connect URL لهذا المتجر.
 *  POST /wp-json/yaamama-tap/v1/charge-webhook     : post.url لـ Tap لتحديث حالة الطلب.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'yamama_tap_register_rest_routes' );

function yamama_tap_register_rest_routes() {
	register_rest_route( 'yaamama-tap/v1', '/sync-merchant', array(
		'methods'             => 'POST',
		'callback'            => 'yamama_tap_rest_sync_merchant',
		'permission_callback' => 'yamama_tap_rest_platform_permission',
	) );

	register_rest_route( 'yaamama-tap/v1', '/request-onboarding', array(
		'methods'             => 'POST',
		'callback'            => 'yamama_tap_rest_request_onboarding',
		'permission_callback' => 'yamama_tap_rest_admin_permission',
	) );

	register_rest_route( 'yaamama-tap/v1', '/upload-document', array(
		'methods'             => 'POST',
		'callback'            => 'yamama_tap_rest_upload_document',
		'permission_callback' => 'yamama_tap_rest_admin_permission',
	) );

	register_rest_route( 'yaamama-tap/v1', '/charge-webhook', array(
		'methods'             => 'POST',
		'callback'            => 'yamama_tap_rest_charge_webhook',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'yaamama-tap/v1', '/status', array(
		'methods'             => 'GET',
		'callback'            => 'yamama_tap_rest_status',
		'permission_callback' => 'yamama_tap_rest_admin_permission',
	) );
}

/**
 * مصادقة طلبات قادمة من المنصة الأم: Bearer = yamama_api_token المخزّن في options.
 */
function yamama_tap_rest_platform_permission( $request ) {
	$auth = $request->get_header( 'authorization' );
	$token = '';
	if ( $auth && stripos( $auth, 'Bearer ' ) === 0 ) {
		$token = trim( substr( $auth, 7 ) );
	}
	if ( ! $token ) {
		return new WP_Error( 'no_token', 'Missing Bearer token', array( 'status' => 401 ) );
	}
	$expected = get_option( 'yamama_api_token', '' );
	if ( ! $expected || ! hash_equals( $expected, $token ) ) {
		return new WP_Error( 'bad_token', 'Invalid platform token', array( 'status' => 403 ) );
	}
	return true;
}

/**
 * مصادقة طلبات قادمة من لوحة WP الإدارية للمتجر.
 */
function yamama_tap_rest_admin_permission() {
	return current_user_can( 'manage_woocommerce' );
}

/**
 * يستقبل بيانات Tap من المنصة الأم ويحفظها كخيارات WP.
 */
function yamama_tap_rest_sync_merchant( $request ) {
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) ) {
		return new WP_REST_Response( array( 'error' => 'invalid_body' ), 400 );
	}

	$fields = array(
		'merchant_id'   => 'yamama_tap_merchant_id',
		'technology_id' => 'yamama_tap_technology_id',
		'platform_id'   => 'yamama_tap_platform_id',
		'secret_key'    => 'yamama_tap_secret_key',
		'test_mode'     => 'yamama_tap_test_mode',
		'livemode'      => 'yamama_tap_livemode',
	);
	$updated = array();
	foreach ( $fields as $key => $option_name ) {
		if ( ! array_key_exists( $key, $payload ) ) {
			continue;
		}
		$value = is_scalar( $payload[ $key ] ) ? sanitize_text_field( (string) $payload[ $key ] ) : '';
		update_option( $option_name, $value, false );
		$updated[] = $option_name;
	}

	if ( ! empty( $payload['merchant_id'] ) ) {
		$gw_options = get_option( 'woocommerce_tap_settings', array() );
		if ( ! is_array( $gw_options ) ) {
			$gw_options = array();
		}
		$gw_options['enabled'] = 'yes';
		update_option( 'woocommerce_tap_settings', $gw_options );
	}

	return new WP_REST_Response( array( 'received' => true, 'updated' => $updated ), 200 );
}

/**
 * يطلب من المنصة الأم إنشاء Lead لهذا المتجر بكل الحقول والوثائق.
 *
 * Body المستلم من dashboard التاجر:
 *   brand_name, country, currency,
 *   user { first_name, middle_name, last_name, title, email, phone_country, phone_number,
 *          national_id, identification_type, nationality, identification_country, birth_date },
 *   entity { legal_name, entity_type, license_number, license_country, license_issue_date,
 *            license_expiry_date, tax_number, tax_issue_date, tax_expiry_date, carries_a_license },
 *   bank { name, number, iban, swift },
 *   documents { commercial_registration, identity_document, bank_certificate, tax_document,
 *               memorandum_association, article_of_association, business_logo },
 *   redirect_url
 */
function yamama_tap_rest_request_onboarding( $request ) {
	$s = yamama_tap_get_settings();
	if ( empty( $s['platform_url'] ) || empty( $s['platform_token'] ) || empty( $s['store_id'] ) ) {
		return new WP_REST_Response( array(
			'error'   => 'platform_not_configured',
			'message' => 'بيانات المنصة الأم غير مضبوطة في خيارات WP (yamama_platform_url, yamama_api_token, yamama_store_id).',
		), 503 );
	}

	$payload = (array) $request->get_json_params();

	// إن لم تُرسل documents في الـ payload، نقرأها من options المحفوظة (yamama_tap_doc_*)
	$documents = isset( $payload['documents'] ) && is_array( $payload['documents'] ) ? $payload['documents'] : array();
	$purposes = function( ) {
		return array(
			'commercial_registration', 'identity_document', 'bank_certificate',
			'tax_document_user_upload', 'tax_document',
			'memorandum_association', 'article_of_association', 'business_logo',
		);
	};
	foreach ( $purposes() as $p ) {
		if ( empty( $documents[ $p ] ) ) {
			$fid = (string) get_option( 'yamama_tap_doc_' . sanitize_key( $p ), '' );
			if ( $fid !== '' ) {
				$documents[ $p ] = $fid;
			}
		}
	}
	// alias: tax_document_user_upload → tax_document لتسهيل المعالجة في tap-client.
	if ( ! empty( $documents['tax_document_user_upload'] ) && empty( $documents['tax_document'] ) ) {
		$documents['tax_document'] = $documents['tax_document_user_upload'];
	}

	$store_url = home_url( '/' );
	$default_redirect = trailingslashit( admin_url() ) . 'admin.php?page=yamamah-payment-settings&tap_returned=1';

	$body = array_merge(
		array(
			'store_id'     => (string) $s['store_id'],
			'store_url'    => $store_url,
			'brand_name'   => get_bloginfo( 'name' ),
			'country'      => 'SA',
			'currency'     => 'SAR',
			'redirect_url' => $default_redirect,
		),
		$payload,
		array(
			'documents' => $documents,
			'metadata'  => array_merge(
				is_array( $payload['metadata'] ?? null ) ? $payload['metadata'] : array(),
				array(
					'yamama_store_id'  => (string) $s['store_id'],
					'yamama_store_url' => $store_url,
					'submitted_at'     => current_time( 'mysql' ),
				)
			),
		)
	);

	$response = wp_remote_post( rtrim( $s['platform_url'], '/' ) . '/wp-json/yaamama/tap/v1/lead', array(
		'timeout' => 60,
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $s['platform_token'],
		),
		'body' => wp_json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response( array( 'error' => 'http_error', 'message' => $response->get_error_message() ), 502 );
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code >= 400 ) {
		return new WP_REST_Response( array( 'error' => 'platform_http_' . $code, 'data' => $data ), 502 );
	}

	if ( is_array( $data ) && ! empty( $data['connect_url'] ) ) {
		update_option( 'yamama_tap_connect_url', $data['connect_url'], false );
		update_option( 'yamama_tap_status', $data['status'] ?? 'connect_sent', false );
	}
	if ( is_array( $data ) && ! empty( $data['lead_id'] ) ) {
		update_option( 'yamama_tap_lead_id', $data['lead_id'], false );
		update_option( 'yamama_tap_status', $data['status'] ?? 'lead_created', false );
	}
	if ( is_array( $data ) && ! empty( $data['merchant_id'] ) ) {
		update_option( 'yamama_tap_merchant_id', $data['merchant_id'], false );
	}

	return new WP_REST_Response( $data, 200 );
}

/**
 * يستلم ملف وثيقة من dashboard التاجر، ويُمرّره عبر multipart إلى المنصة الأم
 * (التي بدورها ترفعه إلى Tap Files API بمفتاح المنصة)، ثم يحفظ file_id في
 * خيار `yamama_tap_doc_{purpose}` ليُستخدم لاحقاً عند بناء Lead.
 *
 * Body (multipart/form-data):
 *  - file       : الملف نفسه
 *  - purpose    : commercial_registration | identity_document | bank_certificate | tax_document_user_upload | memorandum_association | article_of_association | business_logo
 *  - title      : (اختياري)
 */
function yamama_tap_rest_upload_document( $request ) {
	$s = yamama_tap_get_settings();
	if ( empty( $s['platform_url'] ) || empty( $s['platform_token'] ) || empty( $s['store_id'] ) ) {
		return new WP_REST_Response( array(
			'error'   => 'platform_not_configured',
			'message' => 'بيانات المنصة الأم غير مضبوطة في خيارات WP.',
		), 503 );
	}

	$files   = $request->get_file_params();
	$purpose = sanitize_text_field( (string) ( $request->get_param( 'purpose' ) ?: '' ) );
	$title   = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: '' ) );

	if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
		return new WP_REST_Response( array( 'error' => 'no_file', 'message' => 'لم يُرفق ملف.' ), 400 );
	}
	if ( $purpose === '' ) {
		return new WP_REST_Response( array( 'error' => 'no_purpose', 'message' => 'نوع الوثيقة مطلوب.' ), 400 );
	}
	$upload_err = (int) ( $files['file']['error'] ?? UPLOAD_ERR_OK );
	if ( UPLOAD_ERR_OK !== $upload_err ) {
		return new WP_REST_Response( array(
			'error'   => 'upload_error',
			'message' => 'فشل رفع الملف للمتجر (' . $upload_err . ').',
		), 400 );
	}

	$tmp_path      = (string) $files['file']['tmp_name'];
	$original_name = (string) ( $files['file']['name'] ?? 'document' );
	$mime          = (string) ( $files['file']['type'] ?? 'application/octet-stream' );

	$upstream = yamama_tap_proxy_multipart_upload(
		rtrim( $s['platform_url'], '/' ) . '/wp-json/yaamama/tap/v1/upload-file',
		$s['platform_token'],
		array(
			'purpose'  => $purpose,
			'title'    => $title,
			'store_id' => (string) $s['store_id'],
		),
		array(
			'file' => array(
				'path' => $tmp_path,
				'name' => sanitize_file_name( $original_name ),
				'mime' => $mime,
			),
		)
	);

	if ( is_wp_error( $upstream ) ) {
		return new WP_REST_Response( array(
			'error'   => $upstream->get_error_code(),
			'message' => $upstream->get_error_message(),
			'data'    => $upstream->get_error_data(),
		), 502 );
	}

	$code = (int) ( $upstream['code'] ?? 0 );
	$body = $upstream['body'] ?? array();

	if ( $code >= 400 || empty( $body['file_id'] ) ) {
		return new WP_REST_Response( array(
			'error'   => 'platform_upload_failed',
			'message' => $body['message'] ?? "Upload upstream HTTP {$code}",
			'data'    => $body,
		), 502 );
	}

	$option_key = 'yamama_tap_doc_' . sanitize_key( $purpose );
	update_option( $option_key, $body['file_id'], false );
	update_option( $option_key . '_meta', array(
		'name' => $original_name,
		'size' => (int) ( $body['size'] ?? 0 ),
		'type' => (string) ( $body['type'] ?? '' ),
		'at'   => current_time( 'mysql' ),
	), false );

	return new WP_REST_Response( array(
		'success' => true,
		'file_id' => $body['file_id'],
		'purpose' => $purpose,
		'name'    => $original_name,
		'size'    => $body['size'] ?? null,
		'type'    => $body['type'] ?? null,
	), 200 );
}

/**
 * Helper: يُرسل multipart/form-data باستخدام wp_remote_post (يبني الـ body يدوياً).
 *
 * @return array|WP_Error  { code, body }
 */
function yamama_tap_proxy_multipart_upload( $url, $bearer_token, array $fields, array $files ) {
	$boundary = 'yaamama-store-' . wp_generate_password( 16, false );
	$eol      = "\r\n";
	$body     = '';

	foreach ( $fields as $name => $value ) {
		if ( '' === (string) $value ) {
			continue;
		}
		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
		$body .= (string) $value . $eol;
	}

	foreach ( $files as $name => $info ) {
		$file_path = $info['path'] ?? '';
		$file_name = $info['name'] ?? basename( (string) $file_path );
		$mime      = $info['mime'] ?? 'application/octet-stream';

		if ( ! is_readable( (string) $file_path ) ) {
			return new WP_Error( 'unreadable_file', 'الملف غير قابل للقراءة على المتجر.', array( 'path' => $file_path ) );
		}
		$contents = file_get_contents( $file_path );
		if ( false === $contents ) {
			return new WP_Error( 'unreadable_file', 'تعذّر قراءة محتوى الملف.', array( 'path' => $file_path ) );
		}

		$body .= '--' . $boundary . $eol;
		$body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file_name . '"' . $eol;
		$body .= 'Content-Type: ' . $mime . $eol . $eol;
		$body .= $contents . $eol;
	}

	$body .= '--' . $boundary . '--' . $eol;

	$response = wp_remote_post( $url, array(
		'timeout' => 60,
		'headers' => array(
			'Authorization' => 'Bearer ' . $bearer_token,
			'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			'Accept'        => 'application/json',
		),
		'body'    => $body,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$raw  = (string) wp_remote_retrieve_body( $response );
	$json = json_decode( $raw, true );

	return array(
		'code' => $code,
		'body' => is_array( $json ) ? $json : array(),
		'raw'  => $raw,
	);
}

/**
 * post.url من Tap على كل charge. يحدّث حالة طلب WC.
 */
function yamama_tap_rest_charge_webhook( $request ) {
	$data = $request->get_json_params();
	if ( ! is_array( $data ) ) {
		return new WP_REST_Response( array( 'error' => 'invalid' ), 400 );
	}
	$status   = $data['status'] ?? '';
	$ref      = $data['reference'] ?? array();
	$order_id = isset( $ref['order'] ) ? absint( $ref['order'] ) : 0;
	$tap_id   = $data['id'] ?? '';

	if ( ! $order_id && isset( $data['metadata']['wc_order_id'] ) ) {
		$order_id = absint( $data['metadata']['wc_order_id'] );
	}

	if ( ! $order_id ) {
		return new WP_REST_Response( array( 'received' => true, 'matched' => false ), 200 );
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return new WP_REST_Response( array( 'received' => true, 'matched' => false ), 200 );
	}

	if ( in_array( $status, array( 'CAPTURED', 'AUTHORIZED' ), true ) ) {
		if ( ! $order->is_paid() ) {
			$order->payment_complete( $tap_id );
			$order->add_order_note( 'Tap webhook: charge ' . $status . ' (' . $tap_id . ')' );
		}
	} elseif ( in_array( $status, array( 'FAILED', 'DECLINED', 'CANCELLED' ), true ) ) {
		$order->update_status( 'failed', 'Tap webhook: ' . $status );
	}

	return new WP_REST_Response( array( 'received' => true, 'status' => $status ), 200 );
}

/**
 * يُرجع حالة الربط الحالية لهذا المتجر.
 */
function yamama_tap_rest_status() {
	$s = yamama_tap_get_settings();
	return new WP_REST_Response( array(
		'merchant_id' => $s['merchant_id'],
		'has_keys'    => ! empty( $s['secret_key'] ) && ! empty( $s['merchant_id'] ),
		'livemode'    => (int) $s['livemode'],
		'test_mode'   => (int) $s['test_mode'],
		'connect_url' => get_option( 'yamama_tap_connect_url', '' ),
		'lead_id'     => get_option( 'yamama_tap_lead_id', '' ),
		'status'      => get_option( 'yamama_tap_status', '' ),
	), 200 );
}
