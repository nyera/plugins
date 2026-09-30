<?php
/**
 * Plugin Name: Yaamama Points for WooCommerce
 * Description: نظام نقاط بسيط: كسب عند اكتمال الطلب واستبدال في السلة/الدفع.
 * Version: 1.0.0
 * Author: Yaamama
 */

if ( ! defined('ABSPATH') ) exit;

define('LAP_PTS_VER', '1.0.0');
define('LAP_PTS_OPT', 'lap_pts_settings'); // يخزن الإعدادات

/*=====================
* التفعيل + الإعدادات
======================*/
register_activation_hook(__FILE__, function(){
  $defaults = [
    'earn_rate'          => 10,    // كل 10 عملات = نقطة واحدة
    'point_value'        => 0.5,   // قيمة النقطة = 0.5 عملة
    'max_redeem_percent' => 50,    // أقصى استبدال = 50% من قيمة السلة
    'statuses_to_earn'   => ['completed'], // حالات الطلب التي تكسب نقاط
  ];
  add_option(LAP_PTS_OPT, $defaults);
  
  // إضافة نقطة النهاية وإعادة كتابة الروابط
  add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
  flush_rewrite_rules();
});

function lap_pts_get_settings(){
  $s = get_option(LAP_PTS_OPT, []);
  $d = ['earn_rate'=>10,'point_value'=>0.5,'max_redeem_percent'=>50,'statuses_to_earn'=>['completed']];
  return wp_parse_args($s, $d);
}

/*=====================
* دوال مساعدة للنقاط
======================*/
function lap_pts_balance($user_id){
  return max(0, (int) get_user_meta($user_id, '_lap_pts_balance', true));
}
function lap_pts_add($user_id, $points, $note=''){
  $points = (int)$points;
  if($points === 0) return;
  $bal = lap_pts_balance($user_id) + $points;
  update_user_meta($user_id, '_lap_pts_balance', max(0,$bal));
}
function lap_pts_sub($user_id, $points, $note=''){
  lap_pts_add($user_id, -abs((int)$points), $note);
}

/*=====================
* كسب النقاط عند اكتمال الطلب
======================*/
add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate order object
  if ( ! isset($order) || ! is_object($order) || ! method_exists($order, 'get_user_id') ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $set = lap_pts_get_settings();
  if( ! in_array($to, (array)$set['statuses_to_earn'], true) ) return;

  $user_id = $order->get_user_id();
  if( ! $user_id ) return;

  // لا تكرر الكسب
  if( $order->get_meta('_lap_pts_earned_done') ) return;

  $earn_rate   = max(1, (int)$set['earn_rate']);
  $subtotal    = (float) $order->get_subtotal(); // بدون الشحن والضرائب
  
  // إذا تم استخدام نقاط في هذا الطلب، اكسب النقاط على المبلغ الأصلي فقط
  $spent_points = (int) $order->get_meta('_lap_pts_spent');
  if($spent_points > 0){
    $point_value = max(0.0, (float)$set['point_value']);
    $points_discount_value = $spent_points * $point_value;
    // اكسب النقاط على المبلغ الأصلي (بدون خصم النقاط)
    $original_amount = $subtotal + $points_discount_value;
    $points = floor( $original_amount / $earn_rate );
    
    // Debug: إضافة معلومات للتتبع
    $order->update_meta_data('_lap_pts_debug_original_amount', $original_amount);
    $order->update_meta_data('_lap_pts_debug_subtotal', $subtotal);
    $order->update_meta_data('_lap_pts_debug_discount_value', $points_discount_value);
  } else {
    // إذا لم يتم استخدام نقاط، اكسب النقاط على المبلغ العادي
    $points = floor( $subtotal / $earn_rate );
  }

  if($points > 0){
    lap_pts_add($user_id, $points, 'Earned on order #'.$order_id);
    $order->update_meta_data('_lap_pts_earned', $points);
    $order->update_meta_data('_lap_pts_earned_done', 1);
    $order->save();
    
    // Debug
    error_log("LAPANI POINTS DEBUG - Order $order_id completed: Earned $points points for user $user_id");
    lap_pts_debug_user_balance($user_id);
  }
}, 10, 4);

