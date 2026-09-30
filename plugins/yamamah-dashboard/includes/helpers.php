<?php
/**
 * Yamamah Helpers - دوال مساعدة
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * تحويل معرّفات التصنيف/الوسم من طلب AJAX إلى مصفوفة أعداد صحيحة.
 * يدعم: مصفوفة، CSV، أو JSON.
 *
 * @param mixed $raw Raw posted value.
 * @return int[]
 */
function yamamah_parse_posted_term_ids( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = trim( wp_unslash( $raw ) );
		if ( '' === $raw ) {
			return array();
		}
		if ( '[' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		} else {
			$raw = preg_split( '/\s*,\s*/', $raw );
		}
	}
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$ids = array_map( 'absint', $raw );
	return array_values( array_filter( $ids ) );
}

/**
 * أسماء وسوم المنتج (product_tag).
 *
 * @param int|WC_Product $product Product or ID.
 * @return string[]
 */
function yamamah_get_product_tag_names( $product ) {
	$product_id = 0;

	if ( is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ) {
		$product_id = (int) $product->get_id();
	} else {
		$product_id = absint( $product );
	}

	if ( $product_id <= 0 ) {
		return array();
	}

	// Prefer taxonomy terms (authoritative) over WC object cache.
	$terms = get_the_terms( $product_id, 'product_tag' );
	if ( ! is_wp_error( $terms ) && is_array( $terms ) && ! empty( $terms ) ) {
		$names = array();
		foreach ( $terms as $term ) {
			if ( $term && ! is_wp_error( $term ) && '' !== trim( (string) $term->name ) ) {
				$names[] = (string) $term->name;
			}
		}
		if ( ! empty( $names ) ) {
			return $names;
		}
	}

	$tag_ids = array();
	if ( is_object( $product ) && is_callable( array( $product, 'get_tag_ids' ) ) ) {
		$tag_ids = array_map( 'absint', (array) $product->get_tag_ids() );
	}
	if ( empty( $tag_ids ) ) {
		$tag_ids = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $tag_ids ) || ! is_array( $tag_ids ) ) {
			$tag_ids = array();
		}
		$tag_ids = array_map( 'absint', $tag_ids );
	}

	$tag_ids = array_values( array_filter( $tag_ids ) );
	if ( empty( $tag_ids ) ) {
		return array();
	}

	$names = array();
	foreach ( $tag_ids as $tid ) {
		$term = get_term( $tid, 'product_tag' );
		if ( $term && ! is_wp_error( $term ) && '' !== trim( (string) $term->name ) ) {
			$names[] = (string) $term->name;
		}
	}
	return $names;
}

/**
 * سمات المنتج الظاهرة (للواجهة الأمامية).
 *
 * @param int|WC_Product $product       Product or ID.
 * @param bool           $visible_only  Only attributes marked visible on the product page.
 * @return array<int, array{label: string, values: string[], text: string}>
 */
function yamamah_get_product_attribute_rows( $product, $visible_only = true ) {
	if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_attributes' ) ) ) {
		$product_id = absint( $product );
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( $product_id );
	}

	if ( ! $product || ! is_callable( array( $product, 'get_attributes' ) ) ) {
		return array();
	}

	$rows = array();
	foreach ( (array) $product->get_attributes() as $attribute ) {
		if ( ! is_object( $attribute ) || ! is_callable( array( $attribute, 'get_name' ) ) ) {
			continue;
		}
		if ( $visible_only && is_callable( array( $attribute, 'get_visible' ) ) && ! $attribute->get_visible() ) {
			continue;
		}

		$name  = (string) $attribute->get_name();
		$label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $product ) : $name;
		$text  = '';
		if ( is_callable( array( $product, 'get_attribute' ) ) ) {
			$text = trim( (string) $product->get_attribute( $name ) );
		}

		$values = array();
		if ( '' !== $text ) {
			$values = array_values(
				array_filter(
					array_map( 'trim', preg_split( '/\s*,\s*/', $text ) )
				)
			);
		} elseif ( is_callable( array( $attribute, 'is_taxonomy' ) ) && $attribute->is_taxonomy() && function_exists( 'wc_get_product_terms' ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $name, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				$values = array_values( array_filter( array_map( 'strval', $terms ) ) );
				$text   = implode( ', ', $values );
			}
		} elseif ( is_callable( array( $attribute, 'get_options' ) ) ) {
			$options = (array) $attribute->get_options();
			foreach ( $options as $opt ) {
				if ( is_numeric( $opt ) ) {
					$term = get_term( (int) $opt );
					if ( $term && ! is_wp_error( $term ) && '' !== trim( (string) $term->name ) ) {
						$values[] = (string) $term->name;
					}
				} elseif ( is_string( $opt ) && '' !== trim( $opt ) ) {
					$values[] = trim( $opt );
				}
			}
			$text = implode( ', ', $values );
		}

		if ( empty( $values ) && '' === $text ) {
			continue;
		}
		if ( empty( $values ) && '' !== $text ) {
			$values = array( $text );
		}

		$rows[] = array(
			'label'  => (string) $label,
			'values' => $values,
			'text'   => $text,
		);
	}

	return $rows;
}

/**
 * طباعة قائمة سمات المنتج للواجهة (بطاقات / صفحة منفردة).
 *
 * @param int|WC_Product $product Product or ID.
 * @param array          $args {
 *     @type string $list_class  UL class.
 *     @type string $item_class  LI class.
 *     @type string $label_class Label span class.
 *     @type string $value_class Value span class.
 *     @type string $aria_label  aria-label.
 *     @type bool   $visible_only Only visible attributes.
 *     @type bool   $echo         Echo or return HTML.
 * }
 * @return string
 */
function yamamah_render_product_attributes_list( $product, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'list_class'    => 'yamamah-svc-attrs',
			'item_class'    => 'yamamah-svc-attrs__item',
			'label_class'   => 'yamamah-svc-attrs__label',
			'value_class'   => 'yamamah-svc-attrs__value',
			'aria_label'    => __( 'السمات', 'yamamah-dashboard' ),
			'visible_only'  => true,
			'echo'          => true,
		)
	);

	$rows = yamamah_get_product_attribute_rows( $product, (bool) $args['visible_only'] );
	if ( empty( $rows ) ) {
		return '';
	}

	$html  = '<ul class="' . esc_attr( (string) $args['list_class'] ) . '" aria-label="' . esc_attr( (string) $args['aria_label'] ) . '">';
	foreach ( $rows as $row ) {
		$html .= '<li class="' . esc_attr( (string) $args['item_class'] ) . '">';
		$html .= '<span class="' . esc_attr( (string) $args['label_class'] ) . '">' . esc_html( $row['label'] ) . '</span>';
		$html .= '<span class="' . esc_attr( (string) $args['value_class'] ) . '">' . esc_html( $row['text'] ) . '</span>';
		$html .= '</li>';
	}
	$html .= '</ul>';

	if ( $args['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		return '';
	}
	return $html;
}

/**
 * فحص ميزة في باقة المتجر
 */
function yamamah_plan_feature( $key, $default = false ) {
    static $features = null;
    if ( $features === null ) {
        $raw = get_option( 'yamama_plan_features', '' );
        if ( is_array( $raw ) ) {
            $features = $raw;
        } elseif ( is_string( $raw ) && $raw !== '' ) {
            $features = json_decode( $raw, true );
        }
        if ( ! is_array( $features ) ) {
            $features = array();
        }
    }
    if ( ! isset( $features[ $key ] ) ) return $default;
    $v = $features[ $key ];
    if ( is_bool( $v ) ) return $v;
    if ( is_numeric( $v ) ) return (int) $v;
    return in_array( $v, array( 'yes', 'true', '1', true ), true );
}

