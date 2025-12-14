
// 1. Add a toggle switch to the Product Data > Advanced tab
function add_custom_link_toggle_to_product_edit() {
    woocommerce_wp_checkbox(
        array(
            'id'          => '_enable_custom_link_field',
            'label'       => __('Enable Custom Link', 'woocommerce'),
            'description' => __('Check this box to show the custom URL field for this product.', 'woocommerce'),
            'desc_tip'    => true,
        )
    );
}
add_action('woocommerce_product_options_advanced', 'add_custom_link_toggle_to_product_edit');


// 2. Save the value of the custom link toggle switch
function save_custom_link_toggle_state($post_id) {
    $product = wc_get_product($post_id);
    $is_enabled = isset($_POST['_enable_custom_link_field']) ? 'yes' : 'no';
    $product->update_meta_data('_enable_custom_link_field', $is_enabled);
    $product->save();
}
add_action('woocommerce_process_product_meta', 'save_custom_link_toggle_state');


// 3. MODIFIED - Display the custom field on the product page if the toggle is enabled
function custom_product_name() {
    global $product;

    // Check the custom meta field instead of the post name
    if (is_a($product, 'WC_Product') && $product->get_meta('_enable_custom_link_field') === 'yes') {
        echo '<div class="custom-link-field">
                  <div class="custom-link-instruction">
                      Insert your custom link here
                  </div>
                  <input type="text" id="custom_link" name="custom_link" 
                         placeholder="www.yoururl.com" 
                         maxlength="100" 
                         required 
                         pattern="^(https?:\/\/|www\.)[a-zA-Z0-9-]+\.[a-zA-Z]{2,}.*$"
                         title="Enter a valid URL starting with www. or https://">
              </div>';
    }
}
add_action('woocommerce_after_variations_form', 'custom_product_name', 10);


// ---------------------------------------------------------------- //
// UNCHANGED - The following functions remain the same.             //
// ---------------------------------------------------------------- //


// 4. Validate and filter the URL for inappropriate content
function validate_custom_link_field($passed, $product_id, $quantity) {
    if (isset($_POST['custom_link'])) {
        $custom_link = sanitize_text_field($_POST['custom_link']);

        // List of inappropriate keywords to block
        $blocked_keywords = ['porn', 'xxx', 'sex', 'adult', 'nude', 'escort'];

        foreach ($blocked_keywords as $keyword) {
            if (stripos($custom_link, $keyword) !== false) {
                wc_add_notice(__('This URL contains inappropriate content. Please enter a valid link.'), 'error');
                return false;
            }
        }

        // URL validation: Allow only URLs starting with "https://" or "www."
        if (!preg_match('/^(https?:\/\/|www\.)[a-zA-Z0-9-]+\.[a-zA-Z]{2,}.*$/', $custom_link)) {
            wc_add_notice(__('Please enter a valid URL starting with www. or https://'), 'error');
            return false;
        }
    }
    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'validate_custom_link_field', 10, 3);


