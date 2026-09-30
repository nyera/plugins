<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$order = $order_id ? wc_get_order( $order_id ) : null;
$back_url = admin_url( 'admin.php?page=yamamah-orders' );

ob_start();
?>
<div class="page active" id="page-order-detail">
    <div class="page-header flex justify-between items-center">
        <div>
            <?php if ( $order ) : ?>
                <h1>طلب #<?php echo esc_html( $order->get_order_number() ); ?></h1>
                <p>تفاصيل الطلب وبيانات العميل</p>
            <?php else : ?>
                <h1>الطلب غير موجود</h1>
            <?php endif; ?>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline">&larr; العودة للطلبات</a>
            <?php if ( $order ) : ?>
                <button type="button" class="btn btn-outline" onclick="window.print();">
                    <span data-icon="printer"></span> طباعة
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! $order ) : ?>
        <div class="card">
            <div class="text-center p-5 text-muted">الطلب غير موجود أو تم حذفه.</div>
        </div>
    <?php else :
        $wc_status  = $order->get_status();
        $_yd_stype  = function_exists( 'yamamah_get_store_type' ) ? yamamah_get_store_type() : '';
        $_yd_is_rental = in_array( $_yd_stype, array( 'rental', 'booking' ), true );
        $status_map = array(
            'processing' => array( 'label' => 'قيد المعالجة', 'class' => 'badge-yellow' ),
            'completed'  => array( 'label' => 'مكتمل',       'class' => 'badge-green' ),
            'on-hold'    => array( 'label' => 'معلّق',        'class' => 'badge-amber' ),
            'pending'    => array( 'label' => 'بانتظار الدفع','class' => 'badge-yellow' ),
            'cancelled'  => array( 'label' => 'ملغي',         'class' => 'badge-red' ),
            'refunded'   => array( 'label' => 'مسترد',        'class' => 'badge-purple' ),
            'failed'     => array( 'label' => 'فاشل',         'class' => 'badge-red' ),
            'shipped'    => array( 'label' => 'قيد التوصيل',  'class' => 'badge-blue' ),
        );
        $wc_cfg = $status_map[ $wc_status ] ?? array( 'label' => $wc_status, 'class' => 'badge-gray' );

        $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        if ( $customer_name === '' ) $customer_name = 'زائر';
        $customer_email = $order->get_billing_email();
        $customer_phone = $order->get_billing_phone();

        $order_date = $order->get_date_created();
        $date_str   = $order_date ? $order_date->format( 'Y-m-d H:i' ) : '-';

        $shipping_address = $order->get_formatted_shipping_address();
        $billing_address  = $order->get_formatted_billing_address();

        $refunds     = $order->get_refunds();
        $total_refunded = $order->get_total_refunded();
    ?>

    <!-- Status + Quick Info -->
    <div class="grid grid-4 mb-4">
        <div class="card text-center yamamah-status-card">
            <p class="text-xs text-muted mb-1">الحالة</p>
            <?php
            $all_statuses = array(
                'pending'    => array( 'label' => 'بانتظار الدفع', 'class' => 'badge-yellow' ),
                'processing' => array( 'label' => 'قيد المعالجة',  'class' => 'badge-yellow' ),
                'on-hold'    => array( 'label' => 'معلّق',         'class' => 'badge-amber' ),
                'shipped'    => array( 'label' => 'قيد التوصيل',   'class' => 'badge-blue' ),
                'completed'  => array( 'label' => 'مكتمل',        'class' => 'badge-green' ),
                'cancelled'  => array( 'label' => 'ملغي',          'class' => 'badge-red' ),
                'refunded'   => array( 'label' => 'مسترد',         'class' => 'badge-purple' ),
                'failed'     => array( 'label' => 'فاشل',          'class' => 'badge-red' ),
            );
            ?>
            <div class="yamamah-status-dropdown" id="yamamahStatusDropdown">
                <button type="button" class="yamamah-status-toggle" id="yamamahStatusToggle">
                    <span class="badge <?php echo esc_attr( $wc_cfg['class'] ); ?>" style="font-size:0.85rem;padding:0.35rem 0.75rem;">
                        <?php echo esc_html( $wc_cfg['label'] ); ?>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="yamamah-status-arrow"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="yamamah-status-list" id="yamamahStatusList">
                    <?php foreach ( $all_statuses as $key => $cfg ) : ?>
                        <button type="button" class="yamamah-status-option <?php echo $wc_status === $key ? 'current' : ''; ?>"
                                data-status="<?php echo esc_attr( $key ); ?>"
                                data-order="<?php echo esc_attr( $order_id ); ?>">
                            <span class="badge <?php echo esc_attr( $cfg['class'] ); ?>" style="font-size:0.8rem;padding:0.25rem 0.5rem;">
                                <?php echo esc_html( $cfg['label'] ); ?>
                            </span>
                            <?php if ( $wc_status === $key ) : ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <span id="yamamahStatusMsg" style="font-size:0.75rem;display:block;margin-top:0.35rem;min-height:1rem;"></span>
        </div>
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">الإجمالي</p>
            <p class="text-lg" style="font-weight:600;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
        </div>
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">طريقة الدفع</p>
            <p class="text-sm"><?php echo esc_html( $order->get_payment_method_title() ?: '-' ); ?></p>
        </div>
        <div class="card text-center">
            <p class="text-xs text-muted mb-1">التاريخ</p>
            <p class="text-sm font-mono"><?php echo esc_html( $date_str ); ?></p>
        </div>
    </div>

    <div class="grid grid-2 mb-4">
        <!-- بيانات العميل -->
        <div class="card">
            <div class="card-header flex justify-between items-center">
                <h3 class="card-title">بيانات العميل</h3>
                <button type="button" class="btn btn-sm btn-outline" id="editBillingBtn">تعديل</button>
            </div>
            <div id="billingView">
                <table style="width:100%;font-size:0.875rem;">
                    <tr>
                        <td class="text-muted" style="padding:0.5rem 0;width:100px;">الاسم</td>
                        <td style="padding:0.5rem 0;"><?php echo esc_html( $customer_name ); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:0.5rem 0;">البريد</td>
                        <td style="padding:0.5rem 0;">
                            <a href="mailto:<?php echo esc_attr( $customer_email ); ?>" style="color:var(--green);">
                                <?php echo esc_html( $customer_email ); ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="padding:0.5rem 0;">الجوال</td>
                        <td style="padding:0.5rem 0;" class="font-mono"><?php echo esc_html( $customer_phone ); ?></td>
                    </tr>
                </table>
            </div>
            <div id="billingEdit" style="display:none;">
                <div class="form-row" style="gap:0.5rem;">
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">الاسم الأول</label>
                        <input type="text" class="form-control" name="billing_first_name" value="<?php echo esc_attr( $order->get_billing_first_name() ); ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">الاسم الأخير</label>
                        <input type="text" class="form-control" name="billing_last_name" value="<?php echo esc_attr( $order->get_billing_last_name() ); ?>">
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label class="text-xs">البريد الإلكتروني</label>
                    <input type="email" class="form-control ltr" name="billing_email" value="<?php echo esc_attr( $customer_email ); ?>">
                </div>
                <div class="form-group mt-2">
                    <label class="text-xs">رقم الجوال</label>
                    <input type="text" class="form-control ltr" name="billing_phone" value="<?php echo esc_attr( $customer_phone ); ?>">
                </div>
                <div class="flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-green" id="saveBillingBtn">حفظ</button>
                    <button type="button" class="btn btn-sm btn-outline" id="cancelBillingBtn">إلغاء</button>
                    <span id="billingMsg" style="font-size:0.8rem;"></span>
                </div>
            </div>
        </div>

        <?php if ( $_yd_is_rental ) :
            $_r_start = $order->get_meta( '_rental_start_date' ) ?: $order->get_meta( '_drivebro_start' );
            $_r_end   = $order->get_meta( '_rental_end_date' )   ?: $order->get_meta( '_drivebro_end' );
            $_r_dep   = $order->get_meta( '_drivebro_deposit_amount' );
            $_r_rem   = $order->get_meta( '_drivebro_remaining_amount' );
            $_r_ins   = $order->get_meta( '_drivebro_insurance' );
            $_r_full  = $order->get_meta( '_drivebro_full_booking_amount' );
            $_r_dep_only = $order->get_meta( '_drivebro_paid_deposit_only' ) === 'yes';
        ?>
        <!-- تفاصيل التأجير -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">📅 تفاصيل التأجير</h3></div>
            <table style="width:100%;font-size:0.875rem;">
                <?php if ( $_r_start ) : ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;width:130px;">تاريخ الاستلام</td>
                    <td style="padding:0.5rem 0;font-family:monospace;direction:ltr;text-align:right;"><?php echo esc_html( date_i18n( 'Y-m-d  H:i', strtotime( $_r_start ) ) ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $_r_end ) : ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">تاريخ التسليم</td>
                    <td style="padding:0.5rem 0;font-family:monospace;direction:ltr;text-align:right;"><?php echo esc_html( date_i18n( 'Y-m-d  H:i', strtotime( $_r_end ) ) ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $_r_start && $_r_end ) :
                    $_diff = abs( strtotime( $_r_end ) - strtotime( $_r_start ) );
                    $_days = floor( $_diff / 86400 );
                    $_hrs  = floor( ( $_diff % 86400 ) / 3600 );
                ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">المدة</td>
                    <td style="padding:0.5rem 0;">
                        <?php
                        if ( $_days > 0 ) echo esc_html( $_days ) . ' يوم ';
                        if ( $_hrs > 0 ) echo esc_html( $_hrs ) . ' ساعة';
                        if ( $_days === 0 && $_hrs === 0 ) echo 'أقل من ساعة';
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $_r_ins && (float) $_r_ins > 0 ) : ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">التأمين</td>
                    <td style="padding:0.5rem 0;"><?php echo wc_price( $_r_ins ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( $_r_dep && (float) $_r_dep > 0 ) : ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">العربون</td>
                    <td style="padding:0.5rem 0;">
                        <?php echo wc_price( $_r_dep ); ?>
                        <?php if ( $_r_dep_only ) : ?>
                            <span class="badge badge-yellow" style="font-size:0.7rem;padding:0.15rem 0.4rem;margin-right:0.35rem;">دفع عربون فقط</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ( $_r_dep_only && $_r_rem && (float) $_r_rem > 0 ) : ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">المتبقي عند الاستلام</td>
                    <td style="padding:0.5rem 0;font-weight:600;color:#ef4444;"><?php echo wc_price( $_r_rem ); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ( ! $_r_start && ! $_r_end ) : ?>
                <tr>
                    <td colspan="2" class="text-muted" style="padding:0.5rem 0;">لا توجد تواريخ تأجير مسجلة</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php else : ?>
        <!-- العناوين -->
        <div class="card">
            <div class="card-header flex justify-between items-center">
                <h3 class="card-title">العنوان</h3>
                <button type="button" class="btn btn-sm btn-outline" id="editAddressBtn">تعديل</button>
            </div>
            <div id="addressView">
                <div style="font-size:0.875rem;line-height:1.8;">
                    <?php if ( $shipping_address ) : ?>
                        <p class="text-xs text-muted" style="margin:0 0 0.25rem;">عنوان الشحن:</p>
                        <?php echo wp_kses_post( $shipping_address ); ?>
                    <?php elseif ( $billing_address ) : ?>
                        <?php echo wp_kses_post( $billing_address ); ?>
                    <?php else : ?>
                        <p class="text-muted">لا يوجد عنوان</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="addressEdit" style="display:none;">
                <div class="form-group">
                    <label class="text-xs">العنوان (سطر 1)</label>
                    <input type="text" class="form-control" name="shipping_address_1" value="<?php echo esc_attr( $order->get_shipping_address_1() ?: $order->get_billing_address_1() ); ?>">
                </div>
                <div class="form-group mt-2">
                    <label class="text-xs">العنوان (سطر 2)</label>
                    <input type="text" class="form-control" name="shipping_address_2" value="<?php echo esc_attr( $order->get_shipping_address_2() ?: $order->get_billing_address_2() ); ?>">
                </div>
                <div class="form-row mt-2" style="gap:0.5rem;">
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">المدينة</label>
                        <input type="text" class="form-control" name="shipping_city" value="<?php echo esc_attr( $order->get_shipping_city() ?: $order->get_billing_city() ); ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">المنطقة/الولاية</label>
                        <input type="text" class="form-control" name="shipping_state" value="<?php echo esc_attr( $order->get_shipping_state() ?: $order->get_billing_state() ); ?>">
                    </div>
                </div>
                <div class="form-row mt-2" style="gap:0.5rem;">
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">الرمز البريدي</label>
                        <input type="text" class="form-control ltr" name="shipping_postcode" value="<?php echo esc_attr( $order->get_shipping_postcode() ?: $order->get_billing_postcode() ); ?>">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="text-xs">الدولة</label>
                        <input type="text" class="form-control" name="shipping_country" value="<?php echo esc_attr( $order->get_shipping_country() ?: $order->get_billing_country() ); ?>">
                    </div>
                </div>
                <div class="flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-green" id="saveAddressBtn">حفظ</button>
                    <button type="button" class="btn btn-sm btn-outline" id="cancelAddressBtn">إلغاء</button>
                    <span id="addressMsg" style="font-size:0.8rem;"></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title"><?php echo esc_html( $labels['order_section'] ); ?></h3></div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html( $labels['item_singular'] ); ?></th>
                        <th style="text-align:center;">الكمية</th>
                        <th>السعر</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $order->get_items() as $item ) :
                        $qty = $item->get_quantity();
                        $total = $item->get_total();
                        $unit_price = $qty > 0 ? $total / $qty : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $item->get_name() ); ?></td>
                        <td style="text-align:center;"><?php echo esc_html( $qty ); ?></td>
                        <td><?php echo wc_price( $unit_price ); ?></td>
                        <td><?php echo wc_price( $total ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;">المجموع الفرعي</td>
                        <td><?php echo wc_price( $order->get_subtotal() ); ?></td>
                    </tr>
                    <?php if ( (float) $order->get_discount_total() > 0 ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;">الخصم</td>
                        <td style="color:#ef4444;">-<?php echo wc_price( $order->get_discount_total() ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( ! $_yd_is_rental && (float) $order->get_shipping_total() > 0 ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;">الشحن</td>
                        <td><?php echo wc_price( $order->get_shipping_total() ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ( $order->get_fees() as $_fee ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;"><?php echo esc_html( $_fee->get_name() ); ?></td>
                        <td><?php echo wc_price( $_fee->get_total() ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ( (float) $order->get_total_tax() > 0 ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;">الضريبة</td>
                        <td><?php echo wc_price( $order->get_total_tax() ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $total_refunded > 0 ) : ?>
                    <tr>
                        <td colspan="3" style="text-align:left;font-weight:500;color:#ef4444;">المسترد</td>
                        <td style="color:#ef4444;">-<?php echo wc_price( $total_refunded ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="border-top:2px solid var(--border);">
                        <td colspan="3" style="text-align:left;font-weight:700;font-size:1rem;">الإجمالي</td>
                        <td style="font-weight:700;font-size:1rem;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ملاحظات الطلب -->
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">ملاحظات الطلب</h3></div>
        <div style="padding-bottom:0.75rem;border-bottom:1px solid var(--border);margin-bottom:0.75rem;">
            <div class="flex gap-2">
                <textarea class="form-control" id="newOrderNote" rows="2" placeholder="اكتب ملاحظة جديدة..." style="flex:1;resize:vertical;"></textarea>
            </div>
            <div class="flex gap-2 items-center mt-2">
                <button type="button" class="btn btn-sm btn-green" id="addNoteBtn">إضافة ملاحظة</button>
                <label style="font-size:0.8rem;cursor:pointer;display:flex;align-items:center;gap:0.35rem;">
                    <input type="checkbox" id="noteToCustomer" value="1"> إرسال للعميل
                </label>
                <span id="noteMsg" style="font-size:0.8rem;"></span>
            </div>
        </div>
        <?php
        $notes = wc_get_order_notes( array( 'order_id' => $order_id, 'type' => 'any' ) );
        if ( ! empty( $notes ) ) : ?>
            <div class="space-y-2" id="orderNotesList" style="max-height:300px;overflow-y:auto;padding:0.25rem 0;">
                <?php foreach ( $notes as $note ) : ?>
                    <div style="padding:0.5rem 0.75rem;border-radius:0.5rem;background:var(--accent);font-size:0.8125rem;">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs text-muted"><?php echo esc_html( $note->added_by ); ?></span>
                            <span class="text-xs text-muted"><?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $note->date_created ) ) ); ?></span>
                        </div>
                        <p style="margin:0;"><?php echo wp_kses_post( $note->content ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="text-muted text-sm" id="noNotesText" style="padding:0.5rem 0;">لا توجد ملاحظات</p>
        <?php endif; ?>
    </div>

    <!-- إجراءات -->
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">إجراءات</h3></div>
        <div class="flex gap-2 flex-wrap items-start">
            <?php if ( ! $_yd_is_rental ) :
                $ship_detail_url = admin_url( 'admin.php?page=yamamah-shipping-detail&order_id=' . $order_id );
            ?>
            <a href="<?php echo esc_url( $ship_detail_url ); ?>" class="btn btn-outline">
                <span data-icon="package"></span> شحن الطلب
            </a>
            <?php endif; ?>
            <?php
            $remaining = (float) $order->get_total() - $total_refunded;
            if ( $remaining > 0 && ! in_array( $wc_status, array( 'refunded', 'cancelled', 'failed' ), true ) ) : ?>
            <button type="button" class="btn btn-outline" id="showRefundBtn" style="color:var(--red,#ef4444);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-left:4px;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                استرداد
            </button>
            <?php endif; ?>
        </div>

        <!-- نموذج الاسترداد -->
        <?php if ( $remaining > 0 && ! in_array( $wc_status, array( 'refunded', 'cancelled', 'failed' ), true ) ) : ?>
        <div id="refundSection" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
            <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 0.75rem;">استرداد المبلغ</h4>
            <div class="form-row" style="gap:0.75rem;">
                <div class="form-group" style="flex:1;max-width:200px;">
                    <label class="text-xs">المبلغ</label>
                    <input type="number" class="form-control ltr" id="refundAmount" step="0.01" min="0.01"
                           max="<?php echo esc_attr( $remaining ); ?>"
                           value="<?php echo esc_attr( $remaining ); ?>">
                    <small class="text-xs text-muted">الحد الأقصى: <?php echo wc_price( $remaining ); ?></small>
                </div>
                <div class="form-group" style="flex:2;">
                    <label class="text-xs">سبب الاسترداد (اختياري)</label>
                    <input type="text" class="form-control" id="refundReason" placeholder="مثال: طلب العميل إلغاء الطلب">
                </div>
            </div>
            <div class="flex gap-2 items-center mt-2">
                <button type="button" class="btn btn-sm" id="confirmRefundBtn" style="background:#ef4444;color:#fff;">تأكيد الاسترداد</button>
                <button type="button" class="btn btn-sm btn-outline" id="cancelRefundBtn">إلغاء</button>
                <span id="refundMsg" style="font-size:0.8rem;"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- كوبون الخصم -->
    <?php if ( ! in_array( $wc_status, array( 'refunded', 'cancelled', 'failed', 'completed' ), true ) ) : ?>
    <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">كوبون الخصم</h3></div>
        <?php
        $applied_coupons = $order->get_coupon_codes();
        if ( ! empty( $applied_coupons ) ) : ?>
            <div class="flex gap-2 flex-wrap mb-3">
                <?php foreach ( $applied_coupons as $code ) : ?>
                    <span class="badge badge-blue" style="font-size:0.8rem;padding:0.3rem 0.6rem;">
                        <?php echo esc_html( $code ); ?>
                        <button type="button" class="remove-coupon-btn" data-coupon="<?php echo esc_attr( $code ); ?>"
                                style="background:none;border:none;color:inherit;cursor:pointer;margin-right:0.25rem;font-weight:700;">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="flex gap-2 items-end">
            <div class="form-group" style="flex:1;max-width:250px;margin:0;">
                <input type="text" class="form-control ltr" id="couponCode" placeholder="أدخل كود الكوبون">
            </div>
            <button type="button" class="btn btn-sm btn-outline" id="applyCouponBtn">تطبيق</button>
            <span id="couponMsg" style="font-size:0.8rem;"></span>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php if ( $order ) : ?>
<script>
jQuery(document).ready(function($) {
    var orderId = <?php echo intval( $order_id ); ?>;

    /* ── Status dropdown ── */
    var $dropdown = $('#yamamahStatusDropdown');
    var $toggle   = $('#yamamahStatusToggle');
    var $list     = $('#yamamahStatusList');
    var $msg      = $('#yamamahStatusMsg');

    $toggle.on('click', function(e) {
        e.stopPropagation();
        $dropdown.toggleClass('open');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#yamamahStatusDropdown').length) {
            $dropdown.removeClass('open');
        }
    });

    $list.on('click', '.yamamah-status-option', function() {
        var $btn     = $(this);
        var status   = $btn.data('status');

        if ($btn.hasClass('current')) {
            $dropdown.removeClass('open');
            return;
        }

        $dropdown.removeClass('open');
        $list.find('.yamamah-status-option').css('opacity','0.5').css('pointer-events','none');
        $msg.text('جاري التحديث...').css('color','var(--muted-fg)');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_update_order_status',
            nonce: yamamahData.nonce,
            order_id: orderId,
            status: status
        }, function(res) {
            if (res.success) {
                // Bug #86: تحديث نص وصنف شارة الحالة فوراً دون انتظار إعادة التحميل.
                if (res.data && res.data.label) {
                    var $toggleBadge = $toggle.find('.badge');
                    $toggleBadge.text(res.data.label);
                    $toggleBadge.attr('class', 'badge ' + (res.data.badge_class || 'badge-gray'));
                }
                $msg.text('تم التحديث').css('color','#16a34a');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                $msg.text(res.data && res.data.message ? res.data.message : 'حدث خطأ').css('color','#ef4444');
                $list.find('.yamamah-status-option').css('opacity','1').css('pointer-events','auto');
            }
        }).fail(function() {
            $msg.text('خطأ في الاتصال').css('color','#ef4444');
            $list.find('.yamamah-status-option').css('opacity','1').css('pointer-events','auto');
        });
    });

    /* ── Edit billing info ── */
    $('#editBillingBtn').on('click', function() {
        $('#billingView').hide();
        $('#billingEdit').show();
        $(this).hide();
    });
    $('#cancelBillingBtn').on('click', function() {
        $('#billingEdit').hide();
        $('#billingView').show();
        $('#editBillingBtn').show();
    });
    $('#saveBillingBtn').on('click', function() {
        var $btn = $(this), $msg = $('#billingMsg');
        var data = {
            action: 'yamamah_update_order_billing',
            nonce: yamamahData.nonce,
            order_id: orderId,
            billing_first_name: $('[name="billing_first_name"]').val(),
            billing_last_name: $('[name="billing_last_name"]').val(),
            billing_email: $('[name="billing_email"]').val(),
            billing_phone: $('[name="billing_phone"]').val()
        };
        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');
        $.post(yamamahData.ajax_url, data, function(res) {
            $btn.prop('disabled', false).text('حفظ');
            if (res.success) {
                $msg.css('color','#16a34a').text('تم الحفظ');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Edit address ── */
    $('#editAddressBtn').on('click', function() {
        $('#addressView').hide();
        $('#addressEdit').show();
        $(this).hide();
    });
    $('#cancelAddressBtn').on('click', function() {
        $('#addressEdit').hide();
        $('#addressView').show();
        $('#editAddressBtn').show();
    });
    $('#saveAddressBtn').on('click', function() {
        var $btn = $(this), $msg = $('#addressMsg');
        var data = {
            action: 'yamamah_update_order_address',
            nonce: yamamahData.nonce,
            order_id: orderId,
            address_1: $('[name="shipping_address_1"]').val(),
            address_2: $('[name="shipping_address_2"]').val(),
            city: $('[name="shipping_city"]').val(),
            state: $('[name="shipping_state"]').val(),
            postcode: $('[name="shipping_postcode"]').val(),
            country: $('[name="shipping_country"]').val()
        };
        $btn.prop('disabled', true).text('جاري الحفظ...');
        $msg.text('');
        $.post(yamamahData.ajax_url, data, function(res) {
            $btn.prop('disabled', false).text('حفظ');
            if (res.success) {
                $msg.css('color','#16a34a').text('تم الحفظ');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('حفظ');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Add order note ── */
    $('#addNoteBtn').on('click', function() {
        var content = $('#newOrderNote').val().trim();
        if (!content) return;
        var $btn = $(this), $msg = $('#noteMsg');
        var toCustomer = $('#noteToCustomer').is(':checked') ? '1' : '0';

        $btn.prop('disabled', true).text('جاري الإضافة...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_add_order_note',
            nonce: yamamahData.nonce,
            order_id: orderId,
            note: content,
            customer_note: toCustomer
        }, function(res) {
            $btn.prop('disabled', false).text('إضافة ملاحظة');
            if (res.success) {
                $msg.css('color','#16a34a').text('تمت الإضافة');
                $('#newOrderNote').val('');
                $('#noteToCustomer').prop('checked', false);
                var noteHtml = '<div style="padding:0.5rem 0.75rem;border-radius:0.5rem;background:var(--accent);font-size:0.8125rem;">'
                    + '<div class="flex justify-between items-center mb-1">'
                    + '<span class="text-xs text-muted">' + res.data.added_by + '</span>'
                    + '<span class="text-xs text-muted">' + res.data.date + '</span>'
                    + '</div>'
                    + '<p style="margin:0;">' + $('<span>').text(content).html() + '</p></div>';
                $('#noNotesText').remove();
                if ($('#orderNotesList').length) {
                    $('#orderNotesList').prepend(noteHtml);
                } else {
                    $('<div class="space-y-2" id="orderNotesList" style="max-height:300px;overflow-y:auto;padding:0.25rem 0;"></div>')
                        .html(noteHtml)
                        .insertAfter($('#addNoteBtn').closest('div').parent().find('[style*="border-bottom"]'));
                }
                setTimeout(function(){ $msg.text(''); }, 2000);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'خطأ');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('إضافة ملاحظة');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Refund ── */
    $('#showRefundBtn').on('click', function() {
        $('#refundSection').slideToggle(200);
    });
    $('#cancelRefundBtn').on('click', function() {
        $('#refundSection').slideUp(200);
    });
    $('#confirmRefundBtn').on('click', function() {
        var amount = parseFloat($('#refundAmount').val());
        var reason = $('#refundReason').val().trim();
        var $btn = $(this), $msg = $('#refundMsg');

        if (!amount || amount <= 0) {
            $msg.css('color','#ef4444').text('أدخل مبلغاً صحيحاً');
            return;
        }

        if (!confirm('هل أنت متأكد من استرداد ' + amount + '؟')) return;

        $btn.prop('disabled', true).text('جاري المعالجة...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_refund_order',
            nonce: yamamahData.nonce,
            order_id: orderId,
            amount: amount,
            reason: reason
        }, function(res) {
            $btn.prop('disabled', false).text('تأكيد الاسترداد');
            if (res.success) {
                $msg.css('color','#16a34a').text('تم الاسترداد بنجاح');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'فشل الاسترداد');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('تأكيد الاسترداد');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Coupon ── */
    $('#applyCouponBtn').on('click', function() {
        var code = $('#couponCode').val().trim();
        if (!code) return;
        var $btn = $(this), $msg = $('#couponMsg');
        $btn.prop('disabled', true).text('جاري التطبيق...');
        $msg.text('');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_apply_order_coupon',
            nonce: yamamahData.nonce,
            order_id: orderId,
            coupon_code: code
        }, function(res) {
            $btn.prop('disabled', false).text('تطبيق');
            if (res.success) {
                $msg.css('color','#16a34a').text('تم تطبيق الكوبون');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                $msg.css('color','#ef4444').text(res.data || 'كوبون غير صالح');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('تطبيق');
            $msg.css('color','#ef4444').text('خطأ في الاتصال');
        });
    });

    /* ── Remove coupon ── */
    $(document).on('click', '.remove-coupon-btn', function() {
        var code = $(this).data('coupon');
        if (!confirm('إزالة الكوبون "' + code + '"؟')) return;
        var $badge = $(this).closest('.badge');

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_remove_order_coupon',
            nonce: yamamahData.nonce,
            order_id: orderId,
            coupon_code: code
        }, function(res) {
            if (res.success) {
                $badge.fadeOut(200, function() { location.reload(); });
            }
        });
    });
});
</script>
<?php endif; ?>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
