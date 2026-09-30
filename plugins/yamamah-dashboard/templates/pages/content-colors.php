<?php
/**
 * صفحة ألوان الموقع
 * المتغير $colors يأتي من render_site_colors()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! isset( $color_fields ) || ! is_array( $color_fields ) || empty( $color_fields ) ) {
    $color_fields = array(
        'button_primary'    => 'لون الأزرار الرئيسي',
        'header_background' => 'خلفية الهيدر',
        'footer_background' => 'خلفية الفوتر',
    );
}

if ( ! isset( $colors ) || ! is_array( $colors ) ) {
    $colors = array();
}

$ydash_color_defaults = apply_filters( 'ydash_get_default_colors', array() );
if ( ! is_array( $ydash_color_defaults ) ) {
    $ydash_color_defaults = array();
}

foreach ( $color_fields as $ydash_color_key => $ydash_color_label ) {
    if ( ! isset( $colors[ $ydash_color_key ] ) || ! is_string( $colors[ $ydash_color_key ] ) || $colors[ $ydash_color_key ] === '' ) {
        $colors[ $ydash_color_key ] = isset( $ydash_color_defaults[ $ydash_color_key ] ) ? (string) $ydash_color_defaults[ $ydash_color_key ] : '#000000';
    }
    if ( ! preg_match( '/^#[0-9A-Fa-f]{6}$/', (string) $colors[ $ydash_color_key ] ) ) {
        $colors[ $ydash_color_key ] = isset( $ydash_color_defaults[ $ydash_color_key ] ) && preg_match( '/^#[0-9A-Fa-f]{6}$/', (string) $ydash_color_defaults[ $ydash_color_key ] )
            ? (string) $ydash_color_defaults[ $ydash_color_key ]
            : '#000000';
    }
}

ob_start();
?>

<div class="page active" id="page-site-colors">
    <div class="page-header">
        <h1>ألوان الموقع</h1>
        <p>تعديل الألوان الرئيسية المستخدمة في واجهة المتجر</p>
    </div>

    <form id="yamamahSiteColorsForm" method="post">
        <?php wp_nonce_field( 'yamamah_nonce', '_wpnonce' ); ?>

        <div class="card space-y-4 mb-4">
            <h3 class="text-green">الألوان الرئيسية</h3>
            <div class="grid grid-2">
                <?php foreach ( $color_fields as $key => $label ) : ?>
                    <div class="p-4 border rounded-xl flex items-center gap-4">
                        <input type="color" name="colors[<?php echo esc_attr( $key ); ?>]"
                               value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                               class="color-picker-input"
                               data-hex-display="hex-<?php echo esc_attr( $key ); ?>"
                               style="width:3.5rem;height:3.5rem;border-radius:0.75rem;border:2px solid var(--border);cursor:pointer;">
                        <div class="flex-1 text-right">
                            <label class="text-sm mb-2" style="display:block;"><?php echo esc_html( $label ); ?></label>
                            <input type="text" class="form-control text-center ltr"
                                   id="hex-<?php echo esc_attr( $key ); ?>"
                                   value="<?php echo esc_attr( $colors[ $key ] ); ?>"
                                   style="max-width:6rem;font-size:0.75rem;padding:0.375rem;" readonly>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-green">حفظ الألوان</button>
            <button type="button" class="btn btn-outline" id="restoreColorsBtn">استعادة الألوان الأصلية</button>
        </div>
        <div id="colorsSaveResult" class="alert alert-success mt-3" style="display:none;"></div>
    </form>
</div>

<script>
(function($){
    $('.color-picker-input').on('input change', function(){
        var target = $(this).data('hex-display');
        $('#' + target).val(this.value);
    });

    $('#yamamahSiteColorsForm').on('submit', function(e){
        e.preventDefault();
        var $btn = $(this).find('[type="submit"]');
        var $result = $('#colorsSaveResult');
        $btn.prop('disabled', true).text('جاري الحفظ...');

        var colors = {};
        $(this).find('.color-picker-input').each(function(){
            var name = $(this).attr('name').match(/\[(.+)\]/);
            if (name) colors[name[1]] = $(this).val();
        });

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_site_colors',
            _wpnonce: yamamahData.nonce,
            colors: colors
        }, function(res){
            $btn.prop('disabled', false).text('حفظ الألوان');
            if (res.success) {
                $result.removeClass('alert-error').addClass('alert-success').text('تم حفظ الألوان بنجاح').show();
            } else {
                $result.removeClass('alert-success').addClass('alert-error').text(res.data || 'حدث خطأ').show();
            }
            setTimeout(function(){ $result.fadeOut(); }, 3000);
        }).fail(function(){
            $btn.prop('disabled', false).text('حفظ الألوان');
            $result.removeClass('alert-success').addClass('alert-error').text('حدث خطأ في الاتصال').show();
            setTimeout(function(){ $result.fadeOut(); }, 3000);
        });
    });

    $('#restoreColorsBtn').on('click', function(){
        if (!confirm('هل تريد استعادة الألوان الأصلية الافتراضية؟')) return;
        var $result = $('#colorsSaveResult');
        var defaults = <?php
            $restore_defaults = apply_filters( 'ydash_get_default_colors', array() );
            if ( empty( $restore_defaults ) ) {
                $restore_defaults = $colors;
            }
            echo wp_json_encode( $restore_defaults );
        ?>;

        $.each(defaults, function(key, val){
            $('input[name="colors[' + key + ']"]').val(val);
            $('#hex-' + key).val(val);
        });

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_site_colors',
            _wpnonce: yamamahData.nonce,
            colors: defaults
        }, function(res){
            if (res.success) {
                $result.removeClass('alert-error').addClass('alert-success').text('تم استعادة الألوان الأصلية').show();
            }
            setTimeout(function(){ $result.fadeOut(); }, 3000);
        });
    });
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
