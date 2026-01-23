<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_remote_save_log', 'remote_save_log');

function remote_save_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('forbidden', 403);
    }

    $msg = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
    $msg = substr($msg, 0, 5000);

    // Define o caminho absoluto para a pasta logs dentro do seu plugin
    // __DIR__ pega a pasta atual onde este arquivo PHP está.
    $log_dir = plugin_dir_path(__DIR__) . '/logs';
    $log_file = $log_dir . '/importer';
    
    if(isset($_POST['log_type']) && !empty($_POST['log_type'])) {
        $log_type = sanitize_file_name($_POST['log_type']);
        $log_file .= '_' . $log_type;
    }

    if(isset($_POST['hash']) && !empty($_POST['hash'])) {
        $hash = sanitize_file_name($_POST['hash']);
        $log_file .= '_' . $hash;
    }

    $log_file .= '.log';
    
    // 1. Se a pasta logs não existir, tenta criar com permissão total
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // 2. Tenta salvar o conteúdo
    // O sinalizador FILE_APPEND cria o arquivo se ele não existir
    $result = file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] JS_LOG: ' . $msg . PHP_EOL, FILE_APPEND);

    if ($result === false) {
        // Se falhar, avisa o JS qual o erro real
        wp_send_json_error('Falha ao escrever no arquivo. Verifique permissões da pasta logs/ dir:'.$log_dir);
    } else {
        wp_send_json_success('Log salvo');
    }
}