(function ($) {
  'use strict';

  function getConfig() {
    return window.SCG_FRONT_GALLERY || { ajax_url: '', nonce: '', categories: [], messages: {} };
  }

  function findParent(categories, slug) {
    if (!categories.length) return null;
    if (slug) {
      var match = categories.find(function (item) { return item.slug === slug; });
      if (match) return match;
    }
    return categories[0];
  }

  function findChild(parent, slug) {
    if (!parent || !parent.children || !parent.children.length) return null;
    if (slug) {
      var match = parent.children.find(function (item) { return item.slug === slug; });
      if (match) return match;
    }
    return parent.children[0];
  }

  function getUrlState($app) {
    var params = new URLSearchParams(window.location.search);
    return {
      main: params.get('main') || $app.data('initial-main') || '',
      sub: params.get('sub') || $app.data('initial-sub') || ''
    };
  }

  function updateUrl(parent, child, replace) {
    if (!window.history || !window.history.pushState) return;
    var url = new URL(window.location.href);
    if (parent && parent.slug) {
      url.searchParams.set('main', parent.slug);
    } else {
      url.searchParams.delete('main');
    }
    if (child && child.slug) {
      url.searchParams.set('sub', child.slug);
    } else {
      url.searchParams.delete('sub');
    }
    var state = { scgGallery: true, main: parent ? parent.slug : '', sub: child ? child.slug : '' };
    if (replace) {
      window.history.replaceState(state, '', url.toString());
    } else {
      window.history.pushState(state, '', url.toString());
    }
  }

  function Gallery($app) {
    this.$app = $app;
    this.config = getConfig();
    this.categories = this.config.categories || [];
    this.parent = null;
    this.child = null;
    this.items = [];
    this.currentIndex = 0;
    this.loading = false;
    this.request = null;
    this.init();
  }

  Gallery.prototype.init = function () {
    this.renderShell();
    this.bindEvents();

    var state = getUrlState(this.$app);
    this.selectBySlug(state.main, state.sub, true);
  };

  Gallery.prototype.renderShell = function () {
    this.$app.html(
      '<div class="scg-front-main-nav" role="navigation" aria-label="ギャラリーメインカテゴリー"></div>' +
      '<div class="scg-front-body">' +
        '<aside class="scg-front-sidebar" aria-label="ギャラリーサブカテゴリー"></aside>' +
        '<main class="scg-front-content">' +
          '<div class="scg-front-status" aria-live="polite"></div>' +
          '<div class="scg-front-grid" aria-live="polite"></div>' +
        '</main>' +
      '</div>' +
      '<div class="scg-front-modal" aria-hidden="true">' +
        '<div class="scg-front-modal-stage">' +
          '<div class="scg-front-modal-inner">' +
            '<button type="button" class="scg-front-modal-close" aria-label="閉じる">×</button>' +
            '<button type="button" class="scg-front-modal-nav scg-front-modal-prev" aria-label="前の画像"><span aria-hidden="true">‹</span></button>' +
            '<img class="scg-front-modal-image" src="" alt="">' +
            '<button type="button" class="scg-front-modal-nav scg-front-modal-next" aria-label="次の画像"><span aria-hidden="true">›</span></button>' +
            '<div class="scg-front-modal-caption"></div>' +
          '</div>' +
        '</div>' +
      '</div>'
    );

    this.$mainNav = this.$app.find('.scg-front-main-nav');
    this.$sidebar = this.$app.find('.scg-front-sidebar');
    this.$status = this.$app.find('.scg-front-status');
    this.$grid = this.$app.find('.scg-front-grid');
    this.$modal = this.$app.find('.scg-front-modal');
    this.$modalImage = this.$app.find('.scg-front-modal-image');
    this.$modalCaption = this.$app.find('.scg-front-modal-caption');

    this.renderMainNav();
  };

  Gallery.prototype.renderMainNav = function () {
    var self = this;
    if (!this.categories.length) {
      this.$mainNav.html('');
      this.$status.html('<div class="scg-front-empty">ギャラリーカテゴリーがまだありません。</div>');
      return;
    }

    var html = this.categories.map(function (parent) {
      return '<button type="button" class="scg-front-main-button" data-slug="' + escapeHtml(parent.slug) + '"><span class="scg-front-main-marker" aria-hidden="true">▪</span> ' + escapeHtml(parent.name) + '</button>';
    }).join('');

    this.$mainNav.html(html);
  };

  Gallery.prototype.renderSidebar = function () {
    if (!this.parent || !this.parent.children || !this.parent.children.length) {
      this.$sidebar.html('<div class="scg-front-empty">サブカテゴリーがありません。</div>');
      return;
    }

    var html = this.parent.children.map(function (child) {
      var label = escapeHtml(child.name).replace(/\n/g, '<br>');
      return '<button type="button" class="scg-front-sub-button" data-slug="' + escapeHtml(child.slug) + '">' + label + '</button>';
    }).join('');

    this.$sidebar.html(html);
  };

  Gallery.prototype.selectBySlug = function (parentSlug, childSlug, replaceUrl) {
    var parent = findParent(this.categories, parentSlug);
    var child = findChild(parent, childSlug);

    this.parent = parent;
    this.child = child;

    this.updateActiveButtons();
    this.renderSidebar();
    this.updateActiveButtons();

    if (parent && child) {
      updateUrl(parent, child, replaceUrl);
      this.loadPhotos(child.slug);
    } else {
      this.$grid.empty();
      this.$status.html('<div class="scg-front-empty">表示できる画像カテゴリーがありません。</div>');
    }
  };

  Gallery.prototype.updateActiveButtons = function () {
    var parentSlug = this.parent ? this.parent.slug : '';
    var childSlug = this.child ? this.child.slug : '';

    this.$mainNav.find('.scg-front-main-button').removeClass('is-active').filter('[data-slug="' + cssEscape(parentSlug) + '"]').addClass('is-active');
    this.$sidebar.find('.scg-front-sub-button').removeClass('is-active').filter('[data-slug="' + cssEscape(childSlug) + '"]').addClass('is-active');
  };

  Gallery.prototype.loadPhotos = function (subSlug) {
    var self = this;
    if (!subSlug) return;

    if (this.request && this.request.readyState !== 4) {
      this.request.abort();
    }

    this.loading = true;
    this.items = [];
    this.$grid.addClass('is-loading').empty();
    this.$status.html('<div class="scg-front-loading-line">' + escapeHtml(this.config.messages.loading || '読み込み中...') + '</div>');

    this.request = $.ajax({
      url: this.config.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'scg_front_get_photos',
        nonce: this.config.nonce,
        sub: subSlug
      }
    }).done(function (res) {
      if (!res || !res.success) {
        var msg = res && res.data && res.data.message ? res.data.message : (self.config.messages.error || '読み込みに失敗しました。');
        self.$status.html('<div class="scg-front-error">' + escapeHtml(msg) + '</div>');
        return;
      }

      self.items = res.data.items || [];
      self.renderGrid();
    }).fail(function (xhr, status) {
      if (status === 'abort') return;
      self.$status.html('<div class="scg-front-error">' + escapeHtml(self.config.messages.error || '読み込みに失敗しました。') + '</div>');
    }).always(function () {
      self.loading = false;
      self.$grid.removeClass('is-loading');
    });
  };

  Gallery.prototype.renderGrid = function () {
    var self = this;
    if (!this.items.length) {
      this.$grid.empty();
      this.$status.html('<div class="scg-front-empty">' + escapeHtml(this.config.messages.empty || '画像がありません。') + '</div>');
      return;
    }

    this.$status.empty();
    var html = this.items.map(function (item, index) {
      return '<button type="button" class="scg-front-photo" data-index="' + index + '">' +
        '<img src="' + escapeAttr(item.thumb) + '" alt="' + escapeAttr(item.alt || '') + '" loading="lazy">' +
      '</button>';
    }).join('');

    this.$grid.html(html);
    window.setTimeout(function () {
      self.$grid.find('.scg-front-photo').addClass('is-visible');
    }, 20);
  };

  Gallery.prototype.openModal = function (index) {
    if (!this.items[index]) return;
    this.currentIndex = index;
    this.updateModal();
    this.$modal.addClass('is-open').attr('aria-hidden', 'false');
    $('body').addClass('scg-front-modal-open');
  };

  Gallery.prototype.closeModal = function () {
    this.$modal.removeClass('is-open').attr('aria-hidden', 'true');
    this.$modalImage.attr('src', '').attr('alt', '');
    $('body').removeClass('scg-front-modal-open');
  };

  Gallery.prototype.updateModal = function () {
    var item = this.items[this.currentIndex];
    if (!item) return;

    this.$modalImage.attr('src', item.full).attr('alt', item.alt || '');

    if (item.description && String(item.description).trim() !== '') {
      this.$modalCaption.html(item.description).show();
    } else {
      this.$modalCaption.empty().hide();
    }

    this.$app.find('.scg-front-modal-prev, .scg-front-modal-next').toggle(this.items.length > 1);
  };

  Gallery.prototype.moveModal = function (direction) {
    if (!this.items.length) return;
    this.currentIndex = (this.currentIndex + direction + this.items.length) % this.items.length;
    this.updateModal();
  };

  Gallery.prototype.bindEvents = function () {
    var self = this;

    this.$app.on('click', '.scg-front-main-button', function () {
      var slug = $(this).data('slug');
      var parent = findParent(self.categories, slug);
      var child = findChild(parent, '');
      self.selectBySlug(parent ? parent.slug : '', child ? child.slug : '', false);
    });

    this.$app.on('click', '.scg-front-sub-button', function () {
      var slug = $(this).data('slug');
      self.selectBySlug(self.parent ? self.parent.slug : '', slug, false);
    });

    this.$app.on('click', '.scg-front-photo', function () {
      self.openModal(parseInt($(this).data('index'), 10));
    });

    this.$app.on('click', '.scg-front-modal-close', function () {
      self.closeModal();
    });

    this.$app.on('click', '.scg-front-modal-prev', function (e) {
      e.stopPropagation();
      self.moveModal(-1);
    });

    this.$app.on('click', '.scg-front-modal-next', function (e) {
      e.stopPropagation();
      self.moveModal(1);
    });

    this.$app.on('click', '.scg-front-modal', function (e) {
      if ($(e.target).hasClass('scg-front-modal')) {
        self.closeModal();
      }
    });

    this.$app.on('click', '.scg-front-modal-stage, .scg-front-modal-inner', function (e) {
      e.stopPropagation();
    });

    $(document).on('keydown.scgFrontGallery', function (e) {
      if (!self.$modal.hasClass('is-open')) return;
      if (e.key === 'Escape') self.closeModal();
      if (e.key === 'ArrowLeft') self.moveModal(-1);
      if (e.key === 'ArrowRight') self.moveModal(1);
    });

    $(window).on('popstate.scgFrontGallery', function () {
      var state = getUrlState(self.$app);
      self.selectBySlug(state.main, state.sub, true);
    });
  };

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
  }

  function cssEscape(value) {
    if (window.CSS && window.CSS.escape) {
      return window.CSS.escape(value || '');
    }
    return String(value || '').replace(/"/g, '\\"');
  }

  $(function () {
    $('.scg-front-gallery').each(function () {
      if (!$(this).data('scg-front-init')) {
        $(this).data('scg-front-init', true);
        new Gallery($(this));
      }
    });
  });
})(jQuery);
