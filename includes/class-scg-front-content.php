<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Front_Content {
    public static function init() {
        add_shortcode('scg_blog', [__CLASS__, 'render_blog_shortcode']);
        add_shortcode('scg_news', [__CLASS__, 'render_news_shortcode']);
        add_shortcode('scg_blog_list', [__CLASS__, 'render_blog_shortcode']);
        add_shortcode('scg_news_list', [__CLASS__, 'render_news_shortcode']);
        add_action('wp_ajax_scg_front_get_content', [__CLASS__, 'ajax_get_content']);
        add_action('wp_ajax_nopriv_scg_front_get_content', [__CLASS__, 'ajax_get_content']);
    }

    public static function get_config($type = '') {
        $configs = [
            'blog' => [
                'type' => 'blog',
                'post_type' => 'scg_blog',
                'label' => 'blog',
                'label_ja' => 'ブログ',
                'param' => 'scg_blog',
                'empty' => 'ブログ記事はまだありません。',
                'back' => 'back to index',
                'to_index' => 'to index',
            ],
            'news' => [
                'type' => 'news',
                'post_type' => 'scg_news',
                'label' => 'news',
                'label_ja' => 'お知らせ',
                'param' => 'scg_news',
                'empty' => 'お知らせはまだありません。',
                'back' => 'back to index',
                'to_index' => 'to index',
            ],
        ];

        return $configs[$type] ?? null;
    }

    public static function render_blog_shortcode($atts = []) {
        return self::render_shortcode('blog', $atts);
    }

    public static function render_news_shortcode($atts = []) {
        return self::render_shortcode('news', $atts);
    }

    private static function render_shortcode($type, $atts = []) {
        $config = self::get_config($type);
        if (!$config) {
            return '';
        }

        $atts = shortcode_atts([
            'limit' => 10,
            'class' => '',
        ], $atts, 'scg_' . $type);

        $limit = intval($atts['limit']);
        if ($limit <= 0) {
            $limit = 10;
        }

        wp_enqueue_style('scg-front-content', SCG_CMS_URL . 'assets/css/front-content.css', [], SCG_CMS_VERSION);
        wp_enqueue_script('scg-front-content', SCG_CMS_URL . 'assets/js/front-content.js', ['jquery'], SCG_CMS_VERSION, true);

        wp_localize_script('scg-front-content', 'SCG_FRONT_CONTENT', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scg_front_content'),
            'messages' => [
                'loading' => '読み込んでいます...',
                'error' => '読み込みに失敗しました。時間をおいてもう一度お試しください。',
            ],
        ]);

        $initial_slug = isset($_GET[$config['param']]) ? sanitize_title(wp_unslash($_GET[$config['param']])) : '';
        $initial_archive = isset($_GET['archivelist']) ? sanitize_key(wp_unslash($_GET['archivelist'])) : '';
        $instance_id = 'scg-front-content-' . wp_generate_uuid4();
        $classes = trim('scg-front-content scg-front-content-' . $type . ' ' . sanitize_html_class($atts['class']));

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>"
             class="<?php echo esc_attr($classes); ?>"
             data-type="<?php echo esc_attr($type); ?>"
             data-param="<?php echo esc_attr($config['param']); ?>"
             data-initial-slug="<?php echo esc_attr($initial_slug); ?>"
             data-initial-archive="<?php echo esc_attr($initial_archive); ?>"
             data-limit="<?php echo esc_attr($limit); ?>">
            <div class="scg-front-content-loading">読み込んでいます...</div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax_get_content() {
        check_ajax_referer('scg_front_content', 'nonce');

        $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'top';
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        $config = self::get_config($type);
        if (!$config) {
            wp_send_json_error(['message' => '表示タイプが不正です。']);
        }

        if ($view === 'detail') {
            $html = self::render_detail_html($config, $slug);
            $resolved_view = 'detail';
        } elseif ($view === 'index') {
            $html = self::render_index_html($config);
            $resolved_view = 'index';
        } else {
            $html = self::render_top_html($config, $limit);
            $resolved_view = 'top';
        }

        wp_send_json_success([
            'html' => $html,
            'view' => $resolved_view,
            'slug' => $slug,
        ]);
    }

    private static function render_top_html($config, $limit = 10) {
        $query = self::query_active_posts($config, $limit);

        ob_start();
        ?>
        <section class="scg-front-content-shell scg-front-content-top">
            <?php self::render_title_bar($config, 'index'); ?>

            <?php if ($query->have_posts()): ?>
                <div class="scg-front-content-top-list">
                    <?php foreach ($query->posts as $post): ?>
                        <?php self::render_top_item($post, $config); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="scg-front-content-empty"><?php echo esc_html($config['empty']); ?></p>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    private static function render_index_html($config) {
        $query = self::query_active_posts($config, -1);
        $total = intval($query->found_posts);
        $index = 0;

        ob_start();
        ?>
        <section class="scg-front-content-shell scg-front-content-index">
            <?php self::render_title_bar($config, 'back-top'); ?>

            <?php if ($query->have_posts()): ?>
                <div class="scg-front-content-index-list">
                    <?php foreach ($query->posts as $post): ?>
                        <?php
                        $number = max(1, $total - $index);
                        self::render_index_item($post, $config, $number);
                        $index++;
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="scg-front-content-empty"><?php echo esc_html($config['empty']); ?></p>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    private static function render_title_bar($config, $mode = '') {
        ?>
        <div class="scg-front-content-titlebar">
            <div class="scg-front-content-titleline"></div>
            <div class="scg-front-content-titletext"><?php echo esc_html($config['label']); ?></div>
            <div class="scg-front-content-titleline"></div>
            <?php if ($mode === 'index'): ?>
                <a href="<?php echo esc_url(self::make_archive_url()); ?>" class="scg-front-content-navlink" data-scg-content-index="1">
                    <?php echo esc_html($config['to_index']); ?> <span>▶</span>
                </a>
            <?php elseif ($mode === 'back-top'): ?>
                <a href="<?php echo esc_url(self::make_top_url()); ?>" class="scg-front-content-navlink" data-scg-content-top="1">
                    <span>◀</span> back to <?php echo esc_html($config['label']); ?>
                </a>
            <?php elseif ($mode === 'back-index'): ?>
                <a href="<?php echo esc_url(self::make_archive_url()); ?>" class="scg-front-content-navlink" data-scg-content-back="1">
                    <span>◀</span> back to index
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_top_item($post, $config) {
        $slug = $post->post_name ?: (string) $post->ID;
        $date = get_the_date('Y. n.j', $post);
        $images = self::get_images($post->ID, 'medium_large');
        ?>
        <article class="scg-front-content-post" data-slug="<?php echo esc_attr($slug); ?>">
            <div class="scg-front-content-post-layout">
                <div class="scg-front-content-post-main">
                    <div class="scg-front-content-post-head">
                        <time><?php echo esc_html($date); ?></time>
                        <h2><?php echo esc_html($post->post_title ?: '無題'); ?></h2>
                    </div>
                    <div class="scg-front-content-body">
                        <?php echo wp_kses_post(wpautop($post->post_content)); ?>
                    </div>
                </div>
                <?php if (!empty($images)): ?>
                    <div class="scg-front-content-post-images">
                        <?php foreach ($images as $image): ?>
                            <figure>
                                <button type="button" class="scg-front-content-image-button" data-scg-content-image="1" data-full="<?php echo esc_url($image['full_url']); ?>" data-alt="<?php echo esc_attr($image['alt']); ?>">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                                </button>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private static function render_index_item($post, $config, $number) {
        $slug = $post->post_name ?: (string) $post->ID;
        $date = get_the_date('Y. n.j', $post);
        ?>
        <a class="scg-front-content-index-row" href="<?php echo esc_url(self::make_content_url($config, $slug)); ?>" data-scg-content-link="1" data-slug="<?php echo esc_attr($slug); ?>">
            <span class="scg-front-content-index-no">No.<?php echo esc_html(str_pad((string) $number, 4, '0', STR_PAD_LEFT)); ?></span>
            <time><?php echo esc_html($date); ?></time>
            <span class="scg-front-content-index-title"><?php echo esc_html($post->post_title ?: '無題'); ?></span>
        </a>
        <?php
    }

    private static function render_detail_html($config, $slug) {
        if (!$slug) {
            return self::render_top_html($config, 10);
        }

        $post = self::get_content_by_slug($config, $slug);
        if (!$post) {
            ob_start();
            ?>
            <section class="scg-front-content-shell">
                <?php self::render_title_bar($config, 'back-top'); ?>
                <p class="scg-front-content-empty">記事が見つかりません。</p>
            </section>
            <?php
            return ob_get_clean();
        }

        $date = get_the_date('Y. n.j', $post);
        $images = self::get_images($post->ID, 'large');

        ob_start();
        ?>
        <article class="scg-front-content-detail">
            <?php self::render_title_bar($config, 'back-index'); ?>
            <div class="scg-front-content-detail-layout">
                <div class="scg-front-content-detail-main">
                    <div class="scg-front-content-post-head">
                        <time><?php echo esc_html($date); ?></time>
                        <h2><?php echo esc_html($post->post_title ?: '無題'); ?></h2>
                    </div>
                    <div class="scg-front-content-body">
                        <?php echo wp_kses_post(wpautop($post->post_content)); ?>
                    </div>
                </div>
                <?php if (!empty($images)): ?>
                    <div class="scg-front-content-detail-images">
                        <?php foreach ($images as $image): ?>
                            <figure>
                                <button type="button" class="scg-front-content-image-button" data-scg-content-image="1" data-full="<?php echo esc_url($image['full_url']); ?>" data-alt="<?php echo esc_attr($image['alt']); ?>">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" loading="lazy">
                                </button>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function query_active_posts($config, $limit = 10) {
        return new WP_Query([
            'post_type' => $config['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => intval($limit) > 0 ? intval($limit) : -1,
            'meta_query' => [[
                'key' => '_scg_status',
                'value' => 'active',
            ]],
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => false,
        ]);
    }

    private static function get_content_by_slug($config, $slug) {
        $query = new WP_Query([
            'post_type' => $config['post_type'],
            'post_status' => 'publish',
            'name' => $slug,
            'posts_per_page' => 1,
            'meta_query' => [[
                'key' => '_scg_status',
                'value' => 'active',
            ]],
        ]);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return null;
        }

        $post = $query->posts[0];
        wp_reset_postdata();
        return $post;
    }

    private static function get_images($post_id, $size = 'large') {
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $image_id = intval(get_post_meta($post_id, '_scg_image_' . $i, true));
            if (!$image_id) {
                continue;
            }

            $url = wp_get_attachment_image_url($image_id, $size);
            if (!$url) {
                continue;
            }

            $full_url = wp_get_attachment_image_url($image_id, 'full') ?: $url;

            $images[] = [
                'id' => $image_id,
                'url' => $url,
                'full_url' => $full_url,
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '',
            ];
        }

        return $images;
    }

    private static function make_content_url($config, $slug) {
        return add_query_arg($config['param'], $slug, self::current_url_without_content_params());
    }

    private static function make_archive_url() {
        return add_query_arg('archivelist', 'headline', self::current_url_without_content_params());
    }

    private static function make_top_url() {
        return self::current_url_without_content_params();
    }

    private static function current_url_without_content_params() {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $url = $scheme . $host . $uri;
        return remove_query_arg(['scg_blog', 'scg_news', 'archivelist'], $url);
    }
}
