<?php
if (!defined('ABSPATH')) exit;

$required_keys = ["title", "permalink", "date", "excerpt", "thumbnail", "content"];
?>
<link rel="stylesheet" href="<?= plugin_dir_url(dirname(__FILE__)) . 'styles/style.css?cb=' . time(); ?>">

<div class="wrap">
    <h1>Importador de postagens</h1>
    <p>Bem-vindo à página de importações!</p>
</div>

<div id="main-importer">
  <details style="display: block; margin:1rem 0;">
    <summary>
        <h3 style="display: inline-block;">Instruções de importação</h3>
    </summary>
    Certifique-se que seu JSON seja um ARRAY de objetos, onde cada objeto representa uma postagem com as seguintes chaves obrigatórias:
    <br>
    <?= implode(', ', $required_keys); ?>
  </details>

    <form id="uploadJson">
        <div>
            <input type="file" id="fileInput" accept="application/json,.json" class="wp-core-ui" required>
            <div>
              <label for="category_slug">Nome da categoria:</label>
              <input type="text" id="category_slug" placeholder="Nome da categoria para os posts importados" class="wp-core-ui" value="" style="width:200px; margin-left:12px; margin-top: 10px; margin-bottom: 10px;">
            </div>
            <div>
              <label for="ms">Delay de importação (em ms):</label>
              <input type="number" id="ms" placeholder="Delay de importação entre posts (ms)" class="wp-core-ui" value="2000" style="width:150px; margin-left:12px; margin-top: 10px; margin-bottom: 10px;" required>
            </div>
            <div>
              <label for="preview">Prévia</label>
              <input type="checkbox" id="preview" class="wp-core-ui" style="margin-left:12px; margin-top: 10px; margin-bottom: 10px;">
              <!-- <label for="msMidia">Delay de download de mídia (em ms):</label>
              <input type="number" id="msMidia" placeholder="Delay de download de mídia entre posts (ms)" class="wp-core-ui" value="2000" style="width:150px; margin-left:12px; margin-top: 10px; margin-bottom: 10px;" required> -->
            </div>
            <button type="submit" class="wp-core-ui button button-primary">Fazer upload</button>
        </div>


        <div id="progress-wrapper">
            <div id="progressBar" class="hide">
                0%
            </div>
        </div>

        <div id="result" style="margin-top:12px;"></div>
    </form>

    <div id="fileLog">

    </div>

    <div id="imported"></div>
</div>

<script>
window.wp_vars = {
  ajaxUrl: <?php echo json_encode(admin_url('admin-ajax.php')); ?>,
  nonce: <?php echo json_encode(wp_create_nonce('imp_handle_json_upload')); ?>,
  restUrl: <?php echo wp_json_encode(home_url('/wp-json/')); ?>,
  restNonce: <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>
};

async function loadJsonFile() {
    const formData = new FormData();
    formData.append('action', 'imp_read_json');
    formData.append('_ajax_nonce', wp_vars.nonce);

    const data = await fetch(wp_vars.ajaxUrl, {
        method: 'POST',
        body: formData
    });

    const newData = await data.clone().json();

    const evt = new CustomEvent('Imported', {
        detail: { 
          response:  newData,
          wp_vars: window.wp_vars,
          requiredKeys: <?= json_encode($required_keys); ?>,
          ms: document.getElementById('ms').value
        }
    });
    document.dispatchEvent(evt);
    
    return data.json();
}

const form = document.getElementById('uploadJson');
const fileInput = document.getElementById('fileInput');
const progressBar = document.getElementById('progressBar');
const result = document.getElementById('result');

function checkIsJson(file) {
  if (!file) return false;
  if (file.type !== 'application/json' && !file.name.toLowerCase().endsWith('.json')) return false;
  return true;
}

// (Opcional) log remoto
function send_log_to_php(text) {
  const logData = new FormData();
  logData.append('action', 'remote_save_log');
  logData.append('message', String(text).slice(0, 5000));
  fetch(wp_vars.ajaxUrl, { method: 'POST', body: logData });
}

form.addEventListener('submit', function(e) {
  e.preventDefault();

  const file = fileInput.files[0];
  if (!checkIsJson(file)) {
    alert('Selecione um arquivo JSON válido.');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'imp_handle_json_upload');
  formData.append('file', file);
  formData.append('_ajax_nonce', wp_vars.nonce);

  result.textContent = '';
  progressBar.style.width = '0%';
  progressBar.textContent = '0%';

  const xhr = new XMLHttpRequest();
  xhr.open('POST', wp_vars.ajaxUrl, true);

  xhr.upload.onprogress = function(ev) {
    if (!ev.lengthComputable) return;
    const percent = Math.round((ev.loaded / ev.total) * 100);
    progressBar.classList.toggle('hide', false);
    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';
  };

  xhr.onload = function() {
    const raw = xhr.responseText || '';
    console.log('AJAX STATUS:', xhr.status);
    console.log('AJAX RAW (first 500):', raw.slice(0, 500));

    if (xhr.status !== 200) {
      result.textContent = 'Erro do servidor (' + xhr.status + '). Veja console/debug.log.';
      send_log_to_php('UPLOAD STATUS=' + xhr.status + ' RAW=' + raw.slice(0, 500));
      return;
    }

    let data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      result.textContent = 'Resposta inválida (não JSON). Veja console/debug.log.';
      send_log_to_php('NON_JSON_RESPONSE: ' + raw.slice(0, 500));
      return;
    }

    if (!data.success) {
      const msg = (data.data && data.data.message) ? data.data.message : 'Falha no upload.';
      result.textContent = msg;
      send_log_to_php('WP_JSON_ERROR: ' + msg);
      return;
    }

    result.textContent = 'Upload concluído. Arquivo salvo em: ' + data.data.url;
    loadJsonFile();

  };

  xhr.onerror = function() {
    result.textContent = 'Falha de conexão (network error).';
    send_log_to_php('NETWORK_ERROR');
  };

  xhr.send(formData);
});
</script>

<script src="<?php echo plugins_url('js/readInfo.js', __FILE__); ?>?cb=<?= time(); ?>"></script>
