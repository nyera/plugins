<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists( 'yamamah_plan_allows' ) && ! yamamah_plan_allows( 'shipping' ) ) {
    $page_content = yamamah_upgrade_banner( 'الشحن' );
    include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
    return;
}

/**
 * @var bool $has_credentials
 */

ob_start();
?>

<div class="page active" id="page-shipping-rates">
    <div class="page-header">
        <h1>أسعار شركات الشحن</h1>
        <p>الأسعار المعتمدة على منصة الشحن — للاطلاع فقط</p>
    </div>

    <div class="yamamah-settings-container" id="shippingRatesRoot"
         data-connected="<?php echo $has_credentials ? '1' : '0'; ?>"
         data-settings-url="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-shipping-settings' ) ); ?>">

        <?php if ( ! $has_credentials ) : ?>
            <div class="yamamah-connection-card mb-5">
                <p class="text-sm" style="color:var(--red);margin:0;">
                    المتجر غير متصل بمنصة الشحن.
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-shipping-settings' ) ); ?>">انتقل إلى إعدادات الشحن</a>
                </p>
            </div>
        <?php else : ?>

            <div class="yamamah-connection-card mb-5 is-loading" id="walletSection">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h2 class="card-section-title text-blue" style="margin:0;">رصيد الشحن</h2>
                        <p class="text-muted" style="margin:8px 0 0;">
                            الرصيد الحالي: <strong id="walletBalanceAmount" style="color:var(--green);font-size:1.25rem;">جاري التحميل</strong> ر.س
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-primary" id="walletTopupBtn" disabled>شحن الرصيد</button>
                        <button type="button" class="btn btn-outline" id="walletWithdrawBtn" disabled>طلب سحب</button>
                    </div>
                </div>
                <div id="walletTopupNotice" class="notice notice-success inline" style="display:none;margin-top:12px;">
                    <p style="margin:0;">تمت العودة من صفحة الدفع. قد يستغرق تحديث الرصيد بضع ثوانٍ.</p>
                </div>
                <p id="walletLoadError" class="text-sm" style="display:none;color:var(--red);margin-top:12px;"></p>
                <div class="table-responsive" style="margin-top:16px;">
                    <table class="yamamah-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody id="walletTransactionsBody">
                            <tr><td colspan="4" class="text-muted">جاري التحميل...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="shippingRatesLoading" class="yamamah-connection-card mb-5" style="text-align:center;padding:32px;">
                <p class="text-muted" style="margin:0;">جاري تحميل الأسعار...</p>
            </div>
            <div id="shippingRatesContent" style="display:none;"></div>
            <div id="shippingRatesError" class="yamamah-connection-card mb-5" style="display:none;">
                <p class="text-sm" style="color:var(--red);margin:0;"></p>
            </div>

            <div class="yamama-modal" id="walletTopupModal" aria-hidden="true">
                <div class="yamama-modal-overlay" data-modal-close="walletTopupModal"></div>
                <div class="yamama-modal-content">
                    <button type="button" class="yamama-modal-close" data-modal-close="walletTopupModal" aria-label="إغلاق">×</button>
                    <h3 style="margin-top:0;">شحن الرصيد</h3>
                    <p class="text-muted">الدفع عبر Tap — حساب منصة الشحن</p>
                    <label style="display:block;margin-top:12px;">المبلغ (ر.س)
                        <input type="number" id="walletTopupAmount" min="1" step="0.01" class="regular-text" style="width:100%;margin-top:6px;" />
                    </label>
                    <div id="walletTopupStatus" class="yamama-modal-status" role="status" aria-live="polite"></div>
                    <p id="walletTopupError" class="text-sm" style="color:var(--red);display:none;margin-top:8px;"></p>
                    <button type="button" class="btn btn-primary yamama-btn" id="walletTopupSubmit" style="margin-top:12px;">
                        <span class="yamama-btn-label">متابعة للدفع</span>
                    </button>
                </div>
            </div>

            <div class="yamama-modal" id="walletWithdrawModal" aria-hidden="true">
                <div class="yamama-modal-overlay" data-modal-close="walletWithdrawModal"></div>
                <div class="yamama-modal-content yamama-modal-content--wide">
                    <button type="button" class="yamama-modal-close" data-modal-close="walletWithdrawModal" aria-label="إغلاق">×</button>
                    <h3 style="margin-top:0;">طلب سحب رصيد</h3>
                    <p class="text-muted">يُراجع الطلب يدوياً من إدارة المنصة</p>
                    <form id="walletWithdrawForm">
                        <p><label>المبلغ (ر.س)<br><input type="number" id="walletWithdrawAmount" min="1" step="0.01" class="regular-text" style="width:100%;" required /></label></p>
                        <p><label>اسم البنك<br><input type="text" id="walletWithdrawBank" class="regular-text" style="width:100%;" required /></label></p>
                        <p><label>اسم صاحب الحساب<br><input type="text" id="walletWithdrawHolder" class="regular-text" style="width:100%;" required /></label></p>
                        <p><label>IBAN<br><input type="text" id="walletWithdrawIban" class="regular-text" style="width:100%;" required /></label></p>
                        <p><label>ملاحظات<br><textarea id="walletWithdrawNotes" rows="2" class="regular-text" style="width:100%;"></textarea></label></p>
                    </form>
                    <div id="walletWithdrawStatus" class="yamama-modal-status" role="status" aria-live="polite"></div>
                    <p id="walletWithdrawError" class="text-sm" style="color:var(--red);display:none;margin-top:8px;"></p>
                    <button type="button" class="btn btn-primary yamama-btn" id="walletWithdrawSubmit">
                        <span class="yamama-btn-label">إرسال الطلب</span>
                    </button>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php if ( $has_credentials ) : ?>
