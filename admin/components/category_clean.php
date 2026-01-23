<?php

$post_status = isset($_GET['post_status']) ? $_GET['post_status'] : '';

$admin_url = admin_url('admin.php');

$cat_args = [
    'hide_empty' => false,           
    'fields'     => 'all',
    'name__like' => 'imp'
];

switch($post_status) {
    case 'all':
    case 'empty': 
        $cat_args['hide_empty'] = false;
    break;
    case 'not_empty':
        $cat_args['hide_empty'] = true;
    break;
}

$import_categories = get_terms('category', $cat_args);

if($post_status === 'empty') {
    $import_categories = array_filter($import_categories, function($category) {
        return $category->count === 0;
    });
}
?>

<form method="GET" action="<?= esc_url($admin_url); ?>" class="section">
    <div>
        <strong>Filtrar categorias de importação:</strong>
    </div>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <input type="hidden" name="page" value="imp-importador">
        <input type="hidden" name="edit_categories" value="true">
        <input type="hidden" name="tab" value="true"> 

        <div>
            <input type="radio" id="f_all" name="post_status" value="all" <?php checked($post_status, 'all'); ?>>
            <label for="f_all">Ver Todas</label>
        </div>

        <div>
            <input type="radio" id="f_empty" name="post_status" value="empty" <?php checked($post_status, 'empty'); ?>>
            <label for="f_empty">Sem posts</label>
        </div>
    
        <div>
            <input type="radio" id="f_not_empty" name="post_status" value="not_empty" <?php checked($post_status, 'not_empty'); ?>>
            <label for="f_not_empty">Com posts</label>
        </div>
        
        <button type="submit" class="wp-core-ui button button-secondary">Aplicar Filtro</button>
    </div>
</form>

<br>

<?php if(!empty($import_categories) && !empty($post_status)): ?>
    <div class="section">
        <form method="GET" action="<?= admin_url('edit.php'); ?>">
            <div>
                <div style="margin: 4px 0;">
                    <label for="filter_categories"><strong>Selecione a importação para gerenciar:</strong></label>
                </div>
                <select name="cat" id="filter_categories" style="min-width: 200px;" onchange="selectCategory(this.value);">
                    <option value="">Selecione...</option>
                    <?php 
                        foreach($import_categories as $category): 
                    ?>
                        <option value="<?php echo $category->term_id; ?>">
                            <?php echo esc_html($category->name) . ' (' . $category->count . ')'; ?>
                        </option>
                    <?php 
                        endforeach; 
                    ?>
                </select>
                
                <input type="hidden" name="post_type" value="post">
                <button type="submit" class="wp-core-ui button button-primary">Ver Posts desta Categoria</button>
            </div>
        </form>
        <form method="GET" action="<?= esc_url($admin_url); ?>" id="actions" style="display:none;">
            <input type="hidden" name="page" value="imp-importador">
            <input type="hidden" name="edit_categories" value="true">
            <input type="hidden" name="tab" value="true">
            <input type="hidden" name="post_status" value="<?= $post_status ?>">
            <input type="hidden" name="selected_cat" value="<?= $_GET['selected_cat'] ?>">

            <div style="margin:10px 0;">
                <strong style="display: block; margin: 10px 0;">Ações:</strong>
                <div style="display: flex; gap: 1rem;">
                    <div  style="display: flex; align-items: center;">
                        <input type="checkbox" name="del_posts" id="del_posts">
                        <label for="del_posts">Apagar posts</label>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <input type="checkbox" name="del_cat" id="del_cat">
                        <label for="del_cat">Apagar categoria</label>
                    </div>
                </div>
                <button style="margin-top: 1rem;" type="submit" class="wp-core-ui button button-primary">Executar ações</button>
            </div>
        </form>
    </div>
<?php elseif(!empty($post_status)): ?>
    <p>Nenhuma categoria encontrada para este filtro.</p>
<?php endif; ?>

<style>
    .section {
        display: flex; flex-direction: column; gap: 1rem; background: #fff; padding: 15px; border: 1px solid #ccd0d4;
    }
</style>

<script>
    function selectCategory(value) {
        const url = new URL(window.location.href);
        const selectedCatEl = document.querySelector('form input[type=hidden][name=selected_cat]');

        if(value.trim().length == 0) {
            url.searchParams.delete('selected_cat');
            selectedCatEl.value = '';
            document.querySelector('form#actions').style.display = 'none';
            window.history.pushState({}, '', url.toString());

            return;    
        }

        document.querySelector('form#actions').style.display = 'block';
        
        url.searchParams.set('selected_cat', value);
        window.history.pushState({}, '', url.toString());

        selectedCatEl.value = value;
    }
</script>