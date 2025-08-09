<?php

add_filter('woocommerce_checkout_fields', 'rankup_customize_checkout_fields');
function rankup_customize_checkout_fields( $fields ) {

    // Скрываем и устанавливаем страну по умолчанию
    $fields['billing']['billing_country']['type'] = 'hidden';
    $fields['billing']['billing_country']['value'] = 'AM';

    // Удаляем ненужные поля
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['shipping']['shipping_postcode']);

    // Делаем телефон обязательным
    $fields['billing']['billing_phone']['required'] = true;

    return $fields;
}

// Убеждаемся, что страна записывается правильно в заказ
add_action('woocommerce_checkout_create_order', 'rankup_set_billing_country', 10, 2);
function rankup_set_billing_country($order, $data) {
    $order->set_billing_country('AM');
}


// 🔹 1. Удаляем стандартный контент "Thank You"
remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
remove_action('woocommerce_thankyou', 'woocommerce_order_again_button', 20);
remove_action('woocommerce_thankyou', 'woocommerce_customer_details', 30);

// 🔹 2. Добавляем свой кастомный блок
add_action('woocommerce_thankyou', 'rankup_custom_thankyou_content', 5);
function rankup_custom_thankyou_content($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $first_name = $order->get_billing_first_name();
    $items = $order->get_items();
    $product_id = 0;
    $product_title = '';

    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        $product_title = $item->get_name();
        break; // Берем только первый товар
    }

    // Telegram Access Link (на основе покупки)
    $telegram_link = '';
    if ($product_id == 333 || $product_id == 530) {
        $telegram_link = home_url('/telegram-access/' . $product_id);
    }

    ?>
    <div class="rankup-thankyou" style="text-align:center; max-width: 600px; padding: 0 10px; border-radius: 12px; background: #f9f9f9; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <h2 style="color: #222;">Ողջույն <?php echo esc_html($first_name); ?> 🎉<br>Շնորհակալություն «<span style="font-family: fantasy;"><?php echo esc_html($product_title); ?></span>»-ին միանալու համար։</h2>
        <span style="margin-top: 20px; font-size: 16px; color: #444;">Ձեր վճարումը հաջողությամբ կատարվել է 🥳 Այժմ կարող եք սկսել Ձեր դասընթացը։</span>
        <a href="/dashboard/enrolled-courses/" style="display:inline-block; margin: 25px 0; padding: 12px 24px; background-color:#0073aa; color: #fff; text-decoration: none; border-radius: 8px; font-size: 16px;"><i class="fas fa-arrow-right" style="margin-right: 8px;"></i> Սկսել դասընթացը</a>
        <?php if ($telegram_link): ?>
            <br>
            <span style="margin-top: 30px; font-size: 15px; color: #444;">Միացե՛ք մեր ընկերական Telegram խմբին 💬՝ թարմացումներ, աջակցություն և նոր կապեր հաստատելու համար։</span>
            <a href="<?php echo esc_url($telegram_link); ?>" target="_blank" style="display:inline-block; margin-top: 10px; padding: 10px 20px; background-color: #34A853; color: #fff; text-decoration: none; border-radius: 8px;"><i class="fab fa-telegram" style="margin-right: 8px;"></i> Միանալ Telegram խմբին</a>
        <?php endif; ?>
    </div>
    <?php
}

// 🔹 3. CSS – скрываем стандартные блоки Thank You
add_action('wp_head', function () {
    if (is_wc_endpoint_url('order-received')) {
        echo '<style>
            .woocommerce-notice.woocommerce-notice--success.woocommerce-thankyou-order-received,
            .woocommerce-order-overview.woocommerce-thankyou-order-details.order_details,
            .woocommerce-order p,
            .woocommerce-order-details,
            .woocommerce-customer-details {
                display: none !important;
            }
        </style>';
    }
});



// 🔹 Регистрируем endpoint
add_action('init', function () {
    add_rewrite_rule('^telegram-access/([0-9]+)/?', 'index.php?tg_product_id=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'tg_product_id';
    return $vars;
});

// 🔹 Обрабатываем переход и защищаем ссылку
add_action('template_redirect', function () {
    $tg_product_id = get_query_var('tg_product_id');

    if (!$tg_product_id) return;

    // Обязательно войти
    if (!is_user_logged_in()) {
        wp_redirect('https://chandiryan.com/dashboard');
        exit;
    }


    $user_id = get_current_user_id();
    $has_access = false;

    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status' => ['completed', 'processing', 'on-hold'],
        'limit' => -1
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ((int) $item->get_product_id() === (int) $tg_product_id) {
                $has_access = true;
                break 2;
            }
        }
    }

    if ($has_access) {
        // Перенаправляем в соответствующий Telegram
        if ($tg_product_id == 333) {
            wp_redirect('https://t.me/+WbMo3rZ7AMxhMTA6');
        } elseif ($tg_product_id == 530) {
            wp_redirect('https://t.me/+Ypvi3gTfmqRmNGYy');
        } else {
            wp_redirect(home_url()); // fallback
        }
    } else {
        wp_die('Դուք չունեք մուտքի իրավունք այս Telegram խմբին։');
    }

    exit;
});
