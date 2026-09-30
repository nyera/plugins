<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'coupons' ) ) {
    $page_content = yamamah_upgrade_banner( 'القسائم' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

$paged = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per   = 20;

$args = array(
    'post_type'      => 'shop_coupon',
    'posts_per_page' => $per,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'post_status'    => 'publish',
);

$query   = new WP_Query( $args );
$coupons = $query->posts;
$total   = $query->found_posts;
$total_pages = $query->max_num_pages;
$base_url = admin_url( 'admin.php?page=yamamah-coupons' );

ob_start();
?>
<div class="page active" id="page-coupons">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>القسائم</h1>
            <p>إدارة كوبونات وخصومات المتجر (<?php echo esc_html( $total ); ?> قسيمة)</p>
        </div>
        <button type="button" class="btn btn-green" onclick="document.getElementById('couponModal').style.display='flex'">+ إضافة قسيمة</button>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>النوع</th>
                        <th>القيمة</th>
                        <th style="text-align:center">الاستخدام</th>
                        <th>تاريخ الانتهاء</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $coupons ) ) : ?>
                        <?php foreach ( $coupons as $coupon_post ) :
                            $coupon = new WC_Coupon( $coupon_post->ID );
                            $type = $coupon->get_discount_type();
                            $type_label = $type === 'percent' ? 'نسبة مئوية' : ( $type === 'fixed_cart' ? 'مبلغ ثابت' : ( $type === 'fixed_product' ? 'خصم منتج' : $type ) );
                            $amount = $coupon->get_amount();
                            $usage = $coupon->get_usage_count();
                            $limit = $coupon->get_usage_limit() ?: '∞';
                            $expiry = $coupon->get_date_expires();
                            $expiry_str = $expiry ? $expiry->date_i18n( 'Y-m-d' ) : 'بدون';
                            $is_expired = $expiry && $expiry->getTimestamp() < time();
                        ?>
                        <tr>
                            <td><code style="background:var(--accent);padding:2px 8px;border-radius:4px;font-weight:600;"><?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></code></td>
                            <td><span class="badge badge-purple"><?php echo esc_html( $type_label ); ?></span></td>
                            <td><?php echo $type === 'percent' ? esc_html( $amount ) . '%' : wp_kses_post( wc_price( $amount ) ); ?></td>
                            <td style="text-align:center"><?php echo esc_html( $usage ); ?> / <?php echo esc_html( $limit ); ?></td>
                            <td class="text-xs text-muted"><?php echo esc_html( $expiry_str ); ?></td>
                            <td>
                                <?php if ( $is_expired ) : ?>
                                    <span class="badge badge-red">منتهي</span>
                                <?php else : ?>
                                    <span class="badge badge-green">نشط</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="yamamah-action-btns">
                                    <a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $coupon_post->ID . '&action=edit' ) ); ?>" class="yamamah-icon-action" title="تعديل">
                                        <span data-icon="edit"></span>
                                    </a>
                                    <button type="button" class="yamamah-icon-action" title="حذف" onclick="yamamahDeleteCoupon(<?php echo esc_attr( $coupon_post->ID ); ?>, this)">
                                        <span data-icon="trash"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-5">لا توجد قسائم</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="yamamah-pagination mt-4">
            <span class="text-xs text-muted">عرض <?php echo ( $paged - 1 ) * $per + 1; ?> - <?php echo min( $paged * $per, $total ); ?> من <?php echo $total; ?></span>
            <div class="pagination-btns">
                <?php if ( $paged > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>" class="page-btn">&laquo;</a>
                <?php endif; ?>
                <?php for ( $i = 1; $i <= min( $total_pages, 5 ); $i++ ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="page-btn <?php echo $i === $paged ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ( $paged < $total_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>" class="page-btn">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Coupon Modal -->
<div class="yamamah-modal-overlay" id="couponModal" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="yamamah-modal" style="max-width:540px;">
        <div class="modal-header">
            <h2>إضافة قسيمة جديدة</h2>
            <button type="button" class="modal-close" onclick="document.getElementById('couponModal').style.display='none'">&times;</button>
        </div>
        <form id="couponForm">
            <div class="modal-body">
                <div class="form-group">
                    <label>كود القسيمة <span style="color:#ef4444;">*</span></label>
                    <input type="text" class="form-control" name="code" placeholder="مثال: SAVE20" required style="text-transform:uppercase;">
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>نوع الخصم</label>
                        <select class="form-control" name="discount_type">
                            <option value="percent">نسبة مئوية (%)</option>
                            <option value="fixed_cart">مبلغ ثابت (سلة)</option>
                            <option value="fixed_product">مبلغ ثابت (منتج)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>قيمة الخصم <span style="color:#ef4444;">*</span></label>
                        <input type="number" class="form-control" name="amount" min="0" step="0.01" placeholder="0" required>
                    </div>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>الحد الأقصى للاستخدام</label>
                        <input type="number" class="form-control" name="usage_limit" min="0" step="1" placeholder="بدون حد">
                    </div>
                    <div class="form-group">
                        <label>الحد لكل عميل</label>
                        <input type="number" class="form-control" name="usage_limit_per_user" min="0" step="1" placeholder="بدون حد">
                    </div>
                </div>
                <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>الحد الأدنى للطلب</label>
                        <input type="number" class="form-control" name="minimum_amount" min="0" step="0.01" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>تاريخ الانتهاء</label>
                        <input type="date" class="form-control ltr" name="expiry_date" lang="en" dir="ltr"    min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" >                 </div>
                </div>

                <div class="form-group">
                    <label class="flex items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="free_shipping" value="1">
                        شحن مجاني
                    </label>
                </div>
                <div class="form-group">
                    <label class="flex items-center gap-2" style="cursor:pointer;">
                        <input type="checkbox" name="individual_use" value="1">
                        استخدام فردي فقط (لا يمكن دمجها مع قسائم أخرى)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('couponModal').style.display='none'">إلغاء</button>
                <button type="submit" class="btn btn-green" id="saveCouponBtn">إنشاء القسيمة</button>
            </div>
        </form>
    </div>
</div>

<script>
(function($){
    // Show modal
    window.yamamahOpenCouponModal = function(){
        $('#couponModal').css('display','flex');
    };
    // Open via button
    $('.btn-green:contains("إضافة قسيمة")').off('click');

    // Create coupon
    $('#couponForm').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#saveCouponBtn').prop('disabled', true).text('جارٍ الإنشاء...');
        var formData = $(this).serializeArray();
        formData.push({name:'action', value:'yamamah_create_coupon'});
        formData.push({name:'nonce', value: yamamahData.nonce});
        $.post(yamamahData.ajax_url, formData, function(res){
            $btn.prop('disabled', false).text('إنشاء القسيمة');
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
            }
        }).fail(function(){ $btn.prop('disabled', false).text('إنشاء القسيمة'); });
    });

    // Delete coupon
    window.yamamahDeleteCoupon = function(id, el){
        if (!confirm('هل أنت متأكد من حذف هذه القسيمة؟')) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_coupon',
            nonce: yamamahData.nonce,
            coupon_id: id
        }, function(res){
            if (res.success) {
                $(el).closest('tr').fadeOut(300, function(){ $(this).remove(); });
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
            }
        });
    };

    // Open modal - show class
    $('#couponModal').on('click', function(e){
        if (e.target === this) $(this).css('display','none');
    });
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
