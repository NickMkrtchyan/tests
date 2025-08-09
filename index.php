<?php
/**
 * Plugin Name: My Custom Functions
 * Description: Хранилище кастомных функций для сайта.
 * Author: Nick Mkrtchyan
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Автозагрузка всех PHP-файлов в папке includes
$includes_dir = plugin_dir_path( __FILE__ ) . 'includes/';
foreach ( glob( $includes_dir . '*.php' ) as $file ) {
    require_once $file;
}

function allow_json_uploads($mimes) {
    // Add JSON file support
    $mimes['json'] = 'application/json';
    return $mimes;
}
add_filter('upload_mimes', 'allow_json_uploads');
