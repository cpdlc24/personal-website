<?php
$host   = '127.0.0.1';
$dbname = 'nexus_analytics';
$user   = 'collector';
$pass   = 'collector_secure_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL CHECK (role IN ('super_admin', 'analyst', 'viewer')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS analyst_sections (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        section_name VARCHAR(50) NOT NULL,
        UNIQUE(user_id, section_name)
    );

    CREATE TABLE IF NOT EXISTS analyst_comments (
        id SERIAL PRIMARY KEY,
        report_type VARCHAR(50) NOT NULL,
        comment TEXT NOT NULL,
        user_id INT REFERENCES users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    $pdo->exec($sql);
    echo "Tables created successfully.\n";

    // Insert Default Users if don't exist
    
    // Super Admin: admin / password123
    $hashAdmin = password_hash('password123', PASSWORD_DEFAULT);
    
    // Analyst Sam (Performance): sam / analyst123
    $hashSam = password_hash('analyst123', PASSWORD_DEFAULT);

    // Analyst Sally (Performance, Behavioral): sally / analyst123
    $hashSally = password_hash('analyst123', PASSWORD_DEFAULT);

    // Viewer: viewer / viewer123
    $hashViewer = password_hash('viewer123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?) ON CONFLICT (username) DO NOTHING RETURNING id");
    
    $stmt->execute(['admin', $hashAdmin, 'super_admin']);
    $stmt->execute(['sam', $hashSam, 'analyst']);
    $samId = $pdo->lastInsertId();
    if(!$samId) {
        $q = $pdo->query("SELECT id FROM users WHERE username='sam'");
        $samId = $q->fetchColumn();
    }
    
    $stmt->execute(['sally', $hashSally, 'analyst']);
    $sallyId = $pdo->lastInsertId();
    if(!$sallyId) {
        $q = $pdo->query("SELECT id FROM users WHERE username='sally'");
        $sallyId = $q->fetchColumn();
    }

    $stmt->execute(['viewer', $hashViewer, 'viewer']);

    // Map analyst sections
    $mapStmt = $pdo->prepare("INSERT INTO analyst_sections (user_id, section_name) VALUES (?, ?) ON CONFLICT DO NOTHING");
    $mapStmt->execute([$samId, 'performance']);
    $mapStmt->execute([$sallyId, 'performance']);
    $mapStmt->execute([$sallyId, 'activity']); // Sally gets both performance and behavioral (activity)

    echo "Default users seeded.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
