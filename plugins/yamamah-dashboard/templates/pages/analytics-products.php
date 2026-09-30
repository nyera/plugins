<?php
/**
 * صفحة تحليلات المنتجات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$labels   = yamamah_get_catalog_labels();
$products = $data['products'];
$chart    = $data['chart'];
$period   = $data['period'];

$today_ar = wp_date( 'l، j F Y' );

ob_start();
?>

<div class="page active" id="page-analytics-products">
    <div class="page-header" style="text-align:center;">
        <h1><?php echo esc_html( $labels['analytics_title'] ); ?></h1>
        <p><?php echo esc_html( $labels['analytics_subtitle'] ); ?></p>
        <span class="yamamah-date-stamp"><?php echo esc_html( $today_ar ); ?></span>
    </div>

    <!-- Stat Cards — 3 -->
    <div class="yamamah-analytics-grid yamamah-analytics-grid-3 mb-5">
        <div class="yamamah-astat-card active">
            <p class="yamamah-astat-label">الطلبات</p>
            <p class="yamamah-astat-value"><?php echo esc_html( number_format_i18n( $products['total_orders'] ) ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">صافي المبيعات</p>
            <p class="yamamah-astat-value"><?php echo wp_kses_post( $products['net_sales_f'] ); ?></p>
        </div>
        <div class="yamamah-astat-card">
            <p class="yamamah-astat-label">العناصر المباعة</p>
            <p class="yamamah-astat-value"><?php echo esc_html( number_format_i18n( $products['items_sold'] ) ); ?></p>
        </div>
    </div>

    <!-- Chart -->
    <div class="card yamamah-chart-card">
        <div class="yamamah-chart-header">
            <div class="yamamah-chart-legend-wrap">
                <span class="text-sm">الطلبات</span>
                <div class="yamamah-chart-legend">
                    <span class="yamamah-legend-item"><span class="yamamah-legend-line" style="background:#2D9B83;"></span> طلبات</span>
                    <span class="yamamah-legend-item"><span class="yamamah-legend-line" style="background:#E74C3C;"></span> ملغاة</span>
                </div>
            </div>
            <select id="productsChartPeriod" class="yamamah-chart-select">
                <option value="months">الشهور</option>
                <option value="weeks">الأسابيع</option>
                <option value="days">الأيام</option>
            </select>
        </div>
        <div class="yamamah-chart-wrap">
            <canvas id="productsChart" height="320"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if (typeof Chart === 'undefined') return;

    var chartData = <?php echo wp_json_encode( $chart ); ?>;
    var labels    = chartData.map(function(d){ return d.month; });
    var orders    = chartData.map(function(d){ return d.orders; });
    var cancelled = chartData.map(function(d){ return d.cancelled; });

    var ctx = document.getElementById('productsChart');
    if (!ctx) return;

    var isDark = document.getElementById('yamamahApp') && document.getElementById('yamamahApp').classList.contains('dark');
    var gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
    var tickColor = isDark ? '#a0a0a0' : '#666';

    window.yamamahProductsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'طلبات',
                    data: orders,
                    borderColor: '#2D9B83',
                    borderWidth: 2.5,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    fill: false
                },
                {
                    label: 'ملغاة',
                    data: cancelled,
                    borderColor: '#E74C3C',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { rtl: true, textDirection: 'rtl' }
            },
            scales: {
                x: {
                    reverse: true,
                    grid: { color: gridColor },
                    ticks: { color: tickColor, font: { size: 11 } }
                },
                y: {
                    grid: { color: gridColor },
                    ticks: { color: tickColor, font: { size: 11 } }
                }
            }
        }
    });

    document.getElementById('productsChartPeriod').addEventListener('change', function(){
        var period = this.value;
        jQuery.post(yamamahData.ajax_url, {
            action: 'yamamah_get_chart_data',
            nonce: yamamahData.nonce,
            chart_type: 'products',
            period: period
        }, function(res){
            if (res.success && window.yamamahProductsChart) {
                window.yamamahProductsChart.data.labels = res.data.labels;
                window.yamamahProductsChart.data.datasets[0].data = res.data.orders;
                window.yamamahProductsChart.data.datasets[1].data = res.data.cancelled;
                window.yamamahProductsChart.update();
            }
        });
    });
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
