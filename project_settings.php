<?php
/**
 * SuperStudy - Project Settings Page
 * 
 * Allows editing project name, description, AI provider, model, and API key.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_auth();

$user_id = get_user_id();
$csrf_token = generate_csrf_token();

$error = '';
$success = '';

// Get project ID
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    header('Location: dashboard.php');
    exit;
}

// Load project
$stmt = $mysqli->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $project_id, $user_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$project) {
    header('Location: dashboard.php');
    exit;
}

// AI Provider options
$providers = [
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic (Claude)',
    'google-gemini' => 'Google Gemini',
    'xai-grok' => 'xAI Grok',
    'openrouter' => 'OpenRouter'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $ai_provider = sanitize_input($_POST['ai_provider'] ?? '');
        $model_name = sanitize_input($_POST['model_name'] ?? '');
        $new_api_key = $_POST['api_key'] ?? '';
        
        if (empty($name)) {
            $error = 'Project name is required.';
        } elseif (!array_key_exists($ai_provider, $providers)) {
            $error = 'Invalid AI provider selected.';
        } else {
            // Only update API key if a new one was provided
            if (!empty($new_api_key)) {
                $encrypted_key = encrypt_api_key($new_api_key);
                $stmt = $mysqli->prepare('UPDATE projects SET name = ?, description = ?, ai_provider = ?, model_name = ?, api_key = ? WHERE id = ? AND user_id = ?');
                $stmt->bind_param('sssssii', $name, $description, $ai_provider, $model_name, $encrypted_key, $project_id, $user_id);
            } else {
                $stmt = $mysqli->prepare('UPDATE projects SET name = ?, description = ?, ai_provider = ?, model_name = ? WHERE id = ? AND user_id = ?');
                $stmt->bind_param('ssssii', $name, $description, $ai_provider, $model_name, $project_id, $user_id);
            }
            
            if ($stmt->execute()) {
                $success = 'Project settings updated successfully!';
                // Reload project data
                $stmt2 = $mysqli->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
                $stmt2->bind_param('ii', $project_id, $user_id);
                $stmt2->execute();
                $project = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            } else {
                $error = 'Failed to update project settings.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?= sanitize_output($project['name']) ?> - SuperStudy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-book-half me-2"></i>SuperStudy
            </a>
            <div class="d-flex align-items-center">
                <a href="project.php?id=<?= $project_id ?>" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i> Back to Project
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Projects</a></li>
                <li class="breadcrumb-item"><a href="project.php?id=<?= $project_id ?>"><?= sanitize_output($project['name']) ?></a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
        
        <!-- Alerts -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= sanitize_output($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= sanitize_output($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-gear me-2"></i>Project Settings</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Project Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= sanitize_output($project['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"
                                          placeholder="Brief description of this study project"><?= sanitize_output($project['description']) ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ai_provider" class="form-label">AI Provider *</label>
                                    <select class="form-select" id="ai_provider" name="ai_provider" required>
                                        <?php foreach ($providers as $key => $name): ?>
                                            <option value="<?= $key ?>" <?= $project['ai_provider'] === $key ? 'selected' : '' ?>>
                                                <?= sanitize_output($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model_name" class="form-label">Model Name</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="model_name" name="model_name" 
                                               value="<?= sanitize_output($project['model_name']) ?>"
                                               placeholder="e.g., gpt-4o-mini">
                                    </div>
                                    <div class="form-text">Current model: <?= sanitize_output($project['model_name']) ?></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="api_key" class="form-label">API Key</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="api_key" name="api_key" 
                                           placeholder="Enter new API key (leave blank to keep current)">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleKey">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-shield-check text-success me-1"></i>
                                    Current key is encrypted. Leave blank to keep existing key.
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash me-2"></i>Delete Project
                                </button>
                                <div class="d-flex gap-2">
                                    <a href="project.php?id=<?= $project_id ?>" class="btn btn-outline-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Project Info Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Project Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-muted small">
                            <div class="col-md-6">
                                <p><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($project['created_at'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Project ID:</strong> <?= $project_id ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Delete Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong><?= sanitize_output($project['name']) ?></strong>?</p>
                    <p class="text-muted mb-0">This will permanently delete all documents and generated content. This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="delete_handler.php" method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete_project">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        <input type="hidden" name="redirect" value="dashboard.php">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Delete Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // API Key toggle
        document.getElementById('toggleKey')?.addEventListener('click', function() {
            const input = document.getElementById('api_key');
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>
