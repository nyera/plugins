<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$attrs       = wc_get_attribute_taxonomies();
$total       = count( $attrs );
$total_values = 0;
$select_count = 0;
$color_count  = 0;
foreach ( $attrs as $attr ) {
    $terms = get_terms( array( 'taxonomy' => 'pa_' . $attr->attribute_name, 'hide_empty' => false ) );
    if ( ! is_wp_error( $terms ) ) $total_values += count( $terms );
    if ( $attr->attribute_type === 'color' ) $color_count++;
    if ( $attr->attribute_type === 'select' ) $select_count++;
}

ob_start();
?>
<div class="page active" id="page-attributes">
    <div class="page-header flex justify-between items-center">
        <div><h1>السمات</h1><p><?php echo esc_html( $labels['attributes_desc'] ); ?></p></div>
        <button class="btn btn-green" onclick="yamamahOpenModal('addAttributeModal')">+ إضافة سمة جديدة</button>
    </div>

    <!-- إحصائيات -->
    <div class="grid grid-4 mb-4">
        <div class="yamamah-stat-card" style="--stat-color:#3498DB;"><p class="stat-value"><?php echo $total; ?></p><p class="stat-label">إجمالي السمات</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#2D9B83;"><p class="stat-value"><?php echo $total_values; ?></p><p class="stat-label">إجمالي القيم</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#6C3FA0;"><p class="stat-value"><?php echo $select_count; ?></p><p class="stat-label">سمات قائمة</p></div>
        <div class="yamamah-stat-card" style="--stat-color:#E67E22;"><p class="stat-value"><?php echo $color_count; ?></p><p class="stat-label">سمات لون</p></div>
    </div>

    <!-- الجدول -->
    <div class="card">
        <div class="table-wrapper">
            <table id="attributesTable">
                <thead><tr>
                    <th style="width:30px;"></th>
                    <th>الاسم</th><th>الاسم اللطيف</th><th>النوع</th><th style="text-align:center">القيم</th><th style="text-align:center">إجراءات</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $attrs as $attr ) :
                    $taxonomy = 'pa_' . $attr->attribute_name;
                    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
                    if ( is_wp_error( $terms ) ) $terms = array();
                    $type_labels = array( 'select' => 'قائمة منسدلة', 'color' => 'لون', 'button' => 'زر', 'image' => 'صورة' );
                    $type_label  = $type_labels[ $attr->attribute_type ] ?? 'قائمة منسدلة';
                ?>
                <tr class="yamamah-expandable-row" data-attr-id="<?php echo esc_attr( $attr->attribute_id ); ?>">
                    <td class="yamamah-expand-toggle" onclick="yamamahToggleAttrValues(this)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </td>
                    <td><strong><?php echo esc_html( $attr->attribute_label ); ?></strong></td>
                    <td><span class="font-mono text-xs" style="background:var(--accent);padding:2px 8px;border-radius:4px;" dir="ltr"><?php echo esc_html( $taxonomy ); ?></span></td>
                    <td><span class="badge badge-blue"><?php echo esc_html( $type_label ); ?></span></td>
                    <td style="text-align:center"><span class="badge badge-green" id="valCount-<?php echo esc_attr( $attr->attribute_id ); ?>"><?php echo count( $terms ); ?></span></td>
                    <td style="text-align:center">
                        <div class="flex gap-1 justify-center">
                            <button onclick="yamamahOpenEditAttribute(<?php echo esc_attr( $attr->attribute_id ); ?>, '<?php echo esc_js( $attr->attribute_label ); ?>', '<?php echo esc_js( $attr->attribute_name ); ?>', '<?php echo esc_js( $attr->attribute_type ); ?>', '<?php echo esc_js( $attr->attribute_orderby ); ?>', <?php echo $attr->attribute_public ? 'true' : 'false'; ?>)" class="yamamah-icon-action" title="تعديل">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            </button>
                            <button onclick="yamamahDeleteAttribute(<?php echo esc_attr( $attr->attribute_id ); ?>, this)" class="yamamah-icon-action text-red" title="حذف">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr class="yamamah-attr-values-row" style="display:none;" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" data-attr-id="<?php echo esc_attr( $attr->attribute_id ); ?>">
                    <td colspan="6">
                        <div class="yamamah-attr-values-container">
                            <div class="yamamah-attr-values-list" id="valsList-<?php echo esc_attr( $attr->attribute_id ); ?>">
                                <?php if ( ! empty( $terms ) ) : foreach ( $terms as $term ) : ?>
                                <span class="yamamah-attr-value-chip" data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
                                    <?php echo esc_html( $term->name ); ?>
                                    <button type="button" class="yamamah-chip-remove" onclick="yamamahDeleteAttrValue(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_attr( $taxonomy ); ?>', this)" title="حذف القيمة">&times;</button>
                                </span>
                                <?php endforeach; else : ?>
                                <p class="text-muted text-xs yamamah-no-values-msg">لا توجد قيم لهذه السمة</p>
                                <?php endif; ?>
                            </div>
                            <div class="yamamah-add-value-row">
                                <input type="text" class="yamamah-add-value-input" id="newVal-<?php echo esc_attr( $attr->attribute_id ); ?>" placeholder="أدخل اسم القيمة الجديدة..." onkeydown="if(event.key==='Enter'){event.preventDefault();yamamahAddAttrValue(<?php echo esc_attr( $attr->attribute_id ); ?>, '<?php echo esc_attr( $taxonomy ); ?>');}">
                                <button type="button" class="btn btn-sm btn-green" onclick="yamamahAddAttrValue(<?php echo esc_attr( $attr->attribute_id ); ?>, '<?php echo esc_attr( $taxonomy ); ?>')">+ إضافة قيمة</button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ( empty( $attrs ) ) : ?>
                <tr><td colspan="6" class="text-center text-muted p-5">لا توجد سمات</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal إضافة سمة -->
