<?php
/**
 * Plugin Name: MarketWhaleAI
 * Description: AI-powered floating chat widget with WooCommerce product suggestions (Gemini API), dynamic shop enhancements, and admin tools for SEO and bulk category management.
 * Version: 1.6
 * Author: MarketWhaleAI
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Assets (CSS/JS)
function mwai_enqueue_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'mwai-style', $plugin_url . 'assets/css/style.css', array(), '1.6' );
    wp_enqueue_script( 'mwai-js', $plugin_url . 'assets/js/chat-widget.js', array( 'jquery' ), '1.6', true );

    wp_localize_script( 'mwai-js', 'MWAI_Ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mwai_ajax_nonce' )
    ) );

    // Enqueue shop page enhancement assets only on shop-related pages
    if ( is_shop() || is_product_category() || is_product_tag() ) {
        wp_enqueue_style( 'mwai-shop-style', $plugin_url . 'assets/css/shop-styles.css', array(), '1.0' );
        wp_enqueue_script( 'mwai-shop-js', $plugin_url . 'assets/js/shop-enhancements.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-shop-js', 'MWAI_Shop_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_shop_nonce' )
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'mwai_enqueue_assets' );

// Add a body class to hide original shop content while enhancements load
function mwai_add_shop_loading_body_class( $classes ) {
    if ( is_shop() || is_product_category() || is_product_tag() ) {
        $classes[] = 'mwai-shop-loading';
    }
    return $classes;
}
add_filter( 'body_class', 'mwai_add_shop_loading_body_class' );

// Enqueue admin scripts only on the settings page
function mwai_admin_enqueue_scripts( $hook_suffix ) {
    if ( 'toplevel_page_mwai-settings' === $hook_suffix ) {
        wp_enqueue_script( 'mwai-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-admin-js', 'MWAI_Admin_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_test_connection_nonce' ),
        ) );
    }
}
add_action( 'admin_enqueue_scripts', 'mwai_admin_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'mwai_admin_product_seo_enqueue_scripts' ); // New action for product SEO
add_action( 'admin_enqueue_scripts', 'mwai_admin_bulk_categories_enqueue_scripts' ); // New action for bulk categories

// Enqueue admin scripts for product SEO on product edit screen
function mwai_admin_product_seo_enqueue_scripts( $hook_suffix ) {
    if ( 'post.php' === $hook_suffix && 'product' === get_post_type() ) {
        wp_enqueue_script( 'mwai-admin-product-seo-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-product-seo.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-admin-product-seo-js', 'MWAI_Product_SEO_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_generate_seo_content_nonce' ),
        ) );
    }
}

// Enqueue admin scripts and styles for bulk category management
function mwai_admin_bulk_categories_enqueue_scripts( $hook_suffix ) {
    if ( 'edit-tags.php' === $hook_suffix && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat' ) {
        wp_enqueue_style( 'mwai-admin-bulk-categories-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-bulk-categories.css', array(), '1.0' );
        wp_enqueue_script( 'mwai-admin-bulk-categories-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-bulk-categories.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-admin-bulk-categories-js', 'MWAI_Bulk_Categories_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_bulk_add_categories_nonce' )
        ) );
    }
}

// Include AJAX handler
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ajax-handler.php';
// Include custom product shortcode handler
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mwai-product-shortcode.php';
// Include bulk category handler
require_once plugin_dir_path( __FILE__ ) . 'includes/class-mwai-bulk-category-handler.php';

// Chat widget HTML
function mwai_chat_widget_html() {
    $plugin_url = plugin_dir_url( __FILE__ );
    ?>
    <div id="mwai-fab" aria-hidden="false" title="Chat with us">
        <img src="<?php echo esc_url( $plugin_url . 'assets/images/chat-icon.png' ); ?>" alt="Chat">
    </div>

    <div id="mwai-chat-window" class="hidden" role="dialog" aria-label="MarketWhale AI chat">
        <div id="mwai-chat-header">
            <span>MarketWhale AI</span>
            <img id="mwai-close-btn" src="<?php echo esc_url( $plugin_url . 'assets/images/close-icon.png' ); ?>" alt="Close chat">
        </div>

        <div id="mwai-chat-body" aria-live="polite"></div>

        <div id="mwai-chat-footer">
            <input type="text" id="mwai-user-input" placeholder="Type your message..." aria-label="Type your message">
            <button id="mwai-send-btn" aria-label="Send message">
                <img src="<?php echo esc_url( $plugin_url . 'assets/images/chat-icon.png' ); ?>" alt="Send">
            </button>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'mwai_chat_widget_html' );


/* ----------------------------------------------
   WP Admin: Gemini API Key + Model Settings
-------------------------------------------------*/

