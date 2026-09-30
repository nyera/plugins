<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'shipping' ) ) {
    $page_content = yamamah_upgrade_banner( 'الشحن' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

$order = $order_id ? wc_get_order( $order_id ) : null;
$back_url = admin_url( 'admin.php?page=yamamah-shipping-detail&order_id=' . $order_id );

ob_start();
?>
<div class="page active" id="page-create-shipment">
    <div class="page-header flex justify-between items-center">
        <div>
            <?php if ( $order ) : ?>
                <h1>إنشاء شحنة — طلب #<?php echo esc_html( $order->get_order_number() ); ?></h1>
                <p>تعبئة بيانات الشحنة وإرسالها</p>
            <?php else : ?>
                <h1>الطلب غير موجود</h1>
            <?php endif; ?>
        </div>
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline">&larr; العودة لتفاصيل الطلب</a>
    </div>

    <?php if ( ! $order ) : ?>
        <div class="card">
            <div class="text-center p-5 text-muted">الطلب غير موجود أو تم حذفه.</div>
        </div>
    <?php else :
        $shipper = array(
            'name' => '', 'phone' => '', 'country' => 'SA', 'city' => '',
            'district' => '', 'address1' => '', 'address2' => '', 'national_address' => '',
        );
        if ( class_exists( 'Yamama_Shipping_Admin' ) ) {
            $shipper = Yamama_Shipping_Admin::get_shipper_defaults();
        } else {
            $saved = get_option( 'yamama_shipping_shipper', array() );
            if ( is_array( $saved ) ) {
                $shipper = wp_parse_args( $saved, $shipper );
            }
        }

        $customer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
        if ( $customer_name === '' ) {
            $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        }

        $customer_phone   = $order->get_billing_phone();
        $customer_email   = $order->get_billing_email();
        $customer_country = $order->get_shipping_country() ?: $order->get_billing_country() ?: 'SA';
        $customer_city    = $order->get_shipping_city() ?: $order->get_billing_city();
        $customer_address1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $customer_address2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();

        $wc_payment_method = (string) $order->get_payment_method();
        $lamha_payment = 'cod';
        if ( class_exists( 'Yamama_Shipping_Hooks' ) && method_exists( 'Yamama_Shipping_Hooks', 'map_wc_payment_method' ) ) {
            $lamha_payment = Yamama_Shipping_Hooks::map_wc_payment_method( $wc_payment_method );
        }

        $order_date = $order->get_date_created();
        $date_str   = $order_date ? $order_date->date( 'Y-m-d H:i:s' ) : current_time( 'Y-m-d H:i:s' );

        $items_data = array();
        foreach ( $order->get_items() as $item ) {
            if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;
            $product = $item->get_product();
            $qty     = max( 1, (int) $item->get_quantity() );
            $weight  = $product ? (float) $product->get_weight() : 0.5;
            if ( $weight <= 0 ) $weight = 0.5;
            $sku    = $product ? (string) $product->get_sku() : '';
            $amount = (float) $item->get_total() / $qty;

            $items_data[] = array(
                'name'     => (string) $item->get_name(),
                'quantity' => $qty,
                'Sku'      => $sku,
                'amount'   => number_format( $amount, 2, '.', '' ),
                'weight'   => number_format( $weight, 2, '.', '' ),
            );
        }
    ?>

    <div id="yamama-shipment-form" data-order-id="<?php echo esc_attr( $order_id ); ?>">

        <!-- بيانات العميل -->
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">بيانات العميل</h3></div>
            <div class="yamama-section">
                <div class="form-row" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label>الاسم <span class="required">*</span></label>
                        <input type="text" class="form-control" name="customer_name" value="<?php echo esc_attr( $customer_name ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الجوال <span class="required">*</span></label>
                        <input type="text" class="form-control" name="customer_phone1" value="<?php echo esc_attr( $customer_phone ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>جوال 2</label>
                        <input type="text" class="form-control" name="customer_phone2" value="" />
                    </div>
                    <div class="form-group">
                        <label>البريد</label>
                        <input type="email" class="form-control" name="customer_email" value="<?php echo esc_attr( $customer_email ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الدولة</label>
                        <input type="text" class="form-control" name="customer_country" value="<?php echo esc_attr( $customer_country ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>المدينة <span class="required">*</span></label>
                        <input type="text" class="form-control" name="customer_city" value="<?php echo esc_attr( $customer_city ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الحي</label>
                        <input type="text" class="form-control" name="customer_district" value="" />
                    </div>
                    <div class="form-group">
                        <label>العنوان 1 <span class="required">*</span></label>
                        <input type="text" class="form-control" name="customer_address1" value="<?php echo esc_attr( $customer_address1 ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العنوان 2</label>
                        <input type="text" class="form-control" name="customer_address2" value="<?php echo esc_attr( $customer_address2 ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العنوان الوطني</label>
                        <input type="text" class="form-control" name="customer_national_address" value="" />
                    </div>
                </div>
            </div>
        </div>

        <!-- بيانات المرسل -->
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">بيانات المرسل</h3></div>
            <div class="yamama-section">
                <div class="form-row" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label>الاسم <span class="required">*</span></label>
                        <input type="text" class="form-control" name="shipper_name" value="<?php echo esc_attr( $shipper['name'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الجوال <span class="required">*</span></label>
                        <input type="text" class="form-control" name="shipper_phone" value="<?php echo esc_attr( $shipper['phone'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الدولة</label>
                        <input type="text" class="form-control" name="shipper_country" value="<?php echo esc_attr( $shipper['country'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>المدينة <span class="required">*</span></label>
                        <input type="text" class="form-control" name="shipper_city" value="<?php echo esc_attr( $shipper['city'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الحي</label>
                        <input type="text" class="form-control" name="shipper_district" value="<?php echo esc_attr( $shipper['district'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العنوان 1 <span class="required">*</span></label>
                        <input type="text" class="form-control" name="shipper_address1" value="<?php echo esc_attr( $shipper['address1'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العنوان 2</label>
                        <input type="text" class="form-control" name="shipper_address2" value="<?php echo esc_attr( $shipper['address2'] ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العنوان الوطني</label>
                        <input type="text" class="form-control" name="shipper_national_address" value="<?php echo esc_attr( $shipper['national_address'] ); ?>" />
                    </div>
                </div>
            </div>
        </div>

        <!-- تفاصيل الطلب -->
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">تفاصيل الطلب</h3></div>
            <div class="yamama-section">
                <div class="form-row" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                    <div class="form-group">
                        <label>الإجمالي</label>
                        <input type="number" step="0.01" class="form-control" name="total" value="<?php echo esc_attr( $order->get_total() ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>المجموع الفرعي</label>
                        <input type="number" step="0.01" class="form-control" name="sub_total" value="<?php echo esc_attr( $order->get_subtotal() ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>الخصم</label>
                        <input type="number" step="0.01" class="form-control" name="discount" value="<?php echo esc_attr( $order->get_discount_total() ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>تكلفة الشحن</label>
                        <input type="number" step="0.01" class="form-control" name="shopping_cost" value="<?php echo esc_attr( $order->get_shipping_total() ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>طريقة الدفع</label>
                        <select class="form-control" name="payment_method">
                            <option value="cod" <?php selected( $lamha_payment, 'cod' ); ?>>COD</option>
                            <option value="paid" <?php selected( $lamha_payment, 'paid' ); ?>>Paid</option>
                            <option value="bank" <?php selected( $lamha_payment, 'bank' ); ?>>Bank</option>
                            <option value="mada" <?php selected( $lamha_payment, 'mada' ); ?>>Mada</option>
                            <option value="credit_card" <?php selected( $lamha_payment, 'credit_card' ); ?>>Credit Card</option>
                            <option value="tap" <?php selected( $lamha_payment, 'tap' ); ?>>Tap</option>
                            <option value="tamara_installment" <?php selected( $lamha_payment, 'tamara_installment' ); ?>>Tamara</option>
                            <option value="stripe" <?php selected( $lamha_payment, 'stripe' ); ?>>Stripe</option>
                            <option value="tabby" <?php selected( $lamha_payment, 'tabby' ); ?>>Tabby</option>
                            <option value="stc_pay" <?php selected( $lamha_payment, 'stc_pay' ); ?>>STC Pay</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>التاريخ</label>
                        <input type="text" class="form-control" name="date" value="<?php echo esc_attr( $date_str ); ?>" />
                    </div>
                    <div class="form-group">
                        <label>العملة</label>
                        <input type="text" class="form-control" name="currency" value="<?php echo esc_attr( $order->get_currency() ?: 'SAR' ); ?>" />
                    </div>
                </div>
                <input type="hidden" name="reference_id" value="<?php echo esc_attr( $order->get_order_number() ); ?>" />
                <input type="hidden" name="wc_order_id" value="<?php echo esc_attr( $order_id ); ?>" />
            </div>
        </div>

        <!-- المنتجات -->
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">المنتجات</h3></div>
            <div class="table-wrapper">
                <table class="yamama-items-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>SKU</th>
                            <th>السعر</th>
                            <th>الوزن (كجم)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $items_data as $i => $item ) : ?>
                        <tr>
                            <td><input type="text" class="form-control" name="items[<?php echo $i; ?>][name]" value="<?php echo esc_attr( $item['name'] ); ?>" /></td>
                            <td><input type="number" class="form-control" name="items[<?php echo $i; ?>][quantity]" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" /></td>
                            <td><input type="text" class="form-control" name="items[<?php echo $i; ?>][Sku]" value="<?php echo esc_attr( $item['Sku'] ); ?>" /></td>
                            <td><input type="text" class="form-control" name="items[<?php echo $i; ?>][amount]" value="<?php echo esc_attr( $item['amount'] ); ?>" /></td>
                            <td><input type="text" class="form-control" name="items[<?php echo $i; ?>][weight]" value="<?php echo esc_attr( $item['weight'] ); ?>" /></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- خيارات الشحن -->
        <div class="card mb-4">
            <div class="card-header"><h3 class="card-title">خيارات الشحن</h3></div>
            <div class="yamama-section">
                <input type="hidden" name="carrier_id" id="yamama-carrier-input" value="" />
                <div id="yamama-carriers-container" class="yamama-carriers-grid">
                    <div class="yamama-carriers-loading">
                        <span class="spinner is-active" style="float:none;margin:0 0 0 8px;"></span>
                        جاري تحميل شركات الشحن...
                    </div>
                </div>
                <div id="yamama-carrier-warning" class="yamama-carrier-warning notice notice-warning inline" style="display:none;margin:12px 0;">
                    <p></p>
                </div>
                <div class="form-row" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); margin-top:16px;">
                    <div class="form-group">
                        <label>عدد الطرود</label>
                        <input type="number" class="form-control" name="parcels" value="1" min="1" />
                    </div>
                </div>
            </div>
        </div>

        <!-- تكلفة الشحن -->
        <div class="card mb-4" id="yamama-quote-section" style="display:none;">
            <div class="card-header"><h3 class="card-title">تكلفة الشحن</h3></div>
            <div class="shipment-quote-display">
                التكلفة: <strong id="yamama-quote-cost">-</strong> SAR
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div class="card mb-4">
            <div class="shipment-actions-bar">
                <button type="button" id="yamama-pay-ship" class="btn btn-green" style="display:none;">الدفع وإنشاء الشحنة</button>
                <span id="yamama-spinner" class="spinner" style="float:none;"></span>
            </div>
        </div>

        <!-- نتائج -->
        <div id="yamama-result" style="display:none;"></div>

    </div>

    <?php endif; ?>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