function yamamah_plan_allows( $key ) {
    $from_json = yamamah_plan_feature( $key, null );
    if ( $from_json !== null ) {
        return (bool) $from_json;
    }

    // Hyphenated aliases sometimes appear in plan JSON.
    $alt_key = str_replace( '_', '-', $key );
    if ( $alt_key !== $key ) {
        $from_alt = yamamah_plan_feature( $alt_key, null );
        if ( $from_alt !== null ) {
            return (bool) $from_alt;
        }
    }

    // Comprehensive / mid-tier plans often omit abandoned_carts in JSON while
    // enabling coupons/shipping/loyalty — treat abandoned carts as included then.
    if ( $key === 'abandoned_carts' ) {
        if ( yamamah_plan_feature( 'coupons', false )
            || yamamah_plan_feature( 'shipping', false )
            || yamamah_plan_feature( 'loyalty_points', false )
            || get_option( 'yamama_coupons_enabled', 'no' ) === 'yes'
            || get_option( 'yamama_shipping_enabled', 'no' ) === 'yes' ) {
            return true;
        }
    }

    $option_map = array(
        'coupons'            => 'yamama_coupons_enabled',
        'loyalty_points'     => 'yamama_loyalty_enabled',
        'shipping'           => 'yamama_shipping_enabled',
        'electronic_payment' => 'yamama_electronic_payment',
        'custom_domain'      => 'yamama_custom_domain_enabled',
        'abandoned_carts'    => 'yamama_abandoned_carts',
        'api_keys'           => 'yamama_api_keys',
        'sms'                => 'yamama_sms_enabled',
        'analytics_advanced' => 'yamama_analytics_advanced',
        'code_snippets'      => 'yamama_code_snippets',
    );
    if ( isset( $option_map[ $key ] ) ) {
        // Do not default abandoned_carts to 'no' when the option was never written.
        $default = ( $key === 'abandoned_carts' ) ? 'yes' : 'no';
        return get_option( $option_map[ $key ], $default ) === 'yes';
    }
    return false;
}

function yamamah_upgrade_banner( $feature_label ) {
    $platform_url = get_option( 'yamama_platform_url', '' );
    if ( empty( $platform_url ) ) {
        $platform_url = 'https://yaamama.sa';
    }
    $html  = '<div class="page active"><div class="card" style="text-align:center;padding:60px 20px;max-width:600px;margin:40px auto;">';
    $html .= '<div style="margin-bottom:20px;"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>';
    $html .= '<h2 style="margin-bottom:12px;color:#1e293b;">' . esc_html( $feature_label ) . ' غير متاحة في باقتك الحالية</h2>';
    $html .= '<p style="color:#64748b;font-size:15px;line-height:1.7;">قم بترقية باقتك للوصول إلى هذه الميزة والاستفادة من المزيد من الأدوات لتنمية متجرك.</p>';
    $html .= '<a href="' . esc_url( $platform_url ) . '" target="_blank" style="display:inline-block;margin-top:20px;padding:12px 32px;background:#6C3FA0;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">ترقية الباقة</a>';
    $html .= '</div></div>';
    return $html;
}

function yamamah_max_products() {
    $from_opt = get_option( 'yamama_max_products', '' );
    if ( $from_opt !== '' && is_numeric( $from_opt ) ) return (int) $from_opt;
    return (int) yamamah_plan_feature( 'max_products', 50 );
}

/**
 * كشف نوع المتجر من قاعدة البيانات
 */
function yamamah_get_store_type() {
    static $type = null;
    if ( $type === null ) {
        $type = get_option( 'yamama_store_type', 'retail' );
    }
    return $type;
}

/**
 * Catalog UI labels by store type (products / services / assets).
 *
 * @return array<string,string>
 */
function yamamah_get_catalog_labels() {
    static $labels = null;
    if ( $labels !== null ) {
        return $labels;
    }

    $store_type = yamamah_get_store_type();

    $defaults = array(
        'section'              => 'المنتجات',
        'all_items'            => 'جميع المنتجات',
        'add_item'             => 'إضافة منتج',
        'add_item_btn'         => '+ إضافة منتج',
        'item_singular'        => 'المنتج',
        'item_indef'           => 'منتج',
        'items_indef_plural'   => 'منتجات',
        'manage_line'          => 'إدارة منتجات المتجر (%d منتج)',
        'bulk_selected'        => '%d منتج محدد',
        'bulk_selected_suffix' => 'منتج محدد',
        'empty_list'           => 'لا توجد منتجات',
        'delete_confirm'       => 'هل أنت متأكد من حذف هذا المنتج نهائياً؟',
        'analytics_title'      => 'المنتجات',
        'analytics_subtitle'   => 'ملخص شامل لأداء المنتجات',
        'analytics_menu'       => 'تحليلات المنتجات',
        'published_stat'       => 'المنتجات المنشورة',
        'top_sellers'          => 'أكثر المنتجات مبيعاً',
        'order_section'        => 'المنتجات',
        'categories_desc'      => 'إدارة تصنيفات المنتجات',
        'tags_desc'            => 'إدارة وسوم المنتجات وتنظيمها',
        'attributes_desc'      => 'إدارة سمات المنتجات وتنوعاتها',
        'category_count'       => '%d منتج',
        'categories_column'    => 'المنتجات',
        'demo_page_title'      => 'منتجات ديمو',
        'demo_page_desc'       => 'إنشاء المنتجات التجريبية (15 منتج ديمو + 5 تصنيفات ديمو)',
        'demo_empty'           => 'لا توجد منتجات ديمو حالياً',
        'demo_empty_hint'      => 'اضغط على "حذف القديمة وإنشاء ديمو جديد" لإنشاء المنتجات التجريبية.',
        'orders_label'         => 'الطلبات',
        'demo_label'           => 'منتجات ديمو',
        'type_section'         => 'نوع المنتج',
        'type_simple'          => 'منتج بسيط',
        'type_simple_desc'     => 'منتج عادي بسعر ومخزون واحد',
        'type_grouped'         => 'منتج مجمّع',
        'type_grouped_desc'    => 'مجموعة من المنتجات المتعلقة',
        'type_variable'        => 'منتج متعدد الأنواع',
        'type_variable_desc'   => 'منتج بخيارات متعددة (لون، حجم...)',
        'type_external'        => 'منتج خارجي',
        'type_external_desc'   => 'يتم شراؤه من صفحة خارجية',
        'grouped_section'      => 'المنتجات في المجموعة',
        'grouped_pick'         => 'اختر المنتجات البسيطة التي تريد تجميعها',
        'no_simple_items'      => 'لا يوجد منتجات بسيطة منشورة',
        'linked_section'       => 'المنتجات المرتبطة',
        'linked_empty'         => 'لا يوجد منتجات',
        'linked_upsell_hint'   => 'منتجات تُقترح للعميل بدل المنتج الحالي',
        'linked_cross_hint'    => 'منتجات تُعرض في صفحة السلة',
        'visible_on_item_page' => 'مرئية في صفحة المنتج',
        'media_picker_title'   => 'اختر صورة المنتج',
        'save_success'         => 'تم حفظ المنتج بنجاح',
        'name_required'        => 'اسم المنتج مطلوب',
        'max_items_error'      => 'وصلت للحد الأقصى من المنتجات (%d). قم بترقية الباقة لإضافة المزيد.',
        'external_url_required'=> 'رابط المنتج الخارجي مطلوب',
        'not_found'            => 'المنتج غير موجود',
    );

    if ( $store_type === 'booking' ) {
        $labels = array_merge(
            $defaults,
            array(
                'section'              => 'الخدمات',
                'all_items'            => 'جميع الخدمات',
                'add_item'             => 'إضافة خدمة',
                'add_item_btn'         => '+ إضافة خدمة',
                'item_singular'        => 'الخدمة',
                'item_indef'           => 'خدمة',
                'items_indef_plural'   => 'خدمات',
                'manage_line'          => 'إدارة خدمات المتجر (%d خدمة)',
                'bulk_selected'        => '%d خدمة محددة',
                'bulk_selected_suffix' => 'خدمة محددة',
                'empty_list'           => 'لا توجد خدمات',
                'delete_confirm'       => 'هل أنت متأكد من حذف هذه الخدمة نهائياً؟',
                'analytics_title'      => 'الخدمات',
                'analytics_subtitle'   => 'ملخص شامل لأداء الخدمات',
                'analytics_menu'       => 'تحليلات الخدمات',
                'published_stat'       => 'الخدمات المنشورة',
                'top_sellers'          => 'أكثر الخدمات حجزاً',
                'order_section'        => 'الخدمات',
                'categories_desc'      => 'إدارة تصنيفات الخدمات',
                'tags_desc'            => 'إدارة وسوم الخدمات وتنظيمها',
                'attributes_desc'      => 'إدارة سمات الخدمات وتنوعاتها',
                'category_count'       => '%d خدمة',
                'categories_column'    => 'الخدمات',
                'demo_page_title'      => 'خدمات ديمو',
                'demo_page_desc'       => 'إنشاء الخدمات التجريبية (15 خدمة ديمو + 5 تصنيفات ديمو)',
                'demo_empty'           => 'لا توجد خدمات ديمو حالياً',
                'demo_empty_hint'      => 'اضغط على "حذف القديمة وإنشاء ديمو جديد" لإنشاء الخدمات التجريبية.',
                'orders_label'         => 'طلبات الحجز',
                'demo_label'           => 'خدمات ديمو',
                'type_section'         => 'نوع الخدمة',
                'type_simple'          => 'خدمة بسيطة',
                'type_simple_desc'     => 'خدمة بسعر واحد',
                'type_grouped'         => 'خدمة مجمّعة',
                'type_grouped_desc'    => 'مجموعة من الخدمات البسيطة المرتبطة',
                'grouped_section'      => 'الخدمات في المجموعة',
                'grouped_pick'         => 'اختر الخدمات البسيطة التي تريد تجميعها',
                'no_simple_items'      => 'لا توجد خدمات بسيطة منشورة',
                'linked_section'       => 'عناصر مرتبطة',
                'linked_empty'         => 'لا يوجد خدمات',
                'linked_upsell_hint'   => 'خدمات تُقترح للعميل بدل الخدمة الحالية',
                'linked_cross_hint'    => 'خدمات تُعرض في صفحة السلة',
                'visible_on_item_page' => 'مرئية في صفحة الخدمة',
                'media_picker_title'   => 'اختر صورة الخدمة',
                'save_success'         => 'تم حفظ الخدمة بنجاح',
                'name_required'        => 'اسم الخدمة مطلوب',
                'max_items_error'      => 'وصلت للحد الأقصى من الخدمات (%d). قم بترقية الباقة لإضافة المزيد.',
                'external_url_required'=> 'رابط الخدمة الخارجي مطلوب',
                'not_found'            => 'الخدمة غير موجودة',
            )
        );
    } elseif ( $store_type === 'rental' ) {
        $labels = array_merge(
            $defaults,
            array(
                'section'              => 'الأصول',
                'all_items'            => 'جميع الأصول',
                'add_item'             => 'إضافة أصل',
                'add_item_btn'         => '+ إضافة أصل',
                'item_singular'        => 'الأصل',
                'item_indef'           => 'أصل',
                'items_indef_plural'   => 'أصول',
                'manage_line'          => 'إدارة أصول المتجر (%d أصل)',
                'bulk_selected'        => '%d أصل محدد',
                'bulk_selected_suffix' => 'أصل محدد',
                'empty_list'           => 'لا توجد أصول',
                'delete_confirm'       => 'هل أنت متأكد من حذف هذا الأصل نهائياً؟',
                'analytics_title'      => 'الأصول',
                'analytics_subtitle'   => 'ملخص شامل لأداء الأصول',
                'analytics_menu'       => 'تحليلات الأصول',
                'published_stat'       => 'الأصول المنشورة',
                'top_sellers'          => 'أكثر الأصول تأجيراً',
                'order_section'        => 'الأصول',
                'categories_desc'      => 'إدارة تصنيفات الأصول',
                'tags_desc'            => 'إدارة وسوم الأصول وتنظيمها',
                'attributes_desc'      => 'إدارة سمات الأصول وتنوعاتها',
                'category_count'       => '%d أصل',
                'categories_column'    => 'الأصول',
                'demo_page_title'      => 'أصول ديمو',
                'demo_page_desc'       => 'إنشاء الأصول التجريبية (15 أصل ديمو + 5 تصنيفات ديمو)',
                'demo_empty'           => 'لا توجد أصول ديمو حالياً',
                'demo_empty_hint'      => 'اضغط على "حذف القديمة وإنشاء ديمو جديد" لإنشاء الأصول التجريبية.',
                'orders_label'         => 'طلبات التأجير',
                'demo_label'           => 'أصول ديمو',
                'type_section'         => 'نوع الأصل',
                'type_simple'          => 'أصل بسيط',
                'type_simple_desc'     => 'أصل بسعر إيجار وتوفر واحد',
                'type_grouped'         => 'أصل مجمّع',
                'type_grouped_desc'    => 'مجموعة من الأصول البسيطة المرتبطة',
                'grouped_section'      => 'الأصول في المجموعة',
                'grouped_pick'         => 'اختر الأصول البسيطة التي تريد تجميعها',
                'no_simple_items'      => 'لا يوجد أصول بسيطة منشورة',
                'linked_section'       => 'عناصر مرتبطة',
                'linked_empty'         => 'لا يوجد أصول',
                'linked_upsell_hint'   => 'أصول تُقترح للعميل بدل الأصل الحالي',
                'linked_cross_hint'    => 'أصول تُعرض في صفحة السلة',
                'visible_on_item_page' => 'مرئية في صفحة الأصل',
                'media_picker_title'   => 'اختر صورة الأصل',
                'save_success'         => 'تم حفظ الأصل بنجاح',
                'name_required'        => 'اسم الأصل مطلوب',
                'max_items_error'      => 'وصلت للحد الأقصى من الأصول (%d). قم بترقية الباقة لإضافة المزيد.',
                'external_url_required'=> 'رابط الأصل الخارجي مطلوب',
                'not_found'            => 'الأصل غير موجود',
            )
        );
    } else {
        $labels = $defaults;
    }

    return $labels;
}