// Admin menu
function mwai_admin_menu() {
    add_menu_page(
        'MarketWhaleAI Settings',
        'MarketWhaleAI',
        'manage_options',
        'mwai-settings',
        'mwai_settings_page',
        'dashicons-format-chat',
        60
    );
}
add_action( 'admin_menu', 'mwai_admin_menu' );

// Register settings
function mwai_register_settings() {
    register_setting( 'mwai_settings_group', 'mwai_gemini_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_gemini_model', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'gemini-2.5-flash',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_gemini_temperature', array(
        'type'              => 'number',
        'sanitize_callback' => 'floatval',
        'default'           => '0.7',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_gemini_max_tokens', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => '2048',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_gemini_top_p', array(
        'type'              => 'number',
        'sanitize_callback' => 'floatval',
        'default'           => '0.9',
    ) );
}
add_action( 'admin_init', 'mwai_register_settings' );

// Add AJAX action for connection test
add_action( 'wp_ajax_mwai_test_connection', 'mwai_test_connection_callback' );
add_action( 'wp_ajax_mwai_generate_seo_content', 'mwai_generate_seo_content_callback' ); // New AJAX action for product SEO
add_action( 'wp_ajax_mwai_bulk_add_categories', array( 'MWAI_Bulk_Category_Handler', 'bulk_add_categories' ) ); // New AJAX action for bulk category creation

// New AJAX actions for shop page enhancements
add_action( 'wp_ajax_mwai_filter_products', array( 'MWAI_Ajax_Handler', 'filter_products' ) );
add_action( 'wp_ajax_nopriv_mwai_filter_products', array( 'MWAI_Ajax_Handler', 'filter_products' ) );
add_action( 'wp_ajax_mwai_get_categories', array( 'MWAI_Ajax_Handler', 'get_categories' ) );
add_action( 'wp_ajax_nopriv_mwai_get_categories', array( 'MWAI_Ajax_Handler', 'get_categories' ) );

// Add meta box to product edit screen
function mwai_add_product_seo_meta_box() {
    add_meta_box(
        'mwai_product_seo_meta_box',
        __( 'MarketWhale AI SEO', 'marketwhale-ai-chat' ),
        'mwai_product_seo_meta_box_callback',
        'product',
        'side', // Position in the right sidebar
        'high'
    );
}
add_action( 'add_meta_boxes', 'mwai_add_product_seo_meta_box' );

// Callback to render the meta box content
function mwai_product_seo_meta_box_callback( $post ) {
    $product_id = $post->ID;
    ?>
    <div id="mwai-seo-content-generator">
        <p>Generate SEO-optimized title, description, short description, and tags for this product using Gemini AI.</p>
        <button id="mwai_generate_seo_content_btn" class="button button-primary" data-product_id="<?php echo esc_attr( $product_id ); ?>">
            Generate SEO Content
        </button>
        <p id="mwai-seo-generation-status" style="margin-top: 10px;"></p>
    </div>
    <?php
}

