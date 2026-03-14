<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="text-gray-900 font-sans antialiased selection:bg-gray-200" style="background-color: #e2e8f0;">
    <noscript>
        <div style="background-color: #000; color: #fff; padding: 20px; text-align: center; position: fixed; top: 0; width: 100%; z-index: 9999; font-family: sans-serif;">
            <strong>Warning:</strong> JavaScript is required for interactive charts and dynamic visualizations. Please enable it for the full experience.
        </div>
    </noscript>


    <main class="min-h-screen flex items-center justify-center relative z-10 w-full">
        <div class="w-full max-w-md px-8 py-12">
            <div class="text-center mb-16">
                <h1 class="text-4xl font-medium tracking-tight mb-2">Analytics</h1>
                <p class="text-gray-400 font-light tracking-wide uppercase text-[10px]">Secure node authentication</p>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 text-sm flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="/login" method="POST" class="space-y-12">
                <div>
                    <input type="text" name="username" placeholder="Identity Node (Username)" required class="w-full bg-transparent border-b border-gray-200 py-4 text-xl focus:outline-none focus:border-gray-900 transition-colors placeholder-gray-300 text-gray-900 rounded-none">
                </div>
                <div>
                    <input type="password" name="password" placeholder="Access Cipher (Password)" required class="w-full bg-transparent border-b border-gray-200 py-4 text-xl focus:outline-none focus:border-gray-900 transition-colors placeholder-gray-300 text-gray-900 rounded-none">
                </div>
                <button type="submit" class="text-xs uppercase tracking-widest font-semibold border-b-2 border-transparent hover:border-gray-900 transition-colors pb-1 mt-8">
                    Authenticate
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-100 text-center text-xs text-gray-400 font-light">
                Accounts setup: admin, sam (analyst-perf), sally (analyst-all), viewer
            </div>
        </div>
    </main>
</body>
</html>
