<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * NOTE: Replace the GEMINI_API_KEY value with your real key.
 * Keep keys secure. Consider moving to WP admin settings or environment variables.
 */
if ( ! defined( 'GEMINI_API_KEY' ) ) {
    define( 'GEMINI_API_KEY', 'AIzaSyA2vmScQRlnniWTaWLNwkpr-9PdhPyKsTk' );
}

class MWAI_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_mwai_get_response', array( $this, 'get_response' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_response', array( $this, 'get_response' ) );
    }

    /**
     * Handle AJAX request: send user message to Gemini and return response + products.
     */
    public function get_response() {
        // Accept POST input
        $message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $message ) ) {
            wp_send_json_success( array(
                'message'  => 'Please type your question and press send.',
                'products' => array(),
            ) );
        }

        // Build Gemini request using 'contents' format
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . rawurlencode( GEMINI_API_KEY );

        $body = wp_json_encode( array(
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
                } else if ( ! empty( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
                    // Join parts if multiple
                    $parts = wp_list_pluck( $data['candidates'][0]['content']['parts'], 'text' );
                    $ai_text = wp_kses_post( implode( "\n\n", $parts ) );
                }
            } else {
                // Attempt to provide any helpful error from API
                $decoded = json_decode( $raw, true );
                if ( ! empty( $decoded['error']['message'] ) ) {
                    $ai_text = 'API Error: ' . sanitize_text_field( $decoded['error']['message'] );
                }
            }
        }

        // Get WooCommerce product suggestions (safe check)
        $products = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $query = new WP_Query( array(
                'post_type'      => 'product',
                'posts_per_page' => 4,
                'orderby'        => 'rand',
            ) );

            if ( $query->have_posts() ) {
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $id      = get_the_ID();
                    $product = wc_get_product( $id );
                    $img     = get_the_post_thumbnail_url( $id, 'medium' ) ? get_the_post_thumbnail_url( $id, 'medium' ) : wc_placeholder_img_src();
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
?>