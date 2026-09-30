<?php
/**
 * صفحة المنتجات
 * المتغير $data يأتي من render_products()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$labels       = yamamah_get_catalog_labels();
$products     = $data['products'];
$total        = $data['total'];
$total_pages  = $data['pages'];
$current_page = $data['current'];

ob_start();
?>

<div class="page active" id="page-products">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1><?php echo esc_html( $labels['all_items'] ); ?></h1>
            <p><?php echo esc_html( sprintf( $labels['manage_line'], $total ) ); ?></p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-add-product' ) ); ?>" class="btn btn-green"><?php echo esc_html( $labels['add_item_btn'] ); ?></a>
    </div>

    <div class="card">
        <div class="bulk-actions-bar" id="bulkActionsBar" style="display:none;">
            <div class="bulk-info">
                <span id="bulkSelectedCount">0</span> <?php echo esc_html( $labels['bulk_selected_suffix'] ); ?>
            </div>
            <div class="bulk-buttons">
                <button type="button" class="btn btn-sm btn-outline" onclick="yamamahBulkAction('publish')">نشر</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="yamamahBulkAction('draft')">مسودة</button>
                <button type="button" class="btn btn-sm btn-outline btn-danger" onclick="yamamahBulkAction('trash')">حذف</button>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllProducts"></th>
                        <th><?php echo esc_html( $labels['item_singular'] ); ?></th>
                        <th>SKU</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>التصنيف</th>
                        <th>الوسوم</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $products ) ) : ?>
                        <?php foreach ( $products as $product ) : ?>
                            <tr>
                                <td><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $product['id'] ); ?>"></td>
                                <td>
                                    <a href="<?php echo esc_url( $product['edit_url'] ); ?>" class="flex items-center gap-2" style="text-decoration:none;color:inherit;">
                                        <?php if ( $product['image'] ) : ?>
                                            <img src="<?php echo esc_url( $product['image'] ); ?>" alt="" style="width:32px;height:32px;border-radius:4px;object-fit:cover;">
                                        <?php endif; ?>
                                        <span style="color:var(--green);font-weight:500;"><?php echo esc_html( $product['name'] ); ?></span>
                                    </a>
                                </td>
                                <td class="text-xs font-mono ltr"><?php echo esc_html( $product['sku'] ?: '-' ); ?></td>
                                <td><?php echo wp_kses_post( $product['price'] ); ?></td>
                                <td>
                                    <?php
                                    if ( $product['stock'] !== null ) {
                                        echo esc_html( $product['stock'] );
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html( $product['category'] ); ?></td>
                                <td><?php echo esc_html( isset( $product['tags'] ) ? $product['tags'] : '-' ); ?></td>
                                <td><?php echo yamamah_product_status_badge( $product['status'], $product['stock_status'] ); ?></td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="<?php echo esc_url( $product['edit_url'] ); ?>" class="btn btn-sm btn-outline">تعديل</a>
                                        <button type="button" class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red);" onclick="yamamahDeleteSingleProduct(<?php echo esc_attr( $product['id'] ); ?>, this)">حذف</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted p-5"><?php echo esc_html( $labels['empty_list'] ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        yamamah_pagination(
            $current_page,
            $total_pages,
            admin_url( 'admin.php?page=yamamah-products' )
        );
        ?>
    </div>
</div>

<script>
window.yamamahDeleteSingleProduct = function(productId, btn) {
    if (!confirm(<?php echo wp_json_encode( $labels['delete_confirm'] ); ?>)) return;
    jQuery.post(yamamahData.ajax_url, {
        action: 'yamamah_delete_product',
        nonce: yamamahData.nonce,
        product_id: productId
    }, function(res) {
        if (res.success) {
            jQuery(btn).closest('tr').fadeOut(300, function(){ jQuery(this).remove(); });
        } else {
            var msg = res.data && res.data.message ? res.data.message : (typeof res.data === 'string' ? res.data : 'حدث خطأ أثناء الحذف');
            alert(msg);
        }
    }).fail(function(xhr) {
        alert('خطأ في الاتصال بالسيرفر (' + xhr.status + ')');
    });
};
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
