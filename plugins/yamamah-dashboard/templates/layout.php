<?php
/**
 * Layout الرئيسي - يلف كل الصفحات
 * يتم استدعاؤه من كل template
 *
 * المتغيرات المتوقعة:
 * $page_title - عنوان الصفحة
 * $page_description - وصف الصفحة
 * $content_callback - دالة تعرض المحتوى
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$store_name   = get_option( 'yamamah_store_name', get_bloginfo( 'name' ) );
$store_url    = home_url();
$dark_mode    = get_option( 'yamamah_dark_mode', 'no' );
$site_icon_id = (int) get_option( 'site_icon', 0 );
if ( ! $site_icon_id ) {
	$site_icon_id = (int) get_option( 'yamamah_favicon', 0 );
}
$site_icon_url = $site_icon_id ? wp_get_attachment_image_url( $site_icon_id, 'full' ) : '';
if ( $site_icon_url ) :
	?>
	<link rel="icon" href="<?php echo esc_url( $site_icon_url ); ?>" />
<?php endif; ?>
<div class="yamamah-wrap <?php echo $dark_mode === 'yes' ? 'dark' : ''; ?>" id="yamamahApp" dir="rtl">
<script>
(function(){
    var el = document.getElementById('yamamahApp');
    var d  = localStorage.getItem('yamamah_dark') === 'true';
    if (d && !el.classList.contains('dark')) el.classList.add('dark');
    if (!d && el.classList.contains('dark')) el.classList.remove('dark');
})();
</script>
    <div class="app-wrapper">

        <!-- ========== TOP BAR ========== -->
        <div class="topbar-purple">
            <div class="brand">
                <button class="icon-btn hamburger" id="hamburgerBtn"></button>
                <span><?php echo esc_html( $store_name ); ?></span>
            </div>
            <div class="actions">
                <button class="icon-btn" id="darkToggle" title="الوضع الليلي"></button>
                <a href="<?php echo esc_url( $store_url ); ?>" class="btn-store" target="_blank">
                    <span>اذهب إلى المتجر</span>
                </a>
            </div>
        </div>

        <div class="topbar-search">
            <div class="right-section">
                <div class="logo-icon">Y</div>
                <div class="search-box">
                    <span class="search-icon"></span>
                    <input type="text" placeholder="بحث في القائمة..." id="yamamahSearch" autocomplete="off">
                </div>
            </div>
            <div class="left-section">
                <div class="yamamah-notif-wrap" id="yamamahNotifWrap">
                    <button class="icon-btn" style="position:relative;" title="الإشعارات" id="yamamahNotifBtn">
                        <span class="yamamah-notif-count" id="yamamahNotifCount" style="display:none;">0</span>
                    </button>
                    <div class="yamamah-notif-panel" id="yamamahNotifPanel">
                        <div class="yamamah-notif-header">
                            <strong>الإشعارات</strong>
                            <button type="button" id="yamamahMarkAllRead" class="yamamah-notif-mark-all">قراءة الكل</button>
                        </div>
                        <div class="yamamah-notif-body" id="yamamahNotifBody">
                            <div class="yamamah-notif-empty">لا توجد إشعارات</div>
                        </div>
                    </div>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-settings-email' ) ); ?>" class="icon-btn" id="yamamahMailBtn" title="البريد" aria-label="البريد"></a>
                <div class="yamamah-account-menu" id="yamamahAccountMenu">
                    <div class="avatar" title="<?php echo esc_attr( $current_user->display_name ); ?>" id="yamamahAvatarBtn" role="button" tabindex="0">
                        <?php echo esc_html( yamamah_get_initials( $current_user->display_name ) ); ?>
                    </div>
                    <div class="yamamah-account-dropdown" id="yamamahAccountDropdown">
                        <div class="yamamah-account-dropdown-header">
                            <strong><?php echo esc_html( $current_user->display_name ); ?></strong>
                            <span><?php echo esc_html( $current_user->user_email ); ?></span>
                        </div>
                        <div class="yamamah-account-dropdown-divider"></div>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-my-account' ) ); ?>" class="yamamah-account-dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            حسابي
                        </a>
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="yamamah-account-dropdown-item yamamah-account-dropdown-logout">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            تسجيل الخروج
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== MAIN LAYOUT ========== -->
        <div class="main-layout">

            <!-- Sidebar Overlay (mobile) -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-mobile-header">
                    <span class="text-sm text-muted">القائمة</span>
                    <button id="sidebarClose"></button>
                </div>
                <div class="sidebar-scroll" id="sidebarMenu">
                    <?php yamamah_render_sidebar_menu(); ?>
                </div>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
                <?php
                if ( isset( $content_file ) && file_exists( $content_file ) ) {
                    include $content_file;
                } elseif ( isset( $page_content ) ) {
                    echo $page_content;
                }
                ?>
            </main>
        </div>
    </div>
</div>

<?php
// yamamah_render_sidebar_menu() is defined in includes/helpers.php
?>
