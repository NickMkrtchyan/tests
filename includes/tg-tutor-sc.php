<?php
add_shortcode('telegram_access_all_buttons', function () {
    if (!is_user_logged_in()) {
        return '<p>Խնդրում ենք <a href="' . esc_url(wp_login_url()) . '">մուտք գործել</a>, որպեսզի տեսնեք Telegram հղումները։</p>';
    }

    $user_id = get_current_user_id();

    // ID продуктов и Telegram-группы
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

    if (empty($user_product_ids)) {
        return '<p>Դուք չունեք մուտքի իրավունք որևէ Telegram խմբին։</p>';
    }

    ob_start();
    foreach ($user_product_ids as $pid) {
        $group = $product_groups[$pid];
        ?>
        <div class="telegram-access-box" style="text-align: center; margin: 30px 0; padding: 20px; background: #f1f1f1; border-radius: 10px;">
            <h3 style="margin-bottom: 10px;">Դուք ունեք մուտք <strong><?php echo esc_html($group['title']); ?></strong> Telegram խմբին 💬</h3>
            <p style="color: #444;">Սեղմեք ստորև ներկայացված կոճակը՝ միանալու խմբին։</p>
            <a href="<?php echo esc_url($group['url']); ?>" target="_blank" style="display:inline-block; margin-top: 10px; padding: 12px 20px; background-color: #34A853; color: #fff; text-decoration: none; border-radius: 6px; font-size: 16px;">
                <i class="fab fa-telegram" style="margin-right: 6px;"></i> Միանալ Telegram խմբին
            </a>
        </div>
        <?php
    }
    return ob_get_clean();
});


add_action('tutor_profile_after_header', 'custom_message_after_profile_header', 10);

function custom_message_after_profile_header() {
    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);
    
    echo '<div class="custom-profile-message">';
    echo '<p>Ваш кастомный текст здесь! Например, ваше дополнительное сообщение для пользователя.</p>';
    echo '</div>';
}
