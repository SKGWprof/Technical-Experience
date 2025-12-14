add_action('woocommerce_applied_coupon', 'limit_number_of_coupons', 10, 1);

function limit_number_of_coupons($applied_coupon) {
    // Get applied coupons
    $applied_coupons = WC()->cart->get_applied_coupons();

    // Check if more than 3 coupons are applied
    if (count($applied_coupons) > 3) {
        // Remove the last applied coupon
        WC()->cart->remove_coupon($applied_coupon);

        // Check if the coupon has a percentage discount greater than 5%
        $coupon = new WC_Coupon($applied_coupon);
        $discount_type = $coupon->get_discount_type();
        $hint_message = '';

        if ($discount_type === 'percent' && $coupon->get_amount() > 15) {
            $hint_message = __(' Hint: you should probably use that code.', 'woocommerce');
        }

        // Add a custom error notice
        wc_clear_notices(); // Clear all existing notices
        wc_add_notice(
            __('You can only use a maximum of 3 coupons at a time.', 'woocommerce') . $hint_message,
            'error'
        );

        // Output dynamic JavaScript to remove existing notices and add the error notice
        add_action('wp_footer', function () use ($hint_message) {
            ?>
            <script type="text/javascript">
                (function($) {
                    // Wait for 200ms to ensure all notices are dynamically loaded
                    setTimeout(function () {
                        // Remove all success or existing notices
                        $('.woocommerce-message, .woocommerce-error').remove();

                        // Add the error notice
                        $('body').prepend('<div class="woocommerce-error"><?php echo esc_js(__('You can only use a maximum of 3 coupons at a time.', 'woocommerce') . $hint_message); ?></div>');
                    }, 500);
                })(jQuery);
            </script>
            <?php
        });
    }
}
