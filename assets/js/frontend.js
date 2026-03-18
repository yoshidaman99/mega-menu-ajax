/**
 * Mega Menu Ajax - Frontend JavaScript
 */
(function ($) {
    'use strict';

    var MegaMenuAjax = {
        init: function () {
            this.bindEvents();
            this.initMobileToggle();
            this.initLazyLoad();
            this.initSearch();
            this.initPreload();
        },

        bindEvents: function () {
            var self = this;

            $(document).on('mouseenter', '.mega-menu-ajax-item.mega-menu-ajax-has-children', function (e) {
                var $item = $(this);
                var $submenu = $item.children('.mega-menu-ajax-submenu');

                if ($submenu.hasClass('mega-menu-ajax-lazy') && !$submenu.data('loaded')) {
                    self.loadSubmenu($item, $submenu);
                }
            });

            $(document).on('click', '.mega-menu-ajax-item.mega-menu-ajax-has-children > a', function (e) {
                if ($(window).width() <= 768) {
                    e.preventDefault();
                    $(this).parent().toggleClass('mega-menu-ajax-active');
                }
            });

            $(document).on('click', '.mega-menu-ajax-indicator', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.mega-menu-ajax-item').toggleClass('mega-menu-ajax-active');
            });
        },

        loadSubmenu: function ($item, $submenu) {
            var itemId = $item.data('menu-item-id') || $item.attr('class').match(/menu-item-(\d+)/);

            if (Array.isArray(itemId)) {
                itemId = itemId[1];
            }

            if (!itemId) {
                return;
            }

            $submenu.data('loaded', true);
            $submenu.append('<div class="mega-menu-ajax-loading"><span class="mega-menu-ajax-spinner"></span></div>');

            $.ajax({
                url: megaMenuAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mega_menu_ajax_load_submenu',
                    item_id: itemId,
                    nonce: megaMenuAjax.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var html = MegaMenuAjax.renderSubmenuItems(response.data);
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.append(html);
                    }
                },
                error: function () {
                    $submenu.find('.mega-menu-ajax-loading').remove();
                    $submenu.data('loaded', false);
                }
            });
        },

        renderSubmenuItems: function (items) {
            var html = '';

            items.forEach(function (item) {
                html += '<li class="mega-menu-ajax-item';
                if (item.has_children) {
                    html += ' mega-menu-ajax-has-children';
                }
                html += '" data-menu-item-id="' + item.id + '">';
                html += '<a href="' + item.url + '"';

                if (item.target) {
                    html += ' target="' + item.target + '"';
                }

                html += '>' + item.title + '</a>';

                if (item.has_children) {
                    html += '<ul class="mega-menu-ajax-submenu mega-menu-ajax-lazy"></ul>';
                }

                html += '</li>';
            });

            return html;
        },

        initMobileToggle: function () {
            $(document).on('click', '.mega-menu-ajax-toggle', function (e) {
                e.preventDefault();
                $(this).closest('.mega-menu-ajax-wrap').toggleClass('mega-menu-ajax-open');
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.mega-menu-ajax-wrap').length) {
                    $('.mega-menu-ajax-wrap').removeClass('mega-menu-ajax-open');
                }
            });
        },

        initLazyLoad: function () {
            var $placeholders = $('.mega-menu-ajax-placeholder');

            if (!$placeholders.length) {
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        MegaMenuAjax.loadMenu($(entry.target));
                        observer.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '100px' });

            $placeholders.each(function () {
                observer.observe(this);
            });
        },

        loadMenu: function ($placeholder) {
            var location = $placeholder.data('location');

            if (!location) {
                return;
            }

            $.ajax({
                url: megaMenuAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mega_menu_ajax_load_menu',
                    location: location,
                    nonce: megaMenuAjax.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        var html = MegaMenuAjax.renderMenu(response.data, location);
                        $placeholder.replaceWith(html);
                    }
                }
            });
        },

        renderMenu: function (items, location) {
            var html = '<div class="mega-menu-ajax-wrap" data-location="' + location + '">';
            html += '<button class="mega-menu-ajax-toggle" aria-label="' + megaMenuAjax.i18n.menu + '">';
            html += '<span class="mega-menu-ajax-toggle-icon"></span>';
            html += '</button>';
            html += '<ul class="mega-menu-ajax-menu">';

            var topLevelItems = Object.values(items).filter(function (item) {
                return item.parent == 0;
            });

            topLevelItems.forEach(function (item) {
                html += MegaMenuAjax.renderMenuItem(item, items);
            });

            html += '</ul></div>';

            return html;
        },

        renderMenuItem: function (item, allItems) {
            var children = Object.values(allItems).filter(function (child) {
                return child.parent == item.id;
            });

            var html = '<li class="mega-menu-ajax-item';
            if (children.length) {
                html += ' mega-menu-ajax-has-children';
            }
            html += '" data-menu-item-id="' + item.id + '">';
            html += '<a href="' + item.url + '">' + item.title;

            if (children.length) {
                html += '<span class="mega-menu-ajax-indicator" aria-hidden="true"></span>';
            }

            html += '</a>';

            if (children.length) {
                html += '<ul class="mega-menu-ajax-submenu">';
                children.forEach(function (child) {
                    html += MegaMenuAjax.renderMenuItem(child, allItems);
                });
                html += '</ul>';
            }

            html += '</li>';

            return html;
        },

        initSearch: function () {
            var $searchInputs = $('.mega-menu-ajax-search-input');
            var searchTimeout;

            $searchInputs.on('input', function () {
                var $input = $(this);
                var $wrapper = $input.closest('.mega-menu-ajax-search');
                var $results = $wrapper.find('.mega-menu-ajax-search-results');
                var query = $input.val().trim();
                var location = $input.closest('.mega-menu-ajax-wrap').data('location');

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $results.hide().empty();
                    return;
                }

                searchTimeout = setTimeout(function () {
                    $.ajax({
                        url: megaMenuAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mega_menu_ajax_search',
                            query: query,
                            location: location,
                            nonce: megaMenuAjax.nonce
                        },
                        success: function (response) {
                            if (response.success && response.data.length) {
                                var html = '';
                                response.data.forEach(function (item) {
                                    html += '<a href="' + item.url + '" class="mega-menu-ajax-search-result">' + item.title + '</a>';
                                });
                                $results.html(html).show();
                            } else {
                                $results.html('<div class="mega-menu-ajax-search-result">' + megaMenuAjax.i18n.noResults + '</div>').show();
                            }
                        }
                    });
                }, 300);
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.mega-menu-ajax-search').length) {
                    $('.mega-menu-ajax-search-results').hide();
                }
            });
        },

        initPreload: function () {
            var self = this;

            if (!megaMenuAjax.preload || Object.keys(megaMenuAjax.preload).length === 0) {
                return;
            }

            self.preloadCache = {};
            self.preloadTimers = {};
            self.preloadedUrls = {};

            $(document).on('mouseenter', '.mega-menu-ajax-item > a', function (e) {
                var $link = $(this);
                var $item = $link.parent();
                var $wrap = $item.closest('.mega-menu-ajax-wrap');
                var location = $wrap.data('location');

                if (!location || !megaMenuAjax.preload[location]) {
                    return;
                }

                var href = $link.attr('href');
                if (!href || href === '#' || self.preloadedUrls[href]) {
                    return;
                }

                var itemId = $item.data('menu-item-id');
                var settings = megaMenuAjax.preload[location];
                var delay = settings.delay || 30;

                self.preloadTimers[itemId] = setTimeout(function () {
                    self.preloadPage($item, href, itemId, settings);
                }, delay);
            });

            $(document).on('mouseleave', '.mega-menu-ajax-item > a', function (e) {
                var $item = $(this).parent();
                var itemId = $item.data('menu-item-id');

                if (self.preloadTimers[itemId]) {
                    clearTimeout(self.preloadTimers[itemId]);
                    delete self.preloadTimers[itemId];
                }
            });
        },

        preloadPage: function ($item, url, itemId, settings) {
            var self = this;

            if (self.preloadedUrls[url]) {
                return;
            }

            $item.addClass('mega-menu-ajax-preloading');

            $.ajax({
                url: megaMenuAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mega_menu_ajax_preload_page',
                    item_id: itemId,
                    nonce: megaMenuAjax.nonce
                },
                success: function (response) {
                    $item.removeClass('mega-menu-ajax-preloading');

                    if (response.success && response.data) {
                        var data = response.data;
                        self.preloadCache[url] = data;
                        self.preloadedUrls[url] = true;

                        self.injectPreloads(data.assets, settings);
                    }
                },
                error: function () {
                    $item.removeClass('mega-menu-ajax-preloading');
                }
            });
        },

        injectPreloads: function (assets, settings) {
            var self = this;
            var head = document.head || document.getElementsByTagName('head')[0];

            if (assets.css && settings.preload_css) {
                assets.css.forEach(function (url) {
                    if (!self.isAlreadyPreloaded(url, 'style')) {
                        var link = document.createElement('link');
                        link.rel = 'preload';
                        link.as = 'style';
                        link.href = url;
                        head.appendChild(link);
                    }
                });
            }

            if (assets.js && settings.preload_js) {
                assets.js.forEach(function (url) {
                    if (!self.isAlreadyPreloaded(url, 'script')) {
                        var link = document.createElement('link');
                        link.rel = 'preload';
                        link.as = 'script';
                        link.href = url;
                        head.appendChild(link);
                    }
                });
            }

            if (assets.images && settings.preload_images) {
                assets.images.forEach(function (url) {
                    if (!self.isAlreadyPreloaded(url, 'image')) {
                        var link = document.createElement('link');
                        link.rel = 'preload';
                        link.as = 'image';
                        link.href = url;
                        head.appendChild(link);
                    }
                });
            }
        },

        isAlreadyPreloaded: function (url, type) {
            var selector = 'link[rel="preload"][href="' + url + '"]';
            if (type === 'style') {
                selector += ', link[rel="stylesheet"][href="' + url + '"]';
            } else if (type === 'script') {
                selector += ', script[src="' + url + '"]';
            }
            return document.querySelector(selector) !== null;
        }
    };

    $(document).ready(function () {
        MegaMenuAjax.init();
    });

})(jQuery);
