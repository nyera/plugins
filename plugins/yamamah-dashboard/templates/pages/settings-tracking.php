<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();

$t = $tracking;

$platforms = array(
    'gtm' => array(
        'label'       => 'Google Tag Manager',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12.003 23.994l-5.5-5.5 5.5-5.5 5.5 5.5-5.5 5.5z" fill="#8AB4F8"/><path d="M18.497 17.5l-5.5-5.5 5.5-5.5 5.5 5.5-5.5 5.5z" fill="#4285F4"/><path d="M5.503 17.5L.003 12l5.5-5.5 5.5 5.5-5.5 5.5z" fill="#8AB4F8"/><path d="M12.003.006l5.5 5.5-5.5 5.5-5.5-5.5 5.5-5.5z" fill="#246FDB"/></svg>',
        'placeholder' => 'GTM-XXXXXXX',
        'field'       => 'gtm_id',
        'help'        => 'https://tagmanager.google.com/',
        'color'       => '#4285F4',
        'note'        => 'إذا كنت تستخدم GTM يمكنك إدارة جميع أكواد التتبع من خلاله',
    ),
    'ga4' => array(
        'label'       => 'Google Analytics 4',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="1" y="14" width="6" height="9" rx="1" fill="#F9AB00"/><rect x="9" y="8" width="6" height="15" rx="1" fill="#E37400"/><rect x="17" y="1" width="6" height="22" rx="1" fill="#F9AB00"/></svg>',
        'placeholder' => 'G-XXXXXXXXXX',
        'field'       => 'ga4_id',
        'help'        => 'https://analytics.google.com/',
        'color'       => '#F9AB00',
    ),
    'fb_pixel' => array(
        'label'       => 'Meta (Facebook) Pixel',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.017 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.93-1.956 1.886v2.258h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>',
        'placeholder' => '123456789012345',
        'field'       => 'fb_pixel_id',
        'help'        => 'https://business.facebook.com/events_manager',
        'color'       => '#1877F2',
    ),
    'tiktok' => array(
        'label'       => 'TikTok Pixel',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#000"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.88-2.89 2.89 2.89 0 012.88-2.89c.28 0 .56.04.82.12V9a6.33 6.33 0 00-.82-.05A6.34 6.34 0 003.15 15.3a6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.34-6.34V9.26a8.28 8.28 0 004.84 1.56V7.38a4.85 4.85 0 01-1.08-.69z"/></svg>',
        'placeholder' => 'CXXXXXXXXXXXXXXXXX',
        'field'       => 'tiktok_pixel_id',
        'help'        => 'https://ads.tiktok.com/',
        'color'       => '#000000',
    ),
    'snap' => array(
        'label'       => 'Snapchat Pixel',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#FFFC00"><path d="M12.166 1C8.94 1 6.6 3.195 6.6 6.57v2.862c-.6.09-1.14.27-1.14.69 0 .57.96.72 1.08 1.08.12.36-.24.72-.96 1.44-1.14 1.14-2.58 1.5-2.58 2.16 0 .54.72.84 1.68 1.08 1.2.3 2.04.18 2.4.84.36.66-.24 1.74-.24 1.74S8.22 23 12 23s5.16-4.5 5.16-4.5-.6-1.08-.24-1.74c.36-.66 1.2-.54 2.4-.84.96-.24 1.68-.54 1.68-1.08 0-.66-1.44-1.02-2.58-2.16-.72-.72-1.08-1.08-.96-1.44.12-.36 1.08-.51 1.08-1.08 0-.42-.54-.6-1.14-.69V6.57C17.4 3.195 15.36 1 12.166 1z"/></svg>',
        'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
        'field'       => 'snap_pixel_id',
        'help'        => 'https://ads.snapchat.com/',
        'color'       => '#FFFC00',
    ),
    'twitter' => array(
        'label'       => 'Twitter / X Pixel',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#000"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        'placeholder' => 'xxxxx',
        'field'       => 'twitter_pixel_id',
        'help'        => 'https://ads.twitter.com/',
        'color'       => '#000000',
    ),
    'pinterest' => array(
        'label'       => 'Pinterest Tag',
        'icon'        => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#E60023"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>',
        'placeholder' => '1234567890123',
        'field'       => 'pinterest_tag_id',
        'help'        => 'https://ads.pinterest.com/',
        'color'       => '#E60023',
    ),
);

