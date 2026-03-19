/**
 * Mega Menu Ajax - Frontend JavaScript
 * Compatible with Max Mega Menu
 */
(function ($) {
    'use strict';

    var MegaMenuAjax = {
        debug: false,
        compatMode: false,

        log: function () {
            if (this.debug && console && console.log) {
                console.log.apply(console, ['[MegaMenuAjax]'].concat(Array.prototype.slice.call(arguments)));
            }
        },

        init: function () {
            if (typeof megaMenuAjax === 'undefined') {
                console.error('[MegaMenuAjax] Configuration not found. Plugin may not be properly loaded.');
                return;
            }
            
            this.debug = megaMenuAjax.debug;
            
            this.log('Initializing...', megaMenuAjax);
            
            this.detectCompatMode();
            
            this.log('Compat mode:', this.compatMode ? 'Max Mega Menu detected' : 'Native mode');
            this.log('Enabled locations:', megaMenuAjax.enabledLocations);
            this.log('Registered locations:', megaMenuAjax.registeredLocations);
            
            var itemCount = this.compatMode 
                ? document.querySelectorAll('.mega-menu-item').length 
                : document.querySelectorAll('.mega-menu-ajax-item').length;
            this.log('Menu items found:', itemCount);
            
            this.bindEvents();
            this.initMobileToggle();
            this.initLazyLoad();
            this.initSearch();
            this.initPreload();
            this.backgroundPreloadInitialized = false;
            this.pageFullyLoaded = false;
            this.markLazySubmenus();
            
            this.log('Initialized successfully');
        },

        detectCompatMode: function () {
            this.compatMode = document.querySelectorAll('.mega-menu-wrap, .max-mega-menu').length > 0;
        },

        markLazySubmenus: function () {
            return;
        },

        getItemSelector: function () {
            return this.compatMode ? '.mega-menu-item' : '.mega-menu-ajax-item';
        },

        getSubmenuSelector: function () {
            return this.compatMode ? '.mega-sub-menu' : '.mega-menu-ajax-submenu';
        },

        getHasChildrenClass: function () {
            return this.compatMode ? 'mega-menu-item-has-children' : 'mega-menu-ajax-has-children';
        },

        getItemId: function ($item) {
            if (this.compatMode) {
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

            $(document).on('mouseenter', itemSelector, function (e) {
                var $item = $(this);
                var $link = $item.children('a').first();
                var $parent = $item.parents(itemSelector).first();
                var url = $link.attr('href');
                var title = $link.text().trim();
                var depth = $item.parents(submenuSelector).length;
                var isSubmenu = depth > 0;
                
                if (isSubmenu) {
                    var parentTitle = $parent.children('a').first().text().trim();
                    self.log('HOVER on SUBMENU item:', title, '| URL:', url, '| Parent:', parentTitle, '| Depth:', depth, '| ID:', self.getItemId($item));
                } else {
                    self.log('HOVER on TOP-LEVEL menu item:', title, '| URL:', url, '| ID:', self.getItemId($item));
                }
            });

            $(document).on('mouseenter', itemSelector + '.' + hasChildrenClass, function (e) {
                if (self.compatMode) {
                    return;
                }
                
                var $item = $(this);
                var $submenu = $item.children(submenuSelector).first();
                var itemId = self.getItemId($item);

                var isLazy = $submenu.hasClass('mega-menu-ajax-lazy');
                var isLoaded = $submenu.data('loaded');

                self.log('Has children hover - ID:', itemId, 'lazy:', isLazy, 'loaded:', isLoaded);

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
                    self.log('Submenu response:', response);
                    if (response.success && response.data && response.data.length) {
                        var html = self.compatMode 
                            ? self.renderMaxMegaMenuItems(response.data)
                            : self.renderSubmenuItems(response.data);
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.append(html);
                        $submenu.attr('data-loaded', 'true');
                        $submenu.addClass('mega-menu-ajax-loaded');
                        self.log('Submenu loaded with', response.data.length, 'items');
                    } else {
                        $submenu.find('.mega-menu-ajax-loading').remove();
                        $submenu.data('loaded', false);
                        self.log('No submenu items returned or empty response');
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

        renderMaxMegaMenuItems: function (items) {
            var html = '';

            items.forEach(function (item) {
                var classes = ['mega-menu-item', 'mega-menu-item-type-post_type', 'mega-menu-item-object-page'];
                
                if (item.has_children) {
                    classes.push('mega-menu-item-has-children');
                }
                if (item.classes && item.classes.length) {
                    classes = classes.concat(item.classes.filter(function(c) { return c && c !== ''; }));
                }

                html += '<li class="' + classes.join(' ') + '" id="mega-menu-item-' + item.id + '">';
                html += '<a class="mega-menu-link" href="' + item.url + '"';

                if (item.target) {
                    html += ' target="' + item.target + '"';
                }

                html += '>' + item.title + '</a>';

                if (item.has_children) {
                    html += '<ul class="mega-sub-menu mega-menu-ajax-lazy" id="mega-sub-menu-' + item.id + '"></ul>';
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
            self.prefetchStartTimes = {};
            self.fetchControllers = {};

            $(document).on('mouseenter touchstart', itemSelector + ' > a', function (e) {
                var $link = $(this);
                var href = $link.attr('href');

                if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                    return;
                }

                self.preconnectUrl(href);
                self.nativePrefetch(href);
                self.aggressiveFetch(href);

                self.prerenderTimers = self.prerenderTimers || {};
                var timerKey = href.replace(/[^a-zA-Z0-9]/g, '_');
                
                if (self.prerenderTimers[timerKey]) {
                    clearTimeout(self.prerenderTimers[timerKey]);
                }

                self.prerenderTimers[timerKey] = setTimeout(function () {
                    var prerenderEnabled = false;
                    if (location && megaMenuAjax.preload && megaMenuAjax.preload[location]) {
                        prerenderEnabled = megaMenuAjax.preload[location].prerender_enabled;
                    }
                    
                    if (prerenderEnabled) {
                        self.nativePrerender(href);
                    }
                    self.speculationPrerender(href);
                }, 20);

                var $item = $link.parent();
                var $wrap = $item.closest('.mega-menu-wrap, .mega-menu-ajax-wrap');
                var location = $wrap.data('location') || $wrap.attr('id');

                if (location && megaMenuAjax.preload && megaMenuAjax.preload[location]) {
                    var itemId = self.getItemId($item);
                    var settings = megaMenuAjax.preload[location];

                    self.preloadTimers[itemId] = setTimeout(function () {
                        self.preloadPage($item, href, itemId, settings);
                    }, 20);
                }
            });

            $(document).on('mousedown', itemSelector + ' > a', function (e) {
                var href = $(this).attr('href');
                if (href && href !== '#' && href.indexOf('javascript:') === 0) {
                    self.aggressiveFetch(href);
                    
                    var $item = $(this).parent();
                    var $wrap = $item.closest('.mega-menu-wrap, .mega-menu-ajax-wrap');
                    var location = $wrap.data('location') || $wrap.attr('id');
                    var prerenderEnabled = false;
                    if (location && megaMenuAjax.preload && megaMenuAjax.preload[location]) {
                        prerenderEnabled = megaMenuAjax.preload[location].prerender_enabled;
                    }
                    if (prerenderEnabled) {
                        self.nativePrerender(href);
                    }
                }
            });

            $(document).on('click', itemSelector + ' > a', function (e) {
                var href = $(this).attr('href');
                
                if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                    if (self.isPrefetchStalled(href)) {
                        self.abortFetch(href);
                    }
                }
                
                self.cancelPendingRequests();
            });

            $(document).on('mouseleave', itemSelector + ' > a', function (e) {
                var $item = $(this).parent();
                var itemId = self.getItemId($item);

                if (self.preloadTimers[itemId]) {
                    clearTimeout(self.preloadTimers[itemId]);
                    delete self.preloadTimers[itemId];
                }
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
            } catch (e) {
            }
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
                    if (error.name === 'AbortError') {
                        self.log('Fetch aborted:', url);
                    } else {
                        self.log('Aggressive fetch error:', error);
                    }
                    delete self.fetchControllers[url];
                    delete self.prefetchStartTimes[url];
                });
            } else if ('fetch' in window) {
                fetch(url, {
                    method: 'GET',
                    credentials: 'include',
                    mode: 'cors',
                    cache: 'force-cache'
                }).then(function (response) {
                    self.log('Aggressive fetch complete:', url);
                    delete self.prefetchStartTimes[url];
                }).catch(function (error) {
                    self.log('Aggressive fetch error:', error);
                    delete self.prefetchStartTimes[url];
                });
            }

            self.log('Aggressive fetch started:', url);
        },

        isPrefetchStalled: function (url) {
            var startTime = this.prefetchStartTimes[url];
            if (!startTime) {
                return false;
            }
            
            var timeout = megaMenuAjax.prefetchTimeout || 300;
            var elapsed = Date.now() - startTime;
            
            this.log('Prefetch elapsed:', elapsed + 'ms', '(timeout:', timeout + 'ms)');
            
            return elapsed > timeout;
        },

        abortFetch: function (url) {
            if (this.fetchControllers && this.fetchControllers[url]) {
                this.fetchControllers[url].abort();
                delete this.fetchControllers[url];
                this.log('Aborted stalled fetch:', url);
            }
        },

        speculationPrerender: function (url) {
            if (!HTMLScriptElement.supports || !HTMLScriptElement.supports('speculationrules')) {
                return;
            }

            var existing = document.querySelector('script[type="speculationrules"]');
            if (existing) {
                try {
                    var rules = JSON.parse(existing.textContent);
                    if (rules.prerender && rules.prerender.findIndex(function(r) { return r.urls && r.urls.indexOf(url) !== -1; }) !== -1) {
                        return;
                    }
                } catch (e) {}
            }

            var oldScripts = document.querySelectorAll('script[type="speculationrules"]');
            oldScripts.forEach(function (el) {
                el.parentNode.removeChild(el);
            });

            var script = document.createElement('script');
            script.type = 'speculationrules';
            script.textContent = JSON.stringify({
                prerender: [{
                    urls: [url]
                }]
            });

            document.head.appendChild(script);
            this.log('Speculation rules prerender:', url);
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
            link.fetchPriority = 'high';
            document.head.appendChild(link);

            this.log('Native prefetch started:', url);
        },

        nativePrerender: function (url) {
            if (this.prerenderedUrls[url]) {
                return;
            }

            this.prerenderedUrls[url] = true;

            var oldPrerenders = document.querySelectorAll('link[rel="prerender"]');
            oldPrerenders.forEach(function (el) {
                el.parentNode.removeChild(el);
            });

            var link = document.createElement('link');
            link.rel = 'prerender';
            link.href = url;
            document.head.appendChild(link);

            this.log('Native prerender started:', url);
        },

        cancelPendingRequests: function () {
            var self = this;

            for (var key in self.preloadTimers) {
                clearTimeout(self.preloadTimers[key]);
                delete self.preloadTimers[key];
            }

            if (self.prerenderTimers) {
                for (var key in self.prerenderTimers) {
                    clearTimeout(self.prerenderTimers[key]);
                    delete self.prerenderTimers[key];
                }
            }

            self.pendingAjaxRequests.forEach(function (xhr) {
                if (xhr && xhr.abort) {
                    xhr.abort();
                }
            });
            self.pendingAjaxRequests = [];

            self.log('Cancelled pending preload requests');
        },

        preloadPage: function ($item, url, itemId, settings) {
            var self = this;

            if (self.preloadedUrls[url]) {
                return;
            }

            $item.addClass('mega-menu-ajax-preloading');

            var xhr = $.ajax({
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
                error: function (xhr, status, error) {
                    $item.removeClass('mega-menu-ajax-preloading');
                    if (status !== 'abort') {
                        self.log('Preload error:', status, error);
                    }
                }
            });

            self.pendingAjaxRequests.push(xhr);
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
                        link.fetchPriority = 'high';
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
                        link.fetchPriority = 'high';
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
        },

        initBackgroundPreload: function () {
            var self = this;
            
            if (!megaMenuAjax.backgroundPreload || Object.keys(megaMenuAjax.backgroundPreload).length === 0) {
                this.log('Background preloading not enabled');
                return;
            }
            
            this.BackgroundPreloadManager = {
                init: function () {
                    self.log('BackgroundPreloadManager: Initializing...');
                    
                    this.isIdle = false;
                    this.isNetworkIdle = false;
                    this.isActive = false;
                    this.preloadQueue = [];
                    this.activePreloads = 0;
                    this.pendingUrls = new Set();
                    this.networkInfo = null;
                    this.lastActivityTime = Date.now();
                    this.activityTimeout = null;
                    this.networkIdleInterval = null;
                    this.visibilityObserver = null;
                    this.activityCount = 0;
                    this.activityThreshold = 5;
                    this.activityReset = null;
                    
                    var firstLocation = Object.keys(megaMenuAjax.backgroundPreload)[0];
                    if (firstLocation && megaMenuAjax.backgroundPreload[firstLocation]) {
                        this.idleCheckDelay = megaMenuAjax.backgroundPreload[firstLocation].delay || 2000;
                    } else {
                        this.idleCheckDelay = 2000;
                    }
                    
                    this.detectNetworkCapabilities();
                    this.initIdleDetection();
                    this.initVisibilityObserver();
                    this.bindEvents();
                    this.loadPreloadUrls();
                    
                    self.log('BackgroundPreloadManager: Initialized with delay:', this.idleCheckDelay);
                },
                
                detectNetworkCapabilities: function () {
                    if ('connection' in navigator) {
                        this.networkInfo = navigator.connection;
                        self.log('BackgroundPreloadManager: Network info detected', this.networkInfo.effectiveType);
                    }
                },
                
                initIdleDetection: function () {
                    var manager = this;
                    
                    var activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
                    activityEvents.forEach(function (event) {
                        document.addEventListener(event, function () {
                            manager.activityCount++;
                            manager.lastActivityTime = Date.now();
                            
                            if (manager.activityCount > manager.activityThreshold) {
                                if (manager.isActive === false) {
                                    manager.isActive = true;
                                    manager.pausePreloading();
                                }
                            }
                            
                            clearTimeout(manager.activityReset);
                            manager.activityReset = setTimeout(function () {
                                manager.activityCount = 0;
                                manager.isActive = false;
                                manager.maybeResumePreloading();
                            }, 1000);
                        }, { passive: true });
                    });
                    
                    this.checkNetworkIdle();
                    this.networkIdleInterval = setInterval(function () {
                        manager.checkNetworkIdle();
                    }, 1000);
                },
                
                checkNetworkIdle: function () {
                    var wasIdle = this.isNetworkIdle;
                    
                    this.isNetworkIdle = (Date.now() - this.lastActivityTime > 1000) && !this.isActive;
                    
                    if (wasIdle !== this.isNetworkIdle) {
                        self.log('BackgroundPreloadManager: Network idle state:', this.isNetworkIdle);
                        
                        if (this.isNetworkIdle) {
                            this.maybeResumePreloading();
                        } else {
                            this.pausePreloading();
                        }
                    }
                },
                
                initVisibilityObserver: function () {
                    var manager = this;
                    var menuWraps = document.querySelectorAll('.mega-menu-ajax-wrap, .mega-menu-wrap');
                    
                    if (!menuWraps.length) {
                        return;
                    }
                    
                    this.visibilityObserver = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting && self.pageFullyLoaded) {
                                var location = entry.target.dataset.location;
                                
                                if (!location) {
                                    var id = entry.target.id || '';
                                    if (id.indexOf('mega-menu-') === 0) {
                                        location = id.replace('mega-menu-', '');
                                    }
                                    if (!location) {
                                        var classes = entry.target.className.split(' ');
                                        for (var i = 0; i < classes.length; i++) {
                                            if (classes[i].indexOf('mega-menu-wrap-') === 0) {
                                                location = classes[i].replace('mega-menu-wrap-', '');
                                                break;
                                            }
                                        }
                                    }
                                }
                                
                                if (location && megaMenuAjax.backgroundPreload[location]) {
                                    self.log('BackgroundPreloadManager: Menu visible:', location);
                                    manager.maybeStartPreloading(location);
                                }
                            }
                        });
                    }, { rootMargin: '200px' });
                    
                    menuWraps.forEach(function (wrap) {
                        manager.visibilityObserver.observe(wrap);
                    });
                },
                
                maybeStartPreloading: function (location) {
                    if (!this.shouldPreload(location)) {
                        return;
                    }
                    
                    self.log('BackgroundPreloadManager: Starting for location:', location);
                    
                    var manager = this;
                    $.ajax({
                        url: megaMenuAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mega_menu_ajax_background_preload_urls',
                            location: location,
                            nonce: megaMenuAjax.nonce
                        },
                        success: function (response) {
                            if (response.success && response.data && response.data.length) {
                                manager.enqueueUrls(response.data, location);
                            }
                        },
                        error: function (xhr, status, error) {
                            self.log('BackgroundPreloadManager: Error fetching URLs:', status, error);
                        }
                    });
                },
                
                shouldPreload: function (location) {
                    var settings = megaMenuAjax.backgroundPreload[location];
                    
                    if (settings.wifiOnly && this.networkInfo) {
                        var effectiveType = this.networkInfo.effectiveType || '';
                        if (effectiveType !== '4g' && this.networkInfo.downlink < 2) {
                            self.log('BackgroundPreloadManager: Skipping - slow connection');
                            return false;
                        }
                    }
                    
                    if (settings.idleOnly && !this.isNetworkIdle) {
                        self.log('BackgroundPreloadManager: Skipping - network not idle');
                        return false;
                    }
                    
                    return true;
                },
                
                enqueueUrls: function (urls, location) {
                    var manager = this;
                    var settings = megaMenuAjax.backgroundPreload[location];
                    
                    urls.forEach(function (urlData) {
                        if (manager.pendingUrls.has(urlData.url)) {
                            return;
                        }
                        
                        if (manager.isAlreadyPreloaded(urlData.url)) {
                            return;
                        }
                        
                        manager.pendingUrls.add(urlData.url);
                        manager.preloadQueue.push({
                            url: urlData.url,
                            title: urlData.title,
                            location: location,
                            priority: urlData.priority || 10,
                            settings: settings
                        });
                    });
                    
                    manager.preloadQueue.sort(function (a, b) {
                        return b.priority - a.priority;
                    });
                    
                    self.log('BackgroundPreloadManager: Enqueued', urls.length, 'URLs');
                    
                    if (manager.isNetworkIdle) {
                        manager.processQueue();
                    }
                },
                
                isAlreadyPreloaded: function (url) {
                    var selectors = [
                        'link[rel="prefetch"][href="' + url + '"]',
                        'link[rel="preload"][href="' + url + '"]',
                        'link[rel="prerender"][href="' + url + '"]'
                    ];
                    
                    return document.querySelector(selectors.join(', ')) !== null;
                },
                
                processQueue: function () {
                    var manager = this;
                    
                    if (!this.isNetworkIdle || this.isActive || this.preloadQueue.length === 0) {
                        return;
                    }
                    
                    var settings = this.preloadQueue[0] ? this.preloadQueue[0].settings : null;
                    if (!settings) {
                        return;
                    }
                    
                    var maxConcurrent = Math.min(settings.limit, 3);
                    
                    if (this.activePreloads >= maxConcurrent) {
                        return;
                    }
                    
                    var nextUrl = this.preloadQueue.shift();
                    if (!nextUrl) {
                        return;
                    }
                    
                    this.activePreloads++;
                    self.log('BackgroundPreloadManager: Preloading:', nextUrl.url, '(Active:', this.activePreloads + ')');
                    
                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(function () {
                            manager.preloadUrl(nextUrl);
                        }, { timeout: 2000 });
                    } else {
                        setTimeout(function () {
                            manager.preloadUrl(nextUrl);
                        }, 0);
                    }
                    
                    setTimeout(function () {
                        manager.processQueue();
                    }, 300);
                },
                
                preloadUrl: function (urlData) {
                    var manager = this;
                    var url = urlData.url;
                    
                    var link = document.createElement('link');
                    link.rel = 'prefetch';
                    link.href = url;
                    link.fetchPriority = 'low';
                    link.as = 'document';
                    
                    link.onload = function () {
                        self.log('BackgroundPreloadManager: Preloaded:', url);
                        manager.activePreloads--;
                        manager.pendingUrls.delete(url);
                        manager.processQueue();
                    };
                    
                    link.onerror = function () {
                        self.log('BackgroundPreloadManager: Preload error:', url);
                        manager.activePreloads--;
                        manager.pendingUrls.delete(url);
                        manager.processQueue();
                    };
                    
                    document.head.appendChild(link);
                },
                
                maybeResumePreloading: function () {
                    var manager = this;
                    
                    if (!this.isNetworkIdle) {
                        return;
                    }
                    
                    self.log('BackgroundPreloadManager: Resuming');
                    
                    setTimeout(function () {
                        manager.processQueue();
                    }, 500);
                },
                
                pausePreloading: function () {
                    self.log('BackgroundPreloadManager: Paused');
                },
                
                loadPreloadUrls: function () {
                    var manager = this;
                    var menuWraps = document.querySelectorAll('.mega-menu-ajax-wrap, .mega-menu-wrap');
                    
                    menuWraps.forEach(function (wrap) {
                        var location = wrap.dataset.location;
                        
                        if (!location) {
                            var id = wrap.id || '';
                            if (id.indexOf('mega-menu-') === 0) {
                                location = id.replace('mega-menu-', '');
                            }
                            if (!location) {
                                var classes = wrap.className.split(' ');
                                for (var i = 0; i < classes.length; i++) {
                                    if (classes[i].indexOf('mega-menu-wrap-') === 0) {
                                        location = classes[i].replace('mega-menu-wrap-', '');
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (location && megaMenuAjax.backgroundPreload[location]) {
                            setTimeout(function () {
                                manager.maybeStartPreloading(location);
                            }, manager.idleCheckDelay);
                        }
                    });
                },
                
                bindEvents: function () {
                    var manager = this;
                    
                    document.addEventListener('visibilitychange', function () {
                        if (document.hidden) {
                            manager.pausePreloading();
                        } else {
                            manager.maybeResumePreloading();
                        }
                    });
                    
                    window.addEventListener('beforeunload', function () {
                        if (manager.networkIdleInterval) {
                            clearInterval(manager.networkIdleInterval);
                        }
                        if (manager.visibilityObserver) {
                            manager.visibilityObserver.disconnect();
                        }
                    });
                }
            };
            
            this.BackgroundPreloadManager.init();
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
