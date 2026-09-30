<?php

if (!defined('ABSPATH')) {
    exit;
}

class Yamama_Shipping_Client
{
    const SETTINGS_OPTION = 'yamama_shipping_settings';
    const STORE_UUID_OPTION = 'yamama_shipping_store_uuid';
    const API_TOKEN_OPTION = 'yamama_shipping_api_token';
    const HMAC_SECRET_OPTION = 'yamama_shipping_hmac_secret';
    const LAST_REGISTRATION_ERROR_OPTION = 'yamama_shipping_last_registration_error';
    const LAST_REGISTRATION_AT_OPTION = 'yamama_shipping_last_registration_at';
    const REGISTERED_URL_OPTION = 'yamama_shipping_registered_url';
    const REGISTRATION_SECRET_OPTION = 'yamama_shipping_registration_secret';

    public static function get_registration_secret()
    {
        $secret = apply_filters('yamama_shipping_registration_secret', '');
        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        if (defined('YAMAMA_SHIPPING_REGISTRATION_SECRET') && YAMAMA_SHIPPING_REGISTRATION_SECRET !== '') {
            return (string) YAMAMA_SHIPPING_REGISTRATION_SECRET;
        }

        $secret = (string) get_option(self::REGISTRATION_SECRET_OPTION, '');
        if ($secret !== '') {
            return $secret;
        }

        $store_secrets = get_option('yamama_store_secrets', array());
        if (is_string($store_secrets)) {
            $decoded = json_decode($store_secrets, true);
            $store_secrets = is_array($decoded) ? $decoded : array();
        }
        if (is_array($store_secrets)) {
            foreach (array('shipping_registration_secret', 'registration_secret', 'plugin_registration_secret') as $key) {
                if (!empty($store_secrets[$key]) && is_string($store_secrets[$key])) {
                    return $store_secrets[$key];
                }
            }
        }

        return '';
    }

    public static function has_registration_secret()
    {
        return self::get_registration_secret() !== '';
    }

