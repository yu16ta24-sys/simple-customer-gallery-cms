<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Front_Gallery {
    public static function init() {
        add_shortcode('scg_gallery', [__CLASS__, 'render_shortcode']);
        add_action('wp_ajax_scg_front_get_photos', [__CLASS__, 'ajax_get_photos']);
        add_action('wp_ajax_nopriv_scg_front_get_photos', [__CLASS__, 'ajax_get_photos']);
    }

    public static function render_shortcode($atts = []) {
        $atts = shortcode_atts([
            'class' => '',
        ], $atts, 'scg_gallery');

        wp_enqueue_style('scg-front-gallery', SCG_CMS_URL . 'assets/css/front-gallery.css', [], SCG_CMS_VERSION);
        wp_enqueue_script('scg-front-gallery', SCG_CMS_URL . 'assets/js/front-gallery.js', ['jquery'], SCG_CMS_VERSION, true);

        wp_localize_script('scg-front-gallery', 'SCG_FRONT_GALLERY', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scg_front_gallery'),
            'categories' => self::get_category_tree(),
            'messages' => [
                'loading' => '画像を読み込んでいます...',
                'empty' => 'このカテゴリーにはまだ画像がありません。',
                'error' => '画像の読み込みに失敗しました。時間をおいてもう一度お試しください。',
            ],
        ]);

        $initial_main = isset($_GET['scg_main']) ? sanitize_title(wp_unslash($_GET['scg_main'])) : '';
        $initial_sub = isset($_GET['scg_sub']) ? sanitize_title(wp_unslash($_GET['scg_sub'])) : '';
        $instance_id = 'scg-front-gallery-' . wp_generate_uuid4();
        $classes = trim('scg-front-gallery ' . sanitize_html_class($atts['class']));
        $desktop_columns = max(1, min(8, (int) get_option('scg_gallery_columns_desktop', 5)));
        $tablet_columns = max(1, min(6, (int) get_option('scg_gallery_columns_tablet', 4)));
        $mobile_columns = max(1, min(4, (int) get_option('scg_gallery_columns_mobile', 3)));
        $style = sprintf(
            '--scg-gallery-columns-desktop:%d;--scg-gallery-columns-tablet:%d;--scg-gallery-columns-mobile:%d;',
            $desktop_columns,
            $tablet_columns,
            $mobile_columns
        );

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>"
             class="<?php echo esc_attr($classes); ?>"
             style="<?php echo esc_attr($style); ?>"
             data-initial-main="<?php echo esc_attr($initial_main); ?>"
             data-initial-sub="<?php echo esc_attr($initial_sub); ?>">
            <div class="scg-front-loading">画像ギャラリーを準備しています...</div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function get_category_tree() {
        $parents = get_terms([
            'taxonomy' => 'scg_gallery_category',
            'hide_empty' => false,
            'parent' => 0,
            'orderby' => 'term_order',
            'order' => 'ASC',
        ]);

        if (is_wp_error($parents)) {
            return [];
        }

        $tree = [];

        foreach ($parents as $parent) {
            $children = get_terms([
                'taxonomy' => 'scg_gallery_category',
                'hide_empty' => false,
                'parent' => $parent->term_id,
                'orderby' => 'term_order',
                'order' => 'ASC',
            ]);

            $item = [
                'id' => (int) $parent->term_id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'children' => [],
            ];

            if (!is_wp_error($children)) {
                foreach ($children as $child) {
                    $item['children'][] = [
                        'id' => (int) $child->term_id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'description' => $child->description,
                    ];
                }
            }

            $tree[] = $item;
        }

        return $tree;
    }

    public static function ajax_get_photos() {
        check_ajax_referer('scg_front_gallery', 'nonce');

        $sub_slug = isset($_POST['sub']) ? sanitize_title(wp_unslash($_POST['sub'])) : '';
        if (!$sub_slug) {
            wp_send_json_error(['message' => 'サブカテゴリーが指定されていません。']);
        }

        $term = get_term_by('slug', $sub_slug, 'scg_gallery_category');
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(['message' => 'サブカテゴリーが見つかりません。']);
        }

        $query = new WP_Query([
            'post_type' => 'scg_photo',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [[
                'taxonomy' => 'scg_gallery_category',
                'field' => 'term_id',
                'terms' => (int) $term->term_id,
            ]],
            'meta_query' => [[
                'key' => '_scg_status',
                'value' => 'active',
            ]],
            'meta_key' => '_scg_order',
            'meta_type' => 'NUMERIC',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            $attachment_id = (int) get_post_meta($post->ID, '_scg_attachment_id', true);
            if (!$attachment_id) {
                continue;
            }

            $thumb = wp_get_attachment_image_url($attachment_id, 'large');
            $full = wp_get_attachment_image_url($attachment_id, 'full');
            $meta = wp_get_attachment_metadata($attachment_id);

            if (!$thumb || !$full) {
                continue;
            }

            $items[] = [
                'id' => (int) $post->ID,
                'thumb' => $thumb,
                'full' => $full,
                'description' => wp_kses_post($post->post_content),
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: get_the_title($post),
                'width' => isset($meta['width']) ? (int) $meta['width'] : 0,
                'height' => isset($meta['height']) ? (int) $meta['height'] : 0,
            ];
        }

        wp_reset_postdata();

        wp_send_json_success([
            'items' => $items,
            'sub' => [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
            ],
        ]);
    }
}
