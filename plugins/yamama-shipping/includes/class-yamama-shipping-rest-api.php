<?php

if (!defined('ABSPATH')) {
    exit;
}

class Yamama_Shipping_REST_API
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route('yamama-shipping/v1', '/orders/(?P<order_id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_status_webhook'],
            'permission_callback' => [self::class, 'verify_signature'],
        ]);

        register_rest_route('yamama-shipping/v1', '/orders/(?P<order_id>\d+)/label', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_label_webhook'],
            'permission_callback' => [self::class, 'verify_signature'],
        ]);
    }

    public static function verify_signature($request)
    {
        $settings = Yamama_Shipping_Client::get_settings();
        $secret   = (string) $settings['hmac_secret'];

        if ($secret === '') {
            return false;
        }

        $signature = $request->get_header('x-yamama-signature');
        if (!is_string($signature) || $signature === '') {
            return false;
        }

        $body     = $request->get_body();
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }

    public static function handle_status_webhook($request)
    {
        $order_id   = intval($request['order_id']);
        $status_id  = sanitize_text_field((string) $request->get_param('status_id'));
        $status_name = sanitize_text_field((string) $request->get_param('status_name'));
        $lamha_order_id = sanitize_text_field((string) $request->get_param('order_id'));

        if ($order_id <= 0 || $status_id === '') {
            return new WP_REST_Response(['message' => 'Invalid payload.'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response(['message' => 'Order not found.'], 404);
        }

        $wc_status = Yamama_Shipping_Hooks::map_lamha_status($status_id);

        $order->update_meta_data('_yamama_shipment_status', $status_id);
        $order->update_meta_data('_yamama_shipment_status_name', $status_name);
        $order->save();

        $note = $status_name !== ''
            ? sprintf('Yamama: %s (status %s)', $status_name, $status_id)
            : sprintf('Yamama: status updated to %s', $status_id);

        $order->update_status($wc_status, $note);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function handle_label_webhook($request)
    {
        $order_id = intval($request['order_id']);
        $pdf_url  = esc_url_raw((string) $request->get_param('pdf_url'));

        if ($order_id <= 0) {
            return new WP_REST_Response(['message' => 'Invalid payload.'], 400);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_REST_Response(['message' => 'Order not found.'], 404);
        }

        $middleware_base = Yamama_Shipping_Client::get_settings()['middleware_base_url'];
        if ($pdf_url !== '' && strpos($pdf_url, $middleware_base) === false) {
            $order->update_meta_data('_yamama_label_url', $pdf_url);
            $order->save();
            $order->add_order_note('Yamama: shipping label received.');
        }

        $tracking_number = sanitize_text_field((string) $request->get_param('number_tracking'));
        $tracking_link   = esc_url_raw((string) $request->get_param('link_tracking'));

        if ($tracking_number !== '') {
            $order->update_meta_data('_yamama_tracking_number', $tracking_number);
        }
        if ($tracking_link !== '') {
            $order->update_meta_data('_yamama_tracking_link', $tracking_link);
        }

        $order->save();

        return new WP_REST_Response(['ok' => true], 200);
    }
}
