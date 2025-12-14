/**
 * WooCommerce Custom Currency Conversion and GeoIP Switching
 *
 * This consolidated code block handles:
 * 1. Automatically switching the WooCommerce currency based on the user's GeoIP location.
 * 2. Fetching real-time exchange rates from ExchangeRate-API.
 * 3. Converting and formatting prices for products (shop/single pages), cart subtotals, and
 * cart/order totals.
 * 4. Contains client-side JavaScript to ensure dynamic product price updates are also
 * converted,
 * preventing double numerical conversion by ONLY re-formatting prices already numerically
 * converted by PHP.
 * 5. It also handles symbol display more intelligently.
 *
 * IMPORTANT:
 * - This entire block should be placed as a single PHP snippet in a plugin like WPCode.
 *
 */

// Clear all caches (Hostinger, WordPress caching plugins, browser cache) after updating.

// --- 1. Set currency based on user IP (GeoIP)
// This filter changes the currency symbol displayed across the site, impacting WC_Price calls.
add_filter('woocommerce_currency', 'custom_geolocation_currency_switcher', 99); // Higher priority to ensure it runs

function custom_geolocation_currency_switcher($currency) {
    // Do not change currency in the admin area, as it can interfere with product editing/reporting.
    if (is_admin()) {
        return $currency;
    }

    $location = WC_Geolocation::geolocate_ip();
    $country = isset($location['country']) ? $location['country'] : 'N/A_COUNTRY';

    // Define your country-to-currency mapping.
    // Ensure these currency codes are valid ISO 4217 codes (e.g., USD, QAR, EUR).
    $country_currency_map = [
        // North America
        'US' => 'USD', // United States
        'CA' => 'CAD', // Canada
        'MX' => 'MXN', // Mexico

        // Europe (using EUR for most Eurozone countries, explicit for others)
        'AT' => 'EUR', // Austria
        'BE' => 'EUR', // Belgium
        'CY' => 'EUR', // Cyprus
        'EE' => 'EUR', // Estonia
        'FI' => 'EUR', // Finland
        'FR' => 'EUR', // France
        'DE' => 'EUR', // Germany
        'GR' => 'EUR', // Greece
        'IE' => 'EUR', // Ireland
        'IT' => 'EUR', // Italy
        'LV' => 'EUR', // Latvia
        'LT' => 'EUR', // Lithuania
        'LU' => 'EUR', // Luxembourg
        'MT' => 'EUR', // Malta
        'NL' => 'EUR', // Netherlands
        'PT' => 'EUR', // Portugal
        'SK' => 'EUR', // Slovakia
        'SI' => 'EUR', // Slovenia
        'ES' => 'EUR', // Spain
        'AD' => 'EUR', // Andorra
        'MC' => 'EUR', // Monaco
        'SM' => 'EUR', // San Marino
        'VA' => 'EUR', // Vatican City State
        'HR' => 'EUR', // Croatia (joined Eurozone)
        'GB' => 'GBP', // United Kingdom
        'CH' => 'CHF', // Switzerland
        'NO' => 'NOK', // Norway
        'SE' => 'SEK', // Sweden
        'DK' => 'DKK', // Denmark
        'PL' => 'PLN', // Poland
        'CZ' => 'CZK', // Czech Republic
        'HU' => 'HUF', // Hungary
        'RO' => 'RON', // Romania
        'BG' => 'BGN', // Bulgaria

        // Southeast Asia
        'SG' => 'SGD', // Singapore
        'MY' => 'MYR', // Malaysia
        'TH' => 'THB', // Thailand
        'ID' => 'IDR', // Indonesia
        'PH' => 'PHP', // Philippines
        'VN' => 'VND', // Vietnam
        'KH' => 'KHR', // Cambodia
        'LA' => 'LAK', // Laos
        'MM' => 'MMK', // Myanmar
        'BN' => 'BND', // Brunei
        'TL' => 'USD', // Timor-Leste (uses USD)

        // Middle East (excluding Israel)
        'QA' => 'QAR', // Qatar
        'AE' => 'AED', // United Arab Emirates
        'SA' => 'SAR', // Saudi Arabia
        'KW' => 'KWD', // Kuwait
        'BH' => 'BHD', // Bahrain
        'OM' => 'OMR', // Oman
        'JO' => 'JOD', // Jordan
        'LB' => 'LBP', // Lebanon
        'EG' => 'EGP', // Egypt
        'IQ' => 'IQD', // Iraq
        'SY' => 'SYP', // Syria (Note: Currency conversion might be unstable/unavailable due to
        'YE' => 'YER', // Yemen (Note: Currency conversion might be unstable/unavailable due to conflict)

        // Explicitly exclude Israel
        'IL' => get_option('woocommerce_currency'), // Force default for Israel if it's the base currency, or handle otherwise

        // Other common countries
        'IN' => 'INR', // India
        'JP' => 'JPY', // Japan
        'AU' => 'AUD', // Australia
        'NZ' => 'NZD', // New Zealand
        'CN' => 'CNY', // China
        'BR' => 'BRL', // Brazil
        'AR' => 'ARS', // Argentina
        'ZA' => 'ZAR', // South Africa
    ];

    // Get the base currency of the WooCommerce store
    $store_base_currency = get_option('woocommerce_currency');

    // Handle Israel specifically: always revert to the site's base currency
    if ($country === 'IL') {
        return $store_base_currency;
    }

    $intended_currency = $currency; // Default to the currency passed to the filter (usually store base)

    if (isset($country_currency_map[$country])) {
        $intended_currency = $country_currency_map[$country];
    }

    // --- NEW LOGIC: Validate exchange rate before setting currency ---
    if ($intended_currency !== $store_base_currency) {
        $rate = get_exchange_rate($store_base_currency, $intended_currency);
        if ($rate === null) {
            // If get_exchange_rate returns null, it means there was an error or rate not found.
            // In this case, revert to the store's base currency to avoid displaying a wrong currency symbol.
            return $store_base_currency;
        } else {
            return $intended_currency;
        }
    }

    return $currency; // Return the default WooCommerce currency if no specific mapping or conversion applies
}

