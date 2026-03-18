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
        }
    };

    $(document).ready(function () {
        MegaMenuAjax.init();
    });

})(jQuery);
