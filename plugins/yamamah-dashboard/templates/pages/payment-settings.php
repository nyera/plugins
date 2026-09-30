<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'electronic_payment' ) ) {
    $page_content = yamamah_upgrade_banner( 'إعدادات الدفع الإلكتروني' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

/* ===== حالة Tap الحالية ===== */
$tap_merchant_id = get_option( 'yamama_tap_merchant_id', '' );
$tap_livemode    = (int) get_option( 'yamama_tap_livemode', 0 );
$tap_lead_id     = get_option( 'yamama_tap_lead_id', '' );
$tap_connect_url = get_option( 'yamama_tap_connect_url', '' );
$tap_status      = get_option( 'yamama_tap_status', '' );
$tap_returned    = isset( $_GET['tap_returned'] ) ? 1 : 0;

$has_tap = ! empty( $tap_merchant_id );

/* ===== المسودة المحفوظة ===== */
$saved = get_option( 'yamamah_payment_settings', [] );
$g     = function( $k, $d = '' ) use ( $saved ) { return isset( $saved[ $k ] ) ? $saved[ $k ] : $d; };

/* ===== قائمة الوثائق ===== */
$doc_purposes = function_exists( 'yamamah_tap_doc_purposes' ) ? yamamah_tap_doc_purposes() : array();

/* ====== Helper render لأي uploader ====== */
if ( ! function_exists( 'yamamah_render_doc_uploader' ) ) {
    function yamamah_render_doc_uploader( $purpose, $state ) {
        $label    = $state['label']    ?? $purpose;
        $required = ! empty( $state['required'] );
        $accept   = $state['accept']   ?? '*/*';
        $max_kb   = (int) ( $state['max_kb'] ?? 10240 );
        $uploaded = ! empty( $state['uploaded'] );
        $name     = $state['name']     ?? '';
        ?>
        <div class="yamamah-doc-uploader <?php echo $uploaded ? 'has-file' : ''; ?>" data-purpose="<?php echo esc_attr( $purpose ); ?>">
            <div class="yamamah-doc-uploader-row">
                <strong style="flex:1;">📎 <?php echo esc_html( $label ); ?> <?php if ( $required ) : ?><span class="required">*</span><?php endif; ?></strong>
                <label class="btn btn-outline">
                    <span>اختر ملفاً</span>
                    <input type="file" accept="<?php echo esc_attr( $accept ); ?>" style="display:none;" />
                </label>
                <button type="button" class="btn btn-outline btn-remove-doc" style="<?php echo $uploaded ? '' : 'display:none;'; ?>color:#dc2626;border-color:#dc2626;">حذف</button>
                <span class="yamamah-doc-name"><?php echo esc_html( $name ); ?></span>
                <span class="yamamah-doc-status <?php echo $uploaded ? 'ok' : ''; ?>"><?php echo $uploaded ? '✓ مرفوع' : ''; ?></span>
            </div>
            <div class="yamamah-doc-hint" style="font-size:12px;color:#94a3b8;margin-top:6px;">
                الصيغ: <?php echo esc_html( $accept ); ?> · حد أقصى <?php echo (int) ( $max_kb / 1024 ); ?>MB
            </div>
        </div>
        <?php
    }
}

/* ===== قراءة file_ids المحفوظة ===== */
$docs_state = array();
foreach ( $doc_purposes as $p => $cfg ) {
    $opt_key = 'yamama_tap_doc_' . sanitize_key( $p );
    $fid     = get_option( $opt_key, '' );
    $meta    = (array) get_option( $opt_key . '_meta', array() );
    $docs_state[ $p ] = array(
        'file_id'  => (string) $fid,
        'uploaded' => $fid !== '',
        'name'     => $meta['name'] ?? '',
        'label'    => $cfg['label'],
        'required' => ! empty( $cfg['required'] ),
        'accept'   => $cfg['accept'],
        'max_kb'   => (int) $cfg['max_kb'],
    );
}

ob_start();
?>

<div class="page active" id="page-payment-settings">
    <div class="page-header">
        <h1>إعدادات الدفع الإلكتروني — Tap Payments</h1>
        <p>أكمل البيانات والوثائق أدناه ثم اضغط <strong>"فعّل بوابة الدفع"</strong> لإرسال طلب التفعيل مباشرة إلى Tap.</p>
    </div>

    <?php if ( $has_tap ) : ?>
    <div class="yamamah-connection-card mb-5" style="border-color:#bbf7d0;background:#f0fdf4;">
        <div style="display:flex;align-items:center;gap:12px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <h3 style="margin:0;color:#166534;">بوابة Tap مفعّلة في متجرك</h3>
                <p style="margin:4px 0 0;color:#15803d;font-size:13px;">
                    Merchant ID: <code dir="ltr"><?php echo esc_html( $tap_merchant_id ); ?></code>
                    &nbsp;·&nbsp; الوضع: <strong><?php echo $tap_livemode ? 'مباشر (Live)' : 'تجريبي (Test)'; ?></strong>
                </p>
            </div>
        </div>
    </div>

    <div class="yamamah-connection-card mb-5">
        <h2 class="card-section-title">إدارة الحساب</h2>
        <p style="color:#475569;line-height:1.9;">يمكنك إدارة حسابك مباشرة من لوحة Tap.</p>
        <div style="margin-top:16px;">
            <a href="https://business.tap.company/" target="_blank" rel="noopener" class="btn btn-green">فتح لوحة Tap</a>
            <button type="button" class="btn btn-outline" id="btnRefreshStatus" style="margin-right:8px;">تحديث حالة الربط</button>
            <span id="tapStatusMsg" class="text-sm" style="margin-right:10px;color:#64748b;"></span>
        </div>
    </div>
    <?php else : ?>

    <?php if ( $tap_lead_id || $tap_returned ) : ?>
    <div class="yamamah-connection-card mb-5" style="border-color:#fde68a;background:#fffbeb;">
        <div style="display:flex;align-items:center;gap:12px;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div style="flex:1;">
                <h3 style="margin:0;color:#92400e;">طلب التفعيل قيد المراجعة</h3>
                <p style="margin:4px 0 0;color:#a16207;font-size:13px;">
                    تم إرسال البيانات والوثائق إلى Tap. تستغرق المراجعة عادةً <strong>24-48 ساعة</strong>. سنقوم بتفعيل البوابة تلقائياً فور الموافقة.
                </p>
                <?php if ( $tap_connect_url ) : ?>
                    <p style="margin:8px 0 0;color:#a16207;font-size:13px;">
                        طلبت Tap وثائق إضافية:
                        <a href="<?php echo esc_url( $tap_connect_url ); ?>" target="_blank" rel="noopener" class="btn btn-green" style="padding:4px 12px;font-size:12px;margin-right:6px;">إكمالها الآن</a>
                    </p>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline" id="btnRefreshStatus">تحديث الحالة</button>
        </div>
        <div id="tapStatusMsg" class="text-sm" style="margin-top:8px;color:#64748b;"></div>
    </div>
    <?php endif; ?>

    <!-- ====== شريط التقدم ====== -->
    <div class="yamamah-connection-card mb-5" style="background:#f8fafc;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <strong>اكتمال الوثائق:</strong>
                <span id="docsProgressText" style="color:#0ea5e9;">— من <?php echo count( $doc_purposes ); ?></span>
            </div>
            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:8px;margin:0 16px;overflow:hidden;">
                <div id="docsProgressBar" style="height:100%;width:0%;background:#10b981;transition:width .3s;"></div>
            </div>
        </div>
    </div>

    <!-- ====== النموذج ====== -->
    <div class="yamamah-settings-container" id="paymentSettingsForm">

        <!-- ============ معلومات الكيان ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">١) معلومات الكيان</h2>
            <div class="yamamah-form-fields">
                <div class="form-field">
                    <label>اسم العلامة التجارية <span class="required">*</span></label>
                    <input type="text" name="brand_name" value="<?php echo esc_attr( $g( 'brand_name', get_bloginfo( 'name' ) ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>الاسم القانوني للكيان <span class="required">*</span></label>
                    <input type="text" name="legal_name" value="<?php echo esc_attr( $g( 'legal_name' ) ); ?>" placeholder="نفس الاسم في السجل التجاري" />
                </div>
                <div class="form-field">
                    <label>نوع الكيان <span class="required">*</span></label>
                    <select name="entity_type">
                        <?php $et = $g( 'entity_type', 'LLC' ); ?>
                        <option value="LLC"            <?php selected( $et, 'LLC' ); ?>>شركة ذات مسؤولية محدودة (LLC)</option>
                        <option value="Establishment"  <?php selected( $et, 'Establishment' ); ?>>مؤسسة فردية</option>
                        <option value="Freelancer"     <?php selected( $et, 'Freelancer' ); ?>>عمل حر / Freelancer</option>
                        <option value="Profession LLC" <?php selected( $et, 'Profession LLC' ); ?>>شركة مهنية</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>وصف النشاط</label>
                    <input type="text" name="category_description" value="<?php echo esc_attr( $g( 'category_description' ) ); ?>" placeholder="مثال: تجارة إلكترونية، بيع منتجات..." />
                </div>
            </div>
        </div>

        <!-- ============ السجل التجاري ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">٢) السجل التجاري</h2>
            <div class="yamamah-form-fields">
                <div class="form-field">
                    <label>رقم السجل التجاري <span class="required">*</span></label>
                    <input type="text" name="license_number" value="<?php echo esc_attr( $g( 'license_number' ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>تاريخ الإصدار <span class="required">*</span></label>
                    <input type="date" class="ltr" lang="en" dir="ltr" name="license_issue_date" value="<?php echo esc_attr( $g( 'license_issue_date' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>تاريخ الانتهاء <span class="required">*</span></label>
                    <input type="date" class="ltr" lang="en" dir="ltr" name="license_expiry_date" value="<?php echo esc_attr( $g( 'license_expiry_date' ) ); ?>" />
                </div>
            </div>
            <?php yamamah_render_doc_uploader( 'commercial_registration', $docs_state['commercial_registration'] ?? array() ); ?>
        </div>

        <!-- ============ بيانات المسؤول ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">٣) بيانات المسؤول الرئيسي</h2>
            <div class="yamamah-form-fields">
                <div class="form-field">
                    <label>الاسم الأول <span class="required">*</span></label>
                    <input type="text" name="first_name" value="<?php echo esc_attr( $g( 'first_name' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>الاسم الأوسط</label>
                    <input type="text" name="middle_name" value="<?php echo esc_attr( $g( 'middle_name' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>اسم العائلة <span class="required">*</span></label>
                    <input type="text" name="last_name" value="<?php echo esc_attr( $g( 'last_name' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>البريد الإلكتروني <span class="required">*</span></label>
                    <input type="email" name="personal_email" value="<?php echo esc_attr( $g( 'personal_email', get_option( 'admin_email' ) ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>رقم الجوال (بدون رمز الدولة) <span class="required">*</span></label>
                    <input type="tel" name="personal_phone" value="<?php echo esc_attr( $g( 'personal_phone' ) ); ?>" dir="ltr" style="text-align:right;" placeholder="500000000" />
                </div>
                <div class="form-field">
                    <label>نوع الهوية <span class="required">*</span></label>
                    <select name="identification_type">
                        <?php $it = $g( 'identification_type', 'national_id' ); ?>
                        <option value="national_id"     <?php selected( $it, 'national_id' ); ?>>هوية وطنية</option>
                        <option value="iqamah"          <?php selected( $it, 'iqamah' ); ?>>إقامة</option>
                        <option value="passport"        <?php selected( $it, 'passport' ); ?>>جواز سفر</option>
                        <option value="gcc_national_id" <?php selected( $it, 'gcc_national_id' ); ?>>هوية خليجية</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>رقم الهوية <span class="required">*</span></label>
                    <input type="text" name="national_id" value="<?php echo esc_attr( $g( 'national_id' ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>الجنسية</label>
                    <input type="text" name="nationality" value="<?php echo esc_attr( $g( 'nationality', 'SA' ) ); ?>" maxlength="2" placeholder="SA" />
                </div>
                <div class="form-field">
                    <label>تاريخ الميلاد</label>
                    <input type="date" class="ltr" lang="en" dir="ltr" name="birth_date" value="<?php echo esc_attr( $g( 'birth_date' ) ); ?>" />
                </div>
            </div>
            <?php yamamah_render_doc_uploader( 'identity_document', $docs_state['identity_document'] ?? array() ); ?>
        </div>

        <!-- ============ الحساب البنكي ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">٤) الحساب البنكي</h2>
            <div class="yamamah-form-fields">
                <div class="form-field">
                    <label>اسم صاحب الحساب <span class="required">*</span></label>
                    <input type="text" name="bank_name" value="<?php echo esc_attr( $g( 'bank_name' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>IBAN <span class="required">*</span></label>
                    <input type="text" name="iban" value="<?php echo esc_attr( $g( 'iban' ) ); ?>" placeholder="SA1234567890123456789012" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>SWIFT (اختياري)</label>
                    <input type="text" name="bank_swift" value="<?php echo esc_attr( $g( 'bank_swift' ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>رقم الحساب (اختياري)</label>
                    <input type="text" name="bank_account_no" value="<?php echo esc_attr( $g( 'bank_account_no' ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
            </div>
            <?php yamamah_render_doc_uploader( 'bank_certificate', $docs_state['bank_certificate'] ?? array() ); ?>
        </div>

        <!-- ============ الشهادة الضريبية ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">٥) الشهادة الضريبية (اختياري)</h2>
            <div class="yamamah-form-fields">
                <div class="form-field">
                    <label>الرقم الضريبي</label>
                    <input type="text" name="tax_number" value="<?php echo esc_attr( $g( 'tax_number' ) ); ?>" dir="ltr" style="text-align:right;" />
                </div>
                <div class="form-field">
                    <label>تاريخ الإصدار</label>
                    <input type="date" class="ltr" lang="en" dir="ltr" name="tax_issue_date" value="<?php echo esc_attr( $g( 'tax_issue_date' ) ); ?>" />
                </div>
                <div class="form-field">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" class="ltr" lang="en" dir="ltr" name="tax_expiry_date" value="<?php echo esc_attr( $g( 'tax_expiry_date' ) ); ?>" />
                </div>
            </div>
            <?php yamamah_render_doc_uploader( 'tax_document_user_upload', $docs_state['tax_document_user_upload'] ?? array() ); ?>
        </div>

        <!-- ============ وثائق إضافية ============ -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title">٦) وثائق إضافية (اختياري)</h2>
            <?php yamamah_render_doc_uploader( 'memorandum_association', $docs_state['memorandum_association'] ?? array() ); ?>
            <?php yamamah_render_doc_uploader( 'article_of_association', $docs_state['article_of_association'] ?? array() ); ?>
            <?php yamamah_render_doc_uploader( 'business_logo',          $docs_state['business_logo']          ?? array() ); ?>
        </div>

        <div class="mb-5">
            <button type="button" class="btn btn-green" id="btnActivateTap" disabled title="يجب إكمال الحقول والوثائق المطلوبة أولاً">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
                فعّل بوابة الدفع (Tap)
            </button>
            <button type="button" class="btn btn-outline" id="btnSaveOnly" style="margin-right:8px;">حفظ المسودة</button>
            <span id="paymentSaveStatus" class="text-sm" style="margin-right:10px;"></span>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.yamamah-doc-uploader { margin-top:16px; padding:16px; border:1px dashed #cbd5e1; border-radius:10px; background:#f8fafc; }
.yamamah-doc-uploader.has-file { border-color:#10b981; background:#f0fdf4; }
.yamamah-doc-uploader-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.yamamah-doc-uploader label.btn { cursor:pointer; padding:8px 14px; }
.yamamah-doc-status { font-size:13px; color:#64748b; }
.yamamah-doc-status.ok { color:#16a34a; font-weight:600; }
.yamamah-doc-status.err { color:#dc2626; }
.yamamah-doc-name { font-family:monospace; font-size:12px; color:#475569; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.required { color:#dc2626; }
</style>

<script>
(function(){
    var nonce      = '<?php echo wp_create_nonce( 'yamamah_nonce' ); ?>';
    var restNonce  = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';
    var ajaxUrl    = (window.yamamahData && yamamahData.ajax_url) ? yamamahData.ajax_url : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var restRoot   = '<?php echo esc_js( esc_url_raw( rest_url( 'yaamama-tap/v1/' ) ) ); ?>';

    var docsCfg    = <?php echo wp_json_encode( $doc_purposes ); ?>;
    var docsState  = <?php echo wp_json_encode( $docs_state ); ?>;

    function $(s, ctx){ return (ctx||document).querySelector(s); }
    function $$(s, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(s)); }

    function getFormData() {
        var form = document.getElementById('paymentSettingsForm');
        var data = {};
        if (!form) return data;
        form.querySelectorAll('input[name], select[name], textarea[name]').forEach(function(el){
            data[el.name] = el.value;
        });
        return data;
    }

    function refreshProgress() {
        var total = 0, done = 0, missingRequired = false;
        Object.keys(docsCfg).forEach(function(p){
            total += 1;
            if (docsState[p] && docsState[p].uploaded) done += 1;
            else if (docsCfg[p].required) missingRequired = true;
        });
        var pct = total ? Math.round(done / total * 100) : 0;
        var bar = $('#docsProgressBar'); if (bar) bar.style.width = pct + '%';
        var txt = $('#docsProgressText'); if (txt) txt.textContent = done + ' من ' + total;

        var data = getFormData();
        var requiredFields = ['brand_name','legal_name','entity_type','license_number','license_issue_date','license_expiry_date','first_name','last_name','personal_email','personal_phone','identification_type','national_id','bank_name','iban'];
        var missingField = requiredFields.some(function(f){ return !data[f]; });

        var btn = $('#btnActivateTap');
        if (btn) btn.disabled = missingRequired || missingField;
    }

    function uploadDoc(purpose, fileInput) {
        var card = fileInput.closest('.yamamah-doc-uploader');
        var statusEl = card.querySelector('.yamamah-doc-status');
        var nameEl   = card.querySelector('.yamamah-doc-name');
        var btnRemove = card.querySelector('.btn-remove-doc');

        var f = fileInput.files && fileInput.files[0];
        if (!f) return;

        var maxKb = (docsCfg[purpose] && docsCfg[purpose].max_kb) || 10240;
        if (f.size / 1024 > maxKb) {
            statusEl.textContent = 'حجم الملف يتجاوز ' + maxKb + 'KB';
            statusEl.className = 'yamamah-doc-status err';
            return;
        }

        statusEl.textContent = 'جاري الرفع...';
        statusEl.className = 'yamamah-doc-status';

        var fd = new FormData();
        fd.append('action', 'yamamah_upload_tap_document');
        fd.append('nonce', nonce);
        fd.append('purpose', purpose);
        fd.append('document', f);

        fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r && r.success) {
                    statusEl.textContent = '✓ تم الرفع';
                    statusEl.className = 'yamamah-doc-status ok';
                    nameEl.textContent = r.data.name || f.name;
                    card.classList.add('has-file');
                    if (btnRemove) btnRemove.style.display = 'inline-block';
                    docsState[purpose] = { uploaded: true, file_id: r.data.file_id, name: r.data.name };
                    refreshProgress();
                } else {
                    statusEl.textContent = (r && r.data && r.data.message) || 'فشل الرفع';
                    statusEl.className = 'yamamah-doc-status err';
                }
            })
            .catch(function(e){
                statusEl.textContent = 'خطأ: ' + (e.message || 'فشل الاتصال');
                statusEl.className = 'yamamah-doc-status err';
            });
    }

    function removeDoc(purpose, btn) {
        var card = btn.closest('.yamamah-doc-uploader');
        var statusEl = card.querySelector('.yamamah-doc-status');
        var nameEl   = card.querySelector('.yamamah-doc-name');
        var input    = card.querySelector('input[type=file]');

        var fd = new FormData();
        fd.append('action', 'yamamah_remove_tap_document');
        fd.append('nonce', nonce);
        fd.append('purpose', purpose);

        fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(r){
                if (r && r.success) {
                    statusEl.textContent = 'تم الحذف';
                    statusEl.className = 'yamamah-doc-status';
                    nameEl.textContent = '';
                    card.classList.remove('has-file');
                    btn.style.display = 'none';
                    if (input) input.value = '';
                    docsState[purpose] = { uploaded: false, file_id: '' };
                    refreshProgress();
                }
            });
    }

    $$('.yamamah-doc-uploader').forEach(function(card){
        var purpose = card.getAttribute('data-purpose');
        var input   = card.querySelector('input[type=file]');
        var btnRem  = card.querySelector('.btn-remove-doc');
        if (input) {
            input.addEventListener('change', function(){ uploadDoc(purpose, input); });
        }
        if (btnRem) {
            btnRem.addEventListener('click', function(){ removeDoc(purpose, btnRem); });
        }
    });

    $$('#paymentSettingsForm input, #paymentSettingsForm select, #paymentSettingsForm textarea').forEach(function(el){
        el.addEventListener('input', refreshProgress);
        el.addEventListener('change', refreshProgress);
    });

    function saveDraft(cb) {
        var status = $('#paymentSaveStatus');
        if (status) { status.textContent = 'جاري الحفظ...'; status.style.color = '#64748b'; }
        var fd = new FormData();
        fd.append('action', 'yamamah_save_payment_settings');
        fd.append('nonce', nonce);
        var data = getFormData();
        for (var k in data) fd.append('payment_settings[' + k + ']', data[k]);

        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(r){
                if (r && r.success) {
                    if (status) { status.textContent = 'تم الحفظ'; status.style.color = '#16a34a'; }
                    if (cb) cb(true, data);
                } else {
                    if (status) { status.textContent = (r && r.data && r.data.message) || 'فشل الحفظ'; status.style.color = '#dc2626'; }
                    if (cb) cb(false, data);
                }
            })
            .catch(function(){
                if (status) { status.textContent = 'خطأ في الاتصال'; status.style.color = '#dc2626'; }
                if (cb) cb(false, data);
            });
    }

    function activateTap(data) {
        var status = $('#paymentSaveStatus');
        if (status) { status.textContent = 'جاري إرسال البيانات والوثائق إلى Tap...'; status.style.color = '#64748b'; }

        var docs = {};
        Object.keys(docsState).forEach(function(p){
            if (docsState[p] && docsState[p].file_id) docs[p] = docsState[p].file_id;
        });
        // إعادة تسمية tax_document_user_upload إلى tax_document لتسهيل المعالجة في tap-client
        if (docs.tax_document_user_upload && !docs.tax_document) {
            docs.tax_document = docs.tax_document_user_upload;
        }

        var body = {
            brand_name: data.brand_name,
            country: 'SA',
            currency: 'SAR',
            user: {
                first_name: data.first_name,
                middle_name: data.middle_name || '',
                last_name: data.last_name,
                title: 'Mr.',
                email: data.personal_email,
                phone_country: '966',
                phone_number: (data.personal_phone || '').replace(/^0+/, ''),
                national_id: data.national_id || '',
                identification_type: data.identification_type || 'national_id',
                nationality: data.nationality || 'SA',
                identification_country: data.nationality || 'SA',
                birth_date: data.birth_date || ''
            },
            entity: {
                legal_name: data.legal_name,
                entity_type: data.entity_type || 'LLC',
                license_number: data.license_number || '',
                license_country: 'SA',
                license_issue_date: data.license_issue_date || '',
                license_expiry_date: data.license_expiry_date || '',
                tax_number: data.tax_number || '',
                tax_issue_date: data.tax_issue_date || '',
                tax_expiry_date: data.tax_expiry_date || '',
                carries_a_license: true
            },
            bank: {
                name: data.bank_name || '',
                number: data.bank_account_no || '',
                iban: data.iban,
                swift: data.bank_swift || ''
            },
            documents: docs,
            redirect_url: window.location.origin + window.location.pathname + '?page=yamamah-payment-settings&tap_returned=1'
        };

        fetch(restRoot + 'request-onboarding', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': restNonce},
            body: JSON.stringify(body),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json().then(function(d){ return {ok:r.ok, status:r.status, data:d}; }); })
        .then(function(res){
            if (!res.ok) {
                var msg = (res.data && (res.data.message || res.data.error)) || ('فشل الإرسال (' + res.status + ')');
                if (status) { status.textContent = msg; status.style.color = '#dc2626'; }
                return;
            }
            if (res.data.merchant_id) {
                if (status) { status.textContent = 'تم التفعيل! جاري التحديث...'; status.style.color = '#16a34a'; }
                setTimeout(function(){ location.reload(); }, 1200);
                return;
            }
            if (status) { status.textContent = 'تم الإرسال بنجاح. سيتم التحقق تلقائياً...'; status.style.color = '#0ea5e9'; }
            setTimeout(function(){ location.href = window.location.pathname + '?page=yamamah-payment-settings&tap_returned=1'; }, 1500);
        })
        .catch(function(e){
            if (status) { status.textContent = 'خطأ: ' + (e.message || 'فشل الاتصال'); status.style.color = '#dc2626'; }
        });
    }

    var btnActivate = $('#btnActivateTap');
    if (btnActivate) {
        btnActivate.addEventListener('click', function(){
            saveDraft(function(ok, data){
                if (ok) activateTap(data);
            });
        });
    }
    var btnSave = $('#btnSaveOnly');
    if (btnSave) btnSave.addEventListener('click', function(){ saveDraft(); });

    // polling تلقائي عند الرجوع من Tap
    <?php if ( $tap_returned || $tap_lead_id ) : ?>
    var attempts = 0, maxAttempts = 12;
    var pollTimer = setInterval(function(){
        attempts++;
        var msg = $('#tapStatusMsg');
        if (msg) { msg.textContent = 'جاري التحقق من الحالة (' + attempts + '/' + maxAttempts + ')...'; msg.style.color = '#64748b'; }
        fetch(restRoot + 'status', { credentials:'same-origin', headers:{ 'X-WP-Nonce': restNonce } })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.merchant_id) {
                    clearInterval(pollTimer);
                    if (msg) { msg.textContent = 'مفعّل! جاري التحديث...'; msg.style.color = '#16a34a'; }
                    setTimeout(function(){ location.reload(); }, 800);
                } else if (d && d.connect_url) {
                    clearInterval(pollTimer);
                    if (msg) { msg.innerHTML = 'Tap طلب وثائق إضافية: <a href="' + d.connect_url + '" target="_blank" class="btn btn-green">إكمال</a>'; msg.style.color = '#d97706'; }
                } else if (attempts >= maxAttempts) {
                    clearInterval(pollTimer);
                    if (msg) { msg.textContent = 'لم تكتمل المراجعة بعد. سنُحدّث تلقائياً عند الموافقة.'; msg.style.color = '#64748b'; }
                }
            })
            .catch(function(){});
    }, 4000);
    <?php endif; ?>

    var btnRefresh = $('#btnRefreshStatus');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', function(){
            var msg = $('#tapStatusMsg');
            if (msg) { msg.textContent = 'جاري التحقق...'; msg.style.color = '#64748b'; }
            fetch(restRoot + 'status', { credentials:'same-origin', headers:{ 'X-WP-Nonce': restNonce } })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.merchant_id) {
                        if (msg) { msg.textContent = 'مفعّل، جاري التحديث...'; msg.style.color = '#16a34a'; }
                        setTimeout(function(){ location.reload(); }, 800);
                    } else {
                        if (msg) { msg.textContent = 'لم يصل التفعيل بعد.'; msg.style.color = '#d97706'; }
                    }
                })
                .catch(function(){
                    if (msg) { msg.textContent = 'فشل التحقق.'; msg.style.color = '#dc2626'; }
                });
        });
    }

    refreshProgress();
})();
</script>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
