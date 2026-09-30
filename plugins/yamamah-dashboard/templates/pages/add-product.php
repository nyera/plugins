<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$_store_type = yamamah_get_store_type();
$_is_booking_or_rental = in_array( $_store_type, array( 'booking', 'rental' ), true );
$labels = yamamah_get_catalog_labels();

$is_edit    = ! empty( $product );

if ( $_store_type === 'booking' ) {
    $page_title = $is_edit ? 'تعديل الخدمة' : 'إضافة خدمة جديدة';
    $page_desc  = $is_edit ? 'تعديل بيانات الخدمة' : 'أضف خدمة جديدة';
    $noun       = 'الخدمة';
    $noun_indef = 'خدمة';
} elseif ( $_store_type === 'rental' ) {
    $page_title = $is_edit ? 'تعديل الأصل' : 'إضافة أصل جديد';
    $page_desc  = $is_edit ? 'تعديل بيانات الأصل' : 'أضف أصل جديد للتأجير';
    $noun       = 'الأصل';
    $noun_indef = 'أصل';
} else {
    $page_title = $is_edit ? 'تعديل المنتج' : 'إضافة منتج جديد';
    $page_desc  = $is_edit ? 'تعديل بيانات المنتج' : 'أضف منتج جديد إلى المتجر';
    $noun       = 'المنتج';
    $noun_indef = 'منتج';
}

$p_name       = $is_edit ? $product->get_name() : '';
$p_short_desc = $is_edit ? $product->get_short_description() : '';
$p_slug       = $is_edit ? $product->get_slug() : '';
$p_sku        = $is_edit ? $product->get_sku() : '';
$p_desc       = $is_edit ? $product->get_description() : '';
$p_type       = $is_edit ? $product->get_type() : 'simple';
if ( $_store_type === 'rental' && ! in_array( $p_type, array( 'simple', 'grouped' ), true ) ) {
    $p_type = 'simple';
}
$p_price      = $is_edit ? $product->get_regular_price() : '';
$p_sale_price = $is_edit ? $product->get_sale_price() : '';
$p_stock      = $is_edit ? $product->get_stock_quantity() : '';
$p_manage     = $is_edit ? $product->get_manage_stock() : false;
$p_stock_status = $is_edit ? $product->get_stock_status() : 'instock';
$p_backorders = $is_edit ? $product->get_backorders() : 'no';
$p_status     = $is_edit ? $product->get_status() : 'publish';
$p_weight     = $is_edit ? $product->get_weight() : '';
$p_length     = $is_edit ? $product->get_length() : '';
$p_width      = $is_edit ? $product->get_width() : '';
$p_height     = $is_edit ? $product->get_height() : '';
$p_image_id   = $is_edit ? $product->get_image_id() : 0;
$p_image_url  = $p_image_id ? wp_get_attachment_image_url( $p_image_id, 'medium' ) : '';
$p_cat_ids    = $is_edit ? $product->get_category_ids() : array();
$p_tag_ids    = $is_edit ? $product->get_tag_ids() : array();
$p_virtual    = $is_edit ? $product->get_virtual() : false;
$p_downloadable = $is_edit ? $product->get_downloadable() : false;
$p_purchase_note = $is_edit ? $product->get_purchase_note() : '';
$p_upsell_ids    = $is_edit ? $product->get_upsell_ids() : array();
$p_cross_sell_ids = $is_edit ? $product->get_cross_sell_ids() : array();

$p_deposit_enabled   = $is_edit ? get_post_meta( $product->get_id(), '_deposit_enabled', true ) : '';
$p_deposit_type      = $is_edit ? get_post_meta( $product->get_id(), '_deposit_type', true ) : 'percentage';
$p_deposit_value     = $is_edit ? get_post_meta( $product->get_id(), '_deposit_value', true ) : '';
$p_late_fee_enabled  = $is_edit ? get_post_meta( $product->get_id(), '_late_fee_enabled', true ) : '';
$p_late_fee_type     = $is_edit ? get_post_meta( $product->get_id(), '_late_fee_type', true ) : 'fixed';
$p_late_fee_value    = $is_edit ? get_post_meta( $product->get_id(), '_late_fee_value', true ) : '';
$p_insurance_enabled = $is_edit ? get_post_meta( $product->get_id(), '_insurance_enabled', true ) : '';
$p_insurance_amount  = $is_edit ? get_post_meta( $product->get_id(), '_insurance_amount', true ) : '';

$p_sale_from = '';
$p_sale_to   = '';
if ( $is_edit ) {
    $d_from = $product->get_date_on_sale_from();
    $d_to   = $product->get_date_on_sale_to();
    if ( $d_from ) $p_sale_from = $d_from->date( 'Y-m-d' );
    if ( $d_to )   $p_sale_to   = $d_to->date( 'Y-m-d' );
}

$p_gallery_ids = $is_edit ? $product->get_gallery_image_ids() : array();
$p_gallery_urls = array();
foreach ( $p_gallery_ids as $gid ) {
    $url = wp_get_attachment_image_url( $gid, 'thumbnail' );
    if ( $url ) $p_gallery_urls[ $gid ] = $url;
}

$ext_url = '';
$ext_btn = '';
if ( $is_edit && $p_type === 'external' ) {
    $ext_url = $product->get_product_url();
    $ext_btn = $product->get_button_text();
}

$p_grouped_ids = array();
if ( $is_edit && $p_type === 'grouped' && method_exists( $product, 'get_children' ) ) {
    $p_grouped_ids = $product->get_children();
}

$p_attributes = array();
$p_default_attributes = array();
if ( $is_edit ) {
    $raw_attrs = $product->get_attributes();
    foreach ( $raw_attrs as $attr_key => $attr_obj ) {
        if ( is_a( $attr_obj, 'WC_Product_Attribute' ) ) {
            $p_attributes[] = array(
                'id'            => $attr_obj->get_id(),
                'name'          => $attr_obj->get_name(),
                'options'       => $attr_obj->get_options(),
                'visible'       => $attr_obj->get_visible(),
                'variation'     => $attr_obj->get_variation(),
            );
        }
    }
    if ( $p_type === 'variable' ) {
        $p_default_attributes = $product->get_default_attributes();
    }
}

$p_variations = array();
if ( $is_edit && $p_type === 'variable' ) {
    $var_ids = $product->get_children();
    foreach ( $var_ids as $vid ) {
        $v = wc_get_product( $vid );
        if ( ! $v ) continue;
        $v_attrs_raw = $v->get_attributes();
        $v_attrs_display = array();
        foreach ( $v_attrs_raw as $attr_tax => $attr_slug ) {
            $term = get_term_by( 'slug', $attr_slug, $attr_tax );
            $v_attrs_display[ $attr_tax ] = $term ? $term->name : $attr_slug;
        }
        $p_variations[] = array(
            'id'            => $vid,
            'attributes'    => $v_attrs_display,
            'regular_price' => $v->get_regular_price(),
            'sale_price'    => $v->get_sale_price(),
            'sku'           => $v->get_sku(),
            'stock_qty'     => $v->get_stock_quantity(),
            'manage_stock'  => $v->get_manage_stock(),
            'stock_status'  => $v->get_stock_status(),
            'image_id'      => $v->get_image_id(),
            'image_url'     => $v->get_image_id() ? wp_get_attachment_image_url( $v->get_image_id(), 'thumbnail' ) : '',
            'enabled'       => $v->get_status() === 'publish',
        );
    }
}

