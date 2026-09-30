<?php
/**
 * Plugin Name: Disable WooCommerce Styles
 * Description: Disable all default WooCommerce styles network-wide.
 *              Themes must provide their own cart/checkout/shop CSS.
 *              Does NOT block theme template overrides (woocommerce/*.php).
 * Author: Yamama
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
