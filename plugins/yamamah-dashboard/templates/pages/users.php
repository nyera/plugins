<?php
/**
 * صفحة المستخدمين (العملاء المسجلين)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$customers      = $data['customers'];
$all_count      = $data['all_count'];
$active_count   = $data['active_count'];
$inactive_count = $data['inactive_count'];

$avatar_colors = array( '#6C3FA0', '#2D9B83', '#E67E22', '#4A90D9', '#E74C3C', '#8E44AD', '#27AE60', '#F39C12' );

ob_start();
?>

<div class="page active" id="page-users">
    <!-- Header -->
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>المستخدمين</h1>
            <p>المستخدمين المسجلين في المتجر</p>
        </div>
        <button class="btn btn-outline" onclick="alert('سيتم إضافة التصدير لاحقاً')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            تصدير
        </button>
    </div>

    <!-- Stats -->
    <div class="yamamah-users-stats mb-4">
        <div class="yamamah-user-stat-card">
            <div class="yamamah-user-stat-icon" style="background:rgba(108,63,160,0.1);color:#6C3FA0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <p class="text-xs text-muted">الإجمالي</p>
                <p class="text-lg"><?php echo esc_html( $all_count ); ?></p>
            </div>
        </div>
        <div class="yamamah-user-stat-card">
            <div class="yamamah-user-stat-icon" style="background:rgba(45,155,131,0.1);color:#2D9B83;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
            </div>
            <div>
                <p class="text-xs text-muted">نشط</p>
                <p class="text-lg"><?php echo esc_html( $active_count ); ?></p>
            </div>
        </div>
        <div class="yamamah-user-stat-card">
            <div class="yamamah-user-stat-icon" style="background:rgba(217,119,6,0.1);color:#d97706;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="8"/></svg>
            </div>
            <div>
                <p class="text-xs text-muted">غير نشط</p>
                <p class="text-lg"><?php echo esc_html( $inactive_count ); ?></p>
            </div>
        </div>
        <div class="yamamah-user-stat-card">
            <div class="yamamah-user-stat-icon" style="background:rgba(239,68,68,0.1);color:#ef4444;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>
            </div>
            <div>
                <p class="text-xs text-muted">محظور</p>
                <p class="text-lg">0</p>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <input type="text" id="usersSearchInput" placeholder="بحث بالاسم أو البريد أو الجوال..." class="yamamah-search-input" style="max-width:360px;" onkeyup="yamamahFilterUsersTable()">
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-wrapper">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>تاريخ التسجيل</th>
                        <th>آخر دخول</th>
                        <th>الطلبات</th>
                        <th>إجمالي الإنفاق</th>
                        <th>الحالة</th>
                        <th style="text-align:center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $customers ) ) : ?>
                        <?php foreach ( $customers as $idx => $user ) :
                            $initials  = yamamah_get_initials( $user->display_name );
                            $color     = $avatar_colors[ $idx % count( $avatar_colors ) ];
                            $wc_cust   = new WC_Customer( $user->ID );
                            $orders    = $wc_cust->get_order_count();
                            $spent     = $wc_cust->get_total_spent();
                            $last_login = get_user_meta( $user->ID, 'last_login', true );
                            $thirty    = strtotime( '-30 days' );
                            $is_active = $last_login && strtotime( $last_login ) >= $thirty;
                        ?>
                        <tr class="yamamah-user-row" data-name="<?php echo esc_attr( $user->display_name ); ?>" data-email="<?php echo esc_attr( $user->user_email ); ?>">
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="avatar" style="background:<?php echo esc_attr( $color ); ?>;color:#fff;"><?php echo esc_html( $initials ); ?></div>
                                    <span><?php echo esc_html( $user->display_name ); ?></span>
                                </div>
                            </td>
                            <td class="text-xs ltr"><?php echo esc_html( $user->user_email ); ?></td>
                            <td class="text-xs text-muted"><?php echo esc_html( date_i18n( 'Y-m-d', strtotime( $user->user_registered ) ) ); ?></td>
                            <td class="text-xs text-muted"><?php echo $last_login ? esc_html( yamamah_time_ago( $last_login ) ) : '-'; ?></td>
                            <td style="text-align:center;"><?php echo esc_html( $orders ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $spent ) ); ?></td>
                            <td>
                                <?php if ( $is_active ) : ?>
                                    <span class="badge badge-green">نشط</span>
                                <?php else : ?>
                                    <span class="badge badge-yellow">غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <button class="yamamah-icon-action" title="عرض" onclick="yamamahViewUser(<?php echo esc_attr( $user->ID ); ?>, '<?php echo esc_js( $user->display_name ); ?>', '<?php echo esc_js( $user->user_email ); ?>', '<?php echo esc_js( date_i18n( 'Y-m-d', strtotime( $user->user_registered ) ) ); ?>', '<?php echo esc_js( $last_login ? yamamah_time_ago( $last_login ) : '-' ); ?>', <?php echo esc_attr( $orders ); ?>, '<?php echo esc_js( strip_tags( wc_price( $spent ) ) ); ?>', '<?php echo esc_js( $initials ); ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted p-5">لا يوجد عملاء مسجلين</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Detail Modal -->
<div class="yamamah-modal-overlay" id="userDetailModal" style="display:none;" onclick="yamamahCloseModal('userDetailModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:480px;">
        <div class="yamamah-modal-header">
            <h3>بيانات المستخدم</h3>
            <button onclick="yamamahCloseModal('userDetailModal')">&times;</button>
        </div>
        <div class="yamamah-modal-body">
            <div class="flex items-center gap-3 mb-4">
                <div class="avatar" id="modalUserAvatar" style="width:56px;height:56px;font-size:1.2rem;background:#6C3FA0;color:#fff;"></div>
                <div>
                    <h3 id="modalUserName" style="margin:0;"></h3>
                    <span class="badge badge-green" id="modalUserStatusBadge">نشط</span>
                </div>
            </div>
            <div class="yamamah-detail-list">
                <div class="yamamah-detail-row">
                    <span class="text-muted text-sm">البريد:</span>
                    <span id="modalUserEmail" class="ltr text-sm"></span>
                </div>
                <div class="yamamah-detail-row">
                    <span class="text-muted text-sm">تاريخ التسجيل:</span>
                    <span id="modalUserReg" class="text-sm"></span>
                </div>
                <div class="yamamah-detail-row">
                    <span class="text-muted text-sm">آخر دخول:</span>
                    <span id="modalUserLastLogin" class="text-sm"></span>
                </div>
                <div class="yamamah-detail-row">
                    <span class="text-muted text-sm">عدد الطلبات:</span>
                    <span id="modalUserOrders" class="text-sm"></span>
                </div>
                <div class="yamamah-detail-row">
                    <span class="text-muted text-sm">إجمالي الإنفاق:</span>
                    <span id="modalUserSpent" class="text-sm"></span>
                </div>
            </div>
        </div>
        <div class="yamamah-modal-footer">
            <button class="btn btn-outline" onclick="yamamahCloseModal('userDetailModal')">إغلاق</button>
        </div>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