// Admin settings page
function mwai_settings_page() {
    // Retrieve settings
    $api_key     = esc_attr( get_option( 'mwai_gemini_api_key', '' ) );
    $model       = esc_attr( get_option( 'mwai_gemini_model', 'gemini-2.5-flash' ) );
    $temperature = esc_attr( get_option( 'mwai_gemini_temperature', '0.7' ) );
    $max_tokens  = esc_attr( get_option( 'mwai_gemini_max_tokens', '2048' ) );
    $top_p       = esc_attr( get_option( 'mwai_gemini_top_p', '0.9' ) );
    ?>
    <div class="wrap">
        <h1>MarketWhale AI Chat Settings</h1>
        <?php if ( empty( $api_key ) ) : ?>
            <div class="notice notice-error">
                <p><strong>Important:</strong> Please enter your Gemini API Key below to enable AI functionalities.</p>
            </div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'mwai_settings_group' ); ?>
            <?php do_settings_sections( 'mwai_settings_group' ); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="text" name="mwai_gemini_api_key" value="<?php echo $api_key; ?>" size="50" />
                        <p class="description">Enter your Gemini API Key. Get one from <a href="https://ai.google.dev/" target="_blank">Google AI Studio</a>.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Model</th>
                    <td>
                        <select name="mwai_gemini_model">
                            <option value="gemini-2.5-flash" <?php selected( $model, 'gemini-2.5-flash' ); ?>>Gemini 2.5 Flash (Recommended)</option>
                        </select>
                        <p class="description">Choose the model for responses. 'gemini-1.0-pro' is generally supported for `generateContent` in v1beta.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Temperature</th>
                    <td>
                        <input type="number" step="0.1" min="0" max="1" name="mwai_gemini_temperature" value="<?php echo $temperature; ?>" />
                        <p class="description">Controls randomness of responses (0–1).</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Max Output Tokens</th>
                    <td>
                        <input type="number" min="1" name="mwai_gemini_max_tokens" value="<?php echo $max_tokens; ?>" />
                        <p class="description">Maximum tokens returned by Gemini.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Top P</th>
                    <td>
                        <input type="number" step="0.1" min="0" max="1" name="mwai_gemini_top_p" value="<?php echo $top_p; ?>" />
                        <p class="description">Controls nucleus sampling (0–1).</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <h2>Test Gemini Connection</h2>
        <p>Click the button below to test if your Gemini API Key is working correctly.</p>
        <button id="mwai-test-connection-btn" class="button button-secondary">Test Connection</button>
        <p id="mwai-test-connection-result"></p>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#mwai-test-connection-btn').on('click', function(e){
                e.preventDefault();
                const $button = $(this);
                const $result = $('#mwai-test-connection-result');
                $result.text('Testing connection...');
                $button.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'mwai_test_connection',
                    _wpnonce: '<?php echo wp_create_nonce( 'mwai_test_connection_nonce' ); ?>'
                }, function(response){
                    if (response.success) {
                        $result.css('color', 'green').text('Connection successful! ' + response.data.message);
                    } else {
                        $result.css('color', 'red').text('Connection failed: ' + response.data.message);
                    }
                }).fail(function(){
                    $result.css('color', 'red').text('Network error during connection test.');
                }).always(function(){
                    $button.prop('disabled', false);
                });
            });
        });
    </script>

    <hr style="margin: 40px 0;">

    <h2>MarketWhaleAI: Empowering Your WooCommerce Store with AI</h2>
    <p>MarketWhaleAI is a comprehensive WordPress plugin engineered to integrate intelligent, AI-powered capabilities into your WooCommerce store. It aims to elevate the customer shopping experience and streamline administrative tasks, making your online store more dynamic and efficient.</p>

    <h3>1. AI Chat Widget (Frontend)</h3>
    <p>The AI Chat Widget is a floating conversational assistant that appears on your website's frontend, offering instant support and personalized shopping guidance to your customers.</p>
    <ul>
        <li><strong>Interactive Shopping Assistant:</strong> Provides a direct line of communication for customers to interact with an AI. It's designed to be friendly, engaging, and always ready to help.</li>
        <li><strong>Product Discovery:</strong> The AI can understand product-related queries and suggest relevant items from your store. It leverages advanced AI to interpret customer needs beyond simple keyword searches.</li>
        <li><strong>Detailed Information & Comparison:</strong> When products are suggested, customers can select multiple items within the chat to get more detailed information or a side-by-side comparison.</li>
    </ul>

    <h3>2. Shop Page Enhancements (Frontend)</h3>
    <p>MarketWhaleAI transforms your standard WooCommerce shop, category, and tag archive pages into a modern, dynamic browsing experience. This functionality can also be embedded on any page using a shortcode.</p>
    <ul>
        <li><strong>Dynamic Browsing Experience:</strong> Replaces the default, often static, WooCommerce product listings with a more interactive and visually appealing layout.</li>
        <li><strong>Category Scrollers:</strong> Presents product categories and subcategories in intuitive, horizontally scrollable lists. This allows for quick navigation without reloading the entire page.</li>
        <li><strong>Infinite Product Grid:</strong> Products are displayed in a responsive grid that loads more items automatically as the customer scrolls down, eliminating the need for pagination buttons.</li>
        <li><strong>Embeddable Shop Browser (<code>[mwai_shop_browser]</code> shortcode):</strong> Allows you to place the entire dynamic shop browsing experience (category scrollers + infinite product grid) on any WordPress page, post, or widget area.
            <ul>
                <li><em>How to use:</em> Simply add <code>[mwai_shop_browser]</code> to your content. You can specify the default product limit: <code>[mwai_shop_browser limit="16"]</code>.</li>
            </ul>
        </li>
    </ul>

    <h3>3. Admin Tools (For Store Administrators)</h3>
    <p>MarketWhaleAI provides powerful tools within your WordPress admin dashboard to manage AI settings, optimize product SEO, and streamline category management.</p>
    <ul>
        <li><strong>Gemini API Settings:</strong> This dedicated section allows you to configure the core AI functionality of the plugin.
            <ul>
                <li><em>How to use:</em> Navigate to <code>MarketWhaleAI</code> in your WordPress admin menu. Enter your unique API key obtained from Google AI Studio. Configure AI model parameters like temperature, max output tokens, and top P. Click "Test Connection" to verify your API key.</li>
            </ul>
        </li>
        <li><strong>Product SEO Generation:</strong> Leverages Gemini AI to automatically generate SEO-optimized content for your WooCommerce products.
            <ul>
                <li><em>How to use:</em> When editing any product in your WordPress admin, look for the "MarketWhale AI SEO" meta box in the sidebar. Click "Generate SEO Content" to populate product fields with AI-generated content.</li>
            </ul>
        </li>
        <li><strong>Bulk Category Management:</strong> Simplifies the process of creating and organizing multiple product categories and subcategories at once.
            <ul>
                <li><em>How to use:</em> Navigate to <code>Products > Categories</code> in your WordPress admin. Click the "Bulk Add Categories" button. Enter category names, using hyphens (<code>-</code>) to define hierarchy (e.g., <code>Category1</code>, <code>-SubCategory1</code>, <code>--SubSubCategoryA</code>). Click "Add Categories" to process.</li>
            </ul>
        </li>
        <li><strong>Custom Shortcodes:</strong> Provides flexible shortcodes to display various product and category layouts anywhere on your site.
            <ul>
                <li><strong><code>[mwai_products]</code>:</strong> Displays a grid of products.
                    <ul>
                        <li><em>Example:</em> <code>[mwai_products limit="8" columns="2" category="electronics" orderby="price" order="asc"]</code></li>
                        <li><em>Attributes:</em> <code>limit</code>, <code>columns</code>, <code>category</code> (slugs), <code>orderby</code>, <code>order</code>, <code>ids</code> (product IDs), <code>skus</code> (product SKUs), <code>class</code> (custom CSS class).</li>
                    </ul>
                </li>
                <li><strong><code>[mwai_category_scroller]</code>:</strong> Displays a horizontal scroller of categories.
                    <ul>
                        <li><em>Example:</em> <code>[mwai_category_scroller parent_id="0" class="homepage-categories"]</code></li>
                        <li><em>Attributes:</em> <code>parent_id</code> (0 for top-level), <code>columns</code> (for styling), <code>class</code>.</li>
                    </ul>
                </li>
                <li><strong><code>[mwai_product_scroller]</code>:</strong> Displays a horizontal scroller of products.
                    <ul>
                        <li><em>Example:</em> <code>[mwai_product_scroller title="New Arrivals" limit="8" orderby="date" order="desc"]</code></li>
                        <li><em>Attributes:</em> <code>limit</code>, <code>category</code> (slugs), <code>orderby</code>, <code>order</code>, <code>ids</code> (product IDs), <code>skus</code> (product SKUs), <code>class</code>, <code>title</code>.</li>
                    </ul>
                </li>
            </ul>
        </li>
    </ul>
    <p>This detailed overview should provide customers with a clear understanding of MarketWhaleAI's capabilities and how to best utilize them to enhance their WooCommerce store.</p>
    <?php
}