// --- 2. Fetch exchange rates from ExchangeRate-API (Free Tier)
function get_exchange_rate($from_currency, $to_currency) {
    if ($from_currency === $to_currency) {
        return 1.0;
    }

    $transient_key = 'exrate_'. sanitize_key($from_currency). '_to_'. sanitize_key($to_currency);
    $cached = get_transient($transient_key);

    if ($cached !== false) {
        return $cached;
    }

    // ExchangeRate-API URL format for a pair conversion
    $api_url = "https://open.er-api.com/v6/latest/{$from_currency}";
    $response = wp_remote_get($api_url, array('timeout' => 15));

    if (is_wp_error($response)) {
        return null; // Return null on API call failure
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // Check if the API call was successful and rates are available
    if (isset($body['rates'][$to_currency]) && $body['result'] === 'success') {
        $rate = floatval($body['rates'][$to_currency]);
        set_transient($transient_key, $rate, HOUR_IN_SECONDS);
        return $rate;
    } else {
        return null; // Return null if rate is not found in the response
    }
}

// --- 3. Convert Product Prices (Numerical Value for Product Page and Cart Item)
// Global array to track which product IDs have had their numerical prices converted
// within the current request cycle to prevent double conversion.
// This will now store the converted price, not just a flag.
global $custom_converted_product_prices;
$custom_converted_product_prices = [];

// These filters apply the numerical conversion to various product price types.
add_filter('woocommerce_product_get_price', 'custom_convert_product_price_numerical', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'custom_convert_product_price_numerical', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'custom_convert_product_price_numerical', 10, 2);
add_filter('woocommerce_variation_get_price', 'custom_convert_product_price_numerical', 10, 2);
add_filter('woocommerce_variation_get_regular_price', 'custom_convert_product_price_numerical', 10, 2);
add_filter('woocommerce_variation_get_sale_price', 'custom_convert_product_price_numerical', 10, 2);

function custom_convert_product_price_numerical($price, $product) {
    if (!isset($product) || !is_a($product, 'WC_Product') || !is_numeric($price) || $price === "") {
        return $price;
    }

    $product_id = $product->get_id();
    $product_type = $product->get_type();
    $parent_id = ($product->is_type('variation')) ? $product->get_parent_id() : 'N/A';

    // Do not convert in admin backend unless it's a specific AJAX request where prices are meant for frontend display.
    if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
        return $price;
    }

    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency(); // This gets the currency set by GeoIP

    // Only convert if currencies are different
    if ($store_base_currency !== $current_display_currency) {
        $rate = get_exchange_rate($store_base_currency, $current_display_currency);

        // Handle null rate (API error or rate not found)
        if ($rate === null) {
            return $price; // Revert to original price if rate is not available
        }

        if ($rate === 1.0) { // Check if the rate is 1.0 (actual 1:1 or fallback from GeoIP)
            return $price;
        }

        global $custom_converted_product_prices; // Use the new global variable

        // If this product's numerical price was already converted in this request, return the stored converted value.
        // This prevents re-conversion and ensures consistency across multiple calls for the same product.
        if (isset($custom_converted_product_prices[$product_id])) {
            return $custom_converted_product_prices[$product_id];
        }

        if ($rate > 0) { // Ensure rate is valid and positive
            $converted_price = (float)$price * $rate;
            // Apply your custom rounding logic to the ones place
            $final_converted_price = round($converted_price);
            $final_converted_price = max(0.01, $final_converted_price); // Ensure price is not zero or negative

            // IMPORTANT: Set the converted price back on the product object
            // This ensures subsequent calls to $product->get_price() (etc.)
            // within the same request cycle return the converted value.
            $product->set_price($final_converted_price);

            // Store this product's price as numerically converted by PHP for this request cycle
            $custom_converted_product_prices[$product_id] = $final_converted_price;
            return $final_converted_price;
        }
    }
    return $price; // Return original if no conversion needed or rate is invalid
}

