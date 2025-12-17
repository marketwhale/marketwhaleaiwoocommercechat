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
    // Normalize the option to a strict boolean. This avoids PHP truthiness issues
    // (e.g. string values) so the shop is only modified when the admin explicitly enables it.
    $shop_enhancements_enabled = filter_var( get_option( 'mwai_feature_shop_enhancements_enabled', true ), FILTER_VALIDATE_BOOLEAN );

    // If Shop Page Enhancements are disabled, ensure any leftover classes are removed early
    // so the default WooCommerce shop is not accidentally hidden by cached markup/CSS.
    if ( ! $shop_enhancements_enabled ) {
        add_action( 'wp_head', function() {
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ try{ document.body.classList.remove('mwai-shop-loading','mwai-shop-enhancements-active'); }catch(e){} });</script>";
        }, 1 );
    }
    if ( get_option( 'mwai_feature_chat_widget_enabled', true ) ) {
        wp_enqueue_style( 'mwai-style', $plugin_url . 'assets/css/style.css', array(), '1.6' );
        wp_enqueue_script( 'mwai-js', $plugin_url . 'assets/js/chat-widget.js', array( 'jquery' ), '1.6', true );

        wp_localize_script( 'mwai-js', 'MWAI_Ajax', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'mwai_ajax_nonce' ),
            'plugin_url' => $plugin_url, // Add plugin URL
        ) );
    }

    // Enqueue shop page enhancement assets only on shop-related pages if enabled
    if ( $shop_enhancements_enabled && ( is_shop() || is_product_category() || is_product_tag() ) ) {
        wp_enqueue_style( 'mwai-shop-style', $plugin_url . 'assets/css/shop-styles.css', array(), '1.0' );
        wp_enqueue_script( 'jquery-ui-slider' ); // Enqueue jQuery UI Slider
        wp_enqueue_script( 'mwai-shop-js', $plugin_url . 'assets/js/shop-enhancements.js', array( 'jquery', 'jquery-ui-slider' ), '1.0', true );
        wp_localize_script( 'mwai-shop-js', 'MWAI_Shop_Ajax', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'mwai_shop_nonce' ),
            'plugin_url' => $plugin_url,
            'filters_enabled_for_shop' => get_option( 'mwai_enable_filters_for_shop_enhancements', true ),
            'filters_enabled_for_embedded' => get_option( 'mwai_enable_filters_for_shop_browser', true ),
            'shop_enhancements_enabled' => $shop_enhancements_enabled,
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'mwai_enqueue_assets' );

// Add a body class to hide original shop content while enhancements load, if enabled
function mwai_add_shop_loading_body_class( $classes ) {
    $enabled = filter_var( get_option( 'mwai_feature_shop_enhancements_enabled', true ), FILTER_VALIDATE_BOOLEAN );
    if ( $enabled && ( is_shop() || is_product_category() || is_product_tag() ) ) {
        // Add both a loading class and an explicit enhancements-active flag so CSS hiding
        // only applies when the feature is enabled server-side.
        $classes[] = 'mwai-shop-loading';
        $classes[] = 'mwai-shop-enhancements-active';
    }
    return $classes;
}
add_filter( 'body_class', 'mwai_add_shop_loading_body_class' );

