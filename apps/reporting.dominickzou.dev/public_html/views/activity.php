<?php
// ── Fetch data from REST API endpoints ──────────────────────────────────────
$apiBase = 'https://reporting.dominickzou.dev/api';
$ctx = stream_context_create([
    'http' => ['timeout' => 5],
    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$staticRaw   = @file_get_contents($apiBase . '/static', false, $ctx);
$activityRaw = @file_get_contents($apiBase . '/activity', false, $ctx);

$staticData   = $staticRaw   ? json_decode($staticRaw, true)   : [];
$activityData = $activityRaw ? json_decode($activityRaw, true) : [];

// ── Build session lookup for client info ────────────────────────────────────
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

// ── Compute session durations from activity timestamps ──────────────────────
$sessionTimes = [];
foreach ($activityData as $row) {
    $sid = $row['session_id'] ?? '';
    $ms  = $row['created_at_ms'] ?? 0;
    if ($sid && $ms) {
        if (!isset($sessionTimes[$sid])) {
            $sessionTimes[$sid] = ['min' => $ms, 'max' => $ms];
        } else {
            if ($ms < $sessionTimes[$sid]['min']) $sessionTimes[$sid]['min'] = $ms;
            if ($ms > $sessionTimes[$sid]['max']) $sessionTimes[$sid]['max'] = $ms;
        }
    }
}

// ── Process Activity data for click heatmap ─────────────────────────────────
$clicks = [];
foreach ($activityData as $row) {
    $d = $row['data'] ?? [];
    if (($d['action'] ?? '') === 'click' && isset($d['element'])) {
        if (preg_match('/X:\s*([\d.]+),\s*Y:\s*([\d.]+)/', $d['element'], $m)) {
            $clicks[] = [
                'x'    => ((float)$m[1] / 1920) * 100,
                'y'    => ((float)$m[2] / 1080) * 100,
                'sid'  => $row['session_id'] ?? '',
                'page' => $row['page_url'] ?? '',
            ];
        }
    }
}

// ── Build session-grouped table data (exclude heartbeats from actions) ──────
$sessionRows = [];
foreach ($activityData as $row) {
    $d = $row['data'] ?? [];
    $action = $d['action'] ?? 'unknown';
    $sid = $row['session_id'] ?? '';
    $ms  = $row['created_at_ms'] ?? (strtotime($row['created_at']) * 1000);
    $client = $sessionClient[$sid] ?? ['browser' => 'Unknown', 'os' => 'Unknown', 'screen' => '—', 'language' => '—'];

    if (!isset($sessionRows[$sid])) {
        $sessionRows[$sid] = [
            'raw_time'  => $ms,
            'page'      => $row['page_url'] ?? '',
            'actions'   => [],
            'duration'  => 0,
            'browser'   => $client['browser'],
            'os'        => $client['os'],
            'screen'    => $client['screen'],
            'language'  => $client['language'],
        ];
    }
    if ($ms < $sessionRows[$sid]['raw_time']) {
        $sessionRows[$sid]['raw_time'] = $ms;
    }
    // Skip heartbeats from the action summary
    if ($action === 'heartbeat') continue;
    $actionLabel = ucfirst(str_replace('_', ' ', $action));
    $sessionRows[$sid]['actions'][$actionLabel] = ($sessionRows[$sid]['actions'][$actionLabel] ?? 0) + 1;
}

// Compute durations and build action summaries
$dataTable = [];
foreach ($sessionRows as $sid => $sr) {
    $duration = 0;
    if (isset($sessionTimes[$sid])) {
        $duration = round(($sessionTimes[$sid]['max'] - $sessionTimes[$sid]['min']) / 1000);
    }
    $actionParts = [];
    foreach ($sr['actions'] as $act => $count) {
        $actionParts[] = $count . ' ' . $act;
    }
    $actionStr = count($actionParts) > 0 ? implode(', ', $actionParts) : 'Passive';
    $dataTable[] = [
        'raw_time'    => $sr['raw_time'],
        'page'        => $sr['page'],
        'action'      => $actionStr,
        'duration'    => $duration,
        'browser'     => $sr['browser'],
        'os'          => $sr['os'],
        'screen'      => $sr['screen'],
        'language'    => $sr['language'],
        'session_id'  => $sid,
    ];
}
usort($dataTable, fn($a, $b) => $b['raw_time'] - $a['raw_time']);

// Extract unique values for multi-select filters
$actionTypes = [];
foreach ($sessionRows as $sr) {
    foreach (array_keys($sr['actions']) as $act) {
        $actionTypes[$act] = true;
    }
}
$actionTypes = array_keys($actionTypes);
sort($actionTypes);

$uniquePages    = array_values(array_unique(array_column($dataTable, 'page')));
$uniqueBrowsers = array_values(array_unique(array_column($dataTable, 'browser')));
$uniqueOS       = array_values(array_unique(array_column($dataTable, 'os')));
$uniqueScreens  = array_values(array_unique(array_column($dataTable, 'screen')));
$uniqueLocales  = array_values(array_unique(array_column($dataTable, 'language')));
sort($uniquePages); sort($uniqueBrowsers); sort($uniqueOS); sort($uniqueScreens); sort($uniqueLocales);

$serverTimeMs = time() * 1000;
$apiBaseJs = $apiBase;
require __DIR__ . '/header.php';
?>

<style>
    /* ── Custom dropdown widget styles (same as performance page) ── */
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
    .multiselect-btn:hover { border-color: rgba(0,0,0,0.15); }
    .multiselect-btn.active { border-color: rgb(37,99,235); box-shadow: 0 0 0 2px rgba(37,99,235,0.12); }
    .multiselect-panel {
        display: none; position: absolute; top: calc(100% + 4px); left: 0;
        background: #fff; border: 1px solid rgba(0,0,0,0.08);
        border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        min-width: 180px; z-index: 50; padding: 6px 0;
        max-height: 260px; overflow-y: auto;
    }
    .multiselect-panel.open { display: block; }
    .multiselect-panel label {
        display: flex; align-items: center; gap: 8px; padding: 6px 14px;
        font-size: 12px; color: #374151; cursor: pointer; transition: background 0.1s;
        white-space: nowrap;
    }
    .multiselect-panel label:hover { background: rgba(0,0,0,0.03); }
    .multiselect-panel label.select-all-label {
        border-bottom: 1px solid rgba(0,0,0,0.06); padding-bottom: 8px; margin-bottom: 2px;
        font-weight: 600; font-size: 11px; color: #6b7280; text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .multiselect-panel input[type="checkbox"],
    .multiselect-panel input[type="radio"] {
        accent-color: rgb(37,99,235); width: 14px; height: 14px;
        border-radius: 3px; cursor: pointer;
    }
    .multiselect-panel label.ss-selected {
        background: rgba(37,99,235,0.06); color: rgb(37,99,235); font-weight: 600;
    }
    .ms-badge {
        font-size: 9px; font-weight: 700; background: rgb(37,99,235); color: #fff;
        border-radius: 10px; padding: 1px 6px; line-height: 1.3;
    }
</style>

<div class="py-12">
    <div class="mb-12">
        <h1 class="text-4xl font-medium tracking-tighter mb-4 text-gray-900">Behavioral Intelligence</h1>
        <p class="text-xl text-gray-500 font-light">Visualizing click-distribution heatmaps, session activity, and client environments.</p>
    </div>

    <!-- Heatmap — full width -->
    <div class="mb-12">
        <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Aggregate Click Distribution</h3>
        <div class="relative w-full aspect-video bg-white rounded-lg overflow-hidden border border-gray-100" id="heatmapContainer">
            <iframe id="heatmapIframe" src="https://test.dominickzou.dev" class="absolute pointer-events-none border-0 opacity-60" style="width:1920px;height:1080px;transform-origin:0 0;" sandbox="allow-same-origin allow-scripts" loading="lazy"></iframe>
            <div id="heatmapOverlay" class="absolute inset-0 z-10 pointer-events-none"></div>
            <div id="heatmapNoData" class="absolute inset-0 z-20 flex items-center justify-center pointer-events-none" style="display:none;">
                <div class="text-center">
                    <svg class="mx-auto mb-3" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                    <p class="text-xs font-semibold text-gray-300 tracking-widest uppercase">No Click Data</p>
                    <p class="text-[10px] text-gray-300 mt-1">Adjust filters to see activity</p>
                </div>
            </div>
        </div>
        <p id="heatmapCaption" class="text-[10px] text-gray-400 mt-4 tracking-widest uppercase text-center">Showing highest activity page: loading...</p>
    </div>

    <!-- Charts Row 1: Idle Time + OS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
        <div>
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Session Idle Time</h3>
            <div class="relative h-64 w-full">
                <canvas id="idleTimeChart"></canvas>
            </div>
        </div>
        <div>
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Client Architecture (OS)</h3>
            <div class="relative h-64 w-full flex items-center justify-center">
                <canvas id="osChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Actions + Browser -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
        <div>
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Most Common Actions</h3>
            <div class="relative h-64 w-full flex items-center justify-center">
                <canvas id="actionChart"></canvas>
            </div>
        </div>
        <div>
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Browser Distribution</h3>
            <div class="relative h-64 w-full flex items-center justify-center">
                <canvas id="browserChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Table with Inline Filters -->
    <div class="mt-12">
        <div class="data-table-wrapper">
            <!-- Filter Bar -->
            <div class="filter-bar border-b border-gray-100 pb-3 mb-8" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <span class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="margin-right:0.25rem;">Filters</span>

                <!-- Single-select: Time (col 0) -->
                <div class="multiselect-wrapper" data-ss-col="0">
                    <button type="button" class="multiselect-btn" data-ss-default="&lt; 24 Hours" data-ss-all-label="All Time">&lt; 24 Hours</button>
                    <div class="multiselect-panel">
                        <label><input type="radio" name="ss_act_0" value=""> All Time</label>
                        <label><input type="radio" name="ss_act_0" value="1h"> &lt; 1 Hour</label>
                        <label><input type="radio" name="ss_act_0" value="3h"> &lt; 3 Hours</label>
                        <label><input type="radio" name="ss_act_0" value="12h"> &lt; 12 Hours</label>
                        <label class="ss-selected"><input type="radio" name="ss_act_0" value="24h" checked> &lt; 24 Hours</label>
                        <label><input type="radio" name="ss_act_0" value="7d"> &lt; 7 Days</label>
                        <label><input type="radio" name="ss_act_0" value="30d"> &lt; 30 Days</label>
                        <label><input type="radio" name="ss_act_0" value="12h+"> &gt; 12 Hours</label>
                    </div>
                </div>

                <!-- Single-select: Page (col 1) -->
                <div class="multiselect-wrapper" data-ss-col="1">
                    <button type="button" class="multiselect-btn" data-ss-default="All Pages" data-ss-all-label="All Pages">All Pages</button>
                    <div class="multiselect-panel">
                        <label class="ss-selected"><input type="radio" name="ss_act_1" value="" checked> All Pages</label>
                        <?php foreach ($uniquePages as $p): ?>
                        <label><input type="radio" name="ss_act_1" value="<?php echo htmlspecialchars($p); ?>"> <?php echo htmlspecialchars($p); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Action (col 2) -->
                <div class="multiselect-wrapper" data-ms-col="2">
                    <button type="button" class="multiselect-btn" data-ms-all-label="All Actions">All Actions</button>
                    <div class="multiselect-panel">
                        <label class="select-all-label"><input type="checkbox" data-select-all checked> Select All</label>
                        <?php foreach ($actionTypes as $a): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($a); ?>" checked> <?php echo htmlspecialchars(ucfirst($a)); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Single-select: Duration (col 3) -->
                <div class="multiselect-wrapper" data-ss-col="3">
                    <button type="button" class="multiselect-btn" data-ss-default="All Durations" data-ss-all-label="All Durations">All Durations</button>
                    <div class="multiselect-panel">
                        <label class="ss-selected"><input type="radio" name="ss_act_3" value="" checked> All Durations</label>
                        <label><input type="radio" name="ss_act_3" value="30"> &lt; 30 Sec</label>
                        <label><input type="radio" name="ss_act_3" value="60"> &lt; 1 Min</label>
                        <label><input type="radio" name="ss_act_3" value="300"> &lt; 5 Min</label>
                        <label><input type="radio" name="ss_act_3" value="300+"> &gt; 5 Min</label>
                    </div>
                </div>

                <!-- Multi-select: Browser (col 4) -->
                <div class="multiselect-wrapper" data-ms-col="4">
                    <button type="button" class="multiselect-btn" data-ms-all-label="All Browsers">All Browsers</button>
                    <div class="multiselect-panel">
                        <label class="select-all-label"><input type="checkbox" data-select-all checked> Select All</label>
                        <?php foreach ($uniqueBrowsers as $b): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($b); ?>" checked> <?php echo htmlspecialchars($b); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Platform (col 5) -->
                <div class="multiselect-wrapper" data-ms-col="5">
                    <button type="button" class="multiselect-btn" data-ms-all-label="All Platforms">All Platforms</button>
                    <div class="multiselect-panel">
                        <label class="select-all-label"><input type="checkbox" data-select-all checked> Select All</label>
                        <?php foreach ($uniqueOS as $o): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($o); ?>" checked> <?php echo htmlspecialchars($o); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Screen (col 6) -->
                <div class="multiselect-wrapper" data-ms-col="6">
                    <button type="button" class="multiselect-btn" data-ms-all-label="All Screens">All Screens</button>
                    <div class="multiselect-panel">
                        <label class="select-all-label"><input type="checkbox" data-select-all checked> Select All</label>
                        <?php foreach ($uniqueScreens as $s): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($s); ?>" checked> <?php echo htmlspecialchars($s); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-select: Locale (col 7) -->
                <div class="multiselect-wrapper" data-ms-col="7">
                    <button type="button" class="multiselect-btn" data-ms-all-label="All Locales">All Locales</button>
                    <div class="multiselect-panel">
                        <label class="select-all-label"><input type="checkbox" data-select-all checked> Select All</label>
                        <?php foreach ($uniqueLocales as $l): ?>
                        <label><input type="checkbox" value="<?php echo htmlspecialchars($l); ?>" checked> <?php echo htmlspecialchars($l); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button id="clearFiltersBtn" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;cursor:pointer;background:none;border:none;padding:6px 8px;transition:color 0.15s;display:none;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#9ca3af'">Clear</button>
            </div>

            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-4 text-left pr-2 w-36"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Time</span></th>
                            <th class="py-4 text-left pr-2 max-w-[200px]"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Page</span></th>
                            <th class="py-4 text-left pr-2"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Actions</span></th>
                            <th class="py-4 text-left pr-2 w-32"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Session Time</span></th>
                            <th class="py-4 text-left pr-2 w-24"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Browser</span></th>
                            <th class="py-4 text-left pr-2 w-24"><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Platform</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="activityTableBody">
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-between items-center mt-6 mb-24 pb-8">
                <button id="prevPageBtn" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Previous
                </button>
                <span id="pageInfo" class="text-xs text-gray-400 tracking-widest uppercase font-medium"></span>
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Show</span>
                        <div class="multiselect-wrapper" data-ss-col="show">
                            <button type="button" class="multiselect-btn" data-ss-default="25 entries">25 entries</button>
                            <div class="multiselect-panel">
                                <label><input type="radio" name="ss_act_show" value="10"> 10 entries</label>
                                <label class="ss-selected"><input type="radio" name="ss_act_show" value="25" checked> 25 entries</label>
                                <label><input type="radio" name="ss_act_show" value="50"> 50 entries</label>
                                <label><input type="radio" name="ss_act_show" value="100"> 100 entries</label>
                                <label><input type="radio" name="ss_act_show" value="250"> 250 entries</label>
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
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ── Chart color palette ─────────────────────────────────────────────
        const chartColors = ['#2563eb', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#f97316', '#06b6d4', '#dc2626'];

        // ── Data & State ────────────────────────────────────────────────────
        let allData = <?php echo json_encode($dataTable); ?>;
        let allClicks = <?php echo json_encode($clicks); ?>;
        let filteredData = [];
        const serverTimeMs = <?php echo $serverTimeMs; ?>;
        const tbody = document.getElementById('activityTableBody');
        const clearBtn = document.getElementById('clearFiltersBtn');
        const pageInfoEl = document.getElementById('pageInfo');
        const prevBtn = document.getElementById('prevPageBtn');
        const nextBtn = document.getElementById('nextPageBtn');

        // filterState for single-select cols: 0=time, 3=duration
        const filterState = { 0: "24h", 1: "", 3: "" };
        // msState for multi-select cols: 1=page, 2=action, 4=browser, 5=platform, 6=screen, 7=locale
        const msState = {};
        let currentPage = 1;
        let pageSize = 25;

        const timeFormatter = new Intl.DateTimeFormat(undefined, {
            month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        });

        function fmtDuration(sec) {
            if (sec < 60) return sec + 's';
            const m = Math.floor(sec / 60);
            const s = sec % 60;
            return m + 'm ' + s + 's';
        }

        function esc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Dropdown widget helpers ─────────────────────────────────────────
        const allWrappers = document.querySelectorAll('.multiselect-wrapper');

        function closeAllPanels() {
            allWrappers.forEach(w => {
                const panel = w.querySelector('.multiselect-panel');
                const btn = w.querySelector('.multiselect-btn');
                if (panel) panel.classList.remove('open');
                if (btn) btn.classList.remove('active');
            });
        }

        function togglePanel(wrapper) {
            const panel = wrapper.querySelector('.multiselect-panel');
            const btn = wrapper.querySelector('.multiselect-btn');
            const isOpen = panel.classList.contains('open');
            closeAllPanels();
            if (!isOpen) {
                panel.classList.add('open');
                btn.classList.add('active');
            }
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.multiselect-wrapper')) closeAllPanels();
        });

        // ── Multi-select widget init ────────────────────────────────────────
        const msWrappers = document.querySelectorAll('[data-ms-col]');
        msWrappers.forEach(wrapper => {
            const colIdx = parseInt(wrapper.dataset.msCol, 10);
            const btn = wrapper.querySelector('.multiselect-btn');
            const panel = wrapper.querySelector('.multiselect-panel');
            const allLabel = btn.dataset.msAllLabel || 'All';
            const selectAllCb = panel.querySelector('[data-select-all]');
            const itemCbs = panel.querySelectorAll('input[type="checkbox"]:not([data-select-all])');
            const total = itemCbs.length;

            btn.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(wrapper); });

            function syncState() {
                const checked = panel.querySelectorAll('input[type="checkbox"]:not([data-select-all]):checked');
                const count = checked.length;
                if (count === 0 || count === total) {
                    msState[colIdx] = null;
                    btn.innerHTML = allLabel;
                } else {
                    const selected = new Set();
                    checked.forEach(cb => selected.add(cb.value));
                    msState[colIdx] = selected;
                    btn.innerHTML = `${count} of ${total} <span class="ms-badge">${count}</span>`;
                }
                if (selectAllCb) selectAllCb.checked = (count === total);
                updateClearBtn();
                applyFilters();
            }

            if (selectAllCb) {
                selectAllCb.addEventListener('change', () => {
                    itemCbs.forEach(cb => { cb.checked = selectAllCb.checked; });
                    syncState();
                });
            }
            itemCbs.forEach(cb => cb.addEventListener('change', syncState));
        });

        // ── Single-select widget init ───────────────────────────────────────
        function initSingleSelect(wrapper, onChange) {
            const btn = wrapper.querySelector('.multiselect-btn');
            const panel = wrapper.querySelector('.multiselect-panel');
            const radios = panel.querySelectorAll('input[type="radio"]');

            btn.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(wrapper); });

            radios.forEach(radio => {
                radio.addEventListener('change', () => {
                    panel.querySelectorAll('label').forEach(l => l.classList.remove('ss-selected'));
                    radio.closest('label').classList.add('ss-selected');
                    btn.textContent = radio.closest('label').textContent.trim();
                    panel.classList.remove('open');
                    btn.classList.remove('active');
                    if (onChange) onChange(radio.value);
                });
            });
        }

        // Init time filter (col 0)
        document.querySelectorAll('[data-ss-col="0"]').forEach(w => {
            initSingleSelect(w, (val) => {
                filterState[0] = val;
                updateClearBtn();
                applyFilters();
            });
        });

        // Init page filter (col 1)
        document.querySelectorAll('[data-ss-col="1"]').forEach(w => {
            initSingleSelect(w, (val) => {
                filterState[1] = val;
                updateClearBtn();
                applyFilters();
            });
        });

        // Init duration filter (col 3)
        document.querySelectorAll('[data-ss-col="3"]').forEach(w => {
            initSingleSelect(w, (val) => {
                filterState[3] = val;
                updateClearBtn();
                applyFilters();
            });
        });

        // Init Show entries
        document.querySelectorAll('[data-ss-col="show"]').forEach(w => {
            initSingleSelect(w, (val) => {
                pageSize = parseInt(val, 10);
                currentPage = 1;
                renderPage();
            });
        });

        // ── Clear button ────────────────────────────────────────────────────
        function updateClearBtn() {
            const hasFilter = Object.values(filterState).some(v => v !== '') ||
                Object.values(msState).some(v => v && v.size > 0);
            clearBtn.style.display = hasFilter ? 'inline-block' : 'none';
        }
        updateClearBtn();

        clearBtn.addEventListener('click', () => {
            // Reset single-selects
            for (const key in filterState) filterState[key] = '';
            document.querySelectorAll('[data-ss-col]').forEach(w => {
                const radios = w.querySelectorAll('input[type="radio"]');
                radios.forEach(r => {
                    const lbl = r.closest('label');
                    if (r.value === '') { r.checked = true; lbl.classList.add('ss-selected'); }
                    else { r.checked = false; lbl.classList.remove('ss-selected'); }
                });
                const btn = w.querySelector('.multiselect-btn');
                btn.textContent = btn.dataset.ssAllLabel || btn.dataset.ssDefault || 'All';
            });
            // Reset multi-selects
            for (const key in msState) msState[key] = null;
            msWrappers.forEach(w => {
                const cbs = w.querySelectorAll('input[type="checkbox"]');
                cbs.forEach(cb => { cb.checked = true; });
                const btn = w.querySelector('.multiselect-btn');
                btn.innerHTML = btn.dataset.msAllLabel || 'All';
            });
            clearBtn.style.display = 'none';
            applyFilters();
        });

        // ── Pagination ──────────────────────────────────────────────────────
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderPage(); }
        });
        nextBtn.addEventListener('click', () => {
            const tp = Math.ceil(filteredData.length / pageSize) || 1;
            if (currentPage < tp) { currentPage++; renderPage(); }
        });

        function renderPage() {
            const tp = Math.ceil(filteredData.length / pageSize) || 1;
            if (currentPage > tp) currentPage = tp;
            if (currentPage < 1) currentPage = 1;
            const start = (currentPage - 1) * pageSize;
            const pageRows = filteredData.slice(start, start + pageSize);

            let html = '';
            if (pageRows.length === 0) {
                html = '<tr><td colspan="6" class="py-8 text-sm italic text-gray-400 font-light">No matching entries.</td></tr>';
            } else {
                pageRows.forEach(r => {
                    html += '<tr class="data-row hover:bg-gray-50/50 group">';
                    html += `<td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light">${timeFormatter.format(new Date(r.raw_time))}</td>`;
                    html += `<td class="py-4 pr-6 text-sm font-medium text-gray-900 break-words max-w-[200px]">${esc(r.page)}</td>`;
                    html += `<td class="py-4 pr-4 text-sm text-gray-600 font-light">${esc(r.action)}</td>`;
                    html += `<td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-600 font-light">${fmtDuration(r.duration)}</td>`;
                    html += `<td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-900 font-medium">${esc(r.browser)}</td>`;
                    html += `<td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light">${esc(r.os)}</td>`;
                    html += '</tr>';
                });
            }
            tbody.innerHTML = html;
            pageInfoEl.textContent = `Page ${currentPage} of ${tp} (${filteredData.length} entries)`;
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === tp;
        }

        // ── Filter logic ────────────────────────────────────────────────────
        function applyFilters() {
            filteredData = allData.filter(r => {
                // Time
                if (filterState[0]) {
                    const diffHours = (serverTimeMs - r.raw_time) / (1000 * 60 * 60);
                    if (filterState[0] === '1h' && diffHours > 1) return false;
                    if (filterState[0] === '3h' && diffHours > 3) return false;
                    if (filterState[0] === '12h' && diffHours > 12) return false;
                    if (filterState[0] === '24h' && diffHours > 24) return false;
                    if (filterState[0] === '7d' && diffHours > 168) return false;
                    if (filterState[0] === '30d' && diffHours > 720) return false;
                    if (filterState[0] === '12h+' && diffHours <= 12) return false;
                }
                // Page (multi-select col 1)
                // Page (single-select col 1)
                if (filterState[1] && r.page !== filterState[1]) return false;
                // Action (multi-select col 2) — check if any selected action is in the action string
                if (msState[2] && msState[2].size > 0) {
                    let hasMatch = false;
                    for (const a of msState[2]) {
                        if (r.action.includes(a)) { hasMatch = true; break; }
                    }
                    if (!hasMatch) return false;
                }
                // Duration (single-select col 3)
                if (filterState[3]) {
                    if (filterState[3].endsWith('+')) {
                        if (r.duration < parseFloat(filterState[3])) return false;
                    } else {
                        if (r.duration >= parseFloat(filterState[3])) return false;
                    }
                }
                // Browser (multi-select col 4)
                if (msState[4] && msState[4].size > 0 && !msState[4].has(r.browser)) return false;
                // Platform (multi-select col 5)
                if (msState[5] && msState[5].size > 0 && !msState[5].has(r.os)) return false;
                // Screen (multi-select col 6)
                if (msState[6] && msState[6].size > 0 && !msState[6].has(r.screen)) return false;
                // Locale (multi-select col 7)
                if (msState[7] && msState[7].size > 0 && !msState[7].has(r.language)) return false;
                return true;
            });

            currentPage = 1;
            renderPage();
            updateChartsFromData(filteredData);
            updateHeatmapFromData(filteredData);
        }

        // ── Heatmap ─────────────────────────────────────────────────────────
        const overlay = document.getElementById('heatmapOverlay');
        const container = document.getElementById('heatmapContainer');
        const heatmapIframeEl = document.getElementById('heatmapIframe');

        function scaleIframe() {
            const containerW = container.offsetWidth;
            const scale = containerW / 1920;
            heatmapIframeEl.style.transform = `scale(${scale})`;
        }
        scaleIframe();
        window.addEventListener('resize', scaleIframe);

        const heatmapTooltip = document.createElement('div');
        heatmapTooltip.style.cssText = 'position:absolute;z-index:50;background:rgba(17,17,17,0.9);color:#fff;font-size:11px;padding:6px 10px;border-radius:6px;pointer-events:none;opacity:0;transition:opacity 0.15s;white-space:nowrap;font-family:Inter,sans-serif;letter-spacing:0.02em;';
        container.appendChild(heatmapTooltip);

        function renderHeatmap(clicks) {
            overlay.querySelectorAll('.heatmap-dot').forEach(el => el.remove());
            const clusters = [];
            const threshold = 3;
            clicks.forEach(c => {
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
                let dot = document.createElement('div');
                dot.className = 'heatmap-dot';
                dot.style.cssText = `position:absolute;width:${size}px;height:${size}px;border-radius:50%;background:radial-gradient(circle, rgba(239,68,68,${opacity}) 0%, rgba(239,68,68,${opacity*0.4}) 40%, transparent 70%);left:calc(${c.x}% - ${size/2}px);top:calc(${c.y}% - ${size/2}px);cursor:default;pointer-events:auto;`;
                dot.addEventListener('mouseenter', () => {
                    const pxX = Math.round(c.x / 100 * 1920);
                    const pxY = Math.round(c.y / 100 * 1080);
                    heatmapTooltip.textContent = `${c.count} click${c.count > 1 ? 's' : ''} · (${pxX}, ${pxY})px`;
                    heatmapTooltip.style.opacity = '1';
                    heatmapTooltip.style.left = `calc(${c.x}% + ${size/2 + 8}px)`;
                    heatmapTooltip.style.top = `calc(${c.y}% - 14px)`;
                });
                dot.addEventListener('mouseleave', () => { heatmapTooltip.style.opacity = '0'; });
                overlay.appendChild(dot);
            });
        }

        let currentIframeSrc = heatmapIframeEl.src;

        function getTopClickPage(clicks) {
            const pageCounts = {};
            clicks.forEach(c => { if (c.page) pageCounts[c.page] = (pageCounts[c.page] || 0) + 1; });
            let topPage = '', topCount = 0;
            for (const [page, count] of Object.entries(pageCounts)) {
                if (count > topCount) { topCount = count; topPage = page; }
            }
            return topPage;
        }

        function updateIframePage(clicks) {
            const topPage = getTopClickPage(clicks);
            const noDataEl = document.getElementById('heatmapNoData');
            if (topPage && topPage !== currentIframeSrc) {
                currentIframeSrc = topPage;
                heatmapIframeEl.src = topPage;
            }
            const caption = document.getElementById('heatmapCaption');
            if (clicks.length > 0 && topPage) {
                caption.textContent = 'Showing highest activity page: ' + topPage;
                noDataEl.style.display = 'none';
                heatmapIframeEl.style.opacity = '0.6';
            } else {
                caption.textContent = 'No click data for current filters.';
                noDataEl.style.display = 'flex';
                heatmapIframeEl.style.opacity = '0.15';
            }
        }

        renderHeatmap(allClicks);
        updateIframePage(allClicks);

        function updateHeatmapFromData(filteredRows) {
            const visibleSids = new Set(filteredRows.map(r => r.session_id));
            const visiblePages = [...new Set(filteredRows.map(r => r.page))];
            let filteredClicks;
            if (visiblePages.length === 1) {
                filteredClicks = allClicks.filter(c => c.page === visiblePages[0]);
            } else {
                const sessionClicks = allClicks.filter(c => visibleSids.has(c.sid));
                const topPage = getTopClickPage(sessionClicks);
                filteredClicks = topPage ? sessionClicks.filter(c => c.page === topPage) : sessionClicks;
            }
            renderHeatmap(filteredClicks);
            updateIframePage(filteredClicks);
        }

        // ── Chart Instances ─────────────────────────────────────────────────
        const doughnutOpts = {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: { position: 'right' } }
        };

        const osChart = new Chart(document.getElementById('osChart').getContext('2d'), {
            type: 'doughnut',
            data: { labels: [], datasets: [{ data: [], backgroundColor: chartColors, borderWidth: 0 }] },
            options: doughnutOpts
        });

        const browserChart = new Chart(document.getElementById('browserChart').getContext('2d'), {
            type: 'doughnut',
            data: { labels: [], datasets: [{ data: [], backgroundColor: chartColors, borderWidth: 0 }] },
            options: doughnutOpts
        });

        const actionChart = new Chart(document.getElementById('actionChart').getContext('2d'), {
            type: 'bar',
            data: { labels: [], datasets: [{ label: 'Count', data: [], backgroundColor: chartColors, borderWidth: 0 }] },
            options: {
                responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { grid: { display: false }, ticks: { precision: 0 } }, y: { grid: { display: false } } }
            }
        });

        const idleTimeChart = new Chart(document.getElementById('idleTimeChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['0-10s', '10-30s', '30s-1m', '1-5m', '5-15m', '15m+'],
                datasets: [{
                    label: 'Sessions',
                    data: [0, 0, 0, 0, 0, 0],
                    backgroundColor: [
                        'rgba(37,99,235,0.7)', 'rgba(139,92,246,0.7)', 'rgba(236,72,153,0.7)',
                        'rgba(245,158,11,0.7)', 'rgba(16,185,129,0.7)', 'rgba(249,115,22,0.7)'
                    ],
                    borderWidth: 0, borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, title: { display: true, text: 'Duration', font: { size: 10 }, color: '#9ca3af' } },
                    y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { precision: 0 }, title: { display: true, text: 'Sessions', font: { size: 10 }, color: '#9ca3af' } }
                }
            }
        });

        // ── Update charts from filtered data ────────────────────────────────
        function updateChartsFromData(filteredRows) {
            // OS chart
            const osCounts = {};
            filteredRows.forEach(r => { osCounts[r.os] = (osCounts[r.os] || 0) + 1; });
            osChart.data.labels = Object.keys(osCounts);
            osChart.data.datasets[0].data = Object.values(osCounts);
            osChart.update();

            // Browser chart
            const browserCounts = {};
            filteredRows.forEach(r => { browserCounts[r.browser] = (browserCounts[r.browser] || 0) + 1; });
            browserChart.data.labels = Object.keys(browserCounts);
            browserChart.data.datasets[0].data = Object.values(browserCounts);
            browserChart.update();

            // Action chart
            const actionCounts = {};
            filteredRows.forEach(r => {
                if (!r.action || r.action === 'Passive') return;
                r.action.split(', ').forEach(part => {
                    const spaceIdx = part.indexOf(' ');
                    if (spaceIdx > 0) {
                        const count = parseInt(part.substring(0, spaceIdx), 10);
                        const name = part.substring(spaceIdx + 1);
                        if (!isNaN(count)) actionCounts[name] = (actionCounts[name] || 0) + count;
                    }
                });
            });
            const sorted = Object.entries(actionCounts).sort((a, b) => b[1] - a[1]).slice(0, 8);
            actionChart.data.labels = sorted.map(e => e[0]);
            actionChart.data.datasets[0].data = sorted.map(e => e[1]);
            actionChart.update();

            // Idle time chart — bucket sessions by duration
            const buckets = [0, 0, 0, 0, 0, 0]; // 0-10s, 10-30s, 30s-1m, 1-5m, 5-15m, 15m+
            filteredRows.forEach(r => {
                const d = r.duration;
                if (d <= 10) buckets[0]++;
                else if (d <= 30) buckets[1]++;
                else if (d <= 60) buckets[2]++;
                else if (d <= 300) buckets[3]++;
                else if (d <= 900) buckets[4]++;
                else buckets[5]++;
            });
            idleTimeChart.data.datasets[0].data = buckets;
            idleTimeChart.update();
        }

        // Initial render
        applyFilters();

        // ── Lazy-load full dataset in background ────────────────────────────
        (async function loadFullDataset() {
            try {
                const apiBase = <?php echo json_encode($apiBaseJs); ?>;
                const [actRes, staticRes] = await Promise.all([
                    fetch(apiBase + '/activity'),
                    fetch(apiBase + '/static')
                ]);
                const actAll = await actRes.json();
                const staticAll = await staticRes.json();

                const sessionClient = {};
                staticAll.forEach(row => {
                    const sid = row.session_id || '';
                    if (sid && !sessionClient[sid]) {
                        const d = row.data || {};
                        sessionClient[sid] = {
                            browser: d.browser || 'Unknown',
                            os: d.os || 'Unknown',
                            screen: (d.screen_width || '?') + '×' + (d.screen_height || '?'),
                            language: d.language || '—',
                        };
                    }
                });

                const sessionTimes = {};
                actAll.forEach(row => {
                    const sid = row.session_id || '';
                    const ms = row.created_at_ms || 0;
                    if (sid && ms) {
                        if (!sessionTimes[sid]) sessionTimes[sid] = { min: ms, max: ms };
                        if (ms < sessionTimes[sid].min) sessionTimes[sid].min = ms;
                        if (ms > sessionTimes[sid].max) sessionTimes[sid].max = ms;
                    }
                });

                const sessions = {};
                const fullClicks = [];
                actAll.forEach(row => {
                    const d = row.data || {};
                    const action = d.action || 'unknown';
                    const sid = row.session_id || '';
                    const ms = row.created_at_ms || 0;
                    const client = sessionClient[sid] || { browser: 'Unknown', os: 'Unknown', screen: '—', language: '—' };

                    if (!sessions[sid]) {
                        sessions[sid] = {
                            raw_time: ms,
                            page: row.page_url || '',
                            actions: {},
                            browser: client.browser,
                            os: client.os,
                            screen: client.screen,
                            language: client.language,
                        };
                    }
                    if (ms < sessions[sid].raw_time) sessions[sid].raw_time = ms;

                    if (action === 'click' && d.element) {
                        const match = d.element.match(/X:\s*([\d.]+),\s*Y:\s*([\d.]+)/);
                        if (match) {
                            fullClicks.push({
                                x: (parseFloat(match[1]) / 1920) * 100,
                                y: (parseFloat(match[2]) / 1080) * 100,
                                sid: sid,
                                page: row.page_url || ''
                            });
                        }
                    }

                    if (action === 'heartbeat') return;
                    const label = action.charAt(0).toUpperCase() + action.slice(1).replace(/_/g, ' ');
                    sessions[sid].actions[label] = (sessions[sid].actions[label] || 0) + 1;
                });

                const fullTable = [];
                for (const sid in sessions) {
                    const s = sessions[sid];
                    const st = sessionTimes[sid];
                    const duration = st ? Math.round((st.max - st.min) / 1000) : 0;
                    const actionParts = Object.entries(s.actions).map(([k, v]) => v + ' ' + k);
                    fullTable.push({
                        raw_time: s.raw_time,
                        page: s.page,
                        action: actionParts.length > 0 ? actionParts.join(', ') : 'Passive',
                        duration: duration,
                        browser: s.browser,
                        os: s.os,
                        screen: s.screen,
                        language: s.language,
                        session_id: sid,
                    });
                }
                fullTable.sort((a, b) => b.raw_time - a.raw_time);

                allClicks = fullClicks;
                allData = fullTable;

                // Rebuild multi-select options from new data
                rebuildMultiSelectOptions();
                // Rebuild page single-select options
                rebuildPageOptions();
                applyFilters();
            } catch (e) {
                console.warn('Background activity data load failed:', e);
            }
        })();

        function rebuildPageOptions() {
            const wrapper = document.querySelector('[data-ss-col="1"]');
            if (!wrapper) return;
            const panel = wrapper.querySelector('.multiselect-panel');
            const btn = wrapper.querySelector('.multiselect-btn');
            const uniquePages = [...new Set(allData.map(r => r.page))].sort();
            const currentVal = filterState[1] || '';
            panel.innerHTML = '';
            // "All Pages" option
            const allLabel = document.createElement('label');
            if (!currentVal) allLabel.classList.add('ss-selected');
            const allRadio = document.createElement('input');
            allRadio.type = 'radio'; allRadio.name = 'ss_act_1'; allRadio.value = '';
            if (!currentVal) allRadio.checked = true;
            allLabel.appendChild(allRadio);
            allLabel.appendChild(document.createTextNode(' All Pages'));
            panel.appendChild(allLabel);

            uniquePages.forEach(p => {
                const label = document.createElement('label');
                const radio = document.createElement('input');
                radio.type = 'radio'; radio.name = 'ss_act_1'; radio.value = p;
                if (p === currentVal) { radio.checked = true; label.classList.add('ss-selected'); }
                label.appendChild(radio);
                label.appendChild(document.createTextNode(' ' + p));
                panel.appendChild(label);
            });

            // Re-bind change events
            panel.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    panel.querySelectorAll('label').forEach(l => l.classList.remove('ss-selected'));
                    radio.closest('label').classList.add('ss-selected');
                    btn.textContent = radio.value ? radio.value : 'All Pages';
                    panel.classList.remove('open');
                    btn.classList.remove('active');
                    filterState[1] = radio.value;
                    updateClearBtn();
                    applyFilters();
                });
            });
        }

        function rebuildMultiSelectOptions() {
            const colKeyMap = {
                2: 'action', 4: 'browser', 5: 'os', 6: 'screen', 7: 'language'
            };
            msWrappers.forEach(wrapper => {
                const colIdx = parseInt(wrapper.dataset.msCol, 10);
                if (!colKeyMap[colIdx]) return;
                const key = colKeyMap[colIdx];
                const panel = wrapper.querySelector('.multiselect-panel');
                const btn = wrapper.querySelector('.multiselect-btn');
                const allLabel = btn.dataset.msAllLabel || 'All';

                // Collect unique values
                const uniqueVals = new Set();
                allData.forEach(r => {
                    const val = r[key];
                    if (!val) return;
                    if (colIdx === 2) {
                        // Action: parse "3 Click, 2 Keyboard up"
                        val.split(', ').forEach(part => {
                            const spaceIdx = part.indexOf(' ');
                            if (spaceIdx > 0) uniqueVals.add(part.substring(spaceIdx + 1));
                            else uniqueVals.add(part);
                        });
                    } else {
                        uniqueVals.add(val);
                    }
                });

                const sorted = [...uniqueVals].sort();
                const currentSelection = msState[colIdx] || null;

                // Rebuild panel
                panel.innerHTML = '';
                const selectAllLabel = document.createElement('label');
                selectAllLabel.className = 'select-all-label';
                const selectAllCb = document.createElement('input');
                selectAllCb.type = 'checkbox';
                selectAllCb.dataset.selectAll = '';
                selectAllCb.checked = true;
                selectAllLabel.appendChild(selectAllCb);
                selectAllLabel.appendChild(document.createTextNode(' Select All'));
                panel.appendChild(selectAllLabel);

                const itemCbs = [];
                sorted.forEach(v => {
                    const label = document.createElement('label');
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.value = v;
                    cb.checked = !currentSelection || currentSelection.has(v);
                    label.appendChild(cb);
                    label.appendChild(document.createTextNode(' ' + (colIdx === 2 ? v.charAt(0).toUpperCase() + v.slice(1) : v)));
                    panel.appendChild(label);
                    itemCbs.push(cb);
                });

                const total = itemCbs.length;
                function syncState() {
                    const checked = itemCbs.filter(cb => cb.checked);
                    const count = checked.length;
                    if (count === 0 || count === total) {
                        msState[colIdx] = null;
                        btn.innerHTML = allLabel;
                    } else {
                        const selected = new Set();
                        checked.forEach(cb => selected.add(cb.value));
                        msState[colIdx] = selected;
                        btn.innerHTML = `${count} of ${total} <span class="ms-badge">${count}</span>`;
                    }
                    selectAllCb.checked = (count === total);
                    updateClearBtn();
                    applyFilters();
                }

                selectAllCb.addEventListener('change', () => {
                    itemCbs.forEach(cb => { cb.checked = selectAllCb.checked; });
                    syncState();
                });
                itemCbs.forEach(cb => cb.addEventListener('change', syncState));
            });
        }
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
