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
require __DIR__ . '/header.php';
?>

<div class="py-12">
    <div class="mb-12">
        <h1 class="text-4xl font-medium tracking-tighter mb-4 text-gray-900">Performance Metrics</h1>
        <p class="text-xl text-gray-500 font-light">Analyzing Total Load Times and Time-to-First-Byte across endpoints.</p>
    </div>

    <div class="mb-24 relative overflow-hidden">
        <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Load Time vs TTFB</h3>
        <div class="relative h-80 w-full">
            <canvas id="perfChart"></canvas>
        </div>
    </div>

    <!-- Data Table -->
    <div>
        <div class="flex justify-between items-end border-b border-gray-100 pb-3 mb-8">
            <h3 class="flex items-center gap-4 text-sm tracking-widest uppercase font-semibold text-gray-500">
                Recent Benchmarks
                <button type="button" id="clearFiltersBtn" class="hidden px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Clear Filters</button>
            </h3>
            <div class="flex items-center gap-10">
                <div class="flex items-center gap-3" id="pageSizeContainer">
                    <span class="text-xs text-gray-500 tracking-widest uppercase font-medium">Show</span>
                    <select id="pageSizeSelect" class="bg-white border border-gray-200 text-gray-700 hover:border-gray-300 text-sm font-medium px-3 py-2 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm transition-colors cursor-pointer appearance-none pr-8 relative" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.5rem center; background-size: 1.25em 1.25em;">
                        <option value="10">10 entries</option>
                        <option value="25">25 entries</option>
                        <option value="50" selected>50 entries</option>
                        <option value="100">100 entries</option>
                        <option value="1000000">All entries</option>
                    </select>
                </div>
                <span id="pageInfo" class="text-xs text-gray-400 tracking-widest uppercase font-medium">Latest <?php echo count($dataTable); ?> entries</span>
            </div>
        </div>
        <div class="overflow-visible overflow-x-auto pb-48">
            <table class="min-w-full divide-y divide-gray-100">
                <thead>
                    <tr>
                        <th scope="col" class="py-4 text-left relative pr-2 w-32">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="0">
                                <option value="">TIME (ALL)</option>
                                <option value="1h">&lt; 1 HOUR</option>
                                <option value="3h">&lt; 3 HOURS</option>
                                <option value="12h">&lt; 12 HOURS</option>
                                <option value="12h+">&gt; 12 HOURS</option>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="1">
                                <option value="">PATH (ALL)</option>
                                <?php foreach($uniquePaths as $val): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" title="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-28">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="2">
                                <option value="">LOAD (ALL)</option>
                                <option value="100">&lt; 100MS</option>
                                <option value="500">&lt; 500MS</option>
                                <option value="1000">&lt; 1000MS</option>
                                <option value="1000+">&gt; 1000MS</option>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-28">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="3">
                                <option value="">TTFB (ALL)</option>
                                <option value="20">&lt; 20MS</option>
                                <option value="50">&lt; 50MS</option>
                                <option value="100">&lt; 100MS</option>
                                <option value="100+">&gt; 100MS</option>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-32">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="4">
                                <option value="">BROWSER (ALL)</option>
                                <?php foreach($uniqueBrowsers as $val): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" title="<?php echo htmlspecialchars($val); ?>"><?php echo strtoupper(htmlspecialchars($val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-32">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="5">
                                <option value="">PLATFORM (ALL)</option>
                                <?php foreach($uniquePlatforms as $val): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" title="<?php echo htmlspecialchars($val); ?>"><?php echo strtoupper(htmlspecialchars($val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-32">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="6">
                                <option value="">SCREEN (ALL)</option>
                                <?php foreach($uniqueScreens as $val): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" title="<?php echo htmlspecialchars($val); ?>"><?php echo strtoupper(htmlspecialchars($val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th scope="col" class="py-4 text-left relative pr-2 w-32">
                            <select class="filter-select w-full bg-transparent text-[10px] font-semibold text-gray-400 uppercase tracking-widest hover:text-gray-700 cursor-pointer focus:outline-none appearance-none pr-4" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2212%22%20height%3D%2212%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20stroke-width%3D%222%22%20d%3D%22M19%209l-7%207-7-7%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center; background-size: 1em 1em;" data-col="7">
                                <option value="">LOCALE (ALL)</option>
                                <?php foreach($uniqueLocales as $val): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>" title="<?php echo htmlspecialchars($val); ?>"><?php echo strtoupper(htmlspecialchars($val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="perfTableBody">
                    <?php if(empty($dataTable)): ?>
                        <tr><td colspan="8" class="py-8 text-sm italic text-gray-400 font-light">Awaiting data injection.</td></tr>
                    <?php else: ?>
                        <?php foreach($dataTable as $row): ?>
                        <tr class="data-row hover:bg-gray-50/50 transition-colors group" data-raw-time="<?php echo $row['raw_time']; ?>" data-load="<?php echo $row['raw_load']; ?>" data-ttfb="<?php echo $row['raw_ttfb']; ?>">
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light"><span class="local-time" data-timestamp="<?php echo $row['raw_time']; ?>"></span></td>
                            <td class="py-4 pr-6 text-sm font-medium text-gray-900 break-words max-w-[200px]"><?php echo htmlspecialchars($row['url']); ?></td>
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-600 font-light group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($row['load']); ?></td>
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-600 font-light group-hover:text-green-600 transition-colors"><?php echo htmlspecialchars($row['ttfb']); ?></td>
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($row['browser']); ?></td>
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light"><?php echo htmlspecialchars($row['os']); ?></td>
                            <td class="py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light"><?php echo htmlspecialchars($row['screen']); ?></td>
                            <td class="py-4 whitespace-nowrap text-sm text-gray-400 font-light"><?php echo htmlspecialchars($row['language']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="flex justify-between items-center mt-6 mb-24 pb-8">
            <button id="prevPageBtn" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Previous
            </button>
            <button id="nextPageBtn" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                Next <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
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

        const ctx = document.getElementById('perfChart').getContext('2d');
        const perfChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Total Load Time (ms)',
                        data: <?php echo json_encode($loadTimes); ?>,
                        borderColor: 'rgb(37, 99, 235)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        spanGaps: true,
                        fill: true,
                        pointRadius: 2,
                        pointBackgroundColor: 'rgb(37, 99, 235)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgb(37, 99, 235)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Time To First Byte (ms)',
                        data: <?php echo json_encode($ttfbTimes); ?>,
                        borderColor: 'rgb(22, 163, 74)',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        spanGaps: true,
                        fill: true,
                        pointRadius: 2,
                        pointBackgroundColor: 'rgb(22, 163, 74)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgb(22, 163, 74)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
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
                    }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                    x: { display: false }
                }
            }
        });

        // Interactive Table Filtering
        const filterState = ["", "", "", "", "", "", "", ""];
        const tbody = document.getElementById('perfTableBody');
        const rows = Array.from(tbody.querySelectorAll('.data-row'));
        const serverTimeMs = <?php echo $serverTimeMs; ?>;
        const clearBtn = document.getElementById('clearFiltersBtn');
        const filterSelects = document.querySelectorAll('.filter-select');

        filterSelects.forEach(select => {
            select.addEventListener('change', (e) => {
                const col = parseInt(select.dataset.col);
                const val = select.value;
                filterState[col] = val;

                // Update select active outline state
                if (val !== "") {
                    select.classList.add('text-blue-600');
                    select.classList.remove('text-gray-400');
                } else {
                    select.classList.remove('text-blue-600');
                    select.classList.add('text-gray-400');
                }

                // Show/hide clear button
                if (filterState.some(f => f !== "")) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }

                applyFilters();
            });
        });

        clearBtn.addEventListener('click', () => {
            filterState.fill("");
            clearBtn.classList.add('hidden');
            filterSelects.forEach(select => {
                select.value = "";
                select.classList.remove('text-blue-600');
                select.classList.add('text-gray-400');
            });
            applyFilters();
        });

        // Pagination & Filtering Logic
        let currentPage = 1;
        let pageSize = 50;
        const pageSizeSelect = document.getElementById('pageSizeSelect');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageInfo = document.getElementById('pageInfo');
        
        pageSizeSelect.addEventListener('change', (e) => {
            pageSize = parseInt(pageSizeSelect.value, 10);
            currentPage = 1;
            renderTablePagination();
        });

        function renderTablePagination() {
            const matchingRows = rows.filter(r => r.dataset.match === 'true');
            const totalPages = Math.ceil(matchingRows.length / pageSize) || 1;
            
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach(row => {
                row.style.display = 'none';
            });

            matchingRows.slice(startIndex, endIndex).forEach(row => {
                row.style.display = '';
            });

            pageInfo.textContent = `Page ${currentPage} of ${totalPages} (${matchingRows.length} entries)`;
            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === totalPages;
        }

        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTablePagination();
            }
        });

        nextPageBtn.addEventListener('click', () => {
            const matchingRows = rows.filter(r => r.dataset.match === 'true');
            const totalPages = Math.ceil(matchingRows.length / pageSize) || 1;
            if (currentPage < totalPages) {
                currentPage++;
                renderTablePagination();
            }
        });

        function applyFilters() {
            const matchingRowsList = [];
            rows.forEach(row => {
                const tds = row.querySelectorAll('td');
                const loadVal = parseFloat(row.dataset.load);
                const ttfbVal = parseFloat(row.dataset.ttfb);
                const rawTime = parseInt(row.dataset.rawTime);
                let match = true;
                
                // Time
                if (filterState[0]) {
                    const diffHours = (serverTimeMs - rawTime) / (1000 * 60 * 60);
                    if (filterState[0] === '1h' && diffHours > 1) match = false;
                    if (filterState[0] === '3h' && diffHours > 3) match = false;
                    if (filterState[0] === '12h' && diffHours > 12) match = false;
                    if (filterState[0] === '12h+' && diffHours <= 12) match = false;
                }
                
                // Path
                if (filterState[1] && tds[1].textContent !== filterState[1]) match = false;
                
                // Load
                if (filterState[2]) {
                    if (filterState[2] === '100' && loadVal >= 100) match = false;
                    if (filterState[2] === '500' && loadVal >= 500) match = false;
                    if (filterState[2] === '1000' && loadVal >= 1000) match = false;
                    if (filterState[2] === '1000+' && loadVal < 1000) match = false;
                }
                
                // TTFB
                if (filterState[3]) {
                    if (filterState[3] === '20' && ttfbVal >= 20) match = false;
                    if (filterState[3] === '50' && ttfbVal >= 50) match = false;
                    if (filterState[3] === '100' && ttfbVal >= 100) match = false;
                    if (filterState[3] === '100+' && ttfbVal < 100) match = false;
                }
                
                // Browser, Platform, Screen, Locale
                if (filterState[4] && tds[4].textContent !== filterState[4]) match = false;
                if (filterState[5] && tds[5].textContent !== filterState[5]) match = false;
                if (filterState[6] && tds[6].textContent !== filterState[6]) match = false;
                if (filterState[7] && tds[7].textContent !== filterState[7]) match = false;

                if (match) {
                    row.dataset.match = 'true';
                    matchingRowsList.push(row);
                } else {
                    row.dataset.match = 'false';
                }
            });

            const chartRows = [...matchingRowsList].reverse();
            const labels = [];
            const loads = [];
            const ttfbs = [];

            if (chartRows.length > 0) {
                // High density: Bucket data dynamically into ~100 discrete time periods so graph shape is consistent
                const minTime = parseInt(chartRows[0].dataset.rawTime);
                const maxTime = parseInt(chartRows[chartRows.length - 1].dataset.rawTime);
                // Aim for ~100 buckets, but minimum 1-minute resolution
                const bucketMs = Math.max(1000 * 60, (maxTime - minTime) / 100);

                const buckets = {};
                chartRows.forEach(r => {
                    const timeMs = parseInt(r.dataset.rawTime);
                    const b = Math.floor(timeMs / bucketMs) * bucketMs;
                    if (!buckets[b]) buckets[b] = { load: [], ttfb: [] };
                    buckets[b].load.push(parseFloat(r.dataset.load));
                    buckets[b].ttfb.push(parseFloat(r.dataset.ttfb));
                });

                let lastBucketTime = null;
                // Iterate through the time sequentially to maintain chronological integrity
                for (let t = Math.floor(minTime / bucketMs) * bucketMs; t <= maxTime; t += bucketMs) {
                    if (buckets[t]) {
                        // Maintain 1 hour gaps visually in chart
                        if (lastBucketTime !== null && (t - lastBucketTime) > 3600000) {
                            labels.push('');
                            loads.push(null);
                            ttfbs.push(null);
                        }
                        lastBucketTime = t;

                        labels.push(chartTimeFormatter.format(new Date(t)));
                        // We use the MAX of the bucket to ensure that any major outliers are strictly visible
                        loads.push(Math.max(...buckets[t].load));
                        ttfbs.push(Math.max(...buckets[t].ttfb));
                    }
                }
            }
            
            perfChart.data.labels = labels;
            perfChart.data.datasets[0].data = loads;
            perfChart.data.datasets[1].data = ttfbs;
            perfChart.update();

            // Refresh pagination table view
            currentPage = 1;
            renderTablePagination();
        }

        // Initialize chart from raw table state
        applyFilters();
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
