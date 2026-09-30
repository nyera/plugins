<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$order = $order_id ? wc_get_order( $order_id ) : null;

$back_url = admin_url( 'admin.php?page=yamamah-shipping-orders' );

ob_start();
?>
<div class="page active" id="page-shipping-detail">
    <div class="page-header flex justify-between items-center">
        <div>
            <?php if ( $order ) : ?>
                <h1>طلب #<?php echo esc_html( $order->get_order_number() ); ?></h1>
                <p>تفاصيل الشحنة والطلب</p>
            <?php else : ?>
                <h1>الطلب غير موجود</h1>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <?php if ( $order ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-create-shipment&order_id=' . $order_id ) ); ?>" class="btn btn-green">إنشاء شحنة</a>
            <?php endif; ?>
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline">&larr; العودة لطلبات الشحن</a>
        </div>
    </div>

    <?php if ( isset( $_GET['yamama_success'] ) && $_GET['yamama_success'] === '1' ) : ?>
    <div class="card mb-4" style="background:var(--green-light, #ecfdf5);border-right:4px solid var(--green, #10b981);padding:16px 20px;">
        <p style="margin:0;color:var(--green-dark, #065f46);font-weight:600;">تم الدفع وإنشاء الشحنة بنجاح!</p>
    </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['yamama_error'] ) ) :
        $error_messages = array(
            'missing_params' => 'بيانات الدفع غير مكتملة.',
            'order_not_found' => 'الطلب غير موجود.',
            'payment_failed' => 'فشل التحقق من الدفع.',
            'no_pending_data' => 'لم يتم العثور على بيانات الشحنة المعلقة.',
            'create_failed' => 'فشل إنشاء الشحنة بعد الدفع. راجع ملاحظات الطلب.',
        );
        $err_key = sanitize_text_field( $_GET['yamama_error'] );
        $err_msg = $error_messages[ $err_key ] ?? 'حدث خطأ غير معروف.';
    ?>
    <div class="card mb-4" style="background:#fef2f2;border-right:4px solid #ef4444;padding:16px 20px;">
        <p style="margin:0;color:#991b1b;font-weight:600;"><?php echo esc_html( $err_msg ); ?></p>
    </div>
    <?php endif; ?>

    <?php if ( ! $order ) : ?>
        <div class="card">
            <div class="text-center p-5 text-muted">الطلب غير موجود أو تم حذفه.</div>
        </div>
    <?php else :
        $lamha_order_id   = (string) $order->get_meta( '_yamama_lamha_order_id', true );
        $is_shipped       = ( $lamha_order_id !== '' );
        $tracking_number  = (string) $order->get_meta( '_yamama_tracking_number', true );
        $tracking_link    = (string) $order->get_meta( '_yamama_tracking_link', true );
        $shipment_status  = (string) $order->get_meta( '_yamama_shipment_status', true );
        $status_name_raw  = (string) $order->get_meta( '_yamama_shipment_status_name', true );
        $shipping_cost    = (string) $order->get_meta( '_yamama_shipping_cost', true );
        $label_url        = (string) $order->get_meta( '_yamama_label_url', true );

        $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        if ( $customer_name === '' ) $customer_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
        if ( $customer_name === '' ) $customer_name = 'زائر';

        $wc_status = $order->get_status();
        $status_map = array(
            'processing' => array( 'label' => 'قيد التجهيز', 'class' => 'badge-blue' ),
            'completed'  => array( 'label' => 'مكتمل',       'class' => 'badge-green' ),
            'on-hold'    => array( 'label' => 'معلّق',        'class' => 'badge-yellow' ),
            'pending'    => array( 'label' => 'بانتظار الدفع','class' => 'badge-yellow' ),
            'cancelled'  => array( 'label' => 'ملغي',         'class' => 'badge-red' ),
            'refunded'   => array( 'label' => 'مسترد',        'class' => 'badge-red' ),
            'failed'     => array( 'label' => 'فاشل',         'class' => 'badge-red' ),
        );
        $wc_cfg = $status_map[ $wc_status ] ?? array( 'label' => $wc_status, 'class' => 'badge-gray' );

        $ship_status_map = array(
            ''   => 'لم يُشحن',  '0'  => 'جديد',        '1'  => 'معلق',
            '2'  => 'تم التنفيذ', '3'  => 'جاهز للالتقاط','4'  => 'شحنة عكسية',
            '5'  => 'ملغي',       '6'  => 'تم الالتقاط',  '7'  => 'جاري الشحن',
            '8'  => 'تم التوصيل', '9'  => 'فشل التوصيل',  '10' => 'مرتجع',
        );
        $ship_label = $status_name_raw ?: ( $ship_status_map[ $is_shipped ? $shipment_status : '' ] ?? 'غير معروف' );
    ?>

    <div class="grid grid-2 mb-4">
        <!-- ملخص الطلب -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">ملخص الطلب</h3></div>
            <table style="width:100%;font-size:0.875rem;">
                <tr><td class="text-muted" style="padding:0.5rem 0;width:120px;">الحالة</td><td style="padding:0.5rem 0;"><span class="badge <?php echo esc_attr( $wc_cfg['class'] ); ?>"><?php echo esc_html( $wc_cfg['label'] ); ?></span></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">العميل</td><td style="padding:0.5rem 0;"><?php echo esc_html( $customer_name ); ?></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">الجوال</td><td style="padding:0.5rem 0;"><?php echo esc_html( $order->get_billing_phone() ); ?></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">البريد</td><td style="padding:0.5rem 0;"><?php echo esc_html( $order->get_billing_email() ); ?></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">العنوان</td><td style="padding:0.5rem 0;"><?php echo wp_kses_post( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ); ?></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">الإجمالي</td><td style="padding:0.5rem 0;font-weight:600;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">طريقة الدفع</td><td style="padding:0.5rem 0;"><?php echo esc_html( $order->get_payment_method_title() ); ?></td></tr>
            </table>
        </div>

        <!-- حالة الشحنة -->
        <div class="card">
            <div class="card-header"><h3 class="card-title">حالة الشحنة</h3></div>
            <?php if ( $is_shipped ) : ?>
            <table style="width:100%;font-size:0.875rem;">
                <tr><td class="text-muted" style="padding:0.5rem 0;width:120px;">الحالة</td><td style="padding:0.5rem 0;"><span class="badge badge-green"><?php echo esc_html( $ship_label ); ?></span></td></tr>
                <tr><td class="text-muted" style="padding:0.5rem 0;">رقم الشحنة</td><td style="padding:0.5rem 0;"><code><?php echo esc_html( $lamha_order_id ); ?></code></td></tr>
                <?php if ( $tracking_number ) : ?>
                <tr><td class="text-muted" style="padding:0.5rem 0;">رقم التتبع</td><td style="padding:0.5rem 0;"><code><?php echo esc_html( $tracking_number ); ?></code></td></tr>
                <?php endif; ?>
                <?php if ( $tracking_link ) : ?>
                <tr><td class="text-muted" style="padding:0.5rem 0;">رابط التتبع</td><td style="padding:0.5rem 0;"><a href="<?php echo esc_url( $tracking_link ); ?>" target="_blank" class="text-green">تتبع الشحنة &larr;</a></td></tr>
                <?php endif; ?>
                <?php if ( $shipping_cost ) : ?>
                <tr><td class="text-muted" style="padding:0.5rem 0;">تكلفة الشحن</td><td style="padding:0.5rem 0;"><?php echo esc_html( $shipping_cost ); ?> ر.س</td></tr>
                <?php endif; ?>
                <tr>
                    <td class="text-muted" style="padding:0.5rem 0;">البوليصة</td>
                    <td style="padding:0.5rem 0;">
                        <?php if ( $label_url ) : ?>
                            <a href="<?php echo esc_url( $label_url ); ?>" target="_blank" class="btn btn-sm btn-outline">طباعة البوليصة</a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-green" id="yamamah-fetch-label-btn" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                            <?php echo $label_url ? 'تحديث البوليصة' : 'جلب البوليصة'; ?>
                        </button>
                        <span id="yamamah-label-status" style="margin-right:8px;font-size:0.8rem;"></span>
                    </td>
                </tr>
            </table>
            <?php else : ?>
            <div class="text-center p-4">
                <p class="text-muted mb-2">لم يتم شحن هذا الطلب بعد</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-create-shipment&order_id=' . $order_id ) ); ?>" class="btn btn-green">إنشاء شحنة</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- المنتجات -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">المنتجات</h3></div>
        <div class="table-wrapper">
            <table>
                <thead><tr>
                    <th>المنتج</th>
                    <th style="text-align:center">الكمية</th>
                    <th>السعر</th>
                    <th>الإجمالي</th>
                </tr></thead>
                <tbody>
                <?php foreach ( $order->get_items() as $item ) : ?>
                <tr>
                    <td><?php echo esc_html( $item->get_name() ); ?></td>
                    <td style="text-align:center"><?php echo esc_html( $item->get_quantity() ); ?></td>
                    <td><?php echo wc_price( $item->get_total() / max( 1, $item->get_quantity() ) ); ?></td>
                    <td><?php echo wc_price( $item->get_total() ); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php if ( $order && $is_shipped ) : ?>
