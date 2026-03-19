/**
 * Mega Menu Ajax - Frontend JavaScript
 * Compatible with Max Mega Menu and native WordPress menus
 */
(function ($) {
    'use strict';

    var MegaMenuAjax = {
        debug: false,
        compatMode: false,
        maxMegaMenuMode: false,
        submenuCache: {},
        pendingRequests: {},
        prefetchTimers: {},
        prefetchStartTimes: {},
        fetchControllers: {},

        log: function () {
            if (this.debug && console && console.log) {
                console.log.apply(console, ['[MegaMenuAjax]'].concat(Array.prototype.slice.call(arguments)));
            }
        },

        init: function () {
            if (typeof megaMenuAjax === 'undefined') {
                console.error('[MegaMenuAjax] Configuration not found.');
                return;
            }
            
            this.debug = megaMenuAjax.debug || false;
            
            this.log('Initializing...', megaMenuAjax);
            
            this.detectMode();
            this.log('Mode:', this.maxMegaMenuMode ? 'Max Mega Menu' : (this.compatMode ? 'Compatible' : 'Native'));
            
            this.bindEvents();
            this.initMobileToggle();
            this.initLazyLoad();
            this.initSearch();
            this.initPreload();
            this.initMaxMegaMenuLazy();
            
            this.backgroundPreloadInitialized = false;
            this.pageFullyLoaded = false;
            
            this.log('Initialized successfully');
        },

        detectMode: function () {
            this.compatMode = document.querySelectorAll('.mega-menu-wrap, .max-mega-menu').length > 0;
            this.maxMegaMenuMode = document.querySelectorAll('.mega-menu-wrap').length > 0;
        },

        initMaxMegaMenuLazy: function () {
            if (!this.maxMegaMenuMode) return;
            
            var self = this;
            var settings = megaMenuAjax.settings || {};
            
            this.log('Initializing Max Mega Menu lazy loading');
            
            $('.mega-menu-ajax-lazy-parent').each(function () {
                var $item = $(this);
                var $submenu = $item.children('.mega-sub-menu').first();
                var itemId = self.getItemId($item);
                
                if (itemId && $submenu.length) {
                    $submenu.addClass('mega-menu-ajax-lazy').attr('data-loaded', 'false').attr('data-parent-id', itemId);
                    
                    var $innerUl = $submenu.children('ul').first();
                    if ($innerUl.length && !$innerUl.hasClass('mega-menu-ajax-lazy')) {
                        $innerUl.addClass('mega-menu-ajax-lazy');
                    }
                }
            });
            
            this.bindMaxMegaMenuHover();
        },

        bindMaxMegaMenuHover: function () {
            var self = this;
            var hoverTimer = {};
            var loadingStates = {};
            
            $(document).on('mouseenter.megaMenuAjax', '.mega-menu-ajax-lazy-parent', function (e) {
                var $item = $(this);
                var itemId = self.getItemId($item);
                var $submenu = $item.children('.mega-sub-menu').first();
                
                if (!itemId || !$submenu.length) return;
                
                if (hoverTimer[itemId]) {
                    clearTimeout(hoverTimer[itemId]);
                }
                
                if (loadingStates[itemId]) return;
                
                if ($submenu.attr('data-loaded') === 'true') {
                    self.log('Submenu already loaded:', itemId);
                    return;
                }
                
                hoverTimer[itemId] = setTimeout(function () {
                    self.log('Hover triggered lazy load for:', itemId);
                    self.loadMaxMegaMenuSubmenu($item, $submenu, itemId, loadingStates);
                }, 30);
            });
            
            $(document).on('mouseleave.megaMenuAjax', '.mega-menu-ajax-lazy-parent', function (e) {
                var itemId = self.getItemId($(this));
                if (hoverTimer[itemId]) {
                    clearTimeout(hoverTimer[itemId]);
                    delete hoverTimer[itemId];
                }
            });
        },

        loadMaxMegaMenuSubmenu: function ($item, $submenu, itemId, loadingStates) {
            var self = this;
            
            if (this.submenuCache[itemId]) {
                this.log('Using cached submenu:', itemId);
                this.injectMaxMegaMenuSubmenu($submenu, this.submenuCache[itemId]);
                return;
            }
            
            if (this.pendingRequests[itemId]) {
                this.log('Request already pending:', itemId);
                return;
            }
            
            loadingStates[itemId] = true;
            $submenu.addClass('mega-menu-ajax-loading');
            
            this.pendingRequests[itemId] = $.ajax({
                url: megaMenuAjax.restUrl + 'megamenu-submenu/' + itemId,
                method: 'GET',
                data: {
                    location: $item.closest('.mega-menu-wrap').data('location') || ''
                },
                success: function (response) {
                    self.log('Submenu loaded:', itemId, response);
                    
                    if (response && response.length) {
                        self.submenuCache[itemId] = response;
                        self.injectMaxMegaMenuSubmenu($submenu, response);
                    } else {
                        $submenu.attr('data-loaded', 'true').removeClass('mega-menu-ajax-lazy');
                    }
                },
                error: function (xhr, status, error) {
                    self.log('Submenu load error:', itemId, status, error);
                    $submenu.removeClass('mega-menu-ajax-loading');
                },
                complete: function () {
                    delete self.pendingRequests[itemId];
                    delete loadingStates[itemId];
                }
            });
        },

        injectMaxMegaMenuSubmenu: function ($submenu, items) {
            if (!items || !items.length) {
                $submenu.attr('data-loaded', 'true').removeClass('mega-menu-ajax-lazy');
                return;
            }
            
            var html = this.renderMaxMegaMenuSubmenuItems(items);
            
            $submenu.find('.mega-menu-ajax-loading').remove();
            
            $submenu.html(html);
            $submenu.attr('data-loaded', 'true');
            $submenu.addClass('mega-menu-ajax-loaded');
            $submenu.removeClass('mega-menu-ajax-lazy mega-menu-ajax-loading');
            
            this.log('Submenu injected with', items.length, 'items');
        },

        renderMaxMegaMenuSubmenuItems: function (items) {
            var html = '<ul class="mega-sub-menu">';
            
            items.forEach(function (item) {
                var classes = ['mega-menu-item', 'mega-menu-item-type-post_type', 'mega-menu-item-object-page'];
                
                if (item.has_children) {
                    classes.push('mega-menu-item-has-children');
                    classes.push('mega-menu-ajax-lazy-parent');
                }
                
                if (item.current) {
                    classes.push('mega-current-menu-item');
                }
                if (item.current_item_parent) {
                    classes.push('mega-current-menu-parent');
                }
                if (item.current_item_ancestor) {
                    classes.push('mega-current-menu-ancestor');
                }
                
                if (item.classes && item.classes.length) {
                    classes = classes.concat(item.classes.filter(function(c) { return c && c !== ''; }));
                }
                
                html += '<li class="' + classes.join(' ') + '" id="mega-menu-item-' + item.id + '">';
                html += '<a class="mega-menu-link" href="' + item.url + '"';
                
                if (item.target) {
                    html += ' target="' + item.target + '"';
                }
                if (item.attr_title) {
                    html += ' title="' + item.attr_title + '"';
                }
                
                html += '><span class="mega-menu-title">' + item.title + '</span>';
                
                if (item.has_children) {
                    html += '<span class="mega-indicator"></span>';
                }
                
                html += '</a>';
                
                if (item.has_children) {
                    html += '<ul class="mega-sub-menu mega-menu-ajax-lazy" data-loaded="false" data-parent-id="' + item.id + '"></ul>';
                }
                
                html += '</li>';
            });
            
            html += '</ul>';
            return html;
        },

        getItemSelector: function () {
            return this.maxMegaMenuMode ? '.mega-menu-item' : (this.compatMode ? '.mega-menu-item' : '.mega-menu-ajax-item');
        },

        getSubmenuSelector: function () {
            return this.maxMegaMenuMode ? '.mega-sub-menu' : (this.compatMode ? '.mega-sub-menu' : '.mega-menu-ajax-submenu');
        },

        getHasChildrenClass: function () {
            return this.maxMegaMenuMode ? 'mega-menu-item-has-children' : 'mega-menu-ajax-has-children';
        },

        getItemId: function ($item) {
            if (this.maxMegaMenuMode || this.compatMode) {
                var id = $item.attr('id');
                if (id && id.indexOf('mega-menu-item-') === 0) {
                    return id.replace('mega-menu-item-', '');
                }
            }
            return $item.data('menu-item-id');
        },

        bindEvents: function () {
            var self = this;
            var itemSelector = this.getItemSelector();
            var submenuSelector = this.getSubmenuSelector();
            var hasChildrenClass = this.getHasChildrenClass();

            $(document).on('mouseenter', itemSelector + '.' + hasChildrenClass, function (e) {
                if (self.maxMegaMenuMode) return;
                
                var $item = $(this);
                var $submenu = $item.children(submenuSelector).first();
                var itemId = self.getItemId($item);

                var isLazy = $submenu.hasClass('mega-menu-ajax-lazy');
                var isLoaded = $submenu.data('loaded');

                if (isLazy && !isLoaded) {
                    self.loadSubmenu($item, $submenu, itemId);
                }
            });

            $(document).on('click', itemSelector + '.' + hasChildrenClass + ' > a', function (e) {
                if ($(window).width() <= 768) {
                    e.preventDefault();
                    $(this).parent().toggleClass('mega-menu-ajax-active');
                }
            });
        },

        loadSubmenu: function ($item, $submenu, itemId) {
            var self = this;

            if (!itemId) {
                self.log('No item ID found for submenu load');
                return;
            }

            if (self.submenuCache[itemId]) {
                $submenu.html(self.submenuCache[itemId]);
                $submenu.attr('data-loaded', 'true');
                return;
            }

            self.log('Loading submenu for item:', itemId);

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
                    if (response.success && response.data && response.data.html) {
                        self.submenuCache[itemId] = response.data.html;
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.append(response.data.html);
                        $submenu.attr('data-loaded', 'true');
                        $submenu.addClass('mega-menu-ajax-loaded');
                    } else if (response.success && response.data && response.data.length) {
                        var html = self.renderSubmenuItems(response.data);
                        self.submenuCache[itemId] = html;
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.append(html);
                        $submenu.attr('data-loaded', 'true');
                        $submenu.addClass('mega-menu-ajax-loaded');
                    } else {
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.data('loaded', false);
                    }
                },
                error: function (xhr, status, error) {
                    $submenu.find('.mega-menu-ajax-loading').remove();
                    $submenu.data('loaded', false);
                    self.log('Submenu load error:', status, error);
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
                if (item.classes && item.classes.length) {
                    var classes = item.classes.filter(function(c) { return c && c !== ''; });
                    if (classes.length) {
                        html += ' ' + classes.join(' ');
                    }
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
            html += '<button class="mega-menu-ajax-toggle" aria-label="' + (megaMenuAjax.i18n.menu || 'Menu') + '">';
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
            var itemSelector = this.getItemSelector();

            self.prefetchedUrls = {};
            self.prerenderedUrls = {};
            self.preloadCache = {};
            self.preloadTimers = {};
            self.preloadedUrls = {};
            self.pendingAjaxRequests = [];
            self.fetchedPages = {};

            $(document).on('mouseenter touchstart', itemSelector + ' > a', function (e) {
                var $link = $(this);
                var href = $link.attr('href');

                if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                    return;
                }

                self.preconnectUrl(href);
                self.nativePrefetch(href);
                self.aggressiveFetch(href);
            });

            $(document).on('click', itemSelector + ' > a', function (e) {
                self.cancelPendingRequests();
            });
        },

        preconnectUrl: function (url) {
            try {
                var urlObj = new URL(url);
                var origin = urlObj.origin;
                
                if (this.prefetchedUrls['preconnect:' + origin]) {
                    return;
                }
                this.prefetchedUrls['preconnect:' + origin] = true;

                var link = document.createElement('link');
                link.rel = 'preconnect';
                link.href = origin;
                document.head.appendChild(link);

                var dnsLink = document.createElement('link');
                dnsLink.rel = 'dns-prefetch';
                dnsLink.href = origin;
                document.head.appendChild(dnsLink);

                this.log('Preconnect:', origin);
            } catch (e) {}
        },

        aggressiveFetch: function (url) {
            var self = this;

            if (self.fetchedPages[url]) {
                return;
            }

            self.fetchedPages[url] = true;
            self.prefetchStartTimes[url] = Date.now();

            if ('AbortController' in window && 'fetch' in window) {
                var controller = new AbortController();
                self.fetchControllers[url] = controller;

                fetch(url, {
                    method: 'GET',
                    credentials: 'include',
                    mode: 'cors',
                    cache: 'force-cache',
                    signal: controller.signal
                }).then(function (response) {
                    self.log('Aggressive fetch complete:', url);
                    delete self.fetchControllers[url];
                    delete self.prefetchStartTimes[url];
                }).catch(function (error) {
                    if (error.name !== 'AbortError') {
                        self.log('Aggressive fetch error:', error);
                    }
                    delete self.fetchControllers[url];
                    delete self.prefetchStartTimes[url];
                });
            }

            self.log('Aggressive fetch started:', url);
        },

        nativePrefetch: function (url) {
            if (this.prefetchedUrls[url]) {
                return;
            }

            this.prefetchedUrls[url] = true;

            var link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            link.as = 'document';
            link.fetchPriority = 'low';
            document.head.appendChild(link);

            this.log('Native prefetch started:', url);
        },

        cancelPendingRequests: function () {
            var self = this;

            for (var key in self.fetchControllers) {
                if (self.fetchControllers[key]) {
                    self.fetchControllers[key].abort();
                    delete self.fetchControllers[key];
                }
            }

            self.log('Cancelled pending requests');
        },

        initBackgroundPreload: function () {
            var self = this;
            
            if (!megaMenuAjax.backgroundPreload || Object.keys(megaMenuAjax.backgroundPreload).length === 0) {
                this.log('Background preloading not enabled');
                return;
            }
            
            this.log('Background preload initialized');
        },
        
        triggerBackgroundPreload: function () {
            if (this.backgroundPreloadInitialized) {
                return;
            }
            
            this.backgroundPreloadInitialized = true;
            this.pageFullyLoaded = true;
            this.log('Page fully loaded, starting background preload');
            this.initBackgroundPreload();
        }
    };

    $(document).ready(function () {
        MegaMenuAjax.init();
    });
    
    $(window).on('load', function () {
        MegaMenuAjax.triggerBackgroundPreload();
    });

})(jQuery);
