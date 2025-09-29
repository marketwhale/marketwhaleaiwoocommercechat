<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MWAI_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_mwai_get_response', array( $this, 'get_response' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_response', array( $this, 'get_response' ) );
    }

    public function get_response() {
        $message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $message ) ) {
            wp_send_json_success( array(
                'message'  => 'Please type your question and press send.',
                'products' => array(),
            ) );
        }

        $api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : '';
        $model_option = defined( 'GEMINI_MODEL' ) ? GEMINI_MODEL : 'gemini-2.5-flash';
        // Ensure only supported models are used
        $supported_models = array('gemini-2.5-flash'); // As per API error, only this is confirmed to work for generateContent v1beta
        $model = in_array($model_option, $supported_models) ? $model_option : 'gemini-2.5-flash';

        $temperature = defined( 'GEMINI_TEMPERATURE' ) ? floatval(GEMINI_TEMPERATURE) : 0.7;
        $max_tokens  = defined( 'GEMINI_MAX_TOKENS' ) ? intval(GEMINI_MAX_TOKENS) : 1024;
        $top_p       = defined( 'GEMINI_TOP_P' ) ? floatval(GEMINI_TOP_P) : 0.9;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $api_key );

        $body = wp_json_encode( array(
            'generationConfig' => array(
                'temperature' => $temperature,
                'maxOutputTokens' => $max_tokens,
                'topP' => $top_p,
            ),
            'contents' => array(
                array(
                    'role'  => 'user',
                    'parts' => array(
                        array( 'text' => $message ),
                    ),
                ),
            ),
        ) );

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => $body,
            'timeout' => 30,
        ) );

        $ai_text = 'Sorry — I could not fetch an answer right now. Please try again later.';

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            $raw  = wp_remote_retrieve_body( $response );

            if ( $code >= 200 && $code < 300 ) {
                $data = json_decode( $raw, true );
                if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    $ai_text = wp_kses_post( $data['candidates'][0]['content']['parts'][0]['text'] );
                } else if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
                    $parts = wp_list_pluck( $data['candidates'][0]['content']['parts'], 'text' );
                    $ai_text = wp_kses_post( implode( "\n\n", $parts ) );
                }
            } else {
                $decoded = json_decode( $raw, true );
                if ( ! empty( $decoded['error']['message'] ) ) {
                    $ai_text = 'API Error: ' . sanitize_text_field( $decoded['error']['message'] );
                }
            }
        }

        // WooCommerce product suggestions
        $products = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $query = new WP_Query( array(
                'post_type' => 'product',
                'posts_per_page' => 4,
                'orderby' => 'rand',
            ) );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $id      = get_the_ID();
                    $product = wc_get_product( $id );
                    $img     = get_the_post_thumbnail_url( $id, 'medium' ) ?: wc_placeholder_img_src();
                    $products[] = array(
                        'title' => get_the_title(),
                        'price' => $product ? $product->get_price_html() : '',
                        'image' => $img,
                        'link'  => get_permalink( $id ),
                    );
                }
                wp_reset_postdata();
            }
        }

        wp_send_json_success( array(
            'message'  => $ai_text,
            'products' => $products,
        ) );
    }
}

// Initialize
new MWAI_Ajax_Handler();
