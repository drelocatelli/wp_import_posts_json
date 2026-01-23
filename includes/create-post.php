<?php
if (!defined('ABSPATH')) exit;

function imp_get_or_create_category($slug, $name = '') {
  $slug = sanitize_title($slug);

  $term = get_term_by('slug', $slug, 'category');
  if ($term && !is_wp_error($term)) {
    return (int) $term->term_id;
  }

  if (!$name) {
    $name = ucwords(str_replace('-', ' ', $slug));
  }

  $created = wp_insert_term($name, 'category', ['slug' => $slug]);
  if (is_wp_error($created)) {
    return $created;
  }

  return (int) $created['term_id'];
}

function prepare_category($slug) {
  $slug = sanitize_title($slug);
  $imported_id = imp_get_or_create_category(
    $slug,
    ucwords(str_replace('-', ' ', $slug))
  );

  if (is_wp_error($imported_id)) {
    error_log('Erro ao criar categoria: ' . $imported_id->get_error_message());
    return;
  }

  return (int) $imported_id;
}

function imp_prepare_post_date($date) {
  if (!$date) return null;

  // aceita YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date .= ' 00:00:00';
  }

  // valida formato final
  if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
    return null;
  }

  return $date;
}


add_action('wp_ajax_imp_create_post', function () {
  check_ajax_referer('imp_handle_json_upload', 'nonce');

  if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => 'Sem permissão.'], 403);
  }

  $title   = sanitize_text_field($_POST['title'] ?? '');
  $content = wp_kses_post($_POST['content'] ?? '');
  $status  = sanitize_key($_POST['status'] ?? 'draft');

  $cat_ids = [];
  $default = prepare_category('imp');
  if ($default) $cat_ids[] = $default;

  // hash
  
  if (isset($_POST['hash']) && !empty($_POST['hash'])) {
    $hash_param = 'imp_'.sanitize_text_field($_POST['hash']);
    $hash_cat = prepare_category($hash_param);
    if ($hash_cat) $cat_ids[] = $hash_cat;
  }

  if (isset($_POST['category_slug']) && !empty($_POST['category_slug'])) {
    $extra = prepare_category($_POST['category_slug']);
    if ($extra) $cat_ids[] = $extra;
  }
  

  // remove duplicadas e zeros
  $cat_ids = array_values(array_unique(array_filter(array_map('intval', $cat_ids))));

  $raw_date = $_POST['date'] ?? '';
  $post_date = imp_prepare_post_date($raw_date);

  $post_id = wp_insert_post([
    'post_title'   => $title,
    'post_content' => $content,
    'post_status'  => $status,
    'post_type'    => 'post',
    'post_category' => $cat_ids,
    'post_date'    => $post_date,
    'post_date_gmt' => get_gmt_from_date($post_date),
    'edit_date' => true
  ], true);

  if (is_wp_error($post_id)) {
    wp_send_json_error(['message' => $post_id->get_error_message()], 500);
  }

  if (!empty($_POST['thumbnail_id'])) {
    $thumb_id = (int) $_POST['thumbnail_id'];
    if ($thumb_id > 0) {
      set_post_thumbnail($post_id, $thumb_id);
    }
  }

  wp_send_json_success(['post_id' => $post_id]);
});
