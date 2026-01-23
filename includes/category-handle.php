<?php
// Evita acesso direto ao arquivo
if (!defined('ABSPATH')) exit;

function processar_limpeza_de_categorias_importadas() {
    // Só executa se os parâmetros estiverem na URL e o usuário for admin
    if (isset($_GET['selected_cat']) && !empty($_GET['selected_cat']) && is_admin()) {
        
        $cat_id = intval($_GET['selected_cat']);

        // 1. Deletar posts
        if (isset($_GET['del_posts']) && $_GET['del_posts'] == 'on') {
            $posts = get_posts([
                'category'       => $cat_id,
                'posts_per_page' => -1,
                'post_type'      => 'post',
                'fields'         => 'ids'
            ]);
            
            if (!empty($posts)) {
                foreach ($posts as $post_id) {
                    wp_delete_post($post_id, true);
                }
            }
        }

        // 2. Deletar categoria
        if (isset($_GET['del_cat']) && $_GET['del_cat'] == 'on') {
            wp_delete_term($cat_id, 'category');
        }

        // 3. Redirecionar de volta com segurança
        $status_atual = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'all';
        $redirect_url = admin_url('admin.php?page=imp-importador&edit_categories=true&tab=true&post_status=' . $status_atual . '&deleted=true');

        wp_redirect($redirect_url);
        exit;
    }
}

// O Hook 'admin_init' garante que o WP já carregou todas as funções necessárias
add_action('admin_init', 'processar_limpeza_de_categorias_importadas');