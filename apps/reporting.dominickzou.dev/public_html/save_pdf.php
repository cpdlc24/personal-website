<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf'])) {
    $uploadDir = __DIR__ . '/exports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Create a secure hash filename
    $filename = 'report_' . bin2hex(random_bytes(8)) . '.pdf';
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($_FILES['pdf']['tmp_name'], $destination)) {
        // Return the accessible URL for the generated PDF
        echo json_encode([
            'status' => 'success',
            'url' => '/exports/' . $filename
        ]);
        exit();
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save PDF on server.']);
        exit();
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No PDF file detected inline.']);
}
?>
