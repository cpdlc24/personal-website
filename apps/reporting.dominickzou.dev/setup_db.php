<?php
$host   = '127.0.0.1';
$dbname = 'nexus_analytics';
$user   = 'collector';
$pass   = 'collector_secure_pass';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── Migration: update role constraint to include 'admin' ────────────────
    // Drop old CHECK constraint and re-add with 4 roles
    $pdo->exec("
        DO $$
        BEGIN
            -- Drop the existing check constraint on role (name may vary)
            ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
            -- Add the new constraint with all four roles
            ALTER TABLE users ADD CONSTRAINT users_role_check
                CHECK (role IN ('super_admin', 'admin', 'analyst', 'viewer'));
        EXCEPTION WHEN undefined_table THEN
            NULL; -- table doesn't exist yet, will be created below
        END $$;
    ");

    // ── Create tables (idempotent) ──────────────────────────────────────────
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL CHECK (role IN ('super_admin', 'admin', 'analyst', 'viewer')),
        display_name VARCHAR(100) DEFAULT '',
        can_export BOOLEAN DEFAULT FALSE,
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

    // ── Migration: add columns to existing users table if missing ───────────
    $pdo->exec("
        DO $$
        BEGIN
            ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT '';
            ALTER TABLE users ADD COLUMN IF NOT EXISTS can_export BOOLEAN DEFAULT FALSE;
        END $$;
    ");
    echo "Migration columns ensured.\n";

    // ── Seed Default Users ──────────────────────────────────────────────────

    // Super Admin: admin / password123
    $hashAdmin = password_hash('password123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password_hash, role, display_name, can_export)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT (username) DO UPDATE SET
            role = EXCLUDED.role,
            display_name = EXCLUDED.display_name,
            can_export = EXCLUDED.can_export"
    );

    // PDO + PostgreSQL needs 't'/'f' strings for boolean columns in prepared statements
    $stmt->execute(['admin',  $hashAdmin,  'super_admin', 'Super Admin', 't']);

    echo "Default users seeded.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
