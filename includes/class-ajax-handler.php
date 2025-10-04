<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MWAI_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_mwai_get_response', array( $this, 'get_response' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_response', array( $this, 'get_response' ) );
    }

    public function get_response() {
        // Basic request & nonce optional check (client did not send nonce previously)
        // Get history (array of content parts)
        $history = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '[]';
        $history = json_decode( $history, true );

        if ( ! is_array( $history ) ) {
            $history = array();
        }

        // Get latest user message
        $message = '';
        if ( ! empty( $history ) ) {
            $last_entry = end( $history );
            if ( isset($last_entry['role']) && $last_entry['role'] === 'user' && ! empty( $last_entry['parts'][0]['text'] ) ) {
                $message = sanitize_text_field( $last_entry['parts'][0]['text'] );
            }
        }

        if ( empty( $message ) ) {
            wp_send_json_success( array(
                'message'  => 'Please type your question and press send.',
                'products' => array(),
            ) );
        }

        // Quick actions handling
        $ai_text = '';
        $suggested_keywords = array();
        $suggested_product_count = 4;
        $skip_api = false;

        if ( $message === 'Show catalog' ) {
            $ai_text = 'Here\'s a selection from our catalog:';
            $skip_api = true;
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
        } elseif ( $message === 'Search for a product' ) {
            $ai_text = 'What product are you looking for? Please type your search term.';
            $skip_api = true;
        }

        // If not a quick action, call Gemini to get structured JSON (text + suggested_keywords)
        if ( ! $skip_api ) {
            $api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : '';
            if ( empty( $api_key ) ) {
                $ai_text = 'API key not configured.';
            } else {
                $model_option = defined( 'GEMINI_MODEL' ) ? GEMINI_MODEL : 'gemini-2.5-flash';
                $supported_models = array('gemini-2.5-flash');
                $model = in_array( $model_option, $supported_models ) ? $model_option : 'gemini-2.5-flash';

                $temperature = defined( 'GEMINI_TEMPERATURE' ) ? floatval( GEMINI_TEMPERATURE ) : 0.7;
                $max_tokens  = defined( 'GEMINI_MAX_TOKENS' ) ? intval( GEMINI_MAX_TOKENS ) : 2048;
                $top_p       = defined( 'GEMINI_TOP_P' ) ? floatval( GEMINI_TOP_P ) : 0.9;

                $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $api_key );

                $system_instruction = 'You are MarketWhale AI, 
                a friendly and engaging shopping assistant for an online store. 
                Your primary goal is to answer customer questions and engage them in conversation. 
                Provide helpful, concise, and customer-centric responses. 
                Use HTML tags for formatting like <b>bold</b>, <ul><li>lists</li></ul>, 
                <br> for breaks to make it modern and readable. Be conversational, 
                and always end responses with a follow-up question to encourage further interaction. 
                ONLY provide `suggested_keywords` for product search if the user\'s intent is clearly to browse or 
                search for products. For general questions, provide an empty `suggested_keywords` array. 
                When providing `suggested_keywords`, ensure they are highly precise, 
                comma-separated terms suitable for a WooCommerce product search 
                (e.g., "blue jacket", "men\'s shoes", "summer dress"). Optionally, 
                you may also include a `suggested_product_count` (integer, max 6). 
                Always output in strict JSON format only: 
                    {\"text\": \"your formatted response here\", \"suggested_keywords\": [\"keyword1\", \"keyword2\"], \"suggested_product_count\": 4}. Do not add any extra text outside the JSON.';

                $body = wp_json_encode( array(
                    'systemInstruction' => array( 'parts' => array( array( 'text' => $system_instruction ) ) ),
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
                                $suggested_product_count = isset( $parsed['suggested_product_count'] ) ? intval( $parsed['suggested_product_count'] ) : 4;
                                $suggested_product_count = min( $suggested_product_count, 6 );
                            }
                        } else {
                            // If model returned plain text, use it as message (no search)
                            $ai_text = isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ? wp_kses_post( $data['candidates'][0]['content']['parts'][0]['text'] ) : $ai_text;
                        }
                    } else {
                        $decoded = json_decode( $raw, true );
                        if ( ! empty( $decoded['error']['message'] ) ) {
                            $ai_text = 'API Error: ' . sanitize_text_field( $decoded['error']['message'] );
                        }
                    }
                } else {
                    // keep $ai_text default
                }
            }
        } // end if not skip_api

        // Decide whether to fetch products:
        $products = array();
        $should_fetch_products = false;
        $search_query = '';
        $posts_per_page = isset( $suggested_product_count ) ? $suggested_product_count : 4;

        if ( ! empty( $suggested_keywords ) ) {
            $should_fetch_products = true;
            $search_query = implode( ' ', $suggested_keywords );
        } elseif ( $message === 'Show catalog' ) {
            $should_fetch_products = true;
            $search_query = ''; // will fetch popular/random products from WC & global
        }

        // If we should fetch, fetch both WooCommerce and SerpAPI results (Woo first)
        if ( $should_fetch_products ) {
            // 1) WooCommerce local products
            if ( class_exists( 'WooCommerce' ) ) {
                $wc_args = array(
                    'post_type'      => 'product',
                    'posts_per_page' => $posts_per_page,
                );

                if ( ! empty( $search_query ) ) {
                    $wc_args['s'] = $search_query;
                } elseif ( $message === 'Show catalog' ) {
                    // popular products by total_sales
                    $wc_args['meta_key'] = 'total_sales';
                    $wc_args['orderby']  = 'meta_value_num';
                } else {
                    $wc_args['orderby'] = 'rand';
                }

                $wc_query = new WP_Query( $wc_args );
                if ( $wc_query->have_posts() ) {
                    while ( $wc_query->have_posts() ) {
                        $wc_query->the_post();
                        $pid = get_the_ID();
                        $product = wc_get_product( $pid );
                        if ( $product ) {
                            $img = get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src();
                            $products[] = array(
                                'source' => 'WooCommerce',
                                'store' => 'WooCommerce',
                                'title'  => get_the_title(),
                                'price'  => $product->get_price_html(),
                                'image'  => $img,
                                'link'   => get_permalink( $pid ),
                            );
                        }
                    }
                    wp_reset_postdata();
                }
            }
        } // end should_fetch_products

        // Deduplicate products by link / title (only local now)
        $seen = array();
        $final_products = array();
        foreach ( $products as $p ) {
            $uniq = ! empty( $p['link'] ) ? $p['link'] : md5( strtolower( trim( $p['title'] ) ) );
            if ( isset( $seen[ $uniq ] ) ) continue;
            $seen[ $uniq ] = true;
            $final_products[] = $p;
        }

        // If nothing found and Show catalog was requested, do a fallback to random local products (if WC exists)
        if ( empty( $final_products ) && $message === 'Show catalog' && class_exists( 'WooCommerce' ) ) {
            $fallback = new WP_Query( array( 'post_type' => 'product', 'posts_per_page' => 4, 'orderby' => 'rand' ) );
            if ( $fallback->have_posts() ) {
                while ( $fallback->have_posts() ) {
                    $fallback->the_post();
                    $pid = get_the_ID();
                    $product = wc_get_product( $pid );
                    if ( $product ) {
                        $final_products[] = array(
                            'source' => 'WooCommerce',
                            'title'  => get_the_title(),
                            'price'  => $product->get_price_html(),
                            'image'  => get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src(),
                            'link'   => get_permalink( $pid ),
                        );
                    }
                }
                wp_reset_postdata();
            }
        }

        // Return
        wp_send_json_success( array(
            'message'  => $ai_text,
            'products' => $final_products,
        ) );
    }
}

// Initialize
new MWAI_Ajax_Handler();
