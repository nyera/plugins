<?php
/**
 * WooCommerce-compatible CSV Import/Export for Yamamah Dashboard.
 *
 * Implements the same column layout used by WooCommerce's built-in CSV
 * importer/exporter so merchants can switch between the two without
 * data loss. Categories use the `>` separator (e.g. "Clothing > T-Shirts"),
 * tags are comma-separated, and images are URLs separated by commas
 * (first one becomes the featured image, rest become the gallery).
 *
 * For large imports (>100 rows) the work is offloaded to wp_schedule_single_event
 * to avoid PHP timeouts on the synchronous AJAX path.
 */

defined( 'ABSPATH' ) || exit;

class Yamamah_CSV {

	/**
	 * The canonical column order used in templates and exports.
	 * Mirrors WooCommerce >= 6.x export schema (subset of the most-used columns).
	 */
	public static function columns() {
		return array(
			'ID',
			'Type',
			'SKU',
			'Name',
			'Published',
			'Is featured?',
			'Visibility in catalog',
			'Short description',
			'Description',
			'Date sale price starts',
			'Date sale price ends',
			'Tax status',
			'Tax class',
			'In stock?',
			'Stock',
			'Low stock amount',
			'Backorders allowed?',
			'Sold individually?',
			'Weight (kg)',
			'Length (cm)',
			'Width (cm)',
			'Height (cm)',
			'Allow customer reviews?',
			'Purchase note',
			'Sale price',
			'Regular price',
			'Categories',
			'Tags',
			'Shipping class',
			'Images',
			'Download limit',
			'Download expiry days',
			'Parent',
			'Grouped products',
			'Upsells',
			'Cross-sells',
			'External URL',
			'Button text',
			'Position',
			'Attribute 1 name',
			'Attribute 1 value(s)',
			'Attribute 1 visible',
			'Attribute 1 global',
			'Attribute 2 name',
			'Attribute 2 value(s)',
			'Attribute 2 visible',
			'Attribute 2 global',
			'Attribute 3 name',
			'Attribute 3 value(s)',
			'Attribute 3 visible',
			'Attribute 3 global',
		);
	}

	/**
	 * One example row to seed the empty template.
	 */
	public static function example_row() {
		return array(
			'',                                  // ID
			'simple',                            // Type
			'YM-001',                            // SKU
			'تيشيرت قطن',                      // Name
			'1',                                 // Published
			'0',                                 // Is featured?
			'visible',                           // Visibility in catalog
			'تيشيرت أبيض، قطن 100%',          // Short description
			'تيشيرت قطني عالي الجودة...',     // Description
			'',                                  // Date sale price starts
			'',                                  // Date sale price ends
			'taxable',                           // Tax status
			'',                                  // Tax class
			'1',                                 // In stock?
			'50',                                // Stock
			'5',                                 // Low stock amount
			'0',                                 // Backorders allowed?
			'0',                                 // Sold individually?
			'0.2',                               // Weight (kg)
			'',                                  // Length (cm)
			'',                                  // Width (cm)
			'',                                  // Height (cm)
			'1',                                 // Allow customer reviews?
			'',                                  // Purchase note
			'85',                                // Sale price
			'99',                                // Regular price
			'ملابس > تيشيرتات, جديد',         // Categories
			'صيف, قطن',                         // Tags
			'',                                  // Shipping class
			'https://example.com/img1.jpg, https://example.com/img2.jpg', // Images
			'',                                  // Download limit
			'',                                  // Download expiry days
			'',                                  // Parent
			'',                                  // Grouped products
			'',                                  // Upsells
			'',                                  // Cross-sells
			'',                                  // External URL
			'',                                  // Button text
			'0',                                 // Position
			'الحجم',                            // Attribute 1 name
			'S, M, L, XL',                       // Attribute 1 value(s)
			'1',                                 // Attribute 1 visible
			'0',                                 // Attribute 1 global
			'اللون',                            // Attribute 2 name
			'أبيض, أسود',                       // Attribute 2 value(s)
			'1',                                 // Attribute 2 visible
			'0',                                 // Attribute 2 global
			'',                                  // Attribute 3 name
			'',                                  // Attribute 3 value(s)
			'',                                  // Attribute 3 visible
			'',                                  // Attribute 3 global
		);
	}

