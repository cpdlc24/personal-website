<?php
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

// Map resource names to JSONB columns
$resourceMap = [
    'static'       => 'static_data',
    'performance'  => 'performance_data',
    'activity'     => 'activity_data',
];

if (!$resource || !isset($resourceMap[$resource])) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown resource. Use: static, performance, or activity']);
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
                $whereSql = "WHERE created_at >= NOW() - ($hoursQuery || ' hours')::interval";
            }
            
            $stmt = $pdo->prepare("SELECT id, session_id, page_url, $column AS data, created_at FROM events $whereSql ORDER BY id DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $processedRows = [];
            foreach ($rows as $row) {
                $data = json_decode($row['data'], true) ?: [];

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
                    if (empty($data) || (!isset($data['total_load_time_ms']) && !isset($data['fcp']))) {
                        continue; // Skip heartbeats without any performance metric
                    }
                    if (isset($data['total_load_time_ms'])) {
                        $data['loadTime'] = $data['total_load_time_ms'];
                    } elseif (isset($data['fcp'])) {
                        // Fallback to First Contentful Paint if the full load time wasn't captured (ex: about page)
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