<script>
jQuery(function($) {
    if (typeof yamamahData === 'undefined') {
        $('#walletBalanceAmount').text('0');
        $('#walletSection').removeClass('is-loading');
        return;
    }

    function setWalletFallback(amountText, txHtml, errorText) {
        $('#walletBalanceAmount').text(amountText);
        $('#walletSection').removeClass('is-loading');
        $('#walletTopupBtn, #walletWithdrawBtn').prop('disabled', true);
        if (txHtml) {
            $('#walletTransactionsBody').html(txHtml);
        }
        if (errorText) {
            $('#walletLoadError').text(errorText).show();
        } else {
            $('#walletLoadError').hide();
        }
    }

    // Fallback timeout in case AJAX hangs
    var walletLoadTimeout = setTimeout(function() {
        setWalletFallback('—', '<tr><td colspan="4" class="text-muted">انتهت مهلة التحميل</td></tr>', 'تعذّر تحميل الرصيد في الوقت المحدد. يرجى تحديث الصفحة.');
    }, 12000);

    $.post(yamamahData.ajax_url, {
        action: 'yamamah_get_shipping_wallet',
        nonce: yamamahData.nonce
    }).done(function(res) {
        clearTimeout(walletLoadTimeout);
        if (res && res.success && res.data) {
            var balance = parseFloat(res.data.balance || 0);
            if (isNaN(balance)) balance = 0;
            $('#walletBalanceAmount').text(balance.toFixed(2));
            $('#walletSection').removeClass('is-loading');
            $('#walletTopupBtn, #walletWithdrawBtn').prop('disabled', false);
            $('#walletLoadError').hide();

            var txs = res.data.transactions || [];
            if (!txs.length) {
                $('#walletTransactionsBody').html('<tr><td colspan="4" class="text-muted">لا توجد حركات بعد</td></tr>');
                return;
            }
            var rows = '';
            txs.forEach(function(tx) {
                rows += '<tr>';
                rows += '<td>' + (tx.type || '—') + '</td>';
                rows += '<td>' + (parseFloat(tx.amount || 0).toFixed(2)) + ' ر.س</td>';
                rows += '<td>' + (tx.status || '—') + '</td>';
                rows += '<td>' + (tx.createdAt ? String(tx.createdAt).slice(0, 10) : '—') + '</td>';
                rows += '</tr>';
            });
            $('#walletTransactionsBody').html(rows);
            return;
        }
        clearTimeout(walletLoadTimeout);
        setWalletFallback('0', '<tr><td colspan="4" class="text-muted">تعذّر تحميل الحركات</td></tr>', (res && res.data && res.data.message) ? res.data.message : '');
    }).fail(function() {
        clearTimeout(walletLoadTimeout);
        setWalletFallback('0', '<tr><td colspan="4" class="text-muted">تعذّر تحميل الحركات</td></tr>', 'تعذّر الاتصال لتحميل الرصيد');
    });
});
</script>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
