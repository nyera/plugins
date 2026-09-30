<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;
$first_name   = get_user_meta( $user_id, 'first_name', true );
$last_name    = get_user_meta( $user_id, 'last_name', true );
$phone        = get_user_meta( $user_id, 'billing_phone', true );
$registered   = $current_user->user_registered;
$last_login   = get_user_meta( $user_id, 'last_login', true );
$roles        = $current_user->roles;
$role_label   = 'مستخدم';

$role_map = array(
    'administrator' => 'مدير عام',
    'shop_manager'  => 'مدير المتجر',
    'editor'        => 'محرر',
    'customer'      => 'عميل',
);
if ( ! empty( $roles[0] ) && isset( $role_map[ $roles[0] ] ) ) {
    $role_label = $role_map[ $roles[0] ];
}

$initials = '';
$name = $current_user->display_name;
$parts = explode( ' ', trim( $name ) );
foreach ( array_slice( $parts, 0, 2 ) as $p ) {
    $initials .= mb_substr( $p, 0, 1 );
}

$avatar_color = '#6C3FA0';

ob_start();
?>

<div class="page active" id="page-my-account">
    <div class="page-header">
        <h1>حسابي</h1>
        <p>إدارة بيانات الحساب الشخصي</p>
    </div>

    <div class="grid grid-2 mb-4" style="gap:1.5rem;">
        <!-- Profile Card -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">الملف الشخصي</h3></div>
            <div style="padding:1.5rem;">
                <div class="flex items-center gap-3 mb-4">
                    <div class="avatar" style="width:64px;height:64px;font-size:1.4rem;background:<?php echo esc_attr( $avatar_color ); ?>;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <?php echo esc_html( $initials ); ?>
                    </div>
                    <div>
                        <h3 style="margin:0 0 4px;"><?php echo esc_html( $current_user->display_name ); ?></h3>
                        <span class="badge badge-blue"><?php echo esc_html( $role_label ); ?></span>
                    </div>
                </div>
                <div class="yamamah-detail-list">
                    <div class="yamamah-detail-row" style="padding:0.6rem 0;border-bottom:1px solid var(--border);">
                        <span class="text-muted text-sm">اسم المستخدم</span>
                        <span class="text-sm ltr"><?php echo esc_html( $current_user->user_login ); ?></span>
                    </div>
                    <div class="yamamah-detail-row" style="padding:0.6rem 0;border-bottom:1px solid var(--border);">
                        <span class="text-muted text-sm">البريد الإلكتروني</span>
                        <span class="text-sm ltr"><?php echo esc_html( $current_user->user_email ); ?></span>
                    </div>
                    <div class="yamamah-detail-row" style="padding:0.6rem 0;border-bottom:1px solid var(--border);">
                        <span class="text-muted text-sm">رقم الجوال</span>
                        <span class="text-sm ltr"><?php echo $phone ? esc_html( $phone ) : '<span class="text-muted">—</span>'; ?></span>
                    </div>
                    <div class="yamamah-detail-row" style="padding:0.6rem 0;border-bottom:1px solid var(--border);">
                        <span class="text-muted text-sm">تاريخ التسجيل</span>
                        <span class="text-sm"><?php echo esc_html( date_i18n( 'Y-m-d', strtotime( $registered ) ) ); ?></span>
                    </div>
                    <div class="yamamah-detail-row" style="padding:0.6rem 0;">
                        <span class="text-muted text-sm">آخر دخول</span>
                        <span class="text-sm"><?php echo $last_login ? esc_html( yamamah_time_ago( $last_login ) ) : '—'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form Card -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">تعديل البيانات</h3></div>
            <div style="padding:1.5rem;">
                <form id="myAccountForm" onsubmit="return false;">
                    <div class="form-field mb-3">
                        <label class="text-sm text-muted mb-1" style="display:block;">الاسم المعروض <span style="color:var(--red);">*</span></label>
                        <input type="text" id="profileDisplayName" class="form-control" value="<?php echo esc_attr( $current_user->display_name ); ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">
                        <div class="form-field">
                            <label class="text-sm text-muted mb-1" style="display:block;">الاسم الأول</label>
                            <input type="text" id="profileFirstName" class="form-control" value="<?php echo esc_attr( $first_name ); ?>">
                        </div>
                        <div class="form-field">
                            <label class="text-sm text-muted mb-1" style="display:block;">الاسم الأخير</label>
                            <input type="text" id="profileLastName" class="form-control" value="<?php echo esc_attr( $last_name ); ?>">
                        </div>
                    </div>
                    <div class="form-field mb-3">
                        <label class="text-sm text-muted mb-1" style="display:block;">البريد الإلكتروني <span style="color:var(--red);">*</span></label>
                        <input type="email" id="profileEmail" class="form-control" dir="ltr" value="<?php echo esc_attr( $current_user->user_email ); ?>">
                    </div>
                    <div class="form-field mb-3">
                        <label class="text-sm text-muted mb-1" style="display:block;">رقم الجوال</label>
                        <input type="text" id="profilePhone" class="form-control" dir="ltr" value="<?php echo esc_attr( $phone ); ?>" placeholder="+966...">
                    </div>

                    <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0;">
                    <h4 class="text-sm mb-3" style="color:var(--blue);">تغيير كلمة المرور</h4>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="mb-3">
                        <div class="form-field">
                            <label class="text-sm text-muted mb-1" style="display:block;">كلمة المرور الجديدة</label>
                            <input type="password" id="profileNewPass" class="form-control" placeholder="اتركه فارغا لعدم التغيير">
                        </div>
                        <div class="form-field">
                            <label class="text-sm text-muted mb-1" style="display:block;">تأكيد كلمة المرور</label>
                            <input type="password" id="profileConfirmPass" class="form-control" placeholder="أعد كتابة كلمة المرور">
                        </div>
                    </div>

                    <div class="flex items-center gap-3" style="margin-top:1.5rem;">
                        <button type="button" class="btn btn-green" id="saveProfileBtn" onclick="yamamahSaveProfile()">حفظ التعديلات</button>
                        <span id="profileSaveStatus" style="font-size:0.85rem;"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Account Info Cards -->
    <div class="grid grid-3 mb-4" style="gap:1rem;">
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(108,63,160,0.1);color:#6C3FA0;display:inline-flex;align-items:center;justify-content:center;margin-bottom:0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <p class="text-xs text-muted">الدور</p>
            <p class="text-lg" style="font-weight:600;"><?php echo esc_html( $role_label ); ?></p>
        </div>
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(45,155,131,0.1);color:#2D9B83;display:inline-flex;align-items:center;justify-content:center;margin-bottom:0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <p class="text-xs text-muted">تاريخ التسجيل</p>
            <p class="text-lg" style="font-weight:600;"><?php echo esc_html( date_i18n( 'Y/m/d', strtotime( $registered ) ) ); ?></p>
        </div>
        <div class="card" style="text-align:center;padding:1.5rem;">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(74,144,217,0.1);color:#4A90D9;display:inline-flex;align-items:center;justify-content:center;margin-bottom:0.75rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <p class="text-xs text-muted">آخر نشاط</p>
            <p class="text-lg" style="font-weight:600;"><?php echo $last_login ? esc_html( yamamah_time_ago( $last_login ) ) : '—'; ?></p>
        </div>
    </div>
