<?php

if (!defined('ABSPATH')) {
    exit;
}

class Yamama_Shipping_Admin
{
    const SHIPPER_OPTION = 'yamama_shipping_shipper';

    public static function init()
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_yamama_tap_return', [self::class, 'handle_tap_callback']);
        add_action('add_meta_boxes', [self::class, 'register_metabox']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

        add_action('wp_ajax_yamama_get_carriers', [self::class, 'ajax_get_carriers']);
        add_action('wp_ajax_yamama_get_cities', [self::class, 'ajax_get_cities']);
        add_action('wp_ajax_yamama_get_quote', [self::class, 'ajax_get_quote']);
        add_action('wp_ajax_yamama_save_pending', [self::class, 'ajax_save_pending']);
        add_action('wp_ajax_yamama_create_order', [self::class, 'ajax_create_order']);
        add_action('wp_ajax_yamama_complete_tap', [self::class, 'ajax_complete_tap']);
        add_action('wp_ajax_yamama_create_tap_charge', [self::class, 'ajax_create_tap_charge']);
        add_action('wp_ajax_yamama_force_reregister', [self::class, 'ajax_force_reregister']);
        add_action('wp_ajax_yamama_fetch_label', [self::class, 'ajax_fetch_label']);
        add_action('wp_ajax_yamama_get_platform_pricing', [self::class, 'ajax_get_platform_pricing']);
        add_action('wp_ajax_yamama_get_wallet', [self::class, 'ajax_get_wallet']);
        add_action('wp_ajax_yamama_create_wallet_topup', [self::class, 'ajax_create_wallet_topup']);
        add_action('wp_ajax_yamama_request_wallet_withdraw', [self::class, 'ajax_request_wallet_withdraw']);
        add_action('admin_post_yamama_wallet_topup_return', [self::class, 'handle_wallet_topup_return']);
    }

    /* ──────────────────────────────────────────────
     *  Helpers
     * ────────────────────────────────────────────── */

