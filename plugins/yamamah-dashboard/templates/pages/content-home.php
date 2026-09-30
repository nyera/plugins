<?php
/**
 * صفحة تعديل محتوى الصفحة الرئيسية — عرض ديناميكي بناءً على manifest الثيم
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$content  = isset( $content ) && is_array( $content ) ? $content : array();
$manifest = apply_filters( 'ydash_theme_manifest', array() );

$hero_config      = isset( $manifest['hero_config'] ) ? $manifest['hero_config'] : null;
$section_headings = isset( $manifest['section_headings'] ) ? $manifest['section_headings'] : array();
$extra_fields     = isset( $manifest['extra_image_fields'] ) ? $manifest['extra_image_fields'] : array();
$banners_config   = isset( $manifest['banners_config'] ) ? $manifest['banners_config'] : array();

ob_start();
?>

<div class="page active" id="page-home-content">
    <div class="page-header">
        <h1>تعديل الصفحة الرئيسية</h1>
        <p>تعديل محتوى أقسام الصفحة الرئيسية<?php if ( ! empty( $manifest['theme_name'] ) ) echo ' — ' . esc_html( $manifest['theme_name'] ); ?></p>
    </div>

    <form id="yamamahHomeContentForm">

    <?php if ( $hero_config && ! empty( $hero_config['slides'] ) ) : ?>
        <?php
        $is_slider = ( $hero_config['type'] ?? 'single' ) === 'slider';
        $slide_count = count( $hero_config['slides'] );
        foreach ( $hero_config['slides'] as $idx => $slide ) :
            $skey   = $slide['key'];
            $fields = isset( $slide['fields'] ) ? $slide['fields'] : array( 'image' );
            $color  = isset( $slide['color'] ) ? $slide['color'] : '#10b981';

            $img_field_key = $skey . '_image';
            $img_id  = (int) ( $content[ $img_field_key ] ?? 0 );
            $img_url = '';
            if ( $img_id ) {
                $img_url = wp_get_attachment_image_url( $img_id, 'medium' );
            }
            if ( ! $img_url && ! empty( $slide['fallback_image'] ) ) {
                $img_url = apply_filters( 'ydash_get_theme_asset_url', '', $slide['fallback_image'] );
            }
        ?>
        <div class="card space-y-4 mb-4">
            <h3 style="color:<?php echo esc_attr( $color ); ?>;">
                <?php echo esc_html( $slide['label'] ?? ( $is_slider ? 'شريحة ' . ( $idx + 1 ) : 'الهيدر' ) ); ?>
                <?php if ( $is_slider ) : ?>
                    <span style="font-size:0.75rem;color:#9ca3af;font-weight:400;margin-right:0.5rem;">(<?php echo ( $idx + 1 ) . '/' . $slide_count; ?>)</span>
                <?php endif; ?>
            </h3>

            <?php if ( in_array( 'image', $fields, true ) ) : ?>
            <div class="form-field">
                <label>الصورة</label>
                <div class="yamamah-content-image-upload" data-field="<?php echo esc_attr( $img_field_key ); ?>">
                    <div class="yamamah-content-image-preview">
                        <?php if ( $img_url ) : ?>
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="max-width:200px;height:auto;border-radius:8px;<?php echo $img_id ? '' : 'opacity:0.6;'; ?>">
                            <?php if ( ! $img_id ) : ?><span class="text-muted text-xs" style="display:block;margin-top:4px;">صورة افتراضية</span><?php endif; ?>
                        <?php else : ?>
                            <span class="text-muted text-xs">لا توجد صورة</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="home_content[<?php echo esc_attr( $img_field_key ); ?>]" value="<?php echo esc_attr( $img_id ); ?>">
                    <button type="button" class="btn btn-outline btn-sm yamamah-content-upload-btn">رفع صورة</button>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $text_field_map = array(
                'title'    => array( 'label' => 'العنوان',    'type' => 'text' ),
                'text'     => array( 'label' => 'النص',       'type' => 'textarea' ),
                'desc'     => array( 'label' => 'الوصف',      'type' => 'textarea' ),
                'btn_text' => array( 'label' => 'نص الزر',    'type' => 'text' ),
            );
            foreach ( $text_field_map as $fkey => $fconfig ) :
                if ( ! in_array( $fkey, $fields, true ) ) continue;
                $full_key = $skey . '_' . $fkey;
                $value    = $content[ $full_key ] ?? '';
            ?>
            <div class="form-field">
                <label><?php echo esc_html( $fconfig['label'] ); ?></label>
                <?php if ( $fconfig['type'] === 'textarea' ) : ?>
                    <textarea name="home_content[<?php echo esc_attr( $full_key ); ?>]" class="form-control" rows="2"><?php echo esc_textarea( $value ); ?></textarea>
                <?php else : ?>
                    <input type="text" name="home_content[<?php echo esc_attr( $full_key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="form-control">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

    <?php else : ?>
        <?php /* Legacy: no hero_config — render old hardcoded fields */ ?>
        <div class="card space-y-4 mb-4">
            <h3 class="text-green">الهيدر</h3>
            <?php _ydash_home_image_field( 'header_image', $content, 'assets/header.png' ); ?>
            <div class="form-field">
                <label>عنوان الهيدر</label>
                <input type="text" name="home_content[header_title]" value="<?php echo esc_attr( $content['header_title'] ?? '' ); ?>" class="form-control">
            </div>
            <div class="form-field">
                <label>نص الهيدر</label>
                <textarea name="home_content[header_text]" class="form-control" rows="3"><?php echo esc_textarea( $content['header_text'] ?? '' ); ?></textarea>
            </div>
            <div class="form-field">
                <label>نص زر الهيدر</label>
                <input type="text" name="home_content[header_btn_text]" value="<?php echo esc_attr( $content['header_btn_text'] ?? '' ); ?>" class="form-control">
            </div>
        </div>
    <?php endif; ?>

    <?php do_action( 'ydash_home_content_after_hero', $content, $manifest ); ?>

    <?php /* Extra image/text fields from manifest (e.g. dark-theme categories, banners) */ ?>
    <?php if ( ! empty( $extra_fields ) ) : ?>
        <div class="card space-y-4 mb-4">
            <h3 class="text-green">عناصر إضافية</h3>
            <?php foreach ( $extra_fields as $ef ) :
                $ef_key  = $ef['key'] ?? '';
                $ef_type = $ef['type'] ?? ( strpos( $ef_key, 'image' ) !== false ? 'image' : 'text' );
                if ( ! $ef_key ) continue;
            ?>
                <?php if ( $ef_type === 'image' ) : ?>
                    <?php _ydash_home_image_field( $ef_key, $content, $ef['fallback'] ?? '' ); ?>
                <?php else : ?>
                    <div class="form-field">
                        <label><?php echo esc_html( $ef['label'] ?? $ef_key ); ?></label>
                        <input type="text" name="home_content[<?php echo esc_attr( $ef_key ); ?>]" value="<?php echo esc_attr( $content[ $ef_key ] ?? '' ); ?>" class="form-control">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php /* Section headings from manifest */ ?>
    <?php if ( ! empty( $section_headings ) ) : ?>
        <div class="card space-y-4 mb-4">
            <h3 class="text-green">عناوين الأقسام</h3>
            <?php foreach ( $section_headings as $hkey => $hlabel ) : ?>
            <div class="form-field">
                <label><?php echo esc_html( $hlabel ); ?></label>
                <input type="text" name="home_content[<?php echo esc_attr( $hkey ); ?>]" value="<?php echo esc_attr( $content[ $hkey ] ?? '' ); ?>" class="form-control">
            </div>
            <?php endforeach; ?>
        </div>
        <?php do_action( 'ydash_home_content_after_section_headings', $content, $manifest ); ?>
    <?php endif; ?>

    <?php /* Banners from manifest */ ?>
    <?php if ( ! empty( $banners_config ) ) : ?>
        <?php foreach ( $banners_config as $banner ) :
            $bkey   = $banner['key'];
            $bcolor = isset( $banner['color'] ) ? $banner['color'] : '#10b981';
            $bfields = isset( $banner['fields'] ) ? $banner['fields'] : array( 'image' );
        ?>
        <div class="card space-y-4 mb-4">
            <h3 style="color:<?php echo esc_attr( $bcolor ); ?>;"><?php echo esc_html( $banner['label'] ?? $bkey ); ?></h3>

            <?php if ( in_array( 'image', $bfields, true ) ) :
                $b_img_key = $bkey . '_image';
                $b_img_id  = (int) ( $content[ $b_img_key ] ?? 0 );
                $b_img_url = $b_img_id ? wp_get_attachment_image_url( $b_img_id, 'medium' ) : '';
                if ( ! $b_img_url && ! empty( $banner['fallback_image'] ) ) {
                    $b_img_url = apply_filters( 'ydash_get_theme_asset_url', '', $banner['fallback_image'] );
                }
                if ( ! $b_img_url && ! empty( $banner['fallback_url'] ) ) {
                    $b_img_url = $banner['fallback_url'];
                }
            ?>
            <div class="form-field">
                <label>الصورة</label>
                <div class="yamamah-content-image-upload" data-field="<?php echo esc_attr( $b_img_key ); ?>">
                    <div class="yamamah-content-image-preview">
                        <?php if ( $b_img_url ) : ?>
                            <img src="<?php echo esc_url( $b_img_url ); ?>" alt="" style="max-width:200px;height:auto;border-radius:8px;<?php echo $b_img_id ? '' : 'opacity:0.6;'; ?>">
                            <?php if ( ! $b_img_id ) : ?><span class="text-muted text-xs" style="display:block;margin-top:4px;">صورة افتراضية</span><?php endif; ?>
                        <?php else : ?>
                            <span class="text-muted text-xs">لا توجد صورة</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="home_content[<?php echo esc_attr( $b_img_key ); ?>]" value="<?php echo esc_attr( $b_img_id ); ?>">
                    <button type="button" class="btn btn-outline btn-sm yamamah-content-upload-btn">رفع صورة</button>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $b_text_map = array( 'title' => 'العنوان', 'desc' => 'الوصف', 'btn_text' => 'نص الزر' );
            foreach ( $b_text_map as $fk => $flabel ) :
                if ( ! in_array( $fk, $bfields, true ) ) continue;
                $full_k = $bkey . '_' . $fk;
            ?>
            <div class="form-field">
                <label><?php echo esc_html( $flabel ); ?></label>
                <?php if ( $fk === 'desc' ) : ?>
                    <textarea name="home_content[<?php echo esc_attr( $full_k ); ?>]" class="form-control" rows="2"><?php echo esc_textarea( $content[ $full_k ] ?? '' ); ?></textarea>
                <?php else : ?>
                    <input type="text" name="home_content[<?php echo esc_attr( $full_k ); ?>]" value="<?php echo esc_attr( $content[ $full_k ] ?? '' ); ?>" class="form-control">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php /* Extra text fields (e.g. stats) */ ?>
            <?php if ( ! empty( $banner['extra_fields'] ) ) : ?>
                <?php foreach ( $banner['extra_fields'] as $efk => $eflabel ) : ?>
                <div class="form-field">
                    <label><?php echo esc_html( $eflabel ); ?></label>
                    <input type="text" name="home_content[<?php echo esc_attr( $efk ); ?>]" value="<?php echo esc_attr( $content[ $efk ] ?? '' ); ?>" class="form-control">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php /* Sub-cards (e.g. Love Skin duo cards) */ ?>
            <?php if ( ! empty( $banner['cards'] ) ) : ?>
                <?php foreach ( $banner['cards'] as $card ) :
                    $ckey = $card['key'];
                    $cfields = $card['fields'] ?? array( 'image', 'title' );
                ?>
                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-top:0.5rem;">
                    <strong style="font-size:0.8125rem;"><?php echo esc_html( $card['label'] ?? $ckey ); ?></strong>
                    <?php if ( in_array( 'image', $cfields, true ) ) :
                        $c_img_key = $ckey . '_image';
                        $c_img_id  = (int) ( $content[ $c_img_key ] ?? 0 );
                        $c_img_url = $c_img_id ? wp_get_attachment_image_url( $c_img_id, 'medium' ) : '';
                        if ( ! $c_img_url && ! empty( $card['fallback_image'] ) ) {
                            $c_img_url = apply_filters( 'ydash_get_theme_asset_url', '', $card['fallback_image'] );
                        }
                        if ( ! $c_img_url && ! empty( $card['fallback_url'] ) ) {
                            $c_img_url = $card['fallback_url'];
                        }
                    ?>
                    <div class="form-field" style="margin-top:0.5rem;">
                        <div class="yamamah-content-image-upload" data-field="<?php echo esc_attr( $c_img_key ); ?>">
                            <div class="yamamah-content-image-preview">
                                <?php if ( $c_img_url ) : ?>
                                    <img src="<?php echo esc_url( $c_img_url ); ?>" alt="" style="max-width:120px;height:auto;border-radius:6px;">
                                <?php else : ?>
                                    <span class="text-muted text-xs">لا توجد صورة</span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="home_content[<?php echo esc_attr( $c_img_key ); ?>]" value="<?php echo esc_attr( $c_img_id ); ?>">
                            <button type="button" class="btn btn-outline btn-sm yamamah-content-upload-btn">رفع صورة</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ( in_array( 'title', $cfields, true ) ) :
                        $c_title_key = $ckey . '_title';
                    ?>
                    <div class="form-field" style="margin-top:0.5rem;">
                        <label>العنوان</label>
                        <input type="text" name="home_content[<?php echo esc_attr( $c_title_key ); ?>]" value="<?php echo esc_attr( $content[ $c_title_key ] ?? '' ); ?>" class="form-control">
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

        <?php do_action( 'ydash_home_content_after_banners', $content, $manifest ); ?>

        <?php
        /**
         * حقول إضافية خاصة بالثيم (بعد البنرات؛ مثل villaGo: صفحة من نحن المستقلة).
         */
        do_action( 'ydash_home_content_extra_sections', $content, $manifest );
        ?>

        <button type="submit" class="btn btn-green">حفظ التغييرات</button>
        <button type="button" class="btn btn-outline" onclick="if(confirm('هل تريد استعادة المحتوى الأصلي؟')) yamamahRestoreHomeContent()">استعادة المحتوى الأصلي</button>
        <div id="homeContentSaveResult" class="alert alert-success mt-3" style="display:none;">
            ✅ تم الحفظ بنجاح
        </div>
    </form>