</div>

<script>
function yamamahSaveProfile() {
    var $btn = jQuery('#saveProfileBtn'),
        $status = jQuery('#profileSaveStatus');

    $btn.prop('disabled', true).text('جاري الحفظ...');
    $status.html('');

    jQuery.post(ajaxurl, {
        action:           'yamamah_update_profile',
        nonce:            yamamahData.nonce,
        display_name:     jQuery('#profileDisplayName').val(),
        first_name:       jQuery('#profileFirstName').val(),
        last_name:        jQuery('#profileLastName').val(),
        email:            jQuery('#profileEmail').val(),
        phone:            jQuery('#profilePhone').val(),
        new_password:     jQuery('#profileNewPass').val(),
        confirm_password: jQuery('#profileConfirmPass').val()
    }, function(res) {
        $btn.prop('disabled', false).text('حفظ التعديلات');
        if (res.success) {
            $status.html('<span style="color:var(--green);">&#10003; ' + res.data.message + '</span>');
            jQuery('#profileNewPass, #profileConfirmPass').val('');
            setTimeout(function(){ location.reload(); }, 1500);
        } else {
            $status.html('<span style="color:var(--red);">&#10007; ' + (res.data.message || 'حدث خطأ') + '</span>');
        }
    }).fail(function() {
        $btn.prop('disabled', false).text('حفظ التعديلات');
        $status.html('<span style="color:var(--red);">&#10007; خطأ في الاتصال</span>');
    });
}
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