<div class="yamamah-modal-overlay" id="addAttributeModal" style="display:none;" onclick="yamamahCloseModal('addAttributeModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h2>إضافة سمة جديدة</h2>
            <button onclick="yamamahCloseModal('addAttributeModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yAttrName" placeholder="مثال: اللون، الحجم"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yAttrSlug" placeholder="attribute-slug" dir="ltr"></div>
            <div class="form-field">
                <label>نوع السمة</label>
                <select id="yAttrType">
                    <option value="select">قائمة منسدلة</option>
                    <option value="color">لون</option>
                    <option value="button">زر</option>
                    <option value="image">صورة</option>
                </select>
            </div>
            <div class="form-field">
                <label>ترتيب القيم</label>
                <select id="yAttrOrderBy">
                    <option value="menu_order">ترتيب مخصص</option>
                    <option value="name">الاسم</option>
                    <option value="name_num">الاسم (رقمي)</option>
                    <option value="id">ID</option>
                </select>
            </div>
            <div class="form-field">
                <label class="yamamah-cb-item">
                    <input type="checkbox" id="yAttrArchive" value="1">
                    تمكين الأرشيف
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahSaveAttribute()">+ إضافة سمة</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('addAttributeModal')">إلغاء</button>
        </div>
    </div>
</div>

