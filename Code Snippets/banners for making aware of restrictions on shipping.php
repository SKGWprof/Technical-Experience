
/**
 * Plugin Name: WooCommerce Product Shipping Restrictions (Cart-Add Notices)
 * Description: Adds per-product and per-variation include/exclude country fields, silently removes invalid shipping methods, shows notices on Add-to-Cart and at Checkout.
 * Version:     1.2
 * Author:      Your Name
 * Text Domain: your-text-domain
 */

/**
 * 1) Admin: parent-level Exclude/Include fields
 */
add_action( 'woocommerce_product_options_shipping', function() {
    global $post;
    echo '<div class="options_group">';

    woocommerce_wp_text_input( array(
        'id'          => '_restricted_shipping_countries_parent',
        'label'       => __( 'Exclude Countries (All Variations)', 'your-text-domain' ),
        'placeholder' => 'US, CA, MX',
        'desc_tip'    => 'true',
        'description' => __( 'Comma-separated ISO country codes this product (and ALL its variations) CANNOT ship to. Only fill this OR Include.', 'your-text-domain' ),
        'value'       => get_post_meta( $post->ID, '_restricted_shipping_countries_parent', true ),
    ) );

    woocommerce_wp_text_input( array(
        'id'          => '_allowed_shipping_countries_parent',
        'label'       => __( 'Include Countries (All Variations)', 'your-text-domain' ),
        'placeholder' => 'DE, FR, UK',
        'desc_tip'    => 'true',
        'description' => __( 'Comma-separated ISO country codes this product (and ALL its variations) CAN ship to. Only fill this OR Exclude.', 'your-text-domain' ),
        'value'       => get_post_meta( $post->ID, '_allowed_shipping_countries_parent', true ),
    ) );

    echo '</div>';
} );

/**
 * 2) Admin: variation-level Exclude/Include fields
 */
add_action( 'woocommerce_product_after_variable_attributes', function( $loop, $variation_data, $variation ) {
    echo '<div class="options_group">';
    woocommerce_wp_text_input( array(
        'id'            => "_restricted_shipping_countries_variation_{$loop}",
        'name'          => "variable_restricted_shipping_countries[{$loop}]",
        'label'         => __( 'Exclude Countries (This Variation)', 'your-text-domain' ),
        'placeholder'   => 'US, CA, MX',
        'desc_tip'      => 'true',
        'description'   => __( 'Comma-separated ISO country codes this variation CANNOT ship to. Ignored if parent fields are set.', 'your-text-domain' ),
        'value'         => get_post_meta( $variation->ID, '_restricted_shipping_countries_variation', true ),
        'wrapper_class' => 'form-row form-row-full',
    ) );
    woocommerce_wp_text_input( array(
        'id'            => "_allowed_shipping_countries_variation_{$loop}",
        'name'          => "variable_allowed_shipping_countries[{$loop}]",
        'label'         => __( 'Include Countries (This Variation)', 'your-text-domain' ),
        'placeholder'   => 'DE, FR, UK',
        'desc_tip'      => 'true',
        'description'   => __( 'Comma-separated ISO country codes this variation CAN ship to. Ignored if parent fields are set.', 'your-text-domain' ),
        'value'         => get_post_meta( $variation->ID, '_allowed_shipping_countries_variation', true ),
        'wrapper_class' => 'form-row form-row-full',
    ) );
    echo '</div>';
}, 10, 3 );

/**
 * 3) Save parent fields
 */
