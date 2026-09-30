<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// $saved comes from render_payment_methods()

$electronic_allowed = ! function_exists( 'yamamah_plan_allows' ) || yamamah_plan_allows( 'electronic_payment' );
$electronic_methods = array( 'mada', 'visa-master', 'apple-pay', 'stc-pay', 'tabby', 'tamara' );

$default_methods = array(
    array( 'id' => 'mada',          'name' => 'مدى',                  'description' => 'الدفع عبر بطاقات مدى المحلية',                  'icon' => 'creditCard', 'color' => '#00875A', 'fee' => '1.75%', 'cards' => array( 'مدى' ) ),
    array( 'id' => 'visa-master',   'name' => 'Visa / Mastercard',    'description' => 'الدفع عبر بطاقات فيزا وماستركارد الائتمانية',    'icon' => 'creditCard', 'color' => '#1A1F71', 'fee' => '2.50%', 'cards' => array( 'Visa', 'Mastercard' ) ),
    array( 'id' => 'apple-pay',     'name' => 'Apple Pay',            'description' => 'الدفع السريع عبر أجهزة أبل',                     'icon' => 'smartphone', 'color' => '#000000', 'fee' => '2.00%' ),
    array( 'id' => 'stc-pay',       'name' => 'STC Pay',              'description' => 'الدفع عبر محفظة STC Pay',                        'icon' => 'wallet',     'color' => '#6C3FA0', 'fee' => '1.50%' ),
    array( 'id' => 'tabby',         'name' => 'Tabby - تقسيط',       'description' => 'الدفع بالتقسيط على 4 دفعات بدون فوائد',          'icon' => 'banknote',   'color' => '#3DDFCA', 'fee' => '5.00%' ),
    array( 'id' => 'tamara',        'name' => 'Tamara - تمارا',      'description' => 'اشتر الآن وادفع لاحقاً عبر تمارا',               'icon' => 'banknote',   'color' => '#FF6B35', 'fee' => '4.50%' ),
    array( 'id' => 'bank-transfer', 'name' => 'تحويل بنكي',          'description' => 'الدفع عبر التحويل البنكي المباشر',                'icon' => 'globe',      'color' => '#2D9B83', 'fee' => '0%' ),
    array( 'id' => 'cod',           'name' => 'الدفع عند الاستلام',  'description' => 'الدفع نقداً عند استلام الطلب',                    'icon' => 'banknote',   'color' => '#E67E22', 'fee' => '0%' ),
);

$enabled_defaults = array( 'mada', 'visa-master', 'apple-pay', 'bank-transfer', 'cod' );

$methods_state = is_array( $saved ) ? $saved : array();
foreach ( $default_methods as &$m ) {
    if ( isset( $methods_state[ $m['id'] ] ) ) {
        $m['enabled'] = (bool) $methods_state[ $m['id'] ];
    } else {
        $m['enabled'] = in_array( $m['id'], $enabled_defaults, true );
    }
}
unset( $m );

$enabled_count  = count( array_filter( $default_methods, function( $m ) { return $m['enabled']; } ) );
$disabled_count = count( $default_methods ) - $enabled_count;

$icon_svgs = array(
    'creditCard' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
    'smartphone' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>',
    'wallet'     => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
    'banknote'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>',
    'globe'      => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
);

ob_start();
?>

<div class="page active" id="page-payment-methods">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div>
            <h1>طرق الدفع</h1>
            <p>تفعيل أو تعطيل طرق الدفع المتاحة في متجرك</p>
        </div>
        <div style="display:flex;gap:8px;">
            <span class="badge badge-green"><?php echo esc_html( $enabled_count ); ?> مفعّلة</span>
            <span class="badge badge-gray"><?php echo esc_html( $disabled_count ); ?> معطّلة</span>
        </div>
    </div>

    <div class="yamamah-settings-container">

        <!-- Info Banner -->
        <div class="yamamah-info-banner mb-4">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <p>تأكد من إكمال إعدادات الدفع أولاً قبل تفعيل طرق الدفع. بعض الطرق قد تتطلب مراجعة وموافقة من مزود الخدمة.</p>
        </div>

        <!-- Methods List -->
        <div class="yamamah-methods-list">
            <?php if ( ! $electronic_allowed ) : ?>
            <div class="yamamah-info-banner mb-4" style="background:#fef3c7;border-color:#f59e0b;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <p style="color:#92400e;">الدفع الإلكتروني غير متاح في باقتك الحالية. طرق الدفع الإلكتروني معطّلة. قم بترقية باقتك لتفعيلها.</p>
            </div>
            <?php endif; ?>

            <?php foreach ( $default_methods as $method ) :
                $is_electronic = in_array( $method['id'], $electronic_methods, true );
                $blocked = $is_electronic && ! $electronic_allowed;
                if ( $blocked ) { $method['enabled'] = false; }
            ?>
            <div class="yamamah-method-card <?php echo $method['enabled'] ? 'enabled' : 'disabled'; ?><?php echo $blocked ? ' plan-blocked' : ''; ?>" data-method-id="<?php echo esc_attr( $method['id'] ); ?>">
                <div class="method-main">
                    <div class="method-info">
                        <div class="method-icon" style="background:<?php echo esc_attr( $method['color'] ); ?>15;color:<?php echo esc_attr( $method['color'] ); ?>;<?php echo $blocked ? 'opacity:0.5;' : ''; ?>">
                            <?php echo $icon_svgs[ $method['icon'] ] ?? $icon_svgs['creditCard']; ?>
                        </div>
                        <div class="method-details">
                            <div class="method-name-row">
                                <span class="method-name"><?php echo esc_html( $method['name'] ); ?></span>
                                <?php if ( $method['fee'] !== '0%' ) : ?>
                                    <span class="method-fee">رسوم: <?php echo esc_html( $method['fee'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( $blocked ) : ?>
                                    <span class="badge badge-orange" style="font-size:11px;">غير متاح بالباقة</span>
                                <?php endif; ?>
                            </div>
                            <p class="method-desc"><?php echo esc_html( $method['description'] ); ?></p>
                            <?php if ( ! empty( $method['cards'] ) ) : ?>
                                <div class="method-cards">
                                    <?php foreach ( $method['cards'] as $card ) : ?>
                                        <span class="method-card-badge"><?php echo esc_html( $card ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="method-actions">
                        <?php if ( $blocked ) : ?>
                        <button type="button" class="yamamah-toggle-switch" disabled style="opacity:0.4;cursor:not-allowed;">
                            <span class="toggle-knob"></span>
                        </button>
                        <?php else : ?>
                        <button type="button"
                                class="yamamah-toggle-switch <?php echo $method['enabled'] ? 'on' : ''; ?>"
                                onclick="yamamahTogglePayment('<?php echo esc_attr( $method['id'] ); ?>', this)"
                                data-method="<?php echo esc_attr( $method['id'] ); ?>">
                            <span class="toggle-knob"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="method-status-bar <?php echo $method['enabled'] ? 'active' : ''; ?>">
                    <span><?php echo $method['enabled'] ? 'مفعّلة' : 'معطّلة'; ?></span>
                    <span class="text-muted"><?php echo $method['enabled'] ? 'متاحة للعملاء' : 'غير متاحة للعملاء'; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