<!-- Modal تعديل سمة -->
<div class="yamamah-modal-overlay" id="editAttributeModal" style="display:none;" onclick="yamamahCloseModal('editAttributeModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();">
        <div class="modal-header">
            <h2>تعديل السمة</h2>
            <button onclick="yamamahCloseModal('editAttributeModal')" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="yEditAttrId">
            <div class="form-field"><label>الاسم <span class="required">*</span></label><input type="text" id="yEditAttrName" placeholder="اسم السمة"></div>
            <div class="form-field"><label>الاسم اللطيف</label><input type="text" id="yEditAttrSlug" placeholder="attribute-slug" dir="ltr"></div>
            <div class="form-field">
                <label>نوع السمة</label>
                <select id="yEditAttrType">
                    <option value="select">قائمة منسدلة</option>
                    <option value="color">لون</option>
                    <option value="button">زر</option>
                    <option value="image">صورة</option>
                </select>
            </div>
            <div class="form-field">
                <label>ترتيب القيم</label>
                <select id="yEditAttrOrderBy">
                    <option value="menu_order">ترتيب مخصص</option>
                    <option value="name">الاسم</option>
                    <option value="name_num">الاسم (رقمي)</option>
                    <option value="id">ID</option>
                </select>
            </div>
            <div class="form-field">
                <label class="yamamah-cb-item">
                    <input type="checkbox" id="yEditAttrArchive" value="1">
                    تمكين الأرشيف
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-green" onclick="yamamahUpdateAttribute()">حفظ التعديلات</button>
            <button class="btn btn-outline" onclick="yamamahCloseModal('editAttributeModal')">إلغاء</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    window.yamamahOpenEditAttribute = function(id, name, slug, type, orderBy, archive) {
        $('#yEditAttrId').val(id);
        $('#yEditAttrName').val(name);
        $('#yEditAttrSlug').val(slug);
        $('#yEditAttrType').val(type || 'select');
        $('#yEditAttrOrderBy').val(orderBy || 'menu_order');
        $('#yEditAttrArchive').prop('checked', !!archive);
        yamamahOpenModal('editAttributeModal');
    };

    window.yamamahUpdateAttribute = function() {
        var attrId = $('#yEditAttrId').val();
        if (!attrId) return;
        var name = $.trim($('#yEditAttrName').val());
        if (!name) { alert('الاسم مطلوب'); return; }

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_update_attribute',
            nonce: yamamahData.nonce,
            attribute_id: attrId,
            name: name,
            slug: $('#yEditAttrSlug').val(),
            type: $('#yEditAttrType').val(),
            order_by: $('#yEditAttrOrderBy').val(),
            archive: $('#yEditAttrArchive').is(':checked') ? 1 : 0
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    window.yamamahAddAttrValue = function(attrId, taxonomy) {
        var $input = $('#newVal-' + attrId);
        var name = $.trim($input.val());
        if (!name) { $input.focus(); return; }

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_add_attr_value',
            nonce: yamamahData.nonce,
            taxonomy: taxonomy,
            name: name
        }, function(res) {
            if (res.success) {
                var $list = $('#valsList-' + attrId);
                $list.find('.yamamah-no-values-msg').remove();
                var chip = '<span class="yamamah-attr-value-chip" data-term-id="' + res.data.term_id + '">' +
                    res.data.name +
                    '<button type="button" class="yamamah-chip-remove" onclick="yamamahDeleteAttrValue(' + res.data.term_id + ', \'' + taxonomy + '\', this)" title="حذف القيمة">&times;</button>' +
                    '</span>';
                $list.append(chip);
                $input.val('').focus();
                var $badge = $('#valCount-' + attrId);
                $badge.text(parseInt($badge.text()) + 1);
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    window.yamamahDeleteAttrValue = function(termId, taxonomy, btn) {
        if (!confirm('هل تريد حذف هذه القيمة؟')) return;

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_attr_value',
            nonce: yamamahData.nonce,
            taxonomy: taxonomy,
            term_id: termId
        }, function(res) {
            if (res.success) {
                var $chip = $(btn).closest('.yamamah-attr-value-chip');
                var $list = $chip.closest('.yamamah-attr-values-list');
                var $row = $chip.closest('.yamamah-attr-values-row');
                var attrId = $row.data('attr-id');
                $chip.fadeOut(200, function() {
                    $(this).remove();
                    if (!$list.find('.yamamah-attr-value-chip').length) {
                        $list.append('<p class="text-muted text-xs yamamah-no-values-msg">لا توجد قيم لهذه السمة</p>');
                    }
                });
                var $badge = $('#valCount-' + attrId);
                var c = parseInt($badge.text()) - 1;
                $badge.text(c < 0 ? 0 : c);
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
