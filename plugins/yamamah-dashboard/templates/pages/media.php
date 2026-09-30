<?php
/**
 * صفحة الوسائط
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$total_files = count( $attachments );
$images_count = 0;
$videos_count = 0;
$total_size = 0;

foreach ( $attachments as $att ) {
    $mime = get_post_mime_type( $att->ID );
    $file = get_attached_file( $att->ID );
    if ( strpos( $mime, 'image' ) !== false ) $images_count++;
    if ( strpos( $mime, 'video' ) !== false ) $videos_count++;
    if ( $file && file_exists( $file ) ) $total_size += filesize( $file );
}

ob_start();
?>

<div class="page active" id="page-media">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1>الوسائط</h1>
            <p>إدارة الصور والملفات المستخدمة في المتجر</p>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-green" id="yamamahUploadBtn" onclick="yamamahOpenMediaUploader()">رفع ملف جديد</button>
            <button class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="yamamahCleanupMedia()">تنظيف الصور القديمة</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-4 mb-4">
        <div class="stat-card border text-center">
            <p class="text-xl text-purple"><?php echo esc_html( $total_files ); ?></p>
            <p class="text-xs text-muted">إجمالي الملفات</p>
        </div>
        <div class="stat-card border text-center">
            <p class="text-xl text-blue"><?php echo esc_html( $images_count ); ?></p>
            <p class="text-xs text-muted">صور</p>
        </div>
        <div class="stat-card border text-center">
            <p class="text-xl text-amber"><?php echo esc_html( $videos_count ); ?></p>
            <p class="text-xs text-muted">فيديو</p>
        </div>
        <div class="stat-card border text-center">
            <p class="text-xl text-green"><?php echo esc_html( yamamah_format_file_size( $total_size ) ); ?></p>
            <p class="text-xs text-muted">الحجم الكلي</p>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="media-grid">
        <?php foreach ( $attachments as $att ) :
            $mime = get_post_mime_type( $att->ID );
            $is_image = strpos( $mime, 'image' ) !== false;
            $thumb = $is_image ? wp_get_attachment_image_url( $att->ID, 'medium' ) : '';
            $file_path = get_attached_file( $att->ID );
            $file_size = ( $file_path && file_exists( $file_path ) ) ? filesize( $file_path ) : 0;
            $ext = strtoupper( pathinfo( $att->post_title, PATHINFO_EXTENSION ) ?: wp_check_filetype( $file_path )['ext'] );
        ?>
            <div class="media-card" data-id="<?php echo esc_attr( $att->ID ); ?>">
                <button type="button" class="media-delete-btn" onclick="yamamahDeleteMedia(<?php echo esc_attr( $att->ID ); ?>, this)" title="حذف">✕</button>
                <div class="media-thumb">
                    <?php if ( $is_image && $thumb ) : ?>
                        <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $att->post_title ); ?>">
                    <?php else : ?>
                        <span class="text-2xl">📄</span>
                    <?php endif; ?>
                </div>
                <div class="media-info">
                    <p class="name"><?php echo esc_html( $att->post_title ); ?></p>
                    <div class="meta">
                        <span class="size"><?php echo esc_html( yamamah_format_file_size( $file_size ) ); ?></span>
                        <span class="format"><?php echo esc_html( $ext ); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
