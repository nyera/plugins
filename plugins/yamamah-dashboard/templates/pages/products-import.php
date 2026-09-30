<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$last_import = get_option( 'yamamah_last_csv_import', array() );

ob_start();
?>

<div class="page active" id="page-products-import">
    <div class="page-header">
        <h1>استيراد/تصدير المنتجات (CSV)</h1>
        <p>الصيغة متطابقة 100% مع WooCommerce CSV Importer — يمكنك التصدير، التعديل في Excel، ثم إعادة الرفع.</p>
    </div>

    <!-- ========== 1) تنزيل قالب فارغ ========== -->
    <div class="yamamah-connection-card mb-5">
        <h2 class="card-section-title">١) تنزيل قالب فارغ</h2>
        <p style="color:#475569;line-height:1.9;">
            ابدأ بتحميل ملف CSV قالب يحتوي على جميع الأعمدة المطلوبة وصفّاً مثاليّاً واحداً لتفهم تنسيق كل عمود.
        </p>
        <button type="button" class="btn btn-outline" id="btnDownloadTemplate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            تنزيل القالب الفارغ
        </button>
    </div>

    <!-- ========== 2) تصدير المنتجات الحالية ========== -->
    <div class="yamamah-connection-card mb-5">
        <h2 class="card-section-title">٢) تصدير المنتجات الحالية</h2>
        <p style="color:#475569;line-height:1.9;">
            تصدير جميع منتجات متجرك الحالية إلى ملف CSV. مفيد للنسخ الاحتياطي أو التعديل بالجملة.
        </p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="btn btn-outline" id="btnExportCsv">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                تصدير كل المنتجات
            </button>
            <span id="exportStatus" class="text-sm" style="color:#64748b;"></span>
        </div>
    </div>

    <!-- ========== 3) رفع ملف CSV ========== -->
    <div class="yamamah-connection-card mb-5">
        <h2 class="card-section-title">٣) رفع ملف CSV</h2>
        <p style="color:#475569;line-height:1.9;">
            ارفع ملف CSV لإضافة/تحديث منتجاتك دفعةً واحدة. الملفات الكبيرة (>100 صف) تُعالج في الخلفية.
        </p>
        <div style="margin:16px 0;">
            <label style="display:block;margin-bottom:8px;">
                <input type="checkbox" id="updateExisting" checked />
                تحديث المنتج الموجود إذا تطابق SKU (وإلا، تخطّيه)
            </label>
            <label style="display:block;">
                <input type="checkbox" id="createCategories" checked />
                إنشاء التصنيفات والوسوم تلقائياً إن لم تكن موجودة
            </label>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label class="btn btn-outline" style="cursor:pointer;">
                <span>اختر ملف CSV</span>
                <input type="file" id="csvFile" accept=".csv,text/csv" style="display:none;" />
            </label>
            <button type="button" class="btn btn-green" id="btnImportCsv" disabled>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                رفع واستيراد
            </button>
            <span id="csvFileName" class="text-sm" style="color:#475569;"></span>
        </div>
        <div id="importProgress" style="display:none;margin-top:16px;padding:12px;background:#f0f9ff;border-radius:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span id="importStatus" style="font-weight:600;">جارٍ المعالجة...</span>
                <span id="importCount" style="color:#0ea5e9;"></span>
            </div>
            <div style="height:6px;background:#e2e8f0;border-radius:6px;margin-top:8px;overflow:hidden;">
                <div id="importBar" style="height:100%;width:0%;background:#10b981;transition:width .3s;"></div>
            </div>
        </div>
    </div>

    <!-- ========== 4) سجل آخر استيراد ========== -->
    <?php if ( ! empty( $last_import ) ) : ?>
    <div class="yamamah-connection-card mb-5">
        <h2 class="card-section-title">٤) سجل آخر استيراد</h2>
        <table class="widefat" style="margin-top:8px;">
            <tbody>
                <tr><td><strong>التاريخ</strong></td><td><?php echo esc_html( $last_import['date'] ?? '—' ); ?></td></tr>
                <tr><td><strong>إجمالي السطور</strong></td><td><?php echo (int) ( $last_import['total'] ?? 0 ); ?></td></tr>
                <tr><td><strong>تم بنجاح</strong></td><td style="color:#16a34a;"><?php echo (int) ( $last_import['success'] ?? 0 ); ?></td></tr>
                <tr><td><strong>تم تخطّيها</strong></td><td style="color:#d97706;"><?php echo (int) ( $last_import['skipped'] ?? 0 ); ?></td></tr>
                <tr><td><strong>فشل</strong></td><td style="color:#dc2626;"><?php echo (int) ( $last_import['failed'] ?? 0 ); ?></td></tr>
            </tbody>
        </table>
        <?php if ( ! empty( $last_import['errors'] ) ) : ?>
        <details style="margin-top:12px;">
            <summary style="cursor:pointer;color:#dc2626;">عرض الأخطاء (<?php echo count( $last_import['errors'] ); ?>)</summary>
            <ul style="margin-top:8px;padding-right:20px;font-size:13px;">
                <?php foreach ( array_slice( $last_import['errors'], 0, 50 ) as $err ) : ?>
                    <li>السطر <?php echo (int) ( $err['row'] ?? 0 ); ?>: <?php echo esc_html( $err['msg'] ?? '' ); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ========== مرجع الأعمدة ========== -->
    <div class="yamamah-connection-card mb-5" style="background:#fafafa;">
        <h2 class="card-section-title">مرجع الأعمدة (WooCommerce القياسية)</h2>
        <p style="color:#64748b;font-size:13px;line-height:1.8;">
            يدعم القالب جميع أعمدة WooCommerce القياسية. أبرزها: <code>SKU</code>, <code>Name</code>, <code>Type</code> (simple/variable),
            <code>Regular price</code>, <code>Sale price</code>, <code>Stock</code>, <code>Categories</code> (مفصولة بـ <code>></code> للتسلسل وفاصلة للمتوازية),
            <code>Tags</code> (فواصل), <code>Images</code> (URLs بفواصل — أول صورة هي الرئيسية), <code>Description</code>, <code>Short description</code>,
            <code>Weight (kg)</code>, <code>Length/Width/Height (cm)</code>, <code>Attribute 1 name</code>, <code>Attribute 1 value(s)</code>.
        </p>
    </div>

