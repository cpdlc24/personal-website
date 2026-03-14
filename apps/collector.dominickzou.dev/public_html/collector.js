/**
 * Adapted from Perfume.js for CSE 135
 * https://github.com/Zizzamia/perfume.js
 */
(function() {
    const W = window;
    const C = W.console;
    const D = document;
    const WN = W.navigator;
    const WP = W.performance;
    const getDM = () => WN.deviceMemory;
    const getHC = () => WN.hardwareConcurrency;

    const config = {
        isResourceTiming: false,
        maxTime: 15000,
    };

    /* ---- Web Vitals tracking state ---- */

    let perfObservers = {};
    let fp  = { value: 0 };
    let fcp = { value: 0 };
    let fid = { value: 0 };
    let lcp = { value: 0 };
    let cls = { value: 0 };
    let tbt = { value: 0 };
    const fcpEntryName = "first-contentful-paint";
    let rt = {
        value: {
            beacon: 0, css: 0, fetch: 0, img: 0,
            other: 0, script: 0, total: 0, xmlhttprequest: 0,
        },
    };

    const fcpScore = [1000, 2500];
    const lcpScore = [2500, 4000];
    const clsScore = [0.1, 0.25];
    const tbtScore = [300, 600];
    const webVitalsScore = {
        fp: fcpScore, fcp: fcpScore,
        lcp: lcpScore, lcpFinal: lcpScore,
        fid: [100, 300],
        cls: clsScore, clsFinal: clsScore,
        tbt: tbtScore, tbt5S: tbtScore, tbt10S: tbtScore, tbtFinal: tbtScore,
    };

    /* ---- Session Management ---- */

    function getSessionId() {
        let sid = sessionStorage.getItem('_collector_sid');
        if (!sid) {
            sid = Math.random().toString(36).substring(2) + Date.now().toString(36);
            sessionStorage.setItem('_collector_sid', sid);
        }
        return sid;
    }

    const sessionId = getSessionId();

    /* ---- Payload ---- */

    const payload = {
        session_id: sessionId,
        page_url: W.location.href,
        static: {},
        performance: {},
        technographics: {},
        activity: {
            errors: [],
            mouse: { moves: [], clicks: [], scrolls: [] },
            keyboard: [],
            idles: [],
            enter_time: Date.now(),
            page: W.location.pathname,
        },
    };

    /* ---- Utilities ---- */

    function roundByTwo(num) {
        return !Number.isNaN(num) ? Number.parseFloat(num).toFixed(2) : num;
    }

    function convertToKB(bytes) {
        if (typeof bytes !== "number") return null;
        return roundByTwo(bytes / Math.pow(1024, 2));
    }

    function pushTask(cb) {
        if ("requestIdleCallback" in W) {
            W.requestIdleCallback(cb, { timeout: 3000 });
        } else {
            cb();
        }
    }

    function getVitalsScore(measureName, value) {
        if (!webVitalsScore[measureName]) return null;
        if (value <= webVitalsScore[measureName][0]) return "good";
        return value <= webVitalsScore[measureName][1] ? "needsImprovement" : "poor";
    }

    /**
     * True if the browser supports the Navigation Timing API,
     * User Timing API and the PerformanceObserver Interface.
     */
    function isPerformanceSupported() {
        return WP && !!WP.getEntriesByType && !!WP.now && !!WP.mark;
    }

    /* ---- Network & Device Information ---- */

    function getNetworkInformation() {
        if ("connection" in WN) {
            const conn = WN.connection;
            if (typeof conn !== "object") return {};
            return {
                downlink: conn.downlink,
                effectiveType: conn.effectiveType,
                rtt: conn.rtt,
                saveData: !!conn.saveData,
            };
        }
        return {};
    }

    function getIsLowEndDevice() {
        if (getHC() && getHC() <= 4) return true;
        if (getDM() && getDM() <= 4) return true;
        return false;
    }

    function getIsLowEndExperience() {
        if (getIsLowEndDevice()) return true;
        const net = getNetworkInformation();
        if (["slow-2g", "2g", "3g"].includes(net.effectiveType)) return true;
        return false;
    }

    function getNavigatorInfo() {
        if (!WN) return {};
        return {
            deviceMemory: getDM() || 0,
            hardwareConcurrency: getHC() || 0,
            serviceWorkerStatus:
                "serviceWorker" in WN
                    ? WN.serviceWorker.controller ? "controlled" : "supported"
                    : "unsupported",
            isLowEndDevice: getIsLowEndDevice(),
            isLowEndExperience: getIsLowEndExperience(),
        };
    }

    /**
     * Navigation Timing Level 2 via getEntriesByType("navigation").
     * Provides fetchTime, TTFB, download time, DNS lookup, etc.
     */
    function getNavigationTiming() {
        if (!isPerformanceSupported()) return {};
        const n = WP.getEntriesByType("navigation")[0];
        if (!n) return {};
        const responseStart = n.responseStart;
        const responseEnd = n.responseEnd;
        return {
            fetchTime: responseEnd - n.fetchStart,
            workerTime: n.workerStart > 0 ? responseEnd - n.workerStart : 0,
            totalTime: responseEnd - n.requestStart,
            downloadTime: responseEnd - responseStart,
            timeToFirstByte: responseStart,
            headerSize: n.transferSize - n.encodedBodySize || 0,
            dnsLookupTime: n.domainLookupEnd - n.domainLookupStart,
        };
    }

    /* ---- Static Data Collection ---- */

    function collectStaticData() {
        payload.static = {
            user_agent: WN.userAgent,
            language: WN.language || WN.userLanguage,
            cookies_enabled: WN.cookieEnabled,
            javascript_enabled: true,
            screen_width: W.screen.width,
            screen_height: W.screen.height,
            inner_width: W.innerWidth,
            inner_height: W.innerHeight,
            outer_width: W.outerWidth,
            outer_height: W.outerHeight,
            network: getNetworkInformation(),
        };

        const testImage = new Image();
        testImage.onload = function() { payload.static.images_allowed = true; };
        testImage.onerror = function() { payload.static.images_allowed = false; };
        testImage.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        const testDiv = D.createElement('div');
        testDiv.style.display = 'none';
        D.body.appendChild(testDiv);
        payload.static.css_allowed = W.getComputedStyle(testDiv).display === 'none';
        D.body.removeChild(testDiv);
    }

    /* ---- Technographics ---- */

    function collectTechnographics() {
        payload.technographics = {
            navigatorInfo: getNavigatorInfo(),
            pixelRatio: W.devicePixelRatio,
            colorScheme: W.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        };
    }

    /* ---- Performance: PerformanceObserver ---- */

    function po(eventType, cb) {
        try {
            const perfObserver = new PerformanceObserver(function(list) {
                cb(list.getEntries());
            });
            perfObserver.observe({ type: eventType, buffered: true });
            return perfObserver;
        } catch (e) {
            C.warn("Collector:", e);
        }
        return null;
    }

    function poDisconnect(observer) {
        if (perfObservers[observer]) {
            perfObservers[observer].disconnect();
        }
        delete perfObservers[observer];
    }

    function initFirstPaint(performanceEntries) {
        performanceEntries.forEach(function(entry) {
            if (entry.name === "first-paint") {
                fp.value = entry.startTime;
            } else if (entry.name === fcpEntryName) {
                fcp.value = entry.startTime;
                perfObservers[4] = po("longtask", initTotalBlockingTime);
                poDisconnect(0);
            }
        });
    }

    function initFirstInputDelay(performanceEntries) {
        const lastEntry = performanceEntries.pop();
        if (lastEntry) fid.value = lastEntry.duration;
        poDisconnect(1);
        poDisconnect(2);
    }

    function initLargestContentfulPaint(performanceEntries) {
        const lastEntry = performanceEntries.pop();
        if (lastEntry) lcp.value = lastEntry.renderTime || lastEntry.loadTime;
    }

    function initLayoutShift(performanceEntries) {
        const lastEntry = performanceEntries.pop();
        if (lastEntry && !lastEntry.hadRecentInput && lastEntry.value) {
            cls.value += lastEntry.value;
        }
    }

    function initTotalBlockingTime(performanceEntries) {
        performanceEntries.forEach(function(entry) {
            if (entry.name !== "self" || entry.startTime < fcp.value) return;
            const blockingTime = entry.duration - 50;
            if (blockingTime > 0) tbt.value += blockingTime;
        });
    }

    function initResourceTiming(performanceEntries) {
        performanceEntries.forEach(function(entry) {
            if (entry.decodedBodySize && entry.initiatorType) {
                const bodySize = entry.decodedBodySize / 1000;
                rt.value[entry.initiatorType] = (rt.value[entry.initiatorType] || 0) + bodySize;
                rt.value.total += bodySize;
            }
        });
    }

    function initPerformanceObserver() {
        perfObservers[0] = po("paint", initFirstPaint);
        perfObservers[1] = po("first-input", initFirstInputDelay);
        perfObservers[2] = po("largest-contentful-paint", initLargestContentfulPaint);
        if (config.isResourceTiming) po("resource", initResourceTiming);
        perfObservers[3] = po("layout-shift", initLayoutShift);
    }

    /**
     * Snapshot current Web Vitals values into the payload
     * so they are included in the next send.
     */
    function updateVitalsSnapshot() {
        if (fp.value > 0) {
            payload.performance.fp = roundByTwo(fp.value);
            payload.performance.fp_score = getVitalsScore("fp", fp.value);
        }
        if (fcp.value > 0) {
            payload.performance.fcp = roundByTwo(fcp.value);
            payload.performance.fcp_score = getVitalsScore("fcp", fcp.value);
        }
        if (fid.value > 0) {
            payload.performance.fid = roundByTwo(fid.value);
            payload.performance.fid_score = getVitalsScore("fid", fid.value);
        }
        if (lcp.value > 0) {
            payload.performance.lcp = roundByTwo(lcp.value);
            payload.performance.lcp_score = getVitalsScore("lcp", lcp.value);
        }
        if (cls.value > 0) {
            payload.performance.cls = roundByTwo(cls.value);
            payload.performance.cls_score = getVitalsScore("cls", cls.value);
        }
        if (tbt.value > 0) {
            payload.performance.tbt = roundByTwo(tbt.value);
            payload.performance.tbt_score = getVitalsScore("tbt", tbt.value);
        }
    }

    /**
     * Collect page-load timing via the legacy timing API (for the full
     * timing object, page start/end load, and total load time) plus
     * Navigation Timing Level 2 details and storage estimate.
     */
    function collectPerformanceData() {
        payload.performance.navigationTiming = getNavigationTiming();

        if (WP && WP.timing) {
            const timing = WP.timing;
            const loadTime = timing.loadEventEnd - timing.navigationStart;
            payload.performance.timing_object = JSON.parse(JSON.stringify(timing));
            payload.performance.page_start_load = timing.navigationStart;
            payload.performance.page_end_load = timing.loadEventEnd;
            payload.performance.total_load_time_ms = loadTime > 0 ? loadTime : null;
        }

        if (WN && WN.storage && typeof WN.storage.estimate === "function") {
            WN.storage.estimate().then(function(storageInfo) {
                const usageDetails = "usageDetails" in storageInfo ? storageInfo.usageDetails : {};
                payload.performance.storageEstimate = {
                    quota: convertToKB(storageInfo.quota),
                    usage: convertToKB(storageInfo.usage),
                    caches: convertToKB(usageDetails.caches),
                    indexedDB: convertToKB(usageDetails.indexedDB),
                    serviceWorker: convertToKB(usageDetails.serviceWorkerRegistrations),
                };
            });
        }
    }

    /* ---- Activity Tracking ---- */

    W.onerror = function(msg, url, lineNo, columnNo, error) {
        payload.activity.errors.push({
            name: error ? error.name : 'Error',
            message: msg,
            url: url,
            lineno: lineNo,
            colno: columnNo,
            stack: error ? error.stack : null,
            time: Date.now(),
        });
    };

    let lastMouseMoveTime = 0;
    W.addEventListener('mousemove', function(e) {
        const now = Date.now();
        if (now - lastMouseMoveTime > 200) {
            payload.activity.mouse.moves.push({ x: e.clientX, y: e.clientY, time: now });
            lastMouseMoveTime = now;
        }
        resetIdleTimer();
    });

    W.addEventListener('mousedown', function(e) {
        payload.activity.mouse.clicks.push({
            x: e.clientX, y: e.clientY, button: e.button, time: Date.now(),
        });
        resetIdleTimer();
    });

    let lastScrollTime = 0;
    W.addEventListener('scroll', function() {
        const now = Date.now();
        if (now - lastScrollTime > 300) {
            payload.activity.mouse.scrolls.push({ x: W.scrollX, y: W.scrollY, time: now });
            lastScrollTime = now;
        }
        resetIdleTimer();
    });

    W.addEventListener('keydown', function(e) {
        payload.activity.keyboard.push({ type: 'down', key: e.key, time: Date.now() });
        resetIdleTimer();
    });

    W.addEventListener('keyup', function(e) {
        payload.activity.keyboard.push({ type: 'up', key: e.key, time: Date.now() });
        resetIdleTimer();
    });

    /* Idle Tracking: any gap of >= 2 seconds with no user interaction */
    let idleTimer;
    let idleStartTime = Date.now();
    let isIdle = false;

    function resetIdleTimer() {
        if (isIdle) {
            const endTime = Date.now();
            const duration = endTime - idleStartTime;
            if (duration >= 2000) {
                payload.activity.idles.push({
                    start: idleStartTime,
                    end: endTime,
                    duration: duration,
                });
            }
            isIdle = false;
        }
        clearTimeout(idleTimer);
        idleStartTime = Date.now();
        idleTimer = setTimeout(function() {
            isIdle = true;
        }, 2000);
    }
    resetIdleTimer();

    /* ---- Data Transmission ---- */

    const endpoint = 'https://collector.dominickzou.dev/log/index.php';

    function sendData(isUnload) {
        if (isUnload) {
            payload.activity.leave_time = Date.now();
            if (isIdle) {
                const endTime = Date.now();
                payload.activity.idles.push({
                    start: idleStartTime,
                    end: endTime,
                    duration: endTime - idleStartTime,
                });
            }
        }

        updateVitalsSnapshot();

        const hasActivity = payload.activity.errors.length > 0 ||
            payload.activity.mouse.moves.length > 0 ||
            payload.activity.mouse.clicks.length > 0 ||
            payload.activity.mouse.scrolls.length > 0 ||
            payload.activity.keyboard.length > 0 ||
            payload.activity.idles.length > 0;
        const hasStaticData = Object.keys(payload.static).length > 0 ||
            Object.keys(payload.performance).length > 0 ||
            Object.keys(payload.technographics).length > 0;

        if (!isUnload && !hasActivity && !hasStaticData) return;

        const dataStr = JSON.stringify(payload);

        if (isUnload && WN.sendBeacon) {
            WN.sendBeacon(endpoint, dataStr);
        } else {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: dataStr,
                keepalive: !!isUnload,
            }).catch(function(err) { C.error(err); });
        }

        if (!isUnload) {
            payload.activity.errors = [];
            payload.activity.mouse.moves = [];
            payload.activity.mouse.clicks = [];
            payload.activity.mouse.scrolls = [];
            payload.activity.keyboard = [];
            payload.activity.idles = [];
            payload.static = {};
            payload.performance = {};
            payload.technographics = {};
        }
    }

    setInterval(function() { sendData(false); }, 5000);

    /* ---- Initialization & Lifecycle ---- */

    W.addEventListener('load', function() {
        setTimeout(function() {
            if (isPerformanceSupported() && "PerformanceObserver" in W) {
                initPerformanceObserver();
            }
            collectStaticData();
            collectTechnographics();
            collectPerformanceData();
            sendData(false);
        }, 500);
    });

    D.addEventListener('visibilitychange', function() {
        if (D.visibilityState === 'hidden') sendData(true);
    });

    W.addEventListener('beforeunload', function() {
        sendData(true);
    });

})();
