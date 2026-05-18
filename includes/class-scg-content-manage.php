<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Content_Manage {
    const MAX_IMAGES = 3;

    public static function init() {
        add_action('admin_post_scg_save_content', [__CLASS__, 'handle_save']);
        add_action('wp_ajax_scg_save_content_ajax', [__CLASS__, 'handle_save']);
        add_action('admin_post_scg_hide_content', [__CLASS__, 'handle_hide']);
        add_action('admin_post_scg_restore_content', [__CLASS__, 'handle_restore']);
        add_action('admin_post_scg_permanently_delete_content', [__CLASS__, 'handle_permanently_delete']);
    }

    public static function get_config($type = '') {
        $configs = [
            'blog' => [
                'type' => 'blog',
                'post_type' => 'scg_blog',
                'label' => 'ブログ',
                'write_label' => 'ブログを書く',
                'list_label' => 'ブログ一覧',
                'add_page' => 'scg-blog-add',
                'list_page' => 'scg-blog-list',
            ],
            'news' => [
                'type' => 'news',
                'post_type' => 'scg_news',
                'label' => 'お知らせ',
                'write_label' => 'お知らせを書く',
                'list_label' => 'お知らせ一覧',
                'add_page' => 'scg-news-add',
                'list_page' => 'scg-news-list',
            ],
        ];

        return $configs[$type] ?? null;
    }

    public static function render_blog_edit_page() {
        self::render_edit_page('blog');
    }

    public static function render_news_edit_page() {
        self::render_edit_page('news');
    }

    public static function render_blog_list_page() {
        self::render_list_page('blog');
    }

    public static function render_news_list_page() {
        self::render_list_page('news');
    }

    private static function render_edit_page($type) {
        if (!current_user_can('edit_posts')) {
            wp_die('権限がありません');
        }

        $config = self::get_config($type);
        if (!$config) {
            wp_die('設定が見つかりません');
        }

        $content_id = isset($_GET['content_id']) ? absint($_GET['content_id']) : 0;
        $post = null;
        $is_edit = false;

        if ($content_id) {
            $post = get_post($content_id);
            if (!$post || $post->post_type !== $config['post_type']) {
                wp_die('記事が見つかりません');
            }
            $is_edit = true;
        }

        $title = $post ? $post->post_title : '';
        $body = $post ? $post->post_content : '';
        $post_status = $post ? $post->post_status : 'publish';
        $post_datetime = self::get_blog_datetime_value($post);
        $message = isset($_GET['scg_message']) ? sanitize_key($_GET['scg_message']) : '';
        $error = isset($_GET['scg_error']) ? sanitize_key($_GET['scg_error']) : '';
        ?>
        <div class="wrap scg-wrap">
            <h1><?php echo esc_html($is_edit ? $config['label'] . 'を編集' : $config['write_label']); ?></h1>
            <?php self::render_notice($message, $error); ?>

            <?php if ($type === 'news') { self::render_news_notice_form($config, $content_id, $post, $post_status, $is_edit); echo '</div>'; return; } ?>

            <form class="scg-content-form scg-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('scg_save_content_' . $type, 'scg_nonce'); ?>
                <input type="hidden" name="action" value="scg_save_content">
                <input type="hidden" name="scg_type" value="<?php echo esc_attr($type); ?>">
                <input type="hidden" name="content_id" value="<?php echo esc_attr($content_id); ?>">

                <label class="scg-label" for="scg-content-title">タイトル</label>
                <input type="text" id="scg-content-title" name="scg_title" class="scg-content-title" value="<?php echo esc_attr($title); ?>" placeholder="タイトルを入力" required>

                <label class="scg-label" for="scg-content-body">本文</label>
                <textarea id="scg-content-body" name="scg_body" class="scg-content-body" rows="12" placeholder="本文を入力してください"><?php echo esc_textarea($body); ?></textarea>

                <label class="scg-label" for="scg-post-datetime">投稿日時</label>
                <input type="datetime-local" id="scg-post-datetime" name="scg_post_datetime" class="regular-text" value="<?php echo esc_attr($post_datetime); ?>">
                <p class="scg-help">ブログ一覧・フロント表示の並び順に反映されます。未入力の場合は現在日時で保存されます。</p>

                <div class="scg-content-status-row">
                    <span class="scg-label scg-inline-label">公開状態</span>
                    <label><input type="radio" name="scg_post_status" value="publish" <?php checked($post_status, 'publish'); ?>> 公開</label>
                    <label><input type="radio" name="scg_post_status" value="draft" <?php checked($post_status, 'draft'); ?>> 下書き</label>
                </div>

                <div class="scg-content-images">
                    <h2>添付画像</h2>
                    <p class="scg-help">最大3枚まで。対応形式：jpg / png / webp。大きい画像は保存時に自動最適化されます。</p>
                    <?php for ($i = 1; $i <= self::MAX_IMAGES; $i++): ?>
                        <?php
                        $image_id = $content_id ? intval(get_post_meta($content_id, '_scg_image_' . $i, true)) : 0;
                        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                        $is_extra = $i > 1 && !$image_id;
                        ?>
                        <div class="scg-content-image-slot <?php echo $is_extra ? 'is-extra-slot' : ''; ?>" data-slot="<?php echo esc_attr($i); ?>" <?php echo $is_extra ? 'style="display:none;"' : ''; ?>>
                            <div class="scg-content-image-preview">
                                <?php if ($image_url): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                                <?php else: ?>
                                    <span>画像<?php echo esc_html($i); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="scg-content-image-controls">
                                <label class="scg-content-image-label" for="scg-image-<?php echo esc_attr($i); ?>">画像<?php echo esc_html($i); ?></label>
                                <input type="file" id="scg-image-<?php echo esc_attr($i); ?>" name="scg_image_<?php echo esc_attr($i); ?>" accept="image/jpeg,image/png,image/webp">
                                <button type="button" class="button scg-cancel-selected-image" style="display:none;">この画像をキャンセル</button>
                                <?php if ($image_id): ?>
                                    <input type="hidden" name="scg_existing_image_<?php echo esc_attr($i); ?>" value="<?php echo esc_attr($image_id); ?>">
                                    <label class="scg-remove-image"><input type="checkbox" name="scg_remove_image_<?php echo esc_attr($i); ?>" value="1"> この画像を外す</label>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                    <button type="button" class="button scg-add-image-slot">画像を追加する</button>
                </div>

                <div class="scg-content-actions">
                    <button type="submit" class="button button-primary button-large scg-content-submit-button"><?php echo esc_html($is_edit ? '更新する' : '保存する'); ?></button>
                    <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=' . $config['list_page'])); ?>"><?php echo esc_html($config['list_label']); ?>へ戻る</a>
                </div>

                <div class="scg-content-progress" aria-live="polite" style="display:none;">
                    <div class="scg-content-progress-head">
                        <strong>保存処理中</strong>
                        <span class="scg-content-progress-percent">0%</span>
                    </div>
                    <div class="scg-content-progress-bar"><span style="width:0%;"></span></div>
                    <p class="scg-content-progress-message">送信の準備をしています...</p>
                </div>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.querySelector('.scg-content-form');
            var addButton = document.querySelector('.scg-add-image-slot');
            var submitButton = document.querySelector('.scg-content-submit-button');
            var slots = Array.prototype.slice.call(document.querySelectorAll('.scg-content-image-slot'));
            var progressBox = document.querySelector('.scg-content-progress');
            var progressBar = progressBox ? progressBox.querySelector('.scg-content-progress-bar span') : null;
            var progressPercent = progressBox ? progressBox.querySelector('.scg-content-progress-percent') : null;
            var progressMessage = progressBox ? progressBox.querySelector('.scg-content-progress-message') : null;
            var isSubmitting = false;
            var ajaxSaveUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            function getHiddenExtraSlot() {
                return document.querySelector('.scg-content-image-slot.is-extra-slot[style*="display:none"]');
            }

            function refreshAddButton() {
                if (addButton && !getHiddenExtraSlot()) {
                    addButton.style.display = 'none';
                }
            }

            function openNextSlot() {
                var next = getHiddenExtraSlot();
                if (next) {
                    next.style.display = '';
                    next.classList.remove('is-extra-slot');
                }
                refreshAddButton();
            }

            function isImageFile(file) {
                return !!file && /^image\/(jpeg|png|webp)$/.test(file.type);
            }

            function setPreview(slot, file) {
                var preview = slot.querySelector('.scg-content-image-preview');
                var cancelButton = slot.querySelector('.scg-cancel-selected-image');
                if (!preview || !file) return;

                if (!preview.hasAttribute('data-original-html')) {
                    preview.setAttribute('data-original-html', preview.innerHTML);
                }

                if (!isImageFile(file)) {
                    preview.innerHTML = '<span>jpg / png / webp の画像を選択してください</span>';
                    slot.classList.add('has-error');
                    slot.classList.remove('has-preview');
                    if (cancelButton) cancelButton.style.display = 'inline-block';
                    return;
                }

                var previousUrl = preview.getAttribute('data-object-url');
                if (previousUrl) {
                    URL.revokeObjectURL(previousUrl);
                }

                var objectUrl = URL.createObjectURL(file);
                preview.setAttribute('data-object-url', objectUrl);
                preview.innerHTML = '<img src="' + objectUrl + '" alt="">';
                slot.classList.add('has-preview');
                slot.classList.remove('has-error');
                if (cancelButton) cancelButton.style.display = 'inline-block';
            }

            function clearSelectedFile(slot) {
                var input = slot.querySelector('input[type="file"]');
                var preview = slot.querySelector('.scg-content-image-preview');
                var cancelButton = slot.querySelector('.scg-cancel-selected-image');
                if (input) input.value = '';
                if (preview) {
                    var previousUrl = preview.getAttribute('data-object-url');
                    if (previousUrl) {
                        URL.revokeObjectURL(previousUrl);
                        preview.removeAttribute('data-object-url');
                    }
                    preview.innerHTML = preview.getAttribute('data-original-html') || '<span>画像' + (slot.getAttribute('data-slot') || '') + '</span>';
                }
                slot.classList.remove('has-preview', 'has-error', 'is-dragover');
                if (cancelButton) cancelButton.style.display = 'none';
            }

            function assignDroppedFile(input, file) {
                if (!window.DataTransfer || !input || !file) return false;
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }

            function setProgress(percent, message) {
                var safePercent = Math.max(0, Math.min(100, Math.round(percent)));
                if (progressBox) progressBox.style.display = 'block';
                if (progressBar) progressBar.style.width = safePercent + '%';
                if (progressPercent) progressPercent.textContent = safePercent + '%';
                if (progressMessage && message) progressMessage.textContent = message;
            }

            function handleAjaxSubmit(event) {
                if (!form || isSubmitting || !window.XMLHttpRequest || !window.FormData) return;
                event.preventDefault();

                isSubmitting = true;
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = '保存中...';
                }
                setProgress(3, '保存データを準備しています...');

                var formData = new FormData(form);
                formData.delete('action');
                formData.append('action', 'scg_save_content_ajax');
                formData.append('scg_ajax', '1');

                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxSaveUrl, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', function (e) {
                    if (!e.lengthComputable) {
                        setProgress(20, '画像をアップロードしています...');
                        return;
                    }
                    var uploadPercent = Math.min(88, Math.round((e.loaded / e.total) * 88));
                    setProgress(uploadPercent, '画像をアップロードしています...');
                });

                xhr.addEventListener('load', function () {
                    setProgress(96, 'WordPress側で画像を処理しています...');
                    var response = null;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {}

                    if (xhr.status >= 200 && xhr.status < 300 && response && response.success && response.data && response.data.redirect) {
                        setProgress(100, '保存が完了しました。画面を更新します...');
                        window.location.href = response.data.redirect;
                        return;
                    }

                    if (response && response['wp-auth-check']) {
                        setProgress(100, 'ログイン状態の確認が返ってきました。保存リクエストが処理されていないため、ページを再読み込みしてもう一度お試しください。');
                    } else if (response && response.data && response.data.message) {
                        setProgress(100, response.data.message);
                    } else {
                        setProgress(100, '保存に失敗しました。時間をおいてもう一度お試しください。');
                    }
                    isSubmitting = false;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = '<?php echo esc_js($is_edit ? '更新する' : '保存する'); ?>';
                    }
                });

                xhr.addEventListener('error', function () {
                    setProgress(100, '通信に失敗しました。ネットワークを確認してください。');
                    isSubmitting = false;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = '<?php echo esc_js($is_edit ? '更新する' : '保存する'); ?>';
                    }
                });

                setProgress(8, '送信を開始しています...');
                xhr.send(formData);
            }

            if (addButton) {
                addButton.addEventListener('click', openNextSlot);
            }

            if (form) {
                form.addEventListener('submit', handleAjaxSubmit);
            }

            slots.forEach(function (slot) {
                var input = slot.querySelector('input[type="file"]');
                var cancelButton = slot.querySelector('.scg-cancel-selected-image');
                var preview = slot.querySelector('.scg-content-image-preview');
                if (preview && !preview.hasAttribute('data-original-html')) {
                    preview.setAttribute('data-original-html', preview.innerHTML);
                }
                if (!input) return;

                input.addEventListener('change', function () {
                    if (input.files && input.files[0]) {
                        setPreview(slot, input.files[0]);
                    } else {
                        clearSelectedFile(slot);
                    }
                });

                if (cancelButton) {
                    cancelButton.addEventListener('click', function () {
                        clearSelectedFile(slot);
                    });
                }

                slot.addEventListener('dragover', function (event) {
                    event.preventDefault();
                    slot.classList.add('is-dragover');
                });

                slot.addEventListener('dragleave', function (event) {
                    if (!slot.contains(event.relatedTarget)) {
                        slot.classList.remove('is-dragover');
                    }
                });

                slot.addEventListener('drop', function (event) {
                    event.preventDefault();
                    slot.classList.remove('is-dragover');

                    var files = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
                    if (!files || !files.length) return;

                    assignDroppedFile(input, files[0]);
                });
            });

            refreshAddButton();
        });
        </script>
        <?php
    }

    private static function get_blog_datetime_value($post) {
        if ($post && !empty($post->post_date) && $post->post_date !== '0000-00-00 00:00:00') {
            return mysql2date('Y-m-d\TH:i', $post->post_date, false);
        }

        return current_time('Y-m-d\TH:i');
    }

    private static function get_blog_post_date_from_request() {
        $raw_datetime = isset($_POST['scg_post_datetime']) ? sanitize_text_field(wp_unslash($_POST['scg_post_datetime'])) : '';

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $raw_datetime)) {
            return str_replace('T', ' ', $raw_datetime) . ':00';
        }

        return current_time('mysql');
    }

    private static function render_news_notice_form($config, $content_id, $post, $post_status, $is_edit) {
        $news_type = $post ? get_post_meta($content_id, '_scg_news_type', true) : 'blog';
        $news_type = in_array($news_type, ['blog', 'gallery', 'custom'], true) ? $news_type : 'blog';
        $display_date = $post ? get_the_date('Y-m-d', $post) : current_time('Y-m-d');
        $custom_text = $post ? $post->post_title : '';
        $link_url = $post ? get_post_meta($content_id, '_scg_news_link_url', true) : '';
        $gallery_parent_id = $post ? intval(get_post_meta($content_id, '_scg_news_gallery_parent', true)) : 0;
        $gallery_child_id = $post ? intval(get_post_meta($content_id, '_scg_news_gallery_child', true)) : 0;
        $legacy_gallery_term_id = $post ? intval(get_post_meta($content_id, '_scg_news_gallery_term', true)) : 0;
        if (!$gallery_child_id && $legacy_gallery_term_id) {
            $legacy_term = get_term($legacy_gallery_term_id, 'scg_gallery_category');
            if ($legacy_term && !is_wp_error($legacy_term)) {
                if (intval($legacy_term->parent) > 0) {
                    $gallery_child_id = intval($legacy_term->term_id);
                    if (!$gallery_parent_id) {
                        $gallery_parent_id = intval($legacy_term->parent);
                    }
                } elseif (!$gallery_parent_id) {
                    $gallery_parent_id = intval($legacy_term->term_id);
                }
            }
        }
        if ($gallery_child_id && !$gallery_parent_id) {
            $child_term = get_term($gallery_child_id, 'scg_gallery_category');
            if ($child_term && !is_wp_error($child_term)) {
                $gallery_parent_id = intval($child_term->parent);
            }
        }
        $gallery_count = $post ? intval(get_post_meta($content_id, '_scg_news_count', true)) : 1;
        if ($gallery_count <= 0) {
            $gallery_count = 1;
        }
        $terms = get_terms([
            'taxonomy' => 'scg_gallery_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $parent_terms = [];
        $children_by_parent = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (intval($term->parent) === 0) {
                    $parent_terms[] = $term;
                } else {
                    $parent_id = intval($term->parent);
                    if (!isset($children_by_parent[$parent_id])) {
                        $children_by_parent[$parent_id] = [];
                    }
                    $children_by_parent[$parent_id][] = $term;
                }
            }
        }
        $children_map = [];
        foreach ($children_by_parent as $parent_id => $children) {
            $children_map[$parent_id] = array_map(function ($term) {
                return [
                    'id' => intval($term->term_id),
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }, $children);
        }
        ?>
            <form class="scg-content-form scg-news-form scg-card" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('scg_save_content_news', 'scg_nonce'); ?>
                <input type="hidden" name="action" value="scg_save_content">
                <input type="hidden" name="scg_type" value="news">
                <input type="hidden" name="content_id" value="<?php echo esc_attr($content_id); ?>">

                <label class="scg-label" for="scg-news-date">表示日</label>
                <input type="date" id="scg-news-date" name="scg_news_date" class="scg-content-title" value="<?php echo esc_attr($display_date); ?>" required>

                <div class="scg-content-status-row">
                    <span class="scg-label scg-inline-label">公開状態</span>
                    <label><input type="radio" name="scg_post_status" value="publish" <?php checked($post_status, 'publish'); ?>> 公開</label>
                    <label><input type="radio" name="scg_post_status" value="draft" <?php checked($post_status, 'draft'); ?>> 下書き</label>
                </div>

                <div class="scg-news-type-selector">
                    <span class="scg-label scg-inline-label">お知らせタイプ</span>
                    <label><input type="radio" name="scg_news_type" value="blog" <?php checked($news_type, 'blog'); ?>> blog更新</label>
                    <label><input type="radio" name="scg_news_type" value="gallery" <?php checked($news_type, 'gallery'); ?>> ギャラリー追加</label>
                    <label><input type="radio" name="scg_news_type" value="custom" <?php checked($news_type, 'custom'); ?>> 自由入力</label>
                </div>

                <div class="scg-news-panel" data-news-panel="blog">
                    <h2>blog更新</h2>
                    <p class="scg-help">保存すると「blog を更新しました。」を自動生成します。</p>
                    <label class="scg-label" for="scg-news-blog-url">リンク先URL</label>
                    <input type="text" id="scg-news-blog-url" name="scg_news_blog_url" class="regular-text" value="<?php echo esc_attr($news_type === 'blog' && $link_url ? $link_url : home_url('/blog/')); ?>">
                </div>

                <div class="scg-news-panel" data-news-panel="gallery">
                    <h2>ギャラリー写真追加</h2>
                    <p class="scg-help">カテゴリと枚数から「Lake - Ezu に1カットアップ」のような文言を自動生成します。</p>
                    <label class="scg-label" for="scg-news-gallery-parent">親カテゴリー</label>
                    <select id="scg-news-gallery-parent" name="scg_news_gallery_parent" class="regular-text">
                        <option value="0">親カテゴリーを選択</option>
                        <?php foreach ($parent_terms as $term): ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($gallery_parent_id, $term->term_id); ?>>
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="scg-label" for="scg-news-gallery-child">子カテゴリー</label>
                    <select id="scg-news-gallery-child" name="scg_news_gallery_child" class="regular-text" <?php disabled(!$gallery_parent_id); ?>>
                        <option value="0">子カテゴリーを選択</option>
                        <?php if ($gallery_parent_id && isset($children_by_parent[$gallery_parent_id])): ?>
                            <?php foreach ($children_by_parent[$gallery_parent_id] as $term): ?>
                                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($gallery_child_id, $term->term_id); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <label class="scg-label" for="scg-news-gallery-count">追加枚数</label>
                    <input type="number" id="scg-news-gallery-count" name="scg_news_count" min="1" max="999" value="<?php echo esc_attr($gallery_count); ?>" class="small-text"> カット

                    <label class="scg-label" for="scg-news-gallery-base-url">ギャラリーページURL</label>
                    <input type="text" id="scg-news-gallery-base-url" name="scg_news_gallery_base_url" class="regular-text" value="<?php echo esc_attr(home_url('/gallery/')); ?>">
                </div>

                <div class="scg-news-panel" data-news-panel="custom">
                    <h2>自由入力</h2>
                    <label class="scg-label" for="scg-news-custom-text">表示文</label>
                    <input type="text" id="scg-news-custom-text" name="scg_news_custom_text" class="scg-content-title" value="<?php echo esc_attr($news_type === 'custom' ? $custom_text : ''); ?>" placeholder="表示したいお知らせ文を入力">

                    <label class="scg-label" for="scg-news-custom-url">リンク先URL（任意）</label>
                    <input type="text" id="scg-news-custom-url" name="scg_news_custom_url" class="regular-text" value="<?php echo esc_attr($news_type === 'custom' ? $link_url : ''); ?>" placeholder="https://... または /page/">

                    <label class="scg-label" for="scg-news-custom-link-text">リンク文字（任意）</label>
                    <input type="text" id="scg-news-custom-link-text" name="scg_news_custom_link_text" class="regular-text" value="<?php echo esc_attr($post ? get_post_meta($content_id, '_scg_news_link_text', true) : ''); ?>" placeholder="文中の一部だけリンクにする場合に入力">
                </div>

                <div class="scg-news-preview-box">
                    <span class="scg-label">表示イメージ</span>
                    <p class="scg-news-preview-text">保存後、information欄に表示されます。</p>
                </div>

                <div class="scg-content-actions">
                    <button type="submit" class="button button-primary button-large"><?php echo esc_html($is_edit ? '更新する' : '保存する'); ?></button>
                    <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=' . $config['list_page'])); ?>"><?php echo esc_html($config['list_label']); ?>へ戻る</a>
                </div>
            </form>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var form = document.querySelector('.scg-news-form');
                if (!form) return;
                var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="scg_news_type"]'));
                var panels = Array.prototype.slice.call(form.querySelectorAll('.scg-news-panel'));
                var preview = form.querySelector('.scg-news-preview-text');
                var childrenMap = <?php echo wp_json_encode($children_map, JSON_UNESCAPED_UNICODE); ?> || {};
                var parentSelect = form.querySelector('[name="scg_news_gallery_parent"]');
                var childSelect = form.querySelector('[name="scg_news_gallery_child"]');
                var initialChildId = '<?php echo esc_js((string) $gallery_child_id); ?>';

                function currentType() {
                    var checked = form.querySelector('input[name="scg_news_type"]:checked');
                    return checked ? checked.value : 'blog';
                }

                function updatePanels() {
                    var type = currentType();
                    panels.forEach(function (panel) {
                        panel.style.display = panel.getAttribute('data-news-panel') === type ? 'block' : 'none';
                    });
                    updatePreview();
                }

                function updateChildOptions(keepSelected) {
                    if (!parentSelect || !childSelect) return;
                    var parentId = parentSelect.value || '0';
                    var children = childrenMap[parentId] || [];
                    var currentValue = keepSelected ? childSelect.value : initialChildId;
                    childSelect.innerHTML = '<option value="0">子カテゴリーを選択</option>';
                    children.forEach(function (child) {
                        var option = document.createElement('option');
                        option.value = String(child.id);
                        option.textContent = child.name;
                        if (String(child.id) === String(currentValue)) {
                            option.selected = true;
                        }
                        childSelect.appendChild(option);
                    });
                    childSelect.disabled = !parentId || parentId === '0' || children.length === 0;
                    if (!keepSelected) {
                        initialChildId = '';
                    }
                }

                function selectedOptionText(select, fallback) {
                    if (!select || !select.options || select.selectedIndex < 0) return fallback;
                    var option = select.options[select.selectedIndex];
                    return option && option.value !== '0' ? option.text : fallback;
                }

                function updatePreview() {
                    if (!preview) return;
                    var type = currentType();
                    if (type === 'blog') {
                        preview.textContent = 'blog を更新しました。';
                        return;
                    }
                    if (type === 'gallery') {
                        var count = parseInt((form.querySelector('[name="scg_news_count"]') || {}).value || '1', 10);
                        var childLabel = selectedOptionText(childSelect, '');
                        var parentLabel = selectedOptionText(parentSelect, 'カテゴリ');
                        var label = childLabel || parentLabel || 'カテゴリ';
                        preview.textContent = label + ' に' + (count || 1) + 'カットアップ';
                        return;
                    }
                    var text = (form.querySelector('[name="scg_news_custom_text"]') || {}).value || '自由入力のお知らせ';
                    preview.textContent = text;
                }

                radios.forEach(function (radio) { radio.addEventListener('change', updatePanels); });
                if (parentSelect) {
                    parentSelect.addEventListener('change', function () {
                        initialChildId = '';
                        updateChildOptions(true);
                        updatePreview();
                    });
                }
                ['input', 'change'].forEach(function (eventName) {
                    form.addEventListener(eventName, function (event) {
                        if (event.target && event.target.closest('.scg-news-panel')) {
                            updatePreview();
                        }
                    });
                });
                updateChildOptions(false);
                updatePanels();
            });
            </script>
        <?php
    }

    private static function render_list_page($type) {
        if (!current_user_can('edit_posts')) {
            wp_die('権限がありません');
        }

        $config = self::get_config($type);
        if (!$config) {
            wp_die('設定が見つかりません');
        }

        $status = isset($_GET['scg_status']) ? sanitize_key($_GET['scg_status']) : 'active';
        $status = in_array($status, ['active', 'hidden'], true) ? $status : 'active';
        $message = isset($_GET['scg_message']) ? sanitize_key($_GET['scg_message']) : '';
        $error = isset($_GET['scg_error']) ? sanitize_key($_GET['scg_error']) : '';

        $query = new WP_Query([
            'post_type' => $config['post_type'],
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'meta_query' => [[
                'key' => '_scg_status',
                'value' => $status,
            ]],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap scg-wrap">
            <div class="scg-content-list-header">
                <div>
                    <h1><?php echo esc_html($config['list_label']); ?></h1>
                    <p class="scg-lead"><?php echo $status === 'hidden' ? '削除済みの記事を確認・復元できます。' : '記事の編集、下書き管理、削除済み移動ができます。'; ?></p>
                </div>
                <a class="button button-primary button-large" href="<?php echo esc_url(admin_url('admin.php?page=' . $config['add_page'])); ?>"><?php echo esc_html($config['write_label']); ?></a>
            </div>

            <?php self::render_notice($message, $error); ?>

            <div class="scg-content-tabs">
                <a class="button <?php echo $status === 'active' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $config['list_page'] . '&scg_status=active')); ?>">通常一覧</a>
                <a class="button <?php echo $status === 'hidden' ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . $config['list_page'] . '&scg_status=hidden')); ?>">削除済み一覧</a>
            </div>

            <div class="scg-card scg-content-list-card">
                <?php if ($query->have_posts()): ?>
                    <div class="scg-content-list">
                        <?php foreach ($query->posts as $post): ?>
                            <?php self::render_content_row($post, $config, $status); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="scg-empty"><?php echo esc_html($status === 'hidden' ? '削除済みの記事はありません。' : '記事はまだありません。'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_content_row($post, $config, $status) {
        $date = get_the_date('Y年n月j日', $post);
        $label = $post->post_status === 'publish' ? '公開' : '下書き';
        $image_count = 0;
        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            if (intval(get_post_meta($post->ID, '_scg_image_' . $i, true))) {
                $image_count++;
            }
        }

        $edit_url = admin_url('admin.php?page=' . $config['add_page'] . '&content_id=' . $post->ID);
        $hide_url = wp_nonce_url(admin_url('admin-post.php?action=scg_hide_content&scg_type=' . $config['type'] . '&content_id=' . $post->ID), 'scg_hide_content_' . $post->ID);
        $restore_url = wp_nonce_url(admin_url('admin-post.php?action=scg_restore_content&scg_type=' . $config['type'] . '&content_id=' . $post->ID), 'scg_restore_content_' . $post->ID);
        $delete_url = wp_nonce_url(admin_url('admin-post.php?action=scg_permanently_delete_content&scg_type=' . $config['type'] . '&content_id=' . $post->ID), 'scg_permanently_delete_content_' . $post->ID);
        ?>
        <article class="scg-content-row">
            <div class="scg-content-row-main">
                <div class="scg-content-row-date"><?php echo esc_html($date); ?></div>
                <h2><?php echo esc_html($post->post_title ?: '無題'); ?></h2>
                <div class="scg-content-row-meta">
                    <span class="scg-status-pill <?php echo $post->post_status === 'publish' ? 'is-publish' : 'is-draft'; ?>"><?php echo esc_html($label); ?></span>
                    <?php if (($config['type'] ?? '') === 'blog'): ?>
                        <span>画像 <?php echo esc_html($image_count); ?>枚</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="scg-content-row-actions">
                <?php if ($status === 'active'): ?>
                    <a class="button" href="<?php echo esc_url($edit_url); ?>">編集</a>
                    <a class="button scg-danger-link" href="<?php echo esc_url($hide_url); ?>" onclick="return confirm('この記事を削除済みに移動しますか？');">削除</a>
                <?php else: ?>
                    <a class="button button-primary" href="<?php echo esc_url($restore_url); ?>">復元</a>
                    <?php if (current_user_can('manage_options')): ?>
                        <a class="button scg-danger-link" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('完全削除します。元に戻せません。本当に削除しますか？');">完全削除</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private static function render_notice($message, $error) {
        $messages = [
            'saved' => '保存しました。',
            'hidden' => '削除済みに移動しました。',
            'restored' => '復元しました。',
            'deleted' => '完全削除しました。',
        ];
        $errors = [
            'invalid' => '処理できませんでした。',
            'permission' => '権限がありません。',
            'file_size' => 'サーバーのアップロード上限を超えています。PHP設定を確認してください。',
            'file_type' => '対応していない画像形式です。jpg / png / webp を選択してください。',
            'upload' => '画像のアップロードに失敗しました。',
        ];

        if ($message && isset($messages[$message])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$message]) . '</p></div>';
        }
        if ($error && isset($errors[$error])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($errors[$error]) . '</p></div>';
        }
    }

    public static function handle_save() {
        if (!current_user_can('edit_posts')) {
            self::safe_redirect_with_error('blog', 'permission');
        }

        $type = isset($_POST['scg_type']) ? sanitize_key($_POST['scg_type']) : '';
        $config = self::get_config($type);
        if (!$config || !isset($_POST['scg_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scg_nonce'])), 'scg_save_content_' . $type)) {
            self::safe_redirect_with_error($type ?: 'blog', 'invalid');
        }

        $content_id = isset($_POST['content_id']) ? absint($_POST['content_id']) : 0;
        $title = isset($_POST['scg_title']) ? sanitize_text_field(wp_unslash($_POST['scg_title'])) : '';
        $body = isset($_POST['scg_body']) ? sanitize_textarea_field(wp_unslash($_POST['scg_body'])) : '';
        $post_status = isset($_POST['scg_post_status']) ? sanitize_key($_POST['scg_post_status']) : 'publish';
        $post_status = in_array($post_status, ['publish', 'draft'], true) ? $post_status : 'publish';

        if ($type === 'news') {
            self::handle_save_news($config, $content_id, $post_status);
            return;
        }

        if ($title === '') {
            $title = '無題';
        }

        $post_date = self::get_blog_post_date_from_request();

        if ($content_id) {
            $existing = get_post($content_id);
            if (!$existing || $existing->post_type !== $config['post_type']) {
                self::safe_redirect_with_error($type, 'invalid');
            }

            $result = wp_update_post([
                'ID' => $content_id,
                'post_title' => $title,
                'post_content' => $body,
                'post_status' => $post_status,
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date($post_date),
            ], true);
        } else {
            $result = wp_insert_post([
                'post_type' => $config['post_type'],
                'post_title' => $title,
                'post_content' => $body,
                'post_status' => $post_status,
                'post_author' => get_current_user_id(),
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date($post_date),
            ], true);
        }

        if (is_wp_error($result)) {
            self::safe_redirect_with_error($type, 'invalid');
        }

        $content_id = intval($result);
        if (!get_post_meta($content_id, '_scg_status', true)) {
            update_post_meta($content_id, '_scg_status', 'active');
        }

        $image_error = self::handle_images($content_id);
        if ($image_error) {
            self::redirect_to_edit($config, $content_id, '', $image_error);
        }

        self::redirect_to_edit($config, $content_id, 'saved', '');
    }

    private static function handle_save_news($config, $content_id, $post_status) {
        $news_type = isset($_POST['scg_news_type']) ? sanitize_key(wp_unslash($_POST['scg_news_type'])) : 'blog';
        $news_type = in_array($news_type, ['blog', 'gallery', 'custom'], true) ? $news_type : 'blog';
        $date = isset($_POST['scg_news_date']) ? sanitize_text_field(wp_unslash($_POST['scg_news_date'])) : current_time('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = current_time('Y-m-d');
        }
        $time = current_time('H:i:s');
        if ($content_id) {
            $existing = get_post($content_id);
            if ($existing && !empty($existing->post_date)) {
                $time = mysql2date('H:i:s', $existing->post_date, false);
            }
        }
        $post_date = $date . ' ' . $time;
        $title = '';
        $link_url = '';
        $link_text = '';
        $gallery_parent_id = 0;
        $gallery_child_id = 0;
        $gallery_term_id = 0;
        $count = 0;

        if ($news_type === 'blog') {
            $title = 'blog を更新しました。';
            $link_text = 'blog';
            $link_url = isset($_POST['scg_news_blog_url']) ? esc_url_raw(wp_unslash($_POST['scg_news_blog_url'])) : home_url('/blog/');
            if (!$link_url) {
                $link_url = home_url('/blog/');
            }
        } elseif ($news_type === 'gallery') {
            $gallery_parent_id = isset($_POST['scg_news_gallery_parent']) ? absint($_POST['scg_news_gallery_parent']) : 0;
            $gallery_child_id = isset($_POST['scg_news_gallery_child']) ? absint($_POST['scg_news_gallery_child']) : 0;
            $count = isset($_POST['scg_news_count']) ? max(1, absint($_POST['scg_news_count'])) : 1;

            $parent_term = $gallery_parent_id ? get_term($gallery_parent_id, 'scg_gallery_category') : null;
            if (!$parent_term || is_wp_error($parent_term) || intval($parent_term->parent) !== 0) {
                self::safe_redirect_with_error('news', 'invalid');
            }

            $display_term = $parent_term;
            $child_term = null;
            if ($gallery_child_id) {
                $child_term = get_term($gallery_child_id, 'scg_gallery_category');
                if (!$child_term || is_wp_error($child_term) || intval($child_term->parent) !== intval($parent_term->term_id)) {
                    self::safe_redirect_with_error('news', 'invalid');
                }
                $display_term = $child_term;
            }

            $gallery_term_id = intval($display_term->term_id);
            $title = sprintf('%s に%dカットアップ', $display_term->name, $count);
            $link_text = $display_term->name;
            $base_url = isset($_POST['scg_news_gallery_base_url']) ? esc_url_raw(wp_unslash($_POST['scg_news_gallery_base_url'])) : home_url('/gallery/');
            if (!$base_url) {
                $base_url = home_url('/gallery/');
            }
            $args = ['scg_main' => $parent_term->slug];
            if ($child_term) {
                $args['scg_sub'] = $child_term->slug;
            }
            $link_url = add_query_arg($args, $base_url);
        } else {
            $title = isset($_POST['scg_news_custom_text']) ? sanitize_text_field(wp_unslash($_POST['scg_news_custom_text'])) : '';
            if ($title === '') {
                $title = 'お知らせを更新しました。';
            }
            $link_url = isset($_POST['scg_news_custom_url']) ? esc_url_raw(wp_unslash($_POST['scg_news_custom_url'])) : '';
            $link_text = isset($_POST['scg_news_custom_link_text']) ? sanitize_text_field(wp_unslash($_POST['scg_news_custom_link_text'])) : '';
        }

        $postarr = [
            'post_type' => $config['post_type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => $post_status,
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
        ];

        if ($content_id) {
            $existing = get_post($content_id);
            if (!$existing || $existing->post_type !== $config['post_type']) {
                self::safe_redirect_with_error('news', 'invalid');
            }
            $postarr['ID'] = $content_id;
            $result = wp_update_post($postarr, true);
        } else {
            $postarr['post_author'] = get_current_user_id();
            $result = wp_insert_post($postarr, true);
        }

        if (is_wp_error($result)) {
            self::safe_redirect_with_error('news', 'invalid');
        }

        $content_id = intval($result);
        update_post_meta($content_id, '_scg_status', 'active');
        update_post_meta($content_id, '_scg_news_type', $news_type);
        update_post_meta($content_id, '_scg_news_link_url', $link_url);
        update_post_meta($content_id, '_scg_news_link_text', $link_text);
        update_post_meta($content_id, '_scg_news_gallery_parent', $gallery_parent_id);
        update_post_meta($content_id, '_scg_news_gallery_child', $gallery_child_id);
        update_post_meta($content_id, '_scg_news_gallery_term', $gallery_term_id);
        update_post_meta($content_id, '_scg_news_count', $count);

        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            $image_id = intval(get_post_meta($content_id, '_scg_image_' . $i, true));
            if ($image_id) {
                wp_delete_attachment($image_id, true);
            }
            delete_post_meta($content_id, '_scg_image_' . $i);
        }

        self::redirect_to_edit($config, $content_id, 'saved', '');
    }

    private static function handle_images($content_id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            $meta_key = '_scg_image_' . $i;
            $existing_id = intval(get_post_meta($content_id, $meta_key, true));

            if (!empty($_POST['scg_remove_image_' . $i])) {
                if ($existing_id) {
                    wp_delete_attachment($existing_id, true);
                }
                delete_post_meta($content_id, $meta_key);
                continue;
            }

            $field = 'scg_image_' . $i;
            if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) {
                continue;
            }

            if (!empty($_FILES[$field]['error'])) {
                if (in_array(intval($_FILES[$field]['error']), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    return 'file_size';
                }
                return 'upload';
            }

            $file_type = wp_check_filetype_and_ext($_FILES[$field]['tmp_name'], $_FILES[$field]['name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (empty($file_type['type']) || !in_array($file_type['type'], $allowed, true)) {
                return 'file_type';
            }

            $_FILES[$field]['name'] = sanitize_file_name($_FILES[$field]['name']);
            $attachment_id = media_handle_upload($field, $content_id);
            if (is_wp_error($attachment_id)) {
                return 'upload';
            }

            SCG_Image_Optimizer::optimize_attachment($attachment_id);

            if ($existing_id && $existing_id !== intval($attachment_id)) {
                wp_delete_attachment($existing_id, true);
            }

            update_post_meta($content_id, $meta_key, $attachment_id);
        }

        return '';
    }

    public static function handle_hide() {
        self::handle_status_change('hidden', 'hidden');
    }

    public static function handle_restore() {
        self::handle_status_change('active', 'restored');
    }

    private static function handle_status_change($new_status, $message) {
        if (!current_user_can('edit_posts')) {
            self::safe_redirect_with_error('blog', 'permission');
        }

        $type = isset($_GET['scg_type']) ? sanitize_key($_GET['scg_type']) : '';
        $config = self::get_config($type);
        $content_id = isset($_GET['content_id']) ? absint($_GET['content_id']) : 0;

        if (!$config || !$content_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'scg_' . ($new_status === 'hidden' ? 'hide' : 'restore') . '_content_' . $content_id)) {
            self::safe_redirect_with_error($type ?: 'blog', 'invalid');
        }

        $post = get_post($content_id);
        if (!$post || $post->post_type !== $config['post_type']) {
            self::safe_redirect_with_error($type, 'invalid');
        }

        update_post_meta($content_id, '_scg_status', $new_status);
        $url = admin_url('admin.php?page=' . $config['list_page'] . '&scg_status=' . ($new_status === 'hidden' ? 'active' : 'hidden') . '&scg_message=' . $message);
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_permanently_delete() {
        if (!current_user_can('manage_options')) {
            self::safe_redirect_with_error('blog', 'permission');
        }

        $type = isset($_GET['scg_type']) ? sanitize_key($_GET['scg_type']) : '';
        $config = self::get_config($type);
        $content_id = isset($_GET['content_id']) ? absint($_GET['content_id']) : 0;

        if (!$config || !$content_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'scg_permanently_delete_content_' . $content_id)) {
            self::safe_redirect_with_error($type ?: 'blog', 'invalid');
        }

        $post = get_post($content_id);
        if (!$post || $post->post_type !== $config['post_type']) {
            self::safe_redirect_with_error($type, 'invalid');
        }

        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            $image_id = intval(get_post_meta($content_id, '_scg_image_' . $i, true));
            if ($image_id) {
                wp_delete_attachment($image_id, true);
            }
        }

        wp_delete_post($content_id, true);
        wp_safe_redirect(admin_url('admin.php?page=' . $config['list_page'] . '&scg_status=hidden&scg_message=deleted'));
        exit;
    }

    private static function redirect_to_edit($config, $content_id, $message = '', $error = '') {
        $args = [
            'page' => $config['add_page'],
            'content_id' => $content_id,
        ];
        if ($message) {
            $args['scg_message'] = $message;
        }
        if ($error) {
            $args['scg_error'] = $error;
        }

        $url = add_query_arg($args, admin_url('admin.php'));
        if (self::is_ajax_save_request()) {
            wp_send_json_success(['redirect' => $url]);
        }

        wp_safe_redirect($url);
        exit;
    }

    private static function safe_redirect_with_error($type, $error) {
        $config = self::get_config($type) ?: self::get_config('blog');
        $url = admin_url('admin.php?page=' . $config['list_page'] . '&scg_error=' . $error);
        if (self::is_ajax_save_request()) {
            wp_send_json_error([
                'error' => $error,
                'message' => self::get_error_message($error),
                'redirect' => $url,
            ]);
        }

        wp_safe_redirect($url);
        exit;
    }

    private static function is_ajax_save_request() {
        return !empty($_POST['scg_ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH']))) === 'xmlhttprequest');
    }

    private static function get_error_message($error) {
        $errors = [
            'invalid' => '処理できませんでした。入力内容を確認してください。',
            'permission' => '権限がありません。',
            'file_size' => 'サーバーのアップロード上限を超えています。PHP設定を確認してください。',
            'file_type' => '対応していない画像形式です。jpg / png / webp を選択してください。',
            'upload' => '画像のアップロードに失敗しました。',
        ];
        return $errors[$error] ?? '保存に失敗しました。';
    }
}
