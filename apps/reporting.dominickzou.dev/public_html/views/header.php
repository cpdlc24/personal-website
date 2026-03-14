<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- html2pdf for PDF Exports -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* PDF Export explicit overrides */
        @media print {
            .no-print { display: none !important; }
            #unified-bg-canvas { display: none !important; }
            body { background: white; color: black; }
        }
    </style>
</head>
<body class="text-gray-900 font-sans antialiased min-h-screen pb-24 selection:bg-gray-200" style="background-color: #e2e8f0;">
    <noscript>
        <div style="background-color: #000; color: #fff; padding: 20px; text-align: center; position: fixed; top: 0; width: 100%; z-index: 9999; font-family: sans-serif;">
            <strong>Warning:</strong> JavaScript is required for interactive charts and dynamic visualizations. The data below is presented in plain tables.
        </div>
    </noscript>


    <nav class="sticky top-0 w-full z-40 bg-white/90 backdrop-blur-md no-print py-4">
        <div class="px-8 flex flex-col md:flex-row justify-between items-center gap-4 max-w-7xl mx-auto">
            <div class="flex items-center gap-6">
                <a href="/dashboard" class="text-xl font-medium tracking-tight hover:opacity-70 transition-opacity">Dominick Zou <span class="text-gray-400 font-light ml-2">| Analytics</span></a>
                <div class="text-[10px] tracking-widest font-semibold uppercase text-gray-400">
                    Role: <?php echo htmlspecialchars($_SESSION['role']); ?>
                </div>
            </div>
            
            <div class="flex gap-10 items-center text-xs tracking-widest uppercase font-medium">
                <a href="/dashboard" class="<?php echo $request === '/dashboard' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Overview</a>
                <a href="/performance" class="<?php echo $request === '/performance' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Performance</a>
                <a href="/activity" class="<?php echo $request === '/activity' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Behavioral</a>
                <a href="/logout" class="opacity-40 hover:opacity-100 text-red-600 transition-all ml-4">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Top Action Bar for Exporting (hidden, inlined per-page) -->
    <div id="globalExportBar" class="max-w-7xl mx-auto px-8 py-4 flex justify-end no-print" style="display:none;">
        <button onclick="exportToPDF()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-transparent hover:border-gray-900 transition-colors pb-1 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export to PDF
        </button>
    </div>

    <!-- Main Content Container Injection -->
    <main class="max-w-7xl mx-auto px-8 relative z-10 w-full" id="report-content">
