<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MWAI_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_mwai_get_response', array( $this, 'get_response' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_response', array( $this, 'get_response' ) );
    }

    public function get_response() {
        $history = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '[]';
        $history = json_decode( $history, true );

        // Ensure history is an array of valid contents
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        // Get the latest user message for validation/product search fallback
        $message = '';
        if ( ! empty( $history ) ) {
            $last_entry = end( $history );
            if ( $last_entry['role'] === 'user' && ! empty( $last_entry['parts'][0]['text'] ) ) {
                $message = sanitize_text_field( $last_entry['parts'][0]['text'] );
            }
        }

        if ( empty( $message ) ) {
            wp_send_json_success( array(
                'message'  => 'Please type your question and press send.',
                'products' => array(),
            ) );
        }

        // Special handling for buttons to fetch products even if API fails
        $ai_text = '';
        $suggested_keywords = array();
        $skip_api = false;

        if ( $message === 'Show catalog' ) {
            $ai_text = 'Here\'s a selection from our catalog:';
            $skip_api = true;
            // Will fetch random products below
        } elseif ( $message === 'List categories' ) {
            $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
            if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
                $cat_list = '<ul>';
                foreach ( $categories as $cat ) {
                    $cat_list .= '<li>' . esc_html( $cat->name ) . '</li>';
                }
                $cat_list .= '</ul>';
                $ai_text = 'Our product categories:<br>' . $cat_list . '<br>What category are you interested in?';
            } else {
                $ai_text = 'No categories found.';
            }
            $skip_api = true;
            // No products for categories list
        } elseif ( $message === 'Search for a product' ) {
            $ai_text = 'What product are you looking for? Please type your search term.';
            $skip_api = true;
            // No products yet
        }

        if ( ! $skip_api ) {
            $api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : '';
            if ( empty( $api_key ) ) {
                $ai_text = 'API key not configured.';
            } else {
                $model_option = defined( 'GEMINI_MODEL' ) ? GEMINI_MODEL : 'gemini-1.5-flash-latest';
                // Ensure only supported models are used
                $supported_models = array('gemini-1.5-flash-latest');
                $model = in_array($model_option, $supported_models) ? $model_option : 'gemini-1.5-flash-latest';

                $temperature = defined( 'GEMINI_TEMPERATURE' ) ? floatval(GEMINI_TEMPERATURE) : 0.7;
                $max_tokens  = defined( 'GEMINI_MAX_TOKENS' ) ? intval(GEMINI_MAX_TOKENS) : 1024;
                $top_p       = defined( 'GEMINI_TOP_P' ) ? floatval(GEMINI_TOP_P) : 0.9;

                $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $api_key );

                $system_instruction = 'You are MarketWhale AI, a friendly and engaging shopping assistant for an online store. Provide helpful, concise responses. Use HTML tags for formatting like <b>bold</b>, <ul><li>lists</li></ul>, <br> for breaks to make it modern and readable. Be conversational, end responses with a follow-up question to encourage interaction. Suggest relevant products when appropriate. Always output in strict JSON format only: {"text": "your formatted response here", "suggested_keywords": ["keyword1", "keyword2"]} where suggested_keywords are search terms for product recommendations (empty array if none). Do not add any extra text outside the JSON.';

                $body = wp_json_encode( array(
                    'systemInstruction' => array(
                        'parts' => array(
                            array( 'text' => $system_instruction ),
                        ),
                    ),
                    'generationConfig' => array(
                        'temperature' => $temperature,
                        'maxOutputTokens' => $max_tokens,
                        'topP' => $top_p,
                        'responseMimeType' => 'application/json',
                    ),
                    'contents' => $history,
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
                            $json_text = $data['candidates'][0]['content']['parts'][0]['text'];
                            $parsed = json_decode( $json_text, true );
                            if ( $parsed && isset( $parsed['text'] ) ) {
                                $ai_text = wp_kses_post( $parsed['text'] );
                                $suggested_keywords = isset( $parsed['suggested_keywords'] ) ? (array) $parsed['suggested_keywords'] : array();
                            }
                        }
                    } else {
                        $decoded = json_decode( $raw, true );
                        if ( ! empty( $decoded['error']['message'] ) ) {
                            $ai_text = 'API Error: ' . sanitize_text_field( $decoded['error']['message'] );
                        }
                    }
                }
            }
        }

        // WooCommerce product suggestions based on suggested_keywords or fallback to message
        $products = array();
        if ( class_exists( 'WooCommerce' ) ) {
            $search_query = ! empty( $suggested_keywords ) ? implode( ' ', $suggested_keywords ) : $message;
            $query_args = array(
                'post_type' => 'product',
                'posts_per_page' => 4,
                'orderby' => 'relevance',
            );
            if ( ! empty( $search_query ) && $message !== 'List categories' && $message !== 'Search for a product' ) {
                $query_args['s'] = $search_query;
            } else {
                $query_args['orderby'] = 'rand';
            }
            $query = new WP_Query( $query_args );

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
            } else {
                // Fallback to random if no matches
                $query_args['s'] = '';
                $query_args['orderby'] = 'rand';
                $query = new WP_Query( $query_args );
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
        }

        wp_send_json_success( array(
            'message'  => $ai_text,
            'products' => $products,
        ) );
    }
}

// Initialize
new MWAI_Ajax_Handler();