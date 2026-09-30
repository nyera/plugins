<?php
/**
 * صفحة إعدادات المتجر
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// $settings يأتي من render_store_settings()

$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

ob_start();
?>

<div class="page active" id="page-store-settings">
    <div class="page-header">
        <h1>إعدادات المتجر</h1>
        <p>إدارة الإعدادات العامة والتفصيلية للمتجر</p>
    </div>

    <!-- Tabs -->
    <div class="tabs mb-5">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-store-settings&tab=general' ) ); ?>"
           class="tab-btn <?php echo $tab === 'general' ? 'active' : ''; ?>">عام</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-store-settings&tab=store' ) ); ?>"
           class="tab-btn <?php echo $tab === 'store' ? 'active' : ''; ?>">المتجر</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-store-settings&tab=currency' ) ); ?>"
           class="tab-btn <?php echo $tab === 'currency' ? 'active' : ''; ?>">العملة</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-store-settings&tab=tax' ) ); ?>"
           class="tab-btn <?php echo $tab === 'tax' ? 'active' : ''; ?>">الضرائب</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-store-settings&tab=email' ) ); ?>"
           class="tab-btn <?php echo $tab === 'email' ? 'active' : ''; ?>">البريد الإلكتروني</a>
    </div>

    <form id="yamamahSettingsForm" method="post">
        <?php wp_nonce_field( 'yamamah_settings', 'yamamah_settings_nonce' ); ?>

        <?php if ( $tab === 'general' ) : ?>
        <div class="card space-y-4">
            <div class="form-group">
                <label>اسم المتجر</label>
                <input type="text" class="form-control" name="yamamah_store_name"
                       value="<?php echo esc_attr( $settings['store_name'] ); ?>">
            </div>
            <div class="form-group">
                <label>وصف المتجر</label>
                <textarea class="form-control" rows="3" name="blogdescription"><?php echo esc_textarea( get_bloginfo( 'description' ) ); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" class="form-control ltr" name="yamamah_store_email"
                           value="<?php echo esc_attr( $settings['store_email'] ); ?>">
                </div>
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="tel" class="form-control ltr" name="yamamah_store_phone"
                           value="<?php echo esc_attr( $settings['store_phone'] ); ?>">
                </div>
            </div>
        </div>

        <?php elseif ( $tab === 'store' ) : ?>
        <div class="card space-y-4">
            <div class="form-row">
                <div class="form-group">
                    <label>الدولة</label>
                    <select class="form-control" name="woocommerce_default_country">
                        <option value="SA" <?php selected( $settings['country'], 'SA' ); ?>>المملكة العربية السعودية</option>
                        <option value="AE" <?php selected( $settings['country'], 'AE' ); ?>>الإمارات</option>
                        <option value="KW" <?php selected( $settings['country'], 'KW' ); ?>>الكويت</option>
                        <option value="BH" <?php selected( $settings['country'], 'BH' ); ?>>البحرين</option>
                        <option value="QA" <?php selected( $settings['country'], 'QA' ); ?>>قطر</option>
                        <option value="OM" <?php selected( $settings['country'], 'OM' ); ?>>عمان</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>المدينة</label>
                    <input type="text" class="form-control" name="woocommerce_store_city"
                           value="<?php echo esc_attr( $settings['city'] ); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>العنوان</label>
                <input type="text" class="form-control" name="woocommerce_store_address"
                       value="<?php echo esc_attr( $settings['address'] ); ?>">
            </div>
        </div>

        <?php elseif ( $tab === 'currency' ) : ?>
        <div class="card space-y-4">
            <div class="form-group">
                <label>العملة</label>
                <select class="form-control" name="yamamah_store_currency">
                    <option value="SAR" <?php selected( $settings['currency'], 'SAR' ); ?>>ريال سعودي (SAR)</option>
                    <option value="AED" <?php selected( $settings['currency'], 'AED' ); ?>>درهم إماراتي (AED)</option>
                    <option value="KWD" <?php selected( $settings['currency'], 'KWD' ); ?>>دينار كويتي (KWD)</option>
                    <option value="BHD" <?php selected( $settings['currency'], 'BHD' ); ?>>دينار بحريني (BHD)</option>
                    <option value="QAR" <?php selected( $settings['currency'], 'QAR' ); ?>>ريال قطري (QAR)</option>
                    <option value="OMR" <?php selected( $settings['currency'], 'OMR' ); ?>>ريال عماني (OMR)</option>
                    <option value="USD" <?php selected( $settings['currency'], 'USD' ); ?>>دولار أمريكي (USD)</option>
                    <option value="EUR" <?php selected( $settings['currency'], 'EUR' ); ?>>يورو (EUR)</option>
                    <option value="GBP" <?php selected( $settings['currency'], 'GBP' ); ?>>جنيه إسترليني (GBP)</option>
                    <option value="EGP" <?php selected( $settings['currency'], 'EGP' ); ?>>جنيه مصري (EGP)</option>
                    <option value="JOD" <?php selected( $settings['currency'], 'JOD' ); ?>>دينار أردني (JOD)</option>
                </select>
            </div>
            <div class="form-group">
                <label>موضع رمز العملة</label>
                <select class="form-control" name="woocommerce_currency_pos">
                    <?php $currency_pos = get_option( 'woocommerce_currency_pos', 'right_space' ); ?>
                    <option value="right" <?php selected( $currency_pos, 'right' ); ?>>يمين (10ر.س)</option>
                    <option value="right_space" <?php selected( $currency_pos, 'right_space' ); ?>>يمين بمسافة (10 ر.س)</option>
                    <option value="left" <?php selected( $currency_pos, 'left' ); ?>>يسار (ر.س10)</option>
                    <option value="left_space" <?php selected( $currency_pos, 'left_space' ); ?>>يسار بمسافة (ر.س 10)</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>فاصل الآلاف</label>
                    <input type="text" class="form-control ltr" name="woocommerce_price_thousand_sep"
                           value="<?php echo esc_attr( get_option( 'woocommerce_price_thousand_sep', ',' ) ); ?>">
                </div>
                <div class="form-group">
                    <label>الفاصلة العشرية</label>
                    <input type="text" class="form-control ltr" name="woocommerce_price_decimal_sep"
                           value="<?php echo esc_attr( get_option( 'woocommerce_price_decimal_sep', '.' ) ); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>عدد المنازل العشرية</label>
                <input type="number" class="form-control ltr" name="woocommerce_price_num_decimals" min="0" max="4"
                       value="<?php echo esc_attr( get_option( 'woocommerce_price_num_decimals', 2 ) ); ?>">
            </div>
        </div>

        <?php elseif ( $tab === 'tax' ) : ?>
        <div class="card space-y-4">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm">تفعيل الضريبة</span>
                <label class="toggle-label">
                    <input type="hidden" name="yamamah_tax_enabled" value="no">
                    <input type="checkbox" name="yamamah_tax_enabled" value="yes"
                        <?php checked( $settings['tax_enabled'], 'yes' ); ?>>
                    <div class="toggle <?php echo $settings['tax_enabled'] === 'yes' ? 'on' : ''; ?>"></div>
                </label>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>نسبة الضريبة %</label>
                    <input type="number" class="form-control" name="yamamah_tax_rate"
                           value="<?php echo esc_attr( $settings['tax_rate'] ); ?>">
                </div>
                <div class="form-group">
                    <label>اسم الضريبة</label>
                    <input type="text" class="form-control" value="ضريبة القيمة المضافة" readonly>
                </div>
            </div>
        </div>

        <?php elseif ( $tab === 'email' ) : ?>
        <div class="card space-y-4">
            <p class="text-sm text-muted mb-3">يمكنك إدارة إعدادات البريد الإلكتروني التفصيلية من WooCommerce:</p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>" class="btn btn-green">
                إعدادات البريد في WooCommerce
            </a>
        </div>
        <?php endif; ?>

        <?php if ( $tab !== 'email' ) : ?>
        <div class="mt-4">
            <button type="submit" class="btn btn-green" id="saveSettingsBtn">حفظ الإعدادات</button>
            <div id="settingsSaveResult" class="alert alert-success mt-3" style="display:none;">
                ✅ تم الحفظ بنجاح
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
