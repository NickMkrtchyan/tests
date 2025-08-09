<?php
// Отключить AJAX добавление в корзину
add_filter( 'woocommerce_product_single_add_to_cart_ajax_enabled', '__return_false' );
add_filter( 'woocommerce_product_add_to_cart_ajax_enabled', '__return_false' );
