<?php
/**
 * Plugin Name: WooDash Dashboard (AJAX Inline)
 * Description: WooDash с AJAX-фильтрами, сортировкой и встроенным JS в одном файле.
 * Version: 3.0
 * Author: ChatGPT & Gemini
 */

// Регистрация страницы в админ-панели
add_action('admin_menu', function () {
    add_menu_page(
        'WooDash',
        'WooDash',
        'manage_woocommerce',
        'woodash',
        'render_woodash_page',
        'dashicons-analytics',
        56
    );
});

// Подключение стилей и скриптов
function woodash_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_woodash') {
        return;
    }
    // Подключаем только Tailwind CSS и jQuery (он обычно уже есть)
    wp_enqueue_script('jquery');
    wp_enqueue_script('woodash-tailwind', 'https://cdn.tailwindcss.com', [], '3.4.1');
}
add_action('admin_enqueue_scripts', 'woodash_enqueue_admin_assets');


// Главная функция рендеринга страницы плагина
function render_woodash_page() {
    ?>
    <div class="wrap max-w-7xl mx-auto p-4">
        <h1 class="text-3xl font-bold mb-6">WooDash – Orders Overview</h1>

        <form id="woodash-filters-form" class="mb-6 bg-gray-50 p-4 rounded-lg border">
              <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
                  <div class="col-span-2 md:col-span-1">
                      <label for="filter_days" class="block text-sm font-medium mb-1">Период</label>
                      <select id="filter_days" name="filter_days" class="w-full border border-gray-300 rounded px-3 py-2">
                          <option value="today">Сегодня</option>
                          <option value="yesterday">Вчера</option>
                          <option value="7" selected>7 дней</option>
                          <option value="30">30 дней</option>
                          <option value="90">90 дней</option>
                          <option value="9999">Все время</option>
                      </select>
                  </div>
                  <div class="col-span-2 md:col-span-1">
                      <label for="filter_email" class="block text-sm font-medium mb-1">Поиск по email</label>
                      <input type="email" id="filter_email" name="filter_email" placeholder="Email клиента" class="w-full border border-gray-300 rounded px-3 py-2" />
                  </div>
                  <div class="col-span-2 md:col-span-2 lg:col-span-1">
                      <label for="filter_product" class="block text-sm font-medium mb-1">Курс/Продукт</label>
                      <select id="filter_product" name="filter_product" class="w-full border border-gray-300 rounded px-3 py-2">
                          <option value="0">Все продукты</option>
                          <?php
                          $products = get_woocommerce_products_for_filter();
                          $preselected_product_id = 2145;

                          foreach ($products as $product_id => $product_name) {
                              $selected_attr = ($product_id == $preselected_product_id) ? ' selected' : '';
                              echo '<option value="' . esc_attr($product_id) . '"' . $selected_attr . '>' . esc_html($product_name) . '</option>';
                          }
                          ?>
                      </select>
                  </div>
                  <div class="col-span-1">
                      <label for="filter_payment" class="block text-sm font-medium mb-1">Метод оплаты</label>
                      <select id="filter_payment" name="filter_payment" class="w-full border border-gray-300 rounded px-3 py-2">
                          <option value="">Все методы</option>
                          <?php
                          $payment_methods = get_payment_methods_for_filter();
                          foreach ($payment_methods as $method_key => $method_name) {
                              echo '<option value="' . esc_attr($method_key) . '">' . esc_html($method_name) . '</option>';
                          }
                          ?>
                      </select>
                  </div>
                  <div class="col-span-1">
                      <label for="per_page" class="block text-sm font-medium mb-1">Показать</label>
                      <select id="per_page" name="per_page" class="w-full border border-gray-300 rounded px-3 py-2">
                          <option value="100" selected>100</option>
                          <option value="200">200</option>
                          <option value="300">300</option>
                      </select>
                  </div>
              </div>
              <div class="flex flex-wrap gap-2">
                  <button type="submit" class="flex-1 md:flex-initial bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">Применить фильтры</button>
                  <button type="reset" id="reset-filters-btn" class="flex-1 md:flex-initial bg-gray-500 text-white px-5 py-2 rounded hover:bg-gray-600 transition">Сбросить</button>
              </div>
        </form>

        <div id="woodash-content-wrapper" class="overflow-hidden">
            <?php render_woodash_tabs_and_table_container(); ?>
        </div>
    </div>
    <?php
    render_woodash_inline_js();
}