// 5. Save the validated custom link to the cart
function save_custom_link_field($cart_item_data, $product_id) {
    if (isset($_POST['custom_link'])) {
        $cart_item_data['custom_link'] = sanitize_text_field($_POST['custom_link']);
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'save_custom_link_field', 10, 2);


// 6. Display the truncated custom link in the cart and checkout (with limit of 15 characters)
function truncate_custom_link($item_data, $cart_item) {
    if (isset($cart_item['custom_link'])) {
        $full_link = esc_html($cart_item['custom_link']);
        $truncated_link = strlen($full_link) > 15 ? substr($full_link, 0, 15) . '...' : $full_link;
        
        $item_data[] = array(
            'key'   => 'Custom Link',
            'value' => '<span class="variation-CustomLink">' . $truncated_link . '</span>',
        );
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'truncate_custom_link', 10, 2);


// 7. Add the custom link to the order meta in admin dashboard
function custom_link_order_meta($item_id, $item, $order) {
    if (isset($item['custom_link'])) {
        wc_add_order_item_meta($item_id, 'Custom Link', $item['custom_link']);
    }
}
add_action('woocommerce_add_order_item_meta', 'custom_link_order_meta', 10, 3);


// 8. Display the custom link in the order summary and prevent duplication in the email
function display_custom_link_in_order_items($item_id, $item, $order) {
    if ($custom_link = wc_get_order_item_meta($item_id, 'Custom Link', true)) {
        $truncated_link = strlen($custom_link) > 15 ? substr($custom_link, 0, 15) . '...' : $custom_link;
        echo '<p class="variation-CustomLink"><strong>Custom Link:</strong> ' . esc_html($truncated_link) . '</p>';
    }
}
add_action('woocommerce_order_item_meta_end', 'display_custom_link_in_order_items', 10, 3);


// 9. Remove duplicate custom link from the end of the order confirmation page (thank you page)
function remove_duplicate_custom_link_from_thankyou_page($order_id) {
    remove_action('woocommerce_thankyou', 'display_truncated_custom_link_in_order_summary');
}
add_action('template_redirect', 'remove_duplicate_custom_link_from_thankyou_page');


// 10. Modify the email to include only the custom link provided by the user (full-length only)
function custom_link_in_email($order, $sent_to_admin, $plain_text, $email) {
    foreach ($order->get_items() as $item_id => $item) {
        if ($custom_link = wc_get_order_item_meta($item_id, 'Custom Link', true)) {
            // Add custom link to the email content without truncation
            echo '<p><strong>Custom Link:</strong> ' . esc_html($custom_link) . '</p>';
        }
    }
}
add_action('woocommerce_email_order_meta', 'custom_link_in_email', 10, 4);


// ---------------------------------------------------------------- //
// UNCHANGED - Your coupon and AJAX code remains the same.          //
// ---------------------------------------------------------------- //

// Apply Full Discount When 100% Coupon is Applied
function apply_full_discount_on_coupon( $cart ) {
    if ( is_admin() || $cart->is_empty() ) {
        return;
    }
    
    $discount_applied = false;
    $total_amount = 0;

    foreach ( $cart->get_applied_coupons() as $coupon_code ) {
        $coupon = new WC_Coupon( $coupon_code );
        
        // Check if it's a 100% off coupon
        if ( $coupon->get_discount_type() == 'percent' && $coupon->get_amount() == 100 ) {
            // Calculate total (subtotal + shipping)
            $total_amount = $cart->get_subtotal() + $cart->get_shipping_total();

            // Apply the discount as a negative fee
            $cart->add_fee( __( '100% Off Discount', 'woocommerce' ), -$total_amount );
            
            $discount_applied = true;
        }
    }

    // Log through error log during AJAX calls (for backend debugging)
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        error_log('Coupon Applied: ' . ($discount_applied ? 'Yes' : 'No'));
        error_log('Cart Total Calculated: ' . $total_amount);
    }
}

// Force Recalculation When Coupon is Applied or Removed
function recalculate_cart_on_coupon_apply() {
    WC()->cart->calculate_totals();
}
add_action( 'woocommerce_applied_coupon', 'recalculate_cart_on_coupon_apply' );
add_action( 'woocommerce_removed_coupon', 'recalculate_cart_on_coupon_apply' );

// Attach Discount Application to Fee Calculation
add_action( 'woocommerce_cart_calculate_fees', 'apply_full_discount_on_coupon', 20, 1 );


// AJAX Handler to Log Coupon Status to Console
function log_coupon_status() {
    $cart = WC()->cart;
    $total_amount = $cart->get_subtotal() + $cart->get_shipping_total();

    $applied_coupons = $cart->get_applied_coupons();

    wp_send_json([
        'message' => 'Cart Recalculated',
        'applied_coupons' => $applied_coupons,
        'total' => $total_amount
    ]);
}
add_action( 'wp_ajax_log_coupon_status', 'log_coupon_status' );
add_action( 'wp_ajax_nopriv_log_coupon_status', 'log_coupon_status' );


// JavaScript to Trigger AJAX on Coupon Apply/Remove
function add_ajax_coupon_logging_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('body').on('applied_coupon removed_coupon updated_cart_totals', function() {
                $.ajax({
                    url: wc_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'log_coupon_status'
                    },
                    success: function(response) {
                        console.log(response.message);
                        console.log('Applied Coupons:', response.applied_coupons);
                        console.log('Cart Total:', response.total);
                    }
                });
            });
        });
    </script>
    <?php
}
add_action( 'wp_footer', 'add_ajax_coupon_logging_script' );
