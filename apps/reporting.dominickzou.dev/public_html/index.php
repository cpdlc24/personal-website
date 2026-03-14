<?php
session_start();

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$host   = '127.0.0.1';
$dbname = 'nexus_analytics';
$user   = 'collector';
$pass   = 'collector_secure_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed.");
}

// Simple RBAC Bouncer
function requireAccess($requiredSection = null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /login');
        exit();
    }
    
    $role = $_SESSION['role'];
    if ($role === 'super_admin') {
        return; // full access
    }
    
    if ($role === 'viewer') {
        // Viewers can only view the main dashboard/saved reports, nothing specific.
        if ($requiredSection !== null && $requiredSection !== 'saved_reports') {
            http_response_code(403);
            require __DIR__ . '/403.html';
            exit();
        }
        return;
    }
    
    if ($role === 'analyst') {
        // Analysts need specific section access
        if ($requiredSection !== null && !in_array($requiredSection, $_SESSION['sections'])) {
            http_response_code(403);
            require __DIR__ . '/403.html';
            exit();
        }
        return;
    }
    
    // Default deny
    http_response_code(403);
    require __DIR__ . '/403.html';
    exit();
}

switch ($request) {
    case '/':
    case '/login':
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            header('Location: /dashboard');
            exit();
        }
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userRecord && password_verify($password, $userRecord['password_hash'])) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $userRecord['id'];
                $_SESSION['username'] = $userRecord['username'];
                $_SESSION['role'] = $userRecord['role'];
                
                // Fetch analyst sections
                $_SESSION['sections'] = [];
                if ($userRecord['role'] === 'analyst') {
                    $secStmt = $pdo->prepare("SELECT section_name FROM analyst_sections WHERE user_id = ?");
                    $secStmt->execute([$userRecord['id']]);
                    $_SESSION['sections'] = $secStmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                header('Location: /dashboard');
                exit();
            } else {
                $error = "Invalid credentials!";
            }
        }
        require __DIR__ . '/views/login.php';
        break;

    case '/dashboard':
        requireAccess(); // viewable by all logged in
        require __DIR__ . '/views/dashboard.php';
        break;

    case '/performance':
        requireAccess('performance');
        require __DIR__ . '/views/performance.php';
        break;

    case '/activity':
        requireAccess('activity');
        require __DIR__ . '/views/activity.php';
        break;

    case '/api-docs':
        require __DIR__ . '/views/api-docs.php';
        break;

    // Export PDF endpoint handler
    case '/save_pdf.php':
    case '/save_pdf':
        requireAccess(); 
        require __DIR__ . '/save_pdf.php';
        break;

    case '/logout':
        session_unset();
        session_destroy();
        header('Location: /login');
        exit();

    default:
        http_response_code(404);
        require __DIR__ . '/404.html';
        break;
}
?>
