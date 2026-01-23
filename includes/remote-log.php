<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_remote_save_log', 'remote_save_log');

function remote_save_log() {
    if (!current_user_can('manage_options')) {
        wp_die('forbidden', 403);
    }

    $msg = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
    $msg = substr($msg, 0, 5000);

    $log_file = plugin_dir_path(dirname(__FILE__)) . 'importer.log';
    file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] JS_LOG: ' . $msg . PHP_EOL, FILE_APPEND);

    wp_die('ok');
}
