<?php
/**
 * Taqnyat SMS API wrapper for customer stores.
 * Adapted from https://github.com/taqnyat/php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Yamamah_TaqnyatSms {

    private $auth;
    private $base = 'https://api.taqnyat.sa';

    public function __construct( $bearer_token ) {
        $this->auth = $bearer_token;
    }

    public function send( $body, $recipients, $sender ) {
        if ( empty( $this->auth ) ) {
            return [ 'success' => false, 'error' => 'Missing bearer token' ];
        }

        if ( is_string( $recipients ) ) {
            $recipients = array_map( 'trim', explode( ',', $recipients ) );
        }

        $payload = wp_json_encode( [
            'recipients' => $recipients,
            'sender'     => $sender,
            'body'       => $body,
        ] );

        $response = wp_remote_post( $this->base . '/v1/messages', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->auth,
            ],
            'body'    => $payload,
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'success'  => $code >= 200 && $code < 300,
            'code'     => $code,
            'response' => $data,
        ];
    }

    public function balance() {
        $response = wp_remote_get( $this->base . '/account/balance', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->auth,
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'error' => $response->get_error_message() ];
        }

        return [
            'success'  => true,
            'response' => json_decode( wp_remote_retrieve_body( $response ), true ),
        ];
    }
}