/*=====================
* استبدال النقاط في السلة/الدفع
======================*/
// واجهة الإدخال في مراجعة الطلب — مُعطّلة عمداً: غير موجودة في تصميم الواجهة الأصلي (React).
// منطق كسب النقاط (أعلاه) وربطها بالطلب يبقى كما هو دون تغيير.

function lap_pts_ajax_denied(){ wp_send_json_error('يلزم تسجيل الدخول'); }

// إضافة خصم كـ Fee سالبة
add_action('woocommerce_cart_calculate_fees', function($cart){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  if( is_admin() && ! defined('DOING_AJAX') ) return;
  
  // [LB-REST-COMPAT] START - Validate cart and WC() session
  if ( ! isset($cart) || ! is_object($cart) || ! method_exists($cart, 'get_subtotal') ) {
    return;
  }
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $points = (int) WC()->session->get('lap_pts_applied_points', 0);
  if($points < 1) return;

  $set = lap_pts_get_settings();
  $value = $points * max(0.0, (float)$set['point_value']);

  // تأكد ألا يتجاوز الحد المسموح بالنسبة
  $max_value = max(0, (float)$set['max_redeem_percent']/100.0 * (float)$cart->get_subtotal());
  $value = min($value, $max_value);

  if($value > 0){
    $cart->add_fee( __('خصم النقاط','yaamama-points'), -$value, false );
  }
}, 20);

// حفظ خصم النقاط في الطلب عند إنشاء الطلب
add_action('woocommerce_checkout_create_order', function($order, $data){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate order object and WC() session
  if ( ! isset($order) || ! is_object($order) || ! method_exists($order, 'update_meta_data') ) {
    return;
  }
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $points = (int) WC()->session->get('lap_pts_applied_points', 0);
  if($points > 0){
    $order->update_meta_data('_lap_pts_spent', $points);
    // Debug: Log the points being saved to order
    error_log("LAPANI POINTS DEBUG - Order created: Saving $points points to order meta");
  } else {
    error_log("LAPANI POINTS DEBUG - Order created: No points to save (points=$points)");
  }
}, 10, 2);

// Alternative hook to ensure points are saved
add_action('woocommerce_checkout_update_order_meta', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate WC() session
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $points = (int) WC()->session->get('lap_pts_applied_points', 0);
  error_log("LAPANI POINTS DEBUG - Checkout update order meta: Session points=$points for order $order_id");
  if($points > 0){
    update_post_meta($order_id, '_lap_pts_spent', $points);
    error_log("LAPANI POINTS DEBUG - Order meta updated: Saving $points points to order $order_id");
  }
}, 10, 1);

// Try using a different hook that fires later
add_action('woocommerce_new_order', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate WC() session
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $points = (int) WC()->session->get('lap_pts_applied_points', 0);
  error_log("LAPANI POINTS DEBUG - New order hook: Session points=$points for order $order_id");
  if($points > 0){
    update_post_meta($order_id, '_lap_pts_spent', $points);
    error_log("LAPANI POINTS DEBUG - New order: Saved $points points to order $order_id");
  }
}, 10, 1);

// تطبيق/إزالة النقاط في السلة
add_action('wp_ajax_lap_pts_apply', 'lap_pts_ajax_apply_simple');
add_action('wp_ajax_nopriv_lap_pts_apply', 'lap_pts_ajax_denied');
add_action('wp_ajax_lap_pts_remove', 'lap_pts_ajax_remove_simple');
add_action('wp_ajax_nopriv_lap_pts_remove', 'lap_pts_ajax_denied');

