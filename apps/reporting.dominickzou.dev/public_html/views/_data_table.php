<?php
/**
 * Shared data table partial — renders filter bar, table, pagination.
 *
 * Required variables:
 *   $tableId        – unique tbody id
 *   $tableColumns   – array of [ 'label' => string, 'width' => optional ]
 *
 * Optional:
 *   $tableFilters   – array of filter configs: [ 'options' => [['value'=>'','label'=>'...']] ]
 *   $clearBtnId, $pageSizeId, $prevBtnId, $nextBtnId, $pageInfoId – element IDs
 */
$tableFilters  = $tableFilters  ?? [];
$clearBtnId    = $clearBtnId    ?? 'clearFiltersBtn';
$pageSizeId    = $pageSizeId    ?? 'pageSizeSelect';
$prevBtnId     = $prevBtnId     ?? 'prevPageBtn';
$nextBtnId     = $nextBtnId     ?? 'nextPageBtn';
$pageInfoId    = $pageInfoId    ?? 'pageInfo';
?>

<div class="data-table-wrapper">
    <!-- Filter Bar -->
    <?php if (!empty($tableFilters)): ?>
    <div class="filter-bar border-b border-gray-100 pb-3 mb-8" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <span class="text-[10px] tracking-widest uppercase text-gray-400 font-semibold" style="margin-right:0.25rem;">Filters</span>
        <?php foreach ($tableFilters as $i => $f): ?>
        <select class="filter-select" data-col="<?php echo $i; ?>" style="font-size:11px;font-weight:500;padding:6px 14px;border-radius:8px;border:1px solid rgba(0,0,0,0.08);background:rgba(255,255,255,0.95);color:#374151;cursor:pointer;outline:none;letter-spacing:0.03em;">
            <?php foreach ($f['options'] as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt['value']); ?>"<?php echo ($opt['selected'] ?? false) ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endforeach; ?>
        <button id="<?php echo $clearBtnId; ?>" style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#9ca3af;cursor:pointer;background:none;border:none;padding:6px 8px;transition:color 0.15s;display:none;" onmouseover="this.style.color='#111'" onmouseout="this.style.color='#9ca3af'">Clear</button>
    </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead>
                <tr class="border-b border-gray-200">
                    <?php foreach ($tableColumns as $i => $col): ?>
                    <th scope="col" class="py-4 text-left pr-2 <?php echo $col['width'] ?? ''; ?>">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest"><?php echo htmlspecialchars($col['label']); ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="<?php echo $tableId; ?>">
            </tbody>
        </table>
    </div>

    <!-- Pagination + Page Info + Show entries -->
    <div class="flex justify-between items-center mt-6 mb-24 pb-8">
        <button id="<?php echo $prevBtnId; ?>" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg> Previous
        </button>
        <span id="<?php echo $pageInfoId; ?>" class="text-xs text-gray-400 tracking-widest uppercase font-medium"></span>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Show</span>
                <select id="<?php echo $pageSizeId; ?>" class="text-sm bg-white border border-gray-200 rounded-md px-2 py-1.5 text-gray-600 focus:outline-none shadow-sm">
                    <option value="50" selected>50 entries</option>
                    <option value="100">100 entries</option>
                    <option value="250">250 entries</option>
                </select>
            </div>
            <button id="<?php echo $nextBtnId; ?>" disabled class="px-4 py-2 border border-gray-200 bg-white text-gray-700 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 hover:text-gray-900 transition-all shadow-sm flex items-center gap-2">
                Next <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
        </div>
    </div>
</div>