/**
 * استخراج الأحرف الأولى من الاسم (للأفاتار)
 */
function yamamah_get_initials( $name ) {
    $parts = explode( ' ', trim( $name ) );
    $initials = '';
    if ( isset( $parts[0] ) ) $initials .= mb_substr( $parts[0], 0, 1 );
    if ( isset( $parts[1] ) ) $initials .= mb_substr( $parts[1], 0, 1 );
    return $initials ?: 'م';
}

/**
 * هل الثيم الحالي يدعم صفحة إعدادات المدونة في لوحة Yamamah؟
 * يعتمد على ydash_blog_page_manifest — مصفوفة فارغة = لا دعم.
 */
function yamamah_theme_has_blog_settings() {
	$manifest = apply_filters( 'ydash_blog_page_manifest', array() );
	return is_array( $manifest ) && ! empty( $manifest );
}

/**
 * تنظيف بيانات حفظ صفحة المدونة وفق manifest الثيم.
 *
 * @param array<string,mixed> $raw
 * @return array<string,string>
 */
function yamamah_sanitize_blog_page_content( $raw ) {
	$manifest = apply_filters( 'ydash_blog_page_manifest', array() );
	if ( ! is_array( $raw ) || empty( $manifest ) ) {
		return array();
	}
	$out = array();
	foreach ( $manifest as $key => $def ) {
		$key = sanitize_key( (string) $key );
		if ( $key === '' ) {
			continue;
		}
		$type = 'text';
		$min  = 0;
		$max  = 0;
		if ( is_array( $def ) ) {
			$type = isset( $def['type'] ) ? (string) $def['type'] : 'text';
			$min  = isset( $def['min'] ) ? (int) $def['min'] : 0;
			$max  = isset( $def['max'] ) ? (int) $def['max'] : 0;
		}
		$val = isset( $raw[ $key ] ) ? wp_unslash( $raw[ $key ] ) : '';
		if ( $type === 'number' ) {
			if ( (string) $val === '' && $key === 'blog_featured_post_id' ) {
				$out[ $key ] = '';
				continue;
			}
			$n = absint( $val );
			if ( $n < $min ) {
				$n = $min;
			}
			if ( $max > 0 && $n > $max ) {
				$n = $max;
			}
			$out[ $key ] = (string) $n;
		} else {
			$out[ $key ] = sanitize_text_field( is_string( $val ) ? $val : '' );
		}
	}
	return $out;
}

/**
 * قائمة مقالات المدونة للوحة Yamamah.
 *
 * @return array<int,array<string,mixed>>
 */
function yamamah_get_dashboard_blog_posts() {
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$out = array();
	foreach ( $posts as $p ) {
		if ( ! $p instanceof WP_Post ) {
			continue;
		}
		$thumb_id = (int) get_post_thumbnail_id( $p->ID );
		$cats     = get_the_category( $p->ID );
		$cat_id   = ! empty( $cats[0] ) ? (int) $cats[0]->term_id : 0;
		$out[]    = array(
			'id'                 => (int) $p->ID,
			'title'              => $p->post_title,
			'content'            => $p->post_content,
			'excerpt'            => $p->post_excerpt,
			'status'             => $p->post_status,
			'date'               => get_the_date( 'Y-m-d', $p ),
			'permalink'          => get_permalink( $p ),
			'featured_image'     => $thumb_id,
			'featured_image_url' => $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
			'category_id'        => $cat_id,
			'category_name'      => $cat_id ? (string) $cats[0]->name : '',
		);
	}
	return $out;
}

/**
 * قائمة تصنيفات المدونة للوحة Yamamah.
 *
 * @return array<int,array<string,mixed>>
 */
function yamamah_get_dashboard_blog_categories() {
	$default_cat = (int) get_option( 'default_category' );
	$terms       = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$link = get_term_link( $term );
		$out[] = array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'count'       => (int) $term->count,
			'is_default'  => ( (int) $term->term_id === $default_cat ),
			'link'        => is_wp_error( $link ) ? '' : (string) $link,
		);
	}
	return $out;
}