// --- Convert Product Price HTML
// This filter ensures the HTML output of product prices is also converted and formatted by PHP.
// It might still be bypassed by themes/page builders, hence the strong JS fallback.
add_filter('woocommerce_get_price_html', 'custom_convert_product_price_html', 10, 2);
add_filter('woocommerce_variable_price_html', 'custom_convert_product_price_html', 10, 2);

function custom_convert_product_price_html($price_html, $product) {
    if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
        return $price_html;
    }
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    // Only format if currencies are different
    if ($store_base_currency !== $current_display_currency) {
        $rate = get_exchange_rate($store_base_currency, $current_display_currency);
        // Handle null rate (API error or rate not found)
        if ($rate === null) {
            return $price_html; // Revert to original HTML if rate is not available
        }
        if ($rate === 1.0) { // Check if the rate is 1.0 (actual 1:1 or fallback from GeoIP)
            return $price_html;
        }
        // The numerical price should already be converted by 'custom_convert_product_price_numerical'
        $numerical_price = (float) $product->get_price();
        // If the product is on sale, format the sale price string
        if ($product->is_on_sale() && !$product->is_type('variable')) {
            // Get already-converted regular and sale prices
            $regular_price_numerical = (float) $product->get_regular_price();
            $sale_price_numerical = (float) $product->get_sale_price();
            // Apply rounding for display
            $regular_price_display = round($regular_price_numerical);
            $sale_price_display = round($sale_price_numerical);
            // Format the sale price HTML using the already converted and rounded values
            return wc_format_sale_price(
                wc_price($regular_price_display, array('currency' => $current_display_currency)),
                wc_price($sale_price_display, array('currency' => $current_display_currency))
            );
        }
        // For simple products or variable product ranges, format the main price
        // Apply rounding logic for display
        $rounded_price_for_display = round($numerical_price);
        $rounded_price_for_display = max(0.01, $rounded_price_for_display);
        // Format the converted price using wc_price
        return wc_price($rounded_price_for_display, array('currency' => $current_display_currency));
    }
    return $price_html;
    // Return original HTML if no conversion is needed
}

/**
 * --- NEW: Convert Cart Item Prices for WooCommerce Calculations (most crucial for cart totals) ---
 * This function is now the single source of truth for converting all cart item prices.
 * It gets the true base price and saves the converted value in a custom key to prevent conflicts.
 */
