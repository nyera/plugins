<?php
/**
 * صفحة تعليقات الرئيسية
 * المتغير $reviews يأتي من render_home_reviews()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

ob_start();
?>

<div class="page active" id="page-home-reviews">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>تعليقات الرئيسية</h1>
            <p>إدارة التعليقات المعروضة في الصفحة الرئيسية (<?php echo count( $reviews ); ?> تعليق)</p>
        </div>
        <button type="button" class="btn btn-green" id="toggleReviewForm">+ إضافة تعليق</button>
    </div>

    <!-- نموذج الإضافة / التعديل -->
    <div class="card mb-4" id="reviewFormCard" style="display:none;">
        <h3 class="text-green mb-4" id="reviewFormTitle">إضافة تعليق جديد</h3>
        <form id="yamamahReviewForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="edit_index" id="reviewEditIndex" value="">
            <input type="hidden" name="review_image" id="reviewImageId" value="0">
            <div class="grid grid-2 mb-4">
                <div class="form-field">
                    <label class="text-sm mb-2" style="display:block;">الاسم</label>
                    <input type="text" name="review_name" id="reviewName" class="form-control" required>
                </div>
                <div class="form-field">
                    <label class="text-sm mb-2" style="display:block;">التقييم</label>
                    <select name="review_rating" id="reviewRating" class="form-control">
                        <option value="5">5 نجوم</option>
                        <option value="4">4 نجوم</option>
                        <option value="3">3 نجوم</option>
                        <option value="2">2 نجمتان</option>
                        <option value="1">1 نجمة</option>
                    </select>
                </div>
            </div>
            <div class="form-field mb-4">
                <label class="text-sm mb-2" style="display:block;">نص التعليق</label>
                <textarea name="review_text" id="reviewText" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-field mb-4">
                <label class="text-sm mb-2" style="display:block;">صورة المراجع</label>
                <div class="flex items-center gap-3 flex-wrap">
                    <img id="reviewImagePreview" src="" alt="" style="display:none;width:64px;height:64px;border-radius:50%;object-fit:cover;">
                    <button type="button" class="btn btn-outline btn-sm" id="reviewPickImage">اختيار صورة</button>
                    <button type="button" class="btn btn-outline btn-sm" id="reviewClearImage" style="display:none;">إزالة الصورة</button>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-green" id="reviewSubmitBtn">حفظ التعليق</button>
                <button type="button" class="btn btn-outline" id="cancelReviewForm">إلغاء</button>
            </div>
            <div id="reviewSaveResult" class="alert mt-3" style="display:none;"></div>
        </form>
    </div>

    <!-- جدول التعليقات -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>الاسم</th>
                        <th>التعليق</th>
                        <th style="width:120px;">التقييم</th>
                        <th style="width:70px;">الصورة</th>
                        <th style="width:140px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody id="reviewsTableBody">
                    <?php if ( ! empty( $reviews ) ) : ?>
                        <?php foreach ( $reviews as $idx => $rev ) : ?>
                            <tr data-index="<?php echo (int) $idx; ?>"
                                data-name="<?php echo esc_attr( $rev['name'] ?? '' ); ?>"
                                data-text="<?php echo esc_attr( $rev['text'] ?? '' ); ?>"
                                data-rating="<?php echo (int) ( $rev['rating'] ?? 5 ); ?>"
                                data-image="<?php echo (int) ( $rev['image'] ?? 0 ); ?>"
                                data-image-url="<?php echo esc_url( $rev['image_url'] ?? '' ); ?>">
                                <td><?php echo (int) ( $idx + 1 ); ?></td>
                                <td><?php echo esc_html( $rev['name'] ?? '' ); ?></td>
                                <td><?php echo esc_html( wp_trim_words( $rev['text'] ?? '', 15 ) ); ?></td>
                                <td>
                                    <?php
                                    $rating = (int) ( $rev['rating'] ?? 5 );
                                    for ( $s = 1; $s <= 5; $s++ ) {
                                        echo $s <= $rating ? '★' : '☆';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $rev['image_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $rev['image_url'] ); ?>" alt=""
                                             style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline btn-edit-review"
                                            data-index="<?php echo (int) $idx; ?>">تعديل</button>
                                    <button type="button" class="btn btn-sm btn-outline btn-delete-review"
                                            data-index="<?php echo (int) $idx; ?>">حذف</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr id="noReviewsRow">
                            <td colspan="6" class="text-center text-muted p-5">لا توجد تعليقات بعد</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function($){
    function resetReviewForm() {
        $('#reviewFormTitle').text('إضافة تعليق جديد');
        $('#reviewSubmitBtn').text('حفظ التعليق');
        $('#reviewEditIndex').val('');
        $('#yamamahReviewForm')[0].reset();
        $('#reviewImageId').val('0');
        $('#reviewImagePreview').hide().attr('src', '');
        $('#reviewClearImage').hide();
    }

    function showReviewForm() {
        $('#reviewFormCard').slideDown(200);
        $('html, body').animate({ scrollTop: $('#reviewFormCard').offset().top - 80 }, 200);
    }

    $('#toggleReviewForm').on('click', function(){
        resetReviewForm();
        $('#reviewFormCard').slideToggle(200);
    });

    $('#cancelReviewForm').on('click', function(){
        $('#reviewFormCard').slideUp(200);
        resetReviewForm();
    });

    $('#reviewPickImage').on('click', function(e){
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) {
            alert('مكتبة الوسائط غير متاحة');
            return;
        }
        var frame = wp.media({ multiple: false, library: { type: 'image' } });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            if (att && att.id) {
                $('#reviewImageId').val(att.id);
                var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                $('#reviewImagePreview').attr('src', url).show();
                $('#reviewClearImage').show();
            }
        });
        frame.open();
    });

    $('#reviewClearImage').on('click', function(){
        $('#reviewImageId').val('0');
        $('#reviewImagePreview').hide().attr('src', '');
        $(this).hide();
    });

    $(document).on('click', '.btn-edit-review', function(){
        var $row = $(this).closest('tr');
        resetReviewForm();
        $('#reviewFormTitle').text('تعديل التعليق');
        $('#reviewSubmitBtn').text('تحديث التعليق');
        $('#reviewEditIndex').val($row.data('index'));
        $('#reviewName').val($row.data('name'));
        $('#reviewText').val($row.data('text'));
        $('#reviewRating').val(String($row.data('rating') || 5));
        var imageId = parseInt($row.data('image'), 10) || 0;
        var imageUrl = $row.data('image-url') || '';
        $('#reviewImageId').val(imageId);
        if (imageUrl) {
            $('#reviewImagePreview').attr('src', imageUrl).show();
            $('#reviewClearImage').show();
        }
        showReviewForm();
    });

    $('#yamamahReviewForm').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#reviewSubmitBtn');
        var $result = $('#reviewSaveResult');
        $btn.prop('disabled', true);

        var formData = new FormData(this);
        formData.append('action', 'yamamah_save_review');
        formData.append('nonce', yamamahData.nonce);

        $.ajax({
            url: yamamahData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res){
                $btn.prop('disabled', false);
                if (res.success) {
                    $result.removeClass('alert-error').addClass('alert-success').text('تم حفظ التعليق بنجاح').show();
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
                    $result.removeClass('alert-success').addClass('alert-error').text(msg).show();
                }
                setTimeout(function(){ $result.fadeOut(); }, 3000);
            },
            error: function(){
                $btn.prop('disabled', false);
                $result.removeClass('alert-success').addClass('alert-error').text('حدث خطأ في الاتصال').show();
                setTimeout(function(){ $result.fadeOut(); }, 3000);
            }
        });
    });

    $(document).on('click', '.btn-delete-review', function(){
        if (!confirm('هل تريد حذف هذا التعليق؟')) return;
        var $row = $(this).closest('tr');
        var idx = $(this).data('index');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_review',
            nonce: yamamahData.nonce,
            index: idx
        }, function(res){
            if (res.success) {
                $row.fadeOut(300, function(){
                    $(this).remove();
                    if (!$('#reviewsTableBody tr').length) {
                        $('#reviewsTableBody').html('<tr id="noReviewsRow"><td colspan="6" class="text-center text-muted p-5">لا توجد تعليقات بعد</td></tr>');
                    }
                });
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ أثناء الحذف');
                alert(msg);
            }
        }).fail(function(){
            alert('حدث خطأ في الاتصال');
        });
    });
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
