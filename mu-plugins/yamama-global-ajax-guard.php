<?php
/**
 * Plugin Name: Yamama — Global AJAX JSON Guard
 * Description: يُشغّل output buffer عند كل طلبات admin-ajax ليضمن
 *              أن المخرجات إذا كانت JSON فتكون صالحة ولا تسبقها بايتات زائدة.
 * Version: 1.1.1
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
			return;
		}

		if ( ! ob_start() ) {
			return;
		}

		register_shutdown_function(
			static function () {
				$levels = ob_get_level();
				$raw    = '';
				for ( $i = 0; $i < $levels; $i++ ) {
					$raw .= (string) ob_get_clean();
				}

				if ( $raw === '' ) {
					return;
				}

				$trim = trim( $raw );
				if ( $trim === '' ) {
					return;
				}

				// Find the first { or [
				$start_obj = strpos( $trim, '{"' );
				if ( $start_obj === false ) {
					$start_obj = strpos( $trim, '{' );
				}
				$start_arr = strpos( $trim, '[{' );
				if ( $start_arr === false ) {
					$start_arr = strpos( $trim, '[' );
				}

				$start = false;
				if ( $start_obj !== false && ( $start_arr === false || $start_obj < $start_arr ) ) {
					$start = $start_obj;
				} elseif ( $start_arr !== false ) {
					$start = $start_arr;
				}

				if ( $start !== false ) {
					$candidate = substr( $trim, $start );
					
					// Also, WordPress JSON typically ends with } or ].
					// Let's find the LAST } or ]
					$end_obj = strrpos( $candidate, '}' );
					$end_arr = strrpos( $candidate, ']' );
					
					$end = false;
					if ( $end_obj !== false && ( $end_arr === false || $end_obj > $end_arr ) ) {
						$end = $end_obj;
					} elseif ( $end_arr !== false ) {
						$end = $end_arr;
					}

					if ( $end !== false ) {
						$candidate = substr( $candidate, 0, $end + 1 );
						if ( json_decode( $candidate ) !== null ) {
							echo $candidate;
							return;
						}
					}
				}

				// If it's pure json already
				if ( json_decode( $trim ) !== null ) {
					echo $trim;
					return;
				}

				// Fallback
				echo $raw;
			}
		);
	},
	-9999
);