function render_woodash_inline_js() {
    $nonce = wp_create_nonce('woodash_ajax_nonce');
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        'use strict';
        const ajax_url = '<?php echo admin_url('admin-ajax.php'); ?>';
        const woodash_nonce = '<?php echo $nonce; ?>';
        let currentOrderBy = 'date';
        let currentOrder = 'desc';

        // Функция для отображения Skeleton-анимации
        function showSkeleton() {
            const skeletonRows = 10;
            const skeletonCols = 11; // № + 9 колонок + иконка просмотра

            const headerCells = Array(skeletonCols).fill('<th class="px-4 py-4"><div class="h-4 bg-gray-200 rounded"></div></th>').join('');
            const bodyCells = Array(skeletonCols).fill('<td class="px-4 py-4 border-t border-gray-200"><div class="h-4 bg-gray-300 rounded"></div></td>').join('');
            const bodyRows = Array(skeletonRows).fill(`<tr>${bodyCells}</tr>`).join('');

            const skeletonHTML = `
                <div class="animate-pulse">
                    <!-- Skeleton для статистики -->
                    <div class='mb-4 flex justify-between items-center flex-wrap gap-4'>
                        <div class='h-5 bg-gray-200 rounded w-48'></div>
                        <div class='flex gap-4 text-sm'>
                            <div class='h-8 bg-gray-200 rounded w-40'></div>
                            <div class='h-8 bg-gray-200 rounded w-40'></div>
                        </div>
                    </div>

                    <!-- Skeleton для таблицы -->
                    <div class="overflow-x-scroll border border-gray-200 rounded">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                                <tr>${headerCells}</tr>
                            </thead>
                            <tbody>
                                ${bodyRows}
                            </tbody>
                        </table>
                    </div>

                    <!-- Skeleton для пагинации -->
                    <div class="mt-4 flex justify-center items-center gap-2">
                        <div class="h-10 w-32 bg-gray-200 rounded"></div>
                        <div class="h-10 w-10 bg-gray-200 rounded"></div>
                        <div class="h-10 w-10 bg-gray-200 rounded"></div>
                        <div class="h-10 w-10 bg-gray-200 rounded"></div>
                        <div class="h-10 w-32 bg-gray-200 rounded"></div>
                    </div>
                </div>
            `;
            $('#woodash-table-container').html(skeletonHTML);
        }

        function updateTable(paged = 1) {
            showSkeleton(); // Показываем skeleton перед загрузкой
            
            const formData = {
                action: 'woodash_update_table',
                nonce: woodash_nonce,
                status: $('#woodash-tabs .tab-button.bg-blue-500').data('status') || 'all',
                paged: paged,
                orderby: currentOrderBy,
                order: currentOrder,
                filter_days: $('#filter_days').val(),
                filter_email: $('#filter_email').val(),
                filter_product: $('#filter_product').val(),
                filter_payment: $('#filter_payment').val(),
                per_page: $('#per_page').val()
            };
            const urlParams = new URLSearchParams(window.location.search);
            Object.keys(formData).forEach(key => {
                if (key !== 'action' && key !== 'nonce') {
                     urlParams.set(key, formData[key]);
                }
            });
            const newUrl = window.location.pathname + '?' + urlParams.toString();
            history.pushState(null, '', newUrl);

            $.post(ajax_url, formData, function(response) {
                // Небольшая задержка для плавного перехода и предотвращения мигания
                setTimeout(function() {
                    $('#woodash-table-container').html(response);
                }, 200);
            });
        }

        // Обновление по кнопке "Применить фильтры"
        $('#woodash-filters-form').on('submit', function(e) {
            e.preventDefault();
            updateTable(1);
        });

        // Автоматическое обновление при изменении любого селекта в форме
        $('#woodash-filters-form').on('change', 'select', function() {
            updateTable(1);
        });
        
        // Сброс фильтров
        $('#reset-filters-btn').on('click', function(e) {
            e.preventDefault();
            $('#woodash-filters-form')[0].reset();
            $('#filter_days').val('7'); // Изменено на 7 дней
            $('#filter_product').val('2145');
            currentOrderBy = 'date';
            currentOrder = 'desc';
            updateTable(1);
        });

        // Переключение табов статусов
        $('#woodash-tabs').on('click', '.tab-button', function() {
            const $this = $(this);
            $('#woodash-tabs .tab-button').removeClass('bg-blue-500 text-white').addClass('bg-gray-200');
            $this.removeClass('bg-gray-200').addClass('bg-blue-500 text-white');
            updateTable(1);
        });

        // Пагинация
        $('#woodash-table-container').on('click', '.pagination-btn', function() {
            updateTable($(this).data('paged'));
        });

        // Сортировка
        $('#woodash-table-container').on('click', '.sortable-column', function() {
            const $this = $(this);
            currentOrderBy = $this.data('orderby');
            currentOrder = $this.data('order');
            updateTable(1);
        });

        // Первичная загрузка данных
        updateTable(1);
    });
    </script>
    <?php
}

