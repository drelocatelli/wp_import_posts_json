document.addEventListener('FileUploaded', async (e) => {
  const { response, wp_vars, requiredKeys, ms } = e.detail;

  const { data } = response;

  console.log({ data, wp_vars, requiredKeys });

  const allKeysPresent = requiredKeys.every((key) => {
    return data.every((item) => key in item);
  });

  if (!allKeysPresent) {
    alert('Nem todos os objetos no JSON possuem as chaves obrigatórias: ' + requiredKeys.join(', '));
    return;
  }

  const hash = () => Math.random().toString(36).substring(2, 10) + Math.random().toString(36).substring(2, 10);
  const hashValue = hash();

  const hashEl = document.getElementById('hash');
  if (hashEl) {
    hashEl.innerHTML = `Categoria Hash da importação: <strong>imp_${hashValue}</strong>`;
  }

  for (let index = 0; index < data.length; index++) {
    let item = data[index];

    if (!item || typeof item !== 'object') {
      console.warn('Item inválido no índice', index, item);
      // await send_log_to_php(wp_vars, `Item inválido no índice ${index}`);
      continue;
    }
    // send event
    const evt = new CustomEvent('ImportStarted', {
      detail: { item, index, wpVars: wp_vars, hashValue },
    });
    document.dispatchEvent(evt);
    // await send_log_to_php(wp_vars, `Post importado: ${index} - ${item.title}`);
    await wait(ms);
  }
});

async function send_log_to_php(wp_vars, text) {
  const logData = new FormData();
  logData.append('action', 'remote_save_log');
  logData.append('message', String(text).slice(0, 5000));
  await fetch(wp_vars.ajaxUrl, { method: 'POST', body: logData });
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function downloadImageFirst(thumbnailUrl, wpVars) {
  const fd = new FormData();
  fd.append('action', 'imp_download_image');
  fd.append('nonce', wpVars.nonce);
  fd.append('thumbnail_url', thumbnailUrl);

  const res = await fetch(wpVars.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: fd,
  });

  const text = await res.text();
  let json;
  try { json = JSON.parse(text); }
  catch { throw new Error(`Resposta não-JSON: ${text.slice(0, 200)}`); }

  if (!json.success) throw new Error(json.data?.message || 'Falha ao baixar imagem');
  return json.data; // { attach_id, url, cached }
}

function extractMediaUrlsFromContent(content) {
  const mediaUrls = [];
  
  // Criamos um elemento temporário para o jQuery ler o HTML
  const $tempDiv = $('<div>').html(content);

  // 1. Pega SRC de imagens
  $tempDiv.find('img').each(function() {
    mediaUrls.push($(this).attr('src'));
  });

  // 2. Pega SRC de vídeos
  $tempDiv.find('video, source').each(function() {
    mediaUrls.push($(this).attr('src'));
  });

  // 3. Pega HREF de links que terminam em extensões de documento
  const extensoes = /\.(pdf|docx|xlsx|csv|pptx)$/i;
  $tempDiv.find('a').each(function() {
    const link = $(this).attr('href');
    if (link && extensoes.test(link)) {
      mediaUrls.push(link);
    }
  });

  // Remove valores nulos e duplicados
  return [...new Set(mediaUrls.filter(url => url))];
}

// function replaceMediaUrls(content, newUrl) {
//   const regex = /https?:\/\/[^\s]+/g;
//   return content.replace(regex, newUrl);
// }

function replaceMediaUrls(content, oldUrl, newUrl) {
  const $tempDiv = $('<div>').html(content);

  // Troca em Imagens
  $tempDiv.find(`img[src="${oldUrl}"]`).attr('src', newUrl).removeAttr('srcset').removeAttr('sizes');

  // Troca em Vídeos/Sources
  $tempDiv.find(`video[src="${oldUrl}"], source[src="${oldUrl}"]`).attr('src', newUrl);

  // Troca em Links (Documentos)
  $tempDiv.find(`a[href="${oldUrl}"]`).attr('href', newUrl);

  return $tempDiv.html();
}

