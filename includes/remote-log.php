<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_remote_save_log', 'remote_save_log');
add_action('wp_ajax_remote_save_failed_item', 'remote_save_failed_item');

function remote_save_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    $msg = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
    $msg = substr($msg, 0, 5000);

    // Garantir caminho correto da pasta
    $log_dir = rtrim(plugin_dir_path(__DIR__), '/') . '/logs';
    
    // Tratamento seguro para LOG_TYPE e HASH
    $log_type = (!empty($_POST['log_type']) && $_POST['log_type'] !== 'null') ? sanitize_file_name($_POST['log_type']) : 'general';
    $hash = (!empty($_POST['hash']) && $_POST['hash'] !== 'null') ? sanitize_file_name($_POST['hash']) : 'no-hash';

    $log_file = $log_dir . '/importer_' . $log_type . '_' . $hash . '.log';
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $result = file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] JS_LOG: ' . $msg . PHP_EOL, FILE_APPEND);

    if ($result === false) {
        wp_send_json_error('Falha ao escrever log');
    } else {
        wp_send_json_success('Log salvo');
    }
}

function remote_save_failed_item() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    // Tratamento para evitar o arquivo "failed_import_null.json"
    $raw_hash = isset($_POST['hash']) ? $_POST['hash'] : '';
    $hash = (!empty($raw_hash) && $raw_hash !== 'null' && $raw_hash !== 'undefined') ? sanitize_file_name($raw_hash) : 'general';
    
    $item_json = isset($_POST['item_json']) ? wp_unslash($_POST['item_json']) : '';

    if (empty($item_json)) {
        wp_send_json_error('Nenhum dado recebido');
    }

    $log_dir = rtrim(plugin_dir_path(__DIR__), '/') . '/logs';
    $json_file = $log_dir . '/failed_import_' . $hash . '.json';

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $new_item = json_decode($item_json, true);
    if (!$new_item) {
        wp_send_json_error('JSON inválido');
    }

    $current_list = [];
    if (file_exists($json_file)) {
        $content = file_get_contents($json_file);
        $current_list = json_decode($content, true) ?: [];
    }

    // Trava de duplicidade
    $exists = false;
    foreach ($current_list as $existing_item) {
        if (($existing_item['permalink'] ?? '') === ($new_item['permalink'] ?? '') && 
            ($existing_item['title'] ?? '') === ($new_item['title'] ?? '')) {
            $exists = true;
            break;
        }
    }

    if ($exists) {
        wp_send_json_success('Item já existe');
        return;
    }

    $current_list[] = $new_item;
    file_put_contents($json_file, json_encode($current_list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    wp_send_json_success('Item adicionado');
}