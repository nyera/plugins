<?php
/**
 * Plugin Name: Yamama — Legacy Clinic CPT Silencer
 * Description: يُسجّل نوع المنشور القديم "clinic" بصورة خفية لمنع إشعارات map_meta_cap
 *              الناتجة عن بقايا بيانات ثيم Clinicly-theme القديم. لا يعرض أي قوائم أو واجهات.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( post_type_exists( 'clinic' ) ) {
			return;
		}

		register_post_type(
			'clinic',
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'labels'              => array( 'name' => 'Legacy Clinic' ),
			)
		);
	},
	5
);