async function downloadMediaFirst(mediaUrl, wpVars) {
  const fd = new FormData();
  fd.append('action', 'imp_download_media');
  fd.append('nonce', wpVars.nonce);
  fd.append('media_url', mediaUrl);

  const res = await fetch(wpVars.ajaxUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: fd,
  });

  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`Resposta não-JSON: ${text.slice(0, 200)}`);
  }

  if (!json.success) throw new Error(json.data?.message || 'Falha ao baixar mídia');
  return json.data; // { attach_id, url, cached }
}


async function importPosts({ item, wpVars, index, hashValue }) {
  const importedEl = document.getElementById('imported');

  try {
    const formData = new FormData();

    let thumbnailId = null;

    if(!document.querySelector('#preview').checked && item.thumbnail) {
      const img = await downloadImageFirst(item.thumbnail, wpVars);
      thumbnailId = img.attach_id;
      formData.append('thumbnail_id', String(thumbnailId));
    }

    // Baixar outros tipos de mídia (PDF, DOCX, etc.) do conteúdo
    const mediaUrls = extractMediaUrlsFromContent(item.content); // Função para extrair URLs

    for (let mediaUrl of mediaUrls) {
      try {
        console.log(`Baixando mídia para o post ${index}: ${mediaUrl}`);
        const mediaDelayEl = document.getElementById('msMidia');
        if(mediaDelayEl && mediaDelayEl.value.trim() !== '') {
          const delayTime = Number(mediaDelayEl.value);
          if(!isNaN(delayTime) && delayTime > 0) {
            await wait(mediaDelayEl.value);
          }
        }
        
        const media = await downloadMediaFirst(mediaUrl, wpVars); // Baixa documentos e outros tipos de mídia
        item.content = replaceMediaUrls(item.content, mediaUrl, media.url);

      } catch (err) {
        console.error(`Erro ao baixar mídia para o post ${index}: ${err.message}`);
      }
    }

    formData.append('action', 'imp_create_post');
    formData.append('nonce', wpVars.nonce);

    const data = {
      title: item.title,
      permalink: item.permalink,
      date: item.date,
      excerpt: item.excerpt,
      thumbnail: item.thumbnail,
      content: item.content,
    };

    const categoryElement = document.querySelector('#category_slug');
    if(categoryElement && categoryElement.value.trim() !== '') {
      data.category_slug = categoryElement.value;
      formData.append('category_slug', data.category_slug);
    }
    
    formData.append('status', 'publish');
    formData.append('title', data.title);
    formData.append('content', data.content);
    formData.append('excerpt', data.excerpt);
    formData.append('date', data.date);
    formData.append('excerpt', data.excerpt);
    formData.append('hash', hashValue);

    console.log('Sending data: ', Object.fromEntries(formData.entries()));

    if(document.querySelector('#preview').checked) {
      return;
    }

    
    const res = await fetch(wpVars.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData,
    });

    const response = await res.json();

    if (!response.success) {
      console.error('Erro ao importar post:', response.data);
      alert(`Erro ao importar post: ${response.data}`);
    }

    const pOk = document.createElement('p');
    pOk.classList.add('import-element');
    pOk.classList.add('imported-success');
    pOk.textContent = `Post importado com sucesso: ${index} - ${item.title}`;
    importedEl.appendChild(pOk);

    return response;

  } catch (err) {
    console.error('Erro ao importar post:', err);
    const pError = document.createElement('p');
    pError.classList.add('import-element');
    pError.classList.add('imported-error');
    pError.textContent = `Erro ao importar post: ${index} - ${item.title} - ${err.message}`;
    importedEl.appendChild(pError);
  }
}

document.addEventListener('ImportStarted', async (e) => {
  const importedEl = document.getElementById('imported');

  const { item, index, wpVars, hashValue } = e.detail;
  const p = document.createElement('p');
  p.classList.add('import-element');
  p.classList.add('imported-started');
  p.textContent = `Importando post: ${index} - ${item.title}`;
  importedEl.appendChild(p);
  await importPosts({ item, index, wpVars, hashValue });
});
