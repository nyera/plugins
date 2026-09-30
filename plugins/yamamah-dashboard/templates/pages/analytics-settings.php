<?php
/**
 * صفحة إعدادات التحليلات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$excluded   = $data['excluded_statuses'];
$actionable = $data['actionable_statuses'];
$date_range = $data['date_range'];
$date_type  = $data['date_type'];

if ( ! is_array( $excluded ) )   $excluded   = array();
if ( ! is_array( $actionable ) ) $actionable = array();

$today_ar = wp_date( 'l، j F Y' );

$all_statuses = array(
    'pending'    => 'بانتظار الدفع',
    'processing' => 'قيد التنفيذ',
    'on-hold'    => 'قيد الإنتظار',
    'completed'  => 'مكتمل',
    'cancelled'  => 'ملغي',
    'refunded'   => 'مسترد',
    'failed'     => 'فشل',
    'draft'      => 'مسودة',
);

ob_start();
?>

<div class="page active" id="page-analytics-settings">
    <div class="page-header" style="text-align:center;">
        <h1>إعدادات التحليلات</h1>
        <span class="yamamah-date-stamp"><?php echo esc_html( $today_ar ); ?></span>
    </div>

    <div class="yamamah-settings-narrow">

        <!-- Excluded Statuses -->
        <div class="mb-5">
            <h2 class="yamamah-section-title">الحالات المستبعدة</h2>
            <div class="yamamah-settings-box purple-dashed">
                <p class="text-xs text-muted mb-3">
                    تستبعد الطلبات التي تتضمن هذه الحالات من الإحصائيات في تقاريرك. يُفضل استبعاد حالة "غير الإسترداد".
                </p>
                <div class="yamamah-status-list" id="excludedStatusList">
                    <?php foreach ( $all_statuses as $key => $label ) :
                        $is_checked = in_array( $key, $excluded, true );
                    ?>
                    <div class="yamamah-status-row">
                        <span class="text-sm"><?php echo esc_html( $label ); ?></span>
                        <div class="yamamah-status-actions">
                            <span class="yamamah-status-badge <?php echo $is_checked ? 'badge-active' : ''; ?>">
                                <?php echo $is_checked ? 'مستبعدة' : 'مشمولة'; ?>
                            </span>
                            <label class="yamamah-checkbox-wrap">
                                <input type="checkbox" name="excluded[]" value="<?php echo esc_attr( $key ); ?>"
                                    <?php checked( $is_checked ); ?>
                                    onchange="yamamahToggleExcludedBadge(this)">
                                <span class="yamamah-custom-checkbox"></span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Actionable Statuses -->
        <div class="mb-5">
            <h2 class="yamamah-section-title">الحالات القابلة للتنفيذ</h2>
            <div class="yamamah-settings-box purple-dashed">
                <p class="text-xs text-muted mb-3">
                    تمثل الطلبات الخاصة بتلك الحالات التي تحتاج إجراء سابق من مسئولي المتجر. ستظهر هذه الطلبات في الصفحة الرئيسية - صفحة الطلبات.
                </p>
                <div class="yamamah-status-list" id="actionableStatusList">
                    <?php foreach ( $all_statuses as $key => $label ) :
                        $is_enabled = in_array( $key, $actionable, true );
                    ?>
                    <div class="yamamah-status-row">
                        <span class="text-sm"><?php echo esc_html( $label ); ?></span>
                        <div class="yamamah-status-actions">
                            <span class="yamamah-status-badge <?php echo $is_enabled ? 'badge-active' : ''; ?>">
                                <?php echo $is_enabled ? 'مفعّل' : 'غير مفعّل'; ?>
                            </span>
                            <label class="yamamah-toggle-switch">
                                <input type="checkbox" name="actionable[]" value="<?php echo esc_attr( $key ); ?>"
                                    <?php checked( $is_enabled ); ?>
                                    onchange="yamamahToggleActionableBadge(this)">
                                <span class="yamamah-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Date Range -->
        <div class="mb-5">
            <h2 class="yamamah-section-title">نطاق التاريخ الافتراضي</h2>
            <div class="yamamah-settings-box purple-dashed">
                <p class="text-xs text-muted mb-3">
                    حدد نطاق التاريخ الافتراضي. عند عدم تحديد النطاق، ستُعرض التقارير عن طريق نطاق التاريخ الافتراضي.
                </p>
                <select id="analyticsDateRange" class="yamamah-settings-select">
                    <option value="month_to_date" <?php selected( $date_range, 'month_to_date' ); ?>>شهر حتى تاريخه</option>
                    <option value="last_week" <?php selected( $date_range, 'last_week' ); ?>>الأسبوع الماضي</option>
                    <option value="last_month" <?php selected( $date_range, 'last_month' ); ?>>الشهر الماضي</option>
                    <option value="quarter" <?php selected( $date_range, 'quarter' ); ?>>ربع سنة</option>
                    <option value="year_to_date" <?php selected( $date_range, 'year_to_date' ); ?>>سنة حتى تاريخه</option>
                    <option value="last_year" <?php selected( $date_range, 'last_year' ); ?>>السنة الماضية</option>
                </select>
            </div>
        </div>

        <!-- Date Type -->
        <div class="mb-5">
            <h2 class="yamamah-section-title">نوع التاريخ</h2>
            <div class="yamamah-settings-box purple-dashed">
                <p class="text-xs text-muted mb-3">
                    ترتيب حقل التاريخ في الفلاتر المكانية الخاصة بتقارير الإيرادات والطلبات.
                </p>
                <select id="analyticsDateType" class="yamamah-settings-select">
                    <option value="date_created" <?php selected( $date_type, 'date_created' ); ?>>تاريخ الإنشاء</option>
                    <option value="date_modified" <?php selected( $date_type, 'date_modified' ); ?>>تاريخ التعديل</option>
                    <option value="date_completed" <?php selected( $date_type, 'date_completed' ); ?>>تاريخ الاكتمال</option>
                </select>
            </div>
        </div>

        <button class="btn btn-green" onclick="yamamahSaveAnalyticsSettings()">حفظ الإعدادات</button>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