$validation_patterns = array(
    'gtm_id'           => '^GTM-[A-Z0-9]{4,10}$',
    'ga4_id'           => '^G-[A-Za-z0-9]{6,14}$',
    'fb_pixel_id'      => '^[0-9]{8,25}$',
    'tiktok_pixel_id'  => '^C[A-Za-z0-9]{4,35}$',
    'snap_pixel_id'    => '^[a-f0-9\\-]{20,60}$',
    'twitter_pixel_id' => '^[a-zA-Z0-9]{2,25}$',
    'pinterest_tag_id' => '^[0-9]{8,20}$',
);
?>
<div class="page active" id="page-settings-tracking">
    <div class="page-header">
        <h1>أكواد التتبع والتحليلات</h1>
        <p>إدارة أكواد التتبع لمنصات التحليلات والإعلانات</p>
    </div>

    <!-- حالة التثبيت -->
    <div class="card mb-4" id="verifyPanel" style="display:none;">
        <div class="card-header"><h3 class="card-title">حالة التثبيت</h3></div>
        <div id="verifyResults" style="padding:0 1rem 1rem;"></div>
    </div>

    <!-- المنصات -->
    <?php foreach ( $platforms as $key => $p ) :
        $enabled = ( $t[ $key . '_enabled' ] ?? 'no' ) === 'yes';
        $value   = $t[ $p['field'] ] ?? '';
        $is_gtm  = $key === 'gtm';
    ?>
    <div class="card mb-3 tracking-platform-card" data-platform="<?php echo esc_attr( $key ); ?>">
        <div class="flex items-center justify-between" style="padding:1rem 1.25rem;">
            <div class="flex items-center gap-3">
                <div class="tracking-platform-icon" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:<?php echo $is_gtm ? '#e8f0fe' : 'var(--accent,#f3f4f6)'; ?>;">
                    <?php echo $p['icon']; ?>
                </div>
                <div>
                    <strong class="text-sm"><?php echo esc_html( $p['label'] ); ?></strong>
                    <?php if ( ! empty( $p['note'] ) ) : ?>
                        <div class="text-xs text-muted" style="margin-top:2px;"><?php echo esc_html( $p['note'] ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <label class="yamamah-switch" style="margin:0;">
                <input type="checkbox" class="tracking-toggle" data-key="<?php echo esc_attr( $key ); ?>" <?php checked( $enabled ); ?>>
                <span class="yamamah-switch-slider"></span>
            </label>
        </div>
        <div class="tracking-fields" data-key="<?php echo esc_attr( $key ); ?>" style="<?php echo ! $enabled ? 'display:none;' : ''; ?>padding:0 1.25rem 1.25rem;">
            <div class="flex gap-2 items-end">
                <div class="form-group" style="flex:1;margin:0;">
                    <label><?php echo esc_html( $p['label'] ); ?> ID</label>
                    <input type="text" class="form-control tracking-id-input" id="track_<?php echo esc_attr( $p['field'] ); ?>"
                           value="<?php echo esc_attr( $value ); ?>"
                           placeholder="<?php echo esc_attr( $p['placeholder'] ); ?>"
                           data-pattern="<?php echo esc_attr( $validation_patterns[ $p['field'] ] ?? '' ); ?>"
                           data-field="<?php echo esc_attr( $p['field'] ); ?>"
                           style="direction:ltr;text-align:left;font-family:monospace;">
                    <small class="tracking-validation-msg" style="display:none;margin-top:4px;font-size:0.75rem;"></small>
                </div>
                <?php if ( ! empty( $p['help'] ) ) : ?>
                <a href="<?php echo esc_url( $p['help'] ); ?>" target="_blank" class="btn btn-sm btn-outline" title="كيف أحصل على المعرّف؟" style="white-space:nowrap;height:38px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- كود مخصص -->
    <div class="card mb-4">
        <div class="card-header flex items-center justify-between">
            <h3 class="card-title">كود مخصص (Head)</h3>
            <span class="badge badge-yellow" style="font-size:0.65rem;">للمتقدمين فقط</span>
        </div>
        <div style="padding:0 1.25rem 1.25rem;">
            <p class="text-xs text-muted mb-2">أي كود تتبع إضافي سيتم حقنه في &lt;head&gt; مباشرة. استخدم هذا الحقل لمنصات لم تُذكر أعلاه.</p>
            <textarea class="form-control" id="track_custom_head_code" rows="5" placeholder="<!-- الصق كود التتبع هنا -->" style="direction:ltr;text-align:left;font-family:monospace;font-size:0.8rem;resize:vertical;"><?php echo esc_textarea( $t['custom_head_code'] ?? '' ); ?></textarea>
        </div>
    </div>

    <!-- أزرار -->
    <div class="flex gap-3 items-center flex-wrap">
        <button type="button" class="btn btn-green" id="saveTrackingBtn">حفظ الإعدادات</button>
        <button type="button" class="btn btn-outline" id="verifyTrackingBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            تحقق من التثبيت
        </button>
        <span id="trackingSaveMsg" style="font-size:0.85rem;"></span>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    /* ── Toggle platform fields ── */
    $('.tracking-toggle').on('change', function() {
        var key = $(this).data('key');
        $('.tracking-fields[data-key="' + key + '"]').slideToggle(200);
    });

    /* ── Real-time validation ── */
    $('.tracking-id-input').on('input', function() {
        var $input = $(this);
        var pattern = $input.data('pattern');
        var val = $.trim($input.val());
        var $msg = $input.closest('.form-group').find('.tracking-validation-msg');

        if (!val || !pattern) {
            $msg.hide();
            $input.css('border-color', '');
            return;
        }

        var regex = new RegExp(pattern);
        if (regex.test(val)) {
            $msg.css('color', '#16a34a').text('صيغة صحيحة ✓').show();
            $input.css('border-color', '#16a34a');
        } else {
            $msg.css('color', '#ef4444').text('صيغة غير صحيحة — تأكد من المعرّف').show();
            $input.css('border-color', '#ef4444');
        }
    });

    /* ── Save settings ── */
    $('#saveTrackingBtn').on('click', function() {
        var $btn = $(this), $msg = $('#trackingSaveMsg');
        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');

        var hasError = false;
        $('.tracking-toggle:checked').each(function() {
            var key = $(this).data('key');
            var $input = $('.tracking-fields[data-key="' + key + '"] .tracking-id-input');
            var val = $.trim($input.val());
            var pattern = $input.data('pattern');
            if (val && pattern && !(new RegExp(pattern)).test(val)) {
                hasError = true;
                $input.focus();
                return false;
            }
        });

        if (hasError) {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            $msg.css('color', '#ef4444').text('يوجد معرّف بصيغة غير صحيحة');
            return;
        }

        var data = {
            action: 'yamamah_save_tracking_settings',
            nonce: yamamahData.nonce,
            custom_head_code: $('#track_custom_head_code').val()
        };

        <?php foreach ( $platforms as $key => $p ) : ?>
        data['<?php echo $key; ?>_enabled'] = $('.tracking-toggle[data-key="<?php echo $key; ?>"]').is(':checked') ? 'yes' : 'no';
        data['<?php echo $p['field']; ?>'] = $.trim($('#track_<?php echo $p['field']; ?>').val());
        <?php endforeach; ?>

        $.post(yamamahData.ajax_url, data, function(res) {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            if (res.success) {
                $msg.css('color', '#16a34a').text('تم الحفظ بنجاح');
                setTimeout(function(){ $msg.text(''); }, 3000);
            } else {
                $msg.css('color', '#ef4444').text(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            $msg.css('color', '#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Verify installation ── */
    $('#verifyTrackingBtn').on('click', function() {
        var $btn = $(this), $panel = $('#verifyPanel'), $results = $('#verifyResults');
        $btn.prop('disabled', true).text('جاري الفحص...');
        $results.html('<p class="text-sm text-muted">جاري فحص الصفحة الرئيسية...</p>');
        $panel.slideDown(200);

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_verify_tracking',
            nonce: yamamahData.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> تحقق من التثبيت');
            if (!res.success) {
                $results.html('<p style="color:#ef4444;">' + (res.data || 'خطأ في الفحص') + '</p>');
                return;
            }

            var html = '<div style="display:flex;flex-direction:column;gap:8px;">';
            var items = res.data;
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                var icon = '', badgeClass = '', badgeText = '';
                if (it.status === 'installed') {
                    icon = '✅'; badgeClass = 'badge-green'; badgeText = 'مثبّت';
                } else if (it.status === 'disabled') {
                    icon = '⚪'; badgeClass = 'badge-gray'; badgeText = 'معطّل';
                } else if (it.status === 'no_id') {
                    icon = '⚠️'; badgeClass = 'badge-yellow'; badgeText = 'مفعّل بدون معرّف';
                } else {
                    icon = '❌'; badgeClass = 'badge-red'; badgeText = 'مفعّل لكن لم يُعثر عليه';
                }
                html += '<div class="flex items-center justify-between" style="padding:8px 12px;border-radius:6px;background:var(--accent,#f3f4f6);">';
                html += '<div class="flex items-center gap-2"><span>' + icon + '</span><strong class="text-sm">' + it.label + '</strong>';
                if (it.id_val) html += ' <code style="font-size:0.75rem;color:var(--muted-fg);">' + it.id_val + '</code>';
                html += '</div>';
                html += '<span class="badge ' + badgeClass + '">' + badgeText + '</span>';
                html += '</div>';
            }
            html += '</div>';
            $results.html(html);
        }).fail(function() {
            $btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> تحقق من التثبيت');
            $results.html('<p style="color:#ef4444;">خطأ في الاتصال بالسيرفر</p>');
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
