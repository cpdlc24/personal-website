<?php
// ── Fetch data from REST API endpoints ──────────────────────────────────────
$apiBase = 'https://reporting.dominickzou.dev/api';
$ctx = stream_context_create([
    'http' => ['timeout' => 5],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$staticRaw  = @file_get_contents($apiBase . '/static', false, $ctx);
$perfRaw    = @file_get_contents($apiBase . '/performance', false, $ctx);
$activityRaw = @file_get_contents($apiBase . '/activity', false, $ctx);

$staticData    = $staticRaw    ? json_decode($staticRaw, true)    : [];
$perfData      = $perfRaw      ? json_decode($perfRaw, true)      : [];
$activityData  = $activityRaw  ? json_decode($activityRaw, true)  : [];

// ── Process Static / Behavioral data ────────────────────────────────────────
$browsers = [];
$osCounts = [];

foreach ($staticData as $row) {
    $d = $row['data'] ?? [];
    $b = $d['browser'] ?? 'Unknown';
    $o = $d['os'] ?? 'Unknown';
    if ($b && $b !== 'Unknown') $browsers[$b] = ($browsers[$b] ?? 0) + 1;
    if ($o && $o !== 'Unknown') $osCounts[$o] = ($osCounts[$o] ?? 0) + 1;
}
arsort($browsers);
$topBrowser = !empty($browsers) ? array_key_first($browsers) : 'N/A';
$totalBrowserSessions = array_sum($browsers);
$topBrowserPct = $totalBrowserSessions > 0 ? round(reset($browsers) / $totalBrowserSessions * 100) : 0;

// ── Process Performance data ────────────────────────────────────────────────
$loadTimes = [];
$ttfbs = [];
$perfDataTable = [];

// Prepare full raw timeline for JavaScript dynamic bucketing (last 12 hours)
$perfRawList = [];
$nowTs = time();

// Build browser lookup from static data (performance API doesn't include browser)
$browserById = [];
foreach ($staticData as $row) {
    $browserById[$row['id']] = ($row['data']['browser'] ?? 'Unknown');
}

foreach ($perfData as $row) {
    $d = $row['data'] ?? [];
    $load = $d['total_load_time_ms'] ?? $d['loadTime'] ?? $d['fcp'] ?? null;
    $ttfb = $d['navigationTiming']['timeToFirstByte'] ?? null;

    if ($load !== null) {
        $loadTimes[] = (float)$load;
        if ($ttfb !== null) $ttfbs[] = (float)$ttfb;

        $ts = strtotime($row['created_at']);
        // Only include data from the last 12 hours for the mini trend chart
        if ($ts >= ($nowTs - 12 * 3600)) {
            $perfRawList[] = [
                'raw_time' => $ts * 1000,
                'load'     => (float)$load,
                'ttfb'     => $ttfb ? (float)$ttfb : 0
            ];
        }

        // Build benchmarks table (latest 20 — reverse the ascending order)
        if (count($perfDataTable) < 20) {
            array_unshift($perfDataTable, [
                'url'     => rtrim(parse_url($row['page_url'] ?? '', PHP_URL_PATH), '/') ?: '/',
                'browser' => $browserById[$row['id']] ?? 'Unknown',
                'load'    => round((float)$load, 2) . 'ms',
                'ttfb'    => $ttfb ? round((float)$ttfb, 2) . 'ms' : '0ms'
            ]);
        }
    }
}

$avgLoad = count($loadTimes) > 0 ? round(array_sum($loadTimes) / count($loadTimes), 2) : 0;
$avgTtfb = count($ttfbs) > 0 ? round(array_sum($ttfbs) / count($ttfbs), 2) : 0;

$perfRawListJson = json_encode(array_reverse($perfRawList)); // Earliest to latest

// ── Process Activity / Health data ──────────────────────────────────────────
$errors = [];
$errorCountsByDay = [];
$clicks = [];

foreach ($activityData as $row) {
    $d = $row['data'] ?? [];

    // Errors
    if (($d['action'] ?? '') === 'error') {
        $errors[] = [
            'time'    => $row['created_at'],
            'message' => $d['element'] ?? 'Unknown error'
        ];
        $day = substr($row['created_at'], 0, 10);
        $errorCountsByDay[$day] = ($errorCountsByDay[$day] ?? 0) + 1;
    }

    // Clicks for heatmap
    if (($d['action'] ?? '') === 'click' && isset($d['element'])) {
        // Parse "X: 123, Y: 456" format from API
        if (preg_match('/X:\s*([\d.]+),\s*Y:\s*([\d.]+)/', $d['element'], $m)) {
            $clicks[] = [
                'x' => ((float)$m[1] / 1920) * 100,
                'y' => ((float)$m[2] / 1080) * 100
            ];
        }
    }
}

$errors = array_slice($errors, 0, 20);
ksort($errorCountsByDay);
$chartLabels = json_encode(array_keys($errorCountsByDay));
$chartData   = json_encode(array_values($errorCountsByDay));

// ── Aggregate All Events for Raw Telemetry Table ────────────────────────────
// Cross-reference: build a unified per-event table from the three sources
// Use the static endpoint as the base, since it has broader coverage
$allEventsData = [];
$seenIds = [];

// Build a lookup map from performance data by event ID
$perfById = [];
foreach ($perfData as $row) {
    $perfById[$row['id']] = $row;
}

// Build an error-count lookup from activity data by event ID
$errorCountById = [];
foreach ($activityData as $row) {
    $id = $row['id'];
    if (($row['data']['action'] ?? '') === 'error') {
        $errorCountById[$id] = ($errorCountById[$id] ?? 0) + 1;
    }
}

foreach (array_slice($staticData, 0, 100) as $row) {
    $d = $row['data'] ?? [];
    $id = $row['id'];
    $perfRow = $perfById[$id] ?? null;
    $loadStr = 'N/A';
    if ($perfRow) {
        $pd = $perfRow['data'] ?? [];
        $l = $pd['total_load_time_ms'] ?? $pd['loadTime'] ?? $pd['fcp'] ?? null;
        if ($l !== null) $loadStr = round((float)$l, 2) . 'ms';
    }

    $allEventsData[] = [
        'time'   => $row['created_at'],
        'client' => ($d['browser'] ?? 'Unknown') . ' / ' . ($d['os'] ?? 'Unknown'),
        'load'   => $loadStr,
        'errors' => $errorCountById[$id] ?? 0
    ];
}

// Browser Chart Data
$browserDataJson   = json_encode(array_values($browsers));
$browserLabelsJson = json_encode(array_keys($browsers));
$totalEvents  = count($staticData);

require __DIR__ . '/header.php';
?>

<div class="py-8">
    <!-- Title Row -->
    <div class="flex justify-between items-center mb-6 no-print">
        <h1 class="text-4xl font-medium tracking-tighter">Analytics Overview</h1>
        <button onclick="exportToPDF()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-transparent hover:border-gray-900 transition-colors pb-1 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export to PDF
        </button>
    </div>

    <!-- Mini Overview Metrics -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:3rem;margin-top:2rem;" class="pb-12 mb-12" >
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

    <!-- Charts Row 1 -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:3rem;" class="mb-12">
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Error Frequency Trend</h3>
            <div class="relative h-96 w-full">
                <canvas id="healthChart"></canvas>
            </div>
        </div>
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Load Time Trend</h3>
            <div class="relative h-96 w-full">
                <canvas id="miniPerfChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:3rem;">
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Aggregate Click Distribution</h3>
            <div class="relative w-full aspect-video bg-white rounded-lg overflow-hidden border border-gray-100" id="heatmapContainer">
                <iframe id="heatmapIframe" src="https://test.dominickzou.dev" class="absolute top-0 left-0 pointer-events-none border-0 opacity-60" style="width:1920px;height:1080px;transform-origin:0 0;" sandbox="allow-same-origin" loading="lazy"></iframe>
                <div id="heatmapOverlay" class="absolute inset-0 z-10 pointer-events-none"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-6 tracking-widest uppercase text-center">Click coordinates mapped onto the most-visited tracked page.</p>
        </div>
        <div style="background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;padding:3rem;">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 pb-3" style="border-bottom:1px solid rgba(0,0,0,0.04);">Browser Share</h3>
            <div class="relative h-96 w-full flex items-center justify-center">
                <canvas id="browserChart"></canvas>
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('healthChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo $chartLabels; ?>,
                datasets: [{
                    label: 'Errors Over Time',
                    data: <?php echo $chartData; ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)', // Red
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 2,
                    borderSkipped: false,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#f3f4f6' },
                        ticks: {
                            callback: function(value) { return value + ' errs'; }
                        }
                    },
                    x: { grid: { display: false } }
                }
            }
        });

        const chartTimeFormatter = new Intl.DateTimeFormat(undefined, {
            month: '2-digit', day: '2-digit', 
            hour: '2-digit', minute: '2-digit',
            hour12: false
        });

        const rawPerfList = <?php echo $perfRawListJson; ?>;
        const labels = [];
        const loads = [];
        const ttfbs = [];

        if (rawPerfList.length > 0) {
            // Dynamic bucketing algorithm mirroring Performance Page
            const minTime = rawPerfList[0].raw_time;
            const maxTime = rawPerfList[rawPerfList.length - 1].raw_time;
            const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);

            const buckets = {};
            rawPerfList.forEach(r => {
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

        const pCtx = document.getElementById('miniPerfChart').getContext('2d');
        new Chart(pCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Load Time (ms)',
                    data: loads,
                    spanGaps: true,
                    borderColor: 'rgb(37, 99, 235)', // Blue
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: 'rgb(37, 99, 235)',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17,17,17,0.9)',
                        titleFont: { size: 11, weight: '500' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: function(ctx) {
                                return 'Load Time: ' + ctx.parsed.y + 'ms';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#f3f4f6' },
                        ticks: {
                            callback: function(value) { return value + ' ms'; }
                        }
                    },
                    x: { display: false }
                }
            }
        });

        const osCtx = document.getElementById('browserChart').getContext('2d');
        new Chart(osCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo $browserLabelsJson; ?>,
                datasets: [{
                    data: <?php echo $browserDataJson; ?>,
                    backgroundColor: ['#2563eb', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#14b8a6', '#f43f5e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' sessions';
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<script>
    // Heatmap Injector — Interactive with tooltips
    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('heatmapOverlay');
        const container = document.getElementById('heatmapContainer');
        const iframe = document.getElementById('heatmapIframe');
        const rawClicks = <?php echo json_encode($clicks); ?>;
        
        // Scale iframe to fit container exactly
        const updateScale = () => {
            const scale = container.clientWidth / 1920;
            iframe.style.transform = `scale(${scale})`;
        };
        window.addEventListener('resize', updateScale);
        updateScale();
        
        // Aggregate nearby clicks into clusters (within 3% proximity)
        const clusters = [];
        const threshold = 3;
        rawClicks.forEach(c => {
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

        // Tooltip element
        const tooltip = document.createElement('div');
        tooltip.style.cssText = 'position:absolute;z-index:50;background:rgba(17,17,17,0.9);color:#fff;font-size:11px;padding:6px 10px;border-radius:6px;pointer-events:none;opacity:0;transition:opacity 0.15s;white-space:nowrap;font-family:Inter,sans-serif;letter-spacing:0.02em;';
        container.appendChild(tooltip);

        clusters.forEach(c => {
            const size = Math.min(12 + c.count * 6, 48);
            const opacity = Math.min(0.3 + c.count * 0.12, 0.85);
            const dot = document.createElement('div');
            dot.style.position = 'absolute';
            dot.style.width = size + 'px';
            dot.style.height = size + 'px';
            dot.style.borderRadius = '50%';
            dot.style.background = `radial-gradient(circle, rgba(239,68,68,${opacity}) 0%, rgba(239,68,68,${opacity*0.4}) 40%, transparent 70%)`;
            dot.style.left = `calc(${c.x}% - ${size/2}px)`;
            dot.style.top = `calc(${c.y}% - ${size/2}px)`;
            dot.style.cursor = 'default';
            dot.style.pointerEvents = 'auto';
            
            dot.addEventListener('mouseenter', (e) => {
                const pxX = Math.round(c.x / 100 * 1920);
                const pxY = Math.round(c.y / 100 * 1080);
                tooltip.textContent = `${c.count} click${c.count > 1 ? 's' : ''} · (${pxX}, ${pxY})px`;
                tooltip.style.opacity = '1';
                const rect = container.getBoundingClientRect();
                tooltip.style.left = `calc(${c.x}% + ${size/2 + 8}px)`;
                tooltip.style.top = `calc(${c.y}% - 14px)`;
            });
            dot.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';
            });
            
            overlay.appendChild(dot);
        });
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
