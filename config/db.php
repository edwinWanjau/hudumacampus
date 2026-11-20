<?php
/**
 * HudumaCampus - Database & Application Configuration
 * Separates DB credentials and PDO setup from application logic.
 */

declare(strict_types=1);

// Application constants (demonstrates PHP constants requirement)
define('APP_NAME', 'HudumaCampus');
define('APP_TAGLINE', 'Campus Student Helpdesk & Inquiry Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/hudumacampus'); // Change to '' if hosted at domain root on InfinityFree
define('LOG_FILE', dirname(__DIR__) . '/logs/activity.log');
define('ITEMS_PER_PAGE', 5);

// Database configuration (Local XAMPP defaults)
// Update these 4 constants when deploying to InfinityFree
define('DB_HOST', 'localhost');
define('DB_NAME', 'hudumacampus_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO database connection instance.
 *
 * @return PDO
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Enforces real MySQL prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally without exposing DB credentials to users
            error_log('Database Connection Error: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Please ensure MySQL is running in XAMPP and hudumacampus_db is imported.');
        }
    }

    return $pdo;
}
