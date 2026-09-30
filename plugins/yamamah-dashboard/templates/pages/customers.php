<?php
/**
 * صفحة العملاء
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$customers = $data;

ob_start();
?>

<div class="page active" id="page-customers">
    <div class="page-header">
        <h1>العملاء</h1>
        <p>إدارة قاعدة بيانات العملاء (<?php echo count( $customers ); ?> عميل)</p>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>البريد</th>
                        <th>الطلبات</th>
                        <th>المصروف</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $customers ) ) : ?>
                        <?php foreach ( $customers as $index => $customer ) : ?>
                            <tr>
                                <td><?php echo esc_html( $index + 1 ); ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar"><?php echo esc_html( $customer['initials'] ); ?></div>
                                        <span><?php echo esc_html( $customer['name'] ); ?></span>
                                    </div>
                                </td>
                                <td class="text-xs ltr"><?php echo esc_html( $customer['email'] ); ?></td>
                                <td><?php echo esc_html( $customer['orders'] ); ?></td>
                                <td><?php echo wp_kses_post( $customer['spent'] ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-users' ) ); ?>" class="btn btn-sm btn-outline">عرض</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-5">لا يوجد عملاء مسجلين</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
