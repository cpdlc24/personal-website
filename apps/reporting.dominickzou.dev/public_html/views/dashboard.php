<?php
// ── Fetch data from REST API endpoints ──────────────────────────────────────
$apiBase = 'https://reporting.dominickzou.dev/api';
$ctx = stream_context_create([
    'http' => ['timeout' => 5],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$staticRaw   = @file_get_contents($apiBase . '/static', false, $ctx);
$perfRaw     = @file_get_contents($apiBase . '/performance', false, $ctx);
$activityRaw = @file_get_contents($apiBase . '/activity', false, $ctx);

$staticData    = $staticRaw    ? json_decode($staticRaw, true)    : [];
$perfData      = $perfRaw      ? json_decode($perfRaw, true)      : [];
$activityData  = $activityRaw  ? json_decode($activityRaw, true)  : [];

// ── Time boundary: last 24 hours ────────────────────────────────────────────
$nowTs = time();
$cutoff24h = $nowTs - 24 * 3600;

// ── Collect unique page paths from all data sources ─────────────────────────
$allPages = [];
foreach ($staticData as $row) {
    $url = $row['page_url'] ?? '';
    if ($url) {
        // Strip protocol for display, keep full URL for matching
        $display = preg_replace('#^https?://#', '', rtrim($url, '/')) ?: $url;
        $allPages[$display] = true;
    }
}
foreach ($perfData as $row) {
    $url = $row['page_url'] ?? '';
    if ($url) {
        $display = preg_replace('#^https?://#', '', rtrim($url, '/')) ?: $url;
        $allPages[$display] = true;
    }
}
foreach ($activityData as $row) {
    $url = $row['page_url'] ?? '';
    if ($url) {
        $display = preg_replace('#^https?://#', '', rtrim($url, '/')) ?: $url;
        $allPages[$display] = true;
    }
}
$allPages = array_keys($allPages);
sort($allPages);

// Build browser lookup from static data
$browserById = [];
foreach ($staticData as $row) {
    $browserById[$row['id']] = ($row['data']['browser'] ?? 'Unknown');
}

// ── Process Static data for metrics (last 24h) ─────────────────────────────
$browsers = [];
foreach ($staticData as $row) {
    $ts = strtotime($row['created_at']);
    if ($ts < $cutoff24h) continue;
    $d = $row['data'] ?? [];
    $b = $d['browser'] ?? 'Unknown';
    if ($b && $b !== 'Unknown') $browsers[$b] = ($browsers[$b] ?? 0) + 1;
}
arsort($browsers);
$topBrowser = !empty($browsers) ? array_key_first($browsers) : 'N/A';
$totalBrowserSessions = array_sum($browsers);
$topBrowserPct = $totalBrowserSessions > 0 ? round(reset($browsers) / $totalBrowserSessions * 100) : 0;

// ── Process Performance data (last 24h) — full raw list for JS filtering ───
$loadTimes = [];
$ttfbs = [];
$perfRawList = [];

foreach ($perfData as $row) {
    $d = $row['data'] ?? [];
    $load = $d['total_load_time_ms'] ?? $d['loadTime'] ?? $d['fcp'] ?? null;
    $ttfb = $d['navigationTiming']['timeToFirstByte'] ?? null;
    $ts = strtotime($row['created_at']);
    $url = $row['page_url'] ?? '';
    $host = parse_url($url, PHP_URL_HOST) ?: '';

    if ($load !== null && $ts >= $cutoff24h) {
        $loadTimes[] = (float)$load;
        if ($ttfb !== null) $ttfbs[] = (float)$ttfb;

        $perfRawList[] = [
            'raw_time' => $ts * 1000,
            'load'     => (float)$load,
            'ttfb'     => $ttfb ? (float)$ttfb : 0,
            'page'     => preg_replace('#^https?://#', '', rtrim($url, '/')) ?: $url
        ];
    }
}

$avgLoad = count($loadTimes) > 0 ? round(array_sum($loadTimes) / count($loadTimes), 2) : 0;
$avgTtfb = count($ttfbs) > 0 ? round(array_sum($ttfbs) / count($ttfbs), 2) : 0;
$perfRawListJson = json_encode(array_reverse($perfRawList));

// ── Process Activity / Click data (last 24h) ────────────────────────────────
$clicks = [];
$errors = [];

foreach ($activityData as $row) {
    $d = $row['data'] ?? [];
    $ts = strtotime($row['created_at']);
    if ($ts < $cutoff24h) continue;

    $action = $d['action'] ?? '';
    $url = $row['page_url'] ?? '';
    $host = parse_url($url, PHP_URL_HOST) ?: '';

    if ($action === 'error') {
        $errors[] = true;
    }

    if ($action === 'click' && isset($d['element'])) {
        if (preg_match('/X:\s*([\d.]+),\s*Y:\s*([\d.]+)/', $d['element'], $m)) {
            $clicks[] = [
                'x'    => ((float)$m[1] / 1920) * 100,
                'y'    => ((float)$m[2] / 1080) * 100,
                'page' => preg_replace('#^https?://#', '', rtrim($url, '/')) ?: $url
            ];
        }
    }
}

$totalEvents = count($staticData);

require __DIR__ . '/header.php';
?>

<style>
    .multiselect-wrapper { position: relative; display: inline-block; }
    .multiselect-btn {
        font-size: 11px; font-weight: 500; padding: 6px 28px 6px 14px;
        border-radius: 8px; border: 1px solid rgba(0,0,0,0.08);
        background: rgba(255,255,255,0.95); color: #374151;
        cursor: pointer; outline: none; letter-spacing: 0.03em;
        white-space: nowrap; position: relative; text-align: left;
        transition: border-color 0.15s, box-shadow 0.15s;
        min-width: 100px;
    }
    .multiselect-btn::after {
        content: ''; position: absolute; right: 10px; top: 50%;
        transform: translateY(-50%); border-left: 4px solid transparent;
        border-right: 4px solid transparent; border-top: 4px solid #9ca3af;
    }
    .multiselect-btn:hover { border-color: rgba(0,0,0,0.18); }
    .multiselect-btn.active { border-color: rgb(37,99,235); box-shadow: 0 0 0 2px rgba(37,99,235,0.15); }
    .multiselect-panel {
        display: none; position: absolute; top: calc(100% + 4px); left: 0;
        z-index: 100; background: #fff; border: 1px solid rgba(0,0,0,0.08);
        border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        min-width: 200px; max-height: 300px; overflow-y: auto;
        padding: 6px 0; animation: msPanelIn 0.12s ease-out;
    }
    .multiselect-panel.open { display: block; }
    @keyframes msPanelIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
    .multiselect-panel label {
        display: flex; align-items: center; gap: 8px;
        padding: 6px 14px; font-size: 11px; font-weight: 500;
        color: #374151; cursor: pointer; transition: background 0.1s;
        letter-spacing: 0.02em;
    }
    .multiselect-panel label:hover { background: rgba(37,99,235,0.06); }
    .multiselect-panel input[type="radio"] {
        accent-color: rgb(37,99,235); width: 14px; height: 14px;
        border-radius: 50%; cursor: pointer; flex-shrink: 0;
    }
    .multiselect-panel label.ss-selected {
        background: rgba(37,99,235,0.06); color: rgb(37,99,235); font-weight: 600;
    }
</style>

<div class="py-8">
    <!-- Title Row -->
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-4xl font-medium tracking-tighter">Analytics Overview</h1>
        <?php if (!empty($_SESSION['can_export'])): ?>
        <button onclick="exportToPDF()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-transparent hover:border-gray-900 transition-colors pb-1 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export to PDF
        </button>
        <?php endif; ?>
    </div>

    <p class="text-xl text-gray-500 font-light mb-8">Last 24 Hours</p>

    <!-- Mini Overview Metrics -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3rem;margin-top:2rem;" class="pb-12 mb-12">
        <div>
            <h4 class="text-[10px] tracking-widest uppercase text-gray-400 mb-2 font-semibold">Avg Load Time</h4>
            <div class="text-3xl font-light text-gray-900"><?php echo htmlspecialchars($avgLoad); ?><span class="text-sm text-gray-400 ml-1">ms</span></div>
        </div>
        <div>
            <h4 class="text-[10px] tracking-widest uppercase text-gray-400 mb-2 font-semibold">Avg TTFB</h4>
            <div class="text-3xl font-light text-gray-900"><?php echo htmlspecialchars($avgTtfb); ?><span class="text-sm text-gray-400 ml-1">ms</span></div>
        </div>
        <div>
            <h4 class="text-[10px] tracking-widest uppercase text-gray-400 mb-2 font-semibold">Primary Client</h4>
            <div class="text-3xl font-light text-gray-900"><?php echo htmlspecialchars($topBrowser); ?><span class="text-sm text-gray-400 ml-2"><?php echo $topBrowserPct; ?>%</span></div>
        </div>
        <div>
            <h4 class="text-[10px] tracking-widest uppercase text-gray-400 mb-2 font-semibold">Recent Anomalies</h4>
            <div class="text-3xl font-light <?php echo count($errors) > 0 ? 'text-red-600' : 'text-green-600'; ?>"><?php echo count($errors); ?></div>
        </div>
    </div>

    <!-- Page Filter -->
    <div class="border-b border-gray-100 pb-3 mb-8" style="display:flex;gap:0.75rem;align-items:center;">
        <span class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="margin-right:0.25rem;">Filters</span>
        <div class="multiselect-wrapper" id="pageFilterWrapper">
            <button type="button" class="multiselect-btn" id="pageFilterBtn">All Pages</button>
            <div class="multiselect-panel" id="pageFilterPanel">
                <label class="ss-selected"><input type="radio" name="ss_page" value="" checked> All Pages</label>
                <?php foreach ($allPages as $page): ?>
                <label><input type="radio" name="ss_page" value="<?php echo htmlspecialchars($page); ?>"> <?php echo htmlspecialchars($page); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <button id="clearSiteFilter" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;cursor:pointer;background:none;border:none;padding:6px 8px;transition:color 0.15s;display:none;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#9ca3af'">Clear</button>
    </div>

    <!-- Charts -->
    <div style="display:grid;grid-template-columns:1fr;gap:3rem;" class="mb-12">
        <!-- Load Time vs TTFB -->
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Load Time vs TTFB</h3>
            <div class="relative h-96 w-full">
                <canvas id="miniPerfChart"></canvas>
            </div>
        </div>

        <!-- Aggregate Click Distribution -->
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Aggregate Click Distribution</h3>
            <div class="relative w-full bg-white rounded-lg overflow-hidden border border-gray-100" id="heatmapContainer" style="aspect-ratio:16/9;">
                <iframe id="heatmapIframe" src="https://test.dominickzou.dev" class="absolute top-0 left-0 pointer-events-none border-0 opacity-60" style="width:1920px;height:1080px;transform-origin:0 0;" sandbox="allow-same-origin allow-scripts" loading="lazy"></iframe>
                <div id="heatmapOverlay" class="absolute inset-0 z-10 pointer-events-none"></div>
                <div id="heatmapNoData" class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none" style="display:none;">
                    <div class="text-center">
                        <svg class="mx-auto mb-3" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                        <p class="text-xs font-semibold text-gray-300 tracking-widest uppercase">No Click Data</p>
                        <p class="text-[10px] text-gray-300 mt-1">Adjust filters to see activity</p>
                    </div>
                </div>
            </div>
            <p id="heatmapCaption" class="text-[10px] text-gray-400 mt-6 tracking-widest uppercase text-center">Showing highest activity page: loading...</p>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const chartTimeFormatter = new Intl.DateTimeFormat(undefined, {
            month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit',
            hour12: false
        });

        const allPerfData = <?php echo $perfRawListJson; ?>;
        const allClicks = <?php echo json_encode($clicks); ?>;

        let perfChart = null;

        // ── Build and render Load Time vs TTFB chart ─────────────────────
        function buildPerfChart(data) {
            const labels = [];
            const loads = [];
            const ttfbs = [];

            if (data.length > 0) {
                if (data.length <= 50) {
                    data.forEach(r => {
                        labels.push(chartTimeFormatter.format(new Date(r.raw_time)));
                        loads.push(r.load);
                        ttfbs.push(r.ttfb);
                    });
                } else {
                    const minTime = data[0].raw_time;
                    const maxTime = data[data.length - 1].raw_time;
                    const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);

                    const buckets = {};
                    data.forEach(r => {
                        const b = Math.floor(r.raw_time / bucketMs) * bucketMs;
                        if (!buckets[b]) buckets[b] = { load: [], ttfb: [] };
                        buckets[b].load.push(r.load);
                        buckets[b].ttfb.push(r.ttfb);
                    });

                    let lastBucketTime = null;
                    for (let t = Math.floor(minTime / bucketMs) * bucketMs; t <= maxTime; t += bucketMs) {
                        if (buckets[t]) {
                            if (lastBucketTime !== null && (t - lastBucketTime) > 3600000) {
                                labels.push('');
                                loads.push(null);
                                ttfbs.push(null);
                            }
                            lastBucketTime = t;
                            labels.push(chartTimeFormatter.format(new Date(t)));
                            loads.push(Math.max(...buckets[t].load));
                            ttfbs.push(Math.max(...buckets[t].ttfb));
                        }
                    }
                }
            }

            const showPoints = labels.length <= 50;

            if (!perfChart) {
                const pCtx = document.getElementById('miniPerfChart').getContext('2d');
                perfChart = new Chart(pCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Total Load Time (ms)',
                                data: loads,
                                borderColor: 'rgb(37, 99, 235)',
                                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                                borderWidth: 2, tension: 0.4, spanGaps: true, fill: true,
                                pointRadius: showPoints ? 4 : 0,
                                pointBackgroundColor: 'rgb(37, 99, 235)',
                                pointHoverRadius: 5, pointHoverBackgroundColor: 'rgb(37, 99, 235)',
                                pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
                            },
                            {
                                label: 'Time To First Byte (ms)',
                                data: ttfbs,
                                borderColor: 'rgb(22, 163, 74)',
                                backgroundColor: 'rgba(22, 163, 74, 0.1)',
                                borderWidth: 2, tension: 0.4, spanGaps: true, fill: true,
                                pointRadius: showPoints ? 4 : 0,
                                pointBackgroundColor: 'rgb(22, 163, 74)',
                                pointHoverRadius: 5, pointHoverBackgroundColor: 'rgb(22, 163, 74)',
                                pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            tooltip: {
                                backgroundColor: 'rgba(17,17,17,0.9)',
                                titleFont: { size: 11, weight: '500' },
                                bodyFont: { size: 12 },
                                padding: 10, cornerRadius: 6
                            }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { callback: v => v + ' ms' } },
                            x: { display: false }
                        }
                    }
                });
            } else {
                perfChart.data.labels = labels;
                perfChart.data.datasets[0].data = loads;
                perfChart.data.datasets[0].pointRadius = showPoints ? 4 : 0;
                perfChart.data.datasets[1].data = ttfbs;
                perfChart.data.datasets[1].pointRadius = showPoints ? 4 : 0;
                perfChart.update();
            }
        }

        // ── Heatmap rendering ────────────────────────────────────────────
        const overlay = document.getElementById('heatmapOverlay');
        const container = document.getElementById('heatmapContainer');
        const iframe = document.getElementById('heatmapIframe');

        const updateScale = () => {
            const containerW = container.offsetWidth;
            const containerH = container.offsetHeight;
            const scaleX = containerW / 1920;
            const scaleY = containerH / 1080;
            const scale = Math.max(scaleX, scaleY);
            iframe.style.transform = `scale(${scale})`;
            iframe.style.transformOrigin = '0 0';
        };
        window.addEventListener('resize', updateScale);
        updateScale();

        const heatmapTooltip = document.createElement('div');
        heatmapTooltip.style.cssText = 'position:absolute;z-index:50;background:rgba(17,17,17,0.9);color:#fff;font-size:11px;padding:6px 10px;border-radius:6px;pointer-events:none;opacity:0;transition:opacity 0.15s;white-space:nowrap;font-family:Inter,sans-serif;letter-spacing:0.02em;';
        container.appendChild(heatmapTooltip);

        function renderHeatmap(clicks) {
            overlay.querySelectorAll('.heatmap-dot').forEach(el => el.remove());
            const caption = document.getElementById('heatmapCaption');
            const noDataEl = document.getElementById('heatmapNoData');

            if (clicks.length === 0) {
                noDataEl.style.display = 'flex';
                caption.textContent = 'No click data for current filters.';
                return;
            }
            noDataEl.style.display = 'none';

            // Determine highest-activity page
            const pageCounts = {};
            clicks.forEach(c => {
                if (c.page) pageCounts[c.page] = (pageCounts[c.page] || 0) + 1;
            });
            let topPage = '';
            let topCount = 0;
            for (const [page, count] of Object.entries(pageCounts)) {
                if (count > topCount) { topPage = page; topCount = count; }
            }

            if (topPage) {
                const iframeSrc = 'https://' + topPage;
                if (iframe.src !== iframeSrc) iframe.src = iframeSrc;
            }
            caption.textContent = topPage
                ? 'Showing highest activity page: ' + topPage
                : 'No click data for current filters.';

            // Filter clicks to only the top page
            const pageClicks = topPage ? clicks.filter(c => c.page === topPage) : clicks;

            const clusters = [];
            const threshold = 3;
            pageClicks.forEach(c => {
                let found = clusters.find(cl =>
                    Math.abs(cl.x - c.x) < threshold && Math.abs(cl.y - c.y) < threshold
                );
                if (found) {
                    found.count++;
                    found.x = (found.x * (found.count - 1) + c.x) / found.count;
                    found.y = (found.y * (found.count - 1) + c.y) / found.count;
                } else {
                    clusters.push({ x: c.x, y: c.y, count: 1 });
                }
            });

            clusters.forEach(c => {
                const size = Math.min(12 + c.count * 6, 48);
                const opacity = Math.min(0.3 + c.count * 0.12, 0.85);
                const dot = document.createElement('div');
                dot.className = 'heatmap-dot';
                dot.style.position = 'absolute';
                dot.style.width = size + 'px';
                dot.style.height = size + 'px';
                dot.style.borderRadius = '50%';
                dot.style.background = `radial-gradient(circle, rgba(239,68,68,${opacity}) 0%, rgba(239,68,68,${opacity*0.4}) 40%, transparent 70%)`;
                dot.style.left = `calc(${c.x}% - ${size/2}px)`;
                dot.style.top = `calc(${c.y}% - ${size/2}px)`;
                dot.style.cursor = 'default';
                dot.style.pointerEvents = 'auto';

                dot.addEventListener('mouseenter', () => {
                    const pxX = Math.round(c.x / 100 * 1920);
                    const pxY = Math.round(c.y / 100 * 1080);
                    heatmapTooltip.textContent = `${c.count} click${c.count > 1 ? 's' : ''} · (${pxX}, ${pxY})px`;
                    heatmapTooltip.style.opacity = '1';
                    heatmapTooltip.style.left = `calc(${c.x}% + ${size/2 + 8}px)`;
                    heatmapTooltip.style.top = `calc(${c.y}% - 14px)`;
                });
                dot.addEventListener('mouseleave', () => {
                    heatmapTooltip.style.opacity = '0';
                });

                overlay.appendChild(dot);
            });
        }

        // ── Single-select page filter widget ─────────────────────────────
        const wrapper = document.getElementById('pageFilterWrapper');
        const filterBtn = document.getElementById('pageFilterBtn');
        const filterPanel = document.getElementById('pageFilterPanel');
        const radios = filterPanel.querySelectorAll('input[type="radio"]');
        const labels = filterPanel.querySelectorAll('label');
        const clearBtn = document.getElementById('clearSiteFilter');
        let selectedPage = '';

        filterBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            filterPanel.classList.toggle('open');
            filterBtn.classList.toggle('active');
        });

        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                labels.forEach(l => l.classList.remove('ss-selected'));
                radio.closest('label').classList.add('ss-selected');
                filterBtn.textContent = radio.closest('label').textContent.trim();
                filterPanel.classList.remove('open');
                filterBtn.classList.remove('active');
                selectedPage = radio.value;
                clearBtn.style.display = selectedPage ? 'inline-block' : 'none';
                applyFilter();
            });
        });

        clearBtn.addEventListener('click', () => {
            selectedPage = '';
            labels.forEach(l => l.classList.remove('ss-selected'));
            radios[0].checked = true;
            radios[0].closest('label').classList.add('ss-selected');
            filterBtn.textContent = 'All Pages';
            clearBtn.style.display = 'none';
            applyFilter();
        });

        // Close panel on outside click
        document.addEventListener('click', () => {
            filterPanel.classList.remove('open');
            filterBtn.classList.remove('active');
        });
        filterPanel.addEventListener('click', e => e.stopPropagation());

        function applyFilter() {
            const filteredPerf = selectedPage
                ? allPerfData.filter(r => r.page === selectedPage)
                : allPerfData;
            const filteredClicks = selectedPage
                ? allClicks.filter(c => c.page === selectedPage)
                : allClicks;

            buildPerfChart(filteredPerf);
            renderHeatmap(filteredClicks);
        }

        // Initial render
        applyFilter();
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
