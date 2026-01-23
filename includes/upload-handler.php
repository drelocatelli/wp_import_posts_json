<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_imp_handle_json_upload', 'imp_handle_json_upload');
// Remova o nopriv se quiser só admin
// add_action('wp_ajax_nopriv_imp_handle_json_upload', 'imp_handle_json_upload');

function imp_handle_json_upload()
{
    // (Opcional) restringir ao admin
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Sem permissão.'], 403);
    }

    // Nonce
    check_ajax_referer('imp_handle_json_upload', 'nonce');

    if (empty($_FILES['file'])) {
        wp_send_json_error(['message' => 'Nenhum arquivo recebido.'], 400);
    }

    $file = $_FILES['file'];

    if (!empty($file['error'])) {
        wp_send_json_error(['message' => 'Erro no upload: ' . $file['error']], 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'json') {
        wp_send_json_error(['message' => 'Apenas arquivos JSON são permitidos.'], 400);
    }

    // Valida JSON
    $contents = file_get_contents($file['tmp_name']);
    json_decode($contents);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'JSON inválido.'], 400);
    }

    // Pasta /storage na raiz do plugin
    $plugin_dir = plugin_dir_path(dirname(__FILE__)); // .../seu-plugin/
    $dir = $plugin_dir . 'storage/';

    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            wp_send_json_error(['message' => 'Não foi possível criar a pasta storage.'], 500);
        }
    }

    $safe_name = 'merged.json';
    $dest = $dir . $safe_name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        wp_send_json_error(['message' => 'Falha ao salvar o arquivo.'], 500);
    }

    // URL correta
    $base_url = plugin_dir_url(dirname(__FILE__)); // .../seu-plugin/
    $url = $base_url . 'storage/' . $safe_name;

    wp_send_json_success(['url' => $url]);
}


add_action('wp_ajax_imp_read_json', 'imp_read_json');

function imp_read_json() {
    check_ajax_referer('imp_handle_json_upload', 'nonce');

    $file = IMP_PLUGIN_DIR . 'storage/merged.json';

    if (!file_exists($file)) {
        wp_send_json_error(['message' => 'Arquivo não encontrado.']);
    }

    $content = file_get_contents($file);
    $json = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'JSON inválido.']);
    }

    wp_send_json_success($json);
}


require_once IMP_PLUGIN_DIR . 'includes/media-downloader.php';