<script>
jQuery(function($){
    $('#yamamah-fetch-label-btn').on('click', function(){
        var $btn = $(this), $status = $('#yamamah-label-status');
        var orderId = $btn.data('order-id');
        $btn.prop('disabled', true).text('جاري الجلب...');
        $status.html('');

        $.post(ajaxurl, {
            action: 'yamama_fetch_label',
            nonce: '<?php echo esc_js( wp_create_nonce("yamama_shipping_nonce") ); ?>',
            order_id: orderId
        }, function(res){
            $btn.prop('disabled', false);
            if (res.success) {
                if (res.data.pdf_base64) {
                    var blob = b64toBlob(res.data.pdf_base64, 'application/pdf');
                    var url = URL.createObjectURL(blob);
                    window.open(url, '_blank');
                    $btn.text('تحديث البوليصة');
                    $status.html('<span style="color:var(--green);">&#10003; تم جلب البوليصة</span>');
                } else if (res.data.label_url) {
                    window.open(res.data.label_url, '_blank');
                    $btn.text('تحديث البوليصة');
                    $status.html('<span style="color:var(--green);">&#10003; تم جلب البوليصة</span>');
                    setTimeout(function(){ location.reload(); }, 1500);
                }
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'فشل جلب البوليصة';
                $status.html('<span style="color:var(--red);">&#10007; ' + msg + '</span>');
                $btn.text('جلب البوليصة');
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('جلب البوليصة');
            $status.html('<span style="color:var(--red);">&#10007; خطأ في الاتصال</span>');
        });
    });

    function b64toBlob(b64, type) {
        var binary = atob(b64), arr = [];
        for (var i = 0; i < binary.length; i++) arr.push(binary.charCodeAt(i));
        return new Blob([new Uint8Array(arr)], {type: type});
    }
});
</script>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