    public static function get_settings()
    {
        $defaults = [
            'enabled' => 'yes',
            'menu_label' => 'الشحن',
            'middleware_base_url' => 'https://yaamama-shipping.com/api/v1/plugin',
            'store_uuid' => '',
            'api_token' => '',
            'hmac_secret' => '',
            'timeout_seconds' => 20,
        ];

        $settings = get_option(self::SETTINGS_OPTION, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $merged = wp_parse_args($settings, $defaults);

        // Managed settings: keep sensitive and infrastructure values internal.
        $merged['enabled'] = 'yes';
        $merged['menu_label'] = 'الشحن';
        $merged['middleware_base_url'] = 'https://yaamama-shipping.com/api/v1/plugin';
        $merged['timeout_seconds'] = max(5, intval($merged['timeout_seconds']));
        $merged['store_uuid'] = self::ensure_store_uuid();
        $merged['api_token'] = (string) get_option(self::API_TOKEN_OPTION, '');
        $merged['hmac_secret'] = (string) get_option(self::HMAC_SECRET_OPTION, '');

        return $merged;
    }

    public static function is_configured()
    {
        $s = self::get_settings();
        return !empty($s['middleware_base_url']) && !empty($s['store_uuid']);
    }

    public static function ensure_store_uuid()
    {
        $uuid = (string) get_option(self::STORE_UUID_OPTION, '');
        if ($uuid !== '') {
            return $uuid;
        }

        $uuid = wp_generate_uuid4();
        update_option(self::STORE_UUID_OPTION, $uuid, false);
        return $uuid;
    }

    public static function ensure_registered($force = false)
    {
        static $in_progress = false;
        if ($in_progress) {
            return self::credentials_exist();
        }

        if (!$force && self::credentials_exist()) {
            return true;
        }

        if (!self::has_registration_secret()) {
            update_option(
                self::LAST_REGISTRATION_ERROR_OPTION,
                'لم يتم ربط المتجر بمنصة الشحن بعد. يُدار الاتصال تلقائياً من المنصة — جرّب «إعادة التسجيل» أو تواصل مع الدعم.',
                false
            );
            $in_progress = false;
            return false;
        }

        $in_progress = true;

        $settings = self::get_settings();
        $base = untrailingslashit((string) $settings['middleware_base_url']);
        $path = apply_filters('yamama_shipping_registration_path', '/register');
        $path = '/' . ltrim((string) $path, '/');

        $base_payload = [
            'storeUuid' => (string) $settings['store_uuid'],
            'siteUrl' => home_url('/'),
            'adminUrl' => admin_url('/'),
            'storeName' => get_bloginfo('name'),
            'adminEmail' => get_option('admin_email'),
            'currency' => get_option('woocommerce_currency', 'SAR'),
            'locale' => get_locale(),
            'countryCode' => get_option('woocommerce_default_country', 'SA'),
            'cityName' => get_option('woocommerce_store_city', ''),
            'plugin' => 'yamama-shipping',
            'pluginVersion' => defined('YAMAMA_SHIPPING_VERSION') ? YAMAMA_SHIPPING_VERSION : '',
            'woocommerceVersion' => defined('WC_VERSION') ? WC_VERSION : '',
            'wordpressVersion' => get_bloginfo('version'),
            'forceRotate' => (bool) $force,
        ];

        // Try multiple payload shapes to be compatible with middleware validators.
        $payload_variants = [
            $base_payload,
            [
                'store_uuid' => $base_payload['storeUuid'],
                'site_url' => $base_payload['siteUrl'],
                'admin_url' => $base_payload['adminUrl'],
                'store_name' => $base_payload['storeName'],
                'admin_email' => $base_payload['adminEmail'],
                'currency' => $base_payload['currency'],
                'locale' => $base_payload['locale'],
                'country_code' => $base_payload['countryCode'],
                'city_name' => $base_payload['cityName'],
                'plugin' => $base_payload['plugin'],
                'plugin_version' => $base_payload['pluginVersion'],
                'woocommerce_version' => $base_payload['woocommerceVersion'],
                'wordpress_version' => $base_payload['wordpressVersion'],
                'force_rotate' => $base_payload['forceRotate'],
            ],
            [
                'storeUuid' => $base_payload['storeUuid'],
                'siteUrl' => $base_payload['siteUrl'],
                'forceRotate' => $base_payload['forceRotate'],
            ],
            [
                'store_uuid' => $base_payload['storeUuid'],
                'site_url' => $base_payload['siteUrl'],
                'force_rotate' => $base_payload['forceRotate'],
            ],
        ];

        $last_error = 'Registration failed.';
        foreach ($payload_variants as $payload) {
            $result = self::attempt_registration_request($base, $path, $settings, $payload);
            if (!is_wp_error($result) && is_array($result)) {
                $api_token = '';
                $hmac_secret = '';

                if (isset($result['api_token'])) {
                    $api_token = (string) $result['api_token'];
                } elseif (isset($result['apiToken'])) {
                    $api_token = (string) $result['apiToken'];
                } elseif (isset($result['token'])) {
                    $api_token = (string) $result['token'];
                } elseif (isset($result['data']['api_token'])) {
                    $api_token = (string) $result['data']['api_token'];
                } elseif (isset($result['data']['apiToken'])) {
                    $api_token = (string) $result['data']['apiToken'];
                } elseif (isset($result['data']['token'])) {
                    $api_token = (string) $result['data']['token'];
                }

                if (isset($result['hmac_secret'])) {
                    $hmac_secret = (string) $result['hmac_secret'];
                } elseif (isset($result['hmacSecret'])) {
                    $hmac_secret = (string) $result['hmacSecret'];
                } elseif (isset($result['secret'])) {
                    $hmac_secret = (string) $result['secret'];
                } elseif (isset($result['data']['hmac_secret'])) {
                    $hmac_secret = (string) $result['data']['hmac_secret'];
                } elseif (isset($result['data']['hmacSecret'])) {
                    $hmac_secret = (string) $result['data']['hmacSecret'];
                } elseif (isset($result['data']['secret'])) {
                    $hmac_secret = (string) $result['data']['secret'];
                }

                if ($api_token !== '' && $hmac_secret !== '') {
                    update_option(self::API_TOKEN_OPTION, $api_token, false);
                    update_option(self::HMAC_SECRET_OPTION, $hmac_secret, false);
                    update_option(self::LAST_REGISTRATION_AT_OPTION, current_time('mysql'), false);
                    update_option(self::REGISTERED_URL_OPTION, $base, false);
                    delete_option(self::LAST_REGISTRATION_ERROR_OPTION);

                    $in_progress = false;
                    return true;
                }

                $last_error = 'Registration response missing api_token/hmac_secret.';
                continue;
            }

            $last_error = $result instanceof WP_Error ? $result->get_error_message() : 'Registration failed.';
            // Keep trying only when payload shape seems invalid.
            if (stripos($last_error, 'invalid payload') === false) {
                break;
            }
        }

        update_option(self::LAST_REGISTRATION_ERROR_OPTION, $last_error, false);
        $in_progress = false;
        return false;
    }

    public static function get_registration_debug()
    {
        $registered = self::credentials_exist();
        if ($registered) {
            delete_option(self::LAST_REGISTRATION_ERROR_OPTION);
        }

        return [
            'store_uuid' => self::ensure_store_uuid(),
            'registered' => $registered,
            'has_registration_secret' => self::has_registration_secret(),
            'last_registration_at' => (string) get_option(self::LAST_REGISTRATION_AT_OPTION, ''),
            'last_registration_error' => $registered ? '' : (string) get_option(self::LAST_REGISTRATION_ERROR_OPTION, ''),
        ];
    }

    public static function has_auth_credentials()
    {
        return self::credentials_exist();
    }

    /**
     * Fetch platform-configured carrier prices (read-only catalog for merchants).
     *
     * @return array|WP_Error List of carriers with rules, or error.
     */
    public static function get_platform_pricing()
    {
        if (!self::credentials_exist()) {
            return new WP_Error(
                'yamama_not_connected',
                'المتجر غير متصل بمنصة الشحن. راجع إعدادات الشحن.'
            );
        }

        $cache_key = 'yamama_platform_pricing_v2';
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = self::request('GET', '/platform-pricing');
        if ($result instanceof WP_Error) {
            return $result;
        }

        $carriers = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : array();
        set_transient($cache_key, $carriers, 5 * MINUTE_IN_SECONDS);

        return $carriers;
    }

    private static function attempt_registration_request($base, $path, $settings, $payload)
    {
        $url = $base . '/' . ltrim((string) $path, '/');
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Yamama-Store-UUID' => (string) $settings['store_uuid'],
            'X-Yamama-Site-URL' => home_url('/'),
        ];

        $reg_secret = self::get_registration_secret();
        if ($reg_secret !== '') {
            $headers['X-Yamama-Registration-Secret'] = $reg_secret;
            if (!isset($payload['registrationSecret'])) {
                $payload['registrationSecret'] = $reg_secret;
            }
            if (!isset($payload['registration_secret'])) {
                $payload['registration_secret'] = $reg_secret;
            }
        }

        $response = wp_remote_post($url, [
            'timeout' => max(5, intval($settings['timeout_seconds'])),
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $json = [];
        }

        if ($status < 200 || $status >= 300) {
            $message = isset($json['message']) ? (string) $json['message'] : 'Registration failed.';
            return new WP_Error('yamama_register_http_error', $message, ['status' => $status, 'response' => $json]);
        }

        return $json;
    }

    public static function credentials_exist()
    {
        $token = (string) get_option(self::API_TOKEN_OPTION, '');
        $secret = (string) get_option(self::HMAC_SECRET_OPTION, '');
        if ($token === '' || $secret === '') {
            return false;
        }

        $registered_url = (string) get_option(self::REGISTERED_URL_OPTION, '');
        if ($registered_url !== '') {
            $settings = self::get_settings();
            $current_base = untrailingslashit((string) $settings['middleware_base_url']);
            if ($registered_url !== $current_base) {
                delete_option(self::API_TOKEN_OPTION);
                delete_option(self::HMAC_SECRET_OPTION);
                delete_option(self::REGISTERED_URL_OPTION);
                return false;
            }
        }

        return true;
    }

    /**
     * Get the payment configuration from middleware.
     * Returns ['payment_gateway' => 'tap', 'configured' => bool, 'test_mode' => bool,
     *          'supported_methods' => [...], 'tap_merchant_id' => str|null,
     *          'tap_technology_id' => str|null, 'tap_platform_id' => str|null]
     *
     * The merchant/technology/platform IDs identify the central yamama Tap account
     * that handles ALL shipping fees (NOT a per-store sub-merchant). They are
     * cached as WP options so create_tap_charge() can reuse them without an
     * extra HTTP roundtrip on every checkout.
     */
    public static function get_payment_config()
    {
        $result = self::request('GET', '/payment-config');
        if (is_wp_error($result)) {
            return [
                'payment_gateway'   => 'tap',
                'configured'        => false,
                'test_mode'         => false,
                'supported_methods' => ['creditcard', 'stcpay', 'mada', 'apple_pay'],
                'tap_merchant_id'   => get_option('yamama_shipping_tap_merchant_id', ''),
                'tap_technology_id' => get_option('yamama_shipping_tap_technology_id', ''),
                'tap_platform_id'   => get_option('yamama_shipping_tap_platform_id', ''),
            ];
        }

        $methods = self::extract_array_field($result, ['supported_methods', 'supportedMethods', 'methods']);
        if (empty($methods)) {
            $methods = ['creditcard', 'stcpay', 'mada', 'apple_pay'];
        }

        $merchant_id   = isset($result['tap_merchant_id'])   ? (string) $result['tap_merchant_id']   : '';
        $technology_id = isset($result['tap_technology_id']) ? (string) $result['tap_technology_id'] : '';
        $platform_id   = isset($result['tap_platform_id'])   ? (string) $result['tap_platform_id']   : '';

        if ($merchant_id !== '')   update_option('yamama_shipping_tap_merchant_id',   $merchant_id,   false);
        if ($technology_id !== '') update_option('yamama_shipping_tap_technology_id', $technology_id, false);
        if ($platform_id !== '')   update_option('yamama_shipping_tap_platform_id',   $platform_id,   false);

        return [
            'payment_gateway'   => 'tap',
            'configured'        => !empty($result['tap_configured']) || !empty($result['paylink_configured']),
            'test_mode'         => !empty($result['tap_test_mode']) || !empty($result['paylink_test_mode']),
            'supported_methods' => $methods,
            'tap_merchant_id'   => $merchant_id ?: get_option('yamama_shipping_tap_merchant_id', ''),
            'tap_technology_id' => $technology_id ?: get_option('yamama_shipping_tap_technology_id', ''),
            'tap_platform_id'   => $platform_id ?: get_option('yamama_shipping_tap_platform_id', ''),
        ];
    }

    /**
     * Extract an array field from a response trying multiple key names,
     * including nested under 'data'.
     */
    private static function extract_array_field($result, $keys)
    {
        if (!is_array($result)) {
            return [];
        }

        foreach ($keys as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                return $result[$key];
            }
            if (isset($result['data'][$key]) && is_array($result['data'][$key])) {
                return $result['data'][$key];
            }
        }

        return [];
    }