function render_woodash_tabs_and_table_container() {
    $statuses = [
        'completed'  => 'Completed', 'pending'    => 'Pending Payment',
        'processing' => 'Processing', 'on-hold'    => 'On Hold', 'cancelled'  => 'Cancelled',
    ];

    // Используем grid для мобильных устройств (3 колонки) и flex для больших экранов
    echo '<div id="woodash-tabs" class="mb-6 bg-gray-50 p-4 rounded-lg border grid grid-cols-2 md:flex md:flex-wrap gap-2">';
    
    // Добавляем таб "All", активный по умолчанию.
    echo "<button type='button' class='tab-button text-center px-4 py-2 rounded hover:bg-gray-300 transition bg-blue-500 text-white' data-status='all'>All</button>";
    
    foreach ($statuses as $key => $label) {
        // Остальные табы по умолчанию неактивны.
        echo "<button type='button' class='tab-button text-center px-4 py-2 rounded hover:bg-gray-300 transition bg-gray-200' data-status='{$key}'>{$label}</button>";
    }
    echo '</div>';
    
    // Контейнер для таблицы теперь изначально пуст. JS заполнит его скелетоном, а затем данными.
    echo '<div id="woodash-table-container" class="relative"></div>';
}

function woodash_update_table_ajax_handler() {
    check_ajax_referer('woodash_ajax_nonce', 'nonce');
    $status         = sanitize_text_field($_POST['status']);
    $filter_days    = sanitize_text_field($_POST['filter_days']);
    $filter_email   = sanitize_email($_POST['filter_email']);
    $filter_product = intval($_POST['filter_product']);
    $filter_payment = sanitize_text_field($_POST['filter_payment']);
    $orderby        = sanitize_text_field($_POST['orderby']);
    $order          = sanitize_text_field($_POST['order']);
    $per_page       = intval($_POST['per_page']);
    $paged          = intval($_POST['paged']);
    render_orders_table($status, $filter_days, $filter_email, $filter_product, $filter_payment, $orderby, $order, $per_page, $paged);
    wp_die();
}
add_action('wp_ajax_woodash_update_table', 'woodash_update_table_ajax_handler');

