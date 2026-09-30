<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'sms' ) ) {
    $page_content = yamamah_upgrade_banner( 'الرسائل النصية SMS' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

$sms_enabled   = get_option( 'yamama_sms_enabled', 'no' );
$provider      = get_option( 'yamama_sms_provider', 'platform' );
$monthly_limit = intval( get_option( 'yamama_sms_monthly_limit', 0 ) );
$can_own       = get_option( 'yamama_sms_can_own_gateway', 'no' ) === 'yes';
$custom_token  = get_option( 'yamama_sms_custom_token', '' );
$custom_sender = get_option( 'yamama_sms_custom_sender', '' );

if ( class_exists( 'Yamamah_SMS_Manager' ) ) {
    $usage = Yamamah_SMS_Manager::get_monthly_usage();
} else {
    $usage = 0;
}

$limit_display = $monthly_limit;
if ( $monthly_limit === -1 ) $limit_display = 'غير محدود';
elseif ( $monthly_limit === 0 ) $limit_display = 'معطّل';

ob_start();
?>
<div class="page active" id="page-settings-sms">
    <div class="page-header">
        <h1>إعدادات الرسائل النصية (SMS)</h1>
        <p>إدارة خدمة رسائل SMS لتفعيل OTP وإشعارات العملاء</p>
    </div>

    <?php if ( $sms_enabled !== 'yes' ) : ?>
    <div class="card">
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>خدمة SMS غير مفعّلة في باقتك الحالية.</strong>
                <p>قم بترقية باقتك للحصول على ميزة الرسائل النصية.</p>
            </div>
        </div>
    </div>
    <?php else : ?>

    <!-- Usage Stats -->
    <div class="row mb-4" style="display:flex;gap:16px;flex-wrap:wrap;">
        <div class="stat-card" style="flex:1;min-width:200px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#16a34a;"><?php echo esc_html( $usage ); ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;">رسائل مرسلة هذا الشهر</div>
        </div>
        <div class="stat-card" style="flex:1;min-width:200px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:#2563eb;"><?php echo esc_html( $limit_display ); ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;">الحد الشهري</div>
        </div>
        <?php if ( $monthly_limit > 0 ) : ?>
        <div class="stat-card" style="flex:1;min-width:200px;background:<?php echo $usage >= $monthly_limit ? '#fef2f2' : '#fefce8'; ?>;border:1px solid <?php echo $usage >= $monthly_limit ? '#fecaca' : '#fef08a'; ?>;border-radius:12px;padding:20px;text-align:center;">
            <div style="font-size:28px;font-weight:700;color:<?php echo $usage >= $monthly_limit ? '#dc2626' : '#ca8a04'; ?>;"><?php echo max( 0, $monthly_limit - $usage ); ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:4px;">المتبقي</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- OTP Settings -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 12px;font-size:15px;">تفعيل OTP للعملاء</h3>
            <p style="font-size:12px;color:#64748b;margin:0 0 16px;">عند التفعيل، سيُطلب من عملاء المتجر التحقق من رقم جوالهم عند التسجيل أو تسجيل الدخول.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="sms-otp-form">
                <input type="hidden" name="action" value="yamamah_save_sms_settings">
                <?php wp_nonce_field( 'yamamah_save_sms', 'yamamah_sms_nonce' ); ?>

                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;">
                    <input type="checkbox" name="otp_login" value="yes" <?php checked( get_option( 'yamama_otp_login', 'no' ), 'yes' ); ?>>
                    <span style="font-size:13px;">تفعيل OTP لتسجيل الدخول</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;cursor:pointer;">
                    <input type="checkbox" name="otp_register" value="yes" <?php checked( get_option( 'yamama_otp_register', 'no' ), 'yes' ); ?>>
                    <span style="font-size:13px;">تفعيل OTP لإنشاء الحسابات</span>
                </label>

                <!-- Bug #70: حقل اسم المرسل لمزود المنصة -->
                <div style="margin-bottom:16px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                    <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px;">اسم المرسل (Sender ID)</label>
                    <input type="text" name="platform_sender"
                           value="<?php echo esc_attr( get_option( 'yamama_sms_platform_sender', '' ) ); ?>"
                           placeholder="مثال: YamamaStore"
                           style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;"
                           dir="ltr" maxlength="11">
                    <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">الاسم الذي يظهر للعميل عند استقبال الرسالة — 3 إلى 11 حرفاً إنجليزياً بدون مسافات.</p>
                </div>

                <!-- Bug #96: مدة صلاحية رمز OTP -->
                <div style="margin-bottom:16px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                    <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px;">مدة صلاحية رمز OTP (بالدقائق)</label>
                    <input type="number" name="otp_expiry_minutes"
                           value="<?php echo esc_attr( (string) max( 1, intval( get_option( 'yamama_otp_expiry_seconds', 300 ) ) / 60 ) ); ?>"
                           min="1" max="30"
                           style="width:120px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;"
                           dir="ltr">
                    <p style="font-size:11px;color:#94a3b8;margin:4px 0 0;">القيمة الافتراضية 5 دقائق. النطاق المسموح: 1 – 30 دقيقة.</p>
                </div>

                <button type="button" class="btn btn-primary" onclick="saveSmsSettings(this)">حفظ الإعدادات</button>
            </form>
        </div>
    </div>

    <?php if ( $can_own ) : ?>
    <!-- Custom Gateway -->
    <div class="card">
        <div class="card-body">
            <h3 style="margin:0 0 12px;font-size:15px;">بوابة SMS خاصة (Taqnyat)</h3>
            <p style="font-size:12px;color:#64748b;margin:0 0 16px;">ربط حساب Taqnyat الخاص بك للحصول على رسائل غير محدودة وتحكم كامل.</p>

            <form id="sms-gateway-form">
                <input type="hidden" name="action" value="yamamah_save_sms_gateway">
                <?php wp_nonce_field( 'yamamah_save_sms_gateway', 'yamamah_sms_gw_nonce' ); ?>

                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;">
                    <input type="radio" name="sms_provider" value="platform" <?php checked( $provider, 'platform' ); ?>>
                    <span style="font-size:13px;">استخدام بوابة المنصة (حد شهري: <?php echo esc_html( $limit_display ); ?>)</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;cursor:pointer;">
                    <input type="radio" name="sms_provider" value="custom" <?php checked( $provider, 'custom' ); ?>>
                    <span style="font-size:13px;">استخدام حسابي الخاص على Taqnyat (غير محدود)</span>
                </label>

                <div id="custom-gateway-fields" style="<?php echo $provider !== 'custom' ? 'display:none;' : ''; ?>margin-top:12px;padding:16px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px;">Bearer Token</label>
                        <input type="password" name="custom_token" value="<?php echo esc_attr( $custom_token ); ?>" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;" dir="ltr" autocomplete="off">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:12px;color:#64748b;margin-bottom:4px;">اسم المرسل</label>
                        <input type="text" name="custom_sender" value="<?php echo esc_attr( $custom_sender ); ?>" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;" dir="ltr">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="testCustomGateway(this)" style="font-size:12px;">اختبار الاتصال</button>
                    <span id="gateway-test-result" style="font-size:12px;margin-right:8px;"></span>
                </div>

                <div style="margin-top:16px;">
                    <button type="button" class="btn btn-primary" onclick="saveGatewaySettings(this)">حفظ البوابة</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
document.querySelectorAll('input[name="sms_provider"]').forEach(function(r) {
    r.addEventListener('change', function() {
        var fields = document.getElementById('custom-gateway-fields');
        if (fields) fields.style.display = this.value === 'custom' ? '' : 'none';
    });
});

function saveSmsSettings(btn) {
    btn.disabled = true; btn.textContent = 'جاري الحفظ...';
    var form = document.getElementById('sms-otp-form');
    var fd = new FormData(form);
    fd.set('action', 'yamamah_save_sms_settings');
    fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false; btn.textContent = 'حفظ الإعدادات';
            if (res.success) yamamahNotify('success', res.data.message || 'تم الحفظ');
            else yamamahNotify('error', res.data.message || 'فشل الحفظ');
        })
        .catch(function() { btn.disabled = false; btn.textContent = 'حفظ الإعدادات'; yamamahNotify('error', 'خطأ في الاتصال'); });
}

