<?php
/**
 * صفحة الطلبات
 * المتغير $data يأتي من render_orders()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$orders       = $data['orders'];
$total        = $data['total'];
$total_pages  = $data['pages'];
$current_page = $data['current'];
$counts       = $data['counts'];
$current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'any';

ob_start();
?>
<div class="page active" id="page-orders">
    <div class="page-header">
        <h1>الطلبات</h1>
        <p>إدارة ومتابعة جميع طلبات المتجر</p>
    </div>

    <!-- Status Filters -->
    <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
        <div class="flex gap-2 flex-wrap">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'any' ? 'btn-purple' : 'btn-accent'; ?>">
                الكل (<?php echo esc_html( $counts['all'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=pending' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'pending' ? 'btn-purple' : 'btn-accent'; ?>">
                قيد الانتظار (<?php echo esc_html( $counts['pending'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=processing' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'processing' ? 'btn-purple' : 'btn-accent'; ?>">
                قيد المعالجة (<?php echo esc_html( $counts['processing'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=on-hold' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'on-hold' ? 'btn-purple' : 'btn-accent'; ?>">
                معلق (<?php echo esc_html( $counts['on-hold'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=shipped' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'shipped' ? 'btn-purple' : 'btn-accent'; ?>">
                قيد التوصيل (<?php echo esc_html( $counts['shipped'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=completed' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'completed' ? 'btn-purple' : 'btn-accent'; ?>">
                مكتمل (<?php echo esc_html( $counts['completed'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=cancelled' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'cancelled' ? 'btn-purple' : 'btn-accent'; ?>">
                ملغى (<?php echo esc_html( $counts['cancelled'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=refunded' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'refunded' ? 'btn-purple' : 'btn-accent'; ?>">
                مسترجع (<?php echo esc_html( $counts['refunded'] ); ?>)
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders&status=failed' ) ); ?>"
               class="btn btn-sm <?php echo $current_status === 'failed' ? 'btn-purple' : 'btn-accent'; ?>">
                فشل (<?php echo esc_html( $counts['failed'] ); ?>)
            </a>
        </div>
        <div class="search-box" style="max-width:250px;">
            <input type="text" placeholder="بحث في الطلبات..." id="orderSearch">
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $orders ) ) : ?>
                        <?php foreach ( $orders as $index => $order ) :
                            $detail_url = $order['edit_url'];
                        ?>
                            <tr class="yamamah-clickable-row" data-href="<?php echo esc_url( $detail_url ); ?>">
                                <td><?php echo esc_html( ( $current_page - 1 ) * 20 + $index + 1 ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $detail_url ); ?>" class="yamamah-order-link font-mono text-xs">
                                        #<?php echo esc_html( $order['number'] ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $order['customer'] ); ?></td>
                                <td><?php echo wp_kses_post( $order['total'] ); ?></td>
                                <td>
                                    <span class="badge <?php echo esc_attr( yamamah_order_status_badge_class( $order['status'] ) ); ?>">
                                        <?php echo esc_html( yamamah_order_status_label( $order['status'] ) ); ?>
                                    </span>
                                </td>
                                <td class="text-xs text-muted"><?php echo esc_html( $order['date'] ); ?></td>
                                <td class="text-center">
                                    <div class="yamamah-action-btns">
                                        <a href="<?php echo esc_url( $detail_url ); ?>" class="yamamah-icon-action" title="عرض">
                                            <span data-icon="eye"></span>
                                        </a>
                                        <button type="button" class="yamamah-icon-action" title="طباعة" onclick="event.stopPropagation(); var w=window.open('<?php echo esc_url( $detail_url ); ?>','_blank'); setTimeout(function(){w.print();},1000);">
                                            <span data-icon="printer"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-5">لا توجد طلبات</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        yamamah_pagination(
            $current_page,
            $total_pages,
            admin_url( 'admin.php?page=yamamah-orders' . ( $current_status !== 'any' ? '&status=' . $current_status : '' ) )
        );
        ?>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
