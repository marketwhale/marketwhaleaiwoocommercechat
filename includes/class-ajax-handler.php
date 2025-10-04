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
        } elseif ( strpos( $message, 'Tell me more about "' ) === 0 ) {
            // Extract product title from message
            preg_match('/Tell me more about "([^"]+)"/', $message, $matches);
            if (isset($matches[1])) {
                $product_title = $matches[1];
                $product_id = wc_get_product_id_by_name($product_title); // Helper to get ID by name
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $ai_text = "Fantastic choice! Let's explore <b>" . esc_html($product->get_name()) . "</b> in more detail:<br><br>";
                        $ai_text .= "<ul>";
                        $ai_text .= "<li><b>Price:</b> " . $product->get_price_html() . "</li>";
                        $ai_text .= "<li><b>Description:</b> " . wp_kses_post($product->get_description()) . "</li>";
                        $ai_text .= "</ul>";
                        $ai_text .= "This product is truly special! Is there anything specific you'd like to know, or would you like me to suggest some complementary items?";
                        $products[] = $this->format_product_data($product); // Add product to display
                    } else {
                        $ai_text = "Sorry, I couldn't find details for " . esc_html($product_title) . ". Can I help you find something else?";
                    }
                } else {
                    $ai_text = "Sorry, I couldn't find details for " . esc_html($product_title) . ". Can I help you find something else?";
                }
            } else {
                $ai_text = "I'm not sure which product you're asking about. Could you please specify?";
            }
            $skip_api = true;
        } elseif ( strpos( $message, 'Compare these products: ' ) === 0 ) {
            // Extract product titles from message
            $product_titles_str = str_replace('Compare these products: ', '', $message);
            $product_titles = array_map('trim', explode(',', $product_titles_str));
            
            $compared_products = [];
            foreach ($product_titles as $title) {
                $product_id = wc_get_product_id_by_name($title);
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $compared_products[] = $product;
                    }
                }
            }

            if (count($compared_products) >= 2) {
                $ai_text = "Fantastic! Let's compare these amazing products to help you make an informed decision:<br><br>";
                foreach ($compared_products as $product) {
                    $ai_text .= "<h3>✨ <b>" . esc_html($product->get_name()) . "</b> ✨</h3>";
                    $ai_text .= "<ul>";
                    $ai_text .= "<li><b>Price:</b> " . $product->get_price_html() . "</li>";
                    $ai_text .= "<li><b>Quick Look:</b> " . wp_kses_post($product->get_short_description()) . "</li>";
                    $ai_text .= "</ul><br>";
                    $products[] = $this->format_product_data($product); // Add product to display
                }
                $ai_text .= "I hope this detailed comparison sheds some light on your choices! Which one is catching your eye, or would you like to compare other features?";
            } else {
                $ai_text = "To provide a meaningful comparison, please select at least two products. I'm here to help you find the perfect match!";
            }
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

                
                $system_instruction = 'You are MarketWhale AI, a friendly, engaging, and globally product-aware AI with access to a vast products database..

                Your core responsibilities:
                - Provide helpful, concise, and customer-centric answers.
                - Maintain a natural, friendly, and conversational tone.
                - Format responses using HTML (<b>, <ul><li>, <br>) for readability.
                - Always conclude with a follow-up question to encourage continued interaction.

                Product Interaction Guidelines:
                - If the user\'s intent is to browse or search for products, generate `suggested_keywords` (comma-separated, precise terms for WooCommerce search, e.g., "blue jacket", "men\'s shoes").
                - For general inquiries, `suggested_keywords` should be an empty array.
                - You may optionally include `suggested_product_count` (integer, max 6) for product display.
                - When responding to "Show details" or "Compare" requests, provide engaging, structured information about the selected products, without including direct links in your text response, as the product cards themselves are clickable.

                Output Format (Strict JSON):
                {"text": "your formatted response here", "suggested_keywords": ["keyword1", "keyword2"], "suggested_product_count": 4}
                Ensure no additional text outside this JSON structure.';

                $body = wp_json_encode( array(
                    'systemInstruction' => array(
                        'parts' => array(
                            array( 'text' => $system_instruction )
                        )
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
                            $products[] = $this->format_product_data($product);
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
                        $final_products[] = $this->format_product_data($product);
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

    /**
     * Helper function to format product data for the frontend.
     */
    private function format_product_data($product) {
        $pid = $product->get_id();
        $img = get_the_post_thumbnail_url( $pid, 'medium' ) ?: wc_placeholder_img_src();
        return array(
            'id'     => $pid, // Add product ID
            'source' => 'WooCommerce',
            'store'  => 'WooCommerce',
            'title'  => $product->get_name(),
            'price'  => $product->get_price_html(),
            'image'  => $img,
            'link'   => get_permalink( $pid ),
        );
    }
}

// Helper function to get product ID by name (WooCommerce doesn't have this natively)
if ( ! function_exists( 'wc_get_product_id_by_name' ) ) {
    function wc_get_product_id_by_name( $product_name ) {
        global $wpdb;
        $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'product' AND post_status = 'publish'", $product_name ) );
        return $product_id;
    }
}

// Initialize
new MWAI_Ajax_Handler();
