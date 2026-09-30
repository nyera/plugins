<?php
/**
 * صفحة التحليلات — إعادة توجيه إلى صفحة الإيرادات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

wp_safe_redirect( admin_url( 'admin.php?page=yamamah-analytics-revenue' ) );
exit;