</div>

<script>
(function(){
    var nonce   = '<?php echo wp_create_nonce( 'yamamah_nonce' ); ?>';
    var ajaxUrl = (window.yamamahData && yamamahData.ajax_url) ? yamamahData.ajax_url : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    function $(s){ return document.querySelector(s); }

    // 1) Download template
    $('#btnDownloadTemplate').addEventListener('click', function(){
        var url = ajaxUrl + '?action=yamamah_download_products_template&nonce=' + encodeURIComponent(nonce);
        window.location.href = url;
    });

    // 2) Export CSV
    $('#btnExportCsv').addEventListener('click', function(){
        var status = $('#exportStatus');
        status.textContent = 'جاري تجهيز الملف...';
        status.style.color = '#64748b';
        var url = ajaxUrl + '?action=yamamah_export_products_csv&nonce=' + encodeURIComponent(nonce);
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = url;
        document.body.appendChild(iframe);
        setTimeout(function(){
            status.textContent = 'بدأ التحميل ✓';
            status.style.color = '#16a34a';
        }, 1500);
    });

    // 3) Import CSV
    var fileInput = $('#csvFile');
    var btnImport = $('#btnImportCsv');
    var fileNameLabel = $('#csvFileName');

    fileInput.addEventListener('change', function(){
        if (fileInput.files && fileInput.files[0]) {
            fileNameLabel.textContent = fileInput.files[0].name + ' (' + Math.round(fileInput.files[0].size/1024) + 'KB)';
            btnImport.disabled = false;
        } else {
            fileNameLabel.textContent = '';
            btnImport.disabled = true;
        }
    });

    btnImport.addEventListener('click', function(){
        if (!fileInput.files || !fileInput.files[0]) return;

        var prog = $('#importProgress');
        var statusEl = $('#importStatus');
        var countEl = $('#importCount');
        var bar = $('#importBar');
        prog.style.display = 'block';
        statusEl.textContent = 'جاري الرفع...';
        bar.style.width = '10%';
        btnImport.disabled = true;

        var fd = new FormData();
        fd.append('action', 'yamamah_import_products_csv');
        fd.append('nonce', nonce);
        fd.append('csv_file', fileInput.files[0]);
        fd.append('update_existing', $('#updateExisting').checked ? '1' : '0');
        fd.append('create_categories', $('#createCategories').checked ? '1' : '0');

        fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(r){
                bar.style.width = '100%';
                if (r && r.success) {
                    if (r.data && r.data.scheduled) {
                        statusEl.textContent = 'تم استلام الملف. تتم المعالجة في الخلفية.';
                        countEl.textContent = '(الإجمالي: ' + r.data.total + ' صف)';
                        statusEl.style.color = '#0ea5e9';
                    } else {
                        statusEl.textContent = '✓ اكتمل الاستيراد';
                        countEl.textContent = 'نجاح: ' + (r.data.success||0) + ' · تخطي: ' + (r.data.skipped||0) + ' · فشل: ' + (r.data.failed||0);
                        statusEl.style.color = '#16a34a';
                        setTimeout(function(){ location.reload(); }, 2000);
                    }
                } else {
                    statusEl.textContent = (r && r.data && r.data.message) || 'فشل الاستيراد';
                    statusEl.style.color = '#dc2626';
                }
                btnImport.disabled = false;
            })
            .catch(function(e){
                bar.style.width = '100%';
                statusEl.textContent = 'خطأ: ' + (e.message || 'فشل الاتصال');
                statusEl.style.color = '#dc2626';
                btnImport.disabled = false;
            });
    });
})();
</script>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
