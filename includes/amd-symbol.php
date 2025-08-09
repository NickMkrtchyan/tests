<?php 

add_filter('woocommerce_currency_symbol', 'custom_amd_currency_symbol', 10, 2);

function custom_amd_currency_symbol($currency_symbol, $currency) {
    if ($currency === 'AMD') {
        $currency_symbol = '֏'; // Армянский драм
    }
    return $currency_symbol;
}
