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

// ── Process Activity data for click heatmap ─────────────────────────────────
$clicks = [];
foreach ($activityData as $row) {
    $d = $row['data'] ?? [];
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

require __DIR__ . '/header.php';
?>

<div class="py-12">
    <div class="mb-12">
        <h1 class="text-4xl font-medium tracking-tighter mb-4 text-gray-900">Behavioral Intelligence</h1>
        <p class="text-xl text-gray-500 font-light">Visualizing click-distribution heatmaps and client environments.</p>
    </div>

    <!-- Heatmap & Stats Split -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
        
        <!-- Synthetic Visual Heatmap -->
        <div class="mb-12">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Aggregate Click Distribution</h3>
            <!-- Aspect Ratio Box showing tracked site -->
            <div class="relative w-full aspect-video bg-white rounded-lg overflow-hidden border border-gray-100" id="heatmapContainer">
                <iframe id="heatmapIframe" src="https://test.dominickzou.dev" class="absolute inset-0 w-full h-full pointer-events-none border-0 opacity-60" style="transform-origin: 0 0;" sandbox="allow-same-origin" loading="lazy"></iframe>
                <div id="heatmapOverlay" class="absolute inset-0 z-10 pointer-events-none"></div>
            </div>
            <p class="text-[10px] text-gray-400 mt-4 tracking-widest uppercase text-center">Click coordinates mapped onto the most-visited tracked page.</p>
        </div>

        <div class="mb-12">
            <h3 class="text-xs tracking-widest uppercase font-medium text-gray-400 mb-8 border-b border-gray-100 pb-2">Client Architecture (OS)</h3>
            <div class="relative h-64 w-full flex items-center justify-center">
                <canvas id="osChart"></canvas>
            </div>
            
            <div class="mt-8">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead>
                        <tr><th class="py-4 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Browser Engine</th><th class="py-4 text-right text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Sessions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php arsort($browsers); foreach($browsers as $k => $v): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group"><td class="py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($k); ?></td><td class="py-4 text-right text-sm text-gray-400 font-light group-hover:text-gray-900 transition-colors"><?php echo $v; ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Render Heatmap — Interactive with tooltips
        const rawClicks = <?php echo json_encode($clicks); ?>;
        const overlay = document.getElementById('heatmapOverlay');
        const container = document.getElementById('heatmapContainer');
        
        // Aggregate nearby clicks into clusters
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
            let dot = document.createElement('div');
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
                tooltip.textContent = `${c.count} click${c.count > 1 ? 's' : ''} · (${pxX}, ${pxY})px`;
                tooltip.style.opacity = '1';
                tooltip.style.left = `calc(${c.x}% + ${size/2 + 8}px)`;
                tooltip.style.top = `calc(${c.y}% - 14px)`;
            });
            dot.addEventListener('mouseleave', () => {
                tooltip.style.opacity = '0';
            });
            
            overlay.appendChild(dot);
        });

        // Render OS Chart
        const osData = <?php echo json_encode(array_values($osCounts)); ?>;
        const osLabels = <?php echo json_encode(array_keys($osCounts)); ?>;
        const ctx = document.getElementById('osChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: osLabels,
                datasets: [{
                    data: osData,
                    backgroundColor: ['#2563eb', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    });
</script>

<?php require __DIR__ . '/footer.php'; ?>
