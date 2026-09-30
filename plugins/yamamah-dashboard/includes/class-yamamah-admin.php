<?php
/**
 * Yamamah Admin - تسجيل القوائم وتحميل الأصول
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Yamamah_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ), 999 );
        add_action( 'admin_menu', array( $this, 'hide_default_menus' ), 9999 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_head', array( $this, 'dark_mode_early_apply' ), 1 );
        add_action( 'admin_body_class', array( $this, 'add_body_class' ) );
        add_action( 'admin_init', array( $this, 'redirect_default_dashboard' ) );
        add_filter( 'show_admin_bar', array( $this, 'manage_admin_bar' ) );
        add_action( 'in_admin_header', array( $this, 'render_wc_layout_wrapper' ) );
        add_action( 'wp_footer', array( $this, 'render_frontend_dashboard_button' ) );
        add_action( 'wp_head', array( $this, 'frontend_dashboard_button_css' ) );
        add_filter( 'login_redirect', array( $this, 'custom_login_redirect' ), 10, 3 );
        add_filter( 'logout_redirect', array( $this, 'custom_logout_redirect' ), 10, 3 );
        add_action( 'login_init', array( $this, 'redirect_login_page' ) );
        add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
        add_filter( 'wp_mail_from', array( $this, 'smtp_mail_from' ), 99 );
        add_filter( 'wp_mail_from_name', array( $this, 'smtp_mail_from_name' ), 99 );
        add_filter( 'woocommerce_email_styles', array( $this, 'custom_email_styles' ), 9999, 2 );
        add_action( 'woocommerce_email_footer', array( $this, 'custom_email_footer_style' ), 5 );
        add_action( 'admin_footer', array( $this, 'render_support_toggle_button' ) );
        add_action( 'wp_ajax_yamama_toggle_wp_mode', array( $this, 'ajax_toggle_wp_mode' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'disable_wp_auth_check' ), 999 );
        add_action( 'admin_footer', array( $this, 'render_session_modal' ) );
        add_action( 'wp_ajax_yamamah_reauth', array( $this, 'ajax_reauth' ) );
        add_action( 'wp_ajax_nopriv_yamamah_reauth', array( $this, 'ajax_reauth' ) );
    }

    /**
     * إخفاء شريط الأدمن لكل مستخدمي اليمامة (عدا يوزر الدعم الخفي)
     */
    public function manage_admin_bar( $show ) {
        if ( $this->is_hidden_support_user() ) {
            return $show;
        }
        if ( $this->is_yamamah_user() ) {
            return false;
        }
        return $show;
    }

    /**
     * توجيه المستخدم بعد تسجيل الدخول إلى لوحة اليمامة
     */
    public function custom_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! is_object( $user ) || ! ( $user instanceof WP_User ) ) {
            return $redirect_to;
        }
        if ( get_user_meta( $user->ID, '_yamama_hidden_user', true ) ) {
            return admin_url( 'admin.php?page=yamamah-dashboard' );
        }
        if ( $user->has_cap( 'manage_woocommerce' ) ) {
            return admin_url( 'admin.php?page=yamamah-dashboard' );
        }
        $yam_role = get_user_meta( $user->ID, 'yamamah_role', true );
        if ( ! empty( $yam_role ) ) {
            return admin_url( 'admin.php?page=yamamah-dashboard' );
        }
        return $redirect_to;
    }

    /**
     * توجيه المستخدم بعد تسجيل الخروج إلى الصفحة الرئيسية
     */
    public function custom_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
        return home_url();
    }

    /**
     * توجيه wp-login.php إلى صفحة الدخول من الثيم (إن وجدت)
     */
    public function redirect_login_page() {
        if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'confirmaction' ), true ) ) {
            return;
        }

        if ( isset( $_GET['interim-login'] ) ) {
            return;
        }

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        $my_account_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0;
        if ( $my_account_page_id > 0 ) {
            $login_url = get_permalink( $my_account_page_id );
            if ( $login_url ) {
                wp_safe_redirect( $login_url );
                exit;
            }
        }
    }

    /**
     * إعداد SMTP عند إرسال البريد
     */
    public function configure_smtp( $phpmailer ) {
        if ( get_option( 'yamamah_smtp_enabled' ) !== 'yes' ) {
            return;
        }
        $host       = get_option( 'yamamah_smtp_host', '' );
        $port       = get_option( 'yamamah_smtp_port', '587' );
        $encryption = get_option( 'yamamah_smtp_encryption', 'tls' );
        $username   = get_option( 'yamamah_smtp_username', '' );
        $password   = get_option( 'yamamah_smtp_password', '' );

        if ( empty( $host ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = intval( $port );
        $phpmailer->SMTPAuth   = ! empty( $username );
        $phpmailer->Username   = $username;
        $phpmailer->Password   = $password;
        $phpmailer->SMTPSecure = $encryption;
    }

    /**
     * Force a verified sender address for ALL outgoing mail when SMTP is on.
     * Providers like Amazon SES reject mail whose From is not a verified
     * identity; without this, core WordPress mail (password reset, new user,
     * etc.) would be sent from wordpress@<store-domain> and bounce.
     */
    public function smtp_mail_from( $from ) {
        if ( get_option( 'yamamah_smtp_enabled' ) !== 'yes' ) {
            return $from;
        }
        $configured = get_option( 'woocommerce_email_from_address', '' );
        return $configured ? $configured : $from;
    }

    public function smtp_mail_from_name( $name ) {
        if ( get_option( 'yamamah_smtp_enabled' ) !== 'yes' ) {
            return $name;
        }
        $configured = get_option( 'woocommerce_email_from_name', '' );
        return $configured ? $configured : get_bloginfo( 'name' );
    }

    /**
     * تخصيص أنماط CSS لرسائل WooCommerce لتتوافق مع تصميم المعاينة
     */
    public function custom_email_styles( $css, $email = null ) {
        $base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
        $bg_color   = get_option( 'woocommerce_email_background_color', '#f7f7f7' );
        $body_bg    = get_option( 'woocommerce_email_body_background_color', '#ffffff' );
        $text_color = get_option( 'woocommerce_email_text_color', '#3c3c3c' );

        $css .= '
            /* RTL + Yamamah Custom Email Styles */
            body, #wrapper, #template_container, #template_body,
            #body_content, #body_content_inner,
            .td, h1, h2, h3, p, table, td, th {
                direction: rtl !important;
                text-align: right !important;
            }
            body, td, th, p, .td {
                font-family: Tahoma, Arial, sans-serif !important;
            }

            #template_header {
                background-color: ' . esc_attr( $base_color ) . ' !important;
                border-radius: 8px 8px 0 0 !important;
            }
            #template_header h1,
            #template_header .header-link-text {
                color: #ffffff !important;
            }

            #template_container {
                border: none !important;
                border-radius: 8px !important;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }

            #template_footer {
                background-color: ' . esc_attr( $base_color ) . ' !important;
                border-radius: 0 0 8px 8px !important;
                border-top: none !important;
            }
            #template_footer #credit,
            #template_footer #credit a,
            #template_footer td,
            #template_footer p {
                color: rgba(255,255,255,0.85) !important;
            }

            #body_content_inner h2 {
                color: ' . esc_attr( $base_color ) . ' !important;
                font-size: 18px !important;
            }

            /* تنسيق جدول المنتجات */
            .td table.td thead tr th {
                background-color: ' . esc_attr( $base_color ) . ' !important;
                color: #ffffff !important;
                padding: 10px 14px !important;
            }
            .td table.td tbody tr td {
                padding: 10px 14px !important;
            }

            /* تحسين الأزرار */
            a.link {
                color: ' . esc_attr( $base_color ) . ' !important;
            }
        ';

        return $css;
    }

    /**
     * تخصيص فوتر البريد
     */
    public function custom_email_footer_style( $email = null ) {
        $base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
        echo '<style>
            #template_footer { background-color: ' . esc_attr( $base_color ) . ' !important; }
            #template_footer #credit, #template_footer #credit a, #template_footer td, #template_footer p { color: rgba(255,255,255,0.85) !important; }
        </style>';
    }

    /**
     * زر "اذهب إلى لوحة التحكم" في الواجهة الأمامية
     */
    public function render_frontend_dashboard_button() {
        if ( ! is_admin() && $this->is_yamamah_user() ) {
            $url = admin_url( 'admin.php?page=yamamah-dashboard' );
            ?>
            <a href="<?php echo esc_url( $url ); ?>" id="yamamah-frontend-btn" title="لوحة التحكم">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>لوحة التحكم</span>
            </a>
            <?php
        }
    }

    /**
     * تنسيقات زر لوحة التحكم في الواجهة الأمامية
     */
    public function frontend_dashboard_button_css() {
        if ( ! is_admin() && $this->is_yamamah_user() ) {
            ?>
            <style>
                #yamamah-frontend-btn {
                    position: fixed;
                    top: 12px;
                    right: 12px;
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    background: linear-gradient(135deg, #6C3FA0, #5a2d91);
                    color: #fff !important;
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-family: 'Tajawal', sans-serif;
                    font-size: 13px;
                    font-weight: 500;
                    text-decoration: none !important;
                    box-shadow: 0 2px 12px rgba(108,63,160,0.4);
                    transition: all 0.2s;
                    direction: rtl;
                    pointer-events: auto;
                }
                #yamamah-frontend-btn:hover {
                    background: linear-gradient(135deg, #7c4fb0, #6C3FA0);
                    box-shadow: 0 4px 16px rgba(108,63,160,0.5);
                    transform: translateY(-1px);
                }
                #yamamah-frontend-btn svg {
                    flex-shrink: 0;
                }
                @media (max-width: 767.98px) {
                    #yamamah-frontend-btn {
                        top: auto;
                        bottom: 72px;
                        right: 12px;
                        left: auto;
                        z-index: 99999;
                        padding: 8px 12px;
                        font-size: 12px;
                        max-width: calc(100vw - 24px);
                    }
                    #yamamah-frontend-btn span {
                        max-width: 7rem;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                    }
                }
            </style>
            <?php
        }
    }

    /**
     * توجيه كل صفحات wp-admin الأصلية إلى لوحة اليمامة (عدا يوزر الدعم الخفي في وضع WP)
     */
    public function redirect_default_dashboard() {
        global $pagenow;

        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'yamamah' ) !== false ) {
            return;
        }

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( ! $this->is_yamamah_user() ) {
            return;
        }

        if ( $this->is_hidden_support_user() && $this->is_wp_mode_active() ) {
            return;
        }

        $allowed_pagenow = array( 'admin.php', 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'media-upload.php' );
        if ( in_array( $pagenow, $allowed_pagenow, true ) ) {
            return;
        }

        // Allow editing products (wrapped), pages, and coupons via native post.php.
        if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {
            $post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
            $post_type = '';
            if ( $post_id ) {
                $post_type = get_post_type( $post_id );
            } elseif ( isset( $_GET['post_type'] ) ) {
                $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
            } elseif ( $pagenow === 'post-new.php' ) {
                $post_type = 'post';
            }

            $allowed_post_types = array( 'product', 'page', 'shop_coupon' );
            if ( $post_type && in_array( $post_type, $allowed_post_types, true ) ) {
                return;
            }
            if ( function_exists( 'yamamah_get_wc_page_id' ) && yamamah_get_wc_page_id() ) {
                return;
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=yamamah-dashboard' ) );
        exit;
    }

    /**
     * هل المستخدم الحالي تابع لنظام اليمامة؟
     */
    private function is_yamamah_user() {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        $yam_role = get_user_meta( get_current_user_id(), 'yamamah_role', true );
        return ! empty( $yam_role );
    }

    /**
     * هل المستخدم الحالي هو يوزر الدعم الخفي؟
     */
    private function is_hidden_support_user() {
        if ( ! is_user_logged_in() ) return false;
        return (bool) get_user_meta( get_current_user_id(), '_yamama_hidden_user', true );
    }

    /**
     * هل يوزر الدعم في وضع ووردبريس الأصلي؟
     */
    private function is_wp_mode_active() {
        return isset( $_COOKIE['yamama_wp_mode'] ) && $_COOKIE['yamama_wp_mode'] === '1';
    }

    /**
     * إخفاء قوائم ووردبريس الافتراضية (عدا يوزر الدعم الخفي في وضع WP)
     */
    public function hide_default_menus() {
        if ( ! $this->is_yamamah_user() ) {
            return;
        }
        if ( $this->is_hidden_support_user() && $this->is_wp_mode_active() ) {
            return;
        }

        remove_menu_page( 'index.php' );
        remove_menu_page( 'edit.php' );
        remove_menu_page( 'edit-comments.php' );
        remove_menu_page( 'tools.php' );
        remove_menu_page( 'upload.php' );
        remove_menu_page( 'profile.php' );
        remove_menu_page( 'users.php' );
        remove_menu_page( 'themes.php' );
        remove_menu_page( 'plugins.php' );
        remove_menu_page( 'options-general.php' );
        remove_menu_page( 'edit.php?post_type=page' );

        remove_menu_page( 'woocommerce' );
        remove_menu_page( 'edit.php?post_type=product' );
        remove_menu_page( 'edit.php?post_type=shop_order' );
        remove_menu_page( 'edit.php?post_type=shop_coupon' );
        remove_menu_page( 'wc-admin' );
        remove_menu_page( 'wc-admin&path=/analytics/overview' );
        remove_menu_page( 'wc-reports' );
        remove_menu_page( 'wc-settings' );
        remove_menu_page( 'wc-status' );
        remove_menu_page( 'yamama-shipping' );
    }

    /**
     * تسجيل قائمة الإضافة في wp-admin
     */
    public function register_menu() {
        // القائمة الرئيسية
        add_menu_page(
            'اليمامة - لوحة التحكم',
            'اليمامة',
            'manage_woocommerce',
            'yamamah-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-store',
            2
        );

        $store_type = yamamah_get_store_type();
        $labels     = yamamah_get_catalog_labels();

        $products_label    = $labels['section'];
        $add_product_label = $labels['add_item'];
        $orders_label      = $labels['orders_label'];
        if ( $store_type === 'booking' ) {
            $orders_label = 'الحجوزات';
        }

        $subpages = array(
            array( 'yamamah-dashboard', 'الرئيسية',        'yamamah-dashboard',         'render_dashboard' ),
            array( 'yamamah-dashboard', 'مدير العمليات',    'yamamah-operations',        'render_operations' ),
            array( 'yamamah-dashboard', $orders_label,       'yamamah-orders',            'render_orders' ),
            array( 'yamamah-dashboard', 'تفاصيل الطلب',    'yamamah-order-detail',      'render_order_detail' ),
            array( 'yamamah-dashboard', 'نقاط الولاء',     'yamamah-loyalty',           'render_loyalty' ),
            array( 'yamamah-dashboard', 'القسائم',          'yamamah-coupons',           'render_coupons' ),
            array( 'yamamah-dashboard', 'السلات المتروكة', 'yamamah-abandoned-carts',   'render_abandoned_carts' ),
            array( 'yamamah-dashboard', 'التقارير',         'yamamah-reports',           'render_reports' ),
            array( 'yamamah-dashboard', $products_label,     'yamamah-products',          'render_products' ),
            array( 'yamamah-dashboard', $add_product_label,  'yamamah-add-product',       'render_add_product' ),
            array( 'yamamah-dashboard', 'مزودي الخدمات',   'yamamah-providers',         'render_providers' ),
        );

        if ( $store_type === 'retail' ) {
            $subpages[] = array( 'yamamah-dashboard', 'استيراد/تصدير المنتجات', 'yamamah-products-import', 'render_products_import' );
        }

        $subpages = array_merge( $subpages, array(
            array( 'yamamah-dashboard', 'التصنيفات',        'yamamah-categories',        'render_categories' ),
            array( 'yamamah-dashboard', 'الوسوم',           'yamamah-tags',              'render_tags' ),
            array( 'yamamah-dashboard', 'السمات',           'yamamah-attributes',        'render_attributes' ),
            array( 'yamamah-dashboard', 'العملاء',          'yamamah-customers',         'render_customers' ),
            array( 'yamamah-dashboard', 'طرق الدفع',       'yamamah-payment-methods',   'render_payment_methods' ),
            array( 'yamamah-dashboard', 'إعدادات الدفع',   'yamamah-payment-settings',  'render_payment_settings' ),
            array( 'yamamah-dashboard', 'طلبات الشحن',     'yamamah-shipping-orders',   'render_shipping_orders' ),
            array( 'yamamah-dashboard', 'تفاصيل الشحنة',   'yamamah-shipping-detail',   'render_shipping_detail' ),
            array( 'yamamah-dashboard', 'إنشاء شحنة',     'yamamah-create-shipment',   'render_create_shipment' ),
            array( 'yamamah-dashboard', 'أسعار الشحن',     'yamamah-shipping-rates',    'render_shipping_rates' ),
            array( 'yamamah-dashboard', 'إعدادات الشحن',   'yamamah-shipping-settings', 'render_shipping_settings' ),
            array( 'yamamah-dashboard', 'التحليلات',        'yamamah-analytics',         'render_analytics' ),
            array( 'yamamah-dashboard', 'تحليلات الإيرادات', 'yamamah-analytics-revenue', 'render_analytics_revenue' ),
            array( 'yamamah-dashboard', $labels['analytics_menu'], 'yamamah-analytics-products', 'render_analytics_products' ),
            array( 'yamamah-dashboard', 'إعدادات التحليلات','yamamah-analytics-settings','render_analytics_settings' ),
            array( 'yamamah-dashboard', 'المستخدمين',       'yamamah-users',             'render_users' ),
            array( 'yamamah-dashboard', 'المدراء',           'yamamah-admins',            'render_admins' ),
            array( 'yamamah-dashboard', 'حسابي',             'yamamah-my-account',        'render_my_account' ),
            array( 'yamamah-dashboard', 'إعدادات عامة',     'yamamah-settings-general',  'render_settings_general' ),
            array( 'yamamah-dashboard', 'إعدادات المتجر',   'yamamah-settings-store',    'render_settings_store' ),
            array( 'yamamah-dashboard', 'إعدادات العملة',   'yamamah-settings-currency', 'render_settings_currency' ),
            array( 'yamamah-dashboard', 'إعدادات الضرائب',  'yamamah-settings-tax',      'render_settings_tax' ),
            array( 'yamamah-dashboard', 'إعدادات البريد',   'yamamah-settings-email',    'render_settings_email' ),
            array( 'yamamah-dashboard', 'أكواد التتبع',     'yamamah-settings-tracking', 'render_settings_tracking' ),
            array( 'yamamah-dashboard', 'مفاتيح API',       'yamamah-settings-api-keys', 'render_settings_api_keys' ),
        ) );

        if ( $store_type === 'booking' || $store_type === 'rental' ) {
            $label = $store_type === 'booking' ? 'إعدادات الحجز' : 'إعدادات التأجير';
            $subpages[] = array( 'yamamah-dashboard', $label, 'yamamah-settings-rental', 'render_settings_rental' );
        }

        $subpages[] = array( 'yamamah-dashboard', 'إعدادات SMS', 'yamamah-settings-sms', 'render_settings_sms' );
        $subpages[] = array( 'yamamah-dashboard', 'النطاق المخصص', 'yamamah-settings-domain', 'render_settings_domain' );

        $subpages = array_merge( $subpages, array(
            array( 'yamamah-dashboard', 'إعدادات المظهر',   'yamamah-appearance',        'render_appearance' ),
            array( 'yamamah-dashboard', 'الصفحة الرئيسية',  'yamamah-home-content',      'render_home_content' ),
            array( 'yamamah-dashboard', 'ألوان الموقع',     'yamamah-site-colors',       'render_site_colors' ),
            array( 'yamamah-dashboard', 'التعليقات',         'yamamah-home-reviews',      'render_home_reviews' ),
            array( 'yamamah-dashboard', 'السياسات',          'yamamah-policies',          'render_policies' ),
            array( 'yamamah-dashboard', 'تواصل معنا',        'yamamah-contact-settings',  'render_contact_settings' ),
            array( 'yamamah-dashboard', 'الصفحات',           'yamamah-pages',             'render_pages' ),
            array( 'yamamah-dashboard', 'الوسائط',          'yamamah-media',             'render_media' ),
            array( 'yamamah-dashboard', 'الدعم الفني',      'yamamah-support',           'render_support' ),
        ) );

        if ( function_exists( 'yamamah_theme_has_blog_settings' ) && yamamah_theme_has_blog_settings() ) {
            $subpages[] = array( 'yamamah-dashboard', 'المدونة', 'yamamah-blog-settings', 'render_blog_settings' );
        }

        foreach ( $subpages as $page ) {
            add_submenu_page(
                $page[0],
                $page[1],
                $page[1],
                'manage_woocommerce',
                $page[2],
                array( $this, $page[3] )
            );
        }
    }

    /**
     * منع فلاش الوضع الفاتح عند التنقل في الوضع الليلي
     * يعمل قبل رسم الصفحة عبر inline script + CSS في <head>
     */
    public function dark_mode_early_apply() {
        if ( $this->is_hidden_support_user() && $this->is_wp_mode_active() ) {
            return;
        }

        $screen = get_current_screen();
        $is_yamamah = $screen && strpos( $screen->id, 'yamamah' ) !== false;
        $is_wc_wrap = function_exists( 'yamamah_get_wc_page_id' ) && yamamah_get_wc_page_id();
        if ( ! $is_yamamah && ! $is_wc_wrap ) {
            return;
        }
        ?>
        <script>
        (function(){
            if(localStorage.getItem('yamamah_dark')==='true'){
                document.documentElement.setAttribute('data-yamamah-dark','1');
            }
            function fixWidth(){
                var ids = ['wpcontent','wpbody','wpbody-content','wpwrap'];
                for(var i=0;i<ids.length;i++){
                    var el=document.getElementById(ids[i]);
                    if(el){
                        el.style.setProperty('margin-left','0','important');
                        el.style.setProperty('margin-right','0','important');
                        el.style.setProperty('padding-left','0','important');
                        el.style.setProperty('padding-right','0','important');
                        el.style.setProperty('width','100%','important');
                        el.style.setProperty('max-width','100%','important');
                        el.style.setProperty('float','none','important');
                    }
                }
                ['adminmenuwrap','adminmenuback','adminmenumain'].forEach(function(id){
                    var m=document.getElementById(id);
                    if(m) m.style.setProperty('display','none','important');
                });
            }
            fixWidth();
            document.addEventListener('DOMContentLoaded', fixWidth);
            window.addEventListener('load', fixWidth);
        })();
        </script>
        <style>
        html[data-yamamah-dark] .yamamah-wrap {
            --bg: #1a1a1a; --fg: #f5f5f5; --card: #222222; --card-fg: #f5f5f5;
            --muted: #333333; --muted-fg: #a0a0a0; --accent: #333333;
            --accent-fg: #f5f5f5; --border: rgba(255,255,255,0.1); --input-bg: #ffffff;
        }
        #adminmenuback, #adminmenuwrap, #adminmenumain { display:none!important; width:0!important; min-width:0!important; }
        #wpcontent { margin:0!important; padding:0!important; width:100%!important; max-width:100%!important; }
        #wpbody { width:100%!important; margin:0!important; padding:0!important; }
        #wpbody-content { width:100%!important; padding:0!important; float:none!important; overflow:visible!important; margin:0!important; }
        #wpwrap { width:100%!important; margin:0!important; padding:0!important; }
        </style>
        <?php
    }

    /**
     * تحميل ملفات CSS و JS
     */
    public function enqueue_assets( $hook ) {
        $is_yamamah = strpos( $hook, 'yamamah' ) !== false;
        $wc_page_id = function_exists( 'yamamah_get_wc_page_id' ) ? yamamah_get_wc_page_id() : false;

        if ( ! $is_yamamah && ! $wc_page_id ) {
            return;
        }

        if ( strpos( $hook, 'yamamah-media' ) !== false || strpos( $hook, 'yamamah-appearance' ) !== false || strpos( $hook, 'yamamah-payment-settings' ) !== false || strpos( $hook, 'yamamah-add-product' ) !== false || strpos( $hook, 'yamamah-providers' ) !== false || strpos( $hook, 'yamamah-categories' ) !== false || strpos( $hook, 'yamamah-home-content' ) !== false || strpos( $hook, 'yamamah-home-reviews' ) !== false || strpos( $hook, 'yamamah-blog-settings' ) !== false || strpos( $hook, 'yamamah-contact-settings' ) !== false || strpos( $hook, 'yamamah-settings-email' ) !== false ) {
            wp_enqueue_media();
        }

        if ( strpos( $hook, 'yamamah-add-product' ) !== false ) {
            wp_enqueue_editor();
        }

        if ( strpos( $hook, 'yamamah-analytics-revenue' ) !== false || strpos( $hook, 'yamamah-analytics-products' ) !== false || $hook === 'toplevel_page_yamamah-dashboard' ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', array(), '4.4.7', true );
        }

        if ( strpos( $hook, 'yamamah-shipping-rates' ) !== false ) {
            $shipping_plugin_url = defined( 'YAMAMA_SHIPPING_URL' ) ? YAMAMA_SHIPPING_URL : plugin_dir_url( WP_PLUGIN_DIR . '/yamama-shipping/yamama-shipping.php' );
            $shipping_version    = defined( 'YAMAMA_SHIPPING_VERSION' ) ? YAMAMA_SHIPPING_VERSION : '1.0';

            wp_enqueue_style( 'yamama-admin-shipment', $shipping_plugin_url . 'assets/css/admin-shipment.css', array(), $shipping_version );
            wp_enqueue_script( 'yamama-admin-shipping-rates', $shipping_plugin_url . 'assets/js/admin-shipping-rates.js', array( 'jquery' ), $shipping_version, true );

            wp_localize_script( 'yamama-admin-shipping-rates', 'yamamaShipping', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'yamama_shipping_nonce' ),
            ) );
        }

        if ( strpos( $hook, 'yamamah-create-shipment' ) !== false ) {
            $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

            $shipping_plugin_url = defined( 'YAMAMA_SHIPPING_URL' ) ? YAMAMA_SHIPPING_URL : plugin_dir_url( WP_PLUGIN_DIR . '/yamama-shipping/yamama-shipping.php' );
            $shipping_version    = defined( 'YAMAMA_SHIPPING_VERSION' ) ? YAMAMA_SHIPPING_VERSION : '1.0';

            wp_enqueue_style( 'yamama-admin-shipment', $shipping_plugin_url . 'assets/css/admin-shipment.css', array(), $shipping_version );
            wp_enqueue_script( 'yamama-admin-shipment', $shipping_plugin_url . 'assets/js/admin-shipment.js', array( 'jquery' ), $shipping_version, true );

            $localize = array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'yamama_shipping_nonce' ),
                'orderId'       => $order_id,
                'returnTo'      => 'yamama-orders',
            );

            wp_localize_script( 'yamama-admin-shipment', 'yamamaShipping', $localize );
        }

        wp_enqueue_style(
            'yamamah-dashboard-css',
            YAMAMAH_PLUGIN_URL . 'assets/css/style.css',
            array(),
            YAMAMAH_VERSION
        );

        // Google Fonts - Tajawal
        wp_enqueue_style(
            'yamamah-font',
            'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap',
            array(),
            null
        );

        wp_enqueue_script(
            'yamamah-dashboard-js',
            YAMAMAH_PLUGIN_URL . 'assets/js/app.js',
            array( 'jquery' ),
            YAMAMAH_VERSION,
            true
        );

        // تمرير المتغيرات إلى JavaScript
        wp_localize_script( 'yamamah-dashboard-js', 'yamamahData', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'admin_url'   => admin_url(),
            'nonce'       => wp_create_nonce( 'yamamah_nonce' ),
            'plugin_url'  => YAMAMAH_PLUGIN_URL,
            'store_name'  => get_option( 'yamamah_store_name', get_bloginfo( 'name' ) ),
            'store_url'   => home_url(),
            'currency'    => get_option( 'yamamah_store_currency', 'SAR' ),
            'admin_email' => get_option( 'admin_email' ),
            'current_user'=> wp_get_current_user()->display_name,
            'pages'       => array(
                'dashboard'  => admin_url( 'admin.php?page=yamamah-dashboard' ),
                'operations' => admin_url( 'admin.php?page=yamamah-operations' ),
                'orders'     => admin_url( 'admin.php?page=yamamah-orders' ),
                'products'   => admin_url( 'admin.php?page=yamamah-products' ),
                'customers'  => admin_url( 'admin.php?page=yamamah-customers' ),
                'payment_methods'   => admin_url( 'admin.php?page=yamamah-payment-methods' ),
                'payment_settings'  => admin_url( 'admin.php?page=yamamah-payment-settings' ),
                'shipping_orders'   => admin_url( 'admin.php?page=yamamah-shipping-orders' ),
                'shipping_rates'    => admin_url( 'admin.php?page=yamamah-shipping-rates' ),
                'shipping_settings' => admin_url( 'admin.php?page=yamamah-shipping-settings' ),
                'analytics'         => admin_url( 'admin.php?page=yamamah-analytics-revenue' ),
                'analytics_revenue' => admin_url( 'admin.php?page=yamamah-analytics-revenue' ),
                'analytics_products'=> admin_url( 'admin.php?page=yamamah-analytics-products' ),
                'analytics_settings'=> admin_url( 'admin.php?page=yamamah-analytics-settings' ),
                'users'      => admin_url( 'admin.php?page=yamamah-users' ),
                'admins'     => admin_url( 'admin.php?page=yamamah-admins' ),
                'home_content'   => admin_url( 'admin.php?page=yamamah-home-content' ),
                'site_colors'    => admin_url( 'admin.php?page=yamamah-site-colors' ),
                'home_reviews'   => admin_url( 'admin.php?page=yamamah-home-reviews' ),
                'policies'       => admin_url( 'admin.php?page=yamamah-policies' ),
                'contact_settings' => admin_url( 'admin.php?page=yamamah-contact-settings' ),
                'content_pages'  => admin_url( 'admin.php?page=yamamah-pages' ),
                'settings'   => admin_url( 'admin.php?page=yamamah-settings-general' ),
                'appearance' => admin_url( 'admin.php?page=yamamah-appearance' ),
                'media'      => admin_url( 'admin.php?page=yamamah-media' ),
                'support'    => admin_url( 'admin.php?page=yamamah-support' ),
            ),
            'i18n' => array(
                'save_success' => 'تم الحفظ بنجاح',
                'save_error'   => 'حدث خطأ أثناء الحفظ',
                'confirm_delete'=> 'هل أنت متأكد من الحذف؟',
                'loading'      => 'جاري التحميل...',
                'currency_symbol' => 'ر.س',
            ),
            'catalog_labels' => yamamah_get_catalog_labels(),
            'theme_defaults' => array(
                'logo' => apply_filters( 'ydash_get_logo', '' ),
                'icon' => apply_filters( 'ydash_get_favicon', '' ),
            ),
        ));
    }

    /**
     * إضافة كلاس للـ body
     */
    public function add_body_class( $classes ) {
        $screen = get_current_screen();
        if ( $screen && strpos( $screen->id, 'yamamah' ) !== false ) {
            $classes .= ' yamamah-admin-page';
            if ( get_option( 'yamamah_dark_mode', 'no' ) === 'yes' ) {
                $classes .= ' yamamah-dark';
            }
        }

        if ( function_exists( 'yamamah_get_wc_page_id' ) && yamamah_get_wc_page_id() ) {
            $classes .= ' yamamah-admin-page yamamah-wc-wrap';
        }

        return $classes;
    }

    /**
     * عرض sidebar + topbar ثابتين على صفحات WooCommerce المغلفة
     */
    public function render_wc_layout_wrapper() {
        if ( ! function_exists( 'yamamah_get_wc_page_id' ) || ! yamamah_get_wc_page_id() ) {
            return;
        }

        $current_user = wp_get_current_user();
        $store_name   = get_option( 'yamamah_store_name', get_bloginfo( 'name' ) );
        $store_url    = home_url();
        $dark_mode    = get_option( 'yamamah_dark_mode', 'no' );
        ?>
        <div class="yamamah-wrap <?php echo $dark_mode === 'yes' ? 'dark' : ''; ?>" id="yamamahApp" dir="rtl">
        <script>
        (function(){
            var el = document.getElementById('yamamahApp');
            var d  = localStorage.getItem('yamamah_dark') === 'true';
            if (d && !el.classList.contains('dark')) el.classList.add('dark');
            if (!d && el.classList.contains('dark')) el.classList.remove('dark');
        })();
        </script>
            <div class="yamamah-wc-fixed-topbar">
                <div class="topbar-purple">
                    <div class="brand">
                        <button class="icon-btn hamburger" id="hamburgerBtn"></button>
                        <span><?php echo esc_html( $store_name ); ?></span>
                    </div>
                    <div class="actions">
                        <button class="icon-btn" id="darkToggle" title="الوضع الليلي"></button>
                        <a href="<?php echo esc_url( $store_url ); ?>" class="btn-store" target="_blank">
                            <span>اذهب إلى المتجر</span>
                        </a>
                    </div>
                </div>
                <div class="topbar-search">
                    <div class="right-section">
                        <div class="logo-icon">Y</div>
                        <div class="search-box">
                            <span class="search-icon"></span>
                            <input type="text" placeholder="بحث..." id="yamamahSearch">
                        </div>
                    </div>
                    <div class="left-section">
                        <div class="yamamah-notif-wrap" id="yamamahNotifWrap">
                            <button class="icon-btn" style="position:relative;" title="الإشعارات" id="yamamahNotifBtn">
                                <span class="yamamah-notif-count" id="yamamahNotifCount" style="display:none;">0</span>
                            </button>
                            <div class="yamamah-notif-panel" id="yamamahNotifPanel">
                                <div class="yamamah-notif-header">
                                    <strong>الإشعارات</strong>
                                    <button type="button" id="yamamahMarkAllRead" class="yamamah-notif-mark-all">قراءة الكل</button>
                                </div>
                                <div class="yamamah-notif-body" id="yamamahNotifBody">
                                    <div class="yamamah-notif-empty">لا توجد إشعارات</div>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-settings-email' ) ); ?>" class="icon-btn" id="yamamahMailBtn" title="البريد" aria-label="البريد"></a>
                        <div class="avatar" title="<?php echo esc_attr( $current_user->display_name ); ?>">
                            <?php echo esc_html( yamamah_get_initials( $current_user->display_name ) ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            <div class="yamamah-wc-fixed-sidebar">
                <aside class="sidebar" id="sidebar">
                    <div class="sidebar-mobile-header">
                        <span class="text-sm text-muted">القائمة</span>
                        <button id="sidebarClose"></button>
                    </div>
                    <div class="sidebar-scroll" id="sidebarMenu">
                        <?php yamamah_render_sidebar_menu(); ?>
                    </div>
                </aside>
            </div>
        </div>
        <?php
    }

    // ===================================================
    //  Render Methods — كل صفحة تحمل Template خاص بها
    // ===================================================

    public function render_dashboard() {
        $api = new Yamamah_API();
        $data = array(
            'stats'              => $api->get_dashboard_stats(),
            'recent_orders'      => $api->get_recent_orders( 5 ),
            'top_products'       => $api->get_top_products( 5 ),
            'stock_alerts'       => $api->get_stock_alerts(),
            'order_overview'     => $api->get_order_overview(),
            'monthly_chart'      => $api->get_monthly_sales_chart_data(),
            'sales_by_category'  => $api->get_sales_by_category(),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_operations() {
        $api = new Yamamah_API();
        $data = array(
            'order_summary'  => $api->get_order_overview(),
            'recent_orders'  => $api->get_recent_orders( 4 ),
            'top_customers'  => $api->get_top_customers( 4 ),
            'active_coupons' => $api->get_active_coupons(),
        );

        $store_type = yamamah_get_store_type();
        if ( in_array( $store_type, array( 'booking', 'rental' ), true ) ) {
            $data['bookings'] = $this->get_bookings_for_operations( 10 );
        }

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/operations.php';
    }

    private function get_bookings_for_operations( $limit = 50 ) {
        $orders = wc_get_orders( array(
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ),
        ) );

        $bookings = array();
        foreach ( $orders as $order ) {
            $items = $order->get_items();
            foreach ( $items as $item ) {
                $product_id   = $item->get_product_id();
                $product_name = $item->get_name();

                $check_in  = $order->get_meta( '_villago_checkin' )
                          ?: $order->get_meta( '_med_rent_start' )
                          ?: $order->get_meta( '_booking_check_in' )
                          ?: $order->get_meta( '_rental_start_date' )
                          ?: '';
                $check_out = $order->get_meta( '_villago_checkout' )
                          ?: $order->get_meta( '_med_rent_end' )
                          ?: $order->get_meta( '_booking_check_out' )
                          ?: $order->get_meta( '_rental_end_date' )
                          ?: '';

                $deposit_enabled = get_post_meta( $product_id, '_deposit_enabled', true );
                $deposit_amount  = '';
                if ( $deposit_enabled === 'yes' ) {
                    $dep_type  = get_post_meta( $product_id, '_deposit_type', true );
                    $dep_value = (float) get_post_meta( $product_id, '_deposit_value', true );
                    $price     = (float) $item->get_total();
                    if ( $dep_type === 'percentage' && $dep_value > 0 ) {
                        $deposit_amount = wc_price( $price * $dep_value / 100 );
                    } elseif ( $dep_value > 0 ) {
                        $deposit_amount = wc_price( $dep_value );
                    }
                }

                $bookings[] = array(
                    'order_id'     => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'customer'     => $order->get_billing_first_name() ?: $order->get_billing_email(),
                    'product'      => $product_name,
                    'check_in'     => $check_in,
                    'check_out'    => $check_out,
                    'total'        => $order->get_formatted_order_total(),
                    'deposit'      => $deposit_amount,
                    'status'       => $order->get_status(),
                    'status_label' => wc_get_order_status_name( $order->get_status() ),
                    'date'         => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d' ) : '',
                );
            }
        }

        return $bookings;
    }

    public function render_orders() {
        $api = new Yamamah_API();
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'any';
        $data = $api->get_orders( $current_page, 20, $status );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/orders.php';
    }

    public function render_order_detail() {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/order-detail.php';
    }

    public function render_loyalty() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/loyalty.php';
    }

    public function render_coupons() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/coupons.php';
    }

    public function render_abandoned_carts() {
        global $wpdb;
        $table = $wpdb->prefix . 'yamamah_abandoned_carts';

        yamamah_create_abandoned_carts_table();

        $abandon_minutes = intval( get_option( 'yamamah_abandon_minutes', 30 ) );
        if ( $abandon_minutes < 15 ) $abandon_minutes = 15;
        if ( $abandon_minutes > 60 ) $abandon_minutes = 60;
        $cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$abandon_minutes} minutes", current_time( 'timestamp' ) ) );

        $carts = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'open' AND updated_at < %s ORDER BY updated_at DESC LIMIT 200",
            $cutoff
        ) );

        foreach ( $carts as &$c ) {
            if ( $c->user_id > 0 ) {
                $u = get_userdata( $c->user_id );
                if ( $u ) {
                    if ( empty( trim( $c->customer_name ) ) ) {
                        $n = trim( get_user_meta( $c->user_id, 'billing_first_name', true ) . ' ' . get_user_meta( $c->user_id, 'billing_last_name', true ) );
                        if ( empty( trim( $n ) ) ) $n = trim( $u->first_name . ' ' . $u->last_name );
                        if ( empty( trim( $n ) ) ) $n = $u->display_name;
                        $c->customer_name = $n;
                    }
                    if ( empty( $c->customer_email ) ) {
                        $c->customer_email = $u->user_email;
                    }
                    if ( empty( $c->customer_phone ) ) {
                        $p = get_user_meta( $c->user_id, 'billing_phone', true );
                        if ( empty( $p ) ) $p = get_user_meta( $c->user_id, 'phone', true );
                        $c->customer_phone = $p;
                    }
                }
            }
        }
        unset( $c );

        $store_name        = get_bloginfo( 'name' );
        $checkout_url      = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
        $abandoned_settings = get_option( 'yamamah_abandoned_settings', array() );

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/abandoned-carts.php';
    }

    public function render_reports() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/reports.php';
    }

    public function render_products() {
        $api = new Yamamah_API();
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $data = $api->get_products( $current_page, 20 );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/products.php';
    }

    public function render_add_product() {
        $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
        $product = null;
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
        }
        $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        $tags       = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
        $attributes = wc_get_attribute_taxonomies();
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/add-product.php';
    }

    public function render_products_import() {
        if ( function_exists( 'yamamah_get_store_type' ) && yamamah_get_store_type() !== 'retail' ) {
            echo '<div class="wrap"><h1>غير متاح</h1><p>صفحة استيراد/تصدير المنتجات متاحة فقط لمتاجر البيع (retail).</p></div>';
            return;
        }
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/products-import.php';
    }

    public function render_categories() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/categories.php';
    }

    public function render_tags() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/tags.php';
    }

    public function render_attributes() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/attributes.php';
    }

    public function render_customers() {
        $api = new Yamamah_API();
        $data = $api->get_customers();
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/customers.php';
    }

    public function render_payment_methods() {
        $saved = get_option( 'yamamah_payment_methods', array() );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/payment-methods.php';
    }

    public function render_payment_settings() {
        $saved_settings = get_option( 'yamamah_payment_settings', array() );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/payment-settings.php';
    }

    public function render_shipping_orders() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/shipping-orders.php';
    }

    public function render_shipping_detail() {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/shipping-detail.php';
    }

    public function render_create_shipment() {
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/create-shipment.php';
    }

    public function render_shipping_settings() {
        $shipper = array();
        if ( class_exists( 'Yamama_Shipping_Admin' ) ) {
            $shipper = Yamama_Shipping_Admin::get_shipper_defaults();
        } else {
            $shipper = get_option( 'yamama_shipping_shipper', array() );
            if ( ! is_array( $shipper ) ) $shipper = array();
            $shipper = wp_parse_args( $shipper, array(
                'name' => '', 'phone' => '', 'country' => 'SA', 'city' => '',
                'district' => '', 'address1' => '', 'address2' => '', 'national_address' => '',
            ));
        }

        $has_credentials = false;
        $debug = array( 'registered' => false, 'store_uuid' => '', 'last_registration_at' => '', 'last_registration_error' => '' );
        $middleware_url = '';

        if ( class_exists( 'Yamama_Shipping_Client' ) ) {
            $has_credentials = Yamama_Shipping_Client::credentials_exist();
            $debug = Yamama_Shipping_Client::get_registration_debug();
            $client_settings = Yamama_Shipping_Client::get_settings();
            $middleware_url = $client_settings['middleware_base_url'] ?? '';
        }

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/shipping-settings.php';
    }

    public function render_shipping_rates() {
        $has_credentials = false;

        if ( class_exists( 'Yamama_Shipping_Client' ) ) {
            $has_credentials = Yamama_Shipping_Client::credentials_exist();
        }

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/shipping-rates.php';
    }

    public function render_analytics() {
        wp_safe_redirect( admin_url( 'admin.php?page=yamamah-analytics-revenue' ) );
        exit;
    }

    public function render_analytics_revenue() {
        $api = new Yamamah_API();
        $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30day';
        $data = array(
            'revenue' => $api->get_revenue_analytics_full( $period ),
            'chart'   => $api->get_monthly_sales_chart_data(),
            'period'  => $period,
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/analytics-revenue.php';
    }

    public function render_analytics_products() {
        $api = new Yamamah_API();
        $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30day';
        $data = array(
            'products' => $api->get_products_analytics_data( $period ),
            'chart'    => $api->get_monthly_orders_chart_data(),
            'period'   => $period,
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/analytics-products.php';
    }

    public function render_analytics_settings() {
        $data = array(
            'excluded_statuses'   => get_option( 'yamamah_analytics_excluded', array( 'pending', 'cancelled', 'failed' ) ),
            'actionable_statuses' => get_option( 'yamamah_analytics_actionable', array( 'processing', 'on-hold' ) ),
            'date_range'          => get_option( 'yamamah_analytics_date_range', 'month_to_date' ),
            'date_type'           => get_option( 'yamamah_analytics_date_type', 'date_created' ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/analytics-settings.php';
    }

    public function render_users() {
        $customers = get_users( array(
            'role__not_in' => array( 'administrator', 'shop_manager', 'editor' ),
            'orderby'      => 'registered',
            'order'        => 'DESC',
            'number'       => 200,
        ));
        $all_count      = count( $customers );
        $active_count   = 0;
        $inactive_count = 0;
        $thirty_days    = strtotime( '-30 days' );

        foreach ( $customers as $c ) {
            $last = get_user_meta( $c->ID, 'last_login', true );
            if ( $last && strtotime( $last ) >= $thirty_days ) {
                $active_count++;
            } else {
                $inactive_count++;
            }
        }

        $data = array(
            'customers'      => $customers,
            'all_count'      => $all_count,
            'active_count'   => $active_count,
            'inactive_count' => $inactive_count,
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/users.php';
    }

    public function render_admins() {
        $all_admins = get_users( array(
            'role__in' => array( 'administrator', 'shop_manager', 'editor' ),
            'orderby'  => 'registered',
            'order'    => 'DESC',
        ));
        $admins = array();
        foreach ( $all_admins as $a ) {
            if ( ! get_user_meta( $a->ID, '_yamama_hidden_user', true ) ) {
                $admins[] = $a;
            }
        }
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/admins.php';
    }

    public function render_my_account() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/my-account.php';
    }

    public function render_settings_general() {
        $settings = array(
            'store_name'  => get_option( 'yamamah_store_name', get_bloginfo( 'name' ) ),
            'store_email' => get_option( 'yamamah_store_email', get_option( 'admin_email' ) ),
            'store_phone' => get_option( 'yamamah_store_phone', '' ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-general.php';
    }

    public function render_settings_store() {
        $settings = array(
            'country' => get_option( 'woocommerce_default_country', 'SA' ),
            'city'    => get_option( 'woocommerce_store_city', '' ),
            'address' => get_option( 'woocommerce_store_address', '' ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-store.php';
    }

    public function render_settings_currency() {
        $settings = array(
            'currency'     => get_option( 'yamamah_store_currency', 'SAR' ),
            'currency_pos' => get_option( 'woocommerce_currency_pos', 'right_space' ),
            'thousand_sep' => get_option( 'woocommerce_price_thousand_sep', ',' ),
            'decimal_sep'  => get_option( 'woocommerce_price_decimal_sep', '.' ),
            'num_decimals' => get_option( 'woocommerce_price_num_decimals', 2 ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-currency.php';
    }

    public function render_settings_tax() {
        $settings = array(
            'tax_enabled' => get_option( 'yamamah_tax_enabled', 'yes' ),
            'tax_rate'    => get_option( 'yamamah_tax_rate', 15 ),
            'tax_mode'    => function_exists( 'yamamah_get_booking_tax_mode' )
                ? yamamah_get_booking_tax_mode()
                : get_option( 'yamamah_tax_mode', 'exclusive' ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-tax.php';
    }

    public function render_settings_email() {
        $email_settings = array(
            'from_name'    => get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ),
            'from_address' => get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
            'header_image' => get_option( 'woocommerce_email_header_image', '' ),
            'footer_text'  => get_option( 'woocommerce_email_footer_text', '' ),
            'base_color'   => get_option( 'woocommerce_email_base_color', '#7f54b3' ),
            'bg_color'     => get_option( 'woocommerce_email_background_color', '#f7f7f7' ),
            'body_bg'      => get_option( 'woocommerce_email_body_background_color', '#ffffff' ),
            'text_color'   => get_option( 'woocommerce_email_text_color', '#3c3c3c' ),
        );
        $smtp_settings = array(
            'enabled'    => get_option( 'yamamah_smtp_enabled', 'no' ),
            'host'       => get_option( 'yamamah_smtp_host', '' ),
            'port'       => get_option( 'yamamah_smtp_port', '587' ),
            'encryption' => get_option( 'yamamah_smtp_encryption', 'tls' ),
            'username'   => get_option( 'yamamah_smtp_username', '' ),
            'password'   => get_option( 'yamamah_smtp_password', '' ),
        );
        $wc_emails = array();
        if ( function_exists( 'WC' ) && WC()->mailer() ) {
            foreach ( WC()->mailer()->get_emails() as $email ) {
                $wc_emails[] = array(
                    'id'          => $email->id,
                    'title'       => $email->get_title(),
                    'description' => $email->get_description(),
                    'enabled'     => $email->is_enabled(),
                    'recipient'   => $email->is_customer_email() ? '' : $email->get_recipient(),
                    'is_customer' => $email->is_customer_email(),
                );
            }
        }
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-email.php';
    }

    public function render_settings_tracking() {
        $tracking = get_option( 'yamamah_tracking_settings', array() );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-tracking.php';
    }

    public function render_settings_api_keys() {
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_api_keys';
        $keys = array();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $keys = $wpdb->get_results(
                "SELECT key_id, user_id, description, permissions, truncated_key, last_access
                 FROM {$table} ORDER BY key_id DESC"
            );
        }
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-api-keys.php';
    }

    public function render_appearance() {
        $settings = array(
            'primary_color'   => get_option( 'yamamah_primary_color', '#10b981' ),
            'secondary_color' => get_option( 'yamamah_secondary_color', '#6366f1' ),
            'dark_mode'       => get_option( 'yamamah_dark_mode', 'no' ),
        );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/appearance.php';
    }

    public function render_media() {
        // استخدام مكتبة الوسائط في ووردبريس
        $attachments = get_posts( array(
            'post_type'      => 'attachment',
            'posts_per_page' => 50,
            'post_status'    => 'inherit',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/media.php';
    }

    public function render_support() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/support.php';
    }

    public function render_home_content() {
        $content = apply_filters( 'ydash_get_homepage_settings', array() );
        if ( ! is_array( $content ) ) $content = array();
        $template = apply_filters( 'ydash_home_content_template', '' );
        if ( $template && file_exists( $template ) ) {
            include $template;
        } else {
            include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-home.php';
        }
    }

    public function render_site_colors() {
        $colors   = apply_filters( 'ydash_get_color_settings', array() );
        $manifest = apply_filters( 'ydash_theme_manifest', array() );
        $color_fields = isset( $manifest['color_fields'] ) ? $manifest['color_fields'] : array(
            'button_primary'    => 'لون الأزرار الرئيسي',
            'header_background' => 'خلفية الهيدر',
            'footer_background' => 'خلفية الفوتر',
        );
        if ( ! is_array( $colors ) ) $colors = array();
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-colors.php';
    }

    public function render_home_reviews() {
        $reviews = apply_filters( 'ydash_get_reviews', array() );
        if ( ! is_array( $reviews ) ) $reviews = array();
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-reviews.php';
    }

    public function render_policies() {
        $policy_pages = apply_filters( 'ydash_policy_pages', array() );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-policies.php';
    }

    public function render_contact_settings() {
        $manifest = apply_filters( 'ydash_contact_page_manifest', array() );
        $content = apply_filters( 'ydash_get_contact_page_settings', array() );
        $floating = apply_filters( 'ydash_get_floating_buttons', array() );

        if ( ! is_array( $content ) ) $content = array();
        if ( ! is_array( $manifest ) ) $manifest = array();

        $smtp_settings = array(
            'enabled'    => get_option( 'yamamah_smtp_enabled', 'no' ),
            'host'       => get_option( 'yamamah_smtp_host', '' ),
            'port'       => get_option( 'yamamah_smtp_port', '587' ),
            'encryption' => get_option( 'yamamah_smtp_encryption', 'tls' ),
            'username'   => get_option( 'yamamah_smtp_username', '' ),
            'password'   => get_option( 'yamamah_smtp_password', '' ),
        );
        $recipient = get_option( 'yamamah_contact_form_recipient', get_option( 'admin_email' ) );

        $social_links = get_option( 'yamamah_social_links', array() );
        if ( ! is_array( $social_links ) ) {
            $social_links = array();
        }

        $footer_contact_manifest = apply_filters( 'ydash_footer_contact_manifest', array() );
        $footer_contact_settings = apply_filters( 'ydash_get_footer_contact_settings', array() );

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-contact.php';
    }

    public function render_blog_settings() {
        if ( ! function_exists( 'yamamah_theme_has_blog_settings' ) || ! yamamah_theme_has_blog_settings() ) {
            wp_safe_redirect( admin_url( 'admin.php?page=yamamah-appearance' ) );
            exit;
        }

        $manifest = apply_filters( 'ydash_blog_page_manifest', array() );
        $content  = apply_filters( 'ydash_get_blog_page_settings', array() );

        if ( ! is_array( $content ) ) {
            $content = array();
        }
        if ( ! is_array( $manifest ) ) {
            $manifest = array();
        }

        $blog_posts = function_exists( 'yamamah_get_dashboard_blog_posts' )
            ? yamamah_get_dashboard_blog_posts()
            : array();
        $blog_categories = function_exists( 'yamamah_get_dashboard_blog_categories' )
            ? yamamah_get_dashboard_blog_categories()
            : array();

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-blog.php';
    }

    public function render_pages() {
        $pages = apply_filters( 'ydash_get_pages_manifest', array() );
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/content-pages.php';
    }

    public function render_providers() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/providers.php';
    }

    public function render_settings_rental() {
        $settings = apply_filters( 'ydash_get_rental_settings', array() );

        $bridge_booking = apply_filters( 'ydash_get_booking_settings', null );
        if ( is_array( $bridge_booking ) ) {
            $settings = $bridge_booking;
        }

        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-rental.php';
    }

    public function render_settings_sms() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-sms.php';
    }

    public function render_settings_domain() {
        include YAMAMAH_PLUGIN_DIR . 'templates/pages/settings-domain.php';
    }

    /**
     * زر التبديل بين لوحة اليمامة ولوحة ووردبريس الأصلية (ليوزر الدعم الخفي فقط)
     */
    public function render_support_toggle_button() {
        if ( ! $this->is_hidden_support_user() ) return;

        $in_wp_mode = $this->is_wp_mode_active();
        $toggle_label = $in_wp_mode ? 'لوحة اليمامة' : 'لوحة ووردبريس';
        $toggle_icon  = $in_wp_mode
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
        ?>
        <div id="yamama-support-toggle" style="position:fixed;bottom:20px;left:20px;z-index:999999;">
            <button onclick="yamamaToggleWpMode()" style="
                display:flex;align-items:center;gap:8px;
                background:linear-gradient(135deg,#1e3a5f,#0d2137);
                color:#fff;border:none;padding:10px 18px;
                border-radius:10px;font-family:'Tajawal',sans-serif;
                font-size:13px;font-weight:500;cursor:pointer;
                box-shadow:0 4px 16px rgba(0,0,0,0.3);
                transition:all 0.2s;">
                <?php echo $toggle_icon; ?>
                <span>التبديل إلى <?php echo esc_html( $toggle_label ); ?></span>
            </button>
        </div>
        <script>
        function yamamaToggleWpMode(){
            var fd = new FormData();
            fd.append('action','yamama_toggle_wp_mode');
            fd.append('nonce','<?php echo wp_create_nonce( 'yamama_toggle_wp_mode' ); ?>');
            fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>',{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(r){
                if(r.success) window.location.href = r.data.redirect;
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX: تبديل وضع WP / اليمامة ليوزر الدعم
     */
    public function ajax_toggle_wp_mode() {
        check_ajax_referer( 'yamama_toggle_wp_mode', 'nonce' );
        if ( ! $this->is_hidden_support_user() ) {
            wp_send_json_error( 'غير مصرح' );
        }

        $current = isset( $_COOKIE['yamama_wp_mode'] ) && $_COOKIE['yamama_wp_mode'] === '1';
        $new_val = $current ? '0' : '1';

        setcookie( 'yamama_wp_mode', $new_val, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

        if ( $new_val === '1' ) {
            wp_send_json_success( array( 'redirect' => admin_url( 'index.php' ) ) );
        } else {
            wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=yamamah-dashboard' ) ) );
        }
    }

    /**
     * تعطيل modal انتهاء الجلسة الافتراضي من WordPress
     */
    public function disable_wp_auth_check( $hook ) {
        if ( ! $this->is_yamamah_user() ) {
            return;
        }
        if ( $this->is_hidden_support_user() && $this->is_wp_mode_active() ) {
            return;
        }
        remove_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );
        wp_dequeue_script( 'wp-auth-check' );
        wp_dequeue_style( 'wp-auth-check' );
    }

    /**
     * Modal مخصص لإعادة تسجيل الدخول عند انتهاء الجلسة
     */
    public function render_session_modal() {
        if ( ! $this->is_yamamah_user() ) {
            return;
        }
        if ( $this->is_hidden_support_user() && $this->is_wp_mode_active() ) {
            return;
        }

        $screen = get_current_screen();
        $is_yamamah = $screen && strpos( $screen->id, 'yamamah' ) !== false;
        $wc_page    = function_exists( 'yamamah_get_wc_page_id' ) && yamamah_get_wc_page_id();
        if ( ! $is_yamamah && ! $wc_page ) {
            return;
        }

        $current_user = wp_get_current_user();
        $store_name   = get_option( 'yamamah_store_name', get_bloginfo( 'name' ) );
        $user_login   = $current_user->user_login;
        $user_display = $current_user->display_name;
        $user_email   = $current_user->user_email;
        ?>
        <div id="yamamah-session-overlay" style="display:none;">
            <div class="yamamah-session-modal">
                <div class="yamamah-session-header">
                    <div class="yamamah-session-logo">Y</div>
                    <h2><?php echo esc_html( $store_name ); ?></h2>
                    <p>انتهت جلسة العمل الخاصة بك. أدخل كلمة المرور للمتابعة.</p>
                </div>
                <div class="yamamah-session-body">
                    <div class="yamamah-session-user">
                        <div class="yamamah-session-avatar"><?php echo esc_html( mb_substr( $user_display, 0, 1 ) ); ?></div>
                        <div>
                            <strong><?php echo esc_html( $user_display ); ?></strong>
                            <span><?php echo esc_html( $user_email ); ?></span>
                        </div>
                    </div>
                    <div class="yamamah-session-field">
                        <label for="yamamah-session-pass">كلمة المرور</label>
                        <div class="yamamah-session-input-wrap">
                            <input type="password" id="yamamah-session-pass" autocomplete="current-password" placeholder="أدخل كلمة المرور">
                            <button type="button" id="yamamah-session-toggle-pass" class="yamamah-session-eye" title="إظهار"></button>
                        </div>
                    </div>
                    <div id="yamamah-session-error" style="display:none;"></div>
                    <button type="button" id="yamamah-session-submit">
                        <span id="yamamah-session-btn-text">تسجيل الدخول</span>
                        <span id="yamamah-session-btn-spin" style="display:none;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M4 12a8 8 0 018-8" opacity="0.75"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></path></svg>
                        </span>
                    </button>
                    <a href="<?php echo esc_url( wp_login_url( admin_url( 'admin.php?page=yamamah-dashboard' ) ) ); ?>" class="yamamah-session-alt-link">أو سجّل الدخول بحساب آخر</a>
                </div>
            </div>
        </div>

        <style>
        #yamamah-session-overlay{position:fixed;inset:0;z-index:999999;background:rgba(13,33,55,0.7);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;direction:rtl;font-family:'Tajawal',sans-serif;}
        .yamamah-session-modal{background:#fff;border-radius:20px;width:400px;max-width:92vw;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.3);animation:ySessionIn .3s ease;}
        @keyframes ySessionIn{from{opacity:0;transform:scale(0.95) translateY(10px);}to{opacity:1;transform:scale(1) translateY(0);}}
        .yamamah-session-header{background:linear-gradient(135deg,#0D2137 0%,#1a3a5c 50%,rgba(108,63,160,0.4) 100%);padding:28px 24px 22px;text-align:center;}
        .yamamah-session-logo{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#6C3FA0,#5a2d91);color:#fff;font-size:24px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;box-shadow:0 4px 16px rgba(108,63,160,0.4);}
        .yamamah-session-header h2{color:#fff;font-size:18px;font-weight:700;margin:0 0 6px;}
        .yamamah-session-header p{color:rgba(255,255,255,0.55);font-size:13px;margin:0;line-height:1.6;}
        .yamamah-session-body{padding:24px;}
        .yamamah-session-user{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8f9fc;border-radius:12px;margin-bottom:20px;}
        .yamamah-session-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#6C3FA0,#5a2d91);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0;}
        .yamamah-session-user strong{display:block;font-size:14px;color:#0D2137;}
        .yamamah-session-user span{font-size:12px;color:#8896a7;direction:ltr;display:block;}
        .yamamah-session-field{margin-bottom:16px;}
        .yamamah-session-field label{display:block;font-size:13px;color:#5C6478;margin-bottom:6px;font-weight:500;}
        .yamamah-session-input-wrap{position:relative;}
        .yamamah-session-input-wrap input{width:100%;padding:12px 44px 12px 14px;border:1.5px solid #e2e6ec;border-radius:10px;font-size:14px;font-family:'Tajawal',sans-serif;background:#f8f9fc;transition:all .2s;box-sizing:border-box;}
        .yamamah-session-input-wrap input:focus{outline:none;border-color:#6C3FA0;background:#fff;box-shadow:0 0 0 3px rgba(108,63,160,0.1);}
        .yamamah-session-eye{position:absolute;left:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#8896a7;padding:4px;}
        .yamamah-session-eye:hover{color:#6C3FA0;}
        .yamamah-session-eye::before{content:'👁';font-size:16px;}
        #yamamah-session-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:14px;text-align:center;}
        #yamamah-session-submit{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,#6C3FA0,#5a2d91);color:#fff;font-size:15px;font-weight:600;font-family:'Tajawal',sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;}
        #yamamah-session-submit:hover{background:linear-gradient(135deg,#7c4fb0,#6C3FA0);box-shadow:0 4px 16px rgba(108,63,160,0.4);}
        #yamamah-session-submit:disabled{opacity:0.7;cursor:not-allowed;}
        .yamamah-session-alt-link{display:block;text-align:center;margin-top:14px;font-size:12px;color:#8896a7;text-decoration:none;}
        .yamamah-session-alt-link:hover{color:#6C3FA0;text-decoration:underline;}
        .dark #yamamah-session-overlay{background:rgba(0,0,0,0.8);}
        .dark .yamamah-session-modal{background:#222;}
        .dark .yamamah-session-user{background:#2a2a2a;}
        .dark .yamamah-session-user strong{color:#f5f5f5;}
        .dark .yamamah-session-input-wrap input{background:#2a2a2a;border-color:#444;color:#f5f5f5;}
        .dark .yamamah-session-input-wrap input:focus{border-color:#6C3FA0;background:#333;}
        .dark .yamamah-session-field label{color:#a0a0a0;}
        </style>

        <script>
        (function(){
            var overlay  = document.getElementById('yamamah-session-overlay');
            var passInp  = document.getElementById('yamamah-session-pass');
            var submitBtn= document.getElementById('yamamah-session-submit');
            var btnText  = document.getElementById('yamamah-session-btn-text');
            var btnSpin  = document.getElementById('yamamah-session-btn-spin');
            var errorDiv = document.getElementById('yamamah-session-error');
            var toggleBtn= document.getElementById('yamamah-session-toggle-pass');
            var checkInterval = 60000;
            var sessionExpired = false;

            if (toggleBtn && passInp) {
                toggleBtn.addEventListener('click', function() {
                    var isPass = passInp.type === 'password';
                    passInp.type = isPass ? 'text' : 'password';
                    toggleBtn.title = isPass ? 'إخفاء' : 'إظهار';
                });
            }

            function checkSession() {
                if (sessionExpired) return;
                var fd = new FormData();
                fd.append('action', 'heartbeat');
                fd.append('_nonce', '<?php echo wp_create_nonce( 'heartbeat-nonce' ); ?>');
                fd.append('interval', 'fast');
                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r) {
                    if (r.status === 403 || r.status === 401) { showModal(); return; }
                    return r.json();
                })
                .then(function(data) {
                    if (!data) return;
                    if (data.wp_auth_check === false || data.success === false) {
                        showModal();
                    }
                })
                .catch(function() {});
            }

            function showModal() {
                sessionExpired = true;
                overlay.style.display = 'flex';
                if (passInp) { passInp.value = ''; passInp.focus(); }
                errorDiv.style.display = 'none';
            }

            function doReauth() {
                var pass = (passInp || {}).value || '';
                if (!pass.trim()) {
                    errorDiv.textContent = 'يرجى إدخال كلمة المرور';
                    errorDiv.style.display = 'block';
                    return;
                }
                submitBtn.disabled = true;
                btnText.style.display = 'none';
                btnSpin.style.display = 'inline-flex';
                errorDiv.style.display = 'none';

                var fd = new FormData();
                fd.append('action', 'yamamah_reauth');
                fd.append('nonce', '<?php echo wp_create_nonce( 'yamamah_reauth' ); ?>');
                fd.append('user_login', '<?php echo esc_js( $user_login ); ?>');
                fd.append('password', pass);

                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    submitBtn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpin.style.display = 'none';

                    if (res.success) {
                        overlay.style.display = 'none';
                        sessionExpired = false;
                        passInp.value = '';
                    } else {
                        errorDiv.textContent = (res.data && res.data.message) ? res.data.message : 'كلمة المرور غير صحيحة';
                        errorDiv.style.display = 'block';
                        passInp.select();
                    }
                })
                .catch(function() {
                    submitBtn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpin.style.display = 'none';
                    errorDiv.textContent = 'خطأ في الاتصال بالخادم';
                    errorDiv.style.display = 'block';
                });
            }

            if (submitBtn) submitBtn.addEventListener('click', doReauth);
            if (passInp) passInp.addEventListener('keydown', function(e) { if (e.key === 'Enter') doReauth(); });

            setInterval(checkSession, checkInterval);
        })();
        </script>
        <?php
    }

    /**
     * AJAX: إعادة تسجيل الدخول عند انتهاء الجلسة
     */
    public function ajax_reauth() {
        check_ajax_referer( 'yamamah_reauth', 'nonce' );

        $user_login = sanitize_text_field( $_POST['user_login'] ?? '' );
        $password   = $_POST['password'] ?? '';

        if ( ! $user_login || ! $password ) {
            wp_send_json_error( array( 'message' => 'يرجى إدخال كلمة المرور.' ) );
        }

        $user = wp_authenticate( $user_login, $password );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => 'كلمة المرور غير صحيحة.' ) );
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        wp_send_json_success( array( 'message' => 'تم تسجيل الدخول بنجاح.' ) );
    }
}