function saveGatewaySettings(btn) {
    btn.disabled = true; btn.textContent = 'جاري الحفظ...';
    var form = document.getElementById('sms-gateway-form');
    var fd = new FormData(form);
    fd.set('action', 'yamamah_save_sms_gateway');
    fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            btn.disabled = false; btn.textContent = 'حفظ البوابة';
            if (res.success) yamamahNotify('success', res.data.message || 'تم الحفظ');
            else yamamahNotify('error', res.data.message || 'فشل الحفظ');
        })
        .catch(function() { btn.disabled = false; btn.textContent = 'حفظ البوابة'; yamamahNotify('error', 'خطأ في الاتصال'); });
}

function testCustomGateway(btn) {
    btn.disabled = true;
    var res = document.getElementById('gateway-test-result');
    res.textContent = 'جاري الاختبار...'; res.style.color = '#64748b';
    var fd = new FormData();
    fd.append('action', 'yamamah_test_sms_gateway');
    fd.append('_wpnonce', document.querySelector('[name="yamamah_sms_gw_nonce"]').value);
    fd.append('token', document.querySelector('[name="custom_token"]').value);
    fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(r) {
            btn.disabled = false;
            if (r.success) { res.textContent = '✅ ' + r.data.message; res.style.color = '#16a34a'; }
            else { res.textContent = '❌ ' + r.data.message; res.style.color = '#dc2626'; }
        })
        .catch(function() { btn.disabled = false; res.textContent = '❌ خطأ'; res.style.color = '#dc2626'; });
}
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
