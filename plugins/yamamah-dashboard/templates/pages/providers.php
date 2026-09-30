<?php
/**
 * صفحة مزودي الخدمات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$providers = get_posts( array(
    'post_type'      => 'service_provider',
    'posts_per_page' => -1,
    'post_status'    => array( 'publish', 'draft' ),
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

ob_start();
?>

<div class="page active" id="page-providers">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>مزودي الخدمات</h1>
            <p>إدارة مزودي الخدمات (<?php echo count( $providers ); ?> مزود خدمة)</p>
        </div>
        <button type="button" class="btn btn-green" onclick="yamamahShowAddProvider()">+ إضافة مزود خدمة</button>
    </div>

    <!-- نموذج الإضافة/التعديل -->
    <div class="card mb-4" id="providerFormCard" style="display:none;">
        <div class="card-section-title" id="providerFormTitle">إضافة مزود خدمة جديد</div>
        <input type="hidden" id="editProviderId" value="0">

        <div class="form-field" style="text-align:center;">
            <label>صورة مزود الخدمة</label>
            <div class="yamamah-product-image-upload" id="providerImageUpload" onclick="yamamahUploadProviderImage()" style="width:120px;height:120px;margin:0 auto;">
                <div class="upload-empty" id="providerImageEmpty">
                    <div class="upload-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>
            </div>
            <input type="hidden" id="providerImageId" value="0">
        </div>

        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>اسم مزود الخدمة <span class="required">*</span></label>
                <input type="text" id="providerName" placeholder="مثال: د. أحمد محمد">
            </div>
            <div class="form-field">
                <label>التخصص</label>
                <input type="text" id="providerSpecialty" placeholder="مثال: طبيب أسنان">
            </div>
        </div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>رقم الجوال</label>
                <input type="text" id="providerPhone" placeholder="05xxxxxxxx" dir="ltr">
            </div>
            <div class="form-field">
                <label>البريد الإلكتروني (دخول التطبيق) <span class="required">*</span></label>
                <input type="email" id="providerEmail" placeholder="email@example.com" dir="ltr">
            </div>
        </div>
        <div class="form-field">
            <label>الوصف</label>
            <textarea id="providerDesc" rows="3" placeholder="نبذة مختصرة عن مزود الخدمة"></textarea>
        </div>
        <div class="form-field">
            <label>الحالة</label>
            <select id="providerStatus">
                <option value="publish">نشط</option>
                <option value="draft">غير نشط</option>
            </select>
        </div>
        <div class="yamamah-form-row-2" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border,#e5e7eb);">
            <div class="form-field">
                <label id="providerPasswordLabel">كلمة مرور التطبيق <span class="required">*</span></label>
                <input type="password" id="providerAppPassword" placeholder="كلمة المرور لتسجيل الدخول" dir="ltr" autocomplete="new-password">
                <p class="text-xs text-muted" id="providerPasswordHint" style="margin-top:4px;">يستخدمها مزود الخدمة لدخول تطبيق التاجر</p>
            </div>
            <div class="form-field">
                <label>حالة الحساب</label>
                <p id="providerAppStatus" class="text-sm" style="margin-top:8px;">—</p>
                <input type="hidden" id="providerHasAppAccount" value="0">
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="button" class="btn btn-green" onclick="yamamahSaveProvider()">حفظ</button>
            <button type="button" class="btn btn-outline" onclick="yamamahCancelProvider()">إلغاء</button>
        </div>
    </div>

    <!-- جدول مزودي الخدمات -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>مزود الخدمة</th>
                        <th>التخصص</th>
                        <th>الجوال</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $providers ) ) : ?>
                        <?php foreach ( $providers as $i => $provider ) :
                            $p_status = $provider->post_status;
                            $p_specialty = get_post_meta( $provider->ID, '_provider_specialty', true );
                            $p_phone = get_post_meta( $provider->ID, '_provider_phone', true );
                            $p_email = get_post_meta( $provider->ID, '_provider_email', true );
                            $p_image_id = get_post_thumbnail_id( $provider->ID );
                            $p_image_url = $p_image_id ? wp_get_attachment_image_url( $p_image_id, 'thumbnail' ) : '';
                        ?>
                            <tr>
                                <td><?php echo (int) ( $i + 1 ); ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <?php if ( $p_image_url ) : ?>
                                            <img src="<?php echo esc_url( $p_image_url ); ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                        <?php else : ?>
                                            <div style="width:32px;height:32px;border-radius:50%;background:var(--accent,#f3f4f6);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:var(--muted-fg,#6b7280);">
                                                <?php echo esc_html( mb_substr( $provider->post_title, 0, 1 ) ); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo esc_html( $provider->post_title ); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html( $p_specialty ?: '-' ); ?></td>
                                <td class="yamamah-phone-cell font-mono ltr text-right"><?php echo esc_html( $p_phone ?: '-' ); ?></td>
                                <td>
                                    <?php if ( $p_status === 'publish' ) : ?>
                                        <span class="badge badge-green">نشط</span>
                                    <?php else : ?>
                                        <span class="badge badge-gray">غير نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline" onclick="yamamahEditProvider(<?php echo esc_attr( $provider->ID ); ?>)">تعديل</button>
                                        <button type="button" class="btn btn-sm btn-outline" style="color:var(--red);" onclick="yamamahDeleteProvider(<?php echo esc_attr( $provider->ID ); ?>)">حذف</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-5">لا يوجد مزودي خدمات</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    window.yamamahShowAddProvider = function() {
        $('#editProviderId').val(0);
        $('#providerFormTitle').text('إضافة مزود خدمة جديد');
        $('#providerName, #providerSpecialty, #providerPhone, #providerEmail, #providerDesc, #providerAppPassword').val('');
        $('#providerStatus').val('publish');
        $('#providerImageId').val(0);
        $('#providerHasAppAccount').val('0');
        $('#providerAppStatus').text('سيتم إنشاء حساب دخول عند الحفظ');
        $('#providerPasswordLabel').html('كلمة مرور التطبيق <span class="required">*</span>');
        $('#providerPasswordHint').text('يستخدمها مزود الخدمة لدخول تطبيق التاجر');
        $('#providerImageUpload').html('<div class="upload-empty"><div class="upload-icon-box"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div></div>');
        $('#providerFormCard').slideDown();
    };

    window.yamamahCancelProvider = function() {
        $('#providerFormCard').slideUp();
    };

    window.yamamahEditProvider = function(id) {
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_get_provider',
            nonce: yamamahData.nonce,
            provider_id: id
        }, function(res) {
            if (!res.success) { alert(res.data || 'خطأ'); return; }
            var d = res.data;
            $('#editProviderId').val(d.id);
            $('#providerFormTitle').text('تعديل مزود الخدمة');
            $('#providerName').val(d.name);
            $('#providerSpecialty').val(d.specialty);
            $('#providerPhone').val(d.phone);
            $('#providerEmail').val(d.email);
            $('#providerDesc').val(d.description);
            $('#providerStatus').val(d.status);
            $('#providerImageId').val(d.image_id || 0);
            $('#providerAppPassword').val('');
            var hasApp = !!d.has_app_account;
            $('#providerHasAppAccount').val(hasApp ? '1' : '0');
            $('#providerAppStatus').text(hasApp ? 'مرتبط بحساب تطبيق' : 'لا يوجد حساب بعد — سيُنشأ عند الحفظ');
            $('#providerPasswordLabel').html(hasApp ? 'كلمة مرور جديدة (اختياري)' : 'كلمة مرور التطبيق <span class="required">*</span>');
            $('#providerPasswordHint').text(hasApp ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية' : 'يستخدمها مزود الخدمة لدخول تطبيق التاجر');
            if (d.image_url) {
                $('#providerImageUpload').html('<div class="upload-preview"><img src="' + d.image_url + '" alt="" style="border-radius:50%;"><button type="button" class="upload-remove" onclick="event.stopPropagation(); yamamahRemoveProviderImage();">&times;</button></div>');
            }
            $('#providerFormCard').slideDown();
            $('html, body').animate({ scrollTop: $('#providerFormCard').offset().top - 80 }, 300);
        });
    };

    window.yamamahSaveProvider = function() {
        var name = $.trim($('#providerName').val());
        if (!name) { alert('اسم مزود الخدمة مطلوب'); return; }
        var email = $.trim($('#providerEmail').val());
        if (!email) { alert('البريد الإلكتروني مطلوب لتسجيل دخول مزود الخدمة'); return; }
        var pass = $.trim($('#providerAppPassword').val());
        var hasApp = $('#providerHasAppAccount').val() === '1';
        if (!hasApp && !pass) { alert('أدخل كلمة مرور ليستطيع مزود الخدمة تسجيل الدخول'); return; }

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_provider',
            nonce: yamamahData.nonce,
            provider_id: $('#editProviderId').val(),
            name: name,
            specialty: $('#providerSpecialty').val(),
            phone: $('#providerPhone').val(),
            email: email,
            description: $('#providerDesc').val(),
            status: $('#providerStatus').val(),
            image_id: $('#providerImageId').val(),
            create_app_account: 1,
            app_password: pass
        }, function(res) {
            if (res.success) {
                alert('تم الحفظ بنجاح');
                location.reload();
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    window.yamamahDeleteProvider = function(id) {
        if (!confirm('هل أنت متأكد من حذف مزود الخدمة؟')) return;
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_provider',
            nonce: yamamahData.nonce,
            provider_id: id
        }, function(res) {
            if (res && res.success) { location.reload(); }
            else { alert((res && res.data) ? res.data : 'حدث خطأ'); }
        }, 'json').fail(function(xhr) {
            var msg = 'خطأ في الاتصال أو الاستجابة';
            if (xhr && xhr.responseText && xhr.responseText.indexOf('0') === 0) { msg = 'العملية غير متاحة (تحقق من تسجيل الدخول أو الصلاحيات)'; }
            alert(msg);
        });
    };

    window.yamamahUploadProviderImage = function() {
        var frame = wp.media({ title: 'اختر صورة مزود الخدمة', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            $('#providerImageId').val(att.id);
            var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $('#providerImageUpload').html('<div class="upload-preview"><img src="' + url + '" alt="" style="border-radius:50%;"><button type="button" class="upload-remove" onclick="event.stopPropagation(); yamamahRemoveProviderImage();">&times;</button></div>');
        });
        frame.open();
    };

    window.yamamahRemoveProviderImage = function() {
        $('#providerImageId').val(0);
        $('#providerImageUpload').html('<div class="upload-empty"><div class="upload-icon-box"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div></div>');
    };
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