// Enqueue admin scripts only on the settings page
function mwai_admin_enqueue_scripts( $hook_suffix ) {
    if ( 'toplevel_page_mwai-settings' === $hook_suffix ) {
        wp_enqueue_style( 'mwai-admin-tabs-style', plugin_dir_url( __FILE__ ) . 'assets/css/admin-tabs.css', array(), '1.0' );
        wp_enqueue_script( 'mwai-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-settings.js', array( 'jquery' ), '1.0', true );
        wp_enqueue_script( 'mwai-admin-tabs-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-tabs.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-admin-js', 'MWAI_Admin_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_test_connection_nonce' ),
        ) );
    }
}
add_action( 'admin_enqueue_scripts', 'mwai_admin_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'mwai_admin_product_seo_enqueue_scripts' ); // New action for product SEO
add_action( 'admin_enqueue_scripts', 'mwai_admin_bulk_categories_enqueue_scripts' ); // New action for bulk categories

// Register feature activation settings
function mwai_register_feature_settings() {
    register_setting( 'mwai_settings_group', 'mwai_feature_chat_widget_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_feature_shop_enhancements_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_feature_product_seo_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_feature_bulk_categories_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_feature_custom_shortcodes_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_feature_shop_browser_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    // Register settings to control whether filtering & sorting are enabled
    register_setting( 'mwai_settings_group', 'mwai_enable_filters_for_shop_enhancements', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_enable_filters_for_shop_browser', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
}
add_action( 'admin_init', 'mwai_register_feature_settings' );

// Enqueue admin scripts for product SEO on product edit screen if enabled
function mwai_admin_product_seo_enqueue_scripts( $hook_suffix ) {
    if ( get_option( 'mwai_feature_product_seo_enabled', true ) && 'post.php' === $hook_suffix && 'product' === get_post_type() ) {
        wp_enqueue_script( 'mwai-admin-product-seo-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-product-seo.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'mwai-admin-product-seo-js', 'MWAI_Product_SEO_Ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mwai_generate_seo_content_nonce' ),
        ) );
    }
}

// Enqueue admin scripts and styles for bulk category management if enabled
function mwai_admin_bulk_categories_enqueue_scripts( $hook_suffix ) {
    if ( get_option( 'mwai_feature_bulk_categories_enabled', true ) && 'edit-tags.php' === $hook_suffix && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat' ) {
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
    if ( get_option( 'mwai_feature_chat_widget_enabled', true ) ) {
        $plugin_url = plugin_dir_url( __FILE__ );
        ?>
        <div id="mwai-chat-window" class="hidden" role="dialog" aria-label="MarketWhale AI chat">
            <div id="mwai-chat-header">
                <span>MarketWhale AI</span>
                <img id="mwai-close-btn" src="<?php echo esc_url( $plugin_url . 'assets/images/close-icon.png' ); ?>" alt="Close chat">
            </div>

            <div id="mwai-chat-body" aria-live="polite"></div>

            <div id="mwai-quick-actions-container" class="mwai-quick-buttons"></div> <!-- New persistent container for quick buttons -->
        </div>

        <div id="mwai-chat-bar" aria-hidden="false" role="toolbar" aria-label="Chat input bar">
            <input type="text" id="mwai-user-input" placeholder="Type your message..." aria-label="Type your message">
            <button id="mwai-toggle-send-btn" aria-label="Open chat or Send message">
                <img src="<?php echo esc_url( $plugin_url . 'assets/images/openchat2.png' ); ?>" alt="Open Chat">
            </button>
        </div>
        <?php
    }
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

    // Register settings for [mwai_products] shortcode
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_limit', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => 12,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_columns', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => 4,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_category', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_orderby', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'date',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_order', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'desc',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_ids', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_skus', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_class', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_html_class',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_products_slideshow', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );

    // Register settings for [mwai_category_scroller] shortcode
    register_setting( 'mwai_settings_group', 'mwai_shortcode_category_scroller_parent_id', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => 0,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_category_scroller_class', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_html_class',
        'default'           => '',
    ) );

    // Register settings for [mwai_product_scroller] shortcode
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_title', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_limit', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => 12,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_category', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_orderby', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'date',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_order', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'desc',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_ids', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_skus', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_class', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_html_class',
        'default'           => '',
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_slideshow', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_autoplay', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => false,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_product_scroller_scroll_direction', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'ltr',
    ) );

    // Register settings for [mwai_shop_browser] shortcode
    register_setting( 'mwai_settings_group', 'mwai_shortcode_shop_browser_limit', array(
        'type'              => 'integer',
        'sanitize_callback' => 'intval',
        'default'           => 12,
    ) );
    register_setting( 'mwai_settings_group', 'mwai_shortcode_shop_browser_slideshow', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => true,
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

// Add meta box to product edit screen if enabled
function mwai_add_product_seo_meta_box() {
    if ( get_option( 'mwai_feature_product_seo_enabled', true ) ) {
        add_meta_box(
            'mwai_product_seo_meta_box',
            __( 'MarketWhale AI SEO', 'marketwhale-ai-chat' ),
            'mwai_product_seo_meta_box_callback',
            'product',
            'side', // Position in the right sidebar
            'high'
        );
    }
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

    // Retrieve feature activation settings
    $chat_widget_enabled        = get_option( 'mwai_feature_chat_widget_enabled', true );
    $shop_enhancements_enabled  = get_option( 'mwai_feature_shop_enhancements_enabled', true );
    $product_seo_enabled        = get_option( 'mwai_feature_product_seo_enabled', true );
    $bulk_categories_enabled    = get_option( 'mwai_feature_bulk_categories_enabled', true );
    $custom_shortcodes_enabled  = get_option( 'mwai_feature_custom_shortcodes_enabled', true );
    $shop_browser_enabled       = get_option( 'mwai_feature_shop_browser_enabled', true );
    $filters_shop_enhancements_enabled = get_option( 'mwai_enable_filters_for_shop_enhancements', true );
    $filters_shop_browser_enabled = get_option( 'mwai_enable_filters_for_shop_browser', true );
    ?>
    <div class="wrap mwai-admin-settings-wrap">
        <h1>MarketWhale AI Chat Settings</h1>

        <ul class="nav-tab-wrapper mwai-admin-tabs">
            <li><a href="#ai-settings" data-tab="ai-settings" class="nav-tab">AI Settings</a></li>
            <li><a href="#feature-activation" data-tab="feature-activation" class="nav-tab">Feature Activation</a></li>
            <li><a href="#shortcode-settings" data-tab="shortcode-settings" class="nav-tab">Shortcode Settings</a></li>
            <li><a href="#about" data-tab="about" class="nav-tab">About MarketWhaleAI</a></li>
        </ul>

        <form method="post" action="options.php">
            <?php settings_fields( 'mwai_settings_group' ); ?>
            <?php do_settings_sections( 'mwai_settings_group' ); ?>

            <div data-tab-id="ai-settings" class="mwai-tab-content hidden">
                <?php if ( empty( $api_key ) ) : ?>
                    <div class="notice notice-error">
                        <p><strong>Important:</strong> Please enter your Gemini API Key below to enable AI functionalities.</p>
                    </div>
                <?php endif; ?>
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

                <h2>Test Gemini Connection</h2>
                <p>Click the button below to test if your Gemini API Key is working correctly.</p>
                <button id="mwai-test-connection-btn" class="button button-secondary">Test Connection</button>
                <p id="mwai-test-connection-result"></p>
            </div> <!-- #ai-settings -->

            <div data-tab-id="feature-activation" class="mwai-tab-content hidden">
                <h2>Feature Activation</h2>
                <p>Enable or disable specific MarketWhaleAI functionalities for your store.</p>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">AI Chat Widget (Frontend)</th>
                        <td>
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_chat_widget_enabled" value="1" <?php checked( $chat_widget_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_chat_widget_enabled">Enable the floating AI chat widget on your storefront.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Shop Page Enhancements (Frontend)</th>
                        <td>
                            <input type="hidden" name="mwai_feature_shop_enhancements_enabled" value="0" />
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_shop_enhancements_enabled" value="1" <?php checked( $shop_enhancements_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_shop_enhancements_enabled">Enable dynamic category scrollers and infinite product grid on shop pages.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Filtering &amp; Sorting (Shop Pages)</th>
                        <td>
                            <input type="hidden" name="mwai_enable_filters_for_shop_enhancements" value="0" />
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_enable_filters_for_shop_enhancements" value="1" <?php checked( $filters_shop_enhancements_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_enable_filters_for_shop_enhancements">Enable filtering and sorting controls on shop/category/tag pages (requires Shop Page Enhancements enabled).</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Product SEO Generation (Admin)</th>
                        <td>
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_product_seo_enabled" value="1" <?php checked( $product_seo_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_product_seo_enabled">Enable AI-powered SEO content generation for individual products.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Bulk Category Management (Admin)</th>
                        <td>
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_bulk_categories_enabled" value="1" <?php checked( $bulk_categories_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_bulk_categories_enabled">Enable the tool for bulk adding and organizing product categories.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Custom Product Shortcodes (Frontend)</th>
                        <td>
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_custom_shortcodes_enabled" value="1" <?php checked( $custom_shortcodes_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_custom_shortcodes_enabled">Enable custom shortcodes like `[mwai_products]`, `[mwai_category_scroller]`, and `[mwai_product_scroller]`.</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Shop Browser Shortcode (Frontend)</th>
                        <td>
                            <label class="mwai-switch">
                                <input type="checkbox" name="mwai_feature_shop_browser_enabled" value="1" <?php checked( $shop_browser_enabled, true ); ?> />
                                <span class="mwai-slider round"></span>
                            </label>
                            <label for="mwai_feature_shop_browser_enabled">Enable the `[mwai_shop_browser]` shortcode for embedding the dynamic shop experience.</label>
                        </td>
                    </tr>
                </table>
            </div> <!-- #feature-activation -->

            <div data-tab-id="shortcode-settings" class="mwai-tab-content hidden">
                <h2>MarketWhale AI Shortcode Settings</h2>
                <div class="mwai-admin-subtabs-wrapper">
                    <ul class="mwai-admin-subtabs">
                        <li><a href="#mwai_products_shortcode" data-subtab="mwai_products_shortcode" class="nav-tab nav-tab-sub">mwai_products</a></li>
                        <li><a href="#mwai_category_scroller_shortcode" data-subtab="mwai_category_scroller_shortcode" class="nav-tab nav-tab-sub">mwai_category_scroller</a></li>
                        <li><a href="#mwai_product_scroller_shortcode" data-subtab="mwai_product_scroller_shortcode" class="nav-tab nav-tab-sub">mwai_product_scroller</a></li>
                        <li><a href="#mwai_shop_browser_shortcode" data-subtab="mwai_shop_browser_shortcode" class="nav-tab nav-tab-sub">mwai_shop_browser</a></li>
                    </ul>
                    <div class="mwai-admin-subtab-content-wrapper">
                        <!-- mwai_products_shortcode Settings -->
                        <div data-subtab-id="mwai_products_shortcode" class="mwai-admin-subtab-content hidden">
                            <h3>[mwai_products] Shortcode Defaults</h3>
                            <p>Set default attributes for the <code>[mwai_products]</code> shortcode.</p>
                            <table class="form-table">
                                <!-- Attributes for mwai_products -->
                                <tr valign="top">
                                    <th scope="row">Limit</th>
                                    <td>
                                        <input type="number" name="mwai_shortcode_products_limit" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_limit', 12 ) ); ?>" min="1" />
                                        <p class="description">Number of products to display (default: 12).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Columns</th>
                                    <td>
                                        <input type="number" name="mwai_shortcode_products_columns" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_columns', 4 ) ); ?>" min="1" max="6" />
                                        <p class="description">Number of columns for the product grid (default: 4, max: 6).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Category Slugs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_products_category" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_category', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated category slugs to display products from (e.g., `electronics,clothing`). Leave empty for all categories.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Order By</th>
                                    <td>
                                        <select name="mwai_shortcode_products_orderby">
                                            <option value="date" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'date' ); ?>>Date</option>
                                            <option value="title" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'title' ); ?>>Title</option>
                                            <option value="id" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'id' ); ?>>ID</option>
                                            <option value="menu_order" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'menu_order' ); ?>>Menu Order</option>
                                            <option value="popularity" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'popularity' ); ?>>Popularity</option>
                                            <option value="rand" <?php selected( get_option( 'mwai_shortcode_products_orderby', 'date' ), 'rand' ); ?>>Random</option>
                                        </select>
                                        <p class="description">Sort order of products (default: Date).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Order</th>
                                    <td>
                                        <select name="mwai_shortcode_products_order">
                                            <option value="desc" <?php selected( get_option( 'mwai_shortcode_products_order', 'desc' ), 'desc' ); ?>>Descending</option>
                                            <option value="asc" <?php selected( get_option( 'mwai_shortcode_products_order', 'desc' ), 'asc' ); ?>>Ascending</option>
                                        </select>
                                        <p class="description">Sort direction (default: Descending).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Product IDs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_products_ids" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_ids', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated product IDs (e.g., `1,5,10`). Overrides category and other filtering.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Product SKUs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_products_skus" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_skus', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated product SKUs (e.g., `SKU001,SKU005`). Overrides category and other filtering.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Custom CSS Class</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_products_class" value="<?php echo esc_attr( get_option( 'mwai_shortcode_products_class', '' ) ); ?>" size="50" />
                                        <p class="description">Add an extra CSS class to the product grid container.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Enable Slideshow</th>
                                    <td>
                                        <label class="mwai-switch">
                                            <input type="checkbox" name="mwai_shortcode_products_slideshow" value="1" <?php checked( get_option( 'mwai_shortcode_products_slideshow', true ), true ); ?> />
                                            <span class="mwai-slider round"></span>
                                        </label>
                                        <label for="mwai_shortcode_products_slideshow">Enable image slideshow for products in this grid.</label>
                                    </td>
                                </tr>
                            </table>
                        </div> <!-- #mwai_products_shortcode -->

                        <!-- mwai_category_scroller_shortcode Settings -->
                        <div data-subtab-id="mwai_category_scroller_shortcode" class="mwai-admin-subtab-content hidden">
                            <h3>[mwai_category_scroller] Shortcode Defaults</h3>
                            <p>Set default attributes for the <code>[mwai_category_scroller]</code> shortcode.</p>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">Parent Category ID</th>
                                    <td>
                                        <input type="number" name="mwai_shortcode_category_scroller_parent_id" value="<?php echo esc_attr( get_option( 'mwai_shortcode_category_scroller_parent_id', 0 ) ); ?>" min="0" />
                                        <p class="description">ID of the parent category (0 for top-level categories, default: 0).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Custom CSS Class</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_category_scroller_class" value="<?php echo esc_attr( get_option( 'mwai_shortcode_category_scroller_class', '' ) ); ?>" size="50" />
                                        <p class="description">Add an extra CSS class to the category scroller container.</p>
                                    </td>
                                </tr>
                            </table>
                        </div> <!-- #mwai_category_scroller_shortcode -->

                        <!-- mwai_product_scroller_shortcode Settings -->
                        <div data-subtab-id="mwai_product_scroller_shortcode" class="mwai-admin-subtab-content hidden">
                            <h3>[mwai_product_scroller] Shortcode Defaults</h3>
                            <p>Set default attributes for the <code>[mwai_product_scroller]</code> shortcode.</p>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">Title</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_product_scroller_title" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_title', '' ) ); ?>" size="50" />
                                        <p class="description">Optional title displayed above the scroller.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Limit</th>
                                    <td>
                                        <input type="number" name="mwai_shortcode_product_scroller_limit" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_limit', 12 ) ); ?>" min="1" />
                                        <p class="description">Number of products to display (default: 12).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Category Slugs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_product_scroller_category" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_category', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated category slugs to display products from.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Order By</th>
                                    <td>
                                        <select name="mwai_shortcode_product_scroller_orderby">
                                            <option value="date" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'date' ); ?>>Date</option>
                                            <option value="title" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'title' ); ?>>Title</option>
                                            <option value="id" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'id' ); ?>>ID</option>
                                            <option value="menu_order" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'menu_order' ); ?>>Menu Order</option>
                                            <option value="popularity" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'popularity' ); ?>>Popularity</option>
                                            <option value="rand" <?php selected( get_option( 'mwai_shortcode_product_scroller_orderby', 'date' ), 'rand' ); ?>>Random</option>
                                        </select>
                                        <p class="description">Sort order of products (default: Date).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Order</th>
                                    <td>
                                        <select name="mwai_shortcode_product_scroller_order">
                                            <option value="desc" <?php selected( get_option( 'mwai_shortcode_product_scroller_order', 'desc' ), 'desc' ); ?>>Descending</option>
                                            <option value="asc" <?php selected( get_option( 'mwai_shortcode_product_scroller_order', 'desc' ), 'asc' ); ?>>Ascending</option>
                                        </select>
                                        <p class="description">Sort direction (default: Descending).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Product IDs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_product_scroller_ids" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_ids', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated product IDs.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Product SKUs</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_product_scroller_skus" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_skus', '' ) ); ?>" size="50" />
                                        <p class="description">Comma-separated product SKUs.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Custom CSS Class</th>
                                    <td>
                                        <input type="text" name="mwai_shortcode_product_scroller_class" value="<?php echo esc_attr( get_option( 'mwai_shortcode_product_scroller_class', '' ) ); ?>" size="50" />
                                        <p class="description">Add an extra CSS class to the product scroller container.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Enable Slideshow</th>
                                    <td>
                                        <label class="mwai-switch">
                                            <input type="checkbox" name="mwai_shortcode_product_scroller_slideshow" value="1" <?php checked( get_option( 'mwai_shortcode_product_scroller_slideshow', true ), true ); ?> />
                                            <span class="mwai-slider round"></span>
                                        </label>
                                        <label for="mwai_shortcode_product_scroller_slideshow">Enable image slideshow for products in this scroller.</label>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Enable Autoplay</th>
                                    <td>
                                        <label class="mwai-switch">
                                            <input type="checkbox" name="mwai_shortcode_product_scroller_autoplay" value="1" <?php checked( get_option( 'mwai_shortcode_product_scroller_autoplay', false ), true ); ?> />
                                            <span class="mwai-slider round"></span>
                                        </label>
                                        <label for="mwai_shortcode_product_scroller_autoplay">Automatically scroll products horizontally.</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Scroll Direction</th>
                                    <td>
                                        <select name="mwai_shortcode_product_scroller_scroll_direction">
                                            <option value="ltr" <?php selected( get_option( 'mwai_shortcode_product_scroller_scroll_direction', 'ltr' ), 'ltr' ); ?>>Left to Right</option>
                                            <option value="rtl" <?php selected( get_option( 'mwai_shortcode_product_scroller_scroll_direction', 'ltr' ), 'rtl' ); ?>>Right to Left</option>
                                        </select>
                                        <p class="description">Direction for autoplay scrolling (default: Left to Right).</p>
                                    </td>
                                </tr>
                            </table>
                        </div> <!-- #mwai_product_scroller_shortcode -->

                        <!-- mwai_shop_browser_shortcode Settings -->
                        <div data-subtab-id="mwai_shop_browser_shortcode" class="mwai-admin-subtab-content hidden">
                            <h3>[mwai_shop_browser] Shortcode Defaults</h3>
                            <p>Set default attributes for the <code>[mwai_shop_browser]</code> shortcode.</p>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">Product Limit</th>
                                    <td>
                                        <input type="number" name="mwai_shortcode_shop_browser_limit" value="<?php echo esc_attr( get_option( 'mwai_shortcode_shop_browser_limit', 12 ) ); ?>" min="1" />
                                        <p class="description">Number of products to display per load (default: 12).</p>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Enable Slideshow</th>
                                    <td>
                                        <label class="mwai-switch">
                                            <input type="checkbox" name="mwai_shortcode_shop_browser_slideshow" value="1" <?php checked( get_option( 'mwai_shortcode_shop_browser_slideshow', true ), true ); ?> />
                                            <span class="mwai-slider round"></span>
                                        </label>
                                        <label for="mwai_shortcode_shop_browser_slideshow">Enable image slideshow for products in the shop browser.</label>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Enable Filters &amp; Sorting</th>
                                    <td>
                                        <label class="mwai-switch">
                                            <input type="checkbox" name="mwai_enable_filters_for_shop_browser" value="1" <?php checked( $filters_shop_browser_enabled, true ); ?> />
                                            <span class="mwai-slider round"></span>
                                        </label>
                                        <label for="mwai_enable_filters_for_shop_browser">Enable filtering and sorting controls when the <code>[mwai_shop_browser]</code> shortcode is embedded.</label>
                                    </td>
                                </tr>
                            </table>
                        </div> <!-- #mwai_shop_browser_shortcode -->
                    </div><!-- .mwai-admin-subtab-content-wrapper -->
                </div><!-- .mwai-admin-subtabs-wrapper -->
            </div> <!-- #shortcode-settings -->

            <div data-tab-id="about" class="mwai-tab-content hidden">
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
            </div> <!-- #about -->

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Handle connection test AJAX request
function mwai_test_connection_callback() {
    check_ajax_referer( 'mwai_test_connection_nonce', '_wpnonce' );

    $api_key = get_option( 'mwai_gemini_api_key', '' );
    $model   = get_option( 'mwai_gemini_model', 'gemini-2.5-flash' ); // Changed default model

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
