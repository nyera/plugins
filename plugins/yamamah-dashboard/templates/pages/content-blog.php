<?php
/**
 * صفحة المدونة — بطاقات (نمط السياسات) + تحرير منفصل
 *
 * @var array<string,mixed> $manifest
 * @var array<string,mixed> $content
 * @var array<int,array<string,mixed>> $blog_posts
 * @var array<int,array<string,mixed>> $blog_categories
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blog_page      = get_page_by_path( 'blog' );
$blog_list_url  = admin_url( 'admin.php?page=yamamah-blog-settings' );
$editing        = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
$editing_cat    = isset( $_GET['edit_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_cat'] ) ) : '';
$is_new_post    = ( $editing === 'new' );
$is_new_cat     = ( $editing_cat === 'new' );
$edit_post      = null;
$edit_post_data = null;
$edit_cat_data  = null;

if ( $editing && ! $is_new_post && ctype_digit( $editing ) ) {
	$edit_post = get_post( (int) $editing );
	if ( ! $edit_post || $edit_post->post_type !== 'post' ) {
		$edit_post = null;
	} else {
		$thumb_id = (int) get_post_thumbnail_id( $edit_post->ID );
		$cats     = get_the_category( $edit_post->ID );
		$edit_post_data = array(
			'id'                 => (int) $edit_post->ID,
			'title'              => $edit_post->post_title,
			'content'            => $edit_post->post_content,
			'excerpt'            => $edit_post->post_excerpt,
			'status'             => $edit_post->post_status,
			'category_id'        => ! empty( $cats[0] ) ? (int) $cats[0]->term_id : 0,
			'featured_image'     => $thumb_id,
			'featured_image_url' => $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'medium' ) : '',
			'permalink'          => get_permalink( $edit_post ),
		);
	}
}

if ( $editing_cat && ! $is_new_cat && ctype_digit( $editing_cat ) ) {
	$term = get_term( (int) $editing_cat, 'category' );
	if ( $term && ! is_wp_error( $term ) ) {
		$edit_cat_data = array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'is_default'  => ( (int) $term->term_id === (int) get_option( 'default_category' ) ),
		);
	}
}

$status_labels = array(
	'publish' => 'منشور',
	'draft'   => 'مسودة',
	'pending' => 'قيد المراجعة',
	'future'  => 'مجدول',
);

ob_start();
?>
<div class="page active" id="page-blog-settings">

<?php if ( $is_new_post || $edit_post_data ) : ?>

	<div class="page-header">
		<div class="flex items-center gap-3 flex-wrap">
			<a href="<?php echo esc_url( $blog_list_url ); ?>" class="btn btn-outline btn-sm">&rarr; العودة</a>
			<div>
				<h1><?php echo $is_new_post ? 'إضافة مقال جديد' : 'تحرير: ' . esc_html( $edit_post_data['title'] ); ?></h1>
				<p><?php echo $is_new_post ? 'إنشاء مقال جديد للمدونة' : 'تعديل عنوان ومحتوى المقال'; ?></p>
			</div>
		</div>
	</div>

	<form id="yamamahBlogPostForm">
		<input type="hidden" name="post_id" id="blogPostId" value="<?php echo $is_new_post ? '0' : (int) $edit_post_data['id']; ?>">
		<input type="hidden" name="featured_image" id="blogFeaturedImageId" value="<?php echo $is_new_post ? '0' : (int) $edit_post_data['featured_image']; ?>">

		<div class="card space-y-4 mb-4">
			<div class="form-field">
				<label>عنوان المقال</label>
				<input type="text" name="title" id="blogPostTitle" class="form-control" required
					value="<?php echo $is_new_post ? '' : esc_attr( $edit_post_data['title'] ); ?>">
			</div>

			<div class="grid grid-2">
				<div class="form-field">
					<label>الحالة</label>
					<select name="status" id="blogPostStatus" class="form-control">
						<?php
						$cur_status = $is_new_post ? 'draft' : $edit_post_data['status'];
						if ( $cur_status === 'future' ) {
							$cur_status = 'draft';
						}
						foreach ( array( 'publish' => 'منشور', 'draft' => 'مسودة', 'pending' => 'قيد المراجعة' ) as $val => $lbl ) :
							?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cur_status, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-field">
					<label>التصنيف</label>
					<select name="category_id" id="blogPostCategory" class="form-control">
						<option value="0">— بدون تصنيف —</option>
						<?php
						$cur_cat = $is_new_post ? 0 : (int) $edit_post_data['category_id'];
						foreach ( $blog_categories as $cat ) :
							$cat_id   = (int) ( $cat['id'] ?? 0 );
							$cat_name = (string) ( $cat['name'] ?? '' );
							?>
						<option value="<?php echo $cat_id; ?>" <?php selected( $cur_cat, $cat_id ); ?>><?php echo esc_html( $cat_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="form-field">
				<label>المحتوى</label>
				<div class="yamamah-editor-toolbar">
					<button type="button" onclick="yamamahBlogExecCmd('bold')" title="عريض"><b>B</b></button>
					<button type="button" onclick="yamamahBlogExecCmd('italic')" title="مائل"><i>I</i></button>
					<button type="button" onclick="yamamahBlogExecCmd('underline')" title="تحته خط"><u>U</u></button>
					<span class="yamamah-toolbar-sep"></span>
					<button type="button" onclick="yamamahBlogExecCmd('insertUnorderedList')" title="قائمة نقطية">&#8226;</button>
					<button type="button" onclick="yamamahBlogExecCmd('insertOrderedList')" title="قائمة رقمية">1.</button>
					<span class="yamamah-toolbar-sep"></span>
					<button type="button" onclick="yamamahBlogInsertHeading()" title="عنوان">H</button>
					<button type="button" onclick="yamamahBlogInsertLink()" title="رابط">&#128279;</button>
					<span class="yamamah-toolbar-sep"></span>
					<button type="button" onclick="yamamahBlogToggleSource()" title="كود المصدر" id="yamamahBlogSourceBtn">&lt;/&gt;</button>
				</div>
				<div id="blogPostEditor" class="yamamah-richtext-editor" contenteditable="true" dir="rtl"><?php
					echo $is_new_post ? '' : wp_kses_post( $edit_post_data['content'] );
				?></div>
				<textarea name="content" id="blogPostContent" class="form-control yamamah-source-editor" style="display:none;" rows="15"><?php
					echo $is_new_post ? '' : esc_textarea( $edit_post_data['content'] );
				?></textarea>
			</div>

			<div class="form-field">
				<label>مقتطف (اختياري — يظهر في بطاقات المدونة)</label>
				<textarea name="excerpt" id="blogPostExcerpt" class="form-control" rows="2"><?php
					echo $is_new_post ? '' : esc_textarea( $edit_post_data['excerpt'] );
				?></textarea>
			</div>

			<div class="form-field">
				<label>صورة بارزة</label>
				<div class="flex items-center gap-3 flex-wrap">
					<?php
					$feat_url = $is_new_post ? '' : ( $edit_post_data['featured_image_url'] ?? '' );
					$has_feat = $feat_url !== '';
					?>
					<img id="blogFeaturedPreview" src="<?php echo esc_url( $feat_url ); ?>" alt=""
						style="<?php echo $has_feat ? '' : 'display:none;'; ?>width:120px;height:80px;border-radius:8px;object-fit:cover;">
					<button type="button" class="btn btn-outline btn-sm" id="blogPickFeatured">اختيار صورة</button>
					<button type="button" class="btn btn-outline btn-sm" id="blogClearFeatured" style="<?php echo $has_feat ? '' : 'display:none;'; ?>">إزالة الصورة</button>
				</div>
			</div>
		</div>

		<div class="flex gap-3 flex-wrap">
			<button type="submit" class="btn btn-green" id="blogPostSubmitBtn"><?php echo $is_new_post ? 'حفظ المقال' : 'حفظ التغييرات'; ?></button>
			<a href="<?php echo esc_url( $blog_list_url ); ?>" class="btn btn-outline">إلغاء</a>
			<?php if ( ! $is_new_post && ! empty( $edit_post_data['permalink'] ) ) : ?>
				<a href="<?php echo esc_url( $edit_post_data['permalink'] ); ?>" class="btn btn-outline" target="_blank" rel="noopener">معاينة المقال</a>
			<?php endif; ?>
		</div>
		<div id="blogPostSaveResult" class="alert mt-3" style="display:none;"></div>
	</form>

<?php elseif ( $is_new_cat || $edit_cat_data ) : ?>

	<div class="page-header">
		<div class="flex items-center gap-3 flex-wrap">
			<a href="<?php echo esc_url( $blog_list_url ); ?>" class="btn btn-outline btn-sm">&rarr; العودة</a>
			<div>
				<h1><?php echo $is_new_cat ? 'إضافة تصنيف جديد' : 'تحرير: ' . esc_html( $edit_cat_data['name'] ); ?></h1>
				<p><?php echo $is_new_cat ? 'إنشاء تصنيف جديد للمدونة' : 'تعديل اسم ووصف التصنيف'; ?></p>
			</div>
		</div>
	</div>

	<form id="yamamahBlogCategoryForm">
		<input type="hidden" name="term_id" id="blogCategoryId" value="<?php echo $is_new_cat ? '0' : (int) $edit_cat_data['id']; ?>">

		<div class="card space-y-4 mb-4">
			<div class="form-field">
				<label>اسم التصنيف</label>
				<input type="text" name="name" id="blogCategoryName" class="form-control" required
					value="<?php echo $is_new_cat ? '' : esc_attr( $edit_cat_data['name'] ); ?>">
			</div>
			<div class="form-field">
				<label>المسار (slug) — اختياري</label>
				<input type="text" name="slug" id="blogCategorySlug" class="form-control ltr" dir="ltr"
					value="<?php echo $is_new_cat ? '' : esc_attr( $edit_cat_data['slug'] ); ?>"
					placeholder="يُولَّد تلقائياً من الاسم إن تُرك فارغاً">
			</div>
			<div class="form-field">
				<label>الوصف — اختياري</label>
				<textarea name="description" id="blogCategoryDescription" class="form-control" rows="3"><?php
					echo $is_new_cat ? '' : esc_textarea( $edit_cat_data['description'] );
				?></textarea>
			</div>
			<?php if ( ! $is_new_cat && ! empty( $edit_cat_data['is_default'] ) ) : ?>
				<p class="text-sm text-muted" style="margin:0;">هذا التصنيف الافتراضي — لا يمكن حذفه.</p>
			<?php endif; ?>
		</div>

		<div class="flex gap-3 flex-wrap">
			<button type="submit" class="btn btn-green" id="blogCategorySubmitBtn"><?php echo $is_new_cat ? 'حفظ التصنيف' : 'حفظ التغييرات'; ?></button>
			<a href="<?php echo esc_url( $blog_list_url ); ?>" class="btn btn-outline">إلغاء</a>
		</div>
		<div id="blogCategorySaveResult" class="alert mt-3" style="display:none;"></div>
	</form>

<?php else : ?>

	<div class="page-header flex justify-between items-center flex-wrap gap-3">
		<div>
			<h1>المدونة</h1>
			<p>إدارة المقالات والتصنيفات وإعدادات صفحة المدونة (<?php echo count( $blog_posts ); ?> مقال · <?php echo count( $blog_categories ); ?> تصنيف)</p>
		</div>
		<div class="flex gap-2 flex-wrap">
			<a href="<?php echo esc_url( add_query_arg( 'edit_cat', 'new', $blog_list_url ) ); ?>" class="btn btn-outline">+ إضافة تصنيف</a>
			<a href="<?php echo esc_url( add_query_arg( 'edit', 'new', $blog_list_url ) ); ?>" class="btn btn-green">+ إضافة مقال</a>
		</div>
	</div>

	<?php if ( ! $blog_page ) : ?>
	<div class="card mb-4">
		<div class="card-body flex justify-between items-center flex-wrap gap-3">
			<p class="text-muted text-sm" style="margin:0;">صفحة أرشيف المدونة (<span class="ltr">/blog</span>) غير موجودة بعد.</p>
			<button type="button" class="btn btn-sm btn-green" id="yamamahSyncBlogPageBtn">إنشاء صفحة المدونة</button>
		</div>
	</div>
	<?php endif; ?>

	<h2 class="text-lg mb-3" style="font-weight:600;">تصنيفات المدونة</h2>
	<div class="yamamah-policy-grid yamamah-policy-grid--compact mb-4">
		<?php if ( ! empty( $blog_categories ) ) : ?>
			<?php foreach ( $blog_categories as $cat ) :
				$cat_edit_url = add_query_arg( 'edit_cat', (int) $cat['id'], $blog_list_url );
				$cat_link     = ! empty( $cat['link'] ) ? (string) $cat['link'] : '';
				?>
			<div class="yamamah-policy-card yamamah-policy-card--compact card" data-term-id="<?php echo (int) $cat['id']; ?>">
				<div class="yamamah-blog-cat-card__head">
					<h3 class="yamamah-blog-cat-card__title text-green"><?php echo esc_html( $cat['name'] ); ?></h3>
					<?php if ( ! empty( $cat['is_default'] ) ) : ?>
						<span class="badge badge-yellow">افتراضي</span>
					<?php else : ?>
						<span class="badge badge-green"><?php echo (int) $cat['count']; ?> مقال</span>
					<?php endif; ?>
				</div>
				<p class="yamamah-blog-cat-card__meta text-muted">
					<span class="ltr">/<?php echo esc_html( $cat['slug'] ); ?></span>
				</p>
				<div class="yamamah-blog-cat-card__actions flex gap-2 flex-wrap">
					<a href="<?php echo esc_url( $cat_edit_url ); ?>" class="btn btn-sm btn-green">تحرير</a>
					<?php if ( $cat_link ) : ?>
						<a href="<?php echo esc_url( $cat_link ); ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener">معاينة</a>
					<?php endif; ?>
					<?php if ( empty( $cat['is_default'] ) ) : ?>
						<button type="button" class="btn btn-sm btn-outline btn-delete-blog-category">حذف</button>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php if ( empty( $blog_categories ) ) : ?>
	<div class="card mb-4">
		<p class="text-center text-muted p-4" style="margin:0;">لا توجد تصنيفات بعد. اضغط «+ إضافة تصنيف» للبدء.</p>
	</div>
	<?php endif; ?>

	<h2 class="text-lg mb-3" style="font-weight:600;">المقالات</h2>
	<div class="yamamah-policy-grid mb-4">
		<?php if ( ! empty( $blog_posts ) ) : ?>
			<?php foreach ( $blog_posts as $post ) :
				$edit_url = add_query_arg( 'edit', (int) $post['id'], $blog_list_url );
				$is_pub   = ( $post['status'] === 'publish' );
				?>
			<div class="yamamah-policy-card card space-y-3" data-post-id="<?php echo (int) $post['id']; ?>">
				<div class="flex justify-between items-start gap-2">
					<h3 class="text-green" style="margin:0;line-height:1.4;"><?php echo esc_html( $post['title'] ); ?></h3>
					<span class="badge <?php echo $is_pub ? 'badge-green' : 'badge-yellow'; ?>">
						<?php echo esc_html( $status_labels[ $post['status'] ] ?? $post['status'] ); ?>
					</span>
				</div>

				<?php if ( ! empty( $post['featured_image_url'] ) ) : ?>
					<img src="<?php echo esc_url( $post['featured_image_url'] ); ?>" alt=""
						style="width:100%;height:140px;border-radius:8px;object-fit:cover;">
				<?php endif; ?>

				<p class="text-sm text-muted" style="margin:0;">
					<?php if ( ! empty( $post['category_name'] ) ) : ?>
						التصنيف: <?php echo esc_html( $post['category_name'] ); ?>
						<span style="opacity:0.5;"> · </span>
					<?php endif; ?>
					<span class="ltr" style="direction:ltr;"><?php echo esc_html( $post['date'] ); ?></span>
				</p>

				<?php if ( ! empty( $post['excerpt'] ) ) : ?>
					<p class="text-sm text-muted" style="margin:0;"><?php echo esc_html( wp_trim_words( $post['excerpt'], 18, '…' ) ); ?></p>
				<?php elseif ( ! empty( $post['content'] ) ) : ?>
					<p class="text-sm text-muted" style="margin:0;"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post['content'] ), 18, '…' ) ); ?></p>
				<?php endif; ?>

				<div class="flex gap-2 flex-wrap">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-sm btn-green">تحرير</a>
					<?php if ( ! empty( $post['permalink'] ) ) : ?>
						<a href="<?php echo esc_url( $post['permalink'] ); ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener">معاينة</a>
					<?php endif; ?>
					<button type="button" class="btn btn-sm btn-outline btn-delete-blog-post">حذف</button>
				</div>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php if ( empty( $blog_posts ) ) : ?>
	<div class="card mb-4">
		<p class="text-center text-muted p-5" style="margin:0;">لا توجد مقالات بعد. اضغط «+ إضافة مقال» للبدء.</p>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $manifest ) ) : ?>
	<form id="yamamahBlogContentForm">
		<div class="card space-y-4 mb-4">
			<h3 class="text-green">إعدادات عرض صفحة المدونة</h3>
			<p class="text-sm text-muted" style="margin-top:-0.5rem;">النصوص والخيارات المعروضة في أرشيف المدونة</p>
			<?php foreach ( $manifest as $field_key => $field_def ) :
				$key = sanitize_key( (string) $field_key );
				if ( $key === '' ) {
					continue;
				}
				$label       = is_array( $field_def ) ? ( $field_def['label'] ?? $key ) : (string) $field_def;
				$type        = is_array( $field_def ) && isset( $field_def['type'] ) ? (string) $field_def['type'] : 'text';
				$min         = is_array( $field_def ) && isset( $field_def['min'] ) ? (int) $field_def['min'] : 0;
				$max         = is_array( $field_def ) && isset( $field_def['max'] ) ? (int) $field_def['max'] : 0;
				$placeholder = is_array( $field_def ) && isset( $field_def['placeholder'] ) ? (string) $field_def['placeholder'] : '';
				$value       = isset( $content[ $key ] ) ? (string) $content[ $key ] : '';
				?>
			<div class="form-field">
				<label><?php echo esc_html( $label ); ?></label>
				<?php if ( $type === 'number' ) : ?>
					<input type="number" class="form-control" name="blog_content[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( (string) $min ); ?>"
						<?php if ( $max > 0 ) : ?>max="<?php echo esc_attr( (string) $max ); ?>"<?php endif; ?>
						<?php if ( $placeholder !== '' ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?> step="1">
				<?php else : ?>
					<input type="text" class="form-control" name="blog_content[<?php echo esc_attr( $key ); ?>]"
						value="<?php echo esc_attr( $value ); ?>"
						<?php if ( $placeholder !== '' ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="btn btn-green" id="yamamahSaveBlogBtn">حفظ إعدادات العرض</button>
		<span id="yamamahBlogSaveMsg" style="margin-right:12px;font-size:0.9rem;"></span>
	</form>
	<?php endif; ?>

<?php endif; ?>

</div>

<script>
(function($) {
	var blogSourceMode = false;

	window.yamamahBlogExecCmd = function(cmd, val) {
		document.execCommand(cmd, false, val || null);
		$('#blogPostEditor').focus();
	};
	window.yamamahBlogInsertHeading = function() {
		document.execCommand('formatBlock', false, '<h3>');
		$('#blogPostEditor').focus();
	};
	window.yamamahBlogInsertLink = function() {
		var url = prompt('أدخل الرابط:', 'https://');
		if (url) document.execCommand('createLink', false, url);
	};
	window.yamamahBlogToggleSource = function() {
		blogSourceMode = !blogSourceMode;
		if (blogSourceMode) {
			$('#blogPostContent').val($('#blogPostEditor').html()).show();
			$('#blogPostEditor').hide();
			$('#yamamahBlogSourceBtn').addClass('active');
		} else {
			$('#blogPostEditor').html($('#blogPostContent').val()).show();
			$('#blogPostContent').hide();
			$('#yamamahBlogSourceBtn').removeClass('active');
		}
	};

	function syncEditorToTextarea() {
		if (!blogSourceMode) {
			$('#blogPostContent').val($('#blogPostEditor').html());
		}
	}

	$('#blogPickFeatured').on('click', function(e) {
		e.preventDefault();
		if (typeof wp === 'undefined' || !wp.media) {
			alert('مكتبة الوسائط غير متاحة');
			return;
		}
		var frame = wp.media({ multiple: false, library: { type: 'image' } });
		frame.on('select', function() {
			var att = frame.state().get('selection').first().toJSON();
			if (att && att.id) {
				$('#blogFeaturedImageId').val(att.id);
				var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				$('#blogFeaturedPreview').attr('src', url).show();
				$('#blogClearFeatured').show();
			}
		});
		frame.open();
	});

	$('#blogClearFeatured').on('click', function() {
		$('#blogFeaturedImageId').val('0');
		$('#blogFeaturedPreview').hide().attr('src', '');
		$(this).hide();
	});

	$('#yamamahBlogPostForm').on('submit', function(e) {
		e.preventDefault();
		syncEditorToTextarea();
		var $btn = $('#blogPostSubmitBtn');
		var $result = $('#blogPostSaveResult');
		$btn.prop('disabled', true);

		$.post(yamamahData.ajax_url, {
			action: 'yamamah_save_blog_post',
			nonce: yamamahData.nonce,
			post_id: $('#blogPostId').val(),
			title: $('#blogPostTitle').val(),
			content: $('#blogPostContent').val(),
			excerpt: $('#blogPostExcerpt').val(),
			status: $('#blogPostStatus').val(),
			category_id: $('#blogPostCategory').val(),
			featured_image: $('#blogFeaturedImageId').val()
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$result.removeClass('alert-error').addClass('alert-success').text('تم الحفظ بنجاح').show();
				var redirect = '<?php echo esc_js( $blog_list_url ); ?>';
				if (res.data && res.data.post_id) {
					redirect = '<?php echo esc_js( admin_url( 'admin.php?page=yamamah-blog-settings&edit=' ) ); ?>' + res.data.post_id;
				}
				setTimeout(function() { window.location.href = redirect; }, 600);
			} else {
				var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
				$result.removeClass('alert-success').addClass('alert-error').text(msg).show();
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$result.removeClass('alert-success').addClass('alert-error').text('خطأ في الاتصال').show();
		});
	});

	$(document).on('click', '.btn-delete-blog-post', function() {
		if (!confirm('هل تريد حذف هذا المقال؟')) return;
		var $card = $(this).closest('.yamamah-policy-card');
		var postId = $card.data('post-id');

		$.post(yamamahData.ajax_url, {
			action: 'yamamah_delete_blog_post',
			nonce: yamamahData.nonce,
			post_id: postId
		}, function(res) {
			if (res.success) {
				$card.fadeOut(300, function() { $(this).remove(); });
			} else {
				var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
				alert(msg);
			}
		}).fail(function() {
			alert('خطأ في الاتصال');
		});
	});

	$('#yamamahBlogCategoryForm').on('submit', function(e) {
		e.preventDefault();
		var $btn = $('#blogCategorySubmitBtn');
		var $result = $('#blogCategorySaveResult');
		$btn.prop('disabled', true);

		$.post(yamamahData.ajax_url, {
			action: 'yamamah_save_blog_category',
			nonce: yamamahData.nonce,
			term_id: $('#blogCategoryId').val(),
			name: $('#blogCategoryName').val(),
			slug: $('#blogCategorySlug').val(),
			description: $('#blogCategoryDescription').val()
		}, function(res) {
			$btn.prop('disabled', false);
			if (res.success) {
				$result.removeClass('alert-error').addClass('alert-success').text('تم الحفظ بنجاح').show();
				setTimeout(function() { window.location.href = '<?php echo esc_js( $blog_list_url ); ?>'; }, 600);
			} else {
				var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
				$result.removeClass('alert-success').addClass('alert-error').text(msg).show();
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$result.removeClass('alert-success').addClass('alert-error').text('خطأ في الاتصال').show();
		});
	});

	$(document).on('click', '.btn-delete-blog-category', function() {
		if (!confirm('هل تريد حذف هذا التصنيف؟ المقالات المرتبطة ستُنقل للتصنيف الافتراضي.')) return;
		var $card = $(this).closest('.yamamah-policy-card');
		var termId = $card.data('term-id');

		$.post(yamamahData.ajax_url, {
			action: 'yamamah_delete_blog_category',
			nonce: yamamahData.nonce,
			term_id: termId
		}, function(res) {
			if (res.success) {
				$card.fadeOut(300, function() { $(this).remove(); });
			} else {
				var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
				alert(msg);
			}
		}).fail(function() {
			alert('خطأ في الاتصال');
		});
	});

	$('#yamamahSyncBlogPageBtn').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).text('جاري الإنشاء...');
		$.post(yamamahData.ajax_url, {
			action: 'yamamah_sync_theme_pages',
			nonce: yamamahData.nonce
		}, function(res) {
			if (res.success) {
				location.reload();
			} else {
				$btn.prop('disabled', false).text('إنشاء الصفحة');
				var msg = (res.data && res.data.message) ? res.data.message : (res.data || 'حدث خطأ');
				alert(msg);
			}
		}).fail(function() {
			$btn.prop('disabled', false).text('إنشاء الصفحة');
			alert('خطأ في الاتصال');
		});
	});

	$('#yamamahSaveBlogBtn').on('click', function() {
		var $btn = $(this);
		var $msg = $('#yamamahBlogSaveMsg');
		var contentData = {};

		$('#yamamahBlogContentForm').find('input[name], textarea[name]').each(function() {
			var match = this.name && this.name.match(/blog_content\[(.*?)\]/);
			if (match) contentData[match[1]] = $(this).val();
		});

		$btn.prop('disabled', true).text('جاري الحفظ...');
		$msg.text('');

		$.post(yamamahData.ajax_url, {
			action: 'yamamah_save_blog_page_settings',
			nonce: yamamahData.nonce,
			content: contentData
		}, function(res) {
			$btn.prop('disabled', false).text('حفظ إعدادات العرض');
			if (res.success) {
				$msg.css('color', '#16a34a').text('تم الحفظ بنجاح');
				setTimeout(function() { $msg.text(''); }, 3000);
			} else {
				$msg.css('color', '#ef4444').text(res.data || 'حدث خطأ');
			}
		}).fail(function() {
			$btn.prop('disabled', false).text('حفظ إعدادات العرض');
			$msg.css('color', '#ef4444').text('خطأ في الاتصال');
		});
	});
})(jQuery);
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