$all_simple_products = array();
$simple_q = wc_get_products( array( 'type' => 'simple', 'limit' => 200, 'status' => 'publish', 'return' => 'ids' ) );
foreach ( $simple_q as $sid ) {
    $sp = wc_get_product( $sid );
    if ( $sp && ( ! $is_edit || $sid !== $product_id ) ) {
        $all_simple_products[] = array( 'id' => $sid, 'name' => $sp->get_name() );
    }
}

$all_products_for_links = array();
$link_q = wc_get_products( array( 'limit' => 200, 'status' => 'publish', 'return' => 'ids' ) );
foreach ( $link_q as $lid ) {
    $lp = wc_get_product( $lid );
    if ( $lp && ( ! $is_edit || $lid !== $product_id ) ) {
        $all_products_for_links[] = array( 'id' => $lid, 'name' => $lp->get_name() );
    }
}

ob_start();
?>
<div class="page active" id="page-add-product">
    <div class="page-header flex justify-between items-center">
        <div>
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <p><?php echo esc_html( $page_desc ); ?></p>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-green" onclick="yamamahSaveProduct()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
                <?php echo esc_html( 'حفظ ' . $noun ); ?>
            </button>
            <?php if ( $is_edit ) : ?>
            <a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" target="_blank" class="btn btn-outline">معاينة</a>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" id="yProductId" value="<?php echo esc_attr( $product_id ?? 0 ); ?>">

    <?php if ( $_store_type === 'rental' ) : ?>
    <!-- نوع الأصل: بسيط أو مجمّع -->
    <section class="card p-5 mb-4">
        <div class="card-section-title"><?php echo esc_html( $labels['type_section'] ); ?></div>
        <div class="yamamah-product-types">
            <?php
            $rental_types = array(
                'simple'  => array( $labels['type_simple'], $labels['type_simple_desc'] ),
                'grouped' => array( $labels['type_grouped'], $labels['type_grouped_desc'] ),
            );
            foreach ( $rental_types as $key => $info ) :
            ?>
            <button type="button" class="yamamah-type-card <?php echo $p_type === $key ? 'selected' : ''; ?>" onclick="yamamahSelectProductType('<?php echo esc_attr( $key ); ?>', this)">
                <span class="type-radio"><span class="type-radio-dot"></span></span>
                <span class="type-info">
                    <span class="type-label"><?php echo esc_html( $info[0] ); ?></span>
                    <span class="type-desc"><?php echo esc_html( $info[1] ); ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="hidden" id="yProductType" value="<?php echo esc_attr( $p_type ); ?>">
    </section>
    <?php elseif ( $_store_type === 'booking' ) : ?>
    <!-- الحجوزات: خدمة بسيطة أو مجمّعة -->
    <section class="card p-5 mb-4">
        <div class="card-section-title"><?php echo esc_html( $labels['type_section'] ); ?></div>
        <div class="yamamah-product-types">
            <?php
            $booking_types = array(
                'simple'  => array( $labels['type_simple'], $labels['type_simple_desc'] ),
                'grouped' => array( $labels['type_grouped'], $labels['type_grouped_desc'] ),
            );
            foreach ( $booking_types as $key => $info ) :
            ?>
            <button type="button" class="yamamah-type-card <?php echo $p_type === $key ? 'selected' : ''; ?>" onclick="yamamahSelectProductType('<?php echo esc_attr( $key ); ?>', this)">
                <span class="type-radio"><span class="type-radio-dot"></span></span>
                <span class="type-info">
                    <span class="type-label"><?php echo esc_html( $info[0] ); ?></span>
                    <span class="type-desc"><?php echo esc_html( $info[1] ); ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="hidden" id="yProductType" value="<?php echo esc_attr( in_array( $p_type, array( 'simple', 'grouped' ), true ) ? $p_type : 'simple' ); ?>">
    </section>
    <?php else : ?>
    <!-- نوع المنتج (متاجر retail) -->
    <section class="card p-5 mb-4">
        <div class="card-section-title"><?php echo esc_html( $labels['type_section'] ); ?></div>
        <div class="yamamah-product-types">
            <?php
            $types = array(
                'simple'   => array( $labels['type_simple'], $labels['type_simple_desc'] ),
                'grouped'  => array( $labels['type_grouped'], $labels['type_grouped_desc'] ),
                'variable' => array( $labels['type_variable'], $labels['type_variable_desc'] ),
                'external' => array( $labels['type_external'], $labels['type_external_desc'] ),
            );
            foreach ( $types as $key => $info ) :
            ?>
            <button type="button" class="yamamah-type-card <?php echo $p_type === $key ? 'selected' : ''; ?>" onclick="yamamahSelectProductType('<?php echo esc_attr( $key ); ?>', this)">
                <span class="type-radio"><span class="type-radio-dot"></span></span>
                <span class="type-info">
                    <span class="type-label"><?php echo esc_html( $info[0] ); ?></span>
                    <span class="type-desc"><?php echo esc_html( $info[1] ); ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="hidden" id="yProductType" value="<?php echo esc_attr( $p_type ); ?>">

        <!-- خيارات سريعة: افتراضي / قابل للتنزيل -->
        <div id="virtualDownloadableRow" class="flex gap-4 mt-3" style="<?php echo in_array( $p_type, array( 'grouped', 'external' ) ) ? 'display:none;' : ''; ?>">
            <label style="cursor:pointer;display:flex;align-items:center;gap:0.35rem;font-size:0.875rem;">
                <input type="checkbox" id="yVirtual" value="1" <?php checked( $p_virtual ); ?>> منتج افتراضي (لا يحتاج شحن)
            </label>
            <label style="cursor:pointer;display:flex;align-items:center;gap:0.35rem;font-size:0.875rem;">
                <input type="checkbox" id="yDownloadable" value="1" <?php checked( $p_downloadable ); ?>> قابل للتنزيل
            </label>
        </div>
    </section>
    <?php endif; ?>

    <!-- المعلومات الأساسية -->
    <section class="card p-5 mb-4">
        <div class="card-section-title">المعلومات الأساسية</div>
        <div class="form-field" style="text-align:center;">
            <label>الصورة الرئيسية <span class="required">*</span></label>
            <div class="yamamah-product-image-upload" id="productImageUpload" onclick="yamamahUploadProductImage()">
                <?php if ( $p_image_url ) : ?>
                <div class="upload-preview">
                    <img src="<?php echo esc_url( $p_image_url ); ?>" alt="">
                    <button type="button" class="upload-remove" onclick="event.stopPropagation(); yamamahRemoveProductImage();">&times;</button>
                </div>
                <?php else : ?>
                <div class="upload-empty">
                    <div class="upload-icon-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                    <p class="text-xs" style="color:var(--green);">اختر صورة <span class="text-muted">أو اسحبها هنا</span></p>
                </div>
                <?php endif; ?>
            </div>
            <input type="hidden" id="yProductImageId" value="<?php echo esc_attr( $p_image_id ); ?>">
        </div>

        <!-- معرض الصور -->
        <div class="form-field">
            <label>معرض الصور</label>
            <div id="galleryContainer" class="flex gap-2 flex-wrap mb-2">
                <?php foreach ( $p_gallery_urls as $gid => $gurl ) : ?>
                <div class="yamamah-gallery-thumb" data-id="<?php echo esc_attr( $gid ); ?>">
                    <img src="<?php echo esc_url( $gurl ); ?>" alt="">
                    <button type="button" class="upload-remove" onclick="yamamahRemoveGalleryImage(this)">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline" onclick="yamamahAddGalleryImages()">+ إضافة صور</button>
        </div>

        <div class="form-field">
            <label><?php echo esc_html( 'اسم ' . $noun ); ?> <span class="required">*</span></label>
            <input type="text" id="yProductName" value="<?php echo esc_attr( $p_name ); ?>" placeholder="<?php echo esc_attr( 'أدخل اسم ' . $noun ); ?>">
        </div>
        <div class="form-field">
            <label>وصف قصير</label>
            <input type="text" id="yProductShortDesc" value="<?php echo esc_attr( $p_short_desc ); ?>" placeholder="وصف قصير">
        </div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>Slug</label>
                <input type="text" id="yProductSlug" value="<?php echo esc_attr( $p_slug ); ?>" placeholder="product-slug" dir="ltr">
            </div>
            <div class="form-field">
                <label>SKU</label>
                <input type="text" id="yProductSku" value="<?php echo esc_attr( $p_sku ); ?>" placeholder="PROD-001" dir="ltr">
            </div>
        </div>
        <div class="form-field">
            <label>الوصف</label>
            <?php
            if ( function_exists( 'wp_editor' ) ) {
                wp_editor(
                    $p_desc,
                    'yProductDesc',
                    array(
                        'textarea_name' => 'yProductDesc',
                        'textarea_rows' => 8,
                        'media_buttons' => true,
                        'teeny'         => false,
                        'quicktags'     => true,
                        'editor_class'  => 'yamamah-product-desc-editor',
                    )
                );
            } else {
                ?>
                <textarea id="yProductDesc" rows="6" placeholder="وصف تفصيلي"><?php echo esc_textarea( $p_desc ); ?></textarea>
                <?php
            }
            ?>
        </div>
    </section>

    <!-- السعر والمخزون (بسيط + خارجي) -->
    <section class="card p-5 mb-4 yamamah-type-section" id="sectionPricing" style="<?php echo in_array( $p_type, array( 'grouped', 'variable' ) ) ? 'display:none' : ''; ?>">
        <div class="card-section-title"><?php echo $_store_type === 'rental' ? 'سعر الإيجار والتوفر' : ( $_store_type === 'booking' ? 'سعر الخدمة' : 'السعر والمخزون' ); ?></div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>السعر (ر.س) <span class="required">*</span></label>
                <input type="number" id="yProductPrice" value="<?php echo esc_attr( $p_price ); ?>" placeholder="0.00" dir="ltr" step="0.01">
            </div>
            <div class="form-field">
                <label>سعر التخفيض (ر.س)</label>
                <input type="number" id="yProductSalePrice" value="<?php echo esc_attr( $p_sale_price ); ?>" placeholder="0.00" dir="ltr" step="0.01">
            </div>
        </div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>بداية التخفيض</label>
                <input type="date" id="ySaleFrom" class="ltr" lang="en" dir="ltr" value="<?php echo esc_attr( $p_sale_from ); ?>">
            </div>
            <div class="form-field">
                <label>نهاية التخفيض</label>
                <input type="date" id="ySaleTo" class="ltr" lang="en" dir="ltr" value="<?php echo esc_attr( $p_sale_to ); ?>">
            </div>
        </div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>حالة النشر</label>
                <select id="yProductStatus">
                    <option value="publish" <?php selected( $p_status, 'publish' ); ?>>منشور</option>
                    <option value="draft" <?php selected( $p_status, 'draft' ); ?>>مسودة</option>
                    <option value="pending" <?php selected( $p_status, 'pending' ); ?>>قيد المراجعة</option>
                </select>
            </div>
            <div class="form-field">
                <label>إدارة المخزون</label>
                <select id="yManageStock" onchange="yamamahToggleStockFields()">
                    <option value="no" <?php echo ! $p_manage ? 'selected' : ''; ?>>بدون إدارة كمية</option>
                    <option value="yes" <?php echo $p_manage ? 'selected' : ''; ?>>إدارة الكمية</option>
                </select>
            </div>
        </div>
        <div id="stockQtyRow" class="yamamah-form-row-2" style="<?php echo $p_manage ? '' : 'display:none;'; ?>">
            <div class="form-field">
                <label>الكمية</label>
                <input type="number" id="yProductStock" value="<?php echo esc_attr( $p_stock ); ?>" placeholder="0" dir="ltr">
            </div>
            <div class="form-field">
                <label>السماح بالطلب المسبق</label>
                <select id="yBackorders">
                    <option value="no" <?php selected( $p_backorders, 'no' ); ?>>لا</option>
                    <option value="notify" <?php selected( $p_backorders, 'notify' ); ?>>نعم مع إشعار</option>
                    <option value="yes" <?php selected( $p_backorders, 'yes' ); ?>>نعم</option>
                </select>
            </div>
        </div>
        <div id="stockStatusRow" style="<?php echo $p_manage ? 'display:none;' : ''; ?>">
            <div class="form-field">
                <label>حالة المخزون</label>
                <select id="yStockStatus">
                    <option value="instock" <?php selected( $p_stock_status, 'instock' ); ?>>متوفر</option>
                    <option value="outofstock" <?php selected( $p_stock_status, 'outofstock' ); ?>>نفد المخزون</option>
                    <option value="onbackorder" <?php selected( $p_stock_status, 'onbackorder' ); ?>>طلب مسبق</option>
                </select>
            </div>
        </div>
    </section>

    <!-- حالة النشر (للمتعدد والمجمّع) -->
    <section class="card p-5 mb-4 yamamah-type-section" id="sectionStatusOnly" style="<?php echo in_array( $p_type, array( 'grouped', 'variable' ) ) ? '' : 'display:none'; ?>">
        <div class="card-section-title">حالة النشر</div>
        <div class="form-field">
            <select id="yProductStatusAlt">
                <option value="publish" <?php selected( $p_status, 'publish' ); ?>>منشور</option>
                <option value="draft" <?php selected( $p_status, 'draft' ); ?>>مسودة</option>
                <option value="pending" <?php selected( $p_status, 'pending' ); ?>>قيد المراجعة</option>
            </select>
        </div>
    </section>

    <!-- إعدادات المنتج الخارجي -->
    <section class="card p-5 mb-4 yamamah-type-section" id="sectionExternal" style="display:<?php echo $p_type === 'external' ? 'block' : 'none'; ?>">
        <div class="card-section-title">إعدادات المنتج الخارجي</div>
        <div class="form-field">
            <label>رابط المنتج الخارجي <span class="required">*</span></label>
            <input type="url" id="yExternalUrl" value="<?php echo esc_attr( $ext_url ); ?>" placeholder="https://www.example.com/product" dir="ltr">
        </div>
        <div class="form-field">
            <label>نص زر الشراء</label>
            <input type="text" id="yExternalBtn" value="<?php echo esc_attr( $ext_btn ); ?>" placeholder="اذهب إلى المتجر">
        </div>
    </section>

    <!-- المنتجات المرتبطة (مجمّع) -->
    <section class="card p-5 mb-4 yamamah-type-section" id="sectionGrouped" style="display:<?php echo $p_type === 'grouped' ? 'block' : 'none'; ?>">
        <div class="card-section-title"><?php echo esc_html( $labels['grouped_section'] ); ?></div>
        <div class="form-field">
            <label><?php echo esc_html( $labels['grouped_pick'] ); ?></label>
            <div class="yamamah-checkbox-list" style="max-height:200px;overflow-y:auto;">
                <?php if ( ! empty( $all_simple_products ) ) : foreach ( $all_simple_products as $sp ) : ?>
                <label class="yamamah-cb-item">
                    <input type="checkbox" name="grouped_products[]" value="<?php echo esc_attr( $sp['id'] ); ?>" <?php echo in_array( $sp['id'], $p_grouped_ids ) ? 'checked' : ''; ?>>
                    <?php echo esc_html( $sp['name'] ); ?>
                </label>
                <?php endforeach; else : ?>
                <p class="text-xs text-muted" style="padding:0.5rem;"><?php echo esc_html( $labels['no_simple_items'] ); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- سمات المنتج (متعدد + بسيط) -->
    <section class="card p-5 mb-4" id="sectionAttributes" style="<?php echo $p_type === 'external' ? 'display:none;' : ''; ?>">
        <div class="card-section-title flex justify-between items-center">
            <span><?php echo esc_html( $_is_booking_or_rental ? 'السمات' : 'سمات المنتج' ); ?></span>
            <div class="yamamah-attr-add-wrapper" style="position:relative;">
                <button type="button" class="btn btn-sm btn-outline" id="addAttributeBtn" onclick="yamamahToggleAttrDropdown(this);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    إضافة سمة
                </button>
                <div id="attrDropdownMenu" class="yamamah-dropdown-menu" style="display:none;">
                    <?php if ( ! empty( $attributes ) ) : foreach ( $attributes as $attr ) : ?>
                    <button type="button" class="yamamah-dropdown-item" data-attr-id="<?php echo esc_attr( $attr->attribute_id ); ?>" data-taxonomy="pa_<?php echo esc_attr( $attr->attribute_name ); ?>" data-label="<?php echo esc_attr( $attr->attribute_label ); ?>" onclick="yamamahAddAttributeFromMenu(this)">
                        <?php echo esc_html( $attr->attribute_label ); ?>
                    </button>
                    <?php endforeach; else : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=yamamah-attributes' ) ); ?>" class="yamamah-dropdown-item" style="color:var(--green);text-decoration:none;">
                        لا يوجد سمات — اذهب لإضافة سمة
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div id="productAttributesList">
            <?php foreach ( $p_attributes as $idx => $pa ) :
                $tax = $pa['name'];
                $attr_label = wc_attribute_label( $tax );
                $all_terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
                if ( is_wp_error( $all_terms ) ) $all_terms = array();
            ?>
            <div class="yamamah-attr-row" data-taxonomy="<?php echo esc_attr( $tax ); ?>" data-attr-id="<?php echo esc_attr( $pa['id'] ); ?>">
                <div class="flex justify-between items-center mb-2">
                    <strong class="text-sm"><?php echo esc_html( $attr_label ); ?></strong>
                    <button type="button" class="btn btn-sm btn-outline" style="color:var(--red);" onclick="jQuery(this).closest('.yamamah-attr-row').remove();">حذف</button>
                </div>
                <div class="flex gap-2 flex-wrap mb-2">
                    <?php foreach ( $all_terms as $term ) : ?>
                    <label style="font-size:0.8rem;cursor:pointer;display:flex;align-items:center;gap:0.25rem;">
                        <input type="checkbox" class="attr-term-cb" value="<?php echo esc_attr( $term->term_id ); ?>"
                               <?php echo in_array( $term->term_id, $pa['options'] ) ? 'checked' : ''; ?>>
                        <?php echo esc_html( $term->name ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-3" style="font-size:0.8rem;">
                    <label style="cursor:pointer;display:flex;align-items:center;gap:0.25rem;">
                        <input type="checkbox" class="attr-visible-cb" value="1" <?php checked( $pa['visible'] ); ?>> <?php echo esc_html( $labels['visible_on_item_page'] ); ?>
                    </label>
                    <label class="attr-variation-label" style="cursor:pointer;display:<?php echo $p_type !== 'variable' ? 'none' : 'flex'; ?>;align-items:center;gap:0.25rem;">
                        <input type="checkbox" class="attr-variation-cb" value="1" <?php checked( $pa['variation'] ); ?>> مستخدمة للتنويعات
                    </label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- التنويعات (متعدد) -->
    <section class="card p-5 mb-4 yamamah-type-section" id="sectionVariations" style="display:<?php echo $p_type === 'variable' ? 'block' : 'none'; ?>">
        <div class="card-section-title flex justify-between items-center">
            <span>التنويعات</span>
            <div class="flex gap-2">
                <button type="button" class="btn btn-sm btn-outline" onclick="yamamahGenerateVariations()">إنشاء التنويعات من السمات</button>
                <button type="button" class="btn btn-sm btn-outline" onclick="yamamahAddSingleVariation()">+ تنويع يدوي</button>
            </div>
        </div>
        <div id="variationsList">
            <?php foreach ( $p_variations as $vidx => $var ) : ?>
            <div class="yamamah-variation-row" data-variation-id="<?php echo esc_attr( $var['id'] ); ?>">
                <div class="flex justify-between items-center mb-2">
                    <strong class="text-sm">تنويع #<?php echo esc_html( $var['id'] ); ?></strong>
                    <button type="button" class="btn btn-sm btn-outline" style="color:var(--red);font-size:0.75rem;" onclick="jQuery(this).closest('.yamamah-variation-row').remove();">حذف</button>
                </div>
                <div class="yamamah-form-row-2" style="gap:0.5rem;">
                    <?php foreach ( $var['attributes'] as $attr_key => $attr_val ) : ?>
                    <div class="form-field">
                        <label class="text-xs"><?php echo esc_html( wc_attribute_label( $attr_key ) ); ?></label>
                        <input type="text" class="var-attr" data-attr="<?php echo esc_attr( $attr_key ); ?>" value="<?php echo esc_attr( $attr_val ); ?>" style="font-size:0.8rem;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="yamamah-form-row-2" style="gap:0.5rem;margin-top:0.5rem;">
                    <div class="form-field"><label class="text-xs">السعر</label><input type="number" class="var-price" value="<?php echo esc_attr( $var['regular_price'] ); ?>" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>
                    <div class="form-field"><label class="text-xs">سعر التخفيض</label><input type="number" class="var-sale-price" value="<?php echo esc_attr( $var['sale_price'] ); ?>" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>
                    <div class="form-field"><label class="text-xs">SKU</label><input type="text" class="var-sku" value="<?php echo esc_attr( $var['sku'] ); ?>" dir="ltr" style="font-size:0.8rem;"></div>
                    <div class="form-field"><label class="text-xs">الكمية</label><input type="number" class="var-stock" value="<?php echo esc_attr( $var['stock_qty'] ); ?>" dir="ltr" style="font-size:0.8rem;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-muted mt-2" id="noVariationsMsg" style="<?php echo ! empty( $p_variations ) ? 'display:none;' : ''; ?>">
            أضف سمات أعلاه ثم اضغط "إنشاء التنويعات من السمات"
        </p>
    </section>

    <!-- التصنيفات والوسوم -->
    <section class="card p-5 mb-4">
        <div class="card-section-title">التصنيفات والوسوم</div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>التصنيفات</label>
                <div class="yamamah-checkbox-list" style="max-height:180px;overflow-y:auto;">
                    <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : foreach ( $categories as $cat ) : ?>
                    <label class="yamamah-cb-item">
                        <input type="checkbox" name="product_cats[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $p_cat_ids ) ? 'checked' : ''; ?>>
                        <?php echo esc_html( $cat->name ); ?>
                    </label>
                    <?php endforeach; else : ?>
                    <p class="text-xs text-muted" style="padding:0.5rem;">لا يوجد تصنيفات</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-field">
                <label>الوسوم</label>
                <div class="yamamah-checkbox-list" style="max-height:180px;overflow-y:auto;">
                    <?php if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) : foreach ( $tags as $tag ) : ?>
                    <label class="yamamah-cb-item">
                        <input type="checkbox" name="product_tags[]" value="<?php echo esc_attr( $tag->term_id ); ?>" <?php echo in_array( (int) $tag->term_id, array_map( 'intval', (array) $p_tag_ids ), true ) ? 'checked' : ''; ?>>
                        <?php echo esc_html( $tag->name ); ?>
                    </label>
                    <?php endforeach; else : ?>
                    <p class="text-xs text-muted" style="padding:0.5rem;">لا يوجد وسوم</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- المنتجات المرتبطة -->
    <section class="card p-5 mb-4" id="sectionLinkedProducts" style="<?php echo $p_type === 'grouped' ? 'display:none;' : ''; ?>">
        <div class="card-section-title"><?php echo esc_html( $labels['linked_section'] ); ?></div>
        <div class="yamamah-form-row-2">
            <div class="form-field">
                <label>Upsells (بيع إضافي)</label>
                <div class="yamamah-checkbox-list" style="max-height:160px;overflow-y:auto;">
                    <?php if ( ! empty( $all_products_for_links ) ) : foreach ( $all_products_for_links as $lp ) : ?>
                    <label class="yamamah-cb-item">
                        <input type="checkbox" name="upsell_ids[]" value="<?php echo esc_attr( $lp['id'] ); ?>" <?php echo in_array( $lp['id'], $p_upsell_ids ) ? 'checked' : ''; ?>>
                        <?php echo esc_html( $lp['name'] ); ?>
                    </label>
                    <?php endforeach; else : ?>
                    <p class="text-xs text-muted" style="padding:0.5rem;"><?php echo esc_html( $labels['linked_empty'] ); ?></p>
                    <?php endif; ?>
                </div>
                <small class="text-xs text-muted"><?php echo esc_html( $labels['linked_upsell_hint'] ); ?></small>
            </div>
            <div class="form-field">
                <label>Cross-sells (بيع متقاطع)</label>
                <div class="yamamah-checkbox-list" style="max-height:160px;overflow-y:auto;">
                    <?php if ( ! empty( $all_products_for_links ) ) : foreach ( $all_products_for_links as $lp ) : ?>
                    <label class="yamamah-cb-item">
                        <input type="checkbox" name="cross_sell_ids[]" value="<?php echo esc_attr( $lp['id'] ); ?>" <?php echo in_array( $lp['id'], $p_cross_sell_ids ) ? 'checked' : ''; ?>>
                        <?php echo esc_html( $lp['name'] ); ?>
                    </label>
                    <?php endforeach; else : ?>
                    <p class="text-xs text-muted" style="padding:0.5rem;"><?php echo esc_html( $labels['linked_empty'] ); ?></p>
                    <?php endif; ?>
                </div>
                <small class="text-xs text-muted"><?php echo esc_html( $labels['linked_cross_hint'] ); ?></small>
            </div>
        </div>
    </section>

    <?php if ( $_is_booking_or_rental ) : ?>
    <!-- إعدادات الحجز / التأجير -->
    <section class="card p-5 mb-4" id="sectionBookingRental" style="<?php echo $p_type === 'grouped' ? 'display:none;' : ''; ?>">
        <div class="card-section-title"><?php echo $_store_type === 'booking' ? 'إعدادات الحجز' : 'إعدادات التأجير'; ?></div>

        <!-- العربون -->
        <div class="form-field">
            <label style="cursor:pointer;display:flex;align-items:center;gap:0.35rem;font-size:0.9rem;font-weight:600;">
                <input type="checkbox" id="yDepositEnabled" value="1" <?php checked( $p_deposit_enabled, 'yes' ); ?>>
                تفعيل العربون <span style="font-weight:400;color:var(--text-muted);font-size:0.8rem;">(اختياري)</span>
            </label>
        </div>
        <div id="depositFields" class="yamamah-form-row-2" style="<?php echo $p_deposit_enabled === 'yes' ? '' : 'display:none;'; ?>margin-top:0.5rem;">
            <div class="form-field">
                <label>نوع العربون</label>
                <select id="yDepositType">
                    <option value="percentage" <?php selected( $p_deposit_type, 'percentage' ); ?>>نسبة من السعر (%)</option>
                    <option value="fixed" <?php selected( $p_deposit_type, 'fixed' ); ?>>مبلغ ثابت (ر.س)</option>
                </select>
            </div>
            <div class="form-field">
                <label>قيمة العربون</label>
                <input type="number" id="yDepositValue" value="<?php echo esc_attr( $p_deposit_value ); ?>" step="0.01" min="0" dir="ltr" placeholder="0.00">
                <small class="text-muted" id="yDepositValueHint" style="display:block;margin-top:0.35rem;"></small>
            </div>
        </div>

        <?php if ( $_store_type === 'rental' ) : ?>
        <!-- غرامة التأخير -->
        <div class="form-field mt-3">
            <label style="cursor:pointer;display:flex;align-items:center;gap:0.35rem;font-size:0.9rem;font-weight:600;">
                <input type="checkbox" id="yLateFeeEnabled" value="1" <?php checked( $p_late_fee_enabled, 'yes' ); ?>>
                تفعيل غرامة التأخير <span style="font-weight:400;color:var(--text-muted);font-size:0.8rem;">(اختياري — لكل يوم تأخير)</span>
            </label>
        </div>
        <div id="lateFeeFields" class="yamamah-form-row-2" style="<?php echo $p_late_fee_enabled === 'yes' ? '' : 'display:none;'; ?>margin-top:0.5rem;">
            <div class="form-field">
                <label>نوع الغرامة</label>
                <select id="yLateFeeType">
                    <option value="percentage" <?php selected( $p_late_fee_type, 'percentage' ); ?>>نسبة من السعر (%)</option>
                    <option value="fixed" <?php selected( $p_late_fee_type, 'fixed' ); ?>>مبلغ ثابت (ر.س)</option>
                </select>
            </div>
            <div class="form-field">
                <label>قيمة الغرامة</label>
                <input type="number" id="yLateFeeValue" value="<?php echo esc_attr( $p_late_fee_value ); ?>" step="0.01" min="0" dir="ltr" placeholder="0.00">
            </div>
        </div>

        <!-- مبلغ التأمين -->
        <div class="form-field mt-3">
            <label style="cursor:pointer;display:flex;align-items:center;gap:0.35rem;font-size:0.9rem;font-weight:600;">
                <input type="checkbox" id="yInsuranceEnabled" value="1" <?php checked( $p_insurance_enabled, 'yes' ); ?>>
                تفعيل مبلغ التأمين <span style="font-weight:400;color:var(--text-muted);font-size:0.8rem;">(اختياري — مبلغ يُسترد بعد إعادة الأصل)</span>
            </label>
        </div>
        <div id="insuranceFields" style="<?php echo $p_insurance_enabled === 'yes' ? '' : 'display:none;'; ?>margin-top:0.5rem;">
            <div class="form-field">
                <label>مبلغ التأمين (ر.س)</label>
                <input type="number" id="yInsuranceAmount" value="<?php echo esc_attr( $p_insurance_amount ); ?>" step="0.01" min="0" dir="ltr" placeholder="0.00">
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- بيانات الشحن -->
    <section class="card p-5 mb-4" id="sectionShipping" style="<?php echo ( $p_virtual || $_store_type !== 'retail' ) ? 'display:none;' : ''; ?>">
        <div class="card-section-title">بيانات الشحن</div>
        <div class="form-field">
            <label>الوزن (كجم)</label>
            <input type="text" id="yProductWeight" value="<?php echo esc_attr( $p_weight ); ?>" placeholder="0.00" dir="ltr">
        </div>
        <div class="form-field">
            <label>الأبعاد (سم)</label>
            <div class="yamamah-dims-row">
                <div><span class="text-xs text-muted">الطول</span><input type="number" id="yProductLength" value="<?php echo esc_attr( $p_length ); ?>" placeholder="0" dir="ltr"></div>
                <div><span class="text-xs text-muted">العرض</span><input type="number" id="yProductWidth" value="<?php echo esc_attr( $p_width ); ?>" placeholder="0" dir="ltr"></div>
                <div><span class="text-xs text-muted">الارتفاع</span><input type="number" id="yProductHeight" value="<?php echo esc_attr( $p_height ); ?>" placeholder="0" dir="ltr"></div>
            </div>
        </div>
    </section>

    <!-- ملاحظة الشراء -->
    <section class="card p-5 mb-4">
        <div class="card-section-title">ملاحظة الشراء</div>
        <div class="form-field">
            <textarea id="yPurchaseNote" rows="2" placeholder="ملاحظة تظهر للعميل بعد إتمام الطلب (اختياري)"><?php echo esc_textarea( $p_purchase_note ); ?></textarea>
        </div>
    </section>
