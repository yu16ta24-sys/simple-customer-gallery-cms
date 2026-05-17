<?php
if (!defined('ABSPATH')) {
    exit;
}

class SCG_Dashboard {
    public static function init() {}

    public static function render() {
        ?>
        <div class="wrap scg-wrap">
            <h1>専用CMS</h1>
            <p class="scg-lead">更新したい内容を選んでください。</p>

            <div class="scg-dashboard-grid">
                <section class="scg-panel">
                    <h2>ギャラリー管理</h2>
                    <p>写真の追加・管理・説明文編集を行います。</p>
                    <div class="scg-actions">
                        <a class="button button-primary button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-photo-manage')); ?>">ギャラリー管理</a>
                        <?php if (current_user_can('manage_options')): ?>
                            <a class="button button-large" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=scg_gallery_category&post_type=scg_photo')); ?>">カテゴリを管理する</a>
                            <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-gallery-display-settings')); ?>">表示設定</a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="scg-panel">
                    <h2>ブログ管理</h2>
                    <p>ブログ記事の作成・編集を行います。</p>
                    <div class="scg-actions">
                        <a class="button button-primary button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-blog-add')); ?>">ブログを書く</a>
                        <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-blog-list')); ?>">ブログ一覧</a>
                    </div>
                </section>

                <section class="scg-panel">
                    <h2>お知らせ管理</h2>
                    <p>お知らせの作成・編集を行います。</p>
                    <div class="scg-actions">
                        <a class="button button-primary button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-news-add')); ?>">お知らせを書く</a>
                        <a class="button button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-news-list')); ?>">お知らせ一覧</a>
                    </div>
                </section>

                <section class="scg-panel">
                    <h2>トップスライダー管理</h2>
                    <p>トップページ用スライダー画像の追加・並び替え・差し替えを行います。</p>
                    <div class="scg-actions">
                        <a class="button button-primary button-large" href="<?php echo esc_url(admin_url('admin.php?page=scg-top-slider')); ?>">スライダー管理</a>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }
}
