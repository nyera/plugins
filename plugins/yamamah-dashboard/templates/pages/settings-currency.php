<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();
?>
<div class="page active" id="page-settings-currency">
    <div class="page-header">
        <h1>إعدادات العملة</h1>
        <p>العملة وتنسيق الأسعار</p>
    </div>

    <form id="yamamahSettingsForm" method="post">
        <?php wp_nonce_field( 'yamamah_settings', 'yamamah_settings_nonce' ); ?>
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
                    <option value="right" <?php selected( $settings['currency_pos'], 'right' ); ?>>يمين (10ر.س)</option>
                    <option value="right_space" <?php selected( $settings['currency_pos'], 'right_space' ); ?>>يمين بمسافة (10 ر.س)</option>
                    <option value="left" <?php selected( $settings['currency_pos'], 'left' ); ?>>يسار (ر.س10)</option>
                    <option value="left_space" <?php selected( $settings['currency_pos'], 'left_space' ); ?>>يسار بمسافة (ر.س 10)</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>فاصل الآلاف</label>
                    <input type="text" class="form-control ltr" name="woocommerce_price_thousand_sep"
                           value="<?php echo esc_attr( $settings['thousand_sep'] ); ?>">
                </div>
                <div class="form-group">
                    <label>الفاصلة العشرية</label>
                    <input type="text" class="form-control ltr" name="woocommerce_price_decimal_sep"
                           value="<?php echo esc_attr( $settings['decimal_sep'] ); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>عدد المنازل العشرية</label>
                <input type="number" class="form-control ltr" name="woocommerce_price_num_decimals" min="0" max="4"
                       value="<?php echo esc_attr( $settings['num_decimals'] ); ?>">
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="btn btn-green">حفظ الإعدادات</button>
            <div id="settingsSaveResult" class="alert alert-success mt-3" style="display:none;">تم الحفظ بنجاح</div>
        </div>
    </form>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
