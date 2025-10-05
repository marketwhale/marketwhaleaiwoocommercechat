<?php
if ( ! defined( 'ABSPATH' ) ) exit;


// __How to use the new shortcode:__

// You can now use the `[mwai_products]` shortcode in any WordPress post, page, or widget. Here are some examples of its usage:

// - __Display 12 products with default settings:__ `[mwai_products]`

// - __Display 8 products in 2 columns from a specific category (e.g., 'electronics'):__ `[mwai_products limit="8" columns="2" category="electronics"]`

// - __Display products by specific IDs:__ `[mwai_products ids="1,2,3,4"]`

// - __Display products by specific SKUs:__ `[mwai_products skus="SKU001,SKU002"]`

// - __Display products ordered by title in ascending order:__ `[mwai_products orderby="title" order="asc"]`

// - __Add a custom CSS class to the product grid:__ `[mwai_products class="my-custom-grid"]`

// This new shortcode provides a modern look and offers more flexibility for displaying WooCommerce products on your site.


class MWAI_Product_Shortcode {

    public function __construct() {
        add_shortcode( 'mwai_products', array( $this, 'render_mwai_products_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shortcode_assets' ) );
    }

    /**
     * Enqueues necessary assets for the custom product shortcode.
     * This ensures styles are loaded even on non-shop pages.
     */
    public function enqueue_shortcode_assets() {
        global $post;
        if ( has_shortcode( $post->post_content, 'mwai_products' ) ) {
            $plugin_url = plugin_dir_url( dirname( __FILE__ ) ); // Get plugin base URL
            wp_enqueue_style( 'mwai-shop-style', $plugin_url . 'assets/css/shop-styles.css', array(), '1.0' );
            wp_enqueue_script( 'mwai-shop-js', $plugin_url . 'assets/js/shop-enhancements.js', array( 'jquery' ), '1.0', true );
        }
    }

    /**
     * Renders the custom [mwai_products] shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the product grid.
     */
    public function render_mwai_products_shortcode( $atts ) {
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
        ), $atts, 'mwai_products' );

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
                        echo $this->render_product_card( $product );
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
     * Helper function to render a single product card HTML.
     * This is adapted from MWAI_Ajax_Handler::render_product_card
     */
    private function render_product_card( $product ) {
        $product_id = $product->get_id();
        $image_url = get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src();
        $title = $product->get_name();
        $price = $product->get_price_html();
        $link = get_permalink( $product_id );

        ob_start();
        ?>
        <div class="mwai-shop-product-card" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <a href="<?php echo esc_url( $link ); ?>">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                <h3><?php echo esc_html( $title ); ?></h3>
                <p class="price"><?php echo wp_kses_post( $price ); ?></p>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the shortcode handler
new MWAI_Product_Shortcode();
