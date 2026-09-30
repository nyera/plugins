<?php
/**
 * Plugin Name: Yamama — Root WP Mode Toggle
 * Description: يفعّل للرووت (ID=1) نفس آلية التبديل بين لوحة اليمامة ولوحة ووردبريس (meta + cookie) المستخدمة لمستخدم الدعم الخفي.
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		$root_id = 1;
		$user    = get_user_by( 'id', $root_id );
		if ( ! $user ) {
			return;
		}

		// Yamamah dashboard allows switching only for users marked as hidden support.
		if ( ! get_user_meta( $root_id, '_yamama_hidden_user', true ) ) {
			update_user_meta( $root_id, '_yamama_hidden_user', '1' );
		}
	},
	1
);

