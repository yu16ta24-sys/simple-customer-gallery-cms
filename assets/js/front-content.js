(function ($) {
    'use strict';

    function ContentApp(root) {
        this.root = root;
        this.$root = $(root);
        this.type = this.$root.data('type');
        this.param = this.$root.data('param');
        this.limit = parseInt(this.$root.data('limit'), 10) || 10;
        this.initialSlug = this.$root.data('initial-slug') || '';
        this.initialArchive = this.$root.data('initial-archive') || '';
        this.currentSlug = '';
        this.currentView = 'top';
        this.isLoading = false;
        this.modalImages = [];
        this.modalIndex = 0;
        this.init();
    }

    ContentApp.prototype.init = function () {
        var state = this.getUrlState();
        this.bindEvents();

        if (state.view === 'detail' && state.slug) {
            this.loadDetail(state.slug, true);
        } else if (state.view === 'index') {
            this.loadIndex(true);
        } else if (this.initialSlug) {
            this.loadDetail(this.initialSlug, true);
        } else if (this.initialArchive === 'headline') {
            this.loadIndex(true);
        } else {
            this.loadTop(true);
        }
    };

    ContentApp.prototype.bindEvents = function () {
        var self = this;

        this.$root.on('click', '[data-scg-content-link]', function (event) {
            event.preventDefault();
            var slug = $(this).data('slug');
            if (slug) {
                self.loadDetail(slug, false);
            }
        });

        this.$root.on('click', '[data-scg-content-index]', function (event) {
            event.preventDefault();
            self.loadIndex(false);
        });

        this.$root.on('click', '[data-scg-content-top]', function (event) {
            event.preventDefault();
            self.loadTop(false);
        });

        this.$root.on('click', '[data-scg-content-back]', function (event) {
            event.preventDefault();
            self.loadIndex(false);
        });

        this.$root.on('click', '[data-scg-content-image]', function (event) {
            event.preventDefault();
            self.openModal($(this));
        });

        this.$root.on('click', '[data-scg-content-modal-close]', function (event) {
            event.preventDefault();
            self.closeModal();
        });

        this.$root.on('click', '.scg-front-content-modal', function (event) {
            if (event.target === this) {
                self.closeModal();
            }
        });

        this.$root.on('click', '[data-scg-content-modal-prev]', function (event) {
            event.preventDefault();
            self.moveModal(-1);
        });

        this.$root.on('click', '[data-scg-content-modal-next]', function (event) {
            event.preventDefault();
            self.moveModal(1);
        });

        $(document).on('keydown.scgFrontContent', function (event) {
            if (!self.$root.find('.scg-front-content-modal').length) {
                return;
            }
            if (event.key === 'Escape') {
                self.closeModal();
            } else if (event.key === 'ArrowLeft') {
                self.moveModal(-1);
            } else if (event.key === 'ArrowRight') {
                self.moveModal(1);
            }
        });

        $(window).on('popstate.scgFrontContent', function () {
            var state = self.getUrlState();
            if (state.view === 'detail' && state.slug) {
                self.loadDetail(state.slug, true);
            } else if (state.view === 'index') {
                self.loadIndex(true);
            } else {
                self.loadTop(true);
            }
        });
    };

    ContentApp.prototype.getUrlState = function () {
        var params = new URLSearchParams(window.location.search);
        var slug = params.get(this.param) || '';
        var archive = params.get('archivelist') || '';

        if (slug) {
            return { view: 'detail', slug: slug };
        }

        if (archive === 'headline') {
            return { view: 'index', slug: '' };
        }

        return { view: 'top', slug: '' };
    };

    ContentApp.prototype.updateUrl = function (view, slug, replace) {
        if (!window.history || !window.history.pushState) {
            return;
        }

        var url = new URL(window.location.href);
        url.searchParams.delete('scg_blog');
        url.searchParams.delete('scg_news');
        url.searchParams.delete('archivelist');

        if (view === 'index') {
            url.searchParams.set('archivelist', 'headline');
        } else if (view === 'detail' && slug) {
            url.searchParams.set(this.param, slug);
        }

        var state = {
            scgContentType: this.type,
            view: view || 'top',
            slug: slug || ''
        };

        if (replace) {
            window.history.replaceState(state, '', url.toString());
        } else {
            window.history.pushState(state, '', url.toString());
        }
    };

    ContentApp.prototype.setLoading = function () {
        var message = window.SCG_FRONT_CONTENT && SCG_FRONT_CONTENT.messages ? SCG_FRONT_CONTENT.messages.loading : '読み込んでいます...';
        this.$root.html('<div class="scg-front-content-loading">' + this.escapeHtml(message) + '</div>');
    };

    ContentApp.prototype.loadTop = function (replace) {
        this.currentSlug = '';
        this.currentView = 'top';
        this.fetch({ view: 'top' }, replace);
    };

    ContentApp.prototype.loadIndex = function (replace) {
        this.currentSlug = '';
        this.currentView = 'index';
        this.fetch({ view: 'index' }, replace);
    };

    ContentApp.prototype.loadDetail = function (slug, replace) {
        this.currentSlug = slug;
        this.currentView = 'detail';
        this.fetch({ view: 'detail', slug: slug }, replace);
    };

    ContentApp.prototype.fetch = function (payload, replace) {
        var self = this;
        if (this.isLoading) {
            return;
        }

        this.isLoading = true;
        this.setLoading();

        $.ajax({
            url: SCG_FRONT_CONTENT.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: $.extend({
                action: 'scg_front_get_content',
                nonce: SCG_FRONT_CONTENT.nonce,
                type: this.type,
                limit: this.limit
            }, payload)
        }).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                var view = response.data.view || 'top';
                var slug = response.data.slug || '';
                self.$root.html(response.data.html);
                self.updateUrl(view, view === 'detail' ? slug : '', !!replace);
                self.$root[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                self.showError();
            }
        }).fail(function () {
            self.showError();
        }).always(function () {
            self.isLoading = false;
        });
    };

    ContentApp.prototype.showError = function () {
        var message = window.SCG_FRONT_CONTENT && SCG_FRONT_CONTENT.messages ? SCG_FRONT_CONTENT.messages.error : '読み込みに失敗しました。';
        this.$root.html('<div class="scg-front-content-error">' + this.escapeHtml(message) + '</div>');
    };

    ContentApp.prototype.openModal = function ($button) {
        var $buttons = this.$root.find('[data-scg-content-image]');
        var self = this;
        this.modalImages = [];

        $buttons.each(function () {
            var $item = $(this);
            self.modalImages.push({
                url: $item.data('full') || $item.find('img').attr('src'),
                alt: $item.data('alt') || $item.find('img').attr('alt') || ''
            });
        });

        this.modalIndex = Math.max(0, $buttons.index($button));
        this.renderModal();
    };

    ContentApp.prototype.renderModal = function () {
        if (!this.modalImages.length || !this.modalImages[this.modalIndex]) {
            return;
        }

        var image = this.modalImages[this.modalIndex];
        var prevDisabled = this.modalImages.length <= 1 ? ' is-disabled' : '';
        var html = '' +
            '<div class="scg-front-content-modal" role="dialog" aria-modal="true">' +
                '<div class="scg-front-content-modal-inner">' +
                    '<button type="button" class="scg-front-content-modal-close" data-scg-content-modal-close="1" aria-label="閉じる">×</button>' +
                    '<button type="button" class="scg-front-content-modal-nav scg-front-content-modal-prev' + prevDisabled + '" data-scg-content-modal-prev="1" aria-label="前の画像">‹</button>' +
                    '<img src="' + this.escapeAttr(image.url) + '" alt="' + this.escapeAttr(image.alt) + '">' +
                    '<button type="button" class="scg-front-content-modal-nav scg-front-content-modal-next' + prevDisabled + '" data-scg-content-modal-next="1" aria-label="次の画像">›</button>' +
                '</div>' +
            '</div>';

        this.$root.find('.scg-front-content-modal').remove();
        this.$root.append(html);
    };

    ContentApp.prototype.moveModal = function (step) {
        if (!this.modalImages.length || this.modalImages.length <= 1) {
            return;
        }

        this.modalIndex = (this.modalIndex + step + this.modalImages.length) % this.modalImages.length;
        this.renderModal();
    };

    ContentApp.prototype.closeModal = function () {
        this.$root.find('.scg-front-content-modal').remove();
        this.modalImages = [];
        this.modalIndex = 0;
    };

    ContentApp.prototype.escapeAttr = function (text) {
        return this.escapeHtml(text).replace(/'/g, '&#039;');
    };

    ContentApp.prototype.escapeHtml = function (text) {
        return String(text).replace(/[&<>"]/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;'
            }[match];
        });
    };

    $(function () {
        $('.scg-front-content').each(function () {
            if (!$(this).data('scgFrontContentReady')) {
                $(this).data('scgFrontContentReady', true);
                new ContentApp(this);
            }
        });
    });
})(jQuery);
