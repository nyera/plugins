<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$search   = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$view     = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'grid';
$args     = array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' );
if ( $search ) $args['search'] = $search;
$terms    = get_terms( $args );
if ( is_wp_error( $terms ) ) $terms = array();
$all_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>name' ) );
if ( is_wp_error( $all_cats ) ) $all_cats = array();
$base_url = admin_url( 'admin.php?page=yamamah-categories' );

ob_start();
?>
<div class="page active" id="page-categories">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>التصنيفات</h1>
            <p><?php echo esc_html( $labels['categories_desc'] ); ?></p>
        </div>
        <button class="btn btn-green" onclick="yamamahOpenModal('addCategoryModal')">+ إضافة تصنيف جديد</button>
    </div>

    <!-- شريط الأدوات -->
    <div class="yamamah-toolbar">
        <div class="toolbar-right">
            <form method="get" action="<?php echo esc_url( $base_url ); ?>" class="flex gap-2 items-center">
                <input type="hidden" name="page" value="yamamah-categories">
                <input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
                <div class="yamamah-search-input">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="بحث">
                </div>
                <button type="submit" class="btn btn-outline-green btn-sm">بحث</button>
            </form>
        </div>
        <div class="toolbar-left flex gap-2 items-center">
            <div class="yamamah-view-toggle">
                <a href="<?php echo esc_url( add_query_arg( 'view', 'list', $base_url ) ); ?>" class="<?php echo $view === 'list' ? 'active' : ''; ?>" title="قائمة">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'view', 'grid', $base_url ) ); ?>" class="<?php echo $view === 'grid' ? 'active' : ''; ?>" title="شبكة">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                </a>
            </div>
        </div>
    </div>

    <?php if ( $view === 'grid' ) : ?>
    <!-- عرض الشبكة -->
    <div class="grid grid-3">
        <?php foreach ( $terms as $cat ) : ?>
        <div class="card p-4 yamamah-cat-card">
            <div class="flex gap-1 mb-3">
                <button onclick="yamamahOpenEditCategory(<?php echo esc_attr( $cat->term_id ); ?>, '<?php echo esc_js( $cat->name ); ?>', '<?php echo esc_js( $cat->slug ); ?>', <?php echo esc_attr( $cat->parent ); ?>, '<?php echo esc_js( $cat->description ); ?>')" class="yamamah-icon-action" title="تعديل">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                </button>
                <button onclick="yamamahDeleteTerm(<?php echo esc_attr( $cat->term_id ); ?>, 'product_cat', this)" class="yamamah-icon-action text-red" title="حذف">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
            </div>
            <h3 style="font-size:1rem;"><?php echo esc_html( $cat->name ); ?></h3>
            <p class="text-xs text-muted"><?php echo esc_html( $cat->description ?: '—' ); ?></p>
            <div style="text-align:center;margin-top:12px;">
                <span class="badge badge-green"><?php echo esc_html( sprintf( $labels['category_count'], $cat->count ) ); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ( empty( $terms ) ) : ?>
        <div class="col-span-full text-center text-muted p-5">لا توجد تصنيفات</div>
        <?php endif; ?>
    </div>
    <?php else : ?>
    <!-- عرض القائمة -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>الاسم</th><th>الوصف</th><th>التصنيف الأب</th><th style="text-align:center"><?php echo esc_html( $labels['categories_column'] ); ?></th><th style="text-align:center">إجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $terms as $cat ) :
                    $parent_name = $cat->parent ? ( get_term( $cat->parent, 'product_cat' )->name ?? 'بدون' ) : 'بدون';
                ?>
                <tr>
                    <td><?php echo esc_html( $cat->name ); ?></td>
                    <td class="text-xs text-muted"><?php echo esc_html( mb_strimwidth( $cat->description, 0, 60, '...' ) ?: '—' ); ?></td>
                    <td class="text-muted"><?php echo esc_html( $parent_name ); ?></td>
                    <td style="text-align:center"><span class="badge badge-green"><?php echo esc_html( $cat->count ); ?></span></td>
                    <td style="text-align:center">
                        <div class="yamamah-action-btns">
                            <button onclick="yamamahOpenEditCategory(<?php echo esc_attr( $cat->term_id ); ?>, '<?php echo esc_js( $cat->name ); ?>', '<?php echo esc_js( $cat->slug ); ?>', <?php echo esc_attr( $cat->parent ); ?>, '<?php echo esc_js( $cat->description ); ?>')" class="yamamah-icon-action" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            </button>
                            <button onclick="yamamahDeleteTerm(<?php echo esc_attr( $cat->term_id ); ?>, 'product_cat', this)" class="yamamah-icon-action text-red" title="حذف">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $terms ) ) : ?>
                <tr><td colspan="5" class="text-center text-muted p-5">لا توجد تصنيفات</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal إضافة تصنيف -->