/**
 * خريطة الصلاحيات: ربط كل صلاحية بصفحات لوحة التحكم والعمليات المسموحة
 */
function yamamah_permission_map() {
    return array(
        'dashboard-view'      => array( 'pages' => array( 'yamamah-dashboard' ) ),
        'dashboard-analytics' => array( 'pages' => array( 'yamamah-analytics-revenue', 'yamamah-analytics-products', 'yamamah-analytics-settings' ), 'ajax' => array( 'yamamah_get_analytics_revenue', 'yamamah_get_analytics_products', 'yamamah_get_analytics_settings', 'yamamah_save_analytics_settings', 'yamamah_get_chart_data' ) ),
        'products-view'       => array( 'pages' => array( 'yamamah-products', 'yamamah-providers' ), 'ajax' => array( 'yamamah_get_providers', 'yamamah_get_provider' ) ),
        'products-add'        => array( 'pages' => array( 'yamamah-add-product' ) ),
        'products-edit'       => array( 'pages' => array( 'yamamah-add-product' ), 'ajax' => array( 'yamamah_get_product', 'yamamah_save_product', 'yamamah_get_provider', 'yamamah_save_provider', 'yamamah_upload_media' ) ),
        'products-delete'     => array( 'ajax' => array( 'yamamah_delete_product', 'yamamah_delete_provider' ) ),
        'products-categories' => array( 'pages' => array( 'yamamah-categories', 'yamamah-tags', 'yamamah-attributes' ), 'ajax' => array( 'yamamah_add_attr_value', 'yamamah_delete_attr_value' ) ),
        'products-import'     => array( 'pages' => array( 'yamamah-products-import' ), 'ajax' => array( 'yamamah_download_products_template', 'yamamah_export_products_csv', 'yamamah_import_products_csv', 'yamamah_get_import_status' ) ),
        'orders-view'         => array( 'pages' => array( 'yamamah-orders', 'yamamah-order-detail' ) ),
        'orders-edit'         => array( 'ajax' => array( 'yamamah_update_order_status' ) ),
        'orders-delete'       => array( 'ajax' => array( 'yamamah_delete_order' ) ),
        'orders-refund'       => array( 'ajax' => array( 'yamamah_refund_order' ) ),
        'customers-view'      => array( 'pages' => array( 'yamamah-customers', 'yamamah-users' ), 'ajax' => array( 'yamamah_get_customers', 'yamamah_get_customer' ) ),
        'customers-edit'      => array( 'ajax' => array( 'yamamah_save_customer' ) ),
        'customers-delete'    => array( 'ajax' => array( 'yamamah_delete_customer' ) ),
        'shipping-view'       => array( 'pages' => array( 'yamamah-shipping-orders', 'yamamah-shipping-detail', 'yamamah-shipping-rates' ), 'ajax' => array( 'yamamah_get_shipping_orders', 'yamamah_get_shipping_order', 'yamamah_get_shipping_carriers', 'yamamah_get_shipping_quote', 'yamamah_save_shipping_pending', 'yamamah_create_shipping_order', 'yamamah_fetch_label', 'yamamah_get_shipping_wallet', 'yamamah_get_shipping_pricing', 'yamamah_request_shipping_withdraw' ) ),
        'shipping-create'     => array( 'pages' => array( 'yamamah-create-shipment' ), 'ajax' => array( 'yamamah_save_shipping_pending', 'yamamah_create_shipping_order', 'yamamah_get_shipping_carriers', 'yamamah_get_shipping_quote' ) ),
        'shipping-settings'   => array( 'pages' => array( 'yamamah-shipping-settings' ), 'ajax' => array( 'yamamah_get_shipping_settings', 'yamamah_save_shipping_settings', 'yamamah_reregister_shipping' ) ),
        'payments-view'       => array( 'pages' => array( 'yamamah-payment-methods' ), 'ajax' => array( 'yamamah_get_payment_methods', 'yamamah_toggle_payment_method' ) ),
        'payments-settings'   => array( 'pages' => array( 'yamamah-payment-settings' ), 'ajax' => array( 'yamamah_get_payment_settings', 'yamamah_save_payment_settings', 'yamamah_save_tap_keys', 'yamamah_upload_tap_document', 'yamamah_remove_tap_document', 'yamamah_get_tap_documents' ) ),
        'settings-store'      => array( 'pages' => array( 'yamamah-settings-general', 'yamamah-settings-store', 'yamamah-settings-currency', 'yamamah-settings-tax', 'yamamah-settings-email', 'yamamah-settings-tracking', 'yamamah-settings-api-keys' ), 'ajax' => array( 'yamamah_get_store_settings', 'yamamah_save_settings', 'yamamah_save_email_settings', 'yamamah_save_tracking_settings', 'yamamah_verify_tracking', 'yamamah_send_test_email', 'yamamah_check_email_dns', 'yamamah_save_sms_settings', 'yamamah_save_sms_gateway', 'yamamah_test_sms_gateway', 'yamamah_attach_domain', 'yamamah_verify_domain', 'yamamah_poll_domain_job', 'yamamah_remove_domain' ) ),
        'settings-theme'      => array( 'pages' => array( 'yamamah-appearance', 'yamamah-home-content', 'yamamah-site-colors', 'yamamah-home-reviews', 'yamamah-policies', 'yamamah-contact-settings', 'yamamah-blog-settings', 'yamamah-pages' ), 'ajax' => array( 'yamamah_get_appearance_settings', 'yamamah_save_home_content', 'yamamah_restore_home_content', 'yamamah_save_site_colors', 'yamamah_save_logo', 'yamamah_save_policy', 'yamamah_save_contact_page_settings', 'yamamah_upload_media' ) ),
        'settings-users'      => array( 'pages' => array( 'yamamah-users' ) ),
        'settings-admins'     => array( 'pages' => array( 'yamamah-admins' ), 'ajax' => array( 'yamamah_get_admins', 'yamamah_get_admin', 'yamamah_save_admin', 'yamamah_delete_admin' ) ),
        'abandoned-carts'     => array( 'pages' => array( 'yamamah-abandoned-carts' ), 'ajax' => array( 'yamamah_send_cart_reminder', 'yamamah_bulk_cart_reminder', 'yamamah_delete_abandoned_cart', 'yamamah_save_abandoned_settings', 'yamamah_save_abandon_minutes' ) ),
    );
}

/**
 * الصلاحيات الافتراضية لكل دور
 */
function yamamah_role_default_perms( $role ) {
    $all_perms = array_keys( yamamah_permission_map() );
    $defaults  = array(
        'administrator'    => $all_perms,
        'shop_manager'     => array( 'dashboard-view', 'dashboard-analytics', 'products-view', 'products-add', 'products-edit', 'products-delete', 'products-categories', 'products-import', 'orders-view', 'orders-edit', 'orders-refund', 'customers-view', 'customers-edit', 'shipping-view', 'shipping-create', 'shipping-settings', 'payments-view', 'payments-settings', 'settings-store', 'abandoned-carts' ),
        'editor'           => array( 'dashboard-view', 'products-view', 'products-add', 'products-edit', 'products-categories', 'orders-view', 'orders-edit', 'customers-view' ),
        'shipping_manager' => array( 'dashboard-view', 'orders-view', 'shipping-view', 'shipping-create', 'shipping-settings' ),
        'support'          => array( 'dashboard-view', 'orders-view', 'orders-edit', 'customers-view', 'customers-edit' ),
        'custom'           => array(),
        'yamamah_provider' => array(),
    );
    return isset( $defaults[ $role ] ) ? $defaults[ $role ] : array();
}

/**
 * قائمة الصلاحيات الفعلية لمستخدم (نفس منطق اللوحة — بدون { all: true } الفارغ).
 *
 * @param WP_User|null $user
 * @return string[]
 */
function yamamah_user_effective_permissions( $user = null ) {
    if ( ! $user instanceof WP_User ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return array();
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return array_keys( yamamah_permission_map() );
    }

    $perms = get_user_meta( $user->ID, 'yamamah_permissions', true );
    if ( is_array( $perms ) && ! empty( $perms['all'] ) ) {
        return array_keys( yamamah_permission_map() );
    }
    if ( is_array( $perms ) && yamamah_permissions_is_list( $perms ) && ! empty( $perms ) ) {
        return array_values( array_unique( array_map( 'strval', $perms ) ) );
    }

    $yam_role = get_user_meta( $user->ID, 'yamamah_role', true );
    $role     = $yam_role ? (string) $yam_role : ( ! empty( $user->roles ) ? (string) $user->roles[0] : 'editor' );
    return yamamah_role_default_perms( $role );
}

