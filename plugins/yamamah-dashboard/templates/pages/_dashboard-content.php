<?php
/**
 * محتوى الصفحة الرئيسية — Dashboard
 * المتغيرات متاحة من dashboard.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();
?>
<div class="page active" id="page-home">
    <div class="page-header flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1>لوحة التحكم</h1>
            <p>مرحباً بك، إليك نظرة عامة على متجرك</p>
            <div class="meta">
                <?php echo date_i18n( 'l j F Y' ); ?>
                &bull; آخر تحديث <?php echo date_i18n( 'H:i' ); ?>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-add-product' ) ); ?>" class="btn btn-purple btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                <?php echo esc_html( $labels['add_item'] ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-coupons' ) ); ?>" class="btn btn-pink btn-sm">إنشاء كوبون</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="btn btn-orange btn-sm">عرض الطلبات</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-products' ) ); ?>" class="btn btn-success btn-sm">إدارة المخزون</a>
        </div>
    </div>

    <!-- Time Filters -->
    <div class="flex items-center gap-2 mb-5">
        <span class="text-sm text-muted">الفترة الزمنية</span>
        <button class="btn btn-sm btn-purple yamamah-period-btn active" data-period="today">اليوم</button>
        <button class="btn btn-sm btn-accent yamamah-period-btn" data-period="7day">الأسبوع</button>
        <button class="btn btn-sm btn-accent yamamah-period-btn" data-period="30day">الشهر</button>
        <button class="btn btn-sm btn-accent yamamah-period-btn" data-period="year">السنة</button>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-5 mb-5" id="yamamahStatsGrid">
        <div class="stat-card bg-green-light">
            <div class="stat-icon">
                <span class="stat-icon-circle" style="background:rgba(45,155,131,0.15);color:#2D9B83;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
                <span>إجمالي المبيعات</span>
            </div>
            <div class="stat-value"><?php echo wp_kses_post( $stats['total_sales']['formatted'] ); ?></div>
            <?php if ( $stats['total_sales']['change'] != 0 ) : ?>
                <div class="stat-change <?php echo $stats['total_sales']['change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['total_sales']['change'] > 0 ? '↑' : '↓'; ?>
                    <?php echo esc_html( $stats['total_sales']['change'] ); ?>%
                </div>
            <?php endif; ?>
        </div>

        <div class="stat-card bg-blue-light">
            <div class="stat-icon">
                <span class="stat-icon-circle" style="background:rgba(108,63,160,0.15);color:#6C3FA0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                </span>
                <span>الطلبات</span>
            </div>
            <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['total_orders']['value'] ) ); ?></div>
            <?php if ( $stats['total_orders']['change'] != 0 ) : ?>
                <div class="stat-change <?php echo $stats['total_orders']['change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['total_orders']['change'] > 0 ? '↑' : '↓'; ?>
                    <?php echo esc_html( $stats['total_orders']['change'] ); ?>%
                </div>
            <?php endif; ?>
        </div>

        <div class="stat-card bg-orange-light">
            <div class="stat-icon">
                <span class="stat-icon-circle" style="background:rgba(230,126,34,0.15);color:#E67E22;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <span>عدد الزيارات</span>
            </div>
            <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['visits']['value'] ) ); ?></div>
            <?php if ( ! empty( $stats['visits']['change'] ) && $stats['visits']['change'] != 0 ) : ?>
                <div class="stat-change <?php echo $stats['visits']['change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['visits']['change'] > 0 ? '↑' : '↓'; ?>
                    <?php echo esc_html( $stats['visits']['change'] ); ?>%
                </div>
            <?php endif; ?>
        </div>

        <div class="stat-card bg-pink-light">
            <div class="stat-icon">
                <span class="stat-icon-circle" style="background:rgba(233,30,99,0.12);color:#E91E63;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </span>
                <span>معدل التحويل</span>
            </div>
            <div class="stat-value"><?php echo esc_html( $stats['conversion_rate']['value'] ); ?></div>
            <?php if ( ! empty( $stats['conversion_rate']['change'] ) && $stats['conversion_rate']['change'] != 0 ) : ?>
                <div class="stat-change <?php echo $stats['conversion_rate']['change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['conversion_rate']['change'] > 0 ? '↑' : '↓'; ?>
                    <?php echo esc_html( $stats['conversion_rate']['change'] ); ?>%
                </div>
            <?php endif; ?>
        </div>

        <div class="stat-card bg-purple-light">
            <div class="stat-icon">
                <span class="stat-icon-circle" style="background:rgba(108,63,160,0.12);color:#6C3FA0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                <span>عملاء جدد</span>
            </div>
            <div class="stat-value"><?php echo esc_html( number_format_i18n( $stats['new_customers']['value'] ) ); ?></div>
            <?php if ( ! empty( $stats['new_customers']['change'] ) && $stats['new_customers']['change'] != 0 ) : ?>
                <div class="stat-change <?php echo $stats['new_customers']['change'] > 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $stats['new_customers']['change'] > 0 ? '↑' : '↓'; ?>
                    <?php echo esc_html( $stats['new_customers']['change'] ); ?>%
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-2 mb-5" style="gap:1.5rem;">
        <!-- Line Chart: Sales & Orders -->
        <div class="card">
            <div class="card-header">
                <h3>المبيعات والأداء</h3>
                <div class="flex gap-4 text-xs">
                    <span class="flex items-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#E67E22;display:inline-block"></span> المبيعات</span>
                    <span class="flex items-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#6C3FA0;display:inline-block"></span> الطلبات</span>
                    <span class="flex items-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#E91E63;display:inline-block"></span> الزيارات</span>
                </div>
            </div>
            <div style="position:relative;height:280px;">
                <canvas id="yamamahLineChart"></canvas>
            </div>
        </div>

        <!-- Donut Chart: Sales by Category -->
        <div class="card">
            <div class="card-header">
                <h3>الإيرادات حسب الفئة</h3>
            </div>
            <div class="yamamah-donut-wrap" style="display:flex;align-items:center;gap:1.5rem;min-height:280px;">
                <div class="yamamah-donut-canvas-wrap" style="flex:0 0 200px;position:relative;">
                    <canvas id="yamamahDonutChart" width="200" height="200"></canvas>
                </div>
                <div class="yamamah-donut-legend" id="yamamahDonutLegend">
                    <?php
                    $legend_colors = array( '#6C3FA0', '#E91E63', '#E67E22', '#2D9B83', '#3B82F6', '#8B5CF6' );
                    if ( ! empty( $sales_by_category ) ) :
                        foreach ( $sales_by_category as $i => $cat ) :
                            $color = $legend_colors[ $i % count( $legend_colors ) ];
                    ?>
                        <div class="yamamah-donut-legend-item">
                            <span class="yamamah-donut-legend-dot" style="background:<?php echo esc_attr( $color ); ?>;"></span>
                            <span class="yamamah-donut-legend-label"><?php echo esc_html( $cat['name'] ); ?></span>
                            <span class="yamamah-donut-legend-pct"><?php echo esc_html( $cat['percent'] ); ?>%</span>
                        </div>
                    <?php endforeach; else : ?>
                        <p class="text-sm text-muted">لا توجد بيانات بعد</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Data (JSON for JS) -->
    <script>
    var yamamahMonthlyChart = <?php echo wp_json_encode( $monthly_chart ); ?>;
    var yamamahCategoryChart = <?php echo wp_json_encode( $sales_by_category ); ?>;
    var yamamahCategoryColors = <?php echo wp_json_encode( $legend_colors ); ?>;
    </script>

    <!-- Top Products -->
    <div class="card mb-5">
        <div class="card-header">
            <h3><?php echo esc_html( $labels['top_sellers'] ); ?></h3>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-products' ) ); ?>" class="card-link">التفاصيل</a>
        </div>
        <div class="space-y-2">
            <?php if ( ! empty( $top_products ) ) : ?>
                <?php foreach ( $top_products as $product ) : ?>
                    <div class="item-row">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-muted"><?php echo esc_html( $product['rank'] ); ?></span>
                            <?php if ( $product['image'] ) : ?>
                                <img src="<?php echo esc_url( $product['image'] ); ?>" alt="" style="width:24px;height:24px;border-radius:4px;object-fit:cover;">
                            <?php endif; ?>
                            <span class="text-sm"><?php echo esc_html( $product['name'] ); ?></span>
                        </div>
                        <span class="text-sm"><?php echo esc_html( $product['sales'] ); ?> مبيعة</span>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="text-sm text-muted">لا توجد بيانات مبيعات بعد.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bottom Section -->
    <div class="grid grid-2 mb-5">
        <!-- Stock Alerts -->
        <div class="card">
            <h3 class="mb-4">تنبيهات المخزون</h3>
            <div class="space-y-2 max-h-80">
                <?php if ( ! empty( $stock_alerts ) ) : ?>
                    <?php foreach ( $stock_alerts as $alert ) : ?>
                        <div class="item-row">
                            <div class="flex items-center gap-2">
                                <span>⚠️</span>
                                <span class="text-sm"><?php echo esc_html( $alert['name'] ); ?></span>
                            </div>
                            <span class="badge <?php echo esc_attr( $alert['badge_class'] ); ?>">
                                <?php echo esc_html( $alert['badge'] ); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="text-sm text-muted">لا توجد تنبيهات حالياً ✅</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Overview -->
        <div class="card">
            <div class="card-header">
                <h3>نظرة عامة على الطلبات</h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-orders' ) ); ?>" class="card-link">عرض الطلبات</a>
            </div>
            <div class="grid grid-2 gap-3">
                <?php foreach ( $order_overview as $item ) : ?>
                    <div class="status-badge <?php echo esc_attr( $item['bg'] ); ?>">
                        <span><?php echo $item['icon']; ?></span>
                        <div>
                            <p><?php echo esc_html( $item['label'] ); ?></p>
                            <p class="text-lg fw-500" style="color:<?php echo esc_attr( $item['color'] ); ?>">
                                <?php echo esc_html( $item['count'] ); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
