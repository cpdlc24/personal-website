<?php
// ── Fetch data from REST API endpoints ──────────────────────────────────────
$apiBase = 'https://reporting.dominickzou.dev/api';
$ctx = stream_context_create([
    'http' => ['timeout' => 5],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$perfRaw   = @file_get_contents($apiBase . '/performance', false, $ctx);
$staticRaw = @file_get_contents($apiBase . '/static', false, $ctx);

$perfData   = $perfRaw   ? json_decode($perfRaw, true)   : [];
$staticData = $staticRaw ? json_decode($staticRaw, true) : [];

// Build session-to-client lookup from static data
$sessionClient = [];
foreach ($staticData as $row) {
    $sid = $row['session_id'] ?? '';
    if ($sid && !isset($sessionClient[$sid])) {
        $d = $row['data'] ?? [];
        $sessionClient[$sid] = [
            'browser'  => $d['browser'] ?? 'Unknown',
            'os'       => $d['os'] ?? 'Unknown',
            'screen'   => ($d['screen_width'] ?? '?') . '×' . ($d['screen_height'] ?? '?'),
            'language' => $d['language'] ?? '—',
        ];
    }
}

$timeLabels = [];
$loadTimes = [];
$ttfbTimes = [];
$dataTable = [];

foreach($perfData as $row) {
    $d = $row['data'] ?? [];
    $load = $d['total_load_time_ms'] ?? $d['loadTime'] ?? $d['fcp'] ?? null;
    $ttfb = $d['navigationTiming']['timeToFirstByte'] ?? null;
    $sid  = $row['session_id'] ?? '';
    $client = $sessionClient[$sid] ?? ['browser' => 'Unknown', 'os' => 'Unknown', 'screen' => '—', 'language' => '—'];
    
    if ($load !== null) {
        $timeStr = date('m/d H:i', strtotime($row['created_at']));
        $timeLabels[] = $timeStr;
        $loadTimes[] = round((float)$load, 2);
        
        $ttfbValue = $ttfb ? round((float)$ttfb, 2) : 0;
        $ttfbTimes[] = $ttfbValue;
        
        $dataTable[] = [
            'url'      => $row['page_url'],
            'raw_time' => strtotime($row['created_at']) * 1000,
            'browser'  => $client['browser'],
            'os'       => $client['os'],
            'screen'   => $client['screen'],
            'language' => $client['language'],
            'load'     => round((float)$load, 2) . 'ms',
            'raw_load' => round((float)$load, 2),
            'ttfb'     => $ttfbValue . 'ms',
            'raw_ttfb' => $ttfbValue
        ];
    }
}

$uniquePaths = array_values(array_unique(array_column($dataTable, 'url')));
$uniqueBrowsers = array_values(array_unique(array_column($dataTable, 'browser')));
$uniquePlatforms = array_values(array_unique(array_column($dataTable, 'os')));
$uniqueScreens = array_values(array_unique(array_column($dataTable, 'screen')));
$uniqueLocales = array_values(array_unique(array_column($dataTable, 'language')));

sort($uniquePaths);
sort($uniqueBrowsers);
sort($uniquePlatforms);
sort($uniqueScreens);
sort($uniqueLocales);

$serverTimeMs = time() * 1000;
$timeLabels = array_reverse($timeLabels);
$loadTimes = array_reverse($loadTimes);
$ttfbTimes = array_reverse($ttfbTimes);

// The full dataset will be loaded asynchronously by JavaScript
$apiBaseJs = $apiBase;
require __DIR__ . '/header.php';
?>

<div class="py-12">
    <div class="mb-12">
        <h1 class="text-4xl font-medium tracking-tighter mb-4 text-gray-900">Performance Metrics</h1>
        <p class="text-xl text-gray-500 font-light">Analyzing Total Load Times and Time-to-First-Byte across endpoints.</p>
    </div>

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
        .multiselect-btn .ms-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: rgb(37,99,235); color: #fff; font-size: 9px; font-weight: 700;
            border-radius: 50%; width: 16px; height: 16px; margin-left: 6px;
            vertical-align: middle;
        }
        .multiselect-panel {
            display: none; position: absolute; top: calc(100% + 4px); left: 0;
            z-index: 100; background: #fff; border: 1px solid rgba(0,0,0,0.08);
            border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            min-width: 200px; max-height: 260px; overflow-y: auto;
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
        .multiselect-panel input[type="checkbox"],
        .multiselect-panel input[type="radio"] {
            accent-color: rgb(37,99,235); width: 14px; height: 14px;
            border-radius: 4px; cursor: pointer; flex-shrink: 0;
        }
        .multiselect-panel input[type="radio"] { border-radius: 50%; }
        .multiselect-panel .ms-select-all {
            border-bottom: 1px solid rgba(0,0,0,0.06); margin-bottom: 2px; padding-bottom: 8px;
        }
        .multiselect-panel label.ss-selected {
            background: rgba(37,99,235,0.06); color: rgb(37,99,235); font-weight: 600;
        }
        #legendHint {
            font-size: 10px; color: #6b7280; font-weight: 500;
            letter-spacing: 0.03em; transition: opacity 0.2s;
            background: rgba(107,114,128,0.08); padding: 4px 12px;
            border-radius: 6px; display: none; white-space: nowrap;
        }
        .chart-header {
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            border-bottom: 1px solid rgba(0,0,0,0.04); padding-bottom: 8px; margin-bottom: 24px;
        }
        .chart-header h3 {
            margin: 0; font-size: 10px; letter-spacing: 0.1em;
            text-transform: uppercase; font-weight: 500; color: #9ca3af;
        }
        .compare-controls {
            display: none; align-items: center; gap: 8px; margin-left: auto;
        }
        .compare-controls.visible { display: flex; }
        .compare-controls .vs-label {
            font-size: 10px; font-weight: 700; color: #9ca3af;
            text-transform: uppercase; letter-spacing: 0.08em;
        }
    </style>

    <div class="mb-24 relative">
        <div class="chart-header">
            <h3>Load Time vs TTFB</h3>
            <!-- Chart mode single-select -->
            <div class="multiselect-wrapper" data-ss-chartmode="1">
                <button type="button" class="multiselect-btn" data-ss-default="Average">Average</button>
                <div class="multiselect-panel">
                    <label class="ss-selected"><input type="radio" name="ss_chartmode" value="average" checked> Average</label>
                    <label><input type="radio" name="ss_chartmode" value="compare"> Compare</label>
                </div>
            </div>
            <span id="legendHint">Click legend items to hide/show series</span>
            <div id="compareControls" class="compare-controls">
                <!-- Compare site 1 single-select -->
                <div class="multiselect-wrapper" id="compareSite1Wrapper">
                    <button type="button" class="multiselect-btn" data-ss-default="Option 1" style="min-width:120px;">Option 1</button>
                    <div class="multiselect-panel" id="compareSite1Panel"></div>
                </div>
                <span class="vs-label">vs</span>
                <!-- Compare site 2 single-select -->
                <div class="multiselect-wrapper" id="compareSite2Wrapper">
                    <button type="button" class="multiselect-btn" data-ss-default="Option 2" style="min-width:120px;">Option 2</button>
                    <div class="multiselect-panel" id="compareSite2Panel" style="right:0;left:auto;"></div>
                </div>
            </div>
        </div>
        <div class="relative h-80 w-full">
            <canvas id="perfChart"></canvas>
        </div>
    </div>

    <!-- Data Table -->
    <div>
        <!-- Filter Bar -->
        <div class="filter-bar border-b border-gray-100 pb-3 mb-8" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <span class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="margin-right:0.25rem;">Filters</span>

                <!-- Single-select: Time -->
                <div class="multiselect-wrapper" data-ss-col="0">
                    <button type="button" class="multiselect-btn" data-ss-default="&lt; 24 Hours" data-ss-all-label="All Time">&lt; 24 Hours</button>
                    <div class="multiselect-panel">
                        <label class="ss-selected"><input type="radio" name="ss_0" value="" > All Time</label>
                        <label><input type="radio" name="ss_0" value="1h"> &lt; 1 Hour</label>
                        <label><input type="radio" name="ss_0" value="3h"> &lt; 3 Hours</label>
                        <label><input type="radio" name="ss_0" value="12h"> &lt; 12 Hours</label>
                        <label class="ss-selected"><input type="radio" name="ss_0" value="24h" checked> &lt; 24 Hours</label>
                        <label><input type="radio" name="ss_0" value="7d"> &lt; 7 Days</label>
                        <label><input type="radio" name="ss_0" value="30d"> &lt; 30 Days</label>
                        <label><input type="radio" name="ss_0" value="12h+"> &gt; 12 Hours</label>
                    </div>
                </div>

                <!-- Multi-select: Pages -->
                <div class="multiselect-wrapper" data-ms-col="1">
                    <button type="button" class="multiselect-btn" data-ms-label="All Pages">All Pages</button>
                    <div class="multiselect-panel">
                        <label class="ms-select-all"><input type="checkbox" data-ms-all checked> Select All</label>
                        <?php foreach($uniquePaths as $val): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($val); ?>" checked> <?php echo htmlspecialchars($val); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Single-select: Load -->
                <div class="multiselect-wrapper" data-ss-col="2">
                    <button type="button" class="multiselect-btn" data-ss-default="All Load" data-ss-all-label="All Load">All Load</button>
                    <div class="multiselect-panel">
                        <label class="ss-selected"><input type="radio" name="ss_2" value="" checked> All Load</label>
                        <label><input type="radio" name="ss_2" value="100"> &lt; 100ms</label>
                        <label><input type="radio" name="ss_2" value="500"> &lt; 500ms</label>
                        <label><input type="radio" name="ss_2" value="1000"> &lt; 1000ms</label>
                        <label><input type="radio" name="ss_2" value="1000+"> &gt; 1000ms</label>
                    </div>
                </div>

                <!-- Single-select: TTFB -->
                <div class="multiselect-wrapper" data-ss-col="3">
                    <button type="button" class="multiselect-btn" data-ss-default="All TTFB" data-ss-all-label="All TTFB">All TTFB</button>
                    <div class="multiselect-panel">
                        <label class="ss-selected"><input type="radio" name="ss_3" value="" checked> All TTFB</label>
                        <label><input type="radio" name="ss_3" value="20"> &lt; 20ms</label>
                        <label><input type="radio" name="ss_3" value="50"> &lt; 50ms</label>
                        <label><input type="radio" name="ss_3" value="100"> &lt; 100ms</label>
                        <label><input type="radio" name="ss_3" value="100+"> &gt; 100ms</label>
                    </div>
                </div>

                <!-- Multi-select: Browsers -->
                <div class="multiselect-wrapper" data-ms-col="4">
                    <button type="button" class="multiselect-btn" data-ms-label="All Browsers">All Browsers</button>
                    <div class="multiselect-panel">
                        <label class="ms-select-all"><input type="checkbox" data-ms-all checked> Select All</label>
                        <?php foreach($uniqueBrowsers as $val): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($val); ?>" checked> <?php echo htmlspecialchars($val); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Platforms -->
                <div class="multiselect-wrapper" data-ms-col="5">
                    <button type="button" class="multiselect-btn" data-ms-label="All Platforms">All Platforms</button>
                    <div class="multiselect-panel">
                        <label class="ms-select-all"><input type="checkbox" data-ms-all checked> Select All</label>
                        <?php foreach($uniquePlatforms as $val): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($val); ?>" checked> <?php echo htmlspecialchars($val); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Screens -->
                <div class="multiselect-wrapper" data-ms-col="6">
                    <button type="button" class="multiselect-btn" data-ms-label="All Screens">All Screens</button>
                    <div class="multiselect-panel">
                        <label class="ms-select-all"><input type="checkbox" data-ms-all checked> Select All</label>
                        <?php foreach($uniqueScreens as $val): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($val); ?>" checked> <?php echo htmlspecialchars($val); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Locales -->
                <div class="multiselect-wrapper" data-ms-col="7">
                    <button type="button" class="multiselect-btn" data-ms-label="All Locales">All Locales</button>
                    <div class="multiselect-panel">
                        <label class="ms-select-all"><input type="checkbox" data-ms-all checked> Select All</label>
                        <?php foreach($uniqueLocales as $val): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($val); ?>" checked> <?php echo htmlspecialchars($val); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button id="clearFiltersBtn" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;cursor:pointer;background:none;border:none;padding:6px 8px;transition:color 0.15s;display:none;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#9ca3af'">Clear</button>
        </div>

        <div class="overflow-visible overflow-x-auto pb-48">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr>
                        <th scope="col" class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Time</span></th>
                        <th scope="col" class="py-4 text-left pr-2"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Page</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-28"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Load</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-28"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">TTFB</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Browser</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Platform</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Screen</span></th>
                        <th scope="col" class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Locale</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="perfTableBody">
                </tbody>
            </table>
        </div>
        <div class="flex justify-between items-center mt-6 mb-24 pb-8">
            <button id="prevPageBtn" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Previous
            </button>
            <span id="pageInfo" class="text-xs text-gray-400 tracking-widest uppercase font-medium"></span>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Show</span>
                    <div class="multiselect-wrapper" data-ss-pagesize="1">
                        <button type="button" class="multiselect-btn" data-ss-default="25 entries">25 entries</button>
                        <div class="multiselect-panel">
                            <label><input type="radio" name="ss_pagesize" value="10"> 10 entries</label>
                            <label class="ss-selected"><input type="radio" name="ss_pagesize" value="25" checked> 25 entries</label>
                            <label><input type="radio" name="ss_pagesize" value="50"> 50 entries</label>
                            <label><input type="radio" name="ss_pagesize" value="100"> 100 entries</label>
                            <label><input type="radio" name="ss_pagesize" value="1000000"> All entries</label>
                        </div>
                    </div>
                </div>
                <button id="nextPageBtn" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                    Next <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Localize Table Times
        const timeFormatter = new Intl.DateTimeFormat(undefined, {
            month: '2-digit', day: '2-digit', 
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        });
        const chartTimeFormatter = new Intl.DateTimeFormat(undefined, {
            month: '2-digit', day: '2-digit', 
            hour: '2-digit', minute: '2-digit',
            hour12: false
        });

        document.querySelectorAll('.local-time').forEach(el => {
            if(el.dataset.timestamp) {
                el.textContent = timeFormatter.format(new Date(parseInt(el.dataset.timestamp)));
            }
        });

        // ── Color palette for per-page chart lines ─────────────────────────
        const PAGE_COLORS = [
            { border: 'rgb(37, 99, 235)',   bg: 'rgba(37, 99, 235, 0.10)' },   // blue
            { border: 'rgb(220, 38, 38)',   bg: 'rgba(220, 38, 38, 0.10)' },   // red
            { border: 'rgb(22, 163, 74)',   bg: 'rgba(22, 163, 74, 0.10)' },   // green
            { border: 'rgb(168, 85, 247)',  bg: 'rgba(168, 85, 247, 0.10)' },  // purple
            { border: 'rgb(234, 88, 12)',   bg: 'rgba(234, 88, 12, 0.10)' },   // orange
            { border: 'rgb(14, 165, 233)',  bg: 'rgba(14, 165, 233, 0.10)' },  // sky
            { border: 'rgb(236, 72, 153)',  bg: 'rgba(236, 72, 153, 0.10)' },  // pink
            { border: 'rgb(101, 163, 13)',  bg: 'rgba(101, 163, 13, 0.10)' },  // lime
            { border: 'rgb(245, 158, 11)',  bg: 'rgba(245, 158, 11, 0.10)' },  // amber
            { border: 'rgb(20, 184, 166)',  bg: 'rgba(20, 184, 166, 0.10)' },  // teal
        ];

        const ctx = document.getElementById('perfChart').getContext('2d');
        const perfChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Load Time Average (ms)',
                        data: [],
                        borderColor: 'rgb(37, 99, 235)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        spanGaps: true,
                        fill: true,
                        pointRadius: 0,
                        pointBackgroundColor: 'rgb(37, 99, 235)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgb(37, 99, 235)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Time to First Byte Average (ms)',
                        data: [],
                        borderColor: 'rgb(22, 163, 74)',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        spanGaps: true,
                        fill: true,
                        pointRadius: 0,
                        pointBackgroundColor: 'rgb(22, 163, 74)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgb(22, 163, 74)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                normalized: true,
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(17,17,17,0.9)',
                        titleFont: { size: 11, weight: '500' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6
                    },
                    legend: {
                        labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 11 } }
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                    x: { display: false }
                }
            }
        });

        // ── Multi-select widget logic ──────────────────────────────────────
        const allWrappers = document.querySelectorAll('.multiselect-wrapper');
        const msWrappers = document.querySelectorAll('.multiselect-wrapper[data-ms-col]');
        const ssWrappers = document.querySelectorAll('.multiselect-wrapper[data-ss-col]');
        const ssPageSizeWrapper = document.querySelector('.multiselect-wrapper[data-ss-pagesize]');
        const msState = {}; // col -> Set of selected values

        // Helper: close all dropdown panels
        function closeAllPanels(except) {
            allWrappers.forEach(w => {
                if (w !== except) {
                    w.querySelector('.multiselect-panel').classList.remove('open');
                    w.querySelector('.multiselect-btn').classList.remove('active');
                }
            });
        }

        // Helper: toggle a panel open/close
        function togglePanel(wrapper) {
            const panel = wrapper.querySelector('.multiselect-panel');
            const btn = wrapper.querySelector('.multiselect-btn');
            closeAllPanels(wrapper);
            panel.classList.toggle('open');
            btn.classList.toggle('active');
        }

        msWrappers.forEach(wrapper => {
            const col = parseInt(wrapper.dataset.msCol);
            const btn = wrapper.querySelector('.multiselect-btn');
            const panel = wrapper.querySelector('.multiselect-panel');
            const allCb = panel.querySelector('[data-ms-all]');
            const itemCbs = panel.querySelectorAll('input[type="checkbox"]:not([data-ms-all])');
            const allLabel = btn.dataset.msLabel;

            msState[col] = new Set();

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePanel(wrapper);
            });

            allCb.addEventListener('change', () => {
                const checked = allCb.checked;
                itemCbs.forEach(cb => { cb.checked = checked; });
                msState[col] = new Set();
                updateMsButton(col, btn, allLabel, itemCbs);
                triggerFilters();
            });

            itemCbs.forEach(cb => {
                cb.addEventListener('change', () => {
                    const checkedVals = [];
                    let allChecked = true;
                    itemCbs.forEach(c => {
                        if (c.checked) checkedVals.push(c.value);
                        else allChecked = false;
                    });
                    allCb.checked = allChecked;
                    msState[col] = allChecked ? new Set() : new Set(checkedVals);
                    updateMsButton(col, btn, allLabel, itemCbs);
                    triggerFilters();
                });
            });
        });

        function updateMsButton(col, btn, allLabel, itemCbs) {
            const selected = msState[col];
            if (selected.size === 0) {
                btn.innerHTML = allLabel;
            } else {
                const total = itemCbs.length;
                const count = selected.size;
                if (count === 1) {
                    const val = [...selected][0];
                    const short = val.length > 24 ? val.slice(0, 22) + '…' : val;
                    btn.innerHTML = short;
                } else {
                    btn.innerHTML = `${count} of ${total}<span class="ms-badge">${count}</span>`;
                }
            }
        }

        // ── Single-select widget logic ──────────────────────────────────────
        function initSingleSelect(wrapper, onChangeCallback) {
            const btn = wrapper.querySelector('.multiselect-btn');
            const panel = wrapper.querySelector('.multiselect-panel');
            const radios = panel.querySelectorAll('input[type="radio"]');
            const labels = panel.querySelectorAll('label');

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePanel(wrapper);
            });

            radios.forEach(radio => {
                radio.addEventListener('change', () => {
                    // Update selected highlight
                    labels.forEach(l => l.classList.remove('ss-selected'));
                    radio.closest('label').classList.add('ss-selected');
                    // Update button text
                    const labelText = radio.closest('label').textContent.trim();
                    btn.innerHTML = labelText;
                    // Close panel
                    panel.classList.remove('open');
                    btn.classList.remove('active');
                    // Callback
                    onChangeCallback(radio.value);
                });
            });
        }

        // Init filter single-selects (TIME, LOAD, TTFB)
        ssWrappers.forEach(wrapper => {
            const col = wrapper.dataset.ssCol;
            initSingleSelect(wrapper, (val) => {
                filterState[col] = val;
                updateClearBtn();
                applyFilters();
            });
        });

        // Init page-size single-select
        if (ssPageSizeWrapper) {
            initSingleSelect(ssPageSizeWrapper, (val) => {
                pageSize = parseInt(val, 10);
                currentPage = 1;
                renderPage();
            });
        }

        // Close panels on outside click
        document.addEventListener('click', () => {
            closeAllPanels();
        });
        // Prevent panel clicks from closing
        allWrappers.forEach(w => {
            w.querySelector('.multiselect-panel').addEventListener('click', e => e.stopPropagation());
        });

        // ── JSON-driven virtual table ──────────────────────────────────────
        let allData = <?php echo json_encode($dataTable); ?>;
        let fullDataLoaded = false;
        const serverTimeMs = <?php echo $serverTimeMs; ?>;
        const tbody = document.getElementById('perfTableBody');
        const clearBtn = document.getElementById('clearFiltersBtn');
        // filterState for single-select cols: 0=time, 2=load, 3=ttfb
        const filterState = { 0: "24h", 2: "", 3: "" };

        // Show clear button on load since time filter is active
        clearBtn.style.display = 'inline-block';

        function updateClearBtn() {
            const singleActive = Object.values(filterState).some(f => f !== "");
            const multiActive = Object.values(msState).some(s => s.size > 0);
            clearBtn.style.display = (singleActive || multiActive) ? 'inline-block' : 'none';
        }

        function triggerFilters() {
            updateClearBtn();
            applyFilters();
        }

        clearBtn.addEventListener('click', () => {
            // Reset single-select filter widgets
            for (const k in filterState) filterState[k] = "";
            ssWrappers.forEach(wrapper => {
                const btn = wrapper.querySelector('.multiselect-btn');
                const panel = wrapper.querySelector('.multiselect-panel');
                const radios = panel.querySelectorAll('input[type="radio"]');
                const labels = panel.querySelectorAll('label');
                labels.forEach(l => l.classList.remove('ss-selected'));
                radios.forEach(r => { r.checked = false; });
                // Select the first radio (the "All" option)
                if (radios.length > 0) {
                    radios[0].checked = true;
                    radios[0].closest('label').classList.add('ss-selected');
                }
                const allLabel = btn.dataset.ssAllLabel || btn.dataset.ssDefault;
                btn.innerHTML = allLabel;
            });
            // Reset multi-select widgets
            msWrappers.forEach(wrapper => {
                const col = parseInt(wrapper.dataset.msCol);
                msState[col] = new Set();
                const btn = wrapper.querySelector('.multiselect-btn');
                const panel = wrapper.querySelector('.multiselect-panel');
                const allCb = panel.querySelector('[data-ms-all]');
                const itemCbs = panel.querySelectorAll('input[type="checkbox"]:not([data-ms-all])');
                allCb.checked = true;
                itemCbs.forEach(cb => { cb.checked = true; });
                btn.innerHTML = btn.dataset.msLabel;
            });
            clearBtn.style.display = 'none';
            applyFilters();
        });

        // Pagination
        let currentPage = 1;
        let pageSize = 25;
        let filteredData = [];
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageInfo = document.getElementById('pageInfo');

        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderPage(); }
        });

        nextPageBtn.addEventListener('click', () => {
            const totalPages = Math.ceil(filteredData.length / pageSize) || 1;
            if (currentPage < totalPages) { currentPage++; renderPage(); }
        });

        function renderPage() {
            const totalPages = Math.ceil(filteredData.length / pageSize) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const start = (currentPage - 1) * pageSize;
            const pageRows = filteredData.slice(start, start + pageSize);

            let html = '';
            if (pageRows.length === 0) {
                html = '<tr><td colspan="8" class="py-8 text-sm italic text-gray-400 font-light">No matching entries.</td></tr>';
            } else {
                const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                pageRows.forEach(r => {
                    const timeStr = timeFormatter.format(new Date(r.raw_time));
                    html += `<tr class="data-row hover:bg-gray-50/50 group">
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light">${timeStr}</td>
                        <td class="py-4 pr-6 text-sm font-medium text-gray-900 break-words max-w-[200px]">${esc(r.url)}</td>
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-600 font-light group-hover:text-blue-600">${r.load}</td>
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-600 font-light group-hover:text-green-600">${r.ttfb}</td>
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-900 font-medium">${esc(r.browser)}</td>
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light">${esc(r.os)}</td>
                        <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light">${esc(r.screen)}</td>
                        <td class="py-4 whitespace-nowrap text-sm text-gray-400 font-light">${esc(r.language)}</td>
                    </tr>`;
                });
            }
            tbody.innerHTML = html;

            pageInfo.textContent = `Page ${currentPage} of ${totalPages} (${filteredData.length} entries)`;
            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === totalPages;
        }

        // ── Helper: bucket data for chart ──────────────────────────────────
        function bucketForChart(rows) {
            const chartRows = [...rows].sort((a, b) => a.raw_time - b.raw_time);
            const labels = [];
            const loads = [];
            const ttfbs = [];

            if (chartRows.length === 0) return { labels, loads, ttfbs };

            if (chartRows.length <= 50) {
                chartRows.forEach(r => {
                    labels.push(chartTimeFormatter.format(new Date(r.raw_time)));
                    loads.push(r.raw_load);
                    ttfbs.push(r.raw_ttfb);
                });
            } else {
                const minTime = chartRows[0].raw_time;
                const maxTime = chartRows[chartRows.length - 1].raw_time;
                const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);

                const buckets = {};
                chartRows.forEach(r => {
                    const b = Math.floor(r.raw_time / bucketMs) * bucketMs;
                    if (!buckets[b]) buckets[b] = { load: [], ttfb: [] };
                    buckets[b].load.push(r.raw_load);
                    buckets[b].ttfb.push(r.raw_ttfb);
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
            return { labels, loads, ttfbs };
        }

        // ── Helper: build a dataset object ─────────────────────────────────
        function makeDataset(label, data, borderColor, bgColor, showPoints) {
            return {
                label,
                data,
                borderColor,
                backgroundColor: bgColor,
                borderWidth: 2,
                tension: 0.4,
                spanGaps: true,
                fill: true,
                pointRadius: showPoints ? 4 : 0,
                pointBackgroundColor: borderColor,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: borderColor,
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2
            };
        }

        // ── Build a unified time axis across multiple pages ────────────────
        function buildUnifiedLabels(pageDataMap) {
            // Collect all raw times
            const allTimes = new Set();
            for (const rows of Object.values(pageDataMap)) {
                rows.forEach(r => allTimes.add(r.raw_time));
            }
            const sorted = [...allTimes].sort((a, b) => a - b);
            if (sorted.length === 0) return { labels: [], timeIndex: new Map() };

            if (sorted.length <= 100) {
                const labels = sorted.map(t => chartTimeFormatter.format(new Date(t)));
                const timeIndex = new Map(sorted.map((t, i) => [t, i]));
                return { labels, timeIndex, mode: 'raw' };
            }

            // Bucket mode
            const minTime = sorted[0];
            const maxTime = sorted[sorted.length - 1];
            const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);
            const bucketTimes = [];
            const labels = [];
            let lastBucket = null;
            for (let t = Math.floor(minTime / bucketMs) * bucketMs; t <= maxTime + bucketMs; t += bucketMs) {
                bucketTimes.push(t);
            }
            return { bucketTimes, bucketMs, minTime, maxTime, mode: 'bucket' };
        }

        function applyFilters() {
            filteredData = allData.filter(r => {
                const rawTime = r.raw_time;
                const loadVal = r.raw_load;
                const ttfbVal = r.raw_ttfb;

                // Time
                if (filterState[0]) {
                    const diffHours = (serverTimeMs - rawTime) / (1000 * 60 * 60);
                    if (filterState[0] === '1h' && diffHours > 1) return false;
                    if (filterState[0] === '3h' && diffHours > 3) return false;
                    if (filterState[0] === '12h' && diffHours > 12) return false;
                    if (filterState[0] === '24h' && diffHours > 24) return false;
                    if (filterState[0] === '7d' && diffHours > 168) return false;
                    if (filterState[0] === '30d' && diffHours > 720) return false;
                    if (filterState[0] === '12h+' && diffHours <= 12) return false;
                }
                // Path (multi-select col 1)
                if (msState[1] && msState[1].size > 0 && !msState[1].has(r.url)) return false;
                // Load
                if (filterState[2]) {
                    if (filterState[2] === '100' && loadVal >= 100) return false;
                    if (filterState[2] === '500' && loadVal >= 500) return false;
                    if (filterState[2] === '1000' && loadVal >= 1000) return false;
                    if (filterState[2] === '1000+' && loadVal < 1000) return false;
                }
                // TTFB
                if (filterState[3]) {
                    if (filterState[3] === '20' && ttfbVal >= 20) return false;
                    if (filterState[3] === '50' && ttfbVal >= 50) return false;
                    if (filterState[3] === '100' && ttfbVal >= 100) return false;
                    if (filterState[3] === '100+' && ttfbVal < 100) return false;
                }
                // Browser, Platform, Screen, Locale (multi-select)
                if (msState[4] && msState[4].size > 0 && !msState[4].has(r.browser)) return false;
                if (msState[5] && msState[5].size > 0 && !msState[5].has(r.os)) return false;
                if (msState[6] && msState[6].size > 0 && !msState[6].has(r.screen)) return false;
                if (msState[7] && msState[7].size > 0 && !msState[7].has(r.language)) return false;
                return true;
            });

            // Populate compare site pickers from filtered data
            const distinctPages = [...new Set(filteredData.map(r => r.url))].sort();
            populateComparePickers(distinctPages);

            // Update chart
            updateChart();

            currentPage = 1;
            renderPage();
        }

        // ── Chart mode & compare logic ─────────────────────────────────────
        const compareControls = document.getElementById('compareControls');
        const compareSite1Wrapper = document.getElementById('compareSite1Wrapper');
        const compareSite2Wrapper = document.getElementById('compareSite2Wrapper');
        const compareSite1Panel = document.getElementById('compareSite1Panel');
        const compareSite2Panel = document.getElementById('compareSite2Panel');
        const compareSite1Btn = compareSite1Wrapper.querySelector('.multiselect-btn');
        const compareSite2Btn = compareSite2Wrapper.querySelector('.multiselect-btn');
        const legendHint = document.getElementById('legendHint');
        let chartMode = 'average';
        let compareSite1Val = '';
        let compareSite2Val = '';

        // Helper: extract path from URL
        function urlToPath(url) {
            try { return new URL(url).pathname; } catch(e) { return url; }
        }

        // Init chart mode dropdown
        const chartModeWrapper = document.querySelector('[data-ss-chartmode]');
        initSingleSelect(chartModeWrapper, (val) => {
            chartMode = val;
            compareControls.classList.toggle('visible', chartMode === 'compare');
            updateChart();
        });

        // Init compare site pickers (toggle open/close via buttons)
        [compareSite1Wrapper, compareSite2Wrapper].forEach(wrapper => {
            const btn = wrapper.querySelector('.multiselect-btn');
            const panel = wrapper.querySelector('.multiselect-panel');
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                togglePanel(wrapper);
            });
        });

        function populateComparePickers(pages) {
            [{ panel: compareSite1Panel, btn: compareSite1Btn, currentVal: compareSite1Val, setter: v => { compareSite1Val = v; } },
             { panel: compareSite2Panel, btn: compareSite2Btn, currentVal: compareSite2Val, setter: v => { compareSite2Val = v; } }].forEach(({ panel, btn, currentVal, setter }) => {
                panel.innerHTML = '';
                let restoredSelection = false;
                pages.forEach(p => {
                    const path = urlToPath(p);
                    const label = document.createElement('label');
                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = panel.id + '_radio';
                    radio.value = p;
                    if (p === currentVal) {
                        radio.checked = true;
                        label.classList.add('ss-selected');
                        restoredSelection = true;
                    }
                    label.appendChild(radio);
                    label.appendChild(document.createTextNode(' ' + p));
                    panel.appendChild(label);

                    radio.addEventListener('change', () => {
                        panel.querySelectorAll('label').forEach(l => l.classList.remove('ss-selected'));
                        label.classList.add('ss-selected');
                        btn.textContent = p;
                        setter(p);
                        panel.classList.remove('open');
                        btn.classList.remove('active');
                        updateChart();
                    });
                });
                if (!restoredSelection) {
                    setter('');
                    btn.textContent = btn.dataset.ssDefault;
                }
            });
            // Auto-select first two if nothing selected
            if (pages.length >= 2 && !compareSite1Val && !compareSite2Val) {
                compareSite1Val = pages[0];
                compareSite2Val = pages[1];
                compareSite1Btn.textContent = pages[0];
                compareSite2Btn.textContent = pages[1];
                // Check the radios
                const r1 = compareSite1Panel.querySelector(`input[value="${CSS.escape(pages[0])}"]`);
                const r2 = compareSite2Panel.querySelector(`input[value="${CSS.escape(pages[1])}"]`);
                if (r1) { r1.checked = true; r1.closest('label').classList.add('ss-selected'); }
                if (r2) { r2.checked = true; r2.closest('label').classList.add('ss-selected'); }
            }
        }

        function updateChart() {
            if (chartMode === 'average') {
                // Average mode: aggregate all filtered data
                const bucketed = bucketForChart(filteredData);
                const showPoints = bucketed.labels.length <= 50;

                perfChart.data.labels = bucketed.labels;
                perfChart.data.datasets = [
                    makeDataset('Load Time Average (ms)', bucketed.loads, 'rgb(37, 99, 235)', 'rgba(37, 99, 235, 0.1)', showPoints),
                    makeDataset('Time to First Byte Average (ms)', bucketed.ttfbs, 'rgb(22, 163, 74)', 'rgba(22, 163, 74, 0.1)', showPoints)
                ];
                legendHint.style.display = 'none';

                // Reset compare button colors when in average mode
                [compareSite1Btn, compareSite2Btn].forEach(b => {
                    b.style.background = '';
                    b.style.borderColor = '';
                    b.style.color = '';
                });
            } else {
                // Compare mode: show two selected sites side by side
                const site1 = compareSite1Val;
                const site2 = compareSite2Val;

                if (!site1 && !site2) {
                    perfChart.data.labels = [];
                    perfChart.data.datasets = [];
                    legendHint.style.display = 'none';
                    perfChart.update();
                    return;
                }

                const sites = [site1, site2].filter(Boolean);
                const pageDataMap = {};
                sites.forEach(s => { pageDataMap[s] = []; });
                filteredData.forEach(r => {
                    if (pageDataMap[r.url]) pageDataMap[r.url].push(r);
                });

                // Combine all compare data and sort by time
                const allCompareData = sites.flatMap(s => pageDataMap[s]).sort((a, b) => a.raw_time - b.raw_time);

                if (allCompareData.length === 0) {
                    perfChart.data.labels = [];
                    perfChart.data.datasets = [];
                    legendHint.style.display = 'none';
                    perfChart.update();
                    return;
                }

                // Build a shared bucket grid from ALL compare data
                const unifiedLabels = [];
                const datasets = [];

                if (allCompareData.length <= 50) {
                    // Raw mode: use each unique timestamp as a label
                    const allTimes = [...new Set(allCompareData.map(r => r.raw_time))].sort((a, b) => a - b);
                    allTimes.forEach(t => unifiedLabels.push(chartTimeFormatter.format(new Date(t))));

                    sites.forEach((page, idx) => {
                        const color = PAGE_COLORS[idx % PAGE_COLORS.length];
                        // Build lookup from timestamp -> data
                        const loadByTime = new Map();
                        const ttfbByTime = new Map();
                        pageDataMap[page].forEach(r => {
                            const lbl = chartTimeFormatter.format(new Date(r.raw_time));
                            loadByTime.set(lbl, r.raw_load);
                            ttfbByTime.set(lbl, r.raw_ttfb);
                        });

                        const loadData = unifiedLabels.map(lbl => loadByTime.has(lbl) ? loadByTime.get(lbl) : null);
                        const ttfbData = unifiedLabels.map(lbl => ttfbByTime.has(lbl) ? ttfbByTime.get(lbl) : null);

                        const loadDs = makeDataset('Load Time (ms)', loadData, color.border, color.bg, true);
                        const ttfbDs = makeDataset('Time to First Byte (ms)', ttfbData, color.border, 'transparent', true);
                        ttfbDs.borderDash = [5, 3];
                        ttfbDs.fill = false;
                        ttfbDs.borderWidth = 1.5;
                        datasets.push(loadDs, ttfbDs);
                    });
                } else {
                    // Bucket mode: use shared bucket size from combined time range
                    const minTime = allCompareData[0].raw_time;
                    const maxTime = allCompareData[allCompareData.length - 1].raw_time;
                    const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);

                    // Pre-bucket each site's data using the SAME bucket grid
                    const siteBuckets = {};
                    sites.forEach(s => { siteBuckets[s] = {}; });
                    sites.forEach(s => {
                        pageDataMap[s].forEach(r => {
                            const b = Math.floor(r.raw_time / bucketMs) * bucketMs;
                            if (!siteBuckets[s][b]) siteBuckets[s][b] = { load: [], ttfb: [] };
                            siteBuckets[s][b].load.push(r.raw_load);
                            siteBuckets[s][b].ttfb.push(r.raw_ttfb);
                        });
                    });

                    // Walk the shared grid and build labels + per-site data
                    const siteLoadData = {};
                    const siteTtfbData = {};
                    sites.forEach(s => { siteLoadData[s] = []; siteTtfbData[s] = []; });

                    for (let t = Math.floor(minTime / bucketMs) * bucketMs; t <= maxTime; t += bucketMs) {
                        // Check if ANY site has data in this bucket
                        let anyData = false;
                        sites.forEach(s => { if (siteBuckets[s][t]) anyData = true; });
                        if (!anyData) continue;

                        unifiedLabels.push(chartTimeFormatter.format(new Date(t)));
                        sites.forEach(s => {
                            if (siteBuckets[s][t]) {
                                siteLoadData[s].push(Math.max(...siteBuckets[s][t].load));
                                siteTtfbData[s].push(Math.max(...siteBuckets[s][t].ttfb));
                            } else {
                                siteLoadData[s].push(null);
                                siteTtfbData[s].push(null);
                            }
                        });
                    }

                    const showPoints = unifiedLabels.length <= 50;
                    sites.forEach((page, idx) => {
                        const color = PAGE_COLORS[idx % PAGE_COLORS.length];
                        const loadDs = makeDataset('Load Time (ms)', siteLoadData[page], color.border, color.bg, showPoints);
                        const ttfbDs = makeDataset('Time to First Byte (ms)', siteTtfbData[page], color.border, 'transparent', showPoints);
                        ttfbDs.borderDash = [5, 3];
                        ttfbDs.fill = false;
                        ttfbDs.borderWidth = 1.5;
                        datasets.push(loadDs, ttfbDs);
                    });
                }

                perfChart.data.labels = unifiedLabels;
                perfChart.data.datasets = datasets;
                legendHint.style.display = sites.length > 1 ? 'inline-block' : 'none';

                // Color compare dropdown buttons to match chart colors
                const btns = [compareSite1Btn, compareSite2Btn];
                sites.forEach((s, idx) => {
                    const c = PAGE_COLORS[idx % PAGE_COLORS.length];
                    btns[idx].style.background = c.bg.replace('0.1', '0.15');
                    btns[idx].style.borderColor = c.border;
                    btns[idx].style.color = c.border;
                });
                // Reset any unused button
                for (let i = sites.length; i < 2; i++) {
                    btns[i].style.background = '';
                    btns[i].style.borderColor = '';
                    btns[i].style.color = '';
                }
            }

            perfChart.update();
        }

        // Show clear button on load since time filter is active
        clearBtn.style.display = 'inline-block';

        // Initialize with default filters
        applyFilters();

        // ── Lazy-load full dataset in background ───────────────────────
        (async function loadFullDataset() {
            try {
                const apiBase = <?php echo json_encode($apiBaseJs); ?>;
                const [perfRes, staticRes] = await Promise.all([
                    fetch(apiBase + '/performance'),
                    fetch(apiBase + '/static')
                ]);
                const perfAll = await perfRes.json();
                const staticAll = await staticRes.json();

                // Build session-to-client lookup
                const sessionClient = {};
                staticAll.forEach(row => {
                    const sid = row.session_id || '';
                    if (sid && !sessionClient[sid]) {
                        const d = row.data || {};
                        sessionClient[sid] = {
                            browser: d.browser || 'Unknown',
                            os: d.os || 'Unknown',
                            screen: (d.screen_width || '?') + '×' + (d.screen_height || '?'),
                            language: d.language || '—'
                        };
                    }
                });

                // Build full data table — must match server-side API filter
                const fullTable = [];
                perfAll.forEach(row => {
                    const d = row.data || {};
                    const nav = d.navigationTiming || {};
                    const ttfb = nav.timeToFirstByte;
                    
                    // Only include genuine page loads with navigationTiming (matching api.php filter)
                    if (!ttfb || parseFloat(ttfb) <= 0) return;
                    
                    const load = d.total_load_time_ms || d.loadTime || d.fcp || null;
                    if (load === null) return;
                    
                    const sid = row.session_id || '';
                    const client = sessionClient[sid] || { browser: 'Unknown', os: 'Unknown', screen: '—', language: '—' };

                    const ttfbVal = Math.round(parseFloat(ttfb) * 100) / 100;
                    fullTable.push({
                        url: row.page_url,
                        raw_time: row.created_at_ms,
                        browser: client.browser,
                        os: client.os,
                        screen: client.screen,
                        language: client.language,
                        load: (Math.round(parseFloat(load) * 100) / 100) + 'ms',
                        raw_load: Math.round(parseFloat(load) * 100) / 100,
                        ttfb: ttfbVal + 'ms',
                        raw_ttfb: ttfbVal
                    });
                });

                allData = fullTable;
                fullDataLoaded = true;

                // Re-apply current filters with the complete dataset
                applyFilters();
            } catch (e) {
                console.warn('Background data load failed:', e);
            }
        })();
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
