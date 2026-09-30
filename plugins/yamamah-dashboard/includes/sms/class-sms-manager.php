<?php
/**
 * SMS Manager for customer stores.
 *
 * Handles OTP generation/verification, monthly usage tracking,
 * and gateway selection (platform vs. custom).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/TaqnyatSms.php';

class Yamamah_SMS_Manager {

    /**
     * Check if SMS is enabled for this store.
     */
    public static function is_enabled() {
        return get_option( 'yamama_sms_enabled', 'no' ) === 'yes';
    }

    /**
     * Get the SMS configuration for this store.
     */
    public static function get_config() {
        $provider = get_option( 'yamama_sms_provider', 'platform' );

        if ( $provider === 'custom' ) {
            return [
                'provider'    => 'custom',
                'token'       => get_option( 'yamama_sms_custom_token', '' ),
                'sender'      => get_option( 'yamama_sms_custom_sender', '' ),
                'limit'       => -1, // unlimited — uses store owner's own Taqnyat balance
            ];
        }

        return [
            'provider'    => 'platform',
            'token'       => get_option( 'yamama_sms_platform_token', '' ),
            'sender'      => get_option( 'yamama_sms_platform_sender', '' ),
            'limit'       => intval( get_option( 'yamama_sms_monthly_limit', 0 ) ),
        ];
    }

    /**
     * Get monthly usage count.
     */
    public static function get_monthly_usage() {
        $key = 'yamama_sms_usage_' . date( 'Y_m' );
        return intval( get_option( $key, 0 ) );
    }

    /**
     * Increment monthly usage.
     */
    public static function increment_usage() {
        $key = 'yamama_sms_usage_' . date( 'Y_m' );
        $current = intval( get_option( $key, 0 ) );
        update_option( $key, $current + 1 );
    }

    /**
     * Check if limit is reached.
     */
    public static function is_limit_reached() {
        $cfg = self::get_config();
        $limit = $cfg['limit'];
        if ( $limit <= 0 ) {
            return $limit === 0;
        }
        if ( $limit === -1 ) {
            return false;
        }
        return self::get_monthly_usage() >= $limit;
    }

    /**
     * Send an SMS message.
     */
    public static function send_sms( $body, $phone ) {
        if ( ! self::is_enabled() ) {
            return [ 'success' => false, 'error' => 'SMS disabled' ];
        }

        if ( self::is_limit_reached() ) {
            return [ 'success' => false, 'error' => 'تم تجاوز الحد الشهري للرسائل.' ];
        }

        $cfg = self::get_config();
        if ( empty( $cfg['token'] ) || empty( $cfg['sender'] ) ) {
            return [ 'success' => false, 'error' => 'SMS not configured' ];
        }

        $taqnyat = new Yamamah_TaqnyatSms( $cfg['token'] );
        $result  = $taqnyat->send( $body, [ $phone ], $cfg['sender'] );

        if ( ! empty( $result['success'] ) ) {
            self::increment_usage();
        }

        return $result;
    }

    /**
     * Generate and send OTP.
     */
    public static function send_otp( $phone ) {
        $phone = self::sanitize_phone( $phone );
        if ( ! $phone ) {
            return new WP_Error( 'invalid_phone', 'رقم الجوال غير صالح.' );
        }

        $rate_key = 'yamama_otp_rate_' . md5( $phone );
        $attempts = intval( get_transient( $rate_key ) );
        if ( $attempts >= 5 ) {
            return new WP_Error( 'rate_limit', 'تم تجاوز الحد المسموح. انتظر بضع دقائق.' );
        }

        $code = '';
        for ( $i = 0; $i < 4; $i++ ) {
            $code .= wp_rand( 0, 9 );
        }

        // Bug #96: مدة الصلاحية تُقرأ من الإعداد (الافتراضي 300 ثانية = 5 دقائق).
        $expiry_seconds = max( 60, min( 1800, intval( get_option( 'yamama_otp_expiry_seconds', 300 ) ) ) );

        $transient_key = 'yamama_otp_' . md5( $phone );
        set_transient( $transient_key, [
            'code'       => wp_hash( $code ),
            'phone'      => $phone,
            'created_at' => time(),
            'attempts'   => 0,
        ], $expiry_seconds );

        set_transient( $rate_key, $attempts + 1, $expiry_seconds );

        $site_name = get_bloginfo( 'name' );
        $message   = "رمز التحقق الخاص بك في {$site_name}: {$code}";

        $result = self::send_sms( $message, $phone );
        if ( empty( $result['success'] ) ) {
            delete_transient( $transient_key );
            return new WP_Error( 'send_failed', $result['error'] ?? 'فشل إرسال الرسالة.' );
        }

        return true;
    }

    /**
     * Verify an OTP code.
     */
    public static function verify_otp( $phone, $code ) {
        $phone = self::sanitize_phone( $phone );
        if ( ! $phone || empty( $code ) ) {
            return new WP_Error( 'invalid_input', 'بيانات غير مكتملة.' );
        }

        $transient_key = 'yamama_otp_' . md5( $phone );
        $stored = get_transient( $transient_key );

        if ( ! $stored ) {
            return new WP_Error( 'expired', 'رمز التحقق منتهي الصلاحية.' );
        }

        if ( intval( $stored['attempts'] ) >= 5 ) {
            delete_transient( $transient_key );
            return new WP_Error( 'too_many_attempts', 'تم تجاوز عدد المحاولات.' );
        }

        $stored['attempts'] = intval( $stored['attempts'] ) + 1;
        $ttl = max( 0, $stored['created_at'] + 300 - time() );
        if ( $ttl > 0 ) {
            set_transient( $transient_key, $stored, $ttl );
        }

        if ( ! hash_equals( $stored['code'], wp_hash( $code ) ) ) {
            return new WP_Error( 'wrong_code', 'رمز التحقق غير صحيح.' );
        }

        delete_transient( $transient_key );
        return true;
    }

    /**
     * Sanitize Saudi phone number to international format.
     */
    public static function sanitize_phone( $phone ) {
        $phone = preg_replace( '/[^0-9+]/', '', trim( $phone ) );

        if ( preg_match( '/^5\d{8}$/', $phone ) ) {
            $phone = '966' . $phone;
        } elseif ( preg_match( '/^05\d{8}$/', $phone ) ) {
            $phone = '966' . substr( $phone, 1 );
        } elseif ( preg_match( '/^\+9665\d{8}$/', $phone ) ) {
            $phone = substr( $phone, 1 );
        }

        if ( ! preg_match( '/^9665\d{8}$/', $phone ) ) {
            return '';
        }
        return $phone;
    }
}