// Handle connection test AJAX request
function mwai_test_connection_callback() {
    check_ajax_referer( 'mwai_test_connection_nonce', '_wpnonce' );

    $api_key = get_option( 'mwai_gemini_api_key', '' );
    $model   = get_option( 'mwai_gemini_model', 'gemini-1.0-pro' ); // Changed default model

    if ( empty( $api_key ) ) {
        wp_send_json_error( array( 'message' => 'Gemini API Key is not configured.' ) );
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $api_key );

    $body = wp_json_encode( array(
        'contents' => array(
            array(
                'role'  => 'user',
                'parts' => array(
                    array( 'text' => 'Hello, what is your name?' ),
                ),
            ),
        ),
    ) );

    $response = wp_remote_post( $url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => $body,
        'timeout' => 15, // Shorter timeout for test
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'WordPress HTTP Error: ' . $response->get_error_message() ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );
    $data = json_decode( $raw, true );

    if ( $code >= 200 && $code < 300 ) {
        if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            wp_send_json_success( array( 'message' => 'Received a response from Gemini.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Gemini API returned an empty or unexpected response.' ) );
        }
    } else {
        $error_message = 'Unknown API Error.';
        if ( ! empty( $data['error']['message'] ) ) {
            $error_message = sanitize_text_field( $data['error']['message'] );
        }
        wp_send_json_error( array( 'message' => 'Gemini API Error (' . $code . '): ' . $error_message ) );
    }
}

