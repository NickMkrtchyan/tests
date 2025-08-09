<?php
/**
 * Plugin Name: Այլընտրանքային վճարումներ WooCommerce-ի համար
 * Description: Պատվերով վճարային դարպաս՝ վճարման անդորրագրեր վերբեռնելու հնարավորությամբ
 * Version: 1.0.5
 * Author: Your Name
 */

// Պաշտպանում ենք ուղղակի մուտքից
if (!defined('ABSPATH')) {
    exit;
}

// Ստուգում ենք, արդյոք WooCommerce-ը ակտիվ է
add_action('plugins_loaded', 'init_alternative_payments_gateway');

function init_alternative_payments_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Alternative_Payments_Gateway extends WC_Payment_Gateway {
        public $instructions;

        public function __construct() {
            $this->id = 'alternative_payments';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Այլընտրանքային վճարումներ';
            $this->method_description = 'Վճարում քարտից քարտ, MoneyGram կամ բանկային հաշվի միջոցով';

            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);
            $this->enabled = $this->get_option('enabled');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_receipt_in_admin'));
            add_action('woocommerce_checkout_form_start', array($this, 'add_enctype_to_checkout_form'));
            // Основная валидация происходит здесь
            add_action('woocommerce_checkout_process', array($this, 'validate_fields'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Միացնել/Անջատել',
                    'type'    => 'checkbox',
                    'label'   => 'Միացնել այլընտրանքային վճարումները',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => 'Անվանում',
                    'type'        => 'text',
                    'description' => 'Վճարման եղանակի անվանումը, որը կտեսնեն գնորդները',
                    'default'     => 'Այլընտրանքային վճարումներ',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Նկարագրություն',
                    'type'        => 'textarea',
                    'description' => 'Վճարման եղանակի նկարագրություն',
                    'default'     => 'Վճարում այլընտրանքային եղանակներով',
                ),
                'instructions' => array(
                    'title'       => 'Վճարման հրահանգներ',
                    'type'        => 'textarea',
                    'description' => 'Հրահանգներ գնորդների համար',
                    'default'     => 'Ընտրեք վճարման հարմար եղանակ՝<br/>
<strong>Քարտից քարտ փոխանցում:</strong> 4318 2700 0093 6334 ID Bank<br/>
<strong>Բանկային հաշիվ:</strong> 11800374317600 ID Bank<br/><br/>
<strong>Zelle:</strong> 7477670990</br></br>
Վճարումից հետո պարտադիր վերբեռնեք անդորրագիրը կամ փոխանցման սքրինշոթը:',
                    'desc_tip'    => true,
                ),
            );
        }

        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->instructions));
            }
            ?>
            <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-payment-form" style="background:transparent;">
                <p class="form-row form-row-wide">
                    <label for="payment_receipt">Վերբեռնել անդորրագիրը <span class="required">*</span></label>
                    <input type="file" id="payment_receipt" name="payment_receipt" accept="image/*" class="input-text" required />
                    <small>Վերբեռնեք անդորրագրի լուսանկարը կամ փոխանցման սքրինշոթը (միայն պատկերներ)</small>
                </p>
                <div class="clear"></div>
            </fieldset>
            <?php
        }

        public function payment_scripts() {
            if (!is_checkout() || is_admin()) {
                return;
            }

            wp_enqueue_script('alternative-payments-checkout', plugin_dir_url(__FILE__) . 'checkout.js', array('jquery'), '1.0.5', true);
        }

        /**
         * Валидация на стороне сервера при оформлении заказа
         */
        public function validate_fields() {
            if (isset($_POST['payment_method']) && $_POST['payment_method'] === $this->id) {
                if (empty($_POST['payment_receipt_base64']) && (empty($_FILES['payment_receipt']['name']) || $_FILES['payment_receipt']['error'] === UPLOAD_ERR_NO_FILE)) {
                    wc_add_notice('Անդորրագիրն ավելացված է, խնդրում ենք սեղմել Ջեռք Բերել ևս մեկ անգամ', 'error');
                    return false;
                }

                // Проверяем Base64 формат если он есть
                if (!empty($_POST['payment_receipt_base64'])) {
                    $base64_string = $_POST['payment_receipt_base64'];
                    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $base64_string)) {
                        wc_add_notice('Պատկերի սխալ ձևաչափ: Թույլատրվում են JPEG, PNG, GIF:', 'error');
                        return false;
                    }

                    $base64_size = strlen($base64_string) * 0.75; // Приблизительный размер в байтах
                    if ($base64_size > 5000000) {
                        wc_add_notice('Ֆայլի չափը չպետք է գերազանցի 5MB-ը', 'error');
                        return false;
                    }
                }

                // Проверяем загруженный файл, если есть
                if (!empty($_FILES['payment_receipt']['name']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
                    if (!in_array($_FILES['payment_receipt']['type'], $allowed_types)) {
                        wc_add_notice('Խնդրում ենք վերբեռնել պատկեր (JPEG, PNG, GIF)', 'error');
                        return false;
                    }
                    if ($_FILES['payment_receipt']['size'] > 5000000) {
                        wc_add_notice('Ֆայլի չափը չպետք է գերազանցի 5MB-ը', 'error');
                        return false;
                    }
                }
            }

            return true;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            $uploaded_file = false;

            // Обрабатываем Base64, если есть
            if (!empty($_POST['payment_receipt_base64'])) {
                $uploaded_file = $this->handle_base64_upload($_POST['payment_receipt_base64'], $order_id);
            }
            // Иначе загружаем файл из $_FILES
            elseif (!empty($_FILES['payment_receipt']['name'])) {
                $uploaded_file = $this->handle_file_upload($_FILES['payment_receipt'], $order_id);
            }

            if ($uploaded_file) {
                $order->update_meta_data('_payment_receipt_url', $uploaded_file['url']);
                $order->update_meta_data('_payment_receipt_filename', $uploaded_file['filename']);
                $order->save();
            } else {
                wc_add_notice('Անդորրագրի վերբեռնման սխալ: Կրկին փորձեք:', 'error');
                return array(
                    'result' => 'failure'
                );
            }

            $order->update_status('on-hold', __('Հաշվի առնելով վճարման հաստատումը: Անդորրագիրը վերբեռնված է:', 'woocommerce'));
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        private function handle_base64_upload($base64_string, $order_id) {
            if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64_string, $matches)) {
                return false;
            }

            $file_type = $matches[1];
            $file_data = base64_decode($matches[2]);
            if ($file_data === false) {
                return false;
            }

            $allowed_types = array('jpeg', 'jpg', 'png', 'gif');
            if (!in_array(strtolower($file_type), $allowed_types)) {
                return false;
            }

            $filename = 'receipt_order_' . $order_id . '_' . time() . '.' . $file_type;
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            $file_url = $upload_dir['url'] . '/' . $filename;

            if (!file_put_contents($file_path, $file_data)) {
                return false;
            }

            return array(
                'url' => $file_url,
                'filename' => $filename
            );
        }

        private function handle_file_upload($file, $order_id) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $upload_overrides = array(
                'test_form' => false,
                'unique_filename_callback' => function($dir, $name, $ext) use ($order_id) {
                    return 'receipt_order_' . $order_id . '_' . time() . $ext;
                }
            );

            $uploaded_file = wp_handle_upload($file, $upload_overrides);

            if ($uploaded_file && !isset($uploaded_file['error'])) {
                return array(
                    'url' => $uploaded_file['url'],
                    'filename' => basename($uploaded_file['file'])
                );
            }

            return false;
        }

        public function display_receipt_in_admin($order) {
            if ($order->get_payment_method() !== $this->id) {
                return;
            }

            $receipt_url = $order->get_meta('_payment_receipt_url');
            $receipt_filename = $order->get_meta('_payment_receipt_filename');

            if ($receipt_url) {
                ?>
                <div class="order_data_column">
                    <h3>Վճարման անդորրագիր</h3>
                    <p><strong>Ֆայլ:</strong> <a href="<?php echo esc_url($receipt_url); ?>" target="_blank"><?php echo esc_html($receipt_filename); ?></a></p>
                    <img src="<?php echo esc_url($receipt_url); ?>" style="max-width: 300px; max-height: 300px;" />
                </div>
                <?php
            }
        }

        // Добавляем enctype="multipart/form-data" к форме checkout, чтобы можно было загружать файлы
        public function add_enctype_to_checkout_form() {
            ?>
            <script>
                jQuery(function($) {
                    $('#checkoutform').closest('form').attr('enctype', 'multipart/form-data');
                });
            </script>
            <?php
        }
    }

    // Регистрируем шлюз
    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = 'WC_Alternative_Payments_Gateway';
        return $methods;
    });
}