    public static function get_shipper_defaults()
    {
        $defaults = [
            'name'             => '',
            'phone'            => '',
            'country'          => 'SA',
            'city'             => '',
            'district'         => '',
            'address1'         => '',
            'address2'         => '',
            'national_address' => '',
        ];

        $saved = get_option(self::SHIPPER_OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return wp_parse_args($saved, $defaults);
    }

    /* ──────────────────────────────────────────────
     *  Admin Menu & Settings
     * ────────────────────────────────────────────── */

    public static function register_menu()
    {
        add_menu_page(
            'Yamama Shipping',
            __('الشحن', 'yamama-shipping'),
            'manage_woocommerce',
            'yamama-shipping',
            [self::class, 'render_orders_page'],
            'dashicons-location-alt',
            56
        );

        add_submenu_page(
            'yamama-shipping',
            __('الطلبات', 'yamama-shipping'),
            __('الطلبات', 'yamama-shipping'),
            'manage_woocommerce',
            'yamama-shipping',
            [self::class, 'render_orders_page']
        );

        add_submenu_page(
            'yamama-shipping',
            __('الإعدادات', 'yamama-shipping'),
            __('الإعدادات', 'yamama-shipping'),
            'manage_woocommerce',
            'yamama-shipping-settings',
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings()
    {
        register_setting('yamama_shipping_settings', self::SHIPPER_OPTION, [
            'sanitize_callback' => [self::class, 'sanitize_shipper'],
        ]);

        add_settings_section(
            'yamama_shipper_section',
            __('بيانات الشاحن (المرسل)', 'yamama-shipping'),
            function () {
                echo '<p>' . esc_html__('البيانات الافتراضية للمرسل التي تُعبأ تلقائياً عند إنشاء شحنة.', 'yamama-shipping') . '</p>';
            },
            'yamama-shipping-settings'
        );

        $shipper_fields = [
            'name'             => __('اسم المرسل', 'yamama-shipping'),
            'phone'            => __('جوال المرسل', 'yamama-shipping'),
            'country'          => __('الدولة', 'yamama-shipping'),
            'city'             => __('المدينة', 'yamama-shipping'),
            'district'         => __('الحي', 'yamama-shipping'),
            'address1'         => __('العنوان 1', 'yamama-shipping'),
            'address2'         => __('العنوان 2', 'yamama-shipping'),
            'national_address' => __('العنوان الوطني', 'yamama-shipping'),
        ];

        foreach ($shipper_fields as $key => $label) {
            add_settings_field(
                'shipper_' . $key,
                $label,
                function () use ($key) {
                    $shipper = self::get_shipper_defaults();
                    $value   = isset($shipper[$key]) ? esc_attr($shipper[$key]) : '';
                    echo '<input type="text" name="' . esc_attr(self::SHIPPER_OPTION) . '[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" />';
                },
                'yamama-shipping-settings',
                'yamama_shipper_section'
            );
        }

        add_settings_section(
            'yamama_connection_section',
            __('حالة الاتصال', 'yamama-shipping'),
            function () {
                $debug  = Yamama_Shipping_Client::get_registration_debug();
                $status = !empty($debug['registered']) ? __('متصل', 'yamama-shipping') : __('غير متصل', 'yamama-shipping');
                echo '<table class="form-table"><tbody>';
                echo '<tr><th>' . esc_html__('الحالة', 'yamama-shipping') . '</th><td><strong>' . esc_html($status) . '</strong></td></tr>';
                echo '<tr><th>Store UUID</th><td><code>' . esc_html($debug['store_uuid']) . '</code></td></tr>';
                echo '<tr><th>' . esc_html__('آخر تسجيل', 'yamama-shipping') . '</th><td>' . esc_html($debug['last_registration_at'] ?: 'N/A') . '</td></tr>';
                echo '<tr><th>' . esc_html__('آخر خطأ', 'yamama-shipping') . '</th><td>' . esc_html($debug['last_registration_error'] ?: __('لا يوجد', 'yamama-shipping')) . '</td></tr>';
                echo '</tbody></table>';
            },
            'yamama-shipping-settings'
        );
    }

    public static function sanitize_shipper($input)
    {
        if (!is_array($input)) {
            return [];
        }

        return [
            'name'             => sanitize_text_field($input['name'] ?? ''),
            'phone'            => sanitize_text_field($input['phone'] ?? ''),
            'country'          => sanitize_text_field($input['country'] ?? 'SA'),
            'city'             => sanitize_text_field($input['city'] ?? ''),
            'district'         => sanitize_text_field($input['district'] ?? ''),
            'address1'         => sanitize_text_field($input['address1'] ?? ''),
            'address2'         => sanitize_text_field($input['address2'] ?? ''),
            'national_address' => sanitize_text_field($input['national_address'] ?? ''),
        ];
    }

    public static function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Access denied');
        }

        $settings = Yamama_Shipping_Client::get_settings();
        $has_credentials = Yamama_Shipping_Client::credentials_exist();
        $debug = Yamama_Shipping_Client::get_registration_debug();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('إعدادات الشحن', 'yamama-shipping'); ?></h1>

            <!-- Connection Status -->
            <div class="yamama-connection-card" style="background:#fff;border:1px solid #c3c4c7;border-right:4px solid <?php echo $has_credentials ? '#00a32a' : '#d63638'; ?>;padding:16px 20px;margin:20px 0;max-width:800px;">
                <h2 style="margin:0 0 10px;font-size:15px;">
                    <?php esc_html_e('حالة الاتصال بالمنصة', 'yamama-shipping'); ?>
                </h2>
                <table style="border-collapse:collapse;">
                    <tr>
                        <td style="padding:4px 16px 4px 0;font-weight:600;"><?php esc_html_e('الحالة:', 'yamama-shipping'); ?></td>
                        <td>
                            <?php if ($has_credentials) : ?>
                                <span style="color:#00a32a;font-weight:600;">&#10003; <?php esc_html_e('متصل', 'yamama-shipping'); ?></span>
                            <?php else : ?>
                                <span style="color:#d63638;font-weight:600;">&#10007; <?php esc_html_e('غير متصل', 'yamama-shipping'); ?></span>
                            <?php endif; ?>
                        </td>
                </tr>
                <tr>
                        <td style="padding:4px 16px 4px 0;font-weight:600;">Store UUID:</td>
                        <td><code style="font-size:12px;"><?php echo esc_html($settings['store_uuid']); ?></code></td>
                </tr>
                <tr>
                        <td style="padding:4px 16px 4px 0;font-weight:600;"><?php esc_html_e('عنوان المنصة:', 'yamama-shipping'); ?></td>
                        <td><code style="font-size:12px;"><?php echo esc_html($settings['middleware_base_url']); ?></code></td>
                </tr>
                <tr>
                        <td style="padding:4px 16px 4px 0;font-weight:600;"><?php esc_html_e('بوابة الدفع:', 'yamama-shipping'); ?></td>
                        <td>
                            <?php
                            $tap_config = self::get_tap_config();
                            if (!empty($tap_config['secret_key']) && !empty($tap_config['merchant_id'])) :
                            ?>
                                <span style="color:#00a32a;">&#10003;</span> Tap <?php echo $tap_config['test_mode'] ? '<small style="color:#d63638;">(Test)</small>' : '<small style="color:#00a32a;">(Live)</small>'; ?>
                            <?php else : ?>
                                <span style="color:#d63638;">&#10007; <?php esc_html_e('Tap غير مُعدّ', 'yamama-shipping'); ?></span>
                            <?php endif; ?>
                        </td>
                </tr>
                    <?php if (!empty($debug['last_registration_error'])) : ?>
                <tr>
                        <td style="padding:4px 16px 4px 0;font-weight:600;color:#d63638;"><?php esc_html_e('آخر خطأ:', 'yamama-shipping'); ?></td>
                        <td style="color:#d63638;"><?php echo esc_html($debug['last_registration_error']); ?></td>
                </tr>
                    <?php endif; ?>
            </table>
                <p style="margin:12px 0 0;">
                    <button type="button" id="yamama-reregister-btn" class="button button-secondary">
                        <?php esc_html_e('إعادة التسجيل في المنصة', 'yamama-shipping'); ?>
                    </button>
                    <span id="yamama-reregister-status" style="margin-right:10px;"></span>
            </p>
        </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('yamama_shipping_settings');
                do_settings_sections('yamama-shipping-settings');
                submit_button(__('حفظ الإعدادات', 'yamama-shipping'));
                ?>
            </form>
        </div>

        <script>
        jQuery(function($) {
            $('#yamama-reregister-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#yamama-reregister-status');

                $btn.prop('disabled', true).text('<?php echo esc_js(__('جاري إعادة التسجيل...', 'yamama-shipping')); ?>');
                $status.html('');

                $.post(ajaxurl, {
                    action: 'yamama_force_reregister',
                    nonce: '<?php echo esc_js(wp_create_nonce('yamama_shipping_nonce')); ?>'
                }, function(res) {
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('إعادة التسجيل في المنصة', 'yamama-shipping')); ?>');
                    if (res.success) {
                        var keyInfo = '';
                        $status.html('<span style="color:#00a32a;font-weight:600;">&#10003; ' + res.data.message + keyInfo + '</span>');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $status.html('<span style="color:#d63638;">&#10007; ' + (res.data && res.data.message ? res.data.message : 'فشل') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('إعادة التسجيل في المنصة', 'yamama-shipping')); ?>');
                    $status.html('<span style="color:#d63638;">&#10007; خطأ في الاتصال</span>');
                });
            });
        });
        </script>
        <?php
    }

    /* ──────────────────────────────────────────────
     *  Orders Page
     * ────────────────────────────────────────────── */

    public static function render_orders_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Access denied');
        }

        if (isset($_GET['order_id']) && absint($_GET['order_id']) > 0) {
            self::render_order_detail_page(absint($_GET['order_id']));
            return;
        }

        self::render_orders_list_page();
    }

    private static function render_orders_list_page()
    {
        $per_page    = 20;
        $current_page = max(1, absint($_GET['paged'] ?? 1));
        $filter       = sanitize_text_field($_GET['shipment_filter'] ?? 'all');
        $search       = sanitize_text_field($_GET['s'] ?? '');

        $args = [
            'limit'   => $per_page,
            'page'    => $current_page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => ['wc-processing', 'wc-on-hold', 'wc-completed', 'wc-shipped', 'wc-returned', 'wc-pending', 'wc-failed'],
            'paginate' => true,
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        $results    = wc_get_orders($args);
        $orders     = $results->orders;
        $total      = $results->total;
        $total_pages = $results->max_num_pages;

        if ($filter !== 'all') {
            $orders = array_filter($orders, function ($order) use ($filter) {
                $lamha_id = (string) $order->get_meta('_yamama_lamha_order_id', true);
                if ($filter === 'shipped') {
                    return $lamha_id !== '';
                }
                if ($filter === 'not_shipped') {
                    return $lamha_id === '';
                }
                return true;
            });
        }

        $status_labels = self::get_lamha_status_labels();
        $base_url = admin_url('admin.php?page=yamama-shipping');
        ?>
        <div class="wrap yamama-orders-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('طلبات الشحن', 'yamama-shipping'); ?></h1>
            <hr class="wp-header-end">

            <!-- Filters -->
            <div class="yamama-orders-filters">
                <ul class="subsubsub">
                    <li>
                        <a href="<?php echo esc_url($base_url); ?>" class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
                            <?php esc_html_e('الكل', 'yamama-shipping'); ?>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('shipment_filter', 'shipped', $base_url)); ?>" class="<?php echo $filter === 'shipped' ? 'current' : ''; ?>">
                            <?php esc_html_e('تم الشحن', 'yamama-shipping'); ?>
                        </a> |
                    </li>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('shipment_filter', 'not_shipped', $base_url)); ?>" class="<?php echo $filter === 'not_shipped' ? 'current' : ''; ?>">
                            <?php esc_html_e('لم يُشحن', 'yamama-shipping'); ?>
                        </a>
                    </li>
                </ul>

                <form method="get" class="yamama-search-form">
                    <input type="hidden" name="page" value="yamama-shipping" />
                    <?php if ($filter !== 'all') : ?>
                        <input type="hidden" name="shipment_filter" value="<?php echo esc_attr($filter); ?>" />
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('بحث برقم الطلب أو اسم العميل...', 'yamama-shipping'); ?>" />
                    <button type="submit" class="button"><?php esc_html_e('بحث', 'yamama-shipping'); ?></button>
                </form>
            </div>

            <!-- Orders Table -->
            <table class="wp-list-table widefat fixed striped yamama-orders-table">
                <thead>
                    <tr>
                        <th class="column-order"><?php esc_html_e('الطلب', 'yamama-shipping'); ?></th>
                        <th class="column-date"><?php esc_html_e('التاريخ', 'yamama-shipping'); ?></th>
                        <th class="column-customer"><?php esc_html_e('العميل', 'yamama-shipping'); ?></th>
                        <th class="column-total"><?php esc_html_e('الإجمالي', 'yamama-shipping'); ?></th>
                        <th class="column-wc-status"><?php esc_html_e('حالة الطلب', 'yamama-shipping'); ?></th>
                        <th class="column-shipment"><?php esc_html_e('حالة الشحنة', 'yamama-shipping'); ?></th>
                        <th class="column-tracking"><?php esc_html_e('التتبع', 'yamama-shipping'); ?></th>
                        <th class="column-actions"><?php esc_html_e('إجراءات', 'yamama-shipping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('لا توجد طلبات.', 'yamama-shipping'); ?></td>
                        </tr>
                <?php else : ?>
                        <?php foreach ($orders as $order) :
                            $order_id        = $order->get_id();
                            $lamha_order_id  = (string) $order->get_meta('_yamama_lamha_order_id', true);
                            $tracking_number = (string) $order->get_meta('_yamama_tracking_number', true);
                            $tracking_link   = (string) $order->get_meta('_yamama_tracking_link', true);
                            $shipment_status = (string) $order->get_meta('_yamama_shipment_status', true);
                            $status_name     = (string) $order->get_meta('_yamama_shipment_status_name', true);
                            $shipping_cost   = (string) $order->get_meta('_yamama_shipping_cost', true);
                            $label_url       = (string) $order->get_meta('_yamama_label_url', true);
                            $is_shipped      = ($lamha_order_id !== '');

                            $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                            if ($customer_name === '') {
                                $customer_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
                            }
                            if ($customer_name === '') {
                                $customer_name = __('زائر', 'yamama-shipping');
                            }

                            $order_date = $order->get_date_created();
                            $date_str   = $order_date ? $order_date->date_i18n('Y-m-d H:i') : '-';

                            $wc_status = wc_get_order_status_name($order->get_status());

                            $detail_url = add_query_arg(['page' => 'yamama-shipping', 'order_id' => $order_id], admin_url('admin.php'));

                            $status_class = $is_shipped ? 'yamama-status-' . $shipment_status : '';
                        ?>
                        <tr>
                            <td class="column-order">
                                <a href="<?php echo esc_url($detail_url); ?>" class="yamama-order-link">
                                    <strong>#<?php echo esc_html($order->get_order_number()); ?></strong>
                                </a>
                            </td>
                            <td class="column-date"><?php echo esc_html($date_str); ?></td>
                            <td class="column-customer">
                                <?php echo esc_html($customer_name); ?>
                                <br><small><?php echo esc_html($order->get_billing_phone()); ?></small>
                            </td>
                            <td class="column-total">
                                <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                            </td>
                            <td class="column-wc-status">
                                <mark class="order-status status-<?php echo esc_attr($order->get_status()); ?>">
                                    <span><?php echo esc_html($wc_status); ?></span>
                                </mark>
                            </td>
                            <td class="column-shipment">
                                <?php if ($is_shipped) : ?>
                                    <span class="yamama-badge yamama-badge-shipped <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_name ?: __('تم الإرسال', 'yamama-shipping')); ?>
                                    </span>
                                    <?php if ($shipping_cost !== '') : ?>
                                        <br><small><?php echo esc_html($shipping_cost); ?> SAR</small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="yamama-badge yamama-badge-pending"><?php esc_html_e('لم يُشحن', 'yamama-shipping'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-tracking">
                                <?php if ($tracking_number !== '') : ?>
                                    <code><?php echo esc_html($tracking_number); ?></code>
                                    <?php if ($tracking_link !== '') : ?>
                                        <br><a href="<?php echo esc_url($tracking_link); ?>" target="_blank" rel="noopener" class="yamama-track-link"><?php esc_html_e('تتبع', 'yamama-shipping'); ?> &rarr;</a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="yamama-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if (!$is_shipped) : ?>
                                    <a href="<?php echo esc_url($detail_url); ?>" class="button button-primary button-small"><?php esc_html_e('شحن', 'yamama-shipping'); ?></a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($detail_url); ?>" class="button button-small"><?php esc_html_e('عرض', 'yamama-shipping'); ?></a>
                                    <button type="button" class="button button-small yamama-fetch-label" data-order-id="<?php echo esc_attr($order->get_id()); ?>" title="<?php esc_attr_e('بوليصة الشحن', 'yamama-shipping'); ?>">PDF</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(esc_html__('%s طلب', 'yamama-shipping'), number_format_i18n($total)); ?>
                    </span>
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_page,
                    ]);
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_order_detail_page($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('الطلب غير موجود.', 'yamama-shipping') . '</p></div></div>';
            return;
        }

        $lamha_order_id   = (string) $order->get_meta('_yamama_lamha_order_id', true);
        $is_shipped       = ($lamha_order_id !== '');
        $customer_name    = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $wc_status        = wc_get_order_status_name($order->get_status());
        $order_edit_url   = self::get_order_edit_url($order_id);
        $back_url         = admin_url('admin.php?page=yamama-shipping');
        ?>
        <div class="wrap yamama-order-detail-wrap">
            <h1 class="wp-heading-inline">
                <?php printf(esc_html__('طلب #%s', 'yamama-shipping'), esc_html($order->get_order_number())); ?>
                <span class="yamama-detail-status"><?php echo esc_html($wc_status); ?></span>
            </h1>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action"><?php esc_html_e('&rarr; العودة للطلبات', 'yamama-shipping'); ?></a>
            <a href="<?php echo esc_url($order_edit_url); ?>" class="page-title-action"><?php esc_html_e('عرض في WooCommerce', 'yamama-shipping'); ?></a>
            <hr class="wp-header-end">

            <!-- Order Summary Card -->
            <div class="yamama-detail-cards">
                <div class="yamama-card">
                    <h3><?php esc_html_e('ملخص الطلب', 'yamama-shipping'); ?></h3>
                    <table class="yamama-info-table">
                        <tr>
                            <th><?php esc_html_e('العميل', 'yamama-shipping'); ?></th>
                            <td><?php echo esc_html($customer_name); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('الجوال', 'yamama-shipping'); ?></th>
                            <td><?php echo esc_html($order->get_billing_phone()); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('البريد', 'yamama-shipping'); ?></th>
                            <td><?php echo esc_html($order->get_billing_email()); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('العنوان', 'yamama-shipping'); ?></th>
                            <td><?php echo wp_kses_post($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address()); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('الإجمالي', 'yamama-shipping'); ?></th>
                            <td><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('طريقة الدفع', 'yamama-shipping'); ?></th>
                            <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('المنتجات', 'yamama-shipping'); ?></th>
                            <td>
                                <?php foreach ($order->get_items() as $item) : ?>
                                    <?php echo esc_html($item->get_name()); ?> &times; <?php echo esc_html($item->get_quantity()); ?><br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if ($is_shipped) : ?>
                <div class="yamama-card">
                    <h3><?php esc_html_e('بيانات الشحنة', 'yamama-shipping'); ?></h3>
                    <?php
                    self::render_metabox_status($order, [
                        'lamha_order_id'  => $lamha_order_id,
                        'tracking_number' => (string) $order->get_meta('_yamama_tracking_number', true),
                        'tracking_link'   => (string) $order->get_meta('_yamama_tracking_link', true),
                        'shipment_status' => (string) $order->get_meta('_yamama_shipment_status', true),
                        'status_name'     => (string) $order->get_meta('_yamama_shipment_status_name', true),
                        'label_url'       => (string) $order->get_meta('_yamama_label_url', true),
                        'carrier_id'      => (string) $order->get_meta('_yamama_carrier_id', true),
                        'tap_charge' => (string) $order->get_meta('_yamama_tap_charge_id', true),
                        'shipping_cost'   => (string) $order->get_meta('_yamama_shipping_cost', true),
                    ]);
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Shipment Form -->
            <?php if (!$is_shipped) : ?>
            <div class="yamama-card yamama-card-full">
                <h3><?php esc_html_e('إنشاء شحنة', 'yamama-shipping'); ?></h3>
                <?php self::render_metabox_form($order); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function get_lamha_status_labels()
    {
        return [
            '0'  => __('جديد', 'yamama-shipping'),
            '1'  => __('معلق', 'yamama-shipping'),
            '2'  => __('تم التنفيذ', 'yamama-shipping'),
            '3'  => __('جاهز للالتقاط', 'yamama-shipping'),
            '4'  => __('شحنة عكسية', 'yamama-shipping'),
            '5'  => __('ملغي', 'yamama-shipping'),
            '6'  => __('تم الالتقاط', 'yamama-shipping'),
            '7'  => __('جاري الشحن', 'yamama-shipping'),
            '8'  => __('تم التوصيل', 'yamama-shipping'),
            '9'  => __('فشل التوصيل', 'yamama-shipping'),
            '10' => __('مرتجع', 'yamama-shipping'),
        ];
    }

    /* ──────────────────────────────────────────────
     *  Assets
     * ────────────────────────────────────────────── */

    public static function enqueue_assets($hook)
    {
        $current_page    = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $is_orders_page  = ($current_page === 'yamama-shipping');
        $is_our_detail   = $is_orders_page && isset($_GET['order_id']);
        $order_metabox_screens = ['post.php', 'post-new.php', 'woocommerce_page_wc-orders'];
        $is_order_metabox = false;

        if (in_array($hook, $order_metabox_screens, true)) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)) {
                $is_order_metabox = true;
            }
        }

        if (!$is_orders_page && !$is_order_metabox) {
            return;
        }

        $needs_detail = ($is_our_detail || $is_order_metabox);

        wp_enqueue_style(
            'yamama-admin-shipment',
            YAMAMA_SHIPPING_URL . 'assets/css/admin-shipment.css',
            [],
            YAMAMA_SHIPPING_VERSION
        );

        wp_enqueue_script(
            'yamama-admin-shipment',
            YAMAMA_SHIPPING_URL . 'assets/js/admin-shipment.js',
            ['jquery'],
            YAMAMA_SHIPPING_VERSION,
            true
        );

        $localize_data = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('yamama_shipping_nonce'),
        ];

        if ($needs_detail) {
            $order_id = 0;
            if (isset($_GET['order_id'])) {
                $order_id = absint($_GET['order_id']);
            } elseif (isset($_GET['post'])) {
                $order_id = absint($_GET['post']);
            } elseif (isset($_GET['id'])) {
                $order_id = absint($_GET['id']);
            }

            $localize_data['orderId']   = $order_id;
            $localize_data['returnTo']  = $is_our_detail ? 'yamama-orders' : '';
        }

        wp_localize_script('yamama-admin-shipment', 'yamamaShipping', $localize_data);

        if (!$needs_detail) {
            return;
        }
    }

    /* ──────────────────────────────────────────────
     *  Order Metabox
     * ────────────────────────────────────────────── */

    public static function register_metabox()
    {
        $screens = ['shop_order'];

        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $screens[] = wc_get_page_screen_id('shop-order');
            }
        }

        foreach ($screens as $screen) {
            add_meta_box(
                'yamama-shipping-metabox',
                __('Yamama Shipping', 'yamama-shipping'),
                [self::class, 'render_metabox'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public static function render_metabox($post_or_order)
    {
        if ($post_or_order instanceof WP_Post) {
            $order = wc_get_order($post_or_order->ID);
        } elseif (is_a($post_or_order, 'WC_Order')) {
            $order = $post_or_order;
        } else {
            $order = wc_get_order($post_or_order);
        }

        if (!$order) {
            echo '<p>' . esc_html__('Order not found.', 'yamama-shipping') . '</p>';
            return;
        }

        $lamha_order_id   = (string) $order->get_meta('_yamama_lamha_order_id', true);
        $tracking_number  = (string) $order->get_meta('_yamama_tracking_number', true);
        $tracking_link    = (string) $order->get_meta('_yamama_tracking_link', true);
        $shipment_status  = (string) $order->get_meta('_yamama_shipment_status', true);
        $status_name      = (string) $order->get_meta('_yamama_shipment_status_name', true);
        $label_url        = (string) $order->get_meta('_yamama_label_url', true);
        $carrier_id       = (string) $order->get_meta('_yamama_carrier_id', true);
        $tap_charge = (string) $order->get_meta('_yamama_tap_charge_id', true);
        $shipping_cost    = (string) $order->get_meta('_yamama_shipping_cost', true);

        if ($lamha_order_id !== '') {
            self::render_metabox_status($order, [
                'lamha_order_id'  => $lamha_order_id,
                'tracking_number' => $tracking_number,
                'tracking_link'   => $tracking_link,
                'shipment_status' => $shipment_status,
                'status_name'     => $status_name,
                'label_url'       => $label_url,
                'carrier_id'      => $carrier_id,
                'tap_charge' => $tap_charge,
                'shipping_cost'   => $shipping_cost,
            ]);
            return;
        }

        self::render_metabox_form($order);
    }

    private static function render_metabox_status($order, $data)
    {
        ?>
        <div class="yamama-shipment-status">
            <table class="yamama-info-table">
                <tr>
                    <th><?php esc_html_e('Yamama Order ID', 'yamama-shipping'); ?></th>
                    <td><code><?php echo esc_html($data['lamha_order_id']); ?></code></td>
                </tr>
                <?php if ($data['tracking_number'] !== '') : ?>
                <tr>
                    <th><?php esc_html_e('رقم التتبع', 'yamama-shipping'); ?></th>
                    <td>
                        <code><?php echo esc_html($data['tracking_number']); ?></code>
                        <?php if ($data['tracking_link'] !== '') : ?>
                            &nbsp;<a href="<?php echo esc_url($data['tracking_link']); ?>" target="_blank" rel="noopener"><?php esc_html_e('تتبع', 'yamama-shipping'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($data['status_name'] !== '') : ?>
                <tr>
                    <th><?php esc_html_e('حالة الشحنة', 'yamama-shipping'); ?></th>
                    <td><?php echo esc_html($data['status_name']); ?> <small>(<?php echo esc_html($data['shipment_status']); ?>)</small></td>
                </tr>
                <?php endif; ?>
                <?php if ($data['shipping_cost'] !== '') : ?>
                <tr>
                    <th><?php esc_html_e('تكلفة الشحن', 'yamama-shipping'); ?></th>
                    <td><?php echo esc_html($data['shipping_cost']); ?> SAR</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('بوليصة الشحن', 'yamama-shipping'); ?></th>
                    <td>
                        <button type="button" class="button button-small yamama-fetch-label" data-order-id="<?php echo esc_attr($order->get_id()); ?>"><?php echo esc_html($data['label_url'] !== '' ? __('تحميل PDF', 'yamama-shipping') : __('جلب البوليصة', 'yamama-shipping')); ?></button>
                        <span class="yamama-label-status"></span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private static function render_metabox_form($order)
    {
        $shipper = self::get_shipper_defaults();
        $order_id = (int) $order->get_id();

        $customer_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        if ($customer_name === '') {
            $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        }

        $customer_phone = $order->get_billing_phone();
        $customer_email = $order->get_billing_email();

        $customer_country = $order->get_shipping_country();
        if ($customer_country === '') {
            $customer_country = $order->get_billing_country();
        }
        if ($customer_country === '') {
            $customer_country = 'SA';
        }

        $customer_city = $order->get_shipping_city();
        if ($customer_city === '') {
            $customer_city = $order->get_billing_city();
        }

        $customer_address1 = $order->get_shipping_address_1();
        if ($customer_address1 === '') {
            $customer_address1 = $order->get_billing_address_1();
        }

        $customer_address2 = $order->get_shipping_address_2();
        if ($customer_address2 === '') {
            $customer_address2 = $order->get_billing_address_2();
        }

        $wc_payment_method = (string) $order->get_payment_method();
        $lamha_payment     = Yamama_Shipping_Hooks::map_wc_payment_method($wc_payment_method);

        $order_date = $order->get_date_created();
        $date_str   = $order_date ? $order_date->date('Y-m-d H:i:s') : current_time('Y-m-d H:i:s');

        $items_data = [];
        foreach ($order->get_items() as $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }
            $product = $item->get_product();
            $qty     = max(1, (int) $item->get_quantity());
            $weight  = $product ? (float) $product->get_weight() : 0.5;
            if ($weight <= 0) {
                $weight = 0.5;
            }
            $sku    = $product ? (string) $product->get_sku() : '';
            $amount = (float) $item->get_total() / $qty;

            $items_data[] = [
                'name'     => (string) $item->get_name(),
                'quantity' => $qty,
                'Sku'      => $sku,
                'amount'   => number_format($amount, 2, '.', ''),
                'weight'   => number_format($weight, 2, '.', ''),
            ];
        }
        ?>
        <div id="yamama-shipment-form" data-order-id="<?php echo esc_attr($order_id); ?>">
            <!-- Customer Info -->
            <div class="yamama-section">
                <h4><?php esc_html_e('بيانات العميل', 'yamama-shipping'); ?></h4>
                <div class="yamama-form-grid">
                    <label><?php esc_html_e('الاسم', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="customer_name" value="<?php echo esc_attr($customer_name); ?>" />
                    </label>
                    <label><?php esc_html_e('الجوال', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="customer_phone1" value="<?php echo esc_attr($customer_phone); ?>" />
                    </label>
                    <label><?php esc_html_e('جوال 2', 'yamama-shipping'); ?>
                        <input type="text" name="customer_phone2" value="" />
                    </label>
                    <label><?php esc_html_e('البريد', 'yamama-shipping'); ?>
                        <input type="email" name="customer_email" value="<?php echo esc_attr($customer_email); ?>" />
                    </label>
                    <label><?php esc_html_e('الدولة', 'yamama-shipping'); ?>
                        <input type="text" name="customer_country" value="<?php echo esc_attr($customer_country); ?>" />
                    </label>
                    <label><?php esc_html_e('المدينة', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="customer_city" value="<?php echo esc_attr($customer_city); ?>" />
                    </label>
                    <label><?php esc_html_e('الحي', 'yamama-shipping'); ?>
                        <input type="text" name="customer_district" value="" />
                    </label>
                    <label><?php esc_html_e('العنوان 1', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="customer_address1" value="<?php echo esc_attr($customer_address1); ?>" />
                    </label>
                    <label><?php esc_html_e('العنوان 2', 'yamama-shipping'); ?>
                        <input type="text" name="customer_address2" value="<?php echo esc_attr($customer_address2); ?>" />
                    </label>
                    <label><?php esc_html_e('العنوان الوطني', 'yamama-shipping'); ?>
                        <input type="text" name="customer_national_address" value="" />
                    </label>
                </div>
            </div>

            <!-- Shipper Info -->
            <div class="yamama-section">
                <h4><?php esc_html_e('بيانات المرسل', 'yamama-shipping'); ?></h4>
                <div class="yamama-form-grid">
                    <label><?php esc_html_e('الاسم', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="shipper_name" value="<?php echo esc_attr($shipper['name']); ?>" />
                    </label>
                    <label><?php esc_html_e('الجوال', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="shipper_phone" value="<?php echo esc_attr($shipper['phone']); ?>" />
                    </label>
                    <label><?php esc_html_e('الدولة', 'yamama-shipping'); ?>
                        <input type="text" name="shipper_country" value="<?php echo esc_attr($shipper['country']); ?>" />
                    </label>
                    <label><?php esc_html_e('المدينة', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="shipper_city" value="<?php echo esc_attr($shipper['city']); ?>" />
                    </label>
                    <label><?php esc_html_e('الحي', 'yamama-shipping'); ?>
                        <input type="text" name="shipper_district" value="<?php echo esc_attr($shipper['district']); ?>" />
                    </label>
                    <label><?php esc_html_e('العنوان 1', 'yamama-shipping'); ?> <span class="yamama-required">*</span>
                        <input type="text" name="shipper_address1" value="<?php echo esc_attr($shipper['address1']); ?>" />
                    </label>
                    <label><?php esc_html_e('العنوان 2', 'yamama-shipping'); ?>
                        <input type="text" name="shipper_address2" value="<?php echo esc_attr($shipper['address2']); ?>" />
                    </label>
                    <label><?php esc_html_e('العنوان الوطني', 'yamama-shipping'); ?>
                        <input type="text" name="shipper_national_address" value="<?php echo esc_attr($shipper['national_address']); ?>" />
                    </label>
                </div>
            </div>

            <!-- Order Details -->
            <div class="yamama-section">
                <h4><?php esc_html_e('تفاصيل الطلب', 'yamama-shipping'); ?></h4>
                <div class="yamama-form-grid">
                    <label><?php esc_html_e('الإجمالي', 'yamama-shipping'); ?>
                        <input type="number" step="0.01" name="total" value="<?php echo esc_attr($order->get_total()); ?>" />
                    </label>
                    <label><?php esc_html_e('المجموع الفرعي', 'yamama-shipping'); ?>
                        <input type="number" step="0.01" name="sub_total" value="<?php echo esc_attr($order->get_subtotal()); ?>" />
                    </label>
                    <label><?php esc_html_e('الخصم', 'yamama-shipping'); ?>
                        <input type="number" step="0.01" name="discount" value="<?php echo esc_attr($order->get_discount_total()); ?>" />
                    </label>
                    <label><?php esc_html_e('تكلفة الشحن', 'yamama-shipping'); ?>
                        <input type="number" step="0.01" name="shopping_cost" value="<?php echo esc_attr($order->get_shipping_total()); ?>" />
                    </label>
                    <label><?php esc_html_e('طريقة الدفع', 'yamama-shipping'); ?>
                        <select name="payment_method">
                            <option value="cod" <?php selected($lamha_payment, 'cod'); ?>>COD</option>
                            <option value="paid" <?php selected($lamha_payment, 'paid'); ?>>Paid (Tap)</option>
                            <option value="bank" <?php selected($lamha_payment, 'bank'); ?>>Bank</option>
                            <option value="mada" <?php selected($lamha_payment, 'mada'); ?>>Mada</option>
                            <option value="credit_card" <?php selected($lamha_payment, 'credit_card'); ?>>Credit Card</option>
                            <option value="tamara_installment" <?php selected($lamha_payment, 'tamara_installment'); ?>>Tamara</option>
                            <option value="stripe" <?php selected($lamha_payment, 'stripe'); ?>>Stripe</option>
                            <option value="tabby" <?php selected($lamha_payment, 'tabby'); ?>>Tabby</option>
                            <option value="stc_pay" <?php selected($lamha_payment, 'stc_pay'); ?>>STC Pay</option>
                        </select>
                    </label>
                    <label><?php esc_html_e('التاريخ', 'yamama-shipping'); ?>
                        <input type="text" name="date" value="<?php echo esc_attr($date_str); ?>" />
                    </label>
                    <label><?php esc_html_e('العملة', 'yamama-shipping'); ?>
                        <input type="text" name="currency" value="<?php echo esc_attr($order->get_currency() ?: 'SAR'); ?>" />
                    </label>
                    <input type="hidden" name="reference_id" value="<?php echo esc_attr($order->get_order_number()); ?>" />
                    <input type="hidden" name="wc_order_id" value="<?php echo esc_attr($order_id); ?>" />
                </div>
            </div>

            <!-- Items -->
            <div class="yamama-section">
                <h4><?php esc_html_e('المنتجات', 'yamama-shipping'); ?></h4>
                <table class="yamama-items-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('المنتج', 'yamama-shipping'); ?></th>
                            <th><?php esc_html_e('الكمية', 'yamama-shipping'); ?></th>
                            <th><?php esc_html_e('SKU', 'yamama-shipping'); ?></th>
                            <th><?php esc_html_e('السعر', 'yamama-shipping'); ?></th>
                            <th><?php esc_html_e('الوزن (كجم)', 'yamama-shipping'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_data as $i => $item) : ?>
                        <tr>
                            <td><input type="text" name="items[<?php echo $i; ?>][name]" value="<?php echo esc_attr($item['name']); ?>" /></td>
                            <td><input type="number" name="items[<?php echo $i; ?>][quantity]" value="<?php echo esc_attr($item['quantity']); ?>" min="1" style="width:60px;" /></td>
                            <td><input type="text" name="items[<?php echo $i; ?>][Sku]" value="<?php echo esc_attr($item['Sku']); ?>" style="width:80px;" /></td>
                            <td><input type="text" name="items[<?php echo $i; ?>][amount]" value="<?php echo esc_attr($item['amount']); ?>" style="width:80px;" /></td>
                            <td><input type="text" name="items[<?php echo $i; ?>][weight]" value="<?php echo esc_attr($item['weight']); ?>" style="width:80px;" /></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Shipping Options -->
            <div class="yamama-section">
                <h4><?php esc_html_e('خيارات الشحن', 'yamama-shipping'); ?></h4>
                <input type="hidden" name="carrier_id" id="yamama-carrier-input" value="" />
                <div id="yamama-carriers-container" class="yamama-carriers-grid">
                    <div class="yamama-carriers-loading">
                        <span class="spinner is-active" style="float:none;margin:0 0 0 8px;"></span>
                        <?php esc_html_e('جاري تحميل شركات الشحن...', 'yamama-shipping'); ?>
                    </div>
                </div>
                <div id="yamama-carrier-warning" class="yamama-carrier-warning notice notice-warning inline" style="display:none;margin:12px 0;">
                    <p></p>
                </div>
                <div class="yamama-form-grid" style="margin-top:12px;">
                    <label><?php esc_html_e('عدد الطرود', 'yamama-shipping'); ?>
                        <input type="number" name="parcels" value="1" min="1" />
                    </label>
                </div>
            </div>

            <!-- Quote -->
            <div class="yamama-section" id="yamama-quote-section" style="display:none;">
                <h4><?php esc_html_e('تكلفة الشحن', 'yamama-shipping'); ?></h4>
                <p class="yamama-quote-display">
                    <?php esc_html_e('التكلفة:', 'yamama-shipping'); ?>
                    <strong id="yamama-quote-cost">-</strong> SAR
                </p>
                <div id="yamama-wallet-balance-row" style="display:none;margin:12px 0;">
                    <span><?php esc_html_e('رصيد المحفظة:', 'yamama-shipping'); ?></span>
                    <strong id="yamama-wallet-balance">-</strong> SAR
                </div>
                <div id="yamama-payment-choice" style="display:none;margin-top:12px;">
                    <p style="margin:0 0 8px;font-weight:600;"><?php esc_html_e('طريقة الدفع', 'yamama-shipping'); ?></p>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="radio" name="yamama_payment_source" value="tap" checked />
                        <?php esc_html_e('الدفع عبر Tap', 'yamama-shipping'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="yamama_payment_source" value="wallet" id="yamama-pay-wallet-radio" />
                        <?php esc_html_e('الدفع من الرصيد', 'yamama-shipping'); ?>
                        <span id="yamama-wallet-insufficient" class="description" style="display:none;color:#b32d2e;"> — <?php esc_html_e('الرصيد غير كافٍ', 'yamama-shipping'); ?></span>
                    </label>
                </div>
            </div>

            <!-- Actions -->
            <div class="yamama-actions">
                <button type="button" id="yamama-pay-ship" class="button button-primary" style="display:none;">
                    <?php esc_html_e('الدفع وإنشاء الشحنة', 'yamama-shipping'); ?>
                </button>
                <span id="yamama-spinner" class="spinner" style="float:none;"></span>
            </div>

            <!-- Result Messages -->
            <div id="yamama-result" style="display:none;"></div>
        </div>
        <?php
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Get Carriers
     * ────────────────────────────────────────────── */

    public static function ajax_get_carriers()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $result = Yamama_Shipping_Client::request('GET', '/carriers');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $carriers = isset($result['data']) ? $result['data'] : $result;
        wp_send_json_success(['carriers' => $carriers]);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Get Cities
     * ────────────────────────────────────────────── */

    public static function ajax_get_cities()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $country = sanitize_text_field($_POST['country'] ?? 'SA');
        $result  = Yamama_Shipping_Client::request('GET', '/cities/' . rawurlencode($country));

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Get Quote
     * ────────────────────────────────────────────── */

    public static function ajax_get_quote()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $carrier_raw = sanitize_text_field($_POST['carrier_id'] ?? '');
        $payload = [
            'carrier_id'     => is_numeric($carrier_raw) ? intval($carrier_raw) : $carrier_raw,
            'city'           => sanitize_text_field($_POST['city'] ?? ''),
            'weight'         => floatval($_POST['weight'] ?? 1),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'cod'),
        ];

        $result = Yamama_Shipping_Client::request('POST', '/quotes', $payload);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Flatten nested 'data' wrapper if present
        $quote_data = $result;
        if (isset($result['data']) && is_array($result['data'])) {
            $quote_data = $result['data'];
        }

        // Normalize the cost key
        $shipping_cost = 0;
        foreach (['shipping_cost', 'shippingCost', 'cost', 'price'] as $key) {
            if (isset($quote_data[$key]) && is_numeric($quote_data[$key])) {
                $shipping_cost = floatval($quote_data[$key]);
                break;
            }
        }
        $quote_data['shipping_cost'] = $shipping_cost;

        wp_send_json_success($quote_data);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Save Pending Shipment Data
     * ────────────────────────────────────────────── */

    public static function ajax_save_pending()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $order_id  = absint($_POST['order_id'] ?? 0);
        $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : [];

        if ($order_id <= 0 || empty($form_data)) {
            wp_send_json_error(['message' => 'Invalid data.']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
        }

        $sanitized = self::sanitize_form_data($form_data);
        self::save_pending_shipment_data($order, $sanitized);
        $order->save();

        wp_send_json_success(['saved' => true]);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Create Order (after payment)
     * ────────────────────────────────────────────── */

    public static function ajax_create_order()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $order_id   = absint($_POST['order_id'] ?? 0);
        $payment_id = sanitize_text_field($_POST['payment_id'] ?? '');
        $payment_source = sanitize_text_field($_POST['payment_source'] ?? 'tap');

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Missing order_id.']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
        }

        $existing = (string) $order->get_meta('_yamama_lamha_order_id', true);
        if ($existing !== '') {
            wp_send_json_error(['message' => 'Shipment already created for this order.']);
        }

        $form_data = self::get_pending_shipment_data($order);
        if (empty($form_data)) {
            wp_send_json_error(['message' => 'No pending shipment data found. Please try again.']);
        }

        $lamha_payload = self::build_lamha_payload($order, $form_data);
        if ($payment_source === 'wallet') {
            $lamha_payload['payment_source'] = 'wallet';
        } elseif ($payment_id !== '') {
            $lamha_payload['tap_charge_id'] = $payment_id;
        }

        $result = Yamama_Shipping_Client::request('POST', '/create-order', $lamha_payload);

        if (is_wp_error($result)) {
            $order->add_order_note('Yamama: create order failed - ' . $result->get_error_message());
            $order->save();
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            $msg = isset($result['msg']) ? (string) $result['msg'] : 'فشل إنشاء الشحنة في المنصة.';
            $order->add_order_note('Yamama: create order rejected - ' . $msg);
            $order->save();
            wp_send_json_error(['message' => $msg]);
        }

        $lamha_order_id  = '';
        $tracking_number = '';
        $tracking_link   = '';

        if (isset($result['order_id'])) {
            $lamha_order_id = (string) $result['order_id'];
        }
        if (isset($result['number_tracking'])) {
            $tracking_number = (string) $result['number_tracking'];
        }
        if (isset($result['link_tracking'])) {
            $tracking_link = (string) $result['link_tracking'];
        }

        $order->update_meta_data('_yamama_lamha_order_id', $lamha_order_id);
        $order->update_meta_data('_yamama_tracking_number', $tracking_number);
        $order->update_meta_data('_yamama_tracking_link', $tracking_link);
        $order->update_meta_data('_yamama_carrier_id', $form_data['carrier_id'] ?? '');
        if ($payment_id !== '') {
            $order->update_meta_data('_yamama_tap_charge_id', $payment_id);
        }
        if ($payment_source === 'wallet') {
            $order->update_meta_data('_yamama_payment_source', 'wallet');
        }
        $order->update_meta_data('_yamama_shipping_cost', $form_data['quote_cost'] ?? '');
        $order->update_meta_data('_yamama_shipment_status', '0');
        $order->update_meta_data('_yamama_shipment_status_name', 'جديد');
        self::delete_pending_shipment_data($order);

        $note_payment = $payment_source === 'wallet'
            ? 'Wallet'
            : ($payment_id !== '' ? $payment_id : 'N/A (no payment)');
        $order->add_order_note(sprintf('Yamama: shipment created (Lamha #%s). Payment: %s', $lamha_order_id, $note_payment));
            $order->save();

        wp_send_json_success([
            'lamha_order_id'  => $lamha_order_id,
            'tracking_number' => $tracking_number,
            'tracking_link'   => $tracking_link,
        ]);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Complete Tap (after redirect back)
     * ────────────────────────────────────────────── */

    public static function ajax_complete_tap()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $order_id = absint($_POST['order_id'] ?? 0);
        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Missing order_id.']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
        }

        $existing = (string) $order->get_meta('_yamama_lamha_order_id', true);
        if ($existing !== '') {
            wp_send_json_success([
                'lamha_order_id'  => $existing,
                'tracking_number' => (string) $order->get_meta('_yamama_tracking_number', true),
                'tracking_link'   => (string) $order->get_meta('_yamama_tracking_link', true),
            ]);
        }

        $transaction_no = (string) $order->get_meta('_yamama_tap_charge_id', true);
        if ($transaction_no === '') {
            wp_send_json_error(['message' => 'لم يتم العثور على معرف الدفع. يرجى إعادة المحاولة.']);
        }

        $form_data = self::get_pending_shipment_data($order);
        if (empty($form_data)) {
            wp_send_json_error(['message' => 'لم يتم العثور على بيانات الشحنة المعلقة.']);
        }

        $lamha_payload = self::build_lamha_payload($order, $form_data);
        $lamha_payload['tap_charge_id'] = $transaction_no;

        $result = Yamama_Shipping_Client::request('POST', '/create-order', $lamha_payload);

        if (is_wp_error($result)) {
            $order->add_order_note('Yamama: create order failed after Tap - ' . $result->get_error_message());
            $order->save();
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            $msg = isset($result['msg']) ? (string) $result['msg'] : 'فشل إنشاء الشحنة في المنصة.';
            $order->add_order_note('Yamama: create order rejected (Tap) - ' . $msg);
            $order->save();
            wp_send_json_error(['message' => $msg]);
        }

        $lamha_order_id  = isset($result['order_id']) ? (string) $result['order_id'] : '';
        $tracking_number = isset($result['number_tracking']) ? (string) $result['number_tracking'] : '';
        $tracking_link   = isset($result['link_tracking']) ? (string) $result['link_tracking'] : '';

        $order->update_meta_data('_yamama_lamha_order_id', $lamha_order_id);
        $order->update_meta_data('_yamama_tracking_number', $tracking_number);
        $order->update_meta_data('_yamama_tracking_link', $tracking_link);
        $order->update_meta_data('_yamama_carrier_id', $form_data['carrier_id'] ?? '');
        $order->update_meta_data('_yamama_shipping_cost', $form_data['quote_cost'] ?? '');
        $order->update_meta_data('_yamama_shipment_status', '0');
        $order->update_meta_data('_yamama_shipment_status_name', 'جديد');
        self::delete_pending_shipment_data($order);
        $order->add_order_note(sprintf('Yamama: shipment created via Tap (Lamha #%s). Charge: %s', $lamha_order_id, $transaction_no));
        $order->save();

        wp_send_json_success([
            'lamha_order_id'  => $lamha_order_id,
            'tracking_number' => $tracking_number,
            'tracking_link'   => $tracking_link,
        ]);
    }

    /* ──────────────────────────────────────────────
     *  Force Re-registration
     * ────────────────────────────────────────────── */

    public static function ajax_force_reregister()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        if (!Yamama_Shipping_Client::has_registration_secret()) {
            wp_send_json_error([
                'message' => 'لم يتم إعداد اتصال الشحن بعد. يُدار تلقائياً من المنصة — إذا استمرت المشكلة تواصل مع الدعم.',
            ]);
        }

        delete_option(Yamama_Shipping_Client::API_TOKEN_OPTION);
        delete_option(Yamama_Shipping_Client::HMAC_SECRET_OPTION);
        delete_option(Yamama_Shipping_Client::REGISTERED_URL_OPTION);

        $success = Yamama_Shipping_Client::ensure_registered(true);

        if ($success) {
            $settings = Yamama_Shipping_Client::get_settings();
            wp_send_json_success([
                'message'    => 'تم إعادة التسجيل بنجاح.',
                'store_uuid' => $settings['store_uuid'],
            ]);
        } else {
        $debug = Yamama_Shipping_Client::get_registration_debug();
            wp_send_json_error([
                'message' => 'فشل إعادة التسجيل: ' . ($debug['last_registration_error'] ?: 'خطأ غير معروف'),
            ]);
        }
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Fetch Shipping Label from Middleware
     * ────────────────────────────────────────────── */

    public static function ajax_fetch_label()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $order_id = absint($_POST['order_id'] ?? 0);
        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Missing order_id.']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found.']);
        }

        $middleware_base = Yamama_Shipping_Client::get_settings()['middleware_base_url'];
        $existing_label  = (string) $order->get_meta('_yamama_label_url', true);

        if ($existing_label !== '' && strpos($existing_label, $middleware_base) === false) {
            wp_send_json_success([
                'label_url'       => $existing_label,
                'tracking_number' => (string) $order->get_meta('_yamama_tracking_number', true),
            ]);
        }

        $lamha_order_id = (string) $order->get_meta('_yamama_lamha_order_id', true);
        if ($lamha_order_id === '') {
            wp_send_json_error(['message' => 'لا يوجد طلب شحنة لهذا الطلب.']);
        }

        $result = Yamama_Shipping_Client::request('GET', '/label-shipment/' . $lamha_order_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            $msg = isset($result['msg']) ? (string) $result['msg'] : 'البوليصة غير متوفرة حالياً.';
            wp_send_json_error(['message' => $msg]);
        }

        $pdf_base64 = '';
        if (isset($result['pdf_base64']) && (string) $result['pdf_base64'] !== '') {
            $pdf_base64 = (string) $result['pdf_base64'];
        } elseif (isset($result['data']['pdf_base64']) && (string) $result['data']['pdf_base64'] !== '') {
            $pdf_base64 = (string) $result['data']['pdf_base64'];
        }

        $pdf_url = '';
        if ($pdf_base64 === '') {
            if (isset($result['pdf_url'])) {
                $pdf_url = esc_url_raw((string) $result['pdf_url']);
            } elseif (isset($result['data']['pdf_url'])) {
                $pdf_url = esc_url_raw((string) $result['data']['pdf_url']);
            }
            if ($pdf_url !== '' && strpos($pdf_url, $middleware_base) !== false) {
                $pdf_url = '';
            }
        }

        if ($pdf_base64 === '' && $pdf_url === '') {
            wp_send_json_error(['message' => 'البوليصة غير متوفرة حالياً. قد تحتاج بعض الوقت.']);
        }

        if (isset($result['number_tracking']) && (string) $result['number_tracking'] !== '') {
            $order->update_meta_data('_yamama_tracking_number', sanitize_text_field((string) $result['number_tracking']));
        }
        if (isset($result['link_tracking']) && (string) $result['link_tracking'] !== '') {
            $order->update_meta_data('_yamama_tracking_link', esc_url_raw((string) $result['link_tracking']));
        }
        if ($pdf_url !== '') {
            $order->update_meta_data('_yamama_label_url', $pdf_url);
        }

        $order->save();
        $order->add_order_note('Yamama: تم جلب بوليصة الشحن يدوياً.');

        $response = [
            'tracking_number' => (string) $order->get_meta('_yamama_tracking_number', true),
            'tracking_link'   => (string) $order->get_meta('_yamama_tracking_link', true),
        ];

        if ($pdf_base64 !== '') {
            $response['pdf_base64'] = $pdf_base64;
        } else {
            $response['label_url'] = $pdf_url;
        }

        wp_send_json_success($response);
    }

    /* ──────────────────────────────────────────────
     *  Tap Callback (after payment redirect)
     * ────────────────────────────────────────────── */

    public static function handle_tap_callback()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Access denied');
        }

        $wc_order_id = absint($_GET['wc_order_id'] ?? 0);
        $tap_id      = sanitize_text_field($_GET['tap_id'] ?? '');
        $return_to   = sanitize_text_field($_GET['return_to'] ?? '');

        if ($return_to === 'yamama-orders') {
            $redirect_url = admin_url('admin.php?page=yamama-shipping&order_id=' . $wc_order_id);
        } else {
            $redirect_url = self::get_order_edit_url($wc_order_id);
        }

        if ($wc_order_id <= 0) {
            wp_safe_redirect(add_query_arg('yamama_error', 'missing_params', $redirect_url));
            exit;
        }

        $order = wc_get_order($wc_order_id);
        if (!$order) {
            wp_safe_redirect(add_query_arg('yamama_error', 'order_not_found', $redirect_url));
            exit;
        }

        if ($tap_id === '') {
            $tap_id = (string) $order->get_meta('_yamama_tap_charge_id', true);
        }
        if ($tap_id === '') {
            wp_safe_redirect(add_query_arg('yamama_error', 'missing_charge_id', $redirect_url));
            exit;
        }

        $existing = (string) $order->get_meta('_yamama_lamha_order_id', true);
        if ($existing !== '') {
            wp_safe_redirect(add_query_arg('yamama_success', '1', $redirect_url));
            exit;
        }

        $config = self::get_tap_config();
        if (empty($config['secret_key'])) {
            $order->add_order_note('Yamama: Tap secret key is missing during callback.');
            $order->save();
            wp_safe_redirect(add_query_arg('yamama_error', 'tap_not_configured', $redirect_url));
            exit;
        }

        $verify_response = wp_remote_get($config['base_url'] . '/v2/charges/' . rawurlencode($tap_id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['secret_key'],
                'Accept'        => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($verify_response)) {
            $order->add_order_note('Yamama: Tap verify failed - ' . $verify_response->get_error_message());
            $order->save();
            wp_safe_redirect(add_query_arg('yamama_error', 'payment_failed', $redirect_url));
            exit;
        }

        $verify_body    = json_decode(wp_remote_retrieve_body($verify_response), true);
        $payment_status = strtoupper($verify_body['status'] ?? '');

        $order->add_order_note(sprintf('Yamama: Tap callback. charge_id=%s, status=%s', $tap_id, $payment_status ?: '(empty)'));

        if (!in_array($payment_status, ['CAPTURED', 'AUTHORIZED'], true)) {
            $order->add_order_note(sprintf('Yamama: Tap payment not completed. Status: %s', $payment_status));
            $order->save();
            wp_safe_redirect(add_query_arg('yamama_error', 'payment_failed', $redirect_url));
            exit;
        }

        $order->update_meta_data('_yamama_tap_charge_id', $tap_id);
        $order->save();

        $form_data = self::get_pending_shipment_data($order);
        if (empty($form_data)) {
            wp_safe_redirect(add_query_arg('yamama_tap_complete', '1', $redirect_url));
            exit;
        }

        $lamha_payload = self::build_lamha_payload($order, $form_data);
        $lamha_payload['tap_charge_id'] = $tap_id;

        $result = Yamama_Shipping_Client::request('POST', '/create-order', $lamha_payload);

        if (is_wp_error($result)) {
            $order->add_order_note('Yamama: create order failed after Tap - ' . $result->get_error_message());
            $order->save();
            wp_safe_redirect(add_query_arg('yamama_error', 'create_failed', $redirect_url));
            exit;
        }

        if (is_array($result) && isset($result['success']) && $result['success'] === false) {
            $msg = isset($result['msg']) ? (string) $result['msg'] : 'Create order rejected.';
            $order->add_order_note('Yamama: create order rejected after Tap - ' . $msg);
            $order->save();
            wp_safe_redirect(add_query_arg('yamama_error', 'create_failed', $redirect_url));
            exit;
        }

        $lamha_order_id  = isset($result['order_id']) ? (string) $result['order_id'] : '';
        $tracking_number = isset($result['number_tracking']) ? (string) $result['number_tracking'] : '';
        $tracking_link   = isset($result['link_tracking']) ? (string) $result['link_tracking'] : '';

        $order->update_meta_data('_yamama_lamha_order_id', $lamha_order_id);
        $order->update_meta_data('_yamama_tracking_number', $tracking_number);
        $order->update_meta_data('_yamama_tracking_link', $tracking_link);
        $order->update_meta_data('_yamama_carrier_id', $form_data['carrier_id'] ?? '');
        $order->update_meta_data('_yamama_shipping_cost', $form_data['quote_cost'] ?? '');
        $order->update_meta_data('_yamama_shipment_status', '0');
        $order->update_meta_data('_yamama_shipment_status_name', 'جديد');
        self::delete_pending_shipment_data($order);
        $order->add_order_note(sprintf('Yamama: shipment created via Tap (Lamha #%s). Charge: %s', $lamha_order_id, $tap_id));
        $order->save();

        wp_safe_redirect(add_query_arg('yamama_success', '1', $redirect_url));
        exit;
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Create Tap Charge for shipping cost
     * ────────────────────────────────────────────── */

    public static function ajax_create_tap_charge()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $order_id  = absint($_POST['order_id'] ?? 0);
        $amount    = floatval($_POST['amount'] ?? 0);
        $return_to = sanitize_text_field($_POST['return_to'] ?? '');

        if ($order_id <= 0 || $amount <= 0) {
            wp_send_json_error(['message' => 'بيانات غير صالحة.']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'الطلب غير موجود.']);
        }

        $callback_args = [
            'action'      => 'yamama_tap_return',
            'wc_order_id' => $order_id,
        ];
        if ($return_to !== '') {
            $callback_args['return_to'] = $return_to;
        }
        $callback_url = add_query_arg($callback_args, admin_url('admin-post.php'));

        $shipper = self::get_shipper_defaults();
        $settings = Yamama_Shipping_Client::get_settings();
        $webhook_url = preg_replace('#/plugin/?$#', '/webhooks/tap', untrailingslashit((string) ($settings['middleware_base_url'] ?? '')));

        $result = Yamama_Shipping_Client::request('POST', '/charges/shipment', [
            'order_id'     => (string) $order_id,
            'amount'       => $amount,
            'redirect_url' => $callback_url,
            'post_url'     => $webhook_url ?: $callback_url,
            'description'  => sprintf('تكلفة شحن الطلب #%s', $order->get_order_number()),
            'customer'     => [
                'first_name' => $shipper['name'] ?: (get_bloginfo('name') ?: 'Store'),
                'email'      => get_option('admin_email'),
            ],
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $payment_url = $result['data']['paymentUrl'] ?? '';
        $charge_id   = $result['data']['chargeId'] ?? '';

        if ($payment_url === '') {
            $msg = isset($result['message']) ? (string) $result['message'] : 'فشل إنشاء عملية دفع Tap';
            wp_send_json_error(['message' => $msg]);
        }

        if ($charge_id !== '') {
            $order->update_meta_data('_yamama_tap_charge_id', $charge_id);
            $order->save();
        }

        wp_send_json_success(['payment_url' => $payment_url]);
    }

    /* ──────────────────────────────────────────────
     *  Tap Helpers
     * ────────────────────────────────────────────── */

    /**
     * Tap config used to charge SHIPPING fees.
     *
     * Shipping is billed against the central yamama Tap account (the same one
     * that processes platform subscriptions), NOT against the merchant's own
     * sub-merchant gateway. We therefore prefer the IDs cached from
     * `/payment-config` (yamama_shipping_tap_*) and only fall back to the
     * legacy yamama_tap_* options if the middleware hasn't been queried yet.
     *
     * The `secret_key` used here is the same one stored in `yamama_tap_secret_key`
     * because every store in the yamama platform shares the same central
     * Tap account for shipping (pushed to all stores by sync-merchant).
     */
    private static function get_tap_config()
    {
        $merchant_id   = (string) ( get_option('yamama_shipping_tap_merchant_id', '')   ?: get_option('yamama_tap_merchant_id', '') );
        $technology_id = (string) ( get_option('yamama_shipping_tap_technology_id', '') ?: get_option('yamama_tap_technology_id', '') );
        $platform_id   = (string) ( get_option('yamama_shipping_tap_platform_id', '')   ?: get_option('yamama_tap_platform_id', '') );

        return [
            'merchant_id'   => $merchant_id,
            'secret_key'    => get_option('yamama_tap_secret_key', ''),
            'technology_id' => $technology_id,
            'platform_id'   => $platform_id,
            'test_mode'     => (int) get_option('yamama_tap_test_mode', 1) === 1,
            'base_url'      => 'https://api.tap.company',
        ];
    }

    /**
     * Tap doesn't require an auth handshake — it uses the Bearer secret directly per request.
     * Returns the secret_key when valid, or WP_Error otherwise. Kept named tap_authenticate()
     * to preserve the existing call sites.
     */
    private static function tap_authenticate($config)
    {
        if (empty($config['merchant_id']) || empty($config['secret_key'])) {
            return new WP_Error('tap_missing_creds', 'Tap credentials not configured.');
        }
        return $config['secret_key'];
    }

    private static function get_order_edit_url($order_id)
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
        }
        return admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    /* ──────────────────────────────────────────────
     *  Build Lamha Payload
     * ────────────────────────────────────────────── */

    private static function build_lamha_payload($order, $form_data)
    {
        $items = [];
        if (isset($form_data['items']) && is_array($form_data['items'])) {
            foreach ($form_data['items'] as $item) {
                $items[] = [
                    'name'     => sanitize_text_field($item['name'] ?? ''),
                    'quantity' => max(1, intval($item['quantity'] ?? 1)),
                    'Sku'      => sanitize_text_field($item['Sku'] ?? ''),
                    'amount'   => (string) floatval($item['amount'] ?? 0),
                    'weight'   => (string) floatval($item['weight'] ?? 0.5),
                ];
            }
        }

        $callback_url     = rest_url('yamama-shipping/v1/orders/' . $order->get_id() . '/status');
        $callback_pdf_url = rest_url('yamama-shipping/v1/orders/' . $order->get_id() . '/label');

        return [
            'sub_total'        => intval(floatval($form_data['sub_total'] ?? 0)),
            'discount'         => (string) floatval($form_data['discount'] ?? 0),
            'shopping_cost'    => intval(floatval($form_data['shopping_cost'] ?? 0)),
            'total'            => intval(floatval($form_data['total'] ?? 0)),
            'payment_method'   => sanitize_text_field($form_data['payment_method'] ?? 'cod'),
            'date'             => sanitize_text_field($form_data['date'] ?? current_time('Y-m-d H:i:s')),
            'ShipmentCurrency' => sanitize_text_field($form_data['currency'] ?? 'SAR'),
            'reference_id'     => sanitize_text_field($form_data['reference_id'] ?? (string) $order->get_order_number()),
            'order_id'         => (string) $order->get_id(),
            'create_shippment' => !empty($form_data['create_shippment']),
            'shipper'          => [
                'name'             => sanitize_text_field($form_data['shipper_name'] ?? ''),
                'phone'            => sanitize_text_field($form_data['shipper_phone'] ?? ''),
                'Country'          => sanitize_text_field($form_data['shipper_country'] ?? 'SA'),
                'District'         => sanitize_text_field($form_data['shipper_district'] ?? ''),
                'City'             => sanitize_text_field($form_data['shipper_city'] ?? ''),
                'AddressLine1'     => sanitize_text_field($form_data['shipper_address1'] ?? ''),
                'AddressLine2'     => sanitize_text_field($form_data['shipper_address2'] ?? ''),
                'national_address' => sanitize_text_field($form_data['shipper_national_address'] ?? ''),
            ],
            'customer'         => [
                'name'             => sanitize_text_field($form_data['customer_name'] ?? ''),
                'phone1'           => sanitize_text_field($form_data['customer_phone1'] ?? ''),
                'phone2'           => sanitize_text_field($form_data['customer_phone2'] ?? ''),
                'Country'          => sanitize_text_field($form_data['customer_country'] ?? 'SA'),
                'District'         => sanitize_text_field($form_data['customer_district'] ?? ''),
                'City'             => sanitize_text_field($form_data['customer_city'] ?? ''),
                'AddressLine1'     => sanitize_text_field($form_data['customer_address1'] ?? ''),
                'AddressLine2'     => sanitize_text_field($form_data['customer_address2'] ?? ''),
                'email'            => sanitize_email($form_data['customer_email'] ?? ''),
                'national_address' => sanitize_text_field($form_data['customer_national_address'] ?? ''),
            ],
            'items'            => $items,
            'coupon'           => '',
            'parcels'          => max(1, intval($form_data['parcels'] ?? 1)),
            'callback_url'     => $callback_url,
            'callback_pdf_url' => $callback_pdf_url,
            'carrier_id'       => is_numeric($form_data['carrier_id'] ?? '') ? intval($form_data['carrier_id']) : sanitize_text_field($form_data['carrier_id'] ?? ''),
        ];
    }

    /* ──────────────────────────────────────────────
     *  Sanitize Form Data
     * ────────────────────────────────────────────── */

    /**
     * Get pending shipment data. Tries order meta first, then direct DB fallback.
     */
    private static function get_pending_shipment_data($order)
    {
        $order_id = $order->get_id();

        // Try order meta first
        $form_data = $order->get_meta('_yamama_pending_shipment_data', true);
        if (is_array($form_data) && !empty($form_data)) {
            return $form_data;
        }

        // Direct DB fallback (bypasses object cache and multisite table issues)
        global $wpdb;
        $option_name = 'yamama_pending_' . $order_id;
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $option_name
        ));
        if ($row) {
            $data = maybe_unserialize($row);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }

        // Try base options table (for multisite: wp_options instead of wp_X_options)
        if (is_multisite()) {
            $base_table = $wpdb->base_prefix . 'options';
            if ($base_table !== $wpdb->options) {
                $row = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$base_table} WHERE option_name = %s LIMIT 1",
                    $option_name
                ));
                if ($row) {
                    $data = maybe_unserialize($row);
                    if (is_array($data) && !empty($data)) {
                        return $data;
                    }
                }
            }
        }

        return [];
    }

    /**
     * Save pending shipment data to multiple storage locations for reliability.
     */
    private static function save_pending_shipment_data($order, $data)
    {
        global $wpdb;
        $order_id = $order->get_id();

        // Order meta (primary)
        $order->update_meta_data('_yamama_pending_shipment_data', $data);

        // Direct DB write (bypass cache — reliable across HTTP/HTTPS)
        $option_name = 'yamama_pending_' . $order_id;
        $serialized  = maybe_serialize($data);

        $wpdb->replace(
            $wpdb->options,
            [
                'option_name'  => $option_name,
                'option_value' => $serialized,
                'autoload'     => 'no',
            ],
            ['%s', '%s', '%s']
        );

        // Also write to base options table in multisite
        if (is_multisite()) {
            $base_table = $wpdb->base_prefix . 'options';
            if ($base_table !== $wpdb->options) {
                $wpdb->replace(
                    $base_table,
                    [
                        'option_name'  => $option_name,
                        'option_value' => $serialized,
                        'autoload'     => 'no',
                    ],
                    ['%s', '%s', '%s']
                );
            }
        }
    }

    /**
     * Delete pending shipment data from all storage locations.
     */
    private static function delete_pending_shipment_data($order)
    {
        global $wpdb;
        $order_id    = $order->get_id();
        $option_name = 'yamama_pending_' . $order_id;

        $order->delete_meta_data('_yamama_pending_shipment_data');

        $wpdb->delete($wpdb->options, ['option_name' => $option_name], ['%s']);

        if (is_multisite()) {
            $base_table = $wpdb->base_prefix . 'options';
            if ($base_table !== $wpdb->options) {
                $wpdb->delete($base_table, ['option_name' => $option_name], ['%s']);
            }
        }

        delete_transient($option_name);
    }

    private static function sanitize_form_data($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $sanitized = [];
        $text_fields = [
            'customer_name', 'customer_phone1', 'customer_phone2', 'customer_email',
            'customer_country', 'customer_city', 'customer_district',
            'customer_address1', 'customer_address2', 'customer_national_address',
            'shipper_name', 'shipper_phone', 'shipper_country', 'shipper_city',
            'shipper_district', 'shipper_address1', 'shipper_address2', 'shipper_national_address',
            'payment_method', 'date', 'currency', 'reference_id', 'wc_order_id',
            'carrier_id', 'quote_cost',
        ];

        foreach ($text_fields as $field) {
            $sanitized[$field] = isset($data[$field]) ? sanitize_text_field($data[$field]) : '';
        }

        $numeric_fields = ['total', 'sub_total', 'discount', 'shopping_cost', 'parcels'];
        foreach ($numeric_fields as $field) {
            $sanitized[$field] = isset($data[$field]) ? floatval($data[$field]) : 0;
        }

        $sanitized['create_shippment'] = !empty($data['create_shippment']);

        if (isset($data['items']) && is_array($data['items'])) {
            $sanitized['items'] = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sanitized['items'][] = [
                    'name'     => sanitize_text_field($item['name'] ?? ''),
                    'quantity' => max(1, intval($item['quantity'] ?? 1)),
                    'Sku'      => sanitize_text_field($item['Sku'] ?? ''),
                    'amount'   => (string) floatval($item['amount'] ?? 0),
                    'weight'   => (string) floatval($item['weight'] ?? 0.5),
                ];
            }
        } else {
            $sanitized['items'] = [];
        }

        return $sanitized;
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Platform pricing (merchant rates page)
     * ────────────────────────────────────────────── */

    public static function ajax_get_platform_pricing()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $result = Yamama_Shipping_Client::get_platform_pricing();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['carriers' => is_array($result) ? $result : []]);
    }

    /* ──────────────────────────────────────────────
     *  AJAX: Wallet
     * ────────────────────────────────────────────── */

    public static function ajax_get_wallet()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $result = Yamama_Shipping_Client::request('GET', '/wallet');
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(isset($result['data']) ? $result['data'] : $result);
    }

    public static function ajax_create_wallet_topup()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'يرجى إدخال مبلغ صالح.']);
        }

        $return_url = admin_url('admin.php?page=yamamah-shipping-rates&wallet_topup=1');
        $callback_url = $return_url;
        $settings = Yamama_Shipping_Client::get_settings();
        $webhook_url = preg_replace('#/plugin/?$#', '/webhooks/tap', untrailingslashit((string) ($settings['middleware_base_url'] ?? '')));

        $shipper = self::get_shipper_defaults();
        $result = Yamama_Shipping_Client::request('POST', '/wallet/topup', [
            'amount'       => $amount,
            'redirect_url' => $callback_url,
            'post_url'     => $webhook_url ?: $callback_url,
            'customer'     => [
                'first_name' => $shipper['name'] ?: (get_bloginfo('name') ?: 'Store'),
                'email'      => get_option('admin_email'),
            ],
        ]);

        if (is_wp_error($result)) {
            $msg = $result->get_error_message();
            if (stripos($msg, '404') !== false || stripos($msg, 'StoreWallet') !== false) {
                $msg = 'خدمة المحفظة غير مفعّلة على منصة الشحن بعد. تواصل مع الدعم أو طبّق migration.';
            }
            wp_send_json_error(['message' => $msg]);
        }

        $checkout = $result['data']['checkoutUrl'] ?? ($result['data']['checkout_url'] ?? '');
        if ($checkout === '') {
            wp_send_json_error(['message' => 'فشل إنشاء عملية شحن الرصيد.']);
        }

        wp_send_json_success([
            'checkout_url' => $checkout,
            'return_url'   => $return_url,
        ]);
    }

    public static function ajax_request_wallet_withdraw()
    {
        check_ajax_referer('yamama_shipping_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Access denied.'], 403);
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $bank_name = sanitize_text_field($_POST['bank_name'] ?? '');
        $account_holder = sanitize_text_field($_POST['account_holder'] ?? '');
        $iban = sanitize_text_field($_POST['iban'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        if ($amount <= 0 || $bank_name === '' || $account_holder === '' || $iban === '') {
            wp_send_json_error(['message' => 'يرجى تعبئة جميع الحقول المطلوبة.']);
        }

        $result = Yamama_Shipping_Client::request('POST', '/wallet/withdraw-request', [
            'amount'         => $amount,
            'bank_name'      => $bank_name,
            'account_holder' => $account_holder,
            'iban'           => $iban,
            'notes'          => $notes,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'تم إرسال طلب السحب. سيتم مراجعته يدوياً.',
            'data'    => isset($result['data']) ? $result['data'] : $result,
        ]);
    }

    public static function handle_wallet_topup_return()
    {
        $redirect = admin_url('admin.php?page=yamamah-shipping-rates&wallet_topup=1');
        wp_safe_redirect($redirect);
        exit;
    }
}
