<?php
// Enable CORS dynamically for the tracking domains
$allowed_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Read JSON payload
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data || !isset($data['session_id'])) {
    http_response_code(400);
    exit('Bad Request: Invalid JSON or missing session_id');
}

// Database Connection
$host = '127.0.0.1';
$dbname = 'nexus_analytics';
$user = 'collector';
$pass = 'collector_secure_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if it doesn't exist (Runs once per DB init)
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS events (
            id SERIAL PRIMARY KEY,
            session_id TEXT NOT NULL,
            page_url TEXT NOT NULL,
            static_data JSONB,
            performance_data JSONB,
            technographics_data JSONB,
            activity_data JSONB,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS session_idx ON events (session_id);
    ";
    $pdo->exec($createTableQuery);

    // Insert the payload data
    $stmt = $pdo->prepare("
        INSERT INTO events (session_id, page_url, static_data, performance_data, technographics_data, activity_data)
        VALUES (:session_id, :page_url, :static_data, :performance_data, :technographics_data, :activity_data)
    ");

    $stmt->execute([
        ':session_id' => $data['session_id'],
        ':page_url' => $data['page_url'] ?? '',
        ':static_data' => isset($data['static']) ? json_encode($data['static']) : null,
        ':performance_data' => isset($data['performance']) ? json_encode($data['performance']) : null,
        ':technographics_data' => isset($data['technographics']) ? json_encode($data['technographics']) : null,
        ':activity_data' => isset($data['activity']) ? json_encode($data['activity']) : null
    ]);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    error_log("Analytics DB Error: " . $e->getMessage());
    http_response_code(500);
    exit('Internal Server Error');
}
?>