add_action( 'woocommerce_process_product_meta', function( $post_id ) {
    $restricted = isset( $_POST['_restricted_shipping_countries_parent'] )
                ? sanitize_text_field( wp_unslash( $_POST['_restricted_shipping_countries_parent'] ) )
                : '';
    $allowed    = isset( $_POST['_allowed_shipping_countries_parent'] )
                ? sanitize_text_field( wp_unslash( $_POST['_allowed_shipping_countries_parent'] ) )
                : '';

    if ( $restricted && $allowed ) {
        update_post_meta( $post_id, '_restricted_shipping_countries_parent', '' );
        update_post_meta( $post_id, '_allowed_shipping_countries_parent', $allowed );
    } elseif ( $restricted ) {
        update_post_meta( $post_id, '_restricted_shipping_countries_parent', $restricted );
        update_post_meta( $post_id, '_allowed_shipping_countries_parent', '' );
    } elseif ( $allowed ) {
        update_post_meta( $post_id, '_allowed_shipping_countries_parent', $allowed );
        update_post_meta( $post_id, '_restricted_shipping_countries_parent', '' );
    } else {
        delete_post_meta( $post_id, '_restricted_shipping_countries_parent' );
        delete_post_meta( $post_id, '_allowed_shipping_countries_parent' );
    }
} );

/**
 * 4) Save variation fields
 */
add_action( 'woocommerce_save_product_variation', function( $variation_id, $i ) {
    $restricted = isset( $_POST['variable_restricted_shipping_countries'][ $i ] )
                ? sanitize_text_field( wp_unslash( $_POST['variable_restricted_shipping_countries'][ $i ] ) )
                : '';
    $allowed    = isset( $_POST['variable_allowed_shipping_countries'][ $i ] )
                ? sanitize_text_field( wp_unslash( $_POST['variable_allowed_shipping_countries'][ $i ] ) )
                : '';

    if ( $restricted && $allowed ) {
        update_post_meta( $variation_id, '_restricted_shipping_countries_variation', '' );
        update_post_meta( $variation_id, '_allowed_shipping_countries_variation', $allowed );
    } elseif ( $restricted ) {
        update_post_meta( $variation_id, '_restricted_shipping_countries_variation', $restricted );
        update_post_meta( $variation_id, '_allowed_shipping_countries_variation', '' );
    } elseif ( $allowed ) {
        update_post_meta( $variation_id, '_allowed_shipping_countries_variation', $allowed );
        update_post_meta( $variation_id, '_restricted_shipping_countries_variation', '' );
    } else {
        delete_post_meta( $variation_id, '_restricted_shipping_countries_variation' );
        delete_post_meta( $variation_id, '_allowed_shipping_countries_variation' );
    }
}, 10, 2 );

/**
 * 5) Silently filter out shipping rates
 */
add_filter( 'woocommerce_package_rates', function( $rates, $package ) {
    if ( empty( $package['destination']['country'] ) ) {
        return $rates;
    }
    $ship_to = $package['destination']['country'];

    foreach ( $package['contents'] as $item ) {
        $prod_id   = $item['product_id'];
        $var_id    = $item['variation_id'];
        $product   = $var_id ? wc_get_product( $var_id ) : wc_get_product( $prod_id );
        if ( ! $product ) {
            continue;
        }

        $parent_id       = $var_id ? $product->get_parent_id() : $prod_id;
        $parent          = wc_get_product( $parent_id );
        $parent_allow    = $parent->get_meta( '_allowed_shipping_countries_parent', true );
        $parent_restrict = $parent->get_meta( '_restricted_shipping_countries_parent', true );

        if ( $parent_allow || $parent_restrict ) {
            $allow_str    = $parent_allow;
            $restrict_str = $parent_restrict;
        } else {
            $allow_str    = $product->get_meta( '_allowed_shipping_countries_variation', true );
            $restrict_str = $product->get_meta( '_restricted_shipping_countries_variation', true );
        }

        if ( $allow_str ) {
            $allowed = array_map( 'trim', explode( ',', strtoupper( $allow_str ) ) );
            if ( ! in_array( $ship_to, $allowed, true ) ) {
                return [];
            }
        } elseif ( $restrict_str ) {
            $restricted = array_map( 'trim', explode( ',', strtoupper( $restrict_str ) ) );
            if ( in_array( $ship_to, $restricted, true ) ) {
                return [];
            }
        }
    }

    return $rates;
}, 10, 2 );

/**
 * 6) Checkout: visible error if any item invalid
 */
