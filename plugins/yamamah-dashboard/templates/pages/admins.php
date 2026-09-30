<?php
/**
 * صفحة المدراء — إدارة الأدوار والصلاحيات
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = yamamah_get_catalog_labels();

$role_config = array(
    'administrator'    => array( 'label' => 'مدير عام',     'bg' => 'rgba(108,63,160,0.1)', 'color' => '#6C3FA0' ),
    'shop_manager'     => array( 'label' => 'مدير',         'bg' => 'rgba(74,144,217,0.1)', 'color' => '#4A90D9' ),
    'editor'           => array( 'label' => 'محرر',          'bg' => 'rgba(45,155,131,0.1)', 'color' => '#2D9B83' ),
    'shipping_manager' => array( 'label' => 'مدير شحن',     'bg' => 'rgba(230,126,34,0.1)', 'color' => '#E67E22' ),
    'support'          => array( 'label' => 'دعم فني',       'bg' => 'rgba(245,158,11,0.1)', 'color' => '#F59E0B' ),
    'custom'           => array( 'label' => 'مخصص',          'bg' => 'rgba(107,114,128,0.1)', 'color' => '#6B7280' ),
);

$role_counts = array( 'administrator' => 0, 'shop_manager' => 0, 'editor' => 0, 'shipping_manager' => 0, 'support' => 0, 'custom' => 0 );
foreach ( $admins as $a ) {
    $yam_role = get_user_meta( $a->ID, 'yamamah_role', true );
    $r = ! empty( $yam_role ) ? $yam_role : ( ! empty( $a->roles ) ? $a->roles[0] : '' );
    if ( isset( $role_counts[ $r ] ) ) $role_counts[ $r ]++;
}

$permission_groups = array(
    array( 'id' => 'dashboard', 'label' => 'لوحة التحكم', 'perms' => array(
        array( 'id' => 'dashboard-view',      'label' => 'عرض لوحة التحكم' ),
        array( 'id' => 'dashboard-analytics',  'label' => 'عرض التحليلات' ),
    )),
    array( 'id' => 'products', 'label' => $labels['section'], 'perms' => array(
        array( 'id' => 'products-view',   'label' => 'عرض ' . $labels['section'] ),
        array( 'id' => 'products-add',    'label' => $labels['add_item'] ),
        array( 'id' => 'products-edit',   'label' => 'تعديل ' . $labels['section'] ),
        array( 'id' => 'products-delete', 'label' => 'حذف ' . $labels['section'] ),
        array( 'id' => 'products-categories', 'label' => 'إدارة التصنيفات' ),
        array( 'id' => 'products-import', 'label' => 'استيراد/تصدير المنتجات' ),
    )),
    array( 'id' => 'orders', 'label' => 'الطلبات', 'perms' => array(
        array( 'id' => 'orders-view',   'label' => 'عرض الطلبات' ),
        array( 'id' => 'orders-edit',   'label' => 'تعديل الطلبات' ),
        array( 'id' => 'orders-delete', 'label' => 'حذف الطلبات' ),
        array( 'id' => 'orders-refund', 'label' => 'إجراء استرداد' ),
    )),
    array( 'id' => 'customers', 'label' => 'العملاء', 'perms' => array(
        array( 'id' => 'customers-view',   'label' => 'عرض العملاء' ),
        array( 'id' => 'customers-edit',   'label' => 'تعديل بيانات العملاء' ),
        array( 'id' => 'customers-delete', 'label' => 'حذف العملاء' ),
    )),
    array( 'id' => 'shipping', 'label' => 'الشحن', 'perms' => array(
        array( 'id' => 'shipping-view',     'label' => 'عرض الشحنات والأسعار' ),
        array( 'id' => 'shipping-create',   'label' => 'إنشاء شحنة' ),
        array( 'id' => 'shipping-settings', 'label' => 'إعدادات الشحن' ),
    )),
    array( 'id' => 'payments', 'label' => 'المدفوعات', 'perms' => array(
        array( 'id' => 'payments-view',     'label' => 'عرض المدفوعات' ),
        array( 'id' => 'payments-settings', 'label' => 'إعدادات الدفع' ),
    )),
    array( 'id' => 'settings', 'label' => 'الإعدادات', 'perms' => array(
        array( 'id' => 'settings-store',  'label' => 'إعدادات المتجر' ),
        array( 'id' => 'settings-theme',  'label' => 'إعدادات المظهر' ),
        array( 'id' => 'settings-users',  'label' => 'إدارة المستخدمين' ),
        array( 'id' => 'settings-admins', 'label' => 'إدارة المدراء' ),
        array( 'id' => 'abandoned-carts', 'label' => 'السلات المتروكة' ),
    )),
);

$avatar_colors = array( '#6C3FA0', '#4A90D9', '#2D9B83', '#E67E22', '#E74C3C' );

ob_start();
?>

<div class="page active" id="page-admins">
    <!-- Header -->
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>المدراء</h1>
            <p>إدارة مدراء المتجر والأدوار والصلاحيات</p>
        </div>
        <button class="btn btn-green" onclick="yamamahOpenAdminModal()">+ إضافة مدير جديد</button>
    </div>

    <!-- Role summary cards -->
    <div class="yamamah-role-cards mb-4">
        <?php foreach ( $role_config as $rk => $rc ) : ?>
        <div class="yamamah-role-card">
            <div class="yamamah-role-icon" style="background:<?php echo esc_attr( $rc['bg'] ); ?>;color:<?php echo esc_attr( $rc['color'] ); ?>;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <p class="text-xs text-muted"><?php echo esc_html( $rc['label'] ); ?></p>
            <p class="text-lg"><?php echo esc_html( $role_counts[ $rk ] ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <input type="text" id="adminsSearchInput" placeholder="بحث بالاسم أو البريد أو الدور..." class="yamamah-search-input" style="max-width:360px;" onkeyup="yamamahFilterAdminsTable()">
    </div>

    <!-- Table -->
    <div class="card">
        <div class="table-wrapper">
            <table id="adminsTable">
                <thead>
                    <tr>
                        <th>المدير</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th style="text-align:center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $admins ) ) : ?>
                        <?php foreach ( $admins as $idx => $admin ) :
                            if ( get_user_meta( $admin->ID, '_yamama_hidden_user', true ) ) continue;
                            $initials  = yamamah_get_initials( $admin->display_name );
                            $yam_role  = get_user_meta( $admin->ID, 'yamamah_role', true );
                            $role      = ! empty( $yam_role ) ? $yam_role : ( ! empty( $admin->roles ) ? $admin->roles[0] : 'editor' );
                            $rc        = $role_config[ $role ] ?? array( 'label' => $role, 'bg' => '#eee', 'color' => '#666' );
                            $color    = $avatar_colors[ $idx % count( $avatar_colors ) ];
                            $last_login = get_user_meta( $admin->ID, 'last_login', true );
                            $saved_perms = get_user_meta( $admin->ID, 'yamamah_permissions', true );
                            $perm_count  = is_array( $saved_perms ) ? count( $saved_perms ) : 0;
                        ?>
                        <tr class="yamamah-admin-row" data-name="<?php echo esc_attr( $admin->display_name ); ?>" data-email="<?php echo esc_attr( $admin->user_email ); ?>" data-role="<?php echo esc_attr( $rc['label'] ); ?>">
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="avatar" style="background:<?php echo esc_attr( $color ); ?>;color:#fff;"><?php echo esc_html( $initials ); ?></div>
                                    <span><?php echo esc_html( $admin->display_name ); ?></span>
                                </div>
                            </td>
                            <td class="text-xs ltr"><?php echo esc_html( $admin->user_email ); ?></td>
                            <td>
                                <span class="badge" style="background:<?php echo esc_attr( $rc['bg'] ); ?>;color:<?php echo esc_attr( $rc['color'] ); ?>;">
                                    <?php echo esc_html( $rc['label'] ); ?>
                                </span>
                            </td>
                            <td><span class="badge badge-green">نشط</span></td>
                            <td class="text-xs text-muted"><?php echo $last_login ? esc_html( yamamah_time_ago( $last_login ) ) : '-'; ?></td>
                            <td style="text-align:center;">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="yamamah-icon-action" title="تعديل" onclick="yamamahEditAdmin(<?php echo esc_attr( $admin->ID ); ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <?php if ( $role !== 'administrator' || get_current_user_id() !== $admin->ID ) : ?>
                                    <button class="yamamah-icon-action" style="color:#ef4444;" title="حذف" onclick="yamamahDeleteAdmin(<?php echo esc_attr( $admin->ID ); ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-5">لا يوجد مدراء</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>

<div class="yamamah-wrap yamamah-modal-root">
<div class="yamamah-modal-overlay" id="adminModal" style="display:none;" onclick="yamamahCloseModal('adminModal')">
    <div class="yamamah-modal" onclick="event.stopPropagation();" style="max-width:660px;">
        <div class="yamamah-modal-header">
            <h3 id="adminModalTitle">إضافة مدير جديد</h3>
            <button onclick="yamamahCloseModal('adminModal')">&times;</button>
        </div>
        <div class="yamamah-modal-body">
            <input type="hidden" id="adminEditId" value="">

            <!-- Basic Info -->
            <h4 class="yamamah-section-label">البيانات الأساسية</h4>
            <div class="yamamah-form-row-2 mb-3">
                <div class="form-field">
                    <label>الاسم الكامل <span class="required">*</span></label>
                    <input type="text" id="adminName" placeholder="ادخل اسم المدير">
                </div>
                <div class="form-field">
                    <label>البريد الإلكتروني <span class="required">*</span></label>
                    <input type="email" id="adminEmail" placeholder="admin@example.com" dir="ltr">
                </div>
            </div>
            <div class="yamamah-form-row-2 mb-4">
                <div class="form-field">
                    <label>رقم الجوال</label>
                    <input type="tel" id="adminPhone" placeholder="05XXXXXXXX" dir="ltr">
                </div>
                <div class="form-field">
                    <label id="adminPasswordLabel">كلمة المرور <span class="required">*</span></label>
                    <input type="password" id="adminPassword" placeholder="ادخل كلمة المرور">
                </div>
            </div>

            <!-- Role Selection -->
            <h4 class="yamamah-section-label">الدور</h4>
            <div class="yamamah-role-selector mb-2">
                <label class="yamamah-role-option active" data-role="editor">
                    <input type="radio" name="admin_role" value="editor" checked>
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                    <span>محرر</span>
                </label>
                <label class="yamamah-role-option" data-role="shop_manager">
                    <input type="radio" name="admin_role" value="shop_manager">
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    <span>مدير</span>
                </label>
                <label class="yamamah-role-option" data-role="administrator">
                    <input type="radio" name="admin_role" value="administrator">
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span>مدير عام</span>
                </label>
                <label class="yamamah-role-option" data-role="shipping_manager">
                    <input type="radio" name="admin_role" value="shipping_manager">
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm" style="color:#E67E22;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </span>
                    <span>مدير شحن</span>
                </label>
                <label class="yamamah-role-option" data-role="support">
                    <input type="radio" name="admin_role" value="support">
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm" style="color:#F59E0B;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </span>
                    <span>دعم فني</span>
                </label>
                <label class="yamamah-role-option" data-role="custom">
                    <input type="radio" name="admin_role" value="custom">
                    <span class="yamamah-role-check">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="yamamah-role-icon-sm" style="color:#6B7280;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    </span>
                    <span>صلاحيات مخصصة</span>
                </label>
            </div>
            <p class="text-xs text-muted mb-4" id="roleHint">يمكنك تعديل الصلاحيات يدوياً أدناه. عند تغيير أي صلاحية يدوياً سيتحول الدور تلقائياً إلى "صلاحيات مخصصة".</p>

            <!-- Permissions -->
            <div class="flex justify-between items-center mb-2">
                <h4 class="yamamah-section-label" style="margin:0;">الصلاحيات</h4>
                <span class="text-xs text-muted" id="totalPermLocked"></span>
            </div>
            <div class="yamamah-perm-groups" id="permGroupsContainer">
                <?php foreach ( $permission_groups as $group ) : ?>
                <div class="yamamah-perm-group">
                    <button type="button" class="yamamah-perm-group-header" onclick="yamamahTogglePermGroup(this)">
                        <div class="flex items-center gap-2">
                            <label class="yamamah-checkbox-wrap yamamah-group-toggle" onclick="event.stopPropagation();">
                                <input type="checkbox" data-group-toggle="<?php echo esc_attr( $group['id'] ); ?>" onchange="yamamahToggleGroupAll(this, '<?php echo esc_js( $group['id'] ); ?>')">
                                <span class="yamamah-custom-checkbox"></span>
                            </label>
                            <span class="text-sm"><?php echo esc_html( $group['label'] ); ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="yamamah-perm-count" data-group="<?php echo esc_attr( $group['id'] ); ?>">0/<?php echo count( $group['perms'] ); ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="yamamah-perm-chevron"><path d="m6 9 6 6 6-6"/></svg>
                        </div>
                    </button>
                    <div class="yamamah-perm-group-body" style="display:none;">
                        <?php foreach ( $group['perms'] as $perm ) : ?>
                        <div class="yamamah-perm-item">
                            <label class="yamamah-checkbox-wrap">
                                <input type="checkbox" name="perms[]" value="<?php echo esc_attr( $perm['id'] ); ?>" data-group="<?php echo esc_attr( $group['id'] ); ?>" onchange="yamamahUpdatePermCount('<?php echo esc_js( $group['id'] ); ?>'); yamamahCheckCustomRole();">
                                <span class="yamamah-custom-checkbox"></span>
                            </label>
                            <span class="text-sm"><?php echo esc_html( $perm['label'] ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="yamamah-modal-footer">
            <button class="btn btn-outline" onclick="yamamahCloseModal('adminModal')">إلغاء</button>
            <button class="btn btn-green" id="adminSaveBtn" onclick="yamamahSaveAdmin()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-left:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                إنشاء المدير
            </button>
        </div>
    </div>
</div>
</div><!-- /.yamamah-wrap for modal -->
