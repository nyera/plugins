<?php
/**
 * Yamamah API - التكامل مع WooCommerce
 * يسحب البيانات الحقيقية من WooCommerce ويعيدها بتنسيق موحد
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Yamamah_API {

    /**
     * رمز العملة
     */
    private function currency_symbol() {
        return get_woocommerce_currency_symbol();
    }

    /**
     * تنسيق السعر
     */
    private function format_price( $amount ) {
        if ( ! is_numeric( $amount ) ) {
            $amount = 0;
        }
        return wc_price( (float) $amount );
    }

    // ================================================================
    //  إحصائيات الرئيسية (Dashboard Stats)
    // ================================================================

    public function get_dashboard_stats( $period = '30day' ) {
        // --- إجمالي المبيعات ---
        $total_sales = $this->get_total_sales( $period );

        // --- عدد الطلبات ---
        $total_orders = $this->get_total_orders( $period );

        // --- عدد الزيارات (يحتاج إضافة تحليلات أو Google Analytics) ---
        $visits = 0; // placeholder - ربط مع GA4 أو إضافة أخرى

        // --- معدل التحويل ---
        $conversion_rate = $visits > 0 ? round( ( $total_orders / $visits ) * 100, 2 ) : 0;

        // --- عملاء جدد ---
        $new_customers = $this->get_new_customers_count( $period );

        return array(
            'total_sales' => array(
                'value'   => $total_sales,
                'formatted' => $this->format_price( $total_sales ),
                'change'  => $this->calculate_change( 'sales', $period ),
            ),
            'total_orders' => array(
                'value'  => $total_orders,
                'change' => $this->calculate_change( 'orders', $period ),
            ),
            'visits' => array(
                'value'  => $visits,
                'change' => 0,
            ),
            'conversion_rate' => array(
                'value'  => $conversion_rate . '%',
                'change' => 0,
            ),
            'new_customers' => array(
                'value'  => $new_customers,
                'change' => $this->calculate_change( 'customers', $period ),
            ),
        );
    }

    // ================================================================
    //  المبيعات
    // ================================================================

    private function get_total_sales( $period = '30day' ) {
        $date_range = $this->get_date_range( $period );

        $args = array(
            'status'     => array( 'wc-completed', 'wc-processing' ),
            'date_after' => $date_range['start'],
            'date_before'=> $date_range['end'],
            'return'     => 'ids',
            'limit'      => -1,
        );

        $orders = wc_get_orders( $args );
        $total  = 0;

        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $total += $order->get_total();
            }
        }

        return $total;
    }

    private function get_total_orders( $period = '30day' ) {
        $date_range = $this->get_date_range( $period );

        return count( wc_get_orders( array(
            'date_after'  => $date_range['start'],
            'date_before' => $date_range['end'],
            'return'      => 'ids',
            'limit'       => -1,
            'type'        => 'shop_order',
        )));
    }

    private function get_new_customers_count( $period = '30day' ) {
        $date_range = $this->get_date_range( $period );

        $users = get_users( array(
            'role'       => 'customer',
            'date_query' => array(
                array(
                    'after'  => $date_range['start'],
                    'before' => $date_range['end'],
                ),
            ),
            'count_total' => true,
            'fields'      => 'ID',
        ));

        return count( $users );
    }

    // ================================================================
    //  آخر الطلبات
    // ================================================================

    public function get_recent_orders( $limit = 5 ) {
        $orders = wc_get_orders( array(
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ));

        $result = array();
        foreach ( $orders as $order ) {
            if ( ! method_exists( $order, 'get_order_number' ) ) continue;
            $result[] = array(
                'id'          => $order->get_id(),
                'number'      => $order->get_order_number(),
                'customer'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email'       => $order->get_billing_email(),
                'total'       => $order->get_total(),
                'total_formatted' => $this->format_price( $order->get_total() ),
                'status'      => $order->get_status(),
                'status_label'=> wc_get_order_status_name( $order->get_status() ),
                'date'        => $order->get_date_created()->format( 'Y-m-d' ),
                'date_human'  => human_time_diff( $order->get_date_created()->getTimestamp(), time() ),
                'edit_url'    => $order->get_edit_order_url(),
            );
        }

        return $result;
    }

    // ================================================================
    //  قائمة الطلبات (مع صفحات)
    // ================================================================

    public function get_orders( $page = 1, $per_page = 20, $status = 'any' ) {
        $args = array(
            'limit'   => $per_page,
            'page'    => $page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        );

        if ( $status !== 'any' ) {
            $args['status'] = $status;
        }

        $orders = wc_get_orders( $args );

        $count_args = array( 'return' => 'ids', 'limit' => -1, 'type' => 'shop_order' );
        if ( $status !== 'any' ) $count_args['status'] = $status;
        $total = count( wc_get_orders( $count_args ) );

        $result = array();
        foreach ( $orders as $order ) {
            if ( ! method_exists( $order, 'get_order_number' ) ) continue;
            $result[] = array(
                'id'          => $order->get_id(),
                'number'      => $order->get_order_number(),
                'customer'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'total'       => $this->format_price( $order->get_total() ),
                'status'      => $order->get_status(),
                'status_label'=> wc_get_order_status_name( $order->get_status() ),
                'date'        => $order->get_date_created()->format( 'Y-m-d' ),
                'edit_url'    => admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $order->get_id() ),
            );
        }

        return array(
            'orders'     => $result,
            'total'      => $total,
            'pages'      => ceil( $total / $per_page ),
            'current'    => $page,
            'counts'     => $this->get_order_counts(),
        );
    }

    private function get_order_counts() {
        $statuses = array( 'processing', 'on-hold', 'completed', 'cancelled', 'pending', 'shipped', 'refunded', 'failed' );
        $result   = array();
        $all      = 0;

        foreach ( $statuses as $s ) {
            $count = count( wc_get_orders( array( 'status' => $s, 'limit' => -1, 'return' => 'ids' ) ) );
            $result[ $s ] = $count;
            $all += $count;
        }

        $result['all'] = $all;
        return $result;
    }

    // ================================================================
    //  نظرة عامة على حالات الطلبات
    // ================================================================

    public function get_order_overview() {
        $counts = $this->get_order_counts();

        return array(
            array(
                'label' => 'قيد المعالجة',
                'count' => $counts['processing'],
                'icon'  => '⏳',
                'color' => '#ca8a04',
                'bg'    => 'bg-yellow-light',
            ),
            array(
                'label' => 'تم التسليم',
                'count' => $counts['completed'],
                'icon'  => '✅',
                'color' => '#16a34a',
                'bg'    => 'bg-green-light',
            ),
            array(
                'label' => 'ملغاة',
                'count' => $counts['cancelled'],
                'icon'  => '❌',
                'color' => '#dc2626',
                'bg'    => 'bg-red-light',
            ),
            array(
                'label' => 'قيد الانتظار',
                'count' => $counts['pending'] + $counts['on-hold'],
                'icon'  => '🚚',
                'color' => '#2563eb',
                'bg'    => 'bg-blue-light',
            ),
        );
    }

    // ================================================================
    //  أكثر المنتجات مبيعاً
    // ================================================================

    public function get_top_products( $limit = 5 ) {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => $limit,
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        );

        $query = new WP_Query( $args );
        $products = array();
        $rank = 1;

        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );

            $products[] = array(
                'rank'      => $rank++,
                'id'        => $product->get_id(),
                'name'      => $product->get_name(),
                'image'     => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
                'sales'     => $product->get_total_sales(),
                'price'     => $this->format_price( $product->get_price() ),
                'edit_url'  => admin_url( 'admin.php?page=yamamah-add-product&product_id=' . $product->get_id() ),
            );
        }
        wp_reset_postdata();

        return $products;
    }

    // ================================================================
    //  تنبيهات المخزون
    // ================================================================

    public function get_stock_alerts() {
        $low_stock_threshold = get_option( 'woocommerce_notify_low_stock_amount', 5 );

        // منتجات نفدت
        $out_of_stock = wc_get_products( array(
            'stock_status' => 'outofstock',
            'limit'        => 10,
            'return'       => 'objects',
        ));

        // منتجات قليلة المخزون
        $low_stock = wc_get_products( array(
            'stock_status'  => 'instock',
            'manage_stock'  => true,
            'stock_quantity' => array( 1, $low_stock_threshold ),
            'limit'         => 10,
            'return'        => 'objects',
        ));

        $alerts = array();

        foreach ( $out_of_stock as $product ) {
            $alerts[] = array(
                'id'     => $product->get_id(),
                'name'   => $product->get_name(),
                'stock'  => 0,
                'status' => 'out',
                'badge'  => 'نفاذ',
                'badge_class' => 'badge-red',
            );
        }

        foreach ( $low_stock as $product ) {
            $qty = $product->get_stock_quantity();
            if ( $qty !== null && $qty <= $low_stock_threshold && $qty > 0 ) {
                $alerts[] = array(
                    'id'     => $product->get_id(),
                    'name'   => $product->get_name(),
                    'stock'  => $qty,
                    'status' => 'low',
                    'badge'  => 'قليل المخزون',
                    'badge_class' => 'badge-yellow',
                );
            }
        }

        return $alerts;
    }

    // ================================================================
    //  المنتجات
    // ================================================================

    public function get_products( $page = 1, $per_page = 20 ) {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $total_count = $query->found_posts;
        $result = array();

        foreach ( $query->posts as $post ) {
            $wc_product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
            $categories = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
            $tags       = function_exists( 'yamamah_get_product_tag_names' )
                ? yamamah_get_product_tag_names( $post->ID )
                : wp_get_post_terms( $post->ID, 'product_tag', array( 'fields' => 'names' ) );
            if ( is_wp_error( $tags ) || ! is_array( $tags ) ) {
                $tags = array();
            }
            $tags_label = ! empty( $tags ) ? implode( ', ', $tags ) : '-';

            if ( $wc_product ) {
                $result[] = array(
                    'id'           => $wc_product->get_id(),
                    'name'         => $wc_product->get_name(),
                    'sku'          => $wc_product->get_sku(),
                    'price'        => $this->format_price( $wc_product->get_price() ),
                    'stock'        => $wc_product->get_stock_quantity(),
                    'stock_status' => $wc_product->get_stock_status(),
                    'category'     => ! empty( $categories ) && ! is_wp_error( $categories ) ? implode( ', ', $categories ) : '-',
                    'tags'         => $tags_label,
                    'status'       => $wc_product->get_status(),
                    'image'        => wp_get_attachment_image_url( $wc_product->get_image_id(), 'thumbnail' ),
                    'image_id'     => (int) $wc_product->get_image_id(),
                    'edit_url'     => admin_url( 'admin.php?page=yamamah-add-product&product_id=' . $wc_product->get_id() ),
                );
            } else {
                $thumb_id = get_post_thumbnail_id( $post->ID );
                $price_val = get_post_meta( $post->ID, '_price', true );
                $result[] = array(
                    'id'           => $post->ID,
                    'name'         => $post->post_title,
                    'sku'          => get_post_meta( $post->ID, '_sku', true ) ?: '',
                    'price'        => $price_val ? $this->format_price( $price_val ) : '-',
                    'stock'        => null,
                    'stock_status' => 'instock',
                    'category'     => ! empty( $categories ) && ! is_wp_error( $categories ) ? implode( ', ', $categories ) : '-',
                    'tags'         => $tags_label,
                    'status'       => $post->post_status,
                    'image'        => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
                    'image_id'     => (int) $thumb_id,
                    'edit_url'     => admin_url( 'admin.php?page=yamamah-add-product&product_id=' . $post->ID ),
                );
            }
        }

        wp_reset_postdata();

        return array(
            'products' => $result,
            'total'    => $total_count,
            'pages'    => ceil( $total_count / $per_page ),
            'current'  => $page,
        );
    }

    // ================================================================
    //  العملاء
    // ================================================================

    public function get_customers() {
        $customers = get_users( array(
            'role__not_in' => array( 'administrator', 'shop_manager', 'editor' ),
            'orderby'      => 'registered',
            'order'        => 'DESC',
            'number'       => 50,
        ));

        $result = array();
        foreach ( $customers as $customer ) {
            $wc_customer = new WC_Customer( $customer->ID );

            $result[] = array(
                'id'        => $customer->ID,
                'name'      => $customer->display_name,
                'email'     => $customer->user_email,
                'orders'    => $wc_customer->get_order_count(),
                'spent'     => $this->format_price( $wc_customer->get_total_spent() ),
                'spent_raw' => $wc_customer->get_total_spent(),
                'registered'=> $customer->user_registered,
                'initials'  => yamamah_get_initials( $customer->display_name ),
            );
        }

        // ترتيب حسب المصروف
        usort( $result, function( $a, $b ) {
            return $b['spent_raw'] <=> $a['spent_raw'];
        });

        return $result;
    }

    public function get_top_customers( $limit = 4 ) {
        $customers = $this->get_customers();
        return array_slice( $customers, 0, $limit );
    }

    // ================================================================
    //  القسائم النشطة
    // ================================================================

    public function get_active_coupons() {
        $coupons = get_posts( array(
            'post_type'      => 'shop_coupon',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
        ));

        $result = array();
        foreach ( $coupons as $coupon_post ) {
            $coupon = new WC_Coupon( $coupon_post->ID );

            $usage_limit = $coupon->get_usage_limit();
            $usage_count = $coupon->get_usage_count();
            $expiry_date = $coupon->get_date_expires();

            // تخطي المنتهية
            if ( $expiry_date && $expiry_date->getTimestamp() < current_time( 'timestamp' ) ) {
                continue;
            }

            $result[] = array(
                'id'          => $coupon->get_id(),
                'code'        => $coupon->get_code(),
                'type'        => $coupon->get_discount_type(),
                'type_label'  => $this->get_coupon_type_label( $coupon->get_discount_type() ),
                'amount'      => $coupon->get_amount(),
                'usage_count' => $usage_count,
                'usage_limit' => $usage_limit ?: '∞',
                'expiry'      => $expiry_date ? $expiry_date->format( 'Y-m-d' ) : 'غير محدد',
                'percentage'  => $usage_limit > 0 ? round( ( $usage_count / $usage_limit ) * 100 ) : 0,
            );
        }

        return $result;
    }

    private function get_coupon_type_label( $type ) {
        $types = array(
            'percent'       => 'نسبة',
            'fixed_cart'    => 'مبلغ ثابت',
            'fixed_product' => 'خصم منتج',
        );
        return isset( $types[ $type ] ) ? $types[ $type ] : $type;
    }

    // ================================================================
    //  تحليلات — الإيرادات (بيانات حقيقية)
    // ================================================================

    public function get_revenue_analytics_full( $period = '30day' ) {
        $date_range = $this->get_date_range( $period );
        $excluded   = get_option( 'yamamah_analytics_excluded', array( 'pending', 'cancelled', 'failed' ) );
        if ( ! is_array( $excluded ) ) {
            $excluded = array( 'pending', 'cancelled', 'failed' );
        }

        $allowed = array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'wc-refunded' );
        $statuses = array_filter( $allowed, function( $s ) use ( $excluded ) {
            return ! in_array( str_replace( 'wc-', '', $s ), $excluded, true );
        });
        if ( empty( $statuses ) ) {
            $statuses = array( 'wc-completed', 'wc-processing' );
        }

        $orders = wc_get_orders( array(
            'status'      => $statuses,
            'date_after'  => $date_range['start'],
            'date_before' => $date_range['end'],
            'limit'       => -1,
            'type'        => 'shop_order',
        ) );
        if ( ! is_array( $orders ) ) {
            $orders = array();
        }

        $total_sales    = 0.0;
        $total_refunds  = 0.0;
        $total_coupons  = 0.0;
        $total_taxes    = 0.0;
        $total_shipping = 0.0;

        foreach ( $orders as $order ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
                continue;
            }
            $total_sales    += (float) $order->get_total();
            $total_refunds  += (float) $order->get_total_refunded();
            $total_coupons  += (float) $order->get_discount_total();
            $total_taxes    += (float) $order->get_total_tax();
            $total_shipping += (float) $order->get_shipping_total();
        }

        $net_sales   = $total_sales - $total_refunds - $total_coupons;
        $grand_total = $total_sales;

        return array(
            'total_sales'    => $total_sales,
            'refunds'        => $total_refunds,
            'coupons'        => $total_coupons,
            'net_sales'      => $net_sales,
            'taxes'          => $total_taxes,
            'shipping'       => $total_shipping,
            'grand_total'    => $grand_total,
            'total_sales_f'  => $this->format_price( $total_sales ),
            'refunds_f'      => $this->format_price( $total_refunds ),
            'coupons_f'      => $this->format_price( $total_coupons ),
            'net_sales_f'    => $this->format_price( $net_sales ),
            'taxes_f'        => $this->format_price( $total_taxes ),
            'shipping_f'     => $this->format_price( $total_shipping ),
            'grand_total_f'  => $this->format_price( $grand_total ),
        );
    }

    /**
     * Legacy wrapper kept for dashboard home page
     */
    public function get_revenue_analytics() {
        $full = $this->get_revenue_analytics_full( '30day' );
        return array(
            'total_revenue'   => $full['total_sales_f'],
            'net_profit'      => $full['net_sales_f'],
            'profit_margin'   => $full['total_sales'] > 0 ? round( ( $full['net_sales'] / $full['total_sales'] ) * 100, 1 ) . '%' : '0%',
            'avg_order_value' => $this->format_price( 0 ),
        );
    }

    // ================================================================
    //  تحليلات — المنتجات (بيانات حقيقية)
    // ================================================================

    public function get_products_analytics_data( $period = '30day' ) {
        $date_range = $this->get_date_range( $period );
        $excluded   = get_option( 'yamamah_analytics_excluded', array( 'pending', 'cancelled', 'failed' ) );
        if ( ! is_array( $excluded ) ) {
            $excluded = array( 'pending', 'cancelled', 'failed' );
        }

        $allowed = array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' );
        $statuses = array_filter( $allowed, function( $s ) use ( $excluded ) {
            return ! in_array( str_replace( 'wc-', '', $s ), $excluded, true );
        });
        if ( empty( $statuses ) ) {
            $statuses = array( 'wc-completed', 'wc-processing' );
        }

        $orders = wc_get_orders( array(
            'status'      => $statuses,
            'date_after'  => $date_range['start'],
            'date_before' => $date_range['end'],
            'limit'       => -1,
            'type'        => 'shop_order',
        ) );
        if ( ! is_array( $orders ) ) {
            $orders = array();
        }

        $total_orders = 0;
        $net_sales    = 0.0;
        $items_sold   = 0;

        foreach ( $orders as $order ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
                continue;
            }
            $total_orders++;
            $order_total    = (float) $order->get_total();
            $order_refunded = (float) $order->get_total_refunded();
            $net_sales     += $order_total - $order_refunded;
            $items_sold    += (int) $order->get_item_count();
        }

        return array(
            'total_orders' => $total_orders,
            'net_sales'    => $net_sales,
            'net_sales_f'  => $this->format_price( $net_sales ),
            'items_sold'   => $items_sold,
        );
    }

    public function get_product_analytics() {
        $top_product = $this->get_top_products( 1 );
        return array(
            'top_seller'      => ! empty( $top_product ) ? $top_product[0]['sales'] : 0,
            'total_views'     => 0,
            'conversion_rate' => 0,
            'avg_rating'      => $this->get_average_product_rating(),
        );
    }

    private function get_average_product_rating() {
        global $wpdb;
        $avg = $wpdb->get_var(
            "SELECT AVG(meta_value) FROM {$wpdb->commentmeta}
             JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
             WHERE meta_key = 'rating' AND comment_approved = '1'"
        );
        return $avg ? round( (float) $avg, 1 ) : 0;
    }

    // ================================================================
    //  أدوات مساعدة
    // ================================================================

    private function get_date_range( $period ) {
        $end = current_time( 'Y-m-d' );

        switch ( $period ) {
            case 'today':
                $start = $end;
                break;
            case '7day':
                $start = date( 'Y-m-d', strtotime( '-7 days' ) );
                break;
            case '30day':
            default:
                $start = date( 'Y-m-d', strtotime( '-30 days' ) );
                break;
            case 'year':
                $start = date( 'Y-01-01' );
                break;
        }

        return array( 'start' => $start, 'end' => $end );
    }

    private function calculate_change( $type, $period ) {
        return 0;
    }

    // ================================================================
    //  بيانات الرسوم البيانية (Chart Data)
    // ================================================================

    private function get_months_ar() {
        return array(
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        );
    }

    public function get_monthly_sales_chart_data( $year = null ) {
        $rows = $this->get_sales_chart_data( 'months', $year );
        $data = array();
        foreach ( $rows as $row ) {
            $data[] = array(
                'month'  => $row['label'],
                'sales'  => $row['sales'],
                'orders' => $row['orders'],
            );
        }
        return $data;
    }

    public function get_monthly_orders_chart_data( $year = null ) {
        $rows = $this->get_orders_chart_data( 'months', $year );
        $data = array();
        foreach ( $rows as $row ) {
            $data[] = array(
                'month'     => $row['label'],
                'orders'    => $row['orders'],
                'cancelled' => $row['cancelled'],
            );
        }
        return $data;
    }

    /**
     * Sales chart buckets for days / weeks / months.
     *
     * @param string   $period days|weeks|months
     * @param int|null $year   Used for months.
     * @return array
     */
    public function get_sales_chart_data( $period = 'months', $year = null ) {
        $buckets = $this->build_chart_buckets( $period, $year );
        $data    = array();

        foreach ( $buckets as $bucket ) {
            $orders = wc_get_orders(
                array(
                    'status'      => array( 'wc-completed', 'wc-processing' ),
                    'date_after'  => $bucket['start'],
                    'date_before' => $bucket['end'],
                    'limit'       => -1,
                    'type'        => 'shop_order',
                )
            );
            if ( ! is_array( $orders ) ) {
                $orders = array();
            }

            $total = 0.0;
            $count = 0;
            foreach ( $orders as $order ) {
                if ( ! $order || ! is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
                    continue;
                }
                $total += (float) $order->get_total() - (float) $order->get_total_refunded();
                $count++;
            }

            $data[] = array(
                'label'  => $bucket['label'],
                'sales'  => round( $total, 2 ),
                'orders' => $count,
            );
        }

        return $data;
    }

    /**
     * Orders chart buckets for days / weeks / months.
     *
     * @param string   $period days|weeks|months
     * @param int|null $year   Used for months.
     * @return array
     */
    public function get_orders_chart_data( $period = 'months', $year = null ) {
        $buckets = $this->build_chart_buckets( $period, $year );
        $data    = array();

        foreach ( $buckets as $bucket ) {
            $active = wc_get_orders(
                array(
                    'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ),
                    'date_after'  => $bucket['start'],
                    'date_before' => $bucket['end'],
                    'return'      => 'ids',
                    'limit'       => -1,
                    'type'        => 'shop_order',
                )
            );
            $cancelled = wc_get_orders(
                array(
                    'status'      => array( 'wc-cancelled' ),
                    'date_after'  => $bucket['start'],
                    'date_before' => $bucket['end'],
                    'return'      => 'ids',
                    'limit'       => -1,
                    'type'        => 'shop_order',
                )
            );

            $data[] = array(
                'label'     => $bucket['label'],
                'orders'    => is_array( $active ) ? count( $active ) : 0,
                'cancelled' => is_array( $cancelled ) ? count( $cancelled ) : 0,
            );
        }

        return $data;
    }

    /**
     * Build labeled date buckets for chart intervals.
     *
     * @param string   $period days|weeks|months
     * @param int|null $year
     * @return array
     */
    private function build_chart_buckets( $period, $year = null ) {
        $buckets   = array();
        $months_ar = $this->get_months_ar();

        if ( $period === 'days' ) {
            for ( $i = 13; $i >= 0; $i-- ) {
                $day = date( 'Y-m-d', strtotime( "-{$i} days" ) );
                $buckets[] = array(
                    'label' => date_i18n( 'd/m', strtotime( $day ) ),
                    'start' => $day,
                    'end'   => $day,
                );
            }
            return $buckets;
        }

        if ( $period === 'weeks' ) {
            for ( $i = 11; $i >= 0; $i-- ) {
                $start_ts = strtotime( 'monday this week -' . $i . ' weeks' );
                $end_ts   = strtotime( '+6 days', $start_ts );
                $buckets[] = array(
                    'label' => date_i18n( 'd/m', $start_ts ),
                    'start' => date( 'Y-m-d', $start_ts ),
                    'end'   => date( 'Y-m-d', $end_ts ),
                );
            }
            return $buckets;
        }

        if ( ! $year ) {
            $year = (int) date( 'Y' );
        }
        for ( $m = 1; $m <= 12; $m++ ) {
            $start = $year . '-' . sprintf( '%02d', $m ) . '-01';
            $buckets[] = array(
                'label' => $months_ar[ $m ],
                'start' => $start,
                'end'   => date( 'Y-m-t', strtotime( $start ) ),
            );
        }
        return $buckets;
    }

    public function get_sales_by_category( $period = 'year' ) {
        $date_after = '';
        switch ( $period ) {
            case 'today':  $date_after = date( 'Y-m-d' ); break;
            case '7day':   $date_after = date( 'Y-m-d', strtotime( '-7 days' ) ); break;
            case '30day':  $date_after = date( 'Y-m-d', strtotime( '-30 days' ) ); break;
            default:       $date_after = date( 'Y-01-01' ); break;
        }

        $orders = wc_get_orders( array(
            'status'     => array( 'wc-completed', 'wc-processing' ),
            'date_after' => $date_after,
            'limit'      => -1,
            'type'       => 'shop_order',
        ));

        $cat_totals = array();
        foreach ( $orders as $order ) {
            if ( ! $order || ! is_a( $order, 'WC_Order' ) || is_a( $order, 'WC_Order_Refund' ) ) {
                continue;
            }
            foreach ( $order->get_items() as $item ) {
                $product_id = $item->get_product_id();
                $terms      = get_the_terms( $product_id, 'product_cat' );
                $cat_name   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'أخرى';
                if ( ! isset( $cat_totals[ $cat_name ] ) ) {
                    $cat_totals[ $cat_name ] = 0;
                }
                $cat_totals[ $cat_name ] += (float) $item->get_total();
            }
        }

        arsort( $cat_totals );
        $grand_total = array_sum( $cat_totals );
        $result      = array();
        $count       = 0;
        $others      = 0;

        foreach ( $cat_totals as $name => $total ) {
            $count++;
            if ( $count <= 5 ) {
                $result[] = array(
                    'name'    => $name,
                    'total'   => round( $total, 2 ),
                    'percent' => $grand_total > 0 ? round( ( $total / $grand_total ) * 100 ) : 0,
                );
            } else {
                $others += $total;
            }
        }

        if ( $others > 0 ) {
            $result[] = array(
                'name'    => 'أخرى',
                'total'   => round( $others, 2 ),
                'percent' => $grand_total > 0 ? round( ( $others / $grand_total ) * 100 ) : 0,
            );
        }

        return $result;
    }
}