function lap_pts_ajax_apply_simple(){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    wp_send_json_error('REST API requests not supported');
    return;
  }
  // [LB-REST-COMPAT] END
  
  check_ajax_referer('lap_pts_nonce');

  if( ! is_user_logged_in() ) wp_send_json_error('يلزم تسجيل الدخول');
  
  // [LB-REST-COMPAT] START - Validate WC() and cart
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    wp_send_json_error('WooCommerce session not available');
    return;
  }
  // [LB-REST-COMPAT] END
  
  $user_id = get_current_user_id();
  $bal = lap_pts_balance($user_id);
  $points = max(0, (int)($_POST['points'] ?? 0));
  if($points < 1) wp_send_json_error('أدخل عدد نقاط صحيح');
  if($points > $bal) wp_send_json_error('عدد النقاط يتجاوز رصيدك');

  // احسب الحد المسموح كنسبة من مجموع السلة
  $set = lap_pts_get_settings();
  $cart_total = WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;
  $max_value  = max(0, (float)$set['max_redeem_percent'] / 100.0 * $cart_total);
  $point_value = max(0.0, (float)$set['point_value']);
  $requested_value = $points * $point_value;

  if($requested_value <= 0) wp_send_json_error('قيمة النقاط صفر');
  if($requested_value > $max_value + 0.0001){
    $points = floor($max_value / $point_value);
    if($points < 1) wp_send_json_error('لا يمكن استخدام نقاط على هذه السلة حسب الحد الأقصى');
  }

  // حفظ في الجلسة فقط (بدون خصم فوري)
  WC()->session->set('lap_pts_applied_points', $points);
  
  // Verify session was set correctly
  $verify = WC()->session->get('lap_pts_applied_points', 0);
  
  // Debug: Log points application
  error_log("LAPANI POINTS DEBUG - AJAX Apply: User $user_id applied $points points (Balance: $bal, Session verify: $verify)");
  
  wp_send_json_success(['ok'=>1,'points'=>$points]);
}

function lap_pts_ajax_remove_simple(){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    wp_send_json_error('REST API requests not supported');
    return;
  }
  // [LB-REST-COMPAT] END
  
  check_ajax_referer('lap_pts_nonce');
  
  // [LB-REST-COMPAT] START - Validate WC() session
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    wp_send_json_error('WooCommerce session not available');
    return;
  }
  // [LB-REST-COMPAT] END
  
  // Debug: Log points removal
  $user_id = get_current_user_id();
  error_log("LAPANI POINTS DEBUG - AJAX Remove: User $user_id removed applied points");
  
  WC()->session->set('lap_pts_applied_points', 0);
  wp_send_json_success(['ok'=>1]);
}

// خصم النقاط عند إنشاء الطلب
add_action('woocommerce_checkout_order_processed', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $order = wc_get_order($order_id);
  if(!$order) return;
  
  // [LB-REST-COMPAT] START - Validate order object
  if ( ! is_object($order) || ! method_exists($order, 'get_user_id') ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $user_id = $order->get_user_id();
  if(!$user_id) return;

  // Try both methods to get spent points
  $spent = (int)$order->get_meta('_lap_pts_spent');
  if($spent <= 0) {
    $spent = (int)get_post_meta($order_id, '_lap_pts_spent', true);
  }
  
  // Debug: Check what's stored
  error_log("LAPANI POINTS DEBUG - Order $order_id processing: User $user_id, Spent points from meta: $spent");
  
  if($spent > 0 && !$order->get_meta('_lap_pts_spent_deducted')){
    $balance_before = lap_pts_balance($user_id);
    
    // خصم النقاط من رصيد المستخدم
    lap_pts_sub($user_id, $spent, 'Spent on order #'.$order_id);
    $order->update_meta_data('_lap_pts_spent_deducted', 1);
    $order->save();
    
    $balance_after = lap_pts_balance($user_id);
    
    // Debug
    error_log("LAPANI POINTS DEBUG - Order $order_id processed: Deducted $spent points from user $user_id (Before: $balance_before, After: $balance_after)");
  } elseif($spent <= 0) {
    error_log("LAPANI POINTS DEBUG - Order $order_id: No points to deduct (spent=$spent)");
  } elseif($order->get_meta('_lap_pts_spent_deducted')) {
    error_log("LAPANI POINTS DEBUG - Order $order_id: Points already deducted");
  }
}, 10, 1);

