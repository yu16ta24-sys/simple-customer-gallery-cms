<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Gallery_Upload {
    public static function init() {
        add_action('wp_ajax_scg_upload_photos', [__CLASS__, 'handle_upload']);
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

    public static function handle_upload() {
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '権限がありません']);
        }

        check_ajax_referer('scg_upload_photos', 'nonce');

        $main_category = isset($_POST['main_category']) ? absint($_POST['main_category']) : 0;
        $sub_category = isset($_POST['sub_category']) ? absint($_POST['sub_category']) : 0;
        $descriptions = isset($_POST['descriptions']) && is_array($_POST['descriptions'])
            ? array_map('sanitize_textarea_field', wp_unslash($_POST['descriptions']))
            : [];

        if (!$main_category || !$sub_category) {
            wp_send_json_error(['message' => 'カテゴリを選択してください']);
        }

        if (empty($_FILES['photos']) || empty($_FILES['photos']['name'])) {
            wp_send_json_error(['message' => '画像を選択してください']);
        }

        $files = $_FILES['photos'];
        $file_count = count($files['name']);

        if ($file_count > 10) {
            wp_send_json_error(['message' => '画像は最大10枚までです']);
        }


        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $created = [];

        for ($i = 0; $i < $file_count; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            if (!empty($files['error'][$i])) {
                if (in_array(intval($files['error'][$i]), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    wp_send_json_error(['message' => 'サーバーのアップロード上限を超えています。PHP設定を確認してください。']);
                }
                continue;
            }

            $file_type = wp_check_filetype_and_ext($files['tmp_name'][$i], $files['name'][$i]);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];

            if (empty($file_type['type']) || !in_array($file_type['type'], $allowed, true)) {
                continue;
            }

            $_FILES['scg_single_photo'] = [
                'name' => sanitize_file_name($files['name'][$i]),
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $attachment_id = media_handle_upload('scg_single_photo', 0);

            if (is_wp_error($attachment_id)) {
                continue;
            }

            SCG_Image_Optimizer::optimize_attachment($attachment_id);

            $filename_title = pathinfo($files['name'][$i], PATHINFO_FILENAME);
            $description = $descriptions[$i] ?? '';

            $photo_id = wp_insert_post([
                'post_type' => 'scg_photo',
                'post_status' => 'publish',
                'post_title' => sanitize_text_field($filename_title ?: 'Photo ' . $attachment_id),
                'post_content' => $description,
                'post_author' => get_current_user_id(),
            ]);

            if (is_wp_error($photo_id)) {
                continue;
            }

            wp_set_object_terms($photo_id, [$main_category, $sub_category], 'scg_gallery_category');

            update_post_meta($photo_id, '_scg_attachment_id', $attachment_id);
            update_post_meta($photo_id, '_scg_order', self::get_next_order($sub_category));
            update_post_meta($photo_id, '_scg_status', 'active');
            update_post_meta($photo_id, '_scg_uploaded_by', get_current_user_id());

            $created[] = ['photo_id' => $photo_id, 'attachment_id' => $attachment_id];
        }

        if (empty($created)) {
            wp_send_json_error(['message' => 'アップロードに失敗しました']);
        }

        wp_send_json_success(['message' => count($created) . '枚の写真を追加しました', 'items' => $created]);
    }

    private static function get_next_order($sub_category_id) {
        $query = new WP_Query([
            'post_type' => 'scg_photo',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'tax_query' => [[
                'taxonomy' => 'scg_gallery_category',
                'field' => 'term_id',
                'terms' => $sub_category_id,
            ]],
            'meta_key' => '_scg_order',
            'meta_type' => 'NUMERIC',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        if (!$query->have_posts()) {
            return 1;
        }

        return intval(get_post_meta($query->posts[0], '_scg_order', true)) + 1;
    }

}

