jQuery(function ($) {
  const categoryMap = window.SCG_CATEGORY_MAP || {};
  const mainSelect = $('#scg-main-category');
  const subSelect = $('#scg-sub-category');
  const fileInput = $('#scg-photo-files');
  const dropzone = $('#scg-dropzone');
  const selectedFiles = $('#scg-selected-files');
  const form = $('#scg-photo-upload-form');
  const message = $('#scg-upload-message');

  let currentFiles = [];

  mainSelect.on('change', function () {
    const parentId = String($(this).val());
    subSelect.html('');

    if (!parentId || !categoryMap[parentId]) {
      subSelect.append('<option value="">メインカテゴリを選択してください</option>');
      return;
    }

    const children = categoryMap[parentId].children || [];

    if (!children.length) {
      subSelect.append('<option value="">サブカテゴリがありません</option>');
      return;
    }

    subSelect.append('<option value="">選択してください</option>');
    children.forEach(function (child) {
      subSelect.append(`<option value="${child.id}">${escapeHtml(child.name)}</option>`);
    });
  });

  dropzone.on('dragover', function (e) {
    e.preventDefault();
    dropzone.addClass('is-dragover');
  });

  dropzone.on('dragleave drop', function () {
    dropzone.removeClass('is-dragover');
  });

  dropzone.on('drop', function (e) {
    e.preventDefault();
    const files = Array.from(e.originalEvent.dataTransfer.files || []);
    setFiles(files);
  });

  fileInput.on('change', function () {
    setFiles(Array.from(this.files || []));
  });

  function setFiles(files) {
    const max = Number(SCG_UPLOAD.max_files || 10);
    const validFiles = files.filter(file => file.type.match(/^image\/(jpeg|png|webp)$/));

    const descriptions = [];
    selectedFiles.find('.scg-selected-file').each(function () {
      const index = Number($(this).data('index'));
      descriptions[index] = $(this).find('textarea').val() || '';
    });

    currentFiles = currentFiles.map(function (file, index) {
      file._scgDescription = descriptions[index] || file._scgDescription || '';
      return file;
    });

    const remainingSlots = max - currentFiles.length;

    if (remainingSlots <= 0) {
      setMessage('画像は最大' + max + '枚までです', true);
      renderSelectedFiles();
      return;
    }

    validFiles.slice(0, remainingSlots).forEach(function (file) {
      file._scgDescription = '';
      currentFiles.push(file);
    });

    if (validFiles.length > remainingSlots) {
      setMessage('最大' + max + '枚まで追加しました。超過分は除外しました', true);
    }

    renderSelectedFiles();
  }

  function renderSelectedFiles() {
    selectedFiles.html('');

    if (!currentFiles.length) {
      return;
    }

    currentFiles.forEach(function (file, index) {
      const url = URL.createObjectURL(file);
      const description = file._scgDescription || '';

      selectedFiles.append(`
        <div class="scg-selected-file" data-index="${index}">
          <div class="scg-selected-thumb">
            <img src="${url}" alt="">
          </div>
          <div class="scg-selected-meta">
            <div class="scg-selected-topline">
              <div class="scg-selected-name">${escapeHtml(file.name)}</div>
              <button type="button" class="button-link-delete scg-cancel-upload-file" data-index="${index}">この画像をキャンセル</button>
            </div>
            <label>説明文（任意）</label>
            <textarea name="descriptions[]" rows="3" placeholder="画像プレビュー時に表示する説明文">${escapeHtml(description)}</textarea>
          </div>
        </div>
      `);
    });
  }

  selectedFiles.on('input', 'textarea', function () {
    const index = Number($(this).closest('.scg-selected-file').data('index'));
    if (currentFiles[index]) {
      currentFiles[index]._scgDescription = $(this).val();
    }
  });

  selectedFiles.on('click', '.scg-cancel-upload-file', function () {
    const index = Number($(this).data('index'));
    if (currentFiles[index]) {
      currentFiles.splice(index, 1);
      renderSelectedFiles();
      if (!currentFiles.length) {
        fileInput.val('');
      }
      setMessage('アップロード待機から削除しました', false);
    }
  });

  form.on('submit' , function (e) {
    e.preventDefault();

    if (!mainSelect.val() || !subSelect.val()) {
      setMessage('カテゴリを選択してください', true);
      return;
    }

    if (!currentFiles.length) {
      setMessage('画像を選択してください', true);
      return;
    }

    const formData = new FormData();
    formData.append('action', 'scg_upload_photos');
    formData.append('nonce', SCG_UPLOAD.nonce);
    formData.append('main_category', mainSelect.val());
    formData.append('sub_category', subSelect.val());

    currentFiles.forEach(function (file) {
      formData.append('photos[]', file);
    });

    currentFiles.forEach(function (file, index) {
      formData.append('descriptions[]', file._scgDescription || '');
    });

    setMessage('アップロード中...', false);
    form.find('button[type="submit"]').prop('disabled', true);

    $.ajax({
      url: SCG_UPLOAD.ajax_url,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
    }).done(function (response) {
      if (response.success) {
        setMessage(response.data.message || 'アップロードしました', false);
        currentFiles = [];
        selectedFiles.html('');
        fileInput.val('');
      } else {
        setMessage((response.data && response.data.message) || 'アップロードに失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    }).always(function () {
      form.find('button[type="submit"]').prop('disabled', false);
    });
  });

  function setMessage(text, isError) {
    message.removeClass('is-error is-success').addClass(isError ? 'is-error' : 'is-success').text(text || '');
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
});