// Additional hook to ensure points are deducted when order status changes
add_action('woocommerce_order_status_changed', function($order_id, $from, $to, $order){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // Only process when order is completed
  if($to !== 'completed') return;
  
  // [LB-REST-COMPAT] START - Validate order object
  if ( ! isset($order) || ! is_object($order) || ! method_exists($order, 'get_user_id') ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $user_id = $order->get_user_id();
  if(!$user_id) return;
  
  // Check if points were spent and not yet deducted
  $spent = (int)$order->get_meta('_lap_pts_spent');
  if($spent > 0 && !$order->get_meta('_lap_pts_spent_deducted')){
    $balance_before = lap_pts_balance($user_id);
    
    // خصم النقاط من رصيد المستخدم
    lap_pts_sub($user_id, $spent, 'Spent on order #'.$order_id);
    $order->update_meta_data('_lap_pts_spent_deducted', 1);
    $order->save();
    
    $balance_after = lap_pts_balance($user_id);
    
    error_log("LAPANI POINTS DEBUG - Order $order_id status changed to completed: Deducted $spent points from user $user_id (Before: $balance_before, After: $balance_after)");
  }
}, 10, 4);

// Immediate deduction hook when order is completed
add_action('woocommerce_order_status_completed', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $order = wc_get_order($order_id);
  if(!$order) return;
  
  // [LB-REST-COMPAT] START - Validate order object
  if ( ! is_object($order) || ! method_exists($order, 'get_user_id') ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $user_id = $order->get_user_id();
  if(!$user_id) return;
  
  error_log("LAPANI POINTS DEBUG - Order $order_id status completed hook fired");
  
  // Check if points were spent and not yet deducted
  $spent = (int)$order->get_meta('_lap_pts_spent');
  if($spent <= 0) {
    $spent = (int)get_post_meta($order_id, '_lap_pts_spent', true);
  }
  
  error_log("LAPANI POINTS DEBUG - Order $order_id completed: Found spent points = $spent");
  
  if($spent > 0 && !$order->get_meta('_lap_pts_spent_deducted')){
    $balance_before = lap_pts_balance($user_id);
    
    // خصم النقاط من رصيد المستخدم
    lap_pts_sub($user_id, $spent, 'Spent on order #'.$order_id);
    $order->update_meta_data('_lap_pts_spent_deducted', 1);
    $order->save();
    
    $balance_after = lap_pts_balance($user_id);
    
    error_log("LAPANI POINTS DEBUG - Order $order_id completed hook: Deducted $spent points from user $user_id (Before: $balance_before, After: $balance_after)");
  }
}, 10, 1);

// تنظيف الجلسة عند إكمال الطلب
add_action('woocommerce_thankyou', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate WC() session
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  WC()->session->__unset('lap_pts_applied_points');
}, 10, 1);

// Custom thank you page message - Multiple hooks to ensure it shows
add_action('woocommerce_thankyou', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  lap_pts_custom_thankyou_message($order_id);
}, 5, 1);
add_action('woocommerce_order_details_after_order_table', function($order){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  if(is_int($order)) {
    lap_pts_custom_thankyou_message($order);
  }
}, 5, 1);
add_action('woocommerce_before_thankyou', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  lap_pts_custom_thankyou_message($order_id);
}, 5, 1);

// Removed duplicate thank you message - handled by lapani-thankyou plugin

// Removed duplicate CSS styles - handled by lapani-thankyou plugin