</div>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';

/**
 * Reusable image upload field with theme asset fallback
 */
function _ydash_home_image_field( $key, $content, $fallback_asset_path = '' ) {
    $img_id = (int) ( $content[ $key ] ?? 0 );
    $img_url = '';
    if ( $img_id ) {
        $img_url = wp_get_attachment_image_url( $img_id, 'medium' );
    }
    if ( ! $img_url && $fallback_asset_path ) {
        $img_url = apply_filters( 'ydash_get_theme_asset_url', '', $fallback_asset_path );
    }
    ?>
    <div class="form-field">
        <label><?php
            $labels = array(
                'header_image' => 'صورة الهيدر',
            );
            echo esc_html( $labels[ $key ] ?? $key );
        ?></label>
        <div class="yamamah-content-image-upload" data-field="<?php echo esc_attr( $key ); ?>">
            <div class="yamamah-content-image-preview">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="max-width:150px;height:auto;<?php echo $img_id ? '' : 'opacity:0.6;'; ?>">
                    <?php if ( ! $img_id ) : ?><span class="text-muted text-xs" style="display:block;margin-top:4px;">صورة افتراضية</span><?php endif; ?>
                <?php else : ?>
                    <span class="text-muted text-xs">لا توجد صورة</span>
                <?php endif; ?>
            </div>
            <input type="hidden" name="home_content[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $img_id ); ?>">
            <button type="button" class="btn btn-outline btn-sm yamamah-content-upload-btn">رفع صورة</button>
        </div>
    </div>
    <?php
}
?>