// ОСНОВНАЯ ФУНКЦИЯ РЕНДЕРИНГА ТАБЛИЦЫ
function render_orders_table($status, $filter_days, $filter_email, $filter_product, $filter_payment, $orderby, $order, $per_page, $paged) {
    if (!class_exists('WC_Order')) return;

    $args = ['limit' => -1];
    
    // Обработка фильтра по статусу
    if ($status !== 'all' && !empty($status)) {
        $args['status'] = $status;
    } else {
        // Получаем все зарегистрированные статусы для 'all'
        $args['status'] = array_keys(wc_get_order_statuses());
    }

    if ($filter_days !== '9999') {
        if ($filter_days === 'today') $args['date_created'] = date('Y-m-d 00:00:00') . '...' . date('Y-m-d 23:59:59');
        elseif ($filter_days === 'yesterday') $args['date_created'] = date('Y-m-d 00:00:00', strtotime('-1 day')) . '...' . date('Y-m-d 23:59:59', strtotime('-1 day'));
        else $args['date_created'] = '>' . (time() - (intval($filter_days) * 24 * 60 * 60));
    }
    if (!empty($filter_email)) $args['billing_email'] = $filter_email;
    if (!empty($filter_payment)) $args['payment_method'] = $filter_payment;

    $orders = wc_get_orders($args);
    $table_data = [];

    foreach ($orders as $order_obj) {
        $status_slug = $order_obj->get_status();
        $status_name = wc_get_order_status_name($status_slug);

        foreach ($order_obj->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || ($filter_product && $product->get_id() != $filter_product)) continue;

            $customer_name = trim($order_obj->get_billing_first_name() . ' ' . $order_obj->get_billing_last_name());
            $customer_email = $order_obj->get_billing_email();
            $user = get_user_by('email', $customer_email);
            $order_date_obj = $order_obj->get_date_created();
            $raw_method_id = $order_obj->get_payment_method();
            $payment_method_title = $order_obj->get_payment_method_title();

            if (empty($payment_method_title)) {
                $payment_method_title = $raw_method_id;
            }
            if ($raw_method_id === 'wc_apg_gatewey_idram') {
                $payment_method_title = 'Idram';
            }

            $table_data[] = [
                'order_id'          => $order_obj->get_id(),
                'order_status_slug' => $status_slug,
                'order_status_name' => $status_name,
                'customer_name'     => $customer_name, 'user_id'        => $user ? $user->ID : 0,
                'billing_email'     => $customer_email, 'billing_phone'  => $order_obj->get_billing_phone(),
                'payment_method'    => $payment_method_title,
                'product_name'      => $product->get_name(), 'product_id'     => $product->get_id(),
                'price_num'         => floatval($item->get_total()), 'price'          => wc_price($item->get_total()),
                'date'              => $order_date_obj ? $order_date_obj->getTimestamp() : 0,
                'order_date_fmt'    => $order_date_obj ? $order_date_obj->date('Y-m-d H:i') : '',
                'quantity'          => $item->get_quantity(),
            ];
        }
    }

    if (!empty($table_data)) {
        usort($table_data, function($a, $b) use ($orderby, $order) {
            $val_a = $a[$orderby] ?? ''; $val_b = $b[$orderby] ?? '';
            $numeric_fields = ['order_id', 'price_num', 'quantity', 'date', 'user_id', 'product_id'];
            $result = in_array($orderby, $numeric_fields) ? floatval($val_a) <=> floatval($val_b) : strcasecmp(trim($val_a), trim($val_b));
            return ($order === 'desc') ? -$result : $result;
        });
    }

    $total_items = count($table_data);
    $total_pages = ceil($total_items / $per_page);
    $offset = ($paged - 1) * $per_page;
    $paged_data = array_slice($table_data, $offset, $per_page);
    $total_revenue = array_sum(array_column($table_data, 'price_num'));
    $total_quantity = array_sum(array_column($table_data, 'quantity'));
    $start_item = $total_items > 0 ? $offset + 1 : 0;
    $end_item = min($offset + $per_page, $total_items);
    
    echo "<div class='mb-4 flex justify-between items-center flex-wrap gap-4'>";
    echo "<div class='text-sm text-gray-600'>Показано {$start_item}-{$end_item} из {$total_items} записей</div>";
    echo "<div class='flex gap-4 text-sm'>";
    echo "<div class='bg-green-100 text-green-800 px-3 py-1 rounded'>Общая сумма: " . wc_price($total_revenue) . "</div>";
    echo "<div class='bg-blue-100 text-blue-800 px-3 py-1 rounded'>Количество товаров: {$total_quantity}</div>";
    echo "</div></div>";

    echo '<div class="overflow-x-scroll border border-gray-300 rounded"><table class="min-w-full text-sm text-left text-gray-700">';
    echo '<thead class="bg-gray-100"><tr>';
    echo "<th class='px-4 py-3 border border-gray-200'>№</th>";
    $columns = [
        'order_id' => 'ID Заказа и Статус', 'customer_name' => 'Клиент', 'billing_email' => 'Почта', 'billing_phone' => 'Телефон',
        'payment_method' => 'Метод оплаты', 'product_name' => 'Курс', 'quantity' => 'Кол-во', 'price_num' => 'Цена', 'date' => 'Время заказа'
    ];
    foreach ($columns as $col_key => $col_label) {
        $next_order = ($orderby === $col_key && $order === 'asc') ? 'desc' : 'asc';
        $sort_icon = ($orderby === $col_key) ? (($order === 'asc') ? ' ▲' : ' ▼') : '';
        $active_class = ($orderby === $col_key) ? ' bg-blue-100 font-semibold' : '';
        echo "<th class='px-4 py-3 border border-gray-200 cursor-pointer hover:bg-gray-200 transition{$active_class} sortable-column' data-orderby='{$col_key}' data-order='{$next_order}'>{$col_label}{$sort_icon}</th>";
    }
    echo "<th class='px-4 py-3 border border-gray-200'><span class='sr-only'>Просмотр</span></th></tr></thead><tbody>";

    if (empty($paged_data)) {
        echo '<tr><td colspan="' . (count($columns) + 2) . '" class="px-4 py-3 text-center text-gray-500">Нет заказов</td></tr>';
    } else {
        $row_number = $start_item;
        $status_classes = [
            'completed'  => 'bg-green-100 text-green-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'pending'    => 'bg-yellow-100 text-yellow-800',
            'on-hold'    => 'bg-orange-100 text-orange-800',
            'cancelled'  => 'bg-gray-200 text-gray-700',
            'refunded'   => 'bg-red-100 text-red-800',
            'failed'     => 'bg-red-200 text-red-900',
        ];
        foreach ($paged_data as $row) {
            $order_link = admin_url('post.php?post=' . $row['order_id'] . '&action=edit');
            $user_link = $row['user_id'] ? admin_url('user-edit.php?user_id=' . $row['user_id']) : '';
            $product_link = admin_url('post.php?post=' . $row['product_id'] . '&action=edit');
            
            echo '<tr class="border-t hover:bg-gray-50">';
            echo '<td class="px-4 py-2 border font-mono text-center">' . $row_number++ . '</td>';
            
            // Ячейка для ID заказа и статуса
            $status_class = $status_classes[$row['order_status_slug']] ?? 'bg-gray-100 text-gray-800';
            $status_badge = '<span class="px-2 py-1 text-xs font-semibold leading-tight rounded-full ' . $status_class . '">' . esc_html($row['order_status_name']) . '</span>';
            echo '<td class="px-4 py-2 border"><div class="flex flex-col sm:flex-row items-center sm:items-center gap-1 sm:gap-3 text-center"><a href="' . esc_url($order_link) . '" class="font-mono text-blue-600 hover:underline">' . esc_html($row['order_id']) . '</a>' . $status_badge . '</div></td>';

            echo '<td class="px-4 py-2 border">' . ($user_link ? '<a href="' . esc_url($user_link) . '" class="text-blue-600 hover:underline">' . esc_html($row['customer_name']) . '</a>' : esc_html($row['customer_name'])) . '</td>';
            echo '<td class="px-4 py-2 border">' . ($user_link ? '<a href="' . esc_url($user_link) . '" class="text-blue-600 hover:underline">' . esc_html($row['billing_email']) . '</a>' : '<a href="mailto:' . esc_attr($row['billing_email']) . '" class="text-blue-600 hover:underline">' . esc_html($row['billing_email']) . '</a>') . '</td>';
            echo '<td class="px-4 py-2 border">' . (!empty($row['billing_phone']) ? '<a href="tel:' . esc_attr($row['billing_phone']) . '" class="text-blue-600 hover:underline">' . esc_html($row['billing_phone']) . '</a>' : '&mdash;') . '</td>';
            echo '<td class="px-4 py-2 border">' . esc_html($row['payment_method']) . '</td>';
            echo '<td class="px-4 py-2 border"><a href="' . esc_url($product_link) . '" class="text-blue-600 hover:underline">' . esc_html($row['product_name']) . '</a></td>';
            echo '<td class="px-4 py-2 border text-center">' . esc_html($row['quantity']) . '</td>';
            echo '<td class="px-4 py-2 border">' . wp_kses_post($row['price']) . '</td>';
            echo '<td class="px-4 py-2 border">' . esc_html($row['order_date_fmt']) . '</td>';
            echo '<td class="px-4 py-2 border text-center"><a href="' . esc_url($order_link) . '" class="text-gray-600 hover:text-blue-600" title="Просмотреть заказ"><svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
    
    if ($total_pages > 1) {
        echo '<div class="mt-4 flex justify-center items-center gap-2" id="woodash-pagination">';
        if ($paged > 1) echo '<button data-paged="' . ($paged - 1) . '" class="pagination-btn px-3 py-2 border border-gray-300 rounded hover:bg-gray-100 transition">← Предыдущая</button>';
        $start_page = max(1, $paged - 2); $end_page = min($total_pages, $paged + 2);
        if ($start_page > 1) {
            echo '<button data-paged="1" class="pagination-btn px-3 py-2 border border-gray-300 rounded hover:bg-gray-100 transition">1</button>';
            if ($start_page > 2) echo '<span class="px-2">...</span>';
        }
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i == $paged) ? 'bg-blue-600 text-white' : 'border-gray-300 hover:bg-gray-100';
            echo '<button data-paged="' . $i . '" class="pagination-btn px-3 py-2 border rounded transition ' . $active_class . '">' . $i . '</button>';
        }
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo '<span class="px-2">...</span>';
            echo '<button data-paged="' . $total_pages . '" class="pagination-btn px-3 py-2 border border-gray-300 rounded hover:bg-gray-100 transition">' . $total_pages . '</button>';
        }
        if ($paged < $total_pages) echo '<button data-paged="' . ($paged + 1) . '" class="pagination-btn px-3 py-2 border border-gray-300 rounded hover:bg-gray-100 transition">Следующая →</button>';
        echo '</div>';
    }
}

