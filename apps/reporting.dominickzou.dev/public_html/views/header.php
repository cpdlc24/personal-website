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

        /* Account Dropdown */
        .account-dropdown-trigger {
            position: relative;
            cursor: pointer;
            user-select: none;
        }
        .account-dropdown-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            min-width: 220px;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 14px;
            padding: 8px 0;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
            opacity: 0;
            transform: translateY(-6px) scale(0.97);
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 100;
        }
        .account-dropdown-menu.open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .account-dropdown-menu::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 18px;
            width: 12px;
            height: 12px;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-bottom: none;
            border-right: none;
            transform: rotate(45deg);
            z-index: -1;
        }
        .dropdown-header {
            padding: 12px 18px 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            margin-bottom: 4px;
        }
        .dropdown-header .dropdown-name {
            font-size: 13px;
            font-weight: 500;
            color: #111;
            letter-spacing: -0.01em;
        }
        .dropdown-header .dropdown-role {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9ca3af;
            margin-top: 2px;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            letter-spacing: 0.04em;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
            cursor: pointer;
        }
        .dropdown-item:hover {
            background: rgba(0, 0, 0, 0.03);
            color: #111;
        }
        .dropdown-item svg {
            width: 15px;
            height: 15px;
            opacity: 0.4;
            flex-shrink: 0;
        }
        .dropdown-item:hover svg {
            opacity: 0.7;
        }
        .dropdown-divider {
            height: 1px;
            background: rgba(0, 0, 0, 0.04);
            margin: 4px 0;
        }
        .dropdown-item.danger {
            color: #dc2626;
        }
        .dropdown-item.danger:hover {
            background: rgba(220, 38, 38, 0.04);
            color: #b91c1c;
        }
        .dropdown-item.danger svg {
            opacity: 0.5;
        }
        .dropdown-item.active {
            background: rgba(0, 0, 0, 0.02);
        }
        .dropdown-item.active::before {
            content: '';
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #111;
            margin-right: -4px;
        }
    </style>
</head>
<body class="text-gray-900 font-sans antialiased min-h-screen pb-24 selection:bg-gray-200" style="background-color: #fafafa;">
    <noscript>
        <div style="background-color: #000; color: #fff; padding: 20px; text-align: center; position: fixed; top: 0; width: 100%; z-index: 9999; font-family: sans-serif;">
            <strong>Warning:</strong> JavaScript is required for interactive charts and dynamic visualizations. The data below is presented in plain tables.
        </div>
    </noscript>


    <nav class="sticky top-0 w-full z-40 bg-white/90 backdrop-blur-md no-print py-4">
        <div class="px-8 flex flex-col md:flex-row justify-between items-center gap-4 max-w-7xl mx-auto">
            <div class="flex items-center gap-6">
                <a href="/dashboard" class="text-xl font-medium tracking-tight hover:opacity-70 transition-opacity">Dominick Zou <span class="text-gray-400 font-light ml-2">| Analytics</span></a>
            </div>
            
            <div class="flex gap-10 items-center text-xs tracking-widest uppercase font-medium">
                <a href="/dashboard" class="<?php echo $request === '/dashboard' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Overview</a>

                <?php if ($_SESSION['role'] !== 'viewer'): ?>
                <a href="/performance" class="<?php echo $request === '/performance' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Performance</a>
                <a href="/activity" class="<?php echo $request === '/activity' ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?> transition-all">Behavioral</a>
                <?php endif; ?>

                <!-- Account Dropdown -->
                <div class="account-dropdown-trigger" id="accountDropdown">
                    <button onclick="toggleAccountMenu(event)" class="flex items-center gap-2 transition-all <?php echo in_array($request, ['/settings', '/admin']) ? 'opacity-100 font-bold border-b border-gray-900 pb-1' : 'opacity-40 hover:opacity-100'; ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <span>Account</span>
                        <svg class="w-3 h-3 transition-transform" id="accountChevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div class="account-dropdown-menu" id="accountMenu">
                        <div class="dropdown-header">
                            <div class="dropdown-name"><?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?></div>
                            <div class="dropdown-role"><?php echo htmlspecialchars(str_replace('_', ' ', $_SESSION['role'])); ?></div>
                        </div>

                        <a href="/settings" class="dropdown-item <?php echo $request === '/settings' ? 'active' : ''; ?>">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Settings
                        </a>

                        <?php if ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin'): ?>
                        <a href="/admin" class="dropdown-item <?php echo $request === '/admin' ? 'active' : ''; ?>">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Admin Panel
                        </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>

                        <a href="/logout" class="dropdown-item danger">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                            Log Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Account dropdown logic
        function toggleAccountMenu(e) {
            e.stopPropagation();
            const menu = document.getElementById('accountMenu');
            const chevron = document.getElementById('accountChevron');
            const isOpen = menu.classList.contains('open');
            if (isOpen) {
                menu.classList.remove('open');
                chevron.style.transform = 'rotate(0deg)';
            } else {
                menu.classList.add('open');
                chevron.style.transform = 'rotate(180deg)';
            }
        }

        // Close on outside click
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('accountDropdown');
            const menu = document.getElementById('accountMenu');
            const chevron = document.getElementById('accountChevron');
            if (dropdown && !dropdown.contains(e.target)) {
                menu.classList.remove('open');
                chevron.style.transform = 'rotate(0deg)';
            }
        });

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const menu = document.getElementById('accountMenu');
                const chevron = document.getElementById('accountChevron');
                menu.classList.remove('open');
                chevron.style.transform = 'rotate(0deg)';
            }
        });
    </script>

    <!-- Top Action Bar for Exporting (hidden, inlined per-page) -->
    <?php if (!empty($_SESSION['can_export'])): ?>
    <div id="globalExportBar" class="max-w-7xl mx-auto px-8 py-4 flex justify-end no-print" style="display:none;">
        <button onclick="exportToPDF()" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-transparent hover:border-gray-900 transition-colors pb-1 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Export to PDF
        </button>
    </div>
    <?php endif; ?>

    <!-- Main Content Container Injection -->
    <main class="max-w-7xl mx-auto px-8 relative z-10 w-full" id="report-content">
