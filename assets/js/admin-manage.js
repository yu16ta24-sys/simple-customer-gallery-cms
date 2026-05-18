jQuery(function ($) {
  const categoryMap = window.SCG_CATEGORY_MAP || {};
  const canPermanentDelete = !!window.SCG_CAN_PERMANENT_DELETE;

  const mainButtons = $('#scg-manage-main-buttons');
  const subButtons = $('#scg-manage-sub-buttons');
  const mainValue = $('#scg-manage-main-category');
  const subValue = $('#scg-manage-sub-category');
  const grid = $('#scg-photo-grid');
  const message = $('#scg-manage-message');
  const activeHelp = $('#scg-manage-help-active');
  const hiddenHelp = $('#scg-manage-help-hidden');
  const restoreToggle = $('#scg-restore-mode-toggle');

  const uploadToggle = $('#scg-inline-upload-toggle');
  const uploadArea = $('#scg-inline-upload-area');
  const dropzone = $('#scg-inline-dropzone');
  const fileInput = $('#scg-inline-photo-files');
  const selectedFiles = $('#scg-inline-selected-files');
  const uploadSubmit = $('#scg-inline-upload-submit');

  // UI状態は「active=通常」「hidden=復元モード」を軸に管理する。
  // upload_open は uploadArea の表示状態に集約し、フラグ乱立を避ける。
  let openedId = null;
  let isDragging = false;
  let currentItems = [];
  let currentStatus = 'active';
  let currentFiles = [];
  let scgMessageTimer = null;

  renderEmptyGuide('カテゴリを選択すると表示されます', '上のメインカテゴリーから選択すると、対応するサブカテゴリーと画像が表示されます。');

  mainButtons.on('click', '.scg-main-category-button', function () {
    const parentId = String($(this).data('id'));

    mainButtons.find('.scg-category-button').removeClass('is-active');
    $(this).addClass('is-active');

    mainValue.val(parentId);
    subValue.val('');
    openedId = null;
    currentItems = [];
    closeEditor();
    closeUploadArea();

    renderSubCategoryButtons(parentId);
    renderEmptyGuide('サブカテゴリーを選択してください', '下に表示されたサブカテゴリーを選ぶと画像が表示されます。');
  });

  subButtons.on('click', '.scg-sub-category-button', function () {
    const subId = String($(this).data('id'));

    subButtons.find('.scg-category-button').removeClass('is-active');
    $(this).addClass('is-active');

    subValue.val(subId);
    closeEditor();
    closeUploadArea();
    loadPhotos(subId);
  });

  restoreToggle.on('click', function () {
    const isRestoreMode = currentStatus === 'hidden';

    if (isRestoreMode) {
      currentStatus = 'active';
      restoreToggle.removeClass('is-restore-active').text('削除済み画像復元モード');
      hiddenHelp.hide();
      activeHelp.show();
      uploadToggle.prop('disabled', false).removeClass('is-disabled');
    } else {
      currentStatus = 'hidden';
      restoreToggle.addClass('is-restore-active').text('通常モードに戻る');
      activeHelp.hide();
      hiddenHelp.show();
      uploadToggle.prop('disabled', true).addClass('is-disabled');
      closeUploadArea();
    }

    closeEditor();

    if (subValue.val()) {
      loadPhotos(subValue.val());
    } else {
      const title = currentStatus === 'hidden' ? '復元したいカテゴリを選択してください' : 'カテゴリを選択すると表示されます';
      const desc = currentStatus === 'hidden'
        ? '削除済み画像を確認するには、メインカテゴリーとサブカテゴリーを選択してください。'
        : '上のメインカテゴリーから選択すると、対応するサブカテゴリーと画像が表示されます。';
      renderEmptyGuide(title, desc);
    }
  });

  uploadToggle.on('click', function () {
    if (currentStatus === 'hidden') {
      setMessage('復元モード中は写真を追加できません', true);
      return;
    }

    if (!subValue.val()) {
      setMessage('先にサブカテゴリーを選択してください', true);
      return;
    }

    closeEditor();

    if (uploadArea.is(':visible')) {
      closeUploadArea();
      return;
    }

    openUploadArea();
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
    setUploadFiles(files);
  });

  fileInput.on('change', function () {
    setUploadFiles(Array.from(this.files || []));
  });

  uploadSubmit.on('click', function () {
    uploadSelectedFiles();
  });

  function renderSubCategoryButtons(parentId) {
    subButtons.html('');

    if (!parentId || !categoryMap[parentId]) {
      subButtons.html('<span class="scg-category-placeholder">メインカテゴリーを選択してください</span>');
      return;
    }

    const children = categoryMap[parentId].children || [];

    if (!children.length) {
      subButtons.html('<span class="scg-category-placeholder">サブカテゴリーがありません</span>');
      return;
    }

    children.forEach(function (child) {
      subButtons.append(`
        <button type="button" class="scg-category-button scg-sub-category-button" data-id="${child.id}">
          ${formatCategoryLabel(child.name)}
        </button>
      `);
    });
  }

  function formatCategoryLabel(name) {
    const text = escapeHtml(name);
    const parts = text.split(/\s+/);

    if (parts.length >= 2) {
      const firstLine = parts.slice(0, Math.ceil(parts.length / 2)).join(' ');
      const secondLine = parts.slice(Math.ceil(parts.length / 2)).join(' ');
      return `${firstLine}<br><span>${secondLine}</span>`;
    }

    return text;
  }

  function loadPhotos(subCategoryId) {
    openedId = null;
    currentItems = [];
    grid.html('');

    if (!subCategoryId) {
      renderEmptyGuide('カテゴリを選択すると表示されます', '上のメインカテゴリーから選択すると、対応するサブカテゴリーと画像が表示されます。');
      return;
    }

    setMessage('読み込み中...', false);

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_get_photos',
      nonce: SCG_MANAGE.nonce,
      sub_category: subCategoryId,
      status: currentStatus
    }).done(function (response) {
      if (!response.success) {
        setMessage(response.data.message || '読み込みに失敗しました', true);
        return;
      }

      currentItems = response.data.items || [];
      renderPhotos(currentItems);
      setMessage('', false);
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  }

  function renderPhotos(items) {
    grid.html('');

    if (!items.length) {
      if (currentStatus === 'hidden') {
        renderEmptyGuide('削除済み画像はありません', 'このカテゴリーには復元できる削除済み画像がありません。');
      } else {
        renderEmptyGuide('このカテゴリーにはまだ写真がありません', '写真を追加すると、ここに一覧表示されます。');
      }
      return;
    }

    items.forEach(function (item) {
      const badge = currentStatus === 'hidden' ? '<div class="scg-photo-trash-badge">削除済み</div>' : '';
      grid.append(`
        <div class="scg-photo-item ${currentStatus === 'hidden' ? 'is-hidden-photo' : ''}" data-id="${item.id}">
          <div class="scg-photo-thumb">
            <img src="${item.thumb || ''}" alt="">
            ${badge}
            <div class="scg-photo-hover">${currentStatus === 'hidden' ? '復元' : '編集'}</div>
          </div>
        </div>
      `);
    });

    if (currentStatus === 'active') {
      grid.sortable({
        items: '.scg-photo-item',
        tolerance: 'pointer',
        cursor: 'grabbing',
        opacity: 0.96,
        revert: 260,
        forcePlaceholderSize: true,
        placeholder: 'scg-photo-sort-placeholder',
        helper: function (event, item) {
          const helper = item.clone();
          helper.addClass('scg-photo-sort-helper');
          helper.width(item.width());
          helper.height(item.height());
          return helper;
        },
        start: function (event, ui) {
          isDragging = true;
          closeEditor();
          closeUploadArea();
          ui.item.addClass('is-sorting');
          grid.addClass('is-sorting-grid');
        },
        sort: function (event, ui) {
          ui.placeholder.height(ui.item.height());
        },
        stop: function (event, ui) {
          ui.item.removeClass('is-sorting');
          grid.removeClass('is-sorting-grid');
          setTimeout(function () { isDragging = false; }, 160);
        },
        update: function () {
          syncCurrentItemsFromDom();
          saveOrder();
        }
      });
    } else {
      if (grid.data('ui-sortable')) {
        grid.sortable('destroy');
      }
    }
  }

  function renderEmptyGuide(title, description) {
    grid.html(`
      <div class="scg-empty-guide">
        <div class="scg-empty-guide-icon">□</div>
        <div class="scg-empty-guide-title">${escapeHtml(title)}</div>
        <div class="scg-empty-guide-desc">${escapeHtml(description)}</div>
      </div>
    `);
  }

  function setUploadFiles(files) {
    const max = Number(SCG_MANAGE.max_files || 10);
    const maxFileSize = Number(SCG_MANAGE.max_file_size || 0);
    const maxFileSizeLabel = SCG_MANAGE.max_file_size_label || 'サーバー上限';
    const oversizedFiles = maxFileSize > 0 ? files.filter(file => file.size > maxFileSize) : [];

    if (oversizedFiles.length) {
      setMessage('ファイルサイズが大きすぎます。' + maxFileSizeLabel + '以内の画像を選択してください。', true);
    }

    const validFiles = files.filter(file => file.type.match(/^image\/(jpeg|png|webp)$/) && (maxFileSize <= 0 || file.size <= maxFileSize));

    const currentDescriptions = collectCurrentDescriptions();

    currentFiles = currentFiles.map(function (file, index) {
      file._scgDescription = currentDescriptions[index] || file._scgDescription || '';
      return file;
    });

    const remainingSlots = max - currentFiles.length;

    if (remainingSlots <= 0) {
      setMessage('画像は最大' + max + '枚までです', true);
      renderSelectedFiles();
      return;
    }

    const filesToAdd = validFiles.slice(0, remainingSlots);

    filesToAdd.forEach(function (file) {
      file._scgId = createUploadFileId(file);
      file._scgDescription = '';
      currentFiles.push(file);
    });

    if (validFiles.length > remainingSlots) {
      setMessage('最大' + max + '枚まで追加しました。超過分は除外しました', true);
    }

    renderSelectedFiles();
  }

  function collectCurrentDescriptions() {
    const descriptions = [];

    selectedFiles.find('.scg-selected-file').each(function () {
      const index = Number($(this).data('index'));
      descriptions[index] = $(this).find('textarea').val() || '';
    });

    return descriptions;
  }

  function createUploadFileId(file) {
    return [
      file.name,
      file.size,
      file.lastModified,
      Math.random().toString(36).slice(2)
    ].join('-');
  }

  function renderSelectedFiles() {
    selectedFiles.html('');

    currentFiles.forEach(function (file, index) {
      const url = URL.createObjectURL(file);
      const description = file._scgDescription || '';

      selectedFiles.append(`
        <div class="scg-selected-file" data-index="${index}">
          <div class="scg-selected-thumb"><img src="${url}" alt=""></div>
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
    const item = $(this).closest('.scg-selected-file');
    const index = Number(item.data('index'));

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


  function ensureUploadProgress() {
    let progress = $('#scg-inline-upload-progress');

    if (!progress.length) {
      progress = $(`
        <div id="scg-inline-upload-progress" class="scg-upload-progress" style="display:none;">
          <div class="scg-upload-progress-head">
            <span class="scg-upload-progress-message">アップロード準備中...</span>
            <span class="scg-upload-progress-percent">0%</span>
          </div>
          <div class="scg-upload-progress-track">
            <div class="scg-upload-progress-bar"></div>
          </div>
        </div>
      `);
      uploadSubmit.before(progress);
    }

    return progress;
  }

  function setUploadProgress(percent, messageText) {
    const progress = ensureUploadProgress();
    const safePercent = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));

    progress.show();
    progress.find('.scg-upload-progress-message').text(messageText || 'アップロード中...');
    progress.find('.scg-upload-progress-percent').text(safePercent + '%');
    progress.find('.scg-upload-progress-bar').css('width', safePercent + '%');
  }

  function hideUploadProgress(delay) {
    const progress = $('#scg-inline-upload-progress');
    if (!progress.length) return;

    window.setTimeout(function () {
      progress.fadeOut(180, function () {
        progress.removeClass('is-processing');
        progress.find('.scg-upload-progress-bar').css('width', '0%');
        progress.find('.scg-upload-progress-percent').text('0%');
      });
    }, Number(delay) || 0);
  }

  function uploadSelectedFiles() {
    if (!subValue.val() || !mainValue.val()) {
      setMessage('先にカテゴリーを選択してください', true);
      return;
    }

    if (!currentFiles.length) {
      setMessage('画像を選択してください', true);
      return;
    }

    const formData = new FormData();
    formData.append('action', 'scg_upload_photos');
    formData.append('nonce', SCG_MANAGE.upload_nonce);
    formData.append('main_category', mainValue.val());
    formData.append('sub_category', subValue.val());

    currentFiles.forEach(function (file) {
      formData.append('photos[]', file);
    });

    const currentDescriptions = collectCurrentDescriptions();
    currentFiles.forEach(function (file, index) {
      formData.append('descriptions[]', currentDescriptions[index] || file._scgDescription || '');
    });

    uploadSubmit.prop('disabled', true).text('アップロード中...');
    setUploadProgress(0, 'アップロード準備中...');
    setMessage('アップロード中...', false);

    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function (event) {
      if (event.lengthComputable) {
        // ブラウザ→サーバーへの送信完了は95%までにする。
        // 残り5%はWordPress側の画像処理・DB保存が終わった時点で100%にする。
        const rawPercent = (event.loaded / event.total) * 100;
        const displayPercent = Math.min(95, rawPercent * 0.95);

        if (rawPercent >= 100) {
          setUploadProgress(95, '画像を処理中...');
          $('#scg-inline-upload-progress').addClass('is-processing');
        } else {
          setUploadProgress(displayPercent, 'アップロード中...');
        }
      }
    });

    xhr.addEventListener('load', function () {
      uploadSubmit.prop('disabled', false).text('アップロードする');

      let response = null;

      try {
        response = JSON.parse(xhr.responseText);
      } catch (error) {
        $('#scg-inline-upload-progress').removeClass('is-processing');
        setUploadProgress(100, '処理に失敗しました');
        hideUploadProgress(1200);
        setMessage('サーバー応答の解析に失敗しました', true);
        return;
      }

      if (xhr.status >= 200 && xhr.status < 300 && response && response.success) {
        $('#scg-inline-upload-progress').removeClass('is-processing');
        setUploadProgress(100, 'アップロード完了');
        setMessage(response.data.message || 'アップロードしました', false);

        currentFiles = [];
        selectedFiles.html('');
        fileInput.val('');

        // 完了後は待たせず、すぐ一覧を更新する
        closeUploadArea();
        loadPhotos(subValue.val());

        // 進捗バーだけ余韻として短く残す
        hideUploadProgress(250);
      } else {
        $('#scg-inline-upload-progress').removeClass('is-processing');
        setUploadProgress(100, 'アップロード失敗');
        hideUploadProgress(1200);
        const errorMessage = response && response.data && response.data.message
          ? response.data.message
          : 'アップロードに失敗しました';
        setMessage(errorMessage, true);
      }
    });

    xhr.addEventListener('error', function () {
      uploadSubmit.prop('disabled', false).text('アップロードする');
      $('#scg-inline-upload-progress').removeClass('is-processing');
      setUploadProgress(100, '通信エラー');
      hideUploadProgress(1200);
      setMessage('通信エラーが発生しました', true);
    });

    xhr.open('POST', SCG_MANAGE.ajax_url);
    xhr.send(formData);
  }

  function openUploadArea() {
    uploadArea.slideDown(180);
    uploadToggle.addClass('is-upload-open');
    uploadToggle.html('<span class="scg-upload-close-icon">⌃</span><span class="scg-upload-close-text">アップロードを閉じる</span><small>待機中の画像をキャンセル</small>');
  }

  function resetUploadToggle() {
    uploadToggle.removeClass('is-upload-open');
    uploadToggle.text('写真を追加（アップロード）する');
  }

  function closeUploadArea() {
    currentFiles = [];
    selectedFiles.html('');
    fileInput.val('');
    uploadArea.slideUp(120);
    resetUploadToggle();
  }

  grid.on('click', '.scg-photo-thumb', function () {
    if (isDragging) return;

    const itemEl = $(this).closest('.scg-photo-item');
    const id = Number(itemEl.data('id'));

    if (openedId === id) {
      closeEditor();
      return;
    }

    closeUploadArea();

    if (currentStatus === 'hidden') {
      openTrashActionsForItem(itemEl, id);
    } else {
      openEditorForItem(itemEl, id);
    }
  });

  grid.on('click', '.scg-edit-close', function () {
    closeEditor();
  });

  grid.on('click', '.scg-save-description', function () {
    const row = $(this).closest('.scg-photo-edit-row');
    const id = Number(row.data('id'));
    const description = row.find('textarea').val();

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_update_photo_description',
      nonce: SCG_MANAGE.nonce,
      photo_id: id,
      description: description
    }).done(function (response) {
      if (response.success) {
        updateCurrentItemDescription(id, description);
        setMessage(response.data.message || '説明文を保存しました', false);
        closeEditor();
      } else {
        setMessage(response.data.message || '保存に失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  });

  grid.on('click', '.scg-delete-photo', function () {
    const row = $(this).closest('.scg-photo-edit-row');
    const id = Number(row.data('id'));

    if (!confirm('この画像を削除済みに移動しますか？')) {
      return;
    }

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_delete_photo',
      nonce: SCG_MANAGE.nonce,
      photo_id: id
    }).done(function (response) {
      if (response.success) {
        grid.find(`.scg-photo-item[data-id="${id}"]`).fadeOut(160, function () {
          $(this).remove();
          closeEditor();
          currentItems = currentItems.filter(item => Number(item.id) !== id);
          if (!grid.children('.scg-photo-item').length) {
            renderEmptyGuide('このカテゴリーにはまだ写真がありません', '写真を追加すると、ここに一覧表示されます。');
          }
        });
        setMessage(response.data.message || '画像を削除済みに移動しました', false);
      } else {
        setMessage(response.data.message || '削除に失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  });

  grid.on('click', '.scg-restore-photo', function () {
    const row = $(this).closest('.scg-photo-edit-row');
    const id = Number(row.data('id'));

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_restore_photo',
      nonce: SCG_MANAGE.nonce,
      photo_id: id
    }).done(function (response) {
      if (response.success) {
        grid.find(`.scg-photo-item[data-id="${id}"]`).fadeOut(160, function () {
          $(this).remove();
          closeEditor();
          currentItems = currentItems.filter(item => Number(item.id) !== id);
          if (!grid.children('.scg-photo-item').length) {
            renderEmptyGuide('削除済み画像はありません', 'このカテゴリーには復元できる削除済み画像がありません。');
          }
        });
        setMessage(response.data.message || '画像を復元しました', false);
      } else {
        setMessage(response.data.message || '復元に失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  });

  grid.on('click', '.scg-permanent-delete-photo', function () {
    const row = $(this).closest('.scg-photo-edit-row');
    const id = Number(row.data('id'));

    if (!confirm('この画像を完全削除しますか？\nこの操作は取り消せません。')) {
      return;
    }

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_permanently_delete_photo',
      nonce: SCG_MANAGE.nonce,
      photo_id: id
    }).done(function (response) {
      if (response.success) {
        grid.find(`.scg-photo-item[data-id="${id}"]`).fadeOut(160, function () {
          $(this).remove();
          closeEditor();
          currentItems = currentItems.filter(item => Number(item.id) !== id);
          if (!grid.children('.scg-photo-item').length) {
            renderEmptyGuide('削除済み画像はありません', 'このカテゴリーには復元できる削除済み画像がありません。');
          }
        });
        setMessage(response.data.message || '画像を完全削除しました', false);
      } else {
        setMessage(response.data.message || '完全削除に失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  });

  function openEditorForItem(itemEl, id) {
    closeEditor();

    const itemData = currentItems.find(item => Number(item.id) === id) || {};
    const rowEndEl = getRowEndElement(itemEl);
    const positionLabel = getPositionLabel(itemEl);

    const editor = $(`
      <div class="scg-photo-edit-row" data-id="${id}" style="display:none;">
        <div class="scg-edit-pointer"></div>
        <div class="scg-edit-header">
          <div class="scg-edit-title">
            <span class="scg-edit-icon">▣</span>
            <strong>${positionLabel}画像を編集中</strong>
            <span>クリックした画像の説明文を編集できます。</span>
          </div>
          <button type="button" class="button scg-edit-close" aria-label="閉じる">×</button>
        </div>
        <div class="scg-edit-body">
          <label>説明文</label>
          <textarea rows="4" placeholder="画像プレビュー時に表示する説明文">${escapeHtml(itemData.description || '')}</textarea>
        </div>
        <div class="scg-photo-edit-actions">
          <button type="button" class="button-link-delete scg-delete-photo">画像を削除する</button>
          <button type="button" class="button button-primary scg-save-description">確定</button>
        </div>
      </div>
    `);

    rowEndEl.after(editor);
    positionPointer(editor, itemEl);
    editor.slideDown(180);
    itemEl.addClass('is-open');
    openedId = id;
  }

  function openTrashActionsForItem(itemEl, id) {
    closeEditor();

    const rowEndEl = getRowEndElement(itemEl);
    const permanentDeleteButton = canPermanentDelete
      ? '<button type="button" class="button-link-delete scg-permanent-delete-photo">完全削除する</button>'
      : '';

    const editor = $(`
      <div class="scg-photo-edit-row scg-trash-action-row" data-id="${id}" style="display:none;">
        <div class="scg-edit-pointer"></div>
        <div class="scg-edit-header">
          <div class="scg-edit-title">
            <span class="scg-edit-icon">↩</span>
            <strong>削除済み画像を選択中</strong>
            <span>復元すると通常のギャラリー管理に戻ります。</span>
          </div>
          <button type="button" class="button scg-edit-close" aria-label="閉じる">×</button>
        </div>
        <div class="scg-photo-edit-actions">
          ${permanentDeleteButton}
          <button type="button" class="button button-primary scg-restore-photo">復元する</button>
        </div>
      </div>
    `);

    rowEndEl.after(editor);
    positionPointer(editor, itemEl);
    editor.slideDown(180);
    itemEl.addClass('is-open');
    openedId = id;
  }

  function getRowEndElement(itemEl) {
    const items = grid.children('.scg-photo-item');
    const index = items.index(itemEl);
    const columns = getGridColumnCount();
    const rowEndIndex = Math.min(items.length - 1, Math.floor(index / columns) * columns + columns - 1);
    return items.eq(rowEndIndex);
  }

  function getGridColumnCount() {
    const columns = grid.css('grid-template-columns');
    if (!columns || columns === 'none') return 5;
    return columns.split(' ').filter(Boolean).length || 5;
  }

  function getPositionLabel(itemEl) {
    const items = grid.children('.scg-photo-item');
    const index = items.index(itemEl);
    const columns = getGridColumnCount();
    const positionInRow = index % columns;

    if (positionInRow === 0) return '左の';
    if (positionInRow === columns - 1) return '右の';
    return '選択中の';
  }

  function positionPointer(editor, itemEl) {
    const gridLeft = grid.offset().left;
    const itemCenter = itemEl.offset().left + itemEl.outerWidth() / 2;
    const pointerLeft = itemCenter - gridLeft;
    editor.find('.scg-edit-pointer').css('left', pointerLeft + 'px');
  }

  function closeEditor() {
    grid.find('.scg-photo-edit-row').remove();
    grid.find('.scg-photo-item').removeClass('is-open');
    openedId = null;
  }

  function saveOrder() {
    if (currentStatus !== 'active') return;

    const ids = [];
    grid.children('.scg-photo-item').each(function () {
      ids.push($(this).data('id'));
    });

    $.post(SCG_MANAGE.ajax_url, {
      action: 'scg_save_photo_order',
      nonce: SCG_MANAGE.nonce,
      ids: ids
    }).done(function (response) {
      if (response.success) {
        setMessage(response.data.message || '並び順を保存しました', false);
      } else {
        setMessage(response.data.message || '保存に失敗しました', true);
      }
    }).fail(function () {
      setMessage('通信エラーが発生しました', true);
    });
  }

  function syncCurrentItemsFromDom() {
    const newOrder = [];
    grid.children('.scg-photo-item').each(function () {
      const id = Number($(this).data('id'));
      const item = currentItems.find(i => Number(i.id) === id);
      if (item) newOrder.push(item);
    });
    currentItems = newOrder;
  }

  function updateCurrentItemDescription(id, description) {
    currentItems = currentItems.map(function (item) {
      if (Number(item.id) === Number(id)) {
        item.description = description;
      }
      return item;
    });
  }

  function setMessage(text, isError) {
    if (!text) {
      message.removeClass('is-show is-error is-success').text('');
      return;
    }

    if (scgMessageTimer) clearTimeout(scgMessageTimer);

    message
      .removeClass('is-error is-success is-show')
      .addClass(isError ? 'is-error' : 'is-success')
      .text(text);

    setTimeout(function () {
      message.addClass('is-show');
    }, 10);

    scgMessageTimer = setTimeout(function () {
      message.removeClass('is-show');
    }, 2600);
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
