<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'api_keys' ) ) {
    $page_content = yamamah_upgrade_banner( 'مفاتيح API' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

ob_start();

$perm_labels = array(
    'read'       => 'قراءة',
    'write'      => 'كتابة',
    'read_write' => 'قراءة وكتابة',
);
?>
<div class="page active" id="page-settings-api-keys">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>مفاتيح API</h1>
            <p>إدارة مفاتيح الوصول للربط مع التطبيقات والأنظمة الخارجية</p>
        </div>
        <button type="button" class="btn btn-green" id="openCreateKeyModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إنشاء مفتاح جديد
        </button>
    </div>

    <!-- تنبيه أمني -->
    <div class="card mb-4" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#f59e0b;">
        <div class="flex gap-3 items-start" style="padding:1rem 1.25rem;">
            <span style="font-size:1.3rem;flex-shrink:0;">🔐</span>
            <div>
                <strong class="text-sm" style="color:#92400e;">ملاحظة أمنية</strong>
                <p class="text-xs" style="color:#92400e;margin:4px 0 0;line-height:1.6;">مفاتيح API تمنح صلاحيات الوصول لبيانات متجرك. لا تشارك المفاتيح مع أي جهة غير موثوقة. مفتاح العميل والمفتاح السري يظهران <strong>مرة واحدة فقط</strong> عند الإنشاء — انسخهما فوراً.</p>
            </div>
        </div>
    </div>

    <!-- جدول المفاتيح -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">المفاتيح النشطة</h3>
        </div>
        <?php if ( empty( $keys ) ) : ?>
            <div class="text-center p-5 text-muted">
                <p style="font-size:2rem;margin-bottom:0.5rem;">🔑</p>
                <p>لا توجد مفاتيح API حالياً</p>
                <small>أنشئ مفتاحاً جديداً للربط مع التطبيقات الخارجية</small>
            </div>
        <?php else : ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الوصف</th>
                        <th>المفتاح</th>
                        <th>الصلاحية</th>
                        <th>المستخدم</th>
                        <th>آخر استخدام</th>
                        <th style="text-align:center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $keys as $key ) :
                        $user = get_userdata( $key->user_id );
                        $user_name = $user ? $user->display_name : '#' . $key->user_id;
                        $perm_label = $perm_labels[ $key->permissions ] ?? $key->permissions;

                        $perm_badge = 'badge-gray';
                        if ( $key->permissions === 'read_write' ) $perm_badge = 'badge-green';
                        elseif ( $key->permissions === 'write' ) $perm_badge = 'badge-yellow';
                        elseif ( $key->permissions === 'read' ) $perm_badge = 'badge-blue';

                        $last_access = $key->last_access
                            ? human_time_diff( strtotime( $key->last_access ), current_time( 'timestamp' ) ) . ' مضت'
                            : 'لم يُستخدم بعد';
                    ?>
                    <tr data-key-id="<?php echo esc_attr( $key->key_id ); ?>">
                        <td>
                            <strong class="text-sm"><?php echo esc_html( $key->description ?: 'بدون وصف' ); ?></strong>
                        </td>
                        <td>
                            <code style="background:var(--accent,#f3f4f6);padding:3px 8px;border-radius:4px;font-size:0.8rem;letter-spacing:1px;">...<?php echo esc_html( $key->truncated_key ); ?></code>
                        </td>
                        <td><span class="badge <?php echo $perm_badge; ?>"><?php echo esc_html( $perm_label ); ?></span></td>
                        <td><span class="text-sm"><?php echo esc_html( $user_name ); ?></span></td>
                        <td><span class="text-sm text-muted"><?php echo esc_html( $last_access ); ?></span></td>
                        <td style="text-align:center;">
                            <button type="button" class="btn btn-sm btn-outline revoke-key-btn" data-key-id="<?php echo esc_attr( $key->key_id ); ?>" style="color:var(--red,#ef4444);" title="إلغاء المفتاح">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                إلغاء
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Modal: إنشاء مفتاح جديد ── -->
<div class="yamamah-modal-overlay" id="createKeyModal" style="display:none;" onclick="if(event.target===this)jQuery(this).fadeOut(200);">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:500px;">
        <div class="yamamah-modal-header">
            <h3>إنشاء مفتاح API جديد</h3>
            <button type="button" class="yamamah-modal-close" onclick="jQuery('#createKeyModal').fadeOut(200);">&times;</button>
        </div>
        <div class="yamamah-modal-body" id="createKeyForm">
            <div class="form-group">
                <label>الوصف <span style="color:var(--red);">*</span></label>
                <input type="text" class="form-control" id="keyDescription" placeholder="مثال: تطبيق الجوال، ربط مع نظام ERP..." maxlength="200">
                <small class="text-xs text-muted">وصف يساعدك على تذكر الغرض من هذا المفتاح</small>
            </div>
            <div class="form-group">
                <label>الصلاحيات <span style="color:var(--red);">*</span></label>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid var(--border,#e5e7eb);border-radius:8px;transition:all 0.15s;">
                        <input type="radio" name="keyPermission" value="read" style="accent-color:var(--green,#10b981);">
                        <div>
                            <strong class="text-sm">قراءة</strong>
                            <div class="text-xs text-muted">الوصول لقراءة البيانات فقط (المنتجات، الطلبات، العملاء)</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid var(--border,#e5e7eb);border-radius:8px;transition:all 0.15s;">
                        <input type="radio" name="keyPermission" value="write" style="accent-color:var(--green,#10b981);">
                        <div>
                            <strong class="text-sm">كتابة</strong>
                            <div class="text-xs text-muted">إنشاء وتعديل البيانات (إضافة منتجات، تحديث طلبات)</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid var(--border,#e5e7eb);border-radius:8px;transition:all 0.15s;">
                        <input type="radio" name="keyPermission" value="read_write" checked style="accent-color:var(--green,#10b981);">
                        <div>
                            <strong class="text-sm">قراءة وكتابة</strong>
                            <div class="text-xs text-muted">صلاحيات كاملة للقراءة والكتابة</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <div class="yamamah-modal-footer">
            <button type="button" class="btn btn-green" id="generateKeyBtn">إنشاء المفتاح</button>
            <span id="createKeyMsg" style="font-size:0.85rem;margin-right:0.5rem;"></span>
        </div>
    </div>
</div>

<!-- ── Modal: عرض المفاتيح بعد الإنشاء ── -->
<div class="yamamah-modal-overlay" id="showKeysModal" style="display:none;" onclick="event.stopPropagation();">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:560px;">
        <div class="yamamah-modal-header" style="background:#fef3c7;border-bottom-color:#f59e0b;">
            <h3 style="color:#92400e;">انسخ المفاتيح الآن</h3>
        </div>
        <div class="yamamah-modal-body">
            <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <p class="text-sm" style="color:#92400e;margin:0;line-height:1.6;">
                    <strong>تحذير:</strong> لن تتمكن من رؤية هذه المفاتيح مجدداً بعد إغلاق هذه النافذة. انسخهما واحفظهما في مكان آمن.
                </p>
            </div>

            <div class="form-group">
                <label style="font-weight:600;">مفتاح العميل (Consumer Key)</label>
                <div class="flex gap-2">
                    <input type="text" class="form-control" id="showConsumerKey" readonly style="direction:ltr;text-align:left;font-family:monospace;font-size:0.82rem;background:var(--accent,#f3f4f6);">
                    <button type="button" class="btn btn-sm btn-outline copy-key-btn" data-target="showConsumerKey" title="نسخ" style="flex-shrink:0;height:38px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label style="font-weight:600;">المفتاح السري (Consumer Secret)</label>
                <div class="flex gap-2">
                    <input type="text" class="form-control" id="showConsumerSecret" readonly style="direction:ltr;text-align:left;font-family:monospace;font-size:0.82rem;background:var(--accent,#f3f4f6);">
                    <button type="button" class="btn btn-sm btn-outline copy-key-btn" data-target="showConsumerSecret" title="نسخ" style="flex-shrink:0;height:38px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>
            </div>

            <div style="background:var(--accent,#f3f4f6);border-radius:8px;padding:12px 16px;margin-top:8px;">
                <p class="text-xs text-muted" style="margin:0;line-height:1.7;">
                    <strong>طريقة الاستخدام:</strong><br>
                    استخدم هذه المفاتيح للربط مع التطبيقات الخارجية عبر REST API.<br>
                    الرابط الأساسي: <code style="direction:ltr;display:inline-block;"><?php echo esc_html( home_url( '/wp-json/wc/v3/' ) ); ?></code>
                </p>
            </div>
        </div>
        <div class="yamamah-modal-footer">
            <button type="button" class="btn btn-green" id="closeShowKeysBtn">تم — لقد نسخت المفاتيح</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    /* ── Open create modal ── */
    $('#openCreateKeyModal').on('click', function() {
        $('#keyDescription').val('');
        $('input[name="keyPermission"][value="read_write"]').prop('checked', true);
        $('#createKeyMsg').text('');
        $('#createKeyModal').css('display','flex').hide().fadeIn(200);
    });

    /* ── Radio highlight ── */
    $('input[name="keyPermission"]').on('change', function() {
        $('input[name="keyPermission"]').each(function() {
            var $label = $(this).closest('label');
            if (this.checked) {
                $label.css({ 'border-color': 'var(--green,#10b981)', 'background': 'rgba(16,185,129,0.04)' });
            } else {
                $label.css({ 'border-color': 'var(--border,#e5e7eb)', 'background': 'transparent' });
            }
        });
    }).filter(':checked').trigger('change');

    /* ── Generate key ── */
    $('#generateKeyBtn').on('click', function() {
        var desc = $.trim($('#keyDescription').val());
        var perm = $('input[name="keyPermission"]:checked').val();
        var $btn = $(this), $msg = $('#createKeyMsg');

        if (!desc) {
            $msg.css('color', '#ef4444').text('يرجى إدخال وصف للمفتاح');
            $('#keyDescription').focus();
            return;
        }

        $btn.prop('disabled', true).text('جاري الإنشاء...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_create_api_key',
            nonce: yamamahData.nonce,
            description: desc,
            permissions: perm
        }, function(res) {
            $btn.prop('disabled', false).text('إنشاء المفتاح');
            if (res.success) {
                $('#createKeyModal').fadeOut(200);
                $('#showConsumerKey').val(res.data.consumer_key);
                $('#showConsumerSecret').val(res.data.consumer_secret);
                $('#showKeysModal').css('display','flex').hide().fadeIn(200);
            } else {
                $msg.css('color', '#ef4444').text(res.data || 'خطأ في الإنشاء');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('إنشاء المفتاح');
            $msg.css('color', '#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Close show keys & reload ── */
    $('#closeShowKeysBtn').on('click', function() {
        $('#showKeysModal').fadeOut(200);
        location.reload();
    });

    /* ── Copy to clipboard ── */
    $(document).on('click', '.copy-key-btn', function() {
        var targetId = $(this).data('target');
        var $input = $('#' + targetId);
        var val = $input.val();
        var $btn = $(this);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(function() {
                $btn.html('✓').css('color', '#16a34a');
                setTimeout(function() {
                    $btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>').css('color', '');
                }, 2000);
            });
        } else {
            $input[0].select();
            document.execCommand('copy');
            $btn.html('✓').css('color', '#16a34a');
            setTimeout(function() {
                $btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>').css('color', '');
            }, 2000);
        }
    });

    /* ── Revoke key ── */
    $('.revoke-key-btn').on('click', function() {
        if (!confirm('هل أنت متأكد من إلغاء هذا المفتاح؟\nالتطبيقات المرتبطة به ستتوقف عن العمل فوراً.')) return;
        var $btn = $(this);
        var keyId = $btn.data('key-id');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true).text('جاري الإلغاء...');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_revoke_api_key',
            nonce: yamamahData.nonce,
            key_id: keyId
        }, function(res) {
            if (res.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> إلغاء');
                alert(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            alert('خطأ في الاتصال');
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
