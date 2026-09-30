<?php
/**
 * Plugin Name: Yamama Shipping
 * Plugin URI: https://yamama.local
 * Description: WooCommerce shipping integration with Lamha via Yamama middleware. Includes order creation, carrier selection, Tap payment, and webhook status tracking.
 * Version: 1.4.0
 * Author: Yamama
 * Text Domain: yamama-shipping
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YAMAMA_SHIPPING_VERSION', '1.4.4');
define('YAMAMA_SHIPPING_FILE', __FILE__);
define('YAMAMA_SHIPPING_DIR', plugin_dir_path(__FILE__));
define('YAMAMA_SHIPPING_URL', plugin_dir_url(__FILE__));

require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-plugin.php';

register_activation_hook(YAMAMA_SHIPPING_FILE, static function () {
    require_once YAMAMA_SHIPPING_DIR . 'includes/class-yamama-shipping-client.php';

    if (get_option('yamama_shipping_enabled', 'no') !== 'yes') {
        return;
    }
    if (!Yamama_Shipping_Client::has_registration_secret()) {
        return;
    }

    Yamama_Shipping_Client::ensure_store_uuid();
    Yamama_Shipping_Client::ensure_registered(true);
});

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    Yamama_Shipping_Plugin::boot();
});
