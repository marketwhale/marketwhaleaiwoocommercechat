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

// Admin settings page
function mwai_settings_page() {
    // Retrieve settings
    $api_key     = esc_attr( get_option( 'mwai_gemini_api_key', '' ) );
    $model       = esc_attr( get_option( 'mwai_gemini_model', 'gemini-2.5-flash' ) );
    $temperature = esc_attr( get_option( 'mwai_gemini_temperature', '0.7' ) );
    $max_tokens  = esc_attr( get_option( 'mwai_gemini_max_tokens', '1024' ) );
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
    define( 'GEMINI_MAX_TOKENS', get_option( 'mwai_gemini_max_tokens', '1024' ) );
}
if ( ! defined( 'GEMINI_TOP_P' ) ) {
    define( 'GEMINI_TOP_P', get_option( 'mwai_gemini_top_p', '0.9' ) );
}
