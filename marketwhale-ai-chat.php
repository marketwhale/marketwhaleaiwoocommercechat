<?php
/**
 * Plugin Name: MarketWhale AI Chat
 * Description: AI-powered floating chat widget with WooCommerce product suggestions (Gemini API).
 * Version: 1.3
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Assets (CSS/JS)
function mwai_enqueue_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );
    wp_enqueue_style( 'mwai-style', $plugin_url . 'assets/css/style.css', array(), '1.3' );
    wp_enqueue_script( 'mwai-js', $plugin_url . 'assets/js/chat-widget.js', array( 'jquery' ), '1.3', true );

    // Expose ajax url to JS
    wp_localize_script( 'mwai-js', 'MWAI_Ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'mwai_enqueue_assets' );

// Include AJAX handler (Gemini + product suggestions)
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ajax-handler.php';

// Output chat widget HTML in footer
function mwai_chat_widget_html() {
    $plugin_url = plugin_dir_url( __FILE__ );
    ?>
    <!-- MarketWhale AI Chat -->
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
    <!-- /MarketWhale AI Chat -->
    <?php
}
add_action( 'wp_footer', 'mwai_chat_widget_html' );
?>