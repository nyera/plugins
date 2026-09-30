<?php
/**
 * صفحة السياسات — ديناميكية بالكامل عبر فلتر ydash_policy_pages
 * المتغير $policy_pages يأتي من render_policies() عبر apply_filters('ydash_policy_pages', array())
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$editing_slug = isset( $_GET['edit'] ) ? sanitize_text_field( $_GET['edit'] ) : '';

ob_start();
?>

<div class="page active" id="page-policies">

<?php if ( empty( $policy_pages ) ) : ?>
    <div class="page-header">
        <h1>السياسات</h1>
        <p class="text-muted">لا توجد صفحات سياسات معرّفة في الثيم المفعّل.</p>
    </div>

<?php elseif ( $editing_slug && isset( $policy_pages[ $editing_slug ] ) ) :
    $page_obj = get_page_by_path( $editing_slug );
    if ( $page_obj ) :
        $page_title   = $page_obj->post_title;
        $page_content_raw = $page_obj->post_content;
?>
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-policies' ) ); ?>" class="btn btn-outline btn-sm">&rarr; العودة</a>
            <div>
                <h1>تحرير: <?php echo esc_html( $policy_pages[ $editing_slug ] ); ?></h1>
                <p>تعديل عنوان ومحتوى صفحة السياسة</p>
            </div>
        </div>
    </div>

    <form id="yamamahPolicyEditorForm">
        <input type="hidden" name="policy_slug" value="<?php echo esc_attr( $editing_slug ); ?>">
        <input type="hidden" name="page_id" value="<?php echo esc_attr( $page_obj->ID ); ?>">

        <div class="card space-y-4 mb-4">
            <div class="form-field">
                <label>عنوان الصفحة</label>
                <input type="text" name="policy_title" value="<?php echo esc_attr( $page_title ); ?>" class="form-control">
            </div>
            <div class="form-field">
                <label>المحتوى</label>
                <div class="yamamah-editor-toolbar">
                    <button type="button" onclick="yamamahExecCmd('bold')" title="عريض"><b>B</b></button>
                    <button type="button" onclick="yamamahExecCmd('italic')" title="مائل"><i>I</i></button>
                    <button type="button" onclick="yamamahExecCmd('underline')" title="تحته خط"><u>U</u></button>
                    <span class="yamamah-toolbar-sep"></span>
                    <button type="button" onclick="yamamahExecCmd('insertUnorderedList')" title="قائمة نقطية">&#8226;</button>
                    <button type="button" onclick="yamamahExecCmd('insertOrderedList')" title="قائمة رقمية">1.</button>
                    <span class="yamamah-toolbar-sep"></span>
                    <button type="button" onclick="yamamahInsertHeading()" title="عنوان">H</button>
                    <button type="button" onclick="yamamahInsertLink()" title="رابط">&#128279;</button>
                    <span class="yamamah-toolbar-sep"></span>
                    <button type="button" onclick="yamamahToggleSource()" title="كود المصدر" id="yamamahSourceBtn">&lt;/&gt;</button>
                </div>
                <div id="yamamahPolicyEditor" class="yamamah-richtext-editor" contenteditable="true" dir="rtl"><?php echo wp_kses_post( $page_content_raw ); ?></div>
                <textarea name="policy_content" id="yamamahPolicySource" class="form-control yamamah-source-editor" style="display:none;" rows="15"><?php echo esc_textarea( $page_content_raw ); ?></textarea>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-green">حفظ التغييرات</button>
            <a href="<?php echo esc_url( get_permalink( $page_obj->ID ) ); ?>" class="btn btn-outline" target="_blank">معاينة الصفحة</a>
        </div>
        <div id="policySaveResult" class="alert alert-success mt-3" style="display:none;">تم الحفظ بنجاح</div>
    </form>

    <script>
    (function($){
        var isSource = false;

        window.yamamahExecCmd = function(cmd, val) {
            document.execCommand(cmd, false, val || null);
            $('#yamamahPolicyEditor').focus();
        };

        window.yamamahInsertHeading = function() {
            document.execCommand('formatBlock', false, '<h3>');
            $('#yamamahPolicyEditor').focus();
        };

        window.yamamahInsertLink = function() {
            var url = prompt('أدخل الرابط:', 'https://');
            if (url) document.execCommand('createLink', false, url);
        };

        window.yamamahToggleSource = function() {
            isSource = !isSource;
            if (isSource) {
                $('#yamamahPolicySource').val( $('#yamamahPolicyEditor').html() ).show();
                $('#yamamahPolicyEditor').hide();
                $('#yamamahSourceBtn').addClass('active');
            } else {
                $('#yamamahPolicyEditor').html( $('#yamamahPolicySource').val() ).show();
                $('#yamamahPolicySource').hide();
                $('#yamamahSourceBtn').removeClass('active');
            }
        };

        $('#yamamahPolicyEditorForm').on('submit', function(e) {
            e.preventDefault();
            if (!isSource) {
                $('#yamamahPolicySource').val( $('#yamamahPolicyEditor').html() );
            }
            $.post(yamamahData.ajax_url, {
                action: 'yamamah_save_policy',
                nonce: yamamahData.nonce,
                policy_slug: $('input[name="policy_slug"]').val(),
                page_id: $('input[name="page_id"]').val(),
                policy_title: $('input[name="policy_title"]').val(),
                policy_content: $('#yamamahPolicySource').val()
            }, function(res) {
                if (res.success) {
                    $('#policySaveResult').fadeIn().delay(2000).fadeOut();
                } else {
                    alert(res.data.message || 'حدث خطأ');
                }
            });
        });
    })(jQuery);
    </script>

<?php else : ?>
    <div class="page-header">
        <h1>السياسات</h1>
        <p class="text-red">الصفحة غير موجودة — يرجى إنشاء الصفحات أولاً</p>
    </div>
<?php endif; ?>

<?php else : ?>
    <div class="page-header">
        <h1>السياسات</h1>
        <p>إدارة صفحات سياسات المتجر</p>
    </div>

    <div class="yamamah-policy-grid">
        <?php foreach ( $policy_pages as $slug => $title ) :
            $page = get_page_by_path( $slug );
            $exists = (bool) $page;
            $edit_url = admin_url( 'admin.php?page=yamamah-policies&edit=' . urlencode( $slug ) );
        ?>
            <div class="yamamah-policy-card card space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-green"><?php echo esc_html( $title ); ?></h3>
                    <?php if ( $exists ) : ?>
                        <span class="badge badge-green">موجودة</span>
                    <?php else : ?>
                        <span class="badge badge-red">غير موجودة</span>
                    <?php endif; ?>
                </div>

                <p class="text-sm text-muted">
                    المسار: <span class="ltr" style="direction:ltr;unicode-bidi:embed;">/<?php echo esc_html( $slug ); ?></span>
                </p>

                <?php if ( $exists ) : ?>
                    <div class="flex gap-2">
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-green">تحرير</a>
                        <a href="<?php echo esc_url( get_permalink( $page->ID ) ); ?>" class="btn btn-sm btn-outline" target="_blank">معاينة</a>
                    </div>
                <?php else : ?>
                    <p class="text-xs text-muted">
                        يرجى <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-pages' ) ); ?>" class="text-purple">إنشاء الصفحات</a> أولاً من قسم "الصفحات".
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
