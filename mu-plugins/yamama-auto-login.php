<?php
/**
 * Plugin Name: Yamama Auto-Login
 * Description: Handles one-time magic login links from the Yamama Control Plane.
 * Version: 2.0.0
 * 
 * Supports login_as: "admin" (store owner) or "developer" (yamama-dev)
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'yamama_handle_autologin', 1 );

function yamama_handle_autologin() {
    if ( empty( $_GET['yamama_autologin'] ) ) {
        return;
    }

    $token = sanitize_text_field( wp_unslash( $_GET['yamama_autologin'] ) );
    $stored = get_option( 'yamama_autologin_token', '' );

    if ( ! $stored ) {
        wp_die( 'رابط تسجيل الدخول غير صالح.', 'خطأ', array( 'response' => 403 ) );
    }

    $data = json_decode( $stored, true );
    if ( ! $data || empty( $data['token'] ) || empty( $data['expires'] ) ) {
        delete_option( 'yamama_autologin_token' );
        wp_die( 'رابط تسجيل الدخول غير صالح.', 'خطأ', array( 'response' => 403 ) );
    }

    if ( $data['token'] !== $token ) {
        wp_die( 'رابط تسجيل الدخول غير صالح.', 'خطأ', array( 'response' => 403 ) );
    }

    if ( time() > (int) $data['expires'] ) {
        delete_option( 'yamama_autologin_token' );
        wp_die( 'انتهت صلاحية رابط تسجيل الدخول. عد إلى لوحة التحكم وأعد المحاولة.', 'انتهت الصلاحية', array( 'response' => 403 ) );
    }

        // Token is valid: consume it (one-time use)
    delete_option( 'yamama_autologin_token' );

    // Determine which user to login as
    $login_as = isset( $data['login_as'] ) ? $data['login_as'] : 'admin';
    
    if ( 'developer' === $login_as ) {
        // Login as developer user
        $user = get_user_by( 'login', 'yamama-dev' );
        if ( ! $user ) {
            // Fallback to admin if developer doesn't exist
            $user = get_user_by( 'id', 1 );
        }
    } else {
        // Login as store owner (admin)
        $user = get_user_by( 'id', 1 );
    }

    if ( ! $user ) {
        wp_die( 'لم يتم العثور على الحساب المطلوب.', 'خطأ', array( 'response' => 500 ) );
    }

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );

    wp_safe_redirect( admin_url() );
    exit;
}
