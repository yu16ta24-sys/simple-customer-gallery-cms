<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Top_Slider {
    const OPTION_ITEMS = 'scg_top_slider_items';
    const OPTION_INTERVAL = 'scg_top_slider_interval';
    const OPTION_FADE = 'scg_top_slider_fade';
    const MAX_ITEMS = 12;
    const META_PC_X = '_scg_slider_pc_x';
    const META_PC_Y = '_scg_slider_pc_y';
    const META_PC_ZOOM = '_scg_slider_pc_zoom';
    const META_TABLET_X = '_scg_slider_tablet_x';
    const META_TABLET_Y = '_scg_slider_tablet_y';
    const META_TABLET_ZOOM = '_scg_slider_tablet_zoom';
    const META_MOBILE_X = '_scg_slider_mobile_x';
    const META_MOBILE_Y = '_scg_slider_mobile_y';
    const META_MOBILE_ZOOM = '_scg_slider_mobile_zoom';

    public static function init() {
        add_option(self::OPTION_INTERVAL, 5000);
        add_option(self::OPTION_FADE, 900);
        add_shortcode('scg_top_slider', [__CLASS__, 'render_shortcode']);
        add_action('wp_ajax_scg_top_slider_upload', [__CLASS__, 'ajax_upload']);
        add_action('wp_ajax_scg_top_slider_delete', [__CLASS__, 'ajax_delete']);
        add_action('wp_ajax_scg_top_slider_save_order', [__CLASS__, 'ajax_save_order']);
        add_action('wp_ajax_scg_top_slider_save_position', [__CLASS__, 'ajax_save_position']);
    }

    public static function get_settings() {
        return [
            'interval' => max(2000, min(12000, (int) get_option(self::OPTION_INTERVAL, 5000))),
            'fade' => max(200, min(2500, (int) get_option(self::OPTION_FADE, 900))),
        ];
    }

    public static function get_items() {
        $items = get_option(self::OPTION_ITEMS, []);
        if (!is_array($items)) {
            $items = [];
        }

        $items = array_values(array_filter(array_map('absint', $items)));
        $items = array_values(array_unique($items));

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    public static function render_admin_page() {
        if (!current_user_can('upload_files')) {
            wp_die(esc_html__('このページを表示する権限がありません。', 'simple-customer-gallery-cms'));
        }

        $message = '';
        $error = '';

        if (isset($_POST['scg_top_slider_submit'])) {
            check_admin_referer('scg_save_top_slider', 'scg_top_slider_nonce');
            $result = self::handle_save();
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $message = '表示設定を保存しました。';
            }
        }

        $items = self::get_items();
        $settings = self::get_settings();
        ?>
        <div class="wrap scg-wrap scg-slider-admin-wrap">
            <h1>トップスライダー管理</h1>
            <p class="scg-lead">トップページ用スライダー画像を最大12枚まで管理できます。画像は追加時に自動アップロードされ、ドラッグで並び替えできます。</p>

            <?php if ($message): ?>
                <div class="notice notice-success is-dismissible scg-keep-notice"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error is-dismissible scg-keep-notice"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="scg-slider-form">
                <?php wp_nonce_field('scg_save_top_slider', 'scg_top_slider_nonce'); ?>

                <section class="scg-panel scg-slider-panel">
                    <div class="scg-slider-admin-head">
                        <div>
                            <h2>登録中の画像</h2>
                            <p>現在 <strong data-scg-slider-count><?php echo esc_html(count($items)); ?></strong> / <?php echo esc_html(self::MAX_ITEMS); ?> 枚</p>
                        </div>
                        <code>[scg_top_slider]</code>
                    </div>

                    <div class="scg-slider-settings-box">
                        <h2>表示設定</h2>
                        <p>トップページに表示するスライダーの切り替え速度を調整できます。</p>

                        <?php self::render_range_setting('画像の表示時間', 'scg_top_slider_interval', $settings['interval'], 2000, 12000, 500, 'ミリ秒', '1枚の画像を表示しておく時間です。'); ?>
                        <?php self::render_range_setting('フェード時間', 'scg_top_slider_fade', $settings['fade'], 200, 2500, 100, 'ミリ秒', '画像が切り替わる時の演出時間です。'); ?>
                        <div class="scg-slider-settings-actions">
                            <button type="submit" name="scg_top_slider_submit" value="1" class="button button-primary button-large">表示設定を保存</button>
                        </div>
                    </div>

                    <ul class="scg-slider-list" id="scg-slider-list" data-count="<?php echo esc_attr(count($items)); ?>" data-max="<?php echo esc_attr(self::MAX_ITEMS); ?>">
                        <?php if ($items): ?>
                            <?php foreach ($items as $attachment_id): ?>
                                <?php self::render_slider_row($attachment_id); ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="scg-slider-empty">まだ画像が登録されていません。下の「画像を追加」から登録してください。</li>
                        <?php endif; ?>
                    </ul>

                    <div class="scg-slider-add-box">
                        <h2>画像を追加</h2>
                        <p>追加できる残り枚数は <strong data-scg-slider-remaining><?php echo esc_html(max(0, self::MAX_ITEMS - count($items))); ?></strong> 枚です。</p>
                        <label class="scg-slider-file-drop" for="scg-slider-new-files">
                            <span class="scg-slider-file-drop-title">画像を選択またはドラッグ&ドロップ</span>
                            <span class="scg-slider-file-drop-sub">jpg / png / webp、最大20MBまで</span>
                            <input id="scg-slider-new-files" type="file" name="scg_slider_new[]" accept="image/jpeg,image/png,image/webp" multiple>
                        </label>
                        <p class="description">画像を選択すると自動でアップロードされ、一覧に追加されます。</p>
                    </div>

                </section>
            </form>
        </div>
        <?php
    }

    private static function get_device_config() {
        return [
            'pc' => [
                'label' => 'PC',
                'ratio' => '3:1',
                'meta_x' => self::META_PC_X,
                'meta_y' => self::META_PC_Y,
                'meta_zoom' => self::META_PC_ZOOM,
            ],
            'tablet' => [
                'label' => 'タブレット',
                'ratio' => '16:7',
                'meta_x' => self::META_TABLET_X,
                'meta_y' => self::META_TABLET_Y,
                'meta_zoom' => self::META_TABLET_ZOOM,
            ],
            'mobile' => [
                'label' => 'スマホ',
                'ratio' => '1:1',
                'meta_x' => self::META_MOBILE_X,
                'meta_y' => self::META_MOBILE_Y,
                'meta_zoom' => self::META_MOBILE_ZOOM,
            ],
        ];
    }

    private static function get_slide_adjustments($attachment_id) {
        $values = [];
        foreach (self::get_device_config() as $device => $config) {
            $x = get_post_meta($attachment_id, $config['meta_x'], true);
            $y = get_post_meta($attachment_id, $config['meta_y'], true);
            $zoom = get_post_meta($attachment_id, $config['meta_zoom'], true);

            $values[$device] = [
                'x' => ($x === '' || $x === null) ? 50 : max(0, min(100, (int) $x)),
                'y' => ($y === '' || $y === null) ? 50 : max(0, min(100, (int) $y)),
                'zoom' => ($zoom === '' || $zoom === null) ? 100 : max(100, min(200, (int) $zoom)),
            ];
        }
        return $values;
    }

    private static function render_adjustment_controls($attachment_id, $image_url) {
        $values = self::get_slide_adjustments($attachment_id);
        $devices = self::get_device_config();
        ?>
        <div class="scg-slider-adjust-panel" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" hidden>
            <div class="scg-slider-adjust-layout">
                <div class="scg-slider-adjust-previews">
                    <p class="scg-slider-adjust-title">表示プレビュー</p>
                    <?php foreach ($devices as $device => $config): ?>
                        <?php $v = $values[$device]; ?>
                        <div class="scg-slider-device-preview scg-slider-device-preview-<?php echo esc_attr($device); ?>" data-preview-device="<?php echo esc_attr($device); ?>">
                            <div class="scg-slider-device-label"><?php echo esc_html($config['label']); ?> <span><?php echo esc_html($config['ratio']); ?></span></div>
                            <div class="scg-slider-device-frame" style="--scg-preview-ratio: <?php echo esc_attr(str_replace(':', ' / ', $config['ratio'])); ?>;">
                                <?php $preview_shift_x = (50 - (int) $v['x']) * 0.24; $preview_shift_y = (50 - (int) $v['y']) * 0.24; ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" style="object-position: <?php echo esc_attr($v['x']); ?>% <?php echo esc_attr($v['y']); ?>%; transform-origin: center center; transform: translate(<?php echo esc_attr($preview_shift_x); ?>%, <?php echo esc_attr($preview_shift_y); ?>%) scale(<?php echo esc_attr($v['zoom'] / 100); ?>);">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="scg-slider-adjust-controls">
                    <p class="scg-slider-adjust-title">表示位置の調整</p>
                    <?php foreach ($devices as $device => $config): ?>
                        <?php $v = $values[$device]; ?>
                        <div class="scg-slider-device-controls" data-control-device="<?php echo esc_attr($device); ?>">
                            <h3><?php echo esc_html($config['label']); ?></h3>
                            <?php
                            self::render_adjust_range($attachment_id, $device, 'x', '左右位置', $v['x'], 0, 100, 1, '%');
                            self::render_adjust_range($attachment_id, $device, 'y', '上下位置', $v['y'], 0, 100, 1, '%');
                            self::render_adjust_range($attachment_id, $device, 'zoom', '拡大率', $v['zoom'], 100, 200, 1, '%');
                            ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="scg-slider-adjust-actions">
                        <button type="button" class="button button-primary scg-slider-adjust-save" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">表示位置を保存</button>
                        <span class="scg-slider-adjust-status" aria-live="polite"></span>
                    </div>
                </div>
            </div>
            <p class="scg-slider-position-help">PCは3:1、タブレットは16:7、スマホは1:1の枠で表示されます。拡大率は余白が出ない100%以上に制限しています。</p>
        </div>
        <?php
    }

    private static function render_adjust_range($attachment_id, $device, $field, $label, $value, $min, $max, $step, $unit) {
        ?>
        <label class="scg-slider-adjust-row">
            <span><?php echo esc_html($label); ?></span>
            <input class="scg-slider-adjust-range" type="range" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($value); ?>" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-device="<?php echo esc_attr($device); ?>" data-field="<?php echo esc_attr($field); ?>">
            <output><?php echo esc_html($value . $unit); ?></output>
        </label>
        <?php
    }

    private static function render_slider_row($attachment_id) {
        $thumb = wp_get_attachment_image_url($attachment_id, 'medium');
        $full = wp_get_attachment_image_url($attachment_id, 'full');
        $title = get_the_title($attachment_id);
        ?>
        <li class="scg-slider-row" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
            <input type="hidden" name="scg_slider_existing_ids[]" value="<?php echo esc_attr($attachment_id); ?>">
            <div class="scg-slider-drag" title="ドラッグで並び替え">≡</div>
            <div class="scg-slider-thumb">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="">
                <?php else: ?>
                    <span>No image</span>
                <?php endif; ?>
            </div>
            <div class="scg-slider-meta">
                <strong><?php echo esc_html($title ?: 'スライダー画像'); ?></strong>
                <?php if ($full): ?>
                    <a href="<?php echo esc_url($full); ?>" target="_blank" rel="noopener">画像を確認</a>
                <?php endif; ?>
                <button type="button" class="button scg-slider-adjust-toggle" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">表示位置を調整する</button>
                <?php self::render_adjustment_controls($attachment_id, $full ?: $thumb); ?>
            </div>
            <button type="button" class="button scg-slider-delete-button" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">この画像を削除</button>
        </li>
        <?php
    }

    private static function render_range_setting($label, $name, $value, $min, $max, $step, $unit, $description) {
        ?>
        <div class="scg-slider-setting-row">
            <div class="scg-slider-setting-label">
                <strong><?php echo esc_html($label); ?></strong>
                <span><?php echo esc_html($description); ?></span>
            </div>
            <div class="scg-slider-setting-control">
                <input class="scg-slider-range" type="range" name="<?php echo esc_attr($name); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" value="<?php echo esc_attr($value); ?>">
                <output class="scg-slider-range-output" data-scg-slider-range-output="<?php echo esc_attr($name); ?>"><?php echo esc_html($value . $unit); ?></output>
            </div>
        </div>
        <?php
    }

    private static function handle_save() {
        if (!current_user_can('upload_files')) {
            return new WP_Error('scg_forbidden', '保存する権限がありません。');
        }

        $interval = isset($_POST['scg_top_slider_interval']) ? (int) wp_unslash($_POST['scg_top_slider_interval']) : 5000;
        $fade = isset($_POST['scg_top_slider_fade']) ? (int) wp_unslash($_POST['scg_top_slider_fade']) : 900;
        update_option(self::OPTION_INTERVAL, max(2000, min(12000, $interval)));
        update_option(self::OPTION_FADE, max(200, min(2500, $fade)));

        return true;
    }

    private static function handle_single_upload($field_name) {
        if (empty($_FILES[$field_name]) || !empty($_FILES[$field_name]['error'])) {
            return new WP_Error('scg_upload_error', '画像のアップロードに失敗しました。');
        }

        $file = $_FILES[$field_name];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed, true)) {
            return new WP_Error('scg_invalid_type', 'jpg / png / webp の画像を選択してください。');
        }

        if (!empty($file['size']) && $file['size'] > 20 * 1024 * 1024) {
            return new WP_Error('scg_file_too_large', '画像サイズが大きすぎます。20MB以内の画像を選択してください。');
        }

        $attachment_id = media_handle_upload($field_name, 0);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        return (int) $attachment_id;
    }

    public static function ajax_upload() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'アップロードする権限がありません。']);
        }
        check_ajax_referer('scg_top_slider_ajax', 'nonce');

        $items = self::get_items();
        if (count($items) >= self::MAX_ITEMS) {
            wp_send_json_error(['message' => '登録できる画像は最大12枚までです。']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded_id = self::handle_single_upload('file');
        if (is_wp_error($uploaded_id)) {
            wp_send_json_error(['message' => $uploaded_id->get_error_message()]);
        }

        /*
         * v1.6.5:
         * Upload requests can run in parallel. To avoid concurrent option writes
         * overwriting each other, the upload endpoint only creates the attachment.
         * The final slider composition is saved once by ajax_save_order after all
         * selected files have finished uploading.
         */
        $items[] = $uploaded_id;
        $items = array_slice(array_values(array_unique(array_filter(array_map('absint', $items)))), 0, self::MAX_ITEMS);

        ob_start();
        self::render_slider_row($uploaded_id);
        $row = ob_get_clean();

        wp_send_json_success([
            'id' => $uploaded_id,
            'row' => $row,
            'count' => count($items),
            'remaining' => max(0, self::MAX_ITEMS - count($items)),
        ]);
    }

    public static function ajax_delete() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '削除する権限がありません。']);
        }
        check_ajax_referer('scg_top_slider_ajax', 'nonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint(wp_unslash($_POST['attachment_id'])) : 0;
        if (!$attachment_id) {
            wp_send_json_error(['message' => '削除対象の画像が見つかりません。']);
        }

        $items = array_values(array_diff(self::get_items(), [$attachment_id]));
        update_option(self::OPTION_ITEMS, $items);
        wp_delete_attachment($attachment_id, true);

        wp_send_json_success([
            'count' => count($items),
            'remaining' => max(0, self::MAX_ITEMS - count($items)),
        ]);
    }

    public static function ajax_save_order() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '並び替えを保存する権限がありません。']);
        }
        check_ajax_referer('scg_top_slider_ajax', 'nonce');

        $order = isset($_POST['order']) && is_array($_POST['order'])
            ? array_map('absint', wp_unslash($_POST['order']))
            : [];
        $current = self::get_items();
        $ordered = [];

        foreach ($order as $attachment_id) {
            if (!$attachment_id || in_array($attachment_id, $ordered, true)) {
                continue;
            }

            $attachment = get_post($attachment_id);
            if ($attachment && $attachment->post_type === 'attachment') {
                $ordered[] = $attachment_id;
            }
        }

        foreach ($current as $attachment_id) {
            if (!in_array($attachment_id, $ordered, true)) {
                $ordered[] = $attachment_id;
            }
        }

        update_option(self::OPTION_ITEMS, array_slice(array_values(array_unique($ordered)), 0, self::MAX_ITEMS));

        wp_send_json_success(['message' => '並び順を保存しました。']);
    }

    public static function ajax_save_position() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '表示位置を保存する権限がありません。']);
        }
        check_ajax_referer('scg_top_slider_ajax', 'nonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint(wp_unslash($_POST['attachment_id'])) : 0;
        if (!$attachment_id || !in_array($attachment_id, self::get_items(), true)) {
            wp_send_json_error(['message' => '対象のスライダー画像が見つかりません。']);
        }

        $devices = self::get_device_config();
        $saved = [];
        foreach ($devices as $device => $config) {
            $raw = isset($_POST[$device]) && is_array($_POST[$device]) ? wp_unslash($_POST[$device]) : [];
            $x = isset($raw['x']) ? (int) $raw['x'] : 50;
            $y = isset($raw['y']) ? (int) $raw['y'] : 50;
            $zoom = isset($raw['zoom']) ? (int) $raw['zoom'] : 100;

            $x = max(0, min(100, $x));
            $y = max(0, min(100, $y));
            $zoom = max(100, min(200, $zoom));

            update_post_meta($attachment_id, $config['meta_x'], $x);
            update_post_meta($attachment_id, $config['meta_y'], $y);
            update_post_meta($attachment_id, $config['meta_zoom'], $zoom);

            $saved[$device] = [
                'x' => $x,
                'y' => $y,
                'zoom' => $zoom,
            ];
        }

        wp_send_json_success([
            'message' => '表示位置を保存しました。',
            'values' => $saved,
        ]);
    }

    public static function render_shortcode($atts = []) {
        $settings = self::get_settings();
        $atts = shortcode_atts([
            'class' => '',
            'autoplay' => 'true',
            'interval' => (string) $settings['interval'],
            'fade' => (string) $settings['fade'],
        ], $atts, 'scg_top_slider');

        $items = self::get_items();
        if (!$items) {
            return '';
        }

        wp_enqueue_style('scg-top-slider', SCG_CMS_URL . 'assets/css/top-slider.css', [], SCG_CMS_VERSION);
        wp_enqueue_script('scg-top-slider', SCG_CMS_URL . 'assets/js/top-slider.js', [], SCG_CMS_VERSION, true);

        $slides = [];
        foreach ($items as $attachment_id) {
            $image = wp_get_attachment_image_url($attachment_id, 'full');
            if (!$image) {
                continue;
            }
            $slides[] = [
                'id' => $attachment_id,
                'url' => $image,
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: get_the_title($attachment_id),
                'adjust' => self::get_slide_adjustments($attachment_id),
            ];
        }

        if (!$slides) {
            return '';
        }

        $instance_id = 'scg-top-slider-' . wp_generate_uuid4();
        $classes = trim('scg-top-slider ' . sanitize_html_class($atts['class']));
        $autoplay = filter_var($atts['autoplay'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        $interval = max(2000, min(12000, (int) $atts['interval']));
        $fade = max(200, min(2500, (int) $atts['fade']));

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>"
             class="<?php echo esc_attr($classes); ?>"
             data-autoplay="<?php echo esc_attr($autoplay); ?>"
             data-interval="<?php echo esc_attr($interval); ?>"
             data-fade="<?php echo esc_attr($fade); ?>"
             style="--scg-top-slider-fade-ms: <?php echo esc_attr($fade); ?>ms;">
            <div class="scg-top-slider-track">
                <?php foreach ($slides as $index => $slide): ?>
                    <?php
                    $pc_tx = (50 - (int) $slide['adjust']['pc']['x']) * 0.24;
                    $pc_ty = (50 - (int) $slide['adjust']['pc']['y']) * 0.24;
                    $tablet_tx = (50 - (int) $slide['adjust']['tablet']['x']) * 0.24;
                    $tablet_ty = (50 - (int) $slide['adjust']['tablet']['y']) * 0.24;
                    $mobile_tx = (50 - (int) $slide['adjust']['mobile']['x']) * 0.24;
                    $mobile_ty = (50 - (int) $slide['adjust']['mobile']['y']) * 0.24;
                    ?>
                    <figure class="scg-top-slide <?php echo $index === 0 ? 'is-active' : ''; ?>" data-index="<?php echo esc_attr($index); ?>" style="--scg-slide-x-pc: <?php echo esc_attr($slide['adjust']['pc']['x']); ?>%; --scg-slide-y-pc: <?php echo esc_attr($slide['adjust']['pc']['y']); ?>%; --scg-slide-zoom-pc: <?php echo esc_attr($slide['adjust']['pc']['zoom'] / 100); ?>; --scg-slide-tx-pc: <?php echo esc_attr($pc_tx); ?>%; --scg-slide-ty-pc: <?php echo esc_attr($pc_ty); ?>%; --scg-slide-x-tablet: <?php echo esc_attr($slide['adjust']['tablet']['x']); ?>%; --scg-slide-y-tablet: <?php echo esc_attr($slide['adjust']['tablet']['y']); ?>%; --scg-slide-zoom-tablet: <?php echo esc_attr($slide['adjust']['tablet']['zoom'] / 100); ?>; --scg-slide-tx-tablet: <?php echo esc_attr($tablet_tx); ?>%; --scg-slide-ty-tablet: <?php echo esc_attr($tablet_ty); ?>%; --scg-slide-x-mobile: <?php echo esc_attr($slide['adjust']['mobile']['x']); ?>%; --scg-slide-y-mobile: <?php echo esc_attr($slide['adjust']['mobile']['y']); ?>%; --scg-slide-zoom-mobile: <?php echo esc_attr($slide['adjust']['mobile']['zoom'] / 100); ?>; --scg-slide-tx-mobile: <?php echo esc_attr($mobile_tx); ?>%; --scg-slide-ty-mobile: <?php echo esc_attr($mobile_ty); ?>%;">
                        <img src="<?php echo esc_url($slide['url']); ?>" alt="<?php echo esc_attr($slide['alt']); ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if (count($slides) > 1): ?>
                <button class="scg-top-slider-nav scg-top-slider-prev" type="button" aria-label="前の画像へ">‹</button>
                <button class="scg-top-slider-nav scg-top-slider-next" type="button" aria-label="次の画像へ">›</button>
                <div class="scg-top-slider-dots" aria-label="スライダーのページ送り">
                    <?php foreach ($slides as $index => $slide): ?>
                        <button class="scg-top-slider-dot <?php echo $index === 0 ? 'is-active' : ''; ?>" type="button" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php echo esc_attr(($index + 1) . '枚目を表示'); ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
