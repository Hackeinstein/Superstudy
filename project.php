<?php
/**
 * SuperStudy - Project Management Page
 * 
 * Handles project creation, viewing, document upload, and content generation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_auth();

$user_id = get_user_id();
$username = get_username();
$csrf_token = generate_csrf_token();

$error = '';
$success = '';
$project = null;
$documents = [];
$generated_content = [];

// AI Provider options
$providers = [
    'openai' => ['name' => 'OpenAI', 'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo']],
    'anthropic' => ['name' => 'Anthropic (Claude)', 'models' => ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307']],
    'google-gemini' => ['name' => 'Google Gemini', 'models' => ['gemini-1.5-flash', 'gemini-1.5-pro', 'gemini-2.0-flash-exp']],
    'xai-grok' => ['name' => 'xAI Grok', 'models' => ['grok-beta', 'grok-2-1212']],
    'openrouter' => ['name' => 'OpenRouter', 'models' => ['openai/gpt-4o-mini', 'anthropic/claude-3.5-sonnet', 'google/gemini-flash-1.5']]
];

// Determine action: create or view
$action = $_GET['action'] ?? '';
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle project creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $ai_provider = sanitize_input($_POST['ai_provider'] ?? 'openai');
        $model_name = sanitize_input($_POST['model_name'] ?? '');
        $api_key = $_POST['api_key'] ?? '';
        
        if (empty($name) || empty($api_key)) {
            $error = 'Project name and API key are required.';
        } elseif (!array_key_exists($ai_provider, $providers)) {
            $error = 'Invalid AI provider selected.';
        } else {
            // Encrypt API key
            $encrypted_key = encrypt_api_key($api_key);
            $model_name = $model_name ?: DEFAULT_MODELS[$ai_provider];
            
            $stmt = $mysqli->prepare('INSERT INTO projects (user_id, name, description, ai_provider, model_name, api_key) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssss', $user_id, $name, $description, $ai_provider, $model_name, $encrypted_key);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                header('Location: project.php?id=' . $new_id);
                exit;
            } else {
                $error = 'Failed to create project.';
            }
            $stmt->close();
        }
    }
}

// Load project if viewing
if ($project_id > 0) {
    $stmt = $mysqli->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $project_id, $user_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$project) {
        header('Location: dashboard.php');
        exit;
    }
    
    // Load documents
    $doc_stmt = $mysqli->prepare('SELECT * FROM documents WHERE project_id = ? ORDER BY upload_date DESC');
    $doc_stmt->bind_param('i', $project_id);
    $doc_stmt->execute();
    $documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $doc_stmt->close();
    
    // Load generated content
    $content_stmt = $mysqli->prepare('
        SELECT gc.*, d.file_name as document_name 
        FROM generated_content gc
        LEFT JOIN documents d ON gc.document_id = d.id
        WHERE gc.project_id = ?
        ORDER BY gc.generated_at DESC
    ');
    $content_stmt->bind_param('i', $project_id);
    $content_stmt->execute();
    $generated_content = $content_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $content_stmt->close();
}

// Determine page mode
$is_create_mode = ($action === 'create' || !$project);
$page_title = $is_create_mode ? 'Create New Project' : sanitize_output($project['name']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SuperStudy</title>
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
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="bi bi-grid me-1"></i> Dashboard
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
                <li class="breadcrumb-item active"><?= $page_title ?></li>
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

        <?php if ($is_create_mode): ?>
        <!-- CREATE PROJECT FORM -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-folder-plus me-2"></i>Create New Project</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="project.php?action=create">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Project Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="e.g., Biology 101 Notes" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="2"
                                          placeholder="Brief description of this study project"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ai_provider" class="form-label">AI Provider *</label>
                                    <select class="form-select" id="ai_provider" name="ai_provider" required>
                                        <?php foreach ($providers as $key => $provider): ?>
                                            <option value="<?= $key ?>" data-models='<?= json_encode($provider['models']) ?>'>
                                                <?= sanitize_output($provider['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Choose your AI provider</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model_name" class="form-label">Model Name</label>
                                    <div class="input-group">
                                        <select class="form-select" id="model_name" name="model_name" disabled>
                                            <option value="">-- Enter API key first --</option>
                                        </select>
                                        <button class="btn btn-outline-info" type="button" id="fetchModelsBtn" disabled>
                                            <i class="bi bi-arrow-clockwise" id="fetchIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" id="modelStatus">Enter API key to load available models</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="api_key" class="form-label">API Key *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="api_key" name="api_key" 
                                           placeholder="Enter your API key" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleKey">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Your API key is encrypted before storage</div>
                            </div>
                            
                            <div class="alert alert-warning small">
                                <i class="bi bi-shield-exclamation me-1"></i>
                                <strong>Security:</strong> API keys are encrypted in the database. 
                                However, for maximum security, consider using keys with limited permissions.
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="create_project" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-lg me-2"></i> Create Project
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- VIEW PROJECT -->
        <div class="row">
            <!-- Project Info Sidebar -->
            <div class="col-lg-4 mb-4">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Project Info</h5>
                        <span class="badge bg-<?= $project['ai_provider'] === 'openai' ? 'success' : ($project['ai_provider'] === 'anthropic' ? 'warning' : 'info') ?>">
                            <?= sanitize_output($providers[$project['ai_provider']]['name'] ?? $project['ai_provider']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <h4><?= sanitize_output($project['name']) ?></h4>
                        <p class="text-muted"><?= sanitize_output($project['description'] ?: 'No description') ?></p>
                        
                        <div class="mb-3">
                            <small class="text-muted">Model:</small>
                            <div class="badge bg-secondary"><?= sanitize_output($project['model_name']) ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">Created:</small>
                            <div><?= date('M j, Y', strtotime($project['created_at'])) ?></div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 mb-0"><?= count($documents) ?></div>
                                <small class="text-muted">Documents</small>
                            </div>
                            <div class="col-6">
                                <div class="h4 mb-0"><?= count($generated_content) ?></div>
                                <small class="text-muted">Generated</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Document -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Document</h5>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                            
                            <div class="upload-zone mb-3" id="uploadZone">
                                <i class="bi bi-cloud-arrow-up display-4 text-muted"></i>
                                <p class="mb-1">Drag & drop or click to upload</p>
                                <small class="text-muted">PDF, JPG, PNG, TXT (max 10MB)</small>
                                <input type="file" id="fileInput" name="document" 
                                       accept=".pdf,.jpg,.jpeg,.png,.txt" class="d-none">
                            </div>
                            
                            <div id="uploadProgress" class="d-none mb-3">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block" id="uploadStatus">Uploading...</small>
                            </div>
                            
                            <div id="uploadResult" class="d-none"></div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-lg-8">
                <!-- Documents List -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-files me-2"></i>Documents</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-file-earmark-x display-4"></i>
                                <p class="mt-2 mb-0">No documents uploaded yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>File</th>
                                            <th>Type</th>
                                            <th>Uploaded</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td>
                                                    <i class="bi bi-file-earmark-<?= $doc['file_type'] === 'pdf' ? 'pdf' : ($doc['file_type'] === 'txt' ? 'text' : 'image') ?> me-2"></i>
                                                    <?= sanitize_output($doc['file_name']) ?>
                                                </td>
                                                <td><span class="badge bg-secondary"><?= strtoupper($doc['file_type']) ?></span></td>
                                                <td><?= date('M j, Y H:i', strtotime($doc['upload_date'])) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary generate-btn" 
                                                                data-doc-id="<?= $doc['id'] ?>" 
                                                                data-type="summary" title="Generate Summary">
                                                            <i class="bi bi-list-ul"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info generate-btn" 
                                                                data-doc-id="<?= $doc['id'] ?>" 
                                                                data-type="notes" title="Generate Notes">
                                                            <i class="bi bi-journal-text"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning generate-btn" 
                                                                data-doc-id="<?= $doc['id'] ?>" 
                                                                data-type="quiz" title="Generate Quiz">
                                                            <i class="bi bi-question-circle"></i>
                                                        </button>
                                                        <button class="btn btn-outline-success generate-btn" 
                                                                data-doc-id="<?= $doc['id'] ?>" 
                                                                data-type="flashcards" title="Generate Flashcards">
                                                            <i class="bi bi-card-heading"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger delete-doc-btn" 
                                                                data-doc-id="<?= $doc['id'] ?>" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Generated Content -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Generated Content</h5>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary filter-btn active" data-filter="all">All</button>
                            <button type="button" class="btn btn-outline-primary filter-btn" data-filter="summary">Summary</button>
                            <button type="button" class="btn btn-outline-info filter-btn" data-filter="notes">Notes</button>
                            <button type="button" class="btn btn-outline-warning filter-btn" data-filter="quiz">Quiz</button>
                            <button type="button" class="btn btn-outline-success filter-btn" data-filter="flashcards">Flashcards</button>
                        </div>
                    </div>
                    <div class="card-body" id="contentList">
                        <?php if (empty($generated_content)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-lightning display-4"></i>
                                <p class="mt-2 mb-0">No content generated yet</p>
                                <small>Upload a document and click on a generation button</small>
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="contentAccordion">
                                <?php foreach ($generated_content as $idx => $content): ?>
                                    <div class="accordion-item content-item" data-type="<?= $content['type'] ?>">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#content-<?= $content['id'] ?>">
                                                <span class="badge bg-<?= $content['type'] === 'summary' ? 'primary' : ($content['type'] === 'notes' ? 'info' : ($content['type'] === 'quiz' ? 'warning' : 'success')) ?> me-2">
                                                    <?= ucfirst($content['type']) ?>
                                                </span>
                                                <span class="text-muted small">
                                                    <?= sanitize_output($content['document_name'] ?? 'All documents') ?> 
                                                    &bull; <?= date('M j, Y H:i', strtotime($content['generated_at'])) ?>
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="content-<?= $content['id'] ?>" class="accordion-collapse collapse">
                                            <div class="accordion-body">
                                                <?php if ($content['type'] === 'quiz'): ?>
                                                    <?php $questions = parse_quiz_content($content['content_text']); ?>
                                                    <?php if (!empty($questions)): ?>
                                                        <div class="quiz-container">
                                                            <?php foreach ($questions as $q): ?>
                                                                <div class="quiz-question mb-4">
                                                                    <h6>Q<?= $q['number'] ?>: <?= sanitize_output($q['question']) ?></h6>
                                                                    <div class="quiz-options">
                                                                        <?php foreach ($q['options'] as $letter => $opt): ?>
                                                                            <div class="quiz-option <?= $opt['correct'] ? 'correct' : '' ?>" 
                                                                                 onclick="this.classList.toggle('revealed')">
                                                                                <span class="option-letter"><?= $letter ?>)</span>
                                                                                <?= sanitize_output($opt['text']) ?>
                                                                                <?php if ($opt['correct']): ?>
                                                                                    <i class="bi bi-check-circle-fill correct-icon"></i>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <pre class="quiz-raw"><?= sanitize_output($content['content_text']) ?></pre>
                                                    <?php endif; ?>
                                                <?php elseif ($content['type'] === 'flashcards'): ?>
                                                    <?php $cards = parse_flashcard_content($content['content_text']); ?>
                                                    <?php if (!empty($cards)): ?>
                                                        <div class="flashcard-container">
                                                            <?php foreach ($cards as $idx => $card): ?>
                                                                <div class="flashcard" onclick="this.classList.toggle('flipped')">
                                                                    <div class="flashcard-inner">
                                                                        <div class="flashcard-front">
                                                                            <span class="card-number">#<?= $idx + 1 ?></span>
                                                                            <?= sanitize_output($card['front'] ?? '') ?>
                                                                        </div>
                                                                        <div class="flashcard-back">
                                                                            <?= sanitize_output($card['back'] ?? '') ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <pre><?= sanitize_output($content['content_text']) ?></pre>
                                                    <?php endif; ?>
                                                <?php elseif ($content['type'] === 'notes'): ?>
                                                    <div class="notes-content">
                                                        <?= format_notes_content(sanitize_output($content['content_text'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="summary-content">
                                                        <?= nl2br(sanitize_output($content['content_text'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-end mt-3 pt-3 border-top">
                                                    <button class="btn btn-sm btn-outline-danger delete-content-btn" 
                                                            data-content-id="<?= $content['id'] ?>">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Generation Loading Modal -->
    <div class="modal fade" id="generatingModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                    <h5>Generating Content...</h5>
                    <p class="text-muted mb-0" id="generatingType">Creating summary</p>
                    <small class="text-muted">This may take a moment</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Page-specific initialization
        const projectId = <?= $project_id ?: 'null' ?>;
        const csrfToken = '<?= $csrf_token ?>';
        const providers = <?= json_encode($providers) ?>;
    </script>
</body>
</html>
