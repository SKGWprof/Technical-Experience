
/**
 * PHP Code to add a custom checkbox field to WooCommerce products (under the 'Advanced' tab)
 * and conditionally hide swatches based on its value.
 *
 * Add this entire code block to your theme's functions.php file or a custom plugin.
 */

/**
 * 1. Add the custom checkbox field to the 'Advanced' tab in product data.
 */
function my_custom_product_data_fields_advanced_tab() {
    // Start of the 'Advanced' tab content block.
    // This div helps with styling and layout within the WooCommerce product data meta box.
    // Use 'options_group' for general settings within a tab.
    echo '<div class="options_group">';

    woocommerce_wp_checkbox(
        array(
            'id'            => '_hide_all_product_swatches', // Unique ID for the custom field
            'wrapper_class' => 'show_if_simple show_if_variable', // Show for simple and variable products
            'label'         => __( 'Hide All Swatches', 'your-text-domain' ), // Label for the checkbox
            'description'   => __( 'Check this box to hide all product swatches for this specific product on archive pages.', 'your-text-domain' ), // Description/tooltip
            'desc_tip'      => true, // Show description as a tooltip
        )
    );

    echo '</div>'; // End of the options_group
}
// Hook the function to add the field into the WooCommerce product data advanced options.
// This is the new hook for the 'Advanced' tab.
add_action( 'woocommerce_product_options_advanced', 'my_custom_product_data_fields_advanced_tab' );


/**
 * 2. Save the value of the custom checkbox field when the product is saved/updated.
 *
 * @param int $post_id The ID of the post (product) being saved.
 */
function my_custom_product_data_fields_save( $post_id ) {
    // Define the custom field ID. It must match the 'id' used in woocommerce_wp_checkbox.
    $checkbox_field_id = '_hide_all_product_swatches';

    // Check if the checkbox was submitted (i.e., it's checked).
    // Checkboxes only send a value if they are checked.
    $hide_all_swatches_checked = isset( $_POST[ $checkbox_field_id ] ) ? 'yes' : 'no';

    // Get the product object.
    $product = wc_get_product( $post_id );

    // Update the post meta for this product.
    // update_post_meta adds the meta if it doesn't exist, and updates if it does.
    if ( $product ) {
        $product->update_meta_data( $checkbox_field_id, $hide_all_swatches_checked );
        $product->save(); // Save the product to persist meta data changes.
    }
}
// Hook the save function to the WooCommerce action that runs when product data is saved.
add_action( 'woocommerce_process_product_meta', 'my_custom_product_data_fields_save' );


/**
 * 3. Conditionally add a CSS class to product containers in WooCommerce archives.
 * This function determines whether to hide swatches based on the custom field.
 *
 * @param array $classes An array of post classes.
 * @param string $class Additional classes to add to the post.
 * @param int $post_id The post ID.
 * @return array Modified array of post classes.
 */
function my_custom_hide_swatches_class( $classes, $class, $post_id ) {
    // Only apply this logic on product archives and for individual product posts.
    // Ensure the post is a product and we are in a shop/product category/tag archive context.
    if ( !is_shop() && !is_product_category() && !is_product_tag() ) {
        // If the current request is for a single product page or any other non-archive page,
        // return classes as-is.
        if ( is_product() ) {
            return $classes;
        }
    }

    // Get the product object.
    $product = wc_get_product( $post_id );

    // Check if the product object is valid.
    if ( ! $product ) {
        return $classes;
    }

    // Define the meta key for our custom checkbox field.
    // This MUST match the 'id' used when creating the field ('_hide_all_product_swatches').
    $hide_all_swatches_meta_key = '_hide_all_product_swatches';

    // Retrieve the value of the custom field for the current product.
    // The save function stores 'yes' or 'no'.
    $hide_all_swatches = $product->get_meta( $hide_all_swatches_meta_key, true );

    // Check if the checkbox is "checked" (i.e., its value is 'yes').
    if ( $hide_all_swatches === 'yes' ) {
        // Add the custom CSS class to the product's container.
        $classes[] = 'hide-all-swatches-for-this-product';
    }

    // Return the modified array of classes.
    return $classes;
}
// Hook this function into the 'post_class' filter, specifically for WooCommerce products.
// The priority 20 ensures it runs after WooCommerce's default class additions.
// The 3 arguments are $classes, $class (additional), $post_id.
add_filter( 'post_class', 'my_custom_hide_swatches_class', 20, 3 );

