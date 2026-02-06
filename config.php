<?php
/**
 * SuperStudy Configuration File
 * 
 * Database connection and application settings.
 * Update the database credentials below before use.
 */

// ============================================
// Error Reporting (disable in production)
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// Database Configuration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Set your MySQL password
define('DB_NAME', 'superstudy');

// ============================================
// Security Configuration
// ============================================
// IMPORTANT: Change this key in production!
define('ENCRYPTION_KEY', 'your-32-character-encryption-key!');
define('CSRF_TOKEN_NAME', 'csrf_token');

// ============================================
// Application Settings
// ============================================
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'txt']);
define('SESSION_TIMEOUT', 3600); // 1 hour

// ============================================
// AI Provider Endpoints
// ============================================
define('AI_ENDPOINTS', [
    'openai' => 'https://api.openai.com/v1/chat/completions',
    'anthropic' => 'https://api.anthropic.com/v1/messages',
    'google-gemini' => 'https://generativelanguage.googleapis.com/v1beta/models/',
    'xai-grok' => 'https://api.x.ai/v1/chat/completions',
    'openrouter' => 'https://openrouter.ai/api/v1/chat/completions'
]);

// ============================================
// Default AI Models per Provider
// ============================================
define('DEFAULT_MODELS', [
    'openai' => 'gpt-4o-mini',
    'anthropic' => 'claude-3-5-sonnet-20241022',
    'google-gemini' => 'gemini-1.5-flash',
    'xai-grok' => 'grok-beta',
    'openrouter' => 'openai/gpt-4o-mini'
]);

// ============================================
// Database Connection
// ============================================
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');

// ============================================
// Session Configuration
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// ============================================
// Helper Functions
// ============================================

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication - redirect to login if not logged in
 */
function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Get current user ID
 */
function get_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 */
function get_username(): ?string {
    return $_SESSION['username'] ?? null;
}
