<?php
/**
 * صفحة مدير العمليات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$order_summary  = $data['order_summary'];
$recent_orders  = $data['recent_orders'];
$top_customers  = $data['top_customers'];
$active_coupons = $data['active_coupons'];
$store_type     = yamamah_get_store_type();

ob_start();
?>

<div class="page active" id="page-operations">
    <div class="page-header">
        <h1>مدير العمليات</h1>
        <p>نظرة عامة شاملة على جميع العمليات في متجرك</p>
        <div class="meta"><?php echo date_i18n( 'l j F Y' ); ?> &bull; آخر تحديث منذ <?php echo date_i18n( 'H:i' ); ?></div>
    </div>

    <!-- Quick Nav -->
    <div class="grid grid-7 mb-5">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="quick-nav bg-blue-light">
            <div class="nav-icon">🛒</div>
            <div class="nav-label">الطلبات</div>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-customers' ) ); ?>" class="quick-nav bg-green-light">
            <div class="nav-icon">👥</div>
            <div class="nav-label">العملاء</div>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-reports' ) ); ?>" class="quick-nav bg-purple-light">
            <div class="nav-icon">📊</div>
            <div class="nav-label">التقارير</div>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-settings-general' ) ); ?>" class="quick-nav bg-slate-light">
            <div class="nav-icon">⚙️</div>
            <div class="nav-label">الإعدادات</div>
        </a>
    </div>

    <?php
        $is_booking_cal = in_array( $store_type, array( 'booking', 'rental' ), true );
        $cal_title      = $is_booking_cal
            ? ( $store_type === 'booking' ? 'تقويم الحجوزات' : 'تقويم التأجير' )
            : 'تقويم الطلبات';

        $cal_month = isset( $_GET['cal_month'] ) ? (int) $_GET['cal_month'] : (int) date( 'n' );
        $cal_year  = isset( $_GET['cal_year'] ) ? (int) $_GET['cal_year'] : (int) date( 'Y' );
        if ( $cal_month < 1 ) { $cal_month = 12; $cal_year--; }
        if ( $cal_month > 12 ) { $cal_month = 1; $cal_year++; }

        $first_day_ts  = mktime( 0, 0, 0, $cal_month, 1, $cal_year );
        $days_in_month = (int) date( 't', $first_day_ts );
        $first_weekday = (int) date( 'w', $first_day_ts );
        $month_label   = date_i18n( 'F Y', $first_day_ts );

        $prev_m = $cal_month - 1; $prev_y = $cal_year;
        if ( $prev_m < 1 ) { $prev_m = 12; $prev_y--; }
        $next_m = $cal_month + 1; $next_y = $cal_year;
        if ( $next_m > 12 ) { $next_m = 1; $next_y++; }

        $bookings = array();
        if ( $is_booking_cal ) {
            $bookings = ! empty( $data['bookings'] ) ? $data['bookings'] : array();
        } elseif ( function_exists( 'wc_get_orders' ) ) {
            $month_start = sprintf( '%04d-%02d-01 00:00:00', $cal_year, $cal_month );
            $month_end   = sprintf( '%04d-%02d-%02d 23:59:59', $cal_year, $cal_month, $days_in_month );
            $month_orders = wc_get_orders(
                array(
                    'limit'        => 100,
                    'type'         => 'shop_order',
                    'status'       => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
                    'date_created' => $month_start . '...' . $month_end,
                    'orderby'      => 'date',
                    'order'        => 'DESC',
                )
            );
            foreach ( $month_orders as $o ) {
                $created = $o->get_date_created();
                if ( ! $created ) {
                    continue;
                }
                $items   = $o->get_items();
                $first   = $items ? reset( $items ) : null;
                $bookings[] = array(
                    'order_id'     => $o->get_id(),
                    'order_number' => $o->get_order_number(),
                    'customer'     => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
                    'product'      => $first ? $first->get_name() : __( 'طلب', 'yamamah-dashboard' ),
                    'check_in'     => $created->date( 'Y-m-d' ),
                    'check_out'    => $created->date( 'Y-m-d' ),
                    'total'        => $o->get_formatted_order_total(),
                    'deposit'      => '',
                    'status'       => $o->get_status(),
                    'status_label' => wc_get_order_status_name( $o->get_status() ),
                );
            }
        }

        $day_bookings = array();
        foreach ( $bookings as $bk ) {
            $s = ! empty( $bk['check_in'] ) ? strtotime( $bk['check_in'] ) : false;
            $e = ! empty( $bk['check_out'] ) ? strtotime( $bk['check_out'] ) : $s;
            if ( ! $s ) continue;
            if ( ! $e ) $e = $s;
            $cur = $s;
            while ( $cur <= $e ) {
                $dm = (int) date( 'n', $cur );
                $dy = (int) date( 'Y', $cur );
                $dd = (int) date( 'j', $cur );
                if ( $dm === $cal_month && $dy === $cal_year ) {
                    if ( ! isset( $day_bookings[ $dd ] ) ) $day_bookings[ $dd ] = array();
                    $day_bookings[ $dd ][] = $bk;
                }
                $cur = strtotime( '+1 day', $cur );
            }
        }

        $status_colors = array(
            'processing' => '#3b82f6',
            'on-hold'    => '#f59e0b',
            'completed'  => '#10b981',
            'pending'    => '#8b5cf6',
            'cancelled'  => '#ef4444',
            'refunded'   => '#6b7280',
            'failed'     => '#ef4444',
        );
        $base_url = admin_url( 'admin.php?page=yamamah-operations' );
    ?>
    <!-- تقويم الطلبات / الحجوزات / التأجير -->
    <div class="card mb-5">
        <div class="card-header" style="align-items:center;">
            <div class="card-title"><span>📅</span><h3><?php echo esc_html( $cal_title ); ?></h3></div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="<?php echo esc_url( add_query_arg( array( 'cal_month' => $prev_m, 'cal_year' => $prev_y ), $base_url ) ); ?>" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:1rem;">→</a>
                <span style="font-weight:600;min-width:120px;text-align:center;"><?php echo esc_html( $month_label ); ?></span>
                <a href="<?php echo esc_url( add_query_arg( array( 'cal_month' => $next_m, 'cal_year' => $next_y ), $base_url ) ); ?>" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:1rem;">←</a>
            </div>
        </div>
        <div class="yamamah-calendar-grid">
            <?php
            $day_names = array( 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت' );
            foreach ( $day_names as $dn ) :
            ?>
                <div class="yamamah-cal-header"><?php echo esc_html( $dn ); ?></div>
            <?php endforeach; ?>

            <?php for ( $blank = 0; $blank < $first_weekday; $blank++ ) : ?>
                <div class="yamamah-cal-cell yamamah-cal-empty"></div>
            <?php endfor; ?>

            <?php for ( $d = 1; $d <= $days_in_month; $d++ ) :
                $is_today   = ( $d === (int) date( 'j' ) && $cal_month === (int) date( 'n' ) && $cal_year === (int) date( 'Y' ) );
                $has_events = ! empty( $day_bookings[ $d ] );
                $cell_class = 'yamamah-cal-cell';
                if ( $is_today ) $cell_class .= ' yamamah-cal-today';
                if ( $has_events ) $cell_class .= ' yamamah-cal-has-events';
            ?>
                <div class="<?php echo esc_attr( $cell_class ); ?>">
                    <span class="yamamah-cal-day-num"><?php echo esc_html( $d ); ?></span>
                    <?php if ( $has_events ) :
                        $shown = 0;
                        foreach ( $day_bookings[ $d ] as $ev ) :
                            if ( $shown >= 2 ) break;
                            $sc = isset( $status_colors[ $ev['status'] ] ) ? $status_colors[ $ev['status'] ] : '#6b7280';
                    ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $ev['order_id'] ) ); ?>"
                           class="yamamah-cal-event" style="background:<?php echo esc_attr( $sc ); ?>20;color:<?php echo esc_attr( $sc ); ?>;border-right:3px solid <?php echo esc_attr( $sc ); ?>;"
                           title="<?php echo esc_attr( $ev['customer'] . ' — ' . $ev['product'] ); ?>">
                            <?php echo esc_html( mb_strimwidth( $ev['product'], 0, 14, '…' ) ); ?>
                        </a>
                    <?php $shown++; endforeach;
                        if ( count( $day_bookings[ $d ] ) > 2 ) : ?>
                            <span class="yamamah-cal-more">+<?php echo count( $day_bookings[ $d ] ) - 2; ?></span>
                        <?php endif;
                    endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ( ! empty( $bookings ) ) : ?>
        <div style="overflow-x:auto;border-top:1px solid var(--border,#e5e7eb);padding-top:1rem;">
            <table style="width:100%;font-size:0.82rem;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border,#e5e7eb);text-align:right;">
                        <th style="padding:0.5rem 0.6rem;">الطلب</th>
                        <th style="padding:0.5rem 0.6rem;">العميل</th>
                        <th style="padding:0.5rem 0.6rem;"><?php echo $is_booking_cal ? ( $store_type === 'booking' ? 'الخدمة' : 'السيارة' ) : 'المنتج'; ?></th>
                        <th style="padding:0.5rem 0.6rem;"><?php echo $is_booking_cal ? 'بداية التأجير' : 'تاريخ الطلب'; ?></th>
                        <th style="padding:0.5rem 0.6rem;">المبلغ</th>
                        <th style="padding:0.5rem 0.6rem;">العربون</th>
                        <th style="padding:0.5rem 0.6rem;">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $bookings as $bk ) : ?>
                    <tr style="border-bottom:1px solid var(--border,#f0f0f0);">
                        <td style="padding:0.5rem 0.6rem;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-order-detail&order_id=' . $bk['order_id'] ) ); ?>" style="color:var(--green);font-family:monospace;font-size:.8rem;">#<?php echo esc_html( $bk['order_number'] ); ?></a>
                        </td>
                        <td style="padding:0.5rem 0.6rem;"><?php echo esc_html( $bk['customer'] ); ?></td>
                        <td style="padding:0.5rem 0.6rem;"><?php echo esc_html( $bk['product'] ); ?></td>
                        <td style="padding:0.5rem 0.6rem;direction:ltr;text-align:right;"><?php echo esc_html( $bk['check_in'] ?: '—' ); ?></td>
                        <td style="padding:0.5rem 0.6rem;"><?php echo wp_kses_post( $bk['total'] ); ?></td>
                        <td style="padding:0.5rem 0.6rem;"><?php echo $bk['deposit'] ? wp_kses_post( $bk['deposit'] ) : '—'; ?></td>
                        <td style="padding:0.5rem 0.6rem;"><span class="badge <?php echo esc_attr( yamamah_order_status_badge_class( $bk['status'] ) ); ?>"><?php echo esc_html( $bk['status_label'] ); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .yamamah-calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border,#e5e7eb);border:1px solid var(--border,#e5e7eb);border-radius:8px;overflow:hidden;margin-bottom:1rem;}
    .yamamah-cal-header{background:var(--bg-alt,#f9fafb);padding:8px 4px;text-align:center;font-size:.75rem;font-weight:700;color:var(--text-muted,#6b7280);}
    .yamamah-cal-cell{background:#fff;min-height:80px;padding:4px;position:relative;display:flex;flex-direction:column;gap:2px;}
    .yamamah-cal-empty{background:var(--bg-alt,#f9fafb);}
    .yamamah-cal-today{background:#f0fdf4;}
    .yamamah-cal-today .yamamah-cal-day-num{background:var(--green,#16a34a);color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;}
    .yamamah-cal-day-num{font-size:.8rem;font-weight:600;color:var(--text,#111);margin-bottom:2px;}
    .yamamah-cal-event{display:block;font-size:.65rem;padding:2px 4px;border-radius:4px;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.4;}
    .yamamah-cal-event:hover{opacity:.85;}
    .yamamah-cal-more{font-size:.6rem;color:var(--text-muted,#6b7280);text-align:center;}
    .yamamah-cal-has-events{background:#fefce8;}
    @media(max-width:768px){.yamamah-cal-cell{min-height:50px;}.yamamah-cal-event{font-size:.55rem;}}
    </style>

    <!-- Orders Summary -->
    <div class="grid grid-2 mb-5 yamamah-ops-summary-grid" style="grid-template-columns:2fr 1fr;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>🛒</span><h3>ملخص الطلبات</h3></div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="card-link">عرض الكل</a>
            </div>
            <div class="grid grid-4 mb-4" style="gap:0.5rem;">
                <?php foreach ( $order_summary as $item ) : ?>
                    <div class="status-badge <?php echo esc_attr( $item['bg'] ); ?>">
                        <span><?php echo $item['icon']; ?></span>
                        <div>
                            <p class="text-xs"><?php echo esc_html( $item['label'] ); ?></p>
                            <p class="fw-500"><?php echo esc_html( $item['count'] ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card yamamah-latest-orders-card">
            <div class="card-header">
                <h3 class="text-sm">آخر الطلبات</h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="card-link">الكل</a>
            </div>
            <div class="space-y-2 yamamah-latest-orders-list">
                <?php foreach ( $recent_orders as $order ) : ?>
                    <div class="item-row">
                        <div>
                            <div class="flex gap-2 items-center">
                                <span class="text-xs text-muted font-mono">#<?php echo esc_html( $order['number'] ); ?></span>
                                <span class="badge <?php echo esc_attr( yamamah_order_status_badge_class( $order['status'] ) ); ?>">
                                    <?php echo esc_html( yamamah_order_status_label( $order['status'] ) ); ?>
                                </span>
                            </div>
                            <p class="text-xs text-muted mt-1"><?php echo esc_html( $order['customer'] ); ?></p>
                        </div>
                        <div class="text-left">
                            <p class="text-xs"><?php echo wp_kses_post( $order['total_formatted'] ); ?></p>
                            <p class="text-xs text-muted">منذ <?php echo esc_html( $order['date_human'] ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Coupons & Top Customers -->
    <div class="grid grid-2 mb-5">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>🎫</span><h3>القسائم النشطة</h3></div>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>" class="card-link">إدارة القسائم</a>
            </div>
            <?php if ( ! empty( $active_coupons ) ) : ?>
                <?php foreach ( array_slice( $active_coupons, 0, 3 ) as $coupon ) : ?>
                    <div class="coupon-card">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex gap-2 items-center">
                                <span class="coupon-code"><?php echo esc_html( strtoupper( $coupon['code'] ) ); ?></span>
                                <span class="badge badge-pink"><?php echo esc_html( $coupon['type_label'] ); ?></span>
                            </div>
                            <span class="text-sm text-pink">
                                <?php
                                echo $coupon['type'] === 'percent'
                                    ? esc_html( $coupon['amount'] ) . '%'
                                    : wc_price( $coupon['amount'] );
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between text-xs text-muted">
                            <span>الاستخدام: <?php echo esc_html( $coupon['usage_count'] ); ?> / <?php echo esc_html( $coupon['usage_limit'] ); ?></span>
                            <span>ينتهي: <?php echo esc_html( $coupon['expiry'] ); ?></span>
                        </div>
                        <div class="progress-bar mt-2">
                            <div class="fill" style="width:<?php echo esc_attr( $coupon['percentage'] ); ?>%;background:var(--purple)"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="text-sm text-muted">لا توجد قسائم نشطة</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><span>👑</span><h3>أفضل العملاء</h3></div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-customers' ) ); ?>" class="card-link">جميع العملاء</a>
            </div>
            <div class="space-y-3">
                <?php foreach ( $top_customers as $rank => $customer ) : ?>
                    <div class="flex items-center gap-3 py-2" style="border-bottom:1px solid var(--border)">
                        <div class="customer-avatar">
                            <?php echo esc_html( $customer['initials'] ); ?>
                            <span class="rank-badge"><?php echo esc_html( $rank + 1 ); ?></span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm"><?php echo esc_html( $customer['name'] ); ?></p>
                            <div class="flex gap-2 text-xs text-muted">
                                <span><?php echo esc_html( $customer['orders'] ); ?> طلب</span>
                            </div>
                        </div>
                        <span class="text-sm"><?php echo wp_kses_post( $customer['spent'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
