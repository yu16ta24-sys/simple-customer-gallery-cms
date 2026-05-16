<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Gallery_Manage {
    public static function init() {
        add_action('wp_ajax_scg_get_photos', [__CLASS__, 'ajax_get_photos']);
        add_action('wp_ajax_scg_save_photo_order', [__CLASS__, 'ajax_save_order']);
        add_action('wp_ajax_scg_update_photo_description', [__CLASS__, 'ajax_update_description']);
        add_action('wp_ajax_scg_delete_photo', [__CLASS__, 'ajax_delete_photo']);
        add_action('wp_ajax_scg_restore_photo', [__CLASS__, 'ajax_restore_photo']);
        add_action('wp_ajax_scg_permanently_delete_photo', [__CLASS__, 'ajax_permanently_delete_photo']);
    }

    public static function get_main_categories() {
        return get_terms([
            'taxonomy' => 'scg_gallery_category',
            'hide_empty' => false,
            'parent' => 0,
            'orderby' => 'term_order',
            'order' => 'ASC',
        ]);
    }

    public static function get_category_map() {
        $parents = self::get_main_categories();
        $map = [];

        if (is_wp_error($parents)) {
            return $map;
        }

        foreach ($parents as $parent) {
            $children = get_terms([
                'taxonomy' => 'scg_gallery_category',
                'hide_empty' => false,
                'parent' => $parent->term_id,
                'orderby' => 'term_order',
                'order' => 'ASC',
            ]);

            $map[$parent->term_id] = [
                'name' => $parent->name,
                'children' => [],
            ];

            if (!is_wp_error($children)) {
                foreach ($children as $child) {
                    $map[$parent->term_id]['children'][] = [
                        'id' => $child->term_id,
                        'name' => $child->name,
                    ];
                }
            }
        }

        return $map;
    }

    public static function render_manage_page() {
        if (!current_user_can('upload_files')) {
            wp_die('権限がありません');
        }

        $main_categories = self::get_main_categories();
        $category_map = self::get_category_map();
        $can_permanent_delete = current_user_can('manage_options');
        ?>
        <div class="wrap scg-wrap">
            <h1>ギャラリー管理</h1>
            <div class="scg-card">
                <div class="scg-category-button-section">
                    <div class="scg-category-heading">メインカテゴリー</div>
                    <div id="scg-manage-main-buttons" class="scg-category-buttons scg-main-category-buttons">
                        <?php if (!is_wp_error($main_categories)): ?>
                            <?php foreach ($main_categories as $cat): ?>
                                <button type="button" class="scg-category-button scg-main-category-button" data-id="<?php echo esc_attr($cat->term_id); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="scg-category-heading scg-sub-heading">サブカテゴリー</div>
                    <div id="scg-manage-sub-buttons" class="scg-category-buttons scg-sub-category-buttons">
                        <span class="scg-category-placeholder">メインカテゴリーを選択してください</span>
                    </div>

                    <input type="hidden" id="scg-manage-main-category" value="">
                    <input type="hidden" id="scg-manage-sub-category" value="">
                </div>
<p id="scg-manage-help-active" class="scg-help">画像はドラッグで並び替えできます。画像をクリックすると説明文編集と削除ができます。</p>
                <p id="scg-manage-help-hidden" class="scg-help" style="display:none;">復元モード中です。削除済み画像を選択すると復元できます。完全削除は管理者のみ実行できます。</p>

                <div id="scg-manage-message" class="scg-upload-message"></div>
                <div id="scg-photo-grid" class="scg-photo-grid"></div>

                <div id="scg-inline-upload-area" class="scg-inline-upload-area" style="display:none;">
                    <div id="scg-inline-dropzone" class="scg-dropzone scg-inline-dropzone">
                        <strong>ここに画像をドラッグ</strong>
                        <span>または下のボタンから選択</span>
                        <label for="scg-inline-photo-files" id="scg-inline-file-select-button" class="button scg-file-select-button">画像を選択</label>
                        <input type="file" id="scg-inline-photo-files" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                    <p class="scg-help">最大10枚まで。対応形式：jpg / png / webp</p>
                    <div id="scg-inline-selected-files" class="scg-selected-files"></div>
                    <button type="button" id="scg-inline-upload-submit" class="button button-primary button-large">アップロードする</button>
                </div>

                <div class="scg-bottom-actions">
                    <button type="button" id="scg-inline-upload-toggle" class="button scg-bottom-upload-button">写真を追加（アップロード）する</button>
                    <button type="button" id="scg-restore-mode-toggle" class="button scg-restore-mode-button" data-mode="active">削除済み画像復元モード</button>
                </div>
            </div>
        </div>
        <script>
            window.SCG_CATEGORY_MAP = <?php echo wp_json_encode($category_map); ?>;
            window.SCG_CAN_PERMANENT_DELETE = <?php echo $can_permanent_delete ? 'true' : 'false'; ?>;
        </script>
        <?php
    }

    private static function user_can_edit_photo($photo_id) {
        // このCMSでは「個人別の画像管理」ではなく「サイト全体のギャラリー管理」として扱う。
        // そのため、アップロード権限を持つユーザーは全画像の編集・並び替え・削除済み移動・復元が可能。
        // 完全削除のみ ajax_permanently_delete_photo 側で管理者限定にする。
        return current_user_can('upload_files');
    }

    public static function ajax_get_photos() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $sub_category = isset($_POST['sub_category']) ? absint($_POST['sub_category']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'active';
        $status = in_array($status, ['active', 'hidden'], true) ? $status : 'active';

        if (!$sub_category) {
            wp_send_json_error(['message' => 'サブカテゴリを選択してください']);
        }

        $meta_query = [[
            'key' => '_scg_status',
            'value' => $status,
        ]];


        $query = new WP_Query([
            'post_type' => 'scg_photo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => 'scg_gallery_category',
                'field' => 'term_id',
                'terms' => $sub_category,
            ]],
            'meta_query' => $meta_query,
            'meta_key' => '_scg_order',
            'meta_type' => 'NUMERIC',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        $items = [];

        foreach ($query->posts as $post) {
            $attachment_id = intval(get_post_meta($post->ID, '_scg_attachment_id', true));
            $thumb = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
            $full = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'large') : '';

            $items[] = [
                'id' => $post->ID,
                'thumb' => $thumb,
                'full' => $full,
                'description' => $post->post_content,
                'status' => $status,
            ];
        }

        wp_send_json_success(['items' => $items, 'status' => $status]);
    }

    public static function ajax_save_order() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : [];

        foreach ($ids as $index => $photo_id) {
            if (!self::user_can_edit_photo($photo_id)) {
                continue;
            }
            update_post_meta($photo_id, '_scg_order', $index + 1);
        }

        wp_send_json_success(['message' => '並び順を保存しました']);
    }

    public static function ajax_update_description() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if (!$photo_id || !self::user_can_edit_photo($photo_id)) {
            wp_send_json_error(['message' => 'この写真は編集できません']);
        }

        wp_update_post([
            'ID' => $photo_id,
            'post_content' => $description,
        ]);

        wp_send_json_success(['message' => '説明文を保存しました']);
    }

    public static function ajax_delete_photo() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;

        if (!$photo_id || !self::user_can_edit_photo($photo_id)) {
            wp_send_json_error(['message' => 'この写真は削除できません']);
        }

        update_post_meta($photo_id, '_scg_status', 'hidden');

        wp_send_json_success(['message' => '画像を削除済みに移動しました']);
    }

    public static function ajax_restore_photo() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;

        if (!$photo_id || !self::user_can_edit_photo($photo_id)) {
            wp_send_json_error(['message' => 'この写真は復元できません']);
        }

        update_post_meta($photo_id, '_scg_status', 'active');

        wp_send_json_success(['message' => '画像を復元しました']);
    }

    public static function ajax_permanently_delete_photo() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '完全削除は管理者のみ実行できます']);
        }

        check_ajax_referer('scg_manage_photos', 'nonce');

        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;

        if (!$photo_id) {
            wp_send_json_error(['message' => '画像が見つかりません']);
        }

        $attachment_id = intval(get_post_meta($photo_id, '_scg_attachment_id', true));

        if ($attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }

        wp_delete_post($photo_id, true);

        wp_send_json_success(['message' => '画像を完全削除しました']);
    }
}