	/**
	 * Stream a CSV download with UTF-8 BOM so Excel renders Arabic correctly.
	 */
	public static function send_csv_headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		// UTF-8 BOM
		echo "\xEF\xBB\xBF";
	}

	/**
	 * Output a CSV template (headers + 1 example row).
	 */
	public static function output_template() {
		self::send_csv_headers( 'yamamah-products-template-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, self::columns() );
		fputcsv( $out, self::example_row() );
		fclose( $out );
	}

	/**
	 * Export ALL products (paged in chunks of 200) as a single CSV download.
	 */
	public static function export_all() {
		self::send_csv_headers( 'yamamah-products-export-' . gmdate( 'Y-m-d-His' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, self::columns() );

		$page  = 1;
		$batch = 200;
		do {
			$query = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => $batch,
				'paged'          => $page,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			) );
			if ( ! $query->have_posts() ) {
				break;
			}
			foreach ( $query->posts as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product ) continue;
				fputcsv( $out, self::product_to_row( $product ) );
			}
			$page += 1;
			$max_pages = (int) $query->max_num_pages;
			wp_reset_postdata();
		} while ( $page <= $max_pages );

		fclose( $out );
	}

	/**
	 * Convert a WC_Product into a CSV row using the same order as columns().
	 */
	public static function product_to_row( $product ) {
		$cat_ids = $product->get_category_ids();
		$tag_ids = $product->get_tag_ids();

		$cats = array();
		foreach ( $cat_ids as $tid ) {
			$path = self::term_path( $tid, 'product_cat' );
			if ( $path ) $cats[] = $path;
		}
		$tags = array();
		foreach ( $tag_ids as $tid ) {
			$t = get_term( $tid, 'product_tag' );
			if ( $t && ! is_wp_error( $t ) ) $tags[] = $t->name;
		}

		$image_urls = array();
		$main_id = $product->get_image_id();
		if ( $main_id ) {
			$src = wp_get_attachment_url( $main_id );
			if ( $src ) $image_urls[] = $src;
		}
		foreach ( $product->get_gallery_image_ids() as $gid ) {
			$src = wp_get_attachment_url( $gid );
			if ( $src ) $image_urls[] = $src;
		}

		$attrs   = $product->get_attributes();
		$attr_cols = array();
		$idx = 0;
		foreach ( $attrs as $key => $attr ) {
			if ( $idx >= 3 ) break;
			$idx++;
			if ( $attr instanceof WC_Product_Attribute ) {
				$attr_cols[] = $attr->get_name();
				$values = $attr->is_taxonomy() ? wp_list_pluck( $attr->get_terms() ?: array(), 'name' ) : $attr->get_options();
				$attr_cols[] = implode( ', ', (array) $values );
				$attr_cols[] = $attr->get_visible() ? '1' : '0';
				$attr_cols[] = $attr->is_taxonomy() ? '1' : '0';
			} else {
				$attr_cols[] = ''; $attr_cols[] = ''; $attr_cols[] = ''; $attr_cols[] = '';
			}
		}
		while ( count( $attr_cols ) < 12 ) $attr_cols[] = '';

		$type = $product->get_type();
		$type_value = in_array( $type, array( 'simple', 'variable', 'grouped', 'external' ), true ) ? $type : 'simple';

		$row = array(
			$product->get_id(),
			$type_value,
			$product->get_sku(),
			$product->get_name(),
			$product->get_status() === 'publish' ? '1' : '0',
			$product->get_featured() ? '1' : '0',
			$product->get_catalog_visibility(),
			$product->get_short_description(),
			$product->get_description(),
			$product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date( 'Y-m-d' ) : '',
			$product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date( 'Y-m-d' ) : '',
			$product->get_tax_status(),
			$product->get_tax_class(),
			$product->is_in_stock() ? '1' : '0',
			$product->get_stock_quantity(),
			$product->get_low_stock_amount(),
			$product->get_backorders() === 'yes' ? '1' : ( $product->get_backorders() === 'notify' ? 'notify' : '0' ),
			$product->get_sold_individually() ? '1' : '0',
			$product->get_weight(),
			$product->get_length(),
			$product->get_width(),
			$product->get_height(),
			$product->get_reviews_allowed() ? '1' : '0',
			$product->get_purchase_note(),
			$product->get_sale_price(),
			$product->get_regular_price(),
			implode( ', ', $cats ),
			implode( ', ', $tags ),
			$product->get_shipping_class(),
			implode( ', ', $image_urls ),
			method_exists( $product, 'get_download_limit' ) ? $product->get_download_limit() : '',
			method_exists( $product, 'get_download_expiry' ) ? $product->get_download_expiry() : '',
			$product->get_parent_id() ?: '',
			method_exists( $product, 'get_children' ) && 'grouped' === $type_value ? implode( ',', $product->get_children() ) : '',
			implode( ',', method_exists( $product, 'get_upsell_ids' ) ? $product->get_upsell_ids() : array() ),
			implode( ',', method_exists( $product, 'get_cross_sell_ids' ) ? $product->get_cross_sell_ids() : array() ),
			method_exists( $product, 'get_product_url' ) ? $product->get_product_url() : '',
			method_exists( $product, 'get_button_text' ) ? $product->get_button_text() : '',
			$product->get_menu_order(),
		);

		return array_merge( $row, $attr_cols );
	}

	/**
	 * Build a "Parent > Child" path string for a hierarchical term ID.
	 */
	public static function term_path( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) return '';
		$names = array( $term->name );
		while ( $term->parent ) {
			$term = get_term( $term->parent, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) break;
			array_unshift( $names, $term->name );
		}
		return implode( ' > ', $names );
	}

	/**
	 * Resolve a "Parent > Child" path to a term ID, creating missing terms if requested.
	 *
	 * @return int|0
	 */
	public static function resolve_term_path( $path, $taxonomy, $create = true ) {
		$names = array_map( 'trim', explode( '>', $path ) );
		$parent = 0;
		$term_id = 0;
		foreach ( $names as $name ) {
			if ( $name === '' ) continue;
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( $existing && (int) $existing->parent === (int) $parent ) {
				$term_id = (int) $existing->term_id;
			} elseif ( $create ) {
				$res = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
				if ( ! is_wp_error( $res ) ) {
					$term_id = (int) $res['term_id'];
				} else {
					// possibly exists with different parent, try reusing first match
					if ( $existing ) $term_id = (int) $existing->term_id;
				}
			} elseif ( $existing ) {
				$term_id = (int) $existing->term_id;
			}
			$parent = $term_id;
		}
		return $term_id;
	}

	/**
	 * Sideload a remote image URL into WP media library and return attachment ID.
	 * Returns 0 on failure.
	 */
	public static function sideload_image( $url ) {
		if ( empty( $url ) ) return 0;
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) return 0;
		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'image.jpg' ),
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return 0;
		}
		return (int) $id;
	}

	/**
	 * Import a single CSV row into a WC_Product (create or update).
	 *
	 * @return array  { status: created|updated|skipped|failed, id: int, msg: string }
	 */
	public static function import_row( array $row, array $opts ) {
		$update_existing  = ! empty( $opts['update_existing'] );
		$create_categories = ! empty( $opts['create_categories'] );

		$cols = self::columns();
		$data = array();
		foreach ( $cols as $i => $col ) {
			$data[ $col ] = isset( $row[ $i ] ) ? $row[ $i ] : '';
		}

		$sku  = trim( (string) $data['SKU'] );
		$name = trim( (string) $data['Name'] );
		if ( $sku === '' && $name === '' ) {
			return array( 'status' => 'skipped', 'msg' => 'سطر فارغ.' );
		}

		$id = (int) $data['ID'];
		$product = null;
		if ( $id ) {
			$product = wc_get_product( $id );
		}
		if ( ! $product && $sku ) {
			$existing_id = wc_get_product_id_by_sku( $sku );
			if ( $existing_id ) $product = wc_get_product( $existing_id );
		}

		$is_update = (bool) $product;
		if ( $is_update && ! $update_existing ) {
			return array( 'status' => 'skipped', 'id' => $product->get_id(), 'msg' => 'موجود ولم يُسمح بالتحديث.' );
		}

		if ( ! $product ) {
			$type = strtolower( trim( (string) ( $data['Type'] ?: 'simple' ) ) );
			if ( $type === 'variable' ) {
				$product = new WC_Product_Variable();
			} elseif ( $type === 'grouped' ) {
				$product = new WC_Product_Grouped();
			} elseif ( $type === 'external' ) {
				$product = new WC_Product_External();
			} else {
				$product = new WC_Product_Simple();
			}
		}

		if ( $name )            $product->set_name( $name );
		if ( $sku )             $product->set_sku( $sku );
		$product->set_status( ( (string) $data['Published'] === '0' ) ? 'draft' : 'publish' );
		$product->set_featured( (string) $data['Is featured?'] === '1' );
		if ( $data['Visibility in catalog'] !== '' ) $product->set_catalog_visibility( $data['Visibility in catalog'] );
		if ( $data['Short description'] !== '' )    $product->set_short_description( $data['Short description'] );
		if ( $data['Description'] !== '' )          $product->set_description( $data['Description'] );
		if ( $data['Tax status'] !== '' )           $product->set_tax_status( $data['Tax status'] );
		if ( $data['Tax class'] !== '' )            $product->set_tax_class( $data['Tax class'] );

		if ( $data['Regular price'] !== '' ) $product->set_regular_price( (string) $data['Regular price'] );
		if ( $data['Sale price'] !== '' )    $product->set_sale_price( (string) $data['Sale price'] );

		if ( $data['Stock'] !== '' ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $data['Stock'] );
		}
		if ( $data['In stock?'] !== '' ) {
			$product->set_stock_status( ( (string) $data['In stock?'] === '1' ) ? 'instock' : 'outofstock' );
		}
		if ( $data['Low stock amount'] !== '' ) $product->set_low_stock_amount( (int) $data['Low stock amount'] );
		if ( $data['Sold individually?'] !== '' ) $product->set_sold_individually( (string) $data['Sold individually?'] === '1' );

		if ( $data['Weight (kg)'] !== '' )  $product->set_weight( $data['Weight (kg)'] );
		if ( $data['Length (cm)'] !== '' )  $product->set_length( $data['Length (cm)'] );
		if ( $data['Width (cm)'] !== '' )   $product->set_width( $data['Width (cm)'] );
		if ( $data['Height (cm)'] !== '' )  $product->set_height( $data['Height (cm)'] );
		if ( $data['Allow customer reviews?'] !== '' ) $product->set_reviews_allowed( (string) $data['Allow customer reviews?'] === '1' );
		if ( $data['Purchase note'] !== '' ) $product->set_purchase_note( $data['Purchase note'] );

		// Categories
		if ( $data['Categories'] !== '' ) {
			$cat_ids = array();
			foreach ( array_map( 'trim', explode( ',', $data['Categories'] ) ) as $path ) {
				if ( $path === '' ) continue;
				$tid = self::resolve_term_path( $path, 'product_cat', $create_categories );
				if ( $tid ) $cat_ids[] = $tid;
			}
			if ( $cat_ids ) $product->set_category_ids( $cat_ids );
		}

		// Tags
		if ( $data['Tags'] !== '' ) {
			$tag_ids = array();
			foreach ( array_map( 'trim', explode( ',', $data['Tags'] ) ) as $tname ) {
				if ( $tname === '' ) continue;
				$existing = get_term_by( 'name', $tname, 'product_tag' );
				if ( $existing ) {
					$tag_ids[] = (int) $existing->term_id;
				} elseif ( $create_categories ) {
					$res = wp_insert_term( $tname, 'product_tag' );
					if ( ! is_wp_error( $res ) ) $tag_ids[] = (int) $res['term_id'];
				}
			}
			if ( $tag_ids ) $product->set_tag_ids( $tag_ids );
		}

		// Images
		if ( $data['Images'] !== '' ) {
			$urls = array_filter( array_map( 'trim', explode( ',', $data['Images'] ) ) );
			$ids = array();
			foreach ( $urls as $url ) {
				$aid = self::sideload_image( $url );
				if ( $aid ) $ids[] = $aid;
			}
			if ( ! empty( $ids ) ) {
				$product->set_image_id( array_shift( $ids ) );
				if ( ! empty( $ids ) ) $product->set_gallery_image_ids( $ids );
			}
		}

		// Attributes (1..3)
		$attributes = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$name_col   = "Attribute {$i} name";
			$values_col = "Attribute {$i} value(s)";
			$visible_col = "Attribute {$i} visible";
			if ( empty( $data[ $name_col ] ) || empty( $data[ $values_col ] ) ) continue;

			$attr = new WC_Product_Attribute();
			$attr->set_name( $data[ $name_col ] );
			$attr->set_options( array_map( 'trim', explode( ',', $data[ $values_col ] ) ) );
			$attr->set_visible( (string) $data[ $visible_col ] === '1' );
			$attr->set_variation( false );
			$attr->set_position( $i - 1 );
			$attributes[] = $attr;
		}
		if ( $attributes ) $product->set_attributes( $attributes );

		try {
			$saved_id = $product->save();
		} catch ( Exception $e ) {
			return array( 'status' => 'failed', 'msg' => $e->getMessage() );
		}

		return array(
			'status' => $is_update ? 'updated' : 'created',
			'id'     => (int) $saved_id,
			'msg'    => '',
		);
	}

	/**
	 * Run an entire CSV file synchronously and return the summary.
	 *
	 * @return array  { total, success, skipped, failed, errors[] }
	 */
	public static function process_file( $file_path, array $opts ) {
		$summary = array(
			'total'   => 0,
			'success' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			$summary['errors'][] = array( 'row' => 0, 'msg' => 'الملف غير موجود.' );
			return $summary;
		}

		$fh = fopen( $file_path, 'r' );
		if ( ! $fh ) {
			$summary['errors'][] = array( 'row' => 0, 'msg' => 'تعذّر فتح الملف.' );
			return $summary;
		}

		// Strip UTF-8 BOM if present.
		$first_chars = fread( $fh, 3 );
		if ( $first_chars !== "\xEF\xBB\xBF" ) {
			rewind( $fh );
		}

		$header = fgetcsv( $fh );
		if ( ! $header ) {
			fclose( $fh );
			$summary['errors'][] = array( 'row' => 0, 'msg' => 'الملف لا يحتوي على رأس أعمدة.' );
			return $summary;
		}

		$row_index = 1;
		while ( ( $row = fgetcsv( $fh ) ) !== false ) {
			$row_index++;
			if ( count( array_filter( $row, 'strlen' ) ) === 0 ) continue;
			$summary['total']++;
			$result = self::import_row( $row, $opts );
			if ( in_array( $result['status'], array( 'created', 'updated' ), true ) ) {
				$summary['success']++;
			} elseif ( $result['status'] === 'skipped' ) {
				$summary['skipped']++;
			} else {
				$summary['failed']++;
				$summary['errors'][] = array( 'row' => $row_index, 'msg' => $result['msg'] ?? 'unknown' );
			}
		}
		fclose( $fh );
		return $summary;
	}
}

/**
 * Background processor for large CSV imports.
 * Hooked via wp_schedule_single_event in the AJAX handler.
 */
add_action( 'yamamah_process_csv_import', function( $file_path, $opts ) {
	if ( ! file_exists( $file_path ) ) return;
	$summary = Yamamah_CSV::process_file( $file_path, (array) $opts );
	$summary['date'] = current_time( 'mysql' );
	update_option( 'yamamah_last_csv_import', $summary, false );
	@unlink( $file_path );
}, 10, 2 );
