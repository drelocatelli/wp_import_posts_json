<?php
if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_imp_download_image', 'imp_download_image');

function imp_download_image()
{
    check_ajax_referer('imp_handle_json_upload', 'nonce');

    if (! current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Sem permissão para upload.'], 403);
    }

    $url = esc_url_raw($_POST['thumbnail_url'] ?? '');
    if (! $url) {
        wp_send_json_error(['message' => 'thumbnail_url ausente.'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $year  = date_i18n('Y');
    $month = date_i18n('m');

    $upload_dir = wp_upload_dir();
    $import_dir = trailingslashit($upload_dir['basedir']) . "imported/{$year}/{$month}";
    $import_url = trailingslashit($upload_dir['baseurl']) . "imported/{$year}/{$month}";
    
    if (!wp_mkdir_p($import_dir)) {
        wp_send_json_error(['message' => 'Não foi possível criar: ' . $import_dir], 500);
    }

    // cache simples por URL (evita baixar duas vezes)
    $hash = md5($year . $month . '|' . $url);

    $existing = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => 1,
        'post_status'    => 'inherit',
        'meta_key'       => '_imp_source_url_hash',
        'meta_value'     => $hash,
        'fields'         => 'ids',
    ]);

    if (! empty($existing[0])) {
        $attach_id = (int) $existing[0];
        wp_send_json_success([
            'attach_id' => $attach_id,
            'url'       => wp_get_attachment_url($attach_id),
            'cached'    => true,
        ]);
    }

    $tmp_file = download_url($url, 30);
    if (is_wp_error($tmp_file)) {
        wp_send_json_error(['message' => 'Falha ao baixar: ' . $tmp_file->get_error_message()], 502);
    }

    $file_name = wp_basename(parse_url($url, PHP_URL_PATH)) ?: ('imported-' . $hash . '.jpg');
    $file_name = sanitize_file_name($file_name);

    $dest_name = wp_unique_filename($import_dir, $file_name);
    $dest_full = trailingslashit($import_dir) . $dest_name;

    $moved = @rename($tmp_file, $dest_full);
    if (! $moved) {
        $moved = @copy($tmp_file, $dest_full);
        @unlink($tmp_file);
    }

    if (! $moved) {
        @unlink($tmp_file);
        wp_send_json_error(['message' => 'Falha ao mover arquivo para uploads/imported'], 500);
    }

    $filetype = wp_check_filetype($dest_full);

    $attachment = [
        'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
        'post_title'     => preg_replace('/\.[^.]+$/', '', $dest_name),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'guid'           => trailingslashit($import_url) . $dest_name,
    ];

    $attach_id = wp_insert_attachment($attachment, $dest_full, 0);
    if (is_wp_error($attach_id)) {
        wp_send_json_error(['message' => 'Falha ao criar attachment: ' . $attach_id->get_error_message()], 500);
    }

    $meta = wp_generate_attachment_metadata($attach_id, $dest_full);
    if (is_array($meta)) {
        wp_update_attachment_metadata($attach_id, $meta);
    }

    update_post_meta($attach_id, '_imp_source_url', $url);
    update_post_meta($attach_id, '_imp_source_url_hash', $hash);

    wp_send_json_success([
        'attach_id' => (int) $attach_id,
        'url'       => wp_get_attachment_url($attach_id),
        'cached'    => false,
    ]);
}
