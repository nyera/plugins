<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'shipping' ) ) {
    $page_content = yamamah_upgrade_banner( 'الشحن' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

$per_page     = 20;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$filter       = sanitize_text_field( $_GET['shipment_filter'] ?? 'all' );
$search       = sanitize_text_field( $_GET['s'] ?? '' );

$args = array(
    'limit'    => $per_page,
    'page'     => $current_page,
    'orderby'  => 'date',
    'order'    => 'DESC',
    'status'   => array( 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-shipped', 'wc-returned', 'wc-pending', 'wc-failed' ),
    'paginate' => true,
);

if ( $search !== '' ) {
    $args['s'] = $search;
}

$results     = wc_get_orders( $args );
$orders      = $results->orders;
$total       = $results->total;
$total_pages = $results->max_num_pages;

if ( $filter !== 'all' ) {
    $orders = array_filter( $orders, function ( $order ) use ( $filter ) {
        $lamha_id = (string) $order->get_meta( '_yamama_lamha_order_id', true );
        if ( $filter === 'shipped' ) return $lamha_id !== '';
        if ( $filter === 'not_shipped' ) return $lamha_id === '';
        return true;
    });
}

$shipped_count     = 0;
$not_shipped_count = 0;
foreach ( $results->orders as $o ) {
    $lid = (string) $o->get_meta( '_yamama_lamha_order_id', true );
    if ( $lid !== '' ) $shipped_count++;
    else $not_shipped_count++;
}

$base_url = admin_url( 'admin.php?page=yamamah-shipping-orders' );

$status_map = array(
    'processing' => array( 'label' => 'قيد التجهيز', 'class' => 'badge-blue' ),
    'completed'  => array( 'label' => 'مكتمل',       'class' => 'badge-green' ),
    'on-hold'    => array( 'label' => 'معلّق',        'class' => 'badge-yellow' ),
    'pending'    => array( 'label' => 'بانتظار الدفع','class' => 'badge-yellow' ),
    'cancelled'  => array( 'label' => 'ملغي',         'class' => 'badge-red' ),
    'refunded'   => array( 'label' => 'مسترد',        'class' => 'badge-red' ),
    'failed'     => array( 'label' => 'فاشل',         'class' => 'badge-red' ),
    'shipped'    => array( 'label' => 'تم الشحن',     'class' => 'badge-green' ),
    'returned'   => array( 'label' => 'مرتجع',        'class' => 'badge-yellow' ),
);

$shipment_status_map = array(
    ''   => array( 'label' => 'لم يُشحن',      'class' => 'badge-gray' ),
    '0'  => array( 'label' => 'جديد',           'class' => 'badge-blue' ),
    '1'  => array( 'label' => 'معلق',           'class' => 'badge-yellow' ),
    '2'  => array( 'label' => 'تم التنفيذ',     'class' => 'badge-blue' ),
    '3'  => array( 'label' => 'جاهز للالتقاط', 'class' => 'badge-blue' ),
    '4'  => array( 'label' => 'شحنة عكسية',    'class' => 'badge-red' ),
    '5'  => array( 'label' => 'ملغي',           'class' => 'badge-red' ),
    '6'  => array( 'label' => 'تم الالتقاط',    'class' => 'badge-green' ),
    '7'  => array( 'label' => 'جاري الشحن',     'class' => 'badge-blue' ),
    '8'  => array( 'label' => 'تم التوصيل',     'class' => 'badge-green' ),
    '9'  => array( 'label' => 'فشل التوصيل',    'class' => 'badge-red' ),
    '10' => array( 'label' => 'مرتجع',          'class' => 'badge-yellow' ),
);

ob_start();
?>

<div class="page active" id="page-shipping-orders">
    <div class="page-header">
        <h1>طلبات الشحن</h1>
        <p>إدارة ومتابعة شحنات الطلبات</p>
    </div>

    <!-- Tabs -->
    <div class="yamamah-filter-tabs mb-4">
        <a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
            الكل <span class="tab-count"><?php echo esc_html( $total ); ?></span>
        </a>
        <span class="tab-sep">|</span>
        <a href="<?php echo esc_url( add_query_arg( 'shipment_filter', 'shipped', $base_url ) ); ?>" class="<?php echo $filter === 'shipped' ? 'active' : ''; ?>">
            تم الشحن <span class="tab-count"><?php echo esc_html( $shipped_count ); ?></span>
        </a>
        <span class="tab-sep">|</span>
        <a href="<?php echo esc_url( add_query_arg( 'shipment_filter', 'not_shipped', $base_url ) ); ?>" class="<?php echo $filter === 'not_shipped' ? 'active' : ''; ?>">
            لم يُشحن <span class="tab-count"><?php echo esc_html( $not_shipped_count ); ?></span>
        </a>
    </div>

    <!-- Search -->
    <form method="get" class="yamamah-search-bar mb-4">
        <input type="hidden" name="page" value="yamamah-shipping-orders" />
        <?php if ( $filter !== 'all' ) : ?>
            <input type="hidden" name="shipment_filter" value="<?php echo esc_attr( $filter ); ?>" />
        <?php endif; ?>
        <div class="search-input-wrap">
            <span class="search-icon" data-icon="search"></span>
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="بحث برقم الطلب أو اسم العميل..." />
        </div>
        <button type="submit" class="btn btn-outline-green">بحث</button>
    </form>

    <!-- Table -->
    <div class="card p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الطلب</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>الإجمالي</th>
                        <th>حالة الطلب</th>
                        <th>حالة الشحنة</th>
                        <th>التتبع</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $orders ) ) : ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding:48px 16px;">
                                <p style="font-size:2em;opacity:0.3;margin-bottom:8px;">📦</p>
                                لا توجد طلبات
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $order ) :
                            $order_id        = $order->get_id();
                            $lamha_order_id  = (string) $order->get_meta( '_yamama_lamha_order_id', true );
                            $tracking_number = (string) $order->get_meta( '_yamama_tracking_number', true );
                            $tracking_link   = (string) $order->get_meta( '_yamama_tracking_link', true );
                            $shipment_status = (string) $order->get_meta( '_yamama_shipment_status', true );
                            $status_name_raw = (string) $order->get_meta( '_yamama_shipment_status_name', true );
                            $shipping_cost   = (string) $order->get_meta( '_yamama_shipping_cost', true );
                            $is_shipped      = ( $lamha_order_id !== '' );

                            $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                            if ( $customer_name === '' ) {
                                $customer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
                            }
                            if ( $customer_name === '' ) $customer_name = 'زائر';

                            $order_date = $order->get_date_created();
                            $date_str   = $order_date ? $order_date->date_i18n( 'Y-m-d' ) : '-';

                            $wc_status     = $order->get_status();
                            $wc_status_cfg = $status_map[ $wc_status ] ?? array( 'label' => $wc_status, 'class' => 'badge-gray' );

                            $ship_key     = $is_shipped ? $shipment_status : '';
                            $ship_cfg     = $shipment_status_map[ $ship_key ] ?? $shipment_status_map[''];
                            $ship_label   = $status_name_raw ?: $ship_cfg['label'];

                            $detail_url = admin_url( 'admin.php?page=yamamah-shipping-detail&order_id=' . $order_id );
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $detail_url ); ?>" class="text-purple" style="font-weight:600;">
                                    #<?php echo esc_html( $order->get_order_number() ); ?>
                                </a>
                            </td>
                            <td class="text-xs text-muted"><?php echo esc_html( $date_str ); ?></td>
                            <td><?php echo esc_html( $customer_name ); ?></td>
                            <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                            <td>
                                <span class="badge <?php echo esc_attr( $wc_status_cfg['class'] ); ?>">
                                    <?php echo esc_html( $wc_status_cfg['label'] ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo esc_attr( $ship_cfg['class'] ); ?>">
                                    <?php echo esc_html( $ship_label ); ?>
                                </span>
                                <?php if ( $shipping_cost !== '' ) : ?>
                                    <br><small class="text-muted"><?php echo esc_html( $shipping_cost ); ?> SAR</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $tracking_number !== '' ) : ?>
                                    <code class="yamamah-mono-code"><?php echo esc_html( $tracking_number ); ?></code>
                                    <?php if ( $tracking_link !== '' ) : ?>
                                        <br><a href="<?php echo esc_url( $tracking_link ); ?>" target="_blank" rel="noopener" class="text-green text-xs">تتبع &larr;</a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="yamamah-action-btns">
                                    <a href="<?php echo esc_url( $detail_url ); ?>" class="btn btn-sm btn-outline">شحن</a>
                                    <?php if ( $is_shipped ) : ?>
                                        <button type="button" class="yamamah-icon-action yamamah-fetch-label-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>" title="طباعة بوليصة">
                                            <span data-icon="printer"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
    <div class="yamamah-pagination mt-4">
        <span class="text-xs text-muted">
            عرض <?php echo esc_html( ( $current_page - 1 ) * $per_page + 1 ); ?> - <?php echo esc_html( min( $current_page * $per_page, $total ) ); ?> من <?php echo esc_html( $total ); ?>
        </span>
        <div class="pagination-btns">
            <?php if ( $current_page > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1 ) ); ?>" class="page-btn">&laquo;</a>
            <?php endif; ?>
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"
                   class="page-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                    <?php echo esc_html( $i ); ?>
                </a>
            <?php endfor; ?>
            <?php if ( $current_page < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1 ) ); ?>" class="page-btn">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
