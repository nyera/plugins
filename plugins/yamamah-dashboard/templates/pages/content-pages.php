<?php
/**
 * صفحة إدارة الصفحات
 * المتغير $pages يأتي من render_pages()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

ob_start();
?>

<div class="page active" id="page-pages">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>إدارة الصفحات</h1>
            <p>إنشاء وتحديث صفحات المتجر لتطابق القوالب الحالية</p>
        </div>
        <?php
        $sync_action = apply_filters( 'ydash_sync_pages_action', '' );
        $sync_nonce  = $sync_action . '_nonce';
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( $sync_action, $sync_nonce ); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr( $sync_action ); ?>">
            <button type="submit" class="btn btn-green">إنشاء/تحديث الصفحات</button>
        </form>
    </div>

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>العنوان</th>
                        <th>المسار</th>
                        <th>القالب</th>
                        <th>الدور</th>
                        <th style="width:120px;">الحالة</th>
                        <th style="width:180px;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $pages ) ) : ?>
                        <?php $i = 0; foreach ( $pages as $slug => $title ) :
                            $i++;
                            $page     = get_page_by_path( $slug );
                            $template = apply_filters( 'ydash_page_template_for_slug', '', $slug );
                            $role     = apply_filters( 'ydash_page_role_for_slug', 'صفحة ثيم', $slug );
                            $edit_url = $page ? admin_url( 'post.php?post=' . (int) $page->ID . '&action=edit' ) : '';
                            $view_url = $page ? get_permalink( $page->ID ) : '';
                            if ( 'home' === $slug ) {
                                $view_url = home_url( '/' );
                            } elseif ( 'shop' === $slug && function_exists( 'wc_get_page_permalink' ) ) {
                                $view_url = wc_get_page_permalink( 'shop' );
                            } elseif ( 'cart' === $slug && function_exists( 'wc_get_page_permalink' ) ) {
                                $view_url = wc_get_page_permalink( 'cart' );
                            } elseif ( 'checkout' === $slug && function_exists( 'wc_get_page_permalink' ) ) {
                                $view_url = wc_get_page_permalink( 'checkout' );
                            } elseif ( 'my-account' === $slug && function_exists( 'wc_get_page_permalink' ) ) {
                                $view_url = wc_get_page_permalink( 'myaccount' );
                            }
                            if ( $view_url ) {
                                $view_url = apply_filters( 'yamamah_page_preview_url', $view_url, $slug, $page );
                            }
                        ?>
                            <tr>
                                <td><?php echo (int) $i; ?></td>
                                <td><?php echo esc_html( $title ); ?></td>
                                <td class="ltr" style="direction:ltr;unicode-bidi:embed;">/<?php echo esc_html( $slug ); ?></td>
                                <td>
                                    <?php if ( $template ) : ?>
                                        <code class="ltr" style="direction:ltr;unicode-bidi:embed;font-size:0.75rem;"><?php echo esc_html( $template ); ?></code>
                                    <?php else : ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $role ); ?></td>
                                <td>
                                    <?php if ( $page ) : ?>
                                        <?php if ( 'publish' === $page->post_status ) : ?>
                                            <span class="badge badge-green">منشورة</span>
                                        <?php else : ?>
                                            <span class="badge badge-yellow"><?php echo esc_html( $page->post_status ); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="badge badge-red">غير موجودة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $page && $edit_url ) : ?>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-green">تحرير</a>
                                    <?php endif; ?>
                                    <?php if ( $page && $view_url ) : ?>
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener">معاينة</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted p-5">لم يتم العثور على قائمة الصفحات. تأكد من تفعيل القالب.</td>
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