// Handle AJAX request for generating product SEO content
function mwai_generate_seo_content_callback() {
    check_ajax_referer( 'mwai_generate_seo_content_nonce', '_wpnonce' );

    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to generate SEO content.' ) );
    }

    $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
        wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error( array( 'message' => 'Product not found.' ) );
    }

    $product_name = $product->get_name();
    $product_description = wp_strip_all_tags( $product->get_description() );
    $product_short_description = wp_strip_all_tags( $product->get_short_description() );
    $product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
    $product_tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );

    $prompt = "Generate SEO-optimized content for a WooCommerce product. Provide a product title, slug, description, short description, and a list of relevant tags. The output MUST be a JSON object with the following keys: 'title', 'slug', 'description', 'short_description', 'tags' (an array of strings).

    Product Name: {$product_name}
    " . ( ! empty( $product_description ) ? "Existing Description: {$product_description}\n" : "" ) . "
    " . ( ! empty( $product_short_description ) ? "Existing Short Description: {$product_short_description}\n" : "" ) . "
    " . ( ! empty( $product_categories ) ? "Categories: " . implode( ', ', $product_categories ) . "\n" : "" ) . "
    " . ( ! empty( $product_tags ) ? "Existing Tags: " . implode( ', ', $product_tags ) . "\n" : "" ) . "

    Ensure the generated content is unique, engaging, and optimized for search engines. The slug should be lowercase and hyphenated. The description should be comprehensive, and the short description concise. Tags should be comma-separated keywords.

    Example JSON Output:
    {
      \"title\": \"Optimized Product Title\",
      \"slug\": \"optimized-product-slug\",
      \"description\": \"A comprehensive and engaging description of the product, highlighting its features and benefits for SEO.\",
      \"short_description\": \"A concise summary for quick understanding.\",
      \"tags\": [\"tag1\", \"tag2\", \"tag3\"]
    }";

    $api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : '';
    if ( empty( $api_key ) ) {
        wp_send_json_error( array( 'message' => 'Gemini API Key is not configured.' ) );
    }

    $model_option = defined( 'GEMINI_MODEL' ) ? GEMINI_MODEL : 'gemini-2.5-flash';
    $supported_models = array('gemini-2.5-flash');
    $model = in_array( $model_option, $supported_models ) ? $model_option : 'gemini-2.5-flash';

    $temperature = defined( 'GEMINI_TEMPERATURE' ) ? floatval( GEMINI_TEMPERATURE ) : 0.7;
    $max_tokens  = defined( 'GEMINI_MAX_TOKENS' ) ? intval( GEMINI_MAX_TOKENS ) : 2048;
    $top_p       = defined( 'GEMINI_TOP_P' ) ? floatval( GEMINI_TOP_P ) : 0.9;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . rawurlencode( $api_key );

    $body = wp_json_encode( array(
        'generationConfig' => array(
            'temperature' => $temperature,
            'maxOutputTokens' => $max_tokens,
            'topP' => $top_p,
            'responseMimeType' => 'application/json',
        ),
        'contents' => array(
            array(
                'role'  => 'user',
                'parts' => array(
                    array( 'text' => $prompt ),
                ),
            ),
        ),
    ) );

    $response = wp_remote_post( $url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => $body,
        'timeout' => 45, // Longer timeout for content generation
    ) );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( array( 'message' => 'WordPress HTTP Error: ' . $response->get_error_message() ) );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );
    $data = json_decode( $raw, true );

    if ( $code >= 200 && $code < 300 ) {
        if ( ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            $json_content = $data['candidates'][0]['content']['parts'][0]['text'];
            $parsed_content = json_decode( $json_content, true );

            // Attempt to extract JSON content more robustly
            $json_start = strpos( $json_content, '{' );
            $json_end   = strrpos( $json_content, '}' );

            if ( $json_start !== false && $json_end !== false && $json_end > $json_start ) {
                $json_content_clean = substr( $json_content, $json_start, $json_end - $json_start + 1 );
                $parsed_content = json_decode( $json_content_clean, true );
            } else {
                $parsed_content = null; // No valid JSON structure found
            }

            if ( $parsed_content && isset( $parsed_content['title'] ) ) {
                // Sanitize and prepare data for response
                $generated_title = sanitize_text_field( $parsed_content['title'] );
                $generated_slug = sanitize_title( $parsed_content['slug'] ); // WordPress function for slug
                $generated_description = wp_kses_post( $parsed_content['description'] );
                $generated_short_description = wp_kses_post( $parsed_content['short_description'] );
                $generated_tags = array_map( 'sanitize_text_field', (array) $parsed_content['tags'] );

                wp_send_json_success( array(
                    'title'           => $generated_title,
                    'slug'            => $generated_slug,
                    'description'     => $generated_description,
                    'short_description' => $generated_short_description,
                    'tags'            => $generated_tags,
                    'message'         => 'SEO content generated successfully.',
                ) );
            } else {
                wp_send_json_error( array( 'message' => 'Gemini API returned invalid JSON or missing title.' ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Gemini API returned an empty response.' ) );
        }
    } else {
        $error_message = 'Unknown API Error.';
        if ( ! empty( $data['error']['message'] ) ) {
            $error_message = sanitize_text_field( $data['error']['message'] );
        }
        wp_send_json_error( array( 'message' => 'Gemini API Error (' . $code . '): ' . $error_message ) );
    }
}

// Define GEMINI constants (from options)
if ( ! defined( 'GEMINI_API_KEY' ) ) {
    define( 'GEMINI_API_KEY', get_option( 'mwai_gemini_api_key', '' ) );
}
if ( ! defined( 'GEMINI_MODEL' ) ) {
    define( 'GEMINI_MODEL', get_option( 'mwai_gemini_model', 'gemini-2.5-flash' ) );
}
if ( ! defined( 'GEMINI_TEMPERATURE' ) ) {
    define( 'GEMINI_TEMPERATURE', get_option( 'mwai_gemini_temperature', '0.7' ) );
}
if ( ! defined( 'GEMINI_MAX_TOKENS' ) ) {
    define( 'GEMINI_MAX_TOKENS', get_option( 'mwai_gemini_max_tokens', '2048' ) );
}
if ( ! defined( 'GEMINI_TOP_P' ) ) {
    define( 'GEMINI_TOP_P', get_option( 'mwai_gemini_top_p', '0.9' ) );
}