/**
 * هل المصفوفة قائمة صلاحيات (وليس كائن all)؟
 */
function yamamah_permissions_is_list( $perms ) {
    if ( ! is_array( $perms ) || empty( $perms ) ) {
        return false;
    }
    if ( array_key_exists( 'all', $perms ) ) {
        return false;
    }
    return array_keys( $perms ) === range( 0, count( $perms ) - 1 );
}

/**
 * هل المستخدم مزود خدمة مرتبط؟
 *
 * @param WP_User|null $user
 */
function yamamah_user_is_provider( $user = null ) {
    if ( ! $user instanceof WP_User ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return false;
    }
    if ( in_array( 'yamamah_provider', (array) $user->roles, true ) ) {
        return true;
    }
    return (int) get_user_meta( $user->ID, '_linked_service_provider_id', true ) > 0;
}

/**
 * معرف منشور مزود الخدمة المرتبط بالمستخدم.
 *
 * @param WP_User|null $user
 */
function yamamah_user_provider_id( $user = null ) {
    if ( ! $user instanceof WP_User ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return 0;
    }
    return (int) get_user_meta( $user->ID, '_linked_service_provider_id', true );
}

/**
 * هل يمكن للمستخدم دخول تطبيق التاجر / REST ydash؟
 *
 * @param WP_User|int $user
 */
function yamamah_user_can_access_app( $user ) {
    if ( is_numeric( $user ) ) {
        $user = get_userdata( (int) $user );
    }
    if ( ! $user instanceof WP_User ) {
        return false;
    }
    if ( user_can( $user, 'manage_woocommerce' ) ) {
        return true;
    }
    return yamamah_user_is_provider( $user );
}

/**
 * صلاحية مطلوبة لعنصر قائمة bootstrap (معرف القائمة).
 *
 * @return string|null null = متاح دائماً للحسابات المسموحة
 */
function yamamah_menu_item_required_perm( $menu_id ) {
    $map = array(
        'dashboard'       => 'dashboard-view',
        'operations'      => 'orders-view',
        'orders'          => 'orders-view',
        'products'        => 'products-view',
        'providers'       => 'products-view',
        'products-import' => 'products-import',
        'categories'      => 'products-categories',
        'customers'       => 'customers-view',
        'coupons'         => 'orders-view',
        'loyalty'         => 'orders-view',
        'abandoned-carts' => 'abandoned-carts',
        'shipping'        => 'shipping-view',
        'payments'        => 'payments-view',
        'analytics'       => 'dashboard-analytics',
        'reports'         => 'dashboard-analytics',
        'users'           => 'customers-view',
        'admins'          => 'settings-admins',
        'settings'        => 'settings-store',
        'appearance'      => 'settings-theme',
        'media'           => null,
        'support'         => null,
        'account'         => null,
    );
    return array_key_exists( $menu_id, $map ) ? $map[ $menu_id ] : null;
}

/**
 * مستلمو إشعار حجز/طلب: مزود الحجز + أدمن رئيسي + مدراء لديهم orders-view.
 *
 * @param int $order_id
 * @return int[]
 */
function yamamah_booking_notification_recipient_user_ids( $order_id ) {
    $order_id = absint( $order_id );
    $ids      = array();

    $provider_post_id = 0;
    if ( $order_id && function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $provider_post_id = (int) $order->get_meta( '_lumiere_booking_expert_provider_id', true );
            if ( ! $provider_post_id ) {
                foreach ( $order->get_items() as $item ) {
                    $provider_post_id = (int) $item->get_meta( '_lumiere_booking_expert_provider_id', true );
                    if ( $provider_post_id ) {
                        break;
                    }
                }
            }
        }
    }

    if ( $provider_post_id ) {
        $provider_user = (int) get_post_meta( $provider_post_id, '_provider_user_id', true );
        if ( $provider_user > 0 ) {
            $ids[] = $provider_user;
        }
    }

    // أدمن رئيسي + مدراء لديهم orders-view (وليس مزودي خدمة آخرين).
    $staff = get_users(
        array(
            'role__in' => array( 'administrator', 'shop_manager', 'editor', 'shipping_manager', 'support' ),
            'fields'   => array( 'ID' ),
            'number'   => 200,
        )
    );
    foreach ( $staff as $u ) {
        $uid = (int) $u->ID;
        $pu  = get_userdata( $uid );
        if ( ! $pu || yamamah_user_is_provider( $pu ) ) {
            continue;
        }
        if ( in_array( 'administrator', (array) $pu->roles, true ) ) {
            $ids[] = $uid;
            continue;
        }
        if ( ! user_can( $pu, 'manage_woocommerce' ) ) {
            continue;
        }
        $perms = yamamah_user_effective_permissions( $pu );
        if ( in_array( 'orders-view', $perms, true ) ) {
            $ids[] = $uid;
        }
    }

    $ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
    return $ids;
}

/**
 * مستلمو إشعارات عامة للمتجر (عميل جديد، مخزون…) — طاقم فقط بدون مزودي خدمة.
 *
 * @return int[]
 */
function yamamah_staff_notification_recipient_user_ids() {
    return yamamah_booking_notification_recipient_user_ids( 0 );
}

/**
 * التحقق من صلاحية المستخدم الحالي
 */
function yamamah_current_user_can( $perm_id ) {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) return true;
    if ( yamamah_user_is_provider( $user ) ) return false;

    $perms = yamamah_user_effective_permissions( $user );
    return in_array( $perm_id, $perms, true );
}

/**
 * التحقق من أن المستخدم يملك صلاحية الوصول لصفحة معينة
 */
function yamamah_user_can_access_page( $page_slug ) {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) return true;
    if ( yamamah_user_is_provider( $user ) ) return false;

    $perms = yamamah_user_effective_permissions( $user );
    $map   = yamamah_permission_map();
    foreach ( $map as $perm_id => $config ) {
        if ( isset( $config['pages'] ) && in_array( $page_slug, $config['pages'], true ) ) {
            if ( in_array( $perm_id, $perms, true ) ) return true;
        }
    }
    return false;
}

/**
 * التحقق من أن المستخدم يملك صلاحية تنفيذ عملية AJAX
 */
function yamamah_user_can_ajax( $action ) {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) return true;
    if ( yamamah_user_is_provider( $user ) ) {
        $provider_ok = array(
            'yamamah_provider_me',
            'yamamah_provider_update_me',
            'yamamah_provider_my_bookings',
            'yamamah_provider_working_hours',
            'yamamah_get_notifications',
            'yamamah_mark_notification_read',
            'yamamah_mark_all_notifications_read',
            'yamamah_update_profile',
        );
        return in_array( $action, $provider_ok, true );
    }

    $perms = yamamah_user_effective_permissions( $user );
    $map   = yamamah_permission_map();
    $mapped = false;
    foreach ( $map as $perm_id => $config ) {
        if ( isset( $config['ajax'] ) && in_array( $action, $config['ajax'], true ) ) {
            $mapped = true;
            if ( in_array( $perm_id, $perms, true ) ) return true;
        }
    }
    // إجراءات غير مُعرّفة في الخريطة: اسمح لمن لديه manage_woocommerce (توافق رجعي).
    if ( ! $mapped && user_can( $user, 'manage_woocommerce' ) ) {
        return true;
    }
    return false;
}

/**
 * قائمة الصفحات المسموحة للمستخدم الحالي (للقائمة الجانبية)
 */
function yamamah_get_allowed_pages() {
    $user = wp_get_current_user();
    if ( in_array( 'administrator', (array) $user->roles, true ) ) return 'all';
    if ( yamamah_user_is_provider( $user ) ) {
        return array( 'yamamah-my-account', 'yamamah-support' );
    }

    $perms   = yamamah_user_effective_permissions( $user );
    $allowed = array();
    $map     = yamamah_permission_map();
    foreach ( $map as $perm_id => $config ) {
        if ( in_array( $perm_id, $perms, true ) && isset( $config['pages'] ) ) {
            $allowed = array_merge( $allowed, $config['pages'] );
        }
    }
    return array_unique( $allowed );
}

/**
 * ترجمة حالة الطلب
 */
function yamamah_order_status_label( $status ) {
    $labels = array(
        'pending'    => 'قيد الانتظار',
        'processing' => 'قيد المعالجة',
        'on-hold'    => 'معلق',
        'completed'  => 'مكتمل',
        'cancelled'  => 'ملغى',
        'refunded'   => 'مسترجع',
        'failed'     => 'فشل',
        'shipped'    => 'قيد التوصيل',
    );
    $status = str_replace( 'wc-', '', $status );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
}

/**
 * كلاس Badge حسب الحالة
 */
