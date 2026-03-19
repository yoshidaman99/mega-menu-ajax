(function() {
    'use strict';

    if (typeof megaMenuPerformance === 'undefined') {
        return;
    }

    var config = megaMenuPerformance;
    var debug = config.debug || false;

    function log() {
        if (debug && console && console.log) {
            console.log.apply(console, ['[Mega Menu Performance]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function detectLCP() {
        if (!('PerformanceObserver' in window)) {
            log('PerformanceObserver not supported');
            return;
        }

        try {
            var po = new PerformanceObserver(function(entryList) {
                var entries = entryList.getEntries();
                var lastEntry = entries[entries.length - 1];

                if (!lastEntry) {
                    log('No LCP entry found');
                    return;
                }

                var lcpData = extractLCPData(lastEntry);
                
                if (lcpData) {
                    sendBeacon(lcpData);
                }

                po.disconnect();
            });

            po.observe({
                type: 'largest-contentful-paint',
                buffered: true
            });

            setTimeout(function() {
                po.disconnect();
            }, 10000);

        } catch (e) {
            log('Error observing LCP:', e);
        }
    }

    function extractLCPData(entry) {
        var element = entry.element;
        
        if (!element) {
            log('No element in LCP entry');
            return null;
        }

        var data = {
            url: config.currentUrl || window.location.pathname,
            lcp: {
                selector: getUniqueSelector(element),
                elementType: element.tagName.toLowerCase(),
                elementId: element.id || '',
                elementClass: element.className || '',
                imageUrl: '',
                backgroundImage: '',
                size: entry.size || 0
            }
        };

        if (element.tagName === 'IMG') {
            data.lcp.imageUrl = element.src || '';
            if (element.srcset) {
                var sources = element.srcset.split(',').map(function(s) {
                    return s.trim().split(' ')[0];
                });
                if (sources.length > 0) {
                    data.lcp.imageUrl = sources[0];
                }
            }
        } else {
            var bgImage = getComputedStyle(element).backgroundImage;
            if (bgImage && bgImage !== 'none') {
                data.lcp.backgroundImage = bgImage;
            }

            var img = element.querySelector('img');
            if (img && img.src) {
                data.lcp.imageUrl = img.src;
            }
        }

        if (element.hasAttribute('data-id')) {
            data.lcp.elementId = element.getAttribute('data-id');
        }

        log('LCP detected:', data);

        return data;
    }

    function getUniqueSelector(element) {
        if (element.id) {
            return '#' + CSS.escape(element.id);
        }

        var path = [];
        var current = element;

        while (current && current.nodeType === Node.ELEMENT_NODE) {
            var selector = current.tagName.toLowerCase();
            
            if (current.id) {
                selector = '#' + CSS.escape(current.id);
                path.unshift(selector);
                break;
            }

            if (current.className && typeof current.className === 'string') {
                var classes = current.className.trim().split(/\s+/);
                var meaningfulClasses = classes.filter(function(c) {
                    return c && !isGeneratedClass(c);
                });
                
                if (meaningfulClasses.length > 0) {
                    selector += '.' + meaningfulClasses.slice(0, 2).map(function(c) {
                        return CSS.escape(c);
                    }).join('.');
                }
            }

            if (current.parentNode) {
                var siblings = Array.prototype.slice.call(current.parentNode.children);
                var sameTagSiblings = siblings.filter(function(s) {
                    return s.tagName === current.tagName;
                });
                
                if (sameTagSiblings.length > 1) {
                    var index = sameTagSiblings.indexOf(current) + 1;
                    selector += ':nth-of-type(' + index + ')';
                }
            }

            path.unshift(selector);
            current = current.parentNode;

            if (path.length > 5) {
                break;
            }
        }

        return path.join(' > ');
    }

    function isGeneratedClass(className) {
        var patterns = [
            /^e-/,
            /^elementor-/,
            /^wp-/,
            /^has-/,
            /^is-/,
            /^[a-f0-9]{8,}$/i
        ];

        return patterns.some(function(pattern) {
            return pattern.test(className);
        });
    }

    function sendBeacon(data) {
        if (!data || !data.lcp) {
            return;
        }

        if (config.hasLcpData) {
            log('LCP data already cached for this URL');
            return;
        }

        var endpoint = config.restUrl + 'performance/lcp';
        var nonce = config.nonce;

        if ('sendBeacon' in navigator) {
            var formData = new FormData();
            formData.append('url', data.url);
            formData.append('lcp[url]', data.lcp.imageUrl || data.lcp.backgroundImage || '');
            formData.append('lcp[selector]', data.lcp.selector || '');
            formData.append('lcp[elementType]', data.lcp.elementType || '');
            formData.append('lcp[elementId]', data.lcp.elementId || '');
            formData.append('lcp[elementClass]', data.lcp.elementClass || '');
            formData.append('lcp[backgroundImage]', data.lcp.backgroundImage || '');
            formData.append('lcp[size]', data.lcp.size || 0);

            var sent = navigator.sendBeacon(endpoint, formData);
            
            if (sent) {
                log('Beacon sent successfully');
            } else {
                log('Beacon send failed, falling back to fetch');
                sendWithFetch(data, endpoint, nonce);
            }
        } else {
            sendWithFetch(data, endpoint, nonce);
        }
    }

    function sendWithFetch(data, endpoint, nonce) {
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(data),
            keepalive: true
        })
        .then(function(response) {
            if (response.ok) {
                log('Fetch sent successfully');
            } else {
                log('Fetch failed:', response.status);
            }
        })
        .catch(function(error) {
            log('Fetch error:', error);
        });
    }

    if (document.readyState === 'complete') {
        setTimeout(detectLCP, 100);
    } else {
        window.addEventListener('load', function() {
            setTimeout(detectLCP, 100);
        });
    }

})();
