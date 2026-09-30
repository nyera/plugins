<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$total_orders   = 0;
$total_revenue  = 0;
$total_products = 0;
$total_customers = 0;

// HPOS-safe counts (wp_count_posts('shop_order') is empty when custom order tables are enabled).
$order_counts = (object) array(
    'wc-completed'  => 0,
    'wc-processing' => 0,
    'wc-on-hold'    => 0,
    'wc-cancelled'  => 0,
    'wc-refunded'   => 0,
    'wc-pending'    => 0,
);
if ( function_exists( 'wc_get_orders' ) ) {
    foreach ( array_keys( (array) $order_counts ) as $status_key ) {
        $status = str_replace( 'wc-', '', $status_key );
        $order_counts->$status_key = count(
            wc_get_orders(
                array(
                    'status' => $status,
                    'limit'  => -1,
                    'return' => 'ids',
                    'type'   => 'shop_order',
                )
            )
        );
    }
    $total_orders = (int) $order_counts->{'wc-completed'}
                  + (int) $order_counts->{'wc-processing'}
                  + (int) $order_counts->{'wc-on-hold'};

    $completed = wc_get_orders(
        array(
            'status' => 'completed',
            'limit'  => -1,
            'return' => 'ids',
            'type'   => 'shop_order',
        )
    );
    foreach ( $completed as $oid ) {
        $o = wc_get_order( $oid );
        if ( $o ) {
            $total_revenue += (float) $o->get_total();
        }
    }
}

$product_counts = wp_count_posts( 'product' );
if ( $product_counts ) {
    $total_products = $product_counts->publish ?? 0;
}

$total_customers = count( get_users( array( 'role' => 'customer', 'fields' => 'ID' ) ) );

ob_start();
?>
<div class="page active" id="page-reports">
    <div class="page-header">
        <h1>التقارير</h1>
        <p>نظرة سريعة على أداء المتجر</p>
    </div>

    <div class="grid grid-4 mb-4">
        <div class="yamamah-stat-card" style="--stat-color:#6C3FA0;">
            <p class="stat-value"><?php echo esc_html( $total_orders ); ?></p>
            <p class="stat-label">إجمالي الطلبات</p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#2D9B83;">
            <p class="stat-value"><?php echo esc_html( number_format( $total_revenue, 2 ) ); ?> <small>ر.س</small></p>
            <p class="stat-label">إجمالي الإيرادات</p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#3498DB;">
            <p class="stat-value"><?php echo esc_html( $total_products ); ?></p>
            <p class="stat-label"><?php echo esc_html( $labels['published_stat'] ); ?></p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#E67E22;">
            <p class="stat-value"><?php echo esc_html( $total_customers ); ?></p>
            <p class="stat-label">العملاء المسجلين</p>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h3 class="card-title">توزيع حالات الطلبات</h3></div>
            <?php
            $statuses = array(
                'wc-completed'  => array( 'label' => 'مكتمل',        'color' => '#2D9B83' ),
                'wc-processing' => array( 'label' => 'قيد التجهيز',  'color' => '#3498DB' ),
                'wc-on-hold'    => array( 'label' => 'معلّق',         'color' => '#F39C12' ),
                'wc-cancelled'  => array( 'label' => 'ملغي',          'color' => '#E74C3C' ),
                'wc-refunded'   => array( 'label' => 'مسترد',         'color' => '#9B59B6' ),
                'wc-pending'    => array( 'label' => 'بانتظار الدفع', 'color' => '#95A5A6' ),
            );
            foreach ( $statuses as $status_key => $cfg ) :
                $count = $order_counts->$status_key ?? 0;
                $pct = $total_orders > 0 ? round( ( $count / $total_orders ) * 100 ) : 0;
            ?>
            <div class="flex justify-between items-center mb-2" style="font-size:0.85rem;">
                <span><?php echo esc_html( $cfg['label'] ); ?></span>
                <span class="text-muted"><?php echo esc_html( $count ); ?> (<?php echo esc_html( $pct ); ?>%)</span>
            </div>
            <div class="progress-bar mb-3">
                <div class="fill" style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $cfg['color'] ); ?>;"></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">روابط سريعة</h3></div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-analytics-revenue' ) ); ?>" class="btn btn-outline w-full">تحليلات الإيرادات</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-analytics-products' ) ); ?>" class="btn btn-outline w-full"><?php echo esc_html( $labels['analytics_menu'] ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="btn btn-outline w-full">جميع الطلبات</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-customers' ) ); ?>" class="btn btn-outline w-full">جميع العملاء</a>
            </div>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
