<?php
/**
 * Plugin Name: MarketWhale AI Chat
 * Description: AI-powered floating chat widget with WooCommerce product suggestions (Gemini API + SerpAPI).
 * Version: 1.6
 * Author: Your Name
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
}
add_action( 'wp_enqueue_scripts', 'mwai_enqueue_assets' );

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
add_action( 'admin_enqueue_scripts', 'mwai_admin_product_seo_enqueue_scripts' ); // New action for product SEO script

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

// Include AJAX handler
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ajax-handler.php';

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
        'MarketWhale AI Chat Settings',
        'MarketWhale AI Chat',
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
    register_setting( 'mwai_settings_group', 'mwai_gemini_api_key' );
    register_setting( 'mwai_settings_group', 'mwai_gemini_model' );
    register_setting( 'mwai_settings_group', 'mwai_gemini_temperature' );
    register_setting( 'mwai_settings_group', 'mwai_gemini_max_tokens' );
    register_setting( 'mwai_settings_group', 'mwai_gemini_top_p' );
}
add_action( 'admin_init', 'mwai_register_settings' );

// Add AJAX action for connection test
add_action( 'wp_ajax_mwai_test_connection', 'mwai_test_connection_callback' );
add_action( 'wp_ajax_mwai_generate_seo_content', 'mwai_generate_seo_content_callback' ); // New AJAX action for product SEO

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
        <?php if ( empty( $api_key ) || $api_key === 'AIzaSyA2vmScQRlnniWTaWLNwkpr-9PdhPyKsTk' ) : ?>
            <div class="notice notice-error">
                <p><strong>Important:</strong> Please enter your actual Gemini API Key below. The placeholder key will not work.</p>
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
    <?php
}

// Handle connection test AJAX request
function mwai_test_connection_callback() {
    check_ajax_referer( 'mwai_test_connection_nonce', '_wpnonce' );

    $api_key = get_option( 'mwai_gemini_api_key', '' );
    $model   = get_option( 'mwai_gemini_model', 'gemini-1.0-pro' ); // Changed default model

    if ( empty( $api_key ) || $api_key === 'AIzaSyA2vmScQRlnniWTaWLNwkpr-9PdhPyKsTk' ) {
        wp_send_json_error( array( 'message' => 'Gemini API Key is not configured or is still the placeholder key.' ) );
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