function yamamah_order_status_badge_class( $status ) {
    $status = str_replace( 'wc-', '', $status );
    $classes = array(
        'pending'    => 'badge-yellow',
        'processing' => 'badge-yellow',
        'on-hold'    => 'badge-amber',
        'completed'  => 'badge-green',
        'cancelled'  => 'badge-red',
        'refunded'   => 'badge-purple',
        'failed'     => 'badge-red',
        'shipped'    => 'badge-blue',
    );
    return isset( $classes[ $status ] ) ? $classes[ $status ] : 'badge-gray';
}

/**
 * كلاس Badge لحالة المنتج
 */
function yamamah_product_status_badge( $status, $stock_status = '' ) {
    if ( $stock_status === 'outofstock' ) {
        return '<span class="badge badge-red">نفاذ المخزون</span>';
    }
    if ( $status === 'publish' ) {
        return '<span class="badge badge-green">منشور</span>';
    }
    if ( $status === 'draft' ) {
        return '<span class="badge badge-gray">مسودة</span>';
    }
    return '<span class="badge badge-yellow">' . esc_html( $status ) . '</span>';
}

/**
 * ترجمة نوع الكوبون
 */
function yamamah_coupon_type_label( $type ) {
    $types = array(
        'percent'       => 'نسبة',
        'fixed_cart'    => 'مبلغ ثابت',
        'fixed_product' => 'خصم منتج',
    );
    return isset( $types[ $type ] ) ? $types[ $type ] : $type;
}

/**
 * وقت منذ... بالعربية
 */
function yamamah_time_ago( $timestamp ) {
    // Compare in UTC: numeric unix stamps use time(); MySQL datetimes are WP local.
    if ( is_numeric( $timestamp ) ) {
        $then = (int) $timestamp;
        $now  = time();
    } else {
        $gmt  = get_gmt_from_date( (string) $timestamp );
        $then = $gmt ? strtotime( $gmt . ' UTC' ) : strtotime( (string) $timestamp );
        $now  = time();
    }
    if ( ! $then ) {
        return '';
    }
    $diff = max( 0, $now - $then );

    if ( $diff < 60 ) return 'منذ لحظات';
    if ( $diff < 3600 ) return 'منذ ' . floor( $diff / 60 ) . ' دقيقة';
    if ( $diff < 86400 ) return 'منذ ' . floor( $diff / 3600 ) . ' ساعة';
    if ( $diff < 604800 ) return 'منذ ' . floor( $diff / 86400 ) . ' يوم';

    return date_i18n( 'Y-m-d', $then );
}

/**
 * تنسيق حجم الملف
 */
function yamamah_format_file_size( $bytes ) {
    if ( $bytes >= 1048576 ) {
        return round( $bytes / 1048576, 1 ) . ' MB';
    }
    return round( $bytes / 1024 ) . ' KB';
}

/**
 * كشف صفحات WooCommerce التي يجب تغليفها بتخطيط اليمامة
 * يعيد معرف عنصر القائمة المقابل، أو false
 */
function yamamah_get_wc_page_id() {
    global $pagenow;

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return false;
    }

    // Transitional: wrap the native WC product editor (post.php?post=X) until fully replaced
    if ( $pagenow === 'post.php' ) {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        if ( $post_id && get_post_type( $post_id ) === 'product' ) {
            return 'yamamah-add-product';
        }
    }

    return false;
}

/**
 * Bug #113: تسجيل أنواع السمات المخصصة (لون/زر/صورة) لدى WooCommerce حتى لا
 * يرفضها `wc_get_attribute_types()` فتعود القيمة دائماً إلى "قائمة منسدلة".
 *
 * @param array $types أنواع السمات المسجّلة.
 * @return array
 */
function yamamah_register_attribute_types( $types ) {
    if ( ! is_array( $types ) ) {
        $types = array();
    }
    $custom = array(
        'color'  => 'لون',
        'button' => 'زر',
        'image'  => 'صورة',
    );
    foreach ( $custom as $key => $label ) {
        if ( ! isset( $types[ $key ] ) ) {
            $types[ $key ] = $label;
        }
    }
    return $types;
}
add_filter( 'product_attributes_type_selector', 'yamamah_register_attribute_types' );
add_filter( 'woocommerce_attribute_types', 'yamamah_register_attribute_types' );

/**
 * بناء قائمة الشريط الجانبي
 */
