<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MWAI_Ajax_Handler {

    public function __construct() {
        add_action( 'wp_ajax_mwai_get_response', array( $this, 'get_response' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_response', array( $this, 'get_response' ) );

        // Shop page enhancements
        add_action( 'wp_ajax_mwai_filter_products', array( $this, 'filter_products' ) );
        add_action( 'wp_ajax_nopriv_mwai_filter_products', array( $this, 'filter_products' ) );
        add_action( 'wp_ajax_mwai_get_categories', array( $this, 'get_categories' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_categories', array( $this, 'get_categories' ) );

        add_action( 'wp_ajax_mwai_get_category_id_by_slug', array( $this, 'get_category_id_by_slug' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_category_id_by_slug', array( $this, 'get_category_id_by_slug' ) );

        add_action( 'wp_ajax_mwai_get_category_path_by_slugs', array( $this, 'get_category_path_by_slugs' ) );
        add_action( 'wp_ajax_nopriv_mwai_get_category_path_by_slugs', array( $this, 'get_category_path_by_slugs' ) );
    }

    /**
     * Handles the AJAX request for AI chat responses.
     */
    public function get_response() {
        if ( ! get_option( 'mwai_feature_chat_widget_enabled', true ) ) {
            wp_send_json_success( array(
                'message'  => 'The chat widget is currently disabled by the administrator.',
                'products' => array(),
            ) );
        }

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

                
                $system_instruction = 'You are MarketWhale AI, a friendly, engaging, and globally product-aware AI with access to a vast products database.

                Your core responsibilities:
                - Provide helpful, concise, and customer-centric answers.
                - Maintain a natural, friendly, and conversational tone.
                - Format responses using HTML (<b>, <ul><li>, <br>) for readability.
                - Always conclude with a follow-up question to encourage continued interaction.

                Product Interaction Guidelines:
                - If the user\'s intent is to browse or search for products, generate `suggested_keywords` (comma-separated, precise terms for WooCommerce search. These keywords should be comprehensive and cover potential matches in product titles, descriptions, short descriptions, SKUs, variable product SKUs, attributes, categories, and tags. Examples: "blue jacket", "men\'s shoes", "SKU: ABC123", "size large", "electronics").
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
                $products = $this->search_woocommerce_products( $search_query, $posts_per_page, $message );
            }
        } // end should_fetch_products

        // Deduplicate products by link / title (only local now)
        $seen = array();
        $final_products = array();
        if ( isset( $products ) && is_array( $products ) ) {
            foreach ( $products as $p ) {
                $uniq = ! empty( $p['link'] ) ? $p['link'] : md5( strtolower( trim( $p['title'] ) ) );
                if ( isset( $seen[ $uniq ] ) ) continue;
                $seen[ $uniq ] = true;
                $final_products[] = $p;
            }
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
     * Custom function to search WooCommerce products comprehensively across multiple fields.
     *
     * @param string $search_query The search query string.
     * @param int $limit Number of products to return.
     * @param string $message Original user message (for catalog handling).
     * @return array Array of formatted product data.
     */
    private function search_woocommerce_products( $search_query, $limit = 4, $message = '' ) {
        global $wpdb;

        $products = array();
        $product_ids = array();

        // If no search query, handle catalog or random products
        if ( empty( $search_query ) ) {
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => $limit,
                'post_status'    => 'publish',
            );
            if ( $message === 'Show catalog' ) {
                $args['meta_key'] = 'total_sales';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
            } else {
                $args['orderby'] = 'rand';
            }
            $wc_query = new WP_Query( $args );
            if ( $wc_query->have_posts() ) {
                while ( $wc_query->have_posts() ) {
                    $wc_query->the_post();
                    $product_ids[] = get_the_ID();
                }
                wp_reset_postdata();
            }
        } else {
            // Prepare search terms for SQL LIKE queries
            $search_terms_raw = explode( ' ', $search_query );
            $search_terms_sql = array_map( function( $term ) use ( $wpdb ) {
                return '%' . $wpdb->esc_like( sanitize_text_field( $term ) ) . '%';
            }, $search_terms_raw );
            $search_terms_for_tax = array_map( 'sanitize_title', $search_terms_raw ); // For taxonomy slugs

            // 1. Search in product title, description, and short description
            $post_search_sql = $wpdb->prepare( "
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product' AND post_status = 'publish'
                AND (
                    post_title LIKE %s
                    OR post_content LIKE %s
                    OR post_excerpt LIKE %s
                )
            ", $search_terms_sql[0], $search_terms_sql[0], $search_terms_sql[0] ); // Using first term for simplicity, can be expanded

            // Add more LIKE clauses for multiple terms
            for ( $i = 1; $i < count( $search_terms_sql ); $i++ ) {
                $post_search_sql .= $wpdb->prepare( "
                    OR post_title LIKE %s
                    OR post_content LIKE %s
                    OR post_excerpt LIKE %s
                ", $search_terms_sql[$i], $search_terms_sql[$i], $search_terms_sql[$i] );
            }
            $product_ids = array_merge( $product_ids, $wpdb->get_col( $post_search_sql ) );

            // 2. Search in SKU (main product and variations)
            $sku_search_sql = $wpdb->prepare( "
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_sku' AND meta_value LIKE %s
            ", $search_terms_sql[0] );
            for ( $i = 1; $i < count( $search_terms_sql ); $i++ ) {
                $sku_search_sql .= $wpdb->prepare( " OR meta_value LIKE %s", $search_terms_sql[$i] );
            }
            $sku_product_ids = $wpdb->get_col( $sku_search_sql );

            // Get parent IDs for variations found by SKU
            if ( ! empty( $sku_product_ids ) ) {
                $parent_ids_sql = "
                    SELECT post_parent FROM {$wpdb->posts}
                    WHERE ID IN (" . implode( ',', array_map( 'absint', $sku_product_ids ) ) . ")
                    AND post_type = 'product_variation'
                ";
                $parent_ids = $wpdb->get_col( $parent_ids_sql );
                $product_ids = array_merge( $product_ids, $sku_product_ids, $parent_ids );
            }

            // 3. Search in categories and tags
            $taxonomy_search_ids = array();
            if ( ! empty( $search_terms_for_tax ) ) {
                $taxonomy_search_sql = "
                    SELECT object_id FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy IN ('product_cat', 'product_tag')
                    AND (";
                $conditions = array();
                foreach ( $search_terms_for_tax as $term ) {
                    $conditions[] = $wpdb->prepare( "t.slug LIKE %s OR t.name LIKE %s", '%' . $term . '%', '%' . $term . '%' );
                }
                $taxonomy_search_sql .= implode( ' OR ', $conditions ) . ")";
                $taxonomy_search_ids = $wpdb->get_col( $taxonomy_search_sql );
            }
            $product_ids = array_merge( $product_ids, $taxonomy_search_ids );

            // 4. Search in product attributes (pa_*)
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            $attribute_search_ids = array();
            if ( ! empty( $attribute_taxonomies ) && ! empty( $search_terms_for_tax ) ) {
                $attribute_taxonomies_names = array_map( function( $attr ) {
                    return wc_attribute_taxonomy_name( $attr->attribute_name );
                }, $attribute_taxonomies );

                $attribute_search_sql = "
                    SELECT object_id FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                    WHERE tt.taxonomy IN ('" . implode( "','", array_map( 'esc_sql', $attribute_taxonomies_names ) ) . "')
                    AND (";
                $conditions = array();
                foreach ( $search_terms_for_tax as $term ) {
                    $conditions[] = $wpdb->prepare( "t.slug LIKE %s OR t.name LIKE %s", '%' . $term . '%', '%' . $term . '%' );
                }
                $attribute_search_sql .= implode( ' OR ', $conditions ) . ")";
                $attribute_search_ids = $wpdb->get_col( $attribute_search_sql );
            }
            $product_ids = array_merge( $product_ids, $attribute_search_ids );

            // Filter unique and valid product IDs
            $product_ids = array_unique( array_filter( array_map( 'absint', $product_ids ) ) );

            // If no products found by direct SQL, fallback to WP_Query 's' parameter
            if ( empty( $product_ids ) ) {
                $args = array(
                    'post_type'      => 'product',
                    'posts_per_page' => $limit,
                    'post_status'    => 'publish',
                    's'              => $search_query,
                );
                $wc_query = new WP_Query( $args );
                if ( $wc_query->have_posts() ) {
                    while ( $wc_query->have_posts() ) {
                        $wc_query->the_post();
                        $product_ids[] = get_the_ID();
                    }
                    wp_reset_postdata();
                }
            }
        }

        // Fetch product data for the found IDs
        if ( ! empty( $product_ids ) ) {
            // Ensure we only get published products and limit the results
            $product_ids = array_slice( $product_ids, 0, $limit );
            foreach ( $product_ids as $pid ) {
                $product = wc_get_product( $pid );
                if ( $product && $product->is_visible() ) { // Check visibility
                    $products[] = $this->format_product_data( $product );
                }
            }
        }

        return $products;
    }

    /**
     * AJAX callback to get dynamic placeholder texts (e.g., popular product names).
     */
    public function get_dynamic_placeholders() {
        // No nonce check needed for public data fetching, but can be added if sensitive.
        // check_ajax_referer( 'mwai_shop_nonce', 'nonce' ); // If you want to add a nonce

        $placeholders = array();

        // Fetch popular products (e.g., by total sales)
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10, // Fetch up to 10 popular products
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        );

        $products_query = new WP_Query( $args );

        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $product = wc_get_product( get_the_ID() );
                if ( $product && $product->is_visible() ) {
                    $placeholders[] = 'Find me ' . esc_html( $product->get_name() );
                }
            }
            wp_reset_postdata();
        }

        // Add some generic popular search terms as fallback or additional options
        $generic_placeholders = array(
            "What’s the best deal today?",
            "Find me sneakers under $50",
            "Compare iPhone 15 vs Samsung S24",
            "Show trending fashion this week",
            "Which laptop is best for students?",
            "Search top-rated headphones",
            "What’s on discount right now?",
            "Suggest gifts for under ₹2000",
            "Find eco-friendly products",
            "Show me today’s top offers"
        );

        // Combine and ensure uniqueness, prioritize dynamic ones
        $final_placeholders = array_unique( array_merge( $placeholders, $generic_placeholders ) );
        // Limit to a reasonable number
        $final_placeholders = array_slice( $final_placeholders, 0, 10 );


        if ( ! empty( $final_placeholders ) ) {
            wp_send_json_success( array( 'placeholders' => $final_placeholders ) );
        } else {
            wp_send_json_error( array( 'message' => 'No dynamic placeholders found.' ) );
        }
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

    /**
     * AJAX callback to filter products by category.
     */
    public function filter_products() {
        if ( ! get_option( 'mwai_feature_shop_enhancements_enabled', true ) ) {
            wp_send_json_error( array( 'message' => 'Shop enhancements are currently disabled by the administrator.' ) );
        }
        check_ajax_referer( 'mwai_shop_nonce', 'nonce' );

        $category_id = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        $search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( $_POST['search_query'] ) : '';
        $posts_per_page = isset( $_POST['posts_per_page'] ) ? intval( $_POST['posts_per_page'] ) : 12;
        $paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
        $enable_slideshow = isset( $_POST['slideshow_enabled'] ) ? filter_var( $_POST['slideshow_enabled'], FILTER_VALIDATE_BOOLEAN ) : true;

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'orderby'        => 'menu_order title', // Default sorting
            'order'          => 'ASC',
        );

        if ( $category_id > 0 ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_id,
                    'operator' => 'IN',
                ),
            );
        }

        if ( ! empty( $search_query ) ) {
            $args['s'] = $search_query;
        }

        $products_query = new WP_Query( $args );
        $products_html = '';

        if ( $products_query->have_posts() ) {
            while ( $products_query->have_posts() ) {
                $products_query->the_post();
                $product = wc_get_product( get_the_ID() );
                if ( $product ) {
                    $products_html .= $this->render_product_card( $product, $enable_slideshow );
                }
            }
            wp_reset_postdata();
        } else {
            $products_html = '<p>No products found for this selection.</p>';
        }

        wp_send_json_success( array(
            'products_html' => $products_html,
            'max_pages'     => $products_query->max_num_pages,
            'current_page'  => $paged,
        ) );
    }

    /**
     * AJAX callback to get product categories and subcategories.
     */
    public function get_categories() {
        if ( ! get_option( 'mwai_feature_shop_enhancements_enabled', true ) ) {
            wp_send_json_error( array( 'message' => 'Shop enhancements are currently disabled by the administrator.' ) );
        }
        check_ajax_referer( 'mwai_shop_nonce', 'nonce' );

        $parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;

        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $parent_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $categories = get_terms( $args );
        $formatted_categories = array();

        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
            foreach ( $categories as $category ) {
                $formatted_categories[] = array(
                    'id'           => $category->term_id,
                    'name'         => $category->name,
                    'slug'         => $category->slug,
                    'count'        => count( wc_get_term_product_ids( $category->term_id, 'product_cat' ) ),
                    'has_children' => (bool) get_terms( array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'parent'     => $category->term_id,
                        'fields'     => 'ids',
                    ) ),
                );
            }
        }

        wp_send_json_success( array( 'categories' => $formatted_categories ) );
    }

    /**
     * Helper function to render a single product card HTML.
     */
    private function render_product_card( $product, $enable_slideshow = true ) {
        $product_id = $product->get_id();
        $title = $product->get_name();
        $price = $product->get_price_html();
        $link = get_permalink( $product_id );

        ob_start();
        ?>
        <div class="mwai-shop-product-card" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <a href="<?php echo esc_url( $link ); ?>">
                <div class="mwai-product-content">
                    <?php if ( $enable_slideshow ) :
                        $gallery_image_ids = $product->get_gallery_image_ids();
                        $all_image_urls = [];

                        // Add featured image first
                        $featured_image_id = $product->get_image_id();
                        if ( $featured_image_id ) {
                            $all_image_urls[] = wp_get_attachment_image_url( $featured_image_id, 'woocommerce_thumbnail' );
                        } else {
                            $all_image_urls[] = wc_placeholder_img_src();
                        }

                        // Add gallery images
                        foreach ( $gallery_image_ids as $image_id ) {
                            $all_image_urls[] = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
                        }

                        // Filter out any false/empty URLs and ensure at least one image
                        $all_image_urls = array_filter( $all_image_urls );
                        if ( empty( $all_image_urls ) ) {
                            $all_image_urls[] = wc_placeholder_img_src();
                        }
                        ?>
                        <div class="mwai-product-image-slideshow" data-images="<?php echo esc_attr( json_encode( $all_image_urls ) ); ?>">
                            <?php foreach ( $all_image_urls as $index => $image_url ) : ?>
                                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="mwai-slideshow-image <?php echo $index === 0 ? 'active' : ''; ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php else :
                        $image_url = get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src();
                        ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                    <?php endif; ?>
                    <div class="mwai-product-meta">
                        <h3><?php echo esc_html( $title ); ?></h3>
                        <p class="price"><?php echo wp_kses_post( $price ); ?></p>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX callback to get a category ID by its slug.
     */
    public function get_category_id_by_slug() {
        if ( ! get_option( 'mwai_feature_shop_enhancements_enabled', true ) ) {
            wp_send_json_error( array( 'message' => 'Shop enhancements are currently disabled by the administrator.' ) );
        }
        check_ajax_referer( 'mwai_shop_nonce', 'nonce' );

        $slug = isset( $_POST['slug'] ) ? sanitize_title( $_POST['slug'] ) : '';

        if ( empty( $slug ) ) {
            wp_send_json_error( array( 'message' => 'Category slug is missing.' ) );
        }

        $term = get_term_by( 'slug', $slug, 'product_cat' );

        if ( $term && ! is_wp_error( $term ) ) {
            wp_send_json_success( array( 'category_id' => $term->term_id ) );
        } else {
            wp_send_json_error( array( 'message' => 'Category not found for slug: ' . $slug ) );
        }
    }

    /**
     * AJAX callback to get a hierarchical path of category IDs from an array of slugs.
     */
    public function get_category_path_by_slugs() {
        if ( ! get_option( 'mwai_feature_shop_enhancements_enabled', true ) ) {
            wp_send_json_error( array( 'message' => 'Shop enhancements are currently disabled by the administrator.' ) );
        }
        check_ajax_referer( 'mwai_shop_nonce', 'nonce' );

        $slugs = isset( $_POST['slugs'] ) ? (array) wp_unslash( $_POST['slugs'] ) : array();
        $slugs = array_map( 'sanitize_title', $slugs );

        if ( empty( $slugs ) ) {
            wp_send_json_error( array( 'message' => 'Category slugs are missing.' ) );
        }

        $category_path_ids = array();
        $parent_id = 0; // Start from top-level categories

        foreach ( $slugs as $slug ) {
            $term = get_term_by( 'slug', $slug, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) && $term->parent == $parent_id ) {
                $category_path_ids[] = $term->term_id;
                $parent_id = $term->term_id; // Set current term as parent for the next iteration
            } else {
                // If a slug in the path is not found or not a child of the previous, stop.
                wp_send_json_error( array( 'message' => 'Invalid category path or slug not found: ' . $slug ) );
            }
        }

        wp_send_json_success( array( 'category_path_ids' => $category_path_ids ) );
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
