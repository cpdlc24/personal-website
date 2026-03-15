<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host   = '127.0.0.1';
$dbname = 'nexus_analytics';
$user   = 'collector';
$pass   = 'collector_secure_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];
$path   = parse_url($uri, PHP_URL_PATH);

// Strip trailing slash and extract segments: /api/{resource}/{id?}
$path     = rtrim($path, '/');
$segments = explode('/', $path);

// Find the "api" segment and parse from there
$apiIndex = array_search('api', $segments);
if ($apiIndex === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit();
}

$resource = $segments[$apiIndex + 1] ?? null;
$id       = $segments[$apiIndex + 2] ?? null;

// ─── Role hierarchy helper ──────────────────────────────────────────────────
function getRoleLevel($role) {
    $levels = [
        'super_admin' => 4,
        'admin'       => 3,
        'analyst'     => 2,
        'viewer'      => 1,
    ];
    return $levels[$role] ?? 0;
}

function requireSession() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit();
    }
}

function requireApiRole($minRole) {
    requireSession();
    if (getRoleLevel($_SESSION['role']) < getRoleLevel($minRole)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit();
    }
}

// ─── Auth API (/api/auth/...) ───────────────────────────────────────────────
if ($resource === 'auth') {
    $authAction = $id;                              // e.g. login, logout, me, users
    $authId     = $segments[$apiIndex + 3] ?? null;  // user id for /api/auth/users/{id}
    $authSub    = $segments[$apiIndex + 4] ?? null;  // e.g. "sections" for /api/auth/users/{id}/sections

    // ── POST /api/auth/login ────────────────────────────────────────────────
    if ($authAction === 'login' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

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

            $_SESSION['sections'] = [];
            if ($userRecord['role'] === 'analyst') {
                $secStmt = $pdo->prepare("SELECT section_name FROM analyst_sections WHERE user_id = ?");
                $secStmt->execute([$userRecord['id']]);
                $_SESSION['sections'] = $secStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            echo json_encode([
                'status'   => 'ok',
                'user'     => [
                    'id'           => $userRecord['id'],
                    'username'     => $userRecord['username'],
                    'display_name' => $userRecord['display_name'],
                    'role'         => $userRecord['role'],
                    'can_export'   => (bool)$userRecord['can_export'],
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
        exit();
    }

    // ── POST /api/auth/logout ───────────────────────────────────────────────
    if ($authAction === 'logout' && $method === 'POST') {
        session_unset();
        session_destroy();
        echo json_encode(['status' => 'logged_out']);
        exit();
    }

    // ── GET /api/auth/me ────────────────────────────────────────────────────
    if ($authAction === 'me' && $method === 'GET') {
        requireSession();
        echo json_encode([
            'id'           => $_SESSION['user_id'],
            'username'     => $_SESSION['username'],
            'display_name' => $_SESSION['display_name'] ?? $_SESSION['username'],
            'role'         => $_SESSION['role'],
            'can_export'   => $_SESSION['can_export'] ?? false,
            'sections'     => $_SESSION['sections'] ?? [],
        ]);
        exit();
    }

    // ── PUT /api/auth/me (self-update) ──────────────────────────────────────
    if ($authAction === 'me' && $method === 'PUT') {
        requireSession();
        $userId = (int)$_SESSION['user_id'];
        $input = json_decode(file_get_contents('php://input'), true);

        // Require current password for any self-update
        $currentPw = $input['current_password'] ?? '';
        $stmt = $pdo->prepare("SELECT password_hash, username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing || !password_verify($currentPw, $existing['password_hash'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Current password is incorrect']);
            exit();
        }

        $updates = [];
        $params = [];

        // Update username
        if (!empty($input['username']) && $input['username'] !== $existing['username']) {
            $newUsername = trim($input['username']);
            if (strlen($newUsername) < 2) {
                http_response_code(400);
                echo json_encode(['error' => 'Username must be at least 2 characters']);
                exit();
            }
            // Check uniqueness
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$newUsername, $userId]);
            if ($chk->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already taken']);
                exit();
            }
            $updates[] = "username = ?";
            $params[] = $newUsername;
        }

        // Update display name
        if (isset($input['display_name'])) {
            $updates[] = "display_name = ?";
            $params[] = trim($input['display_name']);
        }

        // Update password
        if (!empty($input['new_password'])) {
            if (strlen($input['new_password']) < 4) {
                http_response_code(400);
                echo json_encode(['error' => 'New password must be at least 4 characters']);
                exit();
            }
            $updates[] = "password_hash = ?";
            $params[] = password_hash($input['new_password'], PASSWORD_BCRYPT);
        }

        if (empty($updates)) {
            echo json_encode(['status' => 'no_changes']);
            exit();
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        // Refresh session
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $refreshed = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($refreshed) {
            $_SESSION['username'] = $refreshed['username'];
            $_SESSION['display_name'] = $refreshed['display_name'] ?? $refreshed['username'];
        }

        echo json_encode(['status' => 'updated']);
        exit();
    }

    // ── GET /api/auth/users ─────────────────────────────────────────────────
    if ($authAction === 'users' && !$authId && $method === 'GET') {
        requireApiRole('admin');
        $callerRole = $_SESSION['role'];

        if ($callerRole === 'super_admin') {
            $stmt = $pdo->query("SELECT id, username, display_name, role, can_export, created_at FROM users ORDER BY id");
        } else {
            // Admin can only see analyst + viewer
            $stmt = $pdo->query("SELECT id, username, display_name, role, can_export, created_at FROM users WHERE role IN ('analyst', 'viewer') ORDER BY id");
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach sections for analysts
        foreach ($users as &$u) {
            $u['can_export'] = (bool)$u['can_export'];
            if ($u['role'] === 'analyst') {
                $secStmt = $pdo->prepare("SELECT section_name FROM analyst_sections WHERE user_id = ?");
                $secStmt->execute([$u['id']]);
                $u['sections'] = $secStmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $u['sections'] = [];
            }
        }
        echo json_encode($users);
        exit();
    }

    // ── POST /api/auth/users (create user) ──────────────────────────────────
    if ($authAction === 'users' && !$authId && $method === 'POST') {
        requireApiRole('admin');
        $callerRole = $_SESSION['role'];
        $input = json_decode(file_get_contents('php://input'), true);

        $newUsername    = trim($input['username'] ?? '');
        $newPassword   = $input['password'] ?? '';
        $newRole       = $input['role'] ?? 'viewer';
        $newDisplay    = trim($input['display_name'] ?? $newUsername);
        $newCanExport  = !empty($input['can_export']);
        $newSections   = $input['sections'] ?? [];

        // Validation
        if (strlen($newUsername) < 2 || strlen($newPassword) < 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Username min 2 chars, password min 4 chars']);
            exit();
        }
        if (!in_array($newRole, ['super_admin', 'admin', 'analyst', 'viewer'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role']);
            exit();
        }

        // Admin cannot create admin or super_admin
        if ($callerRole === 'admin' && getRoleLevel($newRole) >= getRoleLevel('admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Admins can only create analyst or viewer accounts']);
            exit();
        }

        // Viewer cannot export
        if ($newRole === 'viewer') {
            $newCanExport = false;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, password_hash, role, display_name, can_export) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$newUsername, $hash, $newRole, $newDisplay, $newCanExport ? 't' : 'f']);
            $newId = $pdo->lastInsertId();

            // Set analyst sections if applicable
            if ($newRole === 'analyst' && !empty($newSections)) {
                $secStmt = $pdo->prepare("INSERT INTO analyst_sections (user_id, section_name) VALUES (?, ?) ON CONFLICT DO NOTHING");
                foreach ($newSections as $sec) {
                    $secStmt->execute([$newId, $sec]);
                }
            }

            http_response_code(201);
            echo json_encode(['status' => 'created', 'id' => (int)$newId]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'duplicate key') !== false || strpos($e->getMessage(), 'unique') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create user']);
            }
        }
        exit();
    }

    // ── PUT /api/auth/users/{id} (update user) ─────────────────────────────
    if ($authAction === 'users' && $authId && !$authSub && $method === 'PUT') {
        requireApiRole('admin');
        $callerRole = $_SESSION['role'];
        $targetId = (int)$authId;

        // Fetch existing user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }

        // Admin can't edit admin/super_admin users
        if ($callerRole === 'admin' && getRoleLevel($existing['role']) >= getRoleLevel('admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions to edit this user']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $newRole      = $input['role'] ?? $existing['role'];
        $newDisplay   = $input['display_name'] ?? $existing['display_name'];
        $newCanExport = isset($input['can_export']) ? !empty($input['can_export']) : (bool)$existing['can_export'];

        if (!in_array($newRole, ['super_admin', 'admin', 'analyst', 'viewer'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid role']);
            exit();
        }

        // Admin can't promote to admin or super_admin
        if ($callerRole === 'admin' && getRoleLevel($newRole) >= getRoleLevel('admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Admins can only assign analyst or viewer roles']);
            exit();
        }

        // Viewers cannot export
        if ($newRole === 'viewer') {
            $newCanExport = false;
        }

        // Update basic fields
        $stmt = $pdo->prepare("UPDATE users SET role = ?, display_name = ?, can_export = ? WHERE id = ?");
        $stmt->execute([$newRole, $newDisplay, $newCanExport ? 't' : 'f', $targetId]);

        // Update password if provided
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 4) {
                http_response_code(400);
                echo json_encode(['error' => 'Password min 4 chars']);
                exit();
            }
            $hash = password_hash($input['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $targetId]);
        }

        echo json_encode(['status' => 'updated', 'id' => $targetId]);
        exit();
    }

    // ── PUT /api/auth/users/{id}/sections ───────────────────────────────────
    if ($authAction === 'users' && $authId && $authSub === 'sections' && $method === 'PUT') {
        requireApiRole('admin');
        $callerRole = $_SESSION['role'];
        $targetId = (int)$authId;

        // Verify user exists and is analyst
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetRole = $stmt->fetchColumn();
        if (!$targetRole) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }
        if ($targetRole !== 'analyst') {
            http_response_code(400);
            echo json_encode(['error' => 'Sections only apply to analysts']);
            exit();
        }
        if ($callerRole === 'admin' && getRoleLevel($targetRole) >= getRoleLevel('admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $sections = $input['sections'] ?? [];

        // Replace all sections: delete + re-insert
        $pdo->prepare("DELETE FROM analyst_sections WHERE user_id = ?")->execute([$targetId]);
        $secStmt = $pdo->prepare("INSERT INTO analyst_sections (user_id, section_name) VALUES (?, ?)");
        foreach ($sections as $sec) {
            if (in_array($sec, ['performance', 'activity'])) {
                $secStmt->execute([$targetId, $sec]);
            }
        }

        echo json_encode(['status' => 'sections_updated', 'sections' => $sections]);
        exit();
    }

    // ── DELETE /api/auth/users/{id} ─────────────────────────────────────────
    if ($authAction === 'users' && $authId && $method === 'DELETE') {
        requireApiRole('admin');
        $callerRole = $_SESSION['role'];
        $targetId = (int)$authId;

        // Can't delete yourself
        if ($targetId === (int)$_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            exit();
        }

        // Fetch target
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetRole = $stmt->fetchColumn();
        if (!$targetRole) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit();
        }

        // Admin can't delete admin/super_admin
        if ($callerRole === 'admin' && getRoleLevel($targetRole) >= getRoleLevel('admin')) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions to delete this user']);
            exit();
        }

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
        echo json_encode(['status' => 'deleted', 'id' => $targetId]);
        exit();
    }

    // Unknown auth route
    http_response_code(404);
    echo json_encode(['error' => 'Unknown auth endpoint']);
    exit();
}

// ─── Analytics Data API (existing) ──────────────────────────────────────────

// Map resource names to JSONB columns
$resourceMap = [
    'static'       => 'static_data',
    'performance'  => 'performance_data',
    'activity'     => 'activity_data',
];

if (!$resource || !isset($resourceMap[$resource])) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown resource. Use: static, performance, activity, or auth']);
    exit();
}

$column = $resourceMap[$resource];

switch ($method) {

    // ---- GET: retrieve all or one by id ----
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT id, session_id, page_url, $column AS data, created_at FROM events WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['error' => 'Entry not found']);
                exit();
            }
            $row['data'] = json_decode($row['data'], true);
            echo json_encode($row);
        } else {
            $hoursQuery = isset($_GET['hours']) ? (int)$_GET['hours'] : 0;
            $whereSql = "";
            $params = [];
            
            if ($hoursQuery > 0) {
                $whereSql = "WHERE created_at >= NOW() - make_interval(hours => :hours)";
                $params[':hours'] = $hoursQuery;
            }
            
            $stmt = $pdo->prepare("SELECT id, session_id, page_url, $column AS data, created_at FROM events $whereSql ORDER BY id DESC");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $processedRows = [];
            foreach ($rows as $row) {
                $data = json_decode($row['data'], true) ?: [];
                // Pre-compute UTC millisecond timestamp for client-side use
                $row['created_at_ms'] = (int)(strtotime($row['created_at']) * 1000);

                // Static mapping
                if ($resource === 'static') {
                    if (empty($data) || !isset($data['user_agent'])) {
                        continue; // Skip heartbeats or events without static footprint
                    }
                    if (isset($data['user_agent'])) {
                        // Simple UA parsing
                        $ua = $data['user_agent'];
                        $browser = 'Unknown';
                        if (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
                        elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
                        elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
                        elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';
                        
                        $os = 'Unknown';
                        if (strpos($ua, 'Win') !== false) $os = 'Windows';
                        elseif (strpos($ua, 'Mac') !== false) $os = 'MacOS';
                        elseif (strpos($ua, 'Linux') !== false || strpos($ua, 'X11') !== false) $os = 'Linux';
                        elseif (strpos($ua, 'Android') !== false) $os = 'Android';
                        elseif (strpos($ua, 'like Mac') !== false) $os = 'iOS';
                        
                        $data['browser'] = $browser;
                        $data['os'] = $os;
                    }
                    $row['data'] = $data;
                    $processedRows[] = $row;
                }
                
                // Performance mapping
                elseif ($resource === 'performance') {
                    // Only include genuine page-load events that have navigationTiming
                    // Heartbeat pings may carry fcp from vitals snapshots but lack navigationTiming
                    $hasNavTiming = isset($data['navigationTiming']) && !empty($data['navigationTiming']['timeToFirstByte']);
                    $hasLoadTime = isset($data['total_load_time_ms']) || isset($data['fcp']);
                    
                    if (!$hasNavTiming || !$hasLoadTime) {
                        continue;
                    }
                    if (isset($data['total_load_time_ms'])) {
                        $data['loadTime'] = $data['total_load_time_ms'];
                    } elseif (isset($data['fcp'])) {
                        $data['loadTime'] = $data['fcp'];
                    }
                    $row['data'] = $data;
                    $processedRows[] = $row;
                }
                
                // Activity mapping
                elseif ($resource === 'activity') {
                    // Flatten arrays into separate rows
                    $hasActivity = false;
                    
                    if (isset($data['mouse']['clicks']) && is_array($data['mouse']['clicks'])) {
                        foreach ($data['mouse']['clicks'] as $click) {
                            $newRow = $row;
                            $newRow['data'] = ['action' => 'click', 'element' => 'X: ' . $click['x'] . ', Y: ' . $click['y']];
                            $processedRows[] = $newRow;
                            $hasActivity = true;
                        }
                    }
                    if (isset($data['mouse']['scrolls']) && is_array($data['mouse']['scrolls'])) {
                        foreach ($data['mouse']['scrolls'] as $scroll) {
                            $newRow = $row;
                            $newRow['data'] = ['action' => 'scroll', 'element' => 'Y: ' . $scroll['y']];
                            $processedRows[] = $newRow;
                            $hasActivity = true;
                        }
                    }
                    if (isset($data['keyboard']) && is_array($data['keyboard'])) {
                        foreach ($data['keyboard'] as $key) {
                            $newRow = $row;
                            $newRow['data'] = ['action' => 'keyboard_' . $key['type'], 'element' => $key['key']];
                            $processedRows[] = $newRow;
                            $hasActivity = true;
                        }
                    }
                    if (isset($data['errors']) && is_array($data['errors'])) {
                        foreach ($data['errors'] as $err) {
                            $newRow = $row;
                            $newRow['data'] = ['action' => 'error', 'element' => $err['name'] . ': ' . $err['message']];
                            $processedRows[] = $newRow;
                            $hasActivity = true;
                        }
                    }
                    
                    if (!$hasActivity) {
                        // Empty batched heartbeat
                        $row['data'] = ['action' => 'heartbeat', 'element' => 'page stay'];
                        $processedRows[] = $row;
                    }
                }
            }
            echo json_encode($processedRows);
        }
        break;

    // ---- POST: create a new entry (no id in URL) ----
    case 'POST':
        if ($id) {
            http_response_code(400);
            echo json_encode(['error' => 'POST should not include an ID in the URL']);
            exit();
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['session_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON or missing session_id']);
            exit();
        }
        $stmt = $pdo->prepare(
            "INSERT INTO events (session_id, page_url, $column) VALUES (:session_id, :page_url, :data) RETURNING id"
        );
        $stmt->execute([
            ':session_id' => $input['session_id'],
            ':page_url'   => $input['page_url'] ?? '',
            ':data'       => isset($input['data']) ? json_encode($input['data']) : '{}',
        ]);
        $newId = $stmt->fetchColumn();
        http_response_code(201);
        echo json_encode(['id' => (int)$newId, 'status' => 'created']);
        break;

    // ---- PUT: update a specific entry (id required) ----
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'PUT requires an ID in the URL']);
            exit();
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['data'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON or missing data field']);
            exit();
        }
        $stmt = $pdo->prepare("UPDATE events SET $column = :data WHERE id = :id");
        $stmt->execute([
            ':data' => json_encode($input['data']),
            ':id'   => $id,
        ]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Entry not found']);
            exit();
        }
        echo json_encode(['id' => (int)$id, 'status' => 'updated']);
        break;

    // ---- DELETE: remove a specific entry (id required) ----
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'DELETE requires an ID in the URL']);
            exit();
        }
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Entry not found']);
            exit();
        }
        echo json_encode(['id' => (int)$id, 'status' => 'deleted']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
