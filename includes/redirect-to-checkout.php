<?php
// Редирект на Checkout после добавления в корзину
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    return wc_get_checkout_url();
});
