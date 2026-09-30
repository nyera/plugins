<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per    = 10;

$args = array( 'taxonomy' => 'product_tag', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' );
if ( $search ) $args['search'] = $search;
$all_tags = get_terms( $args );
if ( is_wp_error( $all_tags ) ) $all_tags = array();

$total       = count( $all_tags );
$total_pages = max( 1, ceil( $total / $per ) );
$tags        = array_slice( $all_tags, ( $paged - 1 ) * $per, $per );
$used_count  = count( array_filter( $all_tags, function($t){ return $t->count > 0; } ) );
$total_usage = array_sum( array_column( $all_tags, 'count' ) );
$max_usage   = $total ? max( array_column( $all_tags, 'count' ) ) : 0;
$base_url    = admin_url( 'admin.php?page=yamamah-tags' );

ob_start();
?>
<div class="page active" id="page-tags">
    <div class="page-header flex justify-between items-center">
        <div><h1>الوسوم</h1><p><?php echo esc_html( $labels['tags_desc'] ); ?></p></div>
        <button class="btn btn-green" onclick="yamamahOpenModal('addTagModal')">+ إضافة وسم جديد</button>
    </div>

    <!-- إحصائيات -->
    <div class="grid grid-4 mb-4">
        <div class="yamamah-stat-card" style="--stat-color:#6C3FA0;"><p class="stat-value"><?php echo $total; ?></p><p class="stat-label">إجمالي الوسوم</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#2D9B83;"><p class="stat-value"><?php echo $used_count; ?></p><p class="stat-label">وسوم مستخدمة</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#E67E22;"><p class="stat-value"><?php echo $total_usage; ?></p><p class="stat-label">إجمالي الاستخدام</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#3498DB;"><p class="stat-value"><?php echo $max_usage; ?></p><p class="stat-label">الأكثر استخداماً</p></div>
    </div>

    <!-- شريط أدوات -->
    <div class="yamamah-toolbar mb-4">
        <div class="toolbar-right">
            <form method="get" action="<?php echo esc_url( $base_url ); ?>" class="flex gap-2 items-center">
                <input type="hidden" name="page" value="yamamah-tags">
                <div class="yamamah-search-input">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="بحث في الوسوم...">
                </div>
                <button type="submit" class="btn btn-outline-green btn-sm">بحث</button>
            </form>
        </div>
    </div>

    <!-- الجدول -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>الاسم</th><th>الوصف</th><th>الاسم اللطيف</th><th style="text-align:center">العدد</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $tags as $tag ) : ?>
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6C3FA0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="#6C3FA0"/></svg>
                            <span><?php echo esc_html( $tag->name ); ?></span>
                        </div>
                        <div class="flex gap-2 mt-1 text-xs">
                            <button onclick="yamamahOpenEditTag(<?php echo esc_attr( $tag->term_id ); ?>, '<?php echo esc_js( $tag->name ); ?>', '<?php echo esc_js( $tag->slug ); ?>', '<?php echo esc_js( $tag->description ); ?>')" style="background:none;font-size:inherit;color:var(--green);cursor:pointer;border:none;">تعديل</button>
                            <button onclick="yamamahDeleteTerm(<?php echo esc_attr( $tag->term_id ); ?>, 'product_tag', this)" class="text-red" style="background:none;font-size:inherit;cursor:pointer;border:none;">حذف</button>
                        </div>
                    </td>
                    <td class="text-xs text-muted"><?php echo esc_html( $tag->description ?: '—' ); ?></td>
                    <td><span class="font-mono text-xs" style="background:var(--accent);padding:2px 8px;border-radius:4px;" dir="ltr"><?php echo esc_html( $tag->slug ); ?></span></td>
                    <td style="text-align:center"><span class="badge badge-purple"><?php echo esc_html( $tag->count ); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $tags ) ) : ?>
                <tr><td colspan="4" class="text-center text-muted p-5">لا توجد وسوم مطابقة للبحث</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="yamamah-pagination mt-4">
        <span class="text-xs text-muted">عرض <?php echo ( $paged - 1 ) * $per + 1; ?> - <?php echo min( $paged * $per, $total ); ?> من <?php echo $total; ?></span>
        <div class="pagination-btns">
            <?php if ( $paged > 1 ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>" class="page-btn">‹</a>
            <?php endif; ?>
            <?php for ( $i = 1; $i <= min( $total_pages, 5 ); $i++ ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>" class="page-btn <?php echo $i === $paged ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ( $paged < $total_pages ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>" class="page-btn">›</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal إضافة وسم -->
<div class="yamamah-modal-overlay" id="addTagModal" style="display:none;" onclick="yamamahCloseModal('addTagModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <div class="flex items-center gap-2">
                <div style="width:36px;height:36px;background:rgba(108,63,160,0.1);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6C3FA0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="#6C3FA0"/></svg>
                </div>
                <h2>إضافة وسم جديد</h2>
            </div>
            <button onclick="yamamahCloseModal('addTagModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yTagName" placeholder="الاسم كما سيظهر في الموقع"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yTagSlug" placeholder="tag-slug" dir="ltr"><p class="field-hint">"الاسم اللطيف" هو الاسم المحرّف من الاسم ليتوافق مع الروابط.</p></div>
            <div class="form-field"><label>الوصف</label><textarea id="yTagDesc" rows="4" placeholder="الوصف لا يظهر بشكل افتراضي لكن بعض القوالب قادرة على إظهاره."></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahAddTerm('product_tag')">+ إضافة وسم</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('addTagModal')">إلغاء</button>
        </div>
    </div>
</div>
<!-- Modal تعديل وسم -->
<div class="yamamah-modal-overlay" id="editTagModal" style="display:none;" onclick="yamamahCloseModal('editTagModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h2>تعديل الوسم</h2>
            <button onclick="yamamahCloseModal('editTagModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="yEditTagId">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yEditTagName" placeholder="اسم الوسم"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yEditTagSlug" placeholder="tag-slug" dir="ltr"></div>
            <div class="form-field"><label>الوصف</label><textarea id="yEditTagDesc" rows="3" placeholder="وصف الوسم"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahUpdateTag()">حفظ التعديلات</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('editTagModal')">إلغاء</button>
        </div>
    </div>
</div>

<script>
(function($){
    window.yamamahOpenEditTag = function(id, name, slug, desc) {
        $('#yEditTagId').val(id);
        $('#yEditTagName').val(name);
        $('#yEditTagSlug').val(slug);
        $('#yEditTagDesc').val(desc);
        yamamahOpenModal('editTagModal');
    };
    window.yamamahUpdateTag = function() {
        var termId = $('#yEditTagId').val();
        if (!termId) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_update_term',
            nonce: yamamahData.nonce,
            term_id: termId,
            taxonomy: 'product_tag',
            name: $('#yEditTagName').val(),
            slug: $('#yEditTagSlug').val(),
            description: $('#yEditTagDesc').val()
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
            }
        });
    };
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
