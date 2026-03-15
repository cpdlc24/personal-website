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

// ── Role hierarchy helper ───────────────────────────────────────────────────
function getRoleLevel($role) {
    $levels = [
        'super_admin' => 4,
        'admin'       => 3,
        'analyst'     => 2,
        'viewer'      => 1,
    ];
    return $levels[$role] ?? 0;
}

// ── RBAC Bouncer ────────────────────────────────────────────────────────────
function requireAccess($requiredSection = null) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /login');
        exit();
    }
    
    $role = $_SESSION['role'];

    // Super admin & admin: full page access
    if ($role === 'super_admin' || $role === 'admin') {
        return;
    }
    
    // Viewer: dashboard only, no sub-pages
    if ($role === 'viewer') {
        if ($requiredSection !== null && $requiredSection !== 'saved_reports') {
            http_response_code(403);
            require __DIR__ . '/403.html';
            exit();
        }
        return;
    }
    
    // Analyst: check section-level access
    if ($role === 'analyst') {
        if ($requiredSection !== null && !in_array($requiredSection, $_SESSION['sections'] ?? [])) {
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

// ── Require minimum role ────────────────────────────────────────────────────
function requireRole($minRole) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: /login');
        exit();
    }
    if (getRoleLevel($_SESSION['role']) < getRoleLevel($minRole)) {
        http_response_code(403);
        require __DIR__ . '/403.html';
        exit();
    }
}

// ── Export permission check ─────────────────────────────────────────────────
function requireExport() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    if (empty($_SESSION['can_export'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Export not permitted for your role']);
        exit();
    }
}

switch ($request) {
    case '/':
    case '/login':
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            header('Location: /dashboard');
            exit();
        }
        $error = null;
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userRecord && password_verify($password, $userRecord['password_hash'])) {
                $_SESSION['logged_in']    = true;
                $_SESSION['user_id']      = $userRecord['id'];
                $_SESSION['username']     = $userRecord['username'];
                $_SESSION['role']         = $userRecord['role'];
                $_SESSION['display_name'] = $userRecord['display_name'] ?? $userRecord['username'];
                $_SESSION['can_export']   = (bool)$userRecord['can_export'];
                
                // Fetch analyst sections
                $_SESSION['sections'] = [];
                if ($userRecord['role'] === 'analyst') {
                    $secStmt = $pdo->prepare("SELECT section_name FROM analyst_sections WHERE user_id = ?");
                    $secStmt->execute([$userRecord['id']]);
                    $_SESSION['sections'] = $secStmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true]);
                    exit();
                }
                header('Location: /dashboard');
                exit();
            } else {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(401);
                    echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
                    exit();
                }
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

    case '/settings':
        requireAccess();
        require __DIR__ . '/views/settings.php';
        break;

    case '/admin':
        requireRole('admin');
        require __DIR__ . '/views/admin.php';
        break;

    case '/api-docs':
        require __DIR__ . '/views/api-docs.php';
        break;

    // Export PDF endpoint handler
    case '/save_pdf.php':
    case '/save_pdf':
        requireExport();
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