add_action('woocommerce_before_calculate_totals', 'custom_convert_cart_item_prices_for_calculation', 20, 1);
function custom_convert_cart_item_prices_for_calculation($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    if (is_null($cart)) {
        return;
    }
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    if ($store_base_currency !== $current_display_currency) {
        $rate = get_exchange_rate($store_base_currency, $current_display_currency);
        // Handle null rate (API error or rate not found)
        if ($rate === null) {
            return; // Exit without conversion
        }
        if ($rate === 1.0) { // Check if the rate is 1.0 (actual 1:1 or fallback from GeoIP)
            return;
        }
        if ($rate > 0) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];
                // Get the TRUE original base price directly from meta data. This works for simple and variable products.
                $original_product_base_price = (float) $product->get_meta('_price', true);
                // Final fallback if meta is empty
                if (empty($original_product_base_price)) {
                    $original_product_base_price = (float) $product->get_price('edit');
                }
                if (is_numeric($original_product_base_price)) {
                    $converted_price = $original_product_base_price * $rate;
                    $final_price_for_item = round($converted_price); // Round to ones place
                    $final_price_for_item = max(0.01, $final_price_for_item);
                    // Set the price on the product object for WC's internal calculations (like subtotal)
                    $product->set_price($final_price_for_item);
                    // ALSO, save the converted price in a custom key. This is a foolproof way to pass
                    // the correct price to the display function.
                    $cart->cart_contents[$cart_item_key]['custom_converted_price'] = $final_price_for_item;
                }
            }
        }
    }
}

/**
 * --- 4. Convert Individual Cart Item Price (Display HTML) ---
 * This function now ONLY displays the price calculated by the function above.
 * It reads the value from the custom key, preventing any double conversions.
 */
add_filter('woocommerce_cart_item_price', 'custom_convert_cart_item_price_display', 10, 3);
function custom_convert_cart_item_price_display($price_html, $cart_item, $cart_item_key) {
    $current_display_currency = get_woocommerce_currency();
    // Check if our custom converted price exists
    if (isset($cart_item['custom_converted_price'])) {
        // If it exists, format and display it. This is now the only job of this function.
        return wc_price($cart_item['custom_converted_price'], array('currency' => $current_display_currency));
    }
    // If for some reason the custom key doesn't exist, return the original HTML
    return $price_html;
}

// --- 5. Convert Cart Subtotal (for Cart Page display) ---
add_filter('woocommerce_cart_subtotal', 'custom_convert_cart_subtotal_display', 10, 3);
function custom_convert_cart_subtotal_display($subtotal_html_string, $compound, $cart_object) {
    if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
        return $subtotal_html_string;
    }
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    // Only proceed with conversion if currencies are different
    if ($store_base_currency !== $current_display_currency) {
        // Get the subtotal from the cart object. Since woocommerce_currency filter
        // has already run, this value *should already be in the display currency*.
        $cart_subtotal_numerical_converted = (float) $cart_object->get_subtotal();
        // Apply rounding for display format
        $rounded_final_display_price = round($cart_subtotal_numerical_converted); // Round to ones place
        $rounded_final_display_price = max(0.01, $rounded_final_display_price);
        // Ensure price is not zero or negative
        // Format the converted subtotal using wc_price
        return wc_price($rounded_final_display_price, array(
            'currency' => $current_display_currency,
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator()
        ));
    }
    return $subtotal_html_string;
    // Return original if no conversion needed
}

/**
 * --- NEW: Convert Variable Product Prices on Variation Selection (AJAX) ---
 * This function uses the same naming convention and targets the data passed
 * during the AJAX update to fix variable product price conversions.
 */
add_filter('woocommerce_available_variation', 'custom_convert_variation_price_for_ajax', 20, 3);
function custom_convert_variation_price_for_ajax($variation_data, $product, $variation) {
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    // Only run if currencies are different
    if ($store_base_currency === $current_display_currency) {
        return $variation_data;
    }
    // Get the exchange rate
    $rate = get_exchange_rate($store_base_currency, $current_display_currency);
    // Handle null rate (API error or rate not found)
    if ($rate === null) {
        return $variation_data; // Revert to original if rate is not available
    }
    if ($rate === 1.0) { // Check if the rate is 1.0 (actual 1:1 or fallback from GeoIP)
        return $variation_data;
    }
    if ($rate <= 0) {
        return $variation_data; // Invalid rate, do nothing
    }
    // Get the price of the specific variation being loaded using 'edit' context to get the raw value
    $variation_price_numerical = (float) $variation->get_price('edit');
    if (empty($variation_price_numerical)) {
        return $variation_data; // No price found for this variation
    }
    // Convert the price
    $converted_price = $variation_price_numerical * $rate;
    // Apply custom rounding
    $final_display_price = round($converted_price); // Round to ones place
    $final_display_price = max(0.01, $final_display_price);
    // Update the data that WooCommerce's JavaScript will use
    $variation_data['price_html'] = wc_price($final_display_price, array('currency' => $current_display_currency));
    // Also update the numerical prices for calculations if the user adds to cart
    $variation_data['display_price'] = $final_display_price;
    // If it's a sale, update the regular price display too
    if ($variation->is_on_sale()) {
        $regular_price_numerical = (float) $variation->get_regular_price('edit');
        $converted_regular_price = $regular_price_numerical * $rate;
        $final_regular_display_price = round($converted_regular_price); // Round to ones place
        $variation_data['display_regular_price'] = $final_regular_display_price;
        // Re-generate the full price HTML to show the sale
        $variation_data['price_html'] = wc_format_sale_price(
            wc_price($final_regular_display_price, array('currency' => $current_display_currency)),
            wc_price($final_display_price, array('currency' => $current_display_currency))
        );
    }
    return $variation_data;
}

