<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'abandoned_carts' ) ) {
    $page_content = yamamah_upgrade_banner( 'السلات المتروكة' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

ob_start();

$total_value = 0;
foreach ( $carts as $c ) {
    $total_value += (float) $c->cart_total;
}
$reminded = 0;
foreach ( $carts as $c ) { if ( $c->reminder_sent ) $reminded++; }

$s = $abandoned_settings;
$auto_enabled   = $s['auto_enabled'] ?? 'no';
$auto_delay     = $s['auto_delay'] ?? 45;
$auto_max       = $s['auto_max_reminders'] ?? 1;
$cpn_enabled    = $s['coupon_enabled'] ?? 'no';
$cpn_type       = $s['coupon_type'] ?? 'percent';
$cpn_amount     = $s['coupon_amount'] ?? 10;
$cpn_expiry     = $s['coupon_expiry'] ?? 3;
$cpn_min        = $s['coupon_min_amount'] ?? 0;
$current_minutes = intval( get_option( 'yamamah_abandon_minutes', 30 ) );

$carts_json = array();
foreach ( $carts as $c ) {
    $items_arr = json_decode( $c->cart_contents, true );
    $items_arr = is_array( $items_arr ) ? $items_arr : array();

    $phone_raw = $c->customer_phone;
    $wa_phone  = '';
    if ( $phone_raw ) {
        $clean = preg_replace( '/[^0-9]/', '', $phone_raw );
        if ( substr( $clean, 0, 1 ) === '0' ) {
            $clean = '966' . substr( $clean, 1 );
        } elseif ( substr( $clean, 0, 3 ) !== '966' && strlen( $clean ) === 9 ) {
            $clean = '966' . $clean;
        }
        $wa_phone = $clean;
    }

    $enriched_items = array();
    foreach ( $items_arr as $it ) {
        $img_url = '';
        if ( ! empty( $it['product_id'] ) ) {
            $thumb_id = get_post_thumbnail_id( $it['product_id'] );
            if ( $thumb_id ) {
                $img_src = wp_get_attachment_image_src( $thumb_id, 'thumbnail' );
                if ( $img_src ) $img_url = $img_src[0];
            }
        }
        $it['image'] = $img_url;
        $enriched_items[] = $it;
    }

    $carts_json[ $c->id ] = array(
        'id'       => $c->id,
        'name'     => $c->customer_name ?: '',
        'email'    => $c->customer_email ?: '',
        'phone'    => $phone_raw ?: '',
        'wa_phone' => $wa_phone,
        'user_id'  => $c->user_id,
        'total'    => (float) $c->cart_total,
        'count'    => (int) $c->item_count,
        'items'    => $enriched_items,
        'created'  => $c->created_at,
        'updated'  => $c->updated_at,
        'reminder' => (int) $c->reminder_sent,
    );
}
?>
<div class="page active" id="page-abandoned-carts">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>السلات المتروكة</h1>
            <p>سلات التسوق التي لم يكمل أصحابها عملية الشراء</p>
        </div>
        <button type="button" class="btn btn-outline" id="toggleSettingsPanel" style="gap:0.35rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            الإعدادات
        </button>
    </div>

    <!-- ── Settings Panel ── -->
    <div class="card mb-4" id="settingsPanel" style="display:none;">
        <div class="card-header"><h3 class="card-title">إعدادات السلات المتروكة</h3></div>
        <div class="abandoned-settings-grid">
            <div>
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 1rem;color:var(--green,#10b981);">إعدادات عامة</h4>
                <div class="form-group mb-3">
                    <label>مدة الانتظار لاعتبار السلة متروكة</label>
                    <select id="sAbandonMinutes" class="form-control" style="max-width:200px;">
                        <?php foreach ( array( 15, 20, 30, 45, 60 ) as $min ) : ?>
                            <option value="<?php echo $min; ?>" <?php selected( $current_minutes, $min ); ?>><?php echo $min; ?> دقيقة</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <h4 style="font-size:0.9rem;font-weight:600;margin:1.5rem 0 1rem;color:var(--green,#10b981);">التذكير التلقائي</h4>
                <div class="form-group mb-3">
                    <div class="flex items-center gap-3">
                        <label class="yamamah-switch" style="margin:0;">
                            <input type="checkbox" id="sAutoEnabled" <?php checked( $auto_enabled, 'yes' ); ?>>
                            <span class="yamamah-switch-slider"></span>
                        </label>
                        <span style="font-weight:500;">تفعيل الإرسال التلقائي بالبريد</span>
                    </div>
                    <small class="text-xs text-muted" style="display:block;margin-top:0.4rem;">يرسل تذكير تلقائي بالبريد للسلات المتروكة بدون تدخل يدوي</small>
                </div>
                <div class="form-row" id="autoFields" style="<?php echo $auto_enabled !== 'yes' ? 'display:none;' : ''; ?>">
                    <div class="form-group">
                        <label>إرسال بعد (دقيقة)</label>
                        <input type="number" class="form-control" id="sAutoDelay" value="<?php echo esc_attr( $auto_delay ); ?>" min="15" max="120">
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى للتذكيرات</label>
                        <select class="form-control" id="sAutoMax">
                            <?php for ( $i = 1; $i <= 3; $i++ ) : ?>
                                <option value="<?php echo $i; ?>" <?php selected( $auto_max, $i ); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 1rem;color:var(--green,#10b981);">كوبون الخصم التلقائي</h4>
                <div class="form-group mb-3">
                    <div class="flex items-center gap-3">
                        <label class="yamamah-switch" style="margin:0;">
                            <input type="checkbox" id="sCouponEnabled" <?php checked( $cpn_enabled, 'yes' ); ?>>
                            <span class="yamamah-switch-slider"></span>
                        </label>
                        <span style="font-weight:500;">إرفاق كوبون مع التذكير التلقائي</span>
                    </div>
                    <small class="text-xs text-muted" style="display:block;margin-top:0.4rem;">ينشئ كوبون خصم فريد لكل عميل تلقائياً</small>
                </div>
                <div id="couponFields" style="<?php echo $cpn_enabled !== 'yes' ? 'display:none;' : ''; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>نوع الخصم</label>
                            <select class="form-control" id="sCouponType">
                                <option value="percent" <?php selected( $cpn_type, 'percent' ); ?>>نسبة مئوية (%)</option>
                                <option value="fixed_cart" <?php selected( $cpn_type, 'fixed_cart' ); ?>>مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>قيمة الخصم</label>
                            <input type="number" class="form-control" id="sCouponAmount" value="<?php echo esc_attr( $cpn_amount ); ?>" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>صلاحية الكوبون (أيام)</label>
                            <input type="number" class="form-control" id="sCouponExpiry" value="<?php echo esc_attr( $cpn_expiry ); ?>" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label>الحد الأدنى للطلب (اختياري)</label>
                            <input type="number" class="form-control" id="sCouponMin" value="<?php echo esc_attr( $cpn_min ); ?>" min="0" step="1">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-top:1rem;border-top:1px solid var(--border,#e5e7eb);padding-top:1rem;">
            <button type="button" class="btn btn-green" id="saveAbandonedSettings">حفظ الإعدادات</button>
            <span id="settingsMsg" style="font-size:0.85rem;margin-right:0.75rem;"></span>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="grid grid-3 mb-4">
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">عدد السلات المتروكة</p>
            <p class="text-lg" style="font-weight:700;color:var(--red,#ef4444);"><?php echo count( $carts ); ?></p>
        </div>
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">القيمة الإجمالية المفقودة</p>
            <p class="text-lg" style="font-weight:700;color:var(--red,#ef4444);"><?php echo wc_price( $total_value ); ?></p>
        </div>
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">تم إرسال تذكيرات</p>
            <p class="text-lg" style="font-weight:700;"><?php echo $reminded . ' / ' . count( $carts ); ?></p>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="card mb-2" id="bulkBar" style="display:none;padding:0.75rem 1rem;">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <span class="text-sm"><strong id="bulkCount">0</strong> سلة محددة</span>
            <div class="flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-outline" id="bulkEmailBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    إرسال تذكير بالبريد
                </button>
                <button type="button" class="btn btn-sm btn-green" id="bulkCouponBtn">إرسال مع كوبون خصم</button>
                <button type="button" class="btn btn-sm btn-outline" style="color:var(--red);" id="bulkDeleteBtn">حذف المحدد</button>
            </div>
        </div>
    </div>

    <!-- جدول السلات -->
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h3 class="card-title">السلات المتروكة (آخر 200)</h3>
        </div>
        <?php if ( empty( $carts ) ) : ?>
            <div class="text-center p-5 text-muted">
                <p style="font-size:2rem;margin-bottom:0.5rem;">🛒</p>
                <p>لا توجد سلات متروكة حالياً</p>
                <small>السلات التي يمر عليها أكثر من <?php echo $current_minutes; ?> دقيقة بدون إكمال الطلب تظهر هنا</small>
            </div>
        <?php else : ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="selectAllCarts"></th>
                        <th>العميل</th>
                        <th>الجوال</th>
                        <th>المنتجات</th>
                        <th style="text-align:center;">العناصر</th>
                        <th>المبلغ</th>
                        <th>آخر نشاط</th>
                        <th>التذكير</th>
                        <th style="text-align:center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $carts as $cart_row ) :
                        $items = json_decode( $cart_row->cart_contents, true );
                        $items = is_array( $items ) ? $items : array();
                        $product_names = array_column( $items, 'name' );
                        $products_str = implode( '، ', array_slice( $product_names, 0, 2 ) );
                        if ( count( $product_names ) > 2 ) {
                            $products_str .= ' (+' . ( count( $product_names ) - 2 ) . ')';
                        }

                        $time_ago = human_time_diff( strtotime( $cart_row->updated_at ), current_time( 'timestamp' ) );

                        $phone_raw = $cart_row->customer_phone;
                        $wa_phone  = '';
                        if ( $phone_raw ) {
                            $clean = preg_replace( '/[^0-9]/', '', $phone_raw );
                            if ( substr( $clean, 0, 1 ) === '0' ) {
                                $clean = '966' . substr( $clean, 1 );
                            } elseif ( substr( $clean, 0, 3 ) !== '966' && strlen( $clean ) === 9 ) {
                                $clean = '966' . $clean;
                            }
                            $wa_phone = $clean;
                        }
                        $wa_message = urlencode( "مرحباً " . $cart_row->customer_name . " 👋\nلاحظنا أن لديك منتجات في سلة التسوق في " . $store_name . ".\nأكمل طلبك الآن من هنا:\n" . $checkout_url . "\nنسعد بخدمتك!" );

                        $is_registered = $cart_row->user_id > 0;
                        $display_name  = $cart_row->customer_name;
                        if ( empty( trim( $display_name ) ) ) {
                            $display_name = $is_registered ? 'عضو #' . $cart_row->user_id : 'زائر';
                        }
                    ?>
                    <tr data-cart-id="<?php echo esc_attr( $cart_row->id ); ?>" data-email="<?php echo esc_attr( $cart_row->customer_email ); ?>" data-wa-phone="<?php echo esc_attr( $wa_phone ); ?>" data-wa-msg="<?php echo esc_attr( $wa_message ); ?>">
                        <td onclick="event.stopPropagation();"><input type="checkbox" class="cart-cb" value="<?php echo esc_attr( $cart_row->id ); ?>"></td>
                        <td class="cart-row-click" data-id="<?php echo esc_attr( $cart_row->id ); ?>" style="cursor:pointer;">
                            <div class="flex items-center gap-2">
                                <div>
                                    <strong class="text-sm" style="color:var(--green,#10b981);"><?php echo esc_html( $display_name ); ?></strong>
                                    <?php if ( $is_registered ) : ?>
                                        <span class="badge badge-green" style="font-size:0.6rem;padding:1px 5px;margin-right:4px;">عضو</span>
                                    <?php endif; ?>
                                    <?php if ( $cart_row->customer_email ) : ?>
                                        <div class="text-xs text-muted" style="margin-top:2px;"><?php echo esc_html( $cart_row->customer_email ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ( $phone_raw ) : ?>
                                <span class="text-sm" style="direction:ltr;display:inline-block;"><?php echo esc_html( $phone_raw ); ?></span>
                            <?php else : ?>
                                <span class="text-xs text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="cart-row-click" data-id="<?php echo esc_attr( $cart_row->id ); ?>" style="cursor:pointer;">
                            <div class="text-sm" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( implode( '، ', $product_names ) ); ?>">
                                <?php echo esc_html( $products_str ?: '-' ); ?>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <span class="badge badge-gray"><?php echo esc_html( $cart_row->item_count ); ?></span>
                        </td>
                        <td><strong><?php echo wc_price( $cart_row->cart_total ); ?></strong></td>
                        <td><span class="text-sm text-muted">منذ <?php echo esc_html( $time_ago ); ?></span></td>
                        <td>
                            <?php if ( $cart_row->reminder_sent ) : ?>
                                <span class="badge badge-green">تم (<?php echo $cart_row->reminder_sent; ?>)</span>
                            <?php else : ?>
                                <span class="badge badge-gray">لم يُرسل</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;" onclick="event.stopPropagation();">
                            <div class="flex gap-1 justify-center flex-wrap">
                                <?php if ( $cart_row->customer_email ) : ?>
                                <button type="button" class="btn btn-sm btn-outline send-reminder-btn" data-cart-id="<?php echo esc_attr( $cart_row->id ); ?>" title="تذكير بالبريد">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </button>
                                <?php endif; ?>
                                <?php if ( $wa_phone ) : ?>
                                <a href="https://api.whatsapp.com/send?phone=<?php echo esc_attr( $wa_phone ); ?>&text=<?php echo esc_attr( $wa_message ); ?>" target="_blank" class="btn btn-sm btn-outline" style="color:#25d366;border-color:#25d366;" title="واتساب">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline delete-cart-btn" data-cart-id="<?php echo esc_attr( $cart_row->id ); ?>" style="color:var(--red,#ef4444);" title="حذف">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Cart Detail Modal ── -->
<div class="yamamah-modal-overlay" id="cartDetailModal" style="display:none;" onclick="if(event.target===this)jQuery(this).fadeOut(200);">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:640px;">
        <div class="yamamah-modal-header">
            <h3>تفاصيل السلة المتروكة</h3>
            <button type="button" class="yamamah-modal-close" onclick="jQuery('#cartDetailModal').fadeOut(200);">&times;</button>
        </div>
        <div class="yamamah-modal-body" id="cartDetailBody" style="gap:0;">
        </div>
        <div class="yamamah-modal-footer" id="cartDetailFooter">
        </div>
    </div>
</div>

<!-- ── Coupon Modal ── -->
<div class="yamamah-modal-overlay" id="couponModal" style="display:none;" onclick="if(event.target===this)jQuery(this).fadeOut(200);">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:480px;">
        <div class="yamamah-modal-header">
            <h3>إرسال كوبون خصم</h3>
            <button type="button" class="yamamah-modal-close" onclick="jQuery('#couponModal').fadeOut(200);">&times;</button>
        </div>
        <div class="yamamah-modal-body">
            <p class="text-sm text-muted mb-3">سيتم إنشاء كوبون فريد لكل سلة محددة وإرساله بالبريد الإلكتروني</p>
            <div class="form-row">
                <div class="form-group">
                    <label>نوع الخصم</label>
                    <select class="form-control" id="mCouponType">
                        <option value="percent">نسبة مئوية (%)</option>
                        <option value="fixed_cart">مبلغ ثابت</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>قيمة الخصم</label>
                    <input type="number" class="form-control" id="mCouponAmount" value="10" min="1" step="0.5">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>صلاحية (أيام)</label>
                    <input type="number" class="form-control" id="mCouponExpiry" value="3" min="1" max="30">
                </div>
                <div class="form-group">
                    <label>الحد الأدنى للطلب</label>
                    <input type="number" class="form-control" id="mCouponMin" value="0" min="0">
                </div>
            </div>
        </div>
        <div class="yamamah-modal-footer">
            <button type="button" class="btn btn-green" id="sendBulkCouponBtn">إرسال الكوبونات</button>
            <span id="couponModalMsg" style="font-size:0.85rem;margin-right:0.5rem;"></span>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var cartsData = <?php echo wp_json_encode( $carts_json ); ?>;
    var checkoutUrl = <?php echo wp_json_encode( $checkout_url ); ?>;

    /* ── Cart detail modal ── */
    $(document).on('click', '.cart-row-click', function(e) {
        e.stopPropagation();
        var id = $(this).data('id');
        var c = cartsData[id];
        if (!c) return;

        var html = '';

        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem;">';
        html += '<div style="background:var(--accent,#f3f4f6);border-radius:8px;padding:14px;">';
        html += '<h4 style="font-size:0.8rem;color:var(--muted-fg,#6b7280);margin:0 0 8px;">معلومات العميل</h4>';
        html += '<div style="font-size:0.9rem;font-weight:600;">' + (c.name || 'زائر') + '</div>';
        if (c.user_id > 0) {
            html += '<span style="display:inline-block;background:#dcfce7;color:#15803d;font-size:0.65rem;padding:1px 6px;border-radius:4px;margin-top:3px;">عضو مسجل</span>';
        } else {
            html += '<span style="display:inline-block;background:#f3f4f6;color:#6b7280;font-size:0.65rem;padding:1px 6px;border-radius:4px;margin-top:3px;">زائر</span>';
        }
        if (c.email) html += '<div style="font-size:0.8rem;color:var(--muted-fg);margin-top:6px;">📧 ' + c.email + '</div>';
        if (c.phone) html += '<div style="font-size:0.8rem;color:var(--muted-fg);margin-top:3px;direction:ltr;text-align:right;">📱 ' + c.phone + '</div>';
        html += '</div>';

        html += '<div style="background:var(--accent,#f3f4f6);border-radius:8px;padding:14px;">';
        html += '<h4 style="font-size:0.8rem;color:var(--muted-fg,#6b7280);margin:0 0 8px;">معلومات السلة</h4>';
        html += '<div style="font-size:0.85rem;margin-bottom:4px;"><span style="color:var(--muted-fg);">عدد العناصر:</span> <strong>' + c.count + '</strong></div>';
        html += '<div style="font-size:0.85rem;margin-bottom:4px;"><span style="color:var(--muted-fg);">الإجمالي:</span> <strong style="color:var(--red,#ef4444);">' + c.total.toFixed(2) + ' ر.س</strong></div>';
        html += '<div style="font-size:0.85rem;margin-bottom:4px;"><span style="color:var(--muted-fg);">أُنشئت:</span> ' + c.created + '</div>';
        html += '<div style="font-size:0.85rem;"><span style="color:var(--muted-fg);">آخر تحديث:</span> ' + c.updated + '</div>';
        if (c.reminder > 0) {
            html += '<div style="margin-top:6px;"><span style="background:#dcfce7;color:#15803d;font-size:0.75rem;padding:2px 8px;border-radius:4px;">تم إرسال ' + c.reminder + ' تذكير</span></div>';
        }
        html += '</div></div>';

        html += '<h4 style="font-size:0.85rem;font-weight:600;margin:0 0 10px;color:var(--fg);">المنتجات في السلة</h4>';
        html += '<div style="border:1px solid var(--border,#e5e7eb);border-radius:8px;overflow:hidden;">';
        html += '<table style="width:100%;border-collapse:collapse;font-size:0.82rem;">';
        html += '<thead><tr style="background:var(--accent,#f3f4f6);">';
        html += '<th style="padding:8px 10px;text-align:right;font-weight:600;">المنتج</th>';
        html += '<th style="padding:8px 10px;text-align:center;font-weight:600;">الكمية</th>';
        html += '<th style="padding:8px 10px;text-align:center;font-weight:600;">السعر</th>';
        html += '<th style="padding:8px 10px;text-align:left;font-weight:600;">المجموع</th>';
        html += '</tr></thead><tbody>';

        if (c.items && c.items.length) {
            c.items.forEach(function(item) {
                html += '<tr style="border-top:1px solid var(--border,#e5e7eb);">';
                html += '<td style="padding:8px 10px;text-align:right;">';
                html += '<div style="display:flex;align-items:center;gap:8px;">';
                if (item.image) {
                    html += '<img src="' + item.image + '" style="width:36px;height:36px;border-radius:4px;object-fit:cover;flex-shrink:0;">';
                }
                html += '<span>' + (item.name || '-') + '</span>';
                html += '</div></td>';
                html += '<td style="padding:8px 10px;text-align:center;">' + item.qty + '</td>';
                html += '<td style="padding:8px 10px;text-align:center;">' + parseFloat(item.price).toFixed(2) + '</td>';
                html += '<td style="padding:8px 10px;text-align:left;font-weight:600;">' + parseFloat(item.total).toFixed(2) + ' ر.س</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" style="padding:16px;text-align:center;color:var(--muted-fg);">لا توجد بيانات منتجات</td></tr>';
        }
        html += '</tbody></table></div>';

        $('#cartDetailBody').html(html);

        var footerHtml = '';
        if (c.email) {
            footerHtml += '<button type="button" class="btn btn-sm btn-outline" onclick="yamamahSendSingleReminder(' + c.id + ', this);">';
            footerHtml += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> تذكير بالبريد</button>';
        }
        if (c.wa_phone) {
            footerHtml += '<a href="https://api.whatsapp.com/send?phone=' + c.wa_phone + '&text=' + encodeURIComponent("مرحباً " + c.name + " 👋\nأكمل طلبك الآن:\n" + checkoutUrl) + '" target="_blank" class="btn btn-sm btn-outline" style="color:#25d366;border-color:#25d366;">';
            footerHtml += '<svg width="14" height="14" viewBox="0 0 24 24" fill="#25d366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg> واتساب</a>';
        }
        $('#cartDetailFooter').html(footerHtml);

        $('#cartDetailModal').css('display','flex').hide().fadeIn(200);
    });

    window.yamamahSendSingleReminder = function(cartId, btn) {
        var $btn = $(btn);
        $btn.prop('disabled', true).text('جاري الإرسال...');
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_send_cart_reminder',
            nonce: yamamahData.nonce,
            cart_id: cartId
        }, function(res) {
            if (res.success) {
                $btn.text('تم الإرسال ✓').css('color','#16a34a');
                $('tr[data-cart-id="' + cartId + '"] td:eq(7) .badge').attr('class','badge badge-green').text('تم');
            } else {
                $btn.prop('disabled', false).text('تذكير بالبريد');
                alert(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('تذكير بالبريد');
        });
    };

    /* ── Settings panel toggle ── */
    $('#toggleSettingsPanel').on('click', function() {
        $('#settingsPanel').slideToggle(250);
    });
    $('#sAutoEnabled').on('change', function() {
        $('#autoFields').slideToggle(200);
    });
    $('#sCouponEnabled').on('change', function() {
        $('#couponFields').slideToggle(200);
    });

    /* ── Save settings ── */
    $('#saveAbandonedSettings').on('click', function() {
        var $btn = $(this), $msg = $('#settingsMsg');
        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_save_abandoned_settings',
            nonce: yamamahData.nonce,
            abandon_minutes: $('#sAbandonMinutes').val(),
            auto_enabled: $('#sAutoEnabled').is(':checked') ? 'yes' : 'no',
            auto_delay: $('#sAutoDelay').val(),
            auto_max_reminders: $('#sAutoMax').val(),
            coupon_enabled: $('#sCouponEnabled').is(':checked') ? 'yes' : 'no',
            coupon_type: $('#sCouponType').val(),
            coupon_amount: $('#sCouponAmount').val(),
            coupon_expiry: $('#sCouponExpiry').val(),
            coupon_min_amount: $('#sCouponMin').val()
        }, function(res) {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            if (res.success) {
                $msg.css('color','#16a34a').text('تم الحفظ بنجاح');
                setTimeout(function(){ $msg.text(''); }, 3000);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ الإعدادات');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Select all / bulk bar ── */
    function getChecked() { return $('.cart-cb:checked'); }
    function updateBulkBar() {
        var count = getChecked().length;
        if (count > 0) { $('#bulkBar').slideDown(150); $('#bulkCount').text(count); }
        else { $('#bulkBar').slideUp(150); }
        var total = $('.cart-cb').length;
        $('#selectAllCarts').prop('indeterminate', count > 0 && count < total);
        $('#selectAllCarts').prop('checked', count === total && total > 0);
    }
    $('#selectAllCarts').on('change', function() {
        $('.cart-cb').prop('checked', this.checked);
        updateBulkBar();
    });
    $(document).on('change', '.cart-cb', updateBulkBar);

    function getSelectedIds() {
        var ids = [];
        getChecked().each(function() { ids.push($(this).val()); });
        return ids;
    }

    /* ── Bulk email ── */
    $('#bulkEmailBtn').on('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('إرسال تذكير بالبريد لـ ' + ids.length + ' سلة؟')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('جاري الإرسال...');
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_bulk_cart_reminder', nonce: yamamahData.nonce,
            cart_ids: ids, coupon_enabled: 'no'
        }, function(res) {
            $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> إرسال تذكير بالبريد');
            if (res.success) {
                alert('تم إرسال ' + res.data.sent + ' تذكير' + (res.data.failed ? ' (' + res.data.failed + ' فشل)' : ''));
                location.reload();
            } else { alert(res.data || 'خطأ'); }
        }).fail(function() { $btn.prop('disabled', false); alert('خطأ في الاتصال'); });
    });

    /* ── Bulk coupon modal ── */
    $('#bulkCouponBtn').on('click', function() {
        if (!getSelectedIds().length) return;
        $('#couponModal').css('display','flex').hide().fadeIn(200);
    });

    /* ── Send bulk with coupon ── */
    $('#sendBulkCouponBtn').on('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var $btn = $(this), $msg = $('#couponModalMsg');
        $btn.prop('disabled', true).text('جاري الإنشاء والإرسال...');
        $msg.text('');
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_bulk_cart_reminder', nonce: yamamahData.nonce,
            cart_ids: ids, coupon_enabled: 'yes',
            coupon_type: $('#mCouponType').val(), coupon_amount: $('#mCouponAmount').val(),
            coupon_expiry: $('#mCouponExpiry').val(), coupon_min_amount: $('#mCouponMin').val()
        }, function(res) {
            $btn.prop('disabled', false).text('إرسال الكوبونات');
            if (res.success) {
                var msg = 'تم إرسال ' + res.data.sent + ' تذكير مع كوبون';
                if (res.data.failed) msg += ' (' + res.data.failed + ' فشل)';
                if (res.data.coupons && res.data.coupons.length) {
                    msg += '\n\nالكوبونات المُنشأة:\n';
                    res.data.coupons.forEach(function(c) { msg += c.name + ': ' + c.code + '\n'; });
                }
                alert(msg); location.reload();
            } else { $msg.css('color','#ef4444').text(res.data || 'خطأ'); }
        }).fail(function() {
            $btn.prop('disabled', false).text('إرسال الكوبونات');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Bulk delete ── */
    $('#bulkDeleteBtn').on('click', function() {
        var ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('حذف ' + ids.length + ' سلة متروكة؟')) return;
        var done = 0;
        ids.forEach(function(id) {
            $.post(yamamahData.ajax_url, {
                action: 'yamamah_delete_abandoned_cart', nonce: yamamahData.nonce, cart_id: id
            }, function() {
                done++;
                $('tr[data-cart-id="' + id + '"]').fadeOut(200);
                if (done === ids.length) setTimeout(function(){ location.reload(); }, 500);
            });
        });
    });

    /* ── Single email reminder ── */
    $('.send-reminder-btn').on('click', function(e) {
        e.stopPropagation();
        var $btn = $(this), cartId = $btn.data('cart-id');
        $btn.prop('disabled', true);
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_send_cart_reminder', nonce: yamamahData.nonce, cart_id: cartId
        }, function(res) {
            if (res.success) {
                $btn.closest('tr').find('td:eq(7) .badge').attr('class','badge badge-green').text('تم');
                $btn.prop('disabled', false);
            } else { $btn.prop('disabled', false); alert(res.data || 'خطأ'); }
        }).fail(function() { $btn.prop('disabled', false); });
    });

    /* ── Single delete ── */
    $('.delete-cart-btn').on('click', function(e) {
        e.stopPropagation();
        if (!confirm('حذف هذه السلة؟')) return;
        var $btn = $(this), cartId = $btn.data('cart-id'), $row = $btn.closest('tr');
        $.post(yamamahData.ajax_url, {
            action: 'yamamah_delete_abandoned_cart', nonce: yamamahData.nonce, cart_id: cartId
        }, function(res) {
            if (res.success) $row.fadeOut(300, function() { $(this).remove(); });
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
