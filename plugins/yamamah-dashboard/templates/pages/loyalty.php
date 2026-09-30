<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'loyalty_points' ) ) {
    $page_content = yamamah_upgrade_banner( 'نقاط الولاء' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

$plugin_file   = 'yaamama-points/yaamama-points.php';
$is_installed  = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
$is_active     = $is_installed && is_plugin_active( $plugin_file );

$settings      = array( 'earn_rate' => 10, 'point_value' => 0.5, 'max_redeem_percent' => 50, 'statuses_to_earn' => array( 'completed' ) );
$wc_statuses   = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
$customers     = array();
$total_points  = 0;

if ( $is_active && function_exists( 'lap_pts_get_settings' ) ) {
    $settings = lap_pts_get_settings();

    $users = get_users( array( 'meta_key' => '_lap_pts_balance', 'meta_value' => 0, 'meta_compare' => '>', 'meta_type' => 'NUMERIC', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'number' => 50 ) );
    foreach ( $users as $u ) {
        $bal = (int) get_user_meta( $u->ID, '_lap_pts_balance', true );
        if ( $bal <= 0 ) continue;
        $total_points += $bal;
        $customers[] = array(
            'id'      => $u->ID,
            'name'    => $u->display_name,
            'email'   => $u->user_email,
            'balance' => $bal,
            'value'   => round( $bal * $settings['point_value'], 2 ),
        );
    }
}

ob_start();
?>
<div class="page active" id="page-loyalty">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>نقاط الولاء</h1>
            <p>إدارة برنامج نقاط الولاء للعملاء</p>
        </div>
    </div>

    <!-- Toggle + Status -->
    <div class="card mb-4">
        <div class="flex justify-between items-center" style="padding:1.25rem;">
            <div class="flex items-center gap-3">
                <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;
                    background:<?php echo $is_active ? 'rgba(34,197,94,0.1)' : 'rgba(156,163,175,0.1)'; ?>;
                    color:<?php echo $is_active ? '#16a34a' : '#9ca3af'; ?>;">
                    🎯
                </div>
                <div>
                    <h3 style="margin:0;font-size:1rem;">نظام نقاط الولاء</h3>
                    <p class="text-xs text-muted" style="margin:0.25rem 0 0;">
                        <?php if ( ! $is_installed ) : ?>
                            الإضافة غير مثبتة
                        <?php elseif ( $is_active ) : ?>
                            مفعّل — يكسب العملاء نقاطاً عند الشراء
                        <?php else : ?>
                            معطّل — لا يكسب العملاء نقاطاً حالياً
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php if ( $is_installed ) : ?>
                <label class="yamamah-switch" title="<?php echo $is_active ? 'إيقاف' : 'تفعيل'; ?>">
                    <input type="checkbox" id="loyalty-toggle" <?php checked( $is_active ); ?>>
                    <span class="yamamah-switch-slider"></span>
                </label>
            <?php else : ?>
                <span class="badge badge-red">غير مثبت</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $is_active ) : ?>

    <!-- Stats -->
    <div class="grid grid-4 mb-4">
        <div class="yamamah-stat-card" style="--stat-color:#6C3FA0;">
            <p class="stat-value"><?php echo esc_html( count( $customers ) ); ?></p>
            <p class="stat-label">عملاء لديهم نقاط</p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#2D9B83;">
            <p class="stat-value"><?php echo esc_html( number_format( $total_points ) ); ?></p>
            <p class="stat-label">إجمالي النقاط</p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#3498DB;">
            <p class="stat-value"><?php echo esc_html( number_format( $total_points * $settings['point_value'], 2 ) ); ?></p>
            <p class="stat-label">القيمة بالعملة</p>
        </div>
        <div class="yamamah-stat-card" style="--stat-color:#E67E22;">
            <p class="stat-value"><?php echo esc_html( $settings['earn_rate'] ); ?></p>
            <p class="stat-label">عملة لكل نقطة</p>
        </div>
    </div>

    <div class="grid grid-2 mb-4">
        <!-- Settings -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">إعدادات النقاط</h3></div>
            <form id="loyalty-settings-form">
                <div class="form-group">
                    <label>معدل الكسب <span class="text-xs text-muted">(نقطة لكل X عملة)</span></label>
                    <input type="number" class="form-control" name="earn_rate" value="<?php echo esc_attr( $settings['earn_rate'] ); ?>" min="1" step="1">
                </div>
                <div class="form-group">
                    <label>قيمة النقطة <span class="text-xs text-muted">(بالعملة)</span></label>
                    <input type="number" class="form-control" name="point_value" value="<?php echo esc_attr( $settings['point_value'] ); ?>" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>أقصى نسبة استبدال <span class="text-xs text-muted">(%)</span></label>
                    <input type="number" class="form-control" name="max_redeem_percent" value="<?php echo esc_attr( $settings['max_redeem_percent'] ); ?>" min="0" max="100" step="1">
                </div>
                <div class="form-group">
                    <label>حالات الطلب التي تكسب نقاطاً</label>
                    <div class="flex gap-2 flex-wrap" style="padding-top:0.375rem;">
                        <?php foreach ( $wc_statuses as $slug => $label ) :
                            $slug_clean = str_replace( 'wc-', '', $slug );
                        ?>
                        <label class="flex items-center gap-1" style="font-size:0.8125rem;cursor:pointer;">
                            <input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $slug_clean ); ?>"
                                <?php checked( in_array( $slug_clean, (array) $settings['statuses_to_earn'], true ) ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-purple" id="save-loyalty-btn">حفظ الإعدادات</button>
            </form>
        </div>

        <!-- Manual Add/Sub -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">تعديل نقاط عميل</h3></div>
            <form id="loyalty-adjust-form">
                <div class="form-group">
                    <label>البريد الإلكتروني أو ID العميل</label>
                    <input type="text" class="form-control" name="customer" placeholder="email@example.com أو رقم العميل" required>
                </div>
                <div class="form-group">
                    <label>العملية</label>
                    <select class="form-control" name="operation">
                        <option value="add">إضافة نقاط</option>
                        <option value="sub">خصم نقاط</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>عدد النقاط</label>
                    <input type="number" class="form-control" name="points" min="1" step="1" placeholder="0" required>
                </div>
                <button type="submit" class="btn btn-green">تنفيذ</button>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <?php if ( ! empty( $customers ) ) : ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title">العملاء وأرصدتهم</h3></div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العميل</th>
                        <th>البريد</th>
                        <th class="text-center">النقاط</th>
                        <th class="text-center">القيمة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $customers as $i => $c ) : ?>
                    <tr>
                        <td><?php echo esc_html( $i + 1 ); ?></td>
                        <td><?php echo esc_html( $c['name'] ); ?></td>
                        <td class="text-xs"><?php echo esc_html( $c['email'] ); ?></td>
                        <td class="text-center"><span class="badge badge-purple"><?php echo esc_html( number_format( $c['balance'] ) ); ?></span></td>
                        <td class="text-center"><?php echo esc_html( number_format( $c['value'], 2 ) ); ?> ر.س</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ( $is_installed ) : ?>
    <div class="card">
        <div class="text-center p-5">
            <p style="font-size:2.5rem;opacity:0.3;margin-bottom:0.5rem;">🔒</p>
            <h3 class="mb-2">نظام النقاط معطّل</h3>
            <p class="text-muted text-sm">قم بتفعيل النظام من الزر أعلاه لعرض الإعدادات وإدارة نقاط العملاء.</p>
        </div>
    </div>
    <?php else : ?>
    <div class="card">
        <div class="text-center p-5">
            <p style="font-size:2.5rem;opacity:0.3;margin-bottom:0.5rem;">⚠️</p>
            <h3 class="mb-2">إضافة النقاط غير مثبتة</h3>
            <p class="text-muted text-sm">يجب تثبيت إضافة <strong>Yaamama Points</strong> أولاً لاستخدام نظام نقاط الولاء.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function($){
    // Toggle plugin
    $('#loyalty-toggle').on('change', function(){
        var active = this.checked;
        var $el = $(this);
        $el.prop('disabled', true);
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_toggle_loyalty',
            nonce: yamamahData.nonce,
            activate: active ? 1 : 0
        }, function(res){
            $el.prop('disabled', false);
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
                $el.prop('checked', !active);
            }
        }).fail(function(){ $el.prop('disabled', false); $el.prop('checked', !active); });
    });

    // Save settings
    $('#loyalty-settings-form').on('submit', function(e){
        e.preventDefault();
        var $btn = $('#save-loyalty-btn').prop('disabled', true).text('جارٍ الحفظ...');
        var formData = $(this).serializeArray();
        formData.push({name:'action', value:'yamamah_save_loyalty_settings'});
        formData.push({name:'nonce', value: yamamahData.nonce});
        $.post(yamamahData.ajax_url, formData, function(res){
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            if (res.success) {
                $btn.text('تم الحفظ').addClass('btn-green').removeClass('btn-purple');
                setTimeout(function(){ $btn.text('حفظ الإعدادات').removeClass('btn-green').addClass('btn-purple'); }, 2000);
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
            }
        });
    });

    // Adjust points
    $('#loyalty-adjust-form').on('submit', function(e){
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type=submit]').prop('disabled', true);
        var formData = $form.serializeArray();
        formData.push({name:'action', value:'yamamah_adjust_loyalty_points'});
        formData.push({name:'nonce', value: yamamahData.nonce});
        $.post(yamamahData.ajax_url, formData, function(res){
            $btn.prop('disabled', false);
            if (res.success) {
                alert(res.data.message || 'تم بنجاح');
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'حدث خطأ');
            }
        });
    });
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
