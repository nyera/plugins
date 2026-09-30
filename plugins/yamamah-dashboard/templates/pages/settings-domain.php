<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$store_id    = get_option( 'yamama_store_id', '' );
$factory_url = get_option( 'yamama_factory_url', '' );
$api_token   = get_option( 'yamama_api_token', '' );
$custom_domain  = get_option( 'yamama_custom_domain', '' );
$domain_status  = get_option( 'yamama_domain_status', '' );
$current_url    = home_url();
$plan_features  = get_option( 'yamama_plan_features', array() );
if ( is_string( $plan_features ) ) {
    $plan_features = json_decode( $plan_features, true ) ?: array();
}
$domain_allowed = ! empty( $plan_features['custom_domain'] );

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'custom_domain' ) ) {
    $page_content = yamamah_upgrade_banner( 'النطاق المخصص' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

ob_start();
?>
<div class="page active" id="page-settings-domain">
    <div class="page-header">
        <h1>النطاق المخصص</h1>
        <p>ربط نطاق خاص بمتجرك بدلاً من النطاق الفرعي الافتراضي</p>
    </div>

    <?php if ( empty( $store_id ) || empty( $factory_url ) ) : ?>
        <div class="card">
            <div style="text-align:center; padding: 40px 20px;">
                <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;">&#9888;&#65039;</div>
                <h3 style="margin-bottom: 8px;">المتجر غير مرتبط بالخادم</h3>
                <p style="color: var(--muted-fg); font-size: 14px;">يرجى التواصل مع الدعم الفني.</p>
            </div>
        </div>
    <?php else : ?>

        <!-- Current domain info -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 12px;">معلومات النطاق الحالي</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>رابط المتجر الحالي</label>
                    <input type="text" class="form-control ltr" value="<?php echo esc_attr( $current_url ); ?>" readonly style="background: var(--muted); cursor: default;">
                </div>
                <div class="form-group">
                    <label>الحالة</label>
                    <?php if ( $domain_status === 'active' && $custom_domain ) : ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; background: rgba(16,192,122,0.1); border: 1px solid rgba(16,192,122,0.3);">
                            <span style="color: #10C07A; font-weight: 600;">&#10003; النطاق المخصص مفعّل (<?php echo esc_html( $custom_domain ); ?>)</span>
                        </div>
                    <?php elseif ( $domain_status === 'pending_dns' && $custom_domain ) : ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);">
                            <span style="color: #f59e0b; font-weight: 600;">&#9203; بانتظار إعدادات DNS</span>
                        </div>
                    <?php elseif ( $domain_status === 'verifying' ) : ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);">
                            <span style="color: #3b82f6; font-weight: 600;">&#128260; جاري التحقق وإصدار الشهادة...</span>
                        </div>
                    <?php else : ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; background: var(--muted); border: 1px solid var(--border);">
                            <span style="color: var(--muted-fg);">لم يتم ربط نطاق مخصص</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ( $domain_status === 'active' && $custom_domain ) : ?>
        <!-- Remove domain -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 12px;">إزالة النطاق المخصص</h3>
            <p style="font-size: 13px; color: var(--muted-fg); margin-bottom: 16px;">سيتم إزالة النطاق المخصص والعودة إلى النطاق الفرعي الافتراضي.</p>
            <button type="button" class="btn btn-red" id="btn-remove-domain" style="background: #ef4444; color: #fff; border: none;">
                <span class="btn-label">إزالة النطاق المخصص</span>
                <span class="btn-spinner" style="display:none;">جاري الإزالة...</span>
            </button>
        </div>
        <?php endif; ?>

        <?php if ( $domain_status !== 'active' ) : ?>
        <!-- Domain input -->
        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 12px;">ربط نطاق مخصص</h3>
            <p style="font-size: 13px; color: var(--muted-fg); margin-bottom: 16px;">أدخل اسم النطاق الذي تملكه وتريد ربطه بمتجرك. تأكد من أنك تملك صلاحية تعديل إعدادات DNS الخاصة بالنطاق.</p>

            <div class="form-group" style="max-width: 500px;">
                <label>اسم النطاق</label>
                <input type="text" class="form-control ltr" id="domain-input" placeholder="example.com" value="<?php echo esc_attr( $custom_domain ); ?>">
            </div>

            <button type="button" class="btn btn-green" id="btn-attach-domain" style="margin-top: 8px;">
                <span class="btn-label">ربط النطاق</span>
                <span class="btn-spinner" style="display:none;">جاري الربط...</span>
            </button>
        </div>

        <!-- DNS Instructions -->
        <div class="card" id="dns-instructions-card" style="margin-bottom: 20px; <?php echo ( $domain_status === 'pending_dns' ) && $custom_domain ? '' : 'display:none;'; ?>">
            <h3 style="margin-bottom: 12px;">إعدادات DNS المطلوبة</h3>
            <p style="font-size: 13px; color: var(--muted-fg); margin-bottom: 16px;">أضف سجل DNS التالي في لوحة إدارة النطاق الخاص بك (GoDaddy, Namecheap, Cloudflare, etc.):</p>

            <div style="background: var(--muted); border-radius: 8px; padding: 16px; direction: ltr; font-family: monospace; font-size: 14px; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <th style="text-align: left; padding: 8px; font-weight: 600;">Type</th>
                            <th style="text-align: left; padding: 8px; font-weight: 600;">Name</th>
                            <th style="text-align: left; padding: 8px; font-weight: 600;">Value</th>
                            <th style="text-align: left; padding: 8px; font-weight: 600;">TTL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 8px;"><span style="background: rgba(16,192,122,0.15); color: #10C07A; padding: 2px 8px; border-radius: 4px; font-weight: 600;">A</span></td>
                            <td style="padding: 8px;">@</td>
                            <td style="padding: 8px;" id="dns-ip-value"><?php echo esc_html( get_option( 'yamama_dns_target_ip', '—' ) ); ?></td>
                            <td style="padding: 8px;">3600</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); border-radius: 8px; padding: 12px; margin-top: 16px;">
                <p style="font-size: 12px; color: #92600a; margin: 0;">
                    <strong>ملاحظة:</strong> بعد إضافة سجل DNS، قد يستغرق التفعيل من دقائق إلى 48 ساعة حسب مزود النطاق. بعد إضافة السجل، اضغط "تحقق من DNS" أدناه.
                </p>
            </div>

            <div style="margin-top: 16px; display: flex; gap: 10px;">
                <button type="button" class="btn btn-green" id="btn-verify-dns">
                    <span class="btn-label">تحقق من DNS وفعّل النطاق</span>
                    <span class="btn-spinner" style="display:none;">جاري التحقق...</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Progress / Result -->
        <div class="card" id="domain-progress-card" style="display:none; margin-bottom: 20px;">
            <h3 style="margin-bottom: 12px;" id="progress-title">جاري تفعيل النطاق...</h3>
            <div id="domain-steps-list"></div>
            <div id="domain-final-msg" style="margin-top: 16px; display: none;"></div>
        </div>

        <!-- Error card -->
        <div class="card" id="domain-result-card" style="display:none;">
            <div id="domain-result-content"></div>
        </div>

    <?php endif; ?>