// --- 6. Pass Currency Parameters to Frontend JavaScript for Dynamic Updates ---
add_action('wp_enqueue_scripts', 'custom_enqueue_currency_converter_script');
function custom_enqueue_currency_converter_script() {
    if (is_admin()) {
        return;
    }
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    $exchange_rate = get_exchange_rate($store_base_currency, $current_display_currency);
    // Only enqueue if the display currency is different from the base currency
    // AND a valid exchange rate is available (not null) AND exchange rate is not 1.0.
    if ($store_base_currency !== $current_display_currency && $exchange_rate !== null && $exchange_rate > 0 && $exchange_rate !== 1.0) {
        wp_enqueue_script(
            'custom-currency-converter',
            plugins_url('js/custom-currency-converter.js', __FILE__), // Assuming the JS file is in a 'js' folder within your plugin. If you're using WPCode, you'll need to use wp_add_inline_script.
            array('jquery'),
            '1.0.0',
            true
        );
        // Pass PHP variables to the JavaScript file
        wp_localize_script('custom-currency-converter', 'custom_currency_converter_params',
            array(
                'store_base_currency' => $store_base_currency,
                'current_display_currency' => $current_display_currency,
                'exchange_rate' => $exchange_rate,
                'currency_symbol' => get_woocommerce_currency_symbol($current_display_currency),
                'currency_position' => get_option('woocommerce_currency_pos'),
                'decimal_separator' => wc_get_price_decimal_separator(),
                'thousand_separator' => wc_get_price_thousand_separator(),
                'num_decimals' => 0 // Changed to 0 for rounding to ones place
            )
        );
    }
}

