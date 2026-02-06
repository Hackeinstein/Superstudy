<?php
/**
 * SuperStudy - User Dashboard
 * 
 * Displays user's projects and allows creating new ones.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_auth();

$user_id = get_user_id();
$username = get_username();

// Handle project deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $project_id = (int)$_POST['project_id'];
        
        // Verify ownership
        $stmt = $mysqli->prepare('SELECT id FROM projects WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $project_id, $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Delete associated documents first (files)
            $doc_stmt = $mysqli->prepare('SELECT file_path FROM documents WHERE project_id = ?');
            $doc_stmt->bind_param('i', $project_id);
            $doc_stmt->execute();
            $docs = $doc_stmt->get_result();
            
            while ($doc = $docs->fetch_assoc()) {
                if (file_exists($doc['file_path'])) {
                    unlink($doc['file_path']);
                }
            }
            $doc_stmt->close();
            
            // Delete project (cascade deletes documents and content)
            $del_stmt = $mysqli->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
            $del_stmt->bind_param('ii', $project_id, $user_id);
            $del_stmt->execute();
            $del_stmt->close();
            
            $success = 'Project deleted successfully.';
        }
        $stmt->close();
    }
}

// Fetch user's projects with document count
$stmt = $mysqli->prepare('
    SELECT p.*, 
           COUNT(DISTINCT d.id) as doc_count,
           COUNT(DISTINCT g.id) as content_count
    FROM projects p
    LEFT JOIN documents d ON p.id = d.project_id
    LEFT JOIN generated_content g ON p.id = g.project_id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$csrf_token = generate_csrf_token();

// AI Provider display names
$provider_names = [
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic (Claude)',
    'google-gemini' => 'Google Gemini',
    'xai-grok' => 'xAI Grok',
    'openrouter' => 'OpenRouter'
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SuperStudy</title>
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
                <span class="text-light me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= sanitize_output($username) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">My Projects</h1>
                <p class="text-muted mb-0">Manage your study projects and AI-generated content</p>
            </div>
            <a href="project.php?action=create" class="btn btn-primary btn-lg">
                <i class="bi bi-plus-lg me-2"></i> New Project
            </a>
        </div>
        
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
        
        <!-- Projects Grid -->
        <?php if (empty($projects)): ?>
            <div class="text-center py-5">
                <div class="empty-state">
                    <i class="bi bi-folder-plus display-1 text-muted"></i>
                    <h3 class="mt-3">No Projects Yet</h3>
                    <p class="text-muted">Create your first project to start generating study materials</p>
                    <a href="project.php?action=create" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i> Create Project
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card project-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="provider-badge badge-<?= $project['ai_provider'] ?>">
                                        <?= sanitize_output($provider_names[$project['ai_provider']] ?? $project['ai_provider']) ?>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="project.php?id=<?= $project['id'] ?>">
                                                    <i class="bi bi-eye me-2"></i> View
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Delete this project and all its content?')">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                                    <button type="submit" name="delete_project" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash me-2"></i> Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <h5 class="card-title mb-2">
                                    <a href="project.php?id=<?= $project['id'] ?>" class="text-decoration-none text-light">
                                        <?= sanitize_output($project['name']) ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?= sanitize_output($project['description'] ?: 'No description') ?>
                                </p>
                                
                                <div class="d-flex gap-3 text-muted small">
                                    <span><i class="bi bi-file-earmark me-1"></i> <?= $project['doc_count'] ?> docs</span>
                                    <span><i class="bi bi-lightning me-1"></i> <?= $project['content_count'] ?> generated</span>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent border-top border-secondary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-cpu me-1"></i>
                                        <?= sanitize_output($project['model_name']) ?>
                                    </small>
                                    <a href="project.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        Open <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Rate Limit Info -->
        <div class="mt-5 pt-4 border-top border-secondary">
            <div class="alert alert-info">
                <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>AI Provider Rate Limits</h6>
                <p class="mb-0 small">
                    Free tiers have usage limits: <strong>Gemini</strong> ~15 requests/min, 
                    <strong>OpenAI</strong> limited messages/day, <strong>Claude</strong> limited usage,
                    <strong>Grok</strong> varies. Use <strong>OpenRouter</strong> for access to multiple models.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
