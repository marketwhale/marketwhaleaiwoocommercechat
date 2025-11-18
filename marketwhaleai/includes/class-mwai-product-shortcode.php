<?php
if ( ! defined( 'ABSPATH' ) ) exit;


class MWAI_Product_Shortcode {

    public function __construct() {
        add_shortcode( 'mwai_products', array( $this, 'render_mwai_products_shortcode' ) );
        add_shortcode( 'mwai_shop_browser', array( $this, 'render_mwai_shop_browser_shortcode' ) ); // New shortcode
        add_shortcode( 'mwai_category_scroller', array( $this, 'render_mwai_category_scroller_shortcode' ) ); // New category scroller shortcode
        add_shortcode( 'mwai_product_scroller', array( $this, 'render_mwai_product_scroller_shortcode' ) ); // New product scroller shortcode
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shortcode_assets' ) );
    }

    /**
     * Enqueues necessary assets for the custom product shortcode.
     * This ensures styles are loaded even on non-shop pages.
     */
    public function enqueue_shortcode_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'mwai_products' ) || has_shortcode( $post->post_content, 'mwai_shop_browser' ) || has_shortcode( $post->post_content, 'mwai_category_scroller' ) || has_shortcode( $post->post_content, 'mwai_product_scroller' ) ) ) {
            // Check if custom shortcodes feature is enabled
            if ( ! get_option( 'mwai_feature_custom_shortcodes_enabled', true ) ) {
                return; // Do not enqueue assets if feature is disabled
            }

            $plugin_url = plugin_dir_url( dirname( __FILE__ ) ); // Get plugin base URL
            
            // Enqueue shop styles
            wp_enqueue_style( 'mwai-shop-style', $plugin_url . 'assets/css/shop-styles.css', array(), '1.0' );

            // Enqueue shop-enhancements.js if mwai_shop_browser or mwai_product_scroller is used AND the respective feature is enabled
            if ( ( has_shortcode( $post->post_content, 'mwai_shop_browser' ) && get_option( 'mwai_feature_shop_browser_enabled', true ) ) || has_shortcode( $post->post_content, 'mwai_product_scroller' ) ) {
                wp_enqueue_script( 'jquery-ui-slider' ); // Ensure jQuery UI Slider is loaded for shop-enhancements.js
                wp_enqueue_script( 'mwai-shop-js', $plugin_url . 'assets/js/shop-enhancements.js', array( 'jquery', 'jquery-ui-slider' ), '1.0', true );
                wp_localize_script( 'mwai-shop-js', 'MWAI_Shop_Ajax', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'mwai_shop_nonce' )
                ) );
            }
        }
    }

    /**
     * Renders the custom [mwai_products] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the product grid.
     */
    public function render_mwai_products_shortcode( $atts ) {
        if ( ! get_option( 'mwai_feature_custom_shortcodes_enabled', true ) ) {
            return '<p>MarketWhaleAI custom product shortcodes are currently disabled by the administrator.</p>';
        }

        // Parse shortcode attributes
        $atts = shortcode_atts( array(
            'limit'      => 12,
            'columns'    => 4,
            'category'   => '', // slug or comma-separated slugs
            'orderby'    => 'date',
            'order'      => 'desc',
            'ids'        => '', // comma-separated product IDs
            'skus'       => '', // comma-separated product SKUs
            'class'      => '', // additional CSS class for the container
            'slideshow'  => true, // New attribute: enable/disable slideshow
        ), $atts, 'mwai_products' );

        // Sanitize slideshow attribute
        $atts['slideshow'] = filter_var( $atts['slideshow'], FILTER_VALIDATE_BOOLEAN );

        $query_args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => sanitize_text_field( $atts['orderby'] ),
            'order'          => sanitize_text_field( $atts['order'] ),
        );

        // Handle product IDs
        if ( ! empty( $atts['ids'] ) ) {
            $ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
            $query_args['post__in'] = $ids;
        }

        // Handle product SKUs
        if ( ! empty( $atts['skus'] ) ) {
            $skus = array_map( 'sanitize_text_field', explode( ',', $atts['skus'] ) );
            $query_args['meta_query'][] = array(
                'key'     => '_sku',
                'value'   => $skus,
                'compare' => 'IN',
            );
        }

        // Handle categories
        if ( ! empty( $atts['category'] ) ) {
            $categories = array_map( 'sanitize_title', explode( ',', $atts['category'] ) );
            $query_args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $categories,
                'operator' => 'IN',
            );
        }

        $products_query = new WP_Query( $query_args );
        ob_start();

        if ( $products_query->have_posts() ) {
            $container_classes = array( 'mwai-products-grid', 'columns-' . intval( $atts['columns'] ) );
            if ( ! empty( $atts['class'] ) ) {
                $container_classes[] = sanitize_html_class( $atts['class'] );
            }
            ?>
            <div class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>">
                <?php
                while ( $products_query->have_posts() ) {
                    $products_query->the_post();
                    $product = wc_get_product( get_the_ID() );
                    if ( $product ) {
                        echo $this->get_product_card_html( $product, $atts['slideshow'] );
                    }
                }
                wp_reset_postdata();
                ?>
            </div>
            <?php
        } else {
            echo '<p>No products found.</p>';
        }

        return ob_get_clean();
    }

    /**
     * Renders the custom [mwai_shop_browser] shortcode.
     * This embeds the full shop browsing experience.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shop browser.
     */
    public function render_mwai_shop_browser_shortcode( $atts ) {
        if ( ! get_option( 'mwai_feature_custom_shortcodes_enabled', true ) ) {
            return '<p>MarketWhaleAI custom product shortcodes are currently disabled by the administrator.</p>';
        }
        // Check if the dedicated shop browser feature is enabled
        if ( ! get_option( 'mwai_feature_shop_browser_enabled', true ) ) {
            return '<p>The MarketWhaleAI Shop Browser shortcode is currently disabled by the administrator.</p>';
        }
        // The mwai_shop_browser shortcode relies on shop enhancements, but its own enablement implies intent to use them.
        // The necessary assets are enqueued if this shortcode is present and enabled.

        $atts = shortcode_atts( array(
            'limit' => 12, // Default limit for products
            'slideshow' => true, // New attribute: enable/disable slideshow
        ), $atts, 'mwai_shop_browser' );

        // Sanitize slideshow attribute
        $atts['slideshow'] = filter_var( $atts['slideshow'], FILTER_VALIDATE_BOOLEAN );

        ob_start();
        ?>
        <div class="mwai-shop-enhancements mwai-embedded-shop" data-product-limit="<?php echo esc_attr( intval( $atts['limit'] ) ); ?>" data-slideshow-enabled="<?php echo esc_attr( $atts['slideshow'] ? 'true' : 'false' ); ?>">
            <div id="mwai-category-scrollers"></div>
            <div id="mwai-product-grid-wrapper" style="position: relative;">
                <div class="mwai-products-grid"></div>
                <div class="mwai-loading-overlay"><div class="mwai-spinner"></div></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper function to render a single product card HTML.
     * This is adapted from MWAI_Ajax_Handler::render_product_card
     */
    private function get_product_card_html( $product, $enable_slideshow = true ) {
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
                        <h3><?php echo esc_html( html_entity_decode( $title ) ); ?></h3>
                        <p class="price"><?php echo wp_kses_post( $price ); ?></p>
                    </div>
                </div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the custom [mwai_category_scroller] shortcode.
     * Displays product categories in a horizontal scroll with thumbnails and names.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the category scroller.
     */
    public function render_mwai_category_scroller_shortcode( $atts ) {
        if ( ! get_option( 'mwai_feature_custom_shortcodes_enabled', true ) ) {
            return '<p>MarketWhaleAI custom product shortcodes are currently disabled by the administrator.</p>';
        }

        $atts = shortcode_atts( array(
            'parent_id' => 0, // Display top-level categories by default
            'columns'   => 4, // Number of columns for responsive grid (not directly used for scroller, but for styling consistency)
            'class'     => '', // Additional CSS class for the container
        ), $atts, 'mwai_category_scroller' );

        $parent_id = intval( $atts['parent_id'] );

        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => $parent_id,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $categories = get_terms( $args );
        ob_start();

        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
            $container_classes = array( 'mwai-category-scroller-wrapper', 'mwai-shortcode-category-scroller' );
            if ( ! empty( $atts['class'] ) ) {
                $container_classes[] = sanitize_html_class( $atts['class'] );
            }
            ?>
            <div class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>">
                <div class="mwai-category-scroller">
                    <?php foreach ( $categories as $category ) :
                        $thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
                        $image = $thumbnail_id ? wp_get_attachment_image_src( $thumbnail_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
                        $image_url = is_array( $image ) ? $image[0] : $image;
                        $category_link = get_term_link( $category );
                        ?>
                        <a href="<?php echo esc_url( $category_link ); ?>" class="mwai-category-tab mwai-category-card">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $category->name ); ?>">
                            <span><?php echo esc_html( html_entity_decode( $category->name ) ); ?> (<?php echo esc_html( $category->count ); ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="mwai-scroll-button left hidden"><</div>
                <div class="mwai-scroll-button right hidden">></div>
            </div>
            <script type="text/javascript">
                jQuery(document).ready(function($){
                    const $scrollerWrapper = $('.mwai-shortcode-category-scroller');
                    const $scroller = $scrollerWrapper.find('.mwai-category-scroller');
                    const $leftButton = $scrollerWrapper.find('.mwai-scroll-button.left');
                    const $rightButton = $scrollerWrapper.find('.mwai-scroll-button.right');

                    function updateScrollButtons() {
                        if ($scroller[0].scrollWidth > $scroller[0].clientWidth) {
                            if ($scroller[0].scrollLeft === 0) {
                                $leftButton.addClass('hidden');
                            } else {
                                $leftButton.removeClass('hidden');
                            }

                            if ($scroller[0].scrollLeft + $scroller[0].clientWidth >= $scroller[0].scrollWidth) {
                                $rightButton.addClass('hidden');
                            } else {
                                $rightButton.removeClass('hidden');
                            }
                        } else {
                            $leftButton.addClass('hidden');
                            $rightButton.addClass('hidden');
                        }
                    }

                        $scroller.on('scroll', updateScrollButtons);
                        $(window).on('resize', updateScrollButtons);
                        setTimeout(updateScrollButtons, 100); // Initial check

                        // Ensure buttons are updated after all images are loaded
                        $(window).on('load', updateScrollButtons);

                        $leftButton.on('click', function() {
                            $scroller.animate({ scrollLeft: $scroller.scrollLeft() - 200 }, 300);
                        });

                    $rightButton.on('click', function() {
                        $scroller.animate({ scrollLeft: $scroller.scrollLeft() + 200 }, 300);
                    });
                });
            </script>
            <?php
        } else {
            echo '<p>No categories found.</p>';
        }

        return ob_get_clean();
    }

    /**
     * Renders the custom [mwai_product_scroller] shortcode.
     * Displays products in a horizontal scroll.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the product scroller.
     */
    public function render_mwai_product_scroller_shortcode( $atts ) {
        if ( ! get_option( 'mwai_feature_custom_shortcodes_enabled', true ) ) {
            return '<p>MarketWhaleAI custom product shortcodes are currently disabled by the administrator.</p>';
        }

        $atts = shortcode_atts( array(
            'limit'      => 12,
            'category'   => '', // slug or comma-separated slugs
            'orderby'    => 'date',
            'order'      => 'desc',
            'ids'        => '', // comma-separated product IDs
            'skus'       => '', // comma-separated product SKUs
            'class'      => '', // additional CSS class for the container
            'title'      => '', // Optional title for the scroller
            'slideshow'       => true, // New attribute: enable/disable slideshow
            'autoplay'        => false, // New attribute: enable/disable continuous autoplay
            'scroll_direction' => 'ltr', // New attribute: 'ltr' for left-to-right, 'rtl' for right-to-left
        ), $atts, 'mwai_product_scroller' );

        // Sanitize slideshow, autoplay, and scroll_direction attributes
        $atts['slideshow'] = filter_var( $atts['slideshow'], FILTER_VALIDATE_BOOLEAN );
        $atts['autoplay'] = filter_var( $atts['autoplay'], FILTER_VALIDATE_BOOLEAN );
        $atts['scroll_direction'] = in_array( $atts['scroll_direction'], array( 'ltr', 'rtl' ) ) ? $atts['scroll_direction'] : 'ltr';

        $query_args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => sanitize_text_field( $atts['orderby'] ),
            'order'          => sanitize_text_field( $atts['order'] ),
        );

        // Handle product IDs
        if ( ! empty( $atts['ids'] ) ) {
            $ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
            $query_args['post__in'] = $ids;
        }

        // Handle product SKUs
        if ( ! empty( $atts['skus'] ) ) {
            $skus = array_map( 'sanitize_text_field', explode( ',', $atts['skus'] ) );
            $query_args['meta_query'][] = array(
                'key'     => '_sku',
                'value'   => $skus,
                'compare' => 'IN',
            );
        }

        // Handle categories
        if ( ! empty( $atts['category'] ) ) {
            $categories = array_map( 'sanitize_title', explode( ',', $atts['category'] ) );
            $query_args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $categories,
                'operator' => 'IN',
            );
        }

        $products_query = new WP_Query( $query_args );
        ob_start();

        if ( $products_query->have_posts() ) {
            $container_classes = array( 'mwai-product-scroller-wrapper', 'mwai-shortcode-product-scroller' );
            if ( ! empty( $atts['class'] ) ) {
                $container_classes[] = sanitize_html_class( $atts['class'] );
            }
            ?>
            <div class="<?php echo esc_attr( implode( ' ', $container_classes ) ); ?>" data-autoplay-enabled="<?php echo esc_attr( $atts['autoplay'] ? 'true' : 'false' ); ?>" data-scroll-direction="<?php echo esc_attr( $atts['scroll_direction'] ); ?>">
                <?php if ( ! empty( $atts['title'] ) ) : ?>
                    <h2 class="mwai-scroller-title"><?php echo esc_html( $atts['title'] ); ?></h2>
                <?php endif; ?>
                <div class="mwai-product-scroller">
                    <?php
                    while ( $products_query->have_posts() ) {
                        $products_query->the_post();
                        $product = wc_get_product( get_the_ID() );
                        if ( $product ) {
                            echo $this->get_product_card_html( $product, $atts['slideshow'] );
                        }
                    }
                    wp_reset_postdata();
                    ?>
                </div>
                <div class="mwai-scroll-button left hidden"><</div>
                <div class="mwai-scroll-button right hidden">></div>
            </div>
            <?php
        } else {
            echo '<p>No products found.</p>';
        }

        return ob_get_clean();
    }
}

// Initialize the shortcode handler
new MWAI_Product_Shortcode();