// If using WPCode or similar for a single snippet, directly embed the JS.
add_action('wp_footer', 'custom_inline_currency_converter_script', 9999);
// High priority
function custom_inline_currency_converter_script() {
    // Only output if not in admin and if conditions are met.
    if (is_admin()) {
        return;
    }
    $store_base_currency = get_option('woocommerce_currency');
    $current_display_currency = get_woocommerce_currency();
    $exchange_rate = get_exchange_rate($store_base_currency, $current_display_currency);
    // Only embed if the display currency is different from the base currency
    // AND a valid exchange rate is available (not null) AND exchange rate is not 1.0.
    if ($store_base_currency !== $current_display_currency && $exchange_rate !== null && $exchange_rate > 0 && $exchange_rate !== 1.0) {
        $currency_symbol = get_woocommerce_currency_symbol($current_display_currency);
        $currency_position = get_option('woocommerce_currency_pos');
        $decimal_separator = wc_get_price_decimal_separator();
        $thousand_separator = wc_get_price_thousand_separator();
        $num_decimals = 0; // Changed to 0 for rounding to ones place
        ?>
        <script>
            jQuery(document).ready(function($) {
                // PHP variables passed to JS
                var storeBaseCurrency = '<?php echo esc_js($store_base_currency); ?>';
                var currentDisplayCurrency = '<?php echo esc_js($current_display_currency); ?>';
                var exchangeRate = parseFloat('<?php echo esc_js($exchange_rate); ?>');
                var currencySymbol = '<?php echo esc_js($currency_symbol); ?>';
                var currencyPosition = '<?php echo esc_js($currency_position); ?>';
                var decimalSeparator = '<?php echo esc_js($decimal_separator); ?>';
                var thousandSeparator = '<?php echo esc_js($thousand_separator); ?>';
                var numDecimals = parseInt('<?php echo esc_js($num_decimals); ?>');

                // Selectors for elements containing prices
                // Ensure these selectors are robust and target only actual price displays.
                var priceSelectors = '.woocommerce-Price-amount, .amount, .price, .single-product-price';

                /**
                 * Formats a numerical price into a currency string.
                 * This mirrors wc_price() functionality in JavaScript.
                 * @param {number} price
                 * @returns {string}
                 */
                function formatPrice(price) {
                    // Ensure price is a number and non-negative
                    price = parseFloat(price);
                    if (isNaN(price)) {
                        return '';
                    }

                    // Apply the custom rounding logic to the ones place
                    var roundedPrice = Math.round(price);
                    roundedPrice = Math.max(0.01, roundedPrice); // Ensure price is not zero or negative

                    // Format decimals
                    var parts = roundedPrice.toFixed(numDecimals).split('.');
                    var integerPart = parts[0];
                    var decimalPart = parts.length > 1 ? decimalSeparator + parts[1] : '';

                    // Add thousand separators to the integer part
                    integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

                    var formatted = integerPart + decimalPart;

                    // Position the currency symbol
                    switch (currencyPosition) {
                        case 'left':
                            formatted = currencySymbol + formatted;
                            break;
                        case 'right':
                            formatted = formatted + currencySymbol;
                            break;
                        case 'left_space':
                            formatted = currencySymbol + ' ' + formatted;
                            break;
                        case 'right_space':
                            formatted = formatted + ' ' + currencySymbol;
                            break;
                    }
                    return '<bdi>' + formatted + '</bdi>';
                }

                /**
                 * Processes a single price element to convert and format its price.
                 * @param {jQuery} $priceElement
                 */
                function processPriceElement($priceElement) {
                    // Prevent reprocessing already handled elements
                    if ($priceElement.data('js-converted')) {
                        return;
                    }

                    // If the exchange rate is 1.0, JS should not attempt any conversion or re-formatting
                    // beyond what the theme/WooCommerce already does by default.
                    if (exchangeRate === 1.0) {
                        $priceElement.data('js-converted', true);
                        return;
                    }

                    if (storeBaseCurrency !== currentDisplayCurrency && exchangeRate > 0) {
                        // Extract numerical value, stripping currency symbols and thousand separators
                        var originalPriceText = $priceElement.text();
                        var currentNumericalPrice = parseFloat(originalPriceText.replace(new RegExp(currencySymbol.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), '').replace(new RegExp(thousandSeparator.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'g'), '').replace(decimalSeparator, '.'));

                        // If the value in the HTML ($priceElement.text()) is not already formatted to the ones place,
                        // and it's not NaN, then attempt to apply the rounding.
                        // We check if the price is already rounded to the nearest whole number within a small epsilon.
                        if (!isNaN(currentNumericalPrice) && Math.abs(currentNumericalPrice - Math.round(currentNumericalPrice)) > 0.001) {
                            var formattedPrice = formatPrice(currentNumericalPrice);
                            $priceElement.html(formattedPrice);
                        }
                        $priceElement.data('js-converted', true);
                    }
                }

                // --- Mutation Observer for dynamically loaded content ---
                var observerConfig = { childList: true, subtree: true };
                var priceObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            $(mutation.addedNodes).find(priceSelectors).each(function() {
                                processPriceElement($(this));
                            });
                        }
                    });
                });
                // Start observing the body for changes
                priceObserver.observe(document.body, observerConfig);

                // Re-process prices after WooCommerce AJAX update events
                $(document.body).on('updated_wc_div woocommerce_price_html_after_sale woocommerce_variation_select_change', function() {
                    $(priceSelectors).each(function() {
                        // Reset the data attribute to allow reprocessing
                        $(this).data('js-converted', false);
                        processPriceElement($(this));
                    });
                });

                // Specific handling for Elementor dynamic content, if applicable
                if (typeof elementorFrontend !== 'undefined') {
                    elementorFrontend.hooks.addAction('frontend/element_ready/section.default',
                        function($scope) {
                            $scope.find(priceSelectors).each(function() {
                                $(this).data('js-converted', false);
                                processPriceElement($(this));
                            });
                        }
                    );
                    elementorFrontend.hooks.addAction('frontend/element_ready/widget', function($scope) {
                        $scope.find(priceSelectors).each(function() {
                            $(this).data('js-converted', false);
                            processPriceElement($(this));
                        });
                    });
                }
            });
        </script>
        <?php
    }
}
