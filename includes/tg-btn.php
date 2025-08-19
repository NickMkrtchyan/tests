<?php
add_shortcode('telegram_access_button', function () {
    if (!is_user_logged_in()) {
        return '<p>Խնդրում ենք <a href="' . esc_url(wp_login_url()) . '">մուտք գործել</a>, որպեսզի տեսնեք Telegram հղումները։</p>';
    }

    $user_id = get_current_user_id();
    $page_id = get_the_ID();

    // Карта ID продуктов и Telegram-групп
    $product_groups = [
        // 3468 => [
        //     'title' => 'Facebuilding V1',
        //     'url'   => 'https://t.me/',
        //     'page_id' => 3445
        // ],
        // 2145 => [
        //     'title' => 'New Body Marathon',
        //     'url'   => 'https://t.me/+ZCYwfOwfzNNkNmQy',
        //     'page_id' => 2121
        // ],
    ];

    $user_product_ids = [];

    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status' => ['completed', 'processing', 'on-hold'],
        'limit' => -1
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            if (isset($product_groups[$product_id])) {
                $user_product_ids[] = $product_id;
            }
        }
    }

    // У пользователя нет ни одного подходящего продукта
    if (empty($user_product_ids)) {
        return '<p>Դուք չունեք մուտքի իրավունք որևէ Telegram խմբին։</p>';
    }

    // 1. Пытаемся найти продукт, привязанный к этой странице
    foreach ($product_groups as $pid => $group) {
        if ($group['page_id'] == $page_id && in_array($pid, $user_product_ids)) {
            return generate_telegram_button($group);
        }
    }

    // 2. Если не совпадает с текущей страницей — просто выводим первый доступный
    foreach ($user_product_ids as $pid) {
        if (isset($product_groups[$pid])) {
            return generate_telegram_button($product_groups[$pid]);
        }
    }

    return '<p>Դուք չունեք մուտքի իրավունք որևէ Telegram խմբին։</p>';
});

// 🔹 Вспомогательная функция генерации кнопки
function generate_telegram_button($group) {
    ob_start();
    ?>
    <div class="telegram-access-box" style="text-align: center; margin: 30px 0; padding: 20px; background: #f1f1f1; border-radius: 10px;">
        <h3 style="margin-bottom: 10px;">Դուք ունեք մուտք <strong><?php echo esc_html($group['title']); ?></strong> Telegram խմբին 💬</h3>
        <p style="color: #444;">Սեղմեք ստորև ներկայացված կոճակը՝ միանալու խմբին։</p>
        <a href="<?php echo esc_url($group['url']); ?>" target="_blank" style="display:inline-block; margin-top: 10px; padding: 12px 20px; background-color: #34A853; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">
            <i class="fab fa-telegram" style="margin-right: 6px;"></i> Միանալ Telegram խմբին
        </a>
    </div>
    <?php
    return ob_get_clean();
}
