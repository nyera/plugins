<?php
/**
 * صفحة منتجات الديمو
 * المتغير $demo_products يأتي من render_demo_products()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$has_products = ! empty( $demo_products );

ob_start();
?>

<div class="page active" id="page-demo-products">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1><?php echo esc_html( $labels['demo_page_title'] ); ?></h1>
            <p><?php echo esc_html( $labels['demo_page_desc'] ); ?></p>
        </div>
        <div class="flex gap-3">
            <button type="button" class="btn btn-green" id="yamamahRecreateDemoBtn" onclick="yamamahRecreateDemoProducts()">
                حذف القديمة وإنشاء ديمو جديد
            </button>
        </div>
    </div>

    <div id="demoResult" class="alert mb-4" style="display:none;"></div>

    <?php if ( $has_products ) : ?>
        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th><?php echo esc_html( $labels['item_singular'] ); ?></th>
                            <th style="width:120px;">السعر</th>
                            <th>التصنيفات</th>
                            <th style="width:100px;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $demo_products as $i => $row ) : ?>
                            <tr>
                                <td><?php echo (int) ( $i + 1 ); ?></td>
                                <td><?php echo esc_html( $row['title'] ); ?></td>
                                <td><?php echo esc_html( $row['price'] ); ?> ر.س</td>
                                <td><?php echo esc_html( $row['categories'] ?: '—' ); ?></td>
                                <td>
                                    <?php if ( ! empty( $row['edit_url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $row['edit_url'] ); ?>" class="btn btn-sm btn-outline">تعديل</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else : ?>
        <div class="card">
            <div class="text-center p-5">
                <p class="text-muted mb-3"><?php echo esc_html( $labels['demo_empty'] ); ?></p>
                <p class="text-sm text-muted"><?php echo esc_html( $labels['demo_empty_hint'] ); ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function yamamahRecreateDemoProducts() {
    if (!confirm('سيتم حذف جميع المنتجات الديمو القديمة وإنشاء 15 منتج ديمو جديد مع 5 تصنيفات.\nهل تريد المتابعة؟')) return;
    var btn = document.getElementById('yamamahRecreateDemoBtn');
    btn.disabled = true;
    btn.textContent = 'جاري التنفيذ...';
    jQuery.post(yamamahData.ajax_url, {
        action: 'yamamah_recreate_demo',
        nonce: yamamahData.nonce
    }, function(res) {
        btn.disabled = false;
        btn.textContent = 'حذف القديمة وإنشاء ديمو جديد';
        var el = document.getElementById('demoResult');
        if (res.success) {
            el.className = 'alert alert-success mb-4';
            el.textContent = 'تم حذف ' + res.data.deleted + ' منتج قديم وإنشاء ' + res.data.created + ' منتج ديمو جديد.';
            el.style.display = 'block';
            setTimeout(function(){ location.reload(); }, 1500);
        } else {
            el.className = 'alert alert-danger mb-4';
            el.textContent = res.data.message || 'حدث خطأ';
            el.style.display = 'block';
        }
    });
}
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