<div class="yamamah-modal-overlay" id="addCategoryModal" style="display:none;" onclick="yamamahCloseModal('addCategoryModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h2>إضافة تصنيف جديد</h2>
            <button onclick="yamamahCloseModal('addCategoryModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yCatName" placeholder="الاسم كما سيظهر في الموقع"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yCatSlug" placeholder="slug" dir="ltr"></div>
            <div class="form-field">
                <label>التصنيف الأب</label>
                <select id="yCatParent">
                    <option value="0">بدون</option>
                    <?php foreach ( $all_cats as $cid => $cname ) : ?>
                    <option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $cname ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>الوصف</label><textarea id="yCatDesc" rows="4" placeholder="الوصف لا يظهر بشكل افتراضي لكن بعض القوالب قادرة على إظهاره."></textarea></div>
            <div class="form-field">
                <label>الصورة البارزة للتصنيف</label>
                <div id="yCatImageWrap">
                    <button type="button" class="btn btn-outline-green btn-sm" onclick="yamamahUploadCatImage()">رفع أو إضافة صورة</button>
                </div>
                <input type="hidden" id="yCatImageId" value="0">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahAddTerm('product_cat')">إضافة</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('addCategoryModal')">إلغاء</button>
        </div>
    </div>
</div>
<!-- Modal تعديل تصنيف -->
<div class="yamamah-modal-overlay" id="editCategoryModal" style="display:none;" onclick="yamamahCloseModal('editCategoryModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h2>تعديل التصنيف</h2>
            <button onclick="yamamahCloseModal('editCategoryModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="yEditCatId">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yEditCatName" placeholder="اسم التصنيف"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yEditCatSlug" placeholder="slug" dir="ltr"></div>
            <div class="form-field">
                <label>التصنيف الأب</label>
                <select id="yEditCatParent">
                    <option value="0">بدون</option>
                    <?php foreach ( $all_cats as $cid => $cname ) : ?>
                    <option value="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $cname ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>الوصف</label><textarea id="yEditCatDesc" rows="4" placeholder="وصف التصنيف"></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahUpdateCategory()">حفظ التعديلات</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('editCategoryModal')">إلغاء</button>
        </div>
    </div>
</div>

<script>
(function($){
    window.yamamahOpenEditCategory = function(id, name, slug, parent, desc) {
        $('#yEditCatId').val(id);
        $('#yEditCatName').val(name);
        $('#yEditCatSlug').val(slug);
        $('#yEditCatParent').val(parent);
        $('#yEditCatDesc').val(desc);
        yamamahOpenModal('editCategoryModal');
    };

    window.yamamahUpdateCategory = function() {
        var termId = $('#yEditCatId').val();
        if (!termId) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_update_term',
            nonce: yamamahData.nonce,
            term_id: termId,
            taxonomy: 'product_cat',
            name: $('#yEditCatName').val(),
            slug: $('#yEditCatSlug').val(),
            parent: $('#yEditCatParent').val(),
            description: $('#yEditCatDesc').val()
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
