<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Salvar configurações
if ( isset($_POST['imp_save_config']) ) {
    check_admin_referer('imp_config_nonce');

    update_option('imp_debug_mode', isset($_POST['imp_debug_mode']) ? 1 : 0);
    update_option('imp_auto_import', sanitize_text_field($_POST['imp_auto_import']));

    echo '<div class="updated"><p>Configurações salvas com sucesso.</p></div>';
}

$debug_mode  = get_option('imp_debug_mode', 0);
$auto_import = get_option('imp_auto_import', 'manual');
?>

<div class="wrap">
    <h1>Configurações do Importador</h1>

    <form method="post">
        <?php wp_nonce_field('imp_config_nonce'); ?>

        <table class="form-table">

            <tr>
                <th scope="row">Modo Debug</th>
                <td>
                    <label>
                        <input type="checkbox" name="imp_debug_mode" value="1" <?php checked($debug_mode, 1); ?>>
                        Ativar logs detalhados
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">Modo de Importação</th>
                <td>
                    <select name="imp_auto_import">
                        <option value="manual" <?php selected($auto_import, 'manual'); ?>>Manual</option>
                        <option value="auto" <?php selected($auto_import, 'auto'); ?>>Automático (cron)</option>
                    </select>
                </td>
            </tr>

        </table>

        <?php submit_button('Salvar Configurações', 'primary', 'imp_save_config'); ?>
    </form>
</div>