</div>

<script>
jQuery(document).ready(function($) {
    var yamamahCatalogLabels = <?php echo wp_json_encode( array(
        'visible_on_item_page'  => $labels['visible_on_item_page'],
        'media_picker_title'    => $labels['media_picker_title'],
        'save_success'          => $labels['save_success'],
        'name_required'         => $labels['name_required'],
        'external_url_required' => $labels['external_url_required'],
    ) ); ?>;
    var yamamahIsBookingOrRental = <?php echo $_is_booking_or_rental ? 'true' : 'false'; ?>;

    /* ── نوع المنتج: إظهار/إخفاء الأقسام ── */
    window.yamamahSelectProductType = function(type, el) {
        if (el) {
            $('.yamamah-type-card').removeClass('selected');
            $(el).addClass('selected');
        }
        $('#yProductType').val(type);

        // Reset all type-dependent sections
        $('#sectionPricing, #sectionExternal, #sectionGrouped, #sectionVariations, #sectionStatusOnly').hide();
        $('#virtualDownloadableRow').hide();
        $('#sectionLinkedProducts').show();
        $('#sectionAttributes').show();

        if (type === 'simple') {
            $('#sectionPricing').show();
            $('#virtualDownloadableRow').show();
        } else if (type === 'external') {
            $('#sectionPricing').show();
            $('#sectionExternal').show();
            $('#virtualDownloadableRow').hide();
            $('#sectionAttributes').hide();
            $('#sectionLinkedProducts').show();
        } else if (type === 'grouped') {
            $('#sectionGrouped').show();
            $('#sectionStatusOnly').show();
            $('#sectionLinkedProducts').hide();
        } else if (type === 'variable') {
            $('#sectionVariations').show();
            $('#sectionStatusOnly').show();
            $('#virtualDownloadableRow').show();
        }

        if (yamamahIsBookingOrRental) {
            $('#sectionBookingRental').toggle(type === 'simple');
        }

        // Show/hide variation checkboxes on attributes
        if (type === 'variable') {
            $('.attr-variation-label').show();
        } else {
            $('.attr-variation-label').hide();
        }
    };

    <?php if ( $_store_type === 'rental' || $_store_type === 'retail' || $_store_type === 'booking' ) : ?>
    var $initialTypeCard = $('.yamamah-type-card.selected');
    if ($initialTypeCard.length) {
        yamamahSelectProductType($('#yProductType').val(), $initialTypeCard[0]);
    }
    <?php endif; ?>

    /* ── Virtual toggle → hide shipping ── */
    $('#yVirtual').on('change', function() {
        <?php if ( $_store_type === 'retail' ) : ?>
        $('#sectionShipping').toggle(!this.checked);
        <?php endif; ?>
    });

    /* ── Booking/Rental toggles ── */
    $('#yDepositEnabled').on('change', function() { $('#depositFields').toggle(this.checked); });
    $('#yLateFeeEnabled').on('change', function() { $('#lateFeeFields').toggle(this.checked); });
    $('#yInsuranceEnabled').on('change', function() { $('#insuranceFields').toggle(this.checked); });

    function yamamahSyncDepositValueBounds() {
        var isPct = ($('#yDepositType').val() || 'percentage') === 'percentage';
        var $el = $('#yDepositValue');
        var $hint = $('#yDepositValueHint');
        if (isPct) {
            $el.attr({ min: 0, max: 100, step: '0.01' });
            $hint.text('أدخل نسبة بين 0 و 100.');
        } else {
            $el.attr({ min: 0, step: '0.01' });
            $el.removeAttr('max');
            $hint.text('أدخل مبلغاً ثابتاً بالريال (0 أو أكثر).');
        }
    }
    $('#yDepositType').on('change', yamamahSyncDepositValueBounds);
    yamamahSyncDepositValueBounds();

    /* ── Manage stock toggle ── */
    window.yamamahToggleStockFields = function() {
        var manage = $('#yManageStock').val() === 'yes';
        $('#stockQtyRow').toggle(manage);
        $('#stockStatusRow').toggle(!manage);
    };

    /* ── معرض الصور ── */
    window.yamamahAddGalleryImages = function() {
        var frame = wp.media({ title: 'اختر صور المعرض', multiple: true, library: { type: 'image' } });
        frame.on('select', function() {
            frame.state().get('selection').each(function(att) {
                var json = att.toJSON();
                var url = json.sizes && json.sizes.thumbnail ? json.sizes.thumbnail.url : json.url;
                $('#galleryContainer').append(
                    '<div class="yamamah-gallery-thumb" data-id="' + json.id + '">' +
                    '<img src="' + url + '" alt="">' +
                    '<button type="button" class="upload-remove" onclick="yamamahRemoveGalleryImage(this)">&times;</button></div>'
                );
            });
        });
        frame.open();
    };
    window.yamamahRemoveGalleryImage = function(btn) {
        $(btn).closest('.yamamah-gallery-thumb').remove();
    };

    /* ── إضافة سمة للمنتج (من القائمة المنسدلة) ── */
    window.yamamahToggleAttrDropdown = function(btn) {
        var $menu = $('#attrDropdownMenu');
        if ($menu.is(':visible')) {
            $menu.hide();
            return;
        }
        var rect = btn.getBoundingClientRect();
        $menu.css({
            top: rect.bottom + 4 + 'px',
            left: rect.left + 'px'
        }).show();
    };
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.yamamah-attr-add-wrapper').length) {
            $('#attrDropdownMenu').hide();
        }
    });
    window.yamamahAddAttributeFromMenu = function(btn) {
        var attrId = btn.getAttribute('data-attr-id');
        var taxonomy = btn.getAttribute('data-taxonomy');
        var label = btn.getAttribute('data-label');
        var pType = $('#yProductType').val();
        $('#attrDropdownMenu').hide();

        if (!taxonomy) { alert('خطأ: لم يتم تحديد السمة'); return; }

        if ($('#productAttributesList').find('[data-taxonomy="' + taxonomy + '"]').length) {
            alert('هذه السمة مضافة مسبقاً');
            return;
        }

        $.post(yamamahData.ajax_url, {
            action: 'yamamah_get_attribute_terms',
            nonce: yamamahData.nonce,
            taxonomy: taxonomy
        }, function(res) {
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : (typeof res.data === 'string' ? res.data : 'حدث خطأ أثناء جلب قيم السمة'));
                return;
            }
            var termsHtml = '';
            if (res.data && res.data.length) {
                $.each(res.data, function(i, t) {
                    termsHtml += '<label style="font-size:0.8rem;cursor:pointer;display:flex;align-items:center;gap:0.25rem;">' +
                        '<input type="checkbox" class="attr-term-cb" value="' + t.id + '"> ' + t.name + '</label>';
                });
            } else {
                termsHtml = '<p class="text-xs text-muted">لا توجد قيم — أضف قيماً من صفحة السمات أولاً</p>';
            }
            var varLabel = pType === 'variable' ? '' : 'style="display:none;"';
            var html = '<div class="yamamah-attr-row" data-taxonomy="' + taxonomy + '" data-attr-id="' + (attrId || 0) + '">' +
                '<div class="flex justify-between items-center mb-2"><strong class="text-sm">' + label + '</strong>' +
                '<button type="button" class="btn btn-sm btn-outline" style="color:var(--red);" onclick="jQuery(this).closest(\'.yamamah-attr-row\').remove();">حذف</button></div>' +
                '<div class="flex gap-2 flex-wrap mb-2">' + termsHtml + '</div>' +
                '<div class="flex gap-3" style="font-size:0.8rem;">' +
                '<label style="cursor:pointer;display:flex;align-items:center;gap:0.25rem;"><input type="checkbox" class="attr-visible-cb" value="1" checked> ' + yamamahCatalogLabels.visible_on_item_page + '</label>' +
                '<label style="cursor:pointer;display:flex;align-items:center;gap:0.25rem;" class="attr-variation-label" ' + varLabel + '><input type="checkbox" class="attr-variation-cb" value="1"> مستخدمة للتنويعات</label>' +
                '</div></div>';
            $('#productAttributesList').append(html);
        }).fail(function(xhr) {
            alert('خطأ في الاتصال بالسيرفر (' + xhr.status + ')');
        });
    };

    /* ── إنشاء تنويعات من السمات ── */
    window.yamamahGenerateVariations = function() {
        var attrArrays = [];
        var attrNames = [];
        $('#productAttributesList .yamamah-attr-row').each(function() {
            var $row = $(this);
            if (!$row.find('.attr-variation-cb').is(':checked')) return;
            var taxonomy = $row.data('taxonomy');
            attrNames.push(taxonomy);
            var vals = [];
            $row.find('.attr-term-cb:checked').each(function() {
                vals.push({ id: $(this).val(), name: $(this).parent().text().trim() });
            });
            if (vals.length) attrArrays.push(vals);
        });

        if (!attrArrays.length) {
            alert('حدد سمات وقيم مستخدمة للتنويعات أولاً');
            return;
        }

        var combos = cartesian(attrArrays);
        $('#variationsList').empty();
        $('#noVariationsMsg').hide();

        combos.forEach(function(combo, idx) {
            var attrsHtml = '';
            combo.forEach(function(val, i) {
                attrsHtml += '<div class="form-field"><label class="text-xs">' + attrNames[i].replace('pa_', '') + '</label>' +
                    '<input type="text" class="var-attr" data-attr="' + attrNames[i] + '" value="' + val.name + '" style="font-size:0.8rem;" readonly></div>';
            });
            var html = '<div class="yamamah-variation-row" data-variation-id="0">' +
                '<div class="flex justify-between items-center mb-2"><strong class="text-sm">تنويع #' + (idx + 1) + '</strong>' +
                '<button type="button" class="btn btn-sm btn-outline" style="color:var(--red);font-size:0.75rem;" onclick="jQuery(this).closest(\'.yamamah-variation-row\').remove();">حذف</button></div>' +
                '<div class="yamamah-form-row-2" style="gap:0.5rem;">' + attrsHtml + '</div>' +
                '<div class="yamamah-form-row-2" style="gap:0.5rem;margin-top:0.5rem;">' +
                '<div class="form-field"><label class="text-xs">السعر</label><input type="number" class="var-price" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>' +
                '<div class="form-field"><label class="text-xs">سعر التخفيض</label><input type="number" class="var-sale-price" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>' +
                '<div class="form-field"><label class="text-xs">SKU</label><input type="text" class="var-sku" dir="ltr" style="font-size:0.8rem;"></div>' +
                '<div class="form-field"><label class="text-xs">الكمية</label><input type="number" class="var-stock" dir="ltr" style="font-size:0.8rem;"></div>' +
                '</div></div>';
            $('#variationsList').append(html);
        });
    };

    window.yamamahAddSingleVariation = function() {
        var count = $('#variationsList .yamamah-variation-row').length + 1;
        var html = '<div class="yamamah-variation-row" data-variation-id="0">' +
            '<div class="flex justify-between items-center mb-2"><strong class="text-sm">تنويع #' + count + '</strong>' +
            '<button type="button" class="btn btn-sm btn-outline" style="color:var(--red);font-size:0.75rem;" onclick="jQuery(this).closest(\'.yamamah-variation-row\').remove();">حذف</button></div>' +
            '<div class="yamamah-form-row-2" style="gap:0.5rem;margin-top:0.5rem;">' +
            '<div class="form-field"><label class="text-xs">السعر</label><input type="number" class="var-price" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>' +
            '<div class="form-field"><label class="text-xs">سعر التخفيض</label><input type="number" class="var-sale-price" step="0.01" dir="ltr" style="font-size:0.8rem;"></div>' +
            '<div class="form-field"><label class="text-xs">SKU</label><input type="text" class="var-sku" dir="ltr" style="font-size:0.8rem;"></div>' +
            '<div class="form-field"><label class="text-xs">الكمية</label><input type="number" class="var-stock" dir="ltr" style="font-size:0.8rem;"></div>' +
            '</div></div>';
        $('#variationsList').append(html);
        $('#noVariationsMsg').hide();
    };

    function cartesian(arrays) {
        if (!arrays.length) return [[]];
        var first = arrays[0], rest = cartesian(arrays.slice(1));
        var result = [];
        first.forEach(function(val) {
            rest.forEach(function(combo) { result.push([val].concat(combo)); });
        });
        return result;
    }

    /* ── حفظ المنتج ── */
    window.yamamahSaveProduct = function() {
        var pName = $.trim($('#yProductName').val());
        if (!pName) { alert(yamamahCatalogLabels.name_required); $('#yProductName').focus(); return; }

        var pType = $('#yProductType').val();
        if ((pType === 'simple' || pType === 'external') && !$('#yProductPrice').val()) {
            alert('السعر مطلوب'); $('#yProductPrice').focus(); return;
        }
        if (pType === 'external' && !$('#yExternalUrl').val()) {
            alert(yamamahCatalogLabels.external_url_required); $('#yExternalUrl').focus(); return;
        }

        var saleFrom = ($('#ySaleFrom').val() || '').trim();
        var saleTo = ($('#ySaleTo').val() || '').trim();
        if (saleFrom && saleTo && saleTo < saleFrom) {
            alert('تاريخ نهاية التخفيض يجب أن يكون مساوياً أو لاحقاً لتاريخ بداية التخفيض');
            $('#ySaleTo').focus();
            return;
        }

        if (yamamahIsBookingOrRental && $('#yDepositEnabled').is(':checked')) {
            var depType = $('#yDepositType').val() || 'percentage';
            var depValRaw = $.trim($('#yDepositValue').val());
            if (depValRaw === '') {
                alert('يرجى إدخال قيمة العربون.');
                $('#yDepositValue').focus();
                return;
            }
            var depVal = parseFloat(depValRaw);
            if (isNaN(depVal)) {
                alert('قيمة العربون يجب أن تكون رقماً صالحاً.');
                $('#yDepositValue').focus();
                return;
            }
            if (depType === 'percentage') {
                if (depVal < 0 || depVal > 100) {
                    alert('نسبة العربون يجب أن تكون بين 0 و 100.');
                    $('#yDepositValue').focus();
                    return;
                }
            } else if (depVal < 0) {
                alert('مبلغ العربون لا يمكن أن يكون سالباً.');
                $('#yDepositValue').focus();
                return;
            }
        }

        var catIds = [], tagIds = [];
        $('input[name="product_cats[]"]:checked').each(function(){ catIds.push(parseInt($(this).val(), 10) || 0); });
        $('input[name="product_tags[]"]:checked').each(function(){ tagIds.push(parseInt($(this).val(), 10) || 0); });
        catIds = catIds.filter(function(id){ return id > 0; });
        tagIds = tagIds.filter(function(id){ return id > 0; });

        var status = (pType === 'grouped' || pType === 'variable') ? ($('#yProductStatusAlt').val() || 'publish') : ($('#yProductStatus').val() || 'publish');

        var galleryIds = [];
        $('#galleryContainer .yamamah-gallery-thumb').each(function(){ galleryIds.push($(this).data('id')); });

        var upsellIds = [];
        $('input[name="upsell_ids[]"]:checked').each(function(){ upsellIds.push($(this).val()); });
        var crossSellIds = [];
        $('input[name="cross_sell_ids[]"]:checked').each(function(){ crossSellIds.push($(this).val()); });
        var groupedIds = [];
        $('input[name="grouped_products[]"]:checked').each(function(){ groupedIds.push($(this).val()); });

        var productAttrs = [];
        $('#productAttributesList .yamamah-attr-row').each(function() {
            var $row = $(this);
            // Use attr() so taxonomy keys are not altered/cached by jQuery .data().
            var taxonomy = $row.attr('data-taxonomy') || '';
            if (!taxonomy) return;
            var termIds = [];
            $row.find('.attr-term-cb:checked').each(function(){
                var tid = parseInt($(this).val(), 10);
                if (tid > 0) termIds.push(tid);
            });
            productAttrs.push({
                attr_id:   parseInt($row.attr('data-attr-id') || '0', 10) || 0,
                taxonomy:  taxonomy,
                term_ids:  termIds,
                visible:   $row.find('.attr-visible-cb').is(':checked') ? 1 : 0,
                variation: $row.find('.attr-variation-cb').is(':checked') ? 1 : 0,
            });
        });

        var variations = [];
        $('#variationsList .yamamah-variation-row').each(function() {
            var $v = $(this);
            var vAttrs = {};
            $v.find('.var-attr').each(function() { vAttrs[$(this).data('attr')] = $(this).val(); });
            variations.push({
                id:           $v.data('variation-id') || 0,
                attributes:   vAttrs,
                regular_price: $v.find('.var-price').val() || '',
                sale_price:   $v.find('.var-sale-price').val() || '',
                sku:          $v.find('.var-sku').val() || '',
                stock:        $v.find('.var-stock').val() || '',
            });
        });

        var data = {
            action:         'yamamah_save_product',
            nonce:          yamamahData.nonce,
            product_id:     $('#yProductId').val(),
            product_type:   pType,
            name:           $('#yProductName').val(),
            short_desc:     $('#yProductShortDesc').val(),
            slug:           $('#yProductSlug').val(),
            sku:            $('#yProductSku').val(),
            desc:           (function() {
                if (typeof tinymce !== 'undefined') {
                    var ed = tinymce.get('yProductDesc');
                    if (ed) return ed.getContent();
                }
                return $('#yProductDesc').val() || '';
            })(),
            price:          $('#yProductPrice').val(),
            sale_price:     $('#yProductSalePrice').val(),
            sale_from:      $('#ySaleFrom').val() || '',
            sale_to:        $('#ySaleTo').val() || '',
            manage_stock:   $('#yManageStock').val(),
            stock:          $('#yProductStock').val(),
            stock_status:   $('#yStockStatus').val(),
            backorders:     $('#yBackorders').val(),
            status:         status,
            image_id:       $('#yProductImageId').val(),
            gallery_ids:    galleryIds,
            weight:         $('#yProductWeight').val(),
            length:         $('#yProductLength').val(),
            width:          $('#yProductWidth').val(),
            height:         $('#yProductHeight').val(),
            cat_ids:        catIds.join(','),
            tag_ids:        tagIds.join(','),
            virtual:        $('#yVirtual').is(':checked') ? 1 : 0,
            downloadable:   $('#yDownloadable').is(':checked') ? 1 : 0,
            purchase_note:  $('#yPurchaseNote').val(),
            external_url:   $('#yExternalUrl').val() || '',
            external_btn:   $('#yExternalBtn').val() || '',
            upsell_ids:     upsellIds,
            cross_sell_ids: crossSellIds,
            grouped_ids:    groupedIds,
            attributes:     JSON.stringify(productAttrs),
            variations:     JSON.stringify(variations),
            deposit_enabled:   $('#yDepositEnabled').is(':checked') ? 1 : 0,
            deposit_type:      $('#yDepositType').val() || 'percentage',
            deposit_value:     $('#yDepositValue').val() || '',
            late_fee_enabled:  $('#yLateFeeEnabled').is(':checked') ? 1 : 0,
            late_fee_type:     $('#yLateFeeType').val() || 'fixed',
            late_fee_value:    $('#yLateFeeValue').val() || '',
            insurance_enabled: $('#yInsuranceEnabled').is(':checked') ? 1 : 0,
            insurance_amount:  $('#yInsuranceAmount').val() || ''
        };

        $.post(yamamahData.ajax_url, data, function(res) {
            if (res.success) {
                alert(yamamahCatalogLabels.save_success);
                if (!data.product_id || data.product_id === '0') {
                    window.location.href = yamamahData.admin_url + 'admin.php?page=yamamah-add-product&product_id=' + res.data.id;
                }
            } else {
                alert(res.data || 'حدث خطأ');
            }
        }).fail(function(){ alert('خطأ في الاتصال'); });
    };

    /* ── رفع صورة المنتج ── */
    window.yamamahUploadProductImage = function() {
        var frame = wp.media({ title: yamamahCatalogLabels.media_picker_title, multiple: false });
        frame.on('select', function() {
            var att = frame.state().get('selection').first().toJSON();
            $('#yProductImageId').val(att.id);
            var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
            $('#productImageUpload').html(
                '<div class="upload-preview"><img src="' + url + '" alt="">' +
                '<button type="button" class="upload-remove" onclick="event.stopPropagation(); yamamahRemoveProductImage();">&times;</button></div>'
            );
        });
        frame.open();
    };
    window.yamamahRemoveProductImage = function() {
        $('#yProductImageId').val('0');
        $('#productImageUpload').html(
            '<div class="upload-empty"><div class="upload-icon-box">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>' +
            '</div><p class="text-xs" style="color:var(--green);">اختر صورة <span class="text-muted">أو اسحبها هنا</span></p></div>'
        );
    };

    function yamamahSyncSaleDateBounds() {
        var from = ($('#ySaleFrom').val() || '').trim();
        var toEl = $('#ySaleTo');
        if (from) {
            toEl.attr('min', from);
            if ((toEl.val() || '').trim() && toEl.val() < from) {
                toEl.val('');
            }
        } else {
            toEl.removeAttr('min');
        }
    }
    $('#ySaleFrom').on('change input', yamamahSyncSaleDateBounds);
    yamamahSyncSaleDateBounds();
});
</script>
<?php
$page_content = ob_get_clean();
include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
?>