function yamamah_render_sidebar_menu() {
    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $wc_page_id   = yamamah_get_wc_page_id();
    if ( $wc_page_id ) {
        $current_page = $wc_page_id;
    }
    if ( empty( $current_page ) ) {
        $current_page = 'yamamah-dashboard';
    }

    $store_type = yamamah_get_store_type();
    $labels     = yamamah_get_catalog_labels();

    $orders_label    = $labels['orders_label'];
    $products_label  = $labels['section'];
    $all_items_label = $labels['all_items'];
    $add_item_label  = $labels['add_item'];
    $analytics_items = $labels['analytics_title'];
    $demo_label      = $labels['demo_label'];

    $operations_children = array(
        array( 'id' => 'yamamah-operations', 'label' => 'الرئيسية',    'url' => admin_url( 'admin.php?page=yamamah-operations' ) ),
        array( 'id' => 'yamamah-orders',     'label' => $orders_label, 'url' => admin_url( 'admin.php?page=yamamah-orders' ) ),
        array( 'id' => 'yamamah-customers',  'label' => 'العملاء',     'url' => admin_url( 'admin.php?page=yamamah-customers' ) ),
        array( 'id' => 'yamamah-loyalty',    'label' => 'نقاط الولاء', 'url' => admin_url( 'admin.php?page=yamamah-loyalty' ) ),
        array( 'id' => 'yamamah-coupons',    'label' => 'القسائم',     'url' => admin_url( 'admin.php?page=yamamah-coupons' ) ),
    );

    if ( $store_type === 'retail' ) {
        $operations_children[] = array( 'id' => 'yamamah-abandoned-carts', 'label' => 'السلات المتروكة', 'url' => admin_url( 'admin.php?page=yamamah-abandoned-carts' ) );
    }

    $operations_children[] = array( 'id' => 'yamamah-reports', 'label' => 'التقارير', 'url' => admin_url( 'admin.php?page=yamamah-reports' ) );

    $products_children = array(
        array( 'id' => 'yamamah-products',    'label' => $all_items_label, 'url' => admin_url( 'admin.php?page=yamamah-products' ) ),
        array( 'id' => 'yamamah-add-product', 'label' => $add_item_label,  'url' => admin_url( 'admin.php?page=yamamah-add-product' ) ),
    );

    if ( $store_type === 'booking' ) {
        $products_children[] = array( 'id' => 'yamamah-providers', 'label' => 'مزودي الخدمات', 'url' => admin_url( 'admin.php?page=yamamah-providers' ) );
    }

    $products_children[] = array( 'id' => 'yamamah-categories', 'label' => 'التصنيفات', 'url' => admin_url( 'admin.php?page=yamamah-categories' ) );
    $products_children[] = array( 'id' => 'yamamah-tags',       'label' => 'الوسوم',    'url' => admin_url( 'admin.php?page=yamamah-tags' ) );

    if ( $store_type !== 'booking' ) {
        $products_children[] = array( 'id' => 'yamamah-attributes', 'label' => 'السمات', 'url' => admin_url( 'admin.php?page=yamamah-attributes' ) );
    }

    if ( $store_type === 'retail' ) {
        $products_children[] = array( 'id' => 'yamamah-products-import', 'label' => 'استيراد/تصدير CSV', 'url' => admin_url( 'admin.php?page=yamamah-products-import' ) );
    }

    $settings_children = array(
        array( 'id' => 'yamamah-settings-general',  'label' => 'عام',               'url' => admin_url( 'admin.php?page=yamamah-settings-general' ) ),
        array( 'id' => 'yamamah-settings-store',    'label' => 'المتجر',            'url' => admin_url( 'admin.php?page=yamamah-settings-store' ) ),
        array( 'id' => 'yamamah-settings-currency', 'label' => 'العملة',            'url' => admin_url( 'admin.php?page=yamamah-settings-currency' ) ),
        array( 'id' => 'yamamah-settings-tax',      'label' => 'الضرائب',           'url' => admin_url( 'admin.php?page=yamamah-settings-tax' ) ),
        array( 'id' => 'yamamah-settings-email',    'label' => 'البريد الإلكتروني', 'url' => admin_url( 'admin.php?page=yamamah-settings-email' ) ),
        array( 'id' => 'yamamah-settings-tracking', 'label' => 'أكواد التتبع',      'url' => admin_url( 'admin.php?page=yamamah-settings-tracking' ) ),
        array( 'id' => 'yamamah-settings-api-keys', 'label' => 'مفاتيح API',        'url' => admin_url( 'admin.php?page=yamamah-settings-api-keys' ) ),
    );

    if ( $store_type === 'booking' ) {
        $settings_children[] = array( 'id' => 'yamamah-settings-rental', 'label' => 'إعدادات الحجز', 'url' => admin_url( 'admin.php?page=yamamah-settings-rental' ) );
    } elseif ( $store_type === 'rental' ) {
        $settings_children[] = array( 'id' => 'yamamah-settings-rental', 'label' => 'إعدادات التأجير', 'url' => admin_url( 'admin.php?page=yamamah-settings-rental' ) );
    }

    $settings_children[] = array( 'id' => 'yamamah-settings-sms', 'label' => 'الرسائل النصية', 'url' => admin_url( 'admin.php?page=yamamah-settings-sms' ) );
    $settings_children[] = array( 'id' => 'yamamah-settings-domain', 'label' => 'النطاق المخصص', 'url' => admin_url( 'admin.php?page=yamamah-settings-domain' ) );

    $menu_items = array(
        array(
            'id'    => 'yamamah-dashboard',
            'label' => 'الرئيسية',
            'icon'  => 'home',
            'url'   => admin_url( 'admin.php?page=yamamah-dashboard' ),
        ),
        array(
            'id'       => 'yamamah-operations',
            'label'    => 'مدير العمليات',
            'icon'     => 'settings',
            'children' => $operations_children,
        ),
        array(
            'id'       => 'yamamah-products',
            'label'    => $products_label,
            'icon'     => 'package',
            'children' => $products_children,
        ),
    );

    $menu_items[] = array(
        'id'       => 'yamamah-payments',
        'label'    => 'المدفوعات',
        'icon'     => 'creditCard',
        'children' => array(
            array( 'id' => 'yamamah-payment-methods',  'label' => 'طرق الدفع',     'url' => admin_url( 'admin.php?page=yamamah-payment-methods' ) ),
            array( 'id' => 'yamamah-payment-settings', 'label' => 'إعدادات الدفع', 'url' => admin_url( 'admin.php?page=yamamah-payment-settings' ) ),
        ),
    );

    if ( $store_type === 'retail' ) {
        $menu_items[] = array(
            'id'       => 'yamamah-shipping',
            'label'    => 'الشحن',
            'icon'     => 'truck',
            'children' => array(
                array( 'id' => 'yamamah-shipping-orders',   'label' => 'طلبات الشحن',   'url' => admin_url( 'admin.php?page=yamamah-shipping-orders' ) ),
                array( 'id' => 'yamamah-shipping-rates',    'label' => 'أسعار الشحن',   'url' => admin_url( 'admin.php?page=yamamah-shipping-rates' ) ),
                array( 'id' => 'yamamah-shipping-settings', 'label' => 'إعدادات الشحن', 'url' => admin_url( 'admin.php?page=yamamah-shipping-settings' ) ),
            ),
        );
    }

    $menu_items[] = array(
        'id'       => 'yamamah-analytics',
        'label'    => 'التحليلات',
        'icon'     => 'barChart',
        'children' => array(
            array( 'id' => 'yamamah-analytics-revenue',  'label' => 'الإيرادات',          'url' => admin_url( 'admin.php?page=yamamah-analytics-revenue' ) ),
            array( 'id' => 'yamamah-analytics-products', 'label' => $analytics_items,      'url' => admin_url( 'admin.php?page=yamamah-analytics-products' ) ),
            array( 'id' => 'yamamah-analytics-settings', 'label' => 'إعدادات التحليلات', 'url' => admin_url( 'admin.php?page=yamamah-analytics-settings' ) ),
        ),
    );
    $menu_items[] = array(
        'id'       => 'yamamah-users-group',
        'label'    => 'المستخدمين',
        'icon'     => 'users',
        'children' => array(
            array( 'id' => 'yamamah-users',      'label' => 'العملاء',  'url' => admin_url( 'admin.php?page=yamamah-users' ) ),
            array( 'id' => 'yamamah-admins',     'label' => 'المدراء', 'url' => admin_url( 'admin.php?page=yamamah-admins' ) ),
            array( 'id' => 'yamamah-my-account', 'label' => 'حسابي',   'url' => admin_url( 'admin.php?page=yamamah-my-account' ) ),
        ),
    );
    $menu_items[] = array(
        'id'       => 'yamamah-store-settings-group',
        'label'    => 'إعدادات المتجر',
        'icon'     => 'store',
        'children' => $settings_children,
    );
    $appearance_children = array(
        array( 'id' => 'yamamah-appearance',   'label' => 'المظهر العام',    'url' => admin_url( 'admin.php?page=yamamah-appearance' ) ),
        array( 'id' => 'yamamah-home-content', 'label' => 'الصفحة الرئيسية', 'url' => admin_url( 'admin.php?page=yamamah-home-content' ) ),
        array( 'id' => 'yamamah-site-colors',  'label' => 'ألوان الموقع',    'url' => admin_url( 'admin.php?page=yamamah-site-colors' ) ),
        array( 'id' => 'yamamah-home-reviews', 'label' => 'التعليقات',        'url' => admin_url( 'admin.php?page=yamamah-home-reviews' ) ),
        array( 'id' => 'yamamah-policies',     'label' => 'السياسات',         'url' => admin_url( 'admin.php?page=yamamah-policies' ) ),
        array( 'id' => 'yamamah-contact-settings', 'label' => 'تواصل معنا', 'url' => admin_url( 'admin.php?page=yamamah-contact-settings' ) ),
    );
    if ( yamamah_theme_has_blog_settings() ) {
        $appearance_children[] = array(
            'id'    => 'yamamah-blog-settings',
            'label' => 'المدونة',
            'url'   => admin_url( 'admin.php?page=yamamah-blog-settings' ),
        );
    }
    $pages_manifest = apply_filters( 'ydash_get_pages_manifest', array() );
    if ( is_array( $pages_manifest ) && ! empty( $pages_manifest ) ) {
        $appearance_children[] = array(
            'id'    => 'yamamah-pages',
            'label' => 'الصفحات',
            'url'   => admin_url( 'admin.php?page=yamamah-pages' ),
        );
    }
    $menu_items[] = array(
        'id'       => 'yamamah-appearance-group',
        'label'    => 'إعدادات المظهر',
        'icon'     => 'palette',
        'children' => $appearance_children,
    );
    $menu_items[] = array(
        'id'    => 'yamamah-media',
        'label' => 'الوسائط',
        'icon'  => 'image',
        'url'   => admin_url( 'admin.php?page=yamamah-media' ),
    );
    $menu_items[] = array(
        'id'    => 'yamamah-support',
        'label' => 'الدعم الفني',
        'icon'  => 'helpCircle',
        'url'   => admin_url( 'admin.php?page=yamamah-support' ),
    );

    $allowed_pages = yamamah_get_allowed_pages();

    foreach ( $menu_items as $item ) :
        $has_children = isset( $item['children'] ) && ! empty( $item['children'] );

        if ( $has_children && $allowed_pages !== 'all' ) {
            $visible_children = array();
            foreach ( $item['children'] as $child ) {
                if ( in_array( $child['id'], $allowed_pages, true ) ) {
                    $visible_children[] = $child;
                }
            }
            if ( empty( $visible_children ) ) continue;
            $item['children'] = $visible_children;
        } elseif ( ! $has_children && $allowed_pages !== 'all' ) {
            if ( isset( $item['url'] ) && ! in_array( $item['id'], $allowed_pages, true )
                 && ! in_array( $item['id'], array( 'yamamah-dashboard', 'yamamah-my-account', 'yamamah-support' ), true ) ) {
                continue;
            }
        }

        $is_active = ( $current_page === $item['id'] );
        $is_parent_active = false;

        if ( $has_children ) {
            foreach ( $item['children'] as $child ) {
                if ( $current_page === $child['id'] ) {
                    $is_parent_active = true;
                    break;
                }
            }
        }

        $url = isset( $item['url'] ) ? $item['url'] : '#';
        ?>
        <?php if ( $has_children ) : ?>
            <button class="menu-item <?php echo $is_parent_active ? 'active' : ''; ?>"
                    onclick="yamamahToggleSubmenu('submenu-<?php echo esc_attr( $item['id'] ); ?>', this)">
                <span class="menu-icon" data-icon="<?php echo esc_attr( $item['icon'] ); ?>"></span>
                <span class="menu-label"><?php echo esc_html( $item['label'] ); ?></span>
                <span class="menu-arrow <?php echo $is_parent_active ? 'expanded' : ''; ?>" data-icon="chevronDown"></span>
            </button>
            <div class="submenu <?php echo $is_parent_active ? 'open' : ''; ?>" id="submenu-<?php echo esc_attr( $item['id'] ); ?>">
                <?php foreach ( $item['children'] as $child ) : ?>
                    <a href="<?php echo esc_url( $child['url'] ); ?>"
                       class="sub-item <?php echo $current_page === $child['id'] ? 'active' : ''; ?>">
                        <?php echo esc_html( $child['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <a href="<?php echo esc_url( $url ); ?>"
               class="menu-item <?php echo $is_active ? 'active' : ''; ?>">
                <span class="menu-icon" data-icon="<?php echo esc_attr( $item['icon'] ); ?>"></span>
                <span class="menu-label"><?php echo esc_html( $item['label'] ); ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach;
}

/**
 * صفحة Pagination
 */
function yamamah_pagination( $current_page, $total_pages, $base_url ) {
    if ( $total_pages <= 1 ) return;
    ?>
    <div class="pagination">
        <?php if ( $current_page > 1 ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>" class="btn btn-sm btn-outline">‹</a>
        <?php else : ?>
            <button disabled>‹</button>
        <?php endif; ?>

        <?php for ( $i = 1; $i <= min( $total_pages, 5 ); $i++ ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $i, $base_url ) ); ?>"
               class="<?php echo $i === $current_page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ( $current_page < $total_pages ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>" class="btn btn-sm btn-outline">›</a>
        <?php else : ?>
            <button disabled>›</button>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * تعقيم قيمة واحدة من محتوى الصفحة الرئيسية (مفتاح يساعد على اختيار نوع التعقيم).
 *
 * @param mixed  $val قيمة خام.
 * @param string $key آخر مفتاح في المسار (مثل hero1_text أو desc).
 * @return mixed
 */
function yamamah_sanitize_home_content_scalar( $val, $key = '' ) {
	$key = (string) $key;
	if ( strpos( $key, 'image' ) !== false ) {
		return absint( $val );
	}
	$s = isset( $val ) ? wp_unslash( (string) $val ) : '';
	if ( preg_match( '/(^|_)(desc|text|body|content|html|paragraph|story|subtitle)$/', $key ) ) {
		return wp_kses_post( $s );
	}
	return sanitize_text_field( $s );
}

/**
 * تعقيم متكرر لمصفوفة home_content (يدعم المصفوفات المتداخلة والفهارس الرقمية).
 *
 * @param mixed $val قيمة أو مصفوفة.
 * @param string $key_hint مفتاح السياق للورقة النصية.
 * @return mixed
 */
function yamamah_sanitize_home_content_branch( $val, $key_hint = '' ) {
	if ( is_array( $val ) ) {
		$out = array();
		foreach ( $val as $k => $v ) {
			if ( is_int( $k ) || ( is_string( $k ) && $k !== '' && ctype_digit( $k ) ) ) {
				$idx = (int) $k;
				$out[ $idx ] = yamamah_sanitize_home_content_branch( $v, $key_hint );
			} else {
				$sk = sanitize_key( (string) $k );
				if ( $sk === '' ) {
					continue;
				}
				$out[ $sk ] = yamamah_sanitize_home_content_branch( $v, $sk );
			}
		}
		if ( array_keys( $out ) === range( 0, count( $out ) - 1 ) ) {
			ksort( $out, SORT_NUMERIC );
		}
		return $out;
	}
	return yamamah_sanitize_home_content_scalar( $val, $key_hint );
}

/**
 * تعقيم كامل لـ $_POST['home_content'] قبل الحفظ.
 *
 * @param array $raw مصفوفة خام.
 * @return array
 */
function yamamah_sanitize_home_content_tree( array $raw ) {
	$saved = array();
	foreach ( $raw as $key => $val ) {
		$sk = sanitize_key( (string) $key );
		if ( $sk === '' ) {
			continue;
		}
		$saved[ $sk ] = yamamah_sanitize_home_content_branch( $val, $sk );
	}
	return apply_filters( 'ydash_sanitize_home_content', $saved, $raw );
}

/**
 * رفع حدود رفع الملفات في لوحة اليمامة (plupload + wp_max_upload_size).
 * بعض بيئات PHP تُبلّغ عن 1M رغم إعدادات wp-config.
 */
add_action(
	'init',
	static function () {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			@ini_set( 'upload_max_filesize', '64M' );
			@ini_set( 'post_max_size', '64M' );
		}
	},
	0
);

add_filter(
	'upload_size_limit',
	static function ( $size, $u_bytes, $p_bytes ) {
		unset( $u_bytes, $p_bytes );
		$floor = 64 * MB_IN_BYTES;
		return max( (int) $size, $floor );
	},
	20,
	3
);

/**
 * مزامنة صورة مزود الخدمة (WorkForce) مع المنتج المرتبط وإزالة رابط الصورة التجريبي.
 *
 * @param int $provider_id   معرف service_provider.
 * @param int $attachment_id معرف المرفق (0 = حذف الصورة).
 */
function yamamah_sync_workforce_provider_image( $provider_id, $attachment_id = 0 ) {
	$provider_id = absint( $provider_id );
	if ( ! $provider_id || 'service_provider' !== get_post_type( $provider_id ) ) {
		return;
	}

	$attachment_id = absint( $attachment_id );

	if ( $attachment_id ) {
		set_post_thumbnail( $provider_id, $attachment_id );
	} else {
		delete_post_thumbnail( $provider_id );
	}
	delete_post_meta( $provider_id, '_wf_remote_image_url' );

	if ( ! class_exists( 'WC_Product' ) ) {
		return;
	}

	$product_id = absint( get_post_meta( $provider_id, '_wf_product_id', true ) );
	if ( $product_id <= 0 ) {
		return;
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return;
	}

	$product->set_image_id( $attachment_id );
	$product->save();
	update_post_meta( $product_id, '_wf_linked_provider', $provider_id );
}

/**
 * مزامنة صورة منتج WooCommerce مع مزود الخدمة المرتبط (WorkForce).
 *
 * @param int $product_id    معرف المنتج.
 * @param int $attachment_id معرف المرفق (0 = حذف الصورة).
 */
function yamamah_sync_workforce_product_image( $product_id, $attachment_id = 0 ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return;
	}

	$attachment_id = absint( $attachment_id );

	$provider_id = absint( get_post_meta( $product_id, '_wf_linked_provider', true ) );
	if ( ! $provider_id ) {
		$found = get_posts(
			array(
				'post_type'      => 'service_provider',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => '_wf_product_id',
						'value' => $product_id,
					),
				),
			)
		);
		$provider_id = ! empty( $found ) ? (int) $found[0] : 0;
	}

	if ( ! $provider_id || 'service_provider' !== get_post_type( $provider_id ) ) {
		return;
	}

	if ( $attachment_id ) {
		set_post_thumbnail( $provider_id, $attachment_id );
	} else {
		delete_post_thumbnail( $provider_id );
	}
	delete_post_meta( $provider_id, '_wf_remote_image_url' );

	update_post_meta( $provider_id, '_wf_product_id', $product_id );
	update_post_meta( $product_id, '_wf_linked_provider', $provider_id );
}