</div>

<script>
(function() {
    var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    var nonce   = '<?php echo esc_js( wp_create_nonce( 'yamamah_nonce' ) ); ?>';

    function setLoading(btn, on) {
        if (!btn) return;
        var label = btn.querySelector('.btn-label');
        var spinner = btn.querySelector('.btn-spinner');
        btn.disabled = on;
        if (label) label.style.display = on ? 'none' : '';
        if (spinner) spinner.style.display = on ? '' : 'none';
    }

    function showResult(type, msg) {
        var card = document.getElementById('domain-result-card');
        var content = document.getElementById('domain-result-content');
        if (!card || !content) return;
        card.style.display = '';
        var bg = type === 'success' ? 'rgba(16,192,122,0.1)' : 'rgba(239,68,68,0.1)';
        var border = type === 'success' ? 'rgba(16,192,122,0.3)' : 'rgba(239,68,68,0.3)';
        var color = type === 'success' ? '#10C07A' : '#ef4444';
        content.innerHTML = '<div style="padding:12px;border-radius:8px;background:'+bg+';border:1px solid '+border+';color:'+color+';font-weight:500;">'+msg+'</div>';
    }

    function hideResult() {
        var card = document.getElementById('domain-result-card');
        if (card) card.style.display = 'none';
    }

    // ── Attach domain ──
    var attachBtn = document.getElementById('btn-attach-domain');
    if (attachBtn) {
        attachBtn.addEventListener('click', function() {
            var domain = document.getElementById('domain-input').value.trim();
            if (!domain) { showResult('error', 'يرجى إدخال اسم النطاق.'); return; }

            domain = domain.replace(/^https?:\/\//, '').replace(/\/+$/, '');
            if (!/^[a-zA-Z0-9]([a-zA-Z0-9-]*\.)+[a-zA-Z]{2,}$/.test(domain)) {
                showResult('error', 'صيغة النطاق غير صحيحة. مثال: example.com');
                return;
            }

            hideResult();
            setLoading(attachBtn, true);
            var fd = new FormData();
            fd.append('action', 'yamamah_attach_domain');
            fd.append('nonce', nonce);
            fd.append('domain', domain);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    setLoading(attachBtn, false);
                    if (res.success) {
                        var dns = res.data.dns_instructions;
                        document.getElementById('dns-ip-value').textContent = dns.value;
                        document.getElementById('dns-instructions-card').style.display = '';
                        showResult('success', 'تم ربط النطاق بنجاح. أضف سجل DNS أدناه ثم اضغط "تحقق من DNS وفعّل النطاق".');
                    } else {
                        showResult('error', res.data.message || 'حدث خطأ.');
                    }
                })
                .catch(function() {
                    setLoading(attachBtn, false);
                    showResult('error', 'خطأ في الاتصال.');
                });
        });
    }

    // ── Verify DNS ──
    var verifyBtn = document.getElementById('btn-verify-dns');
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            setLoading(verifyBtn, true);
            hideResult();
            var fd = new FormData();
            fd.append('action', 'yamamah_verify_domain');
            fd.append('nonce', nonce);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    setLoading(verifyBtn, false);
                    if (res.success) {
                        var jobId = res.data.job_id;
                        showProgressCard();
                        pollJobStatus(jobId);
                    } else {
                        showResult('error', res.data.message || 'DNS لم يتم التحقق منه بعد.');
                    }
                })
                .catch(function() {
                    setLoading(verifyBtn, false);
                    showResult('error', 'خطأ في الاتصال.');
                });
        });
    }

    // ── Progress polling ──
    var stepLabels = {
        'update_vhost_domain': 'تحديث إعدادات الخادم',
        'issue_ssl_domain': 'إصدار شهادة SSL',
        'update_site_url_domain': 'تحديث رابط المتجر',
        'mark_domain_active': 'تفعيل النطاق'
    };

    function showProgressCard() {
        var card = document.getElementById('domain-progress-card');
        if (card) card.style.display = '';
        var dnsCard = document.getElementById('dns-instructions-card');
        if (dnsCard) dnsCard.style.display = 'none';
        hideResult();
    }

    function renderSteps(steps) {
        var list = document.getElementById('domain-steps-list');
        if (!list) return;
        var html = '';
        steps.forEach(function(s) {
            var icon = '&#9898;';
            var color = 'var(--muted-fg)';
            if (s.status === 'running') { icon = '&#9203;'; color = '#3b82f6'; }
            else if (s.status === 'completed') { icon = '&#10004;'; color = '#10C07A'; }
            else if (s.status === 'failed') { icon = '&#10006;'; color = '#ef4444'; }
            html += '<div style="display:flex;align-items:center;gap:10px;padding:8px 0;font-size:14px;color:'+color+'">';
            html += '<span style="font-size:18px;">'+icon+'</span>';
            html += '<span>'+(stepLabels[s.name] || s.name)+'</span>';
            if (s.error) html += '<span style="font-size:12px;color:#ef4444;margin-right:8px;">— '+s.error+'</span>';
            html += '</div>';
        });
        list.innerHTML = html;
    }

    function pollJobStatus(jobId) {
        var fd = new FormData();
        fd.append('action', 'yamamah_poll_domain_job');
        fd.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success) {
                    showResult('error', res.data.message || 'فشل في جلب حالة العملية.');
                    return;
                }
                var data = res.data;
                renderSteps(data.steps || []);

                if (data.status === 'completed') {
                    var title = document.getElementById('progress-title');
                    if (title) title.textContent = 'تم تفعيل النطاق بنجاح!';
                    var msg = document.getElementById('domain-final-msg');
                    if (msg) {
                        msg.style.display = '';
                        msg.innerHTML = '<div style="padding:12px;border-radius:8px;background:rgba(16,192,122,0.1);border:1px solid rgba(16,192,122,0.3);color:#10C07A;font-weight:500;">' +
                            'تم تفعيل النطاق المخصص بنجاح! <a href="'+data.store_url+'" target="_blank" style="color:#10C07A;text-decoration:underline;">افتح المتجر</a>' +
                            '<br><small style="color:#64748b;">سيتم إعادة تحميل الصفحة خلال ثوان...</small></div>';
                    }
                    setTimeout(function() { location.reload(); }, 5000);
                } else if (data.status === 'failed') {
                    var title = document.getElementById('progress-title');
                    if (title) title.textContent = 'فشل تفعيل النطاق';
                    showResult('error', data.error || 'فشلت عملية ربط النطاق. يرجى المحاولة مرة أخرى.');
                } else {
                    setTimeout(function() { pollJobStatus(jobId); }, 4000);
                }
            })
            .catch(function() {
                setTimeout(function() { pollJobStatus(jobId); }, 5000);
            });
    }

    // ── Remove domain ──
    var removeBtn = document.getElementById('btn-remove-domain');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            if (!confirm('هل أنت متأكد من إزالة النطاق المخصص؟ سيعود المتجر إلى النطاق الفرعي الافتراضي.')) return;

            setLoading(removeBtn, true);
            hideResult();
            var fd = new FormData();
            fd.append('action', 'yamamah_remove_domain');
            fd.append('nonce', nonce);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    setLoading(removeBtn, false);
                    if (res.success) {
                        showResult('success', 'تم إزالة النطاق المخصص بنجاح. جاري إعادة تحميل الصفحة...');
                        setTimeout(function() {
                            window.location.href = res.data.redirect || location.href;
                        }, 2000);
                    } else {
                        showResult('error', res.data.message || 'فشل إزالة النطاق.');
                    }
                })
                .catch(function() {
                    setLoading(removeBtn, false);
                    showResult('error', 'خطأ في الاتصال.');
                });
        });
    }
})();
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
