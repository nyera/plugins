<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();
?>
<div class="page active" id="page-settings-store">
    <div class="page-header">
        <h1>إعدادات المتجر</h1>
        <p>الموقع الجغرافي وعنوان المتجر</p>
    </div>

    <form id="yamamahSettingsForm" method="post">
        <?php wp_nonce_field( 'yamamah_settings', 'yamamah_settings_nonce' ); ?>
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
        <div class="mt-4">
            <button type="submit" class="btn btn-green">حفظ الإعدادات</button>
            <div id="settingsSaveResult" class="alert alert-success mt-3" style="display:none;">تم الحفظ بنجاح</div>
        </div>
    </form>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
