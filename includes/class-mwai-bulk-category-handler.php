<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MWAI_Bulk_Category_Handler {

    public function __construct() {
        // This class's methods are hooked in marketwhaleai.php
    }

    /**
     * AJAX callback to handle bulk category creation.
     */
    public static function bulk_add_categories() {
        if ( ! get_option( 'mwai_feature_bulk_categories_enabled', true ) ) {
            wp_send_json_error( array( 'message' => 'Bulk category management is currently disabled by the administrator.' ) );
        }
        check_ajax_referer( 'mwai_bulk_add_categories_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_product_terms' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to manage product categories.' ) );
        }

        $category_data_raw = isset( $_POST['category_data'] ) ? wp_unslash( $_POST['category_data'] ) : '';

        if ( empty( $category_data_raw ) ) {
            wp_send_json_error( array( 'message' => 'No category data provided.' ) );
        }

        $lines = array_map( 'trim', explode( "\n", $category_data_raw ) );
        $categories_to_process = array();
        $current_parent_stack = array(); // Stack to keep track of parent categories

        foreach ( $lines as $line ) {
            if ( empty( $line ) ) continue;

            // Determine indentation level
            preg_match( '/^(-*)\s*(.*)/', $line, $matches );
            $indent_level = strlen( $matches[1] );
            $category_name = trim( $matches[2] );

            if ( empty( $category_name ) ) continue;

            $categories_to_process[] = array(
                'name'  => $category_name,
                'level' => $indent_level,
            );
        }

        $results = array();
        $created_count = 0;
        $updated_count = 0;
        $errors = array();

        // Process categories to create them hierarchically
        foreach ( $categories_to_process as $cat_item ) {
            $category_name = $cat_item['name'];
            $level = $cat_item['level'];
            $parent_id = 0;

            // Adjust parent stack based on current level
            while ( count( $current_parent_stack ) > $level ) {
                array_pop( $current_parent_stack );
            }

            if ( $level > 0 && ! empty( $current_parent_stack ) ) {
                $parent_id = end( $current_parent_stack );
            }

            // Check if category already exists
            $existing_term_data = term_exists( $category_name, 'product_cat' ); // Check existence regardless of parent first

            if ( $existing_term_data && is_array( $existing_term_data ) ) {
                $existing_term_id = $existing_term_data['term_id'];
                $existing_term_object = get_term( $existing_term_id, 'product_cat' );

                if ( $existing_term_object && $existing_term_object->parent != $parent_id ) {
                    // Category exists but its parent needs to be updated
                    $update_result = wp_update_term( $existing_term_id, 'product_cat', array( 'parent' => $parent_id ) );
                    if ( ! is_wp_error( $update_result ) ) {
                        $results[] = array( 'status' => 'updated', 'name' => $category_name, 'term_id' => $existing_term_id, 'message' => 'Category parent updated.' );
                        $updated_count++;
                    } else {
                        $results[] = array( 'status' => 'error', 'name' => $category_name, 'message' => 'Failed to update parent: ' . $update_result->get_error_message() );
                        $errors[] = $update_result->get_error_message();
                    }
                } else {
                    // Category exists and parent is already correct or it's a top-level category
                    $results[] = array( 'status' => 'skipped', 'name' => $category_name, 'message' => 'Category already exists with correct parent.' );
                }

                // Update parent stack with existing term ID (whether updated or skipped)
                if ( count( $current_parent_stack ) === $level ) {
                    $current_parent_stack[$level] = $existing_term_id;
                } else {
                    $current_parent_stack[] = $existing_term_id;
                }
            } else {
                // Category does not exist, create it
                $term_args = array(
                    'parent' => $parent_id,
                    'slug'   => sanitize_title( $category_name ),
                );

                $insert_result = wp_insert_term( $category_name, 'product_cat', $term_args );

                if ( ! is_wp_error( $insert_result ) ) {
                    $results[] = array( 'status' => 'created', 'name' => $category_name, 'term_id' => $insert_result['term_id'] );
                    // Update parent stack with new term ID
                    if ( count( $current_parent_stack ) === $level ) {
                        $current_parent_stack[$level] = $insert_result['term_id'];
                    } else {
                        $current_parent_stack[] = $insert_result['term_id'];
                    }
                    $created_count++;
                } else {
                    $results[] = array( 'status' => 'error', 'name' => $category_name, 'message' => $insert_result->get_error_message() );
                    $errors[] = $insert_result->get_error_message();
                }
            }
        }

        if ( empty( $errors ) ) {
            wp_send_json_success( array(
                'message'       => sprintf( 'Successfully processed categories. Created: %d, Skipped (already existed): %d.', $created_count, $updated_count ),
                'results'       => $results,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
            ) );
        } else {
            wp_send_json_error( array(
                'message'       => sprintf( 'Finished processing categories with some errors. Created: %d, Skipped: %d. Errors: %s', $created_count, $updated_count, implode( '; ', $errors ) ),
                'results'       => $results,
                'created_count' => $created_count,
                'updated_count' => $updated_count,
                'errors'        => $errors,
            ) );
        }
    }
}

// Initialize the handler
new MWAI_Bulk_Category_Handler();
