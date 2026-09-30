<?php
/**
 * صفحة تحليلات الإيرادات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$revenue = $data['revenue'];
$chart   = $data['chart'];
$period  = $data['period'];

$today_ar = wp_date( 'l، j F Y' );

ob_start();
?>

<div class="page active" id="page-analytics-revenue">
    <div class="page-header" style="text-align:center;">
        <h1>الإيرادات</h1>
        <p>ملخص شامل لأداء الإيرادات</p>
        <span class="yamamah-date-stamp"><?php echo esc_html( $today_ar ); ?></span>
    </div>

    <!-- Row 1 — 4 cards -->
    <div class="yamamah-analytics-grid yamamah-analytics-grid-4 mb-4">
        <div class="yamamah-astat-card active">
            <p class="yamamah-astat-label">إجمالي المبيعات</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['total_sales_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">المرتجعات</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['refunds_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">القسائم</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['coupons_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">صافي المبيعات</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['net_sales_f'] ); ?></p>
        </div>
    </div>

    <!-- Row 2 — 3 cards -->
    <div class="yamamah-analytics-grid yamamah-analytics-grid-3 mb-5">
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">الضرائب</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['taxes_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">الشحن</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['shipping_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">مجموع المبيعات</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $revenue['grand_total_f'] ); ?></p>
        </div>
    </div>

    <!-- Chart -->
    <div class="card yamamah-chart-card">
        <div class="yamamah-chart-header">
            <span class="text-sm">إجمالي المبيعات</span>
            <select id="revenueChartPeriod" class="yamamah-chart-select">
                <option value="months">الشهور</option>
                <option value="weeks">الأسابيع</option>
                <option value="days">الأيام</option>
            </select>
        </div>
        <div class="yamamah-chart-wrap">
            <canvas id="revenueChart" height="320"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if (typeof Chart === 'undefined') return;

    var chartData = <?php echo wp_json_encode( $chart ); ?>;
    var labels = chartData.map(function(d){ return d.month; });
    var values = chartData.map(function(d){ return d.sales; });

    var ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    var isDark = document.getElementById('yamamahApp') && document.getElementById('yamamahApp').classList.contains('dark');
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
    var tickColor = isDark ? '#a0a0a0' : '#666';

    window.yamamahRevenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'إجمالي المبيعات',
                data: values,
                borderColor: '#2D9B83',
                backgroundColor: 'rgba(45,155,131,0.08)',
                borderWidth: 2.5,
                tension: 0.3,
                pointRadius: 0,
                pointHoverRadius: 5,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    rtl: true,
                    textDirection: 'rtl',
                    callbacks: {
                        label: function(ctx) {
                            return ctx.parsed.y.toLocaleString() + ' ر.س';
                        }
                    }
                }
            },
            scales: {
                x: {
                    reverse: true,
                    grid: { color: gridColor },
                    ticks: { color: tickColor, font: { size: 11 } }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: tickColor,
                        font: { size: 11 },
                        callback: function(v){
                            if (v >= 1000000) return (v/1000000).toFixed(1) + 'M';
                            if (v >= 1000) return (v/1000).toFixed(0) + 'K';
                            return v;
                        }
                    }
                }
            }
        }
    });

    var periodEl = document.getElementById('revenueChartPeriod');
    if (periodEl) {
        periodEl.addEventListener('change', function(){
            var period = this.value;
            var wrap = document.querySelector('.yamamah-chart-wrap');
            if (wrap) wrap.style.opacity = '0.5';

            jQuery.post(yamamahData.ajax_url, {
                action: 'yamamah_get_chart_data',
                nonce: yamamahData.nonce,
                chart_type: 'revenue',
                period: period
            }).done(function(res){
                if (wrap) wrap.style.opacity = '';
                if (res && res.success && window.yamamahRevenueChart) {
                    // Bug #115: إعادة رسم المخطط عند تغيير الفترة الزمنية.
                    window.yamamahRevenueChart.data.labels = res.data.labels || [];
                    window.yamamahRevenueChart.data.datasets[0].data = res.data.values || [];
                    window.yamamahRevenueChart.update('none');
                }
            }).fail(function(){
                if (wrap) wrap.style.opacity = '';
            });
        });
    }
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