function lap_pts_custom_thankyou_message($order_id){
  // This function is now disabled - points info is handled by lapani-thankyou plugin
  return;
}

// تنظيف الجلسة عند إفراغ السلة
add_action('woocommerce_cart_emptied', function(){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  // [LB-REST-COMPAT] START - Validate WC() session
  if ( ! function_exists('WC') || ! WC() || ! WC()->session ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  WC()->session->__unset('lap_pts_applied_points');
});


/*=====================
* ركن الإدارة: صفحة إعدادات
======================*/
add_action('admin_menu', function(){
  add_submenu_page(
    'woocommerce',
    'نقاط العملاء',
    'نقاط العملاء',
    'manage_woocommerce',
    'yaamama-points',
    'lap_pts_admin_page'
  );
});

function lap_pts_admin_page(){
  if( isset($_POST['lap_pts_save']) && check_admin_referer('lap_pts_save_nonce') ){
    $earn_rate = max(1, (int)($_POST['earn_rate']??10));
    $point_value = max(0, (float)($_POST['point_value']??0.5));
    $max_redeem_percent = min(100, max(0, (int)($_POST['max_redeem_percent']??50)));
    $statuses = array_map('sanitize_text_field', (array)($_POST['statuses']??['completed']));
    update_option(LAP_PTS_OPT, [
      'earn_rate'=>$earn_rate,
      'point_value'=>$point_value,
      'max_redeem_percent'=>$max_redeem_percent,
      'statuses_to_earn'=>$statuses,
    ]);
    echo '<div class="updated"><p>تم الحفظ.</p></div>';
  }
  
  if( isset($_POST['lap_pts_flush']) && check_admin_referer('lap_pts_save_nonce') ){
    add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
    delete_option('lap_pts_rewrite_flushed');
    echo '<div class="updated"><p>تم إعادة كتابة الروابط بنجاح.</p></div>';
  }
  $set = lap_pts_get_settings();
  $wc_statuses = wc_get_order_statuses();
  ?>
  <div class="wrap">
    <h1>إعدادات نقاط العملاء</h1>
    <form method="post">
      <?php wp_nonce_field('lap_pts_save_nonce'); ?>
      <table class="form-table">
        <tr>
          <th>معدل الكسب</th>
          <td>نقطة لكل
            <input type="number" name="earn_rate" value="<?php echo esc_attr($set['earn_rate']); ?>" min="1" step="1" style="width:100px">
            من العملة.
          </td>
        </tr>
        <tr>
          <th>قيمة النقطة</th>
          <td>
            <input type="number" name="point_value" value="<?php echo esc_attr($set['point_value']); ?>" min="0" step="0.01" style="width:120px">
          </td>
        </tr>
        <tr>
          <th>أقصى نسبة استبدال</th>
          <td>
            <input type="number" name="max_redeem_percent" value="<?php echo esc_attr($set['max_redeem_percent']); ?>" min="0" max="100" step="1" style="width:100px"> %
          </td>
        </tr>
        <tr>
          <th>يكسب عند الحالات</th>
          <td>
            <?php foreach($wc_statuses as $slug=>$label): 
              $slug = str_replace('wc-','',$slug); ?>
              <label style="display:inline-block;margin:0 12px 8px 0;">
                <input type="checkbox" name="statuses[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug,(array)$set['statuses_to_earn'],true)); ?>>
                <?php echo esc_html($label); ?>
              </label>
            <?php endforeach; ?>
          </td>
        </tr>
      </table>
      <p>
        <button class="button button-primary" name="lap_pts_save" value="1">حفظ</button>
        <button class="button button-secondary" name="lap_pts_flush" value="1" style="margin-right: 10px;">إعادة كتابة الروابط</button>
      </p>
    </form>
    
    <div style="background: #f0f0f1; padding: 15px; border-radius: 5px; margin-top: 20px;">
      <h3>معلومات إضافية:</h3>
      <p><strong>رابط صفحة النقاط:</strong> <code><?php echo esc_url(wc_get_account_endpoint_url('points')); ?></code></p>
      <p><strong>حالة إعادة كتابة الروابط:</strong> <?php echo get_option('lap_pts_rewrite_flushed') ? 'تمت ✓' : 'لم تتم ✗'; ?></p>
    </div>
  </div>
  <?php
}

/*=====================
* صفحة النقاط في حساب العميل
======================*/
// إضافة صفحة جديدة في حساب العميل
add_action('init', function(){
  add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
});

// إعادة كتابة الروابط عند تفعيل المكون
register_activation_hook(__FILE__, function(){
  add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
  flush_rewrite_rules();
});

// إعادة كتابة الروابط عند إلغاء تفعيل المكون
register_deactivation_hook(__FILE__, function(){
  flush_rewrite_rules();
});

// التأكد من إعادة كتابة الروابط عند زيارة الإدارة
add_action('admin_init', function(){
  if(!get_option('lap_pts_rewrite_flushed')){
    add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
    flush_rewrite_rules();
    update_option('lap_pts_rewrite_flushed', true);
  }
});

// إضافة رابط في قائمة حساب العميل
add_filter('woocommerce_account_menu_items', function($items){
  $new_items = [];
  foreach($items as $key => $item){
    $new_items[$key] = $item;
    if($key === 'orders'){
      $new_items['points'] = 'نقاطي';
    }
  }
  return $new_items;
});

// محتوى صفحة النقاط
add_action('woocommerce_account_points_endpoint', function(){
  $user_id = get_current_user_id();
  $balance = lap_pts_balance($user_id);
  $settings = lap_pts_get_settings();
  
  // جلب تاريخ النقاط (طلبات كسب النقاط)
  $orders = wc_get_orders([
    'customer' => $user_id,
    'meta_query' => [
      [
        'key' => '_lap_pts_earned',
        'compare' => 'EXISTS'
      ]
    ],
    'orderby' => 'date',
    'order' => 'DESC',
    'limit' => 20
  ]);
  
  // جلب الطلبات التي استُخدمت فيها النقاط
  $spent_orders = wc_get_orders([
    'customer' => $user_id,
    'meta_query' => [
      [
        'key' => '_lap_pts_spent',
        'compare' => 'EXISTS'
      ]
    ],
    'orderby' => 'date',
    'order' => 'DESC',
    'limit' => 20
  ]);
  ?>
  <div class="woocommerce-MyAccount-content">
    <div class="lap-points-page">
      <!-- رصيد النقاط الحالي -->
      <div class="points-summary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; text-align: center;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px;">رصيد نقاطك</h2>
        <div style="font-size: 48px; font-weight: bold; margin: 10px 0;"><?php echo esc_html($balance); ?></div>
        <p style="margin: 0; opacity: 0.9;">نقطة</p>
        <div style="margin-top: 15px; font-size: 14px; opacity: 0.8;">
          قيمة النقطة: <?php echo esc_html($settings['point_value']); ?> عملة
        </div>
      </div>

      <!-- معلومات النظام -->
      <div class="points-info" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
          <h3 style="margin: 0 0 10px 0; color: #28a745;">كسب النقاط</h3>
          <p style="margin: 0; font-size: 18px; color: #333;">نقطة واحدة لكل <?php echo esc_html($settings['earn_rate']); ?> عملة</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
          <h3 style="margin: 0 0 10px 0; color: #007bff;">استخدام النقاط</h3>
          <p style="margin: 0; font-size: 18px; color: #333;">حتى <?php echo esc_html($settings['max_redeem_percent']); ?>% من قيمة الطلب</p>
        </div>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
          <h3 style="margin: 0 0 10px 0; color: #6f42c1;">القيمة الإجمالية</h3>
          <p style="margin: 0; font-size: 18px; color: #333;"><?php echo esc_html(number_format($balance * $settings['point_value'], 2)); ?> عملة</p>
        </div>
      </div>

      <!-- تاريخ كسب النقاط -->
      <?php if($orders): ?>
      <div class="points-earned" style="margin-bottom: 30px;">
        <h3 style="border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;">تاريخ كسب النقاط</h3>
        <div class="earned-list" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <?php foreach($orders as $order): 
            $earned_points = (int)$order->get_meta('_lap_pts_earned');
            $order_total = $order->get_subtotal();
          ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee;">
            <div>
              <strong>طلب #<?php echo esc_html($order->get_order_number()); ?></strong>
              <br>
              <small style="color: #666;"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></small>
            </div>
            <div style="text-align: right;">
              <div style="color: #28a745; font-weight: bold;">+<?php echo esc_html($earned_points); ?> نقطة</div>
              <small style="color: #666;">من <?php echo esc_html($order_total); ?> عملة</small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- تاريخ استخدام النقاط -->
      <?php if($spent_orders): ?>
      <div class="points-spent" style="margin-bottom: 30px;">
        <h3 style="border-bottom: 2px solid #dc3545; padding-bottom: 10px; margin-bottom: 20px;">تاريخ استخدام النقاط</h3>
        <div class="spent-list" style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
          <?php foreach($spent_orders as $order): 
            $spent_points = (int)$order->get_meta('_lap_pts_spent');
            $order_total = $order->get_total();
            $discount_value = $spent_points * $settings['point_value'];
          ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee;">
            <div>
              <strong>طلب #<?php echo esc_html($order->get_order_number()); ?></strong>
              <br>
              <small style="color: #666;"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></small>
            </div>
            <div style="text-align: right;">
              <div style="color: #dc3545; font-weight: bold;">-<?php echo esc_html($spent_points); ?> نقطة</div>
              <small style="color: #666;">توفير <?php echo esc_html(number_format($discount_value, 2)); ?> عملة</small>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- رسالة إذا لم تكن هناك معاملات -->
      <?php if(empty($orders) && empty($spent_orders)): ?>
      <div style="text-align: center; padding: 40px; color: #666;">
        <div style="font-size: 48px; margin-bottom: 20px;">🎯</div>
        <h3>لم تكسب أو تستخدم نقاط بعد</h3>
        <p>ابدأ بالتسوق لكسب النقاط واستخدامها في طلباتك القادمة!</p>
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button" style="margin-top: 15px;">تسوق الآن</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  
  <style>
  @media (max-width: 768px) {
    .points-info {
      grid-template-columns: 1fr !important;
    }
    .lap-points-page .points-summary {
      padding: 20px !important;
    }
    .lap-points-page .points-summary div {
      font-size: 36px !important;
    }
  }
  </style>
  <?php
});

// ودجت في "حسابي"
add_action('woocommerce_account_dashboard', function(){
  if( ! is_user_logged_in() ) return;
  echo '<p><strong>رصيد نقاطك:</strong> '.esc_html( lap_pts_balance(get_current_user_id()) ).' نقطة.</p>';
});

/*=====================
* REST API endpoint لإعدادات النقاط (للتطبيق)
======================*/
// [LB-REST-COMPAT] START - Add REST API endpoint for points settings
add_action('rest_api_init', function(){
  register_rest_route('lapani/v1', '/points/settings', array(
    'methods' => 'GET',
    'callback' => 'lap_pts_rest_get_settings',
    'permission_callback' => '__return_true', // Public endpoint for mobile app
  ));
});

function lap_pts_rest_get_settings($request){
  $settings = lap_pts_get_settings();
  
  // Log for debugging
  error_log('LAPANI POINTS: REST API called, returning settings: ' . print_r($settings, true));
  
  $response = array(
    'earn_rate' => (int)$settings['earn_rate'],
    'point_value' => (float)$settings['point_value'],
    'max_redeem_percent' => (int)$settings['max_redeem_percent'],
  );
  
  return new WP_REST_Response($response, 200);
}
// [LB-REST-COMPAT] END

// شورتكود
add_shortcode('lap_points_balance', function($atts){
  if( ! is_user_logged_in() ) return 'يجب تسجيل الدخول.';
  return 'رصيد نقاطك: '.esc_html( lap_pts_balance(get_current_user_id()) ).' نقطة.';
});

// شورتكود لصفحة الشكر
add_shortcode('lap_thankyou_message', function($atts){
  global $wp;
  
  // Check if we're on the thank you page
  if(!is_wc_endpoint_url('order-received')){
    return '';
  }
  
  // Get order ID from URL
  $order_id = 0;
  if(isset($wp->query_vars['order-received'])){
    $order_id = absint($wp->query_vars['order-received']);
  }
  
  if(!$order_id) return '';
  
  $order = wc_get_order($order_id);
  if(!$order) return '';
  
  ob_start();
  lap_pts_custom_thankyou_message($order_id);
  return ob_get_clean();
});

// Debug function to manually fix orders with missing point deductions
add_action('wp_ajax_lap_pts_debug_fix', function(){
  if(!current_user_can('manage_options')) wp_die('Unauthorized');
  
  $order_id = (int)($_GET['order_id'] ?? 0);
  if(!$order_id) wp_die('No order ID');
  
  $order = wc_get_order($order_id);
  if(!$order) wp_die('Order not found');
  
  $user_id = $order->get_user_id();
  if(!$user_id) wp_die('No user ID');
  
  $spent = (int)$order->get_meta('_lap_pts_spent');
  if($spent <= 0) {
    $spent = (int)get_post_meta($order_id, '_lap_pts_spent', true);
  }
  
  echo "Order $order_id: User $user_id, Spent points: $spent<br>";
  
  if($spent > 0 && !$order->get_meta('_lap_pts_spent_deducted')){
    $balance_before = lap_pts_balance($user_id);
    lap_pts_sub($user_id, $spent, 'Manual fix for order #'.$order_id);
    $order->update_meta_data('_lap_pts_spent_deducted', 1);
    $order->save();
    $balance_after = lap_pts_balance($user_id);
    
    echo "Fixed! Deducted $spent points. Balance: $balance_before -> $balance_after";
  } else {
    echo "No action needed. Spent: $spent, Already deducted: " . ($order->get_meta('_lap_pts_spent_deducted') ? 'Yes' : 'No');
  }
  
  wp_die();
});

// دالة مساعدة للتتبع
function lap_pts_debug_user_balance($user_id){
  if(!$user_id) return;
  
  // [LB-REST-COMPAT] START - Validate WC() session before use
  $applied = 0;
  if ( function_exists('WC') && WC() && WC()->session ) {
    $applied = WC()->session->get('lap_pts_applied_points', 0);
  }
  // [LB-REST-COMPAT] END
  
  $balance = lap_pts_balance($user_id);
  error_log("LAPANI POINTS DEBUG - User $user_id: Balance=$balance, Applied=$applied");
}

/*=====================
* ردّ فعل عند الاسترداد/الاسترجاع (مبسط)
======================*/
add_action('woocommerce_order_status_refunded', function($order_id){
  // [LB-REST-COMPAT] START - Skip if request is from REST API
  if ( defined('REST_REQUEST') && REST_REQUEST ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $order = wc_get_order($order_id);
  if(!$order) return;
  
  // [LB-REST-COMPAT] START - Validate order object
  if ( ! is_object($order) || ! method_exists($order, 'get_user_id') ) {
    return;
  }
  // [LB-REST-COMPAT] END
  
  $user_id = $order->get_user_id();
  if(!$user_id) return;

  $earned = (int)$order->get_meta('_lap_pts_earned');
  if($earned>0){
    lap_pts_sub($user_id, $earned, 'Refund adjustment for order #'.$order_id);
  }
});