add_action( 'woocommerce_after_checkout_validation', function( $data, $errors ) {
    $ship_to = WC()->customer->get_shipping_country();
    if ( ! $ship_to ) {
        return;
    }

    foreach ( WC()->cart->get_cart() as $item ) {
        $product = $item['variation_id']
                 ? wc_get_product( $item['variation_id'] )
                 : wc_get_product( $item['product_id'] );
        if ( ! $product ) {
            continue;
        }

        $parent_id       = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
        $parent          = wc_get_product( $parent_id );
        $parent_allow    = $parent->get_meta( '_allowed_shipping_countries_parent', true );
        $parent_restrict = $parent->get_meta( '_restricted_shipping_countries_parent', true );

        if ( $parent_allow || $parent_restrict ) {
            $allow_str    = $parent_allow;
            $restrict_str = $parent_restrict;
        } else {
            $allow_str    = $product->get_meta( '_allowed_shipping_countries_variation', true );
            $restrict_str = $product->get_meta( '_restricted_shipping_countries_variation', true );
        }

        if ( $allow_str ) {
            $allowed = array_map( 'trim', explode( ',', strtoupper( $allow_str ) ) );
            if ( ! in_array( $ship_to, $allowed, true ) ) {
                $errors->add( 'shipping_restriction', sprintf(
                    __( 'Sorry, "%s" cannot be shipped to %s.', 'your-text-domain' ),
                    $product->get_name(),
                    WC()->countries->countries[ $ship_to ]
                ) );
                return;
            }
        } elseif ( $restrict_str ) {
            $restricted = array_map( 'trim', explode( ',', strtoupper( $restrict_str ) ) );
            if ( in_array( $ship_to, $restricted, true ) ) {
                $errors->add( 'shipping_restriction', sprintf(
                    __( 'Sorry, "%s" cannot be shipped to %s.', 'your-text-domain' ),
                    $product->get_name(),
                    WC()->countries->countries[ $ship_to ]
                ) );
                return;
            }
        }
    }
}, 10, 2 );

/**
 * 7) Cart-add: show notice immediately on add to cart
 */
add_action( 'woocommerce_add_to_cart', function( $cart_item_key, $product_id, $quantity, $variation_id ) {
    // determine the product object and metadata
    $product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
    if ( ! $product ) {
        return;
    }

    // get shipping country (fallback to store base country if none set yet)
    $ship_to = WC()->customer->get_shipping_country() ?: WC()->countries->get_base_country();

    // fetch parent or variation rules, same logic as above
    $parent_id       = $variation_id ? $product->get_parent_id() : $product_id;
    $parent          = wc_get_product( $parent_id );
    $parent_allow    = $parent->get_meta( '_allowed_shipping_countries_parent', true );
    $parent_restrict = $parent->get_meta( '_restricted_shipping_countries_parent', true );

    if ( $parent_allow || $parent_restrict ) {
        $allow_str    = $parent_allow;
        $restrict_str = $parent_restrict;
    } else {
        $allow_str    = $product->get_meta( '_allowed_shipping_countries_variation', true );
        $restrict_str = $product->get_meta( '_restricted_shipping_countries_variation', true );
    }

    // show notice if invalid
    if ( $allow_str ) {
        $allowed = array_map( 'trim', explode( ',', strtoupper( $allow_str ) ) );
        if ( ! in_array( $ship_to, $allowed, true ) ) {
            wc_add_notice( sprintf(
                __( 'Note: "%s" cannot be shipped to %s.', 'your-text-domain' ),
                $product->get_name(),
                WC()->countries->countries[ $ship_to ]
            ), 'notice' );
        }
    } elseif ( $restrict_str ) {
        $restricted = array_map( 'trim', explode( ',', strtoupper( $restrict_str ) ) );
        if ( in_array( $ship_to, $restricted, true ) ) {
            wc_add_notice( sprintf(
                __( 'Note: "%s" cannot be shipped to %s.', 'your-text-domain' ),
                $product->get_name(),
                WC()->countries->countries[ $ship_to ]
            ), 'notice' );
        }
    }
}, 10, 4 );
