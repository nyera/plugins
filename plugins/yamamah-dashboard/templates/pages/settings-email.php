<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();
$store_name = get_bloginfo( 'name' );
$can_custom_email = get_option( 'yamama_email_custom_domain', 'no' ) === 'yes';
$custom_domain    = get_option( 'yamama_custom_domain', '' );
?>
<div class="page active" id="page-settings-email">
    <div class="page-header">
        <h1>إعدادات البريد الإلكتروني</h1>
        <p>إدارة إشعارات البريد وقوالب الرسائل</p>
    </div>

    <!-- دليل إنشاء بريد على الدومين -->
    <?php if ( $can_custom_email && $custom_domain ) : ?>
    <div class="card mb-4">
        <div class="card-header" style="cursor:pointer;" id="emailGuideToggle">
            <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary, #6366f1)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                كيف أنشئ بريد إلكتروني على دومين متجري؟
            </h3>
        </div>
        <div class="card-body" id="emailGuideBody" style="display:none;">
            <div style="background:var(--bg-alt, #f8fafc);border-radius:8px;padding:20px;margin-bottom:16px;">
                <h4 style="margin:0 0 12px 0;font-size:1rem;font-weight:600;">عبر Google Workspace (موصى به)</h4>
                <ol style="line-height:2.4;margin:0;padding-right:20px;">
                    <li>اذهب إلى <a href="https://workspace.google.com" target="_blank" rel="noopener" style="color:var(--primary, #6366f1);font-weight:500;">workspace.google.com</a> وأنشئ حساباً باستخدام دومين متجرك <code class="ltr" style="background:#e2e8f0;padding:2px 6px;border-radius:4px;"><?php echo esc_html( $custom_domain ); ?></code></li>
                    <li>أضف سجلات DNS (MX) كما يطلب Google — هذا يوجه البريد الوارد لـ Gmail</li>
                    <li>أنشئ عنوان بريد (مثل: <code class="ltr" style="background:#e2e8f0;padding:2px 6px;border-radius:4px;">info@<?php echo esc_html( $custom_domain ); ?></code>) من لوحة تحكم Google</li>
                    <li>فعّل <strong>"التحقق بخطوتين"</strong> ثم أنشئ <strong>"كلمة مرور التطبيقات"</strong> (App Password)</li>
                    <li>عُد هنا واختر <strong>"Gmail / Google Workspace"</strong> من قائمة مزود البريد أدناه</li>
                    <li>أدخل بريدك ككلمة المستخدم، و App Password ككلمة المرور</li>
                    <li>اضغط <strong>"إرسال بريد تجريبي"</strong> للتأكد</li>
                </ol>
                <p style="font-size:12px;color:#64748b;margin:12px 0 0 0;">التكلفة: تبدأ من ~25 ر.س/شهر لكل حساب بريد عبر Google</p>
            </div>

            <div style="background:var(--bg-alt, #f8fafc);border-radius:8px;padding:20px;">
                <h4 style="margin:0 0 8px 0;font-size:1rem;font-weight:600;">فحص إعدادات DNS لدومينك</h4>
                <p style="font-size:0.85rem;color:#64748b;margin:0 0 12px 0;">تحقق أن سجلات البريد (MX, SPF, DMARC) مضبوطة بشكل صحيح</p>
                <div class="flex items-center gap-3">
                    <input type="text" class="form-control ltr" id="dnsCheckDomain"
                           value="<?php echo esc_attr( $custom_domain ); ?>"
                           placeholder="example.com" style="max-width:300px;">
                    <button type="button" class="btn btn-outline" id="checkDnsBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-left:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        فحص DNS
                    </button>
                </div>
                <div id="dnsCheckResults" style="display:none;margin-top:12px;">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
                        <div id="dnsMxResult" class="dns-result-card" style="padding:12px;border-radius:6px;text-align:center;background:#f1f5f9;">
                            <div style="font-weight:600;font-size:0.9rem;">MX Records</div>
                            <div class="dns-status" style="font-size:1.5rem;margin:4px 0;">—</div>
                            <div class="dns-detail" style="font-size:0.75rem;color:#64748b;"></div>
                        </div>
                        <div id="dnsSpfResult" class="dns-result-card" style="padding:12px;border-radius:6px;text-align:center;background:#f1f5f9;">
                            <div style="font-weight:600;font-size:0.9rem;">SPF</div>
                            <div class="dns-status" style="font-size:1.5rem;margin:4px 0;">—</div>
                            <div class="dns-detail" style="font-size:0.75rem;color:#64748b;"></div>
                        </div>
                        <div id="dnsDmarcResult" class="dns-result-card" style="padding:12px;border-radius:6px;text-align:center;background:#f1f5f9;">
                            <div style="font-weight:600;font-size:0.9rem;">DMARC</div>
                            <div class="dns-status" style="font-size:1.5rem;margin:4px 0;">—</div>
                            <div class="dns-detail" style="font-size:0.75rem;color:#64748b;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- إعدادات المرسل -->
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">إعدادات المرسل</h3></div>
        <form id="yamamahEmailSenderForm">
            <div class="form-row">
                <div class="form-group">
                    <label>اسم المرسل</label>
                    <input type="text" class="form-control" name="woocommerce_email_from_name"
                           value="<?php echo esc_attr( $email_settings['from_name'] ); ?>">
                    <small class="text-xs text-muted">الاسم الذي يظهر في رسائل البريد المرسلة</small>
                </div>
                <div class="form-group">
                    <label>بريد المرسل</label>
                    <input type="email" class="form-control ltr" name="woocommerce_email_from_address"
                           value="<?php echo esc_attr( $email_settings['from_address'] ); ?>">
                    <small class="text-xs text-muted">العنوان الذي تُرسل منه الرسائل</small>
                </div>
            </div>
            <div class="form-group mt-3">
                <label>نص تذييل الرسالة</label>
                <textarea class="form-control" rows="2" name="woocommerce_email_footer_text"><?php echo esc_textarea( $email_settings['footer_text'] ); ?></textarea>
            </div>
        </form>
    </div>

    <!-- إعدادات SMTP -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">إعدادات الإرسال (SMTP)</h3>
        </div>
        <?php if ( ! $can_custom_email ) : ?>
        <div style="padding:16px 20px;background:var(--bg-alt, #f0f4ff);border-radius:0 0 8px 8px;border-top:1px solid var(--border, #e2e8f0);">
            <div style="display:flex;align-items:center;gap:10px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>
                    <strong style="font-size:0.9rem;">ربط بريد مخصص على دومينك متاح في الباقات المتقدمة</strong>
                    <p style="margin:4px 0 0;font-size:0.8rem;color:#64748b;">حالياً يتم إرسال إيميلات متجرك عبر بوابة المنصة الافتراضية.</p>
                </div>
            </div>
        </div>
        <?php else : ?>
        <form id="yamamahSmtpForm">
            <div class="form-group mb-3">
                <div class="flex items-center gap-3" style="cursor:pointer;">
                    <label class="yamamah-switch" style="margin:0;">
                        <input type="checkbox" name="yamamah_smtp_enabled" value="yes"
                               id="smtpEnabledToggle"
                               <?php checked( $smtp_settings['enabled'], 'yes' ); ?>>
                        <span class="yamamah-switch-slider"></span>
                    </label>
                    <span style="font-weight:500;">تفعيل إرسال SMTP</span>
                </div>
                <small class="text-xs text-muted" style="display:block;margin-top:0.5rem;">
                    يُنصح بتفعيل SMTP لضمان وصول الرسائل وعدم تصنيفها كبريد مزعج
                </small>
            </div>

            <div id="smtpFieldsWrapper" style="<?php echo $smtp_settings['enabled'] === 'yes' ? '' : 'display:none;'; ?>">
                <div class="form-group mb-3">
                    <label>مزود البريد</label>
                    <select id="smtpPreset" class="form-control" style="max-width:350px;">
                        <option value="">— اختر مزوداً للتعبئة التلقائية —</option>
                        <option value="gmail">Gmail / Google Workspace</option>
                        <option value="outlook">Outlook / Microsoft 365</option>
                        <option value="yahoo">Yahoo Mail</option>
                        <option value="mailgun">Mailgun</option>
                        <option value="sendgrid">SendGrid</option>
                        <option value="custom">مخصص</option>
                    </select>
                    <small class="text-xs text-muted">اختر مزوداً لتعبئة بيانات الخادم تلقائياً، أو اختر "مخصص" للإدخال يدوياً</small>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>خادم SMTP</label>
                        <input type="text" class="form-control ltr" name="yamamah_smtp_host"
                               value="<?php echo esc_attr( $smtp_settings['host'] ); ?>"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>المنفذ (Port)</label>
                        <input type="number" class="form-control ltr" name="yamamah_smtp_port"
                               value="<?php echo esc_attr( $smtp_settings['port'] ); ?>"
                               placeholder="587">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>التشفير</label>
                        <select class="form-control" name="yamamah_smtp_encryption">
                            <option value="tls" <?php selected( $smtp_settings['encryption'], 'tls' ); ?>>TLS</option>
                            <option value="ssl" <?php selected( $smtp_settings['encryption'], 'ssl' ); ?>>SSL</option>
                            <option value="" <?php selected( $smtp_settings['encryption'], '' ); ?>>بدون تشفير</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>اسم المستخدم</label>
                        <input type="text" class="form-control ltr" name="yamamah_smtp_username"
                               value="<?php echo esc_attr( $smtp_settings['username'] ); ?>"
                               placeholder="user@example.com" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <input type="password" class="form-control ltr" name="yamamah_smtp_password"
                               value="" placeholder="<?php echo ! empty( $smtp_settings['password'] ) ? '••••••••' : ''; ?>"
                               autocomplete="new-password">
                        <small class="text-xs text-muted">اتركه فارغاً للإبقاء على كلمة المرور الحالية</small>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-3">
                    <button type="button" class="btn btn-outline" id="sendTestEmailBtn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-left:4px;"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        إرسال بريد تجريبي
                    </button>
                    <span id="testEmailMsg" style="font-size:0.85rem;"></span>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- تخصيص قالب البريد -->
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">تخصيص قالب البريد</h3></div>
        <form id="yamamahEmailColorsForm">
            <div class="form-group mb-4">
                <label>شعار الهيدر (اختياري)</label>
                <div class="flex items-center gap-3">
                    <input type="text" class="form-control ltr" name="woocommerce_email_header_image"
                           id="emailHeaderImageUrl"
                           value="<?php echo esc_attr( $email_settings['header_image'] ); ?>"
                           placeholder="رابط صورة الشعار" style="flex:1;">
                    <button type="button" class="btn btn-outline btn-sm" id="uploadHeaderImageBtn">رفع صورة</button>
                    <?php if ( ! empty( $email_settings['header_image'] ) ) : ?>
                    <button type="button" class="btn btn-outline btn-sm" id="removeHeaderImageBtn" style="color:var(--red,#ef4444);">إزالة</button>
                    <?php endif; ?>
                </div>
                <small class="text-xs text-muted">الشعار يظهر في أعلى رسائل البريد الإلكتروني</small>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;" class="mb-4" id="emailColorPickers">
                <div class="form-group">
                    <label>اللون الأساسي</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="woocommerce_email_base_color"
                               value="<?php echo esc_attr( $email_settings['base_color'] ); ?>"
                               style="width:40px;height:34px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px;">
                        <input type="text" class="form-control ltr" value="<?php echo esc_attr( $email_settings['base_color'] ); ?>"
                               style="flex:1;font-size:0.8rem;" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>لون الخلفية</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="woocommerce_email_background_color"
                               value="<?php echo esc_attr( $email_settings['bg_color'] ); ?>"
                               style="width:40px;height:34px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px;">
                        <input type="text" class="form-control ltr" value="<?php echo esc_attr( $email_settings['bg_color'] ); ?>"
                               style="flex:1;font-size:0.8rem;" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>لون خلفية المحتوى</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="woocommerce_email_body_background_color"
                               value="<?php echo esc_attr( $email_settings['body_bg'] ); ?>"
                               style="width:40px;height:34px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px;">
                        <input type="text" class="form-control ltr" value="<?php echo esc_attr( $email_settings['body_bg'] ); ?>"
                               style="flex:1;font-size:0.8rem;" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label>لون النص</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="woocommerce_email_text_color"
                               value="<?php echo esc_attr( $email_settings['text_color'] ); ?>"
                               style="width:40px;height:34px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;padding:2px;">
                        <input type="text" class="form-control ltr" value="<?php echo esc_attr( $email_settings['text_color'] ); ?>"
                               style="flex:1;font-size:0.8rem;" readonly>
                    </div>
                </div>
            </div>

            <!-- معاينة حية -->
            <div style="margin-bottom:0.5rem;">
                <h4 style="margin:0 0 0.25rem 0;font-size:0.95rem;font-weight:600;">معاينة القالب</h4>
                <small class="text-xs text-muted">المعاينة تتغير مباشرة عند تعديل الألوان والنصوص</small>
            </div>
            <div id="emailMockupOuter" style="background:<?php echo esc_attr( $email_settings['bg_color'] ); ?>;padding:30px 20px;border-radius:var(--radius);border:1px solid var(--border);direction:rtl;">
                <div style="max-width:600px;margin:0 auto;font-family:Tahoma,Arial,sans-serif;">
                    <!-- Header -->
                    <div id="mockupHeader" style="background:<?php echo esc_attr( $email_settings['base_color'] ); ?>;padding:24px 30px;border-radius:8px 8px 0 0;text-align:center;">
                        <img id="mockupLogo" src="<?php echo esc_url( $email_settings['header_image'] ); ?>"
                             style="max-height:50px;margin-bottom:10px;<?php echo empty( $email_settings['header_image'] ) ? 'display:none;' : ''; ?>" alt="">
                        <div id="mockupStoreName" style="color:#ffffff;font-size:1.25rem;font-weight:700;letter-spacing:0.5px;">
                            <?php echo esc_html( $store_name ); ?>
                        </div>
                    </div>
                    <!-- Body -->
                    <div id="mockupBody" style="background:<?php echo esc_attr( $email_settings['body_bg'] ); ?>;padding:30px;color:<?php echo esc_attr( $email_settings['text_color'] ); ?>;">
                        <h2 id="mockupTitle" style="color:<?php echo esc_attr( $email_settings['base_color'] ); ?>;font-size:1.1rem;margin:0 0 15px 0;font-weight:700;">
                            طلب جديد #1234
                        </h2>
                        <p style="margin:0 0 20px 0;line-height:1.8;font-size:0.9rem;">
                            لقد تلقيت طلباً جديداً من العميل <strong>أحمد محمد</strong>. تفاصيل الطلب أدناه:
                        </p>

                        <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:0.85rem;" dir="rtl">
                            <thead>
                                <tr id="mockupTableHead">
                                    <th style="background:<?php echo esc_attr( $email_settings['base_color'] ); ?>;color:#fff;padding:10px 14px;text-align:right;border-radius:6px 0 0 0;">المنتج</th>
                                    <th style="background:<?php echo esc_attr( $email_settings['base_color'] ); ?>;color:#fff;padding:10px 14px;text-align:center;">الكمية</th>
                                    <th style="background:<?php echo esc_attr( $email_settings['base_color'] ); ?>;color:#fff;padding:10px 14px;text-align:left;border-radius:0 6px 0 0;">السعر</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:right;">منتج تجريبي 1</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:center;">2</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:left;">150 ر.س</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:right;">منتج تجريبي 2</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:center;">1</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #eee;text-align:left;">75 ر.س</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:10px 14px;text-align:right;font-weight:700;border-top:2px solid #ddd;">الإجمالي</td>
                                    <td style="padding:10px 14px;text-align:left;font-weight:700;border-top:2px solid #ddd;">225 ر.س</td>
                                </tr>
                            </tbody>
                        </table>

                        <p style="margin:0;line-height:1.8;font-size:0.9rem;">شكراً لتعاملكم معنا!</p>
                    </div>
                    <!-- Footer -->
                    <div id="mockupFooter" style="background:<?php echo esc_attr( $email_settings['base_color'] ); ?>;padding:16px 30px;border-radius:0 0 8px 8px;text-align:center;">
                        <div id="mockupFooterText" style="color:rgba(255,255,255,0.85);font-size:0.8rem;line-height:1.6;">
                            <?php echo esc_html( $email_settings['footer_text'] ? $email_settings['footer_text'] : $store_name ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- حفظ الإعدادات -->
    <div class="mb-5">
        <button type="button" class="btn btn-green" id="saveEmailSettingsBtn">حفظ جميع الإعدادات</button>
        <span id="emailSettingsMsg" style="font-size:0.85rem;margin-right:0.75rem;"></span>
    </div>

    <!-- إشعارات البريد -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">إشعارات البريد الإلكتروني</h3>
        </div>
        <?php if ( ! empty( $wc_emails ) ) : ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>الإشعار</th>
                        <th>النوع</th>
                        <th>المستقبل</th>
                        <th style="text-align:center;">الحالة</th>
                        <th style="text-align:center;">تفعيل/تعطيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $wc_emails as $email ) : ?>
                    <tr>
                        <td>
                            <strong class="text-sm"><?php echo esc_html( $email['title'] ); ?></strong>
                            <div class="text-xs text-muted" style="margin-top:2px;"><?php echo esc_html( $email['description'] ); ?></div>
                        </td>
                        <td>
                            <?php if ( $email['is_customer'] ) : ?>
                                <span class="badge badge-blue">العميل</span>
                            <?php else : ?>
                                <span class="badge badge-purple">الإدارة</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?php if ( $email['is_customer'] ) : ?>
                                <span class="text-muted">بريد العميل</span>
                            <?php else : ?>
                                <span class="ltr" style="direction:ltr;unicode-bidi:embed;"><?php echo esc_html( $email['recipient'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ( $email['enabled'] ) : ?>
                                <span class="badge badge-green">مفعّل</span>
                            <?php else : ?>
                                <span class="badge badge-gray">معطّل</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <label class="yamamah-switch" style="margin:0 auto;">
                                <input type="checkbox" class="yamamah-email-toggle"
                                       data-email-id="<?php echo esc_attr( $email['id'] ); ?>"
                                       <?php checked( $email['enabled'] ); ?>>
                                <span class="yamamah-switch-slider"></span>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
            <p class="text-sm text-muted">لم يتم العثور على إشعارات WooCommerce.</p>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    /* ── Email guide toggle ── */
    $('#emailGuideToggle').on('click', function() {
        $('#emailGuideBody').slideToggle(200);
    });

    /* ── SMTP Presets ── */
    var smtpPresets = {
        gmail:    { host: 'smtp.gmail.com',       port: 587, enc: 'tls' },
        outlook:  { host: 'smtp.office365.com',   port: 587, enc: 'tls' },
        yahoo:    { host: 'smtp.mail.yahoo.com',  port: 587, enc: 'tls' },
        mailgun:  { host: 'smtp.mailgun.org',     port: 587, enc: 'tls' },
        sendgrid: { host: 'smtp.sendgrid.net',    port: 587, enc: 'tls' },
    };
    $('#smtpPreset').on('change', function() {
        var preset = smtpPresets[this.value];
        if (preset) {
            $('[name="yamamah_smtp_host"]').val(preset.host);
            $('[name="yamamah_smtp_port"]').val(preset.port);
            $('[name="yamamah_smtp_encryption"]').val(preset.enc);
        }
    });

    /* ── DNS Check ── */
    $('#checkDnsBtn').on('click', function() {
        var $btn = $(this);
        var domain = $('#dnsCheckDomain').val().trim();
        if (!domain) return;

        $btn.prop('disabled', true).text('جاري الفحص...');
        $('#dnsCheckResults').show();
        $('.dns-result-card .dns-status').text('...');
        $('.dns-result-card .dns-detail').text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_check_email_dns',
            nonce: yamamahData.nonce,
            domain: domain
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-left:4px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> فحص DNS');
            if (res.success) {
                var d = res.data;
                setDnsCard('#dnsMxResult', d.mx, d.mx_records ? d.mx_records.join(', ') : '');
                setDnsCard('#dnsSpfResult', d.spf, d.spf ? 'موجود' : 'غير موجود');
                setDnsCard('#dnsDmarcResult', d.dmarc, d.dmarc ? 'موجود' : 'غير موجود');
            } else {
                $('.dns-result-card .dns-status').text('!').css('color', '#ef4444');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('فحص DNS');
            $('.dns-result-card .dns-status').text('!').css('color', '#ef4444');
        });
    });

    function setDnsCard(selector, ok, detail) {
        var $card = $(selector);
        $card.find('.dns-status').text(ok ? '\u2705' : '\u274c');
        $card.find('.dns-detail').text(detail);
        $card.css('background', ok ? '#f0fdf4' : '#fff1f2');
    }

    /* ── SMTP toggle ── */
    $('#smtpEnabledToggle').on('change', function() {
        $('#smtpFieldsWrapper').slideToggle(200);
    });

    /* ── Color pickers → text input sync + live mockup ── */
    $('#emailColorPickers input[type="color"]').on('input', function() {
        var name = $(this).attr('name');
        var val  = $(this).val();
        $(this).closest('.flex').find('input[type="text"]').val(val);

        if (name === 'woocommerce_email_base_color') {
            $('#mockupHeader, #mockupFooter').css('background', val);
            $('#mockupTitle').css('color', val);
            $('#mockupTableHead th').css('background', val);
        }
        if (name === 'woocommerce_email_background_color') {
            $('#emailMockupOuter').css('background', val);
        }
        if (name === 'woocommerce_email_body_background_color') {
            $('#mockupBody').css('background', val);
        }
        if (name === 'woocommerce_email_text_color') {
            $('#mockupBody').css('color', val);
        }
    });

    /* ── Footer text → live mockup ── */
    $('[name="woocommerce_email_footer_text"]').on('input', function() {
        var val = $(this).val().trim();
        $('#mockupFooterText').text(val || '<?php echo esc_js( $store_name ); ?>');
    });

    /* ── From name → mockup store name ── */
    $('[name="woocommerce_email_from_name"]').on('input', function() {
        var val = $(this).val().trim();
        $('#mockupStoreName').text(val || '<?php echo esc_js( $store_name ); ?>');
    });

    /* ── Header image → mockup logo ── */
    $('#emailHeaderImageUrl').on('input', function() {
        var val = $(this).val().trim();
        if (val) {
            $('#mockupLogo').attr('src', val).show();
        } else {
            $('#mockupLogo').hide();
        }
    });

    /* ── Upload header image via WP media library ── */
    $('#uploadHeaderImageBtn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'اختر شعار البريد', multiple: false, library: { type: 'image' } });
        frame.on('select', function() {
            var url = frame.state().get('selection').first().toJSON().url;
            $('#emailHeaderImageUrl').val(url).trigger('input');
            if (!$('#removeHeaderImageBtn').length) {
                $('#uploadHeaderImageBtn').after('<button type="button" class="btn btn-outline btn-sm" id="removeHeaderImageBtn" style="color:var(--red,#ef4444);">إزالة</button>');
            }
        });
        frame.open();
    });

    /* ── Remove header image ── */
    $(document).on('click', '#removeHeaderImageBtn', function() {
        $('#emailHeaderImageUrl').val('').trigger('input');
        $(this).remove();
    });

    /* ── Save all settings ── */
    $('#saveEmailSettingsBtn').on('click', function() {
        var $btn = $(this);
        var $msg = $('#emailSettingsMsg');
        var settings = {};

        $('#yamamahEmailSenderForm').find('input, textarea, select').each(function() {
            if (this.name) settings[this.name] = $(this).val();
        });
        $('#yamamahEmailColorsForm').find('input[name], select[name]').each(function() {
            if (this.name && this.type !== 'text') settings[this.name] = $(this).val();
        });
        settings['woocommerce_email_header_image'] = $('#emailHeaderImageUrl').val();

        var smtpData = {};
        smtpData.yamamah_smtp_enabled = $('#smtpEnabledToggle').is(':checked') ? 'yes' : 'no';
        $('#yamamahSmtpForm').find('input[name], select[name]').each(function() {
            if (this.name && this.name !== 'yamamah_smtp_enabled') {
                smtpData[this.name] = $(this).val();
            }
        });

        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_email_settings',
            nonce: yamamahData.nonce,
            settings: settings,
            smtp: smtpData
        }, function(res) {
            $btn.prop('disabled', false).text('حفظ جميع الإعدادات');
            if (res.success) {
                $msg.css('color', '#16a34a').text('تم الحفظ بنجاح');
                setTimeout(function() { $msg.text(''); }, 3000);
            } else {
                $msg.css('color', '#ef4444').text(res.data || 'حدث خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ جميع الإعدادات');
            $msg.css('color', '#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Send test email ── */
    $('#sendTestEmailBtn').on('click', function() {
        var $btn = $(this);
        var $msg = $('#testEmailMsg');
        $btn.prop('disabled', true);
        $msg.css('color', '#6366f1').text('جاري الإرسال...');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_send_test_email',
            nonce: yamamahData.nonce
        }, function(res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $msg.css('color', '#16a34a').text(res.data);
                setTimeout(function() { $msg.text(''); }, 5000);
            } else {
                $msg.css('color', '#ef4444').text(res.data || 'فشل الإرسال');
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            $msg.css('color', '#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Toggle email notifications ── */
    $('.yamamah-email-toggle').on('change', function() {
        var $cb      = $(this);
        var emailId  = $cb.data('email-id');
        var enabled  = $cb.is(':checked') ? 'yes' : 'no';
        var $row     = $cb.closest('tr');
        var $badge   = $row.find('td:eq(3) .badge');

        $badge.text('جاري...').attr('class', 'badge badge-yellow');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_toggle_email_notification',
            nonce: yamamahData.nonce,
            email_id: emailId,
            enabled: enabled
        }, function(res) {
            if (res.success) {
                if (enabled === 'yes') {
                    $badge.text('مفعّل').attr('class', 'badge badge-green');
                } else {
                    $badge.text('معطّل').attr('class', 'badge badge-gray');
                }
            } else {
                $cb.prop('checked', !$cb.is(':checked'));
                $badge.text('خطأ').attr('class', 'badge badge-red');
            }
        }).fail(function() {
            $cb.prop('checked', !$cb.is(':checked'));
            $badge.text('خطأ').attr('class', 'badge badge-red');
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
