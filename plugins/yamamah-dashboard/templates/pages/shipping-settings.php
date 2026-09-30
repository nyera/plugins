<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'shipping' ) ) {
    $page_content = yamamah_upgrade_banner( 'الشحن' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

// $shipper, $has_credentials, $debug, $middleware_url come from render_shipping_settings()

ob_start();
?>

<div class="page active" id="page-shipping-settings">
    <div class="page-header">
        <h1>إعدادات الشحن</h1>
        <p>البيانات الافتراضية للمرسل التي تُعبأ تلقائياً عند إنشاء شحنة.</p>
    </div>

    <div class="yamamah-settings-container">

        <!-- Connection Status Card -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title text-blue">حالة الاتصال بالمنصة</h2>

            <div class="connection-rows">
                <div class="connection-row">
                    <span class="connection-label">الحالة:</span>
                    <div class="connection-value">
                        <?php if ( $has_credentials ) : ?>
                            <span class="connection-status connected">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                متصل
                            </span>
                        <?php else : ?>
                            <span class="connection-status disconnected">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                غير متصل
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="connection-row">
                    <span class="connection-label">Store UUID:</span>
                    <div class="connection-value">
                        <code class="yamamah-mono-code" id="storeUuidText"><?php echo esc_html( $debug['store_uuid'] ?? '' ); ?></code>
                        <button type="button" class="yamamah-copy-btn" onclick="yamamahCopyText('storeUuidText')" title="نسخ">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </div>
                </div>

                <?php if ( $middleware_url ) : ?>
                <div class="connection-row">
                    <span class="connection-label">عنوان المنصة:</span>
                    <div class="connection-value">
                        <code class="yamamah-mono-code"><?php echo esc_html( $middleware_url ); ?></code>
                    </div>
                </div>
                <?php endif; ?>

                <div class="connection-row">
                    <span class="connection-label">بوابة الدفع (Tap):</span>
                    <div class="connection-value">
                        <?php $has_tap = ! empty( get_option( 'yamama_tap_merchant_id', '' ) ); ?>
                        <?php if ( $has_tap ) : ?>
                            <span class="connection-status connected">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                مفعّل
                            </span>
                        <?php else : ?>
                            <span class="connection-status disconnected">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                غير مفعّل — <a href="<?php echo admin_url( 'admin.php?page=yamamah-payment-settings' ); ?>">إعداد الدفع</a>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( ! empty( $debug['last_registration_error'] ) ) : ?>
                <div class="connection-row">
                    <span class="connection-label" style="color:var(--red);">آخر خطأ:</span>
                    <div class="connection-value" style="color:var(--red);">
                        <?php echo esc_html( $debug['last_registration_error'] ); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="connection-row" style="justify-content:center;padding-top:12px;">
                    <button type="button" class="btn btn-outline" id="reRegisterBtn" onclick="yamamahReRegisterShipping()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        إعادة التسجيل في المنصة
                    </button>
                    <span id="reRegisterStatus" class="text-sm" style="margin-right:8px;"></span>
                </div>
            </div>
        </div>

        <!-- Sender Info Card -->
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title text-blue">بيانات الشاحن (المرسل)</h2>
            <p class="text-xs text-muted mb-4">البيانات الافتراضية للمرسل التي تُعبأ تلقائياً عند إنشاء شحنة.</p>

            <div class="yamamah-form-fields" id="shippingSettingsForm">
                <div class="form-field">
                    <label>اسم المرسل</label>
                    <input type="text" name="shipper_name" value="<?php echo esc_attr( $shipper['name'] ?? '' ); ?>" />
                </div>

                <div class="form-field">
                    <label>جوال المرسل</label>
                    <input type="tel" name="shipper_phone" value="<?php echo esc_attr( $shipper['phone'] ?? '' ); ?>" dir="ltr" style="text-align:right;" />
                </div>

                <div class="form-field">
                    <label>الدولة</label>
                    <select name="shipper_country">
                        <option value="SA" <?php selected( $shipper['country'] ?? 'SA', 'SA' ); ?>>المملكة العربية السعودية</option>
                        <option value="AE" <?php selected( $shipper['country'] ?? '', 'AE' ); ?>>الإمارات العربية المتحدة</option>
                        <option value="KW" <?php selected( $shipper['country'] ?? '', 'KW' ); ?>>الكويت</option>
                        <option value="BH" <?php selected( $shipper['country'] ?? '', 'BH' ); ?>>البحرين</option>
                        <option value="OM" <?php selected( $shipper['country'] ?? '', 'OM' ); ?>>عمان</option>
                        <option value="QA" <?php selected( $shipper['country'] ?? '', 'QA' ); ?>>قطر</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>المدينة</label>
                    <input type="text" name="shipper_city" value="<?php echo esc_attr( $shipper['city'] ?? '' ); ?>" />
                </div>

                <div class="form-field">
                    <label>الحي</label>
                    <input type="text" name="shipper_district" value="<?php echo esc_attr( $shipper['district'] ?? '' ); ?>" />
                </div>

                <div class="form-field">
                    <label>العنوان 1</label>
                    <input type="text" name="shipper_address1" value="<?php echo esc_attr( $shipper['address1'] ?? '' ); ?>" />
                </div>

                <div class="form-field">
                    <label>العنوان 2</label>
                    <input type="text" name="shipper_address2" value="<?php echo esc_attr( $shipper['address2'] ?? '' ); ?>" />
                </div>

                <div class="form-field">
                    <label>العنوان الوطني</label>
                    <input type="text" name="shipper_national_address" value="<?php echo esc_attr( $shipper['national_address'] ?? '' ); ?>" />
                </div>
            </div>
        </div>

        <!-- Connection Detail Card -->
        <?php if ( ! empty( $debug['store_uuid'] ) ) : ?>
        <div class="yamamah-connection-card mb-5">
            <h2 class="card-section-title text-blue">حالة الاتصال</h2>
            <div class="connection-rows">
                <div class="connection-row bordered">
                    <span class="connection-label">الحالة</span>
                    <span class="text-sm <?php echo $has_credentials ? 'text-green' : 'text-red'; ?>">
                        <?php echo $has_credentials ? 'متصل' : 'غير متصل'; ?>
                    </span>
                </div>
                <div class="connection-row bordered">
                    <span class="connection-label">Store UUID</span>
                    <code class="yamamah-mono-code"><?php echo esc_html( $debug['store_uuid'] ); ?></code>
                </div>
                <div class="connection-row bordered">
                    <span class="connection-label">آخر تسجيل</span>
                    <span class="text-sm" dir="ltr"><?php echo esc_html( $debug['last_registration_at'] ?: 'N/A' ); ?></span>
                </div>
                <div class="connection-row">
                    <span class="connection-label">آخر خطأ</span>
                    <span class="text-sm text-muted"><?php echo esc_html( $debug['last_registration_error'] ?: 'لا يوجد' ); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Save Button -->
        <div class="mb-5">
            <button type="button" class="btn btn-blue" onclick="yamamahSaveShippingSettings()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                حفظ الإعدادات
            </button>
            <span id="shippingSaveStatus" class="text-sm" style="margin-right:10px;"></span>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
