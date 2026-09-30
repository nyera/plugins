<?php
/**
 * صفحة إعدادات المظهر — الشعارات (ديناميكي بناءً على manifest الثيم)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$manifest          = apply_filters( 'ydash_theme_manifest', array() );
$appearance_fields = isset( $manifest['appearance_fields'] ) ? $manifest['appearance_fields'] : array( 'header_logo', 'footer_logo', 'favicon' );

$show_header = in_array( 'header_logo', $appearance_fields, true );
$show_footer = in_array( 'footer_logo', $appearance_fields, true );
$show_favicon = in_array( 'favicon', $appearance_fields, true );

$header_logo_id = intval( get_option( 'yamamah_header_logo', 0 ) );
$footer_logo_id = intval( get_option( 'yamamah_footer_logo', 0 ) );
$favicon_id     = intval( get_option( 'yamamah_favicon', 0 ) );

$theme_logo_url = apply_filters( 'ydash_get_logo', '' );
$theme_icon_url = apply_filters( 'ydash_get_favicon', '' );

$header_logo_url = $header_logo_id ? wp_get_attachment_url( $header_logo_id ) : $theme_logo_url;
$footer_logo_url = $footer_logo_id ? wp_get_attachment_url( $footer_logo_id ) : $theme_logo_url;
$favicon_url     = $favicon_id     ? wp_get_attachment_url( $favicon_id )     : $theme_icon_url;

$visible_count = (int) $show_header + (int) $show_footer + (int) $show_favicon;
$grid_class    = $visible_count >= 3 ? 'grid grid-3' : ( $visible_count === 2 ? 'grid grid-2' : '' );

ob_start();
?>

<div class="page active" id="page-appearance">
    <div class="page-header">
        <h1>المظهر العام</h1>
        <p>إدارة شعارات المتجر</p>
    </div>

    <div class="card space-y-4 mb-4">
        <h3 class="text-green">الشعارات</h3>
        <div class="<?php echo esc_attr( $grid_class ); ?>">

            <?php if ( $show_header ) : ?>
            <div class="yamamah-logo-field">
                <label class="text-sm mb-2" style="display:block;">شعار الهيدر</label>
                <div class="yamamah-logo-preview" id="headerLogoPreview">
                    <?php if ( $header_logo_url ) : ?>
                        <img src="<?php echo esc_url( $header_logo_url ); ?>" alt="شعار الهيدر" style="max-width:100%;max-height:100%;object-fit:contain;">
                    <?php else : ?>
                        <span class="text-xs text-muted">لا يوجد شعار</span>
                    <?php endif; ?>
                </div>
                <?php if ( ! $header_logo_id && $header_logo_url ) : ?>
                    <span class="text-xs text-muted" style="display:block;margin-top:4px;">الشعار الافتراضي من القالب</span>
                <?php endif; ?>
                <input type="hidden" id="headerLogoId" value="<?php echo esc_attr( $header_logo_id ); ?>">
                <div class="flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline btn-sm" onclick="yamamahUploadLogo('header')"><?php echo $header_logo_id ? 'تغيير' : 'رفع صورة'; ?></button>
                    <?php if ( $header_logo_id ) : ?>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444;" onclick="yamamahRemoveLogo('header')">إزالة</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $show_footer ) : ?>
            <div class="yamamah-logo-field">
                <label class="text-sm mb-2" style="display:block;">شعار الفوتر</label>
                <div class="yamamah-logo-preview" id="footerLogoPreview">
                    <?php if ( $footer_logo_url ) : ?>
                        <img src="<?php echo esc_url( $footer_logo_url ); ?>" alt="شعار الفوتر" style="max-width:100%;max-height:100%;object-fit:contain;">
                    <?php else : ?>
                        <span class="text-xs text-muted">لا يوجد شعار</span>
                    <?php endif; ?>
                </div>
                <?php if ( ! $footer_logo_id && $footer_logo_url ) : ?>
                    <span class="text-xs text-muted" style="display:block;margin-top:4px;">الشعار الافتراضي من القالب</span>
                <?php endif; ?>
                <input type="hidden" id="footerLogoId" value="<?php echo esc_attr( $footer_logo_id ); ?>">
                <div class="flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline btn-sm" onclick="yamamahUploadLogo('footer')"><?php echo $footer_logo_id ? 'تغيير' : 'رفع صورة'; ?></button>
                    <?php if ( $footer_logo_id ) : ?>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444;" onclick="yamamahRemoveLogo('footer')">إزالة</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $show_favicon ) : ?>
            <div class="yamamah-logo-field">
                <label class="text-sm mb-2" style="display:block;">الأيقونة (Favicon)</label>
                <div class="yamamah-logo-preview" id="faviconPreview">
                    <?php if ( $favicon_url ) : ?>
                        <img src="<?php echo esc_url( $favicon_url ); ?>" alt="أيقونة الموقع" style="max-width:100%;max-height:100%;object-fit:contain;">
                    <?php else : ?>
                        <span class="text-xs text-muted">لا توجد أيقونة</span>
                    <?php endif; ?>
                </div>
                <?php if ( ! $favicon_id && $favicon_url ) : ?>
                    <span class="text-xs text-muted" style="display:block;margin-top:4px;">الأيقونة الافتراضية من القالب</span>
                <?php endif; ?>
                <input type="hidden" id="faviconId" value="<?php echo esc_attr( $favicon_id ); ?>">
                <div class="flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline btn-sm" onclick="yamamahUploadLogo('favicon')"><?php echo $favicon_id ? 'تغيير' : 'رفع صورة'; ?></button>
                    <?php if ( $favicon_id ) : ?>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#ef4444;" onclick="yamamahRemoveLogo('favicon')">إزالة</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
