<?php
/**
 * صفحة الدعم الفني
 */
if ( ! defined( 'ABSPATH' ) ) exit;

ob_start();
?>

<div class="page active" id="page-support">
    <div class="page-header">
        <h1>الدعم الفني</h1>
        <p>تواصل مع فريق اليمامة للحلول التقنية للحصول على المساعدة</p>
    </div>

    <!-- Contact Cards -->
    <div class="grid grid-4 mb-5">
        <a href="tel:+966550001234" class="contact-card">
            <div class="contact-icon" style="background:rgba(45,155,131,0.1);color:#2D9B83;">📞</div>
            <p class="contact-label">اتصل بنا</p>
            <p class="contact-value ltr">+966 55 000 1234</p>
        </a>
        <a href="https://wa.me/966550001234" class="contact-card" target="_blank">
            <div class="contact-icon" style="background:rgba(34,197,94,0.1);color:#22c55e;">💬</div>
            <p class="contact-label">واتساب</p>
            <p class="contact-value ltr">+966 55 000 1234</p>
        </a>
        <a href="mailto:support@yaamama.com" class="contact-card">
            <div class="contact-icon" style="background:rgba(37,99,235,0.1);color:#2563eb;">📧</div>
            <p class="contact-label">البريد الإلكتروني</p>
            <p class="contact-value ltr">support@yaamama.com</p>
        </a>
        <div class="contact-card">
            <div class="contact-icon" style="background:rgba(108,63,160,0.1);color:#6C3FA0;">🕐</div>
            <p class="contact-label">أوقات العمل</p>
            <p class="contact-value">الأحد – الخميس</p>
            <p class="contact-value">9:00 ص – 5:00 م</p>
        </div>
    </div>

    <!-- Support Form -->
    <div class="card space-y-4 mb-5">
        <div class="flex items-center gap-2"><span>🎧</span><h3 class="text-green">طلب دعم فني</h3></div>

        <div id="supportSuccess" class="alert alert-success" style="display:none;">
            ✅ <div>
                <p class="text-sm text-green">تم إرسال طلبك بنجاح!</p>
                <p class="text-xs text-muted">سيتواصل معك فريق الدعم خلال ساعات العمل في أقرب وقت.</p>
            </div>
        </div>

        <form id="yamamahSupportForm">
            <?php wp_nonce_field( 'yamamah_support', 'yamamah_support_nonce' ); ?>
            <div class="form-row">
                <div class="form-group">
                    <label>نوع الطلب <span class="required">*</span></label>
                    <select class="form-control" name="support_type" required>
                        <option value="">اختر نوع الطلب</option>
                        <option>مشكلة تقنية</option>
                        <option>استفسار مالي / فواتير</option>
                        <option>ترقية الباقة</option>
                        <option>مشكلة في الدومين</option>
                        <option>طلب تعديل تصميم</option>
                        <option>مشكلة في الشحن</option>
                        <option>مشكلة في الدفع</option>
                        <option>اقتراح أو ملاحظة</option>
                        <option>أخرى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>الأولوية</label>
                    <select class="form-control" name="support_priority">
                        <option>عادية</option>
                        <option>عالية</option>
                        <option>مستعجلة</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>الموضوع</label>
                <input type="text" class="form-control" name="support_subject" placeholder="اكتب موضوع الطلب...">
            </div>
            <div class="form-group">
                <label>الرسالة <span class="required">*</span></label>
                <textarea class="form-control" rows="5" name="support_message" placeholder="اكتب تفاصيل طلبك هنا..." required></textarea>
            </div>
            <button type="submit" class="btn btn-green">إرسال الطلب</button>
        </form>
    </div>

    <!-- System Info -->
    <div class="card">
        <h3 class="text-green mb-3">معلومات النظام</h3>
        <div class="table-wrapper">
            <table>
                <tbody>
                    <tr><td class="text-sm">إصدار WordPress</td><td class="text-sm ltr"><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
                    <tr><td class="text-sm">إصدار WooCommerce</td><td class="text-sm ltr"><?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : '-'; ?></td></tr>
                    <tr><td class="text-sm">إصدار PHP</td><td class="text-sm ltr"><?php echo esc_html( phpversion() ); ?></td></tr>
                    <tr><td class="text-sm">إصدار الإضافة</td><td class="text-sm ltr"><?php echo esc_html( YAMAMAH_VERSION ); ?></td></tr>
                    <tr><td class="text-sm">القالب النشط</td><td class="text-sm"><?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?></td></tr>
                    <tr><td class="text-sm">رابط الموقع</td><td class="text-sm ltr"><?php echo esc_html( home_url() ); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
