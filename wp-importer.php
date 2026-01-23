<?php
/**
 * Plugin Name: WP Importer
 * Description: Importador via upload JSON (AJAX).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

define('IMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMP_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once IMP_PLUGIN_DIR . 'includes/upload-handler.php';
require_once IMP_PLUGIN_DIR . 'includes/remote-log.php';
require_once IMP_PLUGIN_DIR . 'includes/create-post.php';
require_once IMP_PLUGIN_DIR . 'includes/category-handle.php';

add_action('admin_menu', function () {

    add_menu_page(
        'Importador',
        'Importar',
        'manage_options',
        'imp-importador',
        'imp_render_import_page',
        'dashicons-upload',
        6
    );

    add_submenu_page(
        'imp-importador',
        'Importar JSON',
        'Importar JSON',
        'manage_options',
        'imp-importador',
        'imp_render_import_page'
    );

    // add_submenu_page(
    //     'imp-importador',
    //     'Configurações',
    //     'Configurações',
    //     'manage_options',
    //     'imp-importador-config',
    //     'imp_render_config_page'
    // );
});

function imp_render_import_page() {
    require IMP_PLUGIN_DIR . 'admin/page-importer.php';
}

function imp_render_config_page() {
    require IMP_PLUGIN_DIR . 'pages/config.php';
}


// Registra o gancho para processar o formulário
add_action('admin_post_process_category_cleanup', function() {
    if (!current_user_can('manage_categories')) wp_die('Acesso negado');

    $cat_id = intval($_POST['selected_cat']);
    $del_posts = isset($_POST['del_posts']);
    $del_cat = isset($_POST['del_cat']);

    wp_importer_delete_category_data($cat_id, $del_posts, $del_cat);

    // Redireciona de volta
    $url = admin_url('admin.php?page=imp-importador&edit_categories=true&tab=true&post_status=' . $_POST['post_status'] . '&deleted=true');
    wp_redirect($url);
    exit;
});