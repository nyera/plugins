<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();
?>
<div class="page active" id="page-contact-settings">
    <div class="page-header">
        <h1>إعدادات تواصل معنا</h1>
        <p>إدارة خيارات البريد الإلكتروني لفورم التواصل ومحتوى الصفحة وحسابات التواصل الاجتماعي والأزرار العائمة</p>
    </div>

    <!-- إعدادات SMTP واستقبال البريد -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">إعدادات استقبال رسائل الفورم</h3>
        </div>
        <form id="yamamahContactSmtpForm">
            <div class="form-group mb-4">
                <label>البريد المستقبل لرسائل الفورم</label>
                <input type="email" class="form-control ltr" name="yamamah_contact_form_recipient" value="<?php echo esc_attr( $recipient ); ?>" placeholder="info@example.com">
                <small class="text-xs text-muted">الإيميل الذي ستصل إليه الرسائل المرسلة من صفحة تواصل معنا</small>
            </div>

            <div class="form-group mb-3">
                <div class="flex items-center gap-3" style="cursor:pointer;">
                    <label class="yamamah-switch" style="margin:0;">
                        <input type="checkbox" name="yamamah_smtp_enabled" value="yes" id="smtpEnabledToggle" <?php checked( $smtp_settings['enabled'], 'yes' ); ?>>
                        <span class="yamamah-switch-slider"></span>
                    </label>
                    <span style="font-weight:500;">تفعيل إرسال SMTP (مشارك مع إعدادات البريد العامة)</span>
                </div>
                <small class="text-xs text-muted" style="display:block;margin-top:0.5rem;">تفعيل SMTP ضروري لضمان وصول رسائل فورم التواصل بنجاح</small>
            </div>

            <div id="smtpFieldsWrapper" style="<?php echo $smtp_settings['enabled'] === 'yes' ? '' : 'display:none;'; ?>">
                <div class="form-group mb-3">
                    <label>مزود البريد</label>
                    <select id="smtpPreset" class="form-control" style="max-width:350px;">
                        <option value="">— اختر مزوداً للتعبئة التلقائية —</option>
                        <option value="gmail">Gmail / Google Workspace</option>
                        <option value="outlook">Outlook / Microsoft 365</option>
                        <option value="custom">مخصص</option>
                    </select>
                    <div id="gmailGuide" style="display:none; margin-top:10px; background:var(--bg-alt, #f8fafc); padding:15px; border-radius:8px; font-size:0.9rem;">
                        <h4 style="margin:0 0 10px 0;font-weight:600;">خطوات إعداد Gmail (App Password)</h4>
                        <ol style="margin:0; padding-right:20px; line-height:1.6;">
                            <li>سجل الدخول لحساب Google الخاص بك.</li>
                            <li>اذهب إلى "إدارة حسابك" > "الأمان" (Security).</li>
                            <li>فعّل <strong>"التحقق بخطوتين"</strong> (2-Step Verification) إذا لم يكن مفعلاً.</li>
                            <li>ابحث عن <strong>"كلمات مرور التطبيقات"</strong> (App Passwords).</li>
                            <li>أنشئ كلمة مرور جديدة باسم (مثال: "موقع المتجر") وانسخ الرمز المكون من 16 حرفاً.</li>
                            <li>استخدم إيميلك كـ "اسم المستخدم" والرمز المنسوخ كـ "كلمة المرور" بالأسفل.</li>
                        </ol>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>خادم SMTP</label>
                        <input type="text" class="form-control ltr" name="yamamah_smtp_host" value="<?php echo esc_attr( $smtp_settings['host'] ); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>المنفذ (Port)</label>
                        <input type="number" class="form-control ltr" name="yamamah_smtp_port" value="<?php echo esc_attr( $smtp_settings['port'] ); ?>" placeholder="587">
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
                        <input type="text" class="form-control ltr" name="yamamah_smtp_username" value="<?php echo esc_attr( $smtp_settings['username'] ); ?>" placeholder="user@example.com" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label>كلمة المرور</label>
                        <input type="password" class="form-control ltr" name="yamamah_smtp_password" value="" placeholder="<?php echo ! empty( $smtp_settings['password'] ) ? '••••••••' : ''; ?>" autocomplete="new-password">
                        <small class="text-xs text-muted">اتركه فارغاً للإبقاء على كلمة المرور الحالية</small>
                    </div>
                </div>
                <div class="flex items-center gap-3 mt-3">
                    <button type="button" class="btn btn-outline" id="sendTestEmailBtn">
                        إرسال بريد تجريبي للمستقبل
                    </button>
                    <span id="testEmailMsg" style="font-size:0.85rem;"></span>
                </div>
            </div>
        </form>
    </div>

    <?php
    $manifest_page   = array();
    $manifest_footer = array();
    foreach ( $manifest as $key => $label ) {
        $key = (string) $key;
        if ( strpos( $key, 'footer_' ) === 0 ) {
            $manifest_footer[ $key ] = $label;
        } else {
            $manifest_page[ $key ] = $label;
        }
    }
    ?>
    <!-- محتوى صفحة تواصل معنا + الفوتر (نفس النموذج للحفظ) -->
    <form id="yamamahContactContentForm">
        <?php if ( empty( $manifest ) ) : ?>
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted">لم يتم العثور على إعدادات محتوى قابلة للتعديل للثيم الحالي.</p>
                </div>
            </div>
        <?php else : ?>
            <?php if ( ! empty( $manifest_page ) ) : ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">محتوى صفحة تواصل معنا</h3>
                    <p class="text-sm text-muted" style="margin-top:4px;">المحتوى المعروض في صفحة &quot;تواصل معنا&quot; بالموقع</p>
                </div>
                <div class="card-body">
                    <?php foreach ( $manifest_page as $key => $label ) :
                        $val = isset( $content[ $key ] ) ? $content[ $key ] : '';
                    ?>
                        <div class="form-group mb-3">
                            <label><?php echo esc_html( $label ); ?></label>
                            <?php if ( strpos( $key, 'map' ) !== false || strpos( $key, 'desc' ) !== false || $key === 'hours' ) : ?>
                                <textarea class="form-control" name="contact_content[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( $val ); ?></textarea>
                            <?php else : ?>
                                <input type="text" class="form-control" name="contact_content[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $manifest_footer ) ) : ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">محتوى الفوتر</h3>
                    <p class="text-sm text-muted" style="margin-top:4px;">النصوص والعناوين المعروضة في تذييل الموقع (يمكن تعديلها من هنا دون تغيير باقي صفحة التواصل)</p>
                </div>
                <div class="card-body">
                    <?php foreach ( $manifest_footer as $key => $label ) :
                        $val = isset( $content[ $key ] ) ? $content[ $key ] : '';
                    ?>
                        <div class="form-group mb-3">
                            <label><?php echo esc_html( $label ); ?></label>
                            <?php
                            $footer_long = ( $key === 'footer_about' || $key === 'footer_copyright' );
                            if ( strpos( $key, 'map' ) !== false || strpos( $key, 'desc' ) !== false || $key === 'hours' || $footer_long ) :
                                ?>
                                <textarea class="form-control" name="contact_content[<?php echo esc_attr( $key ); ?>]" rows="<?php echo $key === 'footer_about' ? 4 : 2; ?>"><?php echo esc_textarea( $val ); ?></textarea>
                            <?php else : ?>
                                <input type="text" class="form-control" name="contact_content[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </form>

    <?php if ( ! empty( $footer_contact_manifest ) ) : ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">بيانات التواصل في الفوتر</h3>
            <p class="text-sm text-muted" style="margin-top:4px;">البريد والجوال والعنوان المعروضة في الفوتر؛ تُدار عبر جسر الثيم مع الموقع وتُحفظ مع «حفظ جميع الإعدادات».</p>
        </div>
        <div class="card-body">
            <form id="yamamahFooterContactForm">
                <?php foreach ( $footer_contact_manifest as $fc_key => $fc_label ) : ?>
                    <?php
                    $fc_key   = (string) $fc_key;
                    $fc_val   = isset( $footer_contact_settings[ $fc_key ] ) ? (string) $footer_contact_settings[ $fc_key ] : '';
                    $fc_input = 'footer_contact[' . $fc_key . ']';
                    ?>
                    <div class="form-group mb-3">
                        <label><?php echo esc_html( (string) $fc_label ); ?></label>
                        <?php if ( $fc_key === 'address' ) : ?>
                            <textarea class="form-control" name="<?php echo esc_attr( $fc_input ); ?>" rows="2"><?php echo esc_textarea( $fc_val ); ?></textarea>
                        <?php elseif ( $fc_key === 'email' ) : ?>
                            <input type="email" class="form-control ltr" name="<?php echo esc_attr( $fc_input ); ?>" value="<?php echo esc_attr( $fc_val ); ?>">
                        <?php else : ?>
                            <input type="text" class="form-control ltr" name="<?php echo esc_attr( $fc_input ); ?>" value="<?php echo esc_attr( $fc_val ); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- الأزرار العائمة -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">الأزرار العائمة (في الأسفل)</h3>
        </div>
        <form id="yamamahFloatingButtonsForm">
            <div class="form-row">
                <!-- WhatsApp -->
                <div class="form-group p-4" style="background:var(--bg-alt);border-radius:8px;">
                    <div class="flex items-center gap-3 mb-3">
                        <label class="yamamah-switch" style="margin:0;">
                            <input type="checkbox" name="yamamah_floating_wa_enabled" value="yes" <?php checked( !empty($floating['wa_enabled']) && $floating['wa_enabled'] === 'yes' ); ?>>
                            <span class="yamamah-switch-slider"></span>
                        </label>
                        <span style="font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            زر الواتساب
                        </span>
                    </div>
                    <label>رقم الواتساب</label>
                    <input type="text" class="form-control ltr" name="yamamah_floating_wa_number" value="<?php echo esc_attr( isset($floating['wa_number']) ? $floating['wa_number'] : '' ); ?>" placeholder="966500000000">
                    <small class="text-xs text-muted">بدون أصفار بالبداية أو علامة + (مثال: 96650...)</small>
                </div>

                <!-- Call -->
                <div class="form-group p-4" style="background:var(--bg-alt);border-radius:8px;">
                    <div class="flex items-center gap-3 mb-3">
                        <label class="yamamah-switch" style="margin:0;">
                            <input type="checkbox" name="yamamah_floating_call_enabled" value="yes" <?php checked( !empty($floating['call_enabled']) && $floating['call_enabled'] === 'yes' ); ?>>
                            <span class="yamamah-switch-slider"></span>
                        </label>
                        <span style="font-weight:600;display:flex;align-items:center;gap:6px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            زر الاتصال
                        </span>
                    </div>
                    <label>رقم الاتصال</label>
                    <input type="text" class="form-control ltr" name="yamamah_floating_call_number" value="<?php echo esc_attr( isset($floating['call_number']) ? $floating['call_number'] : '' ); ?>" placeholder="+966 50 000 0000">
                </div>
            </div>
        </form>
    </div>

    <?php
    $yamamah_social_platforms = array(
        array(
            'key'         => 'x',
            'label'       => 'منصة اكس (X)',
            'placeholder' => 'https://x.com/yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        ),
        array(
            'key'         => 'instagram',
            'label'       => 'إنستقرام',
            'placeholder' => 'https://instagram.com/yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
        ),
        array(
            'key'         => 'facebook',
            'label'       => 'فيسبوك',
            'placeholder' => 'https://facebook.com/yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
        ),
        array(
            'key'         => 'tiktok',
            'label'       => 'تيك توك',
            'placeholder' => 'https://www.tiktok.com/@yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>',
        ),
        array(
            'key'         => 'snapchat',
            'label'       => 'سناب شات',
            'placeholder' => 'https://www.snapchat.com/add/yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.27 14.99a3.97 3.97 0 0 1 2.73-1.02h2a3.97 3.97 0 0 1 2.73 1.02"/><path d="M12 2a5 5 0 0 0-5 4.82v2.4a7 7 0 0 1-3.96 6.31C2.1 15.6 3 12 3 12h18s.9 3.6-.04 5.53A7 7 0 0 1 17 9.22V6.82A5 5 0 0 0 12 2"/></svg>',
        ),
        array(
            'key'         => 'linkedin',
            'label'       => 'لينكد إن',
            'placeholder' => 'https://linkedin.com/company/yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>',
        ),
        array(
            'key'         => 'youtube',
            'label'       => 'يوتيوب',
            'placeholder' => 'https://youtube.com/@yourstore',
            'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
        ),
    );
    ?>
    <!-- حسابات التواصل الاجتماعي (الفوتر) -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">حسابات التواصل الاجتماعي</h3>
            <p class="text-sm text-muted" style="margin-top:4px;">أدخل رابط كل منصة تريد إظهارها في تذييل الموقع. الحقول الفارغة لا تُعرض كأيقونات.</p>
        </div>
        <form id="yamamahSocialLinksForm">
            <?php foreach ( $yamamah_social_platforms as $plat ) : ?>
                <?php
                $pkey = $plat['key'];
                $pval = isset( $social_links[ $pkey ] ) ? (string) $social_links[ $pkey ] : '';
                ?>
                <div class="form-group mb-3">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                        <span style="display:inline-flex;opacity:0.85;" aria-hidden="true"><?php echo $plat['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php echo esc_html( $plat['label'] ); ?>
                    </label>
                    <input type="url" class="form-control ltr" name="yamamah_social[<?php echo esc_attr( $pkey ); ?>]" value="<?php echo esc_attr( $pval ); ?>" placeholder="<?php echo esc_attr( $plat['placeholder'] ); ?>">
                </div>
            <?php endforeach; ?>
        </form>
    </div>

    <!-- حفظ الإعدادات -->
    <div class="mb-5">
        <button type="button" class="btn btn-green" id="saveContactSettingsBtn">حفظ جميع الإعدادات</button>
        <span id="contactSettingsMsg" style="font-size:0.85rem;margin-right:0.75rem;"></span>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    /* ── SMTP toggle ── */
    $('#smtpEnabledToggle').on('change', function() {
        $('#smtpFieldsWrapper').slideToggle(200);
    });

    /* ── SMTP Presets ── */
    var smtpPresets = {
        gmail:    { host: 'smtp.gmail.com',       port: 587, enc: 'tls' },
        outlook:  { host: 'smtp.office365.com',   port: 587, enc: 'tls' },
    };
    $('#smtpPreset').on('change', function() {
        var preset = smtpPresets[this.value];
        if (preset) {
            $('[name="yamamah_smtp_host"]').val(preset.host);
            $('[name="yamamah_smtp_port"]').val(preset.port);
            $('[name="yamamah_smtp_encryption"]').val(preset.enc);
        }
        if (this.value === 'gmail') {
            $('#gmailGuide').slideDown();
        } else {
            $('#gmailGuide').slideUp();
        }
    });

    /* ── Save all settings ── */
    $('#saveContactSettingsBtn').on('click', function() {
        var $btn = $(this);
        var $msg = $('#contactSettingsMsg');
        
        var smtpData = {
            yamamah_contact_form_recipient: $('[name="yamamah_contact_form_recipient"]').val()
        };
        smtpData.yamamah_smtp_enabled = $('#smtpEnabledToggle').is(':checked') ? 'yes' : 'no';
        $('#yamamahContactSmtpForm').find('input[name], select[name]').each(function() {
            if (this.name && this.name !== 'yamamah_smtp_enabled' && this.name !== 'yamamah_contact_form_recipient') {
                smtpData[this.name] = $(this).val();
            }
        });

        var contentData = {};
        $('#yamamahContactContentForm').find('input[name], textarea[name]').each(function() {
            if (this.name) {
                // استخراج المفتاح من contact_content[key]
                var match = this.name.match(/contact_content\[(.*?)\]/);
                if (match) {
                    contentData[match[1]] = $(this).val();
                }
            }
        });

        var floatingData = {
            yamamah_floating_wa_enabled: $('[name="yamamah_floating_wa_enabled"]').is(':checked') ? 'yes' : 'no',
            yamamah_floating_wa_number: $('[name="yamamah_floating_wa_number"]').val(),
            yamamah_floating_call_enabled: $('[name="yamamah_floating_call_enabled"]').is(':checked') ? 'yes' : 'no',
            yamamah_floating_call_number: $('[name="yamamah_floating_call_number"]').val()
        };

        var socialData = {};
        $('#yamamahSocialLinksForm').find('input[name^="yamamah_social["]').each(function() {
            var match = this.name.match(/yamamah_social\[(.*?)\]/);
            if (match) {
                socialData[match[1]] = $(this).val();
            }
        });

        var footerContactData = {};
        $('#yamamahFooterContactForm').find('input[name], textarea[name]').each(function() {
            var fm = this.name && this.name.match(/footer_contact\[(.*?)\]/);
            if (fm) {
                footerContactData[fm[1]] = $(this).val();
            }
        });

        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_contact_page_settings',
            nonce: yamamahData.nonce,
            smtp: smtpData,
            content: contentData,
            floating: floatingData,
            social: socialData,
            footer_contact: footerContactData
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
            action: 'yamamah_test_contact_email',
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
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
