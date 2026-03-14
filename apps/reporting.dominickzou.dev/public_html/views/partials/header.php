<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Portal</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <div><strong>Analytics</strong></div>
        <div>
            <a href="/dashboard">Dashboard</a>
            <a href="/api-docs" target="_blank">API Docs</a>
            <a href="/logout" class="btn-logout">Logout (<?= htmlspecialchars($_SESSION['username'] ?? '') ?>)</a>
        </div>
    </div>
    <div class="container">
