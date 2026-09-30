<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Yamama_Shipping_Plugin
{
    public static function boot()
    {
        self::load_dependencies();

        Yamama_Shipping_Admin::init();
        Yamama_Shipping_Hooks::init();
        Yamama_Shipping_REST_API::init();

        add_action('init', [self::class, 'maybe_bootstrap_registration'], 20);
    }

    private static function load_dependencies()
    {
        require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-client.php';
        require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-admin.php';
        require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-hooks.php';
        require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-rest-api.php';
    }

    public static function maybe_bootstrap_registration()
    {
        $is_cli = defined('WP_CLI') && WP_CLI;
        if (is_admin() && !current_user_can('manage_woocommerce') && !$is_cli) {
            return;
        }

        if (get_option('yamama_shipping_enabled', 'no') !== 'yes') {
            return;
        }

        Yamama_Shipping_Client::ensure_store_uuid();
        Yamama_Shipping_Client::ensure_registered();
    }
}
