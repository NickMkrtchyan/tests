<?php
/**
 * Plugin Name: RankUP Custom Functions
 * Description: Набор кастомных функций и интеграций для WooCommerce, Tutor LMS и Telegram. Рефактор от RankUP: объединены схожие модули, единые префиксы и структура.
 * Author: RankUP
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Автозагрузка всех PHP-файлов из папки includes (как и было ранее)
$includes_dir = plugin_dir_path( __FILE__ ) . 'includes/';
foreach ( glob( $includes_dir . '*.php' ) as $file ) {
    require_once $file;
}

// Разрешаем загрузку JSON-файлов
add_filter('upload_mimes', function($m) { $m['json'] = 'application/json'; return $m; });