/**
 * التحقق من قيمة العربون (نسبة أو مبلغ ثابت).
 *
 * @param mixed  $raw_value القيمة المرسلة.
 * @param string $type      percentage|fixed.
 * @return array{valid: bool, value: float, message: string}
 */
function yamamah_validate_deposit_value( $raw_value, $type = 'percentage' ) {
	$type = in_array( $type, array( 'percentage', 'fixed' ), true ) ? $type : 'percentage';
	$raw  = is_string( $raw_value ) ? trim( $raw_value ) : $raw_value;

	if ( $raw === '' || $raw === null ) {
		return array(
			'valid'   => false,
			'value'   => 0.0,
			'message' => 'يرجى إدخال قيمة العربون.',
		);
	}

	if ( ! is_numeric( $raw ) ) {
		return array(
			'valid'   => false,
			'value'   => 0.0,
			'message' => 'قيمة العربون يجب أن تكون رقماً صالحاً.',
		);
	}

	$value = (float) $raw;

	if ( 'percentage' === $type ) {
		if ( $value < 0 || $value > 100 ) {
			return array(
				'valid'   => false,
				'value'   => 0.0,
				'message' => 'نسبة العربون يجب أن تكون بين 0 و 100.',
			);
		}
	} elseif ( $value < 0 ) {
		return array(
			'valid'   => false,
			'value'   => 0.0,
			'message' => 'مبلغ العربون لا يمكن أن يكون سالباً.',
		);
	}

	return array(
		'valid'   => true,
		'value'   => $value,
		'message' => '',
	);
}