    /**
     * Extract a field from a response array trying multiple key names,
     * including nested under 'data'.
     */
    private static function extract_field($result, $keys)
    {
        if (!is_array($result)) {
            return '';
        }

        foreach ($keys as $key) {
            if (isset($result[$key]) && (string) $result[$key] !== '') {
                return (string) $result[$key];
            }
            if (isset($result['data'][$key]) && (string) $result['data'][$key] !== '') {
                return (string) $result['data'][$key];
            }
        }

        return '';
    }

    private static $reregistration_attempted = false;

    public static function request($method, $path, $payload = [])
    {
        $normalized_path = '/' . ltrim((string) $path, '/');
        $registration_path = '/' . ltrim((string) apply_filters('yamama_shipping_registration_path', '/register'), '/');

        self::ensure_registered();

        $settings = self::get_settings();
        $base = untrailingslashit((string) $settings['middleware_base_url']);
        $url = $base . '/' . ltrim($normalized_path, '/');
        $is_get = strtoupper((string) $method) === 'GET';

        if ($is_get) {
            $body = '';
        } else {
            $body = wp_json_encode($payload);
            if ($body === false) {
                return new WP_Error('yamama_json_failed', 'Failed to encode request payload.');
            }
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-Yamama-Store-UUID' => (string) $settings['store_uuid'],
            'X-Yamama-Site-URL' => home_url('/'),
        ];

        $has_auth = false;
        if (!empty($settings['api_token']) && !empty($settings['hmac_secret'])) {
            $has_auth = true;
        } elseif ($normalized_path !== $registration_path) {
            // Protected endpoints require auth headers. Retry forced registration once.
            self::ensure_registered(true);
            $settings = self::get_settings();
            if (!empty($settings['api_token']) && !empty($settings['hmac_secret'])) {
                $has_auth = true;
            } else {
                $debug = self::get_registration_debug();
                $reason = (string) $debug['last_registration_error'];
                if ($reason === '') {
                    $reason = 'Missing store auth headers.';
                }
                return new WP_Error('yamama_not_registered', $reason);
            }
        }

        $attempts = [];
        if ($has_auth) {
            if ($is_get) {
                // GET routes verify Bearer token only — one attempt avoids long hangs on 401.
                $attempts[] = [
                    'Authorization' => 'Bearer ' . (string) $settings['api_token'],
                ];
            } else {
                $attempts = self::build_auth_header_attempts(
                    strtoupper((string) $method),
                    $normalized_path,
                    $body,
                    (string) $settings['api_token'],
                    (string) $settings['hmac_secret']
                );
                if (empty($attempts)) {
                    $attempts[] = [
                        'Authorization' => 'Bearer ' . (string) $settings['api_token'],
                        'X-Yamama-Signature' => hash_hmac('sha256', $body, (string) $settings['hmac_secret'], false),
                    ];
                }
            }
        } else {
            $attempts[] = [];
        }

        $last_status = 0;
        $last_data = [];
        foreach ($attempts as $attempt) {
            $attempt_headers = $headers;
            if (!empty($attempt)) {
                foreach ($attempt as $k => $v) {
                    $attempt_headers[$k] = (string) $v;
                }
            }

            $args = [
                'method' => strtoupper($method),
                'timeout' => max(5, intval($settings['timeout_seconds'])),
                'headers' => $attempt_headers,
            ];

            if (!$is_get) {
                $args['body'] = $body;
            }

            $response = wp_remote_request($url, $args);
            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $raw = wp_remote_retrieve_body($response);
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $data = ['raw' => $raw];
            }

            if ($status >= 200 && $status < 300) {
                return $data;
            }

            $last_status = $status;
            $last_data = $data;
            $message = isset($data['message']) ? (string) $data['message'] : '';
            // Retry only for signature/auth related failures.
            $is_auth_like = stripos($message, 'signature') !== false || stripos($message, 'auth') !== false || in_array($status, [401, 403], true);
            if (!$is_auth_like || ($is_get && in_array($status, [401, 403], true))) {
                break;
            }
        }

