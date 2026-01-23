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
