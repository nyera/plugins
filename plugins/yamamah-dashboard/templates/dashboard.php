<?php
/**
 * الصفحة الرئيسية — Dashboard
 * المتغير $data يأتي من render_dashboard() في class-yamamah-admin.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$stats              = $data['stats'];
$recent_orders      = $data['recent_orders'];
$top_products       = $data['top_products'];
$stock_alerts       = $data['stock_alerts'];
$order_overview     = $data['order_overview'];
$monthly_chart      = $data['monthly_chart'];
$sales_by_category  = $data['sales_by_category'];

// تعريف محتوى الصفحة للـ layout
$content_file = YAMAMAH_PLUGIN_DIR . 'templates/pages/_dashboard-content.php';
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
