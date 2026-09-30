<?php
/**
 * صفحة إعدادات الحجز
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$defaults = array(
    'default_duration' => 60,
    'buffer_time'      => 0,
    'max_concurrent'   => 1,
    'working_hours'    => array(),
    'holidays'         => array(),
);
$s = is_array( $settings ) ? array_merge( $defaults, $settings ) : $defaults;

$wh = is_array( $s['working_hours'] ) ? $s['working_hours'] : array();
$holidays = is_array( $s['holidays'] ) ? $s['holidays'] : array();

$days_config = array(
    'sunday'    => 'الأحد',
    'monday'    => 'الاثنين',
    'tuesday'   => 'الثلاثاء',
    'wednesday' => 'الأربعاء',
    'thursday'  => 'الخميس',
    'friday'    => 'الجمعة',
    'saturday'  => 'السبت',
);

$wh_defaults = array(
    'sunday'    => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
    'monday'    => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
    'tuesday'   => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
    'wednesday' => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
    'thursday'  => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
    'friday'    => array( 'enabled' => false, 'start' => '', 'end' => '' ),
    'saturday'  => array( 'enabled' => true, 'start' => '08:00', 'end' => '22:00' ),
);
$wh = array_merge( $wh_defaults, $wh );

ob_start();
?>
<?php $store_type = yamamah_get_store_type(); ?>
<div class="page active" id="page-settings-rental">
    <div class="page-header">
        <h1><?php echo $store_type === 'rental' ? 'إعدادات التأجير' : 'إعدادات الحجز'; ?></h1>
        <p><?php echo $store_type === 'rental' ? 'ضبط إعدادات التأجير وأوقات العمل' : 'ضبط إعدادات الحجز وأوقات العمل'; ?></p>
    </div>

    <form id="yamamahRentalSettingsForm" method="post">
        <?php wp_nonce_field( 'yamamah_nonce', 'yamamah_nonce' ); ?>
        <input type="hidden" name="action" value="yamamah_save_rental_settings">

        <!-- إعدادات المواعيد -->
        <div class="card space-y-4 mb-4">
            <div class="card-section-title" style="font-size:1.05rem;font-weight:700;margin-bottom:0.5rem;">إعدادات المواعيد</div>

            <div class="form-group">
                <label>المدة الافتراضية للموعد (بالدقائق)</label>
                <input type="number" class="form-control" name="default_duration"
                       value="<?php echo esc_attr( $s['default_duration'] ); ?>" min="5" step="5" style="max-width:150px;">
                <small class="text-muted">المدة الافتراضية لكل موعد حجز (يمكن تخصيصها لكل خدمة)</small>
            </div>

            <div class="form-group">
                <label>الفاصل بين المواعيد (بالدقائق)</label>
                <input type="number" class="form-control" name="buffer_time"
                       value="<?php echo esc_attr( $s['buffer_time'] ); ?>" min="0" step="5" style="max-width:150px;">
                <small class="text-muted">وقت فاصل بين كل موعد والذي يليه (0 = بدون فاصل)</small>
            </div>

            <div class="form-group">
                <label>السماح بحجوزات متزامنة</label>
                <input type="number" class="form-control" name="max_concurrent"
                       value="<?php echo esc_attr( $s['max_concurrent'] ); ?>" min="1" style="max-width:150px;">
                <small class="text-muted">عدد الحجوزات المسموح بها في نفس الوقت</small>
            </div>
        </div>

        <!-- أوقات وأيام العمل -->
        <div class="card space-y-4 mb-4">
            <div class="card-section-title" style="font-size:1.05rem;font-weight:700;margin-bottom:0.5rem;">أوقات وأيام العمل</div>
            <small class="text-muted" style="display:block;margin-bottom:1rem;">حدد أيام وساعات العمل. لن يُسمح بالحجز خارج هذه الأوقات.</small>

            <div class="yamamah-working-hours-table" style="overflow-x:auto;">
                <table style="width:100%;font-size:0.875rem;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border,#e5e7eb);">
                            <th style="padding:0.6rem;text-align:right;width:120px;">اليوم</th>
                            <th style="padding:0.6rem;text-align:center;width:80px;">مفعّل</th>
                            <th style="padding:0.6rem;text-align:center;">وقت البداية</th>
                            <th style="padding:0.6rem;text-align:center;">وقت النهاية</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $days_config as $day_key => $day_label ) :
                        $day_data = isset( $wh[ $day_key ] ) ? $wh[ $day_key ] : $wh_defaults[ $day_key ];
                        $enabled  = ! empty( $day_data['enabled'] );
                    ?>
                        <tr style="border-bottom:1px solid var(--border,#f0f0f0);" class="wh-row" data-day="<?php echo esc_attr( $day_key ); ?>">
                            <td style="padding:0.6rem;font-weight:600;"><?php echo esc_html( $day_label ); ?></td>
                            <td style="padding:0.6rem;text-align:center;">
                                <label class="yamamah-toggle" style="display:inline-flex;">
                                    <input type="checkbox" class="wh-enabled"
                                           name="wh_<?php echo esc_attr( $day_key ); ?>_enabled"
                                           value="1" <?php checked( $enabled ); ?>>
                                    <span class="yamamah-toggle-slider"></span>
                                </label>
                            </td>
                            <td style="padding:0.6rem;text-align:center;">
                                <input type="time" class="form-control ltr wh-start"
                                       name="wh_<?php echo esc_attr( $day_key ); ?>_start"
                                       value="<?php echo esc_attr( $day_data['start'] ?? '08:00' ); ?>"
                                       style="max-width:130px;margin:0 auto;" <?php echo ! $enabled ? 'disabled' : ''; ?>>
                            </td>
                            <td style="padding:0.6rem;text-align:center;">
                                <input type="time" class="form-control ltr wh-end"
                                       name="wh_<?php echo esc_attr( $day_key ); ?>_end"
                                       value="<?php echo esc_attr( $day_data['end'] ?? '22:00' ); ?>"
                                       style="max-width:130px;margin:0 auto;" <?php echo ! $enabled ? 'disabled' : ''; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- الإجازات -->
        <div class="card space-y-4 mb-4">
            <div class="card-section-title" style="font-size:1.05rem;font-weight:700;margin-bottom:0.5rem;">إجازات استثنائية</div>
            <small class="text-muted" style="display:block;margin-bottom:1rem;">أضف تواريخ محددة لا يُسمح بالحجز فيها (أعياد، مناسبات، صيانة...).</small>

            <div id="holidaysList">
                <?php if ( ! empty( $holidays ) ) : ?>
                    <?php foreach ( $holidays as $h_date ) : ?>
                    <div class="flex items-center gap-2 mb-2 holiday-row">
                        <input type="date" class="form-control ltr holiday-input" lang="en" dir="ltr" value="<?php echo esc_attr( $h_date ); ?>" style="max-width:200px;">
                        <button type="button" class="btn btn-sm btn-red" onclick="this.closest('.holiday-row').remove();">حذف</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline" id="addHolidayBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                إضافة إجازة
            </button>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-green" id="saveRentalBtn">حفظ الإعدادات</button>
            <div id="rentalSaveResult" class="alert alert-success mt-3" style="display:none;">
                تم الحفظ بنجاح
            </div>
        </div>
    </form>
</div>

<style>
.yamamah-toggle { position:relative;width:44px;height:24px;cursor:pointer; }
.yamamah-toggle input { opacity:0;width:0;height:0; }
.yamamah-toggle-slider {
    position:absolute;inset:0;background:#ccc;border-radius:24px;transition:0.3s;
}
.yamamah-toggle-slider::before {
    content:'';position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;
    top:3px;left:3px;transition:0.3s;
}
.yamamah-toggle input:checked + .yamamah-toggle-slider { background:#16a34a; }
.yamamah-toggle input:checked + .yamamah-toggle-slider::before { transform:translateX(20px); }
.wh-row .wh-start:disabled, .wh-row .wh-end:disabled { opacity:0.4;pointer-events:none; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.wh-enabled').on('change', function() {
        var $row = $(this).closest('.wh-row');
        var on = $(this).is(':checked');
        $row.find('.wh-start, .wh-end').prop('disabled', !on);
    });

    $('#addHolidayBtn').on('click', function() {
        $('#holidaysList').append(
            '<div class="flex items-center gap-2 mb-2 holiday-row">' +
            '<input type="date" class="form-control ltr holiday-input" lang="en" dir="ltr" style="max-width:200px;">' +
            '<button type="button" class="btn btn-sm btn-red" onclick="this.closest(\'.holiday-row\').remove();">حذف</button>' +
            '</div>'
        );
    });

    $('#yamamahRentalSettingsForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#saveRentalBtn');
        var $result = $('#rentalSaveResult');

        var workingHours = {};
        $('.wh-row').each(function() {
            var day = $(this).data('day');
            workingHours[day] = {
                enabled: $(this).find('.wh-enabled').is(':checked') ? 1 : 0,
                start:   $(this).find('.wh-start').val() || '',
                end:     $(this).find('.wh-end').val() || ''
            };
        });

        var holidays = [];
        $('.holiday-input').each(function() {
            var v = $(this).val();
            if (v) holidays.push(v);
        });

        $btn.prop('disabled', true).text('جاري الحفظ...');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_rental_settings',
            nonce: yamamahData.nonce,
            default_duration: $('[name="default_duration"]').val(),
            buffer_time: $('[name="buffer_time"]').val(),
            max_concurrent: $('[name="max_concurrent"]').val(),
            working_hours: JSON.stringify(workingHours),
            holidays: JSON.stringify(holidays)
        }, function(res) {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            if (res.success) {
                $result.text('تم الحفظ بنجاح').removeClass('alert-danger').addClass('alert-success').fadeIn();
            } else {
                $result.text(res.data || 'حدث خطأ').removeClass('alert-success').addClass('alert-danger').fadeIn();
            }
            setTimeout(function() { $result.fadeOut(); }, 3000);
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            $result.text('خطأ في الاتصال').removeClass('alert-success').addClass('alert-danger').fadeIn();
            setTimeout(function() { $result.fadeOut(); }, 3000);
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
