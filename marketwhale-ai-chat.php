<?php
/**
 * Plugin Name: MarketWhale AI Chat
 * Description: AI-powered floating chat widget with WooCommerce product suggestions (Gemini API).
 * Version: 1.4
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Assets (CSS/JS)
function mwai_enqueue_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'mwai-style', $plugin_url . 'assets/css/style.css', array(), '1.4' );
    wp_enqueue_script( 'mwai-js', $plugin_url . 'assets/js/chat-widget.js', array( 'jquery' ), '1.4', true );

    wp_localize_script( 'mwai-js', 'MWAI_Ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'mwai_enqueue_assets' );

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

// Admin settings page
function mwai_settings_page() {
    $api_key = esc_attr( get_option( 'mwai_gemini_api_key', '' ) );
    $model   = esc_attr( get_option( 'mwai_gemini_model', 'gemini-2.5-flash' ) );
    $temperature = esc_attr( get_option( 'mwai_gemini_temperature', '0.7' ) );
    $max_tokens  = esc_attr( get_option( 'mwai_gemini_max_tokens', '1024' ) );
    $top_p       = esc_attr( get_option( 'mwai_gemini_top_p', '0.9' ) );
    ?>
    <div class="wrap">
        <h1>MarketWhale AI Chat Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'mwai_settings_group' ); ?>
            <?php do_settings_sections( 'mwai_settings_group' ); ?>

            <table class="form-table">

                <tr valign="top">
                    <th scope="row">Gemini API Key</th>
                    <td>
                        <input type="text" name="mwai_gemini_api_key" value="<?php echo $api_key; ?>" size="50" />
                        <p class="description">Enter your Gemini API Key.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Model</th>
                    <td>
                        <select name="mwai_gemini_model">
                            <option value="gemini-2.5-flash" <?php selected( $model, 'gemini-2.5-flash' ); ?>>Gemini 2.5 Flash</option>
                            <option value="gemini-2.5" <?php selected( $model, 'gemini-2.5' ); ?>>Gemini 2.5</option>
                            <option value="gemini-1" <?php selected( $model, 'gemini-1' ); ?>>Gemini 1</option>
                        </select>
                        <p class="description">Choose the model for responses.</p>
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
    </div>
    <?php
}

// Define GEMINI_API_KEY, model, and attributes
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
