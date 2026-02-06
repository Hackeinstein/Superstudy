<?php
/**
 * SuperStudy - Login & Registration Page
 * 
 * Handles user authentication and new account creation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$mode = isset($_GET['register']) ? 'register' : 'login';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'register') {
            // Registration
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate
            if (empty($username) || empty($email) || empty($password)) {
                $error = 'All fields are required.';
            } elseif (strlen($username) < 3 || strlen($username) > 50) {
                $error = 'Username must be 3-50 characters.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                // Check if username or email exists
                $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Username or email already exists.';
                } else {
                    // Create user
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $mysqli->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
                    $stmt->bind_param('sss', $username, $email, $password_hash);
                    
                    if ($stmt->execute()) {
                        $success = 'Account created successfully! Please log in.';
                        $mode = 'login';
                    } else {
                        $error = 'Error creating account. Please try again.';
                    }
                }
                $stmt->close();
            }
        } elseif ($action === 'login') {
            // Login
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = 'Email and password are required.';
            } else {
                $stmt = $mysqli->prepare('SELECT id, username, password_hash FROM users WHERE email = ?');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    if (password_verify($password, $row['password_hash'])) {
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['last_activity'] = time();
                        
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
                $stmt->close();
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperStudy - AI-Powered Study Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo & Title -->
            <div class="text-center mb-4">
                <div class="auth-logo">
                    <i class="bi bi-book-half"></i>
                </div>
                <h1 class="h3 mb-2">SuperStudy</h1>
                <p class="text-muted">AI-Powered Study Tool</p>
            </div>
            
            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= sanitize_output($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= sanitize_output($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Tab Navigation -->
            <ul class="nav nav-pills nav-fill mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $mode === 'login' ? 'active' : '' ?>" 
                            id="login-tab" data-bs-toggle="pill" data-bs-target="#login-panel" 
                            type="button" role="tab">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $mode === 'register' ? 'active' : '' ?>" 
                            id="register-tab" data-bs-toggle="pill" data-bs-target="#register-panel" 
                            type="button" role="tab">
                        <i class="bi bi-person-plus me-1"></i> Register
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Login Form -->
                <div class="tab-pane fade <?= $mode === 'login' ? 'show active' : '' ?>" id="login-panel" role="tabpanel">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label for="login-email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="login-email" name="email" 
                                       placeholder="you@example.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="login-password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="login-password" name="password" 
                                       placeholder="••••••••" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                        </button>
                    </form>
                </div>
                
                <!-- Register Form -->
                <div class="tab-pane fade <?= $mode === 'register' ? 'show active' : '' ?>" id="register-panel" role="tabpanel">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="mb-3">
                            <label for="reg-username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="reg-username" name="username" 
                                       placeholder="johndoe" minlength="3" maxlength="50" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reg-email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="reg-email" name="email" 
                                       placeholder="you@example.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reg-password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="reg-password" name="password" 
                                       placeholder="Min 8 characters" minlength="8" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="reg-confirm" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="reg-confirm" name="confirm_password" 
                                       placeholder="Repeat password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            <i class="bi bi-person-plus me-2"></i> Create Account
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Rate Limit Notice -->
            <div class="mt-4 pt-3 border-top">
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Note:</strong> This app uses AI providers with free-tier limits. 
                    Rate limits vary by provider (e.g., Gemini ~15 RPM, OpenAI limited usage).
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