// Вспомогательные функции
function get_woocommerce_products_for_filter() {
    if (!class_exists('WC_Product')) return []; $products = [];
    $wc_products = wc_get_products(['limit' => -1, 'status' => 'publish', 'orderby' => 'name', 'order' => 'ASC']);
    foreach ($wc_products as $product) { $products[$product->get_id()] = $product->get_name(); }
    return $products;
}
function get_payment_methods_for_filter() {
    $payment_methods = []; 
    if (!function_exists('WC')) return [];
    
    $gateways = WC()->payment_gateways->payment_gateways();
    foreach ($gateways as $gateway_id => $gateway) { 
        if ($gateway->enabled === 'yes') {
            $payment_methods[$gateway_id] = $gateway->get_title(); 
        }
    }
    global $wpdb;
    $existing_methods = $wpdb->get_results("SELECT DISTINCT pm1.meta_value as payment_method, pm2.meta_value as payment_title FROM {$wpdb->postmeta} pm1 LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_payment_method_title' INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID WHERE pm1.meta_key = '_payment_method' AND pm1.meta_value != '' AND p.post_type = 'shop_order' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY pm2.meta_value ASC");
    foreach ($existing_methods as $method) { 
        $method_key = $method->payment_method;
        $method_title = !empty($method->payment_title) ? $method->payment_title : $method_key;
        $payment_methods[$method_key] = $method_title; 
    }

    $custom_method_names = [
        'wc_apg_gatewey_idram' => 'Idram',
        'alternative_payments' => 'Այլընտրանքային վճարումներ'
    ];
    $payment_methods = array_merge($payment_methods, $custom_method_names);

    $payment_methods = array_filter($payment_methods); 
    asort($payment_methods);
    return $payment_methods;
}

?>