        // Auto re-register on auth failures (wrong credentials, DB reset, server change)
        if (in_array($last_status, [401, 403], true) && !self::$reregistration_attempted && $normalized_path !== $registration_path) {
            self::$reregistration_attempted = true;
            delete_option(self::API_TOKEN_OPTION);
            delete_option(self::HMAC_SECRET_OPTION);
            delete_option(self::REGISTERED_URL_OPTION);
            if (self::ensure_registered(true)) {
                return self::request($method, $path, $payload);
            }
        }

        if ($last_status < 200 || $last_status >= 300) {
            $message = isset($last_data['message']) ? (string) $last_data['message'] : 'Middleware request failed.';
            return new WP_Error('yamama_http_error', $message, ['status' => $last_status, 'response' => $last_data]);
        }
        return $last_data;
    }

    private static function build_auth_header_attempts($method, $normalized_path, $body, $token, $secret)
    {
        $timestamp = (string) time();
        $candidates = [
            $body,
            $method . "\n" . $normalized_path . "\n" . $body,
            $method . "|" . $normalized_path . "|" . $body,
            $timestamp . "\n" . $body,
            $timestamp . "." . $body,
            $method . "\n" . $normalized_path . "\n" . $timestamp . "\n" . $body,
            $method . "|" . $normalized_path . "|" . $timestamp . "|" . $body,
        ];

        $signature_attempts = [];
        foreach ($candidates as $payload_to_sign) {
            foreach (self::build_signature_attempts($payload_to_sign, $secret) as $sig) {
                $signature_attempts[] = ['sig' => $sig, 'timestamp' => $timestamp];
            }
        }

        $unique = [];
        $seen = [];
        foreach ($signature_attempts as $sig_attempt) {
            $sig = (string) $sig_attempt['sig'];
            if ($sig === '') {
                continue;
            }
            $header_variants = [
                [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Yamama-Token' => $token,
                    'X-Yamama-Signature' => $sig,
                ],
                [
                    'Authorization' => $token,
                    'X-Yamama-Signature' => $sig,
                ],
                [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Signature' => $sig,
                ],
            ];

            foreach ($header_variants as $headers) {
                $key = md5(wp_json_encode($headers));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $unique[] = $headers;
            }

            $timed_header_variants = [
                [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Yamama-Token' => $token,
                    'X-Yamama-Signature' => $sig,
                    'X-Yamama-Timestamp' => $sig_attempt['timestamp'],
                ],
                [
                    'Authorization' => 'Bearer ' . $token,
                    'X-Yamama-Signature' => $sig,
                    'X-Timestamp' => $sig_attempt['timestamp'],
                ],
            ];
            foreach ($timed_header_variants as $headers) {
                $key = md5(wp_json_encode($headers));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $unique[] = $headers;
            }
        }
        return $unique;
    }

    private static function build_signature_attempts($payload_to_sign, $secret)
    {
        $attempts = [];
        $attempts[] = hash_hmac('sha256', (string) $payload_to_sign, (string) $secret, false); // hex, raw secret
        $attempts[] = base64_encode(hash_hmac('sha256', (string) $payload_to_sign, (string) $secret, true)); // b64, raw secret

        $decoded = base64_decode((string) $secret, true);
        if (is_string($decoded) && $decoded !== '') {
            $attempts[] = hash_hmac('sha256', (string) $payload_to_sign, $decoded, false); // hex, decoded secret
            $attempts[] = base64_encode(hash_hmac('sha256', (string) $payload_to_sign, $decoded, true)); // b64, decoded secret
        }

        // Unique only.
        $attempts = array_values(array_unique(array_filter(array_map('strval', $attempts))));
        return $attempts;
    }
}
