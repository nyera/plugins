<?php

if (!defined('ABSPATH')) {
    exit;
}

class Yamama_Shipping_Hooks
{
    public static function init()
    {
        add_action('init', [self::class, 'register_custom_statuses']);
        add_filter('wc_order_statuses', [self::class, 'add_custom_statuses_to_list']);
    }

    public static function register_custom_statuses()
    {
        register_post_status('wc-shipped', [
            'label'                     => _x('Shipped', 'Order status', 'yamama-shipping'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop(
                'Shipped <span class="count">(%s)</span>',
                'Shipped <span class="count">(%s)</span>',
                'yamama-shipping'
            ),
        ]);

        register_post_status('wc-returned', [
            'label'                     => _x('Returned', 'Order status', 'yamama-shipping'),
            'public'                    => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'exclude_from_search'       => false,
            'label_count'               => _n_noop(
                'Returned <span class="count">(%s)</span>',
                'Returned <span class="count">(%s)</span>',
                'yamama-shipping'
            ),
        ]);
    }

    public static function add_custom_statuses_to_list($statuses)
    {
        $statuses['wc-shipped']  = _x('Shipped', 'Order status', 'yamama-shipping');
        $statuses['wc-returned'] = _x('Returned', 'Order status', 'yamama-shipping');
        return $statuses;
    }

    /**
     * Map Lamha status_id to WooCommerce order status slug.
     */
    public static function map_lamha_status($status_id)
    {
        $map = [
            '0'  => 'processing',   // جديد
            '1'  => 'on-hold',      // معلق
            '2'  => 'processing',   // تم التنفيذ
            '3'  => 'processing',   // جاهز للالتقاط
            '4'  => 'returned',     // شحنة عكسية
            '5'  => 'cancelled',    // ملغي
            '6'  => 'shipped',      // تم الالتقاط
            '7'  => 'shipped',      // جاري الشحن
            '8'  => 'completed',    // تم التوصيل
            '9'  => 'failed',       // فشل التوصيل
            '10' => 'returned',     // مرتجع
        ];

        return isset($map[(string) $status_id]) ? $map[(string) $status_id] : 'processing';
    }

    /**
     * Map WooCommerce payment gateway ID to Lamha payment_method value.
     */
    public static function map_wc_payment_method($wc_method)
    {
        $map = [
            'cod'                       => 'cod',
            'bacs'                      => 'bank',
            'cheque'                    => 'bank',
            'mada'                      => 'mada',
            'credit_card'               => 'credit_card',
            'stripe'                    => 'stripe',
            'stripe_cc'                 => 'stripe',
            'tamara'                    => 'tamara_installment',
            'tamara_installment'        => 'tamara_installment',
            'tabby'                     => 'tabby',
            'tabby_installments'        => 'tabby',
            'stcpay'                    => 'stc_pay',
            'stc_pay'                   => 'stc_pay',
            'tap'                       => 'paid',
            'applepay'                  => 'paid',
            'ppcp-gateway'              => 'paid',
            'paypal'                    => 'paid',
        ];

        $wc_method = strtolower(trim((string) $wc_method));

        if (isset($map[$wc_method])) {
            return $map[$wc_method];
        }

        return apply_filters('yamama_shipping_payment_method_map', 'paid', $wc_method);
    }
}
