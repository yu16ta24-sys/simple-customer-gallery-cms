(function($) {
    'use strict';

    var cfg = window.SCG_ADMIN_SLIDER || {};
    var ajaxUrl = cfg.ajax_url || window.ajaxurl;
    var nonce = cfg.nonce || '';
    var maxItems = parseInt(cfg.max_items || 12, 10);
    var maxFileSize = parseInt(cfg.max_file_size || 0, 10);
    var messages = cfg.messages || {};
    var uploadQueue = [];
    var isUploadingQueue = false;

    function getCount() {
        return $('#scg-slider-list .scg-slider-row').length;
    }

    function updateCountUi() {
        var count = getCount();
        var remaining = Math.max(0, maxItems - count);
        $('[data-scg-slider-count]').text(count);
        $('[data-scg-slider-remaining]').text(remaining);
        $('#scg-slider-list').attr('data-count', count);
        $('.scg-slider-file-drop').toggleClass('is-disabled', remaining <= 0 || isUploadingQueue);
    }

    function ensureNotEmpty() {
        var $list = $('#scg-slider-list');
        if (!$list.find('.scg-slider-row').length && !$list.find('.scg-slider-empty').length) {
            $list.append('<li class="scg-slider-empty">まだ画像が登録されていません。下の「画像を追加」から登録してください。</li>');
        }
    }

    function removeEmpty() {
        $('#scg-slider-list .scg-slider-empty').remove();
    }

    function showNotice(message, isError) {
        var $notice = $('.scg-slider-inline-notice');
        if (!$notice.length) {
            $notice = $('<div class="scg-slider-inline-notice"></div>').prependTo('.scg-slider-panel');
        }
        $notice.text(message || '').toggleClass('is-error', !!isError).addClass('is-show');
        window.clearTimeout($notice.data('timer'));
        $notice.data('timer', window.setTimeout(function() {
            $notice.removeClass('is-show');
        }, 2800));
    }

    function syncRangeOutputs() {
        $('.scg-slider-range').each(function() {
            var input = this;
            var $output = $('[data-scg-slider-range-output="' + input.name + '"]');
            var unit = 'ミリ秒';
            var sync = function() {
                $output.text(input.value + unit);
            };
            $(input).on('input change', sync);
            sync();
        });
    }

    function getCurrentOrder() {
        var order = [];
        $('#scg-slider-list .scg-slider-row').each(function() {
            var id = parseInt($(this).attr('data-attachment-id') || $(this).data('attachment-id'), 10);
            if (id) {
                order.push(id);
            }
        });
        return order;
    }

    function saveOrder(options) {
        options = options || {};
        var order = getCurrentOrder();
        if (!order.length) {
            return $.Deferred().resolve().promise();
        }
        if (options.showSaving) {
            showNotice(options.savingText || '保存中...', false);
        }
        return $.post(ajaxUrl, {
            action: 'scg_top_slider_save_order',
            nonce: nonce,
            order: order
        }).done(function(response) {
            if (!response || !response.success) {
                var msg = response && response.data && response.data.message ? response.data.message : (messages.error || '保存に失敗しました。');
                showNotice(msg, true);
                return;
            }
            if (options.showSaved) {
                showNotice(options.savedText || '保存完了', false);
            }
        }).fail(function() {
            showNotice(messages.error || '保存に失敗しました。', true);
        });
    }

    function initSortable() {
        var $list = $('#scg-slider-list');
        if ($list.length && typeof $.fn.sortable === 'function') {
            $list.sortable({
                items: '.scg-slider-row',
                handle: '.scg-slider-drag',
                placeholder: 'scg-slider-placeholder',
                tolerance: 'pointer',
                update: function() {
                    saveOrder({
                        showSaving: true,
                        showSaved: true,
                        savingText: '並び順を保存中...',
                        savedText: '並び順を保存しました。'
                    });
                }
            });
        }
    }

    function createUploadingRow(file) {
        var url = file && file.type && file.type.indexOf('image/') === 0 ? URL.createObjectURL(file) : '';
        var $row = $('<li class="scg-slider-row scg-slider-row-uploading"></li>');
        $row.append('<div class="scg-slider-drag">≡</div>');
        var $thumb = $('<div class="scg-slider-thumb"></div>').appendTo($row);
        if (url) {
            $('<img alt="">').attr('src', url).appendTo($thumb);
        } else {
            $thumb.append('<span>Uploading</span>');
        }
        var $meta = $('<div class="scg-slider-meta"></div>').appendTo($row);
        $('<strong></strong>').text(file.name || 'アップロード中の画像').appendTo($meta);
        $meta.append('<div class="scg-slider-progress"><span></span></div>');
        $meta.append('<p class="scg-slider-progress-text">' + (messages.uploading || 'アップロード中...') + '</p>');
        $row.data('preview-url', url);
        return $row;
    }

    function setProgress($row, percent, text) {
        percent = Math.max(0, Math.min(100, percent));
        $row.find('.scg-slider-progress span').css('width', percent + '%');
        if (text) {
            $row.find('.scg-slider-progress-text').text(text);
        }
    }

    function validateFile(file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return 'jpg / png / webp の画像を選択してください。';
        }
        if (maxFileSize && file.size > maxFileSize) {
            return messages.too_large || '画像サイズが大きすぎます。20MB以内の画像を選択してください。';
        }
        return '';
    }

    function uploadFile(file, $row) {
        var deferred = $.Deferred();
        var error = validateFile(file);
        if (error) {
            if ($row && $row.length) {
                setProgress($row, 0, error);
                $row.addClass('is-error');
            }
            showNotice(error, true);
            deferred.resolve(null);
            return deferred.promise();
        }

        if (!$row || !$row.length) {
            removeEmpty();
            $row = createUploadingRow(file);
            $('#scg-slider-list').append($row);
            updateCountUi();
        }

        var formData = new FormData();
        formData.append('action', 'scg_top_slider_upload');
        formData.append('nonce', nonce);
        formData.append('file', file);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = $.ajaxSettings.xhr();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 92);
                            setProgress($row, percent, messages.uploading || 'アップロード中...');
                        }
                    });
                }
                return xhr;
            }
        }).done(function(response) {
            if (!response || !response.success) {
                var msg = response && response.data && response.data.message ? response.data.message : (messages.error || '処理に失敗しました。');
                $row.addClass('is-error');
                setProgress($row, 0, msg);
                showNotice(msg, true);
                deferred.resolve(null);
                return;
            }
            setProgress($row, 100, messages.complete || 'アップロード完了');
            window.setTimeout(function() {
                var previewUrl = $row.data('preview-url');
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                }
                $row.replaceWith(response.data.row);
                updateCountUi();
                deferred.resolve(response.data.id || null);
            }, 180);
        }).fail(function() {
            $row.addClass('is-error');
            setProgress($row, 0, messages.error || '処理に失敗しました。');
            showNotice(messages.error || '処理に失敗しました。', true);
            deferred.resolve(null);
        });

        return deferred.promise();
    }

    function processUploadQueue(files) {
        var list = Array.prototype.slice.call(files || []);
        if (!list.length) {
            return;
        }

        var remaining = Math.max(0, maxItems - getCount());
        if (remaining <= 0) {
            showNotice(messages.max_reached || '登録できる画像は最大12枚までです。', true);
            return;
        }
        if (list.length > remaining) {
            showNotice('追加できる残り枚数は' + remaining + '枚です。超過分は除外しました。', true);
            list = list.slice(0, remaining);
        }

        removeEmpty();

        var uploadItems = list.map(function(file) {
            var $row = createUploadingRow(file);
            $('#scg-slider-list').append($row);
            return { file: file, $row: $row };
        });

        isUploadingQueue = true;
        updateCountUi();
        showNotice('画像をアップロードしています...', false);

        var uploadPromises = uploadItems.map(function(item) {
            return uploadFile(item.file, item.$row).then(function(id) {
                return id || null;
            });
        });

        $.when.apply($, uploadPromises).always(function() {
            var results = Array.prototype.slice.call(arguments);
            if (uploadPromises.length === 1) {
                results = [arguments[0]];
            }

            var uploadedCount = results.filter(function(id) { return !!id; }).length;
            var failedCount = uploadItems.length - uploadedCount;

            isUploadingQueue = false;
            updateCountUi();

            $('#scg-slider-list .scg-slider-row-uploading.is-error').delay(1600).fadeOut(180, function() {
                $(this).remove();
                ensureNotEmpty();
                updateCountUi();
            });

            if (uploadedCount > 0) {
                saveOrder({
                    showSaving: true,
                    showSaved: true,
                    savingText: 'スライダーを保存中...',
                    savedText: 'スライダー画像を保存しました。'
                });
            } else if (failedCount > 0) {
                showNotice('アップロードできた画像がありませんでした。', true);
            }
        });
    }

    function bindUploader() {
        var $newInput = $('#scg-slider-new-files');
        $newInput.on('change', function() {
            processUploadQueue(this.files);
            this.value = '';
        });

        $('.scg-slider-file-drop').on('dragenter dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!$(this).hasClass('is-disabled')) {
                $(this).addClass('is-dragover');
            }
        }).on('dragleave dragend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('is-dragover');
        }).on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('is-dragover');
            var files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : [];
            processUploadQueue(files);
        });
    }

    function bindDelete() {
        $(document).on('click', '.scg-slider-delete-button', function() {
            var $button = $(this);
            var attachmentId = parseInt($button.data('attachment-id'), 10);
            if (!attachmentId) {
                return;
            }
            if (!window.confirm(messages.delete_confirm || 'この画像を削除します。よろしいですか？')) {
                return;
            }
            $button.prop('disabled', true).text('削除中...');
            $.post(ajaxUrl, {
                action: 'scg_top_slider_delete',
                nonce: nonce,
                attachment_id: attachmentId
            }).done(function(response) {
                if (!response || !response.success) {
                    var msg = response && response.data && response.data.message ? response.data.message : (messages.error || '処理に失敗しました。');
                    $button.prop('disabled', false).text('この画像を削除');
                    showNotice(msg, true);
                    return;
                }
                $button.closest('.scg-slider-row').fadeOut(180, function() {
                    $(this).remove();
                    ensureNotEmpty();
                    updateCountUi();
                    saveOrder({
                        showSaving: true,
                        showSaved: true,
                        savingText: '削除内容を保存中...',
                        savedText: '画像を削除して保存しました。'
                    });
                });
            }).fail(function() {
                $button.prop('disabled', false).text('この画像を削除');
                showNotice(messages.error || '処理に失敗しました。', true);
            });
        });
    }

    $(function() {
        initSortable();
        syncRangeOutputs();
        bindUploader();
        bindDelete();
        updateCountUi();
    });
})(jQuery);
