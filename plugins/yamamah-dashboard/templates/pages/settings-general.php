<?php
if ( ! defined( 'ABSPATH' ) ) exit;
ob_start();
?>
<div class="page active" id="page-settings-general">
    <div class="page-header">
        <h1>الإعدادات العامة</h1>
        <p>إعدادات المتجر الأساسية</p>
    </div>

    <form id="yamamahSettingsForm" method="post">
        <?php wp_nonce_field( 'yamamah_settings', 'yamamah_settings_nonce' ); ?>
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
        <div class="mt-4">
            <button type="submit" class="btn btn-green">حفظ الإعدادات</button>
            <div id="settingsSaveResult" class="alert alert-success mt-3" style="display:none;">تم الحفظ بنجاح</div>
        </div>
    </form>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
